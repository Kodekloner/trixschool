<?php

defined('BASEPATH') OR exit('No direct script access allowed');

/**
 * Domain model for the compact online-assessment flow.
 *
 * Legacy Onlineexam_model remains the reader/writer for workflow_version=1.
 * This model owns v2 validation, immutable revision snapshots and final score
 * calculation only; attempt autosave/start endpoints live separately.
 */
class Onlineexamworkflow_model extends CI_Model
{
    const WORKFLOW_VERSION = 2;

    protected $purposes = array('ca', 'midterm', 'holiday', 'kindergarten');
    protected $adapters = array('standard_component', 'british_outcome', 'kindergarten_concept', 'holiday_assessment');
    protected $terms = array('1st', '2nd', '3rd');
    protected $paper_types = array('objective', 'theory');
    protected $delivery_modes = array('cbt');
    protected $answer_rules = array('all', 'answer_any', 'compulsory_plus_choice');
    protected $localized_question_types = array(
        'singlechoice', 'multichoice', 'true_false', 'short_answer', 'numeric',
        'matching', 'ordering', 'long_answer'
    );

    public function __construct()
    {
        parent::__construct();
        $this->load->library('onlineexam_scoring');
    }

    public function getAssessment($onlineexam_id)
    {
        return $this->db->where('id', (int) $onlineexam_id)->get('onlineexam')->row_array();
    }

    public function detectResultAdapter($class_id)
    {
        $class_id = (int) $class_id;

        if ($this->db->table_exists('kindergarten_assignment')) {
            $kindergarten = $this->db->where('class_id', $class_id)
                ->limit(1)
                ->get('kindergarten_assignment')
                ->row_array();
            if (!empty($kindergarten)) {
                return 'kindergarten_concept';
            }
        }

        $assignment = $this->db->where('ClassID', $class_id)
            ->limit(1)
            ->get('assigncatoclass')
            ->row_array();
        if (!empty($assignment) && strtolower(trim($assignment['ResultType'])) === 'british') {
            return 'british_outcome';
        }

        return !empty($assignment) ? 'standard_component' : null;
    }

    /**
     * Returns the configured CA slots and derived examination maximum for a
     * class. It never assumes 40/60; resultsetting remains authoritative.
     */
    public function getStandardDestinations($class_id, $purpose = null)
    {
        $row = $this->db->select('assigncatoclass.ResultType, assigncatoclass.ResultSettingID, resultsetting.*')
            ->from('assigncatoclass')
            ->join('resultsetting', 'resultsetting.ResultSettingID = assigncatoclass.ResultSettingID')
            ->where('assigncatoclass.ClassID', (int) $class_id)
            ->limit(1)
            ->get()
            ->row_array();

        if (empty($row)) {
            return array('valid' => false, 'errors' => array('No CA setting is assigned to this class.'), 'destinations' => array());
        }
        if (strtolower(trim($row['ResultType'])) === 'british') {
            return array('valid' => false, 'errors' => array('This class uses British outcomes, not numeric CA components.'), 'destinations' => array());
        }

        $number_of_ca = max(0, min(10, (int) $row['NumberOfCA']));
        $errors = array();
        $destinations = array();
        $ca_total = 0.0;

        for ($number = 1; $number <= $number_of_ca; $number++) {
            $score_key = 'CA' . $number . 'Score';
            $title_key = 'CA' . $number . 'Title';
            $maximum = (float) $row[$score_key];
            if ($maximum < 0) {
                $errors[] = $score_key . ' cannot be negative.';
                continue;
            }
            $ca_total += $maximum;
            $destinations[] = array(
                'component' => 'ca' . $number,
                'title' => trim($row[$title_key]) !== '' ? $row[$title_key] : 'CA ' . $number,
                'maximum' => round($maximum, 2),
            );
        }

        $exam_maximum = 100 - $ca_total;
        if ($ca_total < 0 || $ca_total > 100) {
            $errors[] = 'Enabled CA maximums must total between 0 and 100.';
        }
        if (!in_array($purpose, array('ca', 'midterm'), true) && $exam_maximum <= 0) {
            $errors[] = 'The CA settings leave no score available for the examination component.';
        } elseif (!in_array($purpose, array('ca', 'midterm'), true)) {
            $destinations[] = array(
                'component' => 'exam',
                'title' => 'Examination',
                'maximum' => round($exam_maximum, 2),
            );
        }

        if (in_array($purpose, array('ca', 'midterm'), true)) {
            $normalized = $this->onlineexam_scoring->normalizeMidtermSlots($row['MidTermCaToUse'], $number_of_ca);
            if (!empty($normalized['invalid'])) {
                $errors[] = 'MidTermCaToUse contains an invalid CA slot.';
            }
            $destinations = $this->onlineexam_scoring->filterStandardComponents(
                $destinations,
                $purpose,
                $row['MidTermCaToUse'],
                $number_of_ca
            );
            if (empty($destinations)) {
                $errors[] = $purpose === 'midterm'
                    ? 'No positive-score CA slot is configured for Midterm.'
                    : 'No positive-score Continuous Assessment slot remains after Midterm slots are reserved.';
            }
        }

        return array(
            'valid' => empty($errors),
            'errors' => $errors,
            'result_setting_id' => (int) $row['ResultSettingID'],
            'result_type' => $row['ResultType'],
            'midterm_ca_to_use' => $row['MidTermCaToUse'],
            'ca_total' => round($ca_total, 2),
            'exam_maximum' => round($exam_maximum, 2),
            'destinations' => $destinations,
        );
    }

