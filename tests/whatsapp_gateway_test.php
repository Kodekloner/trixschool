<?php

define('BASEPATH', __DIR__);
require_once __DIR__ . '/../application/libraries/Whatsappgateway.php';

$whatsapp_logs = array();
function log_message($level, $message) { $GLOBALS['whatsapp_logs'][] = $message; }
function site_url($path) { return 'https://school.example/' . $path; }
function &get_instance() { return $GLOBALS['whatsapp_ci']; }
function whatsapp_assert($condition, $message) {
    if (!$condition) { throw new RuntimeException($message); }
}

class WhatsappTestLoader {
    public function model($name) {}
}
class WhatsappTestConfig {
    public $config;
    public function getGatewayConfig() { return $this->config; }
}
class WhatsappTestSettings {
    public function getSetting() { return (object) array('name' => 'Test School', 'middlename' => 0, 'lastname' => 1); }
}
class WhatsappTestStudent {
    public function get($id) { return array('guardian_name' => 'Test Guardian'); }
}
class WhatsappTestGateway extends Whatsappgateway {
    public $requests = array();
    public $responses = array();
    protected function postJson($url, $payload, $headers) {
        $this->requests[] = array('url' => $url, 'payload' => $payload, 'headers' => $headers);
        $response = !empty($this->responses) ? array_shift($this->responses) : array(200, '{"messages":[{"id":"wamid.test"}]}');
        return $this->handleProviderResponse($response[0], $response[1]);
    }
}

function whatsapp_gateway(array $overrides = array()) {
    $config = array_replace_recursive(array(
        'enabled' => 1,
        'provider' => 'meta_cloud',
        'default_country_code' => '234',
        'auto_send_login_credentials' => 1,
        'auto_credential_for' => array('parent'),
        'meta_cloud' => array('phone_number_id' => '12345', 'access_token' => 'test-private-token', 'graph_api_version' => 'v24.0', 'template_name' => 'parent_login_credentials', 'template_language' => 'en'),
        'custom_webhook' => array('url' => 'https://provider.example/messages', 'bearer_token' => 'test-webhook-token'),
    ), $overrides);
    $GLOBALS['whatsapp_ci'] = (object) array('load' => new WhatsappTestLoader(), 'whatsappconfig_model' => new WhatsappTestConfig(), 'setting_model' => new WhatsappTestSettings(), 'student_model' => new WhatsappTestStudent());
    $GLOBALS['whatsapp_ci']->whatsappconfig_model->config = $config;
    return new WhatsappTestGateway();
}

$gateway = whatsapp_gateway(array('enabled' => 0));
whatsapp_assert(!$gateway->sendMessage('08012345678', 'Hello') && count($gateway->requests) === 0, 'Disabled sending must not contact a provider.');
whatsapp_assert(strpos($gateway->getLastError(), 'disabled') !== false, 'Disabled sending must explain how to enable it.');
$gateway = whatsapp_gateway(array('meta_cloud' => array('phone_number_id' => '', 'access_token' => '')));
whatsapp_assert(count($gateway->getConfigurationErrors()) === 2, 'Both missing Meta credentials must be reported.');
whatsapp_assert(!$gateway->sendMessage('08012345678', 'Hello') && !$gateway->requests, 'Missing configuration must block the request.');

$gateway = whatsapp_gateway();
foreach (array('0801 234 5678' => '2348012345678', '8012345678' => '2348012345678', '2341234567' => '2342341234567', '+2348012345678' => '2348012345678', '002348012345678' => '2348012345678', '+4412345678' => '4412345678', '004412345678' => '4412345678') as $phone => $expected) {
    whatsapp_assert($gateway->sendMessage($phone, 'Hello'), 'A valid phone number should be accepted: ' . $phone);
    $request = end($gateway->requests);
    whatsapp_assert($request['payload']['to'] === $expected, 'Phone normalization failed for ' . $phone);
    whatsapp_assert($request['payload']['type'] === 'text', 'Text sending must use a text payload.');
}
$before = count($gateway->requests);
foreach (array('', 'not a phone', '000', '+1234567890123456') as $phone) {
    whatsapp_assert(!$gateway->sendMessage($phone, 'Hello'), 'Invalid phones must fail before contacting Meta.');
}
whatsapp_assert(count($gateway->requests) === $before, 'Invalid phone numbers must not contact the provider.');
whatsapp_assert(!$gateway->sendMessage('08012345678', ' '), 'Empty messages must fail.');

