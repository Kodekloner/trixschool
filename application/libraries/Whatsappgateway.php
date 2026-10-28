<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class Whatsappgateway {
    private $_CI;
    private $config = array();
    private $last_error = '';
    private $last_message = array();

    public function __construct($configuration = null) {
        $this->_CI = &get_instance();
        foreach (array('setting_model', 'student_model', 'staff_model', 'whatsappconfig_model') as $model) {
            $this->_CI->load->model($model);
        }
        $this->config = is_array($configuration) ? $configuration : $this->_CI->whatsappconfig_model->getGatewayConfig();
    }

    public function isEnabled() { return $this->toBool($this->configValue('enabled', false)); }
    public function getProvider() { return 'twilio'; }
    public function getEnvironment() { return $this->configValue('environment', 'sandbox'); }
    public function getLastError() { return $this->last_error; }
    public function getLastMessage() { return $this->last_message; }
    public function usesApprovedTemplates() { return true; }

    private function credentialErrors() {
        $twilio = $this->configValue('twilio', array());
        $errors = array();
        if ($this->configValue('provider', 'twilio') !== 'twilio') { $errors[] = 'Configure Twilio in WhatsApp Settings. The previous provider is no longer used.'; }
        if (!in_array($this->getEnvironment(), array('sandbox', 'production'), true)) { $errors[] = 'Choose Sandbox or Production in WhatsApp Settings.'; }
        if (empty($twilio['account_sid']) || !preg_match('/^AC[0-9a-fA-F]{32}$/', $twilio['account_sid'])) { $errors[] = 'Set a valid Twilio Account SID in WhatsApp Settings.'; }
        if (empty($twilio['auth_token']) || !preg_match('/^[0-9a-fA-F]{32}$/', $twilio['auth_token'])) { $errors[] = 'Set a valid Twilio Auth Token in WhatsApp Settings.'; }
        return $errors;
    }

    public function getConfigurationErrors() {
        $errors = $this->credentialErrors();
        $twilio = $this->configValue('twilio', array());
        if (!$this->isEnabled()) { $errors[] = 'WhatsApp sending is disabled. Enable production sending in WhatsApp Settings when ready.'; }
        if (empty($twilio['from']) || !preg_match('/^\+[1-9][0-9]{7,14}$/', $twilio['from'])) { $errors[] = 'Set the Twilio WhatsApp sender using +countrycode format.'; }
        if ($this->getEnvironment() === 'sandbox' && !$this->configValue('diagnostic', false)) { $errors[] = 'Sandbox mode allows single-recipient tests from WhatsApp Settings only. Group and automatic messages require Production.'; }
        return $errors;
    }

    private function fail($message) {
        $this->last_error = $message;
        log_message('error', 'WhatsApp: ' . $message);
        return false;
    }

    private function beginSend($phone) {
        $this->last_error = '';
        $this->last_message = array();
        $errors = $this->getConfigurationErrors();
        if ($errors) { $this->fail(implode(' ', $errors)); return ''; }
        if (!is_scalar($phone) || !preg_match('/^[+0-9() .-]+$/', (string) $phone)) { $this->fail('Enter a valid recipient phone number including the country code.'); return ''; }
        $number = $this->normalizePhone($phone);
        if (!preg_match('/^[1-9][0-9]{7,14}$/', $number)) { $this->fail('Enter a valid recipient phone number including the country code.'); return ''; }
        if ($this->configValue('diagnostic', false)) {
            $twilio = $this->configValue('twilio', array());
            if (empty($twilio['test_recipient']) || !preg_match('/^\+[1-9][0-9]{7,14}$/', $twilio['test_recipient']) || $number !== substr($twilio['test_recipient'], 1)) {
                $this->fail('Save a test recipient in +countrycode format. Tests can send only to that saved number.');
                return '';
            }
        }
        return $number;
    }

    public function shouldAutoSendLoginCredential($details) {
        return $this->isEnabled() && $this->getEnvironment() === 'production'
            && !$this->configValue('diagnostic', false)
            && $this->toBool($this->configValue('auto_send_login_credentials', false))
            && in_array(isset($details['credential_for']) ? $details['credential_for'] : '', $this->configValue('auto_credential_for', array()), true);
    }

    public function sendMessage($phone, $message, $title = '') {
        $to = $this->beginSend($phone);
        if ($to === '') { return false; }
        if (!is_scalar($message) || trim((string) $message) === '') { return $this->fail('Enter a WhatsApp message before sending.'); }
        return $this->sendPayload($to, array('Body' => (string) $message));
    }

    // Content SID selects both the approved wording and language in Twilio.
    public function sendTemplateMessage($phone, $content_sid, $language = '', $values = array()) {
        $to = $this->beginSend($phone);
        if ($to === '') { return false; }
        if (!is_string($content_sid) || !preg_match('/^HX[0-9a-fA-F]{32}$/', $content_sid)) { return $this->fail('Enter a valid Twilio Content SID (HX followed by 32 hex characters).'); }
        if (!is_array($values) || count($values) > 100) { return $this->fail('Enter at most 100 ordered template values.'); }
        $variables = array();
        foreach (array_values($values) as $index => $value) {
            if (!is_scalar($value) || trim((string) $value) === '') { return $this->fail('Each template value must contain text. Check the recipient details.'); }
            $variables[(string) ($index + 1)] = (string) $value;
        }
        $payload = array('ContentSid' => $content_sid);
        if ($variables) {
            $json = json_encode((object) $variables, JSON_UNESCAPED_UNICODE);
            if ($json === false) { return $this->fail('The WhatsApp template contains invalid text encoding.'); }
            $payload['ContentVariables'] = $json;
        }
        return $this->sendPayload($to, $payload);
    }

    public function sendLoginCredential($details, $template = '') {
        $twilio = $this->configValue('twilio', array());
        $details = $this->prepareLoginCredentialDetails($details);
        $values = array();
        foreach ($this->configValue('login_template_parameters', array('display_name', 'school_name', 'url', 'username', 'password')) as $key) {
            $values[] = isset($details[$key]) ? $details[$key] : '';
        }
        return $this->sendTemplateMessage(isset($details['contact_no']) ? $details['contact_no'] : '', isset($twilio['login_content_sid']) ? $twilio['login_content_sid'] : '', '', $values);
    }

    private function sendPayload($to, $payload) {
        $twilio = $this->configValue('twilio', array());
        $payload['From'] = 'whatsapp:' . $twilio['from'];
        $payload['To'] = 'whatsapp:+' . $to;
        $response = $this->request('POST', $this->accountUrl() . '/Messages.json', $payload);
        if ($response === false) { return false; }
        if (empty($response['sid']) || !preg_match('/^(SM|MM)[0-9a-fA-F]{32}$/', $response['sid'])
            || empty($response['status']) || !in_array($response['status'], array('accepted', 'queued', 'sending', 'sent', 'delivered', 'read'), true)) {
            return $this->fail('Twilio did not accept the message with a valid Message SID and status. Check Messaging Logs.');
        }
        $this->last_message = array('sid' => $response['sid'], 'status' => $response['status']);
        log_message('info', 'Twilio accepted WhatsApp request; delivery must be checked separately.');
        return true;
    }

    public function checkConnection() {
        $this->last_error = '';
        $errors = $this->credentialErrors();
        if ($errors) { return $this->fail(implode(' ', $errors)); }
        $account = $this->request('GET', $this->accountUrl() . '.json');
        if ($account === false) { return false; }
        $twilio = $this->configValue('twilio', array());
        if (!isset($account['sid']) || $account['sid'] !== $twilio['account_sid']) { return $this->fail('Twilio returned an unexpected account. Verify the Account SID and Auth Token.'); }
        if (!isset($account['status']) || $account['status'] !== 'active') { return $this->fail('The Twilio account is not active. Check account suspension, closure and billing in Twilio Console.'); }
        return true;
    }

    public function fetchMessageStatus($sid) {
        $this->last_error = '';
        if (!is_string($sid) || !preg_match('/^(SM|MM)[0-9a-fA-F]{32}$/', $sid)) { return $this->fail('Send a test message first.'); }
        $errors = $this->credentialErrors();
        if ($errors) { return $this->fail(implode(' ', $errors)); }
        $message = $this->request('GET', $this->accountUrl() . '/Messages/' . $sid . '.json', array(), true);
        if ($message === false) { return false; }
        if (!isset($message['sid'], $message['status']) || $message['sid'] !== $sid || !in_array($message['status'], array('accepted', 'queued', 'sending', 'sent', 'delivered', 'read', 'failed', 'undelivered', 'canceled'), true)) {
            return $this->fail('Twilio returned an invalid message status. Check Messaging Logs.');
        }
        $this->last_message = array('sid' => $sid, 'status' => $message['status'], 'error_code' => isset($message['error_code']) && is_numeric($message['error_code']) ? (int) $message['error_code'] : 0);
        return $this->last_message;
    }

    private function accountUrl() {
        $twilio = $this->configValue('twilio', array());
        return 'https://api.twilio.com/2010-04-01/Accounts/' . $twilio['account_sid'];
    }

    private function request($method, $url, $payload = array(), $delivery_status = false) {
        $result = $this->httpRequest($method, $url, $payload);
        if ($result === false) { return false; }
        $http = (int) $result[0];
        $data = json_decode($result[1], true);
        $code = is_array($data) && isset($data['code']) && is_numeric($data['code']) ? (int) $data['code'] : 0;
        if (is_array($data) && !empty($data['error_code']) && is_numeric($data['error_code'])) { $code = (int) $data['error_code']; }
        if ($http < 200 || $http >= 300 || (!$delivery_status && ($code || (isset($data['status']) && in_array($data['status'], array('failed', 'undelivered', 'canceled'), true))))) {
            return $this->fail($this->errorExplanation($http, $code));
        }
        if (!is_array($data)) { return $this->fail('Twilio returned an unreadable response. Check the server connection and try again.'); }
        return $data;
    }

    public function errorExplanation($http, $code) {
        $reasons = array(
            20003 => 'Twilio authentication failed. Check the Account SID and Auth Token for this environment. Use live account credentials for the WhatsApp Sandbox.',
            20404 => 'The Twilio resource was not found. Check the account and Message SID.',
            20429 => 'Twilio rate limit reached. Wait before trying again.',
            21211 => 'The recipient number is invalid. Use +countrycode format.',
            21606 => 'The sender is not available on this Twilio account.',
            21608 => 'This trial account cannot send to the recipient. Verify the recipient or review Twilio account compliance.',
            21610 => 'The recipient has opted out. Do not retry until the recipient opts in.',
            21614 => 'The recipient is not a valid messaging destination.',
            21617 => 'The message is too long. Shorten it.',
            21654 => 'A Content SID is required for template variables. Select a Twilio Content template.',
            21656 => 'The template variables are invalid. Match the numbered variables in Twilio Content Template Builder.',
            21658 => 'A message parameter or template value exceeds its character limit. Shorten the content.',
            63003 => 'The recipient is not available on WhatsApp. Check the phone number.',
            63007 => 'The WhatsApp sender is not connected to this Twilio account. Activate the Sandbox or register the production sender.',
            63015 => 'The test recipient has not joined this Sandbox, or membership expired. Send the Sandbox join code again from that phone.',
            63016 => 'The 24-hour reply window is closed. Use an approved Twilio Content SID template.',
            63018 => 'WhatsApp rate limit reached. Wait before retrying; Sandbox sends must be spaced apart.',
            63024 => 'WhatsApp declined delivery. Check the recipient and Twilio Messaging Logs.',
            63027 => 'The template is unavailable in the expected language. Check its Content SID in Twilio.',
            63028 => 'The template variable count does not match. Check the Content SID and numbered values.',
            63040 => 'The WhatsApp template was rejected. Choose an approved Twilio Content template.'
        );
        $reason = isset($reasons[$code]) ? $reasons[$code] : ($http === 401 || $http === 403
            ? 'Twilio denied access. Check account credentials, permissions and account status.'
            : ($http === 429 ? 'Twilio rate limit reached. Wait before retrying.' : 'Twilio rejected the request. Check Messaging Logs, sender approval, template and account balance.'));
        return $reason . ' (HTTP ' . (int) $http . ($code ? ', Twilio code ' . (int) $code : '') . ')';
    }

    protected function httpRequest($method, $url, $payload = array()) {
        if (!function_exists('curl_init')) { return $this->fail('PHP cURL is unavailable. Ask the administrator to enable it.'); }
        $twilio = $this->configValue('twilio', array());
        $ch = curl_init($url);
        curl_setopt_array($ch, array(
            CURLOPT_RETURNTRANSFER => true, CURLOPT_CUSTOMREQUEST => $method,
            CURLOPT_HTTPAUTH => CURLAUTH_BASIC, CURLOPT_USERPWD => $twilio['account_sid'] . ':' . $twilio['auth_token'],
            CURLOPT_HTTPHEADER => array('Accept: application/json', 'Content-Type: application/x-www-form-urlencoded'),
            CURLOPT_TIMEOUT => 30, CURLOPT_CONNECTTIMEOUT => 10,
            CURLOPT_SSL_VERIFYPEER => true, CURLOPT_SSL_VERIFYHOST => 2, CURLOPT_FOLLOWLOCATION => false
        ));
        if ($method === 'POST') { curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($payload, '', '&', PHP_QUERY_RFC3986)); }
        $body = curl_exec($ch);
        $errno = curl_errno($ch);
        $http = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        if ($body === false) {
            return $this->fail('Unable to connect to Twilio (cURL code ' . (int) $errno . '). Check outbound HTTPS access, DNS and TLS certificates. The request may have reached Twilio; check Messaging Logs before resending.');
        }
        return array($http, $body);
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
