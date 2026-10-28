<?php

/**
 * Render the actual report against an isolated clone of the supplied Purplins
 * dump. Run as a local MySQL socket user with RESULT_SUBJECT_TEST_DATABASE set.
 * All fixture changes are rolled back. Never point this at a school database.
 */
$database = getenv('RESULT_SUBJECT_TEST_DATABASE');
if (!$database) {
    echo "SKIP result subject scope: set RESULT_SUBJECT_TEST_DATABASE to a disposable Purplins dump clone.\n";
    exit(0);
}
if (!preg_match('/^codex_purplins_result_test_[a-z0-9_]+$/', $database)) {
    throw new RuntimeException('Only a disposable codex_purplins_result_test_ database is allowed.');
}

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
$link = mysqli_connect('localhost', 'root', '', $database);
mysqli_set_charset($link, 'utf8mb4');
mysqli_query($link, "SET SESSION sql_mode = ''");
require_once __DIR__ . '/../helper/promotion_helper.php';

function subject_scope_check($condition, $message)
{
    if (!$condition) throw new RuntimeException($message);
}

function subject_scope_render($link, $type)
{
    static $renderNumber = 0;
    $renderNumber++;
    $_GET = ['id' => '271', 'classid' => '9', 'classsection' => '16',
        'classsectionactual' => '16', 'session' => '11', 'term' => '1st', 'reltype' => $type];
    $rowsch_settings = mysqli_fetch_assoc(mysqli_query($link, 'SELECT * FROM sch_settings LIMIT 1'));
    $defRUladmin = $defRUlsec = 'https://school.test';
    $adminDirectory = realpath(__DIR__ . '/../admin');
    $source = file_get_contents($adminDirectory . '/resultPage.php');
    $source = str_replace("include('../database/config.php');", '', $source, $count);
    subject_scope_check($count === 1, 'The production database configuration must not be loaded.');
    $source = str_replace('__DIR__', var_export($adminDirectory, true), $source);
    $previousDirectory = getcwd();
    chdir($adminDirectory);
    ob_start();
    try {
        // Keep unrelated legacy notices out of the generated report HTML.
        $previousErrors = error_reporting(E_ERROR | E_PARSE);
        // A browser request starts with fresh PHP function declarations. Give
        // each in-process render its own namespace to model that isolation.
        eval('namespace ResultSubjectRender' . $renderNumber . '; ?>' . $source);
        return ob_get_contents();
    } finally {
        error_reporting($previousErrors);
        ob_end_clean();
        chdir($previousDirectory);
    }
}

function subject_scope_assert_report($html, $type, $format)
{
    $document = new DOMDocument();
    $previousLibxml = libxml_use_internal_errors(true);
    $document->loadHTML($html);
    libxml_clear_errors();
    libxml_use_internal_errors($previousLibxml);
    $xpath = new DOMXPath($document);
    $rows = $xpath->query('//table[contains(@class,"result-report__academic-table")]/tbody/tr[td and not(.//div[contains(@class,"alert")])]');
    subject_scope_check($rows->length === 15, "$format $type must render 15 assigned subject rows; got {$rows->length}.");
    $names = [];
    foreach ($rows as $row) $names[] = trim($row->getElementsByTagName('td')->item(0)->textContent);
    subject_scope_check(count(array_unique($names)) === 15, 'Duplicate group mappings must not repeat a subject.');
    subject_scope_check(!in_array('CREATIVE ARTS', $names) && !in_array('MATHEMATICS', $names), 'Unassigned score records must not appear as current subjects.');
    $footer = $xpath->query('//div[contains(@class,"summDD")]')->item(0)->textContent;
    subject_scope_check((bool) preg_match('/No\. of Subjects:\s*15\b/', $footer), "$format $type footer count must match the 15 rows.");
    subject_scope_check((bool) preg_match('/Total Score:\s*248\.9\b/', $footer), "$format $type total must exclude the extra 26 points.");
    subject_scope_check((bool) preg_match('/Average Score:\s*16\.59\b/', $footer), "$format $type average must use the same 15 subjects.");
}

