<?php
include('../database/config.php');

$ID = $_POST['ID'];
$ca1 = $_POST['ca1'];
$ca2 = $_POST['ca2'];
$ca3 = $_POST['ca3'];
$ca4 = $_POST['ca4'];
$ca5 = $_POST['ca5'];
$ca6 = $_POST['ca6'];
$ca7 = $_POST['ca7'];
$ca8 = $_POST['ca8'];
$ca9 = $_POST['ca9'];
$ca10 = $_POST['ca10'];
$exam = $_POST['exam'];
$term = $_POST['term'];
$session = $_POST['session'];

$total = $ca1 + $ca2 + $ca3 + $ca4 + $ca5 + $ca6 + $ca7 + $ca8 + $ca9 + $ca10 + $exam;

$sqlGetStudentVitals = "SELECT * FROM `score` WHERE ID='$ID'";
$queryGetStudentVitals = mysqli_query($link, $sqlGetStudentVitals);
$rowGetStudentVitals = mysqli_fetch_assoc($queryGetStudentVitals);
$countGetStudentVitals = mysqli_num_rows($queryGetStudentVitals);

$sqlUpdateScore = "UPDATE `score` SET `Exam` = '$exam', `CA1` = '$ca1', `CA2` = '$ca2', `CA3` = '$ca3', `CA4` = '$ca4', `CA5` = '$ca5', `CA6` = '$ca6', `CA7` = '$ca7', `CA8` = '$ca8', `CA9` = '$ca9', `CA10` = '$ca10' WHERE `ID` = '$ID' AND Term = '$term' AND Session = '$session'";
$queryUpdateScore = mysqli_query($link, $sqlUpdateScore);
if (!$queryUpdateScore) {
    echo "MySQL ERROR: " . mysqli_error($link);
    echo "<br>SQL: " . $sqlUpdateScore;
    exit;
}
