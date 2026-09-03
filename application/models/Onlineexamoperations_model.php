<?php

defined('BASEPATH') OR exit('No direct script access allowed');

/**
 * Operational/admin services for workflow-v2 online assessments.
 *
 * Controllers remain responsible for route permissions and CSRF checks.  The
 * optional $scope argument adds a second, model-level boundary for teachers:
 *
 *   array(
 *       'allowed_onlineexam_ids' => array(1, 2), // optional hard allow-list
 *       'enforce_assignment'     => true,        // require teacher_subjects
 *       'staff_id'               => 12,
 *       'section_ids'            => array(3),    // optional narrower scope
 *       'attempt_ids'            => array(45),   // optional narrower scope
 *   )
 *
 * Trusted administrators may omit the scope or pass
 * array('bypass_assignment' => true). Every mutation still verifies that its
 * candidate, attempt, answer or incident belongs to the supplied assessment.
 */
class Onlineexamoperations_model extends CI_Model
{
    const WORKFLOW_VERSION = 2;

    protected $attempt_statuses = array(
        'in_progress', 'submitted', 'timed_out', 'marking', 'completed', 'voided'
    );

    protected $candidate_statuses = array('assigned', 'excluded');
    protected $incident_severities = array('info', 'warning', 'critical');
    protected $mark_statuses = array('draft', 'finalized');

    /**
     * Summary counts used by the operations dashboard.
     */
    public function dashboard($onlineexam_id, array $scope = array())
    {
        $access = $this->resolveScope($onlineexam_id, $scope);
        if (!$access['success']) {
            return $access;
        }

        $candidate_query = $this->db
            ->select("COUNT(DISTINCT os.id) AS total, "
                . "COUNT(DISTINCT CASE WHEN os.candidate_status = 'assigned' THEN os.id END) AS assigned, "
                . "COUNT(DISTINCT CASE WHEN os.candidate_status = 'excluded' THEN os.id END) AS excluded, "
                . "COUNT(DISTINCT CASE WHEN os.candidate_status = 'assigned' AND a.id IS NULL THEN os.id END) AS not_started", false)
            ->from('onlineexam_students os')
            ->join('student_session ss', 'ss.id = os.student_session_id')
            ->join('onlineexam_candidate_attempts a', "a.onlineexam_student_id = os.id AND a.status != 'voided'", 'left')
            ->where('os.onlineexam_id', (int) $onlineexam_id);
        $this->applySectionScope($candidate_query, $access['section_ids'], 'ss.section_id');
        $candidate_counts = $candidate_query->get()->row_array();

        $attempt_query = $this->db
            ->select("COUNT(DISTINCT a.id) AS total, "
                . "COUNT(DISTINCT CASE WHEN a.status = 'in_progress' AND a.deadline_at > " . $this->db->escape(date('Y-m-d H:i:s')) . " THEN a.id END) AS live, "
                . "COUNT(DISTINCT CASE WHEN a.status = 'in_progress' AND a.deadline_at <= " . $this->db->escape(date('Y-m-d H:i:s')) . " THEN a.id END) AS overdue, "
                . "COUNT(DISTINCT CASE WHEN a.status = 'submitted' THEN a.id END) AS submitted, "
                . "COUNT(DISTINCT CASE WHEN a.status = 'timed_out' THEN a.id END) AS timed_out, "
                . "COUNT(DISTINCT CASE WHEN a.status = 'marking' THEN a.id END) AS marking, "
                . "COUNT(DISTINCT CASE WHEN a.status = 'completed' THEN a.id END) AS completed, "
                . "COUNT(DISTINCT CASE WHEN a.status = 'voided' THEN a.id END) AS voided", false)
            ->from('onlineexam_candidate_attempts a')
            ->join('onlineexam_students os', 'os.id = a.onlineexam_student_id')
            ->join('student_session ss', 'ss.id = os.student_session_id')
            ->where('a.onlineexam_id', (int) $onlineexam_id);
        $this->applySectionScope($attempt_query, $access['section_ids'], 'ss.section_id');
        $attempt_counts = $attempt_query->get()->row_array();

        $marking_query = $this->db
            ->select("COUNT(DISTINCT CASE WHEN aa.auto_mark IS NULL AND aa.is_answered = 1 AND aa.marking_status != 'finalized' THEN aa.id END) AS answers_pending, "
                . "COUNT(DISTINCT CASE WHEN aa.auto_mark IS NULL AND aa.is_answered = 1 AND aa.marking_status = 'finalized' THEN aa.id END) AS answers_finalized, "
                . "COUNT(DISTINCT CASE WHEN a.manual_marking_required = 1 AND a.marking_status != 'finalized' THEN a.id END) AS attempts_pending", false)
            ->from('onlineexam_candidate_attempts a')
            ->join('onlineexam_students os', 'os.id = a.onlineexam_student_id')
            ->join('student_session ss', 'ss.id = os.student_session_id')
            ->join('onlineexam_attempt_answers aa', 'aa.attempt_id = a.id', 'left')
            ->where('a.onlineexam_id', (int) $onlineexam_id)
            ->where('a.status !=', 'voided');
        $this->applySectionScope($marking_query, $access['section_ids'], 'ss.section_id');
        $marking_counts = $marking_query->get()->row_array();

        $paper_marking_query = $this->db
            // Delivery semantics were captured on the attempt row as
            // not_required/pending/finalized. Do not join the mutable draft
            // paper table when reporting a frozen historical revision.
            ->select("COUNT(DISTINCT CASE WHEN ap.manual_marking_status NOT IN ('not_required','finalized') THEN ap.id END) AS papers_pending, "
                . "COUNT(DISTINCT CASE WHEN ap.manual_marking_status = 'finalized' THEN ap.id END) AS papers_finalized", false)
            ->from('onlineexam_attempt_papers ap')
            ->join('onlineexam_candidate_attempts a', 'a.id = ap.attempt_id')
            ->join('onlineexam_students os', 'os.id = a.onlineexam_student_id')
            ->join('student_session ss', 'ss.id = os.student_session_id')
            ->where('a.onlineexam_id', (int) $onlineexam_id)
            ->where('a.status !=', 'voided');
        $this->applySectionScope($paper_marking_query, $access['section_ids'], 'ss.section_id');
        $marking_counts = array_merge($marking_counts, $paper_marking_query->get()->row_array());

        $accommodation_query = $this->db
            ->select("COUNT(DISTINCT ac.id) AS candidates, "
                . "COALESCE(SUM(ac.extra_time_minutes), 0) AS total_extra_minutes, "
                . "COALESCE(SUM(ac.makeup_attempts), 0) AS authorized_makeups", false)
            ->from('onlineexam_accommodations ac')
            ->join('onlineexam_students os', 'os.id = ac.onlineexam_student_id')
            ->join('student_session ss', 'ss.id = os.student_session_id')
            ->where('ac.onlineexam_id', (int) $onlineexam_id);
        $this->applySectionScope($accommodation_query, $access['section_ids'], 'ss.section_id');
        $accommodation_counts = $accommodation_query->get()->row_array();

        $incident_query = $this->db
            ->select("COUNT(DISTINCT i.id) AS total, "
                . "COUNT(DISTINCT CASE WHEN i.status = 'open' THEN i.id END) AS open, "
                . "COUNT(DISTINCT CASE WHEN i.status = 'resolved' THEN i.id END) AS resolved, "
                . "COUNT(DISTINCT CASE WHEN i.status = 'open' AND i.severity = 'critical' THEN i.id END) AS critical_open", false)
            ->from('onlineexam_incidents i')
            ->join('onlineexam_candidate_attempts a', 'a.id = i.attempt_id', 'left')
            ->join('onlineexam_students os', 'os.id = COALESCE(i.onlineexam_student_id, a.onlineexam_student_id)', 'left', false)
            ->join('student_session ss', 'ss.id = os.student_session_id', 'left')
            ->where('i.onlineexam_id', (int) $onlineexam_id);
        $this->applyNullableSectionScope($incident_query, $access['section_ids'], 'ss.section_id');
        $incident_counts = $incident_query->get()->row_array();

        $sync_query = $this->db
            ->select("COUNT(DISTINCT rs.id) AS total, "
                . "COUNT(DISTINCT CASE WHEN rs.status = 'posted' THEN rs.id END) AS posted, "
                . "COUNT(DISTINCT CASE WHEN rs.status = 'pending' THEN rs.id END) AS pending, "
                . "COUNT(DISTINCT CASE WHEN rs.status = 'conflict' THEN rs.id END) AS conflicts, "
                . "COUNT(DISTINCT CASE WHEN rs.status = 'error' THEN rs.id END) AS errors", false)
            ->from('onlineexam_result_sync rs')
            ->join('onlineexam_candidate_attempts a', 'a.id = rs.attempt_id')
            ->join('onlineexam_students os', 'os.id = a.onlineexam_student_id')
            ->join('student_session ss', 'ss.id = os.student_session_id')
            ->where('rs.onlineexam_id', (int) $onlineexam_id);
        $this->applySectionScope($sync_query, $access['section_ids'], 'ss.section_id');
        $sync_counts = $sync_query->get()->row_array();

        return array(
            'success' => true,
            'onlineexam_id' => (int) $onlineexam_id,
            'candidates' => $this->integerize($candidate_counts),
            'attempts' => $this->integerize($attempt_counts),
            'marking' => $this->integerize($marking_counts),
            'accommodations' => $this->integerize($accommodation_counts),
            'incidents' => $this->integerize($incident_counts),
            'result_sync' => $this->integerize($sync_counts),
        );
    }

