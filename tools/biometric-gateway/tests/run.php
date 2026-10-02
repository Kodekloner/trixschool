<?php

declare(strict_types=1);

use SchoolLift\BiometricGateway\Clock;
use SchoolLift\BiometricGateway\Config;
use SchoolLift\BiometricGateway\CurlHttpTransport;
use SchoolLift\BiometricGateway\DirectorySynchronizer;
use SchoolLift\BiometricGateway\EventNormalizer;
use SchoolLift\BiometricGateway\GatewayControl;
use SchoolLift\BiometricGateway\GatewayRunner;
use SchoolLift\BiometricGateway\GatewayStore;
use SchoolLift\BiometricGateway\HttpTransport;
use SchoolLift\BiometricGateway\JsonLogger;
use SchoolLift\BiometricGateway\SchoolLiftClient;
use SchoolLift\BiometricGateway\ZkBioClient;
use SchoolLift\BiometricSandbox\ScenarioFactory;

require_once dirname(__DIR__) . '/bootstrap.php';
require_once dirname(__DIR__, 2) . '/biometric-sandbox/bootstrap.php';

final class MutableClock implements Clock
{
    private DateTimeImmutable $time;

    public function __construct(DateTimeImmutable $time)
    {
        $this->time = $time;
    }

    public function now(): DateTimeImmutable
    {
        return $this->time;
    }

    public function advance(int $seconds): void
    {
        $this->time = $this->time->modify('+' . $seconds . ' seconds');
    }
}

final class DirectoryFakeTransport implements HttpTransport
{
    /** @var array<string, mixed> */
    public array $snapshot = [];
    /** @var array<int, array<string, mixed>> */
    public array $reports = [];
    /** @var array<int, array<string, mixed>> */
    public array $departments = [
        ['id' => 1, 'dept_code' => 'SL-ROOT', 'dept_name' => 'Demo School', 'parent_dept' => null],
        ['id' => 2, 'dept_code' => 'SL-STUDENTS', 'dept_name' => 'Students', 'parent_dept' => 1],
        ['id' => 3, 'dept_code' => 'SL-STU-C-1', 'dept_name' => 'Primary 1', 'parent_dept' => 2],
    ];
    /** @var array<int, array<string, mixed>> */
    public array $positions = [
        ['id' => 1, 'position_code' => 'SL-STU-C-1-S-1', 'position_name' => 'A', 'parent_position' => null],
    ];
    /** @var array<int, array<string, mixed>> */
    public array $employees = [];
    /** @var array<string, int> */
    public array $writes = [
        'employee_post' => 0, 'employee_put' => 0, 'employee_delete' => 0,
        'department_post' => 0, 'position_post' => 0, 'resync' => 0,
    ];
    private int $nextEmployeeId = 100;
    private int $nextDepartmentId = 10;
    private int $nextPositionId = 10;

    /** @param array<int, string> $headers
     *  @return array{status:int,headers:array<string,string>,body:string,json:mixed}
     */
    public function request(
        string $method,
        string $url,
        array $headers = [],
        ?string $body = null,
        int $timeoutSeconds = 20
    ): array {
        $path = (string) parse_url($url, PHP_URL_PATH);
        if ($method === 'POST' && $path === '/api-token-auth/') {
            return $this->response(200, ['token' => 'test-token']);
        }
        if ($method === 'GET' && $path === '/api/biometric/v2/directory') {
            return $this->response(200, $this->snapshot);
        }
        if ($method === 'POST' && $path === '/api/biometric/v2/directory/result') {
            $payload = json_decode((string) $body, true);
            if (!is_array($payload)) {
                return $this->response(400, ['success' => false]);
            }
            $this->reports[] = $payload;
            return $this->response(200, ['success' => true]);
        }
        if ($method === 'GET' && $path === '/personnel/api/departments/') {
            return $this->response(200, ['data' => array_values($this->departments), 'next' => null]);
        }
        if ($method === 'GET' && $path === '/personnel/api/positions/') {
            return $this->response(200, ['data' => array_values($this->positions), 'next' => null]);
        }
        if ($method === 'GET' && $path === '/personnel/api/employees/') {
            return $this->response(200, ['data' => array_values($this->employees), 'next' => null]);
        }
        if ($method === 'POST' && $path === '/personnel/api/employees/resync_to_device/') {
            $this->writes['resync']++;
            return $this->response(200, ['success' => true]);
        }
        if ($method === 'POST' && $path === '/personnel/api/employees/') {
            $payload = $this->payload($body);
            $payload['id'] = $this->nextEmployeeId++;
            $payload['fingerprint_count'] = 0;
            $this->employees[] = $payload;
            $this->writes['employee_post']++;
            return $this->response(201, $payload);
        }
        if ($method === 'POST' && $path === '/personnel/api/departments/') {
            $payload = $this->payload($body);
            $payload['id'] = $this->nextDepartmentId++;
            $this->departments[] = $payload;
            $this->writes['department_post']++;
            return $this->response(201, $payload);
        }
        if ($method === 'POST' && $path === '/personnel/api/positions/') {
            $payload = $this->payload($body);
            $payload['id'] = $this->nextPositionId++;
            $this->positions[] = $payload;
            $this->writes['position_post']++;
            return $this->response(201, $payload);
        }
        if ($method === 'PUT' && preg_match('#^/personnel/api/employees/(\d+)/$#', $path, $match)) {
            $id = (int) $match[1];
            $payload = $this->payload($body);
            foreach ($this->employees as &$employee) {
                if ((int) $employee['id'] === $id) {
                    // Provider-managed biometric data survives identity updates.
                    $employee = array_merge($employee, $payload, ['id' => $id]);
                    $this->writes['employee_put']++;
                    return $this->response(200, $employee);
                }
            }
            unset($employee);
            return $this->response(404, ['detail' => 'not found']);
        }
        if ($method === 'DELETE' && preg_match('#^/personnel/api/employees/(\d+)/$#', $path, $match)) {
            $id = (int) $match[1];
            foreach ($this->employees as $index => $employee) {
                if ((int) $employee['id'] === $id) {
                    unset($this->employees[$index]);
                    $this->writes['employee_delete']++;
                    return $this->response(204, []);
                }
            }
            return $this->response(404, ['detail' => 'not found']);
        }
        return $this->response(404, ['detail' => $method . ' ' . $path]);
    }

