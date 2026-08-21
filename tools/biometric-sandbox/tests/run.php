<?php

declare(strict_types=1);

use SchoolLift\BiometricSandbox\HttpClient;
use SchoolLift\BiometricSandbox\JsonFileStore;
use SchoolLift\BiometricSandbox\ScenarioFactory;

require_once dirname(__DIR__) . '/bootstrap.php';

$assertions = 0;
$temporaryFiles = [];
$serverProcess = null;
$serverPipes = [];

try {
    runUnitTests();
    runCliSafetyTests();
    runHttpIntegrationTests();
    echo 'Biometric sandbox tests passed (' . $assertions . ' assertions).' . PHP_EOL;
} catch (Throwable $error) {
    fwrite(STDERR, 'Biometric sandbox test failed: ' . $error->getMessage() . PHP_EOL);
    exit(1);
} finally {
    if (is_resource($serverProcess)) {
        proc_terminate($serverProcess);
        foreach ($serverPipes as $pipe) {
            if (is_resource($pipe)) {
                fclose($pipe);
            }
        }
        proc_close($serverProcess);
    }
    foreach ($temporaryFiles as $file) {
        if (is_file($file)) {
            unlink($file);
        }
    }
}

function runCliSafetyTests(): void
{
    $root = dirname(__DIR__);
    $cases = [
        [
            [PHP_BINARY, $root . '/bin/serve.php', '--host=0.0.0.0'],
            2,
            'Refusing to expose the simulator outside loopback',
            'Launcher should reject a non-loopback bind without explicit approval.',
        ],
        [
            [PHP_BINARY, $root . '/bin/serve.php', '--port=70000'],
            2,
            'Port must be between 1 and 65535',
            'Launcher should reject an invalid TCP port.',
        ],
        [
            [
                PHP_BINARY,
                $root . '/bin/simulate.php',
                '--base-url=https://example.com',
                '--scenario=normal',
            ],
            1,
            'Simulation failed: Refusing a non-loopback target',
            'Simulator should reject a remote target with a controlled error.',
        ],
    ];

    foreach ($cases as $case) {
        [$command, $expectedExitCode, $expectedMessage, $description] = $case;
        $descriptors = [
            0 => ['pipe', 'r'],
            1 => ['pipe', 'w'],
            2 => ['pipe', 'w'],
        ];
        $pipes = [];
        $process = proc_open($command, $descriptors, $pipes, $root);
        if (!is_resource($process)) {
            throw new RuntimeException('Unable to start CLI safety check: ' . $description);
        }

        fclose($pipes[0]);
        $output = stream_get_contents($pipes[1]) . stream_get_contents($pipes[2]);
        fclose($pipes[1]);
        fclose($pipes[2]);
        $exitCode = proc_close($process);

        assertSame($expectedExitCode, $exitCode, $description);
        assertTrue(
            strpos($output, $expectedMessage) !== false,
            $description . ' The refusal should explain why it stopped.'
        );
    }
}

