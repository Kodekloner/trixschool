<?php
defined('BASEPATH') or exit('No direct script access allowed');

/**
 * @package : Ramom School QR Attendance
 * @version : 3.0
 * @developed by : RamomCoder
 * @support : ramomcoder@yahoo.com
 * @author url : http://codecanyon.net/user/RamomCoder
 * @filename : Qr_code_attendance.php
 * @copyright : Reserved RamomCoder Team
 */

class Qrcode_attendance extends Admin_Controller
{
    public function __construct()
    {
        parent::__construct();
        if (!moduleIsEnabled('qr_code_attendance')) {
            access_denied();
        }
        $this->load->library('datatables');
        $this->load->model('attendance_model');
        $this->load->model('qrcode_attendance_model');
        $this->load->model('frontend_model');
        $this->load->model('sms_model');
    }

    public function index()
    {
        if (get_loggedin_id()) {
            redirect(base_url('dashboard'), 'refresh');
        } else {
            redirect(base_url(), 'refresh');
        }
    }

    public function take()
    {
        if (!get_permission('qr_code_employee_attendance', 'is_add')) {
            access_denied();
        }

        $branchID = $this->application_model->get_branch_id();
        $this->data['headerelements'] = array(
            'css' => array(
                'css/qr-code.css',
            ),
            'js' => array(
                'vendor/qrcode/qrcode.min.js',
                'js/qrcode_attendance.js',
            ),
        );
        $this->data['branch_id'] = $branchID;
        $this->data['getSettings'] = $this->qrcode_attendance_model->getSettings($branchID);
        $this->data['title'] = translate('attendance');
        $this->data['sub_page'] = 'qrcode_attendance/take';
        $this->data['main_menu'] = 'qr_attendance';
        $this->load->view('layout/index', $this->data);
    }

    public function getUserByQrcode()
    {
        if ($_POST) {
            $userData = trim(base64_decode($this->input->post('data')));
            $userData = explode("-", $userData);
            if ($userData[0] != 'e' && $userData[0] != 's') {
                $data['status'] = 'failed';
                $data['message'] = "<i class='fas fa-exclamation-triangle'></i> QR code is invalid.";
                echo json_encode($data);
                exit;
            }
            $staffID = $userData[1];
            $staffID = intval($staffID);
            if ($userData[0] == 'e') {
                if (!get_permission('qr_code_employee_attendance', 'is_add')) {
                    ajax_access_denied();
                }

                $data = [];
                $inOutTime = trim($this->input->post('in_out_time'));
                if ($inOutTime == 'in_time') {
                    $this->db->where('in_time !=', '');
                }
                if ($inOutTime == 'out_time') {
                    $this->db->where('out_time !=', '');
                }
                $this->db->where(array('staff_id' => $staffID, 'date' => date('Y-m-d')));
                $attendance = $this->db->get('staff_attendance')->row();
                if (!empty($attendance)) {
                    $data['status'] = 'failed';
                    if ($attendance->qr_code == 1) {
                        $data['message'] = "<i class='fas fa-exclamation-triangle'></i> Attendance has already been taken.";
                    } else {
                        $data['message'] = "<i class='fas fa-exclamation-triangle'></i> Attendance has already been taken by manually.";
                    }
                    echo json_encode($data);
                    exit();
                }
                //getting staff details
                $row = $this->qrcode_attendance_model->getSingleStaff($staffID);
                if (empty($row)) {
                    $data['status'] = 'failed';
                    $data['message'] = "<i class='fas fa-exclamation-triangle'></i> QR code is invalid / staff not found.";
                } else {
                    $data['userType'] = 'staff';
                    $data['status'] = 'successful';
                    $data['photo'] = get_image_url('staff', $row->photo);
                    $data['name'] = $row->name;
                    $data['role'] = $row->role;
                    $data['staff_id'] = $row->staff_id;
                    $data['joining_date'] = _d($row->joining_date);
                    $data['department'] = $row->department_name;
                    $data['designation'] = $row->designation_name;
                    $data['gender'] = ucfirst($row->sex);
                    $data['blood_group'] = (empty($row->blood_group) ? '-' : $row->blood_group);
                    $data['email'] = $row->email;
                }
                echo json_encode($data);
            }

            if ($userData[0] == 's') {
                if (!get_permission('qr_code_student_attendance', 'is_add')) {
                    ajax_access_denied();
                }

                $enrollID = $userData[1];
                $enrollID = intval($enrollID);
                $data = [];
                $inOutTime = trim($this->input->post('in_out_time'));
                if ($inOutTime == 'in_time') {
                    $this->db->where('in_time !=', '');
                }
                if ($inOutTime == 'out_time') {
                    $this->db->where('out_time !=', '');
                }
                $attendance = $this->db->where(array('enroll_id' => $enrollID, 'date' => date('Y-m-d')))->get('student_attendance')->row();
                if (!empty($attendance)) {
                    $data['status'] = 'failed';
                    if ($attendance->qr_code == 1) {
                        $data['message'] = "<i class='fas fa-exclamation-triangle'></i> Attendance has already been taken.";
                    } else {
                        $data['message'] = "<i class='fas fa-exclamation-triangle'></i> Attendance has already been taken by manually.";
                    }
                    echo json_encode($data);
                    exit();
                }
                //getting student details
                $row = $this->qrcode_attendance_model->getStudentDetailsByEid($enrollID);
                if (empty($row)) {
                    $data['status'] = 'failed';
                    $data['message'] = "<i class='fas fa-exclamation-triangle'></i> QR code is invalid / student not found.";
                } else {
                    $data['userType'] = 'student';
                    $data['status'] = 'successful';
                    $data['photo'] = get_image_url('student', $row->photo);
                    $data['full_name'] = $row->first_name . " " . $row->last_name;
                    $data['student_category'] = $row->cname;
                    $data['register_no'] = $row->register_no;
                    $data['roll'] = $row->roll;
                    $data['admission_date'] = empty($row->admission_date) ? "N/A" : _d($row->admission_date);
                    $data['birthday'] = empty($row->birthday) ? "N/A" : _d($row->birthday);
                    $data['class_name'] = $row->class_name;
                    $data['section_name'] = $row->section_name;
                    $data['email'] = $row->email;
                }
                echo json_encode($data);
            }
        }
    }

