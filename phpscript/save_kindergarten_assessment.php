<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);
include('../database/config.php');
$data = json_decode($_POST['data'], true);
die("SAVE FILE HIT");

$assessment_id = $data['assessment_id'] ? intval($data['assessment_id']) : 0;
$assessment_name = mysqli_real_escape_string($link, $data['assessment_name']);
$assessment_label = mysqli_real_escape_string($link, $data['assessment_label']);
$num_result_labels = intval($data['num_result_labels']);
$result_labels = array_map(function ($v) use ($link) {
	return mysqli_real_escape_string($link, $v);
}, $data['result_labels']);
$result_labels_json = json_encode($result_labels);

// Start transaction
mysqli_begin_transaction($link);
try {
	if ($assessment_id > 0) {
		// Update header
		$sql = "UPDATE kindergarten_assessment_header SET 
                assessment_name='$assessment_name',
                assessment_label='$assessment_label',
                num_result_labels='$num_result_labels',
                result_labels_json='$result_labels_json'
                WHERE id='$assessment_id'";
		mysqli_query($link, $sql);
		// Delete existing subjects & concepts
		$subj_sql = "SELECT id FROM kindergarten_assessment_subjects WHERE assessment_id='$assessment_id'";
		$subj_res = mysqli_query($link, $subj_sql);
		while ($subj_row = mysqli_fetch_assoc($subj_res)) {
			mysqli_query($link, "DELETE FROM kindergarten_assessment_concepts WHERE assessment_subject_id='{$subj_row['id']}'");
		}
		mysqli_query($link, "DELETE FROM kindergarten_assessment_subjects WHERE assessment_id='$assessment_id'");
	} else {
		// Insert header
		$sql = "INSERT INTO kindergarten_assessment_header (assessment_name, assessment_label, num_result_labels, result_labels_json)
                VALUES ('$assessment_name', '$assessment_label', '$num_result_labels', '$result_labels_json')";
		mysqli_query($link, $sql);
		$assessment_id = mysqli_insert_id($link);
	}

	// Insert subjects and concepts
	$order = 0;
	foreach ($data['subjects'] as $subj) {
		$subject_id = intval($subj['subject_id']);
		$sql_subj = "INSERT INTO kindergarten_assessment_subjects (assessment_id, subject_id, display_order)
                     VALUES ('$assessment_id', '$subject_id', '$order')";
		mysqli_query($link, $sql_subj);
		$assessment_subject_id = mysqli_insert_id($link);
		$concept_order = 0;
		foreach ($subj['concepts'] as $concept) {
			$concept = mysqli_real_escape_string($link, $concept);
			$sql_con = "INSERT INTO kindergarten_assessment_concepts (assessment_subject_id, concept_text, display_order)
                        VALUES ('$assessment_subject_id', '$concept', '$concept_order')";
			mysqli_query($link, $sql_con);
			$concept_order++;
		}
		$order++;
	}

	mysqli_commit($link);
	echo '<div class="alert alert-success">Assessment saved successfully!</div>';
} catch (Exception $e) {
	mysqli_rollback($link);
	echo '<div class="alert alert-danger">Error: ' . $e->getMessage() . '</div>';
}
