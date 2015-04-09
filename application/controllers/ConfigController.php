<?php

use Icinga\Web\Controller;

class Deployment_ConfigController extends Controller
{
    public function indexAction()
    {
        $this->view->tabs = $this->Module()->getConfigTabs()->activate('config');
        $hintHtml = $this->view->escape($this->translate(
            'Configuration form is still missing in this prototype.'
          . ' Please configure an authentication token in %s as follows:'
        ));
        $this->view->escapedHint = sprintf(
            $hintHtml,
            '<b>' . $this->Config()->getConfigFile() . '</b>'
        );
    }
}
