<?php
include('../database/config.php');
$id = intval($_POST['id']);

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
