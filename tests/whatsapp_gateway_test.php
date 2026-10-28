<?php
if (!defined('BASEPATH')) { define('BASEPATH', __DIR__); }
require_once __DIR__ . '/../application/libraries/Whatsappgateway.php';
$whatsapp_logs = array();
function log_message($level, $message) { $GLOBALS['whatsapp_logs'][] = $message; }
function site_url($path) { return 'https://school.example/' . $path; }
function &get_instance() { return $GLOBALS['whatsapp_ci']; }
function whatsapp_assert($condition, $message) { if (!$condition) { throw new RuntimeException($message); } }
class WhatsappTestLoader { public function model($name) {} }
class WhatsappTestConfig {
    public $config;
    public function getGatewayConfig() { return $this->config; }
}
class WhatsappTestSettings { public function getSetting() { return (object) array('name' => 'Test School', 'middlename' => 0, 'lastname' => 1); } }
class WhatsappTestStudent { public function get($id) { return array('guardian_name' => 'Test Guardian'); } }
class WhatsappTestGateway extends Whatsappgateway {
    public $requests = array();
    public $responses = array();
    protected function httpRequest($method, $url, $payload = array()) {
        $this->requests[] = array('method' => $method, 'url' => $url, 'payload' => $payload);
        return $this->responses ? array_shift($this->responses) : array(201, json_encode(array('sid' => 'SM' . str_repeat('b', 32), 'status' => 'queued')));
    }
}
function whatsapp_gateway(array $overrides = array()) {
    $config = array_replace_recursive(array(
        'enabled' => 1, 'provider' => 'twilio', 'environment' => 'production', 'diagnostic' => false,
        'default_country_code' => '234', 'auto_send_login_credentials' => 1,
        'auto_credential_for' => array('parent'),
        'twilio' => array('account_sid' => 'AC' . str_repeat('a', 32), 'auth_token' => str_repeat('c', 32),
            'from' => '+14155238886', 'test_recipient' => '+2348012345678', 'login_content_sid' => 'HX' . str_repeat('d', 32))
    ), $overrides);
    $GLOBALS['whatsapp_ci'] = (object) array('load' => new WhatsappTestLoader(), 'whatsappconfig_model' => new WhatsappTestConfig(), 'setting_model' => new WhatsappTestSettings(), 'student_model' => new WhatsappTestStudent());
    $GLOBALS['whatsapp_ci']->whatsappconfig_model->config = $config;
    return new WhatsappTestGateway();
}
$content_sid = 'HX' . str_repeat('d', 32);
$gateway = whatsapp_gateway(array('enabled' => 0));
whatsapp_assert(!$gateway->sendMessage('08012345678', 'Hello') && !$gateway->requests, 'Disabled sending must never contact Twilio.');
$gateway = whatsapp_gateway(array('twilio' => array('account_sid' => '', 'auth_token' => '', 'from' => '')));
whatsapp_assert(count($gateway->getConfigurationErrors()) === 3 && !$gateway->sendMessage('08012345678', 'Hello'), 'Missing configuration must block sends.');
$gateway = whatsapp_gateway();
foreach (array('0801 234 5678' => '2348012345678', '8012345678' => '2348012345678', '+2348012345678' => '2348012345678', '002348012345678' => '2348012345678', '+4412345678' => '4412345678') as $phone => $expected) {
    whatsapp_assert($gateway->sendMessage($phone, 'Hello'), 'A valid phone number must be accepted.');
    $request = end($gateway->requests);
    whatsapp_assert($request['payload'] === array('Body' => 'Hello', 'From' => 'whatsapp:+14155238886', 'To' => 'whatsapp:+' . $expected), 'WhatsApp channel prefixes, sender and normalized recipient are required.');
    whatsapp_assert($request['url'] === 'https://api.twilio.com/2010-04-01/Accounts/AC' . str_repeat('a', 32) . '/Messages.json' && $request['method'] === 'POST', 'Twilio account-specific Messages endpoint must be used.');
}
$before = count($gateway->requests);
foreach (array('', 'not a phone', '000', 'abc08012345678', '+1234567890123456') as $phone) {
    whatsapp_assert(!$gateway->sendMessage($phone, 'Hello'), 'Invalid phone must fail before sending.');
}
whatsapp_assert(!$gateway->sendMessage('08012345678', ' ') && count($gateway->requests) === $before, 'Empty content and invalid phone must not contact Twilio.');
whatsapp_assert($gateway->sendTemplateMessage('08012345678', $content_sid, '', array('Guardian', 'Test School')), 'Twilio Content templates must send.');
$request = end($gateway->requests);
whatsapp_assert($request['payload']['ContentSid'] === $content_sid && json_decode($request['payload']['ContentVariables'], true) === array(1 => 'Guardian', 2 => 'Test School') && !isset($request['payload']['Body']), 'Content SID and numbered ContentVariables must preserve the approved order without Body.');
whatsapp_assert($gateway->sendTemplateMessage('08012345678', $content_sid), 'Templates without values must work.');
$request = end($gateway->requests);
whatsapp_assert(!isset($request['payload']['ContentVariables']), 'No-value templates must omit variables.');
whatsapp_assert(!$gateway->sendTemplateMessage('08012345678', 'school_notice') && !$gateway->sendTemplateMessage('08012345678', $content_sid, '', array('')), 'Invalid Content SID and empty values must fail locally.');
foreach (array(20003 => 'authentication', 63007 => 'sender', 63015 => 'joined', 63016 => '24-hour', 63018 => 'rate', 21656 => 'variables', 21658 => 'limit', 21608 => 'trial', 63027 => 'language', 63028 => 'count', 63040 => 'rejected') as $code => $reason) {
    $gateway->responses = array(array(400, json_encode(array('code' => $code, 'message' => str_repeat('c', 32) . ' recipient 2348012345678'))));
    whatsapp_assert(!$gateway->sendMessage('08012345678', 'Hello') && stripos($gateway->getLastError(), $reason) !== false, 'Twilio error must be safe and actionable: ' . $code);
}
foreach (array(array(200, '{}'), array(200, '<html>Proxy</html>'), array(201, '{"sid":"SMbad","status":"queued"}'), array(201, '{"sid":"SMbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbb","status":"failed","error_code":63016}'), array(403, '{"code":99999}')) as $response) {
    $gateway->responses = array($response);
    whatsapp_assert(!$gateway->sendMessage('08012345678', 'Hello'), 'Malformed, missing-SID and failed responses must not count as accepted.');
}
whatsapp_assert($gateway->sendMessage('08012345678', 'Hello') && $gateway->getLastError() === '', 'A success must clear a previous error.');
$gateway = whatsapp_gateway(array('environment' => 'sandbox'));
whatsapp_assert(!$gateway->sendMessage('08012345678', 'Hello') && !$gateway->requests && !$gateway->shouldAutoSendLoginCredential(array('credential_for' => 'parent')), 'Sandbox must block normal school and automatic sends.');
$gateway = whatsapp_gateway(array('environment' => 'sandbox', 'diagnostic' => true));
whatsapp_assert($gateway->sendMessage('08012345678', 'Test'), 'Sandbox diagnostics must allow the saved test phone.');
$before = count($gateway->requests);
whatsapp_assert(!$gateway->sendMessage('08022345678', 'Test') && count($gateway->requests) === $before, 'Diagnostics must reject all other recipients.');
$gateway = whatsapp_gateway();
$gateway->responses = array(array(200, '{"sid":"ACaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa","status":"active","auth_token":"must-not-be-exposed"}'));
whatsapp_assert($gateway->checkConnection() && end($gateway->requests)['method'] === 'GET', 'Connection check must fetch the account without sending.');
$gateway->responses = array(array(200, '{"sid":"ACaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa","status":"suspended"}'));
whatsapp_assert(!$gateway->checkConnection() && strpos($gateway->getLastError(), 'not active') !== false, 'Suspended accounts must not be marked connected.');
$gateway->responses = array(array(200, '{"sid":"ACeeeeeeeeeeeeeeeeeeeeeeeeeeeeeeee","status":"active"}'));
whatsapp_assert(!$gateway->checkConnection(), 'Connection check must reject a different account.');
$message_sid = 'SM' . str_repeat('b', 32);
foreach (array('delivered', 'read', 'queued', 'failed') as $status) {
    $gateway->responses = array(array(200, json_encode(array('sid' => $message_sid, 'status' => $status, 'error_code' => $status === 'failed' ? 63016 : null))));
    $result = $gateway->fetchMessageStatus($message_sid);
    whatsapp_assert($result['status'] === $status, 'Delivery lookup must preserve actual state including failed sends.');
}
$gateway->responses = array(array(200, json_encode(array('sid' => 'SM' . str_repeat('e', 32), 'status' => 'delivered'))));
whatsapp_assert(!$gateway->fetchMessageStatus($message_sid), 'Status must belong to the requested message.');
whatsapp_assert($gateway->sendLoginCredential(array('id' => 1, 'credential_for' => 'parent', 'contact_no' => '08012345678', 'username' => 'parent1', 'password' => 'temporary-password')), 'Login Content SID must support existing credential workflow.');
$request = end($gateway->requests);
whatsapp_assert(array_values(json_decode($request['payload']['ContentVariables'], true)) === array('Test Guardian', 'Test School', 'https://school.example/site/userlogin', 'parent1', 'temporary-password'), 'Login variable order must remain configurable.');
$before = count($gateway->requests);
whatsapp_assert(!$gateway->sendLoginCredential(array('id' => 1, 'credential_for' => 'parent', 'contact_no' => '08012345678', 'username' => 'parent1', 'password' => password_hash('secret', PASSWORD_BCRYPT))) && count($gateway->requests) === $before, 'Stored password hashes must never be transmitted.');
whatsapp_assert(strpos(implode("\n", $whatsapp_logs), str_repeat('c', 32)) === false && strpos(implode("\n", $whatsapp_logs), '2348012345678') === false && strpos(implode("\n", $whatsapp_logs), 'must-not-be-exposed') === false, 'Tokens, phone numbers and raw provider details must not appear in logs.');
echo "Twilio WhatsApp gateway tests passed\n";
