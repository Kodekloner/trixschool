<?php

namespace TeacherManualCommentTest;

// Exercise the real endpoints with an in-memory query adapter. No school database
// or database/config.php is loaded, and no student records are changed.
class Rows
{
    public $rows;
    public $offset = 0;

    public function __construct(array $rows)
    {
        $this->rows = $rows;
    }
}

$comments = [];
$queries = [];
$subjectScoreCalls = [];
$subjectScores = [1 => 60, 2 => 80];

function check($condition, $message)
{
    if (!$condition) {
        throw new \RuntimeException($message);
    }
}

function matches_scope(array $row, $sql)
{
    foreach (['StaffID', 'RemarkType', 'StudentID', 'Session', 'Term', 'ResultSubType'] as $field) {
        if (preg_match('/`?' . $field . "`?\\s*=\\s*'([^']*)'/i", $sql, $match)
            && (string) $row[$field] !== $match[1]) {
            return false;
        }
    }
    return true;
}

function mysqli_query($link, $sql)
{
    global $comments, $queries;
    $sql = preg_replace('/\s+/', ' ', trim($sql));
    $queries[] = $sql;
    if (preg_match('/^SELECT \* FROM `?remark`? /', $sql)) {
        return new Rows(array_values(array_filter($comments, function ($row) use ($sql) {
            return matches_scope($row, $sql);
        })));
    }
    if (strpos($sql, 'INSERT INTO `remark`') === 0) {
        $values = substr($sql, strpos($sql, 'VALUES'));
        preg_match_all("/'([^']*)'/", $values, $matches);
        $comments[] = array_combine(
            ['StaffID', 'RemarkType', 'remark', 'StudentID', 'Session', 'Term', 'ResultSubType'],
            $matches[1]
        );
        return true;
    }
    if (strpos($sql, 'UPDATE `remark`') === 0) {
        preg_match("/SET `remark`='([^']*)'/", $sql, $match);
        $scope = substr($sql, strpos($sql, 'WHERE'));
        foreach ($comments as &$row) {
            if (matches_scope($row, $scope)) {
                $row['remark'] = $match[1];
            }
        }
        return true;
    }
    // The existing list endpoint removes stale scores; keep all test writes in memory.
    if (strpos($sql, 'DELETE FROM score') === 0) return true;
    if (strpos($sql, 'FROM class_sections') !== false || strpos($sql, 'FROM `class_sections`') !== false) {
        return new Rows([['id' => 8, 'section_id' => 2, 'class_id' => 4]]);
    }
    if (strpos($sql, 'FROM `classes`') !== false) return new Rows([['class' => 'Grade 1']]);
    if (strpos($sql, 'FROM `sessions`') !== false) return new Rows([['session' => '2026-27']]);
    if (strpos($sql, 'FROM `assigncatoclass`') !== false || strpos($sql, 'FROM assigncatoclass') !== false) {
        return new Rows([['ResultType' => 'alphabetic', 'ResultSettingID' => 1]]);
    }
    if (strpos($sql, 'FROM kindergarten_assignment') !== false) return new Rows([]);
    if (strpos($sql, 'FROM resultsetting') !== false) return new Rows([['MidTermCaToUse' => '1', 'CA1Score' => 20]]);
    if (strpos($sql, 'INNER JOIN students s') !== false) {
        return new Rows([['StudentID' => 101, 'admission_no' => 'TEST-101', 'lastname' => 'Test', 'middlename' => '', 'firstname' => 'Student']]);
    }
    if (strpos($sql, 'AS total_midterm') !== false) return new Rows([['subj_count' => 2, 'total_midterm' => 32]]);
    if (strpos($sql, 'AS subj_count') !== false) return new Rows([['subj_count' => 2]]);
    if (strpos($sql, 'AS total') !== false) return new Rows([['total' => 150]]);
    if (strpos($sql, 'gradingstructure') !== false) return new Rows([['Grade' => 'A']]);
    throw new \RuntimeException('Unexpected query: ' . $sql);
}

