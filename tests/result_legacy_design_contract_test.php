<?php

/**
 * Protect the established SchoolLift result appearance while allowing the
 * small table, summary, and A4-print improvements requested by schools.
 */

$root = dirname(__DIR__);
$mainResult = file_get_contents($root . '/admin/resultPage.php');
$kindergartenResult = file_get_contents($root . '/admin/kindergarten_result_page.php');
$summary = file_get_contents($root . '/admin/partials/result-summary-panel.php');
$css = file_get_contents($root . '/assets/css/result-report.css');
$printJavascript = file_get_contents($root . '/assets/js/result-report-print.js');
$assertions = 0;

function result_legacy_design_assert($condition, $message)
{
    global $assertions;
    $assertions++;

    if (!$condition) {
        fwrite(STDERR, 'FAIL: ' . $message . PHP_EOL);
        exit(1);
    }
}

foreach (array(
    'main result page' => $mainResult,
    'kindergarten result page' => $kindergartenResult,
) as $name => $source) {
    result_legacy_design_assert($source !== false, ucfirst($name) . ' must be readable.');
    result_legacy_design_assert(
        strpos($source, 'result-report__legacy-header') !== false,
        ucfirst($name) . ' must retain the legacy three-column header.'
    );
    result_legacy_design_assert(
        strpos($source, 'class="schname"') !== false && strpos($source, 'class="schloc"') !== false,
        ucfirst($name) . ' must retain the established school-name and address presentation.'
    );
    result_legacy_design_assert(
        strpos($source, 'result-report__legacy-title') !== false,
        ucfirst($name) . ' must retain the familiar centred result title.'
    );
    result_legacy_design_assert(
        strpos($source, '<header class="result-report__header"') === false,
        ucfirst($name) . ' must not replace the legacy header with the redesigned report-card header.'
    );
    result_legacy_design_assert(
        strpos($source, 'data-result-report') !== false
            && strpos($source, "partials/result-summary-panel.php") !== false,
        ucfirst($name) . ' must keep A4 fitting and the added result-summary fields.'
    );
}

result_legacy_design_assert($summary !== false, 'The shared result summary must be readable.');
foreach (array('NO. IN CLASS:', 'GRADE SUMMARY:', 'CUMULATIVE AVERAGE SCORE:', 'Promotion status', 'Key to grades') as $label) {
    result_legacy_design_assert(
        strpos($summary, $label) !== false,
        'The result summary is missing the required field: ' . $label
    );
}

result_legacy_design_assert(
    strpos($summary, '>NO. IN CLASS:</span>') !== false,
    'The number-in-class field must use the requested descriptive label.'
);

foreach (array('statistics', 'grade_key', 'promotion') as $section) {
    result_legacy_design_assert(
        strpos($summary, "in_array('" . $section . "'") !== false,
        'The shared result summary must support independent ' . $section . ' placement.'
    );
}

result_legacy_design_assert(
    substr_count($mainResult, "array('statistics')") === 6,
    'Midterm, termly, British, and cumulative result branches must place statistics inside their information boxes.'
);
result_legacy_design_assert(
    substr_count($mainResult, "array('grade_key')") === 5,
    'Every numeric/alphabetic academic table must place its grade key directly below the table.'
);
result_legacy_design_assert(
    substr_count($mainResult, 'tb-result-border result-report__academic-table') === 6
        && substr_count($mainResult, 'table-striped') === 6,
    'Every main academic result table must opt into the shared striped-table treatment.'
);
result_legacy_design_assert(
    substr_count($kindergartenResult, "array('statistics')") === 1
        && substr_count($kindergartenResult, 'tb-result-border result-report__academic-table') === 1
        && substr_count($kindergartenResult, 'table-striped') === 1,
    'The kindergarten result must share compatible information-box and striped-table improvements.'
);
result_legacy_design_assert(
    strpos($mainResult, '$showCumulativeAverage = $showPromotionOutcome') !== false,
    'Cumulative-average output must remain conditional instead of appearing on every result type.'
);
result_legacy_design_assert(
    substr_count($mainResult, 'result-report__chart--performance') === 4
        && substr_count($mainResult, 'result-report__domain-title') === 8,
    'Every termly and cumulative performance panel must use the shared chart and domain layout.'
);
result_legacy_design_assert(
    substr_count($mainResult, 'result-report__cell--remark') === 12,
    'Every academic remark heading and value must opt into whole-word remark sizing.'
);
result_legacy_design_assert(
    strpos($mainResult, '<tb>') === false && strpos($mainResult, '</tb>') === false,
    'Result domain tables must contain valid table cells.'
);
result_legacy_design_assert(
    strpos($mainResult, 'responsive: true') !== false
        && strpos($mainResult, 'maintainAspectRatio: false') !== false
        && substr_count($mainResult, 'Chart.min.js') === 1,
    'The academic graph must use boolean sizing options and one Chart.js runtime.'
);

