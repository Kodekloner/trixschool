<?php

defined('BASEPATH') OR exit('No direct script access allowed');

/**
 * Transactional, idempotent adapters from completed online attempts into the
 * existing academic result tables. Report-card publication remains separate.
 */
class Onlineexamresultsync_model extends CI_Model
{
    protected $standard_components = array('ca1', 'ca2', 'ca3', 'ca4', 'ca5', 'ca6', 'ca7', 'ca8', 'ca9', 'ca10', 'exam');

    public function __construct()
    {
        parent::__construct();
        $this->load->library('onlineexam_scoring');
    }

    public function syncCompletedAttempt($attempt_id, $actor_id = null)
    {
        $context = $this->getContext($attempt_id);
        if (empty($context)) {
            return array('success' => false, 'errors' => array('Attempt was not found.'));
        }
        if ((int) $context['workflow_version'] !== 2) {
            return array('success' => false, 'errors' => array('Legacy examination results are not synchronized by the v2 adapters.'));
        }
        if ($context['attempt_status'] !== 'completed' || $context['final_score'] === null) {
            return array('success' => false, 'errors' => array('Attempt must be completely marked and finalized before synchronization.'));
        }
        if ((int) $context['assignment_exam_id'] !== (int) $context['onlineexam_id']) {
            return array('success' => false, 'errors' => array('Candidate assignment does not belong to this assessment.'));
        }
        if ((int) $context['student_class_id'] !== (int) $context['exam_class_id'] || (int) $context['student_session_session_id'] !== (int) $context['exam_session_id']) {
            return array('success' => false, 'errors' => array('Candidate academic context does not match the assessment.'));
        }
        if (!$this->candidateSectionIsAllowed($context, $context['student_section_id'])) {
            return array('success' => false, 'errors' => array('Candidate section is not assigned to this assessment.'));
        }

        $this->db->trans_begin();
        // Lock the attempt so a mark correction and a duplicate submission
        // cannot synchronize concurrently.
        $locked = $this->db->query(
            'SELECT `id`, `status`, `final_score`, `outcome_value`, `updated_at` '
            . 'FROM `onlineexam_candidate_attempts` WHERE `id` = ' . $this->db->escape((int) $attempt_id) . ' FOR UPDATE'
        )->row_array();
        if (empty($locked) || $locked['status'] !== 'completed') {
            $this->db->trans_rollback();
            return array('success' => false, 'errors' => array('Attempt is no longer ready to synchronize.'));
        }
        $context['final_score'] = $locked['final_score'];
        $context['outcome_value'] = $locked['outcome_value'];
        $context['attempt_updated_at'] = $locked['updated_at'];

        try {
            switch ($context['result_adapter']) {
                case 'standard_component':
                    $result = $this->syncStandardComponent($context);
                    break;
                case 'british_outcome':
                    $result = $this->syncBritishOutcome($context);
                    break;
                case 'kindergarten_concept':
                    $result = $this->syncKindergartenConcepts($context);
                    break;
                case 'holiday_assessment':
                    $result = $this->syncHolidayAssessment($context);
                    break;
                default:
                    throw new InvalidArgumentException('Unknown result adapter.');
            }
        } catch (Exception $exception) {
            $this->db->trans_rollback();
            $this->recordSyncError($context, $exception->getMessage());
            return array('success' => false, 'errors' => array($exception->getMessage()));
        }

        if ($this->db->trans_status() === false) {
            $this->db->trans_rollback();
            $this->recordSyncError($context, 'Result synchronization failed and was rolled back.');
            return array('success' => false, 'errors' => array('Result synchronization failed and was rolled back.'));
        }

        if (!empty($result['success'])) {
            $this->db->where('attempt_id', (int) $context['attempt_id'])
                ->where('adapter', $context['result_adapter'])
                ->where('status', 'error')
                ->update('onlineexam_result_sync', array(
                    'status' => 'resolved',
                    'synced_at' => date('Y-m-d H:i:s'),
                    'updated_at' => date('Y-m-d H:i:s'),
                ));
        }
        $this->auditSync($context, $actor_id, $result);
        $this->db->trans_commit();
        return $result;
    }

    /**
     * Explicitly resolves the legacy zero-value ambiguity. A zero in `score`
     * may be a real manual mark or an unused default, so it is never silently
     * claimed. Authorized replacement is narrow, audited, and only valid while
     * the destination still equals the value observed at conflict time.
     */
    public function authorizeConflictReplacement($onlineexam_id, $ledger_id, $actor_id, $reason)
    {
        $reason = trim((string) $reason);
        if ($reason === '' || mb_strlen($reason) > 1000) {
            return array('success' => false, 'reason' => 'Give a conflict-resolution reason of no more than 1,000 characters.');
        }
        $this->db->trans_begin();
        $row = $this->db->query(
            'SELECT * FROM `onlineexam_result_sync` WHERE `id` = ' . $this->db->escape((int) $ledger_id)
            . ' AND `onlineexam_id` = ' . $this->db->escape((int) $onlineexam_id) . ' LIMIT 1 FOR UPDATE'
        )->row_array();
        if (empty($row) || $row['status'] !== 'conflict'
            || !in_array($row['adapter'], array('standard_component', 'holiday_assessment'), true)) {
            $this->db->trans_rollback();
            return array('success' => false, 'reason' => 'Only a held numeric-result conflict can be explicitly replaced.');
        }
        $now = date('Y-m-d H:i:s');
        $this->db->where('id', (int) $ledger_id)->update('onlineexam_result_sync', array(
            'override_authorized_at' => $now,
            'override_authorized_by' => (int) $actor_id,
            'override_reason' => $reason,
            'updated_at' => $now,
        ));
        $this->db->insert('onlineexam_audit_log', array(
            'onlineexam_id' => (int) $onlineexam_id,
            'attempt_id' => (int) $row['attempt_id'],
            'actor_id' => (int) $actor_id,
            'actor_type' => 'staff',
            'action' => 'authorize_result_conflict_replacement',
            'entity_type' => 'onlineexam_result_sync',
            'entity_id' => (string) (int) $ledger_id,
            'before_json' => json_encode($row),
            'after_json' => json_encode(array('override_reason' => $reason, 'expected_value' => $row['previous_value'])),
            'created_at' => $now,
        ));
        if ($this->db->trans_status() === false) {
            $this->db->trans_rollback();
            return array('success' => false, 'reason' => 'The conflict authorization could not be saved.');
        }
        $this->db->trans_commit();
        return array('success' => true, 'attempt_id' => (int) $row['attempt_id']);
    }

    /** Backwards-compatible internal name used by deployments before v135. */
    public function authorizeStandardConflictReplacement($onlineexam_id, $ledger_id, $actor_id, $reason)
    {
        return $this->authorizeConflictReplacement($onlineexam_id, $ledger_id, $actor_id, $reason);
    }

