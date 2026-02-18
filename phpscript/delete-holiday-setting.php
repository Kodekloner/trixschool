<?php
include('../database/config.php');

$id = intval($_POST['id']);

mysqli_begin_transaction($link);
try {
	// Delete subject assignments first (foreign key constraint)
	mysqli_query($link, "DELETE FROM holiday_assessment_subjects WHERE setting_id = '$id'");
	// Delete the main setting
	mysqli_query($link, "DELETE FROM holiday_assessment_settings WHERE id = '$id'");
	mysqli_commit($link);
	echo '<div class="alert alert-success">Holiday Assessment setting deleted successfully.</div>';
} catch (Exception $e) {
	mysqli_rollback($link);
	echo '<div class="alert alert-danger">Error deleting setting: ' . $e->getMessage() . '</div>';
}
