<?php
include('../database/config.php');

$studentid = $_POST['studentid'];

$extcomment = $_POST['extcomment'];

$session = $_POST['session'];

$term = $_POST['term'];

$RemarkType = $_POST['RemarkType'];

$staffid = $_POST['staffid'];

$resultSubTypeRaw = strtolower(trim($_POST['resultType'] ?? 'termly'));
$resultSubType = ($resultSubTypeRaw === 'midterm' || $resultSubTypeRaw === 'mid-term') ? 'midterm' : 'termly';

$sqlGetstudent_session = "SELECT * FROM `remark` WHERE StudentID='$studentid' AND `Session`='$session' AND Term = '$term' AND `RemarkType` = '$RemarkType' AND `ResultSubType` = '$resultSubType' AND `StaffID` = '$staffid'";
$queryGetstudent_session = mysqli_query($link, $sqlGetstudent_session);
$rowGetstudent_session = mysqli_fetch_assoc($queryGetstudent_session);
$countGetstudent_session = mysqli_num_rows($queryGetstudent_session);

if ($countGetstudent_session > 0) {

  $sqlUpdateScore = "UPDATE `remark` SET `remark`='$extcomment' WHERE `RemarkType` ='$RemarkType' AND `StudentID` = '$studentid' AND `Session`= '$session' AND `Term` = '$term' AND `ResultSubType` = '$resultSubType' AND `StaffID` = '$staffid'";
  $queryUpdateScore = mysqli_query($link, $sqlUpdateScore);
} else {
  $sqlUpdateScore = "INSERT INTO `remark`(`StaffID`, `RemarkType`, `remark`, `StudentID`, `Session`, `Term`, `ResultSubType`) VALUES ('$staffid','$RemarkType','$extcomment','$studentid','$session','$term','$resultSubType')";
  $queryUpdateScore = mysqli_query($link, $sqlUpdateScore);
}
