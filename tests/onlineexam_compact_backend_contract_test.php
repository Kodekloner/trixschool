<?php

function compact_backend_assert($condition, $message)
{
    if (!$condition) {
        fwrite(STDERR, $message . PHP_EOL);
        exit(1);
    }
}

function compact_backend_source($relative_path)
{
    $source = file_get_contents(__DIR__ . '/../' . $relative_path);
    compact_backend_assert($source !== false, 'Unable to read ' . $relative_path . '.');
    return $source;
}

$workflow = compact_backend_source('application/models/Onlineexamworkflow_model.php');
compact_backend_assert(strpos($workflow, "protected \$purposes = array('ca', 'midterm', 'exam', 'holiday', 'kindergarten', 'british');") !== false, 'Compact purposes, including Exam and British Assessment, must remain authoritative.');
compact_backend_assert(strpos($workflow, "if (\$purpose === 'exam')") !== false, 'Exam must resolve through its own result-destination branch.');
compact_backend_assert(strpos($workflow, "'exam' => 'standard_component'") !== false, 'Exam must use the existing standard score component adapter.');
compact_backend_assert(strpos($workflow, "'british' => 'british_outcome'") !== false, 'British Assessment must use the existing qualitative result adapter.');
compact_backend_assert(strpos($workflow, "protected \$paper_types = array('objective', 'theory');") !== false, 'Only Objective and Theory paper types may be published.');
compact_backend_assert(strpos($workflow, "protected \$delivery_modes = array('cbt');") !== false, 'Only CBT delivery may be published.');
compact_backend_assert(strpos($workflow, 'validateHolidayResultTarget') !== false, 'Holiday destinations must be revalidated before freezing.');
compact_backend_assert(strpos($workflow, 'validateLegacyQuestionBankAnswer') !== false, 'Traditional Question Bank answer keys must be validated before publication.');
compact_backend_assert(strpos($workflow, "array('[', '{', '\"')") !== false, 'Legacy True/False answer keys must remain strings when snapshots are created.');

$attempt = compact_backend_source('application/models/Onlineexamattempt_model.php');
compact_backend_assert(strpos($attempt, "e.purpose IN ('ca','midterm','exam')") !== false, 'Completed Exam attempts must be included in automatic result reconciliation.');
compact_backend_assert(strpos($attempt, "e.purpose = 'british' AND e.result_adapter = 'british_outcome'") !== false, 'Completed British attempts must be included in automatic result reconciliation.');
compact_backend_assert(strpos($attempt, "if (\$paper->delivery_mode !== 'cbt')") !== false, 'Candidate paper start must enforce CBT server-side.');
compact_backend_assert(strpos($attempt, 'if ($attempt_count >= 1)') !== false, 'Only one non-voided official attempt may exist.');
compact_backend_assert(strpos($attempt, 'Answer attachments are no longer supported') !== false, 'Model-level attachment creation must be retired.');
compact_backend_assert(strpos($attempt, 'FINAL_SUBMISSION_GRACE_SECONDS') !== false, 'Timeout workers and final submissions need one bounded server grace constant.');
compact_backend_assert(strpos($attempt, 'terminalSubmissionAcknowledgesFinalAnswers') !== false, 'A lost-response retry must verify the final answer state before browser storage is cleared.');
compact_backend_assert(strpos($attempt, '$stored_sequence <= $client_sequence') !== false, 'A lost-response retry with identical content must remain idempotent even if the browser allocated a newer sequence.');
compact_backend_assert(strpos($attempt, 'preserve_local_queue') !== false, 'Rejected final answers must tell the browser to preserve its recovery queue.');
compact_backend_assert(strpos($attempt, '$request_received_at') !== false && strpos($attempt, 'trustedRequestTime') !== false, 'Server request-arrival time must survive database lock waits.');
compact_backend_assert(strpos($attempt, "'SELECT id, candidate_status FROM `onlineexam_students`") !== false, 'Candidate start must lock and revalidate the roster row.');
compact_backend_assert(strpos($attempt, 'isSupportedAssessmentContext') !== false && strpos($attempt, 'compactPaperQuestionsSupported') !== false, 'The whole frozen assessment must satisfy the compact CBT contract.');
compact_backend_assert(strpos($attempt, 'attachment_path));') === false, 'Retired attachment metadata must not count as an answered CBT response.');
$candidate_question_start = strpos($attempt, 'public function getPaperQuestions(');
$candidate_question_end = strpos($attempt, 'public function saveAnswer(', $candidate_question_start);
$candidate_question_source = substr($attempt, $candidate_question_start, $candidate_question_end - $candidate_question_start);
compact_backend_assert(strpos($candidate_question_source, 'correct_answer_json') === false, 'The candidate question query must never expose the frozen answer key.');

