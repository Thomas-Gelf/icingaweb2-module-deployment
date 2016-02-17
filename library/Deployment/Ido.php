<?php

namespace Icinga\Module\Deployment;

use Icinga\Module\Monitoring\Backend\Ido\IdoBackend;
use Zend_Db_Expr;

class Ido
{
    protected $ido;

    protected $db;

    public function __construct(IdoBackend $ido)
    {
        $this->ido = $ido;
        $this->db  = $ido->getResource()->getDbAdapter();
    }

    public function getHostsWithServiceSummary()
    {
        $query = $this->db->select()->from(
            array('hstate' => new Zend_Db_Expr('(' . $this->selectHosts() . ')'))
        )->join(
            array('ssum' => new Zend_Db_Expr('(' . $this->selectHostServiceSummary() . ')')),
            'hstate.host_object_id = ssum.host_object_id'
        );

        return $this->db->fetchAll($query);
    }

    protected function selectHosts()
    {
        return $this->db->select()->from(
            array('o' => 'icinga_objects'),
            array(
                'host_object_id' => 'o.object_id',
                'host_name'      => 'o.name1',
                'host_state'     => 'hs.current_state',
            )
        )->join(
            array('hs' => 'icinga_hoststatus'),
            'hs.host_object_id = o.object_id AND o.is_active = 1',
            array()
        );
    }

    protected function selectHostServiceSummary()
    {
        $state   = 'CASE WHEN ss.has_been_checked = 0 OR ss.has_been_checked IS NULL THEN 99 ELSE ss.current_state END';
        $handled = 'CASE WHEN (ss.problem_has_been_acknowledged + ss.scheduled_downtime_depth) > 0 THEN 1 ELSE 0 END';

        return $this->db->select()->from(
            array('o' => 'icinga_objects'),
            array(
                'host_object_id'   => 's.host_object_id',
                'service_problems' => 'SUM(CASE WHEN COALESCE(ss.current_state, 0) = 0 THEN 0 ELSE 1 END)',
                'services_critical'           => 'SUM(CASE WHEN ' . $state . ' = 2 THEN 1 ELSE 0 END)',
                'services_critical_handled'   => 'SUM(CASE WHEN ' . $state . ' = 2 AND ' . $handled . ' = 1 THEN 1 ELSE 0 END)',
                'services_critical_unhandled' => 'SUM(CASE WHEN ' . $state . ' = 2 AND ' . $handled . ' = 0 THEN 1 ELSE 0 END)',
                'services_ok'                 => 'SUM(CASE WHEN ' . $state . ' = 0 THEN 1 ELSE 0 END)',
                'services_pending'            => 'SUM(CASE WHEN ' . $state . ' = 99 THEN 1 ELSE 0 END)',
                'services_total'              => 'SUM(1)',
                'services_unknown'            => 'SUM(CASE WHEN ' . $state . ' = 3 THEN 1 ELSE 0 END)',
                'services_unknown_handled'    => 'SUM(CASE WHEN ' . $state . ' = 3 AND ' . $handled . ' = 1 THEN 1 ELSE 0 END)',
                'services_unknown_unhandled'  => 'SUM(CASE WHEN ' . $state . ' = 3 AND ' . $handled . ' = 0 THEN 1 ELSE 0 END)',
                'services_warning'            => 'SUM(CASE WHEN ' . $state . ' = 1 THEN 1 ELSE 0 END)',
                'services_warning_handled'    => 'SUM(CASE WHEN ' . $state . ' = 1 AND ' . $handled . ' = 1 THEN 1 ELSE 0 END)',
                'services_warning_unhandled'  => 'SUM(CASE WHEN ' . $state . ' = 1 AND ' . $handled . ' = 0 THEN 1 ELSE 0 END)',
            )
        )->join(
            array('s' => 'icinga_services'),
            's.service_object_id = o.object_id AND o.is_active = 1',
            array()
        )->joinLeft(
            array('ss' => 'icinga_servicestatus'),
            'ss.service_object_id = s.service_object_id',
            array()
        )->group('s.host_object_id');
    }
}
