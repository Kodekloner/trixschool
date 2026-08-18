<?php

require_once __DIR__ . '/../helper/promotion_helper.php';

function promotion_assert_same($expected, $actual, $message)
{
    if ($expected !== $actual) {
        fwrite(
            STDERR,
            $message . PHP_EOL
            . 'Expected: ' . var_export($expected, true) . PHP_EOL
            . 'Actual:   ' . var_export($actual, true) . PHP_EOL
        );
        exit(1);
    }
}

$failedBoundary = evaluate_promotion_rule(49.99, true, 50, [], [], 'JSS 2');
promotion_assert_same('not_promoted', $failedBoundary['decision'], '49.99 must fail a 50-point cutoff.');
promotion_assert_same('NOT PROMOTED', $failedBoundary['note'], 'A failed rule must use the standard note.');

$passedBoundary = evaluate_promotion_rule(50, true, 50, [], [], 'JSS 2');
promotion_assert_same('promoted', $passedBoundary['decision'], '50 must pass a 50-point cutoff.');
promotion_assert_same('PROMOTED TO: JSS 2', $passedBoundary['note'], 'A passing rule must include its configured target.');

$requirements = [
    ['subject_id' => 4, 'subject_name' => 'English Language', 'minimum_average' => 50],
    ['subject_id' => 5, 'subject_name' => 'Mathematics', 'minimum_average' => 50],
];
$priorityFailed = evaluate_promotion_rule(81, true, 50, $requirements, [4 => 72, 5 => 49.99], 'SS 1');
promotion_assert_same('not_promoted', $priorityFailed['decision'], 'A high overall average must not compensate for a failed priority subject.');
promotion_assert_same(false, $priorityFailed['priority_results'][1]['passed'], 'The failed priority subject must be reported.');

$priorityPassed = evaluate_promotion_rule(65, true, 50, $requirements, [4 => 50, 5 => 68], 'SS 1');
promotion_assert_same('promoted', $priorityPassed['decision'], 'The overall and all priority boundaries are inclusive.');

$missingPriority = evaluate_promotion_rule(70, true, 50, $requirements, [4 => 70], 'SS 1');
promotion_assert_same('pending', $missingPriority['decision'], 'A missing priority score must remain pending.');
promotion_assert_same('priority_score_missing', $missingPriority['reason_code'], 'The pending reason must identify the missing priority score.');

$missingAcademicData = evaluate_promotion_rule(null, false, 50, [], [], 'Basic 2');
promotion_assert_same('pending', $missingAcademicData['decision'], 'No academic data must remain pending.');

$missingTarget = evaluate_promotion_rule(70, true, 50, [], [], '');
promotion_assert_same('pending', $missingTarget['decision'], 'A passing rule without a target must not emit an incomplete promoted note.');

$automatic = evaluate_promotion_rule(45, true, 50, [], [], 'JSS 2');
$override = [
    'id' => 10,
    'action' => 'set',
    'decision' => 'promoted',
    'target_label' => 'JSS 2',
];
$overridden = apply_promotion_override($automatic, $override);
promotion_assert_same('override', $overridden['source'], 'A set action must replace the system outcome.');
promotion_assert_same('PROMOTED TO: JSS 2', $overridden['note'], 'The override target must be printed.');

$cleared = apply_promotion_override($automatic, ['id' => 11, 'action' => 'clear']);
promotion_assert_same('system', $cleared['source'], 'A clear action must restore the system outcome.');
promotion_assert_same('NOT PROMOTED', $cleared['note'], 'Clearing an override must retain the automatic note.');

$gradingRows = [
    ['Grade' => 'A', 'Remark' => 'Excellent', 'RangeStart' => 70, 'RangeEnd' => 100],
    ['Grade' => 'B', 'Remark' => 'Very Good', 'RangeStart' => 60, 'RangeEnd' => 69.9],
    ['Grade' => 'P', 'Remark' => 'Pass', 'RangeStart' => 50, 'RangeEnd' => 59.9],
    ['Grade' => 'F', 'Remark' => 'Fail', 'RangeStart' => 0, 'RangeEnd' => 49.9],
];
$gradeKey = build_result_grade_key($gradingRows);
promotion_assert_same('70% and Above', $gradeKey[0]['range'], 'The top 100-point grade range must use “and Above”.');
promotion_assert_same('60% - 69.9%', $gradeKey[1]['range'], 'Configured decimal boundaries must be preserved.');
promotion_assert_same('2A, 1B, 2P, 1F', build_result_grade_summary(['A', 'P', 'B', 'A', 'F', 'P'], $gradingRows), 'Grade summary must use grading-order counts and arbitrary grade labels.');

$rawScaleRows = [
    ['Grade' => 'A', 'Remark' => '', 'RangeStart' => 16, 'RangeEnd' => 20],
    ['Grade' => 'B', 'Remark' => '', 'RangeStart' => 12, 'RangeEnd' => 15.99],
];
$rawKey = build_result_grade_key($rawScaleRows);
promotion_assert_same('16 - 20', $rawKey[0]['range'], 'Raw midterm scales must retain their configured upper bound.');
promotion_assert_same('B', find_result_grade(12, $rawScaleRows), 'Grade lookup must include the lower boundary.');

promotion_assert_same(
    '(COALESCE(result_rows.`ca1`, 0) + COALESCE(result_rows.`ca3`, 0))',
    result_midterm_score_expression([1, 3, 3, 11, 0], 'result_rows'),
    'Midterm score expressions must contain only valid configured CA columns.'
);

promotion_assert_same(true, promotion_scope_authorized(true, false, false), 'Senior roles may review every class scope.');
promotion_assert_same(true, promotion_scope_authorized(false, true, true), 'An exactly assigned teacher may access the scope.');
promotion_assert_same(false, promotion_scope_authorized(false, true, false), 'A teacher from another class, section, or session must be rejected.');
promotion_assert_same(false, promotion_scope_authorized(false, false, true), 'A non-teacher cannot gain scope access from a class assignment.');

$lightPalette = build_result_brand_palette('#ffdd00');
promotion_assert_same('#000000', $lightPalette['contrast'], 'Light school colours must receive accessible dark heading text.');
$fallbackPalette = build_result_brand_palette('not-a-colour');
promotion_assert_same('#1f4e78', $fallbackPalette['brand'], 'Invalid school colours must use the print-safe fallback.');

echo "promotion helper tests passed" . PHP_EOL;