    // submitted attendance all data are prepared and stored in the database here
    public function setAttendanceByQrcode()
    {
        if ($_POST) {

            $data = [];
            $userData = trim(base64_decode($this->input->post('data')));
            $userData = explode("-", $userData);
            if ($userData[0] != 'e' && $userData[0] != 's') {
                $data['status'] = 'failed';
                $data['message'] = "<i class='fas fa-exclamation-triangle'></i> QR code is invalid.";
                echo json_encode($data);
                exit;
            }
            $staffID = $userData[1];
            $staffID = intval($staffID);

            $inOutTime = trim($this->input->post('in_out_time'));
            $attendanceRemark = $this->input->post('attendanceRemark');

            $table = "";
            $column = "";
            if ($userData[0] == 'e') {
                if (!get_permission('qr_code_employee_attendance', 'is_add')) {
                    ajax_access_denied();
                }
                $data['userType'] = 'staff';
                $table = "staff_attendance";
                $column = "staff_id";
                //getting student details
                $stuDetail = $this->qrcode_attendance_model->getSingleStaff($staffID);
            } elseif ($userData[0] == 's') {
                if (!get_permission('qr_code_student_attendance', 'is_add')) {
                    access_denied();
                }
                $data['userType'] = 'student';
                $table = "student_attendance";
                $column = "enroll_id";
                //getting student details
                $stuDetail = $this->qrcode_attendance_model->getStudentDetailsByEid($staffID);
            }

            // getting QR attendance settings
            $setting = $this->qrcode_attendance_model->getSettings(empty($stuDetail->branch_id) ? 0 : $stuDetail->branch_id);
            if ($inOutTime == 'in_time') {
                if ($setting->auto_late_detect == 1) {
                    if (strtotime($setting->staff_in_time) <= time()) {
                        $attendanceStatus = 'L';
                    } else {
                        $attendanceStatus = 'P';
                    }
                } else {
                    $attendanceStatus = (isset($_POST['late']) ? 'L' : 'P');
                }
            } else {
                if ($setting->auto_late_detect == 1) {
                    if (strtotime($setting->staff_out_time) >= time()) {
                        $attendanceStatus = 'HD';
                    } else {
                        $attendanceStatus = '';
                    }
                } else {
                    $attendanceStatus = (isset($_POST['late']) ? 'HD' : '');
                }
            }
            $attendance = $this->db->where(array($column => $staffID, 'date' => date('Y-m-d')))->get($table)->row();
            if (empty($attendance)) {
                $data['status'] = 1;
                $arrayAttendance = array(
                    $column => $staffID,
                    'status' => $attendanceStatus,
                    'qr_code' => "1",
                    'remark' => $attendanceRemark,
                    'date' => date('Y-m-d'),
                    'branch_id' => $stuDetail->branch_id,
                );
                $arrayAttendance[$inOutTime] = date('H:i:s');
                $this->db->insert($table, $arrayAttendance);
            } else {
                $data['status'] = 1;
                $update = array();
                $update[$inOutTime] = date('H:i:s');
                if (!empty($attendanceRemark)) {
                    $update['remark'] = $attendanceRemark;
                }
                if (!empty($attendanceStatus)) {
                    $update['status'] = $attendanceStatus;
                }
                $this->db->where('id', $attendance->id)->update($table, $update);
            }
            $data['message'] = translate('attendance_has_been_taken_successfully');
            echo json_encode($data);
        }
    }

