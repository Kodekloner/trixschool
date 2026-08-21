<?php

defined('BASEPATH') OR exit('No direct script access allowed');

class Biometric_v2 extends CI_Controller
{
    public function __construct()
    {
        parent::__construct();
        $this->config->load('biometric_attendance');
        $this->load->library('biometric_attendance_service');
    }

    public function events()
    {
        if ($this->input->method(true) !== 'POST') {
            return $this->respond(array('success' => false, 'message' => 'Method not allowed.', 'results' => array()), 405, array('Allow' => 'POST'));
        }
        if (!$this->secureTransport()) {
            return $this->respond(array('success' => false, 'message' => 'HTTPS is required.', 'results' => array()), 426);
        }
        $maxBytes = (int) $this->config->item('biometric_max_payload_bytes');
        $contentLength = (int) $this->input->server('CONTENT_LENGTH');
        if ($contentLength > $maxBytes) {
            return $this->respond(array('success' => false, 'message' => 'Payload is too large.', 'results' => array()), 413);
        }
        $contentType = strtolower(trim((string) $this->input->get_request_header('Content-Type', true)));
        if ($contentType === '' || strpos($contentType, 'application/json') !== 0) {
            return $this->respond(array('success' => false, 'message' => 'Content-Type must be application/json.', 'results' => array()), 415);
        }

        $integration = $this->authenticate();
        if (!$integration) {
            return $this->respond(array('success' => false, 'message' => 'Invalid bearer token.', 'results' => array()), 401, array('WWW-Authenticate' => 'Bearer'));
        }

        $raw = file_get_contents('php://input');
        if (!is_string($raw) || strlen($raw) > $maxBytes) {
            return $this->respond(array('success' => false, 'message' => 'Payload is too large.', 'results' => array()), 413);
        }
        $payload = json_decode($raw, true);
        if (!is_array($payload) || json_last_error() !== JSON_ERROR_NONE) {
            return $this->respond(array('success' => false, 'message' => 'Request body must be valid JSON.', 'results' => array()), 400);
        }

        $result = $this->biometric_attendance_service->ingestBatch($payload, array('integration' => $integration));
        $status = isset($result['http_status']) ? (int) $result['http_status'] : (!empty($result['success']) ? 202 : 422);
        return $this->respond($result, $status, $status === 429 ? array('Retry-After' => '60') : array());
    }

    public function health()
    {
        if ($this->input->method(true) !== 'GET') {
            return $this->respond(array('status' => 'error', 'message' => 'Method not allowed.'), 405, array('Allow' => 'GET'));
        }
        if (!$this->secureTransport()) {
            return $this->respond(array('status' => 'error', 'message' => 'HTTPS is required.'), 426);
        }
        $integration = $this->authenticate();
        if (!$integration) {
            return $this->respond(array('status' => 'error', 'message' => 'Invalid bearer token.'), 401, array('WWW-Authenticate' => 'Bearer'));
        }
        if (!$this->biometric_attendance_service->isReady()) {
            return $this->respond(array('status' => 'error', 'message' => 'Biometric migration 130 has not been applied.'), 503);
        }
        $settings = $this->biometric_attendance_service->getSettings();
        return $this->respond(array(
            'status' => 'ok',
            'api_version' => '2.0',
            'operating_mode' => $settings['mode'],
            'accepts_gateway_events' => in_array($settings['mode'], array('shadow', 'live'), true),
            'server_time_utc' => gmdate(DateTime::ATOM),
            'integration' => array(
                'id' => (int) $integration['id'],
                'provider' => $integration['provider'],
                'last_cursor' => $integration['last_cursor'],
            ),
        ), 200);
    }

    protected function authenticate()
    {
        $header = $this->input->get_request_header('Authorization', true);
        if (!$header) {
            $header = $this->input->server('HTTP_AUTHORIZATION');
        }
        if (!$header) {
            $header = $this->input->server('REDIRECT_HTTP_AUTHORIZATION');
        }
        if (!is_string($header) || !preg_match('/^Bearer\s+(.+)$/i', trim($header), $matches)) {
            return null;
        }
        return $this->biometric_attendance_service->authenticateToken(trim($matches[1]));
    }

    protected function secureTransport()
    {
        if (!$this->config->item('biometric_require_https')) {
            return true;
        }
        $https = strtolower(trim((string) $this->input->server('HTTPS')));
        if ($https !== '' && $https !== 'off') {
            return true;
        }

        // Forwarded headers are accepted only from explicitly trusted proxy
        // addresses. This prevents a client from bypassing the HTTPS guard by
        // sending X-Forwarded-Proto itself.
        $trusted = $this->config->item('proxy_ips');
        if (is_string($trusted)) {
            $trusted = preg_split('/\s*,\s*/', $trusted, -1, PREG_SPLIT_NO_EMPTY);
        }
        $remote = trim((string) $this->input->server('REMOTE_ADDR'));
        if (is_array($trusted) && $remote !== '' && $this->trustedProxy($remote, $trusted)) {
            $forwarded = strtolower(trim((string) $this->input->server('HTTP_X_FORWARDED_PROTO')));
            return $forwarded === 'https';
        }
        return false;
    }

    protected function trustedProxy($remote, array $trusted)
    {
        foreach ($trusted as $entry) {
            $entry = trim((string) $entry);
            if ($entry !== '' && hash_equals($entry, $remote)) {
                return true;
            }
        }
        return false;
    }

    protected function respond(array $payload, $status, array $headers = array())
    {
        foreach ($headers as $name => $value) {
            $this->output->set_header($name . ': ' . $value);
        }
        $this->output->set_header('Cache-Control: no-store');
        $this->output->set_header('X-Content-Type-Options: nosniff');
        return $this->output->set_status_header((int) $status)
            ->set_content_type('application/json', 'utf-8')
            ->set_output(json_encode($payload));
    }
}
