<?php
include('../database/config.php');

$data = json_decode($_POST['data'], true);
$session_id = mysqli_real_escape_string($link, $data['session_id']);
$term = mysqli_real_escape_string($link, $data['term']);
$class_id = mysqli_real_escape_string($link, $data['class_id']);
$section_id = mysqli_real_escape_string($link, $data['section_id']);
$enabled = intval($data['enabled']);

// Check if setting exists
$sql_sel = "SELECT id FROM holiday_assessment_settings
            WHERE class_id='$class_id' AND section_id='$section_id'
            AND session_id='$session_id' AND term='$term'";
$res_sel = mysqli_query($link, $sql_sel);
if (mysqli_num_rows($res_sel) > 0) {
	$row = mysqli_fetch_assoc($res_sel);
	$setting_id = $row['id'];
	$sql_upd = "UPDATE holiday_assessment_settings SET enabled='$enabled' WHERE id='$setting_id'";
	mysqli_query($link, $sql_upd);
	// Delete old subject assignments
	mysqli_query($link, "DELETE FROM holiday_assessment_subjects WHERE setting_id='$setting_id'");
} else {
	$sql_ins = "INSERT INTO holiday_assessment_settings (class_id, section_id, session_id, term, enabled)
                VALUES ('$class_id','$section_id','$session_id','$term','$enabled')";
	mysqli_query($link, $sql_ins);
	$setting_id = mysqli_insert_id($link);
}

// Insert new subject assignments if enabled
if ($enabled && !empty($data['subjects'])) {
	foreach ($data['subjects'] as $sub) {
		$subject_id = mysqli_real_escape_string($link, $sub['subject_id']);
		$max_score = mysqli_real_escape_string($link, $sub['max_score']);
		if ($max_score == '') $max_score = 0;
		$sql_sub = "INSERT INTO holiday_assessment_subjects (setting_id, subject_id, max_score)
                    VALUES ('$setting_id','$subject_id','$max_score')";
		mysqli_query($link, $sql_sub);
	}
}
echo '<div class="alert alert-success">Holiday Assessment settings saved successfully.</div>';
