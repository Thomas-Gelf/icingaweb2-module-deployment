<?php

namespace Icinga\Module\Deployment\Controllers;

use Icinga\Module\Deployment\ApiController;
use Icinga\Module\Deployment\Ido;

class StatusController extends ApiController
{
    public function hostsAction()
    {
        $ido = new Ido($this->backend());
        try {
            $this->view->hosts = $ido->getHostsWithServiceSummary();
            $this->view->success = true;
        } catch (Exception $e) {
            $this->view->error = $e->getMessage();
            $this->view->success = true;
        }

        if ($this->isJson) {
            $this->render('hosts-json');
        }
    }
}
