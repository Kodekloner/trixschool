<?php

if (!defined('BASEPATH')) {
    exit('No direct script access allowed');
}

/**
 * Server-authoritative candidate attempt lifecycle for workflow-v2 online
 * assessments. Legacy attempts remain in onlineexam_attempts and are never
 * read or changed by this model.
 */
class Onlineexamattempt_model extends CI_Model
{
    const STATUS_IN_PROGRESS = 'in_progress';
    const STATUS_SUBMITTED = 'submitted';
    const STATUS_TIMED_OUT = 'timed_out';
    const STATUS_MARKING = 'marking';
    const STATUS_COMPLETED = 'completed';
    const STATUS_VOIDED = 'voided';
    const FINAL_SUBMISSION_GRACE_SECONDS = 15;
    const FINAL_ANSWERS_MAX_ITEMS = 500;
    const FINAL_ANSWERS_MAX_BYTES = 524288;

    public function getCandidateContext($student_session_id, $onlineexam_id)
    {
        if ($this->db->field_exists('deleted_at', 'onlineexam')) {
            $this->db->where('onlineexam.deleted_at IS NULL', null, false);
        }
        return $this->db
            ->select('onlineexam.*, onlineexam_students.id AS onlineexam_student_id, onlineexam_students.student_session_id, onlineexam_students.candidate_status, student_session.student_id, student_session.class_id AS enrolled_class_id, student_session.section_id AS enrolled_section_id, COALESCE(onlineexam_accommodations.extra_time_minutes, 0) AS accommodation_extra_time_minutes', false)
            ->from('onlineexam_students')
            ->join('onlineexam', 'onlineexam.id = onlineexam_students.onlineexam_id')
            ->join('student_session', 'student_session.id = onlineexam_students.student_session_id')
            ->join('onlineexam_accommodations', 'onlineexam_accommodations.onlineexam_id = onlineexam.id AND onlineexam_accommodations.onlineexam_student_id = onlineexam_students.id', 'left')
            ->where('onlineexam_students.student_session_id', (int) $student_session_id)
            ->where('onlineexam_students.onlineexam_id', (int) $onlineexam_id)
            ->limit(1)
            ->get()
            ->row();
    }

    public function getCurrentAttempt($onlineexam_student_id)
    {
        return $this->db
            ->from('onlineexam_candidate_attempts')
            ->where('onlineexam_student_id', (int) $onlineexam_student_id)
            ->where('status !=', self::STATUS_VOIDED)
            ->order_by('attempt_no', 'DESC')
            ->limit(1)
            ->get()
            ->row();
    }

    public function getPapers($onlineexam_id, $attempt_id = null)
    {
        $revision = null;
        if ($attempt_id) {
            $attempt = $this->db->select('revision')
                ->where('id', (int) $attempt_id)
                ->get('onlineexam_candidate_attempts')
                ->row();
            $revision = $attempt ? (int) $attempt->revision : null;
        }
        if ($revision === null) {
            $exam = $this->db->select('revision')->where('id', (int) $onlineexam_id)->get('onlineexam')->row();
            $revision = $exam ? (int) $exam->revision : 1;
        }

        $configuration = $this->getRevisionConfiguration($onlineexam_id, $revision);
        if (!empty($configuration['papers']) && is_array($configuration['papers'])) {
            $attempt_papers = array();
            if ($attempt_id) {
                foreach ($this->db->where('attempt_id', (int) $attempt_id)->get('onlineexam_attempt_papers')->result() as $row) {
                    $attempt_papers[(int) $row->paper_id] = $row;
                }
            }

            $papers = array();
            foreach ($configuration['papers'] as $paper) {
                if (isset($paper['is_active']) && (int) $paper['is_active'] !== 1) {
                    continue;
                }
                if (!isset($paper['delivery_mode'], $paper['paper_type'])
                    || $paper['delivery_mode'] !== 'cbt'
                    || !in_array($paper['paper_type'], array('objective', 'theory'), true)) {
                    continue;
                }
                $row = (object) $paper;
                $attempt_paper = isset($attempt_papers[(int) $row->id]) ? $attempt_papers[(int) $row->id] : null;
                $row->attempt_paper_id = $attempt_paper ? $attempt_paper->id : null;
                $row->attempt_paper_status = $attempt_paper ? $attempt_paper->status : null;
                $row->attempt_paper_deadline = $attempt_paper ? $attempt_paper->deadline_at : null;
                $row->attempt_paper_submitted_at = $attempt_paper ? $attempt_paper->submitted_at : null;
                $papers[] = $row;
            }
            return $papers;
        }

        $this->db
            ->select('onlineexam_papers.*, onlineexam_attempt_papers.id AS attempt_paper_id, onlineexam_attempt_papers.status AS attempt_paper_status, onlineexam_attempt_papers.deadline_at AS attempt_paper_deadline, onlineexam_attempt_papers.submitted_at AS attempt_paper_submitted_at')
            ->from('onlineexam_papers');

        if ($attempt_id) {
            $this->db->join(
                'onlineexam_attempt_papers',
                'onlineexam_attempt_papers.paper_id = onlineexam_papers.id AND onlineexam_attempt_papers.attempt_id = ' . (int) $attempt_id,
                'left'
            );
        } else {
            $this->db->join('onlineexam_attempt_papers', '1 = 0', 'left');
        }

        return $this->db
            ->where('onlineexam_papers.onlineexam_id', (int) $onlineexam_id)
            ->where('onlineexam_papers.is_active', 1)
            ->where('onlineexam_papers.delivery_mode', 'cbt')
            ->where_in('onlineexam_papers.paper_type', array('objective', 'theory'))
            ->order_by('onlineexam_papers.display_order', 'ASC')
            ->order_by('onlineexam_papers.id', 'ASC')
            ->get()
            ->result();
    }

    public function submitExpiredPapers($student_session_id, $onlineexam_student_id)
    {
        $attempt = $this->getCurrentAttempt($onlineexam_student_id);
        if (!$attempt || $attempt->status !== self::STATUS_IN_PROGRESS) {
            return array();
        }

        $expired = $this->db
            ->select('paper_id')
            ->from('onlineexam_attempt_papers')
            ->where('attempt_id', (int) $attempt->id)
            ->where('status', self::STATUS_IN_PROGRESS)
            ->where('deadline_at <', date('Y-m-d H:i:s', time() - self::FINAL_SUBMISSION_GRACE_SECONDS))
            ->get()
            ->result();

        $results = array();
        foreach ($expired as $paper) {
            $results[] = $this->submitExpiredPaper(
                $student_session_id,
                $attempt->id,
                $paper->paper_id,
                $attempt->submission_key
            );
        }
        return $results;
    }

    /** Candidate view of frozen papers, including authorized individual windows. */
    public function getCandidatePapers($onlineexam_id, $attempt_id = null, $onlineexam_student_id = null)
    {
        $attempt = $attempt_id ? $this->db->where('id', (int) $attempt_id)
            ->where('onlineexam_id', (int) $onlineexam_id)->get('onlineexam_candidate_attempts')->row() : null;
        if ($attempt_id && !$attempt) {
            return array();
        }
        if ($attempt) {
            if ($onlineexam_student_id && (int) $attempt->onlineexam_student_id !== (int) $onlineexam_student_id) {
                return array();
            }
            $onlineexam_student_id = (int) $attempt->onlineexam_student_id;
        }
        $exam = $this->db->where('id', (int) $onlineexam_id)->get('onlineexam')->row();
        if (!$exam) {
            return array();
        }
        $extra = $attempt ? (int) $attempt->extra_time_minutes : 0;
        if (!$attempt && $onlineexam_student_id) {
            $accommodation = $this->db->where('onlineexam_id', (int) $onlineexam_id)
                ->where('onlineexam_student_id', (int) $onlineexam_student_id)->get('onlineexam_accommodations')->row();
            $extra = $accommodation ? (int) $accommodation->extra_time_minutes : 0;
        }
        $rows = array();
        if ($attempt) {
            foreach ($this->db->where('attempt_id', (int) $attempt_id)->get('onlineexam_attempt_papers')->result() as $row) {
                $rows[(int) $row->paper_id] = $row;
            }
        }
        $counts = $this->db->select('q.paper_id, COALESCE(q.paper_section_id, 0) AS section_id, q.is_compulsory, COUNT(q.id) AS question_count, SUM(CASE WHEN aa.is_answered = 1 THEN 1 ELSE 0 END) AS answered_count', false)
            ->from('onlineexam_question_snapshots q')->join('onlineexam_attempt_answers aa',
                'aa.question_snapshot_id = q.id AND aa.attempt_id = ' . (int) $attempt_id, 'left')
            ->where('q.onlineexam_id', (int) $onlineexam_id)->where('q.revision', $attempt ? (int) $attempt->revision : (int) $exam->revision)
            ->group_by(array('q.paper_id', 'q.paper_section_id', 'q.is_compulsory'))->get()->result();
        $by_paper = array();
        foreach ($counts as $count) {
            $by_paper[(int) $count->paper_id][] = array(
                'section_id' => (int) $count->section_id,
                'is_compulsory' => (int) $count->is_compulsory,
                'question_count' => (int) $count->question_count,
                'answered_count' => (int) $count->answered_count,
            );
        }
        $papers = $this->getPapers($onlineexam_id, $attempt_id);
        $frozen_sections = array();
        foreach ($this->getFrozenPapers($onlineexam_id, $attempt ? (int) $attempt->revision : (int) $exam->revision) as $frozen_paper) {
            $frozen_sections[(int) $frozen_paper['id']] = isset($frozen_paper['sections']) ? (array) $frozen_paper['sections'] : array();
        }
        $this->load->library('onlineexam_review');
        foreach ($papers as $paper) {
            $window = $onlineexam_student_id ? $this->candidatePaperWindow($onlineexam_id, $onlineexam_student_id, $paper->id,
                $attempt ? $attempt->revision : $exam->revision) : null;
            $paper->is_rescheduled = !empty($window);
            if ($window) {
                $paper->starts_at = $window->starts_at;
                $paper->ends_at = $window->ends_at;
            }
            $paper->effective_starts_at = !empty($paper->starts_at) ? $paper->starts_at : $exam->exam_from;
            $end = !empty($paper->ends_at) ? $paper->ends_at : $exam->exam_to;
            $paper->effective_ends_at = !empty($end) && strtotime($end) !== false
                ? date('Y-m-d H:i:s', strtotime($end) + max(0, $extra) * 60) : null;
            $row = isset($rows[(int) $paper->id]) ? $rows[(int) $paper->id] : null;
            $paper->raw_score = $row ? (float) $row->raw_score : null;
            $paper->completion_source = $row && isset($row->completion_source) ? $row->completion_source : null;
            $paper->manual_marking_status = $row ? $row->manual_marking_status : 'not_required';
            $paper->attempt_paper_started_at = $row ? $row->started_at : null;
            $groups = isset($by_paper[(int) $paper->id]) ? $by_paper[(int) $paper->id] : array();
            $progress = $this->onlineexam_review->requirementProgress(
                $groups,
                isset($frozen_sections[(int) $paper->id]) ? $frozen_sections[(int) $paper->id] : array()
            );
            $paper->question_count = array_sum(array_column($groups, 'question_count'));
            $paper->answered_count = array_sum(array_column($groups, 'answered_count'));
            $paper->required_answer_count = $progress['required'];
            $paper->completed_required_count = $progress['completed'];
            $paper->requirements_met = $progress['met'];
        }
        return $papers;
    }

    protected function candidatePaperWindow($onlineexam_id, $candidate_id, $paper_id, $revision)
    {
        if (!$this->db->table_exists('onlineexam_candidate_paper_windows')) {
            return null;
        }
        return $this->db->where('onlineexam_id', (int) $onlineexam_id)->where('onlineexam_student_id', (int) $candidate_id)
            ->where('paper_id', (int) $paper_id)->where('revision', (int) $revision)
            ->get('onlineexam_candidate_paper_windows')->row();
    }

