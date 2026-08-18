<?php

require_once __DIR__ . '/../helper/promotion_helper.php';

function promotion_mysql_assert_same($expected, $actual, $message)
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

function promotion_mysql_exec($link, $sql)
{
    mysqli_query($link, $sql);
}

function promotion_mysql_student_session_snapshot($link)
{
    $row = mysqli_fetch_assoc(mysqli_query(
        $link,
        "SELECT COUNT(*) AS row_count,
                COALESCE(GROUP_CONCAT(CONCAT_WS(':', id, student_id, session_id, class_id, section_id)
                                      ORDER BY id SEPARATOR '|'), '') AS row_fingerprint
         FROM student_session"
    ));

    return array((int) $row['row_count'], (string) $row['row_fingerprint']);
}

$testSocket = getenv('PROMOTION_TEST_SOCKET');
$testDatabase = getenv('PROMOTION_TEST_DATABASE');

if ($testSocket === false || $testSocket === '' || $testDatabase === false || $testDatabase === '') {
    echo "promotion MySQL integration tests skipped; set PROMOTION_TEST_SOCKET and PROMOTION_TEST_DATABASE to an isolated test database" . PHP_EOL;
    exit(0);
}

if (!preg_match('/^[A-Za-z0-9_]+$/', $testDatabase) || stripos($testDatabase, 'test') === false) {
    fwrite(STDERR, "PROMOTION_TEST_DATABASE must be an isolated database whose name contains 'test'." . PHP_EOL);
    exit(1);
}

$testUser = getenv('PROMOTION_TEST_USER');
$testPassword = getenv('PROMOTION_TEST_PASSWORD');
$testUser = ($testUser === false || $testUser === '') ? 'root' : $testUser;
$testPassword = $testPassword === false ? '' : $testPassword;

$link = mysqli_init();
if (!mysqli_real_connect($link, null, $testUser, $testPassword, $testDatabase, null, $testSocket)) {
    fwrite(STDERR, 'Unable to connect to the isolated promotion test database: ' . mysqli_connect_error() . PHP_EOL);
    exit(1);
}

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
promotion_mysql_exec($link, "SET SESSION sql_mode = 'STRICT_TRANS_TABLES,ERROR_FOR_DIVISION_BY_ZERO,NO_ENGINE_SUBSTITUTION'");

$tables = array(
    'promotion_note_overrides',
    'promotion_criteria_subjects',
    'promotion_criteria_classes',
    'promotion_criteria',
    'kindergarten_result',
    'britishresult',
    'score',
    'student_session',
    'students',
    'assigngradingtclass',
    'gradingstructure',
    'assigncatoclass',
    'resultsetting',
    'subjects',
    'classes',
);
foreach ($tables as $table) {
    promotion_mysql_exec($link, 'DROP TABLE IF EXISTS `' . $table . '`');
}

