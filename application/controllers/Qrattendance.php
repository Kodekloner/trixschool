<?php

if (!defined('BASEPATH')) {
    exit('No direct script access allowed');
}

class Qrattendance extends CI_Controller
{
    private $school_setting;

    public function __construct()
    {
        parent::__construct();

        $this->load->helper(array('url', 'custom'));
        $this->load->library('customlib');
        $this->load->model('attendencetype_model');
        $this->load->model('setting_model');
        $this->load->model('student_model');
        $this->load->model('stuattendence_model');
        $this->school_setting = $this->setting_model->getSetting();
    }

    public function mark($student_session_id = null, $signature = null)
    {
        $data = $this->process_mark($student_session_id, $signature, 'direct_qr');
        $this->load->view('qrattendance/result', $data);
    }

    public function api_mark()
    {
        if ($this->input->server('REQUEST_METHOD') !== 'POST') {
            return $this->json_response(array(
                'status'  => 'danger',
                'message' => 'Invalid request method.',
            ), 405);
        }

        $scan_value         = trim((string) $this->input->post('scan_value'));
        $student_session_id = $this->input->post('student_session_id');
        $signature          = $this->input->post('signature');

        if ($scan_value !== '') {
            $parsed = $this->parse_scan_value($scan_value);
            if (!$parsed) {
                return $this->json_response(array(
                    'status'  => 'danger',
                    'message' => 'The scanned QR code is invalid.',
                ), 422);
            }

            $student_session_id = $parsed['student_session_id'];
            $signature          = $parsed['signature'];
        }

        $result = $this->process_mark($student_session_id, $signature, 'scan_station');
        return $this->json_response($result, 200);
    }

    private function process_mark($student_session_id, $signature, $source)
    {
        $data = array(
            'message'       => 'The QR code is invalid.',
            'status'        => 'danger',
            'student_name'  => '',
            'admission_no'  => '',
            'class_section' => '',
            'attendance_on' => $this->setting_model->getDateYmd(),
            'scanned_at'    => date('Y-m-d H:i:s'),
            'source'        => $source,
        );

        $student_session_id = (int) $student_session_id;

        if ($student_session_id <= 0 || empty($signature)) {
            $this->append_demo_log($data);
            return $data;
        }

        $expected_signature = get_student_attendance_qr_hash($student_session_id);
        if (!hash_equals($expected_signature, (string) $signature)) {
            $this->append_demo_log($data);
            return $data;
        }

        $student = $this->student_model->getByStudentSession($student_session_id);
        if (empty($student)) {
            $data['message'] = 'Student record not found for this QR code.';
            $this->append_demo_log($data);
            return $data;
        }

        $today                  = $this->setting_model->getDateYmd();
        $data['attendance_on']  = $today;
        $data['student_name']   = $this->customlib->getFullName(
            $student['firstname'],
            $student['middlename'],
            $student['lastname'],
            $this->school_setting->middlename,
            $this->school_setting->lastname
        );
        $data['admission_no']   = $student['admission_no'];
        $data['class_section']  = trim($student['class'] . ' - ' . $student['section']);

        $existing_attendance = $this->attendencetype_model->getStudentAttendence($today, $student_session_id);
        if (!empty($existing_attendance)) {
            $data['status']  = 'info';
            $data['message'] = 'Attendance has already been marked for today as ' . $existing_attendance['type'] . '.';
            $this->append_demo_log($data);
            return $data;
        }

        $insert_record = array(
            'date'                  => $today,
            'student_session_id'    => $student_session_id,
            'attendence_type_id'    => 1,
            'biometric_attendence'  => 0,
            'remark'                => 'QR attendance via ID card',
            'created_at'            => $data['scanned_at'],
            'biometric_device_data' => json_encode(array(
                'source'    => $source,
                'ip'        => $this->input->ip_address(),
                'useragent' => $this->input->user_agent(),
            )),
        );

        $insert_result = $this->stuattendence_model->onlineattendence($insert_record);
        if ($insert_result) {
            $data['status']  = 'success';
            $data['message'] = 'Attendance has been marked successfully.';
        } else {
            $data['status']  = 'warning';
            $data['message'] = 'Attendance could not be marked because a record already exists.';
        }

        $this->append_demo_log($data);
        return $data;
    }

    private function parse_scan_value($scan_value)
    {
        $scan_value = trim((string) $scan_value);
        if ($scan_value === '') {
            return false;
        }

        $matches = array();
        if (preg_match('#qrattendance/mark/([0-9]+)/([a-f0-9]{64})#i', $scan_value, $matches)) {
            return array(
                'student_session_id' => (int) $matches[1],
                'signature'          => $matches[2],
            );
        }

        return false;
    }

    private function append_demo_log($data)
    {
        $file_path = get_qr_attendance_demo_log_file($data['attendance_on']);
        $directory = dirname($file_path);

        if (!is_dir($directory)) {
            @mkdir($directory, 0775, true);
        }

        $is_new_file = !file_exists($file_path);
        $handle      = @fopen($file_path, 'a');
        if ($handle === false) {
            return false;
        }

        if ($is_new_file) {
            fputcsv($handle, array('admission_no', 'student_name', 'class_section', 'attendance_on', 'scanned_at', 'status', 'message', 'source'));
        }

        fputcsv($handle, array(
            $data['admission_no'],
            $data['student_name'],
            $data['class_section'],
            $data['attendance_on'],
            $data['scanned_at'],
            $data['status'],
            $data['message'],
            $data['source'],
        ));

        fclose($handle);
        return true;
    }

    private function json_response($payload, $status_code)
    {
        $this->output
            ->set_status_header($status_code)
            ->set_content_type('application/json')
            ->set_output(json_encode($payload));
    }
}