    protected function syncStandardComponent(array $context)
    {
        $component = strtolower(trim($context['target_component']));
        if (!in_array($component, $this->standard_components, true)) {
            throw new InvalidArgumentException('The standard result component must be ca1 through ca10 or exam.');
        }
        $maximum = (float) $context['target_max_score'];
        $score = round((float) $context['final_score'], 2);
        if ($maximum <= 0 || $score < 0 || $score > $maximum) {
            throw new InvalidArgumentException('Final score is outside the configured result component maximum.');
        }

        $descriptor = 'score:' . $component;
        // Every component lives on the same legacy `score` row.  Serialize
        // creation of that base academic tuple independently of CA/exam so
        // concurrent CA1 and CA2 posts cannot both create a row.
        $this->lockResultTarget($context, 'score:row');
        $ledger = $this->startLedger($context, $descriptor, $score, $score);
        if ($ledger['idempotent']) {
            $posted = $ledger['row'];
            $row = empty($posted['target_record_id']) ? array() : $this->db->query(
                'SELECT * FROM `score` WHERE `ID` = '
                . $this->db->escape((int) $posted['target_record_id']) . ' FOR UPDATE'
            )->row_array();
            $matches_context = !empty($row)
                && (int) $row['StudentID'] === (int) $context['student_id']
                && (int) $row['ClassID'] === (int) $context['exam_class_id']
                && (int) $row['SectionID'] === (int) $context['student_section_id']
                && (int) $row['SubjectID'] === (int) $context['subject_id']
                && (string) $row['Session'] === (string) $context['exam_session_id']
                && (string) $row['Term'] === (string) $context['term'];
            if (!$matches_context
                || !array_key_exists($component, $row)
                || !$this->sameValue($row[$component], $posted['applied_value'])) {
                return $this->conflict(
                    (int) $posted['id'],
                    'score',
                    empty($row) ? null : (int) $row['ID'],
                    $component,
                    'The previously synchronized score was changed or moved outside this assessment.',
                    empty($row) || !array_key_exists($component, $row) ? null : $row[$component]
                );
            }
            return $this->ledgerResult($posted);
        }

        $rows = $this->lockStandardRows($context);
        if (count($rows) > 1) {
            return $this->conflict($ledger['id'], 'score', null, $component, 'Multiple score rows exist for the same student, subject, session and term.');
        }

        if (empty($rows)) {
            $insert = array(
                'StudentID' => (int) $context['student_id'],
                'ClassID' => (int) $context['exam_class_id'],
                'SectionID' => (int) $context['student_section_id'],
                'SubjectID' => (int) $context['subject_id'],
                'Session' => (string) $context['exam_session_id'],
                'Term' => $context['term'],
                $component => $score,
            );
            $this->db->insert('score', $insert);
            $record_id = (int) $this->db->insert_id();
            $this->finishLedger($ledger['id'], 'score', $record_id, $component, '0', (string) $score);
            return array('success' => true, 'status' => 'posted', 'record_id' => $record_id, 'value' => $score, 'idempotent' => false);
        }

        $row = $rows[0];
        $current = (float) $row[$component];
        $ownership = $this->latestOwnedSync($context, 'score', $row['ID'], $component);
        if (empty($ownership) && !$this->authorizedOverrideMatches($ledger, $current)) {
            return $this->conflict($ledger['id'], 'score', $row['ID'], $component, 'The destination already contains a manually-entered or unrelated score.', $current);
        }
        if (!empty($ownership) && !$this->sameValue($current, $ownership['applied_value'])) {
            return $this->conflict($ledger['id'], 'score', $row['ID'], $component, 'The previously synchronized score was changed outside this assessment.', $current);
        }

        $this->db->where('ID', (int) $row['ID'])->update('score', array($component => $score));
        $this->finishLedger($ledger['id'], 'score', $row['ID'], $component, (string) $current, (string) $score);
        return array('success' => true, 'status' => 'posted', 'record_id' => (int) $row['ID'], 'value' => $score, 'idempotent' => false);
    }

