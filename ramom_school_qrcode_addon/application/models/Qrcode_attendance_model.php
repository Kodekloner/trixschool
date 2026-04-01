<?php
if (!defined('BASEPATH')) exit('No direct script access allowed');

class Qrcode_attendance_model extends MY_Model
{
    public function __construct()
    {
        parent::__construct();
    }

    public function getStudentDetailsByEid($enrollID = '')
    {
        $this->db->select('s.first_name,s.last_name,s.register_no,s.email,s.photo,admission_date,birthday,enroll.student_id,enroll.branch_id,enroll.roll,class.name as class_name,section.name as section_name,student_category.name as cname');
        $this->db->from('enroll');
        $this->db->join('student as s', 's.id = enroll.student_id', 'inner');
        $this->db->join('class', 'class.id = enroll.class_id', 'left');
        $this->db->join('section', 'section.id = enroll.section_id', 'left');
        $this->db->join('student_category', 'student_category.id = s.category_id', 'left');
        $this->db->where('enroll.id', $enrollID);
        if (!is_superadmin_loggedin())
            $this->db->where('enroll.branch_id', get_loggedin_branch_id());
        $row = $this->db->get()->row();
        return $row;
    }

    public function getSingleStaff($id = '')
    {
        $this->db->select('staff.*,staff_designation.name as designation_name,staff_department.name as department_name,login_credential.role as role_id,login_credential.active,login_credential.username, roles.name as role');
        $this->db->from('staff');
        $this->db->join('login_credential', 'login_credential.user_id = staff.id and login_credential.role != "6" and login_credential.role != "7"', 'inner');
        $this->db->join('roles', 'roles.id = login_credential.role', 'left');
        $this->db->join('staff_designation', 'staff_designation.id = staff.designation', 'left');
        $this->db->join('staff_department', 'staff_department.id = staff.department', 'left');
        $this->db->where('staff.id', $id);
        if (!is_superadmin_loggedin())
            $this->db->where('staff.branch_id', get_loggedin_branch_id());
        $query = $this->db->get();
        return $query->row();
    }

    public function getStuListDT()
    {
        $today     = date('Y-m-d');
        $sessionID = get_session_id();
        $start     = $this->input->post('start');

        $this->datatables->select('student_attendance.date, student_attendance.in_time, student_attendance.out_time,enroll.id as enroll_id,enroll.roll, student.register_no, CONCAT_WS(" ", student.first_name, student.last_name) as fullname,class.name as class_name, section.name as section_name');
        $this->datatables->from('student_attendance');
        $this->datatables->join('enroll', 'enroll.id = student_attendance.enroll_id', 'inner');
        $this->datatables->join('student', 'student.id = enroll.student_id', 'inner');
        $this->datatables->join('class', 'class.id = enroll.class_id', 'left');
        $this->datatables->join('section', 'section.id = enroll.section_id', 'left');
        $this->datatables->where('enroll.session_id', $sessionID);
        $this->datatables->where('student_attendance.qr_code', 1);
        $this->datatables->where('date(student_attendance.date)', $today);
        if (!is_superadmin_loggedin()) {
            $this->datatables->where('student_attendance.branch_id', get_loggedin_branch_id());
        }
        $this->datatables->search_value('student_attendance.date,student.register_no,student.first_name,student.last_name,class.name,section.name');
        $this->datatables->column_order('student_attendance.id,student.first_name,class.id,student.register_no,enroll.roll,student_attendance.date,student_attendance.in_time,student_attendance.out_time');
        $this->datatables->order_by('student_attendance.id', 'asc');
        $results = $this->datatables->generate();

        // data processing for DataTable
        $records = array();
        $records = json_decode($results);
        $data    = array();
        $count   = $start + 1;
        foreach ($records->data as $key => $record) {
            // dt-data array
            $row    = array();
            $row    = array();
            $row[]  = $count++;
            $row[]  = $record->fullname;
            $row[]  = $record->class_name . ' (' . $record->section_name . ')';
            $row[]  = $record->register_no;
            $row[]  = empty($record->roll) ? '-' : $record->roll;
            $row[]  = _d($record->date);
            $row[]  = empty($record->in_time) ? '-' : date('h:i a', strtotime($record->in_time));
            $row[]  = empty($record->out_time) ? '-' : date('h:i a', strtotime($record->out_time));
            $data[] = $row;
        }
        $json_data = array(
            'draw'            => intval($records->draw),
            'recordsTotal'    => intval($records->recordsTotal),
            'recordsFiltered' => intval($records->recordsFiltered),
            'data'            => $data,
        );
        return json_encode($json_data);
    }