    /** @return array<string, mixed> */
    private function payload(?string $body): array
    {
        $payload = json_decode((string) $body, true);
        return is_array($payload) ? $payload : [];
    }

    /** @param mixed $json @return array{status:int,headers:array<string,string>,body:string,json:mixed} */
    private function response(int $status, $json): array
    {
        return ['status' => $status, 'headers' => [], 'body' => json_encode($json), 'json' => $json];
    }
}

$assertions = 0;
$processes = [];
$temporaryDirectory = sys_get_temp_dir() . '/schoollift_biometric_gateway_' . bin2hex(random_bytes(6));
mkdir($temporaryDirectory, 0770, true);

try {
    unitTests();
    directorySyncTests();
    integrationTests();
    echo 'Biometric gateway tests passed (' . $assertions . ' assertions).' . PHP_EOL;
} catch (Throwable $error) {
    fwrite(STDERR, 'Biometric gateway test failed: ' . $error->getMessage() . PHP_EOL);
    exit(1);
} finally {
    foreach ($processes as $process) {
        stopServer($process);
    }
    removeTree($temporaryDirectory);
}

function directorySyncTests(): void
{
    global $temporaryDirectory;
    $previewHttp = new DirectoryFakeTransport();
    $previewHttp->departments = [];
    $previewHttp->positions = [];
    $previewHttp->snapshot = directorySnapshot(directoryPeople(1), [], [], false, 'preview');
    $previewConfig = directoryConfig($temporaryDirectory);
    $previewClock = new MutableClock(new DateTimeImmutable('2026-10-02T07:00:00Z'));
    $previewSync = new DirectorySynchronizer(
        new GatewayStore($temporaryDirectory . '/directory-preview.sqlite'),
        new ZkBioClient($previewHttp, $previewConfig['provider']),
        new SchoolLiftClient($previewHttp, $previewConfig['schoollift'], $previewConfig['gateway_id']),
        $previewClock,
        new JsonLogger($temporaryDirectory . '/directory-preview.log', false),
        $previewConfig
    );
    $preview = $previewSync->run(true);
    assertSame('preview', $preview['status'], 'Preview should calculate a diff even when SchoolLift groups do not exist yet.');
    assertSame(1, $preview['summary']['created'], 'Preview should report the proposed personnel creation.');
    assertSame(0, count($previewHttp->employees), 'Preview must never write ZKBio personnel.');

    $http = new DirectoryFakeTransport();
    $people = directoryPeople(20);
    $http->employees[] = array_merge(directoryEmployeePayload($people[1]), [
        'id' => 90,
        'fingerprint_count' => 2,
    ]);
    $http->snapshot = directorySnapshot($people);
    $clock = new MutableClock(new DateTimeImmutable('2026-10-02T08:00:00Z'));
    $store = new GatewayStore($temporaryDirectory . '/directory.sqlite');
    $config = directoryConfig($temporaryDirectory);
    $provider = new ZkBioClient($http, $config['provider']);
    $schoolLift = new SchoolLiftClient($http, $config['schoollift'], $config['gateway_id']);
    $logger = new JsonLogger($temporaryDirectory . '/directory.log', false);
    $sync = new DirectorySynchronizer($store, $provider, $schoolLift, $clock, $logger, $config);

    $first = $sync->run(true);
    assertSame('success', $first['status'], 'A complete active snapshot should synchronize successfully.');
    assertSame(19, $first['summary']['created'], 'New SchoolLift people should be created in ZKBio.');
    assertSame(1, $first['summary']['adopted'], 'One unique existing employee code should be adopted.');
    assertSame(20, count($http->employees), 'Directory reconciliation should produce one ZKBio employee per person.');
    assertSame(2, $http->employees[0]['fingerprint_count'], 'Adoption must preserve existing biometric metadata.');

    $writeCount = $http->writes['employee_post'] + $http->writes['employee_put'];
    $second = $sync->run(true);
    assertSame(20, $second['summary']['unchanged'], 'An unchanged second snapshot should be a no-op.');
    assertSame($writeCount, $http->writes['employee_post'] + $http->writes['employee_put'], 'No-op sync must not write personnel.');

    $duplicate = directoryEmployeeByCode($http->employees, 'GIS003');
    $duplicate['id'] = 999;
    $http->employees[] = $duplicate;
    $conflicted = $sync->run(true);
    assertSame(1, $conflicted['summary']['conflicts'], 'Duplicate provider employee codes must be reported instead of guessed.');
    $http->employees = array_values(array_filter($http->employees, static fn (array $row): bool => (int) $row['id'] !== 999));

    $people[0]['department_code'] = 'SL-STU-C-2';
    $people[0]['position_code'] = 'SL-STU-C-2-S-2';
    $people[0]['desired_hash'] = directoryDesiredHash($people[0]);
    $http->snapshot = directorySnapshot($people);
    $moved = $sync->run(true);
    assertSame(1, $moved['summary']['updated'], 'Changing a class and arm should move the ZKBio person.');
    assertSame(1, $http->writes['department_post'], 'The new class department should be created once.');
    assertSame(1, $http->writes['position_post'], 'The new class-arm position should be created once.');

    $people[0]['first_name'] = 'Updated';
    $people[0]['desired_hash'] = directoryDesiredHash($people[0]);
    foreach ($http->employees as &$employee) {
        if ($employee['emp_code'] === 'GIS001') {
            $employee['fingerprint_count'] = 3;
        }
    }
    unset($employee);
    $http->snapshot = directorySnapshot($people);
    $updated = $sync->run(true);
    assertSame(1, $updated['summary']['updated'], 'A SchoolLift name change should update one ZKBio identity.');
    $employee = directoryEmployeeByCode($http->employees, 'GIS001');
    assertSame(3, $employee['fingerprint_count'], 'Ordinary updates must preserve biometric enrollment metadata.');

    $singleDeleted = array_pop($people);
    $http->snapshot = directorySnapshot($people, [directoryTombstone($singleDeleted, $http->employees)]);
    $single = $sync->run(true);
    assertSame(1, $single['summary']['deleted'], 'A single deletion below both safeguards should be immediate.');
    assertSame(1, $http->writes['employee_delete'], 'The disabled person should be hard-deleted through the API.');

    $massDeleted = array_splice($people, 8, 11);
    $tombstones = [];
    foreach ($massDeleted as $person) {
        $tombstones[] = directoryTombstone($person, $http->employees);
    }
    $invalidSnapshot = directorySnapshot($people, $tombstones);
    $invalidSnapshot['complete'] = false;
    $http->snapshot = $invalidSnapshot;
    $beforeDeletes = $http->writes['employee_delete'];
    $invalid = $sync->run(true);
    assertSame('failed', $invalid['status'], 'An incomplete snapshot must fail closed.');
    assertSame($beforeDeletes, $http->writes['employee_delete'], 'An incomplete snapshot must never delete personnel.');

    $http->snapshot = directorySnapshot($people, $tombstones);
    $paused = $sync->run(true);
    assertSame('awaiting_approval', $paused['status'], 'More than ten deletions must pause for privileged approval.');
    assertSame($beforeDeletes, $http->writes['employee_delete'], 'A paused deletion batch must not change ZKBio.');

    $http->snapshot = directorySnapshot($people, $tombstones, [], true);
    $approved = $sync->run(true);
    assertSame('success', $approved['status'], 'The exact approved snapshot should resume destructive reconciliation.');
    assertSame(11, $approved['summary']['deleted'], 'The approved batch should delete every tombstone.');
    assertSame($beforeDeletes + 11, $http->writes['employee_delete'], 'Approved hard deletes must use the employee API.');
}

