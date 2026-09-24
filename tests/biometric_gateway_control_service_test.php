<?php

define('BASEPATH', __DIR__);

function &get_instance()
{
    $instance = &$GLOBALS['gateway_control_ci'];
    return $instance;
}

require_once __DIR__ . '/../application/libraries/Biometric_attendance_service.php';

class GatewayControlFakeResult
{
    private $row;
    public function __construct($row) { $this->row = $row; }
    public function row_array() { return $this->row; }
}

class GatewayControlFakeDb
{
    public $claimedRow = null;
    public $queuedRow = null;
    public $affected = 1;
    public $queries = array();
    public $updates = array();
    public $inserts = array();
    public $commits = 0;
    public $rollbacks = 0;

    public function table_exists($table) { return in_array($table, array('biometric_gateway_agents', 'biometric_gateway_commands', 'biometric_audit_logs'), true); }
    public function where($field, $value = null, $escape = null) { return $this; }
    public function where_in($field, $values) { return $this; }
    public function update($table, $data) { $this->updates[] = array($table, $data); return true; }
    public function insert($table, $data) { $this->inserts[] = array($table, $data); return true; }
    public function insert_id() { return 41; }
    public function affected_rows() { return $this->affected; }
    public function trans_begin() { return true; }
    public function trans_commit() { $this->commits++; return true; }
    public function trans_rollback() { $this->rollbacks++; return true; }
    public function query($sql, $parameters = array())
    {
        $this->queries[] = array($sql, $parameters);
        if (strpos($sql, "`status` = 'claimed'") !== false) {
            return new GatewayControlFakeResult($this->claimedRow);
        }
        if (strpos($sql, "`status` = 'queued'") !== false) {
            return new GatewayControlFakeResult($this->queuedRow);
        }
        return new GatewayControlFakeResult(null);
    }
}

class GatewayControlFakeModel
{
    public $agent = null;
    public $command = null;
    public function findGatewayAgentByGatewayId($gatewayId) { return $this->agent; }
    public function getGatewayCommandByUuid($uuid) { return $this->command; }
    public function getIntegration($id) { return array('id' => (int) $id, 'is_active' => 1); }
}

class GatewayControlServiceUnderTest extends Biometric_attendance_service
{
    public $fakeDb;
    public $fakeModel;
    public function __construct()
    {
        $this->fakeDb = new GatewayControlFakeDb();
        $this->fakeModel = new GatewayControlFakeModel();
        $this->CI = new stdClass();
        $this->CI->db = $this->fakeDb;
        $this->model = $this->fakeModel;
        $GLOBALS['gateway_control_ci'] = $this->CI;
    }
    public function claimForTest($integrationId, $gatewayId, $now)
    {
        return $this->claimGatewayCommand($integrationId, $gatewayId, $now);
    }
}

function gateway_service_assert($condition, $message)
{
    if (!$condition) {
        fwrite(STDERR, $message . PHP_EOL);
        exit(1);
    }
}

function gateway_heartbeat($canAccept)
{
    return array(
        'gateway_id' => 'school-main-gateway-01',
        'gateway_version' => '1.0.0',
        'status' => array(
            'can_accept_command' => $canAccept,
            'provider_reachable' => true,
            'last_sync_ok' => true,
            'last_sync_at' => '2026-08-13T09:30:00+00:00',
            'queue' => array('pending' => 0, 'retry' => 0, 'dead' => 0),
            'cursor' => 'cursor-10',
            'last_error' => null,
        ),
    );
}

$service = new GatewayControlServiceUnderTest();
$service->fakeModel->agent = array('id' => 9, 'integration_id' => 2, 'gateway_id' => 'school-main-gateway-01');
$ownership = $service->pollGateway(array('id' => 1, 'is_active' => 1), gateway_heartbeat(false));
gateway_service_assert($ownership['http_status'] === 409, 'A gateway ID must not move between integrations.');

