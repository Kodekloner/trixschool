<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/** Class-arm review. Every read is enrollment-bound and teacher-subject scoped. */
class Onlineexamreview_model extends CI_Model
{
    public function __construct()
    {
        parent::__construct();
        $this->load->model(array('onlineexam_model', 'onlineexamoperations_model', 'onlineexamattempt_model'));
        $this->load->library('onlineexam_review');
    }

    public function sections($session_id, $class_id, $teacher_id = null)
    {
        $query = $this->db->distinct()->select('s.id, s.section')->from('sections s')
            ->join('class_sections cs', 'cs.section_id = s.id')
            ->join('student_session ss', 'ss.section_id = s.id AND ss.class_id = cs.class_id')
            ->where('cs.class_id', (int) $class_id)->where('ss.session_id', (int) $session_id);
        if ($teacher_id !== null) {
            $query->join('teacher_subjects ts', 'ts.class_section_id = cs.id')
                ->where('ts.session_id', (int) $session_id)->where('ts.teacher_id', (int) $teacher_id);
        }
        return $query->order_by('s.section')->get()->result_array();
    }

    public function overview(array $criteria, array $scope)
    {
        if (empty($criteria['component'])) {
            return array('students' => array(), 'columns' => array(), 'cells' => array());
        }
        $students = $this->db->select("ss.id AS student_session_id, ss.section_id, s.admission_no, CONCAT_WS(' ', s.firstname, s.middlename, s.lastname) AS student_name", false)
            ->from('student_session ss')->join('students s', 's.id = ss.student_id')
            ->where('ss.session_id', (int) $criteria['session_id'])->where('ss.class_id', (int) $criteria['class_id'])
            ->where('ss.section_id', (int) $criteria['section_id'])->where('s.is_active', 'yes')
            ->order_by('s.firstname')->order_by('s.lastname')->get()->result_array();
        $exam_ids = $this->matchingExamIds($criteria, $criteria['component']);
        $columns = $cells = array();
        foreach ($exam_ids as $exam_row) {
            $access = $this->onlineexamoperations_model->canManageExam($exam_row['id'], $scope);
            if (empty($access['success'])) {
                continue;
            }
            $exam = $access['exam'];
            $papers = $this->onlineexamattempt_model->getPapers($exam['id']);
            $records = $this->records($exam['id'], array((int) $criteria['section_id']));
            $subject = $this->db->select('name')->where('id', (int) $exam['subject_id'])->get('subjects')->row_array();
            foreach ($papers as $paper) {
                $paper = (array) $paper;
                $key = (int) $exam['id'] . '_' . (int) $paper['id'];
                $columns[$key] = array('exam_id' => (int) $exam['id'], 'paper_id' => (int) $paper['id'],
                    'subject' => isset($subject['name']) ? $subject['name'] : '', 'assessment' => $exam['exam'],
                    'component' => strtoupper((string) $exam['target_component']), 'title' => $paper['title']);
                foreach ($students as $student) {
                    $cell = $this->makeCell($exam, $paper, $student['student_session_id'], $records);
                    $cells[$student['student_session_id']][$key] = $cell;
                }
            }
        }
        return array('students' => $students, 'columns' => $columns, 'cells' => $cells);
    }

    /** Components that actually have a published assessment in this class arm. */
    public function components(array $criteria, array $scope)
    {
        $components = array();
        foreach ($this->matchingExamIds($criteria) as $exam_row) {
            $access = $this->onlineexamoperations_model->canManageExam($exam_row['id'], $scope);
            if (empty($access['success'])) {
                continue;
            }
            $exam = $access['exam'];
            $value = $exam['result_adapter'] === 'standard_component'
                ? strtolower(trim((string) $exam['target_component']))
                : strtolower(trim((string) $exam['purpose']));
            if ($value === '') {
                continue;
            }
            $components[$value] = array('value' => $value, 'label' => $this->componentLabel($value));
        }
        uasort($components, function ($left, $right) {
            return $this->componentOrder($left['value']) - $this->componentOrder($right['value']);
        });
        return array_values($components);
    }