    public function getStuListDT()
    {
        if ($_POST) {
            echo $this->qrcode_attendance_model->getStuListDT();
        }
    }

    public function getStaffListDT()
    {
        if ($_POST) {
            echo $this->qrcode_attendance_model->getStaffListDT();
        }
    }

    public function studentbydate()
    {
        if (!get_permission('qr_code_student_attendance_report', 'is_view')) {
            access_denied();
        }

        $branchID = $this->application_model->get_branch_id();
        $this->data['getWeekends'] = $this->application_model->getWeekends($branchID);
        $this->data['getHolidays'] = $this->attendance_model->getHolidays($branchID);
        if ($_POST) {
            if (is_superadmin_loggedin()) {
                $this->form_validation->set_rules('branch_id', translate('branch'), 'required');
            }
            $this->form_validation->set_rules('date', translate('date'), 'trim|required|callback_get_valid_date');
            if ($this->form_validation->run() == true) {
                $this->data['class_id'] = $this->input->post('class_id');
                $this->data['section_id'] = $this->input->post('section_id');
                $this->data['date'] = $this->input->post('date');
                $this->data['attendancelist'] = $this->qrcode_attendance_model->getDailyStudentReport($branchID, $this->data['class_id'], $this->data['section_id'], $this->data['date']);
            }
        }
        $this->data['branch_id'] = $branchID;
        $this->data['title'] = translate('student') . ' ' . translate('daily_reports');
        $this->data['sub_page'] = 'qrcode_attendance/studentbydate';
        $this->data['main_menu'] = 'qr_attendance_report';
        $this->load->view('layout/index', $this->data);
    }

    public function staffbydate()
    {
        if (!get_permission('qr_code_employee_attendance_report', 'is_view')) {
            access_denied();
        }

        $branchID = $this->application_model->get_branch_id();
        $this->data['getWeekends'] = $this->application_model->getWeekends($branchID);
        $this->data['getHolidays'] = $this->attendance_model->getHolidays($branchID);
        if ($_POST) {
            if (is_superadmin_loggedin()) {
                $this->form_validation->set_rules('branch_id', translate('branch'), 'required');
            }
            $this->form_validation->set_rules('date', translate('date'), 'trim|required|callback_get_valid_date');
            if ($this->form_validation->run() == true) {
                $this->data['staff_role'] = $this->input->post('staff_role');
                $this->data['date'] = $this->input->post('date');
                $this->data['attendancelist'] = $this->qrcode_attendance_model->getDailyStaffReport($branchID, $this->data['staff_role'], $this->data['date']);
            }
        }
        $this->data['branch_id'] = $branchID;
        $this->data['title'] = translate('employee') . ' ' . translate('daily_reports');
        $this->data['sub_page'] = 'qrcode_attendance/staffbydate';
        $this->data['main_menu'] = 'qr_attendance_report';
        $this->load->view('layout/index', $this->data);
    }