    public function validateAssessment($onlineexam_id)
    {
        $exam = $this->getAssessment($onlineexam_id);
        $errors = array();
        if (empty($exam)) {
            return array('valid' => false, 'errors' => array('Assessment was not found.'));
        }
        if ((int) $exam['workflow_version'] !== self::WORKFLOW_VERSION) {
            return array('valid' => false, 'errors' => array('Legacy examinations cannot be published through the v2 workflow.'));
        }

        foreach (array('session_id', 'class_id', 'subject_id') as $required) {
            if (empty($exam[$required])) {
                $errors[] = ucfirst(str_replace('_', ' ', $required)) . ' is required.';
            }
        }
        if (!in_array($exam['term'], $this->terms, true)) {
            $errors[] = 'Term must be 1st, 2nd or 3rd.';
        }
        if (!in_array($exam['purpose'], $this->purposes, true)) {
            $errors[] = 'Assessment purpose is invalid.';
        }
        if (!in_array($exam['result_adapter'], $this->adapters, true)) {
            $errors[] = 'Result adapter is invalid.';
        }
        $purpose_adapters = array(
            'ca' => 'standard_component',
            'midterm' => 'standard_component',
            'holiday' => 'holiday_assessment',
            'kindergarten' => 'kindergarten_concept',
        );
        if (isset($purpose_adapters[$exam['purpose']])
            && $exam['result_adapter'] !== $purpose_adapters[$exam['purpose']]) {
            $errors[] = 'The result destination does not match the selected assessment purpose.';
        }
        if ((int) $exam['attempt'] !== 1) {
            $errors[] = 'Result-bearing online assessments permit one official attempt only.';
        }
        $this->load->library('onlineexam_setup');
        $duration_parts = explode(':', (string) $exam['duration']);
        $duration_minutes = count($duration_parts) >= 2 ? (int) $duration_parts[0] * 60 + (int) $duration_parts[1] : 0;
        $window_error = $this->onlineexam_setup->windowError($exam['exam_from'], $exam['exam_to'], $duration_minutes);
        if ($window_error) {
            $errors[] = $window_error;
        }

        $sections = $this->db->where('onlineexam_id', (int) $onlineexam_id)->get('onlineexam_class_sections')->result_array();
        if (empty($sections)) {
            $errors[] = 'At least one class section/arm is required.';
        } else {
            foreach ($sections as $section) {
                $valid_section = $this->db->where('class_id', (int) $exam['class_id'])
                    ->where('section_id', (int) $section['section_id'])
                    ->limit(1)
                    ->get('class_sections')
                    ->row_array();
                if (empty($valid_section)) {
                    $errors[] = 'A selected section does not belong to the assessment class.';
                    break;
                }
            }
            $this->load->model('onlineexam_model');
            $section_ids = array_map(function ($section) {
                return (int) $section['section_id'];
            }, $sections);
            if (!$this->onlineexam_model->subjectIsAssignedToAcademicScope(
                $exam['class_id'],
                $section_ids,
                $exam['subject_id'],
                $exam['session_id']
            )) {
                $errors[] = 'The assessment subject is not assigned to every selected class arm in this academic session.';
            }
        }

        if (!$this->db->table_exists('onlineexam_academic_slots')) {
            $errors[] = 'Install Online Examination migration 138 before publishing this assessment.';
        } elseif (count($sections) !== (int) $this->db->where('onlineexam_id', (int) $onlineexam_id)
            ->count_all_results('onlineexam_academic_slots')) {
            $errors[] = 'This subject overlaps another online examination for the selected class arm, term and result component. Edit the academic context and save it again.';
        }

        $target_snapshot = $this->validateResultTarget($exam, $errors);
        $paper_count = (int) $this->db->where('onlineexam_id', (int) $onlineexam_id)
            ->count_all_results('onlineexam_papers');
        $papers = $this->db->where('onlineexam_id', (int) $onlineexam_id)
            ->where('is_active', 1)
            ->order_by('display_order', 'ASC')
            ->get('onlineexam_papers')
            ->result_array();
        if (empty($papers)) {
            $errors[] = 'At least one active paper is required.';
        }
        if ($paper_count > 1) {
            $errors[] = 'Only one paper is allowed for each subject assessment. Delete the extra paper before publishing.';
        }

        $total_contribution = 0.0;
        foreach ($papers as $paper) {
            if (!in_array($paper['paper_type'], $this->paper_types, true)) {
                $errors[] = 'Paper ' . $paper['title'] . ' has an invalid paper type.';
            }
            if (!in_array($paper['delivery_mode'], $this->delivery_modes, true)) {
                $errors[] = 'Paper ' . $paper['title'] . ' has an invalid delivery mode.';
            }
            if ((float) $paper['raw_max_score'] <= 0 || (float) $paper['contribution_score'] <= 0) {
                $errors[] = 'Paper ' . $paper['title'] . ' must have positive raw and contribution maximums.';
            }
            $paper_window_error = $this->onlineexam_setup->windowError(
                $paper['starts_at'] ?: $exam['exam_from'], $paper['ends_at'] ?: $exam['exam_to'],
                $paper['duration_minutes'], $exam['exam_from'], $exam['exam_to']
            );
            if ($paper_window_error) {
                $errors[] = 'Paper ' . $paper['title'] . ': ' . $paper_window_error;
            }
            if (in_array($exam['lifecycle_status'], array('draft', 'scheduled'), true)
                && abs((float) $paper['raw_max_score'] - (float) $exam['target_max_score']) > 0.001) {
                $errors[] = 'Save paper ' . $paper['title'] . ' again so its maximum matches the selected assessment component.';
            }
            $total_contribution += (float) $paper['contribution_score'];
            $this->validatePaperQuestions($paper, $errors);
        }
        if ($total_contribution <= 0) {
            $errors[] = 'Combined paper contribution must be greater than zero.';
        } elseif (count($papers) === 1 && abs($total_contribution - 100.0) > 0.001) {
            $errors[] = 'The single subject paper must contribute 100 percent of the selected result component. Save the paper again.';
        }

        return array(
            'valid' => empty($errors),
            'errors' => array_values(array_unique($errors)),
            'exam' => $exam,
            'papers' => $papers,
            'class_sections' => $sections,
            'result_snapshot' => $target_snapshot,
        );
    }