    /** Recheck under the same lock as submission: a stale cron selection must not close a rescheduled paper. */
    protected function submitExpiredPaper($student_session_id, $attempt_id, $paper_id, $submission_key)
    {
        $this->db->trans_begin();
        $owned = $this->lockOwnedAttemptPaper($student_session_id, $attempt_id, $paper_id);
        if (!$owned || $owned->paper_status !== self::STATUS_IN_PROGRESS
            || empty($owned->paper_deadline_at)
            || strtotime($owned->paper_deadline_at) + self::FINAL_SUBMISSION_GRACE_SECONDS >= time()) {
            $this->db->trans_commit();
            return array('status' => true, 'skipped' => true);
        }
        $result = $this->submitPaper($student_session_id, $attempt_id, $paper_id, $submission_key);
        if (empty($result['status']) || $this->db->trans_status() === false) {
            $this->db->trans_rollback();
        } else {
            $this->db->trans_commit();
        }
        return $result;
    }

    /**
     * Server-side timeout sweeper used by the existing cron controller. It is
     * intentionally independent of a candidate revisiting the portal.
     */
    public function processExpiredPapers($limit = 500)
    {
        if (!$this->db->table_exists('onlineexam_attempt_papers') || !$this->db->table_exists('onlineexam_candidate_attempts')) {
            return array('processed' => 0, 'submitted' => 0, 'finalized' => 0, 'errors' => array());
        }
        $limit = max(1, min(2000, (int) $limit));
        $prepared = $this->prepareExpiredUnstartedPapers($limit);
        $rows = $this->db
            ->select('ap.paper_id, ap.attempt_id, a.onlineexam_id, a.submission_key, os.student_session_id')
            ->from('onlineexam_attempt_papers ap')
            ->join('onlineexam_candidate_attempts a', 'a.id = ap.attempt_id')
            ->join('onlineexam_students os', 'os.id = a.onlineexam_student_id')
            ->where('ap.status', self::STATUS_IN_PROGRESS)
            ->where('ap.deadline_at <', date('Y-m-d H:i:s', time() - self::FINAL_SUBMISSION_GRACE_SECONDS))
            ->where('a.status', self::STATUS_IN_PROGRESS)
            ->order_by('ap.deadline_at', 'ASC')
            ->limit($limit)
            ->get()
            ->result_array();

        $summary = array('prepared' => $prepared, 'processed' => count($rows), 'submitted' => 0, 'finalized' => 0, 'reconciled' => 0, 'errors' => array());
        foreach ($rows as $row) {
            $result = $this->submitExpiredPaper(
                (int) $row['student_session_id'],
                (int) $row['attempt_id'],
                (int) $row['paper_id'],
                (string) $row['submission_key']
            );
            if (!empty($result['skipped'])) {
                continue;
            }
            if (empty($result['status'])) {
                $summary['errors'][] = 'Attempt ' . (int) $row['attempt_id'] . ', paper ' . (int) $row['paper_id'] . ': ' . $result['message'];
                continue;
            }
            $summary['submitted']++;
            $this->db->insert('onlineexam_audit_log', array(
                'onlineexam_id' => (int) $row['onlineexam_id'],
                'attempt_id' => (int) $row['attempt_id'],
                'actor_type' => 'system',
                'action' => 'timeout_submit_paper',
                'entity_type' => 'onlineexam_attempt_papers',
                'entity_id' => (string) (int) $row['paper_id'],
                'after_json' => json_encode($result),
                'created_at' => date('Y-m-d H:i:s'),
            ));
            if (in_array($result['attempt_status'], array(self::STATUS_SUBMITTED, self::STATUS_TIMED_OUT), true)) {
                $this->load->model('onlineexamworkflow_model');
                $finalization = $this->onlineexamworkflow_model->finalizeAttempt((int) $row['attempt_id'], null);
                if (!empty($finalization['success'])) {
                    $this->load->model('onlineexamresultsync_model');
                    $sync = $this->onlineexamresultsync_model->syncCompletedAttempt((int) $row['attempt_id'], null);
                    if (!empty($sync['success'])) {
                        $summary['finalized']++;
                    } elseif (!empty($sync['reason']) || !empty($sync['errors'])) {
                        $sync_error = !empty($sync['reason']) ? $sync['reason'] : implode('; ', (array) $sync['errors']);
                        $summary['errors'][] = 'Attempt ' . (int) $row['attempt_id'] . ' result sync: ' . $sync_error;
                    }
                } elseif (empty($finalization['pending_marking'])) {
                    $summary['errors'][] = 'Attempt ' . (int) $row['attempt_id'] . ' could not be finalized.';
                }
            }
        }
        // A process interruption can occur after finalization commits but
        // before the adapter transaction starts. Reconcile those completed
        // attempts here; syncCompletedAttempt is transactional/idempotent.
        $summary['reconciled'] = $this->reconcileCompletedAttempts($limit, $summary['errors']);
        $exam_ids = array_values(array_unique(array_map(function ($row) {
            return (int) $row['onlineexam_id'];
        }, $rows)));
        foreach ($exam_ids as $exam_id) {
            $this->refreshAssessmentLifecycle($exam_id);
        }
        return $summary;
    }

    /**
     * Bounded cron reconciliation for assessment-level state. It does not
     * depend on finding an expired paper, so assessments with no attempts or
     * with all papers submitted before closing still leave published/
     * in-progress state at the correct time.
     */
    public function refreshActiveAssessmentLifecycles($limit = 1000)
    {
        if (!$this->db->table_exists('onlineexam_candidate_attempts')
            || !$this->db->table_exists('onlineexam_accommodations')
            || !$this->db->field_exists('workflow_version', 'onlineexam')
            || !$this->db->field_exists('lifecycle_checked_at', 'onlineexam')) {
            return array('processed' => 0, 'changed' => 0, 'errors' => array());
        }

        $limit = max(1, min(2000, (int) $limit));
        $rows = $this->db->select('id, lifecycle_status')
            ->from('onlineexam')
            ->where('workflow_version', 2)
            ->where_in('lifecycle_status', array('scheduled', 'published', 'in_progress', 'marking'))
            // Never-checked rows come first; the check timestamp provides a
            // fair round-robin when active assessments exceed one cron batch.
            ->order_by('CASE WHEN lifecycle_checked_at IS NULL THEN 0 ELSE 1 END', 'ASC', false)
            ->order_by('lifecycle_checked_at', 'ASC')
            ->order_by('COALESCE(exam_to, exam_from)', 'ASC', false)
            ->order_by('id', 'ASC')
            ->limit($limit)
            ->get()
            ->result_array();

        $summary = array('processed' => 0, 'changed' => 0, 'errors' => array());
        foreach ($rows as $row) {
            try {
                $desired = $this->refreshAssessmentLifecycle((int) $row['id']);
                $this->db->where('id', (int) $row['id'])->update('onlineexam', array(
                    'lifecycle_checked_at' => date('Y-m-d H:i:s'),
                ));
                $summary['processed']++;
                if ($desired !== null && $desired !== $row['lifecycle_status']) {
                    $summary['changed']++;
                }
            } catch (Exception $exception) {
                $summary['errors'][] = 'Assessment ' . (int) $row['id'] . ': ' . $exception->getMessage();
            }
        }
        return $summary;
    }

    protected function reconcileCompletedAttempts($limit, array &$errors)
    {
        $rows = $this->db
            ->select('a.id')
            ->from('onlineexam_candidate_attempts a')
            ->join('onlineexam e', 'e.id = a.onlineexam_id')
            ->where('a.status', self::STATUS_COMPLETED)
            ->where('e.workflow_version', 2)
            ->where("((e.purpose IN ('ca','midterm','exam') AND e.result_adapter = 'standard_component') OR (e.purpose = 'holiday' AND e.result_adapter = 'holiday_assessment') OR (e.purpose = 'kindergarten' AND e.result_adapter = 'kindergarten_concept') OR (e.purpose = 'british' AND e.result_adapter = 'british_outcome'))", null, false)
            ->where("NOT EXISTS (SELECT 1 FROM onlineexam_result_sync rs WHERE rs.attempt_id = a.id AND rs.status IN ('posted','conflict'))", null, false)
            ->order_by('a.updated_at', 'ASC')
            ->limit((int) $limit)
            ->get()
            ->result_array();
        if (empty($rows)) {
            return 0;
        }
        $this->load->model('onlineexamresultsync_model');
        $reconciled = 0;
        foreach ($rows as $row) {
            $sync = $this->onlineexamresultsync_model->syncCompletedAttempt((int) $row['id'], null);
            if (!empty($sync['success']) || (!empty($sync['status']) && $sync['status'] === 'conflict')) {
                $reconciled++;
            } else {
                $errors[] = 'Attempt ' . (int) $row['id'] . ' reconciliation: '
                    . (!empty($sync['reason']) ? $sync['reason'] : 'result synchronization failed.');
            }
        }
        return $reconciled;
    }

    /**
     * Once a frozen CBT paper window (including accommodation) closes,
     * materialize any missing/pending attempt-paper as a timed-out zero paper.
     * Paper-delivered scripts are never auto-zeroed; invigilators record those.
     */
    protected function prepareExpiredUnstartedPapers($limit)
    {
        $attempts = $this->db
            ->select('a.id, a.onlineexam_id, a.onlineexam_student_id, a.revision, a.attempt_no, a.deadline_at, a.extra_time_minutes, e.exam_to')
            ->from('onlineexam_candidate_attempts a')
            ->join('onlineexam e', 'e.id = a.onlineexam_id')
            ->where('a.status', self::STATUS_IN_PROGRESS)
            ->where('e.workflow_version', 2)
            ->order_by('a.id', 'ASC')
            ->limit((int) $limit)
            ->get()
            ->result_array();
        $prepared = 0;
        $now_timestamp = time();
        $now = date('Y-m-d H:i:s');
        foreach ($attempts as $attempt) {
            $this->db->trans_begin();
            $this->db->query('SELECT id FROM onlineexam_students WHERE id = ' . (int) $attempt['onlineexam_student_id'] . ' FOR UPDATE');
            foreach ($this->getFrozenPapers($attempt['onlineexam_id'], $attempt['revision']) as $paper) {
                if ((isset($paper['is_active']) && (int) $paper['is_active'] !== 1)
                    || $paper['delivery_mode'] !== 'cbt'
                    || !in_array($paper['paper_type'], array('objective', 'theory'), true)
                    || !$this->compactPaperQuestionsSupported($attempt['onlineexam_id'], $attempt['revision'], $paper['id'], $paper['paper_type'])) {
                    continue;
                }
                $hard_end = !empty($paper['ends_at']) ? $paper['ends_at'] : $attempt['exam_to'];
                $window = $this->candidatePaperWindow($attempt['onlineexam_id'], $attempt['onlineexam_student_id'], $paper['id'], $attempt['revision']);
                if ($window) {
                    $hard_end = $window->ends_at;
                }
                if (empty($hard_end)) {
                    continue;
                }
                $effective_end = strtotime($hard_end) + (max(0, (int) $attempt['extra_time_minutes']) * 60);
                if (($effective_end + self::FINAL_SUBMISSION_GRACE_SECONDS) >= $now_timestamp) {
                    continue;
                }
                $record = array(
                    'status' => self::STATUS_IN_PROGRESS,
                    'started_at' => date('Y-m-d H:i:s', $effective_end),
                    'deadline_at' => date('Y-m-d H:i:s', $effective_end),
                    'raw_score' => 0,
                    'raw_max_score' => (float) $paper['raw_max_score'],
                    'contribution_score' => 0,
                    'contribution_max_score' => (float) $paper['contribution_score'],
                    'updated_at' => $now,
                );
                // INSERT IGNORE makes overlapping cron workers harmless. If
                // an invigilation-created pending row already exists, only
                // that pending row is transitioned; started/submitted rows
                // are never rewritten.
                $insert = $record;
                $insert['attempt_id'] = (int) $attempt['id'];
                $insert['paper_id'] = (int) $paper['id'];
                $insert['manual_marking_status'] = 'not_required';
                $insert['created_at'] = $now;
                $columns = array_keys($insert);
                $this->db->query(
                    'INSERT IGNORE INTO `onlineexam_attempt_papers` (`' . implode('`,`', $columns) . '`) VALUES ('
                    . implode(',', array_map(array($this->db, 'escape'), array_values($insert))) . ')'
                );
                $changed = $this->db->affected_rows() > 0;
                if (!$changed) {
                    $this->db->where('attempt_id', (int) $attempt['id'])
                        ->where('paper_id', (int) $paper['id'])
                        ->where('status', 'pending')
                        ->update('onlineexam_attempt_papers', $record);
                    $changed = $this->db->affected_rows() > 0;
                }
                if ($changed) {
                    $prepared++;
                    $this->db->insert('onlineexam_audit_log', array(
                        'onlineexam_id' => (int) $attempt['onlineexam_id'],
                        'attempt_id' => (int) $attempt['id'],
                        'actor_type' => 'system',
                        'action' => 'timeout_prepare_unopened_paper',
                        'entity_type' => 'onlineexam_attempt_papers',
                        'entity_id' => (string) (int) $paper['id'],
                        'after_json' => json_encode($record),
                        'created_at' => $now,
                    ));
                }
            }
            if ($this->db->trans_status() === false) {
                $this->db->trans_rollback();
            } else {
                $this->db->trans_commit();
            }
        }
        return $prepared;
    }