$service = new GatewayControlServiceUnderTest();
$heartbeatOnly = $service->pollGateway(array('id' => 1, 'is_active' => 1), gateway_heartbeat(false));
gateway_service_assert($heartbeatOnly['success'] && $heartbeatOnly['command'] === null, 'A finishing heartbeat must persist status without claiming work.');
gateway_service_assert(count($service->fakeDb->queries) === 0, 'can_accept_command=false must not execute a command claim query.');

$claimed = array(
    'id' => 11, 'command_uuid' => str_repeat('a', 32), 'integration_id' => 1,
    'gateway_id' => 'school-main-gateway-01', 'command_type' => 'sync_now',
    'status' => 'claimed', 'expires_at' => '2026-08-13 10:00:00',
);
$service = new GatewayControlServiceUnderTest();
$service->fakeDb->claimedRow = $claimed;
$replayedClaim = $service->claimForTest(1, 'school-main-gateway-01', '2026-08-13 10:05:00');
gateway_service_assert($replayedClaim['command_uuid'] === str_repeat('a', 32), 'A claimed command must be re-delivered after a lost response.');
gateway_service_assert(count($service->fakeDb->queries) === 1, 'An outstanding claimed command must prevent a second queued command claim.');

$queued = $claimed;
$queued['id'] = 12;
$queued['command_uuid'] = str_repeat('b', 32);
$queued['gateway_id'] = null;
$queued['command_type'] = 'connection_test';
$queued['status'] = 'queued';
$service = new GatewayControlServiceUnderTest();
$service->fakeDb->queuedRow = $queued;
$newClaim = $service->claimForTest(1, 'school-main-gateway-01', '2026-08-13 09:40:00');
gateway_service_assert($newClaim['command_uuid'] === str_repeat('b', 32), 'The oldest eligible queued command must be claimable.');
gateway_service_assert($service->fakeDb->commits === 1 && $service->fakeDb->rollbacks === 0, 'A successful claim must commit atomically.');

$service = new GatewayControlServiceUnderTest();
$service->fakeDb->queuedRow = $queued;
$service->fakeDb->affected = 0;
$lostRace = $service->claimForTest(1, 'school-main-gateway-01', '2026-08-13 09:40:00');
gateway_service_assert($lostRace === null && $service->fakeDb->rollbacks === 1, 'A claim that loses its compare-and-set race must roll back without returning work.');

$finished = $claimed;
$finished['status'] = 'succeeded';
$finished['result_json'] = json_encode(array('message' => 'Done'));
$service = new GatewayControlServiceUnderTest();
$service->fakeModel->command = $finished;
$sameResult = array(
    'gateway_id' => 'school-main-gateway-01', 'command_uuid' => str_repeat('a', 32),
    'status' => 'succeeded', 'result' => array('message' => 'Done'),
);
$replayedResult = $service->completeGatewayCommand(array('id' => 1, 'is_active' => 1), $sameResult);
gateway_service_assert($replayedResult['http_status'] === 200 && $replayedResult['replayed'] === true, 'An identical durable result replay must be acknowledged idempotently.');
$sameResult['result']['message'] = 'Different';
$conflictingResult = $service->completeGatewayCommand(array('id' => 1, 'is_active' => 1), $sameResult);
gateway_service_assert($conflictingResult['http_status'] === 409, 'A conflicting replay must not replace the first result.');

$service->fakeModel->command = $claimed;
$foreignGateway = $sameResult;
$foreignGateway['result']['message'] = 'Done';
$foreignGateway['gateway_id'] = 'another-school-gateway';
$wrongOwner = $service->completeGatewayCommand(array('id' => 1, 'is_active' => 1), $foreignGateway);
gateway_service_assert($wrongOwner['http_status'] === 409, 'A different gateway must not complete a command it does not own.');
$wrongIntegration = $service->completeGatewayCommand(array('id' => 2, 'is_active' => 1), $sameResult);
gateway_service_assert($wrongIntegration['http_status'] === 404, 'A bearer token from another integration must not see or complete the command.');

echo "biometric gateway control service tests passed\n";