$before = mysqli_fetch_assoc(mysqli_query($link, 'SELECT COUNT(*) AS rows_count, SUM(ID) AS id_sum FROM score'));
mysqli_begin_transaction($link);
try {
    $assigned = mysqli_fetch_assoc(mysqli_query($link, 'SELECT COUNT(DISTINCT sgs.subject_id) AS count
        FROM subject_group_class_sections sgcs
        JOIN subject_group_subjects sgs ON sgs.subject_group_id=sgcs.subject_group_id AND sgs.session_id=sgcs.session_id
        WHERE sgcs.class_section_id=16 AND sgcs.session_id=11'));
    subject_scope_check((int) $assigned['count'] === 15, 'The supplied dump must contain 15 assigned subjects.');
    $stale = mysqli_fetch_assoc(mysqli_query($link, 'SELECT COUNT(*) AS count FROM score
        WHERE StudentID=271 AND ClassID=9 AND SectionID=16 AND Session=11 AND SubjectID IN (5,18) AND ca1!=0'));
    subject_scope_check((int) $stale['count'] === 2, 'The regression fixture must retain the two extra score records.');

    foreach (['alphabetic', 'numeric'] as $format) {
        mysqli_query($link, "UPDATE assigncatoclass SET ResultType='$format' WHERE ClassID=9");
        foreach (['midterm', 'termly', 'cummulative'] as $type) {
            $context = get_result_summary_context($link, 271, 11, 9, 16, '1st', $type);
            subject_scope_check($context['subject_count'] === 15, "$type must count distinct assigned subjects.");
            subject_scope_check($context['total_score'] === 248.9 && $context['average_score'] === 16.59, "$type summary must match the rendered table.");
            subject_scope_check($context['cumulative_average'] === 16.59, 'The cumulative-average panel must exclude unassigned scores.');
            $expectedGrades = $type === 'midterm' ? '5A+, 5A, 4B+, 1B' : '15E';
            subject_scope_check($context['grade_summary'] === $expectedGrades, "$type grade summary must describe the same 15 subjects.");
            subject_scope_assert_report(subject_scope_render($link, $type), $type, $format);
            echo "$format $type: 15 rows, 15 subjects, total 248.90, average 16.59.\n";
        }
    }

    mysqli_query($link, 'INSERT INTO subject_group_class_sections (subject_group_id,class_section_id,session_id)
        SELECT subject_group_id,class_section_id,session_id FROM subject_group_class_sections
        WHERE class_section_id=16 AND session_id=11 LIMIT 1');
    subject_scope_assert_report(subject_scope_render($link, 'midterm'), 'midterm', 'duplicate mapping');

    // A second scored term must not count the same subject twice on an annual card.
    $columns = [];
    $fields = mysqli_query($link, 'SHOW COLUMNS FROM score');
    while ($field = mysqli_fetch_assoc($fields)) {
        if (strtolower($field['Field']) !== 'id') $columns[] = $field['Field'];
    }
    $quotedColumns = array_map(function ($column) { return '`' . $column . '`'; }, $columns);
    $values = array_map(function ($column) { return strtolower($column) === 'term' ? "'2nd'" : '`' . $column . '`'; }, $columns);
    mysqli_query($link, 'INSERT INTO score (' . implode(',', $quotedColumns) . ') SELECT ' . implode(',', $values)
        . ' FROM score WHERE StudentID=271 AND ClassID=9 AND SectionID=16 AND Session=11 AND SubjectID=28 LIMIT 1');
    $annual = get_result_summary_context($link, 271, 11, 9, 16, '3rd', 'cummulative');
    subject_scope_check($annual['subject_count'] === 15, 'Additional term rows must not inflate the annual subject count.');
    $term = get_result_summary_context($link, 271, 11, 9, 16, '1st', 'termly');
    subject_scope_check($term['subject_count'] === 15 && $term['total_score'] === 248.9, 'Another term must not change the first-term subject summary.');
    echo "Duplicate mappings and multi-term subject-count checks passed.\n";
} finally {
    mysqli_rollback($link);
}
$after = mysqli_fetch_assoc(mysqli_query($link, 'SELECT COUNT(*) AS rows_count, SUM(ID) AS id_sum FROM score'));
subject_scope_check($before === $after, 'All score fixture changes must be rolled back.');
echo "Result subject scope MySQL regression tests passed; original score records retained.\n";