$sync = compact_backend_source('application/models/Onlineexamresultsync_model.php');
compact_backend_assert(strpos($sync, 'syncHolidayAssessment') !== false, 'Holiday Assessment needs a transactional result adapter.');
compact_backend_assert(strpos($sync, "\$row['score_origin'] === 'placeholder'") !== false, 'Only explicitly-labelled Holiday placeholders may be claimed automatically.');
compact_backend_assert(strpos($sync, 'previous_metadata_json') !== false, 'Holiday reversals require prior provenance metadata.');
compact_backend_assert(substr_count($sync, "if (\$ledger['idempotent'])") >= 4, 'Every active result adapter must handle duplicate synchronization explicitly.');
compact_backend_assert(strpos($sync, 'The previously synchronized score was changed or moved outside this assessment.') !== false, 'Duplicate Standard posting must detect an externally changed destination.');
compact_backend_assert(strpos($sync, 'The previously synchronized British outcome was changed or moved outside this assessment.') !== false, 'Duplicate British posting must detect an externally changed destination.');
compact_backend_assert(strpos($sync, "'outcome_value' => \$outcome") !== false, 'Threshold-based British outcomes must be retained on the completed attempt for review and feedback.');
compact_backend_assert(strpos($sync, 'The previously synchronized Kindergarten outcome was changed or moved outside this assessment.') !== false, 'Duplicate Kindergarten posting must detect an externally changed destination.');

$admin = compact_backend_source('application/controllers/admin/Onlineexam.php');
compact_backend_assert(strpos($admin, 'in_list[ca,midterm,exam,holiday,kindergarten,british]') !== false, 'Admin assessment saves must accept Exam and British purposes.');
compact_backend_assert(strpos($admin, "'exam'           => 'Terminal Examination (Exam)'") !== false, 'The assessment form must offer Terminal Examination as a purpose.');
compact_backend_assert(strpos($admin, "'british'        => 'British Assessment'") !== false, 'The assessment form must offer British Assessment as a purpose.');
compact_backend_assert(strpos($admin, 'ensureDefaultBritishOutcomeProfile') !== false, 'A new British assessment needs a safe teacher-selection default.');
compact_backend_assert(strpos($admin, 'in_list[objective,theory]') !== false, 'Admin paper saves must enforce Objective/Theory.');
compact_backend_assert(strpos($admin, 'in_list[cbt]') !== false, 'Admin paper saves must enforce CBT.');
compact_backend_assert(strpos($admin, 'retiredOnlineexamAction') !== false, 'Legacy admin mutations must terminate explicitly.');
compact_backend_assert(strpos($admin, 'compactAssessmentIsExecutable') !== false, 'Historical workflow-v2 variants must remain read-only on admin routes.');
compact_backend_assert(strpos($admin, '$this->question_model->canAccessQuestion($question_id, $exam->session_id)') !== false, 'Question assignment must reject crafted Question Bank identifiers outside the exact assessment-session scope.');
compact_backend_assert(strpos($admin, 'workflowTeacherHasAssignment(') !== false
    && strpos($admin, 'canManageAllSections') !== false,
    'Teacher Question Bank searches and assessment changes must use exact subject/session/class-arm assignments.');
$question_search_start = strpos($admin, 'public function searchQuestionByExamID()');
$question_search_end = strpos($admin, 'public function rankgenerate()', $question_search_start);
$question_search_source = substr($admin, $question_search_start, $question_search_end - $question_search_start);
compact_backend_assert(strpos($question_search_source, 'workflowQuestionBankScope') !== false
    && strpos($question_search_source, "['view_section_ids']") !== false
    && strpos($question_search_source, "['can_manage_all']") !== false,
    'Question Bank AJAX reads must use partial view scope while assignment controls require every assessment arm.');
