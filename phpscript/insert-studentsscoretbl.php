<?php

// DEV debugging helpers — remove in production

include('../database/config.php');
$classsection = $_POST['classsection'];

$classsectionactual = $_POST['classsectionactual'];

$classid = $_POST['classid'];

$session = $_POST['session'];

$term = $_POST['term'];

$subjects = $_POST['subjects'];

$sqlGetstudent_session = "SELECT * FROM `student_session` INNER JOIN students ON student_session.student_id=students.id WHERE session_id='$session' AND class_id = '$classid' AND section_id = '$classsectionactual'";

$queryGetstudent_session = mysqli_query($link, $sqlGetstudent_session);
$rowGetstudent_session = mysqli_fetch_assoc($queryGetstudent_session);
$countGetstudent_session = mysqli_num_rows($queryGetstudent_session);

echo $countGetstudent_session;

if ($countGetstudent_session > 0) {
    do {
        $studentid = $rowGetstudent_session['student_id'];

        $sqlGetscore = "SELECT * FROM `score` WHERE StudentID='$studentid' AND ClassID = '$classid' AND SectionID = '$classsectionactual' AND SubjectID = '$subjects' AND Session = '$session' AND Term = '$term'";
        $queryGetscore = mysqli_query($link, $sqlGetscore);
        $rowGetscore = mysqli_fetch_assoc($queryGetscore);
        $countGetscore = mysqli_num_rows($queryGetscore);

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
