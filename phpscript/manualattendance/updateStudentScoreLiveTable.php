<?php
    include ('../../database/config.php');
    
    $ID=$_POST['ID'];
	$ca1 = isset($_POST['ca1']) && is_numeric($_POST['ca1']) ? $_POST['ca1'] : 0;
$ca2 = isset($_POST['ca2']) && is_numeric($_POST['ca2']) ? $_POST['ca2'] : 0;
$ca3 = isset($_POST['ca3']) && is_numeric($_POST['ca3']) ? $_POST['ca3'] : 0;
$ca4 = isset($_POST['ca4']) && is_numeric($_POST['ca4']) ? $_POST['ca4'] : 0;

    $term=$_POST['term'];
    $session=$_POST['session'];


	$sqlGetStudentVitals = "SELECT * FROM `score` WHERE ID='$ID'";
	$queryGetStudentVitals = mysqli_query($link, $sqlGetStudentVitals);
	$rowGetStudentVitals = mysqli_fetch_assoc($queryGetStudentVitals);
	$countGetStudentVitals = mysqli_num_rows($queryGetStudentVitals);

	$sqlUpdateScore ="UPDATE `score` SET `ca1` = '$ca1', `ca2` = '$ca2', `ca3` = '$ca3', `ca4` = '$ca4' WHERE `ID` = '$ID'";
	$queryUpdateScore = mysqli_query($link, $sqlUpdateScore);
	echo $sqlUpdateScore;
?>