    /**
     * Post one finalized score into the existing Holiday Assessment row.
     * Explicit origin/source columns distinguish pre-created zero placeholders
     * from real manual zeroes, so no ambiguous legacy value is overwritten.
     */
    protected function syncHolidayAssessment(array $context)
    {
        foreach (array('holiday_assessment_scores', 'holiday_assessment_settings', 'holiday_assessment_subjects') as $table) {
            if (!$this->db->table_exists($table)) {
                throw new RuntimeException('Holiday Assessment tables are not installed.');
            }
        }
        foreach (array('score_origin', 'source_onlineexam_id', 'source_attempt_id', 'source_sync_id') as $field) {
            if (!$this->db->field_exists($field, 'holiday_assessment_scores')) {
                throw new RuntimeException('Holiday Assessment provenance migration 135 has not been applied.');
            }
        }

        $mappings = isset($context['_frozen_result']['holiday_mappings'])
            && is_array($context['_frozen_result']['holiday_mappings'])
            ? $context['_frozen_result']['holiday_mappings']
            : $this->db->where('onlineexam_id', (int) $context['onlineexam_id'])
                ->get('onlineexam_holiday_mappings')->result_array();
        $mapping = null;
        foreach ($mappings as $candidate) {
            if ((int) $candidate['section_id'] !== (int) $context['student_section_id']) {
                continue;
            }
            if ($mapping !== null) {
                throw new RuntimeException('More than one Holiday Assessment destination is mapped to this class arm.');
            }
            $mapping = $candidate;
        }
        if ($mapping === null) {
            throw new RuntimeException('No Holiday Assessment destination is mapped to this candidate class arm.');
        }

        $live = $this->db->select('hs.id AS setting_id, hsub.id AS setting_subject_id, hsub.max_score')
            ->from('holiday_assessment_settings hs')
            ->join('holiday_assessment_subjects hsub', 'hsub.setting_id = hs.id')
            ->where('hs.id', (int) $mapping['setting_id'])
            ->where('hsub.id', (int) $mapping['setting_subject_id'])
            ->where('hs.class_id', (int) $context['exam_class_id'])
            ->where('hs.section_id', (int) $context['student_section_id'])
            ->where('hs.session_id', (int) $context['exam_session_id'])
            ->where('hs.term', $context['term'])
            ->where('hs.enabled', 1)
            ->where('hsub.subject_id', (int) $context['subject_id'])
            ->limit(2)
            ->get()->result_array();
        if (count($live) !== 1
            || (float) $live[0]['max_score'] <= 0
            || abs((float) $live[0]['max_score'] - (float) $mapping['max_score']) > 0.001) {
            throw new RuntimeException('The configured Holiday Assessment destination has changed since publication.');
        }

        $maximum = round((float) $mapping['max_score'], 2);
        $score = round((float) $context['final_score'], 2);
        if ($score < 0 || $score > $maximum
            || ((float) $context['target_max_score'] > 0
                && abs((float) $context['target_max_score'] - $maximum) > 0.001)) {
            throw new InvalidArgumentException('Final score is outside the configured Holiday Assessment maximum.');
        }

        $descriptor = 'holiday_assessment_scores:score';
        $this->lockResultTarget($context, $descriptor);
        $ledger = $this->startLedger($context, $descriptor, $score, $score);
        if ($ledger['idempotent']) {
            $posted = $ledger['row'];
            $row = empty($posted['target_record_id']) ? array() : $this->db->query(
                'SELECT * FROM `holiday_assessment_scores` WHERE `id` = '
                . $this->db->escape((int) $posted['target_record_id']) . ' FOR UPDATE'
            )->row_array();
            if (empty($row)
                || !$this->sameValue($row['score'], $posted['applied_value'])
                || $row['score_origin'] !== 'onlineexam'
                || (int) $row['source_onlineexam_id'] !== (int) $context['onlineexam_id']
                || (int) $row['source_attempt_id'] !== (int) $context['attempt_id']
                || (int) $row['source_sync_id'] !== (int) $posted['id']) {
                return $this->conflict(
                    (int) $posted['id'],
                    'holiday_assessment_scores',
                    empty($row) ? null : (int) $row['id'],
                    'score',
                    'The previously synchronized Holiday score or its provenance was changed outside this assessment.',
                    empty($row) ? null : $row['score']
                );
            }
            return $this->ledgerResult($posted);
        }

        $rows = $this->lockHolidayRows($context);
        if (count($rows) > 1) {
            return $this->conflict($ledger['id'], 'holiday_assessment_scores', null, 'score', 'Multiple Holiday score rows exist for the same academic context.');
        }

        $now = date('Y-m-d H:i:s');
        $applied_metadata = array(
            'record_existed' => true,
            'max_score' => $maximum,
            'score_origin' => 'onlineexam',
            'source_onlineexam_id' => (int) $context['onlineexam_id'],
            'source_attempt_id' => (int) $context['attempt_id'],
            'source_sync_id' => (int) $ledger['id'],
            'updated_at' => $now,
        );
        if (empty($rows)) {
            $insert = array(
                'student_id' => (int) $context['student_id'],
                'class_id' => (int) $context['exam_class_id'],
                'section_id' => (int) $context['student_section_id'],
                'subject_id' => (int) $context['subject_id'],
                'session_id' => (int) $context['exam_session_id'],
                'term' => $context['term'],
                'score' => $score,
                'max_score' => $maximum,
                'score_origin' => 'onlineexam',
                'source_onlineexam_id' => (int) $context['onlineexam_id'],
                'source_attempt_id' => (int) $context['attempt_id'],
                'source_sync_id' => (int) $ledger['id'],
                'updated_at' => $now,
            );
            $this->db->insert('holiday_assessment_scores', $insert);
            $record_id = (int) $this->db->insert_id();
            $this->finishLedger(
                $ledger['id'], 'holiday_assessment_scores', $record_id, 'score', null, (string) $score,
                array('record_existed' => false), $applied_metadata
            );
            return array('success' => true, 'status' => 'posted', 'record_id' => $record_id, 'value' => $score, 'idempotent' => false);
        }

        $row = $rows[0];
        $current = (float) $row['score'];
        if (abs((float) $row['max_score'] - $maximum) > 0.001) {
            return $this->conflict($ledger['id'], 'holiday_assessment_scores', $row['id'], 'score', 'The Holiday score row uses a different maximum.', $current);
        }

        $ownership = $this->latestOwnedSync($context, 'holiday_assessment_scores', $row['id'], 'score');
        $is_placeholder = $row['score_origin'] === 'placeholder'
            && $this->sameValue($current, 0)
            && empty($row['source_onlineexam_id'])
            && empty($row['source_attempt_id'])
            && empty($row['source_sync_id']);
        if (empty($ownership) && !$is_placeholder && !$this->authorizedOverrideMatches($ledger, $current)) {
            return $this->conflict($ledger['id'], 'holiday_assessment_scores', $row['id'], 'score', 'The Holiday destination contains a manual or unrelated score.', $current);
        }
        if (!empty($ownership)
            && (!$this->sameValue($current, $ownership['applied_value'])
                || $row['score_origin'] !== 'onlineexam'
                || (int) $row['source_onlineexam_id'] !== (int) $context['onlineexam_id']
                || (int) $row['source_attempt_id'] !== (int) $context['attempt_id']
                || (int) $row['source_sync_id'] !== (int) $ownership['id'])) {
            return $this->conflict($ledger['id'], 'holiday_assessment_scores', $row['id'], 'score', 'The previously synchronized Holiday score or its provenance was changed outside this assessment.', $current);
        }

        $previous_metadata = array(
            'record_existed' => true,
            'max_score' => (float) $row['max_score'],
            'score_origin' => $row['score_origin'],
            'source_onlineexam_id' => $row['source_onlineexam_id'],
            'source_attempt_id' => $row['source_attempt_id'],
            'source_sync_id' => $row['source_sync_id'],
            'updated_at' => isset($row['updated_at']) ? $row['updated_at'] : null,
        );
        $this->db->where('id', (int) $row['id'])->update('holiday_assessment_scores', array(
            'score' => $score,
            'score_origin' => 'onlineexam',
            'source_onlineexam_id' => (int) $context['onlineexam_id'],
            'source_attempt_id' => (int) $context['attempt_id'],
            'source_sync_id' => (int) $ledger['id'],
            'updated_at' => $now,
        ));
        $this->finishLedger(
            $ledger['id'], 'holiday_assessment_scores', $row['id'], 'score', (string) $current, (string) $score,
            $previous_metadata, $applied_metadata
        );
        return array('success' => true, 'status' => 'posted', 'record_id' => (int) $row['id'], 'value' => $score, 'idempotent' => false);
    }

    protected function syncBritishOutcome(array $context)
    {
        $profile = isset($context['_frozen_result']['profile']) ? $context['_frozen_result']['profile'] : null;
        if (!is_array($profile)) {
            $profile_row = $this->activeProfile($context['onlineexam_id'], 'british_outcome');
            $profile = empty($profile_row) ? null : json_decode($profile_row['configuration_json'], true);
        }
        if (!is_array($profile)) {
            throw new InvalidArgumentException('British outcome profile is missing or invalid.');
        }
        $percentage = $this->percentage($context);
        $outcome = $this->onlineexam_scoring->resolveBritishOutcome($percentage, $profile, $context['outcome_value']);
        // Store the resolved qualitative value on the attempt as well as in
        // britishresult. This lets Review and released online feedback display
        // the same frozen outcome, including when threshold conversion is used.
        if ((string) $context['outcome_value'] !== (string) $outcome) {
            $this->db->where('id', (int) $context['attempt_id'])->update('onlineexam_candidate_attempts', array(
                'outcome_value' => $outcome,
            ));
            $context['outcome_value'] = $outcome;
        }
        $descriptor = 'britishresult:Remark';
        $this->lockResultTarget($context, $descriptor);
        $ledger = $this->startLedger($context, $descriptor, $context['final_score'], $percentage . ':' . $outcome);
        if ($ledger['idempotent']) {
            $posted = $ledger['row'];
            $row = empty($posted['target_record_id']) ? array() : $this->db->query(
                'SELECT * FROM `britishresult` WHERE `ID` = '
                . $this->db->escape((int) $posted['target_record_id']) . ' FOR UPDATE'
            )->row_array();
            $matches_context = !empty($row)
                && (int) $row['StudentID'] === (int) $context['student_id']
                && (int) $row['ClassID'] === (int) $context['exam_class_id']
                && (int) $row['SectionID'] === (int) $context['student_section_id']
                && (int) $row['SubjectID'] === (int) $context['subject_id']
                && (string) $row['Session'] === (string) $context['exam_session_id']
                && (string) $row['Term'] === (string) $context['term'];
            if (!$matches_context || (string) $row['Remark'] !== (string) $posted['applied_value']) {
                return $this->conflict(
                    (int) $posted['id'],
                    'britishresult',
                    empty($row) ? null : (int) $row['ID'],
                    'Remark',
                    'The previously synchronized British outcome was changed or moved outside this assessment.',
                    empty($row) ? null : $row['Remark']
                );
            }
            return $this->ledgerResult($posted);
        }

        $rows = $this->lockBritishRows($context);
        if (count($rows) > 1) {
            return $this->conflict($ledger['id'], 'britishresult', null, 'Remark', 'Multiple British result rows exist for the same academic context.');
        }
        if (empty($rows)) {
            $this->db->insert('britishresult', array(
                'StudentID' => (int) $context['student_id'],
                'ClassID' => (int) $context['exam_class_id'],
                'SectionID' => (int) $context['student_section_id'],
                'SubjectID' => (int) $context['subject_id'],
                'Session' => (string) $context['exam_session_id'],
                'Term' => $context['term'],
                'Remark' => $outcome,
                'AdditionalComments' => null,
            ));
            $record_id = (int) $this->db->insert_id();
            $this->finishLedger($ledger['id'], 'britishresult', $record_id, 'Remark', null, $outcome);
            return array('success' => true, 'status' => 'posted', 'record_id' => $record_id, 'value' => $outcome, 'idempotent' => false);
        }

        $row = $rows[0];
        $current = trim((string) $row['Remark']);
        $ownership = $this->latestOwnedSync($context, 'britishresult', $row['ID'], 'Remark');
        if (empty($ownership) && $current !== '') {
            return $this->conflict($ledger['id'], 'britishresult', $row['ID'], 'Remark', 'The British outcome was entered manually or by another assessment.', $current);
        }
        if (!empty($ownership) && (string) $ownership['applied_value'] !== $current) {
            return $this->conflict($ledger['id'], 'britishresult', $row['ID'], 'Remark', 'The previously synchronized British outcome was changed outside this assessment.', $current);
        }

        // AdditionalComments is deliberately not part of this update.
        $this->db->where('ID', (int) $row['ID'])->update('britishresult', array('Remark' => $outcome));
        $this->finishLedger($ledger['id'], 'britishresult', $row['ID'], 'Remark', $current, $outcome);
        return array('success' => true, 'status' => 'posted', 'record_id' => (int) $row['ID'], 'value' => $outcome, 'idempotent' => false);
    }

