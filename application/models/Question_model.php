<?php

if (!defined('BASEPATH')) {
    exit('No direct script access allowed');
}

class Question_model extends MY_model
{
    public function __construct()
    {
        parent::__construct();
        $this->sch_setting_detail = $this->setting_model->getSetting();
    }
    public function add($data)
    {
        $this->db->trans_start(); # Starting Transaction
        $this->db->trans_strict(false); # See Note 01. If you wish can remove as well
        //=======================Code Start===========================
        if (isset($data['id'])) {
            $this->db->where('id', $data['id']);
            $this->db->update('questions', $data);
            $message   = UPDATE_RECORD_CONSTANT . " On  questions id " . $data['id'];
            $action    = "Update";
            $record_id = $data['id'];
            $this->log($message, $record_id, $action);
            //======================Code End==============================

            $this->db->trans_complete(); # Completing transaction
            /*Optional*/

            if ($this->db->trans_status() === false) {
                # Something went wrong.
                $this->db->trans_rollback();
                return false;

            } else {
                //return $return_value;
            }
        } else {
            $this->db->insert('questions', $data);
            $id        = $this->db->insert_id();
            $message   = INSERT_RECORD_CONSTANT . " On  questions id " . $id;
            $action    = "Insert";
            $record_id = $id;
            $this->log($message, $record_id, $action);
            //======================Code End==============================

            $this->db->trans_complete(); # Completing transaction
            /*Optional*/

            if ($this->db->trans_status() === false) {
                # Something went wrong.
                $this->db->trans_rollback();
                return false;

            } else {
                //return $return_value;
            }
            return $id;
        }
    }

    public function get($id = null)
    {
        $userdata = $this->customlib->getUserData();
        $role_id  = $userdata["role_id"];

        $this->db->select('questions.*,subjects.name,classes.class as `class_name`,sections.section as `section_name`')->from('questions');

        $this->db->join('subjects', 'subjects.id = questions.subject_id');
        $this->db->join('classes', 'classes.id = questions.class_id', 'left');
        $this->db->join('sections', 'sections.id = questions.section_id', 'left');

        if ($id != null) {
            $this->db->where('questions.id', $id);
        } else {
            $this->db->order_by('questions.id');
        }

        $query = $this->db->get();
        if ($id != null) {
            return $query->row();
        } else {
            return $query->result();
        }

    }

    public function getall($limit = null, $offset = null)
    {
        $this->db->select('questions.*,subjects.name,classes.class as `class_name`,sections.section as `section_name`')->from('questions');
        $this->db->join('subjects', 'subjects.id = questions.subject_id');
        $this->db->join('classes', 'classes.id = questions.class_id', 'left');
        $this->db->join('sections', 'sections.id = questions.section_id', 'left');
        $this->db->limit($limit, $offset);
        $this->db->order_by('questions.id');
        $query = $this->db->get();
        return $query->result();
    }

    public function getAllRecord()
    {
        $userdata = $this->customlib->getUserData();
        $role_id  = $userdata["role_id"];

        if ($role_id == 2) {
            $my_section = array();
            if ($this->sch_setting_detail->class_teacher == 'yes' && $this->sch_setting_detail->my_question == '1') {
                $my_class = $this->class_model->get();

                foreach ($my_class as $class_key => $class_value) {
                    $my_class_id[] = $class_value['id'];
                }
                $this->datatables->where_in('questions.class_id', $my_class_id);

            } elseif ($this->sch_setting_detail->class_teacher == 'yes' && $this->sch_setting_detail->my_question == '0') {

                $my_class = $this->class_model->get();
                foreach ($my_class as $class_key => $class_value) {

                    $my_class_id[] = $class_value['id'];

                }

                $this->datatables->where_in('questions.class_id', $my_class_id);
                $this->datatables->where('questions.staff_id', $this->customlib->getStaffID());

            } elseif ($this->sch_setting_detail->class_teacher == 'no' && $this->sch_setting_detail->my_question == '1') {

                $this->datatables->where('questions.staff_id', $this->customlib->getStaffID());

            }

        }

        $this->datatables->select('questions.*,subjects.name,classes.class as `class_name`,sections.section as `section_name`');
        $this->datatables->join('subjects', 'subjects.id = questions.subject_id');
        $this->datatables->join('classes', 'classes.id = questions.class_id', 'left');
        $this->datatables->join('sections', 'sections.id = questions.section_id', 'left');

        $this->datatables->searchable('questions.id,subjects.name,questions.question_type,questions.level,questions.question,classes.class');
        $this->datatables->orderable('questions.id,subjects.name,questions.question_type,questions.level,questions.question,classes.class');
        $this->datatables->from('questions');
        return $this->datatables->generate('json');
    }

    public function remove($id)
    {
        $result = $this->deleteUnassigned(array((int) $id));
        return in_array((int) $id, $result['deleted_ids'], true);
    }

