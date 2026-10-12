<?php

// Local test server only: no SchoolLift database or real messaging provider.
if (PHP_SAPI !== 'cli-server') { http_response_code(404); exit; }
define('BASEPATH', __DIR__);
function log_message($level, $message) {}
class FixtureInput {
    public function method($upper = false) { return $_SERVER['REQUEST_METHOD']; }
    public function server($key) { return isset($_SERVER[$key]) ? $_SERVER[$key] : null; }
    public function get_request_header($name, $xss = false) {
        $key = $name === 'Content-Type' ? 'CONTENT_TYPE' : 'HTTP_' . strtoupper(str_replace('-', '_', $name));
        return $this->server($key);
    }
}
class FixtureConfig {
    public function load($name) {}
    public function item($key) {
        if ($key === 'biometric_require_https' || $key === 'compress_output') { return false; }
        if ($key === 'biometric_automatic_notifications') { return $_SERVER['REQUEST_URI'] !== '/disabled'; }
        return null;
    }
}
class FixtureLoader { public function library($name) {} }
class FixtureOutput {
    public $body;
    public function set_header($value) { header($value); return $this; }
    public function set_status_header($status) { http_response_code($status); return $this; }
    public function set_content_type($type, $charset = null) { header('Content-Type: ' . $type); return $this; }
    public function set_output($body) { $this->body = $body; return $this; }
}
class FixtureBiometricService {
    public function authenticateToken($token) { return $token === 'fixture' ? array('id' => 2, 'is_active' => 1) : null; }
    public function pollGateway($integration, $payload) {
        if (empty($payload['gateway_id']) || empty($payload['gateway_version']) || !isset($payload['status'])) {
            return array('success' => false, 'http_status' => 422, 'command' => null);
        }
        return array('success' => true, 'http_status' => 200, 'command' => null);
    }
    public function processNotificationQueue($limit, $integrationId, $budget) {
        // Prove response delivery does not wait for a slow / noisy provider.
        usleep(1200000);
        echo 'provider diagnostics must not appear in gateway JSON';
        file_put_contents(getenv('BIOMETRIC_FIXTURE_LOG'), json_encode(array($limit, $integrationId, $budget)) . PHP_EOL, FILE_APPEND);
        if ($_SERVER['REQUEST_URI'] === '/failure') { throw new RuntimeException('provider failed'); }
        return array('selected' => 1, 'delivered' => 1);
    }
}
class CI_Controller {
    public $config;
    public $load;
    public $input;
    public $output;
    public $biometric_attendance_service;
    public function __construct() {
        $this->config = new FixtureConfig(); $this->load = new FixtureLoader();
        $this->input = new FixtureInput(); $this->output = new FixtureOutput();
        $this->biometric_attendance_service = new FixtureBiometricService();
    }
}
require __DIR__ . '/../../application/controllers/api/Biometric_v2.php';
$controller = new Biometric_v2();
$controller->gateway_poll();
$controller->_output($controller->output->body);