    /**
     * Freezes all mutable question rows into an immutable exam revision.
     */
    public function freezeRevision($onlineexam_id, $actor_id = null)
    {
        $validation = $this->validateAssessment($onlineexam_id);
        if (!$validation['valid']) {
            return array('success' => false, 'errors' => $validation['errors']);
        }

        $exam = $validation['exam'];
        if (!in_array($exam['lifecycle_status'], array('draft', 'scheduled'), true)) {
            return array('success' => false, 'errors' => array('Only a draft or scheduled assessment can be frozen.'));
        }

        $this->db->trans_begin();
        $locked = $this->db->query(
            'SELECT * FROM `onlineexam` WHERE `id` = ' . $this->db->escape((int) $onlineexam_id) . ' FOR UPDATE'
        )->row_array();
        if (empty($locked) || (int) $locked['revision'] !== (int) $exam['revision']) {
            $this->db->trans_rollback();
            return array('success' => false, 'errors' => array('Assessment revision changed while it was being frozen.'));
        }

        // Serialize draft mutations with the freeze. Controllers also enforce
        // draft-only editing, but these row locks protect against two staff
        // actions arriving at the same time.
        $this->db->query('SELECT `id` FROM `onlineexam_papers` WHERE `onlineexam_id` = ' . $this->db->escape((int) $onlineexam_id) . ' FOR UPDATE');
        $this->db->query('SELECT `id` FROM `onlineexam_questions` WHERE `onlineexam_id` = ' . $this->db->escape((int) $onlineexam_id) . ' FOR UPDATE');
        $validation = $this->validateAssessment($onlineexam_id);
        if (!$validation['valid']) {
            $this->db->trans_rollback();
            return array('success' => false, 'errors' => $validation['errors']);
        }
        $exam = $validation['exam'];

        $existing = $this->db->where('onlineexam_id', (int) $onlineexam_id)
            ->where('revision', (int) $exam['revision'])
            ->count_all_results('onlineexam_question_snapshots');
        $existing_revision = $this->db->where('onlineexam_id', (int) $onlineexam_id)
            ->where('revision', (int) $exam['revision'])
            ->count_all_results('onlineexam_revision_snapshots');
        if ($existing > 0 || $existing_revision > 0) {
            $this->db->trans_rollback();
            return array('success' => false, 'errors' => array('This assessment revision is already frozen. Create a new revision to make changes.'));
        }

        $draft_questions = $this->db->select(
                'onlineexam_questions.*, questions.question, questions.question_type, questions.opt_a, questions.opt_b, '
                . 'questions.opt_c, questions.opt_d, questions.opt_e, questions.correct'
            )
            ->from('onlineexam_questions')
            ->join('questions', 'questions.id = onlineexam_questions.question_id')
            ->join('onlineexam_papers', 'onlineexam_papers.id = onlineexam_questions.paper_id AND onlineexam_papers.onlineexam_id = onlineexam_questions.onlineexam_id')
            ->where('onlineexam_questions.onlineexam_id', (int) $onlineexam_id)
            ->where('onlineexam_papers.is_active', 1)
            ->order_by('onlineexam_questions.paper_id', 'ASC')
            ->order_by('onlineexam_questions.display_order', 'ASC')
            ->get()
            ->result_array();

        $now = date('Y-m-d H:i:s');
        foreach ($draft_questions as $question) {
            $authoring = !empty($question['authoring_json']) ? json_decode($question['authoring_json'], true) : null;
            $is_native = is_array($authoring)
                && isset($authoring['version'], $authoring['question_type'])
                && (int) $authoring['version'] === 1;
            $options = $is_native && isset($authoring['options']) && is_array($authoring['options'])
                ? $authoring['options']
                : $this->snapshotOptions($question);
            $correct = $is_native && array_key_exists('correct_answer', $authoring)
                ? $authoring['correct_answer']
                : $this->snapshotCorrectAnswer($question);
            $response_schema = $is_native && isset($authoring['response_schema']) && is_array($authoring['response_schema'])
                ? $authoring['response_schema']
                : array('version' => 1, 'input' => $question['question_type']);
            $passage = $is_native && !empty($authoring['passage']) && is_array($authoring['passage'])
                ? $authoring['passage']
                : array();
            $payload = array(
                'onlineexam_id' => (int) $onlineexam_id,
                'paper_id' => (int) $question['paper_id'],
                'paper_section_id' => empty($question['paper_section_id']) ? null : (int) $question['paper_section_id'],
                'source_question_id' => (int) $question['question_id'],
                'revision' => (int) $exam['revision'],
                'question_type' => $is_native ? $authoring['question_type'] : $question['question_type'],
                'question_text' => $question['question'],
                'options_json' => json_encode($options),
                'correct_answer_json' => json_encode($correct),
                'response_schema_json' => json_encode($response_schema),
                'passage_group_key' => !empty($passage['group_key']) ? $passage['group_key'] : null,
                'passage_title' => !empty($passage['title']) ? $passage['title'] : null,
                'passage_text' => !empty($passage['text']) ? $passage['text'] : null,
                'marking_scheme' => $question['marking_scheme'],
                'marks' => round((float) $question['marks'], 2),
                'neg_marks' => max(0, round((float) $question['neg_marks'], 2)),
                'is_compulsory' => (int) $question['is_compulsory'],
                'display_order' => (int) $question['display_order'],
                'created_at' => $now,
            );
            $payload['checksum'] = hash('sha256', json_encode($payload));
            $this->db->insert('onlineexam_question_snapshots', $payload);
        }

        $before = $locked;
        $frozen_configuration = $this->buildFrozenConfiguration(
            $exam,
            $validation['result_snapshot'],
            $validation['papers'],
            $validation['class_sections']
        );
        $frozen_json = json_encode($frozen_configuration);
        $this->db->insert('onlineexam_revision_snapshots', array(
            'onlineexam_id' => (int) $onlineexam_id,
            'revision' => (int) $exam['revision'],
            'configuration_json' => $frozen_json,
            'checksum' => hash('sha256', $frozen_json),
            'frozen_by' => $actor_id === null ? null : (int) $actor_id,
            'frozen_at' => $now,
        ));
        $updates = array(
            'target_max_score' => $validation['result_snapshot']['target_maximum'],
            'result_config_snapshot' => $frozen_json,
            'frozen_at' => $now,
            'lifecycle_status' => 'scheduled',
        );
        $this->db->where('id', (int) $onlineexam_id)->update('onlineexam', $updates);
        $this->audit($onlineexam_id, null, $actor_id, 'freeze_revision', 'onlineexam', $onlineexam_id, $before, array_merge($before, $updates));

        if ($this->db->trans_status() === false) {
            $this->db->trans_rollback();
            return array('success' => false, 'errors' => array('The assessment revision could not be frozen.'));
        }
        $this->db->trans_commit();

        return array('success' => true, 'revision' => (int) $exam['revision'], 'snapshot_count' => count($draft_questions));
    }

    /**
     * Opens a new editable revision without deleting the prior immutable
     * snapshots or attempts. Existing paper/question rows become the starting
     * point for the new draft.
     */
    public function beginNewRevision($onlineexam_id, $actor_id = null)
    {
        $this->db->trans_begin();
        $exam = $this->db->query(
            'SELECT * FROM `onlineexam` WHERE `id` = ' . $this->db->escape((int) $onlineexam_id) . ' FOR UPDATE'
        )->row_array();
        if (empty($exam) || (int) $exam['workflow_version'] !== self::WORKFLOW_VERSION) {
            $this->db->trans_rollback();
            return array('success' => false, 'errors' => array('Assessment was not found.'));
        }
        if (empty($exam['frozen_at'])) {
            $this->db->trans_rollback();
            return array('success' => false, 'errors' => array('The current revision is already editable.'));
        }
        $unfinished_attempts = $this->db
            ->where('onlineexam_id', (int) $onlineexam_id)
            ->where('revision', (int) $exam['revision'])
            ->where_in('status', array('in_progress', 'submitted', 'timed_out', 'marking'))
            ->count_all_results('onlineexam_candidate_attempts');
        if ($unfinished_attempts > 0) {
            $this->db->trans_rollback();
            return array('success' => false, 'errors' => array('A new revision cannot be opened while candidates are taking the current revision or awaiting marking.'));
        }
        $active_attempts = $this->db->where('onlineexam_id', (int) $onlineexam_id)
            ->where_in('status', array('in_progress', 'submitted', 'marking'))
            ->count_all_results('onlineexam_candidate_attempts');
        if ($active_attempts > 0) {
            $this->db->trans_rollback();
            return array('success' => false, 'errors' => array('A new revision cannot be opened while candidates are attempting or awaiting marking.'));
        }

        $updates = array(
            'revision' => (int) $exam['revision'] + 1,
            'lifecycle_status' => 'draft',
            'frozen_at' => null,
            'published_at' => null,
            'result_config_snapshot' => null,
            'is_active' => '0',
        );
        $this->db->where('id', (int) $onlineexam_id)->update('onlineexam', $updates);
        $this->audit($onlineexam_id, null, $actor_id, 'begin_revision', 'onlineexam', $onlineexam_id, $exam, array_merge($exam, $updates));
        if ($this->db->trans_status() === false) {
            $this->db->trans_rollback();
            return array('success' => false, 'errors' => array('A new assessment revision could not be opened.'));
        }
        $this->db->trans_commit();
        return array('success' => true, 'revision' => $updates['revision']);
    }

