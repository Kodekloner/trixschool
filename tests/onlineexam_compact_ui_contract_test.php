<?php

function compact_ui_assert($condition, $message)
{
    if (!$condition) {
        fwrite(STDERR, $message . PHP_EOL);
        exit(1);
    }
}

function compact_ui_source($relative_path)
{
    $source = file_get_contents(__DIR__ . '/../' . $relative_path);
    compact_ui_assert($source !== false, 'Unable to read ' . $relative_path . '.');
    return $source;
}

$admin_list = compact_ui_source('application/views/admin/onlineexam/index.php');
preg_match_all('/<th\b/i', $admin_list, $admin_headers);
compact_ui_assert(count($admin_headers[0]) === 9, 'The administration assessment list must keep nine aligned columns.');
compact_ui_assert(strpos($admin_list, 'onlineexam-scroll') !== false, 'The administration assessment list needs a contained horizontal scroller.');
compact_ui_assert(strpos($admin_list, 'printpaper') === false, 'Print-paper actions must stay retired.');
foreach (array('myModal', 'addQuestionModal', 'rankModal') as $retired_modal) {
    compact_ui_assert(strpos($admin_list, 'id="' . $retired_modal . '"') === false, 'A retired legacy modal remains on the assessment list: ' . $retired_modal . '.');
}

$assessment_form = compact_ui_source('application/views/admin/onlineexam/workflow.php');
compact_ui_assert(strpos($assessment_form, 'name="purpose"') !== false, 'The assessment purpose selector is missing.');
compact_ui_assert(strpos($assessment_form, 'name="target_component"') !== false, 'CA and Midterm assessments need the configured result-component selector.');
compact_ui_assert(strpos($assessment_form, 'name="attempts"') === false, 'The one-official-attempt rule must not be exposed as a form field.');
compact_ui_assert(strpos($assessment_form, '>Destination<') === false, 'Result adapters must not be exposed as a Destination field.');
compact_ui_assert(strpos($assessment_form, 'assessment-rule-options') !== false, 'Assessment checkboxes need the responsive alignment wrapper.');
compact_ui_assert(strpos($assessment_form, 'admin/onlineexam/academicclasses') !== false, 'Changing sessions must refresh the teacher\'s assigned classes.');
$abort_position = strpos($assessment_form, 'configurationRequest.abort()');
$incomplete_selection_position = strpos($assessment_form, 'if (!classId)');
compact_ui_assert($abort_position !== false && $incomplete_selection_position !== false && $abort_position < $incomplete_selection_position, 'Clearing a required assessment selection must abort any stale configuration request first.');

$builder = compact_ui_source('application/views/admin/onlineexam/builder.php');
compact_ui_assert(strpos($builder, 'name="delivery_mode" value="cbt"') !== false, 'New papers must be fixed to online CBT delivery.');
compact_ui_assert(strpos($builder, '<select name="delivery_mode"') === false, 'Paper/manual/hybrid delivery choices must not be exposed.');
compact_ui_assert(strpos($builder, 'name="question_level"') === false, 'Difficulty must not be part of compact assessment authoring.');
compact_ui_assert(strpos($builder, 'printpaper') === false, 'The builder must not expose print-paper actions.');
compact_ui_assert(strpos($builder, 'paper-field-guide') === false, 'Training-only paper field descriptions must not clutter the builder.');
compact_ui_assert(strpos($builder, 'name="raw_max_score"') === false, 'Paper maximums must come from the selected assessment component.');
compact_ui_assert(strpos($builder, 'paper-form-grid') !== false, 'Paper fields need the full-width responsive grid.');
compact_ui_assert(strpos($builder, 'workflow-edit-authored') !== false, 'Assigned structured questions need in-place edit controls.');
compact_ui_assert(strpos($builder, 'question-bank-filter-grid') !== false, 'Question-bank filters need a responsive grid.');
compact_ui_assert(strpos($builder, 'builder_question_status') !== false && strpos($builder, "aria-busy', 'true") !== false, 'Question-bank loading needs a visible accessible state.');
compact_ui_assert(strpos($builder, 'paper-section-item') !== false && strpos($builder, 'paper-section-actions') !== false, 'Paper-section labels and actions need a non-overlapping flex layout.');
compact_ui_assert(strpos($builder, '$can_view_roster') !== false && strpos($builder, '$can_view_operations') !== false, 'Builder navigation must respect destination-page privileges.');
compact_ui_assert(substr_count($builder, '!empty($compact_supported)') >= 2, 'Historical builder views must not link into retired roster or operations routes.');
compact_ui_assert(strpos($builder, 'name="onlineexam_workflow_token"') !== false && strpos($builder, 'Teacher selects the outcome') !== false, 'British outcome setup must be protected and understandable in the builder.');

