<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class Whatsappsettings extends Admin_Controller
{
    public function __construct()
    {
        parent::__construct();
        $this->load->model('whatsappconfig_model');
        $this->load->library('whatsappgateway');
    }

    public function index()
    {
        if (!$this->rbac->hasPrivilege('whatsapp_setting', 'can_view')) { access_denied(); return; }
        $this->session->set_userdata('top_menu', 'Communicate');
        $this->session->set_userdata('sub_menu', 'admin/whatsappsettings');
        if (!$this->session->userdata('whatsapp_settings_nonce')) {
            $this->session->set_userdata('whatsapp_settings_nonce', bin2hex(random_bytes(24)));
        }
        $config = $this->whatsappconfig_model->get();
        $errors = array();
        if ($this->input->server('REQUEST_METHOD') === 'POST') {
            if (!$this->rbac->hasPrivilege('whatsapp_setting', 'can_edit')) { access_denied(); return; }
            $nonce = $this->input->post('whatsapp_nonce');
            if (!is_string($nonce) || !hash_equals($this->session->userdata('whatsapp_settings_nonce'), $nonce)) {
                show_error('This settings form expired. Reload WhatsApp Settings and try again.', 403); return;
            }
            $action = $this->input->post('action');
            if ($action === 'save') {
                $post = $this->input->post();
                foreach ($post as $field => $value) {
                    if ($field !== 'auto_credential_for' && !is_string($value)) { $errors[] = 'Enter valid text in the configuration fields.'; break; }
                    if ($field === 'auto_credential_for' && (!is_array($value) || count(array_filter($value, 'is_string')) !== count($value))) { $errors[] = 'Select credential recipients using the checkboxes.'; break; }
                }
                if (!$errors) {
                    $config = $this->whatsappconfig_model->prepare($post);
                    $errors = $this->whatsappconfig_model->validate($config);
                    if (!$errors) {
                        $ok = $this->whatsappconfig_model->save($config);
                        $this->notice($ok, $ok ? 'Twilio settings saved. Use Check connection, then send a test message before enabling production.' : $this->whatsappconfig_model->getLastError());
                        redirect('admin/whatsappsettings'); return;
                    }
                }
            } elseif (in_array($action, array('check_connection', 'send_test', 'check_status'), true)) {
                $this->diagnostic($action, $config);
                redirect('admin/whatsappsettings'); return;
            } else { $errors[] = 'Choose a valid settings action.'; }
        }
        $data = array('title' => 'WhatsApp Settings', 'config' => $this->whatsappconfig_model->publicConfig($config, $this->whatsappconfig_model->get()),
            'settings_errors' => $errors, 'whatsapp_nonce' => $this->session->userdata('whatsapp_settings_nonce'),
            'last_test' => $this->session->userdata('whatsapp_twilio_test'),
            'test_environment' => $this->session->userdata('whatsapp_twilio_test_environment') === 'production' ? 'production' : 'sandbox',
            'can_edit' => $this->rbac->hasPrivilege('whatsapp_setting', 'can_edit'));
        $this->load->view('layout/header', $data);
        $this->load->view('admin/whatsappsettings/index', $data);
        $this->load->view('layout/footer', $data);
    }

    protected function makeGateway($configuration) { return new Whatsappgateway($configuration); }

    private function diagnostic($action, $config)
    {
        $environment = $this->input->post('test_environment');
        if (!in_array($environment, array('sandbox', 'production'), true)) { $this->notice(false, 'Choose the test environment.'); return; }
        $this->session->set_userdata('whatsapp_twilio_test_environment', $environment);
        $gateway = $this->makeGateway($this->whatsappconfig_model->getGatewayConfig($environment, true));
        if ($action === 'check_connection') {
            $ok = $gateway->checkConnection();
            $this->notice($ok, $ok ? ucfirst($environment) . ': Twilio credentials authenticated and the account is active. No message was sent. Sender registration and recipient eligibility must still be verified with a test send.' : $gateway->getLastError());
            return;
        }
        if ($action === 'check_status') {
            $last = $this->session->userdata('whatsapp_twilio_test');
            if (!is_array($last) || $last['environment'] !== $environment || $last['account_sid'] !== $config[$environment]['account_sid']) {
                $this->notice(false, 'No matching test exists for this environment/account. Send a test first.'); return;
            }
            $message = $gateway->fetchMessageStatus($last['sid']);
            if ($message === false) { $this->notice(false, $gateway->getLastError()); return; }
            $last['status'] = $message['status'];
            $this->session->set_userdata('whatsapp_twilio_test', $last);
            if (in_array($message['status'], array('failed', 'undelivered', 'canceled'), true)) {
                $this->notice(false, 'Test status: ' . $message['status'] . '. ' . $gateway->errorExplanation(200, $message['error_code'])); return;
            }
            $delivered = in_array($message['status'], array('delivered', 'read'), true);
            $this->notice(true, 'Test status: ' . $message['status'] . '. ' . ($delivered ? 'Twilio reports delivery to the recipient.' : 'This status does not yet confirm delivery. Check again later.'));
            return;
        }
        $last_attempt = (int) $this->session->userdata('whatsapp_twilio_test_time');
        if (time() - $last_attempt < 4) { $this->notice(false, 'Wait at least four seconds before sending another test.'); return; }
        $type = $this->input->post('test_type');
        if (!in_array($type, array('text', 'template'), true)) { $this->notice(false, 'Choose text or template for the test.'); return; }
        $phone = $config[$environment]['test_recipient'];
        if (!preg_match('/^\+[1-9][0-9]{7,14}$/', $phone)) { $this->notice(false, 'Save the test recipient using +countrycode format first.'); return; }
        $body = $this->input->post('test_body');
        $sid = $this->input->post('test_content_sid');
        $raw = $this->input->post('test_variables');
        if (!is_string($body) || !is_string($sid) || !is_string($raw)) { $this->notice(false, 'Enter valid text for the test message and template.'); return; }
        $values = trim($raw) === '' ? array() : preg_split('/\r\n|\r|\n/', trim($raw));
        $this->session->set_userdata('whatsapp_twilio_test_time', time());
        $ok = $type === 'template' ? $gateway->sendTemplateMessage($phone, trim($sid), '', $values) : $gateway->sendMessage($phone, $body);
        if ($ok) {
            $message = $gateway->getLastMessage();
            $this->session->set_userdata('whatsapp_twilio_test', array('sid' => $message['sid'], 'status' => $message['status'],
                'environment' => $environment, 'account_sid' => $config[$environment]['account_sid']));
            $this->notice(true, 'Twilio accepted one ' . $environment . ' test message. Status: ' . $message['status'] . '. Use Check last test status and confirm it on the receiving phone.');
        } else { $this->notice(false, $gateway->getLastError()); }
    }

    private function notice($success, $message)
    {
        $this->session->set_flashdata('msg', '<div class="alert alert-' . ($success ? 'success' : 'danger') . '">' . html_escape($message) . '</div>');
    }
}
