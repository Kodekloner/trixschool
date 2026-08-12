<?php

if (!defined('BASEPATH')) {
    exit('No direct script access allowed');
}

class Biometric extends CI_Controller
{
    public function index()
    {
        return $this->output
            ->set_status_header(410)
            ->set_content_type('application/json', 'utf-8')
            ->set_header('Cache-Control: no-store')
            ->set_output(json_encode(array(
                'status' => 410,
                'code' => 'LEGACY_BIOMETRIC_ENDPOINT_RETIRED',
                'message' => 'This endpoint has been retired. Configure the authenticated V2 biometric gateway.',
                'replacement' => '/api/biometric/v2/events',
            )));
    }
}