function runUnitTests(): void
{
    global $temporaryFiles;

    $timezone = new DateTimeZone('Africa/Lagos');
    $now = new DateTimeImmutable('2026-08-03 12:00:00', $timezone);
    $factory = new ScenarioFactory($timezone);

    $normal = $factory->make(
        'normal',
        'TBSW/ST/0002',
        '2026-08-03',
        'SIM-GATE-001',
        5,
        $now
    );
    assertSame(2, count($normal['events']), 'Normal scenario should contain IN and OUT.');
    assertSame('0', $normal['events'][0]['punch_state'], 'First normal event should be IN.');
    assertSame('1', $normal['events'][1]['punch_state'], 'Second normal event should be OUT.');
    assertSame(
        $normal['events'][0]['terminal_sn'],
        $normal['events'][1]['terminal_sn'],
        'IN and OUT should use the same bidirectional terminal serial.'
    );

    $duplicate = $factory->make(
        'duplicate',
        'TBSW/ST/0002',
        '2026-08-03',
        'SIM-GATE-001',
        5,
        $now
    );
    assertSame(
        $duplicate['events'][0]['id'],
        $duplicate['events'][1]['id'],
        'Duplicate scenario must replay the same vendor transaction ID.'
    );

    $outOfOrder = $factory->make(
        'out-of-order',
        'TBSW/ST/0002',
        '2026-08-03',
        'SIM-GATE-001',
        5,
        $now
    );
    assertSame('1', $outOfOrder['events'][0]['punch_state'], 'Out-of-order scenario should deliver OUT first.');
    assertSame('0', $outOfOrder['events'][1]['punch_state'], 'Out-of-order scenario should deliver IN later.');

    $stateFile = createTemporaryFile('biometric_sandbox_unit_');
    $temporaryFiles[] = $stateFile;
    $store = new JsonFileStore($stateFile, $timezone);
    $created = $store->appendTransactions($normal['events']);
    assertSame(2, count($created), 'Store should append both transactions.');
    assertSame(2, count($store->listTransactions()), 'Store should list appended transactions.');
    assertSame(
        2,
        count($store->listTransactions(['terminal_sn' => 'SIM-GATE-001'])),
        'Terminal filter should return both directions from the one device.'
    );
    assertSame(
        1,
        count($store->listTransactions(['start_time' => '2026-08-03 12:00:00'])),
        'Start-time filter should retain only checkout.'
    );

    $store->reset();
    $delayed = $factory->make(
        'delayed',
        'TBSW/ST/0002',
        '2026-08-03',
        'SIM-GATE-001',
        10,
        $now
    );
    $store->appendTransactions($delayed['events']);
    assertSame(1, count($store->listTransactions([], $now)), 'Delayed checkout should initially be invisible.');
    assertSame(
        2,
        count($store->listTransactions([], $now->modify('+11 seconds'))),
        'Delayed checkout should appear after its visibility time.'
    );

    $store->scheduleFailure(503, 2, 'maintenance');
    assertSame(1, $store->consumeFailure()['remaining'], 'First failure should leave one scheduled response.');
    assertSame(0, $store->consumeFailure()['remaining'], 'Second failure should exhaust the schedule.');
    assertSame(null, $store->consumeFailure(), 'Scheduled failure should be exhausted.');
}

