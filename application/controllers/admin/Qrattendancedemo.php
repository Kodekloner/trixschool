<?php

if (!defined('BASEPATH')) {
    exit('No direct script access allowed');
}

class Qrattendancedemo extends Admin_Controller
{
    public function __construct()
    {
        parent::__construct();

        $this->load->helper(array('custom', 'url'));
        $this->load->model('setting_model');
    }

    public function index()
    {
        if (!$this->rbac->hasPrivilege('student_attendance', 'can_view')) {
            access_denied();
        }

        $data['title']         = 'QR Attendance Demo';
        $data['attendance_on'] = $this->setting_model->getDateYmd();

        $this->session->set_userdata('top_menu', 'Attendance');
        $this->session->set_userdata('sub_menu', 'qrattendancedemo/index');

        $this->load->view('layout/header', $data);
        $this->load->view('admin/qrattendance/demo', $data);
        $this->load->view('layout/footer', $data);
    }

    public function download($date = null)
    {
        if (!$this->rbac->hasPrivilege('student_attendance', 'can_view')) {
            access_denied();
        }

        if (empty($date)) {
            $date = $this->setting_model->getDateYmd();
        }

        $file_path = get_qr_attendance_demo_log_file($date);
        if (!file_exists($file_path)) {
            show_error('No QR attendance log file was found for ' . $date . '.', 404);
        }

        header('Content-Description: File Transfer');
        header('Content-Type: text/csv');
        header('Content-Disposition: attachment; filename="' . basename($file_path) . '"');
        header('Content-Length: ' . filesize($file_path));
        header('Pragma: public');
        header('Cache-Control: must-revalidate');
        readfile($file_path);
        exit;
    }
}
