<?php
defined('BASEPATH') or exit('No direct script access allowed');

/**
 * @package : Ramom School QR Attendance
 * @version : 2.0
 * @developed by : RamomCoder
 * @support : ramomcoder@yahoo.com
 * @author url : http://codecanyon.net/user/RamomCoder
 * @filename : Qr_code_settings.php
 * @copyright : Reserved RamomCoder Team
 */

class Qr_code_settings extends Admin_Controller
{
    public function __construct()
    {
        parent::__construct();
        $this->load->model('school_model');
    }

    public function index()
    {
        // check access permission
        if (!get_permission('frontend_setting', 'is_view')) {
            access_denied();
        }
        $branchID = $this->frontend_model->getBranchID();
        if ($_POST) {
            $branch_id = $this->input->post('branch_id');
            redirect(base_url('qrcode_attendance/setting?branch_id=' . $branch_id));
        }
        $this->data['headerelements'] = array(
            'css' => array(
                'vendor/dropify/css/dropify.min.css',
                'vendor/jquery-asColorPicker-master/css/asColorPicker.css',
            ),
            'js' => array(
                'vendor/dropify/js/dropify.min.js',
                'vendor/jquery-asColorPicker-master/libs/jquery-asColor.js',
                'vendor/jquery-asColorPicker-master/libs/jquery-asGradient.js',
                'vendor/jquery-asColorPicker-master/dist/jquery-asColorPicker.min.js',
            ),
        );
        $this->data['branch_id'] = $branchID;
        $this->data['setting'] = $this->frontend_model->get('front_cms_setting', array('branch_id' => $branchID), true);
        $this->data['title'] = translate('frontend');
        $this->data['sub_page'] = 'frontend/setting';
        $this->data['main_menu'] = 'frontend';
        $this->load->view('layout/index', $this->data);
    }
}
