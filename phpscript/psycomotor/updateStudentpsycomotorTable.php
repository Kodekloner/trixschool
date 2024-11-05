<?php
    include ('../../database/config.php');
    
    $ID=$_POST['ID'];
    $ca1=$_POST['ca1'];
    $ca2=$_POST['ca2'];
    $ca3=$_POST['ca3'];
    $ca4=$_POST['ca4'];
    $ca5=$_POST['ca5'];
    $ca6=$_POST['ca6'];
    $ca7=$_POST['ca7'];
    $ca8=$_POST['ca8'];
    $ca9=$_POST['ca9'];
    $ca10=$_POST['ca10'];
    $ca11=$_POST['ca11'];
    $ca12=$_POST['ca12'];
    $ca13=$_POST['ca13'];
    $ca14=$_POST['ca14'];
    $ca15=$_POST['ca15'];
    $term=$_POST['term'];
    $session=$_POST['session'];


	$sqlGetStudentVitals = "SELECT * FROM `psycomotor_score` WHERE id='$ID'";
	$queryGetStudentVitals = mysqli_query($link, $sqlGetStudentVitals);
	$rowGetStudentVitals = mysqli_fetch_assoc($queryGetStudentVitals);
	$countGetStudentVitals = mysqli_num_rows($queryGetStudentVitals);

	$sqlUpdateScore ="UPDATE `psycomotor_score` SET `psycomotor1` = '$ca1', `psycomotor2` = '$ca2', `psycomotor3` = '$ca3', `psycomotor4` = '$ca4', `psycomotor5` = '$ca5', `psycomotor6` = '$ca6', `psycomotor7` = '$ca7', `psycomotor8` = '$ca8', `psycomotor9` = '$ca9', `psycomotor10` = '$ca10', `psycomotor11` = '$ca11', `psycomotor12` = '$ca12', `psycomotor13` = '$ca13', `psycomotor14` = '$ca14', `psycomotor15` = '$ca15' WHERE `id` = '$ID' AND term = '$term' AND session = '$session'";
	$queryUpdateScore = mysqli_query($link, $sqlUpdateScore);

?>