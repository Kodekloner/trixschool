<?php

if (!defined('BASEPATH')) {
    exit('No direct script access allowed');
}

class Users extends Admin_Controller
{

    public function __construct()
    {
        parent::__construct();
        $this->load->model("classteacher_model");
        $this->sch_setting_detail = $this->setting_model->getSetting();
        $this->role_model->ensureWhatsappMessagingPermissionSetup();
    }

    public function index()
    {
        $can_manage_users = $this->rbac->hasPrivilege('superadmin', 'can_view');
        $can_message_whatsapp = $this->rbac->hasPrivilege('whatsapp_messaging', 'can_view');

        if (!$can_manage_users && !$can_message_whatsapp) {
            access_denied();
        }
        $this->session->set_userdata('top_menu', 'System Settings');
        $this->session->set_userdata('sub_menu', 'users/index');
        $studentList         = $this->student_model->getStudents();
        $staffList           = $this->staff_model->getAll_users();
        $parentList          = $this->student_model->getParentList();
        $data['sch_setting'] = $this->setting_model->getSetting();
        $data['studentList'] = $studentList;
        $data['parentList']  = $parentList;
        $data['staffList']   = $staffList;
        $data['can_manage_users'] = $can_manage_users;
        $data['whatsapp_messaging_can_view'] = $can_message_whatsapp;

        $this->load->view('layout/header', $data);
        $this->load->view('admin/users/userList', $data);
        $this->load->view('layout/footer', $data);
    }

    public function changeStatus()
    {
        if (!$this->rbac->hasPrivilege('superadmin', 'can_view')) {
            access_denied();
        }
        $id     = $this->input->post('id');
        $status = $this->input->post('status');
        $role   = $this->input->post('role');
        $data   = array('id' => $id, 'is_active' => $status);
        if ($role != "staff") {
            $result = $this->user_model->changeStatus($data);
        } else {
            if ($status == "yes") {
                $data['is_active'] = 1;
            } else {
                $data['is_active'] = 0;
            }

            $result = $this->staff_model->update($data);
        }

        if ($result) {
            $response = array('status' => 1, 'msg' => $this->lang->line('status_change_successfully'));
            echo json_encode($response);
        }
    }

    public function admissionreport()
    {
        if (!$this->rbac->hasPrivilege('student_history', 'can_view')) {
            access_denied();
        }

        $this->session->set_userdata('top_menu', 'Reports');
        $this->session->set_userdata('sub_menu', 'Reports/student_information');
        $this->session->set_userdata('subsub_menu', 'Reports/student_information/student_history');
        $data['title'] = 'Admission Report';

        $class                   = $this->class_model->get();
        $data['classlist']       = $class;
        $userdata                = $this->customlib->getUserData();
        $data['sch_setting']     = $this->sch_setting_detail;
        $data['adm_auto_insert'] = $this->sch_setting_detail->adm_auto_insert;
        $carray                  = array();

        if (!empty($data["classlist"])) {
            foreach ($data["classlist"] as $ckey => $cvalue) {

                $carray[] = $cvalue["id"];
            }
        }

        $admission_year = $this->student_model->admissionYear();
        $data["admission_year"] = $admission_year;
        $this->load->view("layout/header", $data);
        $this->load->view("admin/users/admissionReport", $data);
        $this->load->view("layout/footer", $data);
    }

    public function logindetailreport()
    {
        if (!$this->rbac->hasPrivilege('student_login_credential_report', 'can_view')) {
            access_denied();
        }
        $this->session->set_userdata('top_menu', 'Reports');
        $this->session->set_userdata('sub_menu', 'Reports/student_information');
        $this->session->set_userdata('subsub_menu', 'Reports/student_information/student_login_credential');
        $class             = $this->class_model->get();
        $data['classlist'] = $class;
        $data['adm_auto_insert'] = $this->sch_setting_detail->adm_auto_insert;
        
        $this->load->view("layout/header");
        $this->load->view("admin/users/logindetailreport", $data);
        $this->load->view("layout/footer");
    }

    //datatable function to check search parameter validation
    public function searchvalidation()
    {
        $class_id       = $this->input->post('class_id');
        $year     = $this->input->post('year');

        $this->form_validation->set_rules('class_id', $this->lang->line('class'), 'trim|required|xss_clean');
        if ($this->form_validation->run() == false) { 
            $error = array();
            
            $error['class_id'] = form_error('class_id');
            $array = array('status' => 0, 'error' => $error);
            echo json_encode($array);
        } else {

            $params      = array('class_id' => $class_id, 'year' => $year,  );
            $array       = array('status' => 1, 'error' => '', 'params' => $params);
            echo json_encode($array);
        }
    }

