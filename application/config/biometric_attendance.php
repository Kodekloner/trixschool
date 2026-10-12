<?php

defined('BASEPATH') OR exit('No direct script access allowed');

// Production must terminate TLS at the application or a correctly configured
// trusted reverse proxy. Local development may use HTTP.
$config['biometric_require_https'] = defined('ENVIRONMENT') && ENVIRONMENT === 'production';
$config['biometric_max_payload_bytes'] = 262144;
$config['biometric_max_batch_events'] = 100;

// Existing gateways already heartbeat automatically. Drain a bounded alert
// batch after each authenticated successful heartbeat, without a laptop update.
// Hosting cron is still recommended for backlog delivery when gateways are off.
$config['biometric_automatic_notifications'] = true;