    protected function syncKindergartenConcepts(array $context)
    {
        $mappings = isset($context['_frozen_result']['mappings']) && is_array($context['_frozen_result']['mappings'])
            ? $context['_frozen_result']['mappings']
            : $this->db->where('onlineexam_id', (int) $context['onlineexam_id'])
                ->order_by('id', 'ASC')
                ->get('onlineexam_kindergarten_mappings')
                ->result_array();
        if (empty($mappings)) {
            throw new InvalidArgumentException('Kindergarten concept mapping is missing.');
        }
        $this->lockResultTarget($context, 'kindergarten_result:all');

        $prepared = array();
        $conflicts = array();
        foreach ($mappings as $mapping) {
            $concept = $this->db->select('kac.*, kas.assessment_id, kas.subject_id')
                ->from('kindergarten_assessment_concepts kac')
                ->join('kindergarten_assessment_subjects kas', 'kas.id = kac.assessment_subject_id')
                ->where('kac.id', (int) $mapping['concept_id'])
                ->where('kac.stable_key', $mapping['concept_stable_key'])
                ->limit(1)
                ->get()
                ->row_array();
            if (empty($concept) || (int) $concept['assessment_id'] !== (int) $mapping['assessment_id'] || (int) $concept['subject_id'] !== (int) $mapping['subject_id']) {
                throw new InvalidArgumentException('A mapped Kindergarten concept was replaced or moved.');
            }

            $header = $this->db->where('id', (int) $mapping['assessment_id'])
                ->limit(1)
                ->get('kindergarten_assessment_header')
                ->row_array();
            if (empty($header)) {
                throw new InvalidArgumentException('Mapped Kindergarten assessment no longer exists.');
            }
            $profile = json_decode($mapping['outcome_profile_json'], true);
            $profile = is_array($profile) ? $profile : array();
            $mapping_percentage = $this->kindergartenMappingPercentage($context, $mapping);
            $label = $this->onlineexam_scoring->resolveKindergartenLabel(
                $mapping_percentage,
                $profile,
                (int) $header['num_result_labels'],
                null
            );
            $descriptor = 'kindergarten_result:concept:' . (int) $mapping['concept_id'];
            $ledger = $this->startLedger($context, $descriptor, $mapping_percentage, $label);
            if ($ledger['idempotent']) {
                $posted = $ledger['row'];
                $record = empty($posted['target_record_id']) ? array() : $this->db->query(
                    'SELECT * FROM `kindergarten_result` WHERE `id` = '
                    . $this->db->escape((int) $posted['target_record_id']) . ' FOR UPDATE'
                )->row_array();
                $matches_context = !empty($record)
                    && (int) $record['student_id'] === (int) $context['student_id']
                    && (int) $record['session_id'] === (int) $context['exam_session_id']
                    && (string) $record['term'] === (string) $context['term']
                    && (int) $record['assessment_id'] === (int) $mapping['assessment_id']
                    && (int) $record['subject_id'] === (int) $mapping['subject_id']
                    && (int) $record['concept_id'] === (int) $mapping['concept_id'];
                if (!$matches_context
                    || !$this->sameValue($record['result_label_index'], $posted['applied_value'])) {
                    $conflicts[] = array(
                        'ledger_id' => (int) $posted['id'],
                        'mapping' => $mapping,
                        'record' => empty($record) ? null : $record,
                        'current' => empty($record) ? null : $record['result_label_index'],
                        'reason' => 'The previously synchronized Kindergarten outcome was changed or moved outside this assessment.',
                    );
                    continue;
                }
                $prepared[] = array('idempotent' => true, 'ledger' => $posted);
                continue;
            }

            $rows = $this->lockKindergartenRows($context, $mapping);
            if (count($rows) > 1) {
                $conflicts[] = array('ledger_id' => $ledger['id'], 'mapping' => $mapping, 'record' => null, 'current' => null, 'reason' => 'Multiple Kindergarten result rows exist for this concept.');
                continue;
            }
            $record = empty($rows) ? null : $rows[0];
            if ($record !== null) {
                $ownership = $this->latestOwnedSync($context, 'kindergarten_result', $record['id'], 'concept:' . (int) $mapping['concept_id']);
                if (empty($ownership)) {
                    $conflicts[] = array('ledger_id' => $ledger['id'], 'mapping' => $mapping, 'record' => $record, 'current' => $record['result_label_index'], 'reason' => 'The Kindergarten outcome was entered manually or by another assessment.');
                    continue;
                }
                if (!$this->sameValue($record['result_label_index'], $ownership['applied_value'])) {
                    $conflicts[] = array('ledger_id' => $ledger['id'], 'mapping' => $mapping, 'record' => $record, 'current' => $record['result_label_index'], 'reason' => 'The previously synchronized Kindergarten outcome was changed outside this assessment.');
                    continue;
                }
            }
            $prepared[] = array('idempotent' => false, 'ledger_id' => $ledger['id'], 'mapping' => $mapping, 'record' => $record, 'label' => $label);
        }

        // Concept results are one logical adapter operation: if one target is a
        // conflict, post none of them. Persist every conflict for resolution.
        if (!empty($conflicts)) {
            foreach ($conflicts as $conflict) {
                $record_id = empty($conflict['record']) ? null : $conflict['record']['id'];
                $this->conflict(
                    $conflict['ledger_id'],
                    'kindergarten_result',
                    $record_id,
                    'concept:' . (int) $conflict['mapping']['concept_id'],
                    $conflict['reason'],
                    $conflict['current']
                );
            }
            // Other new pending ledgers must also be held so a retry cannot
            // mistake them for a successful post.
            foreach ($prepared as $item) {
                if (!$item['idempotent']) {
                    $this->conflict(
                        $item['ledger_id'],
                        'kindergarten_result',
                        empty($item['record']) ? null : $item['record']['id'],
                        'concept:' . (int) $item['mapping']['concept_id'],
                        'Another concept mapping in this assessment has a synchronization conflict.'
                    );
                }
            }
            return array('success' => false, 'status' => 'conflict', 'conflicts' => count($conflicts));
        }

        $results = array();
        foreach ($prepared as $item) {
            if ($item['idempotent']) {
                $results[] = $this->ledgerResult($item['ledger']);
                continue;
            }
            $mapping = $item['mapping'];
            if (empty($item['record'])) {
                $this->db->insert('kindergarten_result', array(
                    'student_id' => (int) $context['student_id'],
                    'session_id' => (int) $context['exam_session_id'],
                    'term' => $context['term'],
                    'assessment_id' => (int) $mapping['assessment_id'],
                    'subject_id' => (int) $mapping['subject_id'],
                    'concept_id' => (int) $mapping['concept_id'],
                    'result_label_index' => (int) $item['label'],
                    'created_at' => date('Y-m-d H:i:s'),
                    'updated_at' => date('Y-m-d H:i:s'),
                ));
                $record_id = (int) $this->db->insert_id();
                $previous = null;
            } else {
                $record_id = (int) $item['record']['id'];
                $previous = $item['record']['result_label_index'];
                $this->db->where('id', $record_id)->update('kindergarten_result', array('result_label_index' => (int) $item['label']));
            }
            $field = 'concept:' . (int) $mapping['concept_id'];
            $this->finishLedger($item['ledger_id'], 'kindergarten_result', $record_id, $field, $previous, (string) $item['label']);
            $results[] = array('success' => true, 'status' => 'posted', 'record_id' => $record_id, 'value' => (int) $item['label'], 'idempotent' => false);
        }

        return array('success' => true, 'status' => 'posted', 'results' => $results);
    }

