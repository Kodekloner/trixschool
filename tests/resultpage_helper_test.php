<?php

require_once __DIR__ . '/../helper/resultpage_helper.php';

function assert_same($expected, $actual, $message)
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

assert_same('midterm', normalize_result_page_reltype('mid-term'), 'Mid-term alias should normalize.');
assert_same('midterm', normalize_result_page_reltype('MIDTERM'), 'Midterm should be case insensitive.');
assert_same('termly', normalize_result_page_reltype('termly'), 'Termly should remain termly.');
assert_same('cummulative', normalize_result_page_reltype('cummulative'), 'Legacy cumulative spelling should remain compatible.');
assert_same('cummulative', normalize_result_page_reltype('cumulative'), 'Correct cumulative spelling should be accepted.');
assert_same('', normalize_result_page_reltype('garbage'), 'Unknown result types should be rejected.');

assert_same(0.0, safe_result_average(100, 0), 'A zero denominator should return a finite zero.');
assert_same(77.0, safe_result_average(2695, 35), 'Student 179 cumulative average should be 77.');

$rows = [
    ['id' => 99, 'term' => 'Select Term', 'domain1' => 5, 'domain2' => 5],
    ['id' => 1, 'term' => '1st', 'domain1' => 4, 'domain2' => 5],
    ['id' => 2, 'term' => '2nd', 'domain1' => 0, 'domain2' => 0],
    ['id' => 3, 'term' => '3rd', 'domain1' => 0, 'domain2' => 0],
];
$latest = find_latest_scored_result_domain_row($rows, 'domain');
assert_same(1, $latest['id'] ?? null, 'Later all-zero placeholders must not replace an earlier scored domain row.');

$rows[] = ['id' => 4, 'term' => '2nd', 'domain1' => 3, 'domain2' => 0];
$latest = find_latest_scored_result_domain_row($rows, 'domain');
assert_same(4, $latest['id'] ?? null, 'The latest term containing a score should be selected.');

$empty = find_latest_scored_result_domain_row(
    [['id' => 5, 'term' => '2nd', 'psycomotor1' => 0]],
    'psycomotor'
);
assert_same(null, $empty, 'All-zero psychomotor placeholders should count as no result.');

echo "resultpage_helper tests passed" . PHP_EOL;