$question_assignment = compact_ui_source('application/views/admin/onlineexam/_searchQuestionByExamID.php');
compact_ui_assert(substr_count($question_assignment, 'question-type-cell') >= 2, 'The question type column needs an explicit width class on its header and cells.');
compact_ui_assert(substr_count($question_assignment, 'question-required-cell') >= 2, 'The Required column needs an explicit width class on its header and cells.');
compact_ui_assert(strpos($question_assignment, 'aria-label="Question assignment table"') !== false, 'The scrollable question table needs an accessible region label.');

$admin_controller = compact_ui_source('application/controllers/admin/Onlineexam.php');
compact_ui_assert(strpos($admin_controller, "'exam'           => 'Terminal Examination (Exam)'") !== false, 'Terminal Examination must be listed among the assessment purposes.');
compact_ui_assert(strpos($admin_controller, "'british'        => 'British Assessment'") !== false, 'British Assessment must be listed among the assessment purposes.');
compact_ui_assert(
    strpos($admin_controller, "'native_question_types' => \$this->localizedQuestionTypes()") !== false,
    'Grouped passages must remain available in structured assessment authoring.'
);

$admin_styles = compact_ui_source('application/views/admin/onlineexam/_assessment_styles.php');
compact_ui_assert(strpos($admin_styles, 'overflow-x: auto') !== false, 'Online Examination tables need horizontal overflow support.');
compact_ui_assert(strpos($admin_styles, '.assessment-toolbar') !== false && strpos($admin_styles, 'flex-wrap: nowrap') !== false, 'Assessment actions must remain on one scrollable line.');
compact_ui_assert(strpos($admin_styles, '@media (max-width: 991px)') !== false, 'Administration views need an explicit tablet/mobile layout.');
compact_ui_assert(strpos($admin_styles, '.question-assignment-table .question-type-cell') !== false, 'Question types need a protected table column width.');
compact_ui_assert(strpos($admin_styles, '.question-assignment-table .question-required-cell') !== false, 'Required controls need a protected table column width.');
compact_ui_assert(strpos($admin_styles, 'table-layout: auto') !== false, 'The question table must let explicit column minimums determine its safe width.');
compact_ui_assert(strpos($admin_styles, '@media (max-width: 1199px)') !== false, 'Paper and section forms need a responsive laptop/tablet breakpoint.');
compact_ui_assert(strpos($admin_styles, 'grid-template-columns: 1fr') !== false, 'Administration forms need a single-column phone layout.');
compact_ui_assert(
    !preg_match('/assessment-meta-table[^}]*display\s*:\s*block|paper-summary-table[^}]*display\s*:\s*block/s', $admin_styles),
    'Assessment summary tables must stay horizontal instead of stacking into tall mobile cards.'
);

$analysis = compact_ui_source('application/views/admin/onlineexam/analysis_v2.php');
compact_ui_assert(strpos($analysis, 'onlineexam-analysis-page') !== false, 'Assessment analysis must use the scoped responsive layout.');
compact_ui_assert(substr_count($analysis, 'onlineexam-scroll') >= 2, 'Both assessment-analysis tables need horizontal scrolling on narrow screens.');