    public function image_add($id, $image)
    {

        $this->db->where('id', $id);
        $this->db->update('questions', $image);

    }

    public function bulkdelete($question_array)
    {
        return $this->deleteUnassigned((array) $question_array);
    }

    /**
     * Delete only unassigned source questions while holding the same source-row
     * locks used by assessment assignment. This closes the check/delete race.
     */
    public function deleteUnassigned(array $question_ids)
    {
        $question_ids = array_values(array_unique(array_filter(array_map('intval', $question_ids))));
        $result = array('deleted_ids' => array(), 'assigned_ids' => array(), 'missing_ids' => array());
        if (empty($question_ids)) {
            return $result;
        }

        $escaped_ids = array_map(array($this->db, 'escape'), $question_ids);
        $id_list = implode(',', $escaped_ids);
        $this->db->trans_begin();
        $locked_rows = $this->db->query(
            'SELECT `id` FROM `questions` WHERE `id` IN (' . $id_list . ') ORDER BY `id` FOR UPDATE'
        )->result_array();
        $existing_ids = array_map('intval', array_column($locked_rows, 'id'));
        $result['missing_ids'] = array_values(array_diff($question_ids, $existing_ids));

        if (!empty($existing_ids) && $this->db->table_exists('onlineexam_questions')) {
            $assignment_rows = $this->db->query(
                'SELECT DISTINCT `question_id` FROM `onlineexam_questions` WHERE `question_id` IN ('
                . implode(',', array_map(array($this->db, 'escape'), $existing_ids))
                . ') ORDER BY `question_id` FOR UPDATE'
            )->result_array();
            $result['assigned_ids'] = array_map('intval', array_column($assignment_rows, 'question_id'));
        }

        $result['deleted_ids'] = array_values(array_diff($existing_ids, $result['assigned_ids']));
        if (!empty($result['deleted_ids'])) {
            if ($this->db->table_exists('onlineexam_question_definitions')) {
                $this->db->where_in('question_id', $result['deleted_ids'])->delete('onlineexam_question_definitions');
            }
            $this->db->where_in('id', $result['deleted_ids'])->delete('questions');
            foreach ($result['deleted_ids'] as $question_id) {
                $this->log(DELETE_RECORD_CONSTANT . ' On questions id ' . $question_id, $question_id, 'Delete');
            }
        }

        if ($this->db->trans_status() === false) {
            $this->db->trans_rollback();
            return array('deleted_ids' => array(), 'assigned_ids' => $result['assigned_ids'], 'missing_ids' => $result['missing_ids']);
        }
        $this->db->trans_commit();
        return $result;
    }

    public function isAssignedToOnlineExam($question_id)
    {
        if (!$this->db->table_exists('onlineexam_questions')) {
            return false;
        }

        return $this->db->where('question_id', (int) $question_id)
            ->limit(1)
            ->count_all_results('onlineexam_questions') > 0;
    }

    public function getOnlineExamAssignedIds(array $question_ids)
    {
        $question_ids = array_values(array_unique(array_filter(array_map('intval', $question_ids))));
        if (empty($question_ids) || !$this->db->table_exists('onlineexam_questions')) {
            return array();
        }

        $rows = $this->db->select('question_id')
            ->where_in('question_id', $question_ids)
            ->group_by('question_id')
            ->get('onlineexam_questions')
            ->result_array();

        return array_map('intval', array_column($rows, 'question_id'));
    }

    /** Match the teacher visibility rules used by the Question Bank table. */
    public function canAccessQuestion($question_id)
    {
        $question = $this->db->select('id, class_id, section_id, staff_id')
            ->where('id', (int) $question_id)
            ->limit(1)
            ->get('questions')
            ->row_array();
        if (empty($question)) {
            return false;
        }

        $userdata = $this->customlib->getUserData();
        if (empty($userdata['role_id']) || (int) $userdata['role_id'] !== 2) {
            return true;
        }

        if ($this->sch_setting_detail->class_teacher === 'no') {
            return $this->sch_setting_detail->my_question !== '1'
                || (int) $question['staff_id'] === (int) $this->customlib->getStaffID();
        }
        if ($this->sch_setting_detail->my_question === '0'
            && (int) $question['staff_id'] !== (int) $this->customlib->getStaffID()) {
            return false;
        }
        return $this->canAccessQuestionScope((int) $question['class_id'], (int) $question['section_id']);
    }

    public function getInaccessibleQuestionIds(array $question_ids)
    {
        $question_ids = array_values(array_unique(array_filter(array_map('intval', $question_ids))));
        $inaccessible = array();
        foreach ($question_ids as $question_id) {
            if (!$this->canAccessQuestion($question_id)) {
                $inaccessible[] = $question_id;
            }
        }
        return $inaccessible;
    }

