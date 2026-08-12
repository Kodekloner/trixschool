<?php
if (!defined('BASEPATH')) {
    exit('No direct script access allowed');
}

class Generatestaffidcard extends Admin_Controller
{

    private $generationCsrfKey = 'staff_idcard_generation_csrf';

    public function __construct()
    {
        parent::__construct();
        $this->load->library('biometric_attendance_service');
        $this->load->model('Idcardstudio_model');
        $this->sch_setting_detail = $this->setting_model->getSetting();
        if (!$this->session->userdata($this->generationCsrfKey)) {
            $this->session->set_userdata($this->generationCsrfKey, bin2hex(random_bytes(32)));
        }
    }

    public function index()
    {
        if (!$this->rbac->hasPrivilege('generate_staff_id_card', 'can_view')) {
            access_denied();
        }
        $this->session->set_userdata('top_menu', 'Certificate');
        $this->session->set_userdata('sub_menu', 'admin/generatestaffidcard');
        $idcardlist            = $this->Generatestaffidcard_model->getstaffidcard();
        $data['idcardlist']    = $idcardlist;
        $staffRole             = $this->staff_model->getStaffRole();
        $data['staffRolelist'] = $staffRole;
        $data['idcard_generation_csrf'] = $this->session->userdata($this->generationCsrfKey);
        $this->load->view('layout/header');
        $this->load->view('admin/generatestaffidcard/generatestaffidcardview', $data);
        $this->load->view('layout/footer');
    }

    public function search()
    {
        if (!$this->rbac->hasPrivilege('generate_staff_id_card', 'can_view')) {
            access_denied();
        }
        $this->session->set_userdata('top_menu', 'Certificate');
        $this->session->set_userdata('sub_menu', 'admin/generatestaffidcard');
        $staffRole               = $this->staff_model->getStaffRole();
        $data['staffRolelist']   = $staffRole;
        $data['adm_auto_insert'] = $this->sch_setting_detail->adm_auto_insert;
        $idcardlist              = $this->Generatestaffidcard_model->getstaffidcard();
        $data['idcardlist']      = $idcardlist;
        $data['idcard_generation_csrf'] = $this->session->userdata($this->generationCsrfKey);
        $this->form_validation->set_rules('id_card', $this->lang->line('id_card_template'), 'trim|required|xss_clean');
        if ($this->form_validation->run() == true) {
            $role                 = $this->input->post('role_id');
            $data['role_id']      = $this->input->post('role_id');
            $id_card              = $this->input->post('id_card');
            $idcardResult         = $this->Generatestaffidcard_model->getidcardbyid($id_card);
            $data['idcardResult'] = $idcardResult;
            $resultlist           = $this->staff_model->getEmployee($role, 1);
            $data['resultlist']   = $resultlist;
        }

        $this->load->view('layout/header');
        $this->load->view('admin/generatestaffidcard/generatestaffidcardview', $data);
        $this->load->view('layout/footer');
    }

    public function generatemultiple()
    {
        if (!$this->rbac->hasPrivilege('generate_staff_id_card', 'can_view')) {
            access_denied();
        }
        if (strtoupper((string) $this->input->server('REQUEST_METHOD')) !== 'POST') {
            show_error('This operation accepts POST requests only.', 405);
        }
        $this->requireGenerationCsrf();
        $staffid             = $this->input->post('data');
        $staff_array         = json_decode($staffid);
        $idcard              = (int) $this->input->post('id_card');
        $staffid_arr         = array();
        $data['sch_setting'] = $this->setting_model->get();
        $data['id_card']     = $this->Generatestaffidcard_model->getidcardbyid($idcard);

        if (!is_array($staff_array) || count($staff_array) < 1 || count($staff_array) > 250 || empty($data['id_card'])) {
            return $this->output->set_status_header(422)->set_output('Select between 1 and 250 valid staff members and an ID card template.');
        }
        foreach ($staff_array as $key => $value) {
            if (isset($value->staff_id) && ctype_digit((string) $value->staff_id)) {
                $staffid_arr[] = (int) $value->staff_id;
            }
        }
        $staffid_arr = array_values(array_unique($staffid_arr));
        if (empty($staffid_arr)) {
            return $this->output->set_status_header(422)->set_output('No valid staff members were selected.');
        }
        $data['staffs'] = $this->Generatestaffidcard_model->getEmployee($staffid_arr, 1);

        $studioDesign = $this->Idcardstudio_model->getPublishedForLegacy('staff', $idcard);
        if ($studioDesign) {
            $data = array_merge($data, $this->studioStaffPayload($studioDesign, $data['id_card'][0], $data['staffs']));
            $id_cards = $this->load->view('admin/idcardstudio/runtime_cards', $data, true);
        } else {
            $id_cards = $this->load->view('admin/generatestaffidcard/generatemultiplestaffidcard', $data, true);
        }
        return $this->output->set_output($id_cards);
    }

