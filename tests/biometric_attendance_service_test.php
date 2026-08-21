<?php

define('BASEPATH', __DIR__);

function &get_instance()
{
    $instance = &$GLOBALS['biometric_service_ci'];
    return $instance;
}

require_once __DIR__ . '/../application/libraries/Biometric_event_policy.php';
require_once __DIR__ . '/../application/libraries/Biometric_attendance_service.php';

class BiometricServiceFakeDb
{
    public function trans_begin() { return true; }
    public function trans_commit() { return true; }
    public function trans_rollback() { return true; }
    public function trans_status() { return true; }
    public function where($field, $value = null, $escape = null) { return $this; }
    public function update($table, $data) { return true; }
}

class BiometricServiceFakeCi
{
    public $db;
    public function __construct() { $this->db = new BiometricServiceFakeDb(); }
}

class BiometricServiceFakeModel
{
    private $service;
    public function __construct($service) { $this->service = $service; }
    public function findDeviceBySerial($serial) { return $this->service->fakeDevice($serial); }
    public function findMappingByCode($code, $date = null) { return $this->service->fakeMapping($code); }
    public function findEventByDedupKey($key) { return $this->service->fakeEvent($key); }
    public function getScannerStationByUuid($uuid) { return $this->service->fakeStation($uuid); }
    public function getDevice($id) { return $this->service->fakeDeviceById($id); }
}

class TestableBiometricAttendanceService extends Biometric_attendance_service
{
    public $settings;
    public $devices;
    public $mappings;
    public $events = array();
    public $exceptions = array();
    public $officialProjectionCount = 0;
    private $nextId = 1;

    public function __construct()
    {
        $this->CI = new BiometricServiceFakeCi();
        $GLOBALS['biometric_service_ci'] = $this->CI;
        $this->policy = new Biometric_event_policy();
        $this->model = new BiometricServiceFakeModel($this);
        $this->settings = array(
            'mode' => 'simulation',
            'timezone' => 'Africa/Lagos',
            'max_event_age_days' => 30,
            'student_late_after' => '08:00:00',
            'staff_late_after' => '08:00:00',
            'student_present_type_id' => 1,
            'student_late_type_id' => 3,
            'staff_present_type_id' => 1,
            'staff_late_type_id' => 2,
            'project_students' => 1,
            'project_staff' => 1,
        );
        $this->devices = array(
            'SIM-GATE-001' => array('id' => 1, 'serial_number' => 'SIM-GATE-001', 'integration_id' => null, 'device_type' => 'biometric', 'is_virtual' => 1, 'is_active' => 1),
            'PHYSICAL-001' => array('id' => 2, 'serial_number' => 'PHYSICAL-001', 'integration_id' => 7, 'device_type' => 'biometric', 'is_virtual' => 0, 'is_active' => 1),
            'QR-001' => array('id' => 3, 'serial_number' => 'QR-001', 'integration_id' => null, 'device_type' => 'qr_scanner', 'is_virtual' => 0, 'is_active' => 1),
        );
        $this->mappings = array(
            'STU-001' => array('subject_type' => 'student', 'subject_id' => 101),
            'STF-001' => array('subject_type' => 'staff', 'subject_id' => 201),
        );
    }

    public function isReady() { return true; }
    public function getSettings() { return $this->settings; }
    public function fakeDevice($serial) { return isset($this->devices[$serial]) ? $this->devices[$serial] : null; }
    public function fakeDeviceById($id) { foreach ($this->devices as $device) { if ((int) $device['id'] === (int) $id) return $device; } return null; }
    public function fakeMapping($code) { return isset($this->mappings[$code]) ? $this->mappings[$code] : null; }
    public function fakeEvent($key) { return isset($this->events[$key]) ? $this->events[$key] : null; }
    public function fakeStation($uuid) { return $uuid === 'station-1' ? array('id' => 9, 'station_uuid' => $uuid, 'device_id' => 3, 'is_active' => 1) : null; }
    public function projectionAllowed($scope, $locked, $hasIn) { return $this->shouldProjectOfficial($scope, $locked, $hasIn); }
    public function encryptForTest($token) { return $this->encryptQrToken($token); }
    public function decryptForTest($ciphertext) { return $this->decryptQrToken($ciphertext); }

