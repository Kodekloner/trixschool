<?php

if (!defined('BASEPATH')) {
    exit('No direct script access allowed');
}

class Whatsappsettings extends Admin_Controller
{
    public function __construct()
    {
        parent::__construct();
        $this->load->model('whatsappconfig_model');
    }

    public function index()
    {
        if (!$this->rbac->hasPrivilege('whatsapp_setting', 'can_view')) {
            access_denied();
        }

        $this->session->set_userdata('top_menu', 'Communicate');
        $this->session->set_userdata('sub_menu', 'admin/whatsappsettings');

        if ($this->input->server('REQUEST_METHOD') === 'POST') {
            if (!$this->rbac->hasPrivilege('whatsapp_setting', 'can_edit')) {
                access_denied();
            }

            $this->form_validation->set_rules('provider', 'Provider', 'trim|required|in_list[meta_cloud,360dialog,custom_webhook]');
            $this->form_validation->set_rules('default_country_code', 'Default Country Code', 'trim|required|regex_match[/^[1-9][0-9]{0,3}$/]');
            $saved_config = $this->whatsappconfig_model->get();
            $provider = $this->input->post('provider');
            $has_saved_credential = $saved_config['provider'] === $provider && trim((string) $saved_config['meta_access_token']) !== '';
            $this->form_validation->set_rules('meta_access_token', 'Meta Access Token', 'trim');
            $this->form_validation->set_rules('dialog360_api_key', '360dialog Channel API Key', 'trim');
            if ($this->input->post('enabled')) {
                if ($this->input->post('provider') === 'meta_cloud') {
                    $this->form_validation->set_rules('meta_phone_number_id', 'Meta Phone Number ID', 'trim|required|numeric');
                    $this->form_validation->set_rules('meta_access_token', 'Meta Access Token', $has_saved_credential ? 'trim' : 'trim|required');
                    $this->form_validation->set_rules('meta_graph_api_version', 'Graph API Version', 'trim|required|regex_match[/^v[0-9]+\.0$/]');
                    if ($this->input->post('auto_send_login_credentials')) {
                        $this->form_validation->set_rules('meta_template_name', 'Login Credential Template Name', 'trim|required|regex_match[/^[a-z0-9_]+$/]');
                        $this->form_validation->set_rules('meta_template_language', 'Template Language', 'trim|required|regex_match[/^[a-z]{2,3}(?:_[A-Z]{2})?$/]');
                    }
                } elseif ($provider === '360dialog') {
                    $this->form_validation->set_rules('dialog360_api_key', '360dialog Channel API Key', $has_saved_credential ? 'trim' : 'trim|required');
                    if ($this->input->post('auto_send_login_credentials')) {
                        $this->form_validation->set_rules('meta_template_name', 'Login Credential Template Name', 'trim|required|regex_match[/^[a-z0-9_]+$/]');
                        $this->form_validation->set_rules('meta_template_language', 'Template Language', 'trim|required|regex_match[/^[a-z]{2,3}(?:_[A-Z]{2})?$/]');
                    }
                } elseif ($provider === 'custom_webhook') {
                    $this->form_validation->set_rules('webhook_url', 'Webhook URL', 'trim|required|valid_url');
                }
            }

            if ($this->form_validation->run()) {
                $saved = $this->whatsappconfig_model->save($this->input->post());

                if ($saved) {
                    $this->session->set_flashdata('msg', '<div class="alert alert-success text-left">WhatsApp settings updated successfully.</div>');
                } else {
                    $this->session->set_flashdata('msg', '<div class="alert alert-danger text-left">WhatsApp settings could not be saved. Check that the WhatsApp config SQL migration has been applied and the database is writable.</div>');
                }

                redirect('admin/whatsappsettings');
            }
        }

        $data['title'] = 'WhatsApp Settings';
        $data['config'] = $this->whatsappconfig_model->get();
        $data['credential_saved'] = trim((string) $data['config']['meta_access_token']) !== '';
        // Stored provider credentials are never sent back in page HTML.
        $data['config']['meta_access_token'] = '';
        $data['table_exists'] = $this->db->table_exists('whatsapp_config');

        $this->load->view('layout/header', $data);
        $this->load->view('admin/whatsappsettings/index', $data);
        $this->load->view('layout/footer', $data);
    }
}