    public function listCandidates($onlineexam_id, array $filters = array(), array $scope = array())
    {
        $access = $this->resolveScope($onlineexam_id, $scope);
        if (!$access['success']) {
            return $access;
        }

        $query = $this->db
            ->select("os.id AS onlineexam_student_id, os.student_session_id, os.candidate_status, os.assigned_at, os.excluded_at, os.exclusion_reason, "
                . "ss.student_id, ss.class_id, ss.section_id, s.admission_no, s.roll_no, "
                . "CONCAT_WS(' ', s.firstname, s.middlename, s.lastname) AS student_name, sec.section, "
                . "ac.extra_time_minutes, ac.makeup_attempts, ac.makeup_expires_at, ac.notes AS accommodation_notes, "
                . "(SELECT ca.id FROM onlineexam_candidate_attempts ca WHERE ca.onlineexam_student_id = os.id ORDER BY ca.attempt_no DESC LIMIT 1) AS latest_attempt_id, "
                . "(SELECT ca.status FROM onlineexam_candidate_attempts ca WHERE ca.onlineexam_student_id = os.id ORDER BY ca.attempt_no DESC LIMIT 1) AS latest_attempt_status, "
                . "(SELECT COUNT(*) FROM onlineexam_candidate_attempts ca WHERE ca.onlineexam_student_id = os.id AND ca.status != 'voided') AS official_attempt_count", false)
            ->from('onlineexam_students os')
            ->join('student_session ss', 'ss.id = os.student_session_id')
            ->join('students s', 's.id = ss.student_id')
            ->join('sections sec', 'sec.id = ss.section_id')
            ->join('onlineexam_accommodations ac', 'ac.onlineexam_id = os.onlineexam_id AND ac.onlineexam_student_id = os.id', 'left')
            ->where('os.onlineexam_id', (int) $onlineexam_id);
        $this->applySectionScope($query, $access['section_ids'], 'ss.section_id');

        if (!empty($filters['candidate_status']) && in_array($filters['candidate_status'], $this->candidate_statuses, true)) {
            $query->where('os.candidate_status', $filters['candidate_status']);
        }
        if (!empty($filters['section_id'])) {
            $query->where('ss.section_id', (int) $filters['section_id']);
        }
        if (!empty($filters['search'])) {
            $search = trim((string) $filters['search']);
            $query->group_start()
                ->like('s.admission_no', $search)
                ->or_like('s.firstname', $search)
                ->or_like('s.middlename', $search)
                ->or_like('s.lastname', $search)
                ->group_end();
        }
        if (!empty($filters['attempt_status']) && in_array($filters['attempt_status'], $this->attempt_statuses, true)) {
            $status = $this->db->escape($filters['attempt_status']);
            $query->where("(SELECT ca.status FROM onlineexam_candidate_attempts ca WHERE ca.onlineexam_student_id = os.id ORDER BY ca.attempt_no DESC LIMIT 1) = " . $status, null, false);
        }

        $limit = isset($filters['limit']) ? max(1, min(1000, (int) $filters['limit'])) : 250;
        $offset = isset($filters['offset']) ? max(0, (int) $filters['offset']) : 0;
        $rows = $query->order_by('sec.section', 'ASC')
            ->order_by('s.firstname', 'ASC')
            ->order_by('s.lastname', 'ASC')
            ->limit($limit, $offset)
            ->get()
            ->result_array();

        return array('success' => true, 'rows' => $rows, 'limit' => $limit, 'offset' => $offset);
    }

    public function listAttempts($onlineexam_id, array $filters = array(), array $scope = array())
    {
        $access = $this->resolveScope($onlineexam_id, $scope);
        if (!$access['success']) {
            return $access;
        }

        $query = $this->db
            ->select("a.*, os.student_session_id, ss.student_id, ss.section_id, sec.section, s.admission_no, "
                . "CONCAT_WS(' ', s.firstname, s.middlename, s.lastname) AS student_name, "
                . "(SELECT COUNT(*) FROM onlineexam_attempt_answers aa WHERE aa.attempt_id = a.id AND aa.auto_mark IS NULL AND aa.is_answered = 1 AND aa.marking_status != 'finalized') AS pending_manual_answers, "
                . "(SELECT COUNT(*) FROM onlineexam_attempt_papers ap WHERE ap.attempt_id = a.id AND ap.manual_marking_status NOT IN ('not_required','finalized')) AS pending_manual_papers, "
                . "(SELECT COUNT(*) FROM onlineexam_incidents i WHERE i.attempt_id = a.id AND i.status = 'open') AS open_incidents, "
                . "(SELECT COUNT(*) FROM onlineexam_result_sync rs WHERE rs.attempt_id = a.id AND rs.status = 'conflict') AS sync_conflicts", false)
            ->from('onlineexam_candidate_attempts a')
            ->join('onlineexam_students os', 'os.id = a.onlineexam_student_id')
            ->join('student_session ss', 'ss.id = os.student_session_id')
            ->join('students s', 's.id = ss.student_id')
            ->join('sections sec', 'sec.id = ss.section_id')
            ->where('a.onlineexam_id', (int) $onlineexam_id);
        $this->applySectionScope($query, $access['section_ids'], 'ss.section_id');

        if (!empty($filters['status'])) {
            $statuses = is_array($filters['status']) ? $filters['status'] : array($filters['status']);
            $statuses = array_values(array_intersect($statuses, $this->attempt_statuses));
            if (!empty($statuses)) {
                $query->where_in('a.status', $statuses);
            }
        }
        if (!empty($filters['marking_status'])) {
            $query->where('a.marking_status', substr(trim((string) $filters['marking_status']), 0, 24));
        }
        if (!empty($filters['student_session_id'])) {
            $query->where('os.student_session_id', (int) $filters['student_session_id']);
        }
        if (!empty($filters['search'])) {
            $search = trim((string) $filters['search']);
            $query->group_start()
                ->like('s.admission_no', $search)
                ->or_like('s.firstname', $search)
                ->or_like('s.middlename', $search)
                ->or_like('s.lastname', $search)
                ->group_end();
        }
        $this->applyAttemptScope($query, $scope, 'a.id');

        $limit = isset($filters['limit']) ? max(1, min(1000, (int) $filters['limit'])) : 250;
        $offset = isset($filters['offset']) ? max(0, (int) $filters['offset']) : 0;
        $rows = $query->order_by('a.updated_at', 'DESC')
            ->limit($limit, $offset)
            ->get()
            ->result_array();

        return array('success' => true, 'rows' => $rows, 'limit' => $limit, 'offset' => $offset);
    }

    /**
     * Creates (or returns) the official v2 attempt used for a paper/manual
     * sitting. This is the administrative counterpart to the candidate's CBT
     * start action: merely reopening an operations page never creates another
     * attempt.
     */
    public function ensureOfficialAttempt($onlineexam_id, $onlineexam_student_id, $actor_id, array $scope = array(), $create_makeup = false)
    {
        $candidate_result = $this->candidateContext($onlineexam_id, $onlineexam_student_id, $scope);
        if (!$candidate_result['success']) {
            return $candidate_result;
        }
        $candidate = $candidate_result['candidate'];
        $exam = $candidate_result['access']['exam'];
        if ($candidate['candidate_status'] !== 'assigned') {
            return $this->failure('candidate_not_assigned', 'Only an assigned candidate can receive an official attempt.');
        }
        if (empty($exam['frozen_at']) || !in_array($exam['lifecycle_status'], array('scheduled', 'published', 'in_progress', 'marking', 'completed'), true)) {
            return $this->failure('assessment_not_frozen', 'Freeze the assessment revision before creating an official paper attempt.');
        }
        $papers = $this->getFrozenPapers($onlineexam_id, (int) $exam['revision']);
        if (empty($papers)) {
            return $this->failure('papers_missing', 'The frozen assessment does not contain any active papers.');
        }
        $active_papers = 0;
        foreach ($papers as $paper) {
            if ((isset($paper['is_active']) && (int) $paper['is_active'] !== 1)) {
                continue;
            }
            $active_papers++;
            if ($paper['delivery_mode'] !== 'cbt' || !in_array($paper['paper_type'], array('objective', 'theory'), true)) {
                return $this->failure('retired_paper_type', 'Only Objective/Theory CBT papers can receive an official attempt.');
            }
            $allowed_question_types = array(
                'singlechoice', 'multichoice', 'true_false', 'short_answer',
                'numeric', 'matching', 'ordering', 'long_answer'
            );
            $question_count = $this->db->where('onlineexam_id', (int) $onlineexam_id)
                ->where('revision', (int) $exam['revision'])
                ->where('paper_id', (int) $paper['id'])
                ->count_all_results('onlineexam_question_snapshots');
            $unsupported_count = $this->db->where('onlineexam_id', (int) $onlineexam_id)
                ->where('revision', (int) $exam['revision'])
                ->where('paper_id', (int) $paper['id'])
                ->where_not_in('question_type', $allowed_question_types)
                ->count_all_results('onlineexam_question_snapshots');
            if ($question_count < 1 || $unsupported_count > 0) {
                return $this->failure('retired_response_type', 'The frozen paper contains no supported compact CBT questions.');
            }
            if ($paper['paper_type'] === 'objective'
                && $this->db->where('onlineexam_id', (int) $onlineexam_id)
                    ->where('revision', (int) $exam['revision'])
                    ->where('paper_id', (int) $paper['id'])
                    ->where('question_type', 'long_answer')
                    ->count_all_results('onlineexam_question_snapshots') > 0) {
                return $this->failure('retired_response_type', 'Objective papers cannot contain Theory questions.');
            }
        }
        if ($active_papers < 1) {
            return $this->failure('papers_missing', 'The frozen assessment does not contain any active papers.');
        }
        if ($create_makeup) {
            return $this->failure('makeup_attempts_retired', 'Void the existing attempt before preparing an authorized replacement.');
        }

        $this->db->trans_begin();
        // Serialize candidate-level attempt allocation so two marker screens
        // cannot create duplicate official attempts.
        $locked_candidate = $this->db->query(
            'SELECT * FROM `onlineexam_students` WHERE `id` = '
            . $this->db->escape((int) $onlineexam_student_id)
            . ' AND `onlineexam_id` = ' . $this->db->escape((int) $onlineexam_id) . ' FOR UPDATE'
        )->row_array();
        if (empty($locked_candidate) || $locked_candidate['candidate_status'] !== 'assigned') {
            $this->db->trans_rollback();
            return $this->failure('candidate_not_assigned', 'The candidate assignment changed before the attempt could be created.');
        }

        $attempts = $this->db->where('onlineexam_student_id', (int) $onlineexam_student_id)
            ->where('status !=', 'voided')
            ->order_by('attempt_no', 'DESC')
            ->get('onlineexam_candidate_attempts')
            ->result_array();
        $latest = empty($attempts) ? null : $attempts[0];
        $accommodation = $this->db->where('onlineexam_id', (int) $onlineexam_id)
            ->where('onlineexam_student_id', (int) $onlineexam_student_id)
            ->limit(1)
            ->get('onlineexam_accommodations')
            ->row_array();
        // Existing work is always returned. Staff must void it (with a full
        // audit/reversal) before a replacement attempt can be created.
        if (!empty($latest)) {
            $this->ensureAttemptPaperRows($latest['id'], $papers);
            if ($this->db->trans_status() === false) {
                $this->db->trans_rollback();
                return $this->failure('database_error', 'The official paper attempt could not be prepared.');
            }
            $this->db->trans_commit();
            return array('success' => true, 'attempt' => $latest, 'idempotent' => true);
        }
        if (count($attempts) >= 1) {
            $this->db->trans_rollback();
            return $this->failure('attempt_limit_reached', 'No authorized official attempts remain for this candidate.');
        }

        $max_attempt = $this->db->select_max('attempt_no', 'number')
            ->where('onlineexam_student_id', (int) $onlineexam_student_id)
            ->get('onlineexam_candidate_attempts')
            ->row_array();
        $attempt_no = empty($max_attempt['number']) ? 1 : ((int) $max_attempt['number'] + 1);
        $now = date('Y-m-d H:i:s');
        $deadline = !empty($exam['exam_to']) ? $exam['exam_to'] : $now;
        $payload = array(
            'onlineexam_id' => (int) $onlineexam_id,
            'onlineexam_student_id' => (int) $onlineexam_student_id,
            'revision' => (int) $exam['revision'],
            'attempt_no' => $attempt_no,
            'status' => 'in_progress',
            'started_at' => $now,
            'deadline_at' => $deadline,
            'submission_key' => bin2hex(random_bytes(32)),
            'extra_time_minutes' => empty($accommodation) ? 0 : (int) $accommodation['extra_time_minutes'],
            'manual_marking_required' => 0,
            'marking_status' => 'not_required',
            'created_at' => $now,
            'updated_at' => $now,
        );
        $this->db->insert('onlineexam_candidate_attempts', $payload);
        $attempt_id = (int) $this->db->insert_id();
        $this->ensureAttemptPaperRows($attempt_id, $papers);
        $this->audit($onlineexam_id, $attempt_id, $actor_id, 'create_official_paper_attempt', 'onlineexam_candidate_attempts', $attempt_id, null, array_merge(array('id' => $attempt_id), $payload));
        if ($this->db->trans_status() === false) {
            $this->db->trans_rollback();
            return $this->failure('database_error', 'The official paper attempt could not be created.');
        }
        $this->db->trans_commit();
        $this->load->model('onlineexamattempt_model');
        $this->onlineexamattempt_model->refreshAssessmentLifecycle((int) $onlineexam_id);
        $payload['id'] = $attempt_id;
        return array('success' => true, 'attempt' => $payload, 'idempotent' => false);
    }

