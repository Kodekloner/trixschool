<?php

define('BASEPATH', __DIR__);
require_once __DIR__ . '/../application/libraries/Onlineexam_scoring.php';

function onlineexam_assert_same($expected, $actual, $message)
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

$scoring = new Onlineexam_scoring();

$unanswered = $scoring->scoreObjective('', 'a', 2, 0.5, true, 'multichoice');
onlineexam_assert_same(false, $unanswered['answered'], 'An empty response must be unanswered.');
onlineexam_assert_same(0.0, $unanswered['mark'], 'An unanswered question must not receive a negative mark.');

$wrong = $scoring->scoreObjective('b', 'a', 2, 0.5, true, 'multichoice');
onlineexam_assert_same(true, $wrong['answered'], 'A selected wrong response is attempted.');
onlineexam_assert_same(false, $wrong['correct'], 'The wrong option must not be correct.');
onlineexam_assert_same(-0.5, $wrong['mark'], 'Negative marking applies only to an attempted wrong answer.');

$right = $scoring->scoreObjective('["b","a"]', '["a","b"]', 2, 0.5, true, 'multiple_choice');
onlineexam_assert_same(true, $right['correct'], 'Unordered multi-select answers should compare canonically.');
onlineexam_assert_same(2.0, $right['mark'], 'A correct objective answer receives its full mark.');

$assessment = $scoring->calculateAssessment(array(
    array('earned' => 30, 'raw_max' => 40, 'contribution' => 40),
    array('earned' => 45, 'raw_max' => 60, 'contribution' => 60),
), 20);
onlineexam_assert_same(75.0, $assessment['weighted_score'], 'Paper contributions should be calculated independently.');
onlineexam_assert_same(15.0, $assessment['final_score'], 'Combined papers should scale into the selected CA maximum.');

$negative_total = $scoring->calculateAssessment(array(
    array('earned' => -5, 'raw_max' => 20, 'contribution' => 100),
), 40);
onlineexam_assert_same(0.0, $negative_total['final_score'], 'A synchronized academic result cannot be below zero.');

$british_profile = array(
    'mode' => 'thresholds',
    'outcomes' => array(
        array('value' => 'Emerging', 'min' => 0, 'max' => 39.99),
        array('value' => 'Expected', 'min' => 40, 'max' => 69.99),
        array('value' => 'Exceeding', 'min' => 70, 'max' => 100),
    ),
);
onlineexam_assert_same('Expected', $scoring->resolveBritishOutcome(55, $british_profile), 'British thresholds should resolve the configured outcome.');
onlineexam_assert_same(
    'Exceeding',
    $scoring->resolveBritishOutcome(0, array('mode' => 'teacher_selection'), 'Exceeding'),
    'Teacher-selected British outcomes should be retained.'
);

$kindergarten_profile = array(
    'labels' => array(
        array('index' => 0, 'min' => 0, 'max' => 49.99),
        array('index' => 1, 'min' => 50, 'max' => 100),
    ),
);
onlineexam_assert_same(1, $scoring->resolveKindergartenLabel(75, $kindergarten_profile, 2), 'Kindergarten thresholds should resolve to the configured label index.');

echo "onlineexam scoring tests passed" . PHP_EOL;