compact_backend_assert(strpos($question_search_source, 'workflowTeacherHasAssignment(') === false,
    'A teacher who can view at least one assessment arm must not be rejected by the Question Bank AJAX reader.');
compact_backend_assert(strpos($question_search_source, 'set_status_header(403)') !== false
    && strpos($question_search_source, 'You are not assigned to any class arm in this assessment.') !== false,
    'An out-of-scope Question Bank AJAX request must return an explicit JSON authorization error.');
foreach (array('paperSave', 'paperDelete', 'paperSectionSave', 'paperSectionDelete',
    'workflowQuestionSave', 'workflowQuestionAuthor', 'workflowQuestionDelete', 'lifecycle') as $full_scope_action) {
    $action_start = strpos($admin, 'public function ' . $full_scope_action . '(');
    $action_end = strpos($admin, "\n    public function ", $action_start + 1);
    $action_source = substr($admin, $action_start, $action_end - $action_start);
    compact_backend_assert($action_start !== false && strpos($action_source, 'workflowTeacherHasAssignment(') !== false,
        $full_scope_action . ' must continue requiring subject ownership of every assessment arm.');
}
compact_backend_assert(strpos($admin, 'This question belongs to a class arm that is not selected for the assessment.') !== false, 'Question assignment must reject crafted identifiers from an unselected class arm.');
compact_backend_assert(strpos($admin, "\$mark_scope = \$this->workflowOperationScope(\$exam, 'mark');") !== false
    && substr_count($admin, "->where_in('ss.section_id', \$mark_scope['section_ids'])") >= 2,
    'British/result-conflict actions must enforce the current marker\'s exact class-arm scope.');
compact_backend_assert(strpos($admin, "\$this->academicaccess_model->canMark(") !== false,
    'British teacher-selected outcomes must recheck the candidate arm before mutation.');
compact_backend_assert(strpos($admin, 'private function workflowPublishReadiness(') !== false && substr_count($admin, "['publish_readiness'] = \$this->workflowPublishReadiness") === 2, 'Question assignment and removal must return authoritative live publish readiness.');
$list_start = strpos($admin, 'public function getexamlist()');
$list_end = strpos($admin, 'public function workflow(', $list_start);
$list_source = substr($admin, $list_start, $list_end - $list_start);
compact_backend_assert(substr_count($list_source, '$row[]') === 9, 'Online Examination list rows must match the nine-column compact table.');

$student = compact_backend_source('application/controllers/user/Onlineexam.php');
compact_backend_assert(strpos($student, "show_error('This historical Online Examination is read-only") !== false, 'Students must not render the legacy attempt view.');
compact_backend_assert(strpos($student, "show_error('Legacy Online Examination printing is retired.'") !== false, 'Legacy student printing must be retired.');
compact_backend_assert(strpos($student, 'final_answers') !== false && strpos($student, 'requestReceivedAt') !== false, 'Student submission must pass the bounded atomic answer packet and trusted arrival time.');

$model = compact_backend_source('application/models/Onlineexam_model.php');
$academic_access = compact_backend_source('application/models/Academicaccess_model.php');
compact_backend_assert(strpos($model, '$copied = $reference_count > 1;') !== false, 'Authored question edits need copy-on-write only for shared sources.');
compact_backend_assert(strpos($model, 'LIMIT 1 FOR UPDATE') !== false, 'Question assignment and candidate synchronization need transactional row locks.');
compact_backend_assert(strpos($model, "SELECT * FROM `onlineexam` WHERE `id` =") !== false && strpos($model, '!empty($locked_exam->deleted_at)') !== false, 'Roster saves must lock the assessment and reject a concurrently archived roster.');
compact_backend_assert(
    strpos($model, 'onlineexam.exam,onlineexam.purpose,total_ques,onlineexam.exam_from,onlineexam.exam_to,onlineexam.duration,onlineexam.lifecycle_status,onlineexam.feedback_status," "') !== false,
    'Server-side list ordering must match the compact table\'s nine visible columns.'
);
compact_backend_assert(strpos($model, 'onlineexam.workflow_version = 2 AND') !== false
    && strpos($model, 'class_teacher access_ct') !== false
    && strpos($model, 'subjectAssignmentExistsSql') !== false
    && strpos($academic_access, 'access_ts.subject_id=') !== false
    && strpos($academic_access, 'subjecttables') !== false
    && strpos($academic_access, 'subject_timetable') !== false,
    'Assessment lists must use the class-teacher/subject-teacher scope union and hide legacy records.');
