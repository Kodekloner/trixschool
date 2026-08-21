<?php

if (!defined('BASEPATH')) {
    exit('No direct script access allowed');
}

class Generateidcard extends Admin_Controller
{

    private $generationCsrfKey = 'student_idcard_generation_csrf';

    public function __construct()
    {
        parent::__construct();

        $this->load->library('Customlib');
        $this->load->library('biometric_attendance_service');
        $this->load->model('Idcardstudio_model');
        $this->sch_setting_detail = $this->setting_model->getSetting();
        if (!$this->session->userdata($this->generationCsrfKey)) {
            $this->session->set_userdata($this->generationCsrfKey, bin2hex(random_bytes(32)));
        }
    }

    public function search()
    {
        if (!$this->rbac->hasPrivilege('generate_id_card', 'can_view')) {
            access_denied();
        }
        $this->session->set_userdata('top_menu', 'Certificate');
        $this->session->set_userdata('sub_menu', 'admin/generateidcard');

        $class                   = $this->class_model->get();
        $data['classlist']       = $class;
        $data['adm_auto_insert'] = $this->sch_setting_detail->adm_auto_insert;
        $data['sch_setting']     = $this->sch_setting_detail;
        $data['idcard_generation_csrf'] = $this->session->userdata($this->generationCsrfKey);
        $idcardlist              = $this->Generateidcard_model->getstudentidcard();
        $data['idcardlist']      = $idcardlist;
        $button                  = $this->input->post('search');
        if ($this->input->server('REQUEST_METHOD') == "GET") {
            $this->load->view('layout/header', $data);
            $this->load->view('admin/certificate/generateidcard', $data);
            $this->load->view('layout/footer', $data);
        } else {
            $class   = $this->input->post('class_id');
            $section = $this->input->post('section_id');
            $search  = $this->input->post('search');
            $id_card = $this->input->post('id_card');
            if (isset($search)) {
                $this->form_validation->set_rules('class_id', $this->lang->line('class'), 'trim|required|xss_clean');

                $this->form_validation->set_rules('id_card', $this->lang->line('id_card_template'), 'trim|required|xss_clean');
                if ($this->form_validation->run() == false) {

                } else {
                    $data['searchby']     = "filter";
                    $data['class_id']     = $this->input->post('class_id');
                    $data['section_id']   = $this->input->post('section_id');
                    $id_card              = $this->input->post('id_card');
                    $idcardResult         = $this->Generateidcard_model->getidcardbyid($id_card);
                    $data['idcardResult'] = $idcardResult;
                    $resultlist           = $this->student_model->searchByClassSection($class, $section);
                    $data['resultlist']   = $resultlist;
                    $title                = $this->classsection_model->getDetailbyClassSection($data['class_id'], $data['section_id']);
                    $data['title']        = 'Student Details for ' . $title['class'] . "(" . $title['section'] . ")";
                }
            }

            $this->load->view('layout/header', $data);
            $this->load->view('admin/certificate/generateidcard', $data);
            $this->load->view('layout/footer', $data);
        }
    }

    public function generate($student, $class, $idcard)
    {
        if (!$this->rbac->hasPrivilege('generate_id_card', 'can_view')) {
            access_denied();
        }
        if (!ctype_digit((string) $student) || !ctype_digit((string) $class) || !ctype_digit((string) $idcard)) {
            show_404();
        }

        $idcardlist         = $this->Generateidcard_model->getidcardbyid($idcard);
        if (empty($idcardlist)) {
            show_404();
        }
        $data['idcardlist'] = $idcardlist;
        $resultlist         = $this->student_model->getStudentsByArray(array((int) $student));
        if (!empty($resultlist) && (int) $resultlist[0]->class_id !== (int) $class) {
            show_404();
        }
        $data['resultlist'] = $resultlist;

        $studioDesign = $this->Idcardstudio_model->getPublishedForLegacy('student', $idcard);
        if ($studioDesign && !empty($resultlist)) {
            $students = array();
            foreach ((array) $resultlist as $record) {
                $students[] = is_array($record) ? (object) $record : $record;
            }
            $data = array_merge($data, $this->studioStudentPayload($studioDesign, $idcardlist[0], $students));
            $this->load->view('admin/idcardstudio/runtime_cards', $data);
            return;
        }

        // The unchanged legacy view uses array access while the current
        // student query returns objects. Preserve that renderer contract.
        $data['resultlist'] = array_map(function ($record) {
            return is_object($record) ? get_object_vars($record) : $record;
        }, (array) $resultlist);

        $this->load->view('admin/certificate/studentidcard', $data);
    }

