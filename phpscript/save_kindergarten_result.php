<?php
include('../database/config.php');

$student_id = intval($_POST['student_id']);
$session = intval($_POST['session']);
$term = mysqli_real_escape_string($link, $_POST['term']);
$subject_id = intval($_POST['subject_id']);
$concept_id = intval($_POST['concept_id']);
$label_index = intval($_POST['label_index']);

// First, get the assessment_id for this student's class (we can fetch it from class assignment)
// But we need the assessment_id. We can get it from the subject's assessment via the concept's parent.
// Simpler: we can store assessment_id in the result table, so we need to retrieve it.
// We'll get assessment_id from the concept's parent subject entry.
$sql = "SELECT kas.assessment_id 
        FROM kindergarten_assessment_concepts kac
        INNER JOIN kindergarten_assessment_subjects kas ON kac.assessment_subject_id = kas.id
        WHERE kac.id = '$concept_id'";
$res = mysqli_query($link, $sql);
if (mysqli_num_rows($res) == 0) {
	echo "Invalid concept.";
	exit;
}
$row = mysqli_fetch_assoc($res);
$assessment_id = $row['assessment_id'];

// Insert or update
$sql = "INSERT INTO kindergarten_result (student_id, session_id, term, assessment_id, subject_id, concept_id, result_label_index)
        VALUES ('$student_id', '$session', '$term', '$assessment_id', '$subject_id', '$concept_id', '$label_index')
        ON DUPLICATE KEY UPDATE result_label_index = VALUES(result_label_index)";
if (mysqli_query($link, $sql)) {
	echo "success";
} else {
	echo "Database error: " . mysqli_error($link);
}
