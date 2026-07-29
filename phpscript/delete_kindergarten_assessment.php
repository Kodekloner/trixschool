<?php
include('../database/config.php');
require_once('kindergarten_guard.php');
kindergartenRequireStaff(true, true, 'can_delete');
$id = intval($_POST['id']);

$result_refs = mysqli_query($link, "SELECT COUNT(*) AS total FROM kindergarten_result WHERE assessment_id='$id'");
$result_ref_count = $result_refs ? (int) mysqli_fetch_assoc($result_refs)['total'] : 0;
$online_ref_count = 0;
$online_table = mysqli_query($link, "SHOW TABLES LIKE 'onlineexam_kindergarten_mappings'");
if ($online_table && mysqli_num_rows($online_table) > 0) {
	$online_refs = mysqli_query($link, "SELECT COUNT(*) AS total FROM onlineexam_kindergarten_mappings WHERE assessment_id='$id'");
	$online_ref_count = $online_refs ? (int) mysqli_fetch_assoc($online_refs)['total'] : 0;
}
if ($result_ref_count > 0 || $online_ref_count > 0) {
	http_response_code(409);
	echo '<div class="alert alert-warning">This assessment is referenced by saved results or an online examination and cannot be deleted. Edit it instead; existing identifiers will be preserved.</div>';
	exit;
}

mysqli_begin_transaction($link);
try {
	// Delete concepts through subjects
	$subj_sql = "SELECT id FROM kindergarten_assessment_subjects WHERE assessment_id='$id'";
	$subj_res = mysqli_query($link, $subj_sql);
	while ($subj_row = mysqli_fetch_assoc($subj_res)) {
		mysqli_query($link, "DELETE FROM kindergarten_assessment_concepts WHERE assessment_subject_id='{$subj_row['id']}'");
	}
	mysqli_query($link, "DELETE FROM kindergarten_assessment_subjects WHERE assessment_id='$id'");
	mysqli_query($link, "DELETE FROM kindergarten_assignment WHERE assessment_id='$id'");
	mysqli_query($link, "DELETE FROM kindergarten_assessment_header WHERE id='$id'");
	mysqli_commit($link);
	echo '<div class="alert alert-success">Assessment deleted.</div>';
} catch (Exception $e) {
	mysqli_rollback($link);
	echo '<div class="alert alert-danger">Error: ' . $e->getMessage() . '</div>';
}