promotion_mysql_exec($link, "CREATE TABLE classes (
    id INT NOT NULL,
    class VARCHAR(191) NOT NULL,
    PRIMARY KEY (id)
) ENGINE=InnoDB");
promotion_mysql_exec($link, "CREATE TABLE subjects (
    id INT NOT NULL,
    name VARCHAR(191) NOT NULL,
    PRIMARY KEY (id)
) ENGINE=InnoDB");
promotion_mysql_exec($link, "CREATE TABLE students (
    id INT NOT NULL,
    is_active VARCHAR(10) NOT NULL,
    PRIMARY KEY (id)
) ENGINE=InnoDB");
promotion_mysql_exec($link, "CREATE TABLE student_session (
    id INT NOT NULL AUTO_INCREMENT,
    student_id INT NOT NULL,
    session_id INT NOT NULL,
    class_id INT NOT NULL,
    section_id INT NOT NULL,
    PRIMARY KEY (id),
    KEY scope_idx (session_id, class_id, section_id, student_id)
) ENGINE=InnoDB");
promotion_mysql_exec($link, "CREATE TABLE score (
    ID INT NOT NULL AUTO_INCREMENT,
    StudentID INT NOT NULL,
    ClassID INT NOT NULL,
    SectionID INT NOT NULL,
    SubjectID INT NOT NULL,
    Session INT NOT NULL,
    Term VARCHAR(20) NOT NULL,
    exam DECIMAL(7,2) DEFAULT NULL,
    ca1 DECIMAL(7,2) DEFAULT NULL,
    ca2 DECIMAL(7,2) DEFAULT NULL,
    ca3 DECIMAL(7,2) DEFAULT NULL,
    ca4 DECIMAL(7,2) DEFAULT NULL,
    ca5 DECIMAL(7,2) DEFAULT NULL,
    ca6 DECIMAL(7,2) DEFAULT NULL,
    ca7 DECIMAL(7,2) DEFAULT NULL,
    ca8 DECIMAL(7,2) DEFAULT NULL,
    ca9 DECIMAL(7,2) DEFAULT NULL,
    ca10 DECIMAL(7,2) DEFAULT NULL,
    PRIMARY KEY (ID),
    KEY score_scope_idx (StudentID, Session, ClassID, SectionID, Term, SubjectID)
) ENGINE=InnoDB");
promotion_mysql_exec($link, "CREATE TABLE britishresult (
    ID INT NOT NULL AUTO_INCREMENT,
    StudentID INT NOT NULL,
    ClassID INT NOT NULL,
    SectionID INT NOT NULL,
    SubjectID INT NOT NULL,
    Session INT NOT NULL,
    Term VARCHAR(20) NOT NULL,
    Remark VARCHAR(191) DEFAULT NULL,
    AdditionalComments TEXT DEFAULT NULL,
    PRIMARY KEY (ID)
) ENGINE=InnoDB");
promotion_mysql_exec($link, "CREATE TABLE kindergarten_result (
    id INT NOT NULL AUTO_INCREMENT,
    student_id INT NOT NULL,
    session_id INT NOT NULL,
    term VARCHAR(20) NOT NULL,
    assessment_id INT NOT NULL,
    subject_id INT NOT NULL,
    concept_id INT NOT NULL,
    result_label_index INT NOT NULL,
    PRIMARY KEY (id)
) ENGINE=InnoDB");
promotion_mysql_exec($link, "CREATE TABLE resultsetting (
    ResultSettingID INT NOT NULL,
    MidTermCaToUse VARCHAR(191) DEFAULT NULL,
    PRIMARY KEY (ResultSettingID)
) ENGINE=InnoDB");
promotion_mysql_exec($link, "CREATE TABLE assigncatoclass (
    id INT NOT NULL AUTO_INCREMENT,
    ClassID INT NOT NULL,
    ResultSettingID INT NOT NULL,
    ResultType VARCHAR(30) NOT NULL DEFAULT 'numeric',
    PRIMARY KEY (id)
) ENGINE=InnoDB");
promotion_mysql_exec($link, "CREATE TABLE gradingstructure (
    GradingStructureID INT NOT NULL,
    GradingTitle VARCHAR(191) NOT NULL,
    Grade VARCHAR(30) NOT NULL,
    Remark VARCHAR(191) DEFAULT NULL,
    RangeStart DECIMAL(7,2) NOT NULL,
    RangeEnd DECIMAL(7,2) NOT NULL,
    Type VARCHAR(30) NOT NULL,
    PRIMARY KEY (GradingStructureID)
) ENGINE=InnoDB");
promotion_mysql_exec($link, "CREATE TABLE assigngradingtclass (
    id INT NOT NULL AUTO_INCREMENT,
    GradingTitle VARCHAR(191) NOT NULL,
    ClassID INT NOT NULL,
    PRIMARY KEY (id)
) ENGINE=InnoDB");
promotion_mysql_exec($link, "CREATE TABLE promotion_criteria (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    session_id INT NOT NULL,
    name VARCHAR(191) NOT NULL,
    minimum_average DECIMAL(5,2) NOT NULL,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    PRIMARY KEY (id)
) ENGINE=InnoDB");
promotion_mysql_exec($link, "CREATE TABLE promotion_criteria_classes (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    criteria_id BIGINT UNSIGNED NOT NULL,
    session_id INT NOT NULL,
    class_id INT NOT NULL,
    promoted_to_class_id INT DEFAULT NULL,
    promoted_to_label VARCHAR(191) DEFAULT NULL,
    PRIMARY KEY (id),
    UNIQUE KEY criterion_class_scope (session_id, class_id)
) ENGINE=InnoDB");
promotion_mysql_exec($link, "CREATE TABLE promotion_criteria_subjects (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    criteria_id BIGINT UNSIGNED NOT NULL,
    subject_id INT NOT NULL,
    minimum_average DECIMAL(5,2) NOT NULL,
    PRIMARY KEY (id)
) ENGINE=InnoDB");
promotion_mysql_exec($link, "CREATE TABLE promotion_note_overrides (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    student_id INT NOT NULL,
    session_id INT NOT NULL,
    class_id INT NOT NULL,
    section_id INT NOT NULL,
    action VARCHAR(10) NOT NULL,
    decision VARCHAR(20) DEFAULT NULL,
    target_class_id INT DEFAULT NULL,
    target_label VARCHAR(191) DEFAULT NULL,
    reason TEXT NOT NULL,
    automatic_decision VARCHAR(20) NOT NULL,
    automatic_note VARCHAR(255) NOT NULL,
    created_by INT NOT NULL,
    created_at DATETIME NOT NULL,
    PRIMARY KEY (id),
    KEY override_scope_idx (student_id, session_id, class_id, section_id, id)
) ENGINE=InnoDB");

