<?php
include('../database/config.php');

$id = isset($_POST['id']) ? (int) $_POST['id'] : 0;
$score = isset($_POST['score']) && is_numeric($_POST['score']) ? round((float) $_POST['score'], 2) : -1;
$session = isset($_POST['session']) ? (int) $_POST['session'] : 0;
$term = isset($_POST['term']) ? trim((string) $_POST['term']) : '';

if ($id < 1 || $session < 1 || $score < 0 || !in_array($term, array('1st', '2nd', '3rd'), true)) {
	http_response_code(422);
	echo 'Invalid score request.';
	exit;
}

$record_statement = mysqli_prepare($link, 'SELECT max_score FROM holiday_assessment_scores WHERE id = ? AND session_id = ? AND term = ? LIMIT 1');
mysqli_stmt_bind_param($record_statement, 'iis', $id, $session, $term);
mysqli_stmt_execute($record_statement);
$record = mysqli_fetch_assoc(mysqli_stmt_get_result($record_statement));
mysqli_stmt_close($record_statement);
if (!$record || $score > (float) $record['max_score']) {
	http_response_code(422);
	echo 'Score must be between zero and the configured maximum.';
	exit;
}

$provenance_result = mysqli_query($link, "SHOW COLUMNS FROM `holiday_assessment_scores` LIKE 'score_origin'");
$has_provenance = $provenance_result && mysqli_num_rows($provenance_result) === 1;
$update_sql = $has_provenance
	? "UPDATE holiday_assessment_scores
	   SET score = ?, score_origin = 'manual', source_onlineexam_id = NULL,
	       source_attempt_id = NULL, source_sync_id = NULL, updated_at = NOW()
	   WHERE id = ? AND session_id = ? AND term = ?"
	: 'UPDATE holiday_assessment_scores SET score = ? WHERE id = ? AND session_id = ? AND term = ?';
$update_statement = mysqli_prepare($link, $update_sql);
mysqli_stmt_bind_param($update_statement, 'diis', $score, $id, $session, $term);
$updated = mysqli_stmt_execute($update_statement);
mysqli_stmt_close($update_statement);

if (!$updated) {
	http_response_code(500);
	echo 'The score could not be updated.';
	exit;
}

echo 'success';