    /**
     * Stores a versioned, finalized total raw score for a paper-delivered
     * paper. Migration 128 must include onlineexam_paper_marking; when
     * it does not, this method fails explicitly rather than writing an
     * unversioned aggregate that finalizeAttempt would later overwrite.
     */
    public function saveManualPaperScore($onlineexam_id, $attempt_id, $paper_id, $raw_marks, $rubric, $remark, $status, $actor_id, array $scope = array())
    {
        return $this->failure('offline_paper_marking_retired', 'Offline paper-score entry is retired; mark individual Theory answers instead.');

        if (!$this->db->table_exists('onlineexam_paper_marking')) {
            return array(
                'success' => false,
                'code' => 'missing_paper_marking_schema',
                'errors' => array('The versioned paper-marking table is not installed. Apply the completed migration before entering paper raw scores.'),
            );
        }
        $access = $this->resolveScope($onlineexam_id, $scope);
        if (!$access['success']) {
            return $access;
        }
        $status = strtolower(trim((string) $status));
        if (!in_array($status, $this->mark_statuses, true)) {
            return $this->failure('invalid_mark_status', 'Paper mark status must be draft or finalized.');
        }
        if (!is_numeric($raw_marks)) {
            return $this->failure('invalid_paper_mark', 'The paper raw mark must be numeric.');
        }
        $raw_marks = round((float) $raw_marks, 2);
        $remark = trim((string) $remark);
        if (mb_strlen($remark) > 5000) {
            return $this->failure('remark_too_long', 'The paper-marking remark must not exceed 5,000 characters.');
        }
        $rubric_json = $this->normalizeJson($rubric);
        if ($rubric_json === false) {
            return $this->failure('invalid_rubric', 'Rubric data must be valid JSON or an array.');
        }

        $this->db->trans_begin();
        $attempt = $this->lockAttemptContext($onlineexam_id, $attempt_id);
        if (empty($attempt) || !$this->rowIsInScope($attempt, $scope, $access['section_ids'], 'id', 'section_id')) {
            $this->db->trans_rollback();
            return $this->failure('attempt_not_found', 'The attempt was not found in this assessment scope.');
        }
        if ($attempt['status'] === 'voided') {
            $this->db->trans_rollback();
            return $this->failure('attempt_not_markable', 'A voided attempt cannot receive a paper score.');
        }
        $paper = $this->findFrozenPaper($onlineexam_id, (int) $attempt['revision'], $paper_id);
        if (empty($paper) || $paper['delivery_mode'] !== 'paper') {
            $this->db->trans_rollback();
            return $this->failure('paper_not_markable', 'Only a frozen paper-delivered paper can receive a whole-paper manual score. Hybrid papers use per-answer marking.');
        }
        $maximum = round((float) $paper['raw_max_score'], 2);
        if ($raw_marks < 0 || $raw_marks > $maximum) {
            $this->db->trans_rollback();
            return $this->failure('paper_mark_out_of_range', 'The paper mark must be between 0 and ' . number_format($maximum, 2, '.', '') . '.');
        }

        $attempt_paper = $this->db->query(
            'SELECT * FROM `onlineexam_attempt_papers` WHERE `attempt_id` = '
            . $this->db->escape((int) $attempt_id) . ' AND `paper_id` = '
            . $this->db->escape((int) $paper_id) . ' FOR UPDATE'
        )->row_array();
        if (empty($attempt_paper)) {
            $this->ensureAttemptPaperRows($attempt_id, array($paper));
            $attempt_paper = $this->db->where('attempt_id', (int) $attempt_id)
                ->where('paper_id', (int) $paper_id)
                ->limit(1)
                ->get('onlineexam_attempt_papers')
                ->row_array();
        }

        $latest = $this->db->where('attempt_paper_id', (int) $attempt_paper['id'])
            ->order_by('marking_version', 'DESC')
            ->limit(1)
            ->get('onlineexam_paper_marking')
            ->row_array();
        if (!empty($latest)
            && abs((float) $latest['raw_marks'] - $raw_marks) < 0.00001
            && (string) $latest['rubric_json'] === (string) $rubric_json
            && (string) $latest['remark'] === $remark
            && $latest['status'] === $status) {
            $this->db->trans_commit();
            $result = array('success' => true, 'paper_marking_id' => (int) $latest['id'], 'version' => (int) $latest['marking_version'], 'idempotent' => true);
            return $status === 'finalized'
                ? $this->tryFinalizeIfReady($onlineexam_id, $attempt_id, $actor_id, $scope, $result)
                : $result;
        }

        $version = empty($latest) ? 1 : ((int) $latest['marking_version'] + 1);
        $now = date('Y-m-d H:i:s');
        $record = array(
            'attempt_paper_id' => (int) $attempt_paper['id'],
            'marking_version' => $version,
            'raw_marks' => $raw_marks,
            'rubric_json' => $rubric_json,
            'remark' => $remark === '' ? null : $remark,
            'status' => $status,
            'marked_by' => (int) $actor_id,
            'marked_at' => $now,
            'reviewed_by' => $status === 'finalized' ? (int) $actor_id : null,
            'reviewed_at' => $status === 'finalized' ? $now : null,
            'created_at' => $now,
        );
        $this->db->insert('onlineexam_paper_marking', $record);
        $paper_marking_id = (int) $this->db->insert_id();

        $paper_updates = array(
            'manual_score' => $raw_marks,
            'manual_marking_status' => $status,
            'marked_by' => (int) $actor_id,
            'marked_at' => $now,
            'marking_notes' => $remark === '' ? null : $remark,
            'updated_at' => $now,
        );
        if ($status === 'finalized') {
            $contribution_maximum = (float) $paper['contribution_score'];
            $weighted = $maximum > 0 ? round(($raw_marks / $maximum) * $contribution_maximum, 4) : 0;
            $paper_updates = array_merge($paper_updates, array(
                'status' => 'submitted',
                'submitted_at' => $now,
                'raw_score' => $raw_marks,
                'raw_max_score' => $maximum,
                'contribution_score' => $weighted,
                'contribution_max_score' => $contribution_maximum,
            ));
        }
        $this->db->where('id', (int) $attempt_paper['id'])->update('onlineexam_attempt_papers', $paper_updates);
        $this->db->where('id', (int) $attempt_id)->update('onlineexam_candidate_attempts', array(
            'status' => $attempt['status'] === 'completed' ? 'marking' : $attempt['status'],
            'manual_marking_required' => 1,
            'marking_status' => 'pending',
            'updated_at' => $now,
        ));
        $this->refreshAttemptSubmissionState(
            $attempt_id,
            $this->getFrozenPapers($onlineexam_id, (int) $attempt['revision']),
            $now
        );
        $this->audit($onlineexam_id, $attempt_id, $actor_id, 'save_manual_paper_score', 'onlineexam_attempt_papers', $attempt_paper['id'], $attempt_paper, array_merge($attempt_paper, array(
            'paper_marking_id' => $paper_marking_id,
            'marking_version' => $version,
            'raw_score' => $raw_marks,
            'marking_status' => $status,
        )));
        if ($this->db->trans_status() === false) {
            $this->db->trans_rollback();
            return $this->failure('database_error', 'The paper score could not be saved.');
        }
        $this->db->trans_commit();
        $result = array('success' => true, 'paper_marking_id' => $paper_marking_id, 'version' => $version, 'idempotent' => false);
        return $status === 'finalized'
            ? $this->tryFinalizeIfReady($onlineexam_id, $attempt_id, $actor_id, $scope, $result)
            : $result;
    }