result_legacy_design_assert($css !== false, 'The shared result stylesheet must be readable.');
foreach (array(
    '@page',
    'size: A4 portrait',
    '.result-report__legacy-header',
    'background: var(--result-brand)',
    'tbody > tr:nth-child(even)',
    'table.table-striped',
    '.result-report__summary--grade-key',
    'table-layout: fixed',
    'transform-origin: top center',
) as $contract) {
    result_legacy_design_assert(
        strpos($css, $contract) !== false,
        'The legacy A4 stylesheet is missing: ' . $contract
    );
}

result_legacy_design_assert(
    strpos($css, '.result-report::before {') === false,
    'The result sheet must not add a new decorative top strip to the legacy design.'
);
result_legacy_design_assert(
    strpos($css, '.result-report__legacy-title .report-title') !== false
        && strpos($css, 'background: transparent') !== false,
    'The familiar plain result title must remain visually unchanged.'
);
result_legacy_design_assert(
    strpos($css, 'table tr:first-child > th') === false,
    'A subject cell in the first body row must never inherit dark table-header styling.'
);
result_legacy_design_assert(
    preg_match('/\.result-report\s+\.container-motto\s+\.result-report__statistics\s*\{[^}]*border:\s*0;/s', $css) === 1
        && preg_match('/\.result-report\s+\.container-motto\s+\.result-report__statistics\s*>\s*\.result-report__stat\s*\{[^}]*border:\s*0;/s', $css) === 1,
    'Result statistics must use the plain student-information row treatment without boxed cells.'
);
result_legacy_design_assert(
    preg_match('/\.result-report\s+\.result-report__academic-table\s*>\s*tbody\s*>\s*tr\s*>\s*:first-child\s*\{[^}]*overflow-wrap:\s*normal;[^}]*hyphens:\s*none;/s', $css) === 1,
    'Subject names must wrap only at word boundaries.'
);
result_legacy_design_assert(
    strpos($css, '.result-report .result .tab > thead > tr > th:first-child') !== false,
    'The fixed academic table must reserve subject-column width on its header so subject text is not clipped.'
);
result_legacy_design_assert(
    preg_match('/\.result-report__grade-item[^\{]*\{[^\}]*font-size:\s*7\.8pt;/s', $css) === 1,
    'The standard key-to-grades text must use the requested larger readable size.'
);
result_legacy_design_assert(
    preg_match('/\.result-report__promotion-value\s*\{[^\}]*font-size:\s*10\.2pt;[^\}]*font-weight:\s*800;/s', $css) === 1,
    'The promotion decision must be visibly larger and bold.'
);
result_legacy_design_assert(
    preg_match('/\.result-report\s+\.result-report__academic-table\s+\.result-report__cell--remark\s*\{[^}]*width:\s*12\.5%;[^}]*word-break:\s*normal;[^}]*overflow-wrap:\s*normal;[^}]*hyphens:\s*none;/s', $css) === 1,
    'Academic remarks must reserve width and wrap only between words.'
);
result_legacy_design_assert(
    strpos($css, '.result-report__domain-table:not(.result-report__domain-table--combined) tbody th:nth-child(odd)') !== false
        && strpos($css, 'overflow-wrap: normal') !== false,
    'Affective and psychomotor labels must use compact, whole-word columns.'
);
result_legacy_design_assert(
    preg_match('/\.result-report__watermark\s*\{[^}]*z-index:\s*2;[^}]*width:\s*122mm;[^}]*opacity:\s*0\.065;/s', $css) === 1,
    'The school watermark must remain large, faint, centred, and visible across result sections.'
);
result_legacy_design_assert($printJavascript !== false, 'The shared result fitting script must be readable.');
result_legacy_design_assert(
    strpos($printJavascript, 'function compactDomainTables(root)') !== false
        && strpos($printJavascript, "document.documentElement.clientWidth - 16") !== false,
    'Result fitting must compact legacy domain rows and respect the available viewport width.'
);

echo 'legacy result design contract tests passed (' . $assertions . ' assertions)' . PHP_EOL;
