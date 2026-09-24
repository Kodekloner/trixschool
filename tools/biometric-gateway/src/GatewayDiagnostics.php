<?php

declare(strict_types=1);

namespace SchoolLift\BiometricGateway;

use DateTimeImmutable;
use DateTimeZone;
use Throwable;

final class GatewayDiagnostics
{
    private GatewayStore $store;
    private ZkBioClient $provider;
    private SchoolLiftClient $schoolLift;
    private Clock $clock;
    /** @var array<string, mixed> */
    private array $config;

    /** @param array<string, mixed> $config */
    public function __construct(
        GatewayStore $store,
        ZkBioClient $provider,
        SchoolLiftClient $schoolLift,
        Clock $clock,
        array $config
    ) {
        $this->store = $store;
        $this->provider = $provider;
        $this->schoolLift = $schoolLift;
        $this->clock = $clock;
        $this->config = $config;
    }

    /** @return array<string, mixed> */
    public function run(): array
    {
        $checks = [
            'php_version' => PHP_VERSION,
            'pdo_sqlite' => extension_loaded('pdo_sqlite'),
            'curl' => extension_loaded('curl'),
            'openssl' => extension_loaded('openssl'),
            'database' => $this->store->status(),
            'terminal_serial' => (string) $this->config['provider']['terminal_serial'],
        ];
        $ok = true;
        try {
            $source = $this->provider->fetchTransactions(
                $this->clock->now()->setTimezone(new DateTimeZone((string) $this->config['timezone']))
                    ->modify('-5 minutes')->format('Y-m-d H:i:s'),
                (int) $this->config['request_timeout_seconds']
            );
            $checks['provider'] = [
                'ok' => true,
                'transactions_in_last_five_minutes' => count($source),
            ];
        } catch (Throwable $error) {
            $ok = false;
            $checks['provider'] = ['ok' => false, 'error' => $this->bounded($error->getMessage())];
        }

        try {
            $health = $this->schoolLift->health((int) $this->config['request_timeout_seconds']);
            $reachable = $health['status'] >= 200 && $health['status'] <= 299;
            $acceptsGateway = is_array($health['json'])
                && ($health['json']['accepts_gateway_events'] ?? null) === true;
            $checks['schoollift'] = [
                'ok' => $reachable && $acceptsGateway,
                'http_status' => $health['status'],
                'operating_mode' => is_array($health['json'])
                    ? ($health['json']['operating_mode'] ?? null) : null,
                'accepts_gateway_events' => $acceptsGateway,
            ];
            if ($reachable && !$acceptsGateway) {
                $checks['schoollift']['message'] = 'Use Shadow or Live mode before starting gateway synchronization.';
            }
            $ok = $ok && $checks['schoollift']['ok'];
        } catch (Throwable $error) {
            $ok = false;
            $checks['schoollift'] = ['ok' => false, 'error' => $this->bounded($error->getMessage())];
        }

        return ['ok' => $ok, 'checks' => $checks, 'checked_at' => $this->clock->now()->format(DATE_ATOM)];
    }

    /** @return array<string, mixed> */
    public function safeResult(array $diagnostics): array
    {
        $checks = isset($diagnostics['checks']) && is_array($diagnostics['checks'])
            ? $diagnostics['checks'] : [];
        return [
            'summary' => [
                'ok' => ($diagnostics['ok'] ?? false) === true,
                'checked_at' => isset($diagnostics['checked_at'])
                    ? (string) $diagnostics['checked_at'] : (new DateTimeImmutable('now'))->format(DATE_ATOM),
            ],
            'provider' => $this->safeSection($checks['provider'] ?? []),
            'schoollift' => $this->safeSection($checks['schoollift'] ?? []),
            'queue' => $this->safeSection($this->store->status()),
            'message' => ($diagnostics['ok'] ?? false) === true
                ? 'The gateway can reach ZKBio Time and SchoolLift.'
                : 'One or more connection checks failed. Review the safe details shown here.',
        ];
    }

    /** @param mixed $value @return array<string, mixed> */
    private function safeSection($value): array
    {
        if (!is_array($value)) {
            return [];
        }
        $safe = [];
        foreach (array_slice($value, 0, 30, true) as $key => $item) {
            if (is_bool($item) || is_int($item) || is_float($item) || $item === null) {
                $safe[(string) $key] = $item;
            } elseif (is_string($item)) {
                $safe[(string) $key] = $this->bounded($item);
            }
        }
        return $safe;
    }

    private function bounded(string $message): string
    {
        $message = preg_replace('/[\x00-\x1F\x7F]+/', ' ', $message) ?? '';
        return function_exists('mb_substr') ? mb_substr($message, 0, 500) : substr($message, 0, 500);
    }
}