     public function dtadmissionreportlist()
    {
        
        $currency_symbol = $this->customlib->getSchoolCurrencyFormat();
        $class_id       = $this->input->post('class_id');
        $year       = $this->input->post('year');
        
        $sch_setting     = $this->sch_setting_detail;
        $result =    $this->student_model->searchdatatablebyAdmissionDetails($class_id, $year);
        $resultlist      = json_decode($result);
        
        $dt_data=array();
        if (!empty($resultlist->data)) {
            foreach ($resultlist->data as $resultlist_key => $student) { 


            $id            = $student->sid;
            $sessionlist = $this->student_model->studentSessionDetails($id);
            $startsession = $sessionlist['start'];
            $findstartyear = explode("-", $startsession);
            $startyear = $findstartyear[0];
            $endsession = $sessionlist['end'];
            $findendyear = explode("-", $endsession);
            $endyear = $findendyear[0];

                $viewbtn = "<a  href='".base_url()."student/view/".$student->id."'>".$this->customlib->getFullName($student->firstname,$student->middlename,$student->lastname,$sch_setting->middlename,$sch_setting->lastname)."</a>";
             
                $row   = array();
                $row[] = $student->admission_no ;
                $row[] = $viewbtn ;

                if ($student->admission_date != null && $student->admission_date!='0000-00-00') {
                   $row[]= date($this->customlib->getSchoolDateFormat(), $this->customlib->dateyyyymmddTodateformat($student->admission_date));
                }else{
                    $row[]="";
                }
                 $row[] = $sessionlist['startclass'] . "  -  " . $sessionlist['endclass']; 
                 $row[] = $sessionlist['start'] . "  -  " . $sessionlist['end'];;
                 $row[] = ($endyear - $startyear) + 1;

                if ($sch_setting->mobile_no) {
                     $row[] = $student->mobileno;
                }
                
                if ($sch_setting->guardian_name) {
                     $row[] = $student->guardian_name;
                }
                
                if ($sch_setting->guardian_phone) {
                     $row[] = $student->guardian_phone;
                }
                
                $dt_data[] = $row;  
            }

        }
        $json_data = array(
            "draw"            => intval($resultlist->draw),
            "recordsTotal"    => intval($resultlist->recordsTotal),
            "recordsFiltered" => intval($resultlist->recordsFiltered),
            "data"            => $dt_data,
        );
        echo json_encode($json_data); 
    }

    /*function to check search filter validation forstudent login credential report*/

    public function searchloginvalidation()
    {
        $class_id       = $this->input->post('class_id');
        $section_id     = $this->input->post('section_id');

        $this->form_validation->set_rules('class_id', $this->lang->line('class'), 'trim|required|xss_clean');
        $this->form_validation->set_rules('section_id', $this->lang->line('section'), 'trim|required|xss_clean');

        if ($this->form_validation->run() == false) { 
            $error = array();
            
            $error['class_id'] = form_error('class_id');
            $error['section_id'] = form_error('section_id');
            
            $array = array('status' => 0, 'error' => $error);
            echo json_encode($array);
        } else {

            $params      = array('class_id' => $class_id, 'section' => $section_id,  );
            $array       = array('status' => 1, 'error' => '', 'params' => $params);
            echo json_encode($array);
        }
    }

    public function dtcredentialreportlist()
    {
        
        $sch_setting     = $this->sch_setting_detail;
        $class_id   = $this->input->post("class_id");
        $section_id = $this->input->post("section_id");
        $result = $this->student_model->getdtforlogincredential($class_id, $section_id);
        $resultlist      = json_decode($result);
        $dt_data=array();

        if (!empty($resultlist->data)) {
            foreach ($resultlist->data as $resultlist_key => $student) { 

                $student_username = "";
                $student_password = "";
                $parent_username  = "";
                $parent_password  = "";

                $studentlist = $this->user_model->getUserLoginDetails($student->id);
                $parentlist = $this->user_model->getParentLoginDetails($student->id);
                if (!empty($studentlist) && isset($studentlist["role"]) && $studentlist["role"] == "student") {
                    $student_username = isset($studentlist["username"]) ? $studentlist["username"] : "";
                    $student_password = isset($studentlist["password"]) ? $studentlist["password"] : "";
                    $parent_username  = isset($parentlist["username"]) ? $parentlist["username"] : "";
                    $parent_password  = isset($parentlist["password"]) ? $parentlist["password"] : "";
                }

                $viewbtn = "<a  href='".base_url()."student/view/".$student->id."'>".$this->customlib->getFullName($student->firstname,$student->middlename,$student->lastname,$sch_setting->middlename,$sch_setting->lastname)."</a>";
             
                $row   = array();
                $row[] = $student->admission_no ;
                $row[] = $viewbtn ;

                if ($student_username !== "") {
                   $row[] = $student_username ;  
                }else{
                     $row[]="" ;
                }

                if ($student_password !== "") {
                   $row[] = $this->hideStoredPasswordHash($student_password);
                }else{
                     $row[]="" ;
                }

                if ($parent_username !== "") {
                   $row[] = $parent_username ;  
                }else{
                     $row[]="" ;
                }

                if ($parent_password !== "") {
                   $row[] = $this->hideStoredPasswordHash($parent_password);
                }else{
                     $row[]="" ;
                } 
                 
                $dt_data[] = $row;  
            }

        }
        $json_data = array(
            "draw"            => intval($resultlist->draw),
            "recordsTotal"    => intval($resultlist->recordsTotal),
            "recordsFiltered" => intval($resultlist->recordsFiltered),
            "data"            => $dt_data,
        );
        echo json_encode($json_data); 
    }

    private function hideStoredPasswordHash($value)
    {
        return $this->looksLikeStoredPasswordHash($value) ? '' : $value;
    }

    private function looksLikeStoredPasswordHash($value)
    {
        if (!is_scalar($value) || $value === '') {
            return false;
        }

        $value = trim((string) $value);

        if ($value === '') {
            return false;
        }

        if (function_exists('password_get_info')) {
            $info = password_get_info($value);
            if (!empty($info['algo']) || (!empty($info['algoName']) && $info['algoName'] !== 'unknown')) {
                return true;
            }
        }

        return preg_match('/^\$(2y|2a|2b|argon2i|argon2id)\$/', $value) === 1;
    }


}
