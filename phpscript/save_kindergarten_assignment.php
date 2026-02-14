<?php
include('../database/config.php');

$assessment_id = intval($_POST['assessment_id']);
$class_ids = isset($_POST['class_ids']) ? $_POST['class_ids'] : [];

mysqli_begin_transaction($link);

try {
	// Remove existing assignments for each selected class
	foreach ($class_ids as $class_id) {
		$class_id = intval($class_id);

		// Delete any traditional CA assigned to this class
		mysqli_query($link, "DELETE FROM `assigncatoclass` WHERE ClassID = '$class_id'");

		// Delete any kindergarten assessment assigned to this class (including the one we are about to assign)
		mysqli_query($link, "DELETE FROM `kindergarten_assignment` WHERE class_id = '$class_id'");
	}

	// Insert the new kindergarten assignments
	foreach ($class_ids as $class_id) {
		$class_id = intval($class_id);
		$sqlInsert = "INSERT INTO `kindergarten_assignment` (`assessment_id`, `class_id`)
                      VALUES ('$assessment_id', '$class_id')";
		if (!mysqli_query($link, $sqlInsert)) {
			throw new Exception(mysqli_error($link));
		}
	}

	mysqli_commit($link);
	echo '<div class="alert alert-success">Assignment updated successfully! (Previous assignments for these classes were removed.)</div>';
} catch (Exception $e) {
	mysqli_rollback($link);
	echo '<div class="alert alert-danger">Error: ' . $e->getMessage() . '</div>';
}
