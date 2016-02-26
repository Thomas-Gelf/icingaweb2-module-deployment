<?php

use Icinga\Module\Deployment\ApiController;
use Icinga\Module\Monitoring\Command\Object\ScheduleHostDowntimeCommand;
use Icinga\Module\Monitoring\Command\Object\DeleteDowntimeCommand;

class Deployment_DowntimeController extends ApiController
{
    public function removeAction()
    {
        if (! $this->getRequest()->isPost()) {
            return $this->fail('downtime/remove requires a POST request');
        }

        $hostname = $this->params->get('host');
        $comment  = $this->params->get('comment');

        if (! $hostname) {
            return $this->fail('Parameter "host" is required');
        }

        if (! $comment) {
            return $this->fail('Parameter "comment" is required');
        }

        $columns = array(
            'host'        => 'host_name',
            'service'     => 'service_description',
            'objecttype'  => 'object_type',
            'internal_id' => 'downtime_internal_id'
        );

        $downtimes = $this->backend()
            ->select()
            ->from('downtime', $columns)
            ->where('host_name', $hostname)
            ->where('downtime_comment', $comment)
            ->getQuery()
            ->fetchAll();

        foreach ($downtimes as $downtime) {
            if ($downtime->objecttype === 'service') {
                $object = $this->getServiceObject($downtime->host, $downtime->service);
            } else {
                $object = $this->getHostObject($downtime->host);
            }
            $cmd = new DeleteDowntimeCommand();
            $cmd->setObject($object)
                ->setDowntimeId($downtime->internal_id);
            $this->getTransport()->send($cmd);
        }

        $this->view->cnt_removed = count($downtimes);
        $this->view->removed_downtimes = $downtimes;

        if ($this->isJson) {
            $this->render('remove-json');
        }
    }

    public function scheduleAction()
    {
        if (! $this->getRequest()->isPost()) {
            return $this->fail('downtime/schedule requires a POST request');
        }

        $hostname = $this->params->get('host');
        $host = $this->getHostObject($hostname);

        // Start is NOW if not set
        if (null === ($start = $this->params->get('start'))) {
            $start = time();
        } else {
            $start = ctype_digit($start) ? $start : strtotime($start);
        }

        // End is NOW + 15 minutes if not set
        if (null === ($end = $this->params->get('end'))) {
            $end = $start + (15 * 60);
        } else {
            $end = ctype_digit($end) ? $end : strtotime($end);
        }

        // Duration is 20 minutes if not set
        $duration = $this->params->get('duration', 20 * 60);

        $comment = $this->params->get('comment', 'System went down for reboot');

        $cmd = new ScheduleHostDowntimeCommand();
        $cmd->setObject($host)
            ->setComment($comment)
            ->setAuthor($hostname)
            ->setStart($start)
            ->setEnd($end)
            ->setDuration($duration)
            ->setFixed(false)
            ;

        $this->getTransport()->send($cmd);

        $cmd = new ScheduleHostDowntimeCommand();
        $cmd->setForAllServices()
            ->setObject($host)
            ->setComment($comment)
            ->setAuthor($hostname)
            ->setStart($start)
            ->setEnd($end)
            ->setDuration($duration)
            ->setFixed(false)
            ;

        $this->getTransport()->send($cmd);

        $this->view->hostname = $hostname;
        $this->view->duration = $duration;
        $this->view->comment  = $comment;
        $this->view->start = $start;
        $this->view->end   = $end;
        if ($this->isJson) {
            $this->render('schedule-json');
        }
    }
}