    public function get_valid_date($date)
    {
        $present_date = date('Y-m-d');
        $date = date("Y-m-d", strtotime($date));
        if ($date > $present_date) {
            $this->form_validation->set_message("get_valid_date", "Please Enter Correct Date");
            return false;
        } else {
            return true;
        }
    }

    public function check_holiday($date)
    {
        $branchID = $this->application_model->get_branch_id();
        $getHolidays = $this->attendance_model->getHolidays($branchID);
        $getHolidaysArray = explode('","', $getHolidays);

        if (!empty($getHolidaysArray)) {
            if (in_array($date, $getHolidaysArray)) {
                $this->form_validation->set_message('check_holiday', 'You have selected a holiday.');
                return false;
            } else {
                return true;
            }
        }
    }

    public function check_weekendday($date)
    {
        $branchID = $this->application_model->get_branch_id();
        $getWeekendDays = $this->attendance_model->getWeekendDaysSession($branchID);
        if (!empty($getWeekendDays)) {
            if (in_array($date, $getWeekendDays)) {
                $this->form_validation->set_message('check_weekendday', "You have selected a weekend date.");
                return false;
            } else {
                return true;
            }
        }
        return true;
    }

    public function settings()
    {
        // check access permission
        if (!get_permission('qr_code_settings', 'is_view')) {
            access_denied();
        }
        $branchID = $this->frontend_model->getBranchID();
        if ($_POST) {
            $branch_id = $this->input->post('branch_id');
            redirect(base_url('qrcode_attendance/settings?branch_id=' . $branch_id));
        }
        $this->data['headerelements'] = array(
            'css' => array(
                'vendor/bootstrap-timepicker/css/bootstrap-timepicker.css',
            ),
            'js' => array(
                'vendor/bootstrap-timepicker/bootstrap-timepicker.js',
                'vendor/moment/moment.js',
            ),
        );
        $this->data['branch_id'] = $branchID;
        $this->data['setting'] = $this->qrcode_attendance_model->get('qr_code_settings', array('branch_id' => $branchID), true);
        $this->data['title'] = translate('qr_code') . " " . translate('attendance');
        $this->data['sub_page'] = 'qrcode_attendance/setting';
        $this->data['main_menu'] = 'qr_attendance';
        $this->load->view('layout/index', $this->data);
    }

    public function settings_save()
    {
        if (!get_permission('qr_code_settings', 'is_add')) {
            ajax_access_denied();
        }
        if ($_POST) {
            $branchID = $this->frontend_model->getBranchID();
            $this->form_validation->set_rules('camera', translate('camera'), 'trim|required');
            $this->form_validation->set_rules('staff_in_time', translate('staff_in_time'), 'trim|required');
            $this->form_validation->set_rules('staff_out_time', translate('staff_out_time'), 'trim|required');
            $this->form_validation->set_rules('student_in_time', translate('student_in_time'), 'trim|required');
            $this->form_validation->set_rules('student_out_time', translate('student_out_time'), 'trim|required');

            if ($this->form_validation->run() == true) {
                $qr_setting = array(
                    'branch_id' => $branchID,
                    'confirmation_popup' => $this->input->post('confirmation_popup'),
                    'auto_late_detect' => $this->input->post('auto_late_detect'),
                    'camera' => $this->input->post('camera'),
                    'staff_in_time' => date("H:i:s", strtotime($this->input->post('staff_in_time'))),
                    'staff_out_time' => date("H:i:s", strtotime($this->input->post('staff_out_time'))),
                    'student_in_time' => date("H:i:s", strtotime($this->input->post('student_in_time'))),
                    'student_out_time' => date("H:i:s", strtotime($this->input->post('student_out_time'))),
                );

                // update all information in the database
                $this->db->where(array('branch_id' => $branchID));
                $get = $this->db->get('qr_code_settings');
                if ($get->num_rows() > 0) {
                    $this->db->where('id', $get->row()->id);
                    $this->db->update('qr_code_settings', $qr_setting);
                } else {
                    $this->db->insert('qr_code_settings', $qr_setting);
                }

                set_alert('success', translate('information_has_been_saved_successfully'));
                $array = array('status' => 'success');
            } else {
                $error = $this->form_validation->error_array();
                $array = array('status' => 'fail', 'error' => $error);
            }
            echo json_encode($array);
        }
    }
}
