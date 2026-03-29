<?php

if (!defined('BASEPATH')) {
    exit('No direct script access allowed');
}

class Qrattendance extends CI_Controller
{
    public function __construct()
    {
        parent::__construct();

        $this->load->helper(array('url', 'custom'));
        $this->load->library('customlib');
        $this->load->model('attendencetype_model');
        $this->load->model('setting_model');
        $this->load->model('student_model');
        $this->load->model('stuattendence_model');
    }

    public function mark($student_session_id = null, $signature = null)
    {
        $data = array(
            'message'       => 'The QR code is invalid.',
            'status'        => 'danger',
            'student_name'  => '',
            'admission_no'  => '',
            'class_section' => '',
            'attendance_on' => $this->setting_model->getDateYmd(),
        );

        $student_session_id = (int) $student_session_id;

        if ($student_session_id <= 0 || empty($signature)) {
            return $this->load->view('qrattendance/result', $data);
        }

        $expected_signature = get_student_attendance_qr_hash($student_session_id);
        if (!hash_equals($expected_signature, (string) $signature)) {
            return $this->load->view('qrattendance/result', $data);
        }

        $student = $this->student_model->getByStudentSession($student_session_id);
        if (empty($student)) {
            $data['message'] = 'Student record not found for this QR code.';
            return $this->load->view('qrattendance/result', $data);
        }

        $today = $this->setting_model->getDateYmd();
        $data['attendance_on'] = $today;
        $data['student_name'] = $this->customlib->getFullName(
            $student['firstname'],
            $student['middlename'],
            $student['lastname'],
            $this->setting_model->getSetting()->middlename,
            $this->setting_model->getSetting()->lastname
        );
        $data['admission_no'] = $student['admission_no'];
        $data['class_section'] = trim($student['class'] . ' - ' . $student['section']);

        $existing_attendance = $this->attendencetype_model->getStudentAttendence($today, $student_session_id);
        if (!empty($existing_attendance)) {
            $data['status']  = 'info';
            $data['message'] = 'Attendance has already been marked for today as ' . $existing_attendance['type'] . '.';
            return $this->load->view('qrattendance/result', $data);
        }

        $insert_record = array(
            'date'                 => $today,
            'student_session_id'   => $student_session_id,
            'attendence_type_id'   => 1,
            'biometric_attendence' => 0,
            'remark'               => 'QR attendance via ID card',
            'created_at'           => date('Y-m-d H:i:s'),
            'biometric_device_data' => json_encode(array(
                'source'    => 'qr_id_card',
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

        $this->load->view('qrattendance/result', $data);
    }
}