    private function matchingExamIds(array $criteria, $component = '')
    {
        $purposes = array('term' => array('ca', 'kindergarten'), 'midterm' => array('midterm'), 'holiday' => array('holiday'));
        if (!isset($purposes[$criteria['assessment_type']])
            || empty($criteria['session_id']) || empty($criteria['class_id']) || empty($criteria['section_id'])
            || !in_array($criteria['term'], array('1st', '2nd', '3rd'), true)) {
            return array();
        }
        $query = $this->db->distinct()->select('e.id, e.subject_id')->from('onlineexam e')
            ->join('onlineexam_class_sections ecs', 'ecs.onlineexam_id = e.id')
            ->where('e.workflow_version', 2)->where('e.session_id', (int) $criteria['session_id'])
            ->where('e.term', $criteria['term'])->where('e.class_id', (int) $criteria['class_id'])
            ->where('ecs.section_id', (int) $criteria['section_id'])->where('e.is_active', 1)
            ->where_in('e.purpose', $purposes[$criteria['assessment_type']])
            ->where_in('e.lifecycle_status', array('scheduled', 'published', 'in_progress', 'marking', 'completed'));
        // Only the canonical owner is shown when a pre-migration database
        // contained duplicate assessments for the same academic slot.
        if ($this->db->table_exists('onlineexam_academic_slots')) {
            $query->join('onlineexam_academic_slots eas', 'eas.onlineexam_id = e.id AND eas.section_id = ecs.section_id');
        }
        if ($this->db->field_exists('deleted_at', 'onlineexam')) {
            $query->where('e.deleted_at', null);
        }
        $component = strtolower(trim((string) $component));
        if ($component !== '') {
            $query->where(
                "COALESCE(NULLIF(LOWER(e.target_component), ''), LOWER(e.purpose)) = " . $this->db->escape($component),
                null,
                false
            );
        }
        return $query->order_by('e.subject_id')->order_by('e.id')->get()->result_array();
    }

    private function componentLabel($component)
    {
        if (preg_match('/^ca([1-9]|10)$/', $component, $match)) {
            return 'CA' . $match[1];
        }
        $labels = array('exam' => 'Exam', 'holiday' => 'Holiday', 'kindergarten' => 'Kindergarten');
        return isset($labels[$component]) ? $labels[$component] : ucwords(str_replace('_', ' ', $component));
    }

    private function componentOrder($component)
    {
        if (preg_match('/^ca([1-9]|10)$/', $component, $match)) {
            return (int) $match[1];
        }
        return $component === 'exam' ? 20 : ($component === 'kindergarten' ? 30 : ($component === 'holiday' ? 40 : 99));
    }

    public function cell($exam_id, $student_session_id, $paper_id, array $scope)
    {
        $access = $this->onlineexamoperations_model->canManageExam($exam_id, $scope);
        if (empty($access['success']) || empty($access['section_ids'])) {
            return false;
        }
        $exam = $access['exam'];
        if (!empty($exam['deleted_at']) || empty($exam['frozen_at']) || (int) $exam['is_active'] !== 1) {
            return false;
        }
        $student = $this->db->select("ss.*, CONCAT_WS(' ', s.firstname, s.middlename, s.lastname) AS student_name, s.admission_no", false)
            ->from('student_session ss')->join('students s', 's.id = ss.student_id')
            ->where('ss.id', (int) $student_session_id)->where('ss.session_id', (int) $exam['session_id'])
            ->where('ss.class_id', (int) $exam['class_id'])->where_in('ss.section_id', $access['section_ids'])
            ->where('s.is_active', 'yes')->get()->row_array();
        if (!$student) {
            return false;
        }
        $records = $this->records($exam_id, array((int) $student['section_id']));
        $attempt_id = isset($records['attempts'][$student_session_id]) ? $records['attempts'][$student_session_id]['id'] : null;
        foreach ($this->onlineexamattempt_model->getPapers($exam_id, $attempt_id) as $paper) {
            $paper = (array) $paper;
            if ((int) $paper['id'] === (int) $paper_id) {
                $detail = array('exam' => $exam, 'student' => $student, 'paper' => $paper,
                    'cell' => $this->makeCell($exam, $paper, $student_session_id, $records),
                    'posting_conflicts' => array());
                if ($attempt_id) {
                    $detail['posting_conflicts'] = $this->db->select('id, conflict_reason')
                        ->where('attempt_id', (int) $attempt_id)->where('status', 'conflict')
                        ->order_by('id', 'ASC')->get('onlineexam_result_sync')->result_array();
                }
                return $detail;
            }
        }
        return false;
    }

