<?php

if (!defined('BASEPATH')) {
    exit('No direct script access allowed');
}

class Whatsappgateway {

    private $_CI;
    private $config = array();
    private $last_error = '';

    public function __construct() {
        $this->_CI = &get_instance();
        $this->_CI->load->model('setting_model');
        $this->_CI->load->model('student_model');
        $this->_CI->load->model('staff_model');
        $this->_CI->load->model('whatsappconfig_model');
        $this->config = $this->_CI->whatsappconfig_model->getGatewayConfig();

        if (!is_array($this->config)) {
            $this->config = array();
        }
    }

    public function isEnabled() {
        return $this->toBool($this->configValue('enabled', false));
    }

    public function getProvider() {
        return strtolower(trim((string) $this->configValue('provider', 'meta_cloud')));
    }

    public function getLastError() {
        return $this->last_error;
    }

    public function usesApprovedTemplates() {
        return in_array($this->getProvider(), array('meta_cloud', '360dialog'), true);
    }

    public function getConfigurationErrors() {
        $errors = array();
        if (!$this->isEnabled()) {
            $errors[] = 'WhatsApp sending is disabled. Enable it in System Settings > WhatsApp Settings.';
        }
        if ($this->getProvider() === 'meta_cloud') {
            $meta = $this->configValue('meta_cloud', array());
            if (!isset($meta['phone_number_id']) || trim((string) $meta['phone_number_id']) === '') {
                $errors[] = 'Set the Meta Phone Number ID in WhatsApp Settings.';
            }
            if (!isset($meta['access_token']) || trim((string) $meta['access_token']) === '') {
                $errors[] = 'Set the Meta Access Token in WhatsApp Settings.';
            }
        } elseif ($this->getProvider() === '360dialog') {
            $dialog = $this->configValue('dialog360', array());
            if (!isset($dialog['api_key']) || trim((string) $dialog['api_key']) === '') {
                $errors[] = 'Set the 360dialog channel API key in WhatsApp Settings.';
            }
        } elseif ($this->getProvider() === 'custom_webhook') {
            $webhook = $this->configValue('custom_webhook', array());
            if (!isset($webhook['url']) || trim((string) $webhook['url']) === '') {
                $errors[] = 'Set the Custom Webhook URL in WhatsApp Settings.';
            }
        } else {
            $errors[] = 'Select a supported provider in WhatsApp Settings.';
        }
        return $errors;
    }

    private function beginSend($phone) {
        $this->last_error = '';
        $errors = $this->getConfigurationErrors();
        if (!empty($errors)) {
            $this->fail(implode(' ', $errors));
            return '';
        }
        $number = is_scalar($phone) ? $this->normalizePhone($phone) : '';
        if (!preg_match('/^[1-9][0-9]{7,14}$/', $number)) {
            $this->fail('The recipient needs a valid WhatsApp phone number including the country code.');
            return '';
        }
        return $number;
    }

    private function fail($message) {
        $this->last_error = $message;
        log_message('error', 'WhatsApp send failed: ' . $message);
        return false;
    }

    public function shouldAutoSendLoginCredential($sender_details) {
        if (!$this->isEnabled() || !$this->toBool($this->configValue('auto_send_login_credentials', false))) {
            return false;
        }

        $credential_for = isset($sender_details['credential_for']) ? strtolower($sender_details['credential_for']) : '';
        $allowed        = $this->configValue('auto_credential_for', array('parent'));

        return in_array($credential_for, $allowed, true);
    }

    public function sendLoginCredential($sender_details, $template = '') {
        $send_to = $this->beginSend(isset($sender_details['contact_no']) ? $sender_details['contact_no'] : '');

        if ($send_to === '') {
            return false;
        }

        $sender_details = $this->prepareLoginCredentialDetails($sender_details);
        $provider       = $this->getProvider();

        if ($this->usesApprovedTemplates()) {
            return $this->sendMetaCloudTemplate($send_to, $sender_details);
        }

        if ($provider === 'custom_webhook') {
            return $this->sendCustomWebhook($send_to, $sender_details, $template);
        }

        return $this->fail('Select a supported provider in WhatsApp Settings.');
    }