    /**
     * Reverses values still owned by one attempt and voids it atomically.
     * Any externally changed destination becomes a conflict; it is never
     * overwritten during reversal.
     */
    public function reverseAndVoidAttempt($onlineexam_id, $attempt_id, $reason, $actor_id = null)
    {
        $reason = trim((string) $reason);
        if ($reason === '' || mb_strlen($reason) > 5000) {
            return array('success' => false, 'errors' => array('A void/reversal reason of no more than 5,000 characters is required.'));
        }
        $this->db->trans_begin();
        $attempt = $this->db->query(
            'SELECT * FROM `onlineexam_candidate_attempts` WHERE `id` = ' . $this->db->escape((int) $attempt_id)
            . ' AND `onlineexam_id` = ' . $this->db->escape((int) $onlineexam_id) . ' FOR UPDATE'
        )->row_array();
        if (empty($attempt)) {
            $this->db->trans_rollback();
            return array('success' => false, 'errors' => array('Attempt was not found.'));
        }
        if ($attempt['status'] === 'voided') {
            $this->db->trans_commit();
            return array('success' => true, 'attempt_id' => (int) $attempt_id, 'idempotent' => true);
        }

        $ledgers = $this->db->where('attempt_id', (int) $attempt_id)
            ->where('status', 'posted')
            ->order_by('id', 'ASC')
            ->get('onlineexam_result_sync')
            ->result_array();
        $groups = array();
        foreach ($ledgers as $ledger) {
            if (empty($ledger['target_table']) || empty($ledger['target_record_id'])) {
                continue;
            }
            $key = $ledger['target_table'] . '|' . (int) $ledger['target_record_id'] . '|' . $ledger['target_field'];
            if (!isset($groups[$key])) {
                $groups[$key] = array();
            }
            $groups[$key][] = $ledger;
        }

        foreach ($groups as $target_ledgers) {
            $first = $target_ledgers[0];
            $last = $target_ledgers[count($target_ledgers) - 1];
            $table = $last['target_table'];
            $record_id = (int) $last['target_record_id'];
            $field = $last['target_field'];
            $current = null;
            $row = array();
            $provenance_matches = true;
            if ($table === 'score' && in_array($field, $this->standard_components, true)) {
                $row = $this->db->query('SELECT * FROM `score` WHERE `ID` = ' . $this->db->escape($record_id) . ' FOR UPDATE')->row_array();
                $current = isset($row[$field]) ? $row[$field] : null;
            } elseif ($table === 'britishresult' && $field === 'Remark') {
                $row = $this->db->query('SELECT * FROM `britishresult` WHERE `ID` = ' . $this->db->escape($record_id) . ' FOR UPDATE')->row_array();
                $current = isset($row['Remark']) ? $row['Remark'] : null;
            } elseif ($table === 'kindergarten_result' && strpos((string) $field, 'concept:') === 0) {
                $row = $this->db->query('SELECT * FROM `kindergarten_result` WHERE `id` = ' . $this->db->escape($record_id) . ' FOR UPDATE')->row_array();
                $current = isset($row['result_label_index']) ? $row['result_label_index'] : null;
            } elseif ($table === 'holiday_assessment_scores' && $field === 'score') {
                $row = $this->db->query('SELECT * FROM `holiday_assessment_scores` WHERE `id` = ' . $this->db->escape($record_id) . ' FOR UPDATE')->row_array();
                $current = isset($row['score']) ? $row['score'] : null;
                $provenance_matches = !empty($row)
                    && isset($row['score_origin']) && $row['score_origin'] === 'onlineexam'
                    && (int) $row['source_onlineexam_id'] === (int) $onlineexam_id
                    && (int) $row['source_attempt_id'] === (int) $attempt_id
                    && (int) $row['source_sync_id'] === (int) $last['id'];
            } else {
                $this->db->trans_rollback();
                return array('success' => false, 'errors' => array('A synchronized destination is not eligible for automatic reversal.'));
            }
            if (empty($row) || !$this->sameValue($current, $last['applied_value']) || !$provenance_matches) {
                $this->db->trans_rollback();
                return array(
                    'success' => false,
                    'status' => 'conflict',
                    'errors' => array('The posted result was changed outside this assessment. Resolve that conflict before voiding the attempt.'),
                );
            }

            $original = $first['previous_value'];
            if ($table === 'score') {
                $this->db->where('ID', $record_id)->update('score', array($field => $original === null ? 0 : (float) $original));
            } elseif ($table === 'britishresult') {
                $this->db->where('ID', $record_id)->update('britishresult', array('Remark' => $original));
            } elseif ($table === 'kindergarten_result' && $original === null) {
                $this->db->where('id', $record_id)->delete('kindergarten_result');
            } elseif ($table === 'kindergarten_result') {
                $this->db->where('id', $record_id)->update('kindergarten_result', array(
                    'result_label_index' => (int) $original,
                    'updated_at' => date('Y-m-d H:i:s'),
                ));
            } elseif ($table === 'holiday_assessment_scores') {
                $metadata = !empty($first['previous_metadata_json'])
                    ? json_decode($first['previous_metadata_json'], true) : null;
                if (!is_array($metadata) || !array_key_exists('record_existed', $metadata)) {
                    $this->db->trans_rollback();
                    return array('success' => false, 'errors' => array('Holiday result provenance is incomplete; automatic reversal was stopped safely.'));
                }
                if (!$metadata['record_existed']) {
                    $this->db->where('id', $record_id)->delete('holiday_assessment_scores');
                } else {
                    $this->db->where('id', $record_id)->update('holiday_assessment_scores', array(
                        'score' => $original,
                        'max_score' => $metadata['max_score'],
                        'score_origin' => $metadata['score_origin'],
                        'source_onlineexam_id' => $metadata['source_onlineexam_id'],
                        'source_attempt_id' => $metadata['source_attempt_id'],
                        'source_sync_id' => $metadata['source_sync_id'],
                        'updated_at' => $metadata['updated_at'],
                    ));
                }
            }
        }

        $now = date('Y-m-d H:i:s');
        if (!empty($ledgers)) {
            $this->db->where_in('id', array_map('intval', array_column($ledgers, 'id')))->update('onlineexam_result_sync', array(
                'status' => 'reversed',
                'reversed_at' => $now,
                'reversed_by' => $actor_id === null ? null : (int) $actor_id,
                'reversal_reason' => $reason,
                'updated_at' => $now,
            ));
        }
        $void_updates = array(
            'status' => 'voided',
            'voided_at' => $now,
            'voided_by' => $actor_id === null ? null : (int) $actor_id,
            'void_reason' => $reason,
            'updated_at' => $now,
        );
        $this->db->where('id', (int) $attempt_id)->update('onlineexam_candidate_attempts', $void_updates);
        $this->db->where('attempt_id', (int) $attempt_id)
            ->where_in('status', array('pending', 'in_progress', 'submitted', 'completed'))
            ->update('onlineexam_attempt_papers', array('status' => 'voided', 'updated_at' => $now));
        $remaining = $this->db->where('onlineexam_student_id', (int) $attempt['onlineexam_student_id'])
            ->where('status !=', 'voided')
            ->count_all_results('onlineexam_candidate_attempts');
        if ($remaining === 0) {
            $this->db->where('id', (int) $attempt['onlineexam_student_id'])->update('onlineexam_students', array('is_attempted' => 0));
        }
        $this->db->insert('onlineexam_audit_log', array(
            'onlineexam_id' => (int) $onlineexam_id,
            'attempt_id' => (int) $attempt_id,
            'actor_id' => $actor_id === null ? null : (int) $actor_id,
            'actor_type' => $actor_id === null ? 'system' : 'staff',
            'action' => 'reverse_result_and_void_attempt',
            'entity_type' => 'onlineexam_candidate_attempts',
            'entity_id' => (string) (int) $attempt_id,
            'before_json' => json_encode($attempt),
            'after_json' => json_encode(array_merge($attempt, $void_updates, array('reversed_ledger_ids' => array_column($ledgers, 'id')))),
            'ip_address' => isset($this->input) ? $this->input->ip_address() : null,
            'created_at' => $now,
        ));
        if ($this->db->trans_status() === false) {
            $this->db->trans_rollback();
            return array('success' => false, 'errors' => array('The posted result could not be reversed safely.'));
        }
        $this->db->trans_commit();
        return array('success' => true, 'attempt_id' => (int) $attempt_id, 'reversed_ledgers' => count($ledgers), 'idempotent' => false);
    }

