<?php

require_once __DIR__ . '/whatsapp_gateway_test.php';
require_once __DIR__ . '/../system/libraries/Form_validation.php';

class Admin_Controller {
    public $input, $rbac, $lang, $form_validation, $whatsappgateway, $messages_model;
    public $setting_model, $student_model, $staff_model, $customlib, $sch_setting_detail, $user_model;
}
require_once __DIR__ . '/../application/controllers/admin/Mailsms.php';

function html_escape($text) { return htmlspecialchars((string) $text, ENT_QUOTES, 'UTF-8'); }
function form_error($field) { return $GLOBALS['whatsapp_ci']->form_validation->error($field); }
function access_denied() { throw new RuntimeException('Access denied', 403); }
function show_error($message, $status = 500) { throw new RuntimeException($message, $status); }

class WhatsappComposeInput {
    public $http_method = 'POST';
    public function post($field) { return isset($_POST[str_replace('[]', '', $field)]) ? $_POST[str_replace('[]', '', $field)] : null; }
    public function server($key) { return $this->http_method; }
    public function method() { return strtolower($this->http_method); }
}
class WhatsappComposeLoader extends WhatsappTestLoader {
    public function helper($name) {}
}
class WhatsappComposeLang {
    public function load($name) {}
    public function line($name, $log = true) { return $name . ': {field}'; }
}
class WhatsappComposeRbac {
    public $allowed = true;
    public function hasPrivilege($category, $action) { return $this->allowed && $category === 'whatsapp_messaging' && $action === 'can_view'; }
}
class WhatsappComposeSettings extends WhatsappTestSettings {
    public function getCurrentSessionName() { return '2026/2027'; }
}
class WhatsappComposeStudents {
    public $rows;
    public function get() { return $this->rows; }
}
class WhatsappComposeUsers {
    public function getParentLoginDetails($id) { return array('username' => 'parent' . $id, 'password' => '$2y$10$storedHash'); }
}
class WhatsappComposeNames {
    public function getFullName($first, $middle, $last, $use_middle, $use_last) { return trim($first . ' ' . $last); }
}
class WhatsappComposeMessages {
    public $rows = array();
    public function add($data) { $this->rows[] = $data; return count($this->rows); }
}

function whatsapp_compose(array $post, array $overrides = array()) {
    $_POST = $post;
    $gateway = whatsapp_gateway($overrides);
    $ci = $GLOBALS['whatsapp_ci'];
    $ci->load = new WhatsappComposeLoader();
    $ci->input = new WhatsappComposeInput();
    $ci->lang = new WhatsappComposeLang();
    $ci->form_validation = new CI_Form_validation();
    $controller = (new ReflectionClass('Mailsms'))->newInstanceWithoutConstructor();
    $controller->input = $ci->input;
    $controller->rbac = new WhatsappComposeRbac();
    $controller->lang = $ci->lang;
    $controller->form_validation = $ci->form_validation;
    $controller->whatsappgateway = $gateway;
    $controller->messages_model = new WhatsappComposeMessages();
    $controller->setting_model = new WhatsappComposeSettings();
    $controller->sch_setting_detail = $controller->setting_model->getSetting();
    $controller->student_model = new WhatsappComposeStudents();
    $controller->customlib = new WhatsappComposeNames();
    $controller->user_model = new WhatsappComposeUsers();
    $controller->student_model->rows = array();
    foreach (array('08012345678', '', '08022345678') as $index => $phone) {
        $controller->student_model->rows[] = array('id' => $index + 1, 'firstname' => 'Student', 'middlename' => '', 'lastname' => (string) ($index + 1), 'email' => '', 'mobileno' => '09012345678', 'guardian_email' => '', 'guardian_phone' => $phone, 'guardian_name' => 'Guardian ' . ($index + 1));
    }
    return $controller;
}
function whatsapp_compose_response($controller) {
    ob_start();
    try { $controller->send_group_whatsapp(); $output = ob_get_contents(); }
    finally { ob_end_clean(); }
    $response = json_decode($output, true);
    whatsapp_assert(is_array($response), 'The compose endpoint must return valid JSON.');
    return $response;
}