$review = compact_ui_source('application/views/admin/onlineexam/review.php');
compact_ui_assert(strpos($review, 'Online Examination Review') !== false && strpos($review, 'Select Criteria') !== false, 'The simpler class review must replace the retired operations dashboard.');
compact_ui_assert(strpos($review, 'name="component"') !== false, 'The review criteria must include a CA / Exam component.');
compact_ui_assert(strpos($review, "html_escape(\$column['assessment'])") === false && strpos($review, "html_escape(\$column['title'])") === false, 'Review headers must display only the subject name.');
compact_ui_assert(strpos($review, '.review-cell { display:inline-block; width:auto;') !== false, 'Review result buttons must size to their content.');
compact_ui_assert(strpos($review, 'review-student') !== false && strpos($review, 'position:sticky') !== false, 'The review matrix needs a stable student column while papers scroll.');
compact_ui_assert(strpos($review, 'table-responsive onlineexam-scroll review-scroll') !== false, 'The class review needs a contained responsive table.');
compact_ui_assert(strpos($review, 'Incident register') === false && strpos($review, 'Automatic result-posting ledger') === false, 'Internal incident and posting ledgers must not clutter the class review.');
$review_cell = compact_ui_source('application/views/admin/onlineexam/_review_cell.php');
compact_ui_assert(strpos($review_cell, 'Reschedule this paper') !== false && strpos($review_cell, 'Record score from a supervised paper exam') !== false, 'Missed-paper recovery actions must be available from the review cell.');

$marking = compact_ui_source('application/views/admin/onlineexam/marking_v2.php');
compact_ui_assert(strpos($marking, 'class="theory-response"') !== false, 'Long Theory responses need protected wrapping and overflow.');
compact_ui_assert(strpos($marking, '$can_edit_marking') !== false, 'Read-only reviewers must not receive marking mutation controls.');
compact_ui_assert(strpos($marking, 'operationBritishOutcome') !== false && strpos($marking, 'name="outcome_value"') !== false, 'Teacher-selected British outcomes must be available from Answer and Outcome Review.');

$roster = compact_ui_source('application/views/admin/onlineexam/assign.php');
compact_ui_assert(strpos($roster, '<thead>') !== false && strpos($roster, '$roster_column_count') !== false, 'The candidate roster needs a semantic header and a correct dynamic empty-state colspan.');

$student_list = compact_ui_source('application/views/user/onlineexam/onlineexamlist.php');
compact_ui_assert(strpos($student_list, "'exam'                  => 'Terminal Examination'") !== false, 'The student assessment list needs a clear Exam purpose label.');
compact_ui_assert(strpos($student_list, "'british'               => 'British Assessment'") !== false, 'The student assessment list needs a clear British purpose label.');
compact_ui_assert(strpos($student_list, 'assessment-table-wrap') !== false, 'The student assessment list needs a contained scroller.');
compact_ui_assert(strpos($student_list, 'min-width:860px') !== false, 'The student assessment list needs a stable desktop table width before its phone-card override.');
compact_ui_assert(strpos($student_list, 'content:attr(data-label)') !== false && strpos($student_list, 'min-width:0') !== false, 'The student assessment list must become labelled cards on phones.');