    /** Batch reads per assessment, never per matrix cell. */
    private function records($exam_id, array $section_ids)
    {
        $out = array('candidates' => array(), 'attempts' => array(), 'papers' => array(), 'answers' => array(),
            'answer_groups' => array(), 'pending' => array(), 'questions' => array(), 'question_groups' => array(),
            'windows' => array(), 'sync' => array(), 'extra' => array());
        $candidates = $this->db->select('os.*')->from('onlineexam_students os')
            ->join('student_session ss', 'ss.id = os.student_session_id')->where('os.onlineexam_id', (int) $exam_id)
            ->where_in('ss.section_id', $section_ids)->get()->result_array();
        foreach ($candidates as $candidate) {
            $out['candidates'][$candidate['student_session_id']] = $candidate;
        }
        $attempts = $this->db->select('a.*, os.student_session_id')->from('onlineexam_candidate_attempts a')
            ->join('onlineexam_students os', 'os.id = a.onlineexam_student_id')->join('student_session ss', 'ss.id = os.student_session_id')
            ->where('a.onlineexam_id', (int) $exam_id)->where('a.status !=', 'voided')->where_in('ss.section_id', $section_ids)
            ->order_by('a.attempt_no', 'ASC')->order_by('a.id', 'ASC')->get()->result_array();
        foreach ($attempts as $attempt) {
            $out['attempts'][$attempt['student_session_id']] = $attempt;
        }
        $attempt_ids = array_map('intval', array_column($out['attempts'], 'id'));
        if ($attempt_ids) {
            foreach ($this->db->where_in('attempt_id', $attempt_ids)->get('onlineexam_attempt_papers')->result_array() as $paper) {
                $out['papers'][$paper['attempt_id']][$paper['paper_id']] = $paper;
            }
            $answers = $this->db->select("aa.attempt_id, qs.paper_id, COALESCE(qs.paper_section_id, 0) AS section_id, qs.is_compulsory, SUM(aa.is_answered = 1) AS answered_count, SUM(aa.is_answered = 1 AND aa.auto_mark IS NULL AND aa.marking_status != 'finalized') AS pending_count", false)
                ->from('onlineexam_attempt_answers aa')->join('onlineexam_question_snapshots qs', 'qs.id = aa.question_snapshot_id')
                ->where_in('aa.attempt_id', $attempt_ids)
                ->group_by(array('aa.attempt_id', 'qs.paper_id', 'qs.paper_section_id', 'qs.is_compulsory'))->get()->result_array();
            foreach ($answers as $answer) {
                $attempt_id = (int) $answer['attempt_id'];
                $paper_id = (int) $answer['paper_id'];
                $group_key = (int) $answer['section_id'] . ':' . (int) $answer['is_compulsory'];
                if (!isset($out['answers'][$attempt_id][$paper_id])) {
                    $out['answers'][$attempt_id][$paper_id] = 0;
                    $out['pending'][$attempt_id][$paper_id] = 0;
                }
                $out['answers'][$attempt_id][$paper_id] += (int) $answer['answered_count'];
                $out['pending'][$attempt_id][$paper_id] += (int) $answer['pending_count'];
                $out['answer_groups'][$attempt_id][$paper_id][$group_key] = (int) $answer['answered_count'];
            }
            $sync_targets = array();
            foreach ($this->db->where_in('attempt_id', $attempt_ids)->order_by('updated_at', 'ASC')->order_by('id', 'ASC')->get('onlineexam_result_sync')->result_array() as $sync) {
                // Keep the newest state of each destination, not just the last
                // concept in a multi-concept Kindergarten result.
                $key = $sync['adapter'] . '|' . $sync['target_table'] . '|' . $sync['target_field'];
                $sync_targets[$sync['attempt_id']][$key] = $sync['status'];
            }
            foreach ($sync_targets as $attempt_id => $targets) {
                $out['sync'][$attempt_id] = 'posted';
                foreach ($targets as $status) {
                    if (!in_array($status, array('posted', 'resolved'), true)) {
                        $out['sync'][$attempt_id] = $status;
                        break;
                    }
                }
            }
        }
        foreach ($this->db->select('revision, paper_id, COALESCE(paper_section_id, 0) AS section_id, is_compulsory, COUNT(*) AS question_count', false)
            ->where('onlineexam_id', (int) $exam_id)
            ->group_by(array('revision', 'paper_id', 'paper_section_id', 'is_compulsory'))
            ->get('onlineexam_question_snapshots')->result_array() as $question) {
            $revision = (int) $question['revision'];
            $paper_id = (int) $question['paper_id'];
            if (!isset($out['questions'][$revision][$paper_id])) {
                $out['questions'][$revision][$paper_id] = 0;
            }
            $out['questions'][$revision][$paper_id] += (int) $question['question_count'];
            $out['question_groups'][$revision][$paper_id][] = array(
                'section_id' => (int) $question['section_id'],
                'is_compulsory' => (int) $question['is_compulsory'],
                'question_count' => (int) $question['question_count'],
            );
        }
        if ($this->db->table_exists('onlineexam_candidate_paper_windows')) {
            foreach ($this->db->where('onlineexam_id', (int) $exam_id)->get('onlineexam_candidate_paper_windows')->result_array() as $window) {
                $out['windows'][$window['onlineexam_student_id']][$window['paper_id']] = $window;
            }
        }
        foreach ($this->db->where('onlineexam_id', (int) $exam_id)->get('onlineexam_accommodations')->result_array() as $accommodation) {
            $out['extra'][$accommodation['onlineexam_student_id']] = max(0, (int) $accommodation['extra_time_minutes']);
        }
        return $out;
    }

