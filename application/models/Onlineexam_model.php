<?php
class Onlineexam_model extends MY_model
{
    private $workflow_save_error = '';
    private $paper_save_error = '';

    public function __construct()
    {
        parent::__construct();
        $this->current_session = $this->setting_model->getCurrentSession();
    }
    public function add($data)
    {
        $this->db->trans_start(); # Starting Transaction
        $this->db->trans_strict(false); # See Note 01. If you wish can remove as well
        //=======================Code Start===========================
        if (isset($data['id'])) {
            $this->db->where('id', $data['id']);
            $this->db->update('onlineexam', $data);
            $message   = UPDATE_RECORD_CONSTANT . " On  online exam id " . $data['id'];
            $action    = "Update";
            $record_id = $id = $data['id'];
            $this->log($message, $record_id, $action);

        } else {
            $this->db->insert('onlineexam', $data);
            $id        = $this->db->insert_id();
            $message   = INSERT_RECORD_CONSTANT . " On  online exam id " . $id;
            $action    = "Insert";
            $record_id = $id;
            $this->log($message, $record_id, $action);

            // return $id;
        }

        //======================Code End==============================

        $this->db->trans_complete(); # Completing transaction
        /*Optional*/

        if ($this->db->trans_status() === false) {
            # Something went wrong.
            $this->db->trans_rollback();
            return false;

        } else {
            return $id;
        }
    }

    public function get($id = null, $publish = null)
    {
        if ($this->db->field_exists('deleted_at', 'onlineexam')) {
            $this->db->where('onlineexam.deleted_at', null);
        }
        $this->db->select('onlineexam.*,(select count(*) from onlineexam_questions where onlineexam_questions.onlineexam_id=onlineexam.id ) as `total_ques`, (select count(*) from onlineexam_questions INNER JOIN questions on questions.id=onlineexam_questions.question_id where onlineexam_questions.onlineexam_id=onlineexam.id and questions.question_type="descriptive" ) as `total_descriptive_ques`')->from('onlineexam');
        if ($id != null) {
            $this->db->where('onlineexam.id', $id);
            $this->db->where('onlineexam.session_id', $this->current_session);
        } else {
            $this->db->order_by('onlineexam.id', 'desc');
            $this->db->where('onlineexam.session_id', $this->current_session);
        }
        if ($publish != null) {
            $this->db->where('is_active', ($publish == "publish") ? 1 : 0);
        }
        $query = $this->db->get();
        if ($id != null) {
            return $query->row();
        } else {
            return $query->result();
        }
    }

    public function getexamlist($teacher_id = null)
    {
        if ($this->db->field_exists('deleted_at', 'onlineexam')) {
            $this->datatables->where('onlineexam.deleted_at IS NULL', null, false, false);
        }
       
         $this->datatables
            ->select('onlineexam.*,(select count(*) from onlineexam_questions where onlineexam_questions.onlineexam_id=onlineexam.id ) as `total_ques`, (select count(*) from onlineexam_questions INNER JOIN questions on questions.id=onlineexam_questions.question_id where onlineexam_questions.onlineexam_id=onlineexam.id and questions.question_type="descriptive" ) as `total_descriptive_ques`, (select classes.class from classes where classes.id=onlineexam.class_id) as class_name, (select subjects.name from subjects where subjects.id=onlineexam.subject_id) as subject_name, (select sessions.session from sessions where sessions.id=onlineexam.session_id) as session_name, (select GROUP_CONCAT(sections.section ORDER BY sections.section SEPARATOR ", ") from onlineexam_class_sections INNER JOIN sections on sections.id=onlineexam_class_sections.section_id where onlineexam_class_sections.onlineexam_id=onlineexam.id) as section_names, (select count(*) from onlineexam_papers where onlineexam_papers.onlineexam_id=onlineexam.id and onlineexam_papers.is_active=1) as total_papers')
            ->searchable('onlineexam.exam,onlineexam.purpose,onlineexam.exam_from,onlineexam.exam_to,onlineexam.duration,onlineexam.lifecycle_status,onlineexam.feedback_status')
             ->orderable('onlineexam.exam,onlineexam.purpose,total_ques,onlineexam.exam_from,onlineexam.exam_to,onlineexam.duration,onlineexam.lifecycle_status,onlineexam.feedback_status," "')
            ->sort('onlineexam.exam_from','desc')
            ->from('onlineexam');
        if ($teacher_id !== null) {
            $teacher_id = (int) $teacher_id;
            $condition = "(onlineexam.workflow_version = 2 AND "
                . "EXISTS (SELECT 1 FROM teacher_subjects ts INNER JOIN class_sections cs ON cs.id = ts.class_section_id "
                . "WHERE ts.teacher_id = " . $teacher_id . " AND ts.subject_id = onlineexam.subject_id "
                . "AND ts.session_id = onlineexam.session_id AND cs.class_id = onlineexam.class_id) "
                . "AND NOT EXISTS (SELECT 1 FROM onlineexam_class_sections ocs WHERE ocs.onlineexam_id = onlineexam.id "
                . "AND NOT EXISTS (SELECT 1 FROM teacher_subjects ts2 INNER JOIN class_sections cs2 ON cs2.id = ts2.class_section_id "
                . "WHERE ts2.teacher_id = " . $teacher_id . " AND ts2.subject_id = onlineexam.subject_id "
                . "AND ts2.session_id = onlineexam.session_id AND cs2.class_id = onlineexam.class_id "
                . "AND cs2.section_id = ocs.section_id)))";
            $this->datatables->where($condition, null, false, false);
        }
       return $this->datatables->generate('json');

    }

