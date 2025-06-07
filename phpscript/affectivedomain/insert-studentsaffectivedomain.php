<?php
include('../../database/config.php');
$classsection = $_POST['classsectionactual'];

$classid = $_POST['classid'];

$session = $_POST['session'];

$term = $_POST['term'];

$sqlGetstudent_session = "SELECT *, CONCAT(students.lastname, ' ', COALESCE(students.middlename, ''), ' ', students.firstname) AS full_name FROM `student_session` INNER JOIN students ON student_session.student_id=students.id WHERE session_id='$session' AND class_id = '$classid' AND section_id = '$classsection' AND students.is_active = 'yes' ORDER BY full_name ASC";

$queryGetstudent_session = mysqli_query($link, $sqlGetstudent_session);
$rowGetstudent_session = mysqli_fetch_assoc($queryGetstudent_session);
$countGetstudent_session = mysqli_num_rows($queryGetstudent_session);

if ($countGetstudent_session > 0) {
    do {
        echo $studentid = $rowGetstudent_session['student_id'];

        $sqlGetscore = "SELECT * FROM `affective_domain_score` WHERE studentid='$studentid' AND classid = '$classid' AND sectionid = '$classsection' AND session = '$session' AND term = '$term'";
        $queryGetscore = mysqli_query($link, $sqlGetscore);
        $rowGetscore = mysqli_fetch_assoc($queryGetscore);
        $countGetscore = mysqli_num_rows($queryGetscore);

        if ($countGetscore > 0) {
        } else {

            $sqlInsert = ("INSERT INTO `affective_domain_score` (`studentid`, `classid`, `sectionid`, `session`, `term`) 
                VALUES ('$studentid','$classid','$classsection','$session','$term')");

            if (mysqli_query($link, $sqlInsert)) {
            } else {
            }
        }
    } while ($rowGetstudent_session = mysqli_fetch_assoc($queryGetstudent_session));
} else {
    echo "Error: " . $sqlGetstudent_session . "<br>" . mysqli_error($link);
}