    public function getStaffListDT()
    {
        $today = date('Y-m-d');
        $start = $this->input->post('start');
        $this->datatables->select('staff.name,staff.staff_id,staff_attendance.date,staff_attendance.in_time,staff_attendance.out_time,roles.name as role');
        $this->datatables->from('staff_attendance');
        $this->datatables->join('staff', 'staff.id = staff_attendance.staff_id', 'inner');
        $this->datatables->join('login_credential', "login_credential.user_id = staff.id and login_credential.role != '6' and login_credential.role != '7'", 'inner');
        $this->datatables->join('roles', 'roles.id = login_credential.role', 'left');
        $this->datatables->where('staff_attendance.qr_code', 1);
        $this->datatables->where('date(staff_attendance.date)', $today);
        if (!is_superadmin_loggedin()) {
            $this->datatables->where('staff_attendance.branch_id', get_loggedin_branch_id());
        }
        $this->datatables->search_value('staff.name,staff.staff_id,roles.name,staff_attendance.date');
        $this->datatables->column_order('staff_attendance.id,staff.name,staff.staff_id,roles.name,staff_attendance.date,staff_attendance.in_time,staff_attendance.out_time');
        $this->datatables->order_by('staff_attendance.id', 'asc');
        $results = $this->datatables->generate();
        // data processing for DataTable
        $records = array();
        $records = json_decode($results);
        $data    = array();
        $count   = $start + 1;
        foreach ($records->data as $key => $record) {
            // dt-data array
            $row    = array();
            $row    = array();
            $row[]  = $count++;
            $row[]  = $record->name;
            $row[]  = $record->staff_id;
            $row[]  = $record->role;
            $row[]  = _d($record->date);
            $row[]  = empty($record->in_time) ? '-' : date('h:i a', strtotime($record->in_time));
            $row[]  = empty($record->out_time) ? '-' : date('h:i a', strtotime($record->out_time));
            $data[] = $row;
        }
        $json_data = array(
            'draw'            => intval($records->draw),
            'recordsTotal'    => intval($records->recordsTotal),
            'recordsFiltered' => intval($records->recordsFiltered),
            'data'            => $data,
        );
        return json_encode($json_data);
    }

    public function getDailyStudentReport($branchID = '', $classID = '', $sectionID = '', $date = '')
    {
        $this->db->select('student_attendance.*,CONCAT_WS(" ",first_name, last_name) as fullname,register_no,roll,enroll.student_id');
        $this->db->from('enroll');
        $this->db->join('student_attendance', 'student_attendance.enroll_id = enroll.id', 'right');
        $this->db->join('student', 'student.id = enroll.student_id', 'inner');
        $this->db->where('enroll.class_id', $classID);
        $this->db->where('enroll.section_id', $sectionID);
        $this->db->where('student_attendance.qr_code', 1);
        $this->db->where('student_attendance.date', $date);
        $this->db->order_by('student_attendance.id', 'asc');
        return $this->db->get()->result();
    }

    public function getDailyStaffReport($branchID = '', $staff_role = '', $date = '')
    {
        $this->db->select('staff_attendance.*,staff.name,staff.staff_id as staffID,staff_department.name as department_name,roles.name as role_name');
        $this->db->from('staff');
        $this->db->join('staff_attendance', 'staff_attendance.staff_id = staff.id', 'right');
        $this->db->join('login_credential', 'login_credential.user_id = staff.id and login_credential.role != "6" and login_credential.role != "7"', 'inner');
        $this->db->join('staff_department', 'staff_department.id = staff.department', 'left');
        $this->db->join('roles', 'roles.id = login_credential.role', 'left');
        if ($staff_role != '')
            $this->db->where('login_credential.role', $staff_role);
        $this->db->where('staff_attendance.qr_code', 1);
        $this->db->where('staff_attendance.date', $date);
        $this->db->order_by('staff_attendance.id', 'asc');
        return $this->db->get()->result();
    }

    public function getSettings($branchID = '')
    {
        $row                        = $this->db->where('branch_id', $branchID)->get('qr_code_settings')->row();
        $object                     = new stdClass();
        $object->confirmation_popup = !isset($row->confirmation_popup) ? 1 : $row->confirmation_popup;
        $object->auto_late_detect   = !isset($row->auto_late_detect) ? 0 : $row->auto_late_detect;
        $object->camera             = !isset($row->camera) ? 'environment' : $row->camera;
        $object->staff_in_time      = !isset($row->staff_in_time) ? '00:00:00' : $row->staff_in_time;
        $object->staff_out_time     = !isset($row->staff_out_time) ? '00:00:00' : $row->staff_out_time;
        $object->student_in_time    = !isset($row->student_in_time) ? '00:00:00' : $row->student_in_time;
        $object->student_out_time   = !isset($row->student_out_time) ? '00:00:00' : $row->student_out_time;
        return $object;
    }
}
