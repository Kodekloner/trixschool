<?php

declare(strict_types=1);

namespace SchoolLift\BiometricGateway;

use DateTimeZone;
use RuntimeException;
use Throwable;

final class Config
{
    /** @return array<string, mixed> */
    public static function load(string $path): array
    {
        if (!is_file($path)) {
            throw new RuntimeException(
                'Gateway configuration not found: ' . $path . '. Copy config.example.php to config.php.'
            );
        }
        $loaded = require $path;
        if (!is_array($loaded)) {
            throw new RuntimeException('Gateway configuration must return a PHP array.');
        }

        $root = dirname(realpath($path) ?: $path);
        $defaults = [
            'gateway_id' => '',
            'timezone' => 'Africa/Lagos',
            'database_path' => $root . '/var/gateway.sqlite',
            'lock_path' => $root . '/var/gateway.lock',
            'log_path' => $root . '/var/gateway.log',
            'log_to_stdout' => true,
            'verify_tls' => true,
            'allow_insecure_localhost' => false,
            'request_timeout_seconds' => 20,
            'provider' => [
                'base_url' => '',
                'username' => '',
                'password' => '',
                'auth_path' => '/api-token-auth/',
                'auth_body_format' => 'json',
                'token_field' => 'token',
                'authorization_scheme' => 'Token',
                'transactions_path' => '/iclock/api/transactions/',
                'data_field' => 'data',
                'terminal_serial' => '',
                'page_size' => 100,
                'max_pages' => 50,
                'overlap_seconds' => 172800,
                'initial_lookback_seconds' => 172800,
                'verification_method_map' => [
                    '0' => 'pin',
                    '1' => 'fingerprint',
                    '2' => 'card',
                    '4' => 'face',
                    '15' => 'face',
                ],
            ],
            'schoollift' => [
                'base_url' => '',
                'bearer_token' => '',
                'events_path' => '/api/biometric/v2/events',
                'health_path' => '/api/biometric/v2/health',
                'batch_size' => 100,
                'max_batches_per_run' => 10,
            ],
            'retry' => [
                'base_seconds' => 5,
                'maximum_seconds' => 900,
                'maximum_attempts' => 12,
                'provider_base_seconds' => 15,
                'provider_maximum_seconds' => 900,
            ],
            'delivered_retention_days' => 30,
        ];
        $config = array_replace_recursive($defaults, $loaded);
        self::validate($config);

        return $config;
    }

