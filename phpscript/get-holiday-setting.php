<?php
include('../database/config.php');

$id = intval($_POST['id']);

// Get header data
$sql = "SELECT * FROM holiday_assessment_settings WHERE id = '$id'";
$res = mysqli_query($link, $sql);
if (mysqli_num_rows($res) == 0) {
	echo json_encode(['error' => 'Setting not found']);
	exit;
}
$setting = mysqli_fetch_assoc($res);

// Get subjects for this setting
$subjects = [];
$sub_sql = "SELECT subject_id, max_score FROM holiday_assessment_subjects WHERE setting_id = '$id'";
$sub_res = mysqli_query($link, $sub_sql);
while ($row = mysqli_fetch_assoc($sub_res)) {
	$subjects[] = $row;
}

$setting['subjects'] = $subjects;
echo json_encode($setting);
