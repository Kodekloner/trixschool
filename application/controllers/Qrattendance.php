<?php

if (!defined('BASEPATH')) {
    exit('No direct script access allowed');
}

class Qrattendance extends CI_Controller
{
    private $school_setting;
    private $staff_attendance_config;

    public function __construct()
    {
        parent::__construct();

        $this->load->helper(array('url', 'custom'));
        $this->load->library('customlib');
        $this->load->model('attendencetype_model');
        $this->load->model('setting_model');
        $this->load->model('student_model');
        $this->load->model('staff_model');
        $this->load->model('staffattendancemodel');
        $this->load->model('stuattendence_model');
        $this->school_setting = $this->setting_model->getSetting();
        $this->staff_attendance_config = array(
            'present'  => 1,
            'late'     => 2,
            'absent'   => 3,
            'half_day' => 4,
            'holiday'  => 5,
        );
    }

    public function mark($student_session_id = null, $signature = null)
    {
        $data = $this->process_student_mark($student_session_id, $signature, 'direct_qr');
        $this->load->view('qrattendance/result', $data);
    }

    public function staff($staff_id = null, $signature = null)
    {
        $data = $this->process_staff_mark($staff_id, $signature, 'direct_qr');
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
        $staff_id           = $this->input->post('staff_id');
        $signature          = $this->input->post('signature');
        $entity_type        = trim((string) $this->input->post('entity_type'));

        if ($scan_value !== '') {
            $parsed = $this->parse_scan_value($scan_value);
            if (!$parsed) {
                return $this->json_response(array(
                    'status'  => 'danger',
                    'message' => 'The scanned QR code is invalid.',
                ), 422);
            }

            $entity_type = $parsed['entity_type'];
            $signature   = $parsed['signature'];

            if ($entity_type === 'staff') {
                $staff_id = $parsed['staff_id'];
            } else {
                $student_session_id = $parsed['student_session_id'];
            }
        }

        if ($entity_type === 'staff' || ((int) $staff_id > 0 && (int) $student_session_id <= 0)) {
            $result = $this->process_staff_mark($staff_id, $signature, 'scan_station');
        } else {
            $result = $this->process_student_mark($student_session_id, $signature, 'scan_station');
        }

        return $this->json_response($result, 200);
    }

    private function base_response($source, $entity_type = 'student')
    {
        return array(
            'message'          => 'The QR code is invalid.',
            'status'           => 'danger',
            'entity_type'      => $entity_type,
            'entity_label'     => ucfirst($entity_type),
            'full_name'        => '',
            'identity_no'      => '',
            'details_label'    => $entity_type === 'staff' ? 'Department' : 'Class',
            'details_value'    => '',
            'attendance_on'    => $this->setting_model->getDateYmd(),
            'scanned_at'       => date('Y-m-d H:i:s'),
            'source'           => $source,
            'student_name'     => '',
            'admission_no'     => '',
            'class_section'    => '',
        );
    }

    private function process_student_mark($student_session_id, $signature, $source)
    {
        $data = $this->base_response($source, 'student');

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
        $data['full_name']      = $data['student_name'];
        $data['identity_no']    = $data['admission_no'];
        $data['details_value']  = $data['class_section'];

        $existing_attendance = $this->attendencetype_model->getStudentAttendence($today, $student_session_id);
        if (!empty($existing_attendance)) {
            $attendance_type = '';
            if (is_array($existing_attendance) && isset($existing_attendance['type'])) {
                $attendance_type = $existing_attendance['type'];
            } elseif (is_object($existing_attendance) && isset($existing_attendance->type)) {
                $attendance_type = $existing_attendance->type;
            }

            $data['status']  = 'info';
            $data['message'] = 'Attendance has already been marked for today' . ($attendance_type !== '' ? ' as ' . $attendance_type : '') . '.';
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

    private function process_staff_mark($staff_id, $signature, $source)
    {
        $data = $this->base_response($source, 'staff');
        $staff_id = (int) $staff_id;

        if ($staff_id <= 0 || empty($signature)) {
            $this->append_demo_log($data);
            return $data;
        }

        $expected_signature = get_staff_attendance_qr_hash($staff_id);
        if (!hash_equals($expected_signature, (string) $signature)) {
            $this->append_demo_log($data);
            return $data;
        }

        $staff = $this->staff_model->getAll($staff_id, 1);
        if (empty($staff)) {
            $data['message'] = 'Staff record not found for this QR code.';
            $this->append_demo_log($data);
            return $data;
        }

        $today                 = $this->setting_model->getDateYmd();
        $data['attendance_on'] = $today;
        $data['full_name']     = trim($staff['name'] . ' ' . $staff['surname']);
        $data['identity_no']   = isset($staff['employee_id']) ? $staff['employee_id'] : '';
        $data['details_value'] = isset($staff['department']) ? $staff['department'] : '';

        $existing_attendance = $this->staffattendancemodel->searchStaffattendance($today, $staff_id, true);
        if (!empty($existing_attendance) && !empty($existing_attendance['attendence_id'])) {
            $attendance_type = !empty($existing_attendance['att_type']) ? $existing_attendance['att_type'] : 'present';
            $data['status']  = 'info';
            $data['message'] = 'Attendance has already been marked for today as ' . $attendance_type . '.';
            $this->append_demo_log($data);
            return $data;
        }

        $present_type_id = isset($this->staff_attendance_config['present']) ? (int) $this->staff_attendance_config['present'] : 1;
        $this->staffattendancemodel->add(array(
            'staff_id'                  => $staff_id,
            'staff_attendance_type_id'  => $present_type_id,
            'date'                      => $today,
            'remark'                    => 'QR attendance via ID card',
        ));

        $data['status']  = 'success';
        $data['message'] = 'Attendance has been marked successfully.';
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
                'entity_type'        => 'student',
                'student_session_id' => (int) $matches[1],
                'signature'          => $matches[2],
            );
        }

        if (preg_match('#qrattendance/staff/([0-9]+)/([a-f0-9]{64})#i', $scan_value, $matches)) {
            return array(
                'entity_type' => 'staff',
                'staff_id'    => (int) $matches[1],
                'signature'   => $matches[2],
            );
        }

        return false;
    }

    private function append_demo_log($data)
    {
        $file_path = get_qr_attendance_demo_log_file($data['attendance_on']);
        $directory = dirname($file_path);

        if (!is_dir($directory) && !@mkdir($directory, 0775, true)) {
            log_message('error', 'QR attendance log directory could not be created: ' . $directory);
            return false;
        }

        $is_new_file = !file_exists($file_path);
        $handle      = @fopen($file_path, 'a');
        if ($handle === false) {
            log_message('error', 'QR attendance log file could not be opened for writing: ' . $file_path);
            return false;
        }

        if ($is_new_file) {
            fputcsv($handle, array('person_type', 'identity_no', 'full_name', 'details', 'attendance_on', 'scanned_at', 'status', 'message', 'source'));
        }

        fputcsv($handle, array(
            $data['entity_type'],
            $data['identity_no'],
            $data['full_name'],
            $data['details_value'],
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