    public function publishAssessment($onlineexam_id, $actor_id = null)
    {
        $exam = $this->getAssessment($onlineexam_id);
        if (empty($exam) || (int) $exam['workflow_version'] !== self::WORKFLOW_VERSION) {
            return array('success' => false, 'errors' => array('Assessment was not found.'));
        }
        if (empty($exam['frozen_at'])) {
            $frozen = $this->freezeRevision($onlineexam_id, $actor_id);
            if (!$frozen['success']) {
                return $frozen;
            }
            $exam = $this->getAssessment($onlineexam_id);
        }
        $this->db->trans_begin();
        $exam = $this->db->query(
            'SELECT * FROM `onlineexam` WHERE `id` = ' . $this->db->escape((int) $onlineexam_id) . ' FOR UPDATE'
        )->row_array();
        $snapshot_exists = !empty($exam) && $this->db->where('onlineexam_id', (int) $onlineexam_id)
            ->where('revision', (int) $exam['revision'])
            ->count_all_results('onlineexam_revision_snapshots') === 1;
        if (empty($exam)
            || (int) $exam['workflow_version'] !== self::WORKFLOW_VERSION
            || empty($exam['frozen_at'])
            || !$snapshot_exists
            || !in_array($exam['lifecycle_status'], array('scheduled', 'published'), true)) {
            $this->db->trans_rollback();
            return array('success' => false, 'errors' => array('Only the currently frozen assessment revision can be published.'));
        }
        if ($exam['lifecycle_status'] === 'published') {
            $this->db->trans_commit();
            return array('success' => true, 'already_published' => true);
        }
        $now = date('Y-m-d H:i:s');
        $this->db->where('id', (int) $onlineexam_id)
            ->where('revision', (int) $exam['revision'])
            ->where('lifecycle_status', 'scheduled')
            ->update('onlineexam', array(
            'lifecycle_status' => 'published',
            'published_at' => $now,
            'is_active' => '1',
        ));
        $this->audit($onlineexam_id, null, $actor_id, 'publish_assessment', 'onlineexam', $onlineexam_id, $exam, array_merge($exam, array('lifecycle_status' => 'published', 'published_at' => $now)));
        if ($this->db->trans_status() === false) {
            $this->db->trans_rollback();
            return array('success' => false, 'errors' => array('The assessment could not be published.'));
        }
        $this->db->trans_commit();
        return array('success' => true, 'already_published' => false);
    }

    /**
     * Recalculates a submitted/timed-out attempt. Manual papers are held until
     * the attempt marking status is finalized. Safe to call again after a mark
     * correction; result sync uses a source fingerprint for idempotency.
     */
    public function finalizeAttempt($attempt_id, $actor_id = null)
    {
        $this->db->trans_begin();
        $attempt = $this->db->query(
            'SELECT a.*, e.target_max_score, e.result_adapter, e.lifecycle_status '
            . 'FROM `onlineexam_candidate_attempts` a INNER JOIN `onlineexam` e ON e.id = a.onlineexam_id '
            . 'WHERE a.id = ' . $this->db->escape((int) $attempt_id) . ' FOR UPDATE'
        )->row_array();

        if (empty($attempt)) {
            $this->db->trans_rollback();
            return array('success' => false, 'errors' => array('Attempt was not found.'));
        }
        if (!in_array($attempt['status'], array('submitted', 'timed_out', 'marking', 'completed'), true)) {
            $this->db->trans_rollback();
            return array('success' => false, 'errors' => array('Only a submitted or timed-out attempt can be finalized.'));
        }
        $revision_configuration = $this->getRevisionConfiguration($attempt['onlineexam_id'], $attempt['revision']);
        if (!empty($revision_configuration['result'])) {
            $attempt['target_max_score'] = isset($revision_configuration['result']['target_maximum'])
                ? $revision_configuration['result']['target_maximum']
                : $attempt['target_max_score'];
            $attempt['result_adapter'] = isset($revision_configuration['result']['adapter'])
                ? $revision_configuration['result']['adapter']
                : $attempt['result_adapter'];
        }
        $papers = $this->frozenPapersForAttempt($attempt['onlineexam_id'], $attempt['revision']);
        $paper_manual_pending = array();
        foreach ($papers as $paper) {
            if (isset($paper['is_active']) && (int) $paper['is_active'] !== 1) {
                continue;
            }
            $paper_attempt = $this->db->where('attempt_id', (int) $attempt_id)
                ->where('paper_id', (int) $paper['id'])->limit(1)
                ->get('onlineexam_attempt_papers')->row_array();
            if (empty($paper_attempt) || !in_array($paper_attempt['status'], array('submitted', 'completed'), true)) {
                $this->db->trans_rollback();
                return array('success' => false, 'errors' => array('Every paper must be completed before calculating the assessment result.'));
            }
            if ((isset($paper['delivery_mode']) && $paper['delivery_mode'] === 'paper')
                || (!empty($paper_attempt['completion_source']) && $paper_attempt['completion_source'] === 'manual')) {
                if (empty($paper_attempt) || $paper_attempt['manual_marking_status'] !== 'finalized') {
                    $paper_manual_pending[] = $paper['title'];
                }
            }
        }
        if (((int) $attempt['manual_marking_required'] === 1 && $attempt['marking_status'] !== 'finalized') || !empty($paper_manual_pending)) {
            $this->db->where('id', (int) $attempt_id)->update('onlineexam_candidate_attempts', array(
                'status' => 'marking',
                'manual_marking_required' => 1,
                'marking_status' => 'pending',
                'updated_at' => date('Y-m-d H:i:s'),
            ));
            $this->db->trans_commit();
            $message = !empty($paper_manual_pending)
                ? 'Manual paper scores are still required for: ' . implode(', ', $paper_manual_pending) . '.'
                : 'Manual marking is not finalized.';
            return array('success' => false, 'pending_marking' => true, 'errors' => array($message));
        }
        $paper_inputs = array();
        $paper_rows = array();
        try {
            foreach ($papers as $paper) {
                if (isset($paper['is_active']) && (int) $paper['is_active'] !== 1) {
                    continue;
                }
                $earned = $this->calculateAttemptPaperEarned((int) $attempt_id, $paper);
                $paper_inputs[$paper['id']] = array(
                    'earned' => $earned,
                    'raw_max' => (float) $paper['raw_max_score'],
                    'contribution' => (float) $paper['contribution_score'],
                );
            }

            $target_maximum = (float) $attempt['target_max_score'];
            if ($target_maximum <= 0 && in_array($attempt['result_adapter'], array('british_outcome', 'kindergarten_concept', 'holiday_assessment'), true)) {
                $target_maximum = 100.0;
            }
            $calculation = $this->onlineexam_scoring->calculateAssessment($paper_inputs, $target_maximum);
        } catch (Exception $exception) {
            $this->db->trans_rollback();
            return array('success' => false, 'errors' => array($exception->getMessage()));
        }

        $now = date('Y-m-d H:i:s');
        foreach ($calculation['papers'] as $paper_id => $paper_result) {
            $paper_rows[] = array_merge($paper_result, array(
                'attempt_id' => (int) $attempt_id,
                'paper_id' => (int) $paper_id,
                'status' => 'completed',
                'submitted_at' => $attempt['submitted_at'],
                'created_at' => $now,
                'updated_at' => $now,
            ));
        }
        foreach ($paper_rows as $row) {
            $existing = $this->db->where('attempt_id', $row['attempt_id'])
                ->where('paper_id', $row['paper_id'])
                ->get('onlineexam_attempt_papers')
                ->row_array();
            if (empty($existing)) {
                $this->db->insert('onlineexam_attempt_papers', $row);
            } else {
                unset($row['created_at'], $row['submitted_at']);
                $this->db->where('id', $existing['id'])->update('onlineexam_attempt_papers', $row);
            }
        }

        $before = $attempt;
        $updates = array(
            'status' => 'completed',
            'raw_score' => $calculation['raw_score'],
            'raw_max_score' => $calculation['raw_max_score'],
            'weighted_score' => $calculation['weighted_score'],
            'weighted_max_score' => $calculation['weighted_max_score'],
            'final_score' => $calculation['final_score'],
            'marking_status' => 'finalized',
            'updated_at' => $now,
        );
        $this->db->where('id', (int) $attempt_id)->update('onlineexam_candidate_attempts', $updates);
        $this->audit($attempt['onlineexam_id'], $attempt_id, $actor_id, 'finalize_attempt', 'onlineexam_candidate_attempts', $attempt_id, $before, array_merge($before, $updates));

        if ($this->db->trans_status() === false) {
            $this->db->trans_rollback();
            return array('success' => false, 'errors' => array('Attempt calculation could not be saved.'));
        }
        $this->db->trans_commit();

        return array('success' => true, 'calculation' => $calculation);
    }

