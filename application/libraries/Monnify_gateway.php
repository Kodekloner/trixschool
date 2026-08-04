<?php

defined('BASEPATH') OR exit('No direct script access allowed');

class Monnify_gateway
{
    const SANDBOX_BASE_URL = 'https://sandbox.monnify.com';
    const LIVE_BASE_URL = 'https://api.monnify.com';

    protected $api_key = '';
    protected $secret_key = '';
    protected $contract_code = '';
    protected $gateway_mode = 0;

    public function __construct($config = array())
    {
        $this->api_key = isset($config['api_key']) ? trim((string) $config['api_key']) : '';
        $this->secret_key = isset($config['secret_key']) ? trim((string) $config['secret_key']) : '';
        $this->contract_code = isset($config['contract_code']) ? trim((string) $config['contract_code']) : '';
        $this->gateway_mode = !empty($config['gateway_mode']) ? 1 : 0;
    }

    public function getBaseUrl()
    {
        return $this->gateway_mode === 1 ? self::LIVE_BASE_URL : self::SANDBOX_BASE_URL;
    }

    public function isSandbox()
    {
        return $this->gateway_mode === 0;
    }

    public function initializeTransaction($payload)
    {
        $token_result = $this->getAccessToken();
        if (!$token_result['success']) {
            return $token_result;
        }

        return $this->request(
            'POST',
            '/api/v1/merchant/transactions/init-transaction',
            $payload,
            array('Authorization: Bearer ' . $token_result['access_token'])
        );
    }

    public function verifyTransaction($payment_reference)
    {
        $token_result = $this->getAccessToken();
        if (!$token_result['success']) {
            return $token_result;
        }

        return $this->request(
            'GET',
            '/api/v2/merchant/transactions/query?paymentReference=' . rawurlencode($payment_reference),
            null,
            array('Authorization: Bearer ' . $token_result['access_token'])
        );
    }

    public function isValidWebhookSignature($raw_payload, $signature)
    {
        $signature = strtolower(trim((string) $signature));

        // Monnify does not include the signature header on sandbox notifications.
        if ($signature === '') {
            return $this->isSandbox();
        }

        $expected = hash_hmac('sha512', (string) $raw_payload, $this->secret_key);
        return hash_equals($expected, $signature);
    }

    protected function getAccessToken()
    {
        if ($this->api_key === '' || $this->secret_key === '' || $this->contract_code === '') {
            return array('success' => false, 'message' => 'Monnify credentials are incomplete.');
        }

        $result = $this->request(
            'POST',
            '/api/v1/auth/login',
            null,
            array('Authorization: Basic ' . base64_encode($this->api_key . ':' . $this->secret_key))
        );

        if (!$result['success'] || empty($result['response']['responseBody']['accessToken'])) {
            return array(
                'success' => false,
                'message' => isset($result['message']) ? $result['message'] : 'Monnify authentication failed.',
                'response' => isset($result['response']) ? $result['response'] : null,
            );
        }

        return array(
            'success' => true,
            'access_token' => $result['response']['responseBody']['accessToken'],
        );
    }

    protected function request($method, $path, $payload = null, $extra_headers = array())
    {
        if (!function_exists('curl_init')) {
            return array('success' => false, 'message' => 'The PHP cURL extension is required for Monnify payments.');
        }

        $ch = curl_init($this->getBaseUrl() . $path);
        $headers = array_merge(array('Accept: application/json', 'Content-Type: application/json'), $extra_headers);

        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 10);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, strtoupper($method));
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 2);

        if ($payload !== null) {
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
        }

        $raw_response = curl_exec($ch);
        $curl_error = curl_error($ch);
        $http_code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($raw_response === false) {
            log_message('error', 'Monnify request failed: ' . $curl_error);
            return array('success' => false, 'message' => 'Unable to connect to Monnify right now.');
        }

        $response = json_decode($raw_response, true);
        if (!is_array($response)) {
            log_message('error', 'Monnify returned invalid JSON. HTTP ' . $http_code);
            return array('success' => false, 'message' => 'Invalid response received from Monnify.');
        }

        $successful = $http_code >= 200 && $http_code < 300 && !empty($response['requestSuccessful']);
        if (!$successful) {
            $message = !empty($response['responseMessage']) ? $response['responseMessage'] : 'Monnify request failed.';
            log_message('error', 'Monnify request failed. HTTP ' . $http_code . ' Message: ' . $message);
            return array('success' => false, 'message' => $message, 'response' => $response);
        }

        return array('success' => true, 'response' => $response);
    }
}
