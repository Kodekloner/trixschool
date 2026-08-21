<?php

declare(strict_types=1);

use SchoolLift\BiometricGateway\Config;
use SchoolLift\BiometricGateway\CurlHttpTransport;
use SchoolLift\BiometricGateway\EventNormalizer;
use SchoolLift\BiometricGateway\FileLock;
use SchoolLift\BiometricGateway\GatewayRunner;
use SchoolLift\BiometricGateway\GatewayStore;
use SchoolLift\BiometricGateway\JsonLogger;
use SchoolLift\BiometricGateway\SchoolLiftClient;
use SchoolLift\BiometricGateway\SystemClock;
use SchoolLift\BiometricGateway\ZkBioClient;

require_once dirname(__DIR__) . '/bootstrap.php';

$arguments = $argv;
array_shift($arguments);
$command = 'once';
if (isset($arguments[0]) && $arguments[0] !== '' && $arguments[0][0] !== '-') {
    $command = strtolower((string) array_shift($arguments));
}
$options = parseOptions($arguments);
if ($command === 'help' || isset($options['help'])) {
    usage(0);
}
if (!in_array($command, ['once', 'status', 'doctor', 'retry-dead'], true)) {
    fwrite(STDERR, 'Unknown gateway command: ' . $command . PHP_EOL);
    usage(2);
}

$configPath = (string) (
    $options['config']
    ?? getenv('SCHOOLLIFT_BIOMETRIC_CONFIG')
    ?: dirname(__DIR__) . '/config.php'
);

try {
    $config = Config::load($configPath);
    $clock = new SystemClock();
    $store = new GatewayStore((string) $config['database_path']);
    $logger = new JsonLogger(
        (string) $config['log_path'],
        $command === 'once' ? (bool) $config['log_to_stdout'] : false
    );

    if ($command === 'status') {
        output(['ok' => true, 'gateway_id' => $config['gateway_id'], 'queue' => $store->status()]);
        exit(0);
    }

    $lock = new FileLock((string) $config['lock_path']);
    if (!$lock->acquire()) {
        output(['ok' => true, 'skipped' => true, 'reason' => 'Another gateway process holds the lock.']);
        exit(0);
    }

    if ($command === 'retry-dead') {
        $count = $store->retryDead($clock->now());
        output(['ok' => true, 'retried' => $count, 'queue' => $store->status()]);
        exit(0);
    }

    $http = new CurlHttpTransport((bool) $config['verify_tls']);
    $provider = new ZkBioClient($http, $config['provider']);
    $schoolLift = new SchoolLiftClient($http, $config['schoollift'], (string) $config['gateway_id']);

    if ($command === 'doctor') {
        $checks = [
            'php_version' => PHP_VERSION,
            'pdo_sqlite' => extension_loaded('pdo_sqlite'),
            'curl' => extension_loaded('curl'),
            'openssl' => extension_loaded('openssl'),
            'database' => $store->status(),
            'terminal_serial' => $config['provider']['terminal_serial'],
        ];
        $ok = true;
        try {
            $source = $provider->fetchTransactions(
                $clock->now()->setTimezone(new DateTimeZone((string) $config['timezone']))
                    ->modify('-5 minutes')->format('Y-m-d H:i:s'),
                (int) $config['request_timeout_seconds']
            );
            $checks['provider'] = ['ok' => true, 'transactions_in_last_five_minutes' => count($source)];
        } catch (Throwable $error) {
            $ok = false;
            $checks['provider'] = ['ok' => false, 'error' => $error->getMessage()];
        }
        try {
            $health = $schoolLift->health((int) $config['request_timeout_seconds']);
            $reachable = $health['status'] >= 200 && $health['status'] <= 299;
            $acceptsGateway = is_array($health['json'])
                && ($health['json']['accepts_gateway_events'] ?? null) === true;
            $checks['schoollift'] = [
                'ok' => $reachable && $acceptsGateway,
                'http_status' => $health['status'],
                'operating_mode' => is_array($health['json']) ? ($health['json']['operating_mode'] ?? null) : null,
                'accepts_gateway_events' => $acceptsGateway,
            ];
            if ($reachable && !$acceptsGateway) {
                $checks['schoollift']['message'] = 'Use shadow or live mode before starting gateway synchronization.';
            }
            $ok = $ok && $checks['schoollift']['ok'];
        } catch (Throwable $error) {
            $ok = false;
            $checks['schoollift'] = ['ok' => false, 'error' => $error->getMessage()];
        }
        output(['ok' => $ok, 'checks' => $checks]);
        exit($ok ? 0 : 1);
    }

    $normalizer = new EventNormalizer((string) $config['timezone'], $config['provider']['verification_method_map']);
    $runner = new GatewayRunner($store, $provider, $schoolLift, $normalizer, $clock, $logger, $config);
    $result = $runner->runOnce();
    output($result);
    exit($result['ok'] ? 0 : 1);
} catch (Throwable $error) {
    fwrite(STDERR, json_encode([
        'ok' => false,
        'error' => $error->getMessage(),
    ], JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE) . PHP_EOL);
    exit(1);
}

/** @param array<int, string> $arguments @return array<string, string|bool> */
function parseOptions(array $arguments): array
{
    $options = [];
    foreach ($arguments as $argument) {
        if ($argument === '--help' || $argument === '-h') {
            $options['help'] = true;
            continue;
        }
        if (str_starts_with($argument, '--config=')) {
            $options['config'] = substr($argument, strlen('--config='));
            continue;
        }
        throw new InvalidArgumentException('Unknown option: ' . $argument);
    }
    return $options;
}

/** @param array<string, mixed> $data */
function output(array $data): void
{
    echo json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE) . PHP_EOL;
}

function usage(int $exitCode): void
{
    echo <<<'TXT'
SchoolLift biometric gateway

Usage:
  php bin/gateway.php once [--config=C:\secure\gateway-config.php]
  php bin/gateway.php doctor [--config=...]
  php bin/gateway.php status [--config=...]
  php bin/gateway.php retry-dead [--config=...]

Commands:
  once        Deliver queued events, poll ZKBio Time, then deliver new events.
  doctor      Check PHP, SQLite, ZKBio authentication/read access, and SchoolLift health.
  status      Show the durable cursor and queue counts without making network requests.
  retry-dead  Requeue events after the operator has corrected a permanent error.
TXT;
    echo PHP_EOL;
    exit($exitCode);
}