    private function makeCell(array $exam, array $paper, $student_session_id, array $records)
    {
        $candidate = isset($records['candidates'][$student_session_id]) ? $records['candidates'][$student_session_id] : array();
        $attempt = isset($records['attempts'][$student_session_id]) ? $records['attempts'][$student_session_id] : array();
        $attempt_id = $attempt ? (int) $attempt['id'] : 0;
        $row = isset($records['papers'][$attempt_id][$paper['id']]) ? $records['papers'][$attempt_id][$paper['id']] : array();
        $window = $candidate && isset($records['windows'][$candidate['id']][$paper['id']]) ? $records['windows'][$candidate['id']][$paper['id']] : array();
        $revision = $attempt ? $attempt['revision'] : $exam['revision'];
        if ($window && (int) $window['revision'] !== (int) $revision) {
            $window = array();
        }
        $extra = $attempt ? max(0, (int) $attempt['extra_time_minutes'])
            : ($candidate && isset($records['extra'][$candidate['id']]) ? $records['extra'][$candidate['id']] : 0);
        $end = $window ? $window['ends_at'] : (!empty($paper['ends_at']) ? $paper['ends_at'] : $exam['exam_to']);
        $end = $end && strtotime($end) !== false ? date('Y-m-d H:i:s', strtotime($end) + $extra * 60) : null;
        $groups = isset($records['question_groups'][$revision][$paper['id']])
            ? $records['question_groups'][$revision][$paper['id']] : array();
        foreach ($groups as &$group) {
            $group_key = $group['section_id'] . ':' . $group['is_compulsory'];
            $group['answered_count'] = isset($records['answer_groups'][$attempt_id][$paper['id']][$group_key])
                ? $records['answer_groups'][$attempt_id][$paper['id']][$group_key] : 0;
        }
        unset($group);
        $progress = $this->onlineexam_review->requirementProgress($groups, isset($paper['sections']) ? (array) $paper['sections'] : array());
        $details = array_merge($paper, $row, array(
            'attempt_status' => isset($row['status']) ? $row['status'] : 'pending',
            'raw_score' => isset($row['raw_score']) ? $row['raw_score'] : 0,
            'raw_max_score' => $paper['raw_max_score'],
            'answered_count' => isset($records['answers'][$attempt_id][$paper['id']]) ? $records['answers'][$attempt_id][$paper['id']] : 0,
            'question_count' => isset($records['questions'][$revision][$paper['id']]) ? $records['questions'][$revision][$paper['id']] : 0,
            'required_answer_count' => $progress['required'],
            'completed_required_count' => $progress['completed'],
            'requirements_met' => $progress['met'],
            'effective_starts_at' => $window ? $window['starts_at'] : (!empty($paper['starts_at']) ? $paper['starts_at'] : $exam['exam_from']),
            'effective_ends_at' => $end,
            'extra_time_minutes' => $extra,
            'is_rescheduled' => !empty($window),
        ));
        if ((!isset($row['completion_source']) || $row['completion_source'] !== 'manual')
            && !empty($records['pending'][$attempt_id][$paper['id']])) {
            $details['manual_marking_status'] = 'pending';
        }
        $assigned = $candidate && $candidate['candidate_status'] === 'assigned';
        $state = $this->onlineexam_review->describe($details, $assigned);
        return array_merge($state, array('details' => $details, 'attempt_id' => $attempt_id,
            'candidate_id' => $candidate ? (int) $candidate['id'] : 0, 'assigned' => (bool) $assigned,
            'excluded' => $candidate && !$assigned,
            'posting_status' => isset($records['sync'][$attempt_id]) ? $records['sync'][$attempt_id]
                : ($attempt && $attempt['status'] === 'completed' ? 'pending' : null)));
    }