    public function generatemultiple()
    {
        if (!$this->rbac->hasPrivilege('generate_id_card', 'can_view')) {
            access_denied();
        }
        if (strtoupper((string) $this->input->server('REQUEST_METHOD')) !== 'POST') {
            show_error('This operation accepts POST requests only.', 405);
        }
        $this->requireGenerationCsrf();

        $studentid           = $this->input->post('data');
        $student_array       = json_decode($studentid);
        $idcard              = (int) $this->input->post('id_card');
        $class               = $this->input->post('class_id');
        $data                = array();
        $std_arr             = array();
        $data['sch_setting'] = $this->setting_model->get();
        $data['id_card']     = $this->Generateidcard_model->getidcardbyid($idcard);

        if (!is_array($student_array) || count($student_array) < 1 || count($student_array) > 250 || empty($data['id_card'])) {
            return $this->output->set_status_header(422)->set_content_type('application/json')
                ->set_output(json_encode(array('status' => 0, 'message' => 'Select between 1 and 250 valid students and an ID card template.')));
        }

        foreach ($student_array as $key => $value) {
            if (isset($value->student_id) && ctype_digit((string) $value->student_id)) {
                $std_arr[] = (int) $value->student_id;
            }
        }

        $std_arr = array_values(array_unique($std_arr));
        if (empty($std_arr)) {
            return $this->output->set_status_header(422)->set_content_type('application/json')
                ->set_output(json_encode(array('status' => 0, 'message' => 'No valid students were selected.')));
        }

        $data['students']        = $this->student_model->getStudentsByArray($std_arr);
        $data['sch_settingdata'] = $this->sch_setting_detail;

        $studioDesign = $this->Idcardstudio_model->getPublishedForLegacy('student', $idcard);
        if ($studioDesign) {
            $data = array_merge($data, $this->studioStudentPayload($studioDesign, $data['id_card'][0], $data['students']));
            $id_cards = $this->load->view('admin/idcardstudio/runtime_cards', $data, true);
        } else {
            $id_cards = $this->load->view('admin/certificate/generatemultiple', $data, true);
        }
        return $this->output->set_content_type('application/json')
            ->set_output(json_encode(array('status' => 1, 'page' => $id_cards)));
    }

    private function studioStudentPayload($studioDesign, $legacyCard, array $students)
    {
        $cards = array();
        foreach ($students as $student) {
            $student = is_array($student) ? (object) $student : $student;
            $sessionId = isset($student->student_session_id) ? (int) $student->student_session_id : 0;
            $credential = $sessionId > 0 && $this->db->table_exists('biometric_qr_credentials')
                ? $this->biometric_attendance_service->getActiveQrCredential('student', $sessionId, true)
                : null;
            $token = $credential && !empty($credential['token']) ? $credential['token'] : '';
            $fullName = $this->customlib->getFullName(
                isset($student->firstname) ? $student->firstname : '',
                isset($student->middlename) ? $student->middlename : '',
                isset($student->lastname) ? $student->lastname : '',
                $this->sch_setting_detail->middlename,
                $this->sch_setting_detail->lastname
            );
            $dob = '';
            if (!empty($student->dob) && $student->dob !== '0000-00-00') {
                $dob = date($this->customlib->getSchoolDateFormat(), $this->customlib->dateYYYYMMDDtoStrtotime($student->dob));
            }
            $cards[] = array('bindings' => array(
                'school.name' => (string) $legacyCard->school_name,
                'school.address' => (string) $legacyCard->school_address,
                'school.logo' => !empty($legacyCard->logo) ? site_url('admin/idcardstudio/legacy_asset/student/' . (int) $legacyCard->id . '/logo') : '',
                'school.signature' => !empty($legacyCard->sign_image) ? site_url('admin/idcardstudio/legacy_asset/student/' . (int) $legacyCard->id . '/sign_image') : '',
                'school.background' => !empty($legacyCard->background) ? site_url('admin/idcardstudio/legacy_asset/student/' . (int) $legacyCard->id . '/background') : '',
                'card.title' => (string) $legacyCard->title,
                'attendance.credential' => $token,
                'student.full_name' => $fullName,
                'student.admission_no' => isset($student->admission_no) ? (string) $student->admission_no : '',
                'student.class_section' => trim((isset($student->class) ? $student->class : '') . ' - ' . (isset($student->section) ? $student->section : ''), ' -'),
                'student.father_name' => isset($student->father_name) ? (string) $student->father_name : '',
                'student.mother_name' => isset($student->mother_name) ? (string) $student->mother_name : '',
                'student.address' => isset($student->current_address) ? (string) $student->current_address : '',
                'student.phone' => isset($student->mobileno) ? (string) $student->mobileno : '',
                'student.dob' => $dob,
                'student.blood_group' => isset($student->blood_group) ? (string) $student->blood_group : '',
                'student.photo' => !empty($student->image) ? site_url('admin/idcardstudio/subject_photo/student/' . (int) $student->id) : '',
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
