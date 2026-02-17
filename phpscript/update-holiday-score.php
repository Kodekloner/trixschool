<?php
include('../database/config.php');

$student_id = mysqli_real_escape_string($link, $_POST['student_id']);
$subject_id = mysqli_real_escape_string($link, $_POST['subject_id']);
$class_id = mysqli_real_escape_string($link, $_POST['class_id']);
$section_id = mysqli_real_escape_string($link, $_POST['section_id']);
$session_id = mysqli_real_escape_string($link, $_POST['session_id']);
$term = mysqli_real_escape_string($link, $_POST['term']);
$score = mysqli_real_escape_string($link, $_POST['score']);
if ($score === '') $score = NULL;

// Get max_score from settings
$sql_set = "SELECT has.max_score
            FROM holiday_assessment_subjects has
            INNER JOIN holiday_assessment_settings hs ON has.setting_id = hs.id
            WHERE hs.class_id='$class_id' AND hs.section_id='$section_id'
            AND hs.session_id='$session_id' AND hs.term='$term' AND hs.enabled=1
            AND has.subject_id='$subject_id'";
$res_set = mysqli_query($link, $sql_set);
if (mysqli_num_rows($res_set) == 0) {
	echo "error: no setting";
	exit;
}
$set_row = mysqli_fetch_assoc($res_set);
$max_score = $set_row['max_score'];

// Insert or update
$sql = "INSERT INTO holiday_assessment_scores
        (student_id, class_id, section_id, subject_id, session_id, term, score, max_score)
        VALUES ('$student_id', '$class_id', '$section_id', '$subject_id', '$session_id', '$term', '$score', '$max_score')
        ON DUPLICATE KEY UPDATE score = VALUES(score), max_score = VALUES(max_score)";
if (mysqli_query($link, $sql)) {
	echo "success";
} else {
	echo "error: " . mysqli_error($link);
}