    protected function validateResultTarget(array $exam, array &$errors)
    {
        $snapshot = array(
            'adapter' => $exam['result_adapter'],
            'target_component' => $exam['target_component'],
            'target_maximum' => 100.0,
        );

        if ($exam['result_adapter'] === 'standard_component') {
            $configuration = $this->getStandardDestinations($exam['class_id'], $exam['purpose']);
            if (!$configuration['valid']) {
                $errors = array_merge($errors, $configuration['errors']);
                return $snapshot;
            }
            $selected = null;
            foreach ($configuration['destinations'] as $destination) {
                if ($destination['component'] === strtolower($exam['target_component'])) {
                    $selected = $destination;
                    break;
                }
            }
            if ($selected === null) {
                $errors[] = 'The selected CA/Exam component is not enabled for this class.';
            } elseif ((float) $selected['maximum'] <= 0) {
                $errors[] = 'The selected result component must have a positive maximum.';
            } else {
                $snapshot = array_merge($configuration, array(
                    'adapter' => 'standard_component',
                    'target_component' => $selected['component'],
                    'target_title' => $selected['title'],
                    'target_maximum' => $selected['maximum'],
                ));
            }
        } elseif ($exam['result_adapter'] === 'british_outcome') {
            $profile = $this->getResultProfile($exam['id'], 'british_outcome');
            if (empty($profile)) {
                $errors[] = 'A British outcome profile is required.';
            } else {
                $configuration = json_decode($profile['configuration_json'], true);
                $valid = is_array($configuration) ? $this->onlineexam_scoring->validateBritishProfile($configuration) : array('valid' => false, 'error' => 'British outcome profile is invalid JSON.');
                if (!$valid['valid']) {
                    $errors[] = $valid['error'];
                }
                $snapshot['profile'] = $configuration;
            }
        } elseif ($exam['result_adapter'] === 'kindergarten_concept') {
            $mappings = $this->db->where('onlineexam_id', (int) $exam['id'])->get('onlineexam_kindergarten_mappings')->result_array();
            if (empty($mappings)) {
                $errors[] = 'At least one Kindergarten concept mapping is required.';
            }
            $snapshot['mappings'] = $mappings;
        } elseif ($exam['result_adapter'] === 'holiday_assessment') {
            $snapshot = $this->validateHolidayResultTarget($exam, $snapshot, $errors);
        } else {
            $errors[] = 'Result adapter is invalid.';
        }

        return $snapshot;
    }

