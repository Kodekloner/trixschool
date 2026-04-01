<?php
defined('BASEPATH') or exit('No direct script access allowed');

/**
 * @package : Ramom School QR Attendance
 * @version : 3.0
 * @developed by : RamomCoder
 * @support : ramomcoder@yahoo.com
 * @author url : http://codecanyon.net/user/RamomCoder
 * @filename : Qrcode_auto_submission.php
 * @copyright : Reserved RamomCoder Team
 */

class Qrcode_auto_submission extends Admin_Controller
{

    public function __construct()
    {
        parent::__construct();
        if (!moduleIsEnabled('qr_code_attendance')) {
            access_denied();
        }
        $this->load->model('qrcode_auto_submission_model');
        $this->load->model('qrcode_attendance_model');
        $this->load->model('sms_model');
        $this->api_key = $this->data['global_config']['cron_secret_key'];
    }

    public function index()
    {
        if (get_loggedin_id()) {
            redirect(base_url('qrcode_auto_submission/settings'));
        } else {
            redirect(base_url(), 'refresh');
        }
    }

    public function settings()
    {
        // check access permission
        if (!get_permission('qr_code_settings', 'is_view')) {
            access_denied();
        }

        // Get branch and class IDs
        $branchID = $this->application_model->get_branch_id();
        $classID = $this->input->post('class_id');
        // Handle form submission
        if ($_POST) {
            // Superadmin must select branch
            if (is_superadmin_loggedin()) {
                $this->form_validation->set_rules('branch_id', translate('branch'), 'trim|required');
            }
            $this->form_validation->set_rules('class_id', translate('class_id'), 'trim|required');
            // If validation passes, fetch sections
            if ($this->form_validation->run() === true) {
                $this->data['getSections'] = $this->qrcode_auto_submission_model->getSections($classID, $branchID);
            }
        }

        // Prepare data for view
        $this->data['class_id'] = $classID;
        $this->data['branch_id'] = $branchID;
        $this->data['headerelements'] = [
            'css' => ['vendor/bootstrap-timepicker/css/bootstrap-timepicker.css'],
            'js' => [
                'vendor/bootstrap-timepicker/bootstrap-timepicker.js',
                'vendor/moment/moment.js',
            ],
        ];
        $this->data['title'] = translate('qr_code') . " " . translate('attendance');
        $this->data['sub_page'] = 'qrcode_auto_submission/settings';
        $this->data['main_menu'] = 'qr_attendance';
        $this->load->view('layout/index', $this->data);
    }

    public function save()
    {
        // check access permission
        if (!get_permission('qr_code_settings', 'is_add')) {
            ajax_access_denied();
        }
        if ($_POST) {
            if (!get_permission('qr_code_employee_attendance', 'is_add')) {
                ajax_access_denied();
            }

            $items = $this->input->post('attendance');
            if (!empty($items)) {
                foreach ($items as $key => $value) {
                    $this->form_validation->set_rules('attendance[' . $key . '][time]', translate('time'), 'trim|required');
                }
            }
            if ($this->form_validation->run() == true) {
                $classID = $this->input->post('class_id');
                foreach ($items as $key => $value) {
                    $attStatus = (isset($value['status']) ? 1 : 0);
                    $sectionID = $value['section_id'];
                    $str_time = date("H:i", strtotime($value['time']));
                    $arrayAttendance = array(
                        'class_id' => $classID,
                        'section_id' => $sectionID,
                        'session_id' => get_session_id(),
                    );
                    $query = $this->db->get_where('qrcode_auto_submission', $arrayAttendance);
                    if ($query->num_rows() > 0) {
                        $this->db->where('id', $query->row()->id);
                        $this->db->update('qrcode_auto_submission', array('status' => $attStatus, 'time' => $str_time));
                    } else {
                        $arrayAttendance['time'] = $str_time;
                        $arrayAttendance['status'] = $attStatus;
                        $this->db->insert('qrcode_auto_submission', $arrayAttendance);
                    }
                }
                $message = translate('information_has_been_saved_successfully');
                $array = array('status' => 'success', 'message' => $message, 'error' => '');
            } else {
                $error = $this->form_validation->error_array();
                $array = array('status' => 'fail', 'url' => '', 'error' => $error);
            }
            echo json_encode($array);
        }
    }

    public function cron_job($api_key = '')
    {
        if ($api_key == "" || ($api_key != "" && $this->api_key != $api_key)) {
            echo "API Key is required or API Key does not match.";
            exit();
        }

        $pending = $this->qrcode_auto_submission_model->get_pending_submissions();

        if (!empty($pending)) {
            $attendance_batch = [];
            foreach ($pending as $student) {
                // Prepare Batch Attendance Data
                $attendance_batch[] = [
                    'enroll_id' => $student->enroll_id,
                    'branch_id' => $student->branch_id,
                    'date' => date('Y-m-d'),
                    'status' => 'A', // Absent
                ];

                // Trigger SMS using pre-fetched data
                $this->qrcode_auto_submission_model->send_sms_optimized($student, $student->branch_id);
            }

            // Execute batch insert for high performance
            $this->qrcode_auto_submission_model->batch_insert($attendance_batch);
            echo count($attendance_batch) . " attendance records processed.";
        } else {
            echo "No pending submissions for this time slot.";
        }
    }
}
