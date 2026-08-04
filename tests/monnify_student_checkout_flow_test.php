<?php

define('BASEPATH', __DIR__);

class MonnifyStudentRedirect extends Exception
{
    public $location;

    public function __construct($location)
    {
        parent::__construct($location);
        $this->location = $location;
    }
}

function base_url($path = '')
{
    return '/' . ltrim($path, '/');
}

function redirect($location)
{
    throw new MonnifyStudentRedirect($location);
}

function log_message($level, $message)
{
}

class Student_Controller
{
    public $paymentsetting_model;
    public $monnify_payment_model;
    public $monnify_gateway;
}

class MonnifyStudentPaymentSettings
{
    public $active_type = 'monnify';

    public function getActiveMethod()
    {
        return (object) array('payment_type' => $this->active_type);
    }
}

class MonnifyStudentPayments
{
    public $ready = true;
    public $fulfillment_status = 'processed';

    public function isReady()
    {
        return $this->ready;
    }

    public function findByPaymentReference($payment_reference)
    {
        return (object) array(
            'payment_reference' => $payment_reference,
            'gateway_mode' => 0,
            'status' => 'initialized',
        );
    }

    public function fulfillVerifiedPayment($payment_reference, $transaction)
    {
        return array('success' => true, 'status' => $this->fulfillment_status);
    }
}

class MonnifyStudentGateway
{
    public function verifyTransaction($payment_reference)
    {
        return array('success' => true, 'response' => array('responseBody' => array('paymentReference' => $payment_reference)));
    }
}

require_once __DIR__ . '/../application/controllers/students/Monnify.php';

function monnify_student_assert_same($expected, $actual, $message)
{
    if ($expected !== $actual) {
        fwrite(STDERR, $message . PHP_EOL . 'Expected: ' . var_export($expected, true) . PHP_EOL . 'Actual: ' . var_export($actual, true) . PHP_EOL);
        exit(1);
    }
}

function monnify_student_redirect_from($callback)
{
    try {
        $callback();
    } catch (MonnifyStudentRedirect $redirect) {
        return $redirect->location;
    }

    return null;
}

$controller_reflection = new ReflectionClass('Monnify');
$controller = $controller_reflection->newInstanceWithoutConstructor();
$controller->api_config = (object) array(
    'api_publishable_key' => 'test-key',
    'api_secret_key' => 'test-secret',
    'api_username' => 'test-contract',
    'gateway_mode' => 0,
);
$controller->paymentsetting_model = new MonnifyStudentPaymentSettings();
$controller->monnify_payment_model = new MonnifyStudentPayments();
$controller->monnify_gateway = new MonnifyStudentGateway();

$can_start = new ReflectionMethod($controller, 'canStartPayment');
$can_start->setAccessible(true);
$params = array('student_fees_master_array' => array(array('amount' => 100)));

monnify_student_assert_same(true, $can_start->invoke($controller, $params), 'Monnify initiation should be available while Monnify is active and ready.');
$controller->paymentsetting_model->active_type = 'paystack';
monnify_student_assert_same(false, $can_start->invoke($controller, $params), 'A direct Monnify initiation must be rejected after another gateway becomes active.');

$controller->monnify_payment_model->fulfillment_status = 'processing';
$processing_redirect = monnify_student_redirect_from(function () use ($controller) {
    $controller->complete('MNFY-SF-PROCESSING');
});
monnify_student_assert_same('/students/payment/paymentfailed', $processing_redirect, 'A payment still being fulfilled must not be shown as successful.');

$controller->monnify_payment_model->fulfillment_status = 'processed';
$completed_redirect = monnify_student_redirect_from(function () use ($controller) {
    $controller->complete('MNFY-SF-COMPLETE');
});
monnify_student_assert_same('/students/payment/successinvoice', $completed_redirect, 'An existing pending payment must still complete after another gateway becomes active.');

$controller_source = file_get_contents(__DIR__ . '/../application/controllers/students/Monnify.php');
$preflight_position = strpos($controller_source, 'validateStudentFeePayment');
$pending_position = strpos($controller_source, 'createPending');
$gateway_position = strpos($controller_source, 'initializeTransaction');
monnify_student_assert_same(true, $preflight_position !== false, 'Student checkout must invoke the server-side fee ownership preflight.');
monnify_student_assert_same(true, $preflight_position < $pending_position, 'Fee ownership preflight must run before a local pending payment is created.');
monnify_student_assert_same(true, $preflight_position < $gateway_position, 'Fee ownership preflight must run before Monnify checkout is initialized.');

echo "monnify student checkout flow tests passed" . PHP_EOL;