    public function startPaper($student_session_id, $onlineexam_id, $paper_id)
    {
        $context = $this->getCandidateContext($student_session_id, $onlineexam_id);
        $extra_time_minutes = $context ? max(0, (int) $context->accommodation_extra_time_minutes) : 0;
        $current_attempt = $context ? $this->getCurrentAttempt($context->onlineexam_student_id) : null;
        if ($context && $current_attempt) {
            $context->revision = (int) $current_attempt->revision;
        }
        $window = $context ? $this->candidatePaperWindow($onlineexam_id, $context->onlineexam_student_id, $paper_id, $context->revision) : null;
        if ($window) {
            $context->candidate_paper_override = true;
            $context->exam_from = $window->starts_at;
            $context->exam_to = $window->ends_at;
        }
        $validation = $this->validateCandidateContext($context, $extra_time_minutes, $current_attempt);
        if ($validation !== true) {
            return array('status' => false, 'message' => $validation);
        }

        $paper = $this->getFrozenPaper($onlineexam_id, (int) $context->revision, $paper_id);
        if ($paper && $window) {
            $paper->starts_at = $window->starts_at;
            $paper->ends_at = $window->ends_at;
        }

        if (!$paper) {
            return array('status' => false, 'message' => 'The selected paper does not belong to this assessment.');
        }
        if ($paper->delivery_mode !== 'cbt') {
            return array('status' => false, 'message' => 'Only CBT papers are available in Online Examination.');
        }
        if (!in_array($paper->paper_type, array('objective', 'theory'), true)) {
            return array('status' => false, 'message' => 'This retired paper type cannot be opened in Online Examination.');
        }
        $allowed_question_types = array(
            'singlechoice', 'multichoice', 'true_false', 'short_answer',
            'numeric', 'matching', 'ordering', 'long_answer'
        );
        $question_count = $this->db->where('onlineexam_id', (int) $onlineexam_id)
            ->where('revision', (int) $context->revision)
            ->where('paper_id', (int) $paper->id)
            ->count_all_results('onlineexam_question_snapshots');
        $unsupported_count = $this->db->where('onlineexam_id', (int) $onlineexam_id)
            ->where('revision', (int) $context->revision)
            ->where('paper_id', (int) $paper->id)
            ->where_not_in('question_type', $allowed_question_types)
            ->count_all_results('onlineexam_question_snapshots');
        if ($question_count < 1 || $unsupported_count > 0) {
            return array('status' => false, 'message' => 'This paper contains no supported compact CBT questions.');
        }

        $window_error = $this->validatePaperWindow($paper, $context, $extra_time_minutes, $current_attempt);
        if ($window_error !== true) {
            return array('status' => false, 'message' => $window_error);
        }

        $this->db->trans_begin();
        // Serialize all starts for this candidate. This turns concurrent tabs
        // into a safe resume instead of competing attempt_no=1 inserts.
        $locked_exam = $this->db->query('SELECT * FROM onlineexam WHERE id = ' . (int) $onlineexam_id . ' FOR UPDATE')->row();
        if (!$locked_exam || !empty($locked_exam->deleted_at) || (string) $locked_exam->is_active !== '1'
            || in_array($locked_exam->lifecycle_status, array('draft', 'cancelled', 'legacy'), true)) {
            $this->db->trans_rollback();
            return array('status' => false, 'message' => 'This assessment is no longer available.');
        }
        $locked_candidate = $this->db->query(
            'SELECT id, candidate_status FROM `onlineexam_students` WHERE id = '
            . $this->db->escape((int) $context->onlineexam_student_id)
            . ' AND onlineexam_id = ' . $this->db->escape((int) $context->id)
            . ' LIMIT 1 FOR UPDATE'
        )->row();
        if (!$locked_candidate || $locked_candidate->candidate_status !== 'assigned') {
            $this->db->trans_rollback();
            return array('status' => false, 'message' => 'You are no longer an active candidate for this assessment.');
        }
        // A staff reschedule may have occurred between the initial page request
        // and this lock. Revalidate the current window before starting a timer.
        $locked_window = $this->candidatePaperWindow($onlineexam_id, $context->onlineexam_student_id, $paper_id, $context->revision);
        if ($locked_window) {
            $paper->starts_at = $context->exam_from = $locked_window->starts_at;
            $paper->ends_at = $context->exam_to = $locked_window->ends_at;
        }
        $window_error = $this->validatePaperWindow($paper, $context, $extra_time_minutes, $current_attempt);
        if ($window_error !== true) {
            $this->db->trans_rollback();
            return array('status' => false, 'message' => $window_error);
        }
        $attempt = $this->getOrCreateAttempt($context);
        if (is_array($attempt) && isset($attempt['status']) && $attempt['status'] === false) {
            $this->db->trans_rollback();
            return $attempt;
        }

        if (!in_array($attempt->status, array(self::STATUS_IN_PROGRESS), true)) {
            $this->db->trans_rollback();
            return array('status' => false, 'message' => 'This official attempt has already been submitted.');
        }

        $attempt_paper = $this->db
            ->from('onlineexam_attempt_papers')
            ->where('attempt_id', (int) $attempt->id)
            ->where('paper_id', (int) $paper->id)
            ->limit(1)
            ->get()
            ->row();

        if ($attempt_paper && in_array($attempt_paper->status, array(self::STATUS_SUBMITTED, 'completed', 'voided'), true)) {
            $this->db->trans_rollback();
            return array('status' => false, 'message' => 'This paper has already been submitted.');
        }

        if (!$attempt_paper || $attempt_paper->status === 'pending') {
            $now = new DateTimeImmutable('now');
            $deadline = $this->calculatePaperDeadline($now, $paper, $context, (int) $attempt->extra_time_minutes, $attempt);
            $record = array(
                'status' => self::STATUS_IN_PROGRESS,
                'started_at' => $now->format('Y-m-d H:i:s'),
                'deadline_at' => $deadline->format('Y-m-d H:i:s'),
                'raw_score' => 0,
                'raw_max_score' => (float) $paper->raw_max_score,
                'contribution_score' => 0,
                'contribution_max_score' => (float) $paper->contribution_score,
                'created_at' => $now->format('Y-m-d H:i:s'),
                'updated_at' => $now->format('Y-m-d H:i:s'),
            );
            if ($attempt_paper) {
                // An invigilation action can create a pending roster row before
                // a CBT paper is opened. Start it here so timing and autosave
                // use a real server deadline.
                unset($record['created_at']);
                $this->db->where('id', (int) $attempt_paper->id)->update('onlineexam_attempt_papers', $record);
                $record['id'] = (int) $attempt_paper->id;
                $record['attempt_id'] = (int) $attempt->id;
                $record['paper_id'] = (int) $paper->id;
            } else {
                $record['attempt_id'] = (int) $attempt->id;
                $record['paper_id'] = (int) $paper->id;
                $this->db->insert('onlineexam_attempt_papers', $record);
                $record['id'] = $this->db->insert_id();
            }
            $attempt_paper = (object) $record;
        }

        if ($this->db->trans_status() === false) {
            $this->db->trans_rollback();
            return array('status' => false, 'message' => 'The paper could not be started. Please try again.');
        }
        $this->db->trans_commit();
        $this->refreshAssessmentLifecycle((int) $context->id);

        $questions = $this->getPaperQuestions($attempt->id, $context->id, $paper->id, $context->revision, (int) $context->is_random_question === 1);
        return array(
            'status' => true,
            'context' => $context,
            'attempt' => $attempt,
            'paper' => $paper,
            'attempt_paper' => $attempt_paper,
            'questions' => $questions,
        );
    }

    /** Keep the assessment-level state aligned with candidate work. */
    public function refreshAssessmentLifecycle($onlineexam_id)
    {
        $exam = $this->db->where('id', (int) $onlineexam_id)
            ->where('workflow_version', 2)->limit(1)->get('onlineexam')->row_array();
        if (empty($exam) || !empty($exam['deleted_at']) || in_array($exam['lifecycle_status'], array('draft', 'cancelled', 'legacy'), true)) {
            return empty($exam) ? null : $exam['lifecycle_status'];
        }
        $now = time();
        $started = $this->db->where('onlineexam_id', (int) $onlineexam_id)
            ->where('status !=', self::STATUS_VOIDED)->count_all_results('onlineexam_candidate_attempts');
        $max_extra = (int) $this->db->select_max('extra_time_minutes', 'minutes')
            ->where('onlineexam_id', (int) $onlineexam_id)->get('onlineexam_accommodations')->row()->minutes;
        $normal_close = empty($exam['exam_to']) ? 0 : strtotime($exam['exam_to']) + ($max_extra * 60);
        if ($this->db->table_exists('onlineexam_candidate_paper_windows')) {
            $last_window = $this->db->select_max('ends_at', 'last_end')->where('onlineexam_id', (int) $onlineexam_id)
                ->get('onlineexam_candidate_paper_windows')->row();
            if ($last_window && !empty($last_window->last_end)) {
                $normal_close = max($normal_close, strtotime($last_window->last_end) + $max_extra * 60);
            }
        }

        if (!empty($exam['exam_from']) && $now < strtotime($exam['exam_from'])) {
            $desired = 'scheduled';
        } elseif (!$normal_close || $now < $normal_close) {
            $desired = $started > 0 ? 'in_progress' : 'published';
        } else {
            $assigned = $this->db->where('onlineexam_id', (int) $onlineexam_id)
                ->where('candidate_status', 'assigned')->count_all_results('onlineexam_students');
            $completed = (int) $this->db->query(
                "SELECT COUNT(*) AS total FROM onlineexam_students os WHERE os.onlineexam_id = " . $this->db->escape((int) $onlineexam_id)
                . " AND os.candidate_status = 'assigned'"
                . " AND EXISTS (SELECT 1 FROM onlineexam_candidate_attempts ca WHERE ca.onlineexam_student_id = os.id AND ca.status = 'completed')"
                . " AND NOT EXISTS (SELECT 1 FROM onlineexam_candidate_attempts ca2 WHERE ca2.onlineexam_student_id = os.id AND ca2.status NOT IN ('completed','voided'))"
            )->row()->total;
            $desired = $assigned > 0 && $completed === $assigned ? 'completed' : 'marking';
        }
        if ($desired !== $exam['lifecycle_status']) {
            $this->db->where('id', (int) $onlineexam_id)->update('onlineexam', array('lifecycle_status' => $desired));
            if ($this->db->table_exists('onlineexam_audit_log')) {
                $this->db->insert('onlineexam_audit_log', array(
                    'onlineexam_id' => (int) $onlineexam_id,
                    'actor_type' => 'system',
                    'action' => 'assessment_lifecycle_transition',
                    'entity_type' => 'onlineexam',
                    'entity_id' => (string) (int) $onlineexam_id,
                    'before_json' => json_encode(array('lifecycle_status' => $exam['lifecycle_status'])),
                    'after_json' => json_encode(array('lifecycle_status' => $desired)),
                    'created_at' => date('Y-m-d H:i:s'),
                ));
            }
        }
        return $desired;
    }