/** @return array<int, array<string, mixed>> */
function directoryPeople(int $count): array
{
    $people = [];
    for ($index = 1; $index <= $count; $index++) {
        $person = [
            'person_key' => 'student:' . $index,
            'subject_type' => 'student',
            'subject_key' => $index,
            'subject_id' => 1000 + $index,
            'provider_person_id' => null,
            'applied_hash' => null,
            'emp_code' => 'GIS' . str_pad((string) $index, 3, '0', STR_PAD_LEFT),
            'first_name' => 'Student' . $index,
            'last_name' => 'Demo',
            'gender' => 'M',
            'mobile' => '08000000000',
            'email' => 'student' . $index . '@example.test',
            'hire_date' => '2026-09-01',
            'department_code' => 'SL-STU-C-1',
            'position_code' => 'SL-STU-C-1-S-1',
        ];
        $person['desired_hash'] = directoryDesiredHash($person);
        $people[] = $person;
    }
    return $people;
}

/** @param array<string, mixed> $person */
function directoryDesiredHash(array $person): string
{
    $managed = [];
    foreach (['emp_code', 'first_name', 'last_name', 'gender', 'mobile', 'email', 'hire_date', 'department_code', 'position_code'] as $field) {
        $managed[$field] = $person[$field];
    }
    return hash('sha256', json_encode($managed, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
}

/** @param array<string, mixed> $person @return array<string, mixed> */
function directoryEmployeePayload(array $person): array
{
    return [
        'emp_code' => $person['emp_code'], 'first_name' => $person['first_name'],
        'last_name' => $person['last_name'], 'gender' => 'M', 'mobile' => $person['mobile'],
        'email' => $person['email'], 'department' => 3, 'position' => 1,
        'area' => [1], 'enable_att' => true, 'app_status' => 1, 'hire_date' => $person['hire_date'],
    ];
}

/** @param array<int, array<string, mixed>> $people @param array<int, array<string, mixed>> $tombstones
 *  @param array<int, array<string, mixed>> $conflicts @return array<string, mixed>
 */
function directorySnapshot(
    array $people,
    array $tombstones = [],
    array $conflicts = [],
    bool $approved = false,
    string $mode = 'active'
): array
{
    $departments = [
        ['code' => 'SL-ROOT', 'name' => 'Demo School', 'parent_code' => null, 'depth' => 0],
        ['code' => 'SL-STUDENTS', 'name' => 'Students', 'parent_code' => 'SL-ROOT', 'depth' => 1],
        ['code' => 'SL-STU-C-1', 'name' => 'Primary 1', 'parent_code' => 'SL-STUDENTS', 'depth' => 2],
    ];
    $positions = [
        ['code' => 'SL-STU-C-1-S-1', 'name' => 'A', 'parent_code' => null],
    ];
    foreach ($people as $person) {
        if (($person['department_code'] ?? null) === 'SL-STU-C-2'
            && !in_array('SL-STU-C-2', array_column($departments, 'code'), true)) {
            $departments[] = ['code' => 'SL-STU-C-2', 'name' => 'Primary 2', 'parent_code' => 'SL-STUDENTS', 'depth' => 2];
        }
        if (($person['position_code'] ?? null) === 'SL-STU-C-2-S-2'
            && !in_array('SL-STU-C-2-S-2', array_column($positions, 'code'), true)) {
            $positions[] = ['code' => 'SL-STU-C-2-S-2', 'name' => 'B', 'parent_code' => null];
        }
    }
    $content = [
        'departments' => $departments,
        'positions' => $positions,
        'people' => array_values($people),
        'tombstones' => array_values($tombstones),
        'conflicts' => array_values($conflicts),
    ];
    $hash = hash('sha256', json_encode($content, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
    return array_merge([
        'success' => true, 'complete' => true, 'mode' => $mode,
        'snapshot_id' => substr($hash, 0, 32), 'snapshot_hash' => $hash,
        'generated_at' => '2026-10-02T08:00:00+00:00', 'delete_approved' => $approved,
        'interval_seconds' => 300,
        'counts' => [
            'people' => count($people), 'tombstones' => count($tombstones),
            'conflicts' => count($conflicts), 'departments' => count($content['departments']),
            'positions' => count($content['positions']),
        ],
    ], $content);
}

/** @param array<string, mixed> $person @param array<int, array<string, mixed>> $employees
 *  @return array<string, mixed>
 */
function directoryTombstone(array $person, array $employees): array
{
    $employee = directoryEmployeeByCode($employees, (string) $person['emp_code']);
    return [
        'person_key' => $person['person_key'], 'subject_type' => $person['subject_type'],
        'subject_key' => $person['subject_key'], 'subject_id' => $person['subject_id'],
        'emp_code' => $person['emp_code'], 'provider_person_id' => (string) $employee['id'],
        'reason' => 'disabled',
    ];
}

/** @param array<int, array<string, mixed>> $employees @return array<string, mixed> */
function directoryEmployeeByCode(array $employees, string $code): array
{
    foreach ($employees as $employee) {
        if (($employee['emp_code'] ?? null) === $code) {
            return $employee;
        }
    }
    throw new RuntimeException('Missing fake employee ' . $code);
}

/** @return array<string, mixed> */
function directoryConfig(string $temporaryDirectory): array
{
    return [
        'gateway_id' => 'directory-test-gateway', 'request_timeout_seconds' => 5,
        'provider' => [
            'base_url' => 'http://127.0.0.1:8787', 'username' => 'test', 'password' => 'test',
            'auth_path' => '/api-token-auth/', 'auth_body_format' => 'json', 'token_field' => 'token',
            'authorization_scheme' => 'Token', 'employees_path' => '/personnel/api/employees/',
            'departments_path' => '/personnel/api/departments/', 'positions_path' => '/personnel/api/positions/',
            'areas_path' => '/personnel/api/areas/', 'resync_path' => '/personnel/api/employees/resync_to_device/',
            'data_field' => 'data', 'page_size' => 100, 'max_pages' => 10,
            'area_ids' => [1], 'resync_to_device' => true,
        ],
        'schoollift' => [
            'base_url' => 'http://127.0.0.1:8080', 'bearer_token' => str_repeat('a', 48),
            'directory_path' => '/api/biometric/v2/directory',
            'directory_result_path' => '/api/biometric/v2/directory/result',
        ],
        'directory' => ['default_interval_seconds' => 300, 'maximum_delete_count' => 10, 'maximum_delete_percent' => 10],
    ];
}

function unitTests(): void
{
    global $temporaryDirectory;
    $normalizer = new EventNormalizer('Africa/Lagos', ['15' => 'face']);
    $normalized = $normalizer->normalize([
        'id' => 44,
        'emp_code' => 'STU-001',
        'punch_time' => '2026-08-11 07:30:00',
        'punch_state' => '0',
        'verify_type' => 15,
        'terminal_sn' => 'SIM-GATE-001',
        'face_template' => 'must-never-leave',
        'photo' => 'must-never-leave',
    ]);
    assertSame('zkbio:SIM-GATE-001:44', $normalized['external_event_id'], 'Stable provider ID should be namespaced by terminal.');
    assertSame('2026-08-11T06:30:00+00:00', $normalized['occurred_at'], 'Lagos source time should normalize to UTC.');
    assertSame('0', $normalized['punch_state'], 'Explicit provider punch state must be preserved.');
    assertSame(false, array_key_exists('face_template', $normalized), 'Biometric templates must be stripped.');
    assertSame(false, array_key_exists('photo', $normalized), 'Images must be stripped.');

    $databasePath = $temporaryDirectory . '/unit.sqlite';
    $store = new GatewayStore($databasePath);
    $now = new DateTimeImmutable('2026-08-11T07:00:00Z');
    $first = $store->enqueueAndAdvance([$normalized], $now->format(DATE_ATOM), $now);
    $second = $store->enqueueAndAdvance([$normalized], $now->modify('+1 minute')->format(DATE_ATOM), $now->modify('+1 minute'));
    assertSame(1, $first['inserted'], 'First event should enter the durable queue.');
    assertSame(1, $second['duplicates'], 'Replayed provider event should not duplicate the queue.');
    assertSame(1, $store->status()['pending'], 'Exactly one durable event should remain pending.');

    unset($store);
    $reopened = new GatewayStore($databasePath);
    assertSame(1, $reopened->status()['pending'], 'SQLite queue must survive gateway restart.');
    assertSame('2026-08-11T07:01:00+00:00', $reopened->providerCursor(), 'Cursor must survive gateway restart.');
    $initialHeartbeat = $reopened->heartbeatStatus(true);
    assertSame(true, $initialHeartbeat['can_accept_command'], 'Heartbeat should advertise command readiness explicitly.');
    assertSame(null, $initialHeartbeat['last_sync_ok'], 'A new gateway should not invent a previous sync result.');
    assertSame(0, $initialHeartbeat['queue']['retry'], 'A new pending event has not yet entered retry state.');

    $command = [
        'command_uuid' => '11111111111111111111111111111111',
        'type' => 'sync_now',
        'expires_at' => '2026-08-11T08:00:00+00:00',
    ];
    $reopened->claimCommand($command, $now);
    assertSame(true, $reopened->hasOutstandingCommand(), 'A claimed website command must survive in SQLite.');
    assertSame('sync_now', $reopened->claimedCommand()['command_type'], 'Claimed command type must be durable.');
    $reopened->completeCommand($command['command_uuid'], 'succeeded', ['message' => 'done'], $now);
    assertSame('succeeded', $reopened->pendingCommandResult()['status'], 'Command result must be durable before reporting.');
    $reopened->markCommandReported($command['command_uuid'], $now);
    assertSame(false, $reopened->hasOutstandingCommand(), 'An acknowledged command must no longer block the next claim.');
    $pending = $reopened->pending(100, $now->modify('+2 minutes'));
    assertSame(
        '2026-08-11T07:00:00+00:00',
        $pending[0]['provider_cursor'],
        'A queued event must retain the cursor committed with its first durable insert.'
    );

    $nextEvent = $normalized;
    $nextEvent['external_event_id'] = 'zkbio:SIM-GATE-001:45';
    $reopened->enqueueAndAdvance([$nextEvent], '2026-08-11T07:02:00+00:00', $now->modify('+2 minutes'));
    $firstCursorBatch = $reopened->pending(100, $now->modify('+3 minutes'));
    assertSame(1, count($firstCursorBatch), 'Pending batches must not mix rows from different provider cursors.');
    $reopened->markDelivered(
        $normalized['external_event_id'],
        'accepted',
        null,
        200,
        $now->modify('+3 minutes')
    );
    $secondCursorBatch = $reopened->pending(100, $now->modify('+3 minutes'));
    assertSame(
        '2026-08-11T07:02:00+00:00',
        $secondCursorBatch[0]['provider_cursor'],
        'The next provider poll must be delivered with its own durable cursor.'
    );

    $configPath = $temporaryDirectory . '/config-test.php';
    $minimumConfig = [
        'gateway_id' => 'config-test-gateway',
        'allow_insecure_localhost' => true,
        'verify_tls' => false,
        'provider' => [
            'base_url' => 'http://127.0.0.1:8787',
            'username' => 'user',
            'password' => 'password',
            'terminal_serial' => 'SIM-GATE-001',
        ],
        'schoollift' => [
            'base_url' => 'http://127.0.0.1:8080',
            'bearer_token' => str_repeat('a', 48),
        ],
    ];
    file_put_contents($configPath, '<?php return ' . var_export($minimumConfig, true) . ';');
    $loaded = Config::load($configPath);
    assertSame('SIM-GATE-001', $loaded['provider']['terminal_serial'], 'Config should accept one bidirectional simulator serial.');
    assertSame(100, $loaded['schoollift']['batch_size'], 'Config should default to the API batch ceiling.');

    $jsonConfigPath = $temporaryDirectory . '/config-test.json';
    file_put_contents(
        $jsonConfigPath,
        "\xEF\xBB\xBF" . json_encode($minimumConfig, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)
    );
    $jsonLoaded = Config::load($jsonConfigPath);
    assertSame('config-test-gateway', $jsonLoaded['gateway_id'], 'Manager JSON configuration should load without executing PHP.');
    assertSame('user', $jsonLoaded['provider']['username'], 'UTF-8 BOM from Windows PowerShell should be accepted.');

    file_put_contents($jsonConfigPath, '{not-valid-json');
    $invalidJsonRejected = false;
    try {
        Config::load($jsonConfigPath);
    } catch (Throwable $error) {
        $invalidJsonRejected = str_contains($error->getMessage(), 'JSON configuration is invalid');
    }
    assertSame(true, $invalidJsonRejected, 'Malformed manager JSON configuration should fail closed.');

    file_put_contents($jsonConfigPath, '[]');
    $jsonListRejected = false;
    try {
        Config::load($jsonConfigPath);
    } catch (Throwable $error) {
        $jsonListRejected = str_contains($error->getMessage(), 'top-level object');
    }
    assertSame(true, $jsonListRejected, 'Manager JSON configuration should require one top-level object.');

    $minimumConfig['provider']['terminal_serial'] = 'SIM-IN-001';
    file_put_contents($configPath, '<?php return ' . var_export($minimumConfig, true) . ';');
    $rejectedFixedSerial = false;
    try {
        Config::load($configPath);
    } catch (Throwable $error) {
        $rejectedFixedSerial = str_contains($error->getMessage(), 'bidirectional serial');
    }
    assertSame(true, $rejectedFixedSerial, 'Config should reject obsolete fixed-direction simulator serials.');
}

function integrationTests(): void
{
    global $temporaryDirectory, $processes;
    $sandboxState = $temporaryDirectory . '/sandbox.json';
    $receiverState = $temporaryDirectory . '/receiver.json';
    file_put_contents($sandboxState, '');
    file_put_contents($receiverState, json_encode([
        'known' => [], 'batches' => [], 'failure' => null,
        'commands' => [], 'polls' => [], 'command_results' => [],
    ]));

    $sandboxPort = reservePort();
    $receiverPort = reservePort();
    $sandboxRoot = dirname(__DIR__, 2) . '/biometric-sandbox';
    $gatewayRoot = dirname(__DIR__);
    $processes[] = startServer(
        $sandboxPort,
        $sandboxRoot . '/public',
        $sandboxRoot . '/public/router.php',
        [
            'BIOMETRIC_SANDBOX_STATE' => $sandboxState,
            'BIOMETRIC_SANDBOX_USERNAME' => 'gateway-user',
            'BIOMETRIC_SANDBOX_PASSWORD' => 'gateway-password',
            'BIOMETRIC_SANDBOX_TOKEN' => 'gateway-source-token',
            'BIOMETRIC_SANDBOX_CONTROL_KEY' => 'gateway-control',
        ]
    );
    $processes[] = startServer(
        $receiverPort,
        $gatewayRoot . '/tests/fixtures',
        $gatewayRoot . '/tests/fixtures/schoollift-router.php',
        [
            'GATEWAY_TEST_RECEIVER_STATE' => $receiverState,
            'GATEWAY_TEST_RECEIVER_TOKEN' => 'schoollift-token',
        ]
    );
    waitForServer('http://127.0.0.1:' . $sandboxPort . '/health');

    $http = new CurlHttpTransport(false);
    $factory = new ScenarioFactory(new DateTimeZone('Africa/Lagos'));
    $normal = $factory->make(
        'normal',
        'STU-001',
        '2026-08-11',
        'SIM-GATE-001',
        5,
        new DateTimeImmutable('2026-08-11 12:00:00', new DateTimeZone('Africa/Lagos'))
    );
    $normal['events'][0]['face_template'] = 'private-template';
    injectEvents($http, $sandboxPort, $normal['events']);

    $clock = new MutableClock(new DateTimeImmutable('2026-08-11T12:00:00Z'));
    $config = testConfig($temporaryDirectory . '/integration.sqlite', $sandboxPort, $receiverPort);
    $store = new GatewayStore($config['database_path']);
    $runner = makeRunner($config, $store, $clock, $http);

    $commandUuid = '22222222222222222222222222222222';
    $receiver = readJson($receiverState);
    $receiver['commands'][] = [
        'command_uuid' => $commandUuid,
        'type' => 'connection_test',
        'expires_at' => '2026-08-12T12:00:00+00:00',
    ];
    file_put_contents($receiverState, json_encode($receiver, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES), LOCK_EX);
    $control = new GatewayControl(
        $store,
        new SchoolLiftClient($http, $config['schoollift'], $config['gateway_id']),
        $clock,
        3
    );
    $claimed = $control->poll(true);
    assertSame('connection_test', $claimed['command_type'], 'Heartbeat should claim only the named allowlisted command.');
    assertSame(false, $control->poll(false) !== null, 'End heartbeat must not claim another command.');
    $control->complete($commandUuid, true, [
        'summary' => ['ok' => true],
        'message' => 'connection ok',
        'password' => 'must-not-leave',
    ]);
    assertSame(true, $control->reportPending(), 'Durable command result should be acknowledged by SchoolLift.');
    $receiver = readJson($receiverState);
    assertSame(2, count($receiver['polls']), 'Control test should submit a claim poll and heartbeat-only poll.');
    assertSame(true, $receiver['polls'][0]['status']['can_accept_command'], 'Claim poll must advertise readiness.');
    assertSame(false, $receiver['polls'][1]['status']['can_accept_command'], 'End poll must explicitly disable claiming.');
    assertSame(1, count($receiver['command_results']), 'Exactly one allowlisted command result should be reported.');
    assertSame(false, array_key_exists('password', $receiver['command_results'][0]['result']), 'Unapproved result keys must not leave the gateway.');

    $receiver['commands'][] = [
        'command_uuid' => '33333333333333333333333333333333',
        'type' => 'run_shell',
        'expires_at' => '2026-08-12T12:00:00+00:00',
    ];
    file_put_contents($receiverState, json_encode($receiver, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES), LOCK_EX);
    $unsupportedRejected = false;
    try {
        $control->poll(true);
    } catch (Throwable $error) {
        $unsupportedRejected = str_contains($error->getMessage(), 'unsupported gateway command type');
    }
    assertSame(true, $unsupportedRejected, 'Gateway must reject every command outside the fixed allowlist.');
    assertSame(false, $store->hasOutstandingCommand(), 'A rejected command must never enter the durable execution queue.');

    $first = $runner->runOnce();
    assertSame(true, $first['ok'], 'Normal gateway run should succeed.');
    assertSame(2, $first['provider']['received'], 'One terminal should provide an explicit IN and OUT event.');
    assertSame(2, $first['delivery']['delivered'], 'Both single-terminal events should reach SchoolLift.');
    assertSame(0, $store->status()['pending'], 'No event should remain pending after successful delivery.');
    assertSame(2, $store->status()['delivered'], 'Delivered events should remain briefly for replay deduplication.');

    $receiver = readJson($receiverState);
    assertSame(1, count($receiver['batches']), 'Normal events should be delivered in one batch.');
    $sent = $receiver['batches'][0]['events'];
    assertSame('SIM-GATE-001', $sent[0]['device_serial'], 'IN must use the same bidirectional serial.');
    assertSame('SIM-GATE-001', $sent[1]['device_serial'], 'OUT must use the same bidirectional serial.');
    assertSame('0', $sent[0]['punch_state'], 'First event must retain explicit IN punch state.');
    assertSame('1', $sent[1]['punch_state'], 'Second event must retain explicit OUT punch state.');
    assertSame(false, array_key_exists('face_template', $sent[0]), 'Outbound API payload must not leak a biometric template.');

    $clock->advance(60);
    $replay = $runner->runOnce();
    assertSame(2, $replay['provider']['duplicates'], 'Overlap polling should safely detect already queued source events.');
    $receiver = readJson($receiverState);
    assertSame(1, count($receiver['batches']), 'Overlap replay should not redeliver an already acknowledged queue row.');

    scheduleReceiverFailure($receiverState, 503, 1, 2);
    injectEvents($http, $sandboxPort, [[
        'id' => 7001,
        'emp_code' => 'STAFF-001',
        'punch_time' => '2026-08-11 13:00:00',
        'punch_state' => '0',
        'verify_type' => 1,
        'terminal_sn' => 'SIM-GATE-001',
    ]]);
    $clock->advance(60);
    $failedDelivery = $runner->runOnce();
    assertSame(false, $failedDelivery['ok'], 'HTTP 503 delivery should make the run report a recoverable failure.');
    assertSame(1, $store->status()['pending'], 'HTTP 503 must leave the event durably pending.');

    unset($runner, $store);
    $store = new GatewayStore($config['database_path']);
    $clock->advance(3);
    $runner = makeRunner($config, $store, $clock, $http);
    $recovered = $runner->runOnce();
    assertSame(true, $recovered['ok'], 'A restarted gateway should deliver a queued event after 503 backoff.');
    assertSame(0, $store->status()['pending'], 'Recovered delivery should clear the pending queue.');

    scheduleReceiverFailure($receiverState, 429, 1, 2);
    injectEvents($http, $sandboxPort, [[
        'id' => 7002,
        'emp_code' => 'STU-002',
        'punch_time' => '2026-08-11 13:30:00',
        'punch_state' => '1',
        'verify_type' => 2,
        'terminal_sn' => 'SIM-GATE-001',
    ]]);
    $clock->advance(60);
    $rateLimited = $runner->runOnce();
    assertSame(false, $rateLimited['ok'], 'HTTP 429 should defer rather than discard the event.');
    assertSame(1, $store->status()['pending'], 'Rate-limited event should remain pending.');
    $clock->advance(3);
    assertSame(true, $runner->runOnce()['ok'], 'Rate-limited event should recover after Retry-After.');

    scheduleSandboxFailure($http, $sandboxPort, 503);
    $cursorBefore = $store->providerCursor();
    $clock->advance(60);
    $providerFailure = $runner->runOnce();
    assertSame(false, $providerFailure['ok'], 'ZKBio HTTP 503 should be reported.');
    assertSame($cursorBefore, $store->providerCursor(), 'Provider cursor must not advance on ZKBio failure.');
    assertSame(1, $store->status()['provider_failure_count'], 'Provider backoff state should be durable.');

    $clock->advance(20);
    $afterProviderFailure = $runner->runOnce();
    assertSame(true, $afterProviderFailure['ok'], 'Provider should recover after its durable backoff.');
    assertSame(0, $store->status()['provider_failure_count'], 'Successful poll should clear provider backoff.');

    injectEvents($http, $sandboxPort, [[
        'id' => 7003,
        'emp_code' => 'STU-003',
        'punch_time' => '2026-08-11 14:00:00',
        'punch_state' => '0',
        'verify_type' => 15,
        'terminal_sn' => 'SIM-GATE-001',
    ]]);
    $badSchoolConfig = $config;
    $badSchoolConfig['schoollift']['bearer_token'] = 'wrong-token';
    $badRunner = makeRunner($badSchoolConfig, $store, $clock, $http);
    $clock->advance(60);
    $unauthorized = $badRunner->runOnce();
    assertSame(false, $unauthorized['ok'], 'HTTP 401 must be visible and retryable after credential repair.');
    assertSame(1, $store->status()['pending'], 'Unauthorized event must remain durable.');
    $clock->advance(3);
    assertSame(true, $runner->runOnce()['ok'], 'Corrected SchoolLift credential should release the queue.');
    assertSame(0, $store->status()['pending'], 'Credential recovery should leave no pending event.');

    injectEvents($http, $sandboxPort, [[
        'id' => 7004,
        'emp_code' => 'STAFF-004',
        'punch_time' => '2026-08-11 14:30:00',
        'punch_state' => '1',
        'verify_type' => 1,
        'terminal_sn' => 'SIM-GATE-001',
    ]]);
    $offlineConfig = $config;
    $offlineConfig['schoollift']['base_url'] = 'http://127.0.0.1:' . reservePort();
    $offlineRunner = makeRunner($offlineConfig, $store, $clock, $http);
    $clock->advance(60);
    $offline = $offlineRunner->runOnce();
    assertSame(false, $offline['ok'], 'A refused SchoolLift connection should be reported without losing the event.');
    assertSame(1, $store->status()['pending'], 'Offline SchoolLift event should stay in SQLite.');
    $clock->advance(3);
    assertSame(true, $runner->runOnce()['ok'], 'Queued offline event should deliver when connectivity returns.');
    assertSame(0, $store->status()['pending'], 'Network recovery should drain the queue.');
}

/** @param array<string, mixed> $config */
function makeRunner(array $config, GatewayStore $store, Clock $clock, CurlHttpTransport $http): GatewayRunner
{
    return new GatewayRunner(
        $store,
        new ZkBioClient($http, $config['provider']),
        new SchoolLiftClient($http, $config['schoollift'], $config['gateway_id']),
        new EventNormalizer($config['timezone'], $config['provider']['verification_method_map']),
        $clock,
        new JsonLogger(null, false),
        $config
    );
}

/** @return array<string, mixed> */
function testConfig(string $databasePath, int $sandboxPort, int $receiverPort): array
{
    return [
        'gateway_id' => 'test-gateway-01',
        'timezone' => 'Africa/Lagos',
        'database_path' => $databasePath,
        'request_timeout_seconds' => 3,
        'provider' => [
            'base_url' => 'http://127.0.0.1:' . $sandboxPort,
            'username' => 'gateway-user',
            'password' => 'gateway-password',
            'auth_path' => '/api-token-auth/',
            'auth_body_format' => 'json',
            'token_field' => 'token',
            'authorization_scheme' => 'Token',
            'transactions_path' => '/iclock/api/transactions/',
            'data_field' => 'data',
            'terminal_serial' => 'SIM-GATE-001',
            'page_size' => 100,
            'max_pages' => 10,
            'overlap_seconds' => 172800,
            'initial_lookback_seconds' => 172800,
            'verification_method_map' => ['1' => 'fingerprint', '2' => 'card', '15' => 'face'],
        ],
        'schoollift' => [
            'base_url' => 'http://127.0.0.1:' . $receiverPort,
            'bearer_token' => 'schoollift-token',
            'events_path' => '/api/biometric/v2/events',
            'health_path' => '/api/biometric/v2/health',
            'batch_size' => 100,
            'max_batches_per_run' => 10,
        ],
        'retry' => [
            'base_seconds' => 1,
            'maximum_seconds' => 8,
            'maximum_attempts' => 4,
            'provider_base_seconds' => 1,
            'provider_maximum_seconds' => 8,
        ],
        'delivered_retention_days' => 30,
    ];
}

/** @param array<int, array<string, mixed>> $events */
function injectEvents(CurlHttpTransport $http, int $port, array $events): void
{
    $body = json_encode(['events' => $events], JSON_UNESCAPED_SLASHES);
    $response = $http->request('POST', 'http://127.0.0.1:' . $port . '/sandbox/events', [
        'Content-Type: application/json',
        'X-Sandbox-Key: gateway-control',
    ], $body, 3);
    assertSame(201, $response['status'], 'Sandbox event injection should succeed.');
}

function scheduleSandboxFailure(CurlHttpTransport $http, int $port, int $status): void
{
    $body = json_encode(['status' => $status, 'count' => 1, 'message' => 'scheduled provider outage']);
    $response = $http->request('POST', 'http://127.0.0.1:' . $port . '/sandbox/fail-next', [
        'Content-Type: application/json',
        'X-Sandbox-Key: gateway-control',
    ], $body, 3);
    assertSame(202, $response['status'], 'Sandbox provider failure should be scheduled.');
}

function scheduleReceiverFailure(string $path, int $status, int $remaining, int $retryAfter): void
{
    $state = readJson($path);
    $state['failure'] = ['status' => $status, 'remaining' => $remaining, 'retry_after' => $retryAfter];
    file_put_contents($path, json_encode($state, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES), LOCK_EX);
}

/** @return array<string, mixed> */
function readJson(string $path): array
{
    $decoded = json_decode((string) file_get_contents($path), true);
    return is_array($decoded) ? $decoded : [];
}

/** @return array{process:resource,pipes:array<int,resource>} */
function startServer(int $port, string $documentRoot, string $router, array $environment): array
{
    $command = [PHP_BINARY, '-S', '127.0.0.1:' . $port, '-t', $documentRoot, $router];
    $descriptors = [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']];
    // variables_order may omit E (notably in Windows/WAMP), leaving $_ENV
    // empty. Keep the actual parent environment available to the child PHP
    // server, then overlay only the fixture-specific values.
    $parentEnvironment = getenv();
    $process = proc_open(
        $command,
        $descriptors,
        $pipes,
        dirname($router),
        array_merge(is_array($parentEnvironment) ? $parentEnvironment : $_ENV, $environment)
    );
    if (!is_resource($process)) {
        throw new RuntimeException('Unable to start gateway integration test server.');
    }
    fclose($pipes[0]);
    stream_set_blocking($pipes[1], false);
    stream_set_blocking($pipes[2], false);
    return ['process' => $process, 'pipes' => $pipes];
}

/** @param array{process:resource,pipes:array<int,resource>} $server */
function stopServer(array $server): void
{
    proc_terminate($server['process']);
    foreach ($server['pipes'] as $pipe) {
        if (is_resource($pipe)) {
            fclose($pipe);
        }
    }
    proc_close($server['process']);
}

function waitForServer(string $url): void
{
    for ($attempt = 0; $attempt < 50; $attempt++) {
        $context = stream_context_create(['http' => ['timeout' => 1, 'ignore_errors' => true]]);
        if (@file_get_contents($url, false, $context) !== false) {
            return;
        }
        usleep(100000);
    }
    throw new RuntimeException('Test server did not become ready: ' . $url);
}

function reservePort(): int
{
    $socket = stream_socket_server('tcp://127.0.0.1:0', $errorNumber, $errorMessage);
    if ($socket === false) {
        throw new RuntimeException('Unable to reserve test port: ' . $errorMessage);
    }
    $address = (string) stream_socket_get_name($socket, false);
    fclose($socket);
    return (int) substr(strrchr($address, ':'), 1);
}

/** @param mixed $expected @param mixed $actual */
function assertSame($expected, $actual, string $message): void
{
    global $assertions;
    $assertions++;
    if ($expected !== $actual) {
        throw new RuntimeException(
            $message . ' Expected ' . var_export($expected, true) . ', got ' . var_export($actual, true) . '.'
        );
    }
}

function removeTree(string $path): void
{
    if (!is_dir($path)) {
        return;
    }
    $items = scandir($path) ?: [];
    foreach ($items as $item) {
        if ($item === '.' || $item === '..') {
            continue;
        }
        $target = $path . DIRECTORY_SEPARATOR . $item;
        if (is_dir($target)) {
            removeTree($target);
        } else {
            @unlink($target);
        }
    }
    @rmdir($path);
}
