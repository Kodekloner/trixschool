<?php

require_once __DIR__ . '/../helper/publishresult_helper.php';

function publishresult_assert_same($expected, $actual, $message)
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

publishresult_assert_same(
    'cummulative',
    normalize_publishresult_reltype('cumulative'),
    'The correct cumulative spelling must map to the legacy database value.'
);
publishresult_assert_same(
    'cummulative',
    normalize_publishresult_reltype('CUMMULATIVE'),
    'The legacy cumulative spelling must remain case-insensitively compatible.'
);
publishresult_assert_same(
    'midterm',
    normalize_publishresult_reltype('mid-term'),
    'The mid-term alias must remain compatible.'
);
publishresult_assert_same(
    '',
    normalize_publishresult_reltype('Result Type'),
    'The placeholder must never become a stored result type.'
);
publishresult_assert_same(
    '3rd',
    normalize_publishresult_term('Select Term', 'cumulative'),
    'Annual cumulative publication must use third-term metadata.'
);
publishresult_assert_same(
    '2nd',
    normalize_publishresult_term('2ND', 'termly'),
    'Termly publication must preserve its selected term.'
);
publishresult_assert_same(
    '',
    normalize_publishresult_term('Select Term', 'termly'),
    'A non-cumulative placeholder term must be rejected.'
);
publishresult_assert_same(
    true,
    is_valid_publishresult_date('2026-07-30'),
    'An ISO publication date must be accepted.'
);
publishresult_assert_same(
    false,
    is_valid_publishresult_date('2026-02-30'),
    'An impossible publication date must be rejected.'
);
publishresult_assert_same(
    '',
    get_publishresult_validation_error(10, 'ignored', 'cumulative', 8, 18, '2026-07-30'),
    'A complete cumulative publication request must validate.'
);
publishresult_assert_same(
    'Please select a valid result type.',
    get_publishresult_validation_error(10, '3rd', 'Result Type', 8, 18, '2026-07-30'),
    'An invalid result type must fail closed.'
);
publishresult_assert_same(
    'Please select a valid term.',
    get_publishresult_validation_error(10, 'Select Term', 'termly', 8, 18, '2026-07-30'),
    'A termly request must include a real term.'
);

$testSocket = getenv('PUBLISHRESULT_TEST_SOCKET');
$testDatabase = getenv('PUBLISHRESULT_TEST_DATABASE');

if ($testSocket === false || $testSocket === '' || $testDatabase === false || $testDatabase === '') {
    echo "publishresult helper unit tests passed; MySQL integration tests skipped" . PHP_EOL;
    exit(0);
}

if (!preg_match('/^[A-Za-z0-9_]+$/', $testDatabase)) {
    fwrite(STDERR, "Invalid PUBLISHRESULT_TEST_DATABASE value." . PHP_EOL);
    exit(1);
}

$link = mysqli_init();
if (!mysqli_real_connect($link, null, 'root', '', $testDatabase, null, $testSocket)) {
    fwrite(STDERR, 'Unable to connect to the isolated MySQL test database: ' . mysqli_connect_error() . PHP_EOL);
    exit(1);
}

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
mysqli_query($link, "SET SESSION sql_mode = 'STRICT_TRANS_TABLES,ERROR_FOR_DIVISION_BY_ZERO,NO_ENGINE_SUBSTITUTION'");
mysqli_query($link, 'DROP TABLE IF EXISTS `publishresult`');
mysqli_query(
    $link,
    "CREATE TABLE `publishresult` (
        `id` int NOT NULL AUTO_INCREMENT,
        `Session` int NOT NULL,
        `Term` varchar(225) NOT NULL,
        `ClassID` int NOT NULL DEFAULT '0',
        `SectionID` int NOT NULL DEFAULT '0',
        `ResultType` varchar(225) NOT NULL,
        `Date` varchar(10000) NOT NULL,
        PRIMARY KEY (`id`),
        KEY `idx_publishresult_scope` (`Session`, `Term`, `ClassID`, `SectionID`, `ResultType`)
    ) ENGINE=InnoDB DEFAULT CHARSET=latin1"
);

publishresult_assert_same(
    true,
    save_publishresult_record($link, 10, 'Select Term', 'cumulative', 8, 18, '2026-07-30'),
    'Cumulative creation must succeed under strict SQL mode.'
);
$cumulative = mysqli_fetch_assoc(mysqli_query($link, "SELECT * FROM `publishresult` WHERE `Session` = 10"));
publishresult_assert_same('3rd', $cumulative['Term'], 'Cumulative storage must explicitly include third term.');
publishresult_assert_same('cummulative', $cumulative['ResultType'], 'Cumulative storage must use the legacy canonical value.');