    protected function getContext($attempt_id)
    {
        $context = $this->db->select(
                'a.id AS attempt_id, a.onlineexam_id, a.status AS attempt_status, a.final_score, a.outcome_value, '
                . 'a.revision AS attempt_revision, a.updated_at AS attempt_updated_at, e.workflow_version, e.session_id AS exam_session_id, e.term, '
                . 'e.class_id AS exam_class_id, e.subject_id, e.result_adapter, e.target_component, e.target_max_score, '
                . 'e.revision AS current_exam_revision, os.onlineexam_id AS assignment_exam_id, os.student_session_id, '
                . 'ss.student_id, ss.class_id AS student_class_id, ss.section_id AS student_section_id, '
                . 'ss.session_id AS student_session_session_id'
            )
            ->from('onlineexam_candidate_attempts a')
            ->join('onlineexam e', 'e.id = a.onlineexam_id')
            ->join('onlineexam_students os', 'os.id = a.onlineexam_student_id')
            ->join('student_session ss', 'ss.id = os.student_session_id')
            ->where('a.id', (int) $attempt_id)
            ->limit(1)
            ->get()
            ->row_array();

        if (empty($context)) {
            return $context;
        }
        $context['exam_revision'] = (int) $context['attempt_revision'];
        $snapshot = $this->db->where('onlineexam_id', (int) $context['onlineexam_id'])
            ->where('revision', (int) $context['attempt_revision'])
            ->limit(1)
            ->get('onlineexam_revision_snapshots')
            ->row_array();
        if (!empty($snapshot['configuration_json'])) {
            $configuration = json_decode($snapshot['configuration_json'], true);
            if (is_array($configuration)) {
                $academic = isset($configuration['academic_context']) ? $configuration['academic_context'] : array();
                $result = isset($configuration['result']) ? $configuration['result'] : array();
                $context['exam_session_id'] = isset($academic['session_id']) ? $academic['session_id'] : $context['exam_session_id'];
                $context['term'] = isset($academic['term']) ? $academic['term'] : $context['term'];
                $context['exam_class_id'] = isset($academic['class_id']) ? $academic['class_id'] : $context['exam_class_id'];
                $context['subject_id'] = isset($academic['subject_id']) ? $academic['subject_id'] : $context['subject_id'];
                $context['allowed_section_ids'] = isset($academic['section_ids']) ? $academic['section_ids'] : array();
                $context['result_adapter'] = isset($result['adapter']) ? $result['adapter'] : $context['result_adapter'];
                $context['target_component'] = isset($result['target_component']) ? $result['target_component'] : $context['target_component'];
                $context['target_max_score'] = isset($result['target_maximum']) ? $result['target_maximum'] : $context['target_max_score'];
                $context['_frozen_result'] = $result;
            }
        }
        return $context;
    }

    protected function candidateSectionIsAllowed(array $context, $section_id)
    {
        if (isset($context['allowed_section_ids']) && is_array($context['allowed_section_ids'])) {
            return in_array((int) $section_id, array_map('intval', $context['allowed_section_ids']), true);
        }
        return $this->db->where('onlineexam_id', (int) $context['onlineexam_id'])
            ->where('section_id', (int) $section_id)
            ->count_all_results('onlineexam_class_sections') === 1;
    }

    protected function lockStandardRows(array $context)
    {
        $sql = 'SELECT * FROM `score` WHERE `StudentID` = ' . $this->db->escape((int) $context['student_id'])
            . ' AND `ClassID` = ' . $this->db->escape((int) $context['exam_class_id'])
            . ' AND `SectionID` = ' . $this->db->escape((int) $context['student_section_id'])
            . ' AND `SubjectID` = ' . $this->db->escape((int) $context['subject_id'])
            . ' AND `Session` = ' . $this->db->escape((string) $context['exam_session_id'])
            . ' AND `Term` = ' . $this->db->escape($context['term']) . ' LIMIT 2 FOR UPDATE';
        return $this->db->query($sql)->result_array();
    }

    protected function lockHolidayRows(array $context)
    {
        $sql = 'SELECT * FROM `holiday_assessment_scores` WHERE `student_id` = ' . $this->db->escape((int) $context['student_id'])
            . ' AND `class_id` = ' . $this->db->escape((int) $context['exam_class_id'])
            . ' AND `section_id` = ' . $this->db->escape((int) $context['student_section_id'])
            . ' AND `subject_id` = ' . $this->db->escape((int) $context['subject_id'])
            . ' AND `session_id` = ' . $this->db->escape((int) $context['exam_session_id'])
            . ' AND `term` = ' . $this->db->escape($context['term']) . ' LIMIT 2 FOR UPDATE';
        return $this->db->query($sql)->result_array();
    }