    /** Individual real-record card generation using the same published design. */
    public function generate($staff, $idcard)
    {
        if (!$this->rbac->hasPrivilege('generate_staff_id_card', 'can_view')) {
            access_denied();
        }
        if (!ctype_digit((string) $staff) || !ctype_digit((string) $idcard)) {
            show_404();
        }
        $templates = $this->Generatestaffidcard_model->getidcardbyid((int) $idcard);
        $staffs = $this->Generatestaffidcard_model->getEmployee(array((int) $staff), 1);
        if (empty($templates) || empty($staffs)) {
            show_404();
        }
        $data = array('id_card' => $templates, 'staffs' => $staffs);
        $studioDesign = $this->Idcardstudio_model->getPublishedForLegacy('staff', (int) $idcard);
        if ($studioDesign) {
            $data = array_merge($data, $this->studioStaffPayload($studioDesign, $templates[0], $staffs));
            $this->load->view('admin/idcardstudio/runtime_cards', $data);
            return;
        }
        $this->load->view('admin/generatestaffidcard/generatemultiplestaffidcard', $data);
    }

    private function studioStaffPayload($studioDesign, $legacyCard, array $staffs)
    {
        $cards = array();
        foreach ($staffs as $staff) {
            $staff = is_array($staff) ? (object) $staff : $staff;
            $credential = $this->db->table_exists('biometric_qr_credentials')
                ? $this->biometric_attendance_service->getActiveQrCredential('staff', (int) $staff->id, true)
                : null;
            $token = $credential && !empty($credential['token']) ? $credential['token'] : '';
            $joiningDate = (!empty($staff->date_of_joining) && $staff->date_of_joining !== '0000-00-00')
                ? date($this->customlib->getSchoolDateFormat(), $this->customlib->dateYYYYMMDDtoStrtotime($staff->date_of_joining)) : '';
            $dob = (!empty($staff->dob) && $staff->dob !== '0000-00-00')
                ? date($this->customlib->getSchoolDateFormat(), $this->customlib->dateYYYYMMDDtoStrtotime($staff->dob)) : '';
            $cards[] = array('bindings' => array(
                'school.name' => (string) $legacyCard->school_name,
                'school.address' => (string) $legacyCard->school_address,
                'school.logo' => !empty($legacyCard->logo) ? site_url('admin/idcardstudio/legacy_asset/staff/' . (int) $legacyCard->id . '/logo') : '',
                'school.signature' => !empty($legacyCard->sign_image) ? site_url('admin/idcardstudio/legacy_asset/staff/' . (int) $legacyCard->id . '/sign_image') : '',
                'school.background' => !empty($legacyCard->background) ? site_url('admin/idcardstudio/legacy_asset/staff/' . (int) $legacyCard->id . '/background') : '',
                'card.title' => (string) $legacyCard->title,
                'attendance.credential' => $token,
                'staff.full_name' => trim((isset($staff->name) ? $staff->name : '') . ' ' . (isset($staff->surname) ? $staff->surname : '')),
                'staff.employee_id' => isset($staff->employee_id) ? (string) $staff->employee_id : '',
                'staff.role' => isset($staff->user_type) ? (string) $staff->user_type : '',
                'staff.department' => isset($staff->department) ? (string) $staff->department : '',
                'staff.designation' => isset($staff->designation) ? (string) $staff->designation : '',
                'staff.father_name' => isset($staff->father_name) ? (string) $staff->father_name : '',
                'staff.mother_name' => isset($staff->mother_name) ? (string) $staff->mother_name : '',
                'staff.joining_date' => $joiningDate,
                'staff.address' => isset($staff->permanent_address) ? (string) $staff->permanent_address : '',
                'staff.phone' => isset($staff->contact_no) ? (string) $staff->contact_no : '',
                'staff.dob' => $dob,
                'staff.photo' => !empty($staff->image) ? site_url('admin/idcardstudio/subject_photo/staff/' . (int) $staff->id) : '',
            ));
        }
        return array(
            'studio_design' => $studioDesign,
            'studio_assets' => $this->Idcardstudio_model->publishedAssets($studioDesign->id),
            'studio_cards' => $cards,
        );
    }

    private function requireGenerationCsrf()
    {
        $expected = (string) $this->session->userdata($this->generationCsrfKey);
        $received = (string) $this->input->post('idcard_generation_csrf');
        if ($expected === '' || $received === '' || !hash_equals($expected, $received)) {
            show_error('The ID card generation request expired. Reload the page and try again.', 403);
        }
    }
}
