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

foreach (array('can_view', 'can_add', 'can_edit', 'can_delete') as $permission) {
    question_bank_assert(strpos($controller, "'" . $permission . "'") !== false, 'Question Bank is missing the ' . $permission . ' authorization gate.');
}
question_bank_assert(strpos($controller, 'canAccessQuestionScope') !== false, 'Question writes and CSV imports must validate teacher class/arm scope.');
question_bank_assert(strpos($controller, 'getInaccessibleQuestionIds') !== false, 'Bulk deletion must reject crafted out-of-scope identifiers.');
question_bank_assert(strpos($model, 'public function deleteUnassigned') !== false, 'Question deletion must use one protected model operation.');
question_bank_assert(strpos($model, 'ORDER BY `id` FOR UPDATE') !== false, 'Question source rows must be locked before assignment checks and deletion.');
question_bank_assert(strpos($model, 'onlineexam_question_definitions') !== false, 'Question definitions must be cleaned transactionally with an unassigned source.');
question_bank_assert(strpos($exam_model, "SELECT `id` FROM `questions` WHERE `id` =") !== false, 'Assessment assignment must take the matching source-question lock.');
question_bank_assert(strpos($view, 'question-bank-table-scroll') !== false, 'Question Bank must remain horizontally scrollable on narrow screens.');
question_bank_assert(strpos($view, "hasPrivilege('question_bank', 'can_delete')") !== false, 'Bulk delete controls must be hidden without delete privilege.');

echo "question bank integrity contract tests passed" . PHP_EOL;