    /** Remove completed assessments from working lists, never erase posted results. */
    public function archiveCompleted($exam_id, $actor_id)
    {
        if (!$this->db->field_exists('deleted_at', 'onlineexam')) {
            return array('success' => false, 'message' => 'Install migration 137 before deleting completed assessments.');
        }
        $this->db->trans_begin();
        $exam = $this->db->query('SELECT * FROM onlineexam WHERE id = ? AND workflow_version = 2 FOR UPDATE', array((int) $exam_id))->row_array();
        $error = 'Every assigned student must finish all papers and marking before this assessment can be deleted.';
        if (!$exam || !empty($exam['deleted_at']) || empty($exam['frozen_at'])) {
            $this->db->trans_rollback();
            return array('success' => false, 'message' => $error);
        }
        $sections = $this->onlineexam_model->getWorkflowSectionIds($exam_id);
        if (!$sections) {
            $this->db->trans_rollback();
            return array('success' => false, 'message' => $error);
        }
        $records = $this->records($exam_id, $sections);
        $papers = $this->onlineexamattempt_model->getPapers($exam_id);
        $assigned = 0;
        foreach ($records['candidates'] as $student_id => $candidate) {
            if ($candidate['candidate_status'] !== 'assigned') {
                continue;
            }
            $assigned++;
            $attempt = isset($records['attempts'][$student_id]) ? $records['attempts'][$student_id] : array();
            $ready = $attempt && $attempt['status'] === 'completed';
            foreach ($papers as $paper) {
                $cell = $this->makeCell($exam, (array) $paper, $student_id, $records);
                $ready = $ready && $cell['complete'];
            }
            if (!$ready) {
                $this->db->trans_rollback();
                return array('success' => false, 'message' => $error);
            }
        }
        $total_assigned = $this->db->where('onlineexam_id', (int) $exam_id)->where('candidate_status', 'assigned')->count_all_results('onlineexam_students');
        if (!$assigned || !$papers || $assigned !== (int) $total_assigned) {
            $this->db->trans_rollback();
            return array('success' => false, 'message' => $error);
        }
        // Finalization and posting are separate transactions in normal delivery.
        // Reconcile before removal so interrupted posting or any stale/multiple
        // ledger rows cannot hide an unposted score. Adapters remain idempotent
        // and refuse unrelated manual values; no report card is published here.
        $this->load->model('onlineexamresultsync_model');
        foreach ($records['candidates'] as $student_id => $candidate) {
            if ($candidate['candidate_status'] !== 'assigned') {
                continue;
            }
            $synced = $this->onlineexamresultsync_model->syncCompletedAttempt($records['attempts'][$student_id]['id'], $actor_id);
            if (empty($synced['success']) || $this->db->trans_status() === false) {
                $this->db->trans_rollback();
                return array('success' => false, 'message' => 'This assessment has a result that could not be posted. Review and resolve it before deleting the assessment.');
            }
        }
        $changes = array('deleted_at' => date('Y-m-d H:i:s'), 'deleted_by' => (int) $actor_id, 'is_active' => 0);
        $this->db->where('id', (int) $exam_id)->update('onlineexam', $changes);
        if ($this->db->table_exists('onlineexam_academic_slots')) {
            $this->db->where('onlineexam_id', (int) $exam_id)->delete('onlineexam_academic_slots');
        }
        $this->onlineexam_model->auditWorkflow($exam_id, $actor_id, 'delete_completed_assessment', 'onlineexam', $exam_id, $exam, $changes);
        if ($this->db->trans_status() === false) {
            $this->db->trans_rollback();
            return array('success' => false, 'message' => 'The assessment could not be deleted.');
        }
        $this->db->trans_commit();
        return array('success' => true);
    }
}
