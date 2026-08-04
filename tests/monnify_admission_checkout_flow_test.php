<?php

define('BASEPATH', __DIR__);

class MonnifyAdmissionRedirect extends Exception
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
    throw new MonnifyAdmissionRedirect($location);
}

function log_message($level, $message)
{
}

class Front_Controller
{
    public $paymentsetting_model;
    public $monnify_payment_model;
    public $monnify_gateway;
    public $onlinestudent_model;
}

class MonnifyAdmissionPaymentSettings
{
    public $active_type = 'monnify';

    public function getActiveMethod()
    {
        return (object) array('payment_type' => $this->active_type);
    }
}

class MonnifyAdmissionPayments
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
            'context_id' => 42,
            'gateway_mode' => 0,
            'status' => 'initialized',
        );
    }

    public function fulfillVerifiedPayment($payment_reference, $transaction)
    {
        return array('success' => true, 'status' => $this->fulfillment_status);
    }
}

class MonnifyAdmissionGateway
{
    public function verifyTransaction($payment_reference)
    {
        return array('success' => true, 'response' => array('responseBody' => array('paymentReference' => $payment_reference)));
    }
}

class MonnifyAdmissionStudents
{
    public function getAdmissionData($admission_id)
    {
        return (object) array('reference_no' => 'ADM-42');
    }
}

require_once __DIR__ . '/../application/controllers/onlineadmission/Monnify.php';

function monnify_admission_assert_same($expected, $actual, $message)
{
    if ($expected !== $actual) {
        fwrite(STDERR, $message . PHP_EOL . 'Expected: ' . var_export($expected, true) . PHP_EOL . 'Actual: ' . var_export($actual, true) . PHP_EOL);
        exit(1);
    }
}

function monnify_admission_redirect_from($callback)
{
    try {
        $callback();
    } catch (MonnifyAdmissionRedirect $redirect) {
        return $redirect->location;
    }

    return null;
}

$controller_reflection = new ReflectionClass('Monnify');
$controller = $controller_reflection->newInstanceWithoutConstructor();
$controller->pay_method = (object) array(
    'api_publishable_key' => 'test-key',
    'api_secret_key' => 'test-secret',
    'api_username' => 'test-contract',
    'gateway_mode' => 0,
);
$controller->paymentsetting_model = new MonnifyAdmissionPaymentSettings();
$controller->monnify_payment_model = new MonnifyAdmissionPayments();
$controller->monnify_gateway = new MonnifyAdmissionGateway();
$controller->onlinestudent_model = new MonnifyAdmissionStudents();

$can_start = new ReflectionMethod($controller, 'canStartPayment');
$can_start->setAccessible(true);

monnify_admission_assert_same(true, $can_start->invoke($controller), 'Monnify admission initiation should be available while Monnify is active and ready.');
$controller->paymentsetting_model->active_type = 'paystack';
monnify_admission_assert_same(false, $can_start->invoke($controller), 'A direct Monnify admission initiation must be rejected after another gateway becomes active.');

$controller->monnify_payment_model->fulfillment_status = 'processing';
$processing_redirect = monnify_admission_redirect_from(function () use ($controller) {
    $controller->complete('MNFY-OA-PROCESSING');
});
monnify_admission_assert_same('/onlineadmission/checkout/paymentfailed/ADM-42', $processing_redirect, 'An admission payment still being fulfilled must not be shown as successful.');

$controller->monnify_payment_model->fulfillment_status = 'processed';
$completed_redirect = monnify_admission_redirect_from(function () use ($controller) {
    $controller->complete('MNFY-OA-COMPLETE');
});
monnify_admission_assert_same('/onlineadmission/checkout/successinvoice/ADM-42', $completed_redirect, 'An existing admission payment must still complete after another gateway becomes active.');

echo "monnify admission checkout flow tests passed" . PHP_EOL;
