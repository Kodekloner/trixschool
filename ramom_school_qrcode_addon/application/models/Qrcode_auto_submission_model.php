<?php
defined('BASEPATH') or exit('No direct script access allowed');

class Qrcode_auto_submission_model extends MY_Model
{
    public function getSections($class_id = '', $branch_id = '')
    {
        $session_id = get_session_id();
        $this->db->select('sections_allocation.class_id,sections_allocation.section_id,class.name as class_name,section.name as section_name, qrcode_auto_submission.id as submission_id,qrcode_auto_submission.time,qrcode_auto_submission.status');
        $this->db->from('sections_allocation');
        $this->db->join('class', 'class.id = sections_allocation.class_id', 'inner');
        $this->db->join('section', 'section.id = sections_allocation.section_id', 'inner');
        $this->db->join('qrcode_auto_submission', 'qrcode_auto_submission.class_id = class.id and qrcode_auto_submission.section_id = sections_allocation.section_id and qrcode_auto_submission.session_id = ' . $this->db->escape($session_id), 'left');
        $this->db->where('class.id', $class_id);
        $this->db->where('class.branch_id', $branch_id);
        $this->db->order_by('class.id', 'asc');
        $result = $this->db->get()->result();
        return $result;
    }

    // Get students whose attendance is pending for today
    public function get_pending_submissions()
    {
        $date = date('Y-m-d');
        $session_id = get_session_id();

        $this->db->select('e.id as enroll_id, e.branch_id, e.student_id, s.first_name, s.last_name, s.mobileno, s.register_no, s.admission_date, e.roll, c.name as class_name, se.name as section_name, p.mobileno as parent_mobile');
        $this->db->from('qrcode_auto_submission as qas');
        $this->db->join('enroll as e', 'e.class_id = qas.class_id AND e.section_id = qas.section_id and e.session_id = qas.session_id', 'inner');
        $this->db->join('student as s', 's.id = e.student_id', 'inner');
        $this->db->join('class as c', 'c.id = e.class_id', 'inner');
        $this->db->join('section as se', 'se.id = e.section_id', 'inner');
        $this->db->join('parent as p', 'p.id = s.parent_id', 'left');
        $this->db->join('student_attendance as sa', "sa.enroll_id = e.id AND sa.date = " . $this->db->escape($date), 'left');

        $this->db->where('qas.status', '1');
        $this->db->where('qas.time <=', date('H:i:s'));
        $this->db->where('qas.session_id', $session_id);
        $this->db->where('sa.enroll_id IS NULL'); // Only get students not yet marked
        $this->db->order_by('qas.time', 'ASC');

        return $this->db->get()->result();
    }

    // Insert multiple attendance records at once
    public function batch_insert($data)
    {
        if (!empty($data)) {
            $this->db->insert_batch('student_attendance', $data);
        }
    }

    public function send_sms_optimized($student, $branchID)
    {
        $sms_api = $this->application_model->smsServiceProvider($branchID);
        $template = $this->db->get_where('sms_template_details', array('template_id' => 3, 'branch_id' => $branchID))->row_array();

        if (!empty($template) && ($template['notify_student'] == 1 || $template['notify_parent'] == 1) && $sms_api != 'disabled') {

            $text = str_replace('{name}', $student->first_name . ' ' . $student->last_name, $template['template_body']);
            $text = str_replace('{register_no}', $student->register_no, $text);
            $text = str_replace('{admission_date}', $student->admission_date, $text);
            $text = str_replace('{class}', $student->class_name, $text);
            $text = str_replace('{section}', $student->section_name, $text);
            $text = str_replace('{roll}', $student->roll, $text);

            if ($template['notify_student'] == 1 && !empty($student->mobileno)) {
                $this->sms_model->_send($sms_api, $student->mobileno, $text, $template['dlt_template_id']);
            }

            if ($template['notify_parent'] == 1 && !empty($student->parent_mobile)) {
                $this->sms_model->_send($sms_api, $student->parent_mobile, $text, $template['dlt_template_id']);
            }
        }
    }
}
