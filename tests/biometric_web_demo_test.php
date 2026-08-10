<?php

/**
 * Dependency-free tests for the authenticated website biometric demo service.
 *
 * The service is intentionally exercised without CodeIgniter or a database.
 * If it develops a database/framework dependency, this test will fail early.
 *
 * Run from the repository root:
 *
 *     php tests/biometric_web_demo_test.php
 */

define('BASEPATH', __DIR__);

require_once dirname(__DIR__) . '/application/libraries/Biometricdemo_lib.php';

if (!function_exists('html_escape')) {
    function html_escape($value)
    {
        return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
    }
}

if (!function_exists('site_url')) {
    function site_url($path = '')
    {
        return 'https://demo.school.test/' . ltrim($path, '/');
    }
}

if (!function_exists('validation_errors')) {
    function validation_errors()
    {
        return '';
    }
}

$assertions = 0;

function demo_assert($condition, $message)
{
    global $assertions;
    $assertions++;
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

function demo_assert_same($expected, $actual, $message)
{
    demo_assert(
        $expected === $actual,
        $message . ' Expected ' . var_export($expected, true) . ', received ' . var_export($actual, true) . '.'
    );
}

function demo_run(Biometricdemo_lib $service, $scenario, array $overrides = array())
{
    return $service->simulate(array_merge(array(
        'scenario'      => $scenario,
        'employee'      => 'DEMO/STUDENT/0001',
        'event_date'    => '2026-08-10',
        'entry_serial'  => 'SIM-IN-001',
        'exit_serial'   => 'SIM-OUT-001',
        'delay_seconds' => 5,
    ), $overrides));
}

function demo_assert_throws(callable $callback, $message)
{
    try {
        $callback();
    } catch (InvalidArgumentException $error) {
        demo_assert(true, $message);
        return;
    }

    demo_assert(false, $message);
}

$service = new Biometricdemo_lib();
$scenarios = $service->scenarios();

demo_assert_same(10, count($scenarios), 'The website must expose every supported sandbox scenario.');
foreach (array('normal', 'duplicate', 'delayed', 'out-of-order', 'unknown-student', 'unknown-device', 'invalid-event', 'server-error', 'rate-limit', 'auth-error') as $name) {
    demo_assert(isset($scenarios[$name]), 'Missing scenario: ' . $name);
}

$normal = demo_run($service, 'normal');
demo_assert_same(true, $normal['sandbox'], 'The result must identify itself as sandbox output.');
demo_assert_same(false, $normal['persisted'], 'The demo must never claim to persist data.');
demo_assert_same(0, $normal['database_writes'], 'The demo must never write to a database.');
demo_assert_same(200, $normal['provider']['http_status'], 'Normal provider status mismatch.');
demo_assert_same(2, $normal['counts']['accepted'], 'Normal scenario should accept IN and OUT.');
demo_assert_same('07:30:00', $normal['attendance_preview']['entry_time'], 'Normal entry time mismatch.');
demo_assert_same('15:10:00', $normal['attendance_preview']['checkout_time'], 'Normal checkout time mismatch.');
demo_assert_same('Complete entry and checkout', $normal['attendance_preview']['status'], 'Normal summary mismatch.');

$duplicate = demo_run($service, 'duplicate');
demo_assert_same(3, $duplicate['counts']['received'], 'Duplicate scenario input count mismatch.');
demo_assert_same(2, $duplicate['counts']['accepted'], 'Duplicate scenario accepted count mismatch.');
demo_assert_same(1, $duplicate['counts']['duplicates'], 'Duplicate event must be ignored exactly once.');
demo_assert_same('Complete entry and checkout', $duplicate['attendance_preview']['status'], 'Duplicate scenario must still produce one complete day.');

$delayed = demo_run($service, 'delayed', array('delay_seconds' => 12));
demo_assert_same(2, count($delayed['delivery_batches']), 'Delayed scenario should have two polling batches.');
demo_assert_same(1, $delayed['delivery_batches'][0]['event_count'], 'First delayed poll should expose only entry.');
demo_assert_same(1, $delayed['delivery_batches'][1]['event_count'], 'Second delayed poll should expose checkout.');
demo_assert(strpos($delayed['delivery_batches'][1]['message'], '12 seconds') !== false, 'Delayed poll should display the configured wait.');
demo_assert_same('15:10:00', $delayed['attendance_preview']['checkout_time'], 'Delayed checkout must eventually reconcile.');

$out_of_order = demo_run($service, 'out-of-order');
demo_assert_same('OUT', $out_of_order['normalized_events'][1]['direction'], 'Normalized events must be ordered by occurrence time, not delivery order.');
demo_assert_same('07:30:00', $out_of_order['attendance_preview']['entry_time'], 'Out-of-order entry mismatch.');
demo_assert_same('15:10:00', $out_of_order['attendance_preview']['checkout_time'], 'Out-of-order checkout mismatch.');

$unknown_student = demo_run($service, 'unknown-student');
demo_assert_same(1, $unknown_student['counts']['quarantined'], 'Unknown student must be quarantined.');
demo_assert_same(0, $unknown_student['counts']['accepted'], 'Unknown student must not alter attendance.');
demo_assert_same('No attendance change', $unknown_student['attendance_preview']['status'], 'Unknown student summary mismatch.');

$unknown_device = demo_run($service, 'unknown-device');
demo_assert_same(1, $unknown_device['counts']['quarantined'], 'Unknown terminal must be quarantined.');
demo_assert_same(0, $unknown_device['counts']['accepted'], 'Unknown terminal must not alter attendance.');

$invalid = demo_run($service, 'invalid-event');
demo_assert_same(1, $invalid['counts']['rejected'], 'Malformed event must be rejected.');
demo_assert_same(0, $invalid['counts']['accepted'], 'Malformed event must not alter attendance.');

$server_error = demo_run($service, 'server-error');
demo_assert_same(503, $server_error['provider']['http_status'], 'Server error status mismatch.');
demo_assert_same(true, $server_error['provider']['retryable'], 'Server error should be retryable.');
demo_assert_same(0, $server_error['counts']['received'], 'Server error should deliver no event.');
demo_assert_same(200, $server_error['delivery_batches'][1]['http_status'], 'Server error retry should recover.');

$rate_limit = demo_run($service, 'rate-limit');
demo_assert_same(429, $rate_limit['provider']['http_status'], 'Rate-limit status mismatch.');
demo_assert_same(true, $rate_limit['provider']['retryable'], 'Rate-limit should be retryable.');
demo_assert(strpos($rate_limit['provider']['retry_message'], 'Back off') !== false, 'Rate-limit should require backoff.');

$auth_error = demo_run($service, 'auth-error');
demo_assert_same(401, $auth_error['provider']['http_status'], 'Authentication error status mismatch.');
demo_assert_same(false, $auth_error['provider']['retryable'], 'Authentication failure must not blindly retry.');
demo_assert_same(0, $auth_error['counts']['received'], 'Authentication failure should deliver no event.');

demo_assert_throws(function () use ($service) {
    demo_run($service, 'not-a-scenario');
}, 'Unknown scenarios must be rejected.');

demo_assert_throws(function () use ($service) {
    demo_run($service, 'normal', array('event_date' => '2026-02-31'));
}, 'Invalid dates must be rejected.');

demo_assert_throws(function () use ($service) {
    demo_run($service, 'normal', array('employee' => '<script>alert(1)</script>'));
}, 'Unsafe synthetic identifiers must be rejected.');

demo_assert_throws(function () use ($service) {
    demo_run($service, 'normal', array('delay_seconds' => 301));
}, 'Excessive delay must be rejected.');

$form = $service->defaults();
$result = $normal;
$biometric_demo_token = str_repeat('a', 64);
$simulation_error = '<script>alert("unsafe")</script>';
ob_start();
include dirname(__DIR__) . '/application/views/admin/biometricdemo/index.php';
$rendered = ob_get_clean();

demo_assert(strpos($rendered, 'Biometric Attendance Demo') !== false, 'The browser demonstration view must render.');
demo_assert(strpos($rendered, 'database writes = 0') !== false, 'The rendered view must disclose its zero-write boundary.');
demo_assert(strpos($rendered, '<script>alert("unsafe")</script>') === false, 'View errors must be escaped.');
demo_assert(strpos($rendered, '&lt;script&gt;alert(&quot;unsafe&quot;)&lt;/script&gt;') !== false, 'Escaped view error should remain visible.');

echo 'biometric web demo tests passed (' . $assertions . ' assertions)' . PHP_EOL;
