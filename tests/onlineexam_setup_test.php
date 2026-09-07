<?php

define('BASEPATH', __DIR__);
require_once __DIR__ . '/../application/libraries/Onlineexam_setup.php';
require_once __DIR__ . '/../application/libraries/Onlineexam_scoring.php';

function setupAssert($condition, $message)
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

date_default_timezone_set('Africa/Lagos');
$setup = new Onlineexam_setup();
$start = '2026-09-07 09:00:00';
setupAssert($setup->windowError($start, '2026-09-07 10:00:00', 60) === null, 'An exactly full-duration window is valid.');
setupAssert($setup->windowError($start, '2026-09-07 09:59:59', 60) !== null, 'A window one second shorter than the duration must fail.');
setupAssert($setup->windowError($start, $start, 60) !== null, 'Equal start/end must fail.');
setupAssert($setup->windowError($start, '2026-09-07 08:00:00', 60) !== null, 'Reversed dates must fail.');
setupAssert($setup->windowError('bad', '2026-09-07 10:00:00', 60) !== null, 'Malformed dates must fail.');
setupAssert($setup->windowError($start, '2026-09-07 10:00:00', 0) !== null, 'Zero duration must fail.');
setupAssert($setup->windowError($start, '2026-09-07 10:00:00', '1.5') !== null, 'Fractional duration must fail.');
setupAssert($setup->windowError(strtotime($start), strtotime('2026-09-07 10:00:00'), '60') === null, 'Parsed timestamps and form integer strings must work.');
setupAssert($setup->windowError('2026-09-07 23:30:00', '2026-09-08 00:30:00', 60) === null, 'A full-duration overnight window must work.');
setupAssert($setup->windowError($start, '2026-09-07 10:00:00', 60, $start, '2026-09-07 10:00:00') === null, 'A paper may use the exact parent window.');
setupAssert($setup->windowError($start, '2026-09-07 10:00:00', 60, '2026-09-07 09:01:00', '2026-09-07 11:00:00') !== null, 'Paper cannot open before its parent assessment.');
setupAssert($setup->windowError($start, '2026-09-07 10:00:00', 60, $start, '2026-09-07 09:59:59') !== null, 'Paper cannot end after its parent assessment.');
setupAssert($setup->paperMaximum('20.00') === 20.0, 'CA1 maximum 20 must be the paper raw maximum.');
foreach (array(null, '', 0, -2, 'bad', INF, NAN) as $invalid) {
    setupAssert($setup->paperMaximum($invalid) === null, 'Invalid component maximum must be rejected.');
}
$scoring = new Onlineexam_scoring();
$single = $scoring->calculateAssessment(array(array('earned' => 15, 'raw_max' => $setup->paperMaximum(20), 'contribution' => 100)), 20);
setupAssert($single['final_score'] === 15.0, 'Single-paper scoring should directly preserve marks on the component scale.');
$combined = $scoring->calculateAssessment(array(
    array('earned' => 10, 'raw_max' => $setup->paperMaximum(20), 'contribution' => 40),
    array('earned' => 15, 'raw_max' => $setup->paperMaximum(20), 'contribution' => 60),
), 20);
setupAssert($combined['final_score'] === 13.0, 'Two papers each scored out of 20 must retain their weighted contributions.');

echo "Online Examination setup rules passed." . PHP_EOL;
