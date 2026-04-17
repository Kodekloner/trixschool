<?php
if (!defined('BASEPATH'))
    exit('No direct script access allowed');
  
class Africastalking_lib {

    private $_CI;
    private $environment;
    private $last_error = '';
    var $from; //your AUTH_KEY here
    var $api_key; //your senderId here
    var $api_username;
    function __construct($params) {
 
    	$this->from=$params['from'];
    	$this->api_key=$params['api_key'];
        $this->api_username=$params['api_username'];
        $this->_CI = & get_instance();
        $this->session_name = $this->_CI->setting_model->getCurrentSessionName();
        $environment = isset($params['environment']) ? $params['environment'] : null;
        $this->environment = $this->resolveEnvironment($environment);
    } 

    private function resolveEnvironment($environment = null) {
        $environment = strtolower(trim((string) $environment));

        if ($environment === 'sandbox') {
            return 'sandbox';
        }

        if ($environment === 'live') {
            return 'live';
        }

        $this->_CI->load->model('smsconfig_model');
        $sms_detail = $this->_CI->smsconfig_model->getActiveSMS();

        if (!empty($sms_detail) && $sms_detail->type === 'africastalking' && strtolower(trim((string) $sms_detail->url)) === 'sandbox') {
            return 'sandbox';
        }

        return 'live';
    }

    private function getMessagingUrl() {
        if ($this->environment === 'sandbox') {
            return 'https://api.sandbox.africastalking.com/version1/messaging';
        }

        return 'https://api.africastalking.com/version1/messaging';
    }

    private function isSuccessfulStatus($status) {
        $status = strtolower(trim((string) $status));

        return in_array($status, array('success', 'sent', 'submitted', 'buffered', 'queued'), true);
    }

    private function extractErrorMessage($response_body, $decoded_response = array()) {
        $error_parts = array();

        if (!empty($decoded_response['SMSMessageData']['Message'])) {
            $error_parts[] = $decoded_response['SMSMessageData']['Message'];
        }

        if (!empty($decoded_response['SMSMessageData']['Recipients']) && is_array($decoded_response['SMSMessageData']['Recipients'])) {
            foreach ($decoded_response['SMSMessageData']['Recipients'] as $recipient) {
                $status = isset($recipient['status']) ? trim((string) $recipient['status']) : '';
                $number = isset($recipient['number']) ? trim((string) $recipient['number']) : '';

                if ($status !== '' && !$this->isSuccessfulStatus($status)) {
                    $error_parts[] = trim($status . ($number !== '' ? ' (' . $number . ')' : ''));
                }
            }
        }

        foreach (array('errorMessage', 'message', 'description') as $field) {
            if (!empty($decoded_response[$field])) {
                $error_parts[] = $decoded_response[$field];
            }
        }

        $error_message = trim(implode('; ', array_unique(array_filter($error_parts))));

        if ($error_message !== '') {
            return $error_message;
        }

        $response_body = trim((string) $response_body);

        if ($response_body !== '') {
            return $response_body;
        }

        return 'Africa\'s Talking request failed.';
    }

    public function getEnvironment() {
        return $this->environment;
    }

    public function getLastError() {
        return $this->last_error;
    }

    function sendSMS($to, $message) {
        $this->last_error = '';
        $ch = curl_init();

        curl_setopt($ch, CURLOPT_URL, $this->getMessagingUrl());
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query(array(
            'username' => $this->api_username,
            'to'       => $to,
            'message'  => $message,
            'from'     => $this->from,
        )));

        $headers = array();
        $headers[] = 'Accept: application/json';
        $headers[] = 'Content-Type: application/x-www-form-urlencoded';
        $headers[] = 'Apikey:' . $this->api_key;
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);

        $result = curl_exec($ch);

        if ($result === false) {
            $this->last_error = 'cURL error: ' . curl_error($ch);
            log_message('error', 'AFRICASTALKING SMS send failed: ' . $this->last_error);
            curl_close($ch);

            return false;
        }

        $http_status      = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $decoded_response = json_decode($result, true);
        curl_close($ch);

        if ($http_status < 200 || $http_status >= 300) {
            $this->last_error = $this->extractErrorMessage($result, is_array($decoded_response) ? $decoded_response : array());
            log_message('error', 'AFRICASTALKING SMS send failed: ' . $this->last_error);

            return false;
        }

        if (is_array($decoded_response) && !empty($decoded_response['SMSMessageData']['Recipients'])) {
            foreach ($decoded_response['SMSMessageData']['Recipients'] as $recipient) {
                $status = strtolower(trim((string) (isset($recipient['status']) ? $recipient['status'] : '')));

                if ($status !== '' && !$this->isSuccessfulStatus($status)) {
                    $this->last_error = $this->extractErrorMessage($result, $decoded_response);
                    log_message('error', 'AFRICASTALKING SMS send failed: ' . $this->last_error);

                    return false;
                }
            }
        }

        return true;
    }



}
?>
