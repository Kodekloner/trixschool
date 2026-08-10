<?php

if (!defined('BASEPATH')) {
    exit('No direct script access allowed');
}

class Biometricdemo extends Admin_Controller
{
    /** @var string */
    private $token_session_key = 'biometric_demo_csrf';

    public function __construct()
    {
        parent::__construct();
        $this->load->library('biometricdemo_lib');
    }

    public function index()
    {
        if (!$this->rbac->hasPrivilege('student_attendance', 'can_view')) {
            access_denied();
        }

        $this->session->set_userdata('top_menu', 'Attendance');
        $this->session->set_userdata('sub_menu', 'biometricdemo/index');
        $this->output->set_header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');

        $data = array(
            'title'            => 'Biometric Attendance Demo',
            'scenarios'        => $this->biometricdemo_lib->scenarios(),
            'form'             => $this->biometricdemo_lib->defaults(),
            'result'           => null,
            'simulation_error' => '',
        );

        if ($this->input->server('REQUEST_METHOD') === 'POST') {
            $data['form'] = array(
                'scenario'      => trim((string) $this->input->post('scenario', true)),
                'employee'      => trim((string) $this->input->post('employee', true)),
                'event_date'    => trim((string) $this->input->post('event_date', true)),
                'entry_serial'  => trim((string) $this->input->post('entry_serial', true)),
                'exit_serial'   => trim((string) $this->input->post('exit_serial', true)),
                'delay_seconds' => trim((string) $this->input->post('delay_seconds', true)),
            );

            $this->form_validation->set_rules('scenario', 'Scenario', 'trim|required|max_length[32]');
            $this->form_validation->set_rules('employee', 'Synthetic student code', 'trim|required|max_length[64]');
            $this->form_validation->set_rules('event_date', 'Event date', 'trim|required|max_length[10]');
            $this->form_validation->set_rules('entry_serial', 'Entry terminal serial', 'trim|required|max_length[64]');
            $this->form_validation->set_rules('exit_serial', 'Exit terminal serial', 'trim|required|max_length[64]');
            $this->form_validation->set_rules('delay_seconds', 'Delay', 'trim|required|integer|greater_than_equal_to[0]|less_than_equal_to[300]');

            if (!$this->validToken((string) $this->input->post('biometric_demo_token'))) {
                $this->output->set_status_header(403);
                $data['simulation_error'] = 'The demonstration session token is missing or expired. Refresh this page and try again.';
            } elseif ($this->form_validation->run()) {
                try {
                    $data['result'] = $this->biometricdemo_lib->simulate($data['form']);
                } catch (InvalidArgumentException $error) {
                    $this->output->set_status_header(422);
                    $data['simulation_error'] = $error->getMessage();
                } catch (Throwable $error) {
                    log_message('error', 'Biometric demo failed: ' . $error->getMessage());
                    $this->output->set_status_header(500);
                    $data['simulation_error'] = 'The synthetic demonstration could not be generated. No attendance data was changed.';
                }
            }

            $this->rotateToken();
        }

        $data['biometric_demo_token'] = $this->token();

        $this->load->view('layout/header', $data);
        $this->load->view('admin/biometricdemo/index', $data);
        $this->load->view('layout/footer', $data);
    }

    /** @return string */
    private function token()
    {
        $token = $this->session->userdata($this->token_session_key);
        if (!is_string($token) || strlen($token) !== 64) {
            $token = $this->newToken();
            $this->session->set_userdata($this->token_session_key, $token);
        }

        return $token;
    }

    private function validToken($provided)
    {
        $expected = $this->session->userdata($this->token_session_key);
        return is_string($expected)
            && strlen($expected) === 64
            && is_string($provided)
            && hash_equals($expected, $provided);
    }

    private function rotateToken()
    {
        $this->session->set_userdata($this->token_session_key, $this->newToken());
    }

    /** @return string */
    private function newToken()
    {
        try {
            return bin2hex(random_bytes(32));
        } catch (Throwable $error) {
            return hash('sha256', uniqid('biometric-demo-', true) . mt_rand());
        }
    }
}
