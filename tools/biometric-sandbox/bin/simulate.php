<?php

declare(strict_types=1);

use SchoolLift\BiometricSandbox\HttpClient;
use SchoolLift\BiometricSandbox\ScenarioFactory;

require_once dirname(__DIR__) . '/bootstrap.php';

$options = getopt('', [
    'base-url:',
    'scenario:',
    'employee:',
    'date:',
    'entry-serial:',
    'exit-serial:',
    'delay-seconds:',
    'username:',
    'password:',
    'sandbox-key:',
    'keep-state',
    'allow-remote',
    'list',
    'help',
]);

if (isset($options['help'])) {
    usage(0);
}
if (isset($options['list'])) {
    echo implode(PHP_EOL, ScenarioFactory::names()) . PHP_EOL;
    exit(0);
}

$baseUrl = rtrim((string) ($options['base-url'] ?? 'http://127.0.0.1:8787'), '/');
$scenarioName = (string) ($options['scenario'] ?? 'normal');
$employee = (string) ($options['employee'] ?? 'TBSW/ST/0002');
$timezone = new DateTimeZone('Africa/Lagos');
$date = (string) ($options['date'] ?? (new DateTimeImmutable('now', $timezone))->format('Y-m-d'));
$entrySerial = (string) ($options['entry-serial'] ?? 'SIM-IN-001');
$exitSerial = (string) ($options['exit-serial'] ?? 'SIM-OUT-001');
$delaySeconds = (int) ($options['delay-seconds'] ?? 5);
$username = (string) ($options['username'] ?? 'sandbox_admin');
$password = (string) ($options['password'] ?? 'sandbox_password');
$sandboxKey = (string) ($options['sandbox-key'] ?? 'sandbox-control-key');

$factory = new ScenarioFactory($timezone);
try {
    assertSafeBaseUrl($baseUrl, isset($options['allow-remote']));

    $scenario = $factory->make(
        $scenarioName,
        $employee,
        $date,
        $entrySerial,
        $exitSerial,
        $delaySeconds
    );

    $client = new HttpClient();
    $health = $client->request('GET', $baseUrl . '/health');
    requireStatus($health, 200, 'The sandbox server is not healthy');

    if (!isset($options['keep-state'])) {
        $reset = $client->request(
            'POST',
            $baseUrl . '/sandbox/reset',
            [],
            ['X-Sandbox-Key: ' . $sandboxKey]
        );
        requireStatus($reset, 200, 'Unable to reset the mock state');
    }

    if ($scenario['auth_error']) {
        $response = $client->request('POST', $baseUrl . '/api-token-auth/', [
            'username' => $username,
            'password' => $password . '-intentionally-wrong',
        ]);
        requireStatus($response, 401, 'The auth-error scenario did not receive the expected rejection');
        printResult($scenarioName, $scenario['description'], ['authentication_response' => summarize($response)]);
        exit(0);
    }

    if ($scenario['events'] !== []) {
        $injected = $client->request(
            'POST',
            $baseUrl . '/sandbox/events',
            ['events' => $scenario['events']],
            ['X-Sandbox-Key: ' . $sandboxKey]
        );
        requireStatus($injected, 201, 'Unable to inject scenario events');
    } else {
        $injected = null;
    }

    if ($scenario['failure'] !== null) {
        $scheduled = $client->request(
            'POST',
            $baseUrl . '/sandbox/fail-next',
            $scenario['failure'],
            ['X-Sandbox-Key: ' . $sandboxKey]
        );
        requireStatus($scheduled, 202, 'Unable to schedule the API failure');
    }

    $auth = $client->request('POST', $baseUrl . '/api-token-auth/', [
        'username' => $username,
        'password' => $password,
    ]);
    requireStatus($auth, 200, 'Unable to obtain a mock API token');
    $token = is_array($auth['json']) ? (string) ($auth['json']['token'] ?? '') : '';
    if ($token === '') {
        throw new RuntimeException('Mock token response did not contain token.');
    }

    $transactions = $client->request(
        'GET',
        $baseUrl . '/iclock/api/transactions/?page_size=100',
        null,
        ['Authorization: Token ' . $token]
    );

    $details = [
        'injected' => $injected === null ? null : summarize($injected),
        'transactions_response' => summarize($transactions),
    ];

    if ($scenario['failure'] !== null) {
        requireStatus(
            $transactions,
            (int) $scenario['failure']['status'],
            'The transactions endpoint did not return the scheduled failure'
        );
        $retry = $client->request(
            'GET',
            $baseUrl . '/iclock/api/transactions/?page_size=100',
            null,
            ['Authorization: Token ' . $token]
        );
        requireStatus($retry, 200, 'The endpoint did not recover after its one-shot failure');
        $details['retry_response'] = summarize($retry);
    } else {
        requireStatus($transactions, 200, 'Unable to read the mock transactions');
    }

    printResult($scenarioName, $scenario['description'], $details);
} catch (Throwable $error) {
    fwrite(STDERR, 'Simulation failed: ' . $error->getMessage() . PHP_EOL);
    exit(1);
}

function usage(int $exitCode): void
{
    $scenarios = implode(', ', ScenarioFactory::names());
    echo <<<TXT
Usage:
  php bin/simulate.php --scenario=normal [options]

Options:
  --base-url=http://127.0.0.1:8787
  --employee=TBSW/ST/0002
  --date=YYYY-MM-DD
  --entry-serial=SIM-IN-001
  --exit-serial=SIM-OUT-001
  --delay-seconds=5
  --keep-state                 Do not reset mock state before this run
  --allow-remote               Permit a non-loopback test server explicitly
  --list                       Print scenario names

Scenarios: {$scenarios}
TXT;
    echo PHP_EOL;
    exit($exitCode);
}

function assertSafeBaseUrl(string $url, bool $allowRemote): void
{
    $parts = parse_url($url);
    $host = is_array($parts) ? (string) ($parts['host'] ?? '') : '';
    $scheme = is_array($parts) ? (string) ($parts['scheme'] ?? '') : '';
    if (!in_array($scheme, ['http', 'https'], true) || $host === '') {
        throw new RuntimeException('The base URL must be an absolute HTTP(S) URL.');
    }
    if (!$allowRemote && !in_array(strtolower($host), ['127.0.0.1', 'localhost', '::1'], true)) {
        throw new RuntimeException(
            'Refusing a non-loopback target. Use --allow-remote only for an intentional test environment.'
        );
    }
}

/** @param array{status: int, body: string} $response */
function requireStatus(array $response, int $expected, string $message): void
{
    if ($response['status'] !== $expected) {
        throw new RuntimeException(
            $message . '; expected HTTP ' . $expected . ', got ' . $response['status'] . ': ' . $response['body']
        );
    }
}

/** @param array{status: int, json: mixed, body: string} $response @return array<string, mixed> */
function summarize(array $response): array
{
    return [
        'status' => $response['status'],
        'body' => is_array($response['json']) ? $response['json'] : $response['body'],
    ];
}

/** @param array<string, mixed> $details */
function printResult(string $name, string $description, array $details): void
{
    $result = [
        'scenario' => $name,
        'description' => $description,
        'result' => $details,
    ];
    echo json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;
}