    public function lookupQrCredential($token)
    {
        return $token === 'valid-qr' ? array('id' => 8, 'credential_uuid' => str_repeat('a', 32), 'subject_type' => 'staff', 'subject_id' => 201) : null;
    }

    protected function subjectExists($type, $id)
    {
        return ($type === 'student' && (int) $id === 101) || ($type === 'staff' && (int) $id === 201);
    }

    protected function subjectSummary($type, $id)
    {
        return array('subject_type' => $type, 'subject_id' => (int) $id, 'name' => $type === 'student' ? 'Test Student' : 'Test Staff');
    }

    protected function punchStateMap($integrationId)
    {
        return array('0' => 'IN', '1' => 'OUT');
    }

    protected function insertEventIdempotent(array $row)
    {
        if (isset($this->events[$row['dedup_key']])) {
            return null;
        }
        $row['id'] = $this->nextId++;
        $this->events[$row['dedup_key']] = $row;
        return $row['id'];
    }

    protected function openException($eventId, $dayId, $code, $message)
    {
        $this->exceptions[] = array('event_id' => $eventId, 'attendance_day_id' => $dayId, 'code' => $code, 'message' => $message);
        return count($this->exceptions);
    }

    protected function recomputeDay($subjectType, $subjectId, $date, $scope, array $settings)
    {
        $matching = array();
        foreach ($this->events as $event) {
            if ($event['processing_status'] === 'accepted' && $event['subject_type'] === $subjectType
                && (int) $event['subject_id'] === (int) $subjectId && $event['attendance_date'] === $date
                && $event['operating_mode'] === $scope) {
                $matching[] = $event;
            }
        }
        $aggregate = $this->policy->aggregateDay($matching, $subjectType === 'student' ? $settings['student_late_after'] : $settings['staff_late_after']);
        $projects = $this->shouldProjectOfficial($scope, false, $aggregate['first_in_at'] !== null);
        if ($projects) {
            $this->officialProjectionCount++;
        }
        return array(
            'id' => 500 + (int) $subjectId,
            'attendance_status' => $aggregate['attendance_status'],
            'projection_status' => $projects ? 'projected' : 'not_applicable',
        );
    }
}

function service_assert($condition, $message)
{
    if (!$condition) {
        fwrite(STDERR, $message . PHP_EOL);
        exit(1);
    }
}

function service_event($id, $person, $serial, $state)
{
    $now = new DateTimeImmutable('now', new DateTimeZone('Africa/Lagos'));
    return array(
        'external_event_id' => $id,
        'person_code' => $person,
        'occurred_at' => $now->format(DateTime::ATOM),
        'device_serial' => $serial,
        'punch_state' => $state,
        'verification_method' => 'face',
    );
}

$service = new TestableBiometricAttendanceService();
$simOne = service_event('sim-1', 'STU-001', 'SIM-GATE-001', '0');
$studentIn = $service->ingestEvent($simOne, array('source' => 'simulator'));
service_assert($studentIn['status'] === 'accepted' && $studentIn['projection_status'] === 'not_applicable', 'Simulation student IN must be processed but never officially projected.');
service_assert($service->officialProjectionCount === 0, 'Simulation must perform zero official writes.');

$duplicate = $service->ingestEvent($simOne, array('source' => 'simulator'));
service_assert($duplicate['status'] === 'duplicate', 'Repeated external event ID must be idempotent.');

$conflicting = $simOne;
$conflicting['punch_state'] = '1';
$conflict = $service->ingestEvent($conflicting, array('source' => 'simulator'));
service_assert($conflict['status'] === 'rejected' && $conflict['code'] === 'EXTERNAL_EVENT_ID_CONFLICT', 'Reusing an external ID for different data must be rejected and reconciled.');

$unknown = $service->ingestEvent(service_event('sim-2', 'UNKNOWN', 'SIM-GATE-001', '0'), array('source' => 'simulator'));
service_assert($unknown['status'] === 'quarantined' && $unknown['code'] === 'UNKNOWN_PERSON', 'Unknown person code must be quarantined.');

