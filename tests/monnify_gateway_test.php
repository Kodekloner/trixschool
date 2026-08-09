<?php

define('BASEPATH', __DIR__);
require_once __DIR__ . '/../application/libraries/Monnify_gateway.php';

function monnify_assert_same($expected, $actual, $message)
{
    if ($expected !== $actual) {
        fwrite(STDERR, $message . PHP_EOL);
        exit(1);
    }
}

$sandbox = new Monnify_gateway(array(
    'api_key' => 'MK_TEST_EXAMPLE',
    'secret_key' => 'test-secret',
    'contract_code' => '1234567890',
    'gateway_mode' => 0,
));

monnify_assert_same(Monnify_gateway::SANDBOX_BASE_URL, $sandbox->getBaseUrl(), 'Test mode must use the Monnify sandbox URL.');
monnify_assert_same(true, $sandbox->isValidWebhookSignature('{"test":true}', ''), 'Sandbox webhooks may omit the signature.');

$live = new Monnify_gateway(array(
    'api_key' => 'MK_PROD_EXAMPLE',
    'secret_key' => 'live-secret',
    'contract_code' => '1234567890',
    'gateway_mode' => 1,
));

$payload = '{"eventType":"SUCCESSFUL_TRANSACTION"}';
$signature = hash_hmac('sha512', $payload, 'live-secret');

monnify_assert_same(Monnify_gateway::LIVE_BASE_URL, $live->getBaseUrl(), 'Live mode must use the Monnify production URL.');
monnify_assert_same(false, $live->isValidWebhookSignature($payload, ''), 'Live webhooks must include a signature.');
monnify_assert_same(true, $live->isValidWebhookSignature($payload, $signature), 'A valid live webhook signature must be accepted.');
monnify_assert_same(false, $live->isValidWebhookSignature($payload . 'x', $signature), 'A modified webhook payload must be rejected.');

echo "monnify gateway tests passed" . PHP_EOL;