compact_backend_assert(strpos($model, 'public function getWorkflowClassChoices') !== false, 'Teacher assessment creation needs session-scoped class choices.');
compact_backend_assert(strpos($model, "'exam' => 'term'") !== false, 'Exam must reserve a term assessment slot.');
compact_backend_assert(strpos($model, "onlineexam.purpose IN ('ca','midterm','exam')") !== false, 'Published Exam assessments must be visible to assigned students.');
compact_backend_assert(strpos($model, "onlineexam.purpose='british' AND onlineexam.result_adapter='british_outcome'") !== false, 'Published British assessments must be visible to assigned students.');

$question_picker = compact_backend_source('application/models/Onlineexamquestion_model.php');
compact_backend_assert(strpos($question_picker, "['allowed_section_ids']") !== false, 'Assessment Question Bank searches must apply teacher section scope before pagination.');
compact_backend_assert(strpos($question_picker, "['question_session_id']") !== false
    && strpos($question_picker, "['question_term']") !== false,
    'Assessment Question Bank searches must enforce the assessment session and term before pagination.');

$operations = compact_backend_source('application/models/Onlineexamoperations_model.php');
compact_backend_assert(strpos($operations, "!empty(\$scope['access_mode'])") !== false
    && strpos($operations, "->sectionIdsFor(") !== false,
    'Operational model reads and writes must independently enforce the shared academic capability scope.');

foreach (array($attempt, $sync, $admin, $student, $model, $workflow, $question_picker) as $active_backend) {
    compact_backend_assert(strpos($active_backend, "case 'unlinked_practice'") === false, 'The removed internal-only result adapter must not remain executable.');
}

$migration = compact_backend_source('application/migrations/135_compact_online_examination.php');
foreach (array('onlineexam_holiday_mappings', 'score_origin', 'source_onlineexam_id', 'source_attempt_id', 'source_sync_id', 'previous_metadata_json', 'applied_metadata_json') as $required) {
    compact_backend_assert(strpos($migration, $required) !== false, 'Migration 135 is missing ' . $required . '.');
}

$review = compact_backend_source('application/models/Onlineexamreview_model.php');
compact_backend_assert(strpos($review, "'term' => array('ca', 'exam', 'kindergarten', 'british')") !== false, 'Exam and British results must appear under Term in Online Examination Review.');
compact_backend_assert(strpos($review, 'syncCompletedAttempt') !== false && strpos($review, "'deleted_at'") !== false, 'Completed removal must reconcile results before soft-archiving the assessment.');
compact_backend_assert(strpos($review, "->where('s.is_active', 'yes')") !== false, 'Review rows must come from the authoritative active enrollment roster.');
compact_backend_assert(strpos($admin, 'requireWorkflowCsrf') !== false && strpos($admin, 'allow_assign_candidate') !== false, 'Review writes must retain CSRF and explicit assignment permissions.');

$slot_migration = compact_backend_source('application/migrations/138_onlineexam_single_subject_slots.php');
compact_backend_assert(strpos($slot_migration, "e.purpose IN ('ca','exam','kindergarten','british')") !== false, 'Exam and British assessments must backfill into the term academic-slot namespace.');

$all_school_migration = compact_backend_source('docs/all_school_database_migrations.sql');
compact_backend_assert(strpos($all_school_migration, "e.purpose IN (''ca'',''exam'',''kindergarten'',''british'')") !== false, 'The all-school migration must backfill Exam and British academic slots.');
compact_backend_assert(strpos($all_school_migration, 'question_139_contexts') !== false
    && strpos($all_school_migration, 'question_academic_scope_idx') !== false,
    'The all-school migration must include session-scoped Question Bank migration 139.');

echo "onlineexam compact backend contract tests passed" . PHP_EOL;
