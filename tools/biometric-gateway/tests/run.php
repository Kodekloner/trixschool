<?php

declare(strict_types=1);

use SchoolLift\BiometricGateway\Clock;
use SchoolLift\BiometricGateway\Config;
use SchoolLift\BiometricGateway\CurlHttpTransport;
use SchoolLift\BiometricGateway\EventNormalizer;
use SchoolLift\BiometricGateway\GatewayRunner;
use SchoolLift\BiometricGateway\GatewayStore;
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

$assertions = 0;
$processes = [];
$temporaryDirectory = sys_get_temp_dir() . '/schoollift_biometric_gateway_' . bin2hex(random_bytes(6));
mkdir($temporaryDirectory, 0770, true);

try {
    unitTests();
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
    file_put_contents($receiverState, json_encode(['known' => [], 'batches' => [], 'failure' => null]));

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
    $process = proc_open($command, $descriptors, $pipes, dirname($router), array_merge($_ENV, $environment));
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
