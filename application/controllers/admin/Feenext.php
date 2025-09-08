<?php

if (!defined('BASEPATH'))
    exit('No direct script access allowed');

class Feenext extends Admin_Controller {

    function __construct() {
        parent::__construct();
    }

    function index() {
        if (!$this->rbac->hasPrivilege('fees_type', 'can_view')) {
            access_denied();
        }
        $this->session->set_userdata('top_menu', 'Fees Collection');
        $this->session->set_userdata('sub_menu', 'feetype/index');
        $data['title'] = 'Add Next Fee';
        $data['title_list'] = 'Next Term Fees';

        $this->form_validation->set_rules(
                'name', $this->lang->line('name'), array(
            'required',
            array('check_exists', array($this->feenext_model, 'check_exists'))
                )
        );
        $this->form_validation->set_rules('name', $this->lang->line('name'), 'required');
        if ($this->form_validation->run() == FALSE) {
            
        } else {
            $data = array(
                'name' => $this->input->post('name'),
                'amount' => $this->input->post('amount'),
                'session' => $this->setting_model->getCurrentSession(),
                'term' => $this->input->post('term'),
                'class_id' => $this->input->post('class'),
                
            );
            $this->feetype_model->next_add($data);
            $this->session->set_flashdata('msg', '<div class="alert alert-success text-left">' . $this->lang->line('success_message') . '</div>');
            redirect('admin/feenext/index');
        }
        
        $session = $this->setting_model->getCurrentSession();
        $fee_result = $this->feetype_model->next_get($session);
        $data['fee_next_list'] = $fee_result;
        $class = $this->class_model->get();
        $data['classlist'] = $class;

        $this->load->view('layout/header', $data);
        $this->load->view('admin/feenextterm/feeNextTerm', $data);
        $this->load->view('layout/footer', $data);
    }

    function delete($id) {
        if (!$this->rbac->hasPrivilege('fees_type', 'can_delete')) {
            access_denied();
        }
        $data['title'] = 'Fees Master List';
        $this->feetype_model->next_remove($id);
        redirect('admin/feenext/index');
    }
    
    function affective_domain_delete($id) {
        
        $data['title'] = 'Affective Domain List';
        $this->feetype_model->affective_domain_remove($id);
        redirect('admin/addAffectiveDomain.php');
    }
    
    function psycomotor_delete($id) {
        $data['title'] = 'Psychomotor List';
        $this->feetype_model->psycomotor_remove($id);
        redirect('admin/addPsycomotor.php');
    }

    function edit($id) {
        $data['id']          = $id;
        $fee            = $this->feetype_model->next_get($session, $id);
        
        $this->form_validation->set_rules(
            'name', 'Name', array(
                'required',
                array('check_exists', array($this->feetype_model, 'check_exists')),
            )
        );

        if ($this->form_validation->run() == false) {
            $session = $this->setting_model->getCurrentSession();
            $data['fee'] = $fee;
            $fee_result = $this->feetype_model->next_get($session);
            $data['fee_next_list'] = $fee_result;
            $class = $this->class_model->get();
            $data['classlist'] = $class;
            $this->load->view('layout/header', $data);
            $this->load->view('admin/feenextterm/feeNextTermEdit', $data);
            $this->load->view('layout/footer', $data);
        } else {
            $data = array(
                'id'          => $id,
                'name'        => $this->input->post('name'),
                'amount' => $this->input->post('amount'),
                'session' => $this->setting_model->getCurrentSession(),
                'term' => $this->input->post('term'),
				'class_id' => $this->input->post('class'),
            );
            $this->feetype_model->next_add($data);
             $this->session->set_flashdata('msg', '<div class="alert alert-success">'.$this->lang->line('update_message').'</div>');
            redirect('admin/feenext/index');
        }
    }

}

?>