    public function sendMessage($phone, $message, $title = '') {
        $send_to = $this->beginSend($phone);

        if ($send_to === '') {
            return false;
        }
        if (!is_scalar($message) || trim((string) $message) === '') {
            return $this->fail('Enter a WhatsApp message before sending.');
        }

        $provider = $this->getProvider();

        if ($this->usesApprovedTemplates()) {
            return $this->sendMetaCloudText($send_to, (string) $message);
        }

        if ($provider === 'custom_webhook') {
            return $this->sendCustomWebhookMessage($send_to, (string) $message, $title);
        }

        return $this->fail('Select a supported provider in WhatsApp Settings.');
    }

    public function sendTemplateMessage($phone, $template_name, $language, $values = array()) {
        $send_to = $this->beginSend($phone);
        if ($send_to === '') {
            return false;
        }
        if (!$this->usesApprovedTemplates()) {
            return $this->fail('Approved template sending requires Meta Cloud API or 360dialog.');
        }
        if (!is_scalar($template_name) || !preg_match('/^[a-z0-9_]+$/', (string) $template_name)) {
            return $this->fail('Enter the exact approved Meta template name using lowercase letters, numbers and underscores.');
        }
        if (!is_scalar($language) || !preg_match('/^[a-z]{2,3}(?:_[A-Z]{2})?$/', (string) $language)) {
            return $this->fail('Enter the approved template language code, such as en or en_US.');
        }
        if (!is_array($values)) {
            return $this->fail('Enter the template body values in their approved order.');
        }
        $parameters = array();
        foreach ($values as $value) {
            if (!is_scalar($value) || trim((string) $value) === '') {
                return $this->fail('Each template body value must contain text. Check the recipient details and template variables.');
            }
            $parameters[] = array('type' => 'text', 'text' => (string) $value);
        }
        return $this->sendMetaCloudApprovedTemplate($send_to, (string) $template_name, (string) $language, $parameters);
    }

    private function prepareLoginCredentialDetails($sender_details) {
        $credential_for = isset($sender_details['credential_for']) ? $sender_details['credential_for'] : '';
        $school         = $this->_CI->setting_model->getSetting();
        $school_name    = (!empty($school) && isset($school->name)) ? $school->name : 'School';
        $use_middlename = (!empty($school) && isset($school->middlename)) ? $school->middlename : 0;
        $use_lastname   = (!empty($school) && isset($school->lastname)) ? $school->lastname : 1;

        $sender_details['school_name'] = $school_name;

        if ($credential_for == 'student') {
            $student = $this->_CI->student_model->get($sender_details['id']);
            $sender_details['url'] = site_url('site/userlogin');
            $sender_details['display_name'] = $this->_CI->customlib->getFullName(
                $student['firstname'],
                $student['middlename'],
                $student['lastname'],
                $use_middlename,
                $use_lastname
            );
        } elseif ($credential_for == 'parent') {
            $parent = $this->_CI->student_model->get($sender_details['id']);
            $sender_details['url'] = site_url('site/userlogin');
            $sender_details['display_name'] = $parent['guardian_name'];
        } elseif ($credential_for == 'staff') {
            $staff = $this->_CI->staff_model->get($sender_details['id']);
            $sender_details['url'] = site_url('site/login');
            $sender_details['display_name'] = $staff['name'];
        }

        foreach ($sender_details as $key => $value) {
            if (is_array($value) || is_object($value)) {
                $sender_details[$key] = '';
            }
        }

        $sender_details = $this->suppressStoredPasswordHashes($sender_details);

        return $sender_details;
    }