$badState = $service->ingestEvent(service_event('sim-3', 'STU-001', 'SIM-GATE-001', '9'), array('source' => 'simulator'));
service_assert($badState['status'] === 'quarantined' && $badState['code'] === 'UNKNOWN_PUNCH_STATE', 'Unknown single-terminal punch state must be quarantined.');

$service->settings['mode'] = 'shadow';
$staffShadow = $service->ingestEvent(service_event('shadow-1', 'STF-001', 'PHYSICAL-001', '0'), array(
    'source' => 'gateway',
    'integration' => array('id' => 7, 'is_active' => 1),
));
service_assert($staffShadow['status'] === 'accepted' && $service->officialProjectionCount === 0, 'Shadow staff event must not write official attendance.');

$service->settings['mode'] = 'live';
$simulatorInLive = $service->ingestEvent(service_event('live-sim', 'STU-001', 'SIM-GATE-001', '0'), array('source' => 'simulator'));
service_assert($simulatorInLive['status'] === 'rejected', 'Live mode must reject the Test Terminal.');
$staffLive = $service->ingestEvent(service_event('live-1', 'STF-001', 'PHYSICAL-001', '0'), array(
    'source' => 'gateway',
    'integration' => array('id' => 7, 'is_active' => 1),
));
service_assert($staffLive['status'] === 'accepted' && $staffLive['projection_status'] === 'projected', 'Live staff IN must reach official projection.');
service_assert($service->officialProjectionCount === 1, 'Only the live event should project officially.');

service_assert(!$service->projectionAllowed('simulation', false, true), 'Projection gate must reject simulation scope.');
service_assert(!$service->projectionAllowed('shadow', false, true), 'Projection gate must reject shadow scope.');
service_assert(!$service->projectionAllowed('live', true, true), 'Projection gate must respect a manual lock.');
service_assert($service->projectionAllowed('live', false, true), 'Projection gate may accept unlocked live check-in.');

$service->settings['mode'] = 'simulation';
$qr = $service->scanQrCredential('valid-qr', 'station-1', 'OUT', 1);
service_assert($qr['success'] === true && $qr['direction'] === 'OUT' && $qr['subject']['subject_type'] === 'staff', 'Trusted QR must enter the same manual-direction event workflow.');
$badQr = $service->scanQrCredential('invalid', 'station-1', 'IN', 1);
service_assert($badQr['success'] === false, 'Invalid QR token must be rejected.');

$previousQrKey = getenv('BIOMETRIC_QR_ENCRYPTION_KEY');
putenv('BIOMETRIC_QR_ENCRYPTION_KEY=too-short');
service_assert(!$service->qrEncryptionReady(), 'QR encryption must report unavailable for a short key.');
service_assert($service->encryptForTest('SLQR1.test.secret') === null, 'QR encryption must fail closed without a strong key.');

putenv('BIOMETRIC_QR_ENCRYPTION_KEY=unit-test-key-that-is-longer-than-32-characters');
service_assert($service->qrEncryptionReady(), 'QR encryption must report ready with OpenSSL and a strong key.');
$plainQr = 'SLQR1.' . str_repeat('b', 32) . '.' . str_repeat('c', 43);
$encryptedQr = $service->encryptForTest($plainQr);
service_assert(is_string($encryptedQr) && $encryptedQr !== $plainQr, 'QR plaintext must be encrypted before storage.');
service_assert($service->decryptForTest($encryptedQr) === $plainQr, 'Encrypted QR credentials must decrypt for authorized reprinting.');
$rawQr = base64_decode($encryptedQr, true);
$rawQr[20] = chr(ord($rawQr[20]) ^ 1);
service_assert($service->decryptForTest(base64_encode($rawQr)) === null, 'Tampered QR ciphertext must fail authentication.');
putenv('BIOMETRIC_QR_ENCRYPTION_KEY=a-different-unit-test-key-over-32-characters');
service_assert($service->decryptForTest($encryptedQr) === null, 'QR ciphertext must not decrypt with a different environment key.');
if ($previousQrKey === false) {
    putenv('BIOMETRIC_QR_ENCRYPTION_KEY');
} else {
    putenv('BIOMETRIC_QR_ENCRYPTION_KEY=' . $previousQrKey);
}

echo "biometric attendance service tests passed\n";