    public function upsertAccommodation($onlineexam_id, $onlineexam_student_id, array $data, $actor_id, array $scope = array())
    {
        $candidate = $this->candidateContext($onlineexam_id, $onlineexam_student_id, $scope);
        if (!$candidate['success']) {
            return $candidate;
        }

        $extra_time = isset($data['extra_time_minutes']) ? (int) $data['extra_time_minutes'] : 0;
        $makeups = isset($data['makeup_attempts']) ? (int) $data['makeup_attempts'] : 0;
        $makeup_expires_at = isset($data['makeup_expires_at']) && trim((string) $data['makeup_expires_at']) !== ''
            ? date('Y-m-d H:i:s', strtotime($data['makeup_expires_at'])) : null;
        $notes = isset($data['notes']) ? trim((string) $data['notes']) : null;
        if ($extra_time < 0 || $extra_time > 1440) {
            return $this->failure('invalid_extra_time', 'Extra time must be between 0 and 1,440 minutes.');
        }
        if ($makeups !== 0 || $makeup_expires_at !== null) {
            return $this->failure('makeup_attempts_retired', 'Make-up counters are retired. Void an attempt before creating its audited replacement.');
        }
        if ($notes !== null && mb_strlen($notes) > 5000) {
            return $this->failure('notes_too_long', 'Accommodation notes must not exceed 5,000 characters.');
        }

        $this->db->trans_begin();
        $existing = $this->db->query(
            'SELECT * FROM `onlineexam_accommodations` WHERE `onlineexam_id` = '
            . $this->db->escape((int) $onlineexam_id) . ' AND `onlineexam_student_id` = '
            . $this->db->escape((int) $onlineexam_student_id) . ' FOR UPDATE'
        )->row_array();
        $now = date('Y-m-d H:i:s');
        $payload = array(
            'onlineexam_id' => (int) $onlineexam_id,
            'onlineexam_student_id' => (int) $onlineexam_student_id,
            'extra_time_minutes' => $extra_time,
            'makeup_attempts' => 0,
            'makeup_expires_at' => null,
            'notes' => $notes === '' ? null : $notes,
            'authorized_by' => (int) $actor_id,
            'updated_at' => $now,
        );

        if (!empty($existing) && $this->sameAccommodation($existing, $payload)) {
            $this->db->where('onlineexam_student_id', (int) $onlineexam_student_id)
                ->where('status', 'in_progress')
                ->update('onlineexam_candidate_attempts', array('extra_time_minutes' => $extra_time, 'updated_at' => $now));
            $this->db->trans_commit();
            return array('success' => true, 'id' => (int) $existing['id'], 'idempotent' => true, 'applies_to_future_papers' => true);
        }
        if (empty($existing)) {
            $payload['created_at'] = $now;
            $this->db->insert('onlineexam_accommodations', $payload);
            $id = (int) $this->db->insert_id();
            $before = null;
        } else {
            $id = (int) $existing['id'];
            $this->db->where('id', $id)->update('onlineexam_accommodations', $payload);
            $before = $existing;
        }
        $after = array_merge(empty($existing) ? array('id' => $id, 'created_at' => $now) : $existing, $payload);
        // Paper attempts may be prepared before accommodations are entered.
        // Keep the active attempt's captured allowance current; pending papers
        // will use it when the candidate actually opens them.
        $this->db->where('onlineexam_student_id', (int) $onlineexam_student_id)
            ->where('status', 'in_progress')
            ->update('onlineexam_candidate_attempts', array('extra_time_minutes' => $extra_time, 'updated_at' => $now));
        $this->audit($onlineexam_id, null, $actor_id, 'upsert_accommodation', 'onlineexam_accommodations', $id, $before, $after);

        if ($this->db->trans_status() === false) {
            $this->db->trans_rollback();
            return $this->failure('database_error', 'The accommodation could not be saved.');
        }
        $this->db->trans_commit();
        return array('success' => true, 'id' => $id, 'idempotent' => false, 'applies_to_future_papers' => true);
    }

    public function createIncident($onlineexam_id, array $data, $actor_id, array $scope = array())
    {
        $access = $this->resolveScope($onlineexam_id, $scope);
        if (!$access['success']) {
            return $access;
        }
        $type = isset($data['incident_type']) ? strtolower(trim((string) $data['incident_type'])) : '';
        $severity = isset($data['severity']) ? strtolower(trim((string) $data['severity'])) : 'info';
        $details = isset($data['details']) ? trim((string) $data['details']) : '';
        $attempt_id = !empty($data['attempt_id']) ? (int) $data['attempt_id'] : null;
        $candidate_id = !empty($data['onlineexam_student_id']) ? (int) $data['onlineexam_student_id'] : null;
        if (!preg_match('/^[a-z0-9][a-z0-9_-]{1,49}$/', $type)) {
            return $this->failure('invalid_incident_type', 'Incident type must be a short machine-readable label.');
        }
        if (!in_array($severity, $this->incident_severities, true)) {
            return $this->failure('invalid_severity', 'Incident severity must be info, warning, or critical.');
        }
        if ($details === '' || mb_strlen($details) > 20000) {
            return $this->failure('invalid_details', 'Incident details are required and must not exceed 20,000 characters.');
        }

        $context = $this->validateIncidentContext($onlineexam_id, $attempt_id, $candidate_id, $scope);
        if (!$context['success']) {
            return $context;
        }
        if ($candidate_id === null && !empty($context['onlineexam_student_id'])) {
            $candidate_id = (int) $context['onlineexam_student_id'];
        }

        $this->db->trans_begin();
        $existing_query = $this->db->where('onlineexam_id', (int) $onlineexam_id)
            ->where('incident_type', $type)
            ->where('severity', $severity)
            ->where('details', $details)
            ->where('status', 'open');
        $attempt_id === null ? $existing_query->where('attempt_id IS NULL', null, false) : $existing_query->where('attempt_id', $attempt_id);
        $candidate_id === null ? $existing_query->where('onlineexam_student_id IS NULL', null, false) : $existing_query->where('onlineexam_student_id', $candidate_id);
        $existing = $existing_query->limit(1)->get('onlineexam_incidents')->row_array();
        if (!empty($existing)) {
            $this->db->trans_commit();
            return array('success' => true, 'id' => (int) $existing['id'], 'idempotent' => true);
        }

        $now = date('Y-m-d H:i:s');
        $payload = array(
            'onlineexam_id' => (int) $onlineexam_id,
            'attempt_id' => $attempt_id,
            'onlineexam_student_id' => $candidate_id,
            'incident_type' => $type,
            'severity' => $severity,
            'details' => $details,
            'status' => 'open',
            'reported_by' => $actor_id === null ? null : (int) $actor_id,
            'created_at' => $now,
            'updated_at' => $now,
        );
        $this->db->insert('onlineexam_incidents', $payload);
        $id = (int) $this->db->insert_id();
        $this->audit($onlineexam_id, $attempt_id, $actor_id, 'create_incident', 'onlineexam_incidents', $id, null, array_merge(array('id' => $id), $payload));
        if ($this->db->trans_status() === false) {
            $this->db->trans_rollback();
            return $this->failure('database_error', 'The incident could not be recorded.');
        }
        $this->db->trans_commit();
        return array('success' => true, 'id' => $id, 'idempotent' => false);
    }

    public function resolveIncident($onlineexam_id, $incident_id, $resolution_note, $actor_id, array $scope = array())
    {
        $access = $this->resolveScope($onlineexam_id, $scope);
        if (!$access['success']) {
            return $access;
        }
        $resolution_note = trim((string) $resolution_note);
        if ($resolution_note === '' || mb_strlen($resolution_note) > 5000) {
            return $this->failure('invalid_resolution', 'A resolution note of no more than 5,000 characters is required.');
        }

        $this->db->trans_begin();
        $incident = $this->db->query(
            'SELECT i.*, COALESCE(i.onlineexam_student_id, a.onlineexam_student_id) AS context_student_id, ss.section_id '
            . 'FROM `onlineexam_incidents` i '
            . 'LEFT JOIN `onlineexam_candidate_attempts` a ON a.id = i.attempt_id '
            . 'LEFT JOIN `onlineexam_students` os ON os.id = COALESCE(i.onlineexam_student_id, a.onlineexam_student_id) '
            . 'LEFT JOIN `student_session` ss ON ss.id = os.student_session_id '
            . 'WHERE i.id = ' . $this->db->escape((int) $incident_id)
            . ' AND i.onlineexam_id = ' . $this->db->escape((int) $onlineexam_id) . ' FOR UPDATE'
        )->row_array();
        $exam_wide = !empty($incident) && empty($incident['context_student_id']);
        $incident_in_scope = $exam_wide
            ? !isset($scope['attempt_ids']) && !isset($scope['student_session_ids'])
            : (!empty($incident) && $this->rowIsInScope($incident, $scope, $access['section_ids'], 'attempt_id', 'section_id'));
        if (empty($incident) || !$incident_in_scope) {
            $this->db->trans_rollback();
            return $this->failure('incident_not_found', 'The incident was not found in this assessment scope.');
        }
        if ($incident['status'] === 'resolved') {
            $this->db->trans_commit();
            return array('success' => true, 'id' => (int) $incident['id'], 'idempotent' => true);
        }

        $now = date('Y-m-d H:i:s');
        $updates = array(
            'status' => 'resolved',
            'resolved_by' => $actor_id === null ? null : (int) $actor_id,
            'resolved_at' => $now,
            'updated_at' => $now,
        );
        $this->db->where('id', (int) $incident_id)->where('onlineexam_id', (int) $onlineexam_id)->update('onlineexam_incidents', $updates);
        $after = array_merge($incident, $updates, array('resolution_note' => $resolution_note));
        $this->audit($onlineexam_id, $incident['attempt_id'], $actor_id, 'resolve_incident', 'onlineexam_incidents', $incident_id, $incident, $after);
        if ($this->db->trans_status() === false) {
            $this->db->trans_rollback();
            return $this->failure('database_error', 'The incident could not be resolved.');
        }
        $this->db->trans_commit();
        return array('success' => true, 'id' => (int) $incident_id, 'idempotent' => false);
    }

