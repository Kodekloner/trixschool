<?php

function question_bank_assert($condition, $message)
{
    if (!$condition) {
        fwrite(STDERR, $message . PHP_EOL);
        exit(1);
    }
}

function question_bank_source($relative_path)
{
    $source = file_get_contents(__DIR__ . '/../' . $relative_path);
    question_bank_assert($source !== false, 'Unable to read ' . $relative_path . '.');
    return $source;
}

$controller = question_bank_source('application/controllers/admin/Question.php');
$model = question_bank_source('application/models/Question_model.php');
$exam_model = question_bank_source('application/models/Onlineexam_model.php');
$view = question_bank_source('application/views/admin/question/question.php');
$academic_access = question_bank_source('application/models/Academicaccess_model.php');
$migration = question_bank_source('application/migrations/139_session_scope_question_bank.php');
$migration_config = question_bank_source('application/config/migration.php');
$all_school_sql = question_bank_source('docs/all_school_database_migrations.sql');

foreach (array('can_view', 'can_add', 'can_edit', 'can_delete') as $permission) {
    question_bank_assert(strpos($controller, "'" . $permission . "'") !== false, 'Question Bank is missing the ' . $permission . ' authorization gate.');
}
question_bank_assert(strpos($controller, 'canAccessQuestionScope') !== false, 'Question writes and CSV imports must validate teacher class/arm scope.');
question_bank_assert(substr_count($controller, "(int) \$this->input->post('subject_id')") >= 2, 'Question writes and CSV imports must include the selected subject in their server-side scope check.');
question_bank_assert(strpos($controller, 'public function academicchoices()') !== false, 'Question Bank forms need an assignment-filtered academic choices endpoint.');
question_bank_assert(strpos($controller, 'getInaccessibleQuestionIds') !== false, 'Bulk deletion must reject crafted out-of-scope identifiers.');
question_bank_assert(strpos($model, 'questionVisibilitySql') !== false, 'Question Bank rows must use the shared academic visibility policy.');
question_bank_assert(strpos($academic_access, 'subjectTeacherAssignments') !== false
    && strpos($academic_access, 'teacher_subjects.subject_id') !== false
    && strpos($academic_access, 'access_timetable_subject.subject_id') !== false,
    'Question read/write access must require an exact subject assignment from a recognized assignment source.');
question_bank_assert(strpos($academic_access, "->where('teacher_subjects.session_id', \$session_id)") !== false
    && strpos($academic_access, "->where('access_timetable.session_id', \$session_id)") !== false,
    'Question access must not reuse a direct or timetable teaching assignment from another session.');
question_bank_assert(strpos($academic_access, "array('subjecttables', 'subject_timetable')") !== false,
    'The shared policy must recognize both subject timetable formats used by tenant schools.');
question_bank_assert(strpos($academic_access, 'classTeacherSectionIds') !== false
    && strpos($academic_access, "array('content', 'mark')") !== false,
    'Class-teacher viewing/candidate scope must remain separate from content/marking scope.');
question_bank_assert(strpos($model, 'public function deleteUnassigned') !== false, 'Question deletion must use one protected model operation.');
question_bank_assert(strpos($model, 'ORDER BY `id` FOR UPDATE') !== false, 'Question source rows must be locked before assignment checks and deletion.');
question_bank_assert(strpos($model, 'onlineexam_question_definitions') !== false, 'Question definitions must be cleaned transactionally with an unassigned source.');
question_bank_assert(strpos($exam_model, "SELECT `id` FROM `questions` WHERE `id` =") !== false, 'Assessment assignment must take the matching source-question lock.');
question_bank_assert(strpos($view, 'question-bank-table-scroll') !== false, 'Question Bank must remain horizontally scrollable on narrow screens.');
question_bank_assert(strpos($view, 'question-scope-subject') !== false && strpos($view, 'admin/question/academicchoices') !== false, 'Question Bank subject selectors must load the teacher\'s exact class-arm subjects.');
question_bank_assert(strpos($view, "hasPrivilege('question_bank', 'can_delete')") !== false, 'Bulk delete controls must be hidden without delete privilege.');
question_bank_assert(strpos($controller, 'public function copyquestions()') !== false
    && strpos($model, 'public function copyToContext') !== false,
    'Question Bank needs an explicit authorized copy workflow.');
question_bank_assert(strpos($model, 'questions.session_id') !== false && strpos($model, 'questions.term') !== false,
    'Question Bank reads must be session/term aware.');
question_bank_assert(strpos($migration, 'context_root_id') !== false
    && strpos($migration, 'remapSourceAssignments') !== false,
    'Migration 139 must preserve multi-context legacy source assignments.');
question_bank_assert(
    preg_match('/migration_version[\'\"]?\]\s*=\s*(\d+)\s*;/', $migration_config, $migration_match) === 1
        && (int) $migration_match[1] >= 139,
    'The application migration target must include migration 139 or a later migration.'
);
question_bank_assert(strpos($all_school_sql, 'SchoolLift Question Bank: session/term scope and assignment authorization') !== false
    && strpos($all_school_sql, 'question_academic_scope_idx') !== false,
    'The all-school SQL must include the idempotent migration 139 Question Bank changes.');

echo "question bank integrity contract tests passed" . PHP_EOL;
