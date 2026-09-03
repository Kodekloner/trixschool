<?php
include('../database/config.php');

$session = isset($_POST['session']) ? (int) $_POST['session'] : 0;
$term = isset($_POST['term']) ? trim((string) $_POST['term']) : '';
$class_id = isset($_POST['class_id']) ? (int) $_POST['class_id'] : 0;
$section_id = isset($_POST['section_id']) ? (int) $_POST['section_id'] : 0;
$subject_id = isset($_POST['subject_id']) ? (int) $_POST['subject_id'] : 0;
$max_score = isset($_POST['max_score']) && is_numeric($_POST['max_score']) ? (float) $_POST['max_score'] : -1;

if ($session < 1 || $class_id < 1 || $section_id < 1 || $subject_id < 1
	|| !in_array($term, array('1st', '2nd', '3rd'), true) || $max_score < 0) {
	http_response_code(422);
	echo 'Invalid holiday assessment context.';
	exit;
}

$provenance_result = mysqli_query($link, "SHOW COLUMNS FROM `holiday_assessment_scores` LIKE 'score_origin'");
$has_provenance = $provenance_result && mysqli_num_rows($provenance_result) === 1;

// Get all active students in this class/section for this session
$student_statement = mysqli_prepare($link, 'SELECT student_id FROM student_session WHERE session_id = ? AND class_id = ? AND section_id = ?');
mysqli_stmt_bind_param($student_statement, 'iii', $session, $class_id, $section_id);
mysqli_stmt_execute($student_statement);
$resultStudents = mysqli_stmt_get_result($student_statement);

$insert_sql = $has_provenance
	? "INSERT IGNORE INTO holiday_assessment_scores
		(student_id, class_id, section_id, subject_id, session_id, term, score, max_score,
		 score_origin, source_onlineexam_id, source_attempt_id, source_sync_id, updated_at)
	   VALUES (?, ?, ?, ?, ?, ?, 0.00, ?, 'placeholder', NULL, NULL, NULL, NOW())"
	: 'INSERT IGNORE INTO holiday_assessment_scores
		(student_id, class_id, section_id, subject_id, session_id, term, score, max_score)
	   VALUES (?, ?, ?, ?, ?, ?, 0.00, ?)';
$insert_statement = mysqli_prepare($link, $insert_sql);

while ($row = mysqli_fetch_assoc($resultStudents)) {
	$student_id = (int) $row['student_id'];
	mysqli_stmt_bind_param($insert_statement, 'iiiiisd', $student_id, $class_id, $section_id, $subject_id, $session, $term, $max_score);
	mysqli_stmt_execute($insert_statement);
}
mysqli_stmt_close($insert_statement);
mysqli_stmt_close($student_statement);

// Cleanup: delete scores for students no longer in this class/section
$delete_statement = mysqli_prepare($link, 'DELETE FROM holiday_assessment_scores
	WHERE session_id = ? AND term = ? AND class_id = ? AND section_id = ? AND subject_id = ?
	AND student_id NOT IN (
		SELECT student_id FROM student_session WHERE session_id = ? AND class_id = ? AND section_id = ?
	)');
mysqli_stmt_bind_param($delete_statement, 'isiiiiii', $session, $term, $class_id, $section_id, $subject_id, $session, $class_id, $section_id);
mysqli_stmt_execute($delete_statement);
mysqli_stmt_close($delete_statement);

echo "done";