    public function voidAttempt($onlineexam_id, $attempt_id, $reason, $actor_id, array $scope = array())
    {
        $access = $this->resolveScope($onlineexam_id, $scope);
        if (!$access['success']) {
            return $access;
        }
        $reason = trim((string) $reason);
        if ($reason === '' || mb_strlen($reason) > 5000) {
            return $this->failure('invalid_void_reason', 'A void reason of no more than 5,000 characters is required.');
        }

        $this->db->trans_begin();
        $attempt = $this->lockAttemptContext($onlineexam_id, $attempt_id);
        if (empty($attempt) || !$this->rowIsInScope($attempt, $scope, $access['section_ids'], 'id', 'section_id')) {
            $this->db->trans_rollback();
            return $this->failure('attempt_not_found', 'The attempt was not found in this assessment scope.');
        }
        if ($attempt['status'] === 'voided') {
            $this->db->trans_commit();
            return array('success' => true, 'attempt_id' => (int) $attempt_id, 'idempotent' => true);
        }

        $posted = $this->db->where('attempt_id', (int) $attempt_id)
            ->where('status', 'posted')
            ->order_by('id', 'ASC')
            ->get('onlineexam_result_sync')
            ->result_array();
        if (!empty($posted)) {
            $this->db->trans_rollback();
            $this->load->model('onlineexamresultsync_model');
            return $this->onlineexamresultsync_model->reverseAndVoidAttempt(
                $onlineexam_id,
                $attempt_id,
                $reason,
                $actor_id
            );
        }

        $now = date('Y-m-d H:i:s');
        $updates = array(
            'status' => 'voided',
            'voided_at' => $now,
            'voided_by' => $actor_id === null ? null : (int) $actor_id,
            'void_reason' => $reason,
            'updated_at' => $now,
        );
        $this->db->where('id', (int) $attempt_id)->where('onlineexam_id', (int) $onlineexam_id)->update('onlineexam_candidate_attempts', $updates);
        $this->db->where('attempt_id', (int) $attempt_id)
            ->where_in('status', array('pending', 'in_progress', 'submitted', 'completed'))
            ->update('onlineexam_attempt_papers', array('status' => 'voided', 'updated_at' => $now));

        $remaining = $this->db->where('onlineexam_student_id', (int) $attempt['onlineexam_student_id'])
            ->where('status !=', 'voided')
            ->where_in('status', array('submitted', 'timed_out', 'marking', 'completed'))
            ->count_all_results('onlineexam_candidate_attempts');
        if ($remaining === 0) {
            $this->db->where('id', (int) $attempt['onlineexam_student_id'])->update('onlineexam_students', array('is_attempted' => 0));
        }
        $this->audit($onlineexam_id, $attempt_id, $actor_id, 'void_attempt', 'onlineexam_candidate_attempts', $attempt_id, $attempt, array_merge($attempt, $updates));

        if ($this->db->trans_status() === false) {
            $this->db->trans_rollback();
            return $this->failure('database_error', 'The attempt could not be voided.');
        }
        $this->db->trans_commit();
        return array('success' => true, 'attempt_id' => (int) $attempt_id, 'idempotent' => false);
    }

    public function markingQueue($onlineexam_id, array $filters = array(), array $scope = array())
    {
        if (!isset($filters['status'])) {
            $filters['status'] = array('submitted', 'timed_out', 'marking');
        }
        $attempts = $this->listAttempts($onlineexam_id, $filters, $scope);
        if (!$attempts['success']) {
            return $attempts;
        }

        if (empty($filters['include_ready'])) {
            $attempts['rows'] = array_values(array_filter($attempts['rows'], function ($row) {
                return (int) $row['manual_marking_required'] === 1
                    || (int) $row['pending_manual_answers'] > 0
                    || (int) $row['pending_manual_papers'] > 0;
            }));
        }
        return $attempts;
    }

    public function attemptMarkingDetail($onlineexam_id, $attempt_id, array $scope = array())
    {
        $access = $this->resolveScope($onlineexam_id, $scope);
        if (!$access['success']) {
            return $access;
        }
        $attempt = $this->locklessAttemptContext($onlineexam_id, $attempt_id);
        if (empty($attempt) || !$this->rowIsInScope($attempt, $scope, $access['section_ids'], 'id', 'section_id')) {
            return $this->failure('attempt_not_found', 'The attempt was not found in this assessment scope.');
        }

        $answers = $this->db
            ->select('aa.*, qs.question_text, qs.question_type, qs.marks AS question_max, qs.marking_scheme, qs.paper_id, qs.paper_section_id')
            ->from('onlineexam_attempt_answers aa')
            ->join('onlineexam_question_snapshots qs', 'qs.id = aa.question_snapshot_id')
            ->where('aa.attempt_id', (int) $attempt_id)
            ->where('aa.is_answered', 1)
            ->where('aa.auto_mark IS NULL', null, false)
            ->order_by('qs.paper_id', 'ASC')
            ->order_by('qs.display_order', 'ASC')
            ->get()
            ->result_array();
        $frozen_papers = array();
        foreach ($this->getFrozenPapers($onlineexam_id, (int) $attempt['revision']) as $paper) {
            $frozen_papers[(int) $paper['id']] = $paper;
        }
        foreach ($answers as $key => $answer) {
            $frozen = isset($frozen_papers[(int) $answer['paper_id']]) ? $frozen_papers[(int) $answer['paper_id']] : array();
            $answers[$key]['paper_title'] = isset($frozen['title']) ? $frozen['title'] : 'Frozen paper #' . (int) $answer['paper_id'];
            $answers[$key]['paper_display_order'] = isset($frozen['display_order']) ? (int) $frozen['display_order'] : PHP_INT_MAX;
            $answers[$key]['response'] = $this->decodeJson($answer['response_json']);
            $answers[$key]['marking_scheme_data'] = $this->decodeJson($answer['marking_scheme']);
            $answers[$key]['history'] = $this->db->where('attempt_answer_id', (int) $answer['id'])
                ->order_by('marking_version', 'DESC')
                ->get('onlineexam_marking')
                ->result_array();
        }
        usort($answers, function ($left, $right) {
            if ($left['paper_display_order'] === $right['paper_display_order']) {
                return (int) $left['question_snapshot_id'] <=> (int) $right['question_snapshot_id'];
            }
            return $left['paper_display_order'] <=> $right['paper_display_order'];
        });

        $paper_rows = array();
        foreach ($this->getFrozenPapers($onlineexam_id, (int) $attempt['revision']) as $paper) {
            if ($paper['delivery_mode'] !== 'paper') {
                continue;
            }
            $attempt_paper = $this->db->where('attempt_id', (int) $attempt_id)
                ->where('paper_id', (int) $paper['id'])
                ->limit(1)
                ->get('onlineexam_attempt_papers')
                ->row_array();
            $history = array();
            if ($this->db->table_exists('onlineexam_paper_marking') && !empty($attempt_paper)) {
                $history = $this->db->where('attempt_paper_id', (int) $attempt_paper['id'])
                    ->order_by('marking_version', 'DESC')
                    ->get('onlineexam_paper_marking')
                    ->result_array();
            }
            $paper_rows[] = array('paper' => $paper, 'attempt_paper' => $attempt_paper, 'history' => $history);
        }
        return array('success' => true, 'attempt' => $attempt, 'answers' => $answers, 'papers' => $paper_rows);
    }

    public function markingDetail($onlineexam_id, $attempt_answer_id, array $scope = array())
    {
        $access = $this->resolveScope($onlineexam_id, $scope);
        if (!$access['success']) {
            return $access;
        }

        $answer = $this->answerContext($onlineexam_id, $attempt_answer_id, false);
        if (empty($answer) || !$this->rowIsInScope($answer, $scope, $access['section_ids'], 'attempt_id', 'section_id')) {
            return $this->failure('answer_not_found', 'The answer was not found in this assessment scope.');
        }
        $history = $this->db->where('attempt_answer_id', (int) $attempt_answer_id)
            ->order_by('marking_version', 'DESC')
            ->get('onlineexam_marking')
            ->result_array();
        foreach ($history as $key => $mark) {
            $history[$key]['rubric'] = $this->decodeJson($mark['rubric_json']);
        }
        $answer['response'] = $this->decodeJson($answer['response_json']);
        $answer['marking_scheme_data'] = $this->decodeJson($answer['marking_scheme']);
        return array('success' => true, 'answer' => $answer, 'history' => $history);
    }

    public function saveManualMark($onlineexam_id, $attempt_answer_id, $marks, $rubric, $remark, $status, $actor_id, array $scope = array())
    {
        $access = $this->resolveScope($onlineexam_id, $scope);
        if (!$access['success']) {
            return $access;
        }
        $status = strtolower(trim((string) $status));
        if (!in_array($status, $this->mark_statuses, true)) {
            return $this->failure('invalid_mark_status', 'Manual mark status must be draft or finalized.');
        }
        if (!is_numeric($marks)) {
            return $this->failure('invalid_mark', 'The mark must be numeric.');
        }
        $marks = round((float) $marks, 2);
        $remark = trim((string) $remark);
        if (mb_strlen($remark) > 5000) {
            return $this->failure('remark_too_long', 'The marking remark must not exceed 5,000 characters.');
        }
        $rubric_json = $this->normalizeJson($rubric);
        if ($rubric_json === false) {
            return $this->failure('invalid_rubric', 'Rubric data must be valid JSON or an array.');
        }

        $this->db->trans_begin();
        $answer = $this->answerContext($onlineexam_id, $attempt_answer_id, true);
        if (empty($answer) || !$this->rowIsInScope($answer, $scope, $access['section_ids'], 'attempt_id', 'section_id')) {
            $this->db->trans_rollback();
            return $this->failure('answer_not_found', 'The answer was not found in this assessment scope.');
        }
        if (!in_array($answer['attempt_status'], array('submitted', 'timed_out', 'marking', 'completed'), true)) {
            $this->db->trans_rollback();
            return $this->failure('attempt_not_submitted', 'Manual marking can begin only after the candidate submits the attempt.');
        }
        if ((int) $answer['is_answered'] !== 1) {
            $this->db->trans_rollback();
            return $this->failure('unanswered_question', 'An unanswered question already scores zero and does not require a manual mark.');
        }
        if ($answer['auto_mark'] !== null || $answer['marking_status'] === 'auto_marked') {
            $this->db->trans_rollback();
            return $this->failure('objective_answer', 'Auto-marked objective answers cannot be changed through manual marking.');
        }
        $maximum = round((float) $answer['question_max'], 2);
        if ($marks < 0 || $marks > $maximum) {
            $this->db->trans_rollback();
            return $this->failure('mark_out_of_range', 'The mark must be between 0 and ' . number_format($maximum, 2, '.', '') . '.');
        }

        $latest = $this->db->where('attempt_answer_id', (int) $attempt_answer_id)
            ->order_by('marking_version', 'DESC')
            ->limit(1)
            ->get('onlineexam_marking')
            ->row_array();
        if (!empty($latest)
            && abs((float) $latest['marks'] - $marks) < 0.00001
            && (string) $latest['rubric_json'] === (string) $rubric_json
            && (string) $latest['remark'] === $remark
            && $latest['status'] === $status) {
            $this->db->trans_commit();
            $result = array(
                'success' => true,
                'marking_id' => (int) $latest['id'],
                'version' => (int) $latest['marking_version'],
                'idempotent' => true,
            );
            return $status === 'finalized'
                ? $this->tryFinalizeIfReady($onlineexam_id, (int) $answer['attempt_id'], $actor_id, $scope, $result)
                : $result;
        }

        $version = empty($latest) ? 1 : ((int) $latest['marking_version'] + 1);
        $now = date('Y-m-d H:i:s');
        $record = array(
            'attempt_answer_id' => (int) $attempt_answer_id,
            'marking_version' => $version,
            'marks' => $marks,
            'rubric_json' => $rubric_json,
            'remark' => $remark === '' ? null : $remark,
            'status' => $status,
            'marked_by' => (int) $actor_id,
            'marked_at' => $now,
            'reviewed_by' => $status === 'finalized' ? (int) $actor_id : null,
            'reviewed_at' => $status === 'finalized' ? $now : null,
            'created_at' => $now,
        );
        $this->db->insert('onlineexam_marking', $record);
        $marking_id = (int) $this->db->insert_id();

        $answer_updates = array(
            'manual_mark' => $marks,
            'final_mark' => $marks,
            'marking_status' => $status === 'finalized' ? 'finalized' : 'awaiting_manual',
            'version' => (int) $answer['version'] + 1,
            'updated_at' => $now,
        );
        $this->db->where('id', (int) $attempt_answer_id)->update('onlineexam_attempt_answers', $answer_updates);
        $this->db->where('id', (int) $answer['attempt_id'])->update('onlineexam_candidate_attempts', array(
            'status' => 'marking',
            'manual_marking_required' => 1,
            'marking_status' => 'pending',
            'updated_at' => $now,
        ));
        $this->audit($onlineexam_id, $answer['attempt_id'], $actor_id, 'save_manual_mark', 'onlineexam_attempt_answers', $attempt_answer_id, $answer, array_merge($answer, $answer_updates, array('marking_id' => $marking_id, 'marking_version' => $version)));

        if ($this->db->trans_status() === false) {
            $this->db->trans_rollback();
            return $this->failure('database_error', 'The manual mark could not be saved.');
        }
        $this->db->trans_commit();
        $result = array('success' => true, 'marking_id' => $marking_id, 'version' => $version, 'idempotent' => false);
        return $status === 'finalized'
            ? $this->tryFinalizeIfReady($onlineexam_id, (int) $answer['attempt_id'], $actor_id, $scope, $result)
            : $result;
    }

