<?php
include('../../database/config.php');
// $classsection = $_POST['classsection'];

$classsection = $_POST['classsectionactual'];

$classid = $_POST['classid'];

$session = $_POST['session'];

$term = $_POST['term'];

$sqlGetstudent_session = "SELECT * FROM `student_session` INNER JOIN students ON student_session.student_id=students.id WHERE session_id='$session' AND class_id = '$classid' AND section_id = '$classsection'";

$queryGetstudent_session = mysqli_query($link, $sqlGetstudent_session);
$rowGetstudent_session = mysqli_fetch_assoc($queryGetstudent_session);
$countGetstudent_session = mysqli_num_rows($queryGetstudent_session);

if ($countGetstudent_session > 0) {
    do {
        echo $studentid = $rowGetstudent_session['student_id'];

        $sqlGetscore = "SELECT * FROM `psycomotor_score` WHERE studentid='$studentid' AND classid = '$classid' AND sectionid = '$classsection' AND session = '$session' AND term = '$term'";
        $queryGetscore = mysqli_query($link, $sqlGetscore);
        $rowGetscore = mysqli_fetch_assoc($queryGetscore);
        $countGetscore = mysqli_num_rows($queryGetscore);

        if ($countGetscore > 0) {
        } else {

            $sqlInsert = ("INSERT INTO `psycomotor_score` (`studentid`, `classid`, `sectionid`, `session`, `term`) 
                    VALUES ('$studentid','$classid','$classsection','$session','$term')");

            if (mysqli_query($link, $sqlInsert)) {
            } else {
            }
        }
    } while ($rowGetstudent_session = mysqli_fetch_assoc($queryGetstudent_session));

    // 2) CLEANUP: delete stale score rows for this session/class/subject/term where the student is NOT in the current student_session
    // This removes score rows for students who have left the class/section for this session,
    // so they won't appear when you query for current class/section.
    $sqlDeleteStale = "
        DELETE FROM psycomotor_score
        WHERE session = '$session'
            AND classid = '$classid'
            AND term = '$term'
            AND sectionid = '$classsection'
            AND studentid NOT IN (
                SELECT student_id FROM student_session
                WHERE session_id = '$session' AND class_id = '$classid' AND section_id = '$classsection'
            )
    ";
    if (mysqli_query($link, $sqlDeleteStale)) {
        echo "cleanup done: stale scores removed<br>";
    } else {
        echo "cleanup failed: " . mysqli_error($link) . '<br>';
    }
} else {
    // 2) CLEANUP: delete stale score rows for this session/class/subject/term where the student is NOT in the current student_session
    // This removes score rows for students who have left the class/section for this session,
    // so they won't appear when you query for current class/section.
    $sqlDeleteStale = "
            DELETE FROM psycomotor_score
            WHERE session = '$session'
                AND classid = '$classid'
                AND term = '$term'
                AND sectionid = '$classsection'
                AND studentid NOT IN (
                    SELECT student_id FROM student_session
                    WHERE session_id = '$session' AND class_id = '$classid' AND section_id = '$classsection'
                )
        ";
    if (mysqli_query($link, $sqlDeleteStale)) {
        echo "cleanup done: stale scores removed<br>";
    } else {
        echo "cleanup failed: " . mysqli_error($link) . '<br>';
    }
    echo "Error: " . $sqlGetstudent_session . "<br>" . mysqli_error($link);
}