    /** @param array<string, mixed> $config */
    private static function validate(array $config): void
    {
        foreach (['pdo_sqlite', 'curl', 'json', 'openssl'] as $extension) {
            if (!extension_loaded($extension)) {
                throw new RuntimeException('Required PHP extension is missing: ' . $extension);
            }
        }
        if (!preg_match('/^[A-Za-z0-9][A-Za-z0-9._-]{2,127}$/', (string) $config['gateway_id'])) {
            throw new RuntimeException('gateway_id must be a stable 3-128 character identifier.');
        }
        foreach ([
            'provider.base_url' => $config['provider']['base_url'],
            'provider.username' => $config['provider']['username'],
            'provider.password' => $config['provider']['password'],
            'provider.terminal_serial' => $config['provider']['terminal_serial'],
            'schoollift.base_url' => $config['schoollift']['base_url'],
            'schoollift.bearer_token' => $config['schoollift']['bearer_token'],
        ] as $name => $value) {
            if (!is_string($value) || trim($value) === '') {
                throw new RuntimeException('Required gateway setting is empty: ' . $name);
            }
        }
        foreach (['provider' => $config['provider']['base_url'], 'schoollift' => $config['schoollift']['base_url']] as $name => $url) {
            self::validateBaseUrl((string) $url, $name, (bool) $config['allow_insecure_localhost']);
        }
        if (!$config['verify_tls']
            && (!(bool) $config['allow_insecure_localhost']
                || !self::isLoopbackUrl((string) $config['provider']['base_url'])
                || !self::isLoopbackUrl((string) $config['schoollift']['base_url']))) {
            throw new RuntimeException('TLS verification may be disabled only when both endpoints are explicit loopback tests.');
        }
        try {
            new DateTimeZone((string) $config['timezone']);
        } catch (Throwable $error) {
            throw new RuntimeException('timezone must be a valid PHP timezone identifier.');
        }
        if (!in_array($config['provider']['auth_body_format'], ['json', 'form'], true)) {
            throw new RuntimeException('provider.auth_body_format must be json or form.');
        }
        if (!preg_match('/^(?:Token|Bearer|JWT)$/', (string) $config['provider']['authorization_scheme'])) {
            throw new RuntimeException('provider.authorization_scheme must be Token, Bearer or JWT.');
        }
        if (!preg_match('/^[A-Za-z0-9._:-]{1,100}$/', (string) $config['provider']['terminal_serial'])) {
            throw new RuntimeException('provider.terminal_serial contains unsupported characters or exceeds 100 characters.');
        }
        if (strlen((string) $config['schoollift']['bearer_token']) < 32
            || preg_match('/[\x00-\x1F\x7F]/', (string) $config['schoollift']['bearer_token'])) {
            throw new RuntimeException('schoollift.bearer_token must be a high-entropy token without control characters.');
        }
        if ((string) $config['provider']['terminal_serial'] === 'SIM-IN-001'
            || (string) $config['provider']['terminal_serial'] === 'SIM-OUT-001') {
            throw new RuntimeException('Use one bidirectional serial, for example SIM-GATE-001; fixed IN/OUT serials are unsupported.');
        }
        if (!is_array($config['provider']['verification_method_map'])) {
            throw new RuntimeException('provider.verification_method_map must be an array.');
        }
        foreach ([
            'provider.page_size' => [$config['provider']['page_size'], 1, 1000],
            'provider.max_pages' => [$config['provider']['max_pages'], 1, 1000],
            'provider.overlap_seconds' => [$config['provider']['overlap_seconds'], 60, 2592000],
            'provider.initial_lookback_seconds' => [$config['provider']['initial_lookback_seconds'], 60, 2592000],
            'schoollift.batch_size' => [$config['schoollift']['batch_size'], 1, 100],
            'schoollift.max_batches_per_run' => [$config['schoollift']['max_batches_per_run'], 1, 100],
            'request_timeout_seconds' => [$config['request_timeout_seconds'], 1, 120],
            'retry.base_seconds' => [$config['retry']['base_seconds'], 1, 3600],
            'retry.maximum_seconds' => [$config['retry']['maximum_seconds'], 1, 86400],
            'retry.maximum_attempts' => [$config['retry']['maximum_attempts'], 1, 100],
            'retry.provider_base_seconds' => [$config['retry']['provider_base_seconds'], 1, 3600],
            'retry.provider_maximum_seconds' => [$config['retry']['provider_maximum_seconds'], 1, 86400],
        ] as $name => [$value, $minimum, $maximum]) {
            if (!is_int($value) || $value < $minimum || $value > $maximum) {
                throw new RuntimeException($name . ' must be between ' . $minimum . ' and ' . $maximum . '.');
            }
        }
        if ($config['retry']['maximum_seconds'] < $config['retry']['base_seconds']
            || $config['retry']['provider_maximum_seconds'] < $config['retry']['provider_base_seconds']) {
            throw new RuntimeException('Retry maximum seconds must be greater than or equal to the corresponding base.');
        }
        if (!is_int($config['delivered_retention_days'])
            || $config['delivered_retention_days'] < 0
            || $config['delivered_retention_days'] > 3650) {
            throw new RuntimeException('delivered_retention_days must be between 0 and 3650.');
        }
    }

    private static function validateBaseUrl(string $url, string $name, bool $allowLocalHttp): void
    {
        $parts = parse_url($url);
        $scheme = strtolower((string) ($parts['scheme'] ?? ''));
        $host = strtolower((string) ($parts['host'] ?? ''));
        if ($host === '' || !in_array($scheme, ['http', 'https'], true)) {
            throw new RuntimeException($name . '.base_url must be an absolute HTTP(S) URL.');
        }
        if (isset($parts['user']) || isset($parts['pass']) || isset($parts['query']) || isset($parts['fragment'])) {
            throw new RuntimeException($name . '.base_url must not contain credentials, a query, or a fragment.');
        }
        $loopback = in_array($host, ['127.0.0.1', 'localhost', '::1'], true);
        if ($scheme !== 'https' && !($allowLocalHttp && $loopback)) {
            throw new RuntimeException($name . '.base_url must use HTTPS; HTTP is allowed only for an explicitly enabled loopback test.');
        }
    }

    private static function isLoopbackUrl(string $url): bool
    {
        $host = strtolower((string) (parse_url($url, PHP_URL_HOST) ?: ''));
        return in_array($host, ['127.0.0.1', 'localhost', '::1'], true);
    }
}