    /**
     * Finalizes an attempt after every answered manual question has a finalized
     * mark, recalculates it, and immediately invokes the configured result
     * adapter. Result-card publication is intentionally not touched.
     */
    public function finalizeManualMarking($onlineexam_id, $attempt_id, $actor_id, array $scope = array())
    {
        $access = $this->resolveScope($onlineexam_id, $scope);
        if (!$access['success']) {
            return $access;
        }

        $this->db->trans_begin();
        $attempt = $this->lockAttemptContext($onlineexam_id, $attempt_id);
        if (empty($attempt) || !$this->rowIsInScope($attempt, $scope, $access['section_ids'], 'id', 'section_id')) {
            $this->db->trans_rollback();
            return $this->failure('attempt_not_found', 'The attempt was not found in this assessment scope.');
        }
        if ($attempt['status'] === 'voided') {
            $this->db->trans_rollback();
            return $this->failure('attempt_voided', 'A voided attempt cannot be finalized.');
        }
        if (!in_array($attempt['status'], array('submitted', 'timed_out', 'marking', 'completed'), true)) {
            $this->db->trans_rollback();
            return $this->failure('attempt_not_submitted', 'Only a submitted or timed-out attempt can be finalized.');
        }

        $paper_submission = $this->attemptPaperSubmissionReadiness($onlineexam_id, $attempt_id, (int) $attempt['revision']);
        if (!$paper_submission['ready']) {
            $this->db->trans_rollback();
            return array(
                'success' => false,
                'code' => 'papers_incomplete',
                'pending_papers' => $paper_submission['pending'],
                'errors' => array($paper_submission['pending'] . ' assessment paper(s) have not been submitted or entered.'),
            );
        }

        $manual_total = $this->db->from('onlineexam_attempt_answers')
            ->where('attempt_id', (int) $attempt_id)
            ->where('is_answered', 1)
            ->where('auto_mark IS NULL', null, false)
            ->count_all_results();
        $manual_pending = $this->db->from('onlineexam_attempt_answers')
            ->where('attempt_id', (int) $attempt_id)
            ->where('is_answered', 1)
            ->where('auto_mark IS NULL', null, false)
            ->where('marking_status !=', 'finalized')
            ->count_all_results();
        $paper_marking = $this->paperMarkingReadiness($onlineexam_id, $attempt_id, (int) $attempt['revision']);
        if (!$paper_marking['success']) {
            $this->db->trans_rollback();
            return $paper_marking;
        }
        if ($manual_pending > 0 || $paper_marking['pending'] > 0) {
            $this->db->where('id', (int) $attempt_id)->update('onlineexam_candidate_attempts', array(
                'status' => 'marking',
                'manual_marking_required' => 1,
                'marking_status' => 'pending',
                'updated_at' => date('Y-m-d H:i:s'),
            ));
            $this->db->trans_commit();
            $this->load->model('onlineexamattempt_model');
            $this->onlineexamattempt_model->refreshAssessmentLifecycle((int) $onlineexam_id);
            return array(
                'success' => false,
                'code' => 'marking_incomplete',
                'pending_marking' => true,
                'pending_answers' => $manual_pending,
                'pending_papers' => $paper_marking['pending'],
                'errors' => array(
                    $manual_pending . ' answered manual question(s) and '
                    . $paper_marking['pending'] . ' paper score(s) still require finalized marks.'
                ),
            );
        }

        $already_completed = $attempt['status'] === 'completed' && $attempt['marking_status'] === 'finalized';
        if (!$already_completed) {
            $updates = array(
                'manual_marking_required' => ($manual_total + $paper_marking['total']) > 0 ? 1 : 0,
                'marking_status' => 'finalized',
                'status' => $attempt['status'] === 'completed' ? 'completed' : 'marking',
                'updated_at' => date('Y-m-d H:i:s'),
            );
            $this->db->where('id', (int) $attempt_id)->update('onlineexam_candidate_attempts', $updates);
            $this->audit($onlineexam_id, $attempt_id, $actor_id, 'finalize_manual_marking', 'onlineexam_candidate_attempts', $attempt_id, $attempt, array_merge($attempt, $updates));
        }
        if ($this->db->trans_status() === false) {
            $this->db->trans_rollback();
            return $this->failure('database_error', 'Manual marking could not be finalized.');
        }
        $this->db->trans_commit();

        if (!$already_completed) {
            $this->load->model('onlineexamworkflow_model');
            $finalization = $this->onlineexamworkflow_model->finalizeAttempt((int) $attempt_id, $actor_id);
            if (empty($finalization['success'])) {
                return array(
                    'success' => false,
                    'attempt_finalized' => false,
                    'finalization' => $finalization,
                    'errors' => isset($finalization['errors']) ? $finalization['errors'] : array('Attempt calculation failed.'),
                );
            }
        } else {
            $finalization = array('success' => true, 'idempotent' => true);
        }

        $this->load->model('onlineexamresultsync_model');
        $sync = $this->onlineexamresultsync_model->syncCompletedAttempt((int) $attempt_id, $actor_id);
        $this->load->model('onlineexamattempt_model');
        $this->onlineexamattempt_model->refreshAssessmentLifecycle((int) $onlineexam_id);
        return array(
            'success' => !empty($sync['success']),
            'attempt_finalized' => true,
            'idempotent' => $already_completed && !empty($sync['idempotent']),
            'finalization' => $finalization,
            'result_sync' => $sync,
            'requires_conflict_resolution' => isset($sync['status']) && $sync['status'] === 'conflict',
            'errors' => empty($sync['success'])
                ? array(isset($sync['reason']) && $sync['reason'] ? $sync['reason'] : 'The attempt was finalized, but its result could not be posted automatically.')
                : array(),
        );
    }

    /**
     * A finalized mark is the normal trigger for completing a mixed or paper
     * assessment. The readiness checks in finalizeManualMarking are
     * authoritative, so calling this after every finalized save is safe and
     * also repairs a save/finalize interruption when the same mark is retried.
     */
    protected function tryFinalizeIfReady($onlineexam_id, $attempt_id, $actor_id, array $scope, array $save_result)
    {
        $automatic = $this->finalizeManualMarking($onlineexam_id, $attempt_id, $actor_id, $scope);
        $save_result['automatic_finalization'] = $automatic;
        $save_result['attempt_finalized'] = !empty($automatic['attempt_finalized']) || !empty($automatic['success']);
        $save_result['requires_conflict_resolution'] = !empty($automatic['requires_conflict_resolution']);

        // Saving the mark succeeded even when other papers/answers remain, or
        // when posting is deliberately held as a destination conflict. Surface
        // unexpected finalization failures as a warning without rolling back a
        // valid, versioned mark.
        $expected_pending_codes = array('attempt_not_submitted', 'papers_incomplete', 'marking_incomplete');
        if (empty($automatic['success'])
            && empty($automatic['pending_marking'])
            && empty($automatic['requires_conflict_resolution'])
            && !in_array(isset($automatic['code']) ? $automatic['code'] : '', $expected_pending_codes, true)) {
            $save_result['warnings'] = isset($automatic['errors'])
                ? (array) $automatic['errors']
                : array('The mark was saved, but automatic attempt finalization needs attention.');
        }
        return $save_result;
    }

    /**
     * Public helper for controllers that want to evaluate a scope before
     * rendering an operations route.
     */
    public function canManageExam($onlineexam_id, array $scope = array())
    {
        return $this->resolveScope($onlineexam_id, $scope);
    }

