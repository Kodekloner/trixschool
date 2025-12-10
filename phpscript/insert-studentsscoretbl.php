<?php

ini_set('display_errors', 1);
error_reporting(E_ALL);
header('Content-Type: application/json; charset=UTF-8');

include('../database/config.php');

$debug = [
    'received_post' => $_POST,
    'queries' => []
];

// helper to push query debug info
function push_query_debug(&$debug, $sql, $result = null, $error = null)
{
    $entry = ['sql' => $sql];
    if (!is_null($error)) $entry['error'] = $error;
    if (is_null($result)) {
        $entry['count'] = 0;
        $entry['rows'] = [];
    } elseif (is_bool($result)) {
        // e.g. INSERT return true/false
        $entry['success'] = $result;
        $entry['affected_rows'] = mysqli_affected_rows($GLOBALS['link']);
    } else {
        // assume mysqli_result
        $rows = [];
        while ($r = mysqli_fetch_assoc($result)) $rows[] = $r;
        $entry['count'] = count($rows);
        $entry['rows'] = $rows;
    }
    $debug['queries'][] = $entry;
}

$classsection = $_POST['classsection'];

$classsectionactual = $_POST['classsectionactual'];

$classid = $_POST['classid'];

$session = $_POST['session'];

$term = $_POST['term'];

$subjects = $_POST['subjects'];

$sqlGetstudent_session = "SELECT * FROM `student_session` INNER JOIN students ON student_session.student_id=students.id WHERE session_id='$session' AND class_id = '$classid' AND section_id = '$classsectionactual'";

$queryGetstudent_session = mysqli_query($link, $sqlGetstudent_session);
if (!$queryGetstudent_session) {
    push_query_debug($debug, $sqlGetstudent_session, null, mysqli_error($link));
    echo json_encode($debug, JSON_PRETTY_PRINT);
    exit;
}
push_query_debug($debug, $sqlGetstudent_session, $queryGetstudent_session);
// $rowGetstudent_session = mysqli_fetch_assoc($queryGetstudent_session);
$countGetstudent_session = mysqli_num_rows($queryGetstudent_session);

if ($countGetstudent_session > 0) {
    do {
        $studentid = $rowGetstudent_session['student_id'];

        $sqlGetscore = "SELECT * FROM `score` WHERE StudentID='$studentid' AND ClassID = '$classid' AND SectionID = '$classsectionactual' AND SubjectID = '$subjects' AND Session = '$session' AND Term = '$term'";
        $queryGetscore = mysqli_query($link, $sqlGetscore);
        $rowGetscore = mysqli_fetch_assoc($queryGetscore);
        $countGetscore = mysqli_num_rows($queryGetscore);
        echo $countGetscore;
        if ($countGetscore > 0) {
        } else {

            $sqlInsert = ("INSERT INTO `score` (`StudentID`, `ClassID`, `SectionID`, `SubjectID`, `Session`, `Term`) 
            VALUES ('$studentid','$classid','$classsectionactual','$subjects','$session','$term')");

            if (mysqli_query($link, $sqlInsert)) {
            } else {
            }
        }
    } while ($rowGetstudent_session = mysqli_fetch_assoc($queryGetstudent_session));
} else {
    echo "Error: " . $sqlGetstudent_session . "<br>" . mysqli_error($link);
}