$content_sid = 'HX' . str_repeat('d', 32);
$post = array('group_delivery_type' => 'template', 'group_title' => 'School notice', 'twilio_content_sid' => ' ' . $content_sid . ' ', 'twilio_template_parameters' => "{{display_name}}\r\n{{school_name}}", 'user' => array('parent'));
$controller = whatsapp_compose($post);
$controller->whatsappgateway->responses = array(array(201, '{"sid":"SMbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbb","status":"queued"}'), array(401, '{"code":20003,"message":"sensitive"}'));
$response = whatsapp_compose_response($controller);
whatsapp_assert($response['status'] === 0 && $response['summary'] === array('accepted' => 1, 'failed' => 1, 'missing_phone' => 1), 'Mixed results must count acceptance, failure and missing phones accurately.');
whatsapp_assert(strpos($response['msg'], 'authentication') !== false, 'Partial failure must include a safe Twilio reason.');
$requests = $controller->whatsappgateway->requests;
whatsapp_assert(count($requests) === 2 && $requests[0]['payload']['To'] === 'whatsapp:+2348012345678', 'Parents must use guardian phones and skip missing contacts.');
whatsapp_assert($requests[0]['payload']['ContentSid'] === $content_sid && array_values(json_decode($requests[0]['payload']['ContentVariables'], true)) === array('Guardian 1', 'Test School'), 'Content SID must be trimmed and variables personalized.');
whatsapp_assert(count($controller->messages_model->rows) === 1 && strpos($controller->messages_model->rows[0]['message'], $content_sid) !== false, 'Accepted sends must record Content SID.');
$controller = whatsapp_compose($post);
$controller->whatsappgateway->responses = array(array(400, '{"code":21658}'), array(400, '{"code":21658}'));
$response = whatsapp_compose_response($controller);
whatsapp_assert($response['status'] === 1 && $response['summary']['accepted'] === 0 && $response['summary']['failed'] === 2 && !$controller->messages_model->rows, 'Total rejection must not create a successful log.');
foreach (array(array('twilio_content_sid' => 'school_notice'), array('twilio_content_sid' => array('bad')), array('user' => array()), array('group_title' => array('invalid')), array('user' => 'parent'), array('twilio_template_parameters' => array('invalid')), array('group_delivery_type' => 'unsupported')) as $invalid) {
    $controller = whatsapp_compose(array_replace($post, $invalid));
    $response = whatsapp_compose_response($controller);
    whatsapp_assert($response['status'] === 1 && !$controller->whatsappgateway->requests, 'Invalid input must not contact Twilio.');
}
foreach (array(array('enabled' => 0), array('environment' => 'sandbox')) as $blocked) {
    $controller = whatsapp_compose($post, $blocked);
    $response = whatsapp_compose_response($controller);
    whatsapp_assert($response['status'] === 1 && !$controller->whatsappgateway->requests, 'Disabled and Sandbox school sends must be blocked.');
}
$controller = whatsapp_compose($post);
$controller->student_model->rows = array();
$response = whatsapp_compose_response($controller);
whatsapp_assert($response['status'] === 1 && !$controller->messages_model->rows && !$controller->whatsappgateway->requests, 'Empty groups must not send or log success.');
$text_post = array('group_delivery_type' => 'text', 'group_title' => 'Notice', 'group_message' => 'Hello {{display_name}} from {{school_name}}', 'user' => array('parent'));
$controller = whatsapp_compose($text_post);
$response = whatsapp_compose_response($controller);
whatsapp_assert($response['summary']['accepted'] === 2 && $response['summary']['missing_phone'] === 1 && $controller->whatsappgateway->requests[0]['payload']['Body'] === 'Hello Guardian 1 from Test School', 'Text replies must remain personalized.');
$controller = whatsapp_compose(array_replace($text_post, array('group_message' => '')));
$response = whatsapp_compose_response($controller);
whatsapp_assert($response['status'] === 1 && !$controller->whatsappgateway->requests, 'Text requires a message.');
foreach (array('GET' => 405, 'DENIED' => 403) as $case => $status) {
    $controller = whatsapp_compose($post);
    if ($case === 'GET') { $controller->input->http_method = 'GET'; } else { $controller->rbac->allowed = false; }
    try { whatsapp_compose_response($controller); throw new RuntimeException('Expected rejection.'); }
    catch (RuntimeException $error) { whatsapp_assert($error->getCode() === $status, 'Only permitted POST requests may send.'); }
    whatsapp_assert(!$controller->whatsappgateway->requests, 'Rejected requests must not contact Twilio.');
}
echo "Twilio WhatsApp compose tests passed\n";