    protected function lockBritishRows(array $context)
    {
        $sql = 'SELECT * FROM `britishresult` WHERE `StudentID` = ' . $this->db->escape((int) $context['student_id'])
            . ' AND `ClassID` = ' . $this->db->escape((int) $context['exam_class_id'])
            . ' AND `SectionID` = ' . $this->db->escape((int) $context['student_section_id'])
            . ' AND `SubjectID` = ' . $this->db->escape((int) $context['subject_id'])
            . ' AND `Session` = ' . $this->db->escape((string) $context['exam_session_id'])
            . ' AND `Term` = ' . $this->db->escape($context['term']) . ' LIMIT 2 FOR UPDATE';
        return $this->db->query($sql)->result_array();
    }

    protected function lockKindergartenRows(array $context, array $mapping)
    {
        $sql = 'SELECT * FROM `kindergarten_result` WHERE `student_id` = ' . $this->db->escape((int) $context['student_id'])
            . ' AND `session_id` = ' . $this->db->escape((int) $context['exam_session_id'])
            . ' AND `term` = ' . $this->db->escape($context['term'])
            . ' AND `assessment_id` = ' . $this->db->escape((int) $mapping['assessment_id'])
            . ' AND `subject_id` = ' . $this->db->escape((int) $mapping['subject_id'])
            . ' AND `concept_id` = ' . $this->db->escape((int) $mapping['concept_id']) . ' LIMIT 2 FOR UPDATE';
        return $this->db->query($sql)->result_array();
    }

    protected function activeProfile($onlineexam_id, $adapter)
    {
        return $this->db->where('onlineexam_id', (int) $onlineexam_id)
            ->where('adapter', $adapter)
            ->where('is_active', 1)
            ->limit(1)
            ->get('onlineexam_result_profiles')
            ->row_array();
    }

    /** Percentage for the exact frozen paper or section mapped to one concept. */
    protected function kindergartenMappingPercentage(array $context, array $mapping)
    {
        $paper_id = (int) $mapping['paper_id'];
        $section_id = empty($mapping['paper_section_id']) ? 0 : (int) $mapping['paper_section_id'];
        if ($paper_id < 1) {
            throw new InvalidArgumentException('A Kindergarten concept must be mapped to a paper or paper section.');
        }

        if ($section_id === 0) {
            $paper = $this->db->select('raw_score, raw_max_score')
                ->where('attempt_id', (int) $context['attempt_id'])
                ->where('paper_id', $paper_id)
                ->limit(1)
                ->get('onlineexam_attempt_papers')
                ->row_array();
            if (empty($paper) || (float) $paper['raw_max_score'] <= 0) {
                throw new RuntimeException('The mapped paper has no finalized score.');
            }
            return round(max(0, min(100, ((float) $paper['raw_score'] / (float) $paper['raw_max_score']) * 100)), 2);
        }

        $configuration = $this->db->select('configuration_json')
            ->where('onlineexam_id', (int) $context['onlineexam_id'])
            ->where('revision', (int) $context['exam_revision'])
            ->limit(1)
            ->get('onlineexam_revision_snapshots')
            ->row_array();
        $configuration = empty($configuration['configuration_json']) ? array() : json_decode($configuration['configuration_json'], true);
        $section_rule = null;
        foreach (isset($configuration['papers']) ? $configuration['papers'] : array() as $paper) {
            if ((int) $paper['id'] !== $paper_id) {
                continue;
            }
            foreach (isset($paper['sections']) ? $paper['sections'] : array() as $section) {
                if ((int) $section['id'] === $section_id) {
                    $section_rule = $section;
                    break 2;
                }
            }
        }
        if (!$section_rule) {
            throw new RuntimeException('The mapped Kindergarten paper section is not present in the frozen revision.');
        }

        $rows = $this->db->select('snap.marks, snap.is_compulsory, snap.display_order, answer.is_answered, answer.final_mark')
            ->from('onlineexam_question_snapshots snap')
            ->join('onlineexam_attempt_answers answer', 'answer.question_snapshot_id = snap.id AND answer.attempt_id = ' . (int) $context['attempt_id'], 'left')
            ->where('snap.onlineexam_id', (int) $context['onlineexam_id'])
            ->where('snap.revision', (int) $context['exam_revision'])
            ->where('snap.paper_id', $paper_id)
            ->where('snap.paper_section_id', $section_id)
            ->order_by('snap.display_order', 'ASC')
            ->order_by('snap.id', 'ASC')
            ->get()
            ->result_array();
        if (empty($rows)) {
            throw new RuntimeException('The mapped Kindergarten paper section has no frozen questions.');
        }

        $rule = $section_rule['answer_rule'];
        $answer_count = (int) $section_rule['answer_count'];
        $earned = 0.0;
        $maximum = 0.0;
        if ($rule === 'all') {
            foreach ($rows as $row) {
                $earned += (float) $row['final_mark'];
                $maximum += (float) $row['marks'];
            }
        } else {
            $compulsory = array();
            $optional = array();
            foreach ($rows as $row) {
                if ($rule === 'compulsory_plus_choice' && (int) $row['is_compulsory'] === 1) {
                    $compulsory[] = $row;
                } else {
                    $optional[] = $row;
                }
            }
            foreach ($compulsory as $row) {
                $earned += (float) $row['final_mark'];
                $maximum += (float) $row['marks'];
            }
            $answered_optional = array_values(array_filter($optional, function ($row) {
                return (int) $row['is_answered'] === 1;
            }));
            foreach (array_slice($answered_optional, 0, $answer_count) as $row) {
                $earned += (float) $row['final_mark'];
            }
            $optional_marks = array_map(function ($row) {
                return (float) $row['marks'];
            }, $optional);
            rsort($optional_marks, SORT_NUMERIC);
            $maximum += array_sum(array_slice($optional_marks, 0, $answer_count));
        }

        if ($maximum <= 0) {
            throw new RuntimeException('The mapped Kindergarten paper section has an invalid score maximum.');
        }
        return round(max(0, min(100, ($earned / $maximum) * 100)), 2);
    }

    protected function percentage(array $context)
    {
        $maximum = (float) $context['target_max_score'];
        if ($maximum <= 0) {
            $maximum = 100.0;
        }
        return round(max(0, min(100, ((float) $context['final_score'] / $maximum) * 100)), 2);
    }

    protected function startLedger(array $context, $descriptor, $source_score, $applied_source)
    {
        $fingerprint = hash('sha256', json_encode(array(
            'attempt_id' => (int) $context['attempt_id'],
            'exam_revision' => (int) $context['exam_revision'],
            'final_score' => (string) $context['final_score'],
            'outcome_value' => $context['outcome_value'],
            'applied_source' => (string) $applied_source,
        )));
        $idempotency_key = hash('sha256', implode('|', array(
            (int) $context['onlineexam_id'],
            (int) $context['attempt_id'],
            $context['result_adapter'],
            $descriptor,
            $fingerprint,
        )));
        $existing = $this->db->where('idempotency_key', $idempotency_key)
            ->limit(1)
            ->get('onlineexam_result_sync')
            ->row_array();
        if (!empty($existing)) {
            if ($existing['status'] === 'posted') {
                return array('idempotent' => true, 'row' => $existing);
            }
            // A held conflict/error is re-evaluated against the destination
            // using the same ledger row. This permits an authorized user to
            // correct/clear the unrelated destination value and safely retry
            // without creating a duplicate posting record.
            $this->db->where('id', (int) $existing['id'])->update('onlineexam_result_sync', array(
                'status' => 'pending',
                'updated_at' => date('Y-m-d H:i:s'),
            ));
            return array('idempotent' => false, 'id' => (int) $existing['id'], 'retry' => true, 'row' => $existing);
        }

        $now = date('Y-m-d H:i:s');
        $this->db->insert('onlineexam_result_sync', array(
            'onlineexam_id' => (int) $context['onlineexam_id'],
            'attempt_id' => (int) $context['attempt_id'],
            'student_session_id' => (int) $context['student_session_id'],
            'adapter' => $context['result_adapter'],
            'source_score' => $source_score === null ? null : (float) $source_score,
            'scaled_score' => is_numeric($applied_source) ? (float) $applied_source : null,
            'status' => 'pending',
            'source_fingerprint' => $fingerprint,
            'idempotency_key' => $idempotency_key,
            'created_at' => $now,
            'updated_at' => $now,
        ));
        return array('idempotent' => false, 'id' => (int) $this->db->insert_id());
    }

