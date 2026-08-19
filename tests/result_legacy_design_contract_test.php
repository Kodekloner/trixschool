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
foreach (array('NO.:', 'GRADE SUMMARY:', 'CUMULATIVE AVERAGE SCORE:', 'Promotion status', 'Key to grades') as $label) {
    result_legacy_design_assert(
        strpos($summary, $label) !== false,
        'The result summary is missing the required field: ' . $label
    );
}

result_legacy_design_assert($css !== false, 'The shared result stylesheet must be readable.');
foreach (array(
    '@page',
    'size: A4 portrait',
    '.result-report__legacy-header',
    'background: var(--result-brand)',
    'tbody tr:nth-child(even)',
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

echo 'legacy result design contract tests passed (' . $assertions . ' assertions)' . PHP_EOL;