    /** Validate both the class/section relationship and a teacher's assigned arms. */
    public function canAccessQuestionScope($class_id, $section_id = 0)
    {
        $class_id = (int) $class_id;
        $section_id = (int) $section_id;
        if ($class_id < 1 || $this->db->where('id', $class_id)->count_all_results('classes') < 1) {
            return false;
        }
        if ($section_id > 0
            && $this->db->where('class_id', $class_id)->where('section_id', $section_id)->count_all_results('class_sections') < 1) {
            return false;
        }

        $userdata = $this->customlib->getUserData();
        if (empty($userdata['role_id']) || (int) $userdata['role_id'] !== 2
            || $this->sch_setting_detail->class_teacher !== 'yes') {
            return true;
        }
        $class_ids = array_map('intval', array_column($this->class_model->get(), 'id'));
        if (!in_array($class_id, $class_ids, true)) {
            return false;
        }
        if ($section_id < 1) {
            return true;
        }
        $sections = $this->teacher_model->get_teacherrestricted_modesections(
            $this->customlib->getStaffID(),
            $class_id
        );
        return in_array($section_id, array_map('intval', array_column($sections, 'section_id')), true);
    }

    public function add_option($data)
    {

        $this->db->trans_start(); # Starting Transaction
        $this->db->trans_strict(false); # See Note 01. If you wish can remove as well
        //=======================Code Start===========================

        if (isset($data['id'])) {
            $this->db->where('id', $data['id']);
            $this->db->update('question_options', $data);
            $message   = UPDATE_RECORD_CONSTANT . " On question_options id " . $data['id'];
            $action    = "Update";
            $record_id = $data['id'];
            $this->log($message, $record_id, $action);
            $return_value = $data['id'];
        } else {
            $this->db->insert('question_options', $data);
            $message   = INSERT_RECORD_CONSTANT . " On question_options id " . $this->db->insert_id();
            $action    = "Insert";
            $record_id = $this->db->insert_id();
            $this->log($message, $record_id, $action);
            $return_value = $this->db->insert_id();
        }

        //======================Code End==============================
        $this->db->trans_complete(); # Completing transaction
        /*Optional*/
        if ($this->db->trans_status() === false) {
            # Something went wrong.
            $this->db->trans_rollback();
            return false;
        } else {
            return $return_value;
        }
    }

    public function add_question_answers($data)
    {
        $this->db->trans_start(); # Starting Transaction
        $this->db->trans_strict(false); # See Note 01. If you wish can remove as well
        //=======================Code Start===========================

        if (isset($data['id'])) {
            $this->db->where('id', $data['id']);
            $this->db->update('question_answers', $data);
            $message   = UPDATE_RECORD_CONSTANT . " On question_answers id " . $data['id'];
            $action    = "Update";
            $record_id = $data['id'];
            $this->log($message, $record_id, $action);
            $return_value = $data['id'];
        } else {
            $this->db->insert('question_answers', $data);
            $message   = INSERT_RECORD_CONSTANT . " On question_answers id " . $this->db->insert_id();
            $action    = "Insert";
            $record_id = $this->db->insert_id();
            $this->log($message, $record_id, $action);
            $return_value = $this->db->insert_id();
        }

        //======================Code End==============================
        $this->db->trans_complete(); # Completing transaction
        /*Optional*/
        if ($this->db->trans_status() === false) {
            # Something went wrong.
            $this->db->trans_rollback();
            return false;
        } else {
            return $return_value;
        }
    }

    public function add_question_bulk($data)
    {

        $this->db->trans_start(); # Starting Transaction
        $this->db->trans_strict(false); # See Note 01. If you wish can remove as well
        //=======================Code Start===========================
        $this->db->insert_batch('questions', $data);
        $message   = 'Questions ' . IMPORT_RECORD_CONSTANT . " (" . count($data) . ")";
        $action    = "Import";
        $record_id = null;
        $this->log($message, $record_id, $action);
        //======================Code End==============================
        $this->db->trans_complete(); # Completing transaction
        /*Optional*/
        if ($this->db->trans_status() === false) {
            # Something went wrong.
            $this->db->trans_rollback();
            return false;
        } else {
            //return $return_value;
        }

    }

    public function get_result($id)
    {
        return $this->db->select('*')->from('questions')->join('question_answers', 'question.id=question_answers.question_id')->get()->row_array();

    }
    public function get_option($id)
    {
        return $this->db->select('id,option')->from('question_options')->where('question_id', $id)->get()->result_array();
    }

    public function get_answer($id)
    {
        return $this->db->select('option_id as answer_id')->from('question_answers')->where('question_id', $id)->get()->row_array();
    }

    public function count()
    {
        $query = $this->db->select('count(*) as total')->get('questions')->row_array();
        return $query['total'];
    }
}
