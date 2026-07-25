<?php

if (!defined('BASEPATH'))
    exit('No direct script access allowed');

class Roles extends Admin_Controller
{

    private $perm_category = array();

    function __construct()
    {
        parent::__construct();
        $this->load->config('mailsms');
        $this->perm_category = $this->config->item('perm_category');
    }

    function index()
    {
        if (!$this->rbac->hasPrivilege('superadmin', 'can_view')) {
            access_denied();
        }

        $data['title'] = 'Add Role';
        $this->session->set_userdata('top_menu', 'System Settings');
        $this->session->set_userdata('sub_menu', 'admin/roles');

        $this->form_validation->set_rules(
            'name',
            $this->lang->line('name'),
            array(
                'required',
                array('check_exists', array($this->role_model, 'valid_check_exists'))
            )
        );
        if ($this->form_validation->run() == FALSE) {
            $listroute = $this->role_model->get();
            $data['listroute'] = $listroute;
            $this->load->view('layout/header');
            $this->load->view('admin/roles/create', $data);
            $this->load->view('layout/footer');
        } else {
            $data = array(
                'name' => $this->input->post('name')
            );
            $this->role_model->add($data);
            $this->session->set_flashdata('msg', '<div class="alert alert-success text-left">' . $this->lang->line('success_message') . '</div>');
            redirect('admin/roles');
        }
    }

    function permission($id)
    {
        if (!$this->rbac->hasPrivilege('superadmin', 'can_view')) {
            access_denied();
        }
        $this->role_model->ensurePublishResultPermissionSetup();
        $this->role_model->ensureWhatsappMessagingPermissionSetup();
        $data['title'] = 'Add Role';
        $data['id'] = $id;
        $role = $this->role_model->get($id);

        $data['role'] = $role;
        $role_permission = $this->role_model->find($role['id']);


        $data['role_permission'] = $role_permission;

        if ($this->input->server('REQUEST_METHOD') == "POST") {
            $permissions = $this->input->post('permissions');
            if (!is_array($permissions)) {
                $permissions = array();
            }

            if ($this->role_model->replacePermissions((int) $id, $permissions)) {
                $this->session->set_flashdata('msg', '<div class="alert alert-success text-left">' . $this->lang->line('success_message') . '</div>');
            } else {
                $this->session->set_flashdata('msg', '<div class="alert alert-danger text-left">' . $this->lang->line('something_went_wrong') . '</div>');
            }
            redirect('admin/roles/permission/' . $id);
        }

        $this->load->view('layout/header');
        $this->load->view('admin/roles/allotmodule', $data);
        $this->load->view('layout/footer');
    }

    function edit($id)
    {
        if (!$this->rbac->hasPrivilege('superadmin', 'can_view')) {
            access_denied();
        }
        $data['title'] = 'Edit Role';
        $data['id'] = $id;
        $editrole = $this->role_model->get($id);
        $data['editrole'] = $editrole;
        $data['name'] = $editrole["name"];

        $this->form_validation->set_rules(
            'name',
            $this->lang->line('name'),
            array(
                'required',
                array('check_exists', array($this->role_model, 'valid_check_exists'))
            )
        );
        if ($this->form_validation->run() == FALSE) {
            $listroute = $this->role_model->get();
            $data['listroute'] = $listroute;
            $this->load->view('layout/header');
            $this->load->view('admin/roles/edit', $data);
            $this->load->view('layout/footer');
        } else {
            $data = array(
                'id' => $id,
                'name' => $this->input->post('name')
            );
            $this->role_model->add($data);
            $this->session->set_flashdata('msg', '<div class="alert alert-success text-left">' . $this->lang->line('success_message') . '</div>');
            redirect('admin/roles/index');
        }
    }

    function delete($id)
    {
        $data['title'] = 'Fees Master List';
        $this->role_model->remove($id);
        redirect('admin/roles/index');
    }
}
