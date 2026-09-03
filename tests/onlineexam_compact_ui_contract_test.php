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

$builder = compact_ui_source('application/views/admin/onlineexam/builder.php');
compact_ui_assert(strpos($builder, 'name="delivery_mode" value="cbt"') !== false, 'New papers must be fixed to online CBT delivery.');
compact_ui_assert(strpos($builder, '<select name="delivery_mode"') === false, 'Paper/manual/hybrid delivery choices must not be exposed.');
compact_ui_assert(strpos($builder, 'name="question_level"') === false, 'Difficulty must not be part of compact assessment authoring.');
compact_ui_assert(strpos($builder, 'printpaper') === false, 'The builder must not expose print-paper actions.');
compact_ui_assert(strpos($builder, 'paper-field-guide') !== false, 'Paper fields need concise explanations for staff.');
compact_ui_assert(strpos($builder, 'paper-form-grid') !== false, 'Paper fields need the full-width responsive grid.');
compact_ui_assert(strpos($builder, 'workflow-edit-authored') !== false, 'Assigned structured questions need in-place edit controls.');

$admin_controller = compact_ui_source('application/controllers/admin/Onlineexam.php');
compact_ui_assert(
    strpos($admin_controller, "'native_question_types' => \$this->localizedQuestionTypes()") !== false,
    'Grouped passages must remain available in structured assessment authoring.'
);

$admin_styles = compact_ui_source('application/views/admin/onlineexam/_assessment_styles.php');
compact_ui_assert(strpos($admin_styles, 'overflow-x: auto') !== false, 'Online Examination tables need horizontal overflow support.');
compact_ui_assert(strpos($admin_styles, '.assessment-toolbar') !== false && strpos($admin_styles, 'flex-wrap: nowrap') !== false, 'Assessment actions must remain on one scrollable line.');
compact_ui_assert(strpos($admin_styles, '@media (max-width: 991px)') !== false, 'Administration views need an explicit tablet/mobile layout.');
compact_ui_assert(
    !preg_match('/assessment-meta-table[^}]*display\s*:\s*block|paper-summary-table[^}]*display\s*:\s*block/s', $admin_styles),
    'Assessment summary tables must stay horizontal instead of stacking into tall mobile cards.'
);

$analysis = compact_ui_source('application/views/admin/onlineexam/analysis_v2.php');
compact_ui_assert(strpos($analysis, 'onlineexam-analysis-page') !== false, 'Assessment analysis must use the scoped responsive layout.');
compact_ui_assert(substr_count($analysis, 'onlineexam-scroll') >= 2, 'Both assessment-analysis tables need horizontal scrolling on narrow screens.');

$student_list = compact_ui_source('application/views/user/onlineexam/onlineexamlist.php');
compact_ui_assert(strpos($student_list, 'assessment-table-wrap') !== false, 'The student assessment list needs a contained scroller.');
compact_ui_assert(strpos($student_list, 'min-width:860px') !== false, 'The student assessment list must stay horizontal on small screens.');

$student_view = compact_ui_source('application/views/user/onlineexam/view_v2.php');
compact_ui_assert(strpos($student_view, 'assessment-parts-wrap') !== false, 'Student paper status needs a responsive table wrapper.');
compact_ui_assert(strpos($student_view, 'queueSave') !== false && strpos($student_view, 'flushQueue') !== false, 'Weak-network answer retry must remain enabled.');
compact_ui_assert(strpos($student_view, 'remaining_seconds') !== false, 'The student countdown must use server-provided remaining time.');
compact_ui_assert(strpos($student_view, 'displayDeadline - Date.now()') !== false, 'The countdown must recover elapsed time after a backgrounded or suspended browser tab.');
compact_ui_assert(strpos($student_view, 'final_answers: JSON.stringify(finalAnswers)') !== false, 'Paper submission must send one atomic final-answer packet.');
compact_ui_assert(strpos($student_view, 'terminal_submission_mismatch') === false, 'Server error codes must not be hard-coded into the student interface.');
compact_ui_assert(strpos($student_view, 'clearAttemptQueue(attemptId)') !== false && strpos($student_view, '.done(function (data)') !== false, 'The browser queue may be cleared only after a successful terminal response.');

$student_paper = compact_ui_source('application/views/user/onlineexam/_paper_v2.php');
foreach (array('file_upload', 'data-has-attachment', 'type="file"') as $retired_response_marker) {
    compact_ui_assert(strpos($student_paper, $retired_response_marker) === false, 'A retired file-response marker remains in the CBT paper: ' . $retired_response_marker . '.');
}
compact_ui_assert(strpos($student_paper, "elseif (\$type === 'long_answer')") !== false, 'Theory answers must use an explicit long-answer branch.');
compact_ui_assert(strpos($student_paper, 'v2-unsupported-question') !== false, 'An unexpected historical response type must render a non-interactive warning.');

$question_bank = compact_ui_source('application/views/admin/question/question.php');
compact_ui_assert(strpos($question_bank, 'question-bank-table-scroll') !== false, 'Question Bank needs a horizontal table scroller.');
compact_ui_assert(strpos($question_bank, "hasPrivilege('question_bank', 'can_delete')") !== false, 'Bulk Question Bank deletion must be privilege-gated.');
compact_ui_assert(strpos($question_bank, '@media (max-width: 767px)') !== false, 'Question Bank needs an explicit mobile layout.');

foreach (array($admin_list, $assessment_form, $builder, $analysis, $student_list, $student_view, $student_paper) as $active_view) {
    compact_ui_assert(stripos($active_view, 'Workflow v2') === false, 'Technical version labels must not be shown to users.');
    compact_ui_assert(stripos($active_view, 'Nigerian') === false, 'Country branding must not be shown in Online Examination views.');
}

echo "onlineexam compact UI contract tests passed" . PHP_EOL;