    public function getPaperQuestions($attempt_id, $onlineexam_id, $paper_id, $revision, $randomize = false)
    {
        $query = $this->db
            ->select('onlineexam_question_snapshots.id, onlineexam_question_snapshots.onlineexam_id, onlineexam_question_snapshots.paper_id, onlineexam_question_snapshots.paper_section_id, onlineexam_question_snapshots.source_question_id, onlineexam_question_snapshots.revision, onlineexam_question_snapshots.question_type, onlineexam_question_snapshots.question_text, onlineexam_question_snapshots.options_json, onlineexam_question_snapshots.response_schema_json, onlineexam_question_snapshots.passage_group_key, onlineexam_question_snapshots.passage_title, onlineexam_question_snapshots.passage_text, onlineexam_question_snapshots.marks, onlineexam_question_snapshots.neg_marks, onlineexam_question_snapshots.is_compulsory, onlineexam_question_snapshots.display_order, onlineexam_question_snapshots.checksum, onlineexam_attempt_answers.response_json, onlineexam_attempt_answers.attachment_name, onlineexam_attempt_answers.marking_status AS answer_marking_status, onlineexam_attempt_answers.version AS answer_version, onlineexam_attempt_answers.client_sequence AS answer_client_sequence')
            ->from('onlineexam_question_snapshots')
            ->join(
                'onlineexam_attempt_answers',
                'onlineexam_attempt_answers.question_snapshot_id = onlineexam_question_snapshots.id AND onlineexam_attempt_answers.attempt_id = ' . (int) $attempt_id,
                'left'
            )
            ->where('onlineexam_question_snapshots.onlineexam_id', (int) $onlineexam_id)
            ->where('onlineexam_question_snapshots.paper_id', (int) $paper_id)
            ->where('onlineexam_question_snapshots.revision', (int) $revision);

        if ($randomize) {
            // A stable per-attempt order preserves randomisation across reconnects.
            $query->order_by(
                'SHA2(CONCAT(' . (int) $attempt_id . ", '-', onlineexam_question_snapshots.id), 256)",
                'ASC',
                false
            );
        } else {
            $query->order_by('onlineexam_question_snapshots.display_order', 'ASC');
            $query->order_by('onlineexam_question_snapshots.id', 'ASC');
        }
        $questions = $query->get()->result();
        $paper = $this->getFrozenPaper($onlineexam_id, $revision, $paper_id);
        $sections = array();
        if ($paper && !empty($paper->sections) && is_array($paper->sections)) {
            foreach ($paper->sections as $section) {
                $sections[(int) $section['id']] = $section;
            }
        }
        foreach ($questions as $question) {
            $section = isset($sections[(int) $question->paper_section_id]) ? $sections[(int) $question->paper_section_id] : array();
            $question->section_title = isset($section['title']) ? $section['title'] : null;
            $question->section_instructions = isset($section['instructions']) ? $section['instructions'] : null;
            $question->answer_rule = isset($section['answer_rule']) ? $section['answer_rule'] : 'all';
            $question->answer_count = isset($section['answer_count']) ? $section['answer_count'] : null;
            $question->section_order = isset($section['display_order']) ? $section['display_order'] : 0;
        }
        $passage_anchors = array();
        foreach ($questions as $question) {
            if (!empty($question->passage_group_key)) {
                $anchor_key = (int) $question->paper_section_id . ':' . $question->passage_group_key;
                if (!isset($passage_anchors[$anchor_key]) || (int) $question->display_order < $passage_anchors[$anchor_key]) {
                    $passage_anchors[$anchor_key] = (int) $question->display_order;
                }
            }
        }
        usort($questions, function ($left, $right) use ($randomize, $attempt_id, $passage_anchors) {
            $left_section_order = empty($left->paper_section_id) ? -1 : (int) $left->section_order;
            $right_section_order = empty($right->paper_section_id) ? -1 : (int) $right->section_order;
            $section_compare = $left_section_order - $right_section_order;
            if ($section_compare === 0) {
                $section_compare = (int) $left->paper_section_id - (int) $right->paper_section_id;
            }
            if ($section_compare !== 0) {
                return $section_compare;
            }
            if ($randomize) {
                $left_group = !empty($left->passage_group_key) ? 'passage:' . $left->passage_group_key : 'question:' . (int) $left->id;
                $right_group = !empty($right->passage_group_key) ? 'passage:' . $right->passage_group_key : 'question:' . (int) $right->id;
                $group_compare = strcmp(hash('sha256', (int) $attempt_id . '-' . $left_group), hash('sha256', (int) $attempt_id . '-' . $right_group));
                if ($group_compare !== 0) {
                    return $group_compare;
                }
                return strcmp(hash('sha256', (int) $attempt_id . '-' . (int) $left->id), hash('sha256', (int) $attempt_id . '-' . (int) $right->id));
            }
            $left_anchor_key = (int) $left->paper_section_id . ':' . $left->passage_group_key;
            $right_anchor_key = (int) $right->paper_section_id . ':' . $right->passage_group_key;
            $left_order = !empty($left->passage_group_key) && isset($passage_anchors[$left_anchor_key]) ? $passage_anchors[$left_anchor_key] : (int) $left->display_order;
            $right_order = !empty($right->passage_group_key) && isset($passage_anchors[$right_anchor_key]) ? $passage_anchors[$right_anchor_key] : (int) $right->display_order;
            if ($left_order !== $right_order) {
                return $left_order - $right_order;
            }
            $left_group = !empty($left->passage_group_key) ? 'passage:' . $left->passage_group_key : 'question:' . (int) $left->id;
            $right_group = !empty($right->passage_group_key) ? 'passage:' . $right->passage_group_key : 'question:' . (int) $right->id;
            if ($left_group !== $right_group) {
                if (!empty($left->passage_group_key) !== !empty($right->passage_group_key)) {
                    return !empty($left->passage_group_key) ? -1 : 1;
                }
                return strcmp($left_group, $right_group);
            }
            return (int) $left->display_order - (int) $right->display_order;
        });
        return $questions;
    }

    public function saveAnswer($student_session_id, $attempt_id, $question_snapshot_id, $response, $client_sequence = null, $request_received_at = null)
    {
        $this->db->trans_begin();
        $owned = $this->lockOwnedAttemptQuestion($student_session_id, $attempt_id, $question_snapshot_id);
        if (!$owned) {
            $this->db->trans_rollback();
            return array('status' => false, 'message' => 'The answer does not belong to your active assessment.');
        }
        if ($owned->attempt_status !== self::STATUS_IN_PROGRESS || $owned->paper_status !== self::STATUS_IN_PROGRESS) {
            $this->db->trans_rollback();
            return array('status' => false, 'message' => 'This paper is no longer open for answers.');
        }
        $request_received_at = $this->trustedRequestTime($request_received_at);
        if ((strtotime($owned->paper_deadline_at) + self::FINAL_SUBMISSION_GRACE_SECONDS) < $request_received_at) {
            $this->db->trans_rollback();
            return array('status' => false, 'expired' => true, 'preserve_local_queue' => true, 'message' => 'The server time for this paper has elapsed.');
        }
        $result = $this->persistAnswerInOpenTransaction(
            $owned,
            $attempt_id,
            $question_snapshot_id,
            $response,
            $client_sequence,
            true
        );
        if (empty($result['status']) || $this->db->trans_status() === false) {
            $this->db->trans_rollback();
            return empty($result['status'])
                ? $result
                : array('status' => false, 'message' => 'The answer could not be saved.');
        }
        $this->db->trans_commit();
        return $result;
    }