    private function sendMetaCloudTemplate($send_to, $sender_details) {
        $meta_config     = $this->configValue('meta_cloud', array());
        $template_name   = isset($meta_config['template_name']) ? trim($meta_config['template_name']) : '';
        $language        = isset($meta_config['template_language']) ? trim($meta_config['template_language']) : 'en';
        if ($template_name === '') {
            return $this->fail('Set the approved login credential Template Name in WhatsApp Settings.');
        }
        return $this->sendMetaCloudApprovedTemplate($send_to, $template_name, $language, $this->buildTemplateParameters($sender_details));
    }

    private function sendMetaCloudApprovedTemplate($send_to, $template_name, $language, $parameters) {
        $meta_config     = $this->configValue('meta_cloud', array());
        $phone_number_id = isset($meta_config['phone_number_id']) ? trim($meta_config['phone_number_id']) : '';
        $access_token    = isset($meta_config['access_token']) ? trim($meta_config['access_token']) : '';
        $api_version     = isset($meta_config['graph_api_version']) ? trim($meta_config['graph_api_version']) : 'v20.0';

        if (($this->getProvider() === 'meta_cloud' && ($phone_number_id === '' || $access_token === '')) || $template_name === '') {
            return $this->fail('Set the Meta Phone Number ID, Access Token and approved Template Name in WhatsApp Settings.');
        }

        $payload = array(
            'messaging_product' => 'whatsapp',
            'recipient_type'    => 'individual',
            'to'                => $send_to,
            'type'              => 'template',
            'template'          => array(
                'name'     => $template_name,
                'language' => array('code' => $language),
            ),
        );

        if (!empty($parameters)) {
            $payload['template']['components'] = array(
                array(
                    'type'       => 'body',
                    'parameters' => $parameters,
                ),
            );
        }

        return $this->postPlatformPayload($payload);
    }

    private function sendCustomWebhook($send_to, $sender_details, $template) {
        $webhook_config = $this->configValue('custom_webhook', array());
        $url            = isset($webhook_config['url']) ? trim($webhook_config['url']) : '';
        $token          = isset($webhook_config['bearer_token']) ? trim($webhook_config['bearer_token']) : '';

        if ($url === '') {
            return $this->fail('Set the Custom Webhook URL in WhatsApp Settings.');
        }

        $payload = array(
            'to'         => $send_to,
            'message'    => $this->renderTemplate($sender_details, $template),
            'parameters' => $sender_details,
        );

        $headers = array('Content-Type: application/json');

        if ($token !== '') {
            $headers[] = 'Authorization: Bearer ' . $token;
        }

        return $this->postJson($url, $payload, $headers);
    }

    private function sendMetaCloudText($send_to, $message) {
        $meta_config     = $this->configValue('meta_cloud', array());
        $phone_number_id = isset($meta_config['phone_number_id']) ? trim($meta_config['phone_number_id']) : '';
        $access_token    = isset($meta_config['access_token']) ? trim($meta_config['access_token']) : '';
        $api_version     = isset($meta_config['graph_api_version']) ? trim($meta_config['graph_api_version']) : 'v20.0';

        if ($this->getProvider() === 'meta_cloud' && ($phone_number_id === '' || $access_token === '')) {
            return $this->fail('Set the Meta Phone Number ID and Access Token in WhatsApp Settings.');
        }

        $payload = array(
            'messaging_product' => 'whatsapp',
            'recipient_type'    => 'individual',
            'to'                => $send_to,
            'type'              => 'text',
            'text'              => array(
                'preview_url' => false,
                'body'        => $message,
            ),
        );

        return $this->postPlatformPayload($payload);
    }