$student_view = compact_ui_source('application/views/user/onlineexam/view_v2.php');
compact_ui_assert(strpos($student_view, "'exam'                  => 'Terminal Examination'") !== false, 'The student assessment details need a clear Exam purpose label.');
compact_ui_assert(strpos($student_view, "'british'               => 'British Assessment'") !== false, 'The student assessment details need a clear British purpose label.');
compact_ui_assert(strpos($student_view, 'assessment-parts-wrap') !== false, 'Student paper status needs a responsive table wrapper.');
compact_ui_assert(strpos($student_view, 'queueSave') !== false && strpos($student_view, 'flushQueue') !== false, 'Weak-network answer retry must remain enabled.');
compact_ui_assert(strpos($student_view, 'remaining_seconds') !== false, 'The student countdown must use server-provided remaining time.');
compact_ui_assert(strpos($student_view, 'displayDeadline - new Date().getTime()') !== false, 'The countdown must use numeric elapsed wall time even when the portal DateJS overrides Date.now().');
compact_ui_assert(strpos($student_view, 'final_answers: JSON.stringify(finalAnswers)') !== false, 'Paper submission must send one atomic final-answer packet.');
compact_ui_assert(strpos($student_view, 'terminal_submission_mismatch') === false, 'Server error codes must not be hard-coded into the student interface.');
compact_ui_assert(strpos($student_view, 'clearAttemptQueue(attemptId)') !== false && strpos($student_view, '.done(function (data)') !== false, 'The browser queue may be cleared only after a successful terminal response.');
compact_ui_assert(strpos($student_view, 'height:100dvh') !== false && strpos($student_view, 'safe-area-inset-bottom') !== false, 'The examination modal must account for dynamic mobile viewports and safe areas.');
compact_ui_assert(strpos($student_view, 'initialiseQuestionNavigation') !== false && strpos($student_view, 'v2-nav-answered') !== false, 'Long papers need current and answered question navigation.');
compact_ui_assert(strpos($student_view, 'refreshOrderingChoices') !== false, 'Ordering questions must prevent duplicate choices before submission.');
compact_ui_assert(strpos($student_view, "$(document).on('mousedown touchstart', '#v2PaperContainer .v2-question'") !== false, 'Touch and pointer interaction must keep Previous/Next navigation aligned with the visible question.');
compact_ui_assert(strpos($student_view, '.v2-order-control[aria-invalid="true"]') !== false, 'Manual submission must focus and block a recovered duplicate ordering answer.');
compact_ui_assert(strpos($student_view, "toggleClass('text-danger', answered > maximum)") !== false, 'A section at its valid answer limit must not be styled as an error.');

$student_paper = compact_ui_source('application/views/user/onlineexam/_paper_v2.php');
foreach (array('file_upload', 'data-has-attachment', 'type="file"') as $retired_response_marker) {
    compact_ui_assert(strpos($student_paper, $retired_response_marker) === false, 'A retired file-response marker remains in the CBT paper: ' . $retired_response_marker . '.');
}
compact_ui_assert(strpos($student_paper, "elseif (\$type === 'long_answer')") !== false, 'Theory answers must use an explicit long-answer branch.');
compact_ui_assert(strpos($student_paper, 'v2-unsupported-question') !== false, 'An unexpected historical response type must render a non-interactive warning.');
compact_ui_assert(strpos($student_paper, 'v2-question-navigation') !== false && strpos($student_paper, 'v2-paper-step-controls') !== false, 'The candidate paper needs numbered and previous/next navigation.');
compact_ui_assert(strpos($student_paper, 'v2-choice-lock-notice') !== false, 'Answer-any-N locks need a visible explanation.');

$question_bank = compact_ui_source('application/views/admin/question/question.php');
compact_ui_assert(strpos($question_bank, 'question-bank-table-scroll') !== false, 'Question Bank needs a horizontal table scroller.');
compact_ui_assert(strpos($question_bank, "hasPrivilege('question_bank', 'can_delete')") !== false, 'Bulk Question Bank deletion must be privilege-gated.');
compact_ui_assert(strpos($question_bank, '@media (max-width: 767px)') !== false, 'Question Bank needs an explicit mobile layout.');

foreach (array($admin_list, $assessment_form, $builder, $analysis, $student_list, $student_view, $student_paper) as $active_view) {
    compact_ui_assert(stripos($active_view, 'Workflow v2') === false, 'Technical version labels must not be shown to users.');
    compact_ui_assert(stripos($active_view, 'Nigerian') === false, 'Country branding must not be shown in Online Examination views.');
}

echo "onlineexam compact UI contract tests passed" . PHP_EOL;
