<?php
require_once __DIR__ . '/whatsapp_gateway_test.php';
require_once __DIR__ . '/whatsapp_provider_config_test.php';
class Admin_Controller { public $load, $session, $rbac, $input, $whatsappconfig_model; }
require_once __DIR__ . '/../application/controllers/admin/Whatsappsettings.php';
function html_escape($text) { return htmlspecialchars((string) $text, ENT_QUOTES, 'UTF-8'); }
function redirect($path) { throw new RuntimeException('redirect', 302); }
function access_denied() { throw new RuntimeException('denied', 403); }
function show_error($message, $code) { throw new RuntimeException($message, $code); }
class TwilioAdminSession {
    public $values = array('whatsapp_settings_nonce' => 'test-form-nonce');
    public function userdata($key) { return isset($this->values[$key]) ? $this->values[$key] : null; }
    public function set_userdata($key, $value) { $this->values[$key] = $value; }
    public function set_flashdata($key, $value) { $this->values[$key] = $value; }
}
class TwilioAdminRbac {
    public $view = true, $edit = true;
    public function hasPrivilege($category, $action) { return $category === 'whatsapp_setting' && ($action === 'can_view' ? $this->view : $this->edit); }
}
class TwilioAdminInput {
    public $post = array(), $method = 'POST';
    public function post($field = null) { return $field === null ? $this->post : (isset($this->post[$field]) ? $this->post[$field] : null); }
    public function server($field) { return $this->method; }
}
class TwilioAdminLoader { public $data; public function view($path, $data) { $this->data = $data; } }
class TwilioAdminController extends Whatsappsettings {
    public $gateways = array(), $responses = array();
    protected function makeGateway($configuration) {
        $gateway = new WhatsappTestGateway($configuration);
        $gateway->responses = $this->responses;
        $this->gateways[] = $gateway;
        return $gateway;
    }
}
function twilio_admin($action) {
    $controller = (new ReflectionClass('TwilioAdminController'))->newInstanceWithoutConstructor();
    $controller->session = new TwilioAdminSession();
    $controller->input = new TwilioAdminInput();
    $controller->rbac = new TwilioAdminRbac();
    $controller->load = new TwilioAdminLoader();
    $model = (new ReflectionClass('Whatsappconfig_model'))->newInstanceWithoutConstructor();
    $model->db = new ProviderConfigDb();
    $config = $model->defaults();
    $config['sandbox'] = array('account_sid' => 'AC' . str_repeat('a', 32), 'auth_token' => str_repeat('c', 32), 'from' => '+14155238886', 'test_recipient' => '+2348012345678', 'login_content_sid' => '');
    $model->db->table_present = true;
    $model->db->row = array('settings_json' => json_encode($config));
    $controller->whatsappconfig_model = $model;
    $controller->input->post = array('action' => $action, 'whatsapp_nonce' => 'test-form-nonce', 'test_environment' => 'sandbox', 'test_type' => 'text', 'test_body' => 'Admin test', 'test_content_sid' => '', 'test_variables' => '');
    return $controller;
}
function run_twilio_admin($controller) {
    try { $controller->index(); } catch (RuntimeException $error) { if ($error->getCode() !== 302) { throw $error; } }
}
$controller = twilio_admin('check_connection');
$controller->responses = array(array(200, '{"sid":"ACaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa","status":"active"}'));
run_twilio_admin($controller);
whatsapp_assert(strpos($controller->session->values['msg'], 'authenticated') !== false && count($controller->gateways[0]->requests) === 1 && $controller->gateways[0]->requests[0]['method'] === 'GET', 'Admin connection check must authenticate without sending.');
$controller = twilio_admin('send_test');
run_twilio_admin($controller);
whatsapp_assert(strpos($controller->session->values['msg'], 'accepted one sandbox') !== false && $controller->gateways[0]->requests[0]['payload']['To'] === 'whatsapp:+2348012345678' && !$controller->whatsappconfig_model->get()['enabled'], 'Admin can test the saved phone while school sending remains off.');
whatsapp_assert($controller->session->values['whatsapp_twilio_test']['environment'] === 'sandbox', 'Last test must be associated with its environment and account.');
$controller->session->values['whatsapp_twilio_test_time'] = 0;
$controller->input->post['action'] = 'check_status';
$controller->responses = array(array(200, '{"sid":"SMbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbb","status":"undelivered","error_code":63015,"error_message":"private phone data"}'));
run_twilio_admin($controller);
whatsapp_assert($controller->session->values['whatsapp_twilio_test']['status'] === 'undelivered' && strpos($controller->session->values['msg'], '63015') !== false && strpos($controller->session->values['msg'], 'private phone data') === false, 'Delayed delivery failure must update status and display a safe provider explanation.');
$controller = twilio_admin('check_status');
$controller->session->values['whatsapp_twilio_test'] = array('environment' => 'production', 'account_sid' => 'AC' . str_repeat('e', 32), 'sid' => 'SM' . str_repeat('b', 32));
run_twilio_admin($controller);
whatsapp_assert(!$controller->gateways[0]->requests && strpos($controller->session->values['msg'], 'No matching test') !== false, 'Status lookup must not cross environments or accounts.');
foreach (array('nonce', 'readonly', 'no_view') as $case) {
    $controller = twilio_admin('send_test');
    if ($case === 'nonce') { $controller->input->post['whatsapp_nonce'] = 'bad'; }
    elseif ($case === 'readonly') { $controller->rbac->edit = false; }
    else { $controller->rbac->view = false; }
    try { run_twilio_admin($controller); throw new RuntimeException('Expected denial'); }
    catch (RuntimeException $error) { whatsapp_assert($error->getCode() === 403 && !$controller->gateways, 'Only authorized settings editors with the form nonce may test.'); }
}
$controller = twilio_admin('send_test');
$controller->session->values['whatsapp_twilio_test_time'] = time();
run_twilio_admin($controller);
whatsapp_assert(!$controller->gateways[0]->requests && strpos($controller->session->values['msg'], 'four seconds') !== false, 'Repeated test clicks must be throttled.');
$controller = twilio_admin('');
$controller->input->method = 'GET';
run_twilio_admin($controller);
whatsapp_assert($controller->load->data['config']['sandbox']['auth_token'] === '' && !$controller->gateways, 'Viewing settings must not send or include credentials in page HTML data.');
$controller->session->values['whatsapp_twilio_test_environment'] = 'production';
run_twilio_admin($controller);
whatsapp_assert($controller->load->data['test_environment'] === 'production', 'The selected diagnostic environment must persist so the next action uses the visible intended account.');
echo "Twilio admin workflow tests passed\n";
