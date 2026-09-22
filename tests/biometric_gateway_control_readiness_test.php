<?php

define('BASEPATH', __DIR__);

class MY_Model
{
    public $db;
}

require_once __DIR__ . '/../application/models/Biometric_attendance_model.php';

class GatewayReadinessFakeDb
{
    public $tables = array();
    public $fields = array();
    public function table_exists($table) { return in_array($table, $this->tables, true); }
    public function field_exists($field, $table) { return isset($this->fields[$table]) && in_array($field, $this->fields[$table], true); }
}

function gateway_readiness_assert($condition, $message)
{
    if (!$condition) {
        fwrite(STDERR, $message . PHP_EOL);
        exit(1);
    }
}

$model = new Biometric_attendance_model();
$model->db = new GatewayReadinessFakeDb();
$model->db->tables = array(
    'biometric_settings', 'biometric_integrations', 'biometric_devices',
    'biometric_punch_state_mappings', 'biometric_identity_mappings',
    'biometric_gateway_batches', 'biometric_gateway_cursors',
    'biometric_events', 'biometric_attendance_days',
    'biometric_exceptions', 'biometric_reconciliation_actions',
    'biometric_scanner_stations', 'biometric_qr_credentials',
    'biometric_audit_logs', 'student_attendences', 'staff_attendance',
);
$model->db->fields = array(
    'student_attendences' => array('attendance_source', 'biometric_day_id'),
    'staff_attendance' => array('attendance_source', 'biometric_day_id'),
);

gateway_readiness_assert(!$model->isReady(), 'A migration-130-only database must not report ready after the control-plane release.');
$model->db->tables[] = 'biometric_gateway_agents';
gateway_readiness_assert(!$model->isReady(), 'Both migration 132 tables are required.');
$model->db->tables[] = 'biometric_gateway_commands';
gateway_readiness_assert($model->isReady(), 'A complete schema through migration 132 must report ready.');

echo "biometric gateway control readiness tests passed\n";
