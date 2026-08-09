<?php

define('BASEPATH', __DIR__);

class Admin_Controller
{
    public $form_validation;
    public $input;
    public $lang;
    public $paymentsetting_model;
}

class MonnifyAdminInput
{
    private $values;

    public function __construct($values)
    {
        $this->values = $values;
    }

    public function post($key)
    {
        return isset($this->values[$key]) ? $this->values[$key] : null;
    }
}

class MonnifyAdminFormValidation
{
    public $messages = array();
    public $rules = array();
    private $run_result;

    public function __construct($run_result)
    {
        $this->run_result = $run_result;
    }

    public function set_error_delimiters($prefix, $suffix)
    {
    }

    public function set_rules($field, $label, $rules)
    {
        $this->rules[$field] = $rules;
    }

    public function set_message($rule, $message)
    {
        $this->messages[$rule] = $message;
    }

    public function run()
    {
        return $this->run_result;
    }
}

class MonnifyAdminLang
{
    public function line($key)
    {
        return $key;
    }
}

class MonnifyAdminPaymentSettingModel
{
    public $added = array();
    private $existing;

    public function __construct($existing)
    {
        $this->existing = $existing;
    }

    public function getByType($payment_type)
    {
        return $payment_type === 'monnify' ? $this->existing : null;
    }

    public function add($data)
    {
        $this->added[] = $data;
    }
}

function form_error($field)
{
    return '';
}

function monnify_admin_assert($condition, $message)
{
    if (!$condition) {
        fwrite(STDERR, $message . PHP_EOL);
        exit(1);
    }
}

require_once __DIR__ . '/../application/controllers/admin/Paymentsettings.php';

function monnify_admin_controller($mode, $existing = null, $secret = '', $validation_result = true)
{
    $reflection = new ReflectionClass('Paymentsettings');
    $controller = $reflection->newInstanceWithoutConstructor();
    $controller->input = new MonnifyAdminInput(array(
        'monnify_gateway_mode' => $mode,
        'monnify_api_key' => $mode === '0' ? 'MK_TEST_EXAMPLE' : 'MK_PROD_EXAMPLE',
        'monnify_secret_key' => $secret,
        'monnify_contract_code' => '1234567890',
    ));
    $controller->form_validation = new MonnifyAdminFormValidation($validation_result);
    $controller->lang = new MonnifyAdminLang();
    $controller->paymentsetting_model = new MonnifyAdminPaymentSettingModel($existing);

    return $controller;
}

$controller = monnify_admin_controller('0');
monnify_admin_assert($controller->valid_monnify_api_key('MK_TEST_EXAMPLE') === true, 'Sandbox mode must accept MK_TEST_ API keys.');
monnify_admin_assert($controller->valid_monnify_api_key('MK_PROD_EXAMPLE') === false, 'Sandbox mode must reject MK_PROD_ API keys.');

$controller = monnify_admin_controller('1');
monnify_admin_assert($controller->valid_monnify_api_key('MK_PROD_EXAMPLE') === true, 'Live mode must accept MK_PROD_ API keys.');
monnify_admin_assert($controller->valid_monnify_api_key('MK_TEST_EXAMPLE') === false, 'Live mode must reject MK_TEST_ API keys.');
monnify_admin_assert($controller->valid_monnify_api_key('MK_LIVE_EXAMPLE') === false, 'Live mode must reject legacy or unknown API-key prefixes.');

$existing = (object) array(
    'api_secret_key' => 'stored-secret',
    'api_publishable_key' => 'MK_TEST_EXAMPLE',
    'gateway_mode' => 0,
);
$controller = monnify_admin_controller('0', $existing, '');
ob_start();
$controller->monnify();
ob_end_clean();
monnify_admin_assert($controller->form_validation->rules['monnify_secret_key'] === 'trim|xss_clean', 'An existing setup must allow a blank secret.');
monnify_admin_assert(!array_key_exists('api_secret_key', $controller->paymentsetting_model->added[0]), 'A blank update must not overwrite the stored secret.');

$controller = monnify_admin_controller('1', $existing, '', false);
ob_start();
$controller->monnify();
ob_end_clean();
monnify_admin_assert($controller->form_validation->rules['monnify_secret_key'] === 'trim|required|xss_clean', 'Changing modes must require the matching secret.');

$different_key = (object) array(
    'api_secret_key' => 'stored-secret',
    'api_publishable_key' => 'MK_TEST_DIFFERENT',
    'gateway_mode' => 0,
);
$controller = monnify_admin_controller('0', $different_key, '', false);
ob_start();
$controller->monnify();
ob_end_clean();
monnify_admin_assert($controller->form_validation->rules['monnify_secret_key'] === 'trim|required|xss_clean', 'Changing the API key must require the matching secret.');

$controller = monnify_admin_controller('0', null, '', false);
ob_start();
$controller->monnify();
ob_end_clean();
monnify_admin_assert($controller->form_validation->rules['monnify_secret_key'] === 'trim|required|xss_clean', 'The first setup must require a secret.');
monnify_admin_assert(count($controller->paymentsetting_model->added) === 0, 'An invalid first setup must not be saved.');

$controller = monnify_admin_controller('0', (object) array('api_secret_key' => ''), '', false);
ob_start();
$controller->monnify();
ob_end_clean();
monnify_admin_assert($controller->form_validation->rules['monnify_secret_key'] === 'trim|required|xss_clean', 'A setup with no stored secret must still require one.');

$controller = monnify_admin_controller('1', null, 'new-secret');
ob_start();
$controller->monnify();
ob_end_clean();
monnify_admin_assert($controller->paymentsetting_model->added[0]['api_secret_key'] === 'new-secret', 'A supplied secret must be saved.');

$view = file_get_contents(__DIR__ . '/../application/views/admin/payment_setting/paymentsettingList.php');
monnify_admin_assert(preg_match('/<input[^>]+name="monnify_secret_key"[^>]*>/s', $view, $matches) === 1, 'The Monnify secret input must exist.');
monnify_admin_assert(strpos($matches[0], 'value=""') !== false, 'The Monnify secret input must always render blank.');
monnify_admin_assert(strpos($matches[0], 'api_secret_key') === false, 'The stored secret must never be rendered in the input.');
monnify_admin_assert(preg_match('/echo[^;\r\n]*\$monnify_result->api_secret_key/', $view) === 0, 'The stored secret must never be echoed elsewhere in the settings HTML.');
monnify_admin_assert(preg_match('/name="monnify_api_key"[^>]+value="<\?php echo html_escape\(/', $view) === 1, 'The API key must be escaped in its HTML attribute.');
monnify_admin_assert(preg_match('/name="monnify_contract_code"[^>]+value="<\?php echo html_escape\(/', $view) === 1, 'The contract code must be escaped in its HTML attribute.');

echo "monnify admin settings security tests passed" . PHP_EOL;
