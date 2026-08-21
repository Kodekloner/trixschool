<?php

function promotion_security_assert($condition, $message)
{
    if (!$condition) {
        fwrite(STDERR, $message . PHP_EOL);
        exit(1);
    }
}

$root = dirname(__DIR__);
$controller = file_get_contents($root . '/application/controllers/admin/Promotioncriteria.php');
$model = file_get_contents($root . '/application/models/Promotioncriteria_model.php');
$migration = file_get_contents($root . '/application/migrations/133_add_promotion_system.php');
$helper = file_get_contents($root . '/helper/promotion_helper.php');
$resultPage = file_get_contents($root . '/admin/resultPage.php');
$kindergartenPage = file_get_contents($root . '/admin/kindergarten_result_page.php');
$printCss = file_get_contents($root . '/assets/css/result-report.css');
$printJs = file_get_contents($root . '/assets/js/result-report-print.js');

promotion_security_assert(strpos($controller, "hasPrivilege('manage_promotion_criteria'") !== false, 'Criteria mutations must be protected by the criteria permission.');
promotion_security_assert(strpos($controller, "hasPrivilege('override_promotion_note'") !== false, 'Override mutations must be protected by the override permission.');
promotion_security_assert(strpos($controller, "REQUEST_METHOD')) !== 'POST'") !== false, 'Promotion mutations must reject non-POST requests.');
promotion_security_assert(strpos($controller, 'hash_equals($expected, $provided)') !== false, 'Promotion mutations must validate their scoped CSRF token.');
promotion_security_assert(strpos($controller, 'studentMatchesScope(') !== false, 'Override scope must be checked against the student roster.');
promotion_security_assert(strpos($controller, 'teacherHasScope(') !== false, 'Teachers must be checked against an exact class-teacher assignment.');
promotion_security_assert(strpos($controller, "decision === 'promoted' && \$targetClassId <= 0 && \$targetLabel === ''") !== false, 'A promoted override must require a destination.');

promotion_security_assert(stripos($model, "insert('student_session'") === false, 'The promotion model must never insert student_session rows.');
promotion_security_assert(stripos($model, "update('student_session'") === false, 'The promotion model must never update student_session rows.');
promotion_security_assert(stripos($model, "delete('student_session'") === false, 'The promotion model must never delete student_session rows.');
promotion_security_assert(strpos($model, "'action' => 'clear'") !== false, 'Clearing an override must append a clear audit row.');

promotion_security_assert(strpos($migration, "'manage_promotion_criteria'") !== false, 'Migration 133 must seed criteria management RBAC.');
promotion_security_assert(strpos($migration, "'override_promotion_note'") !== false, 'Migration 133 must seed override RBAC.');
promotion_security_assert(strpos($helper, "TRIM(COALESCE(result_rows.Remark, '')) != ''") !== false, 'British class counts must ignore blank placeholder rows.');
promotion_security_assert(strpos($helper, 'get_result_midterm_ca_indices') !== false, 'Midterm class counts must reuse configured CA scope.');

foreach (array($resultPage, $kindergartenPage) as $page) {
    promotion_security_assert(strpos($page, 'result-summary-panel.php') !== false, 'Every result format must render the shared summary panel.');
    promotion_security_assert(strpos($page, 'data-result-report') !== false, 'Every result format must use the shared A4 report contract.');
}
promotion_security_assert(strpos($printCss, 'size: A4 portrait') !== false, 'Print CSS must declare A4 portrait output.');
promotion_security_assert(strpos($printJs, "window.addEventListener('beforeprint', fitAll)") !== false, 'The report must be measured again before printing.');

echo "promotion security contract tests passed" . PHP_EOL;
