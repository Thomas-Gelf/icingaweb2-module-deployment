<?php

use Icinga\Module\Deployment\ApiController;
use Icinga\Module\Monitoring\Command\Object\ScheduleHostCheckCommand;
use Icinga\Module\Monitoring\Command\Object\ScheduleServiceCheckCommand;

class Deployment_HealthController extends ApiController
{
    protected $host;

    protected $services;

    protected $schedule = array();

    public function checkAction()
    {
        $hostname = $this->params->get('host');
        if (empty($hostname)) {
            return $this->fail('Parameter "hostname" is required');
        }
        $host = $this->host($hostname);
        $services = $this->services($hostname);

        if (empty($host)) {
            return $this->fail('No such host found: %s', $hostname);
        }

        if (count($services) === 0) {
            return $this->fail('Host %s has no services', $hostname);
        }

        $host_ok = $this->isOk($host);
        $services_ok = true;
        foreach ($services as $service) {
            if (! $this->isOk($service)) {
                $services_ok = false;
            }
        }

        $this->view->healthy = $host_ok && $services_ok;
        $this->view->services = $services;

        if ($this->params->get('checkNow')) {
            if (! $this->getRequest()->isPost()) {
                return $this->fail('checkNow requires a POST request');
            }
            if (! $this->recheckNow($hostname)) {
                return $this->fail('Recheck did not finish in time');
            }
        }

        if ($this->isJson) {
            $this->render('check-json');
        }
    }

    protected function recheckNow($hostname)
    {
        $now = time();
        $timeout = 15;

        $this->rememberSchedule($hostname);

        $host = $this->getHostObject($hostname);
        $cmd = new ScheduleHostCheckCommand();
        $cmd->setForced()
            ->setOfAllServices()
            ->setObject(clone($host))
            ->setCheckTime($now - 1);
        $this->getTransport()->send($cmd);

        $cmd = new ScheduleHostCheckCommand();
        $cmd->setForced()
            ->setObject($host)
            ->setCheckTime($now - 1);
        $this->getTransport()->send($cmd);

        $pending = true;
        while ($pending && time() < ($now + $timeout)) {
            sleep(1);
            $pending = ! $this->allCheckedSince($now, $hostname);
        }

        $this->restoreSchedule($hostname);

        return ! $pending;
    }

    protected function rememberSchedule($hostname)
    {
        $this->schedule[$hostname] = array();
        $host = $this->host($hostname);
        $this->schedule[$hostname]['host'] = $host->next_check;
        $services = array();
        foreach ($this->services($hostname) as $service) {
            $services[$service->service] = $service->next_check;
        }
        $this->schedule[$hostname]['services'] = $services;
    }

    protected function restoreSchedule($hostname)
    {
        $host = $this->getHostObject($hostname);
        $cmd = new ScheduleHostCheckCommand();
        $cmd->setObject($host)
            ->setCheckTime($this->schedule[$hostname]['host']);

        $this->getTransport()->send($cmd);
        foreach ($this->schedule[$hostname]['services'] as $service => $time) {
            $service = $this->getServiceObject($hostname, $service);
            if ($time < time()) {
                continue;
            }
            $cmd = new ScheduleServiceCheckCommand();
            $cmd->setObject($service)
                ->setCheckTime($time);
            $this->getTransport()->send($cmd);
        }
    }

    protected function allCheckedSince($now, $hostname)
    {
        $host = $this->fetchHost($hostname);
        if ($host->last_check < $now) {
            return false;
        }

        foreach ($this->fetchServices($hostname) as $service) {
            if ($service->last_check < $now) {
                return false;
            }
        }

        return true;
    }

    protected function host($hostname)
    {
        if ($this->host === null) {
            $this->fetchHost($hostname);
        }

        return $this->host;
    }

    protected function fetchHost($hostname)
    {
        $columns = array(
            'host'         => 'host_name',
            'state'        => 'host_state',
            'problem'      => 'host_problem',
            'output'       => 'host_output',
            'handled'      => 'host_handled',
            'in_downtime'  => 'host_in_downtime',
            'acknowledged' => 'host_acknowledged',
            'last_check'   => 'host_last_check',
            'next_check'   => 'host_next_check',
        );

        $this->host = $this->view->host = $this->backend()->select()
            ->from('hostStatus', $columns)
            ->where('host_name', $hostname)
            ->getQuery()
            ->fetchRow();

        return $this->host;
    }

    protected function services($hostname)
    {
        if ($this->services === null) {
            $this->fetchServices($hostname);
        }

        return $this->services;
    }

    protected function fetchServices($hostname)
    {
        $columns = array(
            'host'         => 'host_name',
            'service'      => 'service_description',
            'state'        => 'service_state',
            'problem'      => 'service_problem',
            'output'       => 'service_output',
            'handled'      => 'service_handled',
            'in_downtime'  => 'service_in_downtime',
            'acknowledged' => 'service_acknowledged',
            'last_check'   => 'service_last_check',
            'next_check'   => 'service_next_check',
        );

        $this->services = $this->view->services = $this->backend()->select()
            ->from('serviceStatus', $columns)
            ->where('host_name', $hostname)
            ->getQuery()
            ->fetchAll();

        return $this->services;
    }
}
