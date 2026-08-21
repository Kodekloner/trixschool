<?php

/**
 * Unit coverage for the independently positioned result-summary fragments.
 */

$assertions = 0;

function result_summary_panel_assert($condition, $message)
{
    global $assertions;
    $assertions++;

    if (!$condition) {
        fwrite(STDERR, 'FAIL: ' . $message . PHP_EOL);
        exit(1);
    }
}

function result_summary_panel_render($sections, array $context, $showCumulative, $showPromotion, array $outcome)
{
    $resultSummaryContext = $context;
    $showCumulativeAverage = $showCumulative;
    $showPromotionOutcome = $showPromotion;
    $promotionOutcome = $outcome;

    if ($sections !== null) {
        $resultSummaryPanelSections = $sections;
    }

    ob_start();
    include dirname(__DIR__) . '/admin/partials/result-summary-panel.php';
    return ob_get_clean();
}

$context = array(
    'number_in_class' => 27,
    'grade_summary' => '5A, 2B, 1C',
    'cumulative_average' => 72.345,
    'grade_key' => array(
        array('grade' => '<A>', 'range' => '70% & Above'),
        array('grade' => 'B', 'range' => '60% - 69.99%'),
    ),
);
$outcome = array(
    'decision' => 'not_promoted',
    'note' => 'NOT PROMOTED <review>',
);

$statistics = result_summary_panel_render(array('statistics'), $context, true, true, $outcome);
result_summary_panel_assert(strpos($statistics, 'data-result-summary-section="statistics"') !== false, 'Statistics mode must render the statistics section.');
result_summary_panel_assert(strpos($statistics, 'NO. IN CLASS:') !== false && strpos($statistics, '>27<') !== false, 'Statistics mode must render number in class.');
result_summary_panel_assert(substr_count($statistics, 'result-report__stat-line') === 3, 'Statistics must use the same heading-line structure as the surrounding student details.');
result_summary_panel_assert(strpos($statistics, '5A, 2B, 1C') !== false, 'Statistics mode must render the grade summary.');
result_summary_panel_assert(strpos($statistics, '72.35') !== false, 'Statistics mode must format the cumulative average to two decimals.');
result_summary_panel_assert(strpos($statistics, 'Key to grades') === false && strpos($statistics, 'Promotion status') === false, 'Statistics mode must not duplicate grade-key or promotion content.');

$termStatistics = result_summary_panel_render(array('statistics'), $context, false, false, $outcome);
result_summary_panel_assert(strpos($termStatistics, '5A, 2B, 1C') !== false, 'Non-cumulative numeric results must retain their grade summary.');
result_summary_panel_assert(strpos($termStatistics, 'CUMULATIVE AVERAGE SCORE:') === false, 'Non-cumulative result scopes must omit the cumulative-average field.');

$gradeKey = result_summary_panel_render(array('grade_key'), $context, true, true, $outcome);
result_summary_panel_assert(strpos($gradeKey, 'data-result-summary-section="grade-key"') !== false, 'Grade-key mode must render directly addressable grade-key markup.');
result_summary_panel_assert(strpos($gradeKey, '&lt;A&gt;') !== false && strpos($gradeKey, '70% &amp; Above') !== false, 'Grade-key mode must escape configured labels and ranges.');
result_summary_panel_assert(strpos($gradeKey, 'NO. IN CLASS:') === false && strpos($gradeKey, 'Promotion status') === false, 'Grade-key mode must not duplicate statistics or promotion content.');

$promotion = result_summary_panel_render(array('promotion'), $context, true, true, $outcome);
result_summary_panel_assert(strpos($promotion, 'data-promotion-status="not-promoted"') !== false, 'Promotion mode must normalize the decision for styling.');
result_summary_panel_assert(strpos($promotion, 'NOT PROMOTED &lt;review&gt;') !== false, 'Promotion mode must escape the final promotion note.');
result_summary_panel_assert(strpos($promotion, 'NO. IN CLASS:') === false && strpos($promotion, 'Key to grades') === false, 'Promotion mode must not duplicate statistics or grade-key content.');

$hiddenPromotion = result_summary_panel_render(array('promotion'), $context, true, false, $outcome);
result_summary_panel_assert(trim($hiddenPromotion) === '', 'Promotion mode must remain empty outside supported result scopes.');

$qualitativeContext = array(
    'number_in_class' => 14,
    'grade_summary' => '',
    'cumulative_average' => null,
    'grade_key' => array(),
);
$qualitativeKey = result_summary_panel_render(array('grade_key'), $qualitativeContext, false, true, $outcome);
result_summary_panel_assert(trim($qualitativeKey) === '', 'Qualitative results must omit an unsupported grade key.');
$qualitativeStatistics = result_summary_panel_render(array('statistics'), $qualitativeContext, false, true, $outcome);
result_summary_panel_assert(strpos($qualitativeStatistics, 'NO. IN CLASS:') !== false && strpos($qualitativeStatistics, '>14<') !== false, 'British and kindergarten statistics must retain number in class.');
result_summary_panel_assert(strpos($qualitativeStatistics, 'GRADE SUMMARY:') === false && strpos($qualitativeStatistics, 'CUMULATIVE AVERAGE SCORE:') === false, 'British and kindergarten statistics must omit unsupported numeric fields.');

$all = result_summary_panel_render(null, $context, true, true, $outcome);
result_summary_panel_assert(substr_count($all, 'data-result-summary-section=') === 3, 'Default rendering must remain backwards-compatible with all three sections.');

echo 'result summary panel tests passed (' . $assertions . ' assertions)' . PHP_EOL;
