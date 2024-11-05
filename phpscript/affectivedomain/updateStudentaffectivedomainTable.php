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


	$sqlGetStudentVitals = "SELECT * FROM `affective_domain_score` WHERE id='$ID'";
	$queryGetStudentVitals = mysqli_query($link, $sqlGetStudentVitals);
	$rowGetStudentVitals = mysqli_fetch_assoc($queryGetStudentVitals);
	$countGetStudentVitals = mysqli_num_rows($queryGetStudentVitals);

	$sqlUpdateScore ="UPDATE `affective_domain_score` SET `domain1` = '$ca1', `domain2` = '$ca2', `domain3` = '$ca3', `domain4` = '$ca4', `domain5` = '$ca5', `domain6` = '$ca6', `domain7` = '$ca7', `domain8` = '$ca8', `domain9` = '$ca9', `domain10` = '$ca10', `domain11` = '$ca11', `domain12` = '$ca12', `domain13` = '$ca13', `domain14` = '$ca14', `domain15` = '$ca15' WHERE `id` = '$ID' AND term = '$term' AND session = '$session'";
	$queryUpdateScore = mysqli_query($link, $sqlUpdateScore);

?>