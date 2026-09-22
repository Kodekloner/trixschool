<?php

declare(strict_types=1);

use SchoolLift\BiometricGateway\Config;
use SchoolLift\BiometricGateway\CurlHttpTransport;
use SchoolLift\BiometricGateway\EventNormalizer;
use SchoolLift\BiometricGateway\FileLock;
use SchoolLift\BiometricGateway\GatewayControl;
use SchoolLift\BiometricGateway\GatewayDiagnostics;
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
    $diagnostics = new GatewayDiagnostics($store, $provider, $schoolLift, $clock, $config);

    if ($command === 'doctor') {
        $result = $diagnostics->run();
        output($result);
        exit($result['ok'] ? 0 : 1);
    }

    $normalizer = new EventNormalizer((string) $config['timezone'], $config['provider']['verification_method_map']);
    $runner = new GatewayRunner($store, $provider, $schoolLift, $normalizer, $clock, $logger, $config);
    $control = new GatewayControl($store, $schoolLift, $clock, (int) $config['request_timeout_seconds']);
    $controlSummary = ['claimed' => null, 'reported' => false, 'warnings' => []];

    // A result survives a process or network failure in SQLite. Report it
    // before asking for another command, so commands cannot overtake each other.
    try {
        $controlSummary['reported'] = $control->reportPending();
    } catch (Throwable $error) {
        controlWarning($logger, $controlSummary, 'Could not report the previous website command result.', $error);
    }

    try {
        $claimed = $control->poll(!$store->hasOutstandingCommand());
        $controlSummary['claimed'] = is_array($claimed) ? ($claimed['command_type'] ?? null) : null;
    } catch (Throwable $error) {
        // Control-plane availability must never stop the durable attendance
        // synchronization that Task Scheduler launched.
        controlWarning($logger, $controlSummary, 'Website command polling was unavailable.', $error);
    }

    $claimed = $control->claimedCommand();
    $commandType = is_array($claimed) ? (string) ($claimed['command_type'] ?? '') : '';
    $commandResult = null;
    $retried = null;
    if ($commandType === 'connection_test') {
        $commandResult = $diagnostics->run();
    } elseif ($commandType === 'retry_failed') {
        $retried = $store->retryDead($clock->now());
    }

    $result = $runner->runOnce();
    $store->recordSyncResult($result, $clock->now());

    if (is_array($claimed)) {
        $safeRun = safeRunSummary($result, $store->status());
        if ($commandType === 'connection_test' && is_array($commandResult)) {
            $succeeded = ($commandResult['ok'] ?? false) === true;
            $websiteResult = $diagnostics->safeResult($commandResult);
        } elseif ($commandType === 'sync_now') {
            $succeeded = ($result['ok'] ?? false) === true;
            $websiteResult = [
                'summary' => $safeRun,
                'queue' => safeQueue($store->status()),
                'message' => $succeeded
                    ? 'Synchronization completed.'
                    : 'Synchronization ran, but one or more provider or delivery steps failed.',
            ];
        } elseif ($commandType === 'retry_failed') {
            $succeeded = ($result['ok'] ?? false) === true;
            $websiteResult = [
                'retried' => (int) $retried,
                'summary' => $safeRun,
                'queue' => safeQueue($store->status()),
                'message' => $succeeded
                    ? 'Failed queue records were released and synchronization completed.'
                    : 'Failed queue records were released, but synchronization still needs attention.',
            ];
        } else {
            // This branch is defensive; GatewayControl never accepts another
            // type, and no command text is executed under any circumstance.
            $succeeded = false;
            $websiteResult = ['message' => 'The stored command type is not supported by this gateway.'];
        }
        $control->complete((string) $claimed['command_uuid'], $succeeded, $websiteResult);
        try {
            $controlSummary['reported'] = $control->reportPending() || $controlSummary['reported'];
        } catch (Throwable $error) {
            controlWarning($logger, $controlSummary, 'The website command result was saved locally for retry.', $error);
        }
    }

    // The end-of-run poll is a heartbeat only. `false` is sent explicitly so
    // it cannot claim a second command in the same scheduled execution.
    try {
        $control->poll(false);
    } catch (Throwable $error) {
        controlWarning($logger, $controlSummary, 'The final website heartbeat was unavailable.', $error);
    }
    $result['control'] = $controlSummary;
    output($result);
    exit($result['ok'] ? 0 : 1);
} catch (Throwable $error) {
    fwrite(STDERR, json_encode([
        'ok' => false,
        'error' => $error->getMessage(),
    ], JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE) . PHP_EOL);
    exit(1);
}

/** @param array<string, mixed> $controlSummary */
function controlWarning(
    JsonLogger $logger,
    array &$controlSummary,
    string $message,
    Throwable $error
): void {
    $detail = boundedMessage($error->getMessage());
    $controlSummary['warnings'][] = $message . ' ' . $detail;
    $logger->log('warning', $message, ['error' => $detail]);
}

/** @param array<string, mixed> $result @param array<string, mixed> $queue
 *  @return array<string, mixed>
 */
function safeRunSummary(array $result, array $queue): array
{
    $provider = isset($result['provider']) && is_array($result['provider']) ? $result['provider'] : [];
    $delivery = isset($result['delivery']) && is_array($result['delivery']) ? $result['delivery'] : [];
    return [
        'ok' => ($result['ok'] ?? false) === true,
        'started_at' => isset($result['started_at']) ? (string) $result['started_at'] : null,
        'finished_at' => isset($result['finished_at']) ? (string) $result['finished_at'] : null,
        'provider_polled' => ($provider['polled'] ?? false) === true,
        'provider_received' => (int) ($provider['received'] ?? 0),
        'provider_inserted' => (int) ($provider['inserted'] ?? 0),
        'provider_duplicates' => (int) ($provider['duplicates'] ?? 0),
        'delivered' => (int) ($delivery['delivered'] ?? 0),
        'deferred' => (int) ($delivery['deferred'] ?? 0),
        'dead_this_run' => (int) ($delivery['dead'] ?? 0),
        'pending_after_run' => (int) ($queue['pending'] ?? 0),
        'dead_after_run' => (int) ($queue['dead'] ?? 0),
        'error' => isset($delivery['error']) && $delivery['error'] !== null
            ? boundedMessage((string) $delivery['error'])
            : (isset($provider['error']) ? boundedMessage((string) $provider['error']) : null),
    ];
}

/** @param array<string, mixed> $queue @return array<string, int|string|null> */
function safeQueue(array $queue): array
{
    return [
        'cursor' => isset($queue['provider_cursor']) && $queue['provider_cursor'] !== ''
            ? (string) $queue['provider_cursor'] : null,
        'pending' => (int) ($queue['pending'] ?? 0),
        'retry' => (int) ($queue['retry'] ?? 0),
        'dead' => (int) ($queue['dead'] ?? 0),
    ];
}

function boundedMessage(string $message): string
{
    $message = preg_replace('/[\x00-\x1F\x7F]+/', ' ', $message) ?? '';
    return function_exists('mb_substr') ? mb_substr($message, 0, 500) : substr($message, 0, 500);
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
