<?php

define('BASEPATH', __DIR__);
require_once __DIR__ . '/../application/libraries/Biometric_attendance_service.php';

function gateway_control_assert($condition, $message)
{
    if (!$condition) {
        fwrite(STDERR, $message . PHP_EOL);
        exit(1);
    }
}

class GatewayControlContractService extends Biometric_attendance_service
{
    public function __construct() {}
    public function sanitizeStatusForTest($value) { return $this->sanitizeGatewayStatus($value); }
    public function sanitizeResultForTest($value) { return $this->sanitizeGatewayResult($value); }
    public function gatewayIdForTest($value) { return $this->validGatewayId($value); }
    public function atomForTest($value) { return $this->atomDateToDatabase($value); }
}

$service = new GatewayControlContractService();
$status = array(
    'can_accept_command' => true,
    'provider_reachable' => true,
    'last_sync_ok' => false,
    'last_sync_at' => '2026-08-13T09:30:00+00:00',
    'queue' => array('pending' => 2, 'retry' => 1, 'dead' => 0),
    'cursor' => 'cursor-10',
    'last_error' => 'Temporary provider outage',
);
gateway_control_assert($service->sanitizeStatusForTest($status) === $status, 'The fixed gateway heartbeat contract must be accepted unchanged.');
$status['can_accept_command'] = 1;
gateway_control_assert($service->sanitizeStatusForTest($status) === null, 'can_accept_command must be a real JSON boolean.');
unset($status['can_accept_command']);
$status['can_accept_command'] = false;
$status['unexpected'] = 'no';
gateway_control_assert($service->sanitizeStatusForTest($status) === null, 'Unknown heartbeat fields must fail closed.');

gateway_control_assert($service->gatewayIdForTest('school-main-gateway-01'), 'A normal stable gateway ID must be accepted.');
gateway_control_assert(!$service->gatewayIdForTest('../bad gateway'), 'Unsafe gateway IDs must be rejected.');
gateway_control_assert($service->atomForTest('2026-08-13T09:30:00Z') === '2026-08-13 09:30:00', 'UTC ISO timestamps must normalize for storage.');
gateway_control_assert($service->atomForTest('2026-08-13T10:30:00+01:00') === null, 'Heartbeat timestamps must explicitly be UTC.');

$result = array(
    'summary' => array('ok' => true, 'message' => 'Connection succeeded'),
    'provider' => array('reachable' => true),
    'schoollift' => array('reachable' => true),
    'queue' => array('pending' => 0, 'retry' => 0, 'dead' => 0),
    'retried' => 0,
    'message' => 'Done',
);
gateway_control_assert($service->sanitizeResultForTest($result) === $result, 'The agreed bounded command result contract must be accepted.');
gateway_control_assert($service->sanitizeResultForTest(array('shell_output' => 'no')) === null, 'Arbitrary result fields must be rejected.');
gateway_control_assert($service->sanitizeResultForTest(array('provider' => array('password' => 'secret'))) === null, 'Secret-shaped result keys must be rejected.');
gateway_control_assert($service->sanitizeResultForTest(array('message' => str_repeat('x', 1001))) === null, 'Oversized result strings must be rejected.');
$redacted = $service->sanitizeResultForTest(array('message' => 'Bearer dangerous.token password=hunter2'));
gateway_control_assert(strpos($redacted['message'], 'dangerous.token') === false && strpos($redacted['message'], 'hunter2') === false, 'Gateway results must redact common credential patterns before storage.');

$source = file_get_contents(__DIR__ . '/../application/libraries/Biometric_attendance_service.php');
gateway_control_assert(strpos($source, "array('connection_test', 'sync_now', 'retry_failed')") !== false, 'The command vocabulary must be a hard-coded allowlist.');
gateway_control_assert(strpos($source, "where('status', 'queued')->where('expires_at <=', \$now)") !== false, 'Only unclaimed commands may expire automatically.');
gateway_control_assert(strpos($source, "\$safeStatus['can_accept_command'] === true") !== false, 'A finishing heartbeat must not claim another command.');
gateway_control_assert(strpos($source, "AND `gateway_id` = ? AND `status` = 'claimed'") !== false, 'Poll must replay an outstanding claimed command before claiming new work.');

echo "biometric gateway control contract tests passed\n";