function mysqli_fetch_assoc($result)
{
    return $result->rows[$result->offset++] ?? null;
}

function mysqli_num_rows($result)
{
    return count($result->rows);
}

function mysqli_real_escape_string($link, $value)
{
    return addslashes($value);
}

function get_result_subject_scores($link, $student, $session, $class, $section, $term, $type)
{
    global $subjectScoreCalls, $subjectScores;
    $subjectScoreCalls[] = [$student, $session, $class, $section, $term, $type];
    return $subjectScores;
}

function run_endpoint($filename, array $payload)
{
    $_POST = $payload;
    $link = null;
    $source = file_get_contents(__DIR__ . '/../phpscript/' . $filename);
    $source = str_replace("include('../database/config.php');", '', $source, $replacements);
    check($replacements === 1, 'The real database configuration must be excluded.');
    ob_start();
    try {
        eval('namespace ' . __NAMESPACE__ . '; ?>' . $source);
        return ob_get_contents();
    } finally {
        ob_end_clean();
    }
}

$originalDirectory = getcwd();
chdir(__DIR__ . '/../phpscript');
try {
    $payload = [
        'studentid' => '101', 'extcomment' => '', 'session' => '12', 'term' => '1st',
        'staffid' => '11', 'RemarkType' => 'teacher', 'classid' => '4',
        'classsectionactual' => '2', 'classsection' => '8',
    ];
    // Each report must keep its own comment, including third-term Termly versus Cumulative.
    $cases = [
        ['termly', '1st', 'First term comment'],
        ['mid-term', '1st', 'Midterm comment'],
        ['termly', '3rd', 'Third term comment'],
        ['cumulative', '1st', 'Annual <focus> & practice'],
    ];
    foreach ($cases as [$type, $term, $comment]) {
        $request = array_merge($payload, ['resultType' => $type, 'term' => $term, 'extcomment' => $comment]);
        run_endpoint('updateStudentmancomment.php', $request);
        $html = run_endpoint('view-studentmanualcomment.php', $request);
        check(strpos($html, htmlspecialchars($comment)) !== false, "$type comment must reload in its own type.");
    }
    check(count($comments) === 4, 'Saving Cumulative must not overwrite Termly or Midterm.');
    check($comments[3]['ResultSubType'] === 'cummulative', 'Cumulative must use the same stored type as the report.');
    check($comments[3]['Term'] === '3rd', 'The server must enforce the cumulative report term.');
    check($comments[1]['ResultSubType'] === 'midterm', 'The mid-term alias must normalize.');
    check($subjectScoreCalls[0] === [101, '12', '4', '2', '3rd', 'cummulative'], 'Cumulative scores must use the report calculation and scope.');
    check(strpos($html, '<th>Cumulative Average</th>') !== false && strpos($html, '<td>70</td>') !== false, 'The cumulative list must show its annual average.');

    $request = array_merge($payload, ['resultType' => 'cummulative', 'term' => '2nd', 'extcomment' => 'Updated annual comment']);
    run_endpoint('updateStudentmancomment.php', $request);
    check(count($comments) === 4 && $comments[3]['remark'] === 'Updated annual comment', 'The legacy spelling must update the same annual comment.');
    check($comments[2]['remark'] === 'Third term comment', 'Annual updates must leave the third-term comment intact.');

    $request['staffid'] = '22';
    check(strpos(run_endpoint('view-studentmanualcomment.php', $request), 'Updated annual comment') === false, 'Another staff selection must not load this teacher comment.');
    $request['staffid'] = '11';
    $request['session'] = '13';
    check(strpos(run_endpoint('view-studentmanualcomment.php', $request), 'Updated annual comment') === false, 'Another session must not load this comment.');

    $subjectScores = [];
    $html = run_endpoint('view-studentmanualcomment.php', $request);
    check(strpos($html, '<td>NA</td>') !== false, 'Students without cumulative scores must still have an editable comment.');
    echo 'Teacher manual comment flow tests passed (isolated saves, reloads, aliases, scope, cumulative average).' . PHP_EOL;
} finally {
    chdir($originalDirectory);
}