    /** Persist one validated answer while the caller owns the attempt transaction. */
    protected function persistAnswerInOpenTransaction($owned, $attempt_id, $question_snapshot_id, $response, $client_sequence = null, $enforce_selection_limit = true)
    {
        $normalized = $this->normalizeResponse($response);
        $response_validation = $this->validateAndNormalizeResponse($owned, $normalized);
        if (!$response_validation['valid']) {
            return array('status' => false, 'message' => $response_validation['message']);
        }
        $normalized = $response_validation['response'];
        $response_is_answered = $this->isAnswered($normalized);
        $now = date('Y-m-d H:i:s');
        $response_json = json_encode($normalized, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if ($response_json === false) {
            return array('status' => false, 'message' => 'The answer could not be encoded safely.');
        }
        $response_hash = hash('sha256', $response_json);
        $client_sequence = (int) $client_sequence;
        if ($client_sequence <= 0) {
            $client_sequence = (int) floor(microtime(true) * 1000);
        }

        $existing = $this->db->query(
            'SELECT * FROM `onlineexam_attempt_answers` WHERE `attempt_id` = '
            . $this->db->escape((int) $attempt_id)
            . ' AND `question_snapshot_id` = ' . $this->db->escape((int) $question_snapshot_id)
            . ' LIMIT 1 FOR UPDATE'
        )->row();
        if ($existing && (int) $existing->client_sequence > $client_sequence) {
            return array(
                'status' => true,
                'stale' => true,
                'answer_id' => (int) $existing->id,
                'saved_at' => $existing->saved_at,
                'version' => (int) $existing->version,
                'client_sequence' => (int) $existing->client_sequence,
            );
        }
        if ($existing && (int) $existing->client_sequence === $client_sequence) {
            if (hash_equals((string) $existing->response_hash, $response_hash)) {
                return array(
                    'status' => true,
                    'idempotent' => true,
                    'answer_id' => (int) $existing->id,
                    'saved_at' => $existing->saved_at,
                    'version' => (int) $existing->version,
                    'client_sequence' => (int) $existing->client_sequence,
                );
            }
            return array('status' => false, 'message' => 'An answer save sequence was reused with different content. Please reload this paper.');
        }

        if ($enforce_selection_limit && $response_is_answered && (!$existing || (int) $existing->is_answered !== 1)) {
            $selection_error = $this->selectionLimitError($attempt_id, $owned);
            if ($selection_error !== true) {
                return array('status' => false, 'message' => $selection_error);
            }
        }

        // Typed compact CBT responses are the only source of answered state.
        // Historical attachment metadata must never revive a retired response.
        $is_answered = $response_is_answered;
        $mark = $this->autoMark($owned, $normalized, $is_answered);
        $data = array(
            'response_json' => $response_json,
            'response_hash' => $response_hash,
            'is_answered' => $is_answered ? 1 : 0,
            'is_correct' => $mark['is_correct'],
            'auto_mark' => $mark['auto_mark'],
            'final_mark' => $mark['final_mark'],
            'marking_status' => $mark['marking_status'],
            'client_sequence' => $client_sequence,
            'saved_at' => $now,
            'updated_at' => $now,
        );
        if ($existing) {
            $data['version'] = ((int) $existing->version) + 1;
            $this->db->where('id', (int) $existing->id)->update('onlineexam_attempt_answers', $data);
            $answer_id = (int) $existing->id;
        } else {
            $data += array(
                'attempt_id' => (int) $attempt_id,
                'question_snapshot_id' => (int) $question_snapshot_id,
                'version' => 1,
                'created_at' => $now,
            );
            $this->db->insert('onlineexam_attempt_answers', $data);
            $answer_id = (int) $this->db->insert_id();
        }
        $this->db->where('id', (int) $attempt_id)->update('onlineexam_candidate_attempts', array(
            'last_saved_at' => $now,
            'updated_at' => $now,
        ));

        return array(
            'status' => true,
            'answer_id' => $answer_id,
            'saved_at' => $now,
            'version' => isset($data['version']) ? $data['version'] : 1,
            'client_sequence' => $client_sequence,
        );
    }

    public function attachFile($student_session_id, $attempt_id, $question_snapshot_id, array $file)
    {
        // Compact Online Examination accepts typed CBT answers only. Keeping
        // the method as a hard failure avoids breaking old callers while
        // ensuring no new attachment record can be created.
        return array('status' => false, 'message' => 'Answer attachments are no longer supported in Online Examination.');
    }

    public function submitPaper($student_session_id, $attempt_id, $paper_id, $submission_key, array $final_answers = array(), $request_received_at = null)
    {
        $this->db->trans_begin();
        $owned = $this->lockOwnedAttemptPaper($student_session_id, $attempt_id, $paper_id);
        if (!$owned) {
            $this->db->trans_rollback();
            return array('status' => false, 'message' => 'The paper does not belong to your assessment.');
        }
        if (!hash_equals((string) $owned->submission_key, (string) $submission_key)) {
            $this->db->trans_rollback();
            return array('status' => false, 'message' => 'The submission token is invalid.');
        }
        $encoded_final_answers = json_encode($final_answers, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if ($encoded_final_answers === false
            || strlen($encoded_final_answers) > self::FINAL_ANSWERS_MAX_BYTES
            || count($final_answers) > self::FINAL_ANSWERS_MAX_ITEMS) {
            $this->db->trans_rollback();
            return array('status' => false, 'code' => 'final_answers_too_large', 'preserve_local_queue' => true, 'message' => 'Too many queued answers were included. Reload the paper and retry.');
        }
        if (in_array($owned->paper_status, array(self::STATUS_SUBMITTED, self::STATUS_COMPLETED), true)) {
            if (!empty($final_answers)
                && !$this->terminalSubmissionAcknowledgesFinalAnswers($student_session_id, $attempt_id, $paper_id, $final_answers)) {
                $this->db->trans_rollback();
                return array(
                    'status' => false,
                    'code' => 'terminal_submission_mismatch',
                    'preserve_local_queue' => true,
                    'message' => 'The paper is already closed, but one or more answers from this device were not recorded. Contact an invigilator.',
                );
            }
            $this->db->trans_commit();
            return array(
                'status' => true,
                'idempotent' => true,
                'final_answers_applied' => !empty($final_answers),
                'attempt_id' => (int) $attempt_id,
                'attempt_status' => $owned->paper_status === self::STATUS_COMPLETED ? self::STATUS_COMPLETED : $owned->attempt_status,
            );
        }
        if ($owned->attempt_status !== self::STATUS_IN_PROGRESS || $owned->paper_status !== self::STATUS_IN_PROGRESS) {
            $this->db->trans_rollback();
            return array('status' => false, 'preserve_local_queue' => !empty($final_answers), 'message' => 'This official attempt is no longer open.');
        }
        if ($owned->delivery_mode !== 'cbt'
            || !in_array($owned->paper_type, array('objective', 'theory'), true)
            || !$this->compactPaperQuestionsSupported($owned->onlineexam_id, $owned->revision, $paper_id, $owned->paper_type)) {
            $this->db->trans_rollback();
            return array('status' => false, 'preserve_local_queue' => !empty($final_answers), 'message' => 'This retired paper or response type cannot be submitted in compact CBT.');
        }

        $request_received_at = $this->trustedRequestTime($request_received_at);
        $deadline_timestamp = !empty($owned->paper_deadline_at) ? strtotime($owned->paper_deadline_at) : 0;
        $timed_out = $deadline_timestamp > 0 && $deadline_timestamp <= $request_received_at;
        if (!empty($final_answers)
            && $timed_out
            && $request_received_at > ($deadline_timestamp + self::FINAL_SUBMISSION_GRACE_SECONDS)) {
            $this->db->trans_rollback();
            return array(
                'status' => false,
                'code' => 'unsaved_answers_expired',
                'expired' => true,
                'preserve_local_queue' => true,
                'message' => 'Queued answers arrived after the secure final-submission window and were not discarded. Contact an invigilator.',
            );
        }
        $seen_question_ids = array();
        $final_answer_counts = array('applied' => 0, 'stale' => 0, 'idempotent' => 0);
        foreach ($final_answers as $index => $answer) {
            if (!is_array($answer)
                || empty($answer['question_snapshot_id'])
                || !array_key_exists('response', $answer)) {
                $this->db->trans_rollback();
                return array('status' => false, 'code' => 'invalid_final_answer', 'preserve_local_queue' => true, 'message' => 'A queued answer has an invalid format.');
            }
            $question_snapshot_id = (int) $answer['question_snapshot_id'];
            if (isset($seen_question_ids[$question_snapshot_id])) {
                $this->db->trans_rollback();
                return array('status' => false, 'code' => 'duplicate_final_answer', 'preserve_local_queue' => true, 'message' => 'A queued answer was included more than once.');
            }
            $seen_question_ids[$question_snapshot_id] = true;
            $owned_question = $this->lockOwnedAttemptQuestion($student_session_id, $attempt_id, $question_snapshot_id);
            if (!$owned_question
                || (int) $owned_question->paper_id !== (int) $paper_id
                || $owned_question->attempt_status !== self::STATUS_IN_PROGRESS
                || $owned_question->paper_status !== self::STATUS_IN_PROGRESS) {
                $this->db->trans_rollback();
                return array('status' => false, 'code' => 'invalid_final_answer', 'preserve_local_queue' => true, 'message' => 'A queued answer does not belong to this open paper.');
            }
            $answer_result = $this->persistAnswerInOpenTransaction(
                $owned_question,
                $attempt_id,
                $question_snapshot_id,
                $answer['response'],
                isset($answer['client_sequence']) ? $answer['client_sequence'] : null,
                false
            );
            if (empty($answer_result['status'])) {
                $this->db->trans_rollback();
                $answer_result['code'] = 'final_answer_rejected';
                $answer_result['preserve_local_queue'] = true;
                return $answer_result;
            }
            if (!empty($answer_result['stale'])) {
                $final_answer_counts['stale']++;
            } elseif (!empty($answer_result['idempotent'])) {
                $final_answer_counts['idempotent']++;
            } else {
                $final_answer_counts['applied']++;
            }
        }
        if ($this->db->trans_status() === false) {
            $this->db->trans_rollback();
            return array('status' => false, 'code' => 'final_answer_save_failed', 'preserve_local_queue' => true, 'message' => 'Queued answers could not be saved, so the paper was not submitted. Please retry.');
        }
        if (!$timed_out) {
            $selection_error = $this->validateSectionSelections($attempt_id, $paper_id);
            if ($selection_error !== true) {
                $this->db->trans_rollback();
                return array('status' => false, 'message' => $selection_error);
            }
        }

        $now = date('Y-m-d H:i:s');
        $this->createUnansweredRows($attempt_id, $paper_id, $now);

        $scores = $this->calculatePaperScore($attempt_id, $paper_id, $owned);
        $submission_updates = array(
            'status' => self::STATUS_SUBMITTED, 'submitted_at' => $now,
            'raw_score' => $scores['raw_score'], 'raw_max_score' => $scores['raw_max_score'],
            'contribution_score' => $scores['contribution_score'], 'contribution_max_score' => $scores['contribution_max_score'],
            'updated_at' => $now,
        );
        if ($this->db->field_exists('completion_source', 'onlineexam_attempt_papers')) {
            $submission_updates['completion_source'] = $timed_out ? 'timed_out' : 'submitted';
        }
        $this->db
            ->where('attempt_id', (int) $attempt_id)
            ->where('paper_id', (int) $paper_id)
            ->update('onlineexam_attempt_papers', $submission_updates);

        $attempt_status = $this->recalculateAttempt($attempt_id, $now, $timed_out);
        if ($this->db->trans_status() === false) {
            $this->db->trans_rollback();
            return array('status' => false, 'message' => 'The paper could not be submitted. Please retry.');
        }
        $this->db->trans_commit();

        return array(
            'status' => true,
            'attempt_id' => (int) $attempt_id,
            'onlineexam_id' => (int) $owned->onlineexam_id,
            'attempt_status' => $attempt_status,
            'timed_out' => $timed_out,
            'final_answers_applied' => !empty($final_answers),
            'final_answer_counts' => $final_answer_counts,
            'paper_score' => $scores,
        );
    }

    /**
     * A retry after a lost response may clear browser storage only when every
     * submitted client sequence is already represented by the terminal paper.
     */
    protected function terminalSubmissionAcknowledgesFinalAnswers($student_session_id, $attempt_id, $paper_id, array $final_answers)
    {
        $seen = array();
        foreach ($final_answers as $answer) {
            if (!is_array($answer)
                || empty($answer['question_snapshot_id'])
                || !array_key_exists('response', $answer)
                || empty($answer['client_sequence'])) {
                return false;
            }
            $question_id = (int) $answer['question_snapshot_id'];
            if (isset($seen[$question_id])) {
                return false;
            }
            $seen[$question_id] = true;

            $owned_question = $this->lockOwnedAttemptQuestion($student_session_id, $attempt_id, $question_id);
            if (!$owned_question || (int) $owned_question->paper_id !== (int) $paper_id) {
                return false;
            }
            $normalized = $this->normalizeResponse($answer['response']);
            $validation = $this->validateAndNormalizeResponse($owned_question, $normalized);
            if (empty($validation['valid'])) {
                return false;
            }
            $encoded = json_encode($validation['response'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            if ($encoded === false) {
                return false;
            }
            $stored = $this->db->query(
                'SELECT `client_sequence`, `response_hash` FROM `onlineexam_attempt_answers` WHERE `attempt_id` = '
                . $this->db->escape((int) $attempt_id)
                . ' AND `question_snapshot_id` = ' . $this->db->escape($question_id)
                . ' LIMIT 1 FOR UPDATE'
            )->row();
            $client_sequence = (int) $answer['client_sequence'];
            if (!$stored) {
                return false;
            }
            $stored_sequence = (int) $stored->client_sequence;
            $submitted_hash = hash('sha256', $encoded);
            // A browser retry can allocate a newer sequence after the first
            // committed response was lost. It is still safe to acknowledge
            // when the content is identical. A genuinely newer stored answer
            // also supersedes an older queued sequence safely.
            if ($stored_sequence <= $client_sequence
                && !hash_equals((string) $stored->response_hash, $submitted_hash)) {
                return false;
            }
        }
        return true;
    }

    protected function trustedRequestTime($request_received_at)
    {
        $request_received_at = is_numeric($request_received_at) ? (float) $request_received_at : microtime(true);
        return $request_received_at > 0 ? $request_received_at : microtime(true);
    }

    public function getOwnedAttempt($student_session_id, $attempt_id)
    {
        return $this->db
            ->select('onlineexam_candidate_attempts.*, onlineexam_students.student_session_id, onlineexam.result_adapter, onlineexam.target_component, onlineexam.target_max_score, onlineexam.workflow_version')
            ->from('onlineexam_candidate_attempts')
            ->join('onlineexam_students', 'onlineexam_students.id = onlineexam_candidate_attempts.onlineexam_student_id')
            ->join('onlineexam', 'onlineexam.id = onlineexam_candidate_attempts.onlineexam_id')
            ->where('onlineexam_candidate_attempts.id', (int) $attempt_id)
            ->where('onlineexam_students.student_session_id', (int) $student_session_id)
            ->limit(1)
            ->get()
            ->row();
    }

    /** Candidate-visible review, gated independently from report publication. */
    public function getReleasedFeedback($student_session_id, $attempt_id)
    {
        $attempt = $this->db
            ->select('a.*, e.feedback_status')
            ->from('onlineexam_candidate_attempts a')
            ->join('onlineexam_students os', 'os.id = a.onlineexam_student_id')
            ->join('onlineexam e', 'e.id = a.onlineexam_id')
            ->where('a.id', (int) $attempt_id)
            ->where('os.student_session_id', (int) $student_session_id)
            ->where('a.status', self::STATUS_COMPLETED)
            ->where('e.feedback_status', 'released')
            ->limit(1)->get()->row_array();
        if (empty($attempt)) {
            return array();
        }
        if ($this->db->field_exists('completion_source', 'onlineexam_attempt_papers')) {
            $this->db->where("NOT EXISTS (SELECT 1 FROM onlineexam_attempt_papers feedback_p WHERE feedback_p.attempt_id = " . (int) $attempt_id
                . " AND feedback_p.paper_id = qs.paper_id AND feedback_p.completion_source = 'manual')", null, false);
        }
        $questions = $this->db
            ->select('qs.id, qs.paper_id, qs.paper_section_id, qs.question_type, qs.question_text, qs.options_json, qs.correct_answer_json, qs.marking_scheme, qs.marks, qs.display_order, aa.response_json, aa.is_answered, aa.is_correct, aa.final_mark, aa.marking_status, aa.attachment_name')
            ->from('onlineexam_question_snapshots qs')
            ->join('onlineexam_attempt_answers aa', 'aa.question_snapshot_id = qs.id AND aa.attempt_id = ' . (int) $attempt_id, 'left')
            ->where('qs.onlineexam_id', (int) $attempt['onlineexam_id'])
            ->where('qs.revision', (int) $attempt['revision'])
            ->order_by('qs.paper_id', 'ASC')->order_by('qs.display_order', 'ASC')
            ->get()->result_array();
        $configuration = $this->getRevisionConfiguration((int) $attempt['onlineexam_id'], (int) $attempt['revision']);
        $paper_map = array();
        $section_map = array();
        foreach (isset($configuration['papers']) ? (array) $configuration['papers'] : array() as $paper) {
            $paper_map[(int) $paper['id']] = $paper;
            foreach (isset($paper['sections']) ? (array) $paper['sections'] : array() as $section) {
                $section_map[(int) $section['id']] = $section;
            }
        }
        foreach ($questions as &$question) {
            $question['paper_title'] = isset($paper_map[(int) $question['paper_id']]['title'])
                ? $paper_map[(int) $question['paper_id']]['title'] : 'Paper';
            $question['section_title'] = isset($section_map[(int) $question['paper_section_id']]['title'])
                ? $section_map[(int) $question['paper_section_id']]['title'] : null;
            foreach (array('response_json', 'correct_answer_json', 'options_json') as $field) {
                $decoded = json_decode($question[$field], true);
                $question[str_replace('_json', '', $field)] = json_last_error() === JSON_ERROR_NONE ? $decoded : $question[$field];
            }
        }
        unset($question);
        return array('attempt' => $attempt, 'questions' => $questions);
    }

    public function isSupportedAssessmentContext($context)
    {
        if (!$context || !empty($context->deleted_at) || (int) $context->workflow_version < 2) {
            return false;
        }

        $adapters = array(
            'ca' => 'standard_component',
            'midterm' => 'standard_component',
            'exam' => 'standard_component',
            'holiday' => 'holiday_assessment',
            'kindergarten' => 'kindergarten_concept',
            'british' => 'british_outcome',
        );
        $purpose = isset($context->purpose) ? (string) $context->purpose : '';
        $adapter = isset($context->result_adapter) ? (string) $context->result_adapter : '';
        if (!isset($adapters[$purpose]) || $adapters[$purpose] !== $adapter) {
            return false;
        }

        $onlineexam_id = isset($context->id) ? (int) $context->id : 0;
        $revision = isset($context->revision) ? (int) $context->revision : 0;
        if ($onlineexam_id < 1 || $revision < 1) {
            return false;
        }

        $active_papers = 0;
        foreach ($this->getFrozenPapers($onlineexam_id, $revision) as $paper) {
            if (isset($paper['is_active']) && (int) $paper['is_active'] !== 1) {
                continue;
            }
            $active_papers++;
            if (!isset($paper['id'], $paper['delivery_mode'], $paper['paper_type'])
                || $paper['delivery_mode'] !== 'cbt'
                || !in_array($paper['paper_type'], array('objective', 'theory'), true)
                || !$this->compactPaperQuestionsSupported(
                    $onlineexam_id,
                    $revision,
                    (int) $paper['id'],
                    $paper['paper_type']
                )) {
                return false;
            }
        }
        return $active_papers > 0;
    }

    protected function validateCandidateContext($context, $extra_time_minutes = 0, $attempt = null)
    {
        if (!$this->isSupportedAssessmentContext($context)) {
            return 'This historical assessment is read-only.';
        }
        if ($context->candidate_status !== 'assigned') {
            return 'You are not an active candidate for this assessment.';
        }
        if ((int) $context->class_id !== (int) $context->enrolled_class_id) {
            return 'Your current class does not match this assessment.';
        }
        $configuration = $this->getRevisionConfiguration($context->id, $context->revision);
        $assigned_sections = isset($configuration['academic_context']['section_ids'])
            ? array_map('intval', (array) $configuration['academic_context']['section_ids'])
            : array();
        $section_is_assigned = !empty($assigned_sections)
            ? in_array((int) $context->enrolled_section_id, $assigned_sections, true)
            : $this->db
                ->where('onlineexam_id', (int) $context->id)
                ->where('section_id', (int) $context->enrolled_section_id)
                ->count_all_results('onlineexam_class_sections') > 0;
        if (!$section_is_assigned) {
            return 'Your current class arm is not assigned to this assessment.';
        }
        $allowed_lifecycles = !empty($context->candidate_paper_override)
            ? array('scheduled', 'published', 'in_progress', 'marking', 'completed')
            : array('scheduled', 'published', 'in_progress');
        if (!in_array($context->lifecycle_status, $allowed_lifecycles, true)
            || (string) $context->is_active !== '1') {
            return 'This assessment has not been published.';
        }
        $now = time();
        if (!empty($context->exam_from) && $now < strtotime($context->exam_from)) {
            return 'This assessment has not started.';
        }
        if (!empty($context->exam_to) && $now >= (strtotime($context->exam_to) + (max(0, (int) $extra_time_minutes) * 60))) {
            return 'This assessment has closed.';
        }
        return true;
    }

    protected function validatePaperWindow($paper, $context, $extra_time_minutes = 0, $attempt = null)
    {
        $now = time();
        $starts_at = !empty($paper->starts_at) ? strtotime($paper->starts_at) : strtotime($context->exam_from);
        $ends_at = !empty($paper->ends_at) ? strtotime($paper->ends_at) : strtotime($context->exam_to);
        if ($ends_at) {
            $ends_at += max(0, (int) $extra_time_minutes) * 60;
        }
        if ($starts_at && $now < $starts_at) {
            return 'This paper has not started.';
        }
        if ($ends_at && $now >= $ends_at) {
            return 'This paper has closed.';
        }
        return true;
    }

    protected function getOrCreateAttempt($context)
    {
        $attempt_count = $this->db
            ->where('onlineexam_student_id', (int) $context->onlineexam_student_id)
            ->where('status !=', self::STATUS_VOIDED)
            ->count_all_results('onlineexam_candidate_attempts');

        $accommodation = $this->db
            ->from('onlineexam_accommodations')
            ->where('onlineexam_id', (int) $context->id)
            ->where('onlineexam_student_id', (int) $context->onlineexam_student_id)
            ->limit(1)
            ->get()
            ->row();

        $attempt = $this->getCurrentAttempt($context->onlineexam_student_id);
        if ($attempt) {
            // Opening/reloading a paper always resumes the same non-voided
            // official attempt. A replacement is possible only after staff
            // explicitly void the earlier attempt and retain its audit trail.
            return $attempt;
        }
        if ($attempt_count >= 1) {
            return array('status' => false, 'message' => 'No official attempts remain for this assessment.');
        }

        $last_attempt = $this->db
            ->select_max('attempt_no', 'last_attempt_no')
            ->where('onlineexam_student_id', (int) $context->onlineexam_student_id)
            ->get('onlineexam_candidate_attempts')
            ->row();
        $next_attempt_no = $last_attempt && $last_attempt->last_attempt_no !== null
            ? ((int) $last_attempt->last_attempt_no + 1)
            : 1;

        $now = date('Y-m-d H:i:s');
        $extra_time = $accommodation ? (int) $accommodation->extra_time_minutes : 0;
        $deadline = !empty($context->exam_to) ? $context->exam_to : date('Y-m-d H:i:s', time() + 86400);
        $data = array(
            'onlineexam_id' => (int) $context->id,
            'onlineexam_student_id' => (int) $context->onlineexam_student_id,
            'revision' => (int) $context->revision,
            'attempt_no' => $next_attempt_no,
            'status' => self::STATUS_IN_PROGRESS,
            'started_at' => $now,
            'deadline_at' => $deadline,
            'submission_key' => bin2hex(random_bytes(32)),
            'extra_time_minutes' => $extra_time,
            'manual_marking_required' => 0,
            'marking_status' => 'not_required',
            'created_at' => $now,
            'updated_at' => $now,
        );
        $this->db->insert('onlineexam_candidate_attempts', $data);
        $data['id'] = $this->db->insert_id();
        return (object) $data;
    }

    protected function calculatePaperDeadline(DateTimeImmutable $now, $paper, $context, $extra_time_minutes, $attempt = null)
    {
        $duration = max(1, (int) $paper->duration_minutes) + max(0, (int) $extra_time_minutes);
        $deadline = $now->modify('+' . $duration . ' minutes');
        $hard_ends = array($paper->ends_at, $context->exam_to);
        foreach ($hard_ends as $hard_end) {
            if (!empty($hard_end)) {
                $candidate = new DateTimeImmutable($hard_end);
                if ((int) $extra_time_minutes > 0 && count($hard_ends) > 1) {
                    // An authorized accommodation extends the scheduled hard
                    // stop; otherwise an "extra time" award would be erased
                    // whenever the paper window equals its normal duration.
                    $candidate = $candidate->modify('+' . (int) $extra_time_minutes . ' minutes');
                }
                if ($candidate < $deadline) {
                    $deadline = $candidate;
                }
            }
        }
        return $deadline;
    }

    protected function getOwnedAttemptQuestion($student_session_id, $attempt_id, $question_snapshot_id)
    {
        return $this->loadOwnedAttemptQuestion($student_session_id, $attempt_id, $question_snapshot_id, false);
    }

    protected function lockOwnedAttemptQuestion($student_session_id, $attempt_id, $question_snapshot_id)
    {
        return $this->loadOwnedAttemptQuestion($student_session_id, $attempt_id, $question_snapshot_id, true);
    }

    protected function loadOwnedAttemptQuestion($student_session_id, $attempt_id, $question_snapshot_id, $lock)
    {
        $sql = 'SELECT a.status AS attempt_status, a.submission_key, ap.status AS paper_status, '
            . 'ap.deadline_at AS paper_deadline_at, q.*, oe.is_neg_marking '
            . 'FROM `onlineexam_candidate_attempts` a '
            . 'INNER JOIN `onlineexam_students` os ON os.id = a.onlineexam_student_id '
            . 'INNER JOIN `onlineexam` oe ON oe.id = a.onlineexam_id '
            . 'INNER JOIN `onlineexam_question_snapshots` q ON q.onlineexam_id = a.onlineexam_id AND q.revision = a.revision '
            . 'INNER JOIN `onlineexam_attempt_papers` ap ON ap.attempt_id = a.id AND ap.paper_id = q.paper_id '
            . 'WHERE a.id = ' . $this->db->escape((int) $attempt_id)
            . ' AND q.id = ' . $this->db->escape((int) $question_snapshot_id)
            . ' AND os.student_session_id = ' . $this->db->escape((int) $student_session_id)
            . ' LIMIT 1' . ($lock ? ' FOR UPDATE' : '');
        return $this->db->query($sql)->row();
    }

    protected function getOwnedAttemptPaper($student_session_id, $attempt_id, $paper_id)
    {
        return $this->loadOwnedAttemptPaper($student_session_id, $attempt_id, $paper_id, false);
    }

    protected function lockOwnedAttemptPaper($student_session_id, $attempt_id, $paper_id)
    {
        return $this->loadOwnedAttemptPaper($student_session_id, $attempt_id, $paper_id, true);
    }

    protected function loadOwnedAttemptPaper($student_session_id, $attempt_id, $paper_id, $lock)
    {
        $sql = 'SELECT a.onlineexam_id, a.revision, a.status AS attempt_status, a.submission_key, '
            . 'ap.status AS paper_status, ap.deadline_at AS paper_deadline_at, oe.is_neg_marking '
            . 'FROM `onlineexam_candidate_attempts` a '
            . 'INNER JOIN `onlineexam_students` os ON os.id = a.onlineexam_student_id '
            . 'INNER JOIN `onlineexam` oe ON oe.id = a.onlineexam_id '
            . 'INNER JOIN `onlineexam_attempt_papers` ap ON ap.attempt_id = a.id '
            . 'WHERE a.id = ' . $this->db->escape((int) $attempt_id)
            . ' AND ap.paper_id = ' . $this->db->escape((int) $paper_id)
            . ' AND os.student_session_id = ' . $this->db->escape((int) $student_session_id)
            . ' LIMIT 1' . ($lock ? ' FOR UPDATE' : '');
        $row = $this->db->query($sql)->row();
        if (!$row) {
            return null;
        }
        $paper = $this->getFrozenPaper($row->onlineexam_id, $row->revision, $paper_id);
        if (!$paper) {
            return null;
        }
        foreach (get_object_vars($paper) as $key => $value) {
            $row->{$key} = $value;
        }
        return $row;
    }

    protected function normalizeResponse($response)
    {
        if (is_array($response)) {
            return $response;
        }
        if (is_string($response)) {
            $trimmed = trim($response);
            if ($trimmed !== '' && in_array(substr($trimmed, 0, 1), array('[', '{', '"'), true)) {
                $decoded = json_decode($trimmed, true);
                if (json_last_error() === JSON_ERROR_NONE) {
                    return $decoded;
                }
            }
            return $trimmed;
        }
        return $response;
    }

    protected function validateAndNormalizeResponse($question, $response)
    {
        $type = strtolower((string) $question->question_type);
        if (!in_array($type, array(
            'singlechoice', 'multichoice', 'true_false', 'short_answer',
            'numeric', 'matching', 'ordering', 'long_answer'
        ), true)) {
            return array('valid' => false, 'response' => null, 'message' => 'This retired response type is not accepted by compact CBT.');
        }
        if (!$this->isAnswered($response)) {
            return array('valid' => true, 'response' => $response, 'message' => '');
        }
        $options = json_decode((string) $question->options_json, true);
        $options = is_array($options) ? $options : array();
        if (in_array($type, array('singlechoice', 'single_choice'), true)) {
            if (is_array($response) || !array_key_exists((string) $response, $options)) {
                return array('valid' => false, 'response' => null, 'message' => 'Select one of the available options.');
            }
        } elseif (in_array($type, array('multichoice', 'multiple_choice'), true)) {
            if (!is_array($response)) {
                return array('valid' => false, 'response' => null, 'message' => 'Multiple-choice answers must be submitted as a selection list.');
            }
            $response = array_values(array_unique(array_map('strval', $response)));
            foreach ($response as $choice) {
                if (!array_key_exists($choice, $options)) {
                    return array('valid' => false, 'response' => null, 'message' => 'A selected option is not part of this question.');
                }
            }
        } elseif (in_array($type, array('true_false', 'true/false'), true)) {
            $response = strtolower((string) $response);
            if (!in_array($response, array('true', 'false'), true)) {
                return array('valid' => false, 'response' => null, 'message' => 'Select either True or False.');
            }
        } elseif ($type === 'numeric') {
            if (is_array($response) || !is_numeric($response)) {
                return array('valid' => false, 'response' => null, 'message' => 'Enter a valid numeric answer.');
            }
            $response = trim((string) $response);
        } elseif ($type === 'matching') {
            if (!is_array($response) || empty($options['matching_left']) || empty($options['matching_right'])) {
                return array('valid' => false, 'response' => null, 'message' => 'The matching response is invalid.');
            }
            $left_ids = array_map(function ($item) { return (string) $item['id']; }, $options['matching_left']);
            $right_ids = array_map(function ($item) { return (string) $item['id']; }, $options['matching_right']);
            $clean = array();
            foreach ($response as $left_id => $right_id) {
                $left_id = (string) $left_id;
                $right_id = (string) $right_id;
                if (!in_array($left_id, $left_ids, true) || ($right_id !== '' && !in_array($right_id, $right_ids, true))) {
                    return array('valid' => false, 'response' => null, 'message' => 'A matching item is not part of this question.');
                }
                $clean[$left_id] = $right_id;
            }
            $response = $clean;
        } elseif ($type === 'ordering') {
            if (!is_array($response) || empty($options['dynamic'])) {
                return array('valid' => false, 'response' => null, 'message' => 'The ordering response is invalid.');
            }
            $allowed = array_map(function ($item) { return (string) $item['id']; }, $options['dynamic']);
            $selected = array();
            foreach ($response as $item_id) {
                $item_id = (string) $item_id;
                if ($item_id !== '' && !in_array($item_id, $allowed, true)) {
                    return array('valid' => false, 'response' => null, 'message' => 'An ordering item is not part of this question.');
                }
                if ($item_id !== '') {
                    $selected[] = $item_id;
                }
            }
            if (count($selected) !== count(array_unique($selected))) {
                return array('valid' => false, 'response' => null, 'message' => 'Each ordering item can be selected only once.');
            }
            $response = array_values($response);
        } elseif (is_array($response)) {
            return array('valid' => false, 'response' => null, 'message' => 'This response must be submitted as text.');
        } elseif (mb_strlen((string) $response) > 100000) {
            return array('valid' => false, 'response' => null, 'message' => 'The response is too long.');
        }
        return array('valid' => true, 'response' => $response, 'message' => '');
    }

    protected function isAnswered($response)
    {
        if (is_array($response)) {
            return count(array_filter($response, function ($value) {
                return $value !== null && $value !== '';
            })) > 0;
        }
        return $response !== null && trim((string) $response) !== '';
    }

    protected function autoMark($question, $response, $is_answered)
    {
        $manual_types = array('long_answer');
        if (in_array($question->question_type, $manual_types, true)) {
            return array(
                'is_correct' => null,
                'auto_mark' => null,
                'final_mark' => 0,
                'marking_status' => $is_answered ? 'awaiting_manual' : 'not_required',
            );
        }
        if (!$is_answered) {
            return array('is_correct' => null, 'auto_mark' => 0, 'final_mark' => 0, 'marking_status' => 'auto_marked');
        }

        $correct = json_decode($question->correct_answer_json, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            $correct = $question->correct_answer_json;
        } elseif (is_string($correct)) {
            $trimmed_correct = trim($correct);
            if ($trimmed_correct !== '' && in_array(substr($trimmed_correct, 0, 1), array('[', '{', '"'), true)) {
                $nested = json_decode($trimmed_correct, true);
                if (json_last_error() === JSON_ERROR_NONE) {
                    $correct = $nested;
                }
            }
        }
        $is_correct = $this->responsesEqual($response, $correct, $question->question_type);
        $auto_mark = $is_correct ? (float) $question->marks : 0.0;
        if (!$is_correct && (int) $question->is_neg_marking === 1) {
            $auto_mark = -abs((float) $question->neg_marks);
        }
        return array(
            'is_correct' => $is_correct ? 1 : 0,
            'auto_mark' => $auto_mark,
            'final_mark' => $auto_mark,
            'marking_status' => 'auto_marked',
        );
    }

    protected function responsesEqual($response, $correct, $question_type)
    {
        if ($question_type === 'matching') {
            if (!is_array($response) || !is_array($correct)) {
                return false;
            }
            $normalize_map = function (array $values) {
                $normalized = array();
                foreach ($values as $left => $right) {
                    $normalized[preg_replace('/^opt_/', '', (string) $left)] = preg_replace('/^opt_/', '', (string) $right);
                }
                ksort($normalized);
                return $normalized;
            };
            return $normalize_map($response) === $normalize_map($correct);
        }
        if (in_array($question_type, array('multichoice', 'ordering'), true)) {
            $response = is_array($response) ? $response : array($response);
            $correct = is_array($correct) ? $correct : array($correct);
            $normalize = function ($value) {
                return preg_replace('/^opt_/', '', (string) $value);
            };
            $response = array_map($normalize, $response);
            $correct = array_map($normalize, $correct);
            if ($question_type !== 'ordering') {
                sort($response);
                sort($correct);
            }
            return $response === $correct;
        }
        if ($question_type === 'numeric') {
            $expected = is_array($correct) && isset($correct['value']) ? $correct['value'] : $correct;
            $tolerance = is_array($correct) && isset($correct['tolerance']) ? (float) $correct['tolerance'] : 0.0;
            return is_numeric($response) && is_numeric($expected) && abs((float) $response - (float) $expected) <= $tolerance;
        }
        if (is_array($correct) && array_key_exists('value', $correct)) {
            $correct = $correct['value'];
        }
        if (in_array($question_type, array('short_answer', 'fill_blank'), true)) {
            $response = mb_strtolower(trim((string) $response));
            $accepted = is_array($correct) ? $correct : array($correct);
            foreach ($accepted as $answer) {
                if ($response === mb_strtolower(trim((string) $answer))) {
                    return true;
                }
            }
            return false;
        }
        $response = preg_replace('/^opt_/', '', (string) $response);
        $correct = preg_replace('/^opt_/', '', (string) $correct);
        return $response === $correct;
    }

    protected function validateSectionSelections($attempt_id, $paper_id)
    {
        $attempt = $this->db->select('onlineexam_id, revision')
            ->where('id', (int) $attempt_id)
            ->get('onlineexam_candidate_attempts')
            ->row();
        $paper = $attempt ? $this->getFrozenPaper($attempt->onlineexam_id, $attempt->revision, $paper_id) : null;
        $sections = array();
        if ($paper && !empty($paper->sections)) {
            foreach ($paper->sections as $section) {
                if (in_array($section['answer_rule'], array('answer_any', 'compulsory_plus_choice'), true)) {
                    $sections[] = (object) $section;
                }
            }
        }

        foreach ($sections as $section) {
            $this->db
                ->from('onlineexam_attempt_answers')
                ->join('onlineexam_question_snapshots', 'onlineexam_question_snapshots.id = onlineexam_attempt_answers.question_snapshot_id')
                ->where('onlineexam_attempt_answers.attempt_id', (int) $attempt_id)
                ->where('onlineexam_question_snapshots.paper_section_id', (int) $section->id)
                ->where('onlineexam_attempt_answers.is_answered', 1);
            if ($section->answer_rule === 'compulsory_plus_choice') {
                $this->db->where('onlineexam_question_snapshots.is_compulsory', 0);
            }
            $answered = $this->db->count_all_results();
            if ($section->answer_count !== null && $answered > (int) $section->answer_count) {
                return 'You may answer only ' . (int) $section->answer_count . ' question(s) in ' . $section->title . '.';
            }
        }
        return true;
    }

    /** Enforce answer-any caps during autosave as well as at submission. */
    protected function selectionLimitError($attempt_id, $question)
    {
        if (empty($question->paper_section_id)) {
            return true;
        }
        $paper = $this->getFrozenPaper($question->onlineexam_id, $question->revision, $question->paper_id);
        if (!$paper || empty($paper->sections)) {
            return true;
        }
        $section = null;
        foreach ($paper->sections as $candidate) {
            if ((int) $candidate['id'] === (int) $question->paper_section_id) {
                $section = $candidate;
                break;
            }
        }
        if (!$section || !in_array($section['answer_rule'], array('answer_any', 'compulsory_plus_choice'), true)) {
            return true;
        }
        if ($section['answer_rule'] === 'compulsory_plus_choice' && (int) $question->is_compulsory === 1) {
            return true;
        }
        $this->db->from('onlineexam_attempt_answers aa')
            ->join('onlineexam_question_snapshots qs', 'qs.id = aa.question_snapshot_id')
            ->where('aa.attempt_id', (int) $attempt_id)
            ->where('aa.is_answered', 1)
            ->where('qs.paper_section_id', (int) $question->paper_section_id);
        if ($section['answer_rule'] === 'compulsory_plus_choice') {
            $this->db->where('qs.is_compulsory', 0);
        }
        $answered = $this->db->count_all_results();
        if ($answered >= (int) $section['answer_count']) {
            return 'This section already has its permitted ' . (int) $section['answer_count'] . ' optional answer(s). Clear one before answering another.';
        }
        return true;
    }

    protected function createUnansweredRows($attempt_id, $paper_id, $now)
    {
        $missing = $this->db
            ->select('onlineexam_question_snapshots.id, onlineexam_question_snapshots.question_type')
            ->from('onlineexam_question_snapshots')
            ->join('onlineexam_candidate_attempts', 'onlineexam_candidate_attempts.id = ' . (int) $attempt_id . ' AND onlineexam_candidate_attempts.onlineexam_id = onlineexam_question_snapshots.onlineexam_id AND onlineexam_candidate_attempts.revision = onlineexam_question_snapshots.revision')
            ->join(
                'onlineexam_attempt_answers',
                'onlineexam_attempt_answers.question_snapshot_id = onlineexam_question_snapshots.id AND onlineexam_attempt_answers.attempt_id = ' . (int) $attempt_id,
                'left'
            )
            ->where('onlineexam_question_snapshots.paper_id', (int) $paper_id)
            ->where('onlineexam_attempt_answers.id IS NULL', null, false)
            ->get()
            ->result();

        foreach ($missing as $question) {
            $this->db->insert('onlineexam_attempt_answers', array(
                'attempt_id' => (int) $attempt_id,
                'question_snapshot_id' => (int) $question->id,
                'is_answered' => 0,
                'auto_mark' => 0,
                'final_mark' => 0,
                'marking_status' => 'not_required',
                'version' => 1,
                'saved_at' => $now,
                'submitted_at' => $now,
                'created_at' => $now,
                'updated_at' => $now,
            ));
        }
        $question_ids = $this->questionIdsForPaper($attempt_id, $paper_id);
        if (!empty($question_ids)) {
            $this->db
                ->where('attempt_id', (int) $attempt_id)
                ->where_in('question_snapshot_id', $question_ids)
                ->update('onlineexam_attempt_answers', array('submitted_at' => $now, 'updated_at' => $now));
        }
    }

    protected function questionIdsForPaper($attempt_id, $paper_id)
    {
        $rows = $this->db
            ->select('onlineexam_question_snapshots.id')
            ->from('onlineexam_question_snapshots')
            ->join('onlineexam_candidate_attempts', 'onlineexam_candidate_attempts.id = ' . (int) $attempt_id . ' AND onlineexam_candidate_attempts.onlineexam_id = onlineexam_question_snapshots.onlineexam_id AND onlineexam_candidate_attempts.revision = onlineexam_question_snapshots.revision')
            ->where('paper_id', (int) $paper_id)
            ->get()
            ->result_array();
        return array_map(function ($row) {
            return (int) $row['id'];
        }, $rows);
    }

    protected function calculatePaperScore($attempt_id, $paper_id, $paper)
    {
        $rows = $this->db
            ->select('onlineexam_attempt_answers.final_mark, onlineexam_attempt_answers.is_answered, onlineexam_attempt_answers.created_at AS answer_created_at, onlineexam_question_snapshots.paper_section_id, onlineexam_question_snapshots.is_compulsory, onlineexam_question_snapshots.display_order')
            ->from('onlineexam_attempt_answers')
            ->join('onlineexam_question_snapshots', 'onlineexam_question_snapshots.id = onlineexam_attempt_answers.question_snapshot_id')
            ->where('onlineexam_attempt_answers.attempt_id', (int) $attempt_id)
            ->where('onlineexam_question_snapshots.paper_id', (int) $paper_id)
            ->get()->result_array();

        $section_rules = array();
        foreach (!empty($paper->sections) ? $paper->sections : array() as $section) {
            $section_rules[(int) $section['id']] = $section;
        }
        $earned = 0.0;
        $choice_rows = array();
        foreach ($rows as $answer) {
            $section_id = (int) $answer['paper_section_id'];
            $rule = isset($section_rules[$section_id]) ? $section_rules[$section_id] : array('answer_rule' => 'all');
            $is_choice = in_array($rule['answer_rule'], array('answer_any', 'compulsory_plus_choice'), true)
                && !($rule['answer_rule'] === 'compulsory_plus_choice' && (int) $answer['is_compulsory'] === 1);
            if ($is_choice) {
                if ((int) $answer['is_answered'] === 1) {
                    $choice_rows[$section_id][] = $answer;
                }
            } else {
                $earned += (float) $answer['final_mark'];
            }
        }
        foreach ($choice_rows as $section_id => $answers) {
            usort($answers, function ($left, $right) {
                $left_time = strtotime($left['answer_created_at']);
                $right_time = strtotime($right['answer_created_at']);
                return $left_time === $right_time
                    ? ((int) $left['display_order'] <=> (int) $right['display_order'])
                    : ($left_time <=> $right_time);
            });
            $limit = isset($section_rules[$section_id]['answer_count']) ? (int) $section_rules[$section_id]['answer_count'] : count($answers);
            foreach (array_slice($answers, 0, max(0, $limit)) as $answer) {
                $earned += (float) $answer['final_mark'];
            }
        }

        $raw_max = (float) $paper->raw_max_score;
        if ($raw_max <= 0) {
            $max_row = $this->db
                ->select('COALESCE(SUM(marks), 0) AS total', false)
                ->from('onlineexam_question_snapshots')
                ->where('paper_id', (int) $paper_id)
                ->get()
                ->row();
            $raw_max = (float) $max_row->total;
        }
        $earned = max(0, min((float) $earned, $raw_max));
        $contribution_max = (float) $paper->contribution_score;
        $contribution = $raw_max > 0 ? round(($earned / $raw_max) * $contribution_max, 4) : 0;
        return array(
            'raw_score' => $earned,
            'raw_max_score' => $raw_max,
            'contribution_score' => $contribution,
            'contribution_max_score' => $contribution_max,
        );
    }

    protected function recalculateAttempt($attempt_id, $now, $timed_out = false)
    {
        $summary = $this->db
            ->select("COUNT(*) AS paper_count, SUM(CASE WHEN onlineexam_attempt_papers.status IN ('submitted','completed') THEN 1 ELSE 0 END) AS submitted_count, COALESCE(SUM(onlineexam_attempt_papers.raw_score), 0) AS raw_score, COALESCE(SUM(onlineexam_attempt_papers.raw_max_score), 0) AS raw_max_score, COALESCE(SUM(onlineexam_attempt_papers.contribution_score), 0) AS weighted_score, COALESCE(SUM(onlineexam_attempt_papers.contribution_max_score), 0) AS weighted_max_score", false)
            ->from('onlineexam_attempt_papers')
            ->where('attempt_id', (int) $attempt_id)
            ->get()
            ->row();

        if ($this->db->field_exists('completion_source', 'onlineexam_attempt_papers')) {
            $this->db->where("NOT EXISTS (SELECT 1 FROM onlineexam_question_snapshots review_q JOIN onlineexam_attempt_papers review_p ON review_p.paper_id = review_q.paper_id AND review_p.attempt_id = onlineexam_attempt_answers.attempt_id WHERE review_q.id = onlineexam_attempt_answers.question_snapshot_id AND review_p.completion_source = 'manual')", null, false);
        }
        $manual_pending = $this->db
            ->from('onlineexam_attempt_answers')
            ->where('attempt_id', (int) $attempt_id)
            ->where('is_answered', 1)
            ->where('marking_status', 'awaiting_manual')
            ->count_all_results();

        $attempt_context = $this->db->select('onlineexam_id, revision')
            ->where('id', (int) $attempt_id)
            ->get('onlineexam_candidate_attempts')
            ->row();
        $expected_papers = 0;
        if ($attempt_context) {
            foreach ($this->getFrozenPapers($attempt_context->onlineexam_id, $attempt_context->revision) as $paper) {
                if (!isset($paper['is_active']) || (int) $paper['is_active'] === 1) {
                    $expected_papers++;
                }
            }
        }

        $all_submitted = $expected_papers > 0 && (int) $summary->submitted_count >= $expected_papers;
        $status = $all_submitted
            ? ($timed_out ? self::STATUS_TIMED_OUT : self::STATUS_SUBMITTED)
            : self::STATUS_IN_PROGRESS;
        $marking_status = $manual_pending > 0 ? 'pending' : 'not_required';

        $attempt = $this->db
            ->select('onlineexam_candidate_attempts.*, onlineexam.target_max_score')
            ->from('onlineexam_candidate_attempts')
            ->join('onlineexam', 'onlineexam.id = onlineexam_candidate_attempts.onlineexam_id')
            ->where('onlineexam_candidate_attempts.id', (int) $attempt_id)
            ->limit(1)
            ->get()
            ->row();
        $target_max = $attempt && $attempt->target_max_score !== null
            ? (float) $attempt->target_max_score
            : (float) $summary->weighted_max_score;
        $final = (float) $summary->weighted_max_score > 0
            ? round(((float) $summary->weighted_score / (float) $summary->weighted_max_score) * $target_max, 2)
            : 0;

        $this->db->where('id', (int) $attempt_id)->update('onlineexam_candidate_attempts', array(
            'status' => $status,
            'submitted_at' => $all_submitted ? $now : null,
            'raw_score' => (float) $summary->raw_score,
            'raw_max_score' => (float) $summary->raw_max_score,
            'weighted_score' => (float) $summary->weighted_score,
            'weighted_max_score' => (float) $summary->weighted_max_score,
            'final_score' => null,
            'manual_marking_required' => $manual_pending > 0 ? 1 : 0,
            'marking_status' => $marking_status,
            'updated_at' => $now,
        ));

        if ($all_submitted) {
            $this->db
                ->where('id', (int) $attempt->onlineexam_student_id)
                ->update('onlineexam_students', array('is_attempted' => 1));
        }
        return $status;
    }

    protected function getRevisionConfiguration($onlineexam_id, $revision)
    {
        $snapshot = $this->db
            ->select('configuration_json')
            ->where('onlineexam_id', (int) $onlineexam_id)
            ->where('revision', (int) $revision)
            ->limit(1)
            ->get('onlineexam_revision_snapshots')
            ->row();
        if (!$snapshot || empty($snapshot->configuration_json)) {
            return array();
        }
        $configuration = json_decode($snapshot->configuration_json, true);
        return is_array($configuration) ? $configuration : array();
    }

    protected function getFrozenPapers($onlineexam_id, $revision)
    {
        $configuration = $this->getRevisionConfiguration($onlineexam_id, $revision);
        if (!empty($configuration['papers']) && is_array($configuration['papers'])) {
            return $configuration['papers'];
        }
        // Compatibility fallback for early workflow-v2 drafts created before
        // migration 128 gained full revision configuration snapshots.
        $papers = $this->db
            ->where('onlineexam_id', (int) $onlineexam_id)
            ->where('is_active', 1)
            ->order_by('display_order', 'ASC')
            ->get('onlineexam_papers')
            ->result_array();
        foreach ($papers as &$paper) {
            $paper['sections'] = $this->db
                ->where('paper_id', (int) $paper['id'])
                ->order_by('display_order', 'ASC')
                ->get('onlineexam_paper_sections')
                ->result_array();
        }
        unset($paper);
        return $papers;
    }

    protected function getFrozenPaper($onlineexam_id, $revision, $paper_id)
    {
        foreach ($this->getFrozenPapers($onlineexam_id, $revision) as $paper) {
            if ((int) $paper['id'] === (int) $paper_id && (!isset($paper['is_active']) || (int) $paper['is_active'] === 1)) {
                return (object) $paper;
            }
        }
        return null;
    }

    protected function compactPaperQuestionsSupported($onlineexam_id, $revision, $paper_id, $paper_type = null)
    {
        $allowed = array(
            'singlechoice', 'multichoice', 'true_false', 'short_answer',
            'numeric', 'matching', 'ordering', 'long_answer'
        );
        $total = $this->db->where('onlineexam_id', (int) $onlineexam_id)
            ->where('revision', (int) $revision)
            ->where('paper_id', (int) $paper_id)
            ->count_all_results('onlineexam_question_snapshots');
        if ($total < 1) {
            return false;
        }
        $unsupported = $this->db->where('onlineexam_id', (int) $onlineexam_id)
            ->where('revision', (int) $revision)
            ->where('paper_id', (int) $paper_id)
            ->where_not_in('question_type', $allowed)
            ->count_all_results('onlineexam_question_snapshots');
        if ($unsupported > 0) {
            return false;
        }
        if ($paper_type === 'objective') {
            return $this->db->where('onlineexam_id', (int) $onlineexam_id)
                ->where('revision', (int) $revision)
                ->where('paper_id', (int) $paper_id)
                ->where('question_type', 'long_answer')
                ->count_all_results('onlineexam_question_snapshots') === 0;
        }
        return true;
    }
}
