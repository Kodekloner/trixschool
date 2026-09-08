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
        $this->current_session = (int) $this->setting_model->getCurrentSession();
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
            $teacher_id = (int) $this->customlib->getStaffID();
            $assignment_scope = "EXISTS (SELECT 1 FROM teacher_subjects qts "
                . "INNER JOIN class_sections qcs ON qcs.id = qts.class_section_id "
                . "WHERE qts.teacher_id = " . $teacher_id
                . " AND qts.session_id = " . (int) $this->current_session
                . " AND qts.subject_id = questions.subject_id"
                . " AND qcs.class_id = questions.class_id"
                . " AND (questions.section_id = 0 OR qcs.section_id = questions.section_id))";
            $this->datatables->where($assignment_scope, null, false, false);

            if ($this->sch_setting_detail->class_teacher == 'yes' && $this->sch_setting_detail->my_question == '0') {
                $this->datatables->where('questions.staff_id', $this->customlib->getStaffID());
            } elseif ($this->sch_setting_detail->class_teacher == 'no' && $this->sch_setting_detail->my_question == '1') {
                $this->datatables->where('questions.staff_id', $this->customlib->getStaffID());
            }
        }

        $this->datatables->select('questions.*,subjects.name,classes.class as `class_name`,sections.section as `section_name`');
        $this->datatables->join('subjects', 'subjects.id = questions.subject_id');
        $this->datatables->join('classes', 'classes.id = questions.class_id', 'left');
        $this->datatables->join('sections', 'sections.id = questions.section_id', 'left');

        $this->datatables->searchable('questions.id,subjects.name,questions.question_type,questions.question,classes.class');
        // Match the visible checkbox, ID, subject, type, question and action columns.
        $this->datatables->orderable('questions.id,questions.id,subjects.name,questions.question_type,questions.question,questions.id');
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
    public function canAccessQuestion($question_id, $session_id = null)
    {
        $question = $this->db->select('id, class_id, section_id, subject_id, staff_id')
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

        if (!$this->canAccessQuestionScope(
            (int) $question['class_id'],
            (int) $question['section_id'],
            (int) $question['subject_id'],
            $session_id
        )) {
            return false;
        }

        $own_questions_only = ($this->sch_setting_detail->class_teacher === 'yes'
                && $this->sch_setting_detail->my_question === '0')
            || ($this->sch_setting_detail->class_teacher === 'no'
                && $this->sch_setting_detail->my_question === '1');
        if ($own_questions_only
            && (int) $question['staff_id'] !== (int) $this->customlib->getStaffID()) {
            return false;
        }
        return true;
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

    /** Validate the class/arm and require a teacher's exact subject assignment. */
    public function canAccessQuestionScope($class_id, $section_id = 0, $subject_id = 0, $session_id = null)
    {
        $class_id = (int) $class_id;
        $section_id = (int) $section_id;
        $subject_id = (int) $subject_id;
        if ($class_id < 1 || $this->db->where('id', $class_id)->count_all_results('classes') < 1) {
            return false;
        }
        if ($section_id > 0
            && $this->db->where('class_id', $class_id)->where('section_id', $section_id)->count_all_results('class_sections') < 1) {
            return false;
        }
        if ($subject_id < 1 || $this->db->where('id', $subject_id)->count_all_results('subjects') < 1) {
            return false;
        }

        $userdata = $this->customlib->getUserData();
        if (empty($userdata['role_id']) || (int) $userdata['role_id'] !== 2) {
            return true;
        }
        $session_id = $session_id === null ? (int) $this->current_session : (int) $session_id;
        $query = $this->db->from('teacher_subjects')
            ->join('class_sections', 'class_sections.id = teacher_subjects.class_section_id')
            ->where('teacher_subjects.teacher_id', (int) $this->customlib->getStaffID())
            ->where('teacher_subjects.subject_id', $subject_id)
            ->where('teacher_subjects.session_id', $session_id)
            ->where('class_sections.class_id', $class_id);
        if ($section_id > 0) {
            $query->where('class_sections.section_id', $section_id);
        }
        return $query->select('teacher_subjects.id')->limit(1)->get()->num_rows() > 0;
    }

    /** Classes and exact class-arm subjects available to a Question Bank user. */
    public function getQuestionBankAcademicChoices($class_id = 0, $section_id = 0, $session_id = null)
    {
        $userdata = $this->customlib->getUserData();
        if (empty($userdata['role_id']) || (int) $userdata['role_id'] !== 2) {
            return array(
                'classes' => $this->class_model->get(),
                'sections' => $class_id > 0 ? $this->section_model->getClassBySection((int) $class_id) : array(),
                'subjects' => $this->subject_model->get(),
            );
        }

        $session_id = $session_id === null ? (int) $this->current_session : (int) $session_id;
        $teacher_id = (int) $this->customlib->getStaffID();
        $classes = $this->db->distinct()->select('classes.id, classes.class')
            ->from('teacher_subjects')
            ->join('class_sections', 'class_sections.id = teacher_subjects.class_section_id')
            ->join('classes', 'classes.id = class_sections.class_id')
            ->where('teacher_subjects.teacher_id', $teacher_id)
            ->where('teacher_subjects.session_id', $session_id)
            ->order_by('classes.id')
            ->get()->result_array();

        $sections = array();
        $subjects = array();
        if ((int) $class_id > 0) {
            $rows = $this->db->distinct()
                ->select('sections.id AS section_id, sections.section, subjects.id AS subject_id, subjects.name, subjects.code')
                ->from('teacher_subjects')
                ->join('class_sections', 'class_sections.id = teacher_subjects.class_section_id')
                ->join('sections', 'sections.id = class_sections.section_id')
                ->join('subjects', 'subjects.id = teacher_subjects.subject_id')
                ->where('teacher_subjects.teacher_id', $teacher_id)
                ->where('teacher_subjects.session_id', $session_id)
                ->where('class_sections.class_id', (int) $class_id)
                ->order_by('sections.section')
                ->order_by('subjects.name')
                ->get()->result_array();
            foreach ($rows as $row) {
                $sections[(int) $row['section_id']] = array(
                    'id' => (int) $row['section_id'],
                    'section_id' => (int) $row['section_id'],
                    'section' => $row['section'],
                );
                if ((int) $section_id < 1 || (int) $row['section_id'] === (int) $section_id) {
                    $subjects[(int) $row['subject_id']] = array(
                        'id' => (int) $row['subject_id'],
                        'name' => $row['name'],
                        'code' => $row['code'],
                    );
                }
            }
        }

        return array('classes' => $classes, 'sections' => array_values($sections), 'subjects' => array_values($subjects));
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
