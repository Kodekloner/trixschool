<?php
include('../database/config.php');
$assessment_id = intval($_POST['assessment_id']);
$class_ids = isset($_POST['class_ids']) ? $_POST['class_ids'] : [];

mysqli_begin_transaction($link);
try {
	mysqli_query($link, "DELETE FROM kindergarten_assignment WHERE assessment_id='$assessment_id'");
	foreach ($class_ids as $class_id) {
		$class_id = intval($class_id);
		mysqli_query($link, "INSERT INTO kindergarten_assignment (assessment_id, class_id) VALUES ('$assessment_id', '$class_id')");
	}
	mysqli_commit($link);
	echo '<div class="alert alert-success">Assignment updated successfully!</div>';
} catch (Exception $e) {
	mysqli_rollback($link);
	echo '<div class="alert alert-danger">Error: ' . $e->getMessage() . '</div>';
}
