<?php

require_once __DIR__ . '/whatsapp_compose_workflow_test.php';

function dialog_gateway($key = 'test-channel-api-key') {
    return whatsapp_gateway(array('provider' => '360dialog', 'meta_cloud' => array('phone_number_id' => '', 'access_token' => ''), 'dialog360' => array('api_key' => $key)));
}

$gateway = dialog_gateway();
whatsapp_assert($gateway->usesApprovedTemplates() && !$gateway->getConfigurationErrors(), '360dialog must support approved templates without a direct Meta token or sender ID.');
whatsapp_assert($gateway->sendTemplateMessage('08012345678', 'school_notice', 'en', array('Guardian', 'SchoolLift')), '360dialog must send approved templates.');
$request = end($gateway->requests);
whatsapp_assert($request['url'] === 'https://waba-v2.360dialog.io/messages', '360dialog must use its fixed production Messaging API endpoint.');
whatsapp_assert($request['headers'] === array('D360-API-KEY: test-channel-api-key', 'Content-Type: application/json'), '360dialog must use channel-key authentication, not Meta bearer authentication.');
whatsapp_assert($request['payload']['template']['name'] === 'school_notice' && $request['payload']['to'] === '2348012345678', 'Approved template names and normalized recipients must be preserved.');
whatsapp_assert(array_column($request['payload']['template']['components'][0]['parameters'], 'text') === array('Guardian', 'SchoolLift'), '360dialog body values must stay in approved order.');
whatsapp_assert($gateway->sendMessage('08012345678', 'A reply'), '360dialog text replies must work.');
$request = end($gateway->requests);
whatsapp_assert($request['payload']['type'] === 'text' && $request['payload']['text']['body'] === 'A reply', '360dialog must use a platform text payload, not a generic webhook payload.');
whatsapp_assert($gateway->sendLoginCredential(array('id' => 1, 'credential_for' => 'parent', 'contact_no' => '08012345678', 'username' => 'parent1', 'password' => 'temporary-password')), 'Existing login credential templates must use the managed provider.');
$request = end($gateway->requests);
whatsapp_assert($request['payload']['template']['name'] === 'parent_login_credentials', 'Login credential templates must preserve their configured name.');

$gateway->responses = array(array(401, '{"error":"invalid API key test-channel-api-key"}'));
whatsapp_assert(!$gateway->sendMessage('08012345678', 'A reply') && strpos($gateway->getLastError(), 'channel API key is invalid') !== false, 'A managed-provider auth error must explain the channel key, not a Meta token.');
$gateway->responses = array(array(403, '{"code":"insufficient_balance","error":"Not enough money"}'));
whatsapp_assert(!$gateway->sendMessage('08012345678', 'A reply') && strpos($gateway->getLastError(), 'balance') !== false, 'Managed-provider billing failures must be actionable.');
$gateway->responses = array(array(200, '{"success":true}'));
whatsapp_assert(!$gateway->sendMessage('08012345678', 'A reply'), '360dialog must return a message ID before a send is counted as accepted.');
$gateway->responses = array(array(200, '{"messages":[{"id":"wamid.paused","message_status":"paused"}]}'));
whatsapp_assert(!$gateway->sendTemplateMessage('08012345678', 'school_notice', 'en'), 'Paused messages must not be reported as successful.');
$gateway->responses = array(array(200, '{"messages":[{"id":"wamid.held","message_status":"held_for_quality_assessment"}]}'));
whatsapp_assert($gateway->sendTemplateMessage('08012345678', 'school_notice', 'en'), 'Provider acceptance pending quality assessment must remain acceptance, not a delivery claim.');
$gateway = dialog_gateway('');
whatsapp_assert(!$gateway->sendMessage('08012345678', 'A reply') && !$gateway->requests, 'A missing channel key must block the request.');
$gateway = dialog_gateway("bad\r\nInjected: header");
whatsapp_assert(!$gateway->sendMessage('08012345678', 'A reply') && !$gateway->requests, 'A malformed channel key must not inject HTTP headers.');

$post = array('group_delivery_type' => 'template', 'group_title' => 'School notice', 'meta_template_name' => 'school_notice', 'meta_template_language' => 'en', 'meta_template_parameters' => '{{display_name}}', 'user' => array('parent'));
$controller = whatsapp_compose($post, array('provider' => '360dialog', 'dialog360' => array('api_key' => 'test-channel-api-key')));
$response = whatsapp_compose_response($controller);
whatsapp_assert($response['status'] === 0 && $response['summary'] === array('accepted' => 2, 'failed' => 0, 'missing_phone' => 1), 'The composer must allow 360dialog templates and count missing guardian phones.');
whatsapp_assert($controller->whatsappgateway->requests[0]['url'] === 'https://waba-v2.360dialog.io/messages', 'The composer must route managed template sends to 360dialog.');
whatsapp_assert(strpos(implode('\n', $whatsapp_logs), 'test-channel-api-key') === false, 'Provider responses must not leak channel keys into logs.');

echo "360dialog gateway and compose tests passed\n";