    protected function recordSyncError(array $context, $message)
    {
        if (!$this->db->table_exists('onlineexam_result_sync')) {
            return;
        }
        $fingerprint = hash('sha256', json_encode(array(
            'attempt_id' => (int) $context['attempt_id'],
            'revision' => (int) $context['exam_revision'],
            'final_score' => (string) $context['final_score'],
            'adapter' => $context['result_adapter'],
        )));
        $idempotency_key = hash('sha256', (int) $context['onlineexam_id'] . '|' . (int) $context['attempt_id'] . '|adapter-error|' . $fingerprint);
        $now = date('Y-m-d H:i:s');
        $this->db->query(
            'INSERT INTO `onlineexam_result_sync` (`onlineexam_id`,`attempt_id`,`student_session_id`,`adapter`,`source_score`,`status`,`error_message`,`source_fingerprint`,`idempotency_key`,`created_at`,`updated_at`) VALUES ('
            . implode(',', array(
                $this->db->escape((int) $context['onlineexam_id']),
                $this->db->escape((int) $context['attempt_id']),
                $this->db->escape((int) $context['student_session_id']),
                $this->db->escape($context['result_adapter']),
                $this->db->escape($context['final_score']),
                $this->db->escape('error'),
                $this->db->escape(mb_substr((string) $message, 0, 5000)),
                $this->db->escape($fingerprint),
                $this->db->escape($idempotency_key),
                $this->db->escape($now),
                $this->db->escape($now),
            )) . ') ON DUPLICATE KEY UPDATE `status` = VALUES(`status`), `error_message` = VALUES(`error_message`), `updated_at` = VALUES(`updated_at`)'
        );
    }

    /** Serialize adapters that share a logical legacy result destination. */
    protected function lockResultTarget(array $context, $descriptor)
    {
        $target_descriptor = implode('|', array(
            (int) $context['student_id'],
            (int) $context['exam_class_id'],
            (int) $context['student_section_id'],
            (int) $context['subject_id'],
            (int) $context['exam_session_id'],
            $context['term'],
            $descriptor,
        ));
        $target_hash = hash('sha256', $target_descriptor);
        $now = date('Y-m-d H:i:s');
        $this->db->query(
            'INSERT INTO `onlineexam_result_target_locks` (`target_hash`, `target_descriptor`, `created_at`, `updated_at`) VALUES ('
            . $this->db->escape($target_hash) . ', ' . $this->db->escape($target_descriptor) . ', '
            . $this->db->escape($now) . ', ' . $this->db->escape($now) . ') '
            . 'ON DUPLICATE KEY UPDATE `updated_at` = VALUES(`updated_at`)'
        );
        $this->db->query(
            'SELECT `target_hash` FROM `onlineexam_result_target_locks` WHERE `target_hash` = '
            . $this->db->escape($target_hash) . ' FOR UPDATE'
        )->row_array();
    }

    protected function finishLedger($ledger_id, $table, $record_id, $field, $previous, $applied, $previous_metadata = null, $applied_metadata = null)
    {
        $now = date('Y-m-d H:i:s');
        $updates = array(
            'target_table' => $table,
            'target_record_id' => $record_id,
            'target_field' => $field,
            'previous_value' => $previous === null ? null : (string) $previous,
            'applied_value' => $applied === null ? null : (string) $applied,
            'status' => 'posted',
            'conflict_reason' => null,
            'error_message' => null,
            'synced_at' => $now,
            'updated_at' => $now,
        );
        if ($this->db->field_exists('previous_metadata_json', 'onlineexam_result_sync')) {
            $updates['previous_metadata_json'] = $previous_metadata === null ? null : json_encode($previous_metadata);
        }
        if ($this->db->field_exists('applied_metadata_json', 'onlineexam_result_sync')) {
            $updates['applied_metadata_json'] = $applied_metadata === null ? null : json_encode($applied_metadata);
        }
        $this->db->where('id', (int) $ledger_id)->update('onlineexam_result_sync', $updates);
    }

    protected function conflict($ledger_id, $table, $record_id, $field, $reason, $current = null)
    {
        $this->db->where('id', (int) $ledger_id)->update('onlineexam_result_sync', array(
            'target_table' => $table,
            'target_record_id' => $record_id,
            'target_field' => $field,
            'previous_value' => $current === null ? null : (string) $current,
            'status' => 'conflict',
            'conflict_reason' => $reason,
            // Any earlier replacement decision was for the previously
            // observed value. A changed destination must be reviewed again.
            'override_authorized_at' => null,
            'override_authorized_by' => null,
            'override_reason' => null,
            'updated_at' => date('Y-m-d H:i:s'),
        ));
        return array('success' => false, 'status' => 'conflict', 'reason' => $reason, 'idempotent' => false);
    }

    protected function latestOwnedSync(array $context, $table, $record_id, $field)
    {
        return $this->db->where('onlineexam_id', (int) $context['onlineexam_id'])
            ->where('student_session_id', (int) $context['student_session_id'])
            ->where('target_table', $table)
            ->where('target_record_id', (int) $record_id)
            ->where('target_field', $field)
            ->where('status', 'posted')
            ->order_by('id', 'DESC')
            ->limit(1)
            ->get('onlineexam_result_sync')
            ->row_array();
    }

    protected function sameValue($current, $expected)
    {
        if (is_numeric($current) && is_numeric($expected)) {
            return abs((float) $current - (float) $expected) < 0.00001;
        }
        return (string) $current === (string) $expected;
    }

    protected function authorizedOverrideMatches(array $ledger, $current)
    {
        return !empty($ledger['row']['override_authorized_at'])
            && array_key_exists('previous_value', $ledger['row'])
            && $this->sameValue($current, $ledger['row']['previous_value']);
    }

    protected function ledgerResult(array $row)
    {
        if ($row['status'] === 'posted') {
            return array(
                'success' => true,
                'status' => 'posted',
                'record_id' => $row['target_record_id'],
                'value' => $row['applied_value'],
                'idempotent' => true,
            );
        }
        return array(
            'success' => false,
            'status' => $row['status'],
            'reason' => $row['conflict_reason'] ?: $row['error_message'],
            'idempotent' => true,
        );
    }

    protected function auditSync(array $context, $actor_id, array $result)
    {
        $this->db->insert('onlineexam_audit_log', array(
            'onlineexam_id' => (int) $context['onlineexam_id'],
            'attempt_id' => (int) $context['attempt_id'],
            'actor_id' => $actor_id === null ? null : (int) $actor_id,
            'actor_type' => $actor_id === null ? 'system' : 'staff',
            'action' => 'sync_result',
            'entity_type' => 'onlineexam_result_sync',
            'entity_id' => null,
            'before_json' => null,
            'after_json' => json_encode($result),
            'ip_address' => isset($this->input) ? $this->input->ip_address() : null,
            'created_at' => date('Y-m-d H:i:s'),
        ));
    }
}
