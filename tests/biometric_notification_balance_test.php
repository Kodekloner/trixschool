<?php

define('BASEPATH', __DIR__);

function &get_instance()
{
    $instance = &$GLOBALS['biometric_notification_balance_ci'];
    return $instance;
}

require_once __DIR__ . '/../application/libraries/Biometric_attendance_service.php';

class BiometricNotificationBalanceLoader
{
    public function model($name) { return true; }
}

class BiometricNotificationBalanceFeeModel
{
    public $fees = array();
    public function getStudentFees($studentSessionId) { return $this->fees; }
}

class BiometricNotificationBalanceCi
{
    public $load;
    public $studentfeemaster_model;
    public function __construct()
    {
        $this->load = new BiometricNotificationBalanceLoader();
        $this->studentfeemaster_model = new BiometricNotificationBalanceFeeModel();
    }
}

class TestableBiometricNotificationService extends Biometric_attendance_service
{
    public function __construct($ci) { $this->CI = $ci; }
    public function balance($studentSessionId) { return $this->studentFeeBalance($studentSessionId); }
    public function render($template, array $variables) { return $this->renderNotificationTemplate($template, $variables); }
    public function channel(array $template, $channel) { return $this->templateChannelEnabled($template, $channel); }
}

function biometric_balance_assert($condition, $message)
{
    if (!$condition) {
        fwrite(STDERR, $message . PHP_EOL);
        exit(1);
    }
}

$ci = new BiometricNotificationBalanceCi();
$GLOBALS['biometric_notification_balance_ci'] = $ci;
$feeMaster = new stdClass();
$feeMaster->fees = array();

$partPaid = new stdClass();
$partPaid->amount = 1000;
$partPaid->amount_detail = json_encode(array(
    '1' => array('amount' => 250, 'amount_discount' => 100, 'amount_fine' => 25),
));
$paidInFull = new stdClass();
$paidInFull->amount = 500;
$paidInFull->amount_detail = json_encode(array(
    '2' => array('amount' => 500, 'amount_discount' => 0, 'amount_fine' => 0),
));
$unpaid = new stdClass();
$unpaid->amount = 200;
$unpaid->amount_detail = '0';
$feeMaster->fees = array($partPaid, $paidInFull, $unpaid);
$ci->studentfeemaster_model->fees = array($feeMaster);

$service = new TestableBiometricNotificationService($ci);
$balance = $service->balance(99);
biometric_balance_assert(abs($balance['outstanding'] - 850.00) < 0.001, 'Balance must subtract payments and discounts without adding fines.');
biometric_balance_assert($balance['item_count'] === 2, 'Only fee items with a positive balance must be counted.');
biometric_balance_assert(
    $service->render('Hello {{guardian_name}}, balance {{amount}}.', array('guardian_name' => 'Ada', 'amount' => '850.00'))
        === 'Hello Ada, balance 850.00.',
    'Notification variables must render in editable templates.'
);
biometric_balance_assert($service->channel(array('is_mail' => '1'), 'email'), 'Enabled template email channel must be recognized.');
biometric_balance_assert(!$service->channel(array('is_mail' => '0'), 'email'), 'Disabled template email channel must be rejected.');

echo "biometric notification balance tests passed\n";