promotion_mysql_exec($link, "INSERT INTO classes (id, class) VALUES (7, 'Basic 1'), (8, 'Basic 2')");
promotion_mysql_exec($link, "INSERT INTO subjects (id, name) VALUES
    (101, 'English Language'), (102, 'Mathematics'), (103, 'Basic Science')");
promotion_mysql_exec($link, "INSERT INTO students (id, is_active) VALUES
    (1, 'yes'), (2, 'yes'), (3, 'yes'), (4, 'yes'), (5, 'no'), (6, 'yes')");
promotion_mysql_exec($link, "INSERT INTO student_session
    (id, student_id, session_id, class_id, section_id) VALUES
    (1, 1, 10, 7, 12),
    (2, 2, 10, 7, 12),
    (3, 3, 10, 7, 12),
    (4, 4, 10, 7, 12),
    (5, 5, 10, 7, 12),
    (6, 6, 10, 7, 13)");

promotion_mysql_exec($link, "INSERT INTO score
    (StudentID, ClassID, SectionID, SubjectID, Session, Term, exam, ca1) VALUES
    (1, 7, 12, 101, 10, '1st', NULL, 50),
    (1, 7, 12, 101, 10, '2nd', NULL, NULL),
    (1, 7, 12, 101, 10, '3rd', 50, 20),
    (1, 7, 12, 102, 10, '1st', 30, 10),
    (1, 7, 12, 102, 10, '2nd', 40, 20),
    (2, 7, 12, 101, 10, '3rd', 80, NULL),
    (3, 7, 12, 101, 10, '2nd', NULL, 45),
    (4, 7, 12, 101, 10, '3rd', NULL, NULL),
    (5, 7, 12, 101, 10, '3rd', 90, NULL),
    (6, 7, 13, 101, 10, '3rd', 75, NULL)");

promotion_mysql_exec($link, "INSERT INTO britishresult
    (StudentID, ClassID, SectionID, SubjectID, Session, Term, Remark, AdditionalComments) VALUES
    (1, 7, 12, 101, 10, '3rd', '', ''),
    (2, 7, 12, 101, 10, '3rd', 'Working Towards', ''),
    (3, 7, 12, 101, 10, '3rd', '', 'Shows steady progress'),
    (5, 7, 12, 101, 10, '3rd', 'Secure', ''),
    (6, 7, 13, 101, 10, '3rd', 'Secure', '')");

promotion_mysql_exec($link, "INSERT INTO kindergarten_result
    (student_id, session_id, term, assessment_id, subject_id, concept_id, result_label_index) VALUES
    (1, 10, '3rd', 20, 101, 1001, 2),
    (2, 10, '3rd', 21, 101, 1001, 2),
    (3, 10, '2nd', 20, 101, 1001, 2),
    (4, 10, '3rd', 20, 101, 1001, 1),
    (5, 10, '3rd', 20, 101, 1001, 2),
    (6, 10, '3rd', 20, 101, 1001, 2)");

promotion_mysql_exec($link, "INSERT INTO resultsetting (ResultSettingID, MidTermCaToUse) VALUES (1, '1,1')");
promotion_mysql_exec($link, "INSERT INTO assigncatoclass (ClassID, ResultSettingID, ResultType) VALUES (7, 1, 'numeric')");
promotion_mysql_exec($link, "INSERT INTO gradingstructure
    (GradingStructureID, GradingTitle, Grade, Remark, RangeStart, RangeEnd, Type) VALUES
    (1, 'Term 100', 'A', 'Excellent', 70, 100, 'term'),
    (2, 'Term 100', 'B', 'Very good', 60, 69.99, 'term'),
    (3, 'Term 100', 'C', 'Good', 50, 59.99, 'term'),
    (4, 'Term 100', 'F', 'Needs support', 0, 49.99, 'term'),
    (5, 'Midterm 20', 'P1', 'Excellent', 16, 20, 'midterm'),
    (6, 'Midterm 20', 'P2', 'Developing', 0, 15.99, 'midterm')");
promotion_mysql_exec($link, "INSERT INTO assigngradingtclass (GradingTitle, ClassID) VALUES
    ('Term 100', 7), ('Midterm 20', 7)");

promotion_mysql_exec($link, "INSERT INTO promotion_criteria
    (id, session_id, name, minimum_average, is_active) VALUES
    (1, 10, 'Basic promotion', 55.00, 1)");
promotion_mysql_exec($link, "INSERT INTO promotion_criteria_classes
    (criteria_id, session_id, class_id, promoted_to_class_id, promoted_to_label) VALUES
    (1, 10, 7, 8, NULL)");
promotion_mysql_exec($link, "INSERT INTO promotion_criteria_subjects
    (criteria_id, subject_id, minimum_average) VALUES
    (1, 101, 60.00)");

$studentSessionBefore = promotion_mysql_student_session_snapshot($link);

$annual = get_student_annual_score_data($link, 1, 10, 7, 12);
promotion_mysql_assert_same(true, $annual['has_data'], 'Annual evaluation must find scored academic rows.');
promotion_mysql_assert_same(4, $annual['score_count'], 'The all-null legacy placeholder must be excluded from annual averages.');
promotion_mysql_assert_same(55.0, $annual['overall_average'], 'Annual average must reuse the row-weighted cumulative calculation.');
promotion_mysql_assert_same(60.0, $annual['subject_averages'][101], 'Priority subjects must average their scored term rows.');
promotion_mysql_assert_same(50.0, $annual['subject_averages'][102], 'Each priority subject average must remain isolated by subject.');

promotion_mysql_exec($link, "INSERT INTO score
    (StudentID, ClassID, SectionID, SubjectID, Session, Term, exam, ca1) VALUES
    (1, 7, 12, 101, 10, '3rd', 55, NULL)");
$duplicateSameTermScoreId = (int) mysqli_insert_id($link);
$annualWithDuplicateSameTerm = get_student_annual_score_data($link, 1, 10, 7, 12);
$cumulativeScoresWithDuplicateSameTerm = get_result_subject_scores($link, 1, 10, 7, 12, '3rd', 'cummulative');
promotion_mysql_assert_same(
    55.0,
    $annualWithDuplicateSameTerm['overall_average'],
    'The overall annual average must retain its existing scored-row weighting.'
);
promotion_mysql_assert_same(
    87.5,
    $annualWithDuplicateSameTerm['subject_averages'][101],
    'A priority subject must divide combined scores by distinct scored terms, not duplicate same-term rows.'
);
promotion_mysql_assert_same(
    87.5,
    $cumulativeScoresWithDuplicateSameTerm[101],
    'The cumulative grade summary must reuse the distinct-scored-term subject average shown in the result table.'
);
promotion_mysql_exec($link, "DELETE FROM score WHERE ID = '$duplicateSameTermScoreId'");

$placeholderAnnual = get_student_annual_score_data($link, 4, 10, 7, 12);
promotion_mysql_assert_same(false, $placeholderAnnual['has_data'], 'An all-zero/all-null score placeholder must remain missing.');
promotion_mysql_assert_same(null, $placeholderAnnual['overall_average'], 'A placeholder-only learner must not receive a zero annual average.');

$automatic = get_promotion_automatic_outcome($link, 1, 10, 7, 12, false);
promotion_mysql_assert_same('promoted', $automatic['decision'], 'Meeting the overall and priority boundaries must promote.');
promotion_mysql_assert_same('PROMOTED TO: Basic 2', $automatic['note'], 'The configured target class must appear in the automatic note.');
promotion_mysql_assert_same(55.0, $automatic['overall_average'], 'Review and result decisions must reuse the same annual average.');
promotion_mysql_assert_same(60.0, $automatic['priority_results'][0]['average'], 'The review outcome must expose the priority-subject score.');

$qualitative = get_promotion_automatic_outcome($link, 1, 10, 7, 12, true);
promotion_mysql_assert_same('pending', $qualitative['decision'], 'British and kindergarten automatic decisions must remain pending.');
promotion_mysql_assert_same('qualitative_result', $qualitative['reason_code'], 'Qualitative pending results must expose the correct reason.');

promotion_mysql_exec($link, "INSERT INTO promotion_note_overrides
    (student_id, session_id, class_id, section_id, action, decision, target_class_id, target_label,
     reason, automatic_decision, automatic_note, created_by, created_at) VALUES
    (1, 10, 7, 12, 'set', 'not_promoted', NULL, NULL,
     'Approved retention', 'promoted', 'PROMOTED TO: Basic 2', 44, '2026-08-16 10:00:00')");
$overridden = get_final_promotion_outcome($link, 1, 10, 7, 12, false);
promotion_mysql_assert_same('not_promoted', $overridden['decision'], 'The latest set action must override the system decision.');
promotion_mysql_assert_same('override', $overridden['source'], 'A set action must identify Override as its source.');

promotion_mysql_exec($link, "INSERT INTO promotion_note_overrides
    (student_id, session_id, class_id, section_id, action, decision, target_class_id, target_label,
     reason, automatic_decision, automatic_note, created_by, created_at) VALUES
    (1, 10, 7, 12, 'clear', NULL, NULL, NULL,
     'Return to current criteria', 'promoted', 'PROMOTED TO: Basic 2', 44, '2026-08-16 10:01:00')");
$cleared = get_final_promotion_outcome($link, 1, 10, 7, 12, false);
promotion_mysql_assert_same('promoted', $cleared['decision'], 'The latest clear action must restore the current automatic decision.');
promotion_mysql_assert_same('system', $cleared['source'], 'A cleared decision must identify System as its source.');

promotion_mysql_exec($link, "INSERT INTO promotion_note_overrides
    (student_id, session_id, class_id, section_id, action, decision, target_class_id, target_label,
     reason, automatic_decision, automatic_note, created_by, created_at) VALUES
    (1, 10, 7, 12, 'set', 'promoted', NULL, 'Graduated',
     'Final-year completion', 'promoted', 'PROMOTED TO: Basic 2', 44, '2026-08-16 10:02:00')");
$customTarget = get_final_promotion_outcome($link, 1, 10, 7, 12, false);
promotion_mysql_assert_same('PROMOTED TO: Graduated', $customTarget['note'], 'The latest custom target must take precedence on both result types.');

promotion_mysql_assert_same(
    2,
    get_result_bearing_class_count($link, 10, 7, 12, '3rd', 'termly', 'numeric'),
    'Numeric termly NO. must count active exact-roster learners with non-placeholder scores.'
);
promotion_mysql_assert_same(
    1,
    get_result_bearing_class_count($link, 10, 7, 12, '3rd', 'midterm', 'numeric'),
    'Midterm NO. must use only configured CA columns and ignore exam-only rows.'
);
promotion_mysql_assert_same(
    3,
    get_result_bearing_class_count($link, 10, 7, 12, '3rd', 'cummulative', 'numeric'),
    'Cumulative NO. must count active learners with a result-bearing row in any scored term.'
);
promotion_mysql_assert_same(
    2,
    get_result_bearing_class_count($link, 10, 7, 12, '3rd', 'termly', 'british'),
    'British NO. must exclude blank roster placeholders, inactive learners, and other sections.'
);
promotion_mysql_assert_same(
    2,
    get_result_bearing_class_count($link, 10, 7, 12, '3rd', 'termly', 'kindergarten', 20),
    'Kindergarten NO. must respect assessment, term, active roster, class, and section scope.'
);

$numericContext = get_result_summary_context($link, 1, 10, 7, 12, '3rd', 'termly', 'numeric');
promotion_mysql_assert_same(2, $numericContext['number_in_class'], 'Numeric context must reuse the exact result-bearing count.');
promotion_mysql_assert_same('1A', $numericContext['grade_summary'], 'Numeric context must count the student grades in configured order.');
promotion_mysql_assert_same('70% and Above', $numericContext['grade_key'][0]['range'], 'A 100-point grade key must use percentage notation.');
promotion_mysql_assert_same(55.0, $numericContext['cumulative_average'], 'Third-term summary must reuse the annual average.');

$midtermContext = get_result_summary_context($link, 1, 10, 7, 12, '3rd', 'midterm', 'numeric');
promotion_mysql_assert_same(1, $midtermContext['number_in_class'], 'Midterm context must reuse its result-specific class count.');
promotion_mysql_assert_same('1P1', $midtermContext['grade_summary'], 'Midterm summary must grade only configured CA components.');
promotion_mysql_assert_same('16 - 20', $midtermContext['grade_key'][0]['range'], 'Raw midterm keys must preserve their configured scale.');

$britishContext = get_result_summary_context($link, 2, 10, 7, 12, '3rd', 'termly', 'british');
promotion_mysql_assert_same(2, $britishContext['number_in_class'], 'British context must expose its exact result-bearing class count.');
promotion_mysql_assert_same(array(), $britishContext['grade_key'], 'British cards must omit numeric grade keys.');
promotion_mysql_assert_same('', $britishContext['grade_summary'], 'British cards must omit numeric grade summaries.');
promotion_mysql_assert_same(null, $britishContext['cumulative_average'], 'British cards must omit numeric cumulative averages.');

$kindergartenContext = get_result_summary_context($link, 1, 10, 7, 12, '3rd', 'termly', 'kindergarten', 20);
promotion_mysql_assert_same(2, $kindergartenContext['number_in_class'], 'Kindergarten context must expose its exact result-bearing class count.');
promotion_mysql_assert_same(array(), $kindergartenContext['grade_key'], 'Kindergarten cards must omit numeric grade keys.');
promotion_mysql_assert_same('', $kindergartenContext['grade_summary'], 'Kindergarten cards must omit numeric grade summaries.');
promotion_mysql_assert_same(null, $kindergartenContext['cumulative_average'], 'Kindergarten cards must omit numeric cumulative averages.');

$studentSessionAfter = promotion_mysql_student_session_snapshot($link);
promotion_mysql_assert_same(
    $studentSessionBefore,
    $studentSessionAfter,
    'Evaluation, review, count, and override resolution must never mutate student_session.'
);

echo "promotion MySQL integration tests passed" . PHP_EOL;