function runHttpIntegrationTests(): void
{
    global $temporaryFiles, $serverProcess, $serverPipes;

    $stateFile = createTemporaryFile('biometric_sandbox_http_');
    $temporaryFiles[] = $stateFile;
    $socket = stream_socket_server('tcp://127.0.0.1:0', $errorNumber, $errorMessage);
    if ($socket === false) {
        throw new RuntimeException('Unable to reserve test port: ' . $errorMessage);
    }
    $address = (string) stream_socket_get_name($socket, false);
    fclose($socket);
    $port = (int) substr(strrchr($address, ':'), 1);

    $root = dirname(__DIR__);
    $command = [
        PHP_BINARY,
        '-S',
        '127.0.0.1:' . $port,
        '-t',
        $root . '/public',
        $root . '/public/router.php',
    ];
    $descriptors = [
        0 => ['pipe', 'r'],
        1 => ['pipe', 'w'],
        2 => ['pipe', 'w'],
    ];
    $environment = array_merge($_ENV, [
        'BIOMETRIC_SANDBOX_STATE' => $stateFile,
        'BIOMETRIC_SANDBOX_USERNAME' => 'integration-user',
        'BIOMETRIC_SANDBOX_PASSWORD' => 'integration-password',
        'BIOMETRIC_SANDBOX_TOKEN' => 'integration-token',
        'BIOMETRIC_SANDBOX_CONTROL_KEY' => 'integration-control-key',
    ]);
    $serverProcess = proc_open($command, $descriptors, $serverPipes, $root, $environment);
    if (!is_resource($serverProcess)) {
        throw new RuntimeException('Unable to start PHP sandbox server.');
    }
    foreach ([1, 2] as $pipeIndex) {
        stream_set_blocking($serverPipes[$pipeIndex], false);
    }

    $client = new HttpClient();
    $baseUrl = 'http://127.0.0.1:' . $port;
    $healthy = false;
    for ($attempt = 0; $attempt < 50; $attempt++) {
        try {
            $response = $client->request('GET', $baseUrl . '/health');
            if ($response['status'] === 200) {
                $healthy = true;
                break;
            }
        } catch (Throwable $error) {
            // The built-in server can take a few milliseconds to begin listening.
        }
        usleep(100000);
    }
    if (!$healthy) {
        $logs = stream_get_contents($serverPipes[1]) . stream_get_contents($serverPipes[2]);
        throw new RuntimeException('Sandbox HTTP server did not start. ' . trim($logs));
    }

    assertSame(200, $response['status'], 'Health endpoint should respond successfully.');
    assertSame(true, $response['json']['sandbox'] ?? null, 'Health response should identify itself as a sandbox.');

    $badAuth = $client->request('POST', $baseUrl . '/api-token-auth/', [
        'username' => 'integration-user',
        'password' => 'wrong',
    ]);
    assertSame(401, $badAuth['status'], 'Bad API credentials should be rejected.');

    $auth = $client->request('POST', $baseUrl . '/jwt-api-token-auth/', [
        'username' => 'integration-user',
        'password' => 'integration-password',
    ]);
    assertSame(200, $auth['status'], 'Valid API credentials should return a token.');
    assertSame('integration-token', $auth['json']['token'] ?? null, 'Token endpoint should return configured token.');

    $unauthorizedTransactions = $client->request('GET', $baseUrl . '/iclock/api/transactions/');
    assertSame(401, $unauthorizedTransactions['status'], 'Transactions should require token authentication.');

    $factory = new ScenarioFactory(new DateTimeZone('Africa/Lagos'));
    $normal = $factory->make(
        'normal',
        'TBSW/ST/0002',
        '2026-08-03',
        'SIM-GATE-001'
    );
    $inject = $client->request(
        'POST',
        $baseUrl . '/sandbox/events',
        ['events' => $normal['events']],
        ['X-Sandbox-Key: integration-control-key']
    );
    assertSame(201, $inject['status'], 'Control endpoint should accept synthetic events.');
    assertSame(2, $inject['json']['created'] ?? null, 'Control endpoint should create two events.');

    $page = $client->request(
        'GET',
        $baseUrl . '/iclock/api/transactions/?page_size=1&page=1',
        null,
        ['Authorization: JWT integration-token']
    );
    assertSame(200, $page['status'], 'JWT-style header should authorize transactions.');
    assertSame(2, $page['json']['count'] ?? null, 'Paginated response should report total count.');
    assertSame(1, count($page['json']['data'] ?? []), 'Page size should limit returned records.');
    assertTrue(is_string($page['json']['next'] ?? null), 'First page should include a next link.');

    $filtered = $client->request(
        'GET',
        $baseUrl . '/iclock/api/transactions/?terminal_sn=SIM-GATE-001',
        null,
        ['Authorization: Bearer integration-token']
    );
    assertSame(2, $filtered['json']['count'] ?? null, 'One-terminal query should return both explicit IN and OUT events.');

    $scheduleFailure = $client->request(
        'POST',
        $baseUrl . '/sandbox/fail-next',
        ['status' => 503, 'count' => 1, 'message' => 'test outage'],
        ['X-Sandbox-Key: integration-control-key']
    );
    assertSame(202, $scheduleFailure['status'], 'Failure control should schedule an outage.');
    $failure = $client->request(
        'GET',
        $baseUrl . '/iclock/api/transactions/',
        null,
        ['Authorization: Token integration-token']
    );
    assertSame(503, $failure['status'], 'Scheduled outage should affect the next poll.');
    $recovered = $client->request(
        'GET',
        $baseUrl . '/iclock/api/transactions/',
        null,
        ['Authorization: Token integration-token']
    );
    assertSame(200, $recovered['status'], 'Transactions endpoint should recover after one-shot outage.');

    $badReset = $client->request('POST', $baseUrl . '/sandbox/reset', []);
    assertSame(403, $badReset['status'], 'Reset should require the separate control key.');
    $reset = $client->request(
        'POST',
        $baseUrl . '/sandbox/reset',
        [],
        ['X-Sandbox-Key: integration-control-key']
    );
    assertSame(200, $reset['status'], 'Authorized reset should succeed.');
    $empty = $client->request(
        'GET',
        $baseUrl . '/iclock/api/transactions/',
        null,
        ['Authorization: Token integration-token']
    );
    assertSame(0, $empty['json']['count'] ?? null, 'Reset should remove all synthetic transactions.');
}

function createTemporaryFile(string $prefix): string
{
    $file = tempnam(sys_get_temp_dir(), $prefix);
    if ($file === false) {
        throw new RuntimeException('Unable to allocate temporary test file.');
    }

    return $file;
}

/** @param mixed $expected @param mixed $actual */
function assertSame($expected, $actual, string $message): void
{
    global $assertions;
    $assertions++;
    if ($expected !== $actual) {
        throw new RuntimeException(
            $message
            . PHP_EOL . 'Expected: ' . var_export($expected, true)
            . PHP_EOL . 'Actual:   ' . var_export($actual, true)
        );
    }
}

/** @param mixed $value */
function assertTrue($value, string $message): void
{
    assertSame(true, $value, $message);
}