    /**
     * Revalidate every arm-specific Holiday destination at publication time.
     * The identifiers saved with the draft are frozen into the revision only
     * after they still match the live Holiday configuration.
     */
    protected function validateHolidayResultTarget(array $exam, array $snapshot, array &$errors)
    {
        if (!$this->db->table_exists('onlineexam_holiday_mappings')
            || !$this->db->table_exists('holiday_assessment_settings')
            || !$this->db->table_exists('holiday_assessment_subjects')) {
            $errors[] = 'Holiday Assessment mapping tables are not installed.';
            return $snapshot;
        }

        $selected_sections = $this->db->select('section_id')
            ->where('onlineexam_id', (int) $exam['id'])
            ->order_by('section_id', 'ASC')
            ->get('onlineexam_class_sections')
            ->result_array();
        $selected_sections = array_map(function ($row) {
            return (int) $row['section_id'];
        }, $selected_sections);
        $stored = $this->db->where('onlineexam_id', (int) $exam['id'])
            ->order_by('section_id', 'ASC')
            ->get('onlineexam_holiday_mappings')
            ->result_array();
        if (empty($stored) || count($stored) !== count($selected_sections)) {
            $errors[] = 'Every selected class arm must have one Holiday Assessment destination.';
            return $snapshot;
        }

        $by_section = array();
        $target_maximum = null;
        foreach ($stored as $mapping) {
            $section_id = (int) $mapping['section_id'];
            if (isset($by_section[$section_id]) || !in_array($section_id, $selected_sections, true)) {
                $errors[] = 'Holiday Assessment mappings do not match the selected class arms.';
                return $snapshot;
            }
            $rows = $this->db->select('has.id AS setting_id, hsub.id AS setting_subject_id, hsub.max_score')
                ->from('holiday_assessment_settings has')
                ->join('holiday_assessment_subjects hsub', 'hsub.setting_id = has.id')
                ->where('has.id', (int) $mapping['setting_id'])
                ->where('hsub.id', (int) $mapping['setting_subject_id'])
                ->where('has.class_id', (int) $exam['class_id'])
                ->where('has.section_id', $section_id)
                ->where('has.session_id', (int) $exam['session_id'])
                ->where('has.term', $exam['term'])
                ->where('has.enabled', 1)
                ->where('hsub.subject_id', (int) $exam['subject_id'])
                ->limit(2)
                ->get()
                ->result_array();
            if (count($rows) !== 1 || (float) $rows[0]['max_score'] <= 0
                || abs((float) $rows[0]['max_score'] - (float) $mapping['max_score']) > 0.001) {
                $errors[] = 'A Holiday Assessment destination changed after this draft was configured. Re-save the academic setup.';
                return $snapshot;
            }
            $mapping_maximum = round((float) $mapping['max_score'], 2);
            if ($target_maximum !== null && abs($target_maximum - $mapping_maximum) > 0.001) {
                $errors[] = 'Selected class arms use different Holiday Assessment maximums.';
                return $snapshot;
            }
            $target_maximum = $mapping_maximum;
            $by_section[$section_id] = array(
                'section_id' => $section_id,
                'setting_id' => (int) $mapping['setting_id'],
                'setting_subject_id' => (int) $mapping['setting_subject_id'],
                'max_score' => $mapping_maximum,
            );
        }

        if (array_diff($selected_sections, array_keys($by_section))) {
            $errors[] = 'Every selected class arm must have one Holiday Assessment destination.';
            return $snapshot;
        }
        if ((float) $exam['target_max_score'] > 0
            && abs((float) $exam['target_max_score'] - (float) $target_maximum) > 0.001) {
            $errors[] = 'The saved Holiday Assessment maximum no longer matches its arm configuration.';
            return $snapshot;
        }

        $snapshot['target_component'] = null;
        $snapshot['target_title'] = 'Holiday Assessment';
        $snapshot['target_maximum'] = $target_maximum;
        $snapshot['holiday_mappings'] = array_values($by_section);
        return $snapshot;
    }

    protected function buildFrozenConfiguration(array $exam, array $result_snapshot, array $papers, array $class_sections)
    {
        $paper_snapshots = array();
        foreach ($papers as $paper) {
            $paper['sections'] = $this->db->where('paper_id', (int) $paper['id'])
                ->order_by('display_order', 'ASC')
                ->get('onlineexam_paper_sections')
                ->result_array();
            $paper_snapshots[] = $paper;
        }

        return array(
            'workflow_version' => self::WORKFLOW_VERSION,
            'revision' => (int) $exam['revision'],
            'academic_context' => array(
                'session_id' => (int) $exam['session_id'],
                'term' => $exam['term'],
                'class_id' => (int) $exam['class_id'],
                'section_ids' => array_map(function ($row) {
                    return (int) $row['section_id'];
                }, $class_sections),
                'subject_id' => (int) $exam['subject_id'],
                'purpose' => $exam['purpose'],
            ),
            'result' => $result_snapshot,
            'papers' => $paper_snapshots,
        );
    }

    protected function frozenPapersForAttempt($onlineexam_id, $revision)
    {
        $configuration = $this->getRevisionConfiguration($onlineexam_id, $revision);
        if (isset($configuration['papers']) && is_array($configuration['papers'])) {
            return $configuration['papers'];
        }

        $exam = $this->db->select('result_config_snapshot')
            ->where('id', (int) $onlineexam_id)
            ->get('onlineexam')
            ->row_array();
        if (!empty($exam['result_config_snapshot'])) {
            $configuration = json_decode($exam['result_config_snapshot'], true);
            if (is_array($configuration)
                && isset($configuration['revision'], $configuration['papers'])
                && (int) $configuration['revision'] === (int) $revision
                && is_array($configuration['papers'])) {
                return $configuration['papers'];
            }
        }

        // An older v2 draft may predate the full configuration snapshot. This
        // fallback is safe only for a currently-frozen matching revision.
        return $this->db->where('onlineexam_id', (int) $onlineexam_id)
            ->where('is_active', 1)
            ->order_by('display_order', 'ASC')
            ->get('onlineexam_papers')
            ->result_array();
    }

    public function getRevisionConfiguration($onlineexam_id, $revision)
    {
        $snapshot = $this->db->where('onlineexam_id', (int) $onlineexam_id)
            ->where('revision', (int) $revision)
            ->limit(1)
            ->get('onlineexam_revision_snapshots')
            ->row_array();
        if (empty($snapshot['configuration_json'])) {
            return array();
        }
        $configuration = json_decode($snapshot['configuration_json'], true);
        return is_array($configuration) ? $configuration : array();
    }

    protected function validatePaperQuestions(array $paper, array &$errors)
    {
        $sections = $this->db->where('paper_id', (int) $paper['id'])
            ->order_by('display_order', 'ASC')
            ->get('onlineexam_paper_sections')
            ->result_array();
        foreach ($sections as $section) {
            if (!in_array($section['answer_rule'], $this->answer_rules, true)) {
                $errors[] = 'Section ' . $section['title'] . ' has an invalid answer rule.';
            }
            if ($section['answer_rule'] !== 'all' && (int) $section['answer_count'] < 1) {
                $errors[] = 'Section ' . $section['title'] . ' must specify how many optional questions to answer.';
            }
        }

        $questions = $this->db->select('onlineexam_questions.*, questions.question_type AS source_question_type, questions.question AS source_question_text')
            ->from('onlineexam_questions')
            ->join('questions', 'questions.id = onlineexam_questions.question_id')
            ->where('onlineexam_questions.onlineexam_id', (int) $paper['onlineexam_id'])
            ->where('onlineexam_questions.paper_id', (int) $paper['id'])
            ->order_by('onlineexam_questions.display_order', 'ASC')
            ->get()
            ->result_array();
        if ($paper['delivery_mode'] === 'cbt' && empty($questions)) {
            $errors[] = 'CBT paper ' . $paper['title'] . ' must contain questions.';
            return;
        }
        foreach ($questions as $question) {
            if ($paper['paper_type'] === 'objective' && $question['source_question_type'] === 'long_answer') {
                $errors[] = 'Objective paper ' . $paper['title'] . ' cannot contain Theory questions.';
            }
            $this->validateAuthoredQuestionDefinition($question, $paper, $errors);
            if (!empty($question['paper_section_id'])) {
                $valid = false;
                foreach ($sections as $section) {
                    if ((int) $section['id'] === (int) $question['paper_section_id']) {
                        $valid = true;
                        break;
                    }
                }
                if (!$valid) {
                    $errors[] = 'A question in ' . $paper['title'] . ' is assigned to a section from another paper.';
                    break;
                }
            }
        }

        if (!empty($questions)) {
            $selectable_maximum = $this->calculateSelectablePaperMax($questions, $sections);
            if (abs($selectable_maximum - (float) $paper['raw_max_score']) > 0.01) {
                $errors[] = 'Paper ' . $paper['title'] . ' raw maximum must equal its selectable question total (' . round($selectable_maximum, 2) . ').';
            }
        }
    }

