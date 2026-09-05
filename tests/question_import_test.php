<?php
defined('BASEPATH') OR define('BASEPATH', __DIR__);
require_once __DIR__ . '/../application/helpers/question_import_helper.php';

function question_import_assert($condition, $message)
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

function question_import_read($csv)
{
    $stream = fopen('php://temp', 'w+');
    fwrite($stream, $csv);
    rewind($stream);
    try {
        return parse_question_import_csv($stream);
    } finally {
        fclose($stream);
    }
}

$sample = file_get_contents(__DIR__ . '/../backend/import/import_question_sample_file.csv');
$questions = question_import_read($sample);
question_import_assert(count($questions) === 4, 'All four question types in the template must import.');
question_import_assert($questions[0]['opt_a'] === 'Abuja' && $questions[0]['correct'] === 'opt_a', 'Single-choice options and answer must not shift.');
question_import_assert(json_decode($questions[1]['correct'], true) === array('opt_a', 'opt_c'), 'Multiple-choice answer JSON must survive CSV parsing.');
question_import_assert($questions[2]['correct'] === 'true', 'True/false answer must stay aligned.');
question_import_assert($questions[3]['question_type'] === 'descriptive' && $questions[3]['correct'] === '', 'A written question needs no objective answer.');
foreach ($questions as $question) {
    question_import_assert(count($question) === 8 && !array_key_exists('level', $question), 'Imported data must contain only the supported question fields.');
}

// A school may still upload a previously downloaded, nine-column template.
$legacy = '"question type Like (singlechoice,multichoice,true_false,descriptive)","level like (high,low,medium)",question,opt_a,opt_b,opt_c,opt_d,opt_e,"correct Like (opt_a,opt_b,opt_c,opt_d)' . "\n"
    . 'for true false it will true or false' . "\n"
    . 'for multichoice [""opt_b"",""opt_c""]"' . "\n"
    . 'singlechoice,high,What is 1 + 1?,1,2,3,4,,opt_b' . "\n"
    . 'true_false,,Water can freeze.,,,,,,true' . "\n";
$old_questions = question_import_read($legacy);
question_import_assert($old_questions[0]['question'] === 'What is 1 + 1?' && $old_questions[0]['correct'] === 'opt_b', 'Old template metadata must be ignored without shifting any field.');
question_import_assert(!array_key_exists('level', $old_questions[0]) && count($old_questions) === 2, 'Old difficulty values must neither be stored nor required.');

$reordered = "\xEF\xBB\xBFcorrect,question,opt_e,opt_d,opt_c,opt_b,opt_a,question_type\n"
    . "opt_a,\"Which city, in Nigeria, is the capital?\",,Ibadan,Kano,Lagos,Abuja,singlechoice\n\n";
$reordered_questions = question_import_read($reordered);
question_import_assert(count($reordered_questions) === 1 && $reordered_questions[0]['opt_a'] === 'Abuja', 'Headers, not position, must determine option mapping, including UTF-8 BOMs.');
question_import_assert($reordered_questions[0]['question'] === 'Which city, in Nigeria, is the capital?', 'Quoted commas must survive parsing.');
$multiline = "question_type,question,opt_a,opt_b,opt_c,opt_d,opt_e,correct\n"
    . "descriptive,\"Read the passage.\nExplain its meaning.\",,,,,,\n";
question_import_assert(strpos(question_import_read($multiline)[0]['question'], "\n") !== false, 'Quoted multiline questions must remain intact.');

foreach (array(
    '',
    "question_type,question\nsinglechoice,Incomplete columns\n",
    "question_type,question,opt_a,opt_b,opt_c,opt_d,opt_e,correct,correct\n",
    "question_type,question,opt_a,opt_b,opt_c,opt_d,opt_e,correct\n",
    "question_type,question,opt_a,opt_b,opt_c,opt_d,opt_e,correct\ndescriptive,,a,b,c,d,e,\n",
    "question_type,question,opt_a,opt_b,opt_c,opt_d,opt_e,correct\ndescriptive,Good question,,,,,,\nsinglechoice,Broken,a,b\n",
) as $invalid_csv) {
    $rejected = false;
    try {
        question_import_read($invalid_csv);
    } catch (InvalidArgumentException $exception) {
        $rejected = true;
    }
    question_import_assert($rejected, 'Malformed CSV must be rejected before any questions are imported.');
}

echo "question import tests passed\n";
