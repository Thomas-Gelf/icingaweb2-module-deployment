<?php

namespace Icinga\Module\Deployment;

use Icinga\Web\Controller;
use Icinga\Application\Config;
use Icinga\Module\Monitoring\Backend;
use Icinga\Module\Monitoring\Command\Transport\CommandTransport;
use Icinga\Module\Monitoring\Object\Host;
use Icinga\Module\Monitoring\Object\Service;
use Icinga\Exception\IcingaException;
use Icinga\Exception\AuthenticationException;

class ApiController extends Controller
{
    protected $requiresAuthentication = false;

    protected $backend;

    private $halfBakedTransport;

    protected $isJson;

    public function init()
    {
        $configuredToken = $this->Config()->get('auth', 'token');
        if (empty($configuredToken)) {
            $this->redirectNow('deployment/config');
        }

        if ($this->params->get('token') !== $configuredToken) {
            throw new AuthenticationException('Got no valid authentication token');
        }

        $this->checkJson();
    }

    protected function checkJson()
    {
        $this->isJson = (bool) $this->params->get('json');

        if ($this->isJson) {
            if ($this->params->get('pretty')) {
                if (version_compare(PHP_VERSION, '5.4.0') >= 0) {
                    $this->view->pretty = JSON_PRETTY_PRINT;
                }
            } else {
                $this->_helper->layout->disableLayout();
                header('Content-type: text/json');
            }
        }
    }

    protected function getHostObject($hostname)
    {
        $backend = $this->backend();
        $found = $backend->select()->from('hostStatus')->where('host_name', $hostname)->count();
        if ($found !== 1) {
            return $this->fail('No such host found: %s', $hostname);
        }
        $host = new Host($backend, $hostname);
        return $host;
    }

    protected function getServiceObject($hostname, $servicename)
    {
        return new Service($this->backend(), $hostname, $servicename);
    }

    protected function getTransport()
    {
        if ($this->halfBakedTransport === null) {
            // This is a completely silly object :( Every call to send
            // creates many new objects...
            $this->halfBakedTransport = new CommandTransport();
        }

        return $this->halfBakedTransport;
    }

    protected function isOk($row)
    {
        $ok = isset($row->service) ? 1 : 0;
        return (int) $row->state <= $ok
            || (int) $row->acknowledged === 1
            || (int) $row->in_downtime === 1;
    }

    protected function fail()
    {
        $args = func_get_args();
        $msg = vsprintf(array_shift($args), $args);
        if ($this->isJson) {
            $this->view->error = $msg;
            $this->render('jsonerror', null, true);
            exit;
        } else {
            throw new IcingaException($msg);
        }
    }

    protected function backend()
    {
        if ($this->backend === null) {
            $this->backend = Backend::createBackend($this->params->get('backend'));
        }

        return $this->backend;
    }
}