    public function insertExamQuestion($insert_data)
    {
        $this->db->trans_start(); # Starting Transaction
        $this->db->trans_strict(false); # See Note 01. If you wish can remove as well
        //=======================Code Start===========================
        $source_question = $this->db->query(
            'SELECT `id` FROM `questions` WHERE `id` = '
            . $this->db->escape((int) $insert_data['question_id'])
            . ' LIMIT 1 FOR UPDATE'
        )->row();
        if (!$source_question) {
            $this->db->trans_rollback();
            return false;
        }
        $this->db->where('question_id', $insert_data['question_id']);
        $this->db->where('onlineexam_id', $insert_data['onlineexam_id']);
        $q = $this->db->get('onlineexam_questions');

        if ($q->num_rows() > 0) {
            $result = $q->row();
            $this->db->where('id', $result->id);
            $this->db->delete('onlineexam_questions');
            $message   = DELETE_RECORD_CONSTANT . " On  onlineexam questions id " . $result->id;
            $action    = "Delete";
            $record_id = $result->id;
            $this->log($message, $record_id, $action);

        } else {
            $this->db->insert('onlineexam_questions', $insert_data);
            $id        = $this->db->insert_id();
            $message   = INSERT_RECORD_CONSTANT . " On  onlineexam questions id " . $id;
            $action    = "Insert";
            $record_id = $id;
            $this->log($message, $record_id, $action);

        }
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

    public function remove($id)
    {
        $this->db->trans_start(); # Starting Transaction
        $this->db->trans_strict(false); # See Note 01. If you wish can remove as well
        //=======================Code Start===========================
        $this->db->where('id', $id);
        $this->db->delete('onlineexam');
        $message   = DELETE_RECORD_CONSTANT . " On  online exam id " . $id;
        $action    = "Delete";
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
    }

    public function searchOnlineExamStudents($class_id, $section_id, $onlineexam_id, $session_id = null)
    {

        $this->db->select('classes.id AS `class_id`,student_session.id as student_session_id,students.id,classes.class,sections.id AS `section_id`,sections.section,students.id,students.admission_no , students.roll_no,students.admission_date,students.firstname,students.middlename,  students.lastname,students.image,    students.mobileno, students.email ,students.state ,   students.city , students.pincode ,     students.religion,     students.dob ,students.current_address,    students.permanent_address,IFNULL(students.category_id, 0) as `category_id`,IFNULL(categories.category, "") as `category`,students.adhar_no,students.samagra_id,students.bank_account_no,students.bank_name, students.ifsc_code , students.guardian_name , students.guardian_relation,students.guardian_phone,students.guardian_address,students.is_active ,students.created_at ,students.updated_at,students.father_name,students.rte,students.gender,IFNULL(onlineexam_students.id, 0) as onlineexam_student_id,IFNULL(onlineexam_students.student_session_id, 0) as onlineexam_student_session_id')->from('students');
        $this->db->join('student_session', 'student_session.student_id = students.id');
        $this->db->join('classes', 'student_session.class_id = classes.id');
        $this->db->join('sections', 'sections.id = student_session.section_id');
        $this->db->join('categories', 'students.category_id = categories.id', 'left');
        $this->db->join('onlineexam_students', 'onlineexam_students.student_session_id = student_session.id and onlineexam_students.onlineexam_id=' . $onlineexam_id, 'left');
        $this->db->where('student_session.session_id', $session_id === null ? $this->current_session : (int) $session_id);
        $this->db->where('student_session.class_id', $class_id);
          $this->db->where('students.is_active', 'yes');
        if ($section_id != "") {
            $this->db->where('student_session.section_id', $section_id);
        }
        $this->db->order_by('students.id');

        $query = $this->db->get();
        return $query->result_array();

    }

    public function searchAllOnlineExamStudents($onlineexam_id, $class_id = null, $section_id = null)
    {
        $this->db->select('class_sections.id as class_section_id,classes.id AS `class_id`,student_session.id as student_session_id,students.id,classes.class,sections.id AS `section_id`,sections.section,students.id,students.admission_no , students.roll_no,students.admission_date,students.firstname,students.middlename,  students.lastname,students.image,    students.mobileno, students.email ,students.state ,   students.city , students.pincode ,     students.religion,     students.dob ,students.current_address,    students.permanent_address,IFNULL(students.category_id, 0) as `category_id`,IFNULL(categories.category, "") as `category`,students.adhar_no,students.samagra_id,students.bank_account_no,students.bank_name, students.ifsc_code , students.guardian_name , students.guardian_relation,students.guardian_phone,students.guardian_address,students.is_active ,students.created_at ,students.updated_at,students.father_name,students.rte,students.gender,IFNULL(onlineexam_students.id, 0) as onlineexam_student_id,IFNULL(onlineexam_students.student_session_id, 0) as onlineexam_student_session_id,IFNULL(onlineexam_students.rank, 0) as rank,onlineexam_students.is_attempted')->from('students');
        $this->db->join('student_session', 'student_session.student_id = students.id');
        $this->db->join('classes', 'student_session.class_id = classes.id');
        $this->db->join('sections', 'sections.id = student_session.section_id');
        $this->db->join('class_sections', 'class_sections.class_id = classes.id and class_sections.section_id = sections.id');
        $this->db->join('categories', 'students.category_id = categories.id', 'left');
        $this->db->join('onlineexam_students', 'onlineexam_students.student_session_id = student_session.id and onlineexam_students.onlineexam_id=' . $onlineexam_id);
        $this->db->where('student_session.session_id', $this->current_session);
         $this->db->where('students.is_active', 'yes');
        if ($class_id != null) {
            $this->db->where('student_session.class_id', $class_id);
        }
        if ($section_id != null) {
            $this->db->where('student_session.section_id', $section_id);
        }
        $this->db->order_by('onlineexam_students.rank', 'ASC');
        $this->db->order_by('onlineexam_students.is_attempted', 'DESC');
        $query = $this->db->get();
        return $query->result_array();

    }

    public function addStudents($data_insert, $data_delete, $onlineexam_id)
    {

        $this->db->trans_begin();

        if (!empty($data_insert)) {

            $this->db->insert_batch('onlineexam_students', $data_insert);
        }
        if (!empty($data_delete)) {

            $this->db->where('onlineexam_id', $onlineexam_id);
            $this->db->where_in('student_session_id', $data_delete);
            $this->db->delete('onlineexam_students');
        }

        if ($this->db->trans_status() === false) {
            $this->db->trans_rollback();
            return false;
        } else {
            $this->db->trans_commit();
            return true;
        }
    }

    public function updateStudentRank($onlineexam_students, $exam_id)
    {
        $this->db->trans_begin();
        $this->db->where('id', $exam_id);
        $this->db->update('onlineexam', array('is_rank_generated' => 1));
        $this->db->update_batch('onlineexam_students', $onlineexam_students, 'id');

        if ($this->db->trans_status() === false) {
            $this->db->trans_rollback();
            return false;
        } else {
            $this->db->trans_commit();
            return true;
        }

    }

    public function getStudentAttemts($onlineexam_student_id)
    {

        $this->db->where('onlineexam_student_id', $onlineexam_student_id);
        $total_rows = $this->db->count_all_results('onlineexam_attempts');
        return $total_rows;
    }
    public function addStudentAttemts($data)
    {
        $this->db->insert('onlineexam_attempts', $data);
        return $this->db->insert_id();
    }

    public function examstudentsID($student_session_id, $onlineexam_id)
    {
        $this->db->from('onlineexam_students');
        $this->db->where('student_session_id', $student_session_id);
        $this->db->where('onlineexam_id', $onlineexam_id);
        $query = $this->db->get();
        return $query->row();
    }

    public function updateExamResult($onlineexam_student_id)
    {
        $this->db->where('id', $onlineexam_student_id);
        $this->db->update('onlineexam_students', array('is_attempted' => 1));
    }

    public function getStudentexam($student_session_id)
    {
        $not_deleted = $this->db->field_exists('deleted_at', 'onlineexam') ? ' AND onlineexam.deleted_at IS NULL ' : '';
        $query = "SELECT onlineexam.*, onlineexam_students.id AS onlineexam_student_id,
            CASE WHEN onlineexam.workflow_version >= 2
                THEN (SELECT COUNT(*) FROM onlineexam_candidate_attempts WHERE onlineexam_candidate_attempts.onlineexam_student_id = onlineexam_students.id AND onlineexam_candidate_attempts.status != 'voided')
                ELSE (SELECT COUNT(*) FROM onlineexam_attempts WHERE onlineexam_attempts.onlineexam_student_id = onlineexam_students.id)
            END AS counter,
            (SELECT onlineexam_candidate_attempts.status FROM onlineexam_candidate_attempts WHERE onlineexam_candidate_attempts.onlineexam_student_id = onlineexam_students.id AND onlineexam_candidate_attempts.status != 'voided' ORDER BY onlineexam_candidate_attempts.attempt_no DESC LIMIT 1) AS candidate_attempt_status
            FROM onlineexam
            INNER JOIN onlineexam_students ON onlineexam_students.onlineexam_id = onlineexam.id
            WHERE onlineexam_students.student_session_id=" . $this->db->escape($student_session_id) . "
              AND onlineexam.is_active=1
              AND onlineexam.workflow_version >= 2
              AND onlineexam_students.candidate_status='assigned'
              AND (
                    (onlineexam.purpose IN ('ca','midterm') AND onlineexam.result_adapter='standard_component')
                    OR (onlineexam.purpose='holiday' AND onlineexam.result_adapter='holiday_assessment')
                    OR (onlineexam.purpose='kindergarten' AND onlineexam.result_adapter='kindergarten_concept')
              )
            " . $not_deleted . " ORDER BY onlineexam.exam_from DESC";

        $query = $this->db->query($query);
        $rows = $query->result();
        $this->load->model('onlineexamattempt_model');
        return array_values(array_filter($rows, function ($exam) {
            return $this->onlineexamattempt_model->isSupportedAssessmentContext($exam);
        }));

    }

    public function getExamQuestions($id = null, $random_type = false)
    {
        $this->db->select('onlineexam_questions.*,questions.subject_id,questions.question,questions.opt_a,questions.opt_b,questions.opt_c,questions.opt_d,questions.opt_e,questions.correct,questions.question_type,questions.class_id,questions.section_id')->from('onlineexam_questions');
        $this->db->join('questions', 'questions.id = onlineexam_questions.question_id');
        $this->db->where('onlineexam_questions.onlineexam_id', $id);
        if ($random_type) {
            $this->db->order_by('rand()');
        } else {
            $this->db->order_by('onlineexam_questions.id', 'DESC');
        }
        $query = $this->db->get();
        return $query->result();
    }
    public function onlineexamReport($condition)
    {
        $query = "SELECT onlineexam.*,(select count(*) from onlineexam_students WHERE onlineexam_students.onlineexam_id = onlineexam.id) as assign,(select count(*) from onlineexam_questions where onlineexam_questions.onlineexam_id=onlineexam.id) as questions FROM `onlineexam`  where " . $condition . " ";

        $this->datatables->query($query)
        ->searchable('onlineexam.exam,onlineexam.attempt,onlineexam.exam_from,onlineexam.exam_to,onlineexam.duration')
        ->orderable('onlineexam.exam,onlineexam.attempt,onlineexam.exam_from,onlineexam.exam_to,onlineexam.duration,null,null,null') 
        ->query_where_enable(TRUE)
        ->sort('onlineexam.id','asc') ;
        return $this->datatables->generate('json');
    }

    public function onlineexamatteptreport($condition)
    {
        $query = "SELECT student_session.id,students.admission_no,students.id as sid, CONCAT_WS(' ',firstname,middlename,lastname) as name,firstname,middlename,lastname,GROUP_CONCAT(onlineexam.id,'@',onlineexam.exam,'@',onlineexam.attempt,'@',onlineexam.exam_from,'@',onlineexam.exam_to,'@',onlineexam.duration,'@',onlineexam.passing_percentage,'@',onlineexam.is_active,'@',onlineexam.publish_result) as exams,GROUP_CONCAT(onlineexam_students.onlineexam_id) as attempt,`classes`.`id` AS `class_id`, `student_session`.`id` as `student_session_id`, `students`.`id`, `classes`.`class`, `sections`.`id` AS `section_id`, `sections`.`section`, `students`.`id`, `students`.`admission_no` FROM `student_session` INNER JOIN onlineexam_students on onlineexam_students.student_session_id=student_session.id INNER JOIN students on students.id=student_session.student_id JOIN `classes` ON `student_session`.`class_id` = `classes`.`id` JOIN `sections` ON `sections`.`id` = `student_session`.`section_id` LEFT JOIN `categories` ON `students`.`category_id` = `categories`.`id` INNER JOIN onlineexam on onlineexam_students.onlineexam_id=onlineexam.id WHERE  student_session.session_id=" . $this->db->escape($this->current_session) . " and students.is_active='yes' " . $condition . " group by students.id";

        $this->datatables->query($query)
        ->searchable('students.firstname,students.admission_no,classes.class,sections.section')
        ->orderable('students.firstname,students.admission_no,classes.class,sections.section,null,null,null,null,null') 
        ->query_where_enable(TRUE);
        return $this->datatables->generate('json');  
    }


    public function getstudentByexam_id($id){
        $this->db->select('students.*,classes.class,sections.section')->from('onlineexam_students')->join('student_session','student_session.id=onlineexam_students.student_session_id')->join('students','students.id=student_session.student_id');
         $this->db->join('classes', 'student_session.class_id = classes.id');
        $this->db->join('sections', 'sections.id = student_session.section_id');
        $this->db->where('onlineexam_id', $id);
        $query = $this->db->get();
        return $query->result_array();
    }

    public function get_msnstatusByexam_id($id){
         return $this->db->select('onlineexam.publish_exam_notification,onlineexam.publish_result_notification')->where('onlineexam.id',$id)->get('onlineexam')->row_array();

    }

    public function syncWorkflowCandidates($onlineexam_id, array $eligible_student_sessions, array $selected_student_sessions, $actor_id)
    {
        $onlineexam_id = (int) $onlineexam_id;
        $eligible_student_sessions = array_values(array_unique(array_map('intval', $eligible_student_sessions)));
        $selected_student_sessions = array_values(array_unique(array_map('intval', $selected_student_sessions)));
        $now = date('Y-m-d H:i:s');
        $this->db->trans_begin();

        // Serialize roster saves for the assessment. This also prevents two
        // concurrent requests from both observing a missing assignment and
        // inserting duplicate candidate rows in the legacy table.
        $locked_exam = $this->db->query(
            'SELECT * FROM `onlineexam` WHERE `id` = '
            . $this->db->escape($onlineexam_id) . ' LIMIT 1 FOR UPDATE'
        )->row();
        if (!$locked_exam || !empty($locked_exam->deleted_at)) {
            $this->db->trans_rollback();
            return array('success' => false, 'message' => 'The assessment no longer exists.');
        }

        $existing = array();
        if (!empty($eligible_student_sessions)) {
            foreach ($this->db->where('onlineexam_id', $onlineexam_id)->where_in('student_session_id', $eligible_student_sessions)->get('onlineexam_students')->result_array() as $row) {
                $existing[(int) $row['student_session_id']] = $row;
            }
        }

        foreach ($eligible_student_sessions as $student_session_id) {
            $selected = in_array($student_session_id, $selected_student_sessions, true);
            if (isset($existing[$student_session_id])) {
                $assignment = $this->db->query(
                    'SELECT * FROM `onlineexam_students` WHERE `id` = '
                    . $this->db->escape((int) $existing[$student_session_id]['id'])
                    . ' AND `onlineexam_id` = ' . $this->db->escape($onlineexam_id)
                    . ' LIMIT 1 FOR UPDATE'
                )->row_array();
                if (empty($assignment)) {
                    $this->db->trans_rollback();
                    return array('success' => false, 'message' => 'A candidate roster record changed while the roster was being saved. Reload and try again.');
                }
                if (!$selected) {
                    $has_attempt = $this->db->where('onlineexam_student_id', (int) $assignment['id'])
                        ->where('status !=', 'voided')
                        ->count_all_results('onlineexam_candidate_attempts') > 0;
                    if ($has_attempt) {
                        $this->db->trans_rollback();
                        return array('success' => false, 'message' => 'A candidate who has started an official attempt cannot be excluded. Void the attempt with an incident reason first.');
                    }
                }
                $this->db->where('id', (int) $assignment['id'])->update('onlineexam_students', $selected ? array(
                    'candidate_status' => 'assigned',
                    'assigned_at' => $now,
                    'excluded_at' => null,
                    'excluded_by' => null,
                    'exclusion_reason' => null,
                ) : array(
                    'candidate_status' => 'excluded',
                    'excluded_at' => $now,
                    'excluded_by' => (int) $actor_id,
                    'exclusion_reason' => 'Removed from the assessment roster',
                ));
            } elseif ($selected) {
                $this->db->insert('onlineexam_students', array(
                    'onlineexam_id' => $onlineexam_id,
                    'student_session_id' => $student_session_id,
                    'candidate_status' => 'assigned',
                    'assigned_at' => $now,
                ));
            }
        }

        if ($this->db->trans_status() === false) {
            $this->db->trans_rollback();
            return array('success' => false, 'message' => 'The candidate roster could not be saved.');
        }
        $this->db->trans_commit();
        return array('success' => true, 'message' => 'Candidate roster saved.');
    }

    /**
     * Return a workflow-v2 examination with its academic display values.
     * This deliberately does not use get(), whose current-session filter is
     * retained for backwards compatibility with legacy examinations.
     */
    public function getWorkflow($id)
    {
        if ($this->db->field_exists('deleted_at', 'onlineexam')) {
            $this->db->where('onlineexam.deleted_at', null);
        }
        $this->db->select('onlineexam.*, classes.class as class_name, subjects.name as subject_name, sessions.session as session_name');
        $this->db->from('onlineexam');
        $this->db->join('classes', 'classes.id=onlineexam.class_id', 'left');
        $this->db->join('subjects', 'subjects.id=onlineexam.subject_id', 'left');
        $this->db->join('sessions', 'sessions.id=onlineexam.session_id', 'left');
        $this->db->where('onlineexam.id', (int) $id);
        $this->db->where('onlineexam.workflow_version', 2);
        $exam = $this->db->get()->row();

        if ($exam) {
            $exam->section_ids   = $this->getWorkflowSectionIds($exam->id);
            $exam->section_names = $this->getWorkflowSectionNames($exam->id);
        }

        return $exam;
    }

    public function getWorkflowSectionIds($onlineexam_id)
    {
        $rows = $this->db->select('section_id')
            ->where('onlineexam_id', (int) $onlineexam_id)
            ->order_by('section_id')
            ->get('onlineexam_class_sections')
            ->result_array();

        return array_map(function ($row) {
            return (int) $row['section_id'];
        }, $rows);
    }

    public function getWorkflowSectionNames($onlineexam_id)
    {
        $rows = $this->db->select('sections.section')
            ->from('onlineexam_class_sections')
            ->join('sections', 'sections.id=onlineexam_class_sections.section_id')
            ->where('onlineexam_class_sections.onlineexam_id', (int) $onlineexam_id)
            ->order_by('sections.section')
            ->get()->result_array();

        return array_column($rows, 'section');
    }

    public function getClassSectionsForWorkflow($class_id)
    {
        return $this->db->select('sections.id, sections.section')
            ->from('class_sections')
            ->join('sections', 'sections.id=class_sections.section_id')
            ->where('class_sections.class_id', (int) $class_id)
            ->order_by('sections.section')
            ->get()->result_array();
    }

    /** Classes in which a teacher has at least one exact subject assignment. */
    public function getWorkflowClassChoices($session_id, $teacher_id = null)
    {
        $query = $this->db->distinct()->select('classes.id, classes.class')
            ->from('classes')
            ->join('class_sections', 'class_sections.class_id = classes.id');
        if ($teacher_id !== null) {
            $query->join('teacher_subjects ts', 'ts.class_section_id = class_sections.id')
                ->where('ts.teacher_id', (int) $teacher_id)
                ->where('ts.session_id', (int) $session_id);
        }
        $rows = $query->order_by('classes.id')->get()->result_array();
        foreach ($rows as $key => $row) {
            $rows[$key]['id'] = (int) $row['id'];
        }
        return $rows;
    }

    /** Curriculum assignments are session-wide: these tables have no term column. */
    public function getWorkflowAcademicChoices($class_id, $session_id, $term, $subject_id = 0, $teacher_id = null)
    {
        if ((int) $class_id < 1 || (int) $session_id < 1 || !in_array($term, array('1st', '2nd', '3rd'), true)) {
            return array('subjects' => array(), 'sections' => array());
        }
        $this->db->distinct()->select('subjects.id, subjects.name, subjects.code, sections.id AS section_id, sections.section')
            ->from('class_sections')
            ->join('sections', 'sections.id=class_sections.section_id')
            ->join('subject_group_class_sections sgcs', 'sgcs.class_section_id=class_sections.id')
            ->join('subject_group_subjects sgs', 'sgs.subject_group_id=sgcs.subject_group_id')
            ->join('subjects', 'subjects.id=sgs.subject_id')
            ->where('class_sections.class_id', (int) $class_id)
            ->where('sgcs.session_id', (int) $session_id)
            ->where('sgs.session_id', (int) $session_id);
        if ($teacher_id !== null) {
            $this->db->join('teacher_subjects ts', 'ts.class_section_id=class_sections.id AND ts.subject_id=subjects.id')
                ->where('ts.teacher_id', (int) $teacher_id)->where('ts.session_id', (int) $session_id);
        }
        $rows = $this->db->order_by('subjects.name')->order_by('sections.section')->get()->result_array();
        $subjects = array();
        $sections = array();
        foreach ($rows as $row) {
            $subjects[(int) $row['id']] = array('id' => (int) $row['id'], 'name' => $row['name'], 'code' => $row['code']);
            if ((int) $row['id'] === (int) $subject_id) {
                $sections[(int) $row['section_id']] = array('id' => (int) $row['section_id'], 'section' => $row['section']);
            }
        }
        return array('subjects' => array_values($subjects), 'sections' => array_values($sections));
    }

    /**
     * Confirm that a curriculum subject belongs to every selected class arm
     * in the chosen session, not merely that the global subjects row exists.
     */
    public function subjectIsAssignedToAcademicScope($class_id, $section_ids, $subject_id, $session_id)
    {
        foreach (array('class_sections', 'subject_group_class_sections', 'subject_group_subjects') as $table) {
            if (!$this->db->table_exists($table)) {
                return false;
            }
        }
        $class_id = (int) $class_id;
        $subject_id = (int) $subject_id;
        $session_id = (int) $session_id;
        $section_ids = array_values(array_unique(array_filter(array_map('intval', (array) $section_ids))));
        if ($class_id < 1 || $subject_id < 1 || $session_id < 1 || empty($section_ids)) {
            return false;
        }

        $rows = $this->db->select('class_sections.section_id')
            ->distinct()
            ->from('class_sections')
            ->join('subject_group_class_sections sgcs', 'sgcs.class_section_id = class_sections.id')
            ->join('subject_group_subjects sgs', 'sgs.subject_group_id = sgcs.subject_group_id')
            ->where('class_sections.class_id', $class_id)
            ->where_in('class_sections.section_id', $section_ids)
            ->where('sgcs.session_id', $session_id)
            ->where('sgs.session_id', $session_id)
            ->where('sgs.subject_id', $subject_id)
            ->get()->result_array();
        $assigned = array_values(array_unique(array_map(function ($row) {
            return (int) $row['section_id'];
        }, $rows)));
        sort($assigned);
        sort($section_ids);
        return $assigned === $section_ids;
    }

    /**
     * Resolve the existing academic result configuration for a class. The
     * legacy tables remain authoritative; this method only describes valid
     * destinations for the Online Examination UI.
     */
    public function getAcademicConfiguration($class_id, $subject_id = null, $purpose = null, $session_id = null, $term = null, $section_ids = array())
    {
        $class_id = (int) $class_id;
        $subject_id = (int) $subject_id;
        $purpose = strtolower(trim((string) $purpose));
        $config = array(
            'adapter'       => null,
            'result_type'   => null,
            'components'    => array(),
            'exam_maximum'  => null,
            'target_maximum' => null,
            'valid'         => false,
            'message'       => '',
            'kindergarten'  => array(),
            'holiday_mappings' => array(),
        );

        $kindergarten = array();
        if ($this->db->table_exists('kindergarten_assignment') && $this->db->table_exists('kindergarten_assessment_header')) {
            $kindergarten = $this->db->select('kindergarten_assessment_header.id, kindergarten_assessment_header.assessment_name, kindergarten_assessment_header.result_labels_json')
                ->from('kindergarten_assignment')
                ->join('kindergarten_assessment_header', 'kindergarten_assessment_header.id=kindergarten_assignment.assessment_id')
                ->where('kindergarten_assignment.class_id', $class_id)
                ->get()->result_array();
        }

        if ($purpose === 'kindergarten') {
            $config['adapter']      = 'kindergarten_concept';
            $config['result_type']  = 'termly';
            $config['kindergarten'] = $kindergarten;
            $config['target_maximum'] = 100.0;
            $mapping_options = empty($kindergarten) || $subject_id < 1
                ? array()
                : $this->getKindergartenMappingOptions($class_id, $subject_id);
            $config['valid'] = !empty($mapping_options);
            $config['message'] = $config['valid']
                ? 'Kindergarten results use configured concept mappings; map each paper or section before publishing.'
                : 'This class and subject do not have an active Kindergarten assessment concept configuration.';
            return $config;
        }

        if ($purpose === 'holiday') {
            return $this->getHolidayAcademicConfiguration($class_id, $subject_id, $session_id, $term, $section_ids);
        }

        // Existing frozen British/Kindergarten records still need their old
        // configuration to remain viewable. New compact assessments must choose
        // their explicit purpose and are validated below.
        if ($purpose === '' && !empty($kindergarten)) {
            $config['adapter']      = 'kindergarten_concept';
            $config['result_type']  = 'termly';
            $config['kindergarten'] = $kindergarten;
            $config['target_maximum'] = 100.0;
            $config['valid'] = true;
            $config['message'] = 'This class uses Kindergarten concept outcomes.';
            return $config;
        }

        $row = $this->db->select('assigncatoclass.ResultType, resultsetting.*')
            ->from('assigncatoclass')
            ->join('resultsetting', 'resultsetting.ResultSettingID=assigncatoclass.ResultSettingID', 'left')
            ->where('assigncatoclass.ClassID', $class_id)
            ->get()->row_array();

        if (empty($row)) {
            $config['message'] = !empty($kindergarten)
                ? 'This class uses Kindergarten outcomes. Choose the Kindergarten purpose.'
                : 'No CA/result setting is assigned to this class.';
            return $config;
        }

        if (strtolower($row['ResultType']) === 'british') {
            $config['adapter']      = 'british_outcome';
            $config['result_type']  = 'termly';
            $config['target_maximum'] = 100.0;
            $config['components'][] = array('value' => 'outcome', 'label' => 'British qualitative outcome', 'maximum' => 100);
            $config['valid'] = $purpose === '';
            $config['message'] = $config['valid']
                ? 'British results require a configured outcome profile and final teacher outcome.'
                : 'British outcome classes are preserved, but the compact CA/Midterm workflow cannot safely write a numeric CA component for this class.';
            return $config;
        }

        $config['adapter']     = 'standard_component';
        $config['result_type'] = $purpose === 'midterm' ? 'midterm' : 'termly';
        $number_of_ca          = min(10, max(0, (int) $row['NumberOfCA']));
        $ca_total              = 0.0;
        $component_rows        = array();
        $errors                = array();

        for ($index = 1; $index <= $number_of_ca; $index++) {
            $title_key = 'CA' . $index . 'Title';
            $score_key = 'CA' . $index . 'Score';
            $maximum   = isset($row[$score_key]) ? (float) $row[$score_key] : 0;
            $title     = isset($row[$title_key]) && trim($row[$title_key]) !== '' ? $row[$title_key] : 'CA ' . $index;

            if ($maximum < 0) {
                $errors[] = $score_key . ' cannot be negative.';
                continue;
            }
            if ($maximum > 0) {
                $ca_total += $maximum;
                $component_rows[] = array(
                    'value'   => 'ca' . $index,
                    'label'   => $title,
                    'maximum' => round($maximum, 2),
                );
            }
        }

        $exam_maximum = round(100 - $ca_total, 2);
        $config['exam_maximum'] = $exam_maximum;
        if ($exam_maximum < 0) {
            $errors[] = 'The configured CA maximums exceed 100.';
        }

        if (in_array($purpose, array('ca', 'midterm'), true)) {
            $this->load->library('onlineexam_scoring');
            $normalized = $this->onlineexam_scoring->normalizeMidtermSlots($row['MidTermCaToUse'], $number_of_ca);
            if (!empty($normalized['invalid'])) {
                $errors[] = 'MidTermCaToUse contains an invalid CA slot.';
            }
            $config['components'] = $this->onlineexam_scoring->filterStandardComponents(
                $component_rows,
                $purpose,
                $row['MidTermCaToUse'],
                $number_of_ca
            );
            if (empty($config['components'])) {
                $errors[] = $purpose === 'midterm'
                    ? 'No positive-score CA slot is configured for Midterm.'
                    : 'No positive-score Continuous Assessment slot remains after the Midterm slots are reserved.';
            }
        } else {
            // Historical callers may still display the original broad list;
            // new saves never use this compatibility branch.
            $config['components'] = $component_rows;
            if ($exam_maximum > 0) {
                $config['components'][] = array(
                'value'   => 'exam',
                'label'   => 'Examination',
                'maximum' => $exam_maximum,
            );
            }
        }

        $config['valid'] = empty($errors) && !empty($config['components']);
        $config['message'] = empty($errors)
            ? ($purpose === 'midterm'
                ? 'Only CA slots reserved by the class Midterm setting are available.'
                : ($purpose === 'ca' ? 'Midterm-reserved CA slots are excluded.' : 'Standard result components loaded.'))
            : implode(' ', array_values(array_unique($errors)));

        return $config;
    }

    /**
     * Resolve one immutable Holiday destination for every selected class arm.
     * A single online assessment has one target maximum, so all selected arms
     * must use the same configured maximum for this subject.
     */
    public function getHolidayAcademicConfiguration($class_id, $subject_id, $session_id, $term, $section_ids)
    {
        $config = array(
            'adapter' => 'holiday_assessment',
            'result_type' => 'holiday',
            'components' => array(),
            'exam_maximum' => null,
            'target_maximum' => null,
            'valid' => false,
            'message' => '',
            'kindergarten' => array(),
            'holiday_mappings' => array(),
        );
        if (!$this->db->table_exists('holiday_assessment_settings')
            || !$this->db->table_exists('holiday_assessment_subjects')) {
            $config['message'] = 'Holiday Assessment tables are not installed for this school.';
            return $config;
        }

        $class_id = (int) $class_id;
        $subject_id = (int) $subject_id;
        $session_id = (int) $session_id;
        $term = strtolower(trim((string) $term));
        $section_ids = array_values(array_unique(array_filter(array_map('intval', (array) $section_ids))));
        if ($class_id < 1 || $subject_id < 1 || $session_id < 1
            || !in_array($term, array('1st', '2nd', '3rd'), true) || empty($section_ids)) {
            $config['message'] = 'Select session, term, class, class arm and subject to load the Holiday Assessment setting.';
            return $config;
        }

        $maximum = null;
        foreach ($section_ids as $section_id) {
            $rows = $this->db->select('holiday_assessment_settings.id AS setting_id, holiday_assessment_subjects.id AS setting_subject_id, holiday_assessment_subjects.max_score')
                ->from('holiday_assessment_settings')
                ->join('holiday_assessment_subjects', 'holiday_assessment_subjects.setting_id=holiday_assessment_settings.id')
                ->where('holiday_assessment_settings.class_id', $class_id)
                ->where('holiday_assessment_settings.section_id', $section_id)
                ->where('holiday_assessment_settings.session_id', $session_id)
                ->where('holiday_assessment_settings.term', $term)
                ->where('holiday_assessment_settings.enabled', 1)
                ->where('holiday_assessment_subjects.subject_id', $subject_id)
                ->limit(2)
                ->get()->result_array();
            if (count($rows) !== 1 || (float) $rows[0]['max_score'] <= 0) {
                $config['message'] = 'Holiday Assessment is not enabled with a positive maximum for every selected class arm and subject.';
                return $config;
            }
            $row_maximum = round((float) $rows[0]['max_score'], 2);
            if ($maximum !== null && abs($maximum - $row_maximum) > 0.001) {
                $config['message'] = 'Selected class arms use different Holiday Assessment maximums. Use separate online assessments for those arms.';
                return $config;
            }
            $maximum = $row_maximum;
            $config['holiday_mappings'][] = array(
                'section_id' => $section_id,
                'setting_id' => (int) $rows[0]['setting_id'],
                'setting_subject_id' => (int) $rows[0]['setting_subject_id'],
                'max_score' => $row_maximum,
            );
        }

        $config['target_maximum'] = $maximum;
        $config['valid'] = !empty($config['holiday_mappings']);
        $config['message'] = 'Holiday Assessment settings were verified for every selected class arm.';
        return $config;
    }

    public function saveWorkflow($exam_data, $section_ids, array $holiday_mappings = array())
    {
        $this->workflow_save_error = '';
        $this->load->library('onlineexam_setup');
        $duration = explode(':', isset($exam_data['duration']) ? $exam_data['duration'] : '');
        $minutes = count($duration) >= 2 ? (int) $duration[0] * 60 + (int) $duration[1] : 0;
        if ($this->onlineexam_setup->windowError($exam_data['exam_from'], $exam_data['exam_to'], $minutes)
            || $this->onlineexam_setup->paperMaximum($exam_data['target_max_score']) === null) {
            $this->workflow_save_error = 'The assessment window or result-component maximum is invalid.';
            return false;
        }
        $id = isset($exam_data['id']) ? (int) $exam_data['id'] : 0;
        $selected_section_ids = array_values(array_unique(array_filter(array_map('intval', (array) $section_ids))));
        if (empty($selected_section_ids)) {
            $this->workflow_save_error = 'Select at least one class arm/section.';
            return false;
        }
        if (!$this->db->table_exists('onlineexam_academic_slots')) {
            $this->workflow_save_error = 'Install Online Examination migration 138 before saving an assessment.';
            return false;
        }

        $slot = $this->workflowAcademicSlot($exam_data);
        if ($slot === null) {
            $this->workflow_save_error = 'The selected academic assessment type or result component is invalid.';
            return false;
        }

        $this->db->trans_begin();
        if (isset($exam_data['result_adapter']) && $exam_data['result_adapter'] === 'holiday_assessment') {
            $mapped_section_ids = array_values(array_unique(array_map(function ($mapping) {
                return isset($mapping['section_id']) ? (int) $mapping['section_id'] : 0;
            }, $holiday_mappings)));
            sort($mapped_section_ids);
            $expected_section_ids = $selected_section_ids;
            sort($expected_section_ids);
            if (!$this->db->table_exists('onlineexam_holiday_mappings')
                || empty($holiday_mappings)
                || $mapped_section_ids !== $expected_section_ids) {
                $this->workflow_save_error = 'The Holiday Assessment mapping does not match every selected class arm.';
                $this->db->trans_rollback();
                return false;
            }
        }

        if ($id > 0) {
            $existing = $this->db->query('SELECT * FROM onlineexam WHERE id = ? FOR UPDATE', array($id))->row_array();
            if (empty($existing) || $existing['lifecycle_status'] !== 'draft'
                || $this->hasWorkflowAttemptsForRevision($id, $existing['revision'])) {
                $this->workflow_save_error = 'This assessment is no longer an editable draft.';
                $this->db->trans_rollback();
                return false;
            }
            $this->db->where('id', $id);
            $this->db->where('workflow_version', 2);
            $saved_exam = $this->db->update('onlineexam', $exam_data);
            // Only mutable draft papers follow a changed component maximum.
            $this->db->where('onlineexam_id', $id)->update('onlineexam_papers', array(
                'raw_max_score' => $this->onlineexam_setup->paperMaximum($exam_data['target_max_score']),
                'updated_at' => date('Y-m-d H:i:s'),
            ));
        } else {
            unset($exam_data['id']);
            $saved_exam = $this->db->insert('onlineexam', $exam_data);
            $id = (int) $this->db->insert_id();
        }
        if (!$saved_exam || $id < 1 || $this->db->trans_status() === false) {
            $this->workflow_save_error = 'The assessment could not be saved.';
            $this->db->trans_rollback();
            return false;
        }

        if ($this->workflowAcademicSlotConflict($slot, $selected_section_ids, $id)) {
            $this->workflow_save_error = 'This subject already has an online examination for one or more selected class arms in the chosen session, term, assessment type and result component.';
            $this->db->trans_rollback();
            return false;
        }

        $this->db->where('onlineexam_id', $id)->delete('onlineexam_academic_slots');
        $now = date('Y-m-d H:i:s');
        foreach ($selected_section_ids as $section_id) {
            $this->db->query(
                'INSERT IGNORE INTO `onlineexam_academic_slots` '
                . '(`onlineexam_id`,`session_id`,`term`,`class_id`,`section_id`,`assessment_type`,`component_key`,`subject_id`,`created_at`,`updated_at`) '
                . 'VALUES (?,?,?,?,?,?,?,?,?,?)',
                array($id, $slot['session_id'], $slot['term'], $slot['class_id'], $section_id,
                    $slot['assessment_type'], $slot['component_key'], $slot['subject_id'], $now, $now)
            );
        }
        $reserved_sections = (int) $this->db->where('onlineexam_id', $id)
            ->count_all_results('onlineexam_academic_slots');
        if ($reserved_sections !== count($selected_section_ids)) {
            $this->workflow_save_error = 'This subject already has an online examination for one or more selected class arms in the chosen session, term, assessment type and result component.';
            $this->db->trans_rollback();
            return false;
        }

        $this->db->where('onlineexam_id', $id)->delete('onlineexam_class_sections');
        foreach ($selected_section_ids as $section_id) {
            if ($section_id > 0) {
                $this->db->insert('onlineexam_class_sections', array(
                    'onlineexam_id' => $id,
                    'section_id'    => $section_id,
                    'created_at'    => date('Y-m-d H:i:s'),
                ));
            }
        }

        if ($this->db->table_exists('onlineexam_holiday_mappings')) {
            $this->db->where('onlineexam_id', $id)->delete('onlineexam_holiday_mappings');
            if (isset($exam_data['result_adapter']) && $exam_data['result_adapter'] === 'holiday_assessment') {
                $now = date('Y-m-d H:i:s');
                foreach ($holiday_mappings as $mapping) {
                    if (empty($mapping['section_id']) || empty($mapping['setting_id'])
                        || empty($mapping['setting_subject_id']) || (float) $mapping['max_score'] <= 0) {
                        $this->workflow_save_error = 'A Holiday Assessment result mapping is incomplete.';
                        $this->db->trans_rollback();
                        return false;
                    }
                    $this->db->insert('onlineexam_holiday_mappings', array(
                        'onlineexam_id' => $id,
                        'section_id' => (int) $mapping['section_id'],
                        'setting_id' => (int) $mapping['setting_id'],
                        'setting_subject_id' => (int) $mapping['setting_subject_id'],
                        'max_score' => round((float) $mapping['max_score'], 2),
                        'created_at' => $now,
                        'updated_at' => $now,
                    ));
                }
            }
        }

        if ($this->db->trans_status() === false) {
            $this->workflow_save_error = 'The assessment could not be saved because the database rejected one of its academic mappings.';
            $this->db->trans_rollback();
            return false;
        }

        $this->db->trans_commit();
        return $id;
    }

    public function getWorkflowSaveError()
    {
        return $this->workflow_save_error;
    }

    /** Canonical identity used by the unique subject/arm assessment slot. */
    private function workflowAcademicSlot(array $exam_data)
    {
        $purpose = strtolower(trim(isset($exam_data['purpose']) ? (string) $exam_data['purpose'] : ''));
        $adapter = strtolower(trim(isset($exam_data['result_adapter']) ? (string) $exam_data['result_adapter'] : ''));
        $assessment_types = array('ca' => 'term', 'kindergarten' => 'term', 'midterm' => 'midterm', 'holiday' => 'holiday');
        if (!isset($assessment_types[$purpose])) {
            return null;
        }
        $component = $adapter === 'standard_component'
            ? strtolower(trim(isset($exam_data['target_component']) ? (string) $exam_data['target_component'] : ''))
            : $purpose;
        $term = strtolower(trim(isset($exam_data['term']) ? (string) $exam_data['term'] : ''));
        if ($component === '' || !in_array($term, array('1st', '2nd', '3rd'), true)) {
            return null;
        }
        $slot = array(
            'session_id' => isset($exam_data['session_id']) ? (int) $exam_data['session_id'] : 0,
            'term' => $term,
            'class_id' => isset($exam_data['class_id']) ? (int) $exam_data['class_id'] : 0,
            'assessment_type' => $assessment_types[$purpose],
            'component_key' => $component,
            'subject_id' => isset($exam_data['subject_id']) ? (int) $exam_data['subject_id'] : 0,
        );
        return min($slot['session_id'], $slot['class_id'], $slot['subject_id']) > 0 ? $slot : null;
    }

    /** Protects migrated databases that already contained overlapping rows. */
    private function workflowAcademicSlotConflict(array $slot, array $section_ids, $exclude_onlineexam_id)
    {
        $section_ids = array_values(array_unique(array_filter(array_map('intval', $section_ids))));
        if (empty($section_ids)) {
            return true;
        }
        $sql = "SELECT e.id FROM onlineexam e
            INNER JOIN onlineexam_class_sections ecs ON ecs.onlineexam_id = e.id
            WHERE e.id <> ? AND e.workflow_version = 2
              AND e.session_id = ? AND e.term = ? AND e.class_id = ? AND e.subject_id = ?
              AND (CASE WHEN e.purpose IN ('ca','kindergarten') THEN 'term'
                        WHEN e.purpose = 'midterm' THEN 'midterm'
                        WHEN e.purpose = 'holiday' THEN 'holiday' ELSE '' END) = ?
              AND COALESCE(NULLIF(LOWER(e.target_component),''), LOWER(e.purpose)) = ?
              AND ecs.section_id IN (" . implode(',', $section_ids) . ')';
        if ($this->db->field_exists('deleted_at', 'onlineexam')) {
            $sql .= ' AND e.deleted_at IS NULL';
        }
        $sql .= ' LIMIT 1 FOR UPDATE';
        return $this->db->query($sql, array((int) $exclude_onlineexam_id, $slot['session_id'], $slot['term'],
            $slot['class_id'], $slot['subject_id'], $slot['assessment_type'], $slot['component_key']))->num_rows() > 0;
    }

    public function getHolidayMappings($onlineexam_id)
    {
        if (!$this->db->table_exists('onlineexam_holiday_mappings')) {
            return array();
        }
        return $this->db->where('onlineexam_id', (int) $onlineexam_id)
            ->order_by('section_id', 'ASC')
            ->get('onlineexam_holiday_mappings')
            ->result_array();
    }

    public function getWorkflowPapers($onlineexam_id)
    {
        $papers = $this->db->select('onlineexam_papers.*, (select count(*) from onlineexam_questions where onlineexam_questions.paper_id=onlineexam_papers.id) as question_count')
            ->where('onlineexam_id', (int) $onlineexam_id)
            ->order_by('display_order')
            ->order_by('id')
            ->get('onlineexam_papers')->result_array();

        foreach ($papers as $key => $paper) {
            $papers[$key]['sections'] = $this->db->select('onlineexam_paper_sections.*, (select count(*) from onlineexam_questions where onlineexam_questions.paper_section_id=onlineexam_paper_sections.id) as question_count')
                ->where('paper_id', (int) $paper['id'])
                ->order_by('display_order')
                ->order_by('id')
                ->get('onlineexam_paper_sections')->result_array();
        }

        return $papers;
    }

    public function getWorkflowPaper($paper_id)
    {
        return $this->db->select('onlineexam_papers.*, onlineexam.lifecycle_status, onlineexam.workflow_version')
            ->from('onlineexam_papers')
            ->join('onlineexam', 'onlineexam.id=onlineexam_papers.onlineexam_id')
            ->where('onlineexam_papers.id', (int) $paper_id)
            ->get()->row_array();
    }

    public function saveWorkflowPaper($data)
    {
        $this->paper_save_error = '';
        $this->load->library('onlineexam_setup');
        $this->db->trans_begin();
        $exam = $this->db->query('SELECT * FROM onlineexam WHERE id = ? FOR UPDATE', array((int) $data['onlineexam_id']))->row();
        if (!$exam || (int) $exam->workflow_version !== 2 || !empty($exam->deleted_at) || $exam->lifecycle_status !== 'draft'
            || $this->hasWorkflowAttemptsForRevision($exam->id, $exam->revision)
            || $this->onlineexam_setup->windowError($data['starts_at'], $data['ends_at'], $data['duration_minutes'], $exam->exam_from, $exam->exam_to)) {
            $this->paper_save_error = 'This paper is no longer editable or its delivery window is invalid.';
            $this->db->trans_rollback();
            return false;
        }
        $data['raw_max_score'] = $this->onlineexam_setup->paperMaximum($exam->target_max_score);
        $data['contribution_score'] = 100.0;
        if ($data['raw_max_score'] === null) {
            $this->paper_save_error = 'The selected result component has no valid maximum score.';
            $this->db->trans_rollback();
            return false;
        }
        if (!empty($data['id'])) {
            $id = (int) $data['id'];
            $owned = $this->db->where('id', $id)->where('onlineexam_id', (int) $exam->id)->count_all_results('onlineexam_papers');
            if ($owned !== 1) {
                $this->paper_save_error = 'The selected paper does not belong to this assessment.';
                $this->db->trans_rollback();
                return false;
            }
            $data['updated_at'] = date('Y-m-d H:i:s');
            $saved = $this->db->where('id', $id)->where('onlineexam_id', (int) $data['onlineexam_id'])->update('onlineexam_papers', $data);
        } else {
            $paper_count = (int) $this->db->where('onlineexam_id', (int) $exam->id)
                ->count_all_results('onlineexam_papers');
            if ($paper_count > 0) {
                $this->paper_save_error = 'Only one paper is allowed for each subject assessment.';
                $this->db->trans_rollback();
                return false;
            }
            unset($data['id']);
            $data['created_at'] = date('Y-m-d H:i:s');
            $data['updated_at'] = date('Y-m-d H:i:s');
            $saved = $this->db->insert('onlineexam_papers', $data);
            $id = (int) $this->db->insert_id();
        }
        if (!$saved || $this->db->trans_status() === false) {
            $this->paper_save_error = 'The paper could not be saved.';
            $this->db->trans_rollback();
            return false;
        }
        $this->db->trans_commit();
        return $id;
    }

    public function getWorkflowPaperSaveError()
    {
        return $this->paper_save_error;
    }

    public function removeWorkflowPaper($paper_id, $onlineexam_id)
    {
        $this->db->trans_begin();
        $owned = $this->db->where('id', (int) $paper_id)
            ->where('onlineexam_id', (int) $onlineexam_id)
            ->count_all_results('onlineexam_papers') === 1;
        if (!$owned) {
            $this->db->trans_rollback();
            return false;
        }
        $this->db->where('onlineexam_id', (int) $onlineexam_id)->where('paper_id', (int) $paper_id)->delete('onlineexam_questions');
        $this->db->where('paper_id', (int) $paper_id)->where('onlineexam_id', (int) $onlineexam_id)->delete('onlineexam_kindergarten_mappings');
        $this->db->where('paper_id', (int) $paper_id)
            ->where('EXISTS (SELECT 1 FROM onlineexam_papers p WHERE p.id = onlineexam_paper_sections.paper_id AND p.onlineexam_id = ' . (int) $onlineexam_id . ')', null, false)
            ->delete('onlineexam_paper_sections');
        $this->db->where('id', (int) $paper_id)->where('onlineexam_id', (int) $onlineexam_id)->delete('onlineexam_papers');
        if ($this->db->trans_status() === false) {
            $this->db->trans_rollback();
            return false;
        }
        $this->db->trans_commit();
        return true;
    }

    public function saveWorkflowPaperSection($data)
    {
        if (!empty($data['id'])) {
            $id = (int) $data['id'];
            $data['updated_at'] = date('Y-m-d H:i:s');
            $saved = $this->db->where('id', $id)->where('paper_id', (int) $data['paper_id'])->update('onlineexam_paper_sections', $data);
            return $saved ? $id : false;
        }

        unset($data['id']);
        $data['created_at'] = date('Y-m-d H:i:s');
        $data['updated_at'] = date('Y-m-d H:i:s');
        $saved = $this->db->insert('onlineexam_paper_sections', $data);
        return $saved ? (int) $this->db->insert_id() : false;
    }

    public function getWorkflowPaperSection($section_id)
    {
        return $this->db->select('onlineexam_paper_sections.*, onlineexam_papers.onlineexam_id')
            ->from('onlineexam_paper_sections')
            ->join('onlineexam_papers', 'onlineexam_papers.id=onlineexam_paper_sections.paper_id')
            ->where('onlineexam_paper_sections.id', (int) $section_id)
            ->get()->row_array();
    }

    public function removeWorkflowPaperSection($section_id, $paper_id, $onlineexam_id)
    {
        $owned = $this->db->select('onlineexam_paper_sections.id')
            ->from('onlineexam_paper_sections')
            ->join('onlineexam_papers', 'onlineexam_papers.id = onlineexam_paper_sections.paper_id')
            ->where('onlineexam_paper_sections.id', (int) $section_id)
            ->where('onlineexam_paper_sections.paper_id', (int) $paper_id)
            ->where('onlineexam_papers.onlineexam_id', (int) $onlineexam_id)
            ->count_all_results() === 1;
        if (!$owned) {
            return false;
        }
        $this->db->where('onlineexam_id', (int) $onlineexam_id)->where('paper_section_id', (int) $section_id)->where('paper_id', (int) $paper_id)->update('onlineexam_questions', array('paper_section_id' => null));
        $this->db->where('onlineexam_id', (int) $onlineexam_id)->where('paper_id', (int) $paper_id)->where('paper_section_id', (int) $section_id)->delete('onlineexam_kindergarten_mappings');
        $this->db->where('id', (int) $section_id);
        $this->db->where('paper_id', (int) $paper_id);
        return $this->db->delete('onlineexam_paper_sections');
    }

    public function assignWorkflowQuestion($data)
    {
        if (!array_key_exists('is_compulsory', $data)) {
            $data['is_compulsory'] = 1;
        }
        $this->db->trans_begin();
        $source_question = $this->db->query(
            'SELECT `id` FROM `questions` WHERE `id` = '
            . $this->db->escape((int) $data['question_id'])
            . ' LIMIT 1 FOR UPDATE'
        )->row();
        if (!$source_question) {
            $this->db->trans_rollback();
            return false;
        }
        if (empty($data['authoring_json']) && $this->db->table_exists('onlineexam_question_definitions')) {
            $definition = $this->db->select('definition_json')
                ->where('question_id', (int) $data['question_id'])
                ->limit(1)
                ->get('onlineexam_question_definitions')
                ->row_array();
            if (!empty($definition['definition_json'])) {
                $data['authoring_json'] = $definition['definition_json'];
            }
        }
        $existing = $this->db->select('id')
            ->where('onlineexam_id', (int) $data['onlineexam_id'])
            ->where('question_id', (int) $data['question_id'])
            ->get('onlineexam_questions')->row_array();

        if ($existing) {
            $data['id'] = (int) $existing['id'];
            $this->db->where('id', $data['id'])->update('onlineexam_questions', $data);
            $id = $data['id'];
        } else {
            $this->db->insert('onlineexam_questions', $data);
            $id = (int) $this->db->insert_id();
        }
        if ($id < 1 || $this->db->trans_status() === false) {
            $this->db->trans_rollback();
            return false;
        }
        $this->db->trans_commit();
        return $id;
    }

    /**
     * Creates a v2-native question and its assessment assignment atomically.
     * The minimal source row keeps the existing question-bank relationship
     * intact; the richer definition belongs to onlineexam_questions and is
     * frozen into the revision snapshot at publication time.
     */
    public function createWorkflowAuthoredQuestion(array $source, array $assignment)
    {
        if (!array_key_exists('is_compulsory', $assignment)) {
            $assignment['is_compulsory'] = 1;
        }
        $this->db->trans_begin();
        $this->db->insert('questions', $source);
        $question_id = (int) $this->db->insert_id();
        if ($question_id <= 0) {
            $this->db->trans_rollback();
            return false;
        }

        $assignment['question_id'] = $question_id;
        if (!empty($assignment['authoring_json'])) {
            $now = date('Y-m-d H:i:s');
            $this->db->insert('onlineexam_question_definitions', array(
                'question_id' => $question_id,
                'definition_version' => 1,
                'definition_json' => $assignment['authoring_json'],
                'created_by' => isset($source['staff_id']) ? (int) $source['staff_id'] : null,
                'created_at' => $now,
                'updated_at' => $now,
            ));
        }
        $this->db->insert('onlineexam_questions', $assignment);
        $assignment_id = (int) $this->db->insert_id();
        if ($assignment_id <= 0 || $this->db->trans_status() === false) {
            $this->db->trans_rollback();
            return false;
        }

        $this->db->trans_commit();
        return array('question_id' => $question_id, 'assignment_id' => $assignment_id);
    }

    /** Return a passage definition already used in this draft, if any. */
    public function getWorkflowPassageDefinition($onlineexam_id, $group_key, $exclude_assignment_id = null)
    {
        $this->db->select('id, authoring_json')
            ->where('onlineexam_id', (int) $onlineexam_id)
            ->where('authoring_json IS NOT NULL', null, false);
        if ($exclude_assignment_id !== null) {
            $this->db->where('id !=', (int) $exclude_assignment_id);
        }
        $rows = $this->db->get('onlineexam_questions')->result_array();
        foreach ($rows as $row) {
            $definition = json_decode($row['authoring_json'], true);
            if (!is_array($definition) || empty($definition['passage']['group_key'])) {
                continue;
            }
            if (hash_equals((string) $definition['passage']['group_key'], (string) $group_key)) {
                return $definition['passage'];
            }
        }
        return null;
    }

    public function getWorkflowAuthoredQuestions($onlineexam_id)
    {
        return $this->db->select('onlineexam_questions.*, questions.question, questions.question_type AS source_question_type, onlineexam_papers.title AS paper_title, onlineexam_paper_sections.title AS section_title')
            ->from('onlineexam_questions')
            ->join('questions', 'questions.id = onlineexam_questions.question_id')
            ->join('onlineexam_papers', 'onlineexam_papers.id = onlineexam_questions.paper_id')
            ->join('onlineexam_paper_sections', 'onlineexam_paper_sections.id = onlineexam_questions.paper_section_id', 'left')
            ->where('onlineexam_questions.onlineexam_id', (int) $onlineexam_id)
            ->where('onlineexam_questions.authoring_json IS NOT NULL', null, false)
            ->order_by('onlineexam_papers.display_order', 'ASC')
            ->order_by('onlineexam_questions.display_order', 'ASC')
            ->order_by('onlineexam_questions.id', 'ASC')
            ->get()->result_array();
    }

    public function getWorkflowAuthoredQuestion($assignment_id, $onlineexam_id)
    {
        return $this->db->select('onlineexam_questions.*, questions.question, questions.question_type AS source_question_type')
            ->from('onlineexam_questions')
            ->join('questions', 'questions.id = onlineexam_questions.question_id')
            ->where('onlineexam_questions.id', (int) $assignment_id)
            ->where('onlineexam_questions.onlineexam_id', (int) $onlineexam_id)
            ->where('onlineexam_questions.authoring_json IS NOT NULL', null, false)
            ->limit(1)->get()->row_array();
    }

    /**
     * Edit the assigned v2-authored question in place when this draft owns its
     * source exclusively. A genuinely shared source still uses copy-on-write so
     * another assessment cannot be changed as a side effect.
     */
    public function reviseWorkflowAuthoredQuestion($assignment_id, $onlineexam_id, array $source, array $assignment)
    {
        $reference = $this->db->select('question_id')
            ->where('id', (int) $assignment_id)
            ->where('onlineexam_id', (int) $onlineexam_id)
            ->where('authoring_json IS NOT NULL', null, false)
            ->limit(1)
            ->get('onlineexam_questions')
            ->row_array();
        if (empty($reference)) {
            return false;
        }

        $this->db->trans_begin();
        $original_question_id = (int) $reference['question_id'];
        $locked_source = $this->db->query(
            'SELECT `id` FROM `questions` WHERE `id` = ' . $this->db->escape($original_question_id) . ' LIMIT 1 FOR UPDATE'
        )->row_array();
        if (empty($locked_source)) {
            $this->db->trans_rollback();
            return false;
        }
        $owned = $this->db->query(
            'SELECT * FROM `onlineexam_questions` WHERE `id` = ' . $this->db->escape((int) $assignment_id)
            . ' AND `onlineexam_id` = ' . $this->db->escape((int) $onlineexam_id)
            . ' AND `authoring_json` IS NOT NULL LIMIT 1 FOR UPDATE'
        )->row_array();
        if (empty($owned) || (int) $owned['question_id'] !== $original_question_id) {
            $this->db->trans_rollback();
            return false;
        }

        $references = $this->db->query(
            'SELECT `id` FROM `onlineexam_questions` WHERE `question_id` = '
            . $this->db->escape($original_question_id) . ' FOR UPDATE'
        )->result_array();
        $reference_count = count($references);
        $now = date('Y-m-d H:i:s');
        $copied = $reference_count > 1;
        if ($copied) {
            $this->db->insert('questions', $source);
            $question_id = (int) $this->db->insert_id();
            if ($question_id <= 0) {
                $this->db->trans_rollback();
                return false;
            }
            $this->db->insert('onlineexam_question_definitions', array(
                'question_id' => $question_id,
                'definition_version' => 1,
                'definition_json' => $assignment['authoring_json'],
                'created_by' => isset($source['staff_id']) ? (int) $source['staff_id'] : null,
                'created_at' => $now,
                'updated_at' => $now,
            ));
        } else {
            $question_id = $original_question_id;
            $this->db->where('id', $question_id)->update('questions', $source);
            $definition = $this->db->query(
                'SELECT * FROM `onlineexam_question_definitions` WHERE `question_id` = '
                . $this->db->escape($question_id) . ' LIMIT 1 FOR UPDATE'
            )->row_array();
            if (empty($definition)) {
                $this->db->insert('onlineexam_question_definitions', array(
                    'question_id' => $question_id,
                    'definition_version' => 1,
                    'definition_json' => $assignment['authoring_json'],
                    'created_by' => isset($source['staff_id']) ? (int) $source['staff_id'] : null,
                    'created_at' => $now,
                    'updated_at' => $now,
                ));
            } else {
                $this->db->where('id', (int) $definition['id'])->update('onlineexam_question_definitions', array(
                    'definition_version' => (int) $definition['definition_version'] + 1,
                    'definition_json' => $assignment['authoring_json'],
                    'updated_at' => $now,
                ));
            }
        }

        $assignment['question_id'] = $question_id;
        $this->db->where('id', (int) $assignment_id)
            ->where('onlineexam_id', (int) $onlineexam_id)
            ->update('onlineexam_questions', $assignment);
        if ($this->db->trans_status() === false) {
            $this->db->trans_rollback();
            return false;
        }
        $this->db->trans_commit();
        return array(
            'question_id' => $question_id,
            'assignment_id' => (int) $assignment_id,
            'copied_shared_source' => $copied,
        );
    }

    public function removeWorkflowQuestion($onlineexam_question_id, $onlineexam_id)
    {
        $this->db->where('id', (int) $onlineexam_question_id);
        $this->db->where('onlineexam_id', (int) $onlineexam_id);
        return $this->db->delete('onlineexam_questions');
    }

    public function hasWorkflowAttempts($onlineexam_id)
    {
        $new_attempts = $this->db->where('onlineexam_id', (int) $onlineexam_id)
            ->count_all_results('onlineexam_candidate_attempts');
        if ($new_attempts > 0) {
            return true;
        }

        $this->db->from('onlineexam_attempts');
        $this->db->join('onlineexam_students', 'onlineexam_students.id=onlineexam_attempts.onlineexam_student_id');
        $this->db->where('onlineexam_students.onlineexam_id', (int) $onlineexam_id);
        return $this->db->count_all_results() > 0;
    }

    public function hasWorkflowAttemptsForRevision($onlineexam_id, $revision)
    {
        return $this->db
            ->where('onlineexam_id', (int) $onlineexam_id)
            ->where('revision', (int) $revision)
            ->count_all_results('onlineexam_candidate_attempts') > 0;
    }

    public function removeWorkflowDraft($onlineexam_id)
    {
        $onlineexam_id = (int) $onlineexam_id;
        $this->db->trans_begin();
        $paper_ids = array_column($this->db->select('id')->where('onlineexam_id', $onlineexam_id)->get('onlineexam_papers')->result_array(), 'id');
        if (!empty($paper_ids)) {
            $this->db->where_in('paper_id', $paper_ids)->delete('onlineexam_paper_sections');
        }
        $this->db->where('onlineexam_id', $onlineexam_id)->delete('onlineexam_kindergarten_mappings');
        if ($this->db->table_exists('onlineexam_holiday_mappings')) {
            $this->db->where('onlineexam_id', $onlineexam_id)->delete('onlineexam_holiday_mappings');
        }
        $this->db->where('onlineexam_id', $onlineexam_id)->delete('onlineexam_result_profiles');
        $this->db->where('onlineexam_id', $onlineexam_id)->delete('onlineexam_questions');
        $this->db->where('onlineexam_id', $onlineexam_id)->delete('onlineexam_papers');
        if ($this->db->table_exists('onlineexam_academic_slots')) {
            $this->db->where('onlineexam_id', $onlineexam_id)->delete('onlineexam_academic_slots');
        }
        $this->db->where('onlineexam_id', $onlineexam_id)->delete('onlineexam_class_sections');
        $this->db->where('onlineexam_id', $onlineexam_id)->delete('onlineexam_students');
        $this->db->where('id', $onlineexam_id)->where('workflow_version', 2)->where('lifecycle_status', 'draft')->delete('onlineexam');
        if ($this->db->trans_status() === false) {
            $this->db->trans_rollback();
            return false;
        }
        $this->db->trans_commit();
        return true;
    }

    public function getWorkflowPublishErrors($onlineexam_id)
    {
        $this->load->model('onlineexamworkflow_model');
        $validation = $this->onlineexamworkflow_model->validateAssessment((int) $onlineexam_id);
        return $validation['valid'] ? array() : $validation['errors'];

        // Kept below for migration-era compatibility documentation. Runtime
        // validation is owned by Onlineexamworkflow_model above.
        $exam = $this->getWorkflow($onlineexam_id);
        $errors = array();
        if (!$exam) {
            return array('The assessment does not exist.');
        }

        if (!in_array($exam->lifecycle_status, array('draft', 'scheduled'), true)) {
            $errors[] = 'Only a draft or scheduled assessment can be published.';
        }
        if (empty($exam->session_id) || empty($exam->term) || empty($exam->class_id) || empty($exam->subject_id)) {
            $errors[] = 'Session, term, class, and subject are required.';
        }
        if (empty($exam->section_ids)) {
            $errors[] = 'Select at least one class arm/section.';
        }
        if (strtotime($exam->exam_to) <= strtotime($exam->exam_from)) {
            $errors[] = 'The assessment closing time must be after its opening time.';
        }

        if ($exam->result_adapter === 'standard_component') {
            $configuration = $this->getAcademicConfiguration($exam->class_id, $exam->subject_id);
            $valid_component = false;
            foreach ($configuration['components'] as $component) {
                if ($component['value'] === $exam->target_component && abs((float) $component['maximum'] - (float) $exam->target_max_score) < 0.01) {
                    $valid_component = true;
                    break;
                }
            }
            if (!$configuration['valid'] || !$valid_component) {
                $errors[] = 'The result destination no longer matches the class CA configuration. Re-open the assessment and select it again.';
            }
        }
        if ((float) $exam->target_max_score <= 0) {
            $errors[] = 'The selected result destination must have a positive maximum score.';
        }
        if ($exam->result_adapter === 'british_outcome' && !$this->getWorkflowResultProfile($onlineexam_id, 'british_outcome')) {
            $errors[] = 'Configure the British outcome profile before publishing.';
        }
        if ($exam->result_adapter === 'kindergarten_concept' && empty($this->getKindergartenMappings($onlineexam_id))) {
            $errors[] = 'Map at least one paper or section to a Kindergarten assessment concept before publishing.';
        }

        $papers = $this->getWorkflowPapers($onlineexam_id);
        $active_papers = array_filter($papers, function ($paper) {
            return (int) $paper['is_active'] === 1;
        });
        if (empty($active_papers)) {
            $errors[] = 'Add at least one active paper.';
        }

        $contribution_total = 0;
        foreach ($active_papers as $paper) {
            if ((float) $paper['raw_max_score'] <= 0 || (float) $paper['contribution_score'] <= 0) {
                $errors[] = $paper['title'] . ' must have positive raw and contribution scores.';
            }
            if (in_array($paper['delivery_mode'], array('cbt', 'hybrid'), true) && (int) $paper['question_count'] === 0) {
                $errors[] = $paper['title'] . ' requires at least one question for CBT/hybrid delivery.';
            }
            $contribution_total += (float) $paper['contribution_score'];
        }
        if (!empty($active_papers) && abs($contribution_total - 100) > 0.01) {
            $errors[] = 'Active paper contributions must total exactly 100.';
        }

        return $errors;
    }

    /** Freeze the draft question bank and academic destination atomically. */
    public function publishWorkflow($onlineexam_id, $actor_id)
    {
        $this->load->model('onlineexamworkflow_model');
        return $this->onlineexamworkflow_model->publishAssessment((int) $onlineexam_id, $actor_id);

        $errors = $this->getWorkflowPublishErrors($onlineexam_id);
        if (!empty($errors)) {
            return array('success' => false, 'errors' => $errors);
        }

        $exam = $this->getWorkflow($onlineexam_id);
        $revision = max(1, (int) $exam->revision);
        $questions = $this->db->select('onlineexam_questions.*, questions.question_type, questions.question, questions.opt_a, questions.opt_b, questions.opt_c, questions.opt_d, questions.opt_e, questions.correct')
            ->from('onlineexam_questions')
            ->join('questions', 'questions.id=onlineexam_questions.question_id')
            ->where('onlineexam_questions.onlineexam_id', (int) $onlineexam_id)
            ->order_by('onlineexam_questions.display_order')
            ->order_by('onlineexam_questions.id')
            ->get()->result_array();

        $this->db->trans_begin();
        $this->db->where('onlineexam_id', (int) $onlineexam_id)
            ->where('revision', $revision)
            ->delete('onlineexam_question_snapshots');

        foreach ($questions as $question) {
            $options = array(
                'A' => $question['opt_a'],
                'B' => $question['opt_b'],
                'C' => $question['opt_c'],
                'D' => $question['opt_d'],
                'E' => $question['opt_e'],
            );
            $snapshot = array(
                'onlineexam_id'        => (int) $onlineexam_id,
                'paper_id'             => (int) $question['paper_id'],
                'paper_section_id'     => empty($question['paper_section_id']) ? null : (int) $question['paper_section_id'],
                'source_question_id'   => (int) $question['question_id'],
                'revision'             => $revision,
                'question_type'        => $question['question_type'],
                'question_text'        => $question['question'],
                'options_json'         => json_encode($options),
                'correct_answer_json'  => json_encode($question['correct']),
                'marking_scheme'       => $question['marking_scheme'],
                'marks'                => $question['marks'],
                'neg_marks'            => $question['neg_marks'],
                'is_compulsory'        => (int) $question['is_compulsory'],
                'display_order'        => (int) $question['display_order'],
                'created_at'           => date('Y-m-d H:i:s'),
            );
            $snapshot['checksum'] = hash('sha256', json_encode($snapshot));
            $this->db->insert('onlineexam_question_snapshots', $snapshot);
        }

        $configuration = $this->getAcademicConfiguration($exam->class_id, $exam->subject_id);
        $status = strtotime($exam->exam_from) > time() ? 'scheduled' : 'published';
        $this->db->where('id', (int) $onlineexam_id)->update('onlineexam', array(
            'lifecycle_status'      => $status,
            'is_active'            => 1,
            'frozen_at'            => date('Y-m-d H:i:s'),
            'published_at'         => date('Y-m-d H:i:s'),
            'result_config_snapshot' => json_encode($configuration),
        ));

        if ($this->db->trans_status() === false) {
            $this->db->trans_rollback();
            return array('success' => false, 'errors' => array('The assessment could not be published. No changes were saved.'));
        }

        $this->db->trans_commit();
        return array('success' => true, 'status' => $status, 'errors' => array());
    }

    public function unpublishWorkflow($onlineexam_id)
    {
        // Frozen v2 revisions are immutable. Call beginNewRevision() on the
        // workflow model instead of clearing their snapshot metadata.
        return false;

        if ($this->hasWorkflowAttempts($onlineexam_id)) {
            return false;
        }

        $exam = $this->getWorkflow($onlineexam_id);
        if (!$exam || !in_array($exam->lifecycle_status, array('scheduled', 'published'), true)) {
            return false;
        }

        return $this->db->where('id', (int) $onlineexam_id)->update('onlineexam', array(
            'lifecycle_status' => 'draft',
            'is_active'        => 0,
            'frozen_at'        => null,
            'published_at'     => null,
        ));
    }

    public function getWorkflowPrintablePapers($onlineexam_id, $revision)
    {
        $revision_row = $this->db->select('configuration_json')
            ->where('onlineexam_id', (int) $onlineexam_id)
            ->where('revision', (int) $revision)
            ->limit(1)
            ->get('onlineexam_revision_snapshots')
            ->row_array();
        if (!empty($revision_row['configuration_json'])) {
            $configuration = json_decode($revision_row['configuration_json'], true);
            if (is_array($configuration) && isset($configuration['papers']) && is_array($configuration['papers'])) {
                return array_values(array_filter($configuration['papers'], function ($paper) {
                    return !isset($paper['is_active']) || (int) $paper['is_active'] === 1;
                }));
            }
        }
        return $this->db->where('onlineexam_id', (int) $onlineexam_id)
            ->where('is_active', 1)
            ->order_by('display_order', 'ASC')
            ->get('onlineexam_papers')
            ->result_array();
    }

    public function getWorkflowPrintableQuestions($onlineexam_id, $revision)
    {
        $snapshot_count = $this->db->where('onlineexam_id', (int) $onlineexam_id)
            ->where('revision', (int) $revision)
            ->count_all_results('onlineexam_question_snapshots');

        if ($snapshot_count > 0) {
            $papers = $this->getWorkflowPrintablePapers($onlineexam_id, $revision);
            $paper_map = array();
            $section_map = array();
            foreach ($papers as $paper) {
                $paper_map[(int) $paper['id']] = $paper;
                foreach (isset($paper['sections']) ? (array) $paper['sections'] : array() as $section) {
                    $section_map[(int) $section['id']] = $section;
                }
            }
            $rows = $this->db->where('onlineexam_id', (int) $onlineexam_id)
                ->where('revision', (int) $revision)
                ->get('onlineexam_question_snapshots')
                ->result_array();
            $printable = array();
            foreach ($rows as $row) {
                $paper_id = (int) $row['paper_id'];
                if (!isset($paper_map[$paper_id])) {
                    continue;
                }
                $paper = $paper_map[$paper_id];
                $section = !empty($row['paper_section_id']) && isset($section_map[(int) $row['paper_section_id']])
                    ? $section_map[(int) $row['paper_section_id']]
                    : array();
                $row += array(
                    'paper_title' => $paper['title'],
                    'paper_code' => $paper['paper_code'],
                    'paper_type' => $paper['paper_type'],
                    'delivery_mode' => $paper['delivery_mode'],
                    'paper_instructions' => $paper['instructions'],
                    'section_title' => isset($section['title']) ? $section['title'] : null,
                    'section_instructions' => isset($section['instructions']) ? $section['instructions'] : null,
                    'answer_rule' => isset($section['answer_rule']) ? $section['answer_rule'] : null,
                    'answer_count' => isset($section['answer_count']) ? $section['answer_count'] : null,
                    '_paper_order' => isset($paper['display_order']) ? (int) $paper['display_order'] : 0,
                    '_section_order' => isset($section['display_order']) ? (int) $section['display_order'] : 0,
                );
                $printable[] = $row;
            }
            usort($printable, function ($left, $right) {
                foreach (array('_paper_order', '_section_order', 'display_order', 'id') as $key) {
                    $difference = (int) $left[$key] - (int) $right[$key];
                    if ($difference !== 0) {
                        return $difference;
                    }
                }
                return 0;
            });
            foreach ($printable as &$row) {
                unset($row['_paper_order'], $row['_section_order']);
            }
            unset($row);
            return $printable;
        }

        $rows = $this->db->select('onlineexam_questions.id, onlineexam_questions.paper_id, onlineexam_questions.paper_section_id, onlineexam_questions.question_id as source_question_id, onlineexam_questions.authoring_json, questions.question_type, questions.question as question_text, questions.opt_a, questions.opt_b, questions.opt_c, questions.opt_d, questions.opt_e, onlineexam_questions.marking_scheme, onlineexam_questions.marks, onlineexam_questions.neg_marks, onlineexam_questions.is_compulsory, onlineexam_questions.display_order, onlineexam_papers.title as paper_title, onlineexam_papers.paper_code, onlineexam_papers.paper_type, onlineexam_papers.delivery_mode, onlineexam_papers.instructions as paper_instructions, onlineexam_paper_sections.title as section_title, onlineexam_paper_sections.instructions as section_instructions, onlineexam_paper_sections.answer_rule, onlineexam_paper_sections.answer_count')
            ->from('onlineexam_questions')
            ->join('questions', 'questions.id=onlineexam_questions.question_id')
            ->join('onlineexam_papers', 'onlineexam_papers.id=onlineexam_questions.paper_id')
            ->join('onlineexam_paper_sections', 'onlineexam_paper_sections.id=onlineexam_questions.paper_section_id', 'left')
            ->where('onlineexam_questions.onlineexam_id', (int) $onlineexam_id)
            ->where('onlineexam_papers.is_active', 1)
            ->order_by('onlineexam_papers.display_order')
            ->order_by('onlineexam_paper_sections.display_order')
            ->order_by('onlineexam_questions.display_order')
            ->order_by('onlineexam_questions.id')
            ->get()->result_array();
        foreach ($rows as &$row) {
            $definition = !empty($row['authoring_json']) ? json_decode($row['authoring_json'], true) : null;
            if (!is_array($definition) || (int) (isset($definition['version']) ? $definition['version'] : 0) !== 1) {
                continue;
            }
            $passage = !empty($definition['passage']) && is_array($definition['passage']) ? $definition['passage'] : array();
            $row['question_type'] = $definition['question_type'];
            $row['options_json'] = json_encode(isset($definition['options']) ? $definition['options'] : array());
            $row['response_schema_json'] = json_encode(isset($definition['response_schema']) ? $definition['response_schema'] : array());
            $row['passage_group_key'] = isset($passage['group_key']) ? $passage['group_key'] : null;
            $row['passage_title'] = isset($passage['title']) ? $passage['title'] : null;
            $row['passage_text'] = isset($passage['text']) ? $passage['text'] : null;
        }
        unset($row);
        return $rows;
    }

    public function getWorkflowResultProfile($onlineexam_id, $adapter)
    {
        return $this->db->where('onlineexam_id', (int) $onlineexam_id)
            ->where('adapter', $adapter)
            ->where('is_active', 1)
            ->get('onlineexam_result_profiles')->row_array();
    }

    public function saveWorkflowResultProfile($data)
    {
        $existing = $this->db->select('id')->where('onlineexam_id', (int) $data['onlineexam_id'])
            ->where('adapter', $data['adapter'])->get('onlineexam_result_profiles')->row_array();
        $data['updated_at'] = date('Y-m-d H:i:s');
        if ($existing) {
            $this->db->where('id', (int) $existing['id'])->update('onlineexam_result_profiles', $data);
            return (int) $existing['id'];
        }
        $data['created_at'] = date('Y-m-d H:i:s');
        $this->db->insert('onlineexam_result_profiles', $data);
        return (int) $this->db->insert_id();
    }

    public function getKindergartenMappingOptions($class_id, $subject_id)
    {
        $rows = $this->db->select('kindergarten_assessment_header.id as assessment_id, kindergarten_assessment_header.assessment_name, kindergarten_assessment_header.num_result_labels, kindergarten_assessment_header.result_labels_json, kindergarten_assessment_subjects.id as assessment_subject_id, kindergarten_assessment_subjects.subject_id, kindergarten_assessment_concepts.id as concept_id, kindergarten_assessment_concepts.stable_key as concept_stable_key, kindergarten_assessment_concepts.concept_text')
            ->from('kindergarten_assignment')
            ->join('kindergarten_assessment_header', 'kindergarten_assessment_header.id=kindergarten_assignment.assessment_id')
            ->join('kindergarten_assessment_subjects', 'kindergarten_assessment_subjects.assessment_id=kindergarten_assessment_header.id')
            ->join('kindergarten_assessment_concepts', 'kindergarten_assessment_concepts.assessment_subject_id=kindergarten_assessment_subjects.id')
            ->where('kindergarten_assignment.class_id', (int) $class_id)
            ->where('kindergarten_assessment_subjects.subject_id', (int) $subject_id)
            ->where('kindergarten_assessment_subjects.is_active', 1)
            ->where('kindergarten_assessment_concepts.is_active', 1)
            ->order_by('kindergarten_assessment_header.assessment_name')
            ->order_by('kindergarten_assessment_concepts.display_order')
            ->get()->result_array();

        foreach ($rows as $key => $row) {
            $rows[$key]['result_labels'] = (array) json_decode($row['result_labels_json'], true);
        }
        return $rows;
    }

    public function getKindergartenMappings($onlineexam_id)
    {
        return $this->db->select('onlineexam_kindergarten_mappings.*, kindergarten_assessment_header.assessment_name, kindergarten_assessment_concepts.concept_text, onlineexam_papers.title as paper_title, onlineexam_paper_sections.title as section_title')
            ->from('onlineexam_kindergarten_mappings')
            ->join('kindergarten_assessment_header', 'kindergarten_assessment_header.id=onlineexam_kindergarten_mappings.assessment_id')
            ->join('kindergarten_assessment_concepts', 'kindergarten_assessment_concepts.id=onlineexam_kindergarten_mappings.concept_id')
            ->join('onlineexam_papers', 'onlineexam_papers.id=onlineexam_kindergarten_mappings.paper_id', 'left')
            ->join('onlineexam_paper_sections', 'onlineexam_paper_sections.id=onlineexam_kindergarten_mappings.paper_section_id', 'left')
            ->where('onlineexam_kindergarten_mappings.onlineexam_id', (int) $onlineexam_id)
            ->order_by('onlineexam_kindergarten_mappings.id')
            ->get()->result_array();
    }

    public function saveKindergartenMapping($data)
    {
        $existing = $this->db->select('id')->where('onlineexam_id', (int) $data['onlineexam_id'])
            ->where('concept_id', (int) $data['concept_id'])->get('onlineexam_kindergarten_mappings')->row_array();
        $data['updated_at'] = date('Y-m-d H:i:s');
        if ($existing) {
            $this->db->where('id', (int) $existing['id'])->update('onlineexam_kindergarten_mappings', $data);
            return (int) $existing['id'];
        }
        $data['created_at'] = date('Y-m-d H:i:s');
        $this->db->insert('onlineexam_kindergarten_mappings', $data);
        return (int) $this->db->insert_id();
    }

    public function removeKindergartenMapping($id, $onlineexam_id)
    {
        return $this->db->where('id', (int) $id)->where('onlineexam_id', (int) $onlineexam_id)->delete('onlineexam_kindergarten_mappings');
    }

    public function auditWorkflow($onlineexam_id, $actor_id, $action, $entity_type, $entity_id = null, $before = null, $after = null)
    {
        return $this->db->insert('onlineexam_audit_log', array(
            'onlineexam_id' => $onlineexam_id ? (int) $onlineexam_id : null,
            'actor_id'      => $actor_id ? (int) $actor_id : null,
            'actor_type'    => 'staff',
            'action'        => $action,
            'entity_type'   => $entity_type,
            'entity_id'     => $entity_id === null ? null : (string) $entity_id,
            'before_json'   => $before === null ? null : json_encode($before),
            'after_json'    => $after === null ? null : json_encode($after),
            'ip_address'    => $this->input->ip_address(),
            'created_at'    => date('Y-m-d H:i:s'),
        ));
    }

}