    /** Frozen-revision paper and item analysis for authorized staff. */
    public function assessmentAnalysis($onlineexam_id, array $scope = array())
    {
        $access = $this->resolveScope($onlineexam_id, $scope);
        if (!$access['success']) {
            return $access;
        }
        $paper_query = $this->db
            ->select("a.revision, ap.paper_id, COUNT(DISTINCT ap.attempt_id) AS candidates, "
                . "SUM(CASE WHEN ap.status IN ('submitted','completed') THEN 1 ELSE 0 END) AS submitted, "
                . "ROUND(AVG(CASE WHEN ap.status IN ('submitted','completed') THEN ap.raw_score END),2) AS average_raw_score, "
                . "ROUND(AVG(CASE WHEN ap.status IN ('submitted','completed') THEN ap.contribution_score END),2) AS average_contribution", false)
            ->from('onlineexam_attempt_papers ap')
            ->join('onlineexam_candidate_attempts a', 'a.id = ap.attempt_id')
            ->join('onlineexam_students os', 'os.id = a.onlineexam_student_id')
            ->join('student_session ss', 'ss.id = os.student_session_id')
            ->where('a.onlineexam_id', (int) $onlineexam_id)
            ->where('a.status !=', 'voided')
            ->group_by(array('a.revision', 'ap.paper_id'));
        $this->applySectionScope($paper_query, $access['section_ids'], 'ss.section_id');
        $paper_rows = $paper_query->get()->result_array();

        $question_query = $this->db
            ->select("a.revision, qs.id AS question_snapshot_id, qs.paper_id, qs.question_type, qs.question_text, qs.marks, "
                . "COUNT(DISTINCT ap.attempt_id) AS candidates, "
                . "SUM(CASE WHEN aa.is_answered = 1 THEN 1 ELSE 0 END) AS answered, "
                . "SUM(CASE WHEN aa.is_correct = 1 THEN 1 ELSE 0 END) AS correct, "
                . "SUM(CASE WHEN aa.is_correct = 0 THEN 1 ELSE 0 END) AS incorrect, "
                . "ROUND(AVG(CASE WHEN aa.id IS NOT NULL THEN aa.final_mark END),2) AS average_mark, "
                . "ROUND(CASE WHEN qs.marks > 0 AND COUNT(DISTINCT ap.attempt_id) > 0 "
                . "THEN (SUM(COALESCE(aa.final_mark,0)) / (qs.marks * COUNT(DISTINCT ap.attempt_id))) * 100 ELSE 0 END,2) AS facility_percent", false)
            ->from('onlineexam_candidate_attempts a')
            ->join('onlineexam_students os', 'os.id = a.onlineexam_student_id')
            ->join('student_session ss', 'ss.id = os.student_session_id')
            ->join('onlineexam_attempt_papers ap', 'ap.attempt_id = a.id')
            ->join('onlineexam_question_snapshots qs', 'qs.onlineexam_id = a.onlineexam_id AND qs.revision = a.revision AND qs.paper_id = ap.paper_id')
            ->join('onlineexam_attempt_answers aa', 'aa.attempt_id = a.id AND aa.question_snapshot_id = qs.id', 'left')
            ->where('a.onlineexam_id', (int) $onlineexam_id)
            ->where('a.status !=', 'voided')
            ->group_by(array('a.revision', 'qs.id'));
        $this->applySectionScope($question_query, $access['section_ids'], 'ss.section_id');
        $question_rows = $question_query->get()->result_array();

        $paper_maps = array();
        $revisions = array_unique(array_merge(array_column($paper_rows, 'revision'), array_column($question_rows, 'revision')));
        foreach ($revisions as $revision) {
            foreach ($this->getFrozenPapers($onlineexam_id, (int) $revision) as $paper) {
                $paper_maps[(int) $revision][(int) $paper['id']] = $paper;
            }
        }
        foreach ($paper_rows as &$row) {
            $paper = isset($paper_maps[(int) $row['revision']][(int) $row['paper_id']])
                ? $paper_maps[(int) $row['revision']][(int) $row['paper_id']] : array();
            $row['paper_title'] = isset($paper['title']) ? $paper['title'] : 'Frozen paper #' . (int) $row['paper_id'];
            $row['paper_type'] = isset($paper['paper_type']) ? $paper['paper_type'] : null;
            $row['raw_max_score'] = isset($paper['raw_max_score']) ? (float) $paper['raw_max_score'] : null;
            $row['contribution_max'] = isset($paper['contribution_score']) ? (float) $paper['contribution_score'] : null;
        }
        unset($row);
        foreach ($question_rows as &$row) {
            $paper = isset($paper_maps[(int) $row['revision']][(int) $row['paper_id']])
                ? $paper_maps[(int) $row['revision']][(int) $row['paper_id']] : array();
            $row['paper_title'] = isset($paper['title']) ? $paper['title'] : 'Frozen paper #' . (int) $row['paper_id'];
        }
        unset($row);
        return array('success' => true, 'papers' => $paper_rows, 'questions' => $question_rows);
    }

    protected function getFrozenPapers($onlineexam_id, $revision)
    {
        $snapshot = $this->db->select('configuration_json')
            ->where('onlineexam_id', (int) $onlineexam_id)
            ->where('revision', (int) $revision)
            ->limit(1)
            ->get('onlineexam_revision_snapshots')
            ->row_array();
        if (!empty($snapshot['configuration_json'])) {
            $configuration = json_decode($snapshot['configuration_json'], true);
            if (is_array($configuration) && isset($configuration['papers']) && is_array($configuration['papers'])) {
                return array_values(array_filter($configuration['papers'], function ($paper) {
                    return !isset($paper['is_active']) || (int) $paper['is_active'] === 1;
                }));
            }
        }
        return array();
    }

    protected function findFrozenPaper($onlineexam_id, $revision, $paper_id)
    {
        foreach ($this->getFrozenPapers($onlineexam_id, $revision) as $paper) {
            if ((int) $paper['id'] === (int) $paper_id) {
                return $paper;
            }
        }
        return null;
    }

    protected function ensureAttemptPaperRows($attempt_id, array $papers)
    {
        $now = date('Y-m-d H:i:s');
        foreach ($papers as $paper) {
            $exists = $this->db->where('attempt_id', (int) $attempt_id)
                ->where('paper_id', (int) $paper['id'])
                ->count_all_results('onlineexam_attempt_papers');
            if ($exists > 0) {
                continue;
            }
            $manual_status = $paper['delivery_mode'] === 'paper' ? 'pending' : 'not_required';
            $this->db->insert('onlineexam_attempt_papers', array(
                'attempt_id' => (int) $attempt_id,
                'paper_id' => (int) $paper['id'],
                'status' => 'pending',
                'raw_score' => 0,
                'raw_max_score' => (float) $paper['raw_max_score'],
                'contribution_score' => 0,
                'contribution_max_score' => (float) $paper['contribution_score'],
                'manual_marking_status' => $manual_status,
                'created_at' => $now,
                'updated_at' => $now,
            ));
        }
    }

    protected function refreshAttemptSubmissionState($attempt_id, array $papers, $now)
    {
        $paper_ids = array();
        foreach ($papers as $paper) {
            if (!isset($paper['is_active']) || (int) $paper['is_active'] === 1) {
                $paper_ids[] = (int) $paper['id'];
            }
        }
        if (empty($paper_ids)) {
            return;
        }
        $submitted = $this->db->where('attempt_id', (int) $attempt_id)
            ->where_in('paper_id', $paper_ids)
            ->where_in('status', array('submitted', 'completed'))
            ->count_all_results('onlineexam_attempt_papers');
        if ($submitted === count($paper_ids)) {
            $this->db->where('id', (int) $attempt_id)->update('onlineexam_candidate_attempts', array(
                'status' => 'submitted',
                'submitted_at' => $now,
                'updated_at' => $now,
            ));
        }
    }

    protected function attemptPaperSubmissionReadiness($onlineexam_id, $attempt_id, $revision)
    {
        $paper_ids = array_map(function ($paper) {
            return (int) $paper['id'];
        }, $this->getFrozenPapers($onlineexam_id, $revision));
        if (empty($paper_ids)) {
            return array('ready' => false, 'pending' => 1);
        }
        $ready = $this->db->where('attempt_id', (int) $attempt_id)
            ->where_in('paper_id', $paper_ids)
            ->where_in('status', array('submitted', 'completed'))
            ->count_all_results('onlineexam_attempt_papers');
        return array('ready' => $ready === count($paper_ids), 'pending' => max(0, count($paper_ids) - $ready));
    }

    /**
     * Paper-delivered papers require a finalized versioned whole-paper mark.
     * Hybrid papers always use answer-level marking so CBT work is preserved.
     */
    protected function paperMarkingReadiness($onlineexam_id, $attempt_id, $revision)
    {
        if (!$this->db->table_exists('onlineexam_paper_marking')) {
            return $this->failure('missing_paper_marking_schema', 'The versioned paper-marking table is not installed.');
        }
        $total = 0;
        $pending = 0;
        foreach ($this->getFrozenPapers($onlineexam_id, $revision) as $paper) {
            if ($paper['delivery_mode'] !== 'paper') {
                continue;
            }
            $attempt_paper = $this->db->where('attempt_id', (int) $attempt_id)
                ->where('paper_id', (int) $paper['id'])
                ->limit(1)
                ->get('onlineexam_attempt_papers')
                ->row_array();
            $latest = empty($attempt_paper) ? null : $this->db
                ->where('attempt_paper_id', (int) $attempt_paper['id'])
                ->order_by('marking_version', 'DESC')
                ->limit(1)
                ->get('onlineexam_paper_marking')
                ->row_array();
            $total++;
            if (empty($latest) || $latest['status'] !== 'finalized') {
                $pending++;
            }
        }
        return array('success' => true, 'total' => $total, 'pending' => $pending);
    }

    protected function resolveScope($onlineexam_id, array $scope)
    {
        $exam = $this->db->where('id', (int) $onlineexam_id)
            ->where('workflow_version', self::WORKFLOW_VERSION)
            ->limit(1)
            ->get('onlineexam')
            ->row_array();
        if (empty($exam)) {
            return $this->failure('assessment_not_found', 'The assessment was not found.');
        }
        $this->load->model('onlineexamattempt_model');
        if (!$this->onlineexamattempt_model->isSupportedAssessmentContext((object) $exam)) {
            return $this->failure('historical_assessment_read_only', 'This historical assessment is read-only.');
        }

        if (isset($scope['allowed_onlineexam_ids'])) {
            $allowed = array_map('intval', (array) $scope['allowed_onlineexam_ids']);
            if (!in_array((int) $onlineexam_id, $allowed, true)) {
                return $this->failure('assessment_out_of_scope', 'This assessment is outside the authorized scope.');
            }
        }

        $exam_sections = array_map('intval', array_column(
            $this->db->select('section_id')->where('onlineexam_id', (int) $onlineexam_id)->get('onlineexam_class_sections')->result_array(),
            'section_id'
        ));
        $section_ids = $exam_sections;

        if (!empty($scope['enforce_assignment']) && empty($scope['bypass_assignment'])) {
            if (empty($scope['staff_id'])) {
                return $this->failure('staff_scope_required', 'A staff identity is required to enforce teacher assignment scope.');
            }
            $teacher_query = $this->db->select('class_sections.section_id')
                ->distinct()
                ->from('teacher_subjects')
                ->join('class_sections', 'class_sections.id = teacher_subjects.class_section_id')
                ->where('teacher_subjects.teacher_id', (int) $scope['staff_id'])
                ->where('teacher_subjects.subject_id', (int) $exam['subject_id'])
                ->where('teacher_subjects.session_id', (int) $exam['session_id'])
                ->where('class_sections.class_id', (int) $exam['class_id']);
            if (!empty($exam_sections)) {
                $teacher_query->where_in('class_sections.section_id', $exam_sections);
            }
            $teacher_sections = array_map('intval', array_column($teacher_query->get()->result_array(), 'section_id'));
            if (empty($teacher_sections)) {
                return $this->failure('teacher_assignment_required', 'The staff member is not assigned to this assessment subject and class arm.');
            }
            $section_ids = $teacher_sections;
        }

        if (isset($scope['section_ids'])) {
            $requested = array_values(array_unique(array_map('intval', (array) $scope['section_ids'])));
            $section_ids = empty($section_ids) ? $requested : array_values(array_intersect($section_ids, $requested));
            if (empty($section_ids)) {
                return $this->failure('section_out_of_scope', 'No authorized class arm remains in this assessment scope.');
            }
        }

        return array('success' => true, 'exam' => $exam, 'section_ids' => $section_ids);
    }