    protected function validateAuthoredQuestionDefinition(array $question, array $paper, array &$errors)
    {
        if (trim((string) $question['source_question_text']) === '') {
            $errors[] = 'A question in ' . $paper['title'] . ' has no question text.';
        }
        if (!in_array($question['source_question_type'], $this->localized_question_types, true)) {
            $errors[] = 'A question in ' . $paper['title'] . ' has an unsupported response type.';
            return;
        }
        if ($question['source_question_type'] === 'long_answer'
            && trim((string) $question['marking_scheme']) === '') {
            $errors[] = 'A Theory question in ' . $paper['title'] . ' has no marking scheme.';
        }
        if (empty($question['authoring_json'])) {
            return;
        }
        $definition = json_decode($question['authoring_json'], true);
        if (!is_array($definition) || (int) (isset($definition['version']) ? $definition['version'] : 0) !== 1) {
            $errors[] = 'A structured question in ' . $paper['title'] . ' has an invalid authoring definition.';
            return;
        }
        $type = isset($definition['question_type']) ? $definition['question_type'] : '';
        if (!in_array($type, $this->localized_question_types, true)) {
            $errors[] = 'A structured question in ' . $paper['title'] . ' has an unsupported response type.';
            return;
        }
        $options = isset($definition['options']) && is_array($definition['options']) ? $definition['options'] : array();
        $correct = array_key_exists('correct_answer', $definition) ? $definition['correct_answer'] : null;
        $response_schema = isset($definition['response_schema']) && is_array($definition['response_schema']) ? $definition['response_schema'] : array();
        if (empty($response_schema['input']) || $response_schema['input'] !== $type) {
            $errors[] = 'A structured question in ' . $paper['title'] . ' has an invalid response schema.';
        }
        if ($type === 'singlechoice' && (count($options) < 2 || !is_string($correct) || !array_key_exists($correct, $options))) {
            $errors[] = 'A single-choice question in ' . $paper['title'] . ' has invalid choices or answer.';
        } elseif ($type === 'multichoice') {
            if (count($options) < 2 || !is_array($correct) || empty($correct) || array_diff($correct, array_keys($options))) {
                $errors[] = 'A multiple-choice question in ' . $paper['title'] . ' has invalid choices or answers.';
            }
        } elseif ($type === 'true_false' && !in_array($correct, array('true', 'false'), true)) {
            $errors[] = 'A true/false question in ' . $paper['title'] . ' has no valid answer.';
        } elseif ($type === 'short_answer' && (!is_array($correct) || empty($correct))) {
            $errors[] = 'A short-answer question in ' . $paper['title'] . ' has no accepted answer.';
        } elseif ($type === 'numeric' && (!is_array($correct) || !isset($correct['value'], $correct['tolerance']) || !is_numeric($correct['value']) || !is_numeric($correct['tolerance']) || (float) $correct['tolerance'] < 0)) {
            $errors[] = 'A numeric question in ' . $paper['title'] . ' has an invalid answer or tolerance.';
        } elseif ($type === 'matching') {
            if (empty($options['matching_left']) || empty($options['matching_right']) || !is_array($correct)) {
                $errors[] = 'A matching question in ' . $paper['title'] . ' has invalid pairs.';
            } else {
                $left_ids = array_map(function ($item) { return isset($item['id']) ? $item['id'] : null; }, $options['matching_left']);
                $right_ids = array_map(function ($item) { return isset($item['id']) ? $item['id'] : null; }, $options['matching_right']);
                if (array_diff(array_keys($correct), $left_ids) || array_diff(array_values($correct), $right_ids) || count($correct) !== count($left_ids)) {
                    $errors[] = 'A matching question in ' . $paper['title'] . ' has an incomplete answer map.';
                }
            }
        } elseif ($type === 'ordering') {
            if (empty($options['dynamic']) || !is_array($correct) || count($options['dynamic']) !== count($correct)) {
                $errors[] = 'An ordering question in ' . $paper['title'] . ' has invalid items.';
            } else {
                $item_ids = array_map(function ($item) { return isset($item['id']) ? $item['id'] : null; }, $options['dynamic']);
                if (array_diff($correct, $item_ids) || count(array_unique($correct)) !== count($correct)) {
                    $errors[] = 'An ordering question in ' . $paper['title'] . ' has an invalid answer order.';
                }
            }
        }
        if ($type === 'long_answer'
            && trim((string) $question['marking_scheme']) === '') {
            $errors[] = 'A Theory question in ' . $paper['title'] . ' has no marking scheme.';
        }
        if (!empty($definition['passage'])) {
            $passage = $definition['passage'];
            if (!is_array($passage)
                || empty($passage['title'])
                || empty($passage['text'])
                || empty($passage['group_key'])
                || !preg_match('/^[a-z0-9][a-z0-9_-]{0,63}$/', $passage['group_key'])) {
                $errors[] = 'A grouped passage question in ' . $paper['title'] . ' has an invalid passage definition.';
            }
        }
    }

    protected function calculateSelectablePaperMax(array $questions, array $sections)
    {
        $by_section = array(0 => array());
        foreach ($sections as $section) {
            $by_section[(int) $section['id']] = array();
        }
        foreach ($questions as $question) {
            $section_id = empty($question['paper_section_id']) ? 0 : (int) $question['paper_section_id'];
            if (!isset($by_section[$section_id])) {
                $by_section[$section_id] = array();
            }
            $by_section[$section_id][] = $question;
        }

        $maximum = 0.0;
        foreach ($by_section[0] as $question) {
            $maximum += (float) $question['marks'];
        }
        foreach ($sections as $section) {
            $section_questions = $by_section[(int) $section['id']];
            if ($section['answer_rule'] === 'all') {
                foreach ($section_questions as $question) {
                    $maximum += (float) $question['marks'];
                }
                continue;
            }

            $compulsory = array();
            $optional = array();
            foreach ($section_questions as $question) {
                if ($section['answer_rule'] === 'compulsory_plus_choice' && (int) $question['is_compulsory'] === 1) {
                    $compulsory[] = (float) $question['marks'];
                } else {
                    $optional[] = (float) $question['marks'];
                }
            }
            rsort($optional, SORT_NUMERIC);
            $maximum += array_sum($compulsory) + array_sum(array_slice($optional, 0, (int) $section['answer_count']));
            if ((int) $section['answer_count'] > count($optional)) {
                $maximum = -INF;
                break;
            }
        }
        return $maximum;
    }

