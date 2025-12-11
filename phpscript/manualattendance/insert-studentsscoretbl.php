<?php
include('../../database/config.php');
$classsection = $_POST['classsection'];

$classid = $_POST['classid'];

$session = $_POST['session'];

$term = $_POST['term'];
//$sqlGetstudent_session = "SELECT * FROM `student_session` INNER JOIN students ON student_session.student_id=students.id WHERE session_id='$session' AND class_id = '$classid' AND section_id = '$classsectionactual'";

$sqlGetstudent_session = "SELECT * FROM `student_session` INNER JOIN students ON student_session.student_id=students.id WHERE session_id='$session' AND class_id = '$classid' AND section_id = '$classsection'";

$queryGetstudent_session = mysqli_query($link, $sqlGetstudent_session);
$rowGetstudent_session = mysqli_fetch_assoc($queryGetstudent_session);
$countGetstudent_session = mysqli_num_rows($queryGetstudent_session);

if ($countGetstudent_session > 0) {
    do {
        echo $studentid = $rowGetstudent_session['student_id'];

        $sqlGetscore = "SELECT * FROM `score` WHERE StudentID='$studentid' AND ClassID = '$classid' AND SectionID = '$classsection' AND SubjectID = '0' AND Session = '$session' AND Term = '$term'";
        $queryGetscore = mysqli_query($link, $sqlGetscore);
        $rowGetscore = mysqli_fetch_assoc($queryGetscore);
        $countGetscore = mysqli_num_rows($queryGetscore);

        if ($countGetscore > 0) {
        } else {

            $sqlInsert = ("INSERT INTO `score` (`StudentID`, `ClassID`, `SectionID`, `SubjectID`, `Session`, `Term`)
                VALUES ('$studentid','$classid','$classsection','0','$session','$term')");

            if (mysqli_query($link, $sqlInsert)) {
            } else {
            }
        }

        $sqlDeleteStale = "
            DELETE FROM score
            WHERE `Session` = '$session'
                AND ClassID = '$classid'
                AND SubjectID = '0'
                AND Term = '$term'
                AND SectionID = '$classsection'
                AND StudentID NOT IN (
                    SELECT student_id FROM student_session
                    WHERE session_id = '$session' AND class_id = '$classid' AND section_id = '$classsection'
                )
        ";
        if (mysqli_query($link, $sqlDeleteStale)) {
            echo "cleanup done: stale scores removed<br>";
        } else {
            echo "cleanup failed: " . mysqli_error($link) . '<br>';
        }
    } while ($rowGetstudent_session = mysqli_fetch_assoc($queryGetstudent_session));
} else {
    $sqlDeleteStale = "
        DELETE FROM score
        WHERE `Session` = '$session'
            AND ClassID = '$classid'
            AND SubjectID = '0'
            AND Term = '$term'
            AND SectionID = '$classsection'
            AND StudentID NOT IN (
                SELECT student_id FROM student_session
                WHERE session_id = '$session' AND class_id = '$classid' AND section_id = '$classsection'
            )
    ";
    if (mysqli_query($link, $sqlDeleteStale)) {
        echo "cleanup done: stale scores removed<br>";
    } else {
        echo "cleanup failed: " . mysqli_error($link) . '<br>';
    }
    //echo "Error: " . $sqlGetstudent_session . "<br>" . mysqli_error($link);
}
