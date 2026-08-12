<?php

define('BASEPATH', __DIR__);

class CI_Controller
{
    public $output;

    public function __construct()
    {
        $this->output = new LegacyBiometricOutput();
    }
}

class LegacyBiometricOutput
{
    public $status;
    public $contentType;
    public $headers = array();
    public $body;

    public function set_status_header($status) { $this->status = $status; return $this; }
    public function set_content_type($type, $charset = null) { $this->contentType = $type; return $this; }
    public function set_header($header) { $this->headers[] = $header; return $this; }
    public function set_output($body) { $this->body = $body; return $this; }
}

require_once __DIR__ . '/../application/controllers/Biometric.php';

$controller = new Biometric();
$controller->index();
$payload = json_decode($controller->output->body, true);

if ($controller->output->status !== 410) {
    fwrite(STDERR, 'Legacy biometric endpoint must return HTTP 410.' . PHP_EOL);
    exit(1);
}
if (!is_array($payload) || $payload['code'] !== 'LEGACY_BIOMETRIC_ENDPOINT_RETIRED') {
    fwrite(STDERR, 'Legacy biometric endpoint must return the structured retirement code.' . PHP_EOL);
    exit(1);
}
if ($payload['replacement'] !== '/api/biometric/v2/events') {
    fwrite(STDERR, 'Legacy biometric endpoint must identify the V2 replacement.' . PHP_EOL);
    exit(1);
}

echo "legacy biometric endpoint retirement test passed\n";