publishresult_assert_same(
    true,
    save_publishresult_record($link, 10, '', 'cummulative', 8, 18, '2026-08-01'),
    'Saving the same cumulative scope must update it.'
);
$sameScopeCount = (int) mysqli_fetch_assoc(
    mysqli_query(
        $link,
        "SELECT COUNT(*) AS total
         FROM `publishresult`
         WHERE `Session` = 10 AND `ClassID` = 8 AND `SectionID` = 18 AND `ResultType` = 'cummulative'"
    )
)['total'];
publishresult_assert_same(1, $sameScopeCount, 'A repeated cumulative save must not create a duplicate.');

publishresult_assert_same(
    true,
    save_publishresult_record($link, 10, '', 'cummulative', 9, 18, '2026-08-02'),
    'A different class must receive an isolated cumulative publication.'
);
publishresult_assert_same(
    true,
    save_publishresult_record($link, 10, '1st', 'midterm', 8, 18, '2026-07-01'),
    'Midterm publication must still save.'
);
publishresult_assert_same(
    true,
    save_publishresult_record($link, 10, '2nd', 'termly', 8, 18, '2026-07-02'),
    'Termly publication must still save.'
);
publishresult_assert_same(
    null,
    find_publishresult_record($link, 10, '2nd', 'midterm', 8, 18, null, false),
    'Midterm publication must not cross term boundaries.'
);
publishresult_assert_same(
    'termly',
    find_publishresult_record($link, 10, '2nd', 'termly', 8, 18, null, false)['ResultType'] ?? null,
    'Termly lookup must return only the selected term and type.'
);

mysqli_query(
    $link,
    "INSERT INTO `publishresult`
        (`Session`, `Term`, `ClassID`, `SectionID`, `ResultType`, `Date`)
     VALUES
        (18, '', 0, 0, 'cummulative', '2026-07-01')"
);
publishresult_assert_same(
    0,
    (int) (find_publishresult_record($link, 18, '', 'cummulative', 4, 6)['ClassID'] ?? -1),
    'An exact miss must retain read-only compatibility with a legacy global publication.'
);
publishresult_assert_same(
    true,
    save_publishresult_record($link, 18, '', 'cummulative', 4, 6, '2026-07-03'),
    'Saving after a legacy fallback must create an exact class publication.'
);
$legacyDate = mysqli_fetch_assoc(
    mysqli_query($link, "SELECT `Date` FROM `publishresult` WHERE `Session` = 18 AND `ClassID` = 0 AND `SectionID` = 0")
)['Date'];
publishresult_assert_same('2026-07-01', $legacyDate, 'An exact save must never overwrite the legacy global row.');

$rowCountBeforeInvalid = (int) mysqli_fetch_assoc(
    mysqli_query($link, 'SELECT COUNT(*) AS total FROM `publishresult`')
)['total'];
publishresult_assert_same(
    false,
    save_publishresult_record($link, 10, '3rd', 'Result Type', 8, 18, '2026-07-30'),
    'An invalid result type must not be stored.'
);
$rowCountAfterInvalid = (int) mysqli_fetch_assoc(
    mysqli_query($link, 'SELECT COUNT(*) AS total FROM `publishresult`')
)['total'];
publishresult_assert_same($rowCountBeforeInvalid, $rowCountAfterInvalid, 'Invalid input must not change publication data.');

$futureDate = date('Y-m-d', strtotime('+2 days'));
mysqli_query(
    $link,
    "INSERT INTO `publishresult`
        (`Session`, `Term`, `ClassID`, `SectionID`, `ResultType`, `Date`)
     VALUES
        (20, '', 0, 0, 'cummulative', '2026-01-01')"
);
publishresult_assert_same(
    true,
    save_publishresult_record($link, 20, '', 'cummulative', 8, 18, $futureDate),
    'A future-dated cumulative publication must be schedulable.'
);
publishresult_assert_same(
    null,
    find_publishresult_record($link, 20, '', 'cummulative', 8, 18, date('Y-m-d'), false),
    'A future publication must remain hidden before its date.'
);
publishresult_assert_same(
    null,
    find_publishresult_record($link, 20, '', 'cummulative', 8, 18, date('Y-m-d'), true),
    'A scheduled exact publication must shadow an older global publication until its date.'
);

echo "publishresult helper unit and MySQL integration tests passed" . PHP_EOL;