    private function postPlatformPayload($payload) {
        if ($this->getProvider() === '360dialog') {
            $dialog = $this->configValue('dialog360', array());
            $key = isset($dialog['api_key']) ? trim((string) $dialog['api_key']) : '';
            if ($key === '' || preg_match('/[\r\n]/', $key)) {
                return $this->fail('Set a valid 360dialog channel API key in WhatsApp Settings.');
            }
            return $this->postJson('https://waba-v2.360dialog.io/messages', $payload, array(
                'D360-API-KEY: ' . $key,
                'Content-Type: application/json',
            ));
        }
        $meta = $this->configValue('meta_cloud', array());
        $version = isset($meta['graph_api_version']) ? trim((string) $meta['graph_api_version']) : 'v20.0';
        $url = 'https://graph.facebook.com/' . $version . '/' . trim((string) $meta['phone_number_id']) . '/messages';
        return $this->postJson($url, $payload, array(
            'Authorization: Bearer ' . trim((string) $meta['access_token']),
            'Content-Type: application/json',
        ));
    }

    private function sendCustomWebhookMessage($send_to, $message, $title = '') {
        $webhook_config = $this->configValue('custom_webhook', array());
        $url            = isset($webhook_config['url']) ? trim($webhook_config['url']) : '';
        $token          = isset($webhook_config['bearer_token']) ? trim($webhook_config['bearer_token']) : '';

        if ($url === '') {
            return $this->fail('Set the Custom Webhook URL in WhatsApp Settings.');
        }

        $payload = array(
            'to'      => $send_to,
            'title'   => $title,
            'message' => $message,
        );

        $headers = array('Content-Type: application/json');

        if ($token !== '') {
            $headers[] = 'Authorization: Bearer ' . $token;
        }

        return $this->postJson($url, $payload, $headers);
    }

    private function buildTemplateParameters($sender_details) {
        $parameters = array();
        $keys       = $this->configValue('login_template_parameters', array('display_name', 'school_name', 'url', 'username', 'password'));

        foreach ($keys as $key) {
            $parameters[] = array(
                'type' => 'text',
                'text' => isset($sender_details[$key]) ? (string) $sender_details[$key] : '',
            );
        }

        return $parameters;
    }

    private function renderTemplate($sender_details, $template) {
        if (trim((string) $template) === '') {
            $template = 'Hello {{display_name}}, your {{school_name}} portal login details are: Login URL: {{url}} Username: {{username}} Password: {{password}}';
        }

        foreach ($sender_details as $key => $value) {
            $template = str_replace('{{' . $key . '}}', (string) $value, $template);
        }

        return $template;
    }

    private function normalizePhone($phone) {
        $phone = trim((string) $phone);
        $international = preg_match('/^(?:\+|00)/', $phone) === 1;
        $number = preg_replace('/\D+/', '', $phone);

        if ($number === '') {
            return '';
        }

        if (strpos($number, '00') === 0) {
            $number = substr($number, 2);
        }

        $country_code = preg_replace('/\D+/', '', (string) $this->configValue('default_country_code', '234'));

        if ($country_code !== '' && !$international) {
            if (strlen($number) === 11 && strpos($number, '0') === 0) {
                $number = $country_code . substr($number, 1);
            } elseif (strlen($number) === 10) {
                $number = $country_code . $number;
            }
        }

        return $number;
    }

