<?php

declare(strict_types=1);

/*
 * Copy this file to config.php. Keep config.php outside source control and
 * restrict its Windows ACL to the gateway service account and administrators.
 */
return [
    'gateway_id' => 'school-main-gateway-01',
    'timezone' => 'Africa/Lagos',
    'database_path' => __DIR__ . '/var/gateway.sqlite',
    'lock_path' => __DIR__ . '/var/gateway.lock',
    'log_path' => __DIR__ . '/var/gateway.log',
    'verify_tls' => true,
    // ZKBio Time commonly exposes its API only on this same Windows PC. This
    // exception never permits another LAN/public HTTP host; SchoolLift remains
    // HTTPS and verify_tls remains enabled.
    'allow_insecure_localhost' => true,

    'provider' => [
        'base_url' => 'http://127.0.0.1:8098',
        'username' => getenv('ZKBIO_GATEWAY_USERNAME') ?: '',
        'password' => getenv('ZKBIO_GATEWAY_PASSWORD') ?: '',
        'auth_path' => '/api-token-auth/',
        'auth_body_format' => 'json',
        'token_field' => 'token',
        'authorization_scheme' => 'Token',
        'transactions_path' => '/iclock/api/transactions/',
        'employees_path' => '/personnel/api/employees/',
        'departments_path' => '/personnel/api/departments/',
        'positions_path' => '/personnel/api/positions/',
        'areas_path' => '/personnel/api/areas/',
        'resync_path' => '/personnel/api/employees/resync_to_device/',
        // Use the ZKBio area that owns the school's terminal. Leave empty only
        // while running Preview; Active synchronization requires at least one.
        'area_ids' => [1],
        'resync_to_device' => true,

        // One physical terminal handles both directions. Direction comes only
        // from the explicit punch state selected by the user on that terminal.
        'terminal_serial' => 'SCHOOL-GATE-001',
        'verification_method_map' => [
            '0' => 'pin',
            '1' => 'fingerprint',
            '2' => 'card',
            '4' => 'face',
            '15' => 'face',
        ],
        'page_size' => 100,
        'max_pages' => 50,
        'overlap_seconds' => 172800,
        'initial_lookback_seconds' => 172800,
    ],

    'schoollift' => [
        'base_url' => 'https://school.example.edu.ng',
        'bearer_token' => getenv('SCHOOLLIFT_BIOMETRIC_TOKEN') ?: '',
        'events_path' => '/api/biometric/v2/events',
        'health_path' => '/api/biometric/v2/health',
        'control_poll_path' => '/api/biometric/v2/gateway/poll',
        'control_result_path' => '/api/biometric/v2/gateway/result',
        'directory_path' => '/api/biometric/v2/directory',
        'directory_result_path' => '/api/biometric/v2/directory/result',
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
    'directory' => [
        'default_interval_seconds' => 300,
        'maximum_delete_count' => 10,
        'maximum_delete_percent' => 10,
    ],
    'delivered_retention_days' => 30,
];
