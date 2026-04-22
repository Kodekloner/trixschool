<?php

if (!defined('BASEPATH')) {
    exit('No direct script access allowed');
}

class Whatsappgateway {

    private $_CI;
    private $config = array();

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

    public function shouldAutoSendLoginCredential($sender_details) {
        if (!$this->isEnabled() || !$this->toBool($this->configValue('auto_send_login_credentials', false))) {
            return false;
        }

        $credential_for = isset($sender_details['credential_for']) ? strtolower($sender_details['credential_for']) : '';
        $allowed        = $this->configValue('auto_credential_for', array('parent'));

        return in_array($credential_for, $allowed, true);
    }

    public function sendLoginCredential($sender_details, $template = '') {
        if (!$this->isEnabled()) {
            log_message('debug', 'WhatsApp gateway skipped: gateway is disabled.');
            return false;
        }

        $send_to = $this->normalizePhone(isset($sender_details['contact_no']) ? $sender_details['contact_no'] : '');

        if ($send_to === '') {
            log_message('error', 'WhatsApp login credential send failed: recipient phone number is empty.');
            return false;
        }

        $sender_details = $this->prepareLoginCredentialDetails($sender_details);
        $provider       = strtolower((string) $this->configValue('provider', 'meta_cloud'));

        if ($provider === 'meta_cloud') {
            return $this->sendMetaCloudTemplate($send_to, $sender_details);
        }

        if ($provider === 'custom_webhook') {
            return $this->sendCustomWebhook($send_to, $sender_details, $template);
        }

        log_message('error', 'WhatsApp login credential send failed: unsupported provider ' . $provider);
        return false;
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

        return $sender_details;
    }

    private function sendMetaCloudTemplate($send_to, $sender_details) {
        $meta_config     = $this->configValue('meta_cloud', array());
        $phone_number_id = isset($meta_config['phone_number_id']) ? trim($meta_config['phone_number_id']) : '';
        $access_token    = isset($meta_config['access_token']) ? trim($meta_config['access_token']) : '';
        $template_name   = isset($meta_config['template_name']) ? trim($meta_config['template_name']) : '';
        $language        = isset($meta_config['template_language']) ? trim($meta_config['template_language']) : 'en';
        $api_version     = isset($meta_config['graph_api_version']) ? trim($meta_config['graph_api_version']) : 'v20.0';

        if ($phone_number_id === '' || $access_token === '' || $template_name === '') {
            log_message('error', 'WhatsApp Meta Cloud send failed: phone number ID, access token, or template name is missing.');
            return false;
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

        $parameters = $this->buildTemplateParameters($sender_details);

        if (!empty($parameters)) {
            $payload['template']['components'] = array(
                array(
                    'type'       => 'body',
                    'parameters' => $parameters,
                ),
            );
        }

        $url = 'https://graph.facebook.com/' . $api_version . '/' . $phone_number_id . '/messages';

        return $this->postJson($url, $payload, array(
            'Authorization: Bearer ' . $access_token,
            'Content-Type: application/json',
        ));
    }

    private function sendCustomWebhook($send_to, $sender_details, $template) {
        $webhook_config = $this->configValue('custom_webhook', array());
        $url            = isset($webhook_config['url']) ? trim($webhook_config['url']) : '';
        $token          = isset($webhook_config['bearer_token']) ? trim($webhook_config['bearer_token']) : '';

        if ($url === '') {
            log_message('error', 'WhatsApp custom webhook send failed: webhook URL is missing.');
            return false;
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
        $number = preg_replace('/\D+/', '', (string) $phone);

        if ($number === '') {
            return '';
        }

        if (strpos($number, '00') === 0) {
            $number = substr($number, 2);
        }

        $country_code = preg_replace('/\D+/', '', (string) $this->configValue('default_country_code', '234'));

        if ($country_code !== '') {
            if (strlen($number) === 11 && strpos($number, '0') === 0) {
                $number = $country_code . substr($number, 1);
            } elseif (strlen($number) === 10 && strpos($number, $country_code) !== 0) {
                $number = $country_code . $number;
            }
        }

        return $number;
    }

    private function postJson($url, $payload, $headers) {
        if (!function_exists('curl_init')) {
            log_message('error', 'WhatsApp send failed: PHP cURL extension is not available.');
            return false;
        }

        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 2);

        $response  = curl_exec($ch);
        $curl_error = curl_error($ch);
        $http_code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($response === false || $http_code < 200 || $http_code >= 300) {
            log_message('error', 'WhatsApp send failed: http_code=' . $http_code . ', curl_error=' . $curl_error . ', response=' . $response);
            return false;
        }

        log_message('info', 'WhatsApp send success: http_code=' . $http_code);
        return true;
    }

    private function configValue($key, $default = null) {
        if (array_key_exists($key, $this->config)) {
            return $this->config[$key];
        }

        return $default;
    }

    private function toBool($value) {
        if (is_bool($value)) {
            return $value;
        }

        return in_array(strtolower((string) $value), array('1', 'true', 'yes', 'enabled', 'on'), true);
    }
}