whatsapp_assert($gateway->sendTemplateMessage('08012345678', 'school_notice', 'en_US', array('Guardian', 'Test School')), 'Approved body templates must be accepted.');
$request = end($gateway->requests);
whatsapp_assert($request['url'] === 'https://graph.facebook.com/v24.0/12345/messages', 'The configured API version and sender ID must be used.');
whatsapp_assert($request['payload']['type'] === 'template' && $request['payload']['template']['name'] === 'school_notice', 'Meta must receive an approved template payload.');
whatsapp_assert($request['payload']['template']['language']['code'] === 'en_US', 'The exact approved language must be used.');
whatsapp_assert(array_column($request['payload']['template']['components'][0]['parameters'], 'text') === array('Guardian', 'Test School'), 'Template body values must preserve their order.');
whatsapp_assert($gateway->sendTemplateMessage('08012345678', 'hello_world', 'en_US'), 'Templates without values must work.');
$request = end($gateway->requests);
whatsapp_assert(!isset($request['payload']['template']['components']), 'A template without values must omit components.');
$before = count($gateway->requests);
whatsapp_assert(!$gateway->sendTemplateMessage('08012345678', 'Invalid Name', 'en'), 'Invalid template names must fail locally.');
whatsapp_assert(!$gateway->sendTemplateMessage('08012345678', 'school_notice', 'English'), 'Invalid language codes must fail locally.');
whatsapp_assert(!$gateway->sendTemplateMessage('08012345678', 'school_notice', 'en', array('')), 'Missing personalized values must fail locally.');
whatsapp_assert(count($gateway->requests) === $before, 'Invalid templates must not contact Meta.');

foreach (array(190 => 'token', 131047 => 'reply window', 132000 => 'value count', 132001 => 'name or language', 131030 => 'test recipient') as $code => $reason) {
    $gateway->responses = array(array(400, json_encode(array('error' => array('code' => $code, 'message' => 'test-private-token recipient 2348012345678')))));
    whatsapp_assert(!$gateway->sendMessage('08012345678', 'Hello'), 'Meta errors must fail.');
    whatsapp_assert(strpos($gateway->getLastError(), $reason) !== false, 'Meta error ' . $code . ' must be actionable.');
}
$gateway->responses = array(array(200, '{"error":{"code":190}}'));
whatsapp_assert(!$gateway->sendMessage('08012345678', 'Hello'), 'An error body must fail even with HTTP 200.');
$gateway->responses = array(array(200, '{}'));
whatsapp_assert(!$gateway->sendMessage('08012345678', 'Hello'), 'Meta must return a message ID before acceptance is reported.');
$gateway->responses = array(array(200, '<html>Proxy error</html>'));
whatsapp_assert(!$gateway->sendMessage('08012345678', 'Hello'), 'An HTML response must not count as Meta acceptance.');
whatsapp_assert($gateway->sendMessage('08012345678', 'Hello') && $gateway->getLastError() === '', 'A new success must clear the previous error.');

whatsapp_assert($gateway->sendLoginCredential(array('id' => 1, 'credential_for' => 'parent', 'contact_no' => '08012345678', 'username' => 'parent1', 'password' => 'temporary-password')), 'Existing login credential template sending must work.');
$request = end($gateway->requests);
whatsapp_assert($request['payload']['template']['name'] === 'parent_login_credentials', 'Login credentials must use their configured template.');
whatsapp_assert(array_column($request['payload']['template']['components'][0]['parameters'], 'text') === array('Test Guardian', 'Test School', 'https://school.example/site/userlogin', 'parent1', 'temporary-password'), 'Login credential value order must be preserved.');
$hash = password_hash('secret', PASSWORD_BCRYPT);
whatsapp_assert($gateway->sendLoginCredential(array('id' => 1, 'credential_for' => 'parent', 'contact_no' => '08012345678', 'username' => 'parent1', 'password' => $hash)), 'Credential preparation must complete.');
$request = end($gateway->requests);
whatsapp_assert(strpos(json_encode($request['payload']), $hash) === false, 'Stored password hashes must never be sent.');

$gateway = whatsapp_gateway(array('provider' => 'custom_webhook'));
$gateway->responses = array(array(200, '{"success":false,"error":"private provider details"}'));
whatsapp_assert(!$gateway->sendMessage('08012345678', 'Hello', 'Notice'), 'Webhook rejection must fail even with HTTP 200.');
$request = end($gateway->requests);
whatsapp_assert($request['payload'] === array('to' => '2348012345678', 'title' => 'Notice', 'message' => 'Hello'), 'Existing custom webhook payloads must remain compatible.');
$gateway->responses = array(array(202, '{"success":true}'));
whatsapp_assert($gateway->sendMessage('08012345678', 'Hello'), 'A webhook acceptance must succeed.');
$gateway->responses = array(array(200, '{"status":"failed"}'));
whatsapp_assert(!$gateway->sendMessage('08012345678', 'Hello'), 'A failed webhook status must not be counted as accepted.');
whatsapp_assert(!$gateway->sendTemplateMessage('08012345678', 'school_notice', 'en'), 'Meta templates must not be sent to a custom webhook.');
whatsapp_assert(strpos(implode('\n', $whatsapp_logs), 'test-private-token') === false && strpos(implode('\n', $whatsapp_logs), '2348012345678') === false, 'Logs must not expose tokens or recipient phone numbers from provider responses.');

echo "WhatsApp gateway tests passed\n";