    protected function postJson($url, $payload, $headers) {
        if (!function_exists('curl_init')) {
            return $this->fail('PHP cURL is unavailable. Ask the server administrator to enable it.');
        }
        $json = json_encode($payload);
        if ($json === false) {
            return $this->fail('The WhatsApp message contains invalid text encoding.');
        }

        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $json);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 10);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 2);

        $response  = curl_exec($ch);
        $curl_errno = curl_errno($ch);
        $http_code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($response === false) {
            return $this->fail('Unable to connect to the WhatsApp provider (cURL code ' . $curl_errno . '). Check the server connection and TLS certificates.');
        }
        return $this->handleProviderResponse($http_code, $response);
    }

    protected function handleProviderResponse($http_code, $response) {
        $data = json_decode($response, true);
        $error_code = is_array($data) && isset($data['error']) && is_array($data['error']) && isset($data['error']['code']) ? (int) $data['error']['code'] : 0;
        $has_error = is_array($data) && (!empty($data['error'])
            || (array_key_exists('success', $data) && !$this->toBool($data['success']))
            || (isset($data['status']) && is_scalar($data['status']) && in_array(strtolower((string) $data['status']), array('error', 'failed', 'failure'), true)));
        if ($http_code < 200 || $http_code >= 300 || $has_error) {
            if ($this->getProvider() === '360dialog') {
                if ((int) $http_code === 401) {
                    return $this->fail('The 360dialog channel API key is invalid. Update the key from the connected number in 360dialog Hub. (HTTP 401)');
                }
                if (is_array($data) && isset($data['code']) && $data['code'] === 'insufficient_balance') {
                    return $this->fail('The 360dialog account has insufficient messaging balance. Check billing in 360dialog Hub. (HTTP ' . (int) $http_code . ')');
                }
            }
            $reasons = array(
                190 => 'The Meta access token is invalid or expired. Update the Access Token in WhatsApp Settings.',
                10 => 'The Meta access token does not have permission to send WhatsApp messages.',
                200 => 'The Meta access token does not have permission to send WhatsApp messages.',
                131047 => 'The WhatsApp reply window has closed. Send an approved Meta template, or ask the recipient to message the school first.',
                132000 => 'The template body value count does not match the approved Meta template.',
                132001 => 'The Meta template name or language is not approved or does not exist for this WhatsApp account.',
                132015 => 'The approved Meta template is paused. Check WhatsApp Manager.',
                132016 => 'The approved Meta template is disabled. Check WhatsApp Manager.',
                131026 => 'WhatsApp could not deliver to this recipient. Check that the phone number has a WhatsApp account.',
                131030 => 'The recipient is not in the allowed test recipient list in the Meta developer dashboard.',
                131042 => 'The WhatsApp business account has a payment or eligibility problem. Check WhatsApp Manager.',
                130429 => 'The WhatsApp provider rate limit was reached. Try again later.',
                100 => 'Meta rejected a request parameter. Check the Phone Number ID, template name, language and body values.',
            );
            $reason = isset($reasons[$error_code]) ? $reasons[$error_code] : 'The WhatsApp provider rejected the message. Check the provider account and configuration.';
            return $this->fail($reason . ' (HTTP ' . (int) $http_code . ($error_code ? ', provider code ' . $error_code : '') . ')');
        }
        if ($this->usesApprovedTemplates() && empty($data['messages'][0]['id'])) {
            return $this->fail('The WhatsApp provider did not return a message ID. The message was not accepted.');
        }
        if ($this->usesApprovedTemplates() && isset($data['messages'][0]['message_status']) && $data['messages'][0]['message_status'] === 'paused') {
            return $this->fail('The WhatsApp provider paused this message. Check the template and account in the provider dashboard.');
        }
        log_message('info', 'WhatsApp provider accepted message: http_code=' . (int) $http_code);
        return true;
    }

    private function configValue($key, $default = null) {
        if (array_key_exists($key, $this->config)) {
            return $this->config[$key];
        }

        return $default;
    }

    private function suppressStoredPasswordHashes($values) {
        foreach ($values as $key => $value) {
            if (stripos((string) $key, 'password') !== false && $this->looksLikeStoredPasswordHash($value)) {
                $values[$key] = '';
            }
        }

        return $values;
    }

    private function looksLikeStoredPasswordHash($value) {
        if (!is_scalar($value) || $value === '') {
            return false;
        }

        $value = trim((string) $value);

        if ($value === '') {
            return false;
        }

        if (function_exists('password_get_info')) {
            $info = password_get_info($value);
            if (!empty($info['algo']) || (!empty($info['algoName']) && $info['algoName'] !== 'unknown')) {
                return true;
            }
        }

        return preg_match('/^\$(2y|2a|2b|argon2i|argon2id)\$/', $value) === 1;
    }

    private function toBool($value) {
        if (is_bool($value)) {
            return $value;
        }
        if (!is_scalar($value)) {
            return false;
        }

        return in_array(strtolower(trim((string) $value)), array('1', 'true', 'yes', 'enabled', 'on'), true);
    }
}
