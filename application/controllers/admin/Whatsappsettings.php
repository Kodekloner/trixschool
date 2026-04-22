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

            $this->form_validation->set_rules('provider', 'Provider', 'trim|required');
            $this->form_validation->set_rules('default_country_code', 'Default Country Code', 'trim|required');

            if ($this->form_validation->run()) {
                $saved = $this->whatsappconfig_model->save($this->input->post());

                if ($saved) {
                    $this->session->set_flashdata('msg', '<div class="alert alert-success text-left">WhatsApp settings updated successfully.</div>');
                } else {
                    $this->session->set_flashdata('msg', '<div class="alert alert-danger text-left">WhatsApp config table is missing. Run the WhatsApp config SQL migration first.</div>');
                }

                redirect('admin/whatsappsettings');
            }
        }

        $data['title'] = 'WhatsApp Settings';
        $data['config'] = $this->whatsappconfig_model->get();
        $data['table_exists'] = $this->db->table_exists('whatsapp_config');

        $this->load->view('layout/header', $data);
        $this->load->view('admin/whatsappsettings/index', $data);
        $this->load->view('layout/footer', $data);
    }
}