    protected function candidateContext($onlineexam_id, $onlineexam_student_id, array $scope)
    {
        $access = $this->resolveScope($onlineexam_id, $scope);
        if (!$access['success']) {
            return $access;
        }
        $row = $this->db->select('os.*, ss.section_id, ss.class_id, ss.session_id')
            ->from('onlineexam_students os')
            ->join('student_session ss', 'ss.id = os.student_session_id')
            ->where('os.id', (int) $onlineexam_student_id)
            ->where('os.onlineexam_id', (int) $onlineexam_id)
            ->limit(1)
            ->get()
            ->row_array();
        if (empty($row) || !$this->rowIsInScope($row, $scope, $access['section_ids'], null, 'section_id')) {
            return $this->failure('candidate_not_found', 'The candidate was not found in this assessment scope.');
        }
        return array('success' => true, 'candidate' => $row, 'access' => $access);
    }

    protected function validateIncidentContext($onlineexam_id, $attempt_id, $candidate_id, array $scope)
    {
        if ($attempt_id !== null) {
            $attempt = $this->locklessAttemptContext($onlineexam_id, $attempt_id);
            if (empty($attempt)) {
                return $this->failure('attempt_not_found', 'The incident attempt does not belong to this assessment.');
            }
            if ($candidate_id !== null && (int) $attempt['onlineexam_student_id'] !== (int) $candidate_id) {
                return $this->failure('candidate_attempt_mismatch', 'The incident candidate does not own the selected attempt.');
            }
            $access = $this->resolveScope($onlineexam_id, $scope);
            if (!$access['success'] || !$this->rowIsInScope($attempt, $scope, $access['section_ids'], 'id', 'section_id')) {
                return $this->failure('attempt_out_of_scope', 'The incident attempt is outside the authorized scope.');
            }
            return array('success' => true, 'onlineexam_student_id' => (int) $attempt['onlineexam_student_id']);
        }
        if ($candidate_id !== null) {
            return $this->candidateContext($onlineexam_id, $candidate_id, $scope);
        }
        return array('success' => true);
    }

    protected function answerContext($onlineexam_id, $attempt_answer_id, $lock)
    {
        $sql = 'SELECT aa.*, aa.id AS answer_id, a.onlineexam_id, a.id AS attempt_id, a.revision, a.status AS attempt_status, '
            . 'a.marking_status AS attempt_marking_status, a.onlineexam_student_id, os.student_session_id, '
            . 'ss.section_id, ss.student_id, qs.marks AS question_max, qs.question_type, qs.question_text, '
            . 'qs.marking_scheme, qs.paper_id, qs.paper_section_id '
            . 'FROM `onlineexam_attempt_answers` aa '
            . 'INNER JOIN `onlineexam_candidate_attempts` a ON a.id = aa.attempt_id '
            . 'INNER JOIN `onlineexam_students` os ON os.id = a.onlineexam_student_id '
            . 'INNER JOIN `student_session` ss ON ss.id = os.student_session_id '
            . 'INNER JOIN `onlineexam_question_snapshots` qs ON qs.id = aa.question_snapshot_id '
            . 'WHERE aa.id = ' . $this->db->escape((int) $attempt_answer_id)
            . ' AND a.onlineexam_id = ' . $this->db->escape((int) $onlineexam_id)
            . ' AND qs.onlineexam_id = a.onlineexam_id AND qs.revision = a.revision '
            . 'LIMIT 1' . ($lock ? ' FOR UPDATE' : '');
        $row = $this->db->query($sql)->row_array();
        if (!empty($row)) {
            $paper = $this->findFrozenPaper($onlineexam_id, (int) $row['revision'], (int) $row['paper_id']);
            $row['paper_title'] = !empty($paper['title']) ? $paper['title'] : 'Frozen paper #' . (int) $row['paper_id'];
        }
        return $row;
    }

    protected function lockAttemptContext($onlineexam_id, $attempt_id)
    {
        return $this->attemptContext($onlineexam_id, $attempt_id, true);
    }

    protected function locklessAttemptContext($onlineexam_id, $attempt_id)
    {
        return $this->attemptContext($onlineexam_id, $attempt_id, false);
    }

    protected function attemptContext($onlineexam_id, $attempt_id, $lock)
    {
        $sql = 'SELECT a.*, os.student_session_id, ss.student_id, ss.section_id '
            . 'FROM `onlineexam_candidate_attempts` a '
            . 'INNER JOIN `onlineexam_students` os ON os.id = a.onlineexam_student_id '
            . 'INNER JOIN `student_session` ss ON ss.id = os.student_session_id '
            . 'WHERE a.id = ' . $this->db->escape((int) $attempt_id)
            . ' AND a.onlineexam_id = ' . $this->db->escape((int) $onlineexam_id)
            . ' LIMIT 1' . ($lock ? ' FOR UPDATE' : '');
        return $this->db->query($sql)->row_array();
    }

    protected function rowIsInScope(array $row, array $scope, array $section_ids, $attempt_key, $section_key)
    {
        if (!empty($section_ids) && (!isset($row[$section_key]) || !in_array((int) $row[$section_key], $section_ids, true))) {
            return false;
        }
        if ($attempt_key !== null && isset($scope['attempt_ids'])) {
            $allowed_attempts = array_map('intval', (array) $scope['attempt_ids']);
            if (!isset($row[$attempt_key]) || !in_array((int) $row[$attempt_key], $allowed_attempts, true)) {
                return false;
            }
        }
        if (isset($scope['student_session_ids'])) {
            $allowed_students = array_map('intval', (array) $scope['student_session_ids']);
            if (!isset($row['student_session_id']) || !in_array((int) $row['student_session_id'], $allowed_students, true)) {
                return false;
            }
        }
        return true;
    }

    protected function applySectionScope($query, array $section_ids, $column)
    {
        if (!empty($section_ids)) {
            $query->where_in($column, $section_ids);
        }
    }

    protected function applyNullableSectionScope($query, array $section_ids, $column)
    {
        if (!empty($section_ids)) {
            $query->group_start()->where_in($column, $section_ids)->or_where($column . ' IS NULL', null, false)->group_end();
        }
    }

    protected function applyAttemptScope($query, array $scope, $column)
    {
        if (isset($scope['attempt_ids'])) {
            $attempt_ids = array_values(array_unique(array_map('intval', (array) $scope['attempt_ids'])));
            if (empty($attempt_ids)) {
                $query->where('1 = 0', null, false);
            } else {
                $query->where_in($column, $attempt_ids);
            }
        }
    }

    protected function sameAccommodation(array $before, array $after)
    {
        return (int) $before['extra_time_minutes'] === (int) $after['extra_time_minutes']
            && (int) $before['makeup_attempts'] === (int) $after['makeup_attempts']
            && (string) $before['makeup_expires_at'] === (string) $after['makeup_expires_at']
            && (string) $before['notes'] === (string) $after['notes']
            && (int) $before['authorized_by'] === (int) $after['authorized_by'];
    }

    protected function normalizeJson($value)
    {
        if ($value === null || $value === '') {
            return null;
        }
        if (is_string($value)) {
            $decoded = json_decode($value, true);
            if (json_last_error() !== JSON_ERROR_NONE) {
                return false;
            }
            $value = $decoded;
        }
        if (!is_array($value)) {
            return false;
        }
        $this->sortRecursive($value);
        return json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }

    protected function sortRecursive(array &$value)
    {
        foreach ($value as &$item) {
            if (is_array($item)) {
                $this->sortRecursive($item);
            }
        }
        unset($item);
        if (array_keys($value) !== range(0, count($value) - 1)) {
            ksort($value);
        }
    }

    protected function decodeJson($value)
    {
        if ($value === null || $value === '') {
            return null;
        }
        $decoded = json_decode($value, true);
        return json_last_error() === JSON_ERROR_NONE ? $decoded : $value;
    }

    protected function integerize($row)
    {
        $row = is_array($row) ? $row : array();
        foreach ($row as $key => $value) {
            $row[$key] = (int) $value;
        }
        return $row;
    }

    protected function failure($code, $message)
    {
        return array('success' => false, 'code' => $code, 'errors' => array($message));
    }

    protected function audit($onlineexam_id, $attempt_id, $actor_id, $action, $entity_type, $entity_id, $before, $after)
    {
        if (!$this->db->table_exists('onlineexam_audit_log')) {
            return;
        }
        $this->db->insert('onlineexam_audit_log', array(
            'onlineexam_id' => $onlineexam_id === null ? null : (int) $onlineexam_id,
            'attempt_id' => $attempt_id === null ? null : (int) $attempt_id,
            'actor_id' => $actor_id === null ? null : (int) $actor_id,
            'actor_type' => $actor_id === null ? 'system' : 'staff',
            'action' => $action,
            'entity_type' => $entity_type,
            'entity_id' => $entity_id === null ? null : (string) $entity_id,
            'before_json' => $before === null ? null : json_encode($before),
            'after_json' => $after === null ? null : json_encode($after),
            'ip_address' => isset($this->input) ? $this->input->ip_address() : null,
            'created_at' => date('Y-m-d H:i:s'),
        ));
    }
}
