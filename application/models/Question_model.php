<?php

if (!defined('BASEPATH')) {
    exit('No direct script access allowed');
}

class Question_model extends MY_model
{
    public $sch_setting_detail;
    public $current_session;

    public function __construct()
    {
        parent::__construct();
        $this->load->model('academicaccess_model');
        $this->sch_setting_detail = $this->setting_model->getSetting();
        $this->current_session = (int) $this->setting_model->getCurrentSession();
    }
    public function add($data)
    {
        if (!empty($data['class_id']) && !empty($data['section_id'])
            && empty($data['class_section_id'])) {
            $data['class_section_id'] = $this->classSectionId($data['class_id'], $data['section_id']);
        }
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
            if ($this->db->field_exists('context_root_id', 'questions')
                && empty($data['context_root_id'])) {
                $this->db->where('id', $id)->update('questions', array('context_root_id' => $id));
            }
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

    public function getAllRecord(array $filters = array())
    {
        $this->datatables->where('questions.session_id', (int) $this->current_session);
        $this->datatables->where(
            $this->academicaccess_model->questionVisibilitySql('questions', $this->current_session),
            null,
            false,
            false
        );
        foreach (array('term', 'class_id', 'section_id', 'subject_id', 'question_type') as $field) {
            if (isset($filters[$field]) && $filters[$field] !== '' && $filters[$field] !== null) {
                $this->datatables->where('questions.' . $field, $filters[$field]);
            }
        }
        if (!empty($filters['keyword'])) {
            $keyword = $this->db->escape_like_str(trim((string) $filters['keyword']));
            $this->datatables->where(
                "(questions.question LIKE '%" . $keyword . "%' ESCAPE '!'"
                . " OR subjects.name LIKE '%" . $keyword . "%' ESCAPE '!')",
                null,
                false,
                false
            );
        }
        $this->datatables->select('questions.*,subjects.name,classes.class as `class_name`,sections.section as `section_name`');
        $this->datatables->join('subjects', 'subjects.id = questions.subject_id');
        $this->datatables->join('classes', 'classes.id = questions.class_id', 'left');
        $this->datatables->join('sections', 'sections.id = questions.section_id', 'left');

        $this->datatables->searchable('questions.id,questions.term,classes.class,sections.section,subjects.name,questions.question_type,questions.question');
        // Match checkbox, ID, term, class, arm, subject, type, question and action.
        $this->datatables->orderable('questions.id,questions.id,questions.term,classes.class,sections.section,subjects.name,questions.question_type,questions.question,questions.id');
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
    public function canAccessQuestion($question_id, $session_id = null, $mode = 'view')
    {
        $question = $this->db->select('id, session_id, term, class_id, section_id, subject_id, staff_id')
            ->where('id', (int) $question_id)
            ->limit(1)
            ->get('questions')
            ->row_array();
        if (empty($question)) {
            return false;
        }

        $session_id = $session_id === null ? $this->current_session : (int) $session_id;
        if ((int) $question['session_id'] !== (int) $session_id) {
            return false;
        }
        $capabilities = $this->academicaccess_model->questionCapabilities($question);
        return $mode === 'view' ? !empty($capabilities['view']) : !empty($capabilities['content']);
    }

    public function getInaccessibleQuestionIds(array $question_ids, $mode = 'view')
    {
        $question_ids = array_values(array_unique(array_filter(array_map('intval', $question_ids))));
        $inaccessible = array();
        foreach ($question_ids as $question_id) {
            if (!$this->canAccessQuestion($question_id, null, $mode)) {
                $inaccessible[] = $question_id;
            }
        }
        return $inaccessible;
    }

    /** Validate the class/arm and require a teacher's exact subject assignment. */
    public function canAccessQuestionScope($class_id, $section_id = 0, $subject_id = 0, $session_id = null, $mode = 'content')
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

        $session_id = $session_id === null ? (int) $this->current_session : (int) $session_id;
        return $mode === 'view'
            ? $this->academicaccess_model->canViewContext($session_id, $class_id, $section_id, $subject_id)
            : $this->academicaccess_model->canManageContent($session_id, $class_id, $section_id, $subject_id);
    }

    /** Classes and exact class-arm subjects available to a Question Bank user. */
    public function getQuestionBankAcademicChoices($class_id = 0, $section_id = 0, $session_id = null, $mode = 'view')
    {
        $session_id = $session_id === null ? (int) $this->current_session : (int) $session_id;
        if ($this->academicaccess_model->isAcademicAdministrator()) {
            $sections = array();
            $subjects = array();
            if ((int) $class_id > 0) {
                $rows = $this->db->distinct()
                    ->select('sections.id AS section_id, sections.section, subjects.id AS subject_id, subjects.name, subjects.code')
                    ->from('class_sections')
                    ->join('sections', 'sections.id=class_sections.section_id')
                    ->join('subject_group_class_sections sgcs', 'sgcs.class_section_id=class_sections.id')
                    ->join('subject_group_subjects sgs', 'sgs.subject_group_id=sgcs.subject_group_id')
                    ->join('subjects', 'subjects.id=sgs.subject_id')
                    ->where('class_sections.class_id', (int) $class_id)
                    ->where('sgcs.session_id', $session_id)
                    ->where('sgs.session_id', $session_id)
                    ->order_by('sections.section')->order_by('subjects.name')
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
            return array(
                'classes' => $this->class_model->get(),
                'sections' => array_values($sections),
                'subjects' => array_values($subjects),
            );
        }
        $teacher_id = (int) $this->customlib->getStaffID();
        $subject_classes = $this->db->distinct()->select('classes.id, classes.class')
            ->from('teacher_subjects')
            ->join('class_sections', 'class_sections.id = teacher_subjects.class_section_id')
            ->join('classes', 'classes.id = class_sections.class_id')
            ->where('teacher_subjects.teacher_id', $teacher_id)
            ->where('teacher_subjects.session_id', $session_id)
            ->order_by('classes.id')
            ->get()->result_array();
        $class_teacher_classes = array();
        if ($mode === 'view') {
            $class_teacher_classes = $this->db->distinct()->select('classes.id, classes.class')
                ->from('class_teacher')
                ->join('classes', 'classes.id = class_teacher.class_id')
                ->where('class_teacher.staff_id', $teacher_id)
                ->where('class_teacher.session_id', $session_id)
                ->order_by('classes.id')->get()->result_array();
        }
        $classes = array();
        foreach (array_merge($subject_classes, $class_teacher_classes) as $row) {
            $classes[(int) $row['id']] = $row;
        }

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
            if ($mode === 'view') {
                $class_sections = $this->db->distinct()->select('sections.id AS section_id, sections.section')
                    ->from('class_teacher')->join('sections', 'sections.id=class_teacher.section_id')
                    ->where('class_teacher.staff_id', $teacher_id)
                    ->where('class_teacher.session_id', $session_id)
                    ->where('class_teacher.class_id', (int) $class_id)
                    ->order_by('sections.section')->get()->result_array();
                foreach ($class_sections as $row) {
                    $sections[(int) $row['section_id']] = array(
                        'id' => (int) $row['section_id'],
                        'section_id' => (int) $row['section_id'],
                        'section' => $row['section'],
                    );
                }
                // A class teacher may filter/view every curriculum subject and
                // every preserved legacy-question subject in their own arms.
                // Authoring choices remain subject-assignment only.
                $class_teacher_sections = array_map('intval', array_column($class_sections, 'section_id'));
                if ((int) $section_id > 0) {
                    $class_teacher_sections = in_array((int) $section_id, $class_teacher_sections, true)
                        ? array((int) $section_id) : array();
                }
                if (!empty($class_teacher_sections)) {
                    $curriculum_subjects = $this->db->distinct()->select('subjects.id, subjects.name, subjects.code')
                        ->from('class_sections')
                        ->join('subject_group_class_sections sgcs', 'sgcs.class_section_id=class_sections.id')
                        ->join('subject_group_subjects sgs', 'sgs.subject_group_id=sgcs.subject_group_id')
                        ->join('subjects', 'subjects.id=sgs.subject_id')
                        ->where('class_sections.class_id', (int) $class_id)
                        ->where_in('class_sections.section_id', $class_teacher_sections)
                        ->where('sgcs.session_id', $session_id)
                        ->where('sgs.session_id', $session_id)
                        ->get()->result_array();
                    $visible_subjects = $this->db->distinct()->select('subjects.id, subjects.name, subjects.code')
                        ->from('questions')->join('subjects', 'subjects.id=questions.subject_id')
                        ->where('questions.session_id', $session_id)
                        ->where('questions.class_id', (int) $class_id)
                        ->group_start()->where_in('questions.section_id', $class_teacher_sections)
                        ->or_where('questions.section_id', 0)->group_end()
                        ->get()->result_array();
                    foreach (array_merge($curriculum_subjects, $visible_subjects) as $row) {
                        $subjects[(int) $row['id']] = $row;
                    }
                }
            }
        }
        ksort($classes);
        return array('classes' => array_values($classes), 'sections' => array_values($sections), 'subjects' => array_values($subjects));
    }

    /** Copy source content only; examination assignments and candidate work are never copied. */
    public function copyToContext(array $question_ids, array $target)
    {
        $question_ids = array_values(array_unique(array_filter(array_map('intval', $question_ids))));
        $target['session_id'] = (int) $target['session_id'];
        $target['class_id'] = (int) $target['class_id'];
        $target['section_id'] = (int) $target['section_id'];
        $target['subject_id'] = (int) $target['subject_id'];
        $target['term'] = strtolower(trim((string) $target['term']));
        if (empty($question_ids) || !in_array($target['term'], array('1st', '2nd', '3rd'), true)
            || !$this->canAccessQuestionScope($target['class_id'], $target['section_id'], $target['subject_id'], $target['session_id'], 'content')) {
            return array('success' => false, 'message' => 'The selected target is outside your subject assignment.', 'copied' => 0);
        }

        $this->db->trans_begin();
        $copied = 0;
        foreach ($question_ids as $question_id) {
            $source = $this->db->where('id', $question_id)->limit(1)->get('questions')->row_array();
            if (empty($source) || !$this->canAccessQuestion($question_id, (int) $source['session_id'], 'view')) {
                $this->db->trans_rollback();
                return array('success' => false, 'message' => 'One or more source questions are outside your permitted scope.', 'copied' => 0);
            }
            $source_id = (int) $source['id'];
            unset($source['id']);
            $source['staff_id'] = (int) $this->customlib->getStaffID();
            $source['session_id'] = $target['session_id'];
            $source['term'] = $target['term'];
            $source['class_id'] = $target['class_id'];
            $source['section_id'] = $target['section_id'];
            $source['subject_id'] = $target['subject_id'];
            $source['class_section_id'] = $this->classSectionId($target['class_id'], $target['section_id']);
            // An intentional staff copy is a new reusable source, not another
            // migration alias of the legacy row. Keep migration lineage only
            // for automatic multi-session backfill copies.
            $source['context_root_id'] = null;
            $source['created_at'] = date('Y-m-d H:i:s');
            $source['updated_at'] = null;
            $this->db->insert('questions', $source);
            $copy_id = (int) $this->db->insert_id();
            $this->db->where('id', $copy_id)->update('questions', array('context_root_id' => $copy_id));
            $this->cloneQuestionChildren($source_id, $copy_id);
            $this->log('Copy question ' . $source_id . ' to academic context as question ' . $copy_id, $copy_id, 'Insert');
            $copied++;
        }
        if ($this->db->trans_status() === false) {
            $this->db->trans_rollback();
            return array('success' => false, 'message' => 'The selected questions could not be copied.', 'copied' => 0);
        }
        $this->db->trans_commit();
        return array('success' => true, 'message' => $copied . ' question(s) copied into the current session.', 'copied' => $copied);
    }

    public function getCopyCandidates($session_id, array $filters = array(), $limit = 200)
    {
        $session_id = (int) $session_id;
        if ($session_id < 1) {
            return array();
        }
        $this->db->select('questions.id, questions.term, questions.question, questions.question_type,'
            . ' questions.class_id, questions.section_id, questions.subject_id,'
            . ' classes.class AS class_name, sections.section AS section_name, subjects.name AS subject_name')
            ->from('questions')
            ->join('classes', 'classes.id=questions.class_id', 'left')
            ->join('sections', 'sections.id=questions.section_id', 'left')
            ->join('subjects', 'subjects.id=questions.subject_id', 'left')
            ->where('questions.session_id', $session_id)
            ->where($this->academicaccess_model->questionVisibilitySql('questions', $session_id), null, false);
        foreach (array('term', 'class_id', 'section_id', 'subject_id', 'question_type') as $field) {
            if (!empty($filters[$field])) {
                $this->db->where('questions.' . $field, $filters[$field]);
            }
        }
        if (!empty($filters['keyword'])) {
            $this->db->group_start()->like('questions.question', trim((string) $filters['keyword']))
                ->or_like('subjects.name', trim((string) $filters['keyword']))->group_end();
        }
        return $this->db->order_by('classes.class')->order_by('sections.section')
            ->order_by('subjects.name')->order_by('questions.id')->limit((int) $limit)->get()->result_array();
    }

    private function cloneQuestionChildren($source_id, $copy_id)
    {
        if ($this->db->table_exists('onlineexam_question_definitions')) {
            $rows = $this->db->where('question_id', (int) $source_id)
                ->get('onlineexam_question_definitions')->result_array();
            foreach ($rows as $row) {
                unset($row['id']);
                $row['question_id'] = (int) $copy_id;
                $this->db->insert('onlineexam_question_definitions', $row);
            }
        }

        $option_map = array();
        if ($this->db->table_exists('question_options')) {
            $options = $this->db->where('question_id', (int) $source_id)
                ->order_by('id', 'ASC')->get('question_options')->result_array();
            foreach ($options as $option) {
                $old_option_id = (int) $option['id'];
                unset($option['id']);
                $option['question_id'] = (int) $copy_id;
                $this->db->insert('question_options', $option);
                $option_map[$old_option_id] = (int) $this->db->insert_id();
            }
        }

        if ($this->db->table_exists('question_answers')) {
            $answers = $this->db->where('question_id', (int) $source_id)
                ->order_by('id', 'ASC')->get('question_answers')->result_array();
            foreach ($answers as $answer) {
                $old_option_id = (int) $answer['option_id'];
                if (!isset($option_map[$old_option_id])) {
                    continue;
                }
                unset($answer['id']);
                $answer['question_id'] = (int) $copy_id;
                $answer['option_id'] = $option_map[$old_option_id];
                $this->db->insert('question_answers', $answer);
            }
        }
    }

    private function classSectionId($class_id, $section_id)
    {
        $row = $this->db->select('id')->where('class_id', (int) $class_id)
            ->where('section_id', (int) $section_id)->limit(1)->get('class_sections')->row_array();
        return empty($row['id']) ? null : (int) $row['id'];
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
        foreach ($data as &$row) {
            if (!empty($row['class_id']) && !empty($row['section_id']) && empty($row['class_section_id'])) {
                $row['class_section_id'] = $this->classSectionId($row['class_id'], $row['section_id']);
            }
        }
        unset($row);
        $this->db->trans_start(); # Starting Transaction
        $this->db->trans_strict(false); # See Note 01. If you wish can remove as well
        //=======================Code Start===========================
        $this->db->insert_batch('questions', $data);
        if ($this->db->field_exists('context_root_id', 'questions')) {
            $this->db->query('UPDATE `questions` SET `context_root_id`=`id` WHERE `context_root_id` IS NULL OR `context_root_id`=0');
        }
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