    protected function snapshotOptions(array $question)
    {
        $options = array();
        foreach (array('a', 'b', 'c', 'd', 'e') as $letter) {
            $value = $question['opt_' . $letter];
            if ($value !== null && trim((string) $value) !== '') {
                $options[$letter] = $value;
            }
        }

        $dynamic = $this->db->where('question_id', (int) $question['question_id'])
            ->order_by('id', 'ASC')
            ->get('question_options')
            ->result_array();
        if (!empty($dynamic)) {
            $options['dynamic'] = array();
            foreach ($dynamic as $option) {
                $options['dynamic'][] = array('id' => (int) $option['id'], 'option' => $option['option']);
            }
        }
        return $options;
    }

    protected function snapshotCorrectAnswer(array $question)
    {
        $answers = $this->db->where('question_id', (int) $question['question_id'])
            ->order_by('id', 'ASC')
            ->get('question_answers')
            ->result_array();
        if (!empty($answers)) {
            return array_map(function ($answer) {
                return (int) $answer['option_id'];
            }, $answers);
        }
        $stored = $question['correct'];
        if (is_string($stored)) {
            $decoded = json_decode($stored, true);
            if (json_last_error() === JSON_ERROR_NONE) {
                return $decoded;
            }
        }
        return $stored;
    }

    protected function calculateAttemptPaperEarned($attempt_id, array $paper)
    {
        $existing_paper = $this->db->where('attempt_id', (int) $attempt_id)
            ->where('paper_id', (int) $paper['id'])->limit(1)->get('onlineexam_attempt_papers')->row_array();
        if ((isset($paper['delivery_mode']) && $paper['delivery_mode'] === 'paper')
            || (!empty($existing_paper['completion_source']) && $existing_paper['completion_source'] === 'manual')) {
            $paper_attempt = $this->db->select('onlineexam_attempt_papers.manual_score, onlineexam_attempt_papers.manual_marking_status, onlineexam_paper_marking.raw_marks')
                ->from('onlineexam_attempt_papers')
                ->join('onlineexam_paper_marking', "onlineexam_paper_marking.attempt_paper_id = onlineexam_attempt_papers.id AND onlineexam_paper_marking.status = 'finalized'", 'left')
                ->where('onlineexam_attempt_papers.attempt_id', (int) $attempt_id)
                ->where('onlineexam_attempt_papers.paper_id', (int) $paper['id'])
                ->order_by('onlineexam_paper_marking.marking_version', 'DESC')
                ->limit(1)
                ->get()
                ->row_array();
            $recorded_score = !empty($paper_attempt) && $paper_attempt['raw_marks'] !== null
                ? $paper_attempt['raw_marks']
                : (!empty($paper_attempt) ? $paper_attempt['manual_score'] : null);
            if (empty($paper_attempt) || $paper_attempt['manual_marking_status'] !== 'finalized' || $recorded_score === null) {
                throw new RuntimeException('The manual score for paper ' . $paper['title'] . ' has not been finalized.');
            }
            $manual_score = (float) $recorded_score;
            if ($manual_score < 0 || $manual_score > (float) $paper['raw_max_score']) {
                throw new RuntimeException('The manual score for paper ' . $paper['title'] . ' is outside its allowed maximum.');
            }
            return $manual_score;
        }

        $attempt = $this->db->select('onlineexam_id, revision')
            ->where('id', (int) $attempt_id)
            ->limit(1)
            ->get('onlineexam_candidate_attempts')
            ->row_array();
        $rows = $this->db->select(
                'snap.id, snap.paper_section_id, snap.is_compulsory, snap.display_order, snap.marks, '
                . 'answer.is_answered, answer.final_mark'
            )
            ->from('onlineexam_question_snapshots snap')
            ->join(
                'onlineexam_attempt_answers answer',
                'answer.question_snapshot_id = snap.id AND answer.attempt_id = ' . $this->db->escape((int) $attempt_id),
                'left',
                false
            )
            ->where('snap.paper_id', (int) $paper['id'])
            ->where('snap.onlineexam_id', (int) $attempt['onlineexam_id'])
            ->where('snap.revision', (int) $attempt['revision'])
            ->order_by('snap.display_order', 'ASC')
            ->get()
            ->result_array();

        $sections = isset($paper['sections']) && is_array($paper['sections']) ? $paper['sections'] : array();
        $rules = array();
        foreach ($sections as $section) {
            $rules[(int) $section['id']] = $section;
        }

        $unsectioned = array();
        $grouped = array();
        foreach ($rows as $row) {
            $section_id = empty($row['paper_section_id']) ? 0 : (int) $row['paper_section_id'];
            if ($section_id === 0) {
                $unsectioned[] = $row;
            } else {
                if (!isset($grouped[$section_id])) {
                    $grouped[$section_id] = array();
                }
                $grouped[$section_id][] = $row;
            }
        }

        $earned = $this->sumFinalMarks($unsectioned);
        foreach ($rules as $section_id => $rule) {
            $section_rows = isset($grouped[$section_id]) ? $grouped[$section_id] : array();
            if ($rule['answer_rule'] === 'all') {
                $earned += $this->sumFinalMarks($section_rows);
                continue;
            }

            $compulsory = array();
            $answered_optional = array();
            foreach ($section_rows as $row) {
                if ($rule['answer_rule'] === 'compulsory_plus_choice' && (int) $row['is_compulsory'] === 1) {
                    $compulsory[] = $row;
                } elseif ((int) $row['is_answered'] === 1) {
                    $answered_optional[] = $row;
                }
            }
            // First N by the frozen display order is deterministic and avoids a
            // candidate gaining an advantage by answering every option.
            $earned += $this->sumFinalMarks($compulsory);
            $earned += $this->sumFinalMarks(array_slice($answered_optional, 0, (int) $rule['answer_count']));
        }
        return $earned;
    }

    protected function sumFinalMarks(array $rows)
    {
        $total = 0.0;
        foreach ($rows as $row) {
            $total += isset($row['final_mark']) ? (float) $row['final_mark'] : 0.0;
        }
        return $total;
    }

    protected function getResultProfile($onlineexam_id, $adapter)
    {
        return $this->db->where('onlineexam_id', (int) $onlineexam_id)
            ->where('adapter', $adapter)
            ->where('is_active', 1)
            ->limit(1)
            ->get('onlineexam_result_profiles')
            ->row_array();
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
            'entity_id' => (string) $entity_id,
            'before_json' => $before === null ? null : json_encode($before),
            'after_json' => $after === null ? null : json_encode($after),
            'ip_address' => isset($this->input) ? $this->input->ip_address() : null,
            'created_at' => date('Y-m-d H:i:s'),
        ));
    }
}
