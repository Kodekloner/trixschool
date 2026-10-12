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

$post = array('group_delivery_type' => 'template', 'group_title' => 'School notice', 'meta_template_name' => ' school_notice ', 'meta_template_language' => ' en_US ', 'meta_template_parameters' => "{{display_name}}\r\n{{school_name}}", 'user' => array('parent'));
$controller = whatsapp_compose($post);
$controller->whatsappgateway->responses = array(array(200, '{"messages":[{"id":"wamid.accepted"}]}'), array(400, '{"error":{"code":190}}'));
$response = whatsapp_compose_response($controller);
whatsapp_assert($response['status'] === 0 && $response['summary'] === array('accepted' => 1, 'failed' => 1, 'missing_phone' => 1), 'Mixed results must count acceptance, failure and missing phones accurately.');
whatsapp_assert(strpos($response['msg'], 'token') !== false, 'A partial failure must expose the safe provider reason.');
$requests = $controller->whatsappgateway->requests;
whatsapp_assert(count($requests) === 2 && $requests[0]['payload']['to'] === '2348012345678', 'Parent messages must use guardian phones, and skip missing phones.');
whatsapp_assert($requests[0]['payload']['template']['name'] === 'school_notice', 'CodeIgniter validation must trim template names.');
whatsapp_assert(array_column($requests[0]['payload']['template']['components'][0]['parameters'], 'text') === array('Guardian 1', 'Test School'), 'Each guardian must receive personalized template body values.');
whatsapp_assert(count($controller->messages_model->rows) === 1 && strpos($controller->messages_model->rows[0]['message'], 'school_notice') !== false, 'Accepted template sends must record the template name.');

$controller = whatsapp_compose($post);
$controller->whatsappgateway->responses = array(array(400, '{"error":{"code":132001}}'), array(400, '{"error":{"code":132001}}'));
$response = whatsapp_compose_response($controller);
whatsapp_assert($response['status'] === 1 && $response['summary']['accepted'] === 0 && $response['summary']['failed'] === 2, 'An entirely rejected send must report failure.');
whatsapp_assert(count($controller->messages_model->rows) === 0, 'An entirely rejected send must not create a successful message log.');
whatsapp_assert(strpos($response['msg']['whatsapp'], 'name or language') !== false, 'An invalid approved template must show the specific reason.');

foreach (array(array('meta_template_name' => 'Bad Name'), array('meta_template_language' => 'English'), array('user' => array()), array('group_title' => array('invalid')), array('user' => 'parent'), array('meta_template_parameters' => array('invalid')), array('group_delivery_type' => 'unsupported')) as $invalid) {
    $controller = whatsapp_compose(array_replace($post, $invalid));
    $response = whatsapp_compose_response($controller);
    whatsapp_assert($response['status'] === 1 && !$controller->whatsappgateway->requests, 'Invalid compose input must fail before contacting the provider.');
}
$controller = whatsapp_compose($post, array('enabled' => 0));
$response = whatsapp_compose_response($controller);
whatsapp_assert($response['status'] === 1 && strpos($response['msg']['whatsapp'], 'disabled') !== false && !$controller->whatsappgateway->requests, 'Disabled sending must show its configuration problem.');
$controller = whatsapp_compose($post);
$controller->student_model->rows = array();
$response = whatsapp_compose_response($controller);
whatsapp_assert($response['status'] === 1 && !$controller->messages_model->rows && !$controller->whatsappgateway->requests, 'Empty recipient groups must not create a message log or send requests.');

$text_post = array('group_delivery_type' => 'text', 'group_title' => 'Notice', 'group_message' => 'Hello {{display_name}} from {{school_name}}', 'user' => array('parent'));
$controller = whatsapp_compose($text_post, array('provider' => 'custom_webhook'));
$controller->whatsappgateway->responses = array(array(202, '{"success":true}'), array(202, '{"success":true}'));
$response = whatsapp_compose_response($controller);
whatsapp_assert($response['summary']['accepted'] === 2 && $response['summary']['missing_phone'] === 1, 'Custom webhook text messages must keep working.');
whatsapp_assert($controller->whatsappgateway->requests[0]['payload']['message'] === 'Hello Guardian 1 from Test School', 'Custom webhook text must remain personalized.');
$controller = whatsapp_compose(array_replace($text_post, array('group_message' => '')));
$response = whatsapp_compose_response($controller);
whatsapp_assert($response['status'] === 1 && !$controller->whatsappgateway->requests, 'Text mode must still require a message.');

foreach (array('GET' => 405, 'DENIED' => 403) as $case => $status) {
    $controller = whatsapp_compose($post);
    if ($case === 'GET') { $controller->input->http_method = 'GET'; }
    else { $controller->rbac->allowed = false; }
    try { whatsapp_compose_response($controller); throw new RuntimeException('Expected request rejection.'); }
    catch (RuntimeException $error) { whatsapp_assert($error->getCode() === $status, 'Only permitted POST requests may send messages.'); }
    whatsapp_assert(!$controller->whatsappgateway->requests, 'Rejected requests must not contact the provider.');
}

echo "WhatsApp compose workflow tests passed\n";
