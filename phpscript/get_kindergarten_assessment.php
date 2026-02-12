<?php
include('../database/config.php');
$id = intval($_POST['id']);

// Header
$sql = "SELECT * FROM kindergarten_assessment_header WHERE id='$id'";
$res = mysqli_query($link, $sql);
$header = mysqli_fetch_assoc($res);
$header['result_labels'] = json_decode($header['result_labels_json'], true);

// Subjects
$subjects = [];
$sql_subj = "SELECT * FROM kindergarten_assessment_subjects WHERE assessment_id='$id' ORDER BY display_order";
$res_subj = mysqli_query($link, $sql_subj);
while ($subj = mysqli_fetch_assoc($res_subj)) {
	$concepts = [];
	$sql_con = "SELECT concept_text FROM kindergarten_assessment_concepts WHERE assessment_subject_id='{$subj['id']}' ORDER BY display_order";
	$res_con = mysqli_query($link, $sql_con);
	while ($con = mysqli_fetch_assoc($res_con)) {
		$concepts[] = $con['concept_text'];
	}
	$subjects[] = [
		'subject_id' => $subj['subject_id'],
		'concepts' => $concepts
	];
}
$header['subjects'] = $subjects;
echo json_encode($header);
