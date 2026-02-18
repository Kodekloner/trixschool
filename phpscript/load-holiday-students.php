<?php
include('../database/config.php');

$class_id = mysqli_real_escape_string($link, $_POST['class_id']);
$section_id = mysqli_real_escape_string($link, $_POST['section_id']);
$session_id = mysqli_real_escape_string($link, $_POST['session_id']);
$term = mysqli_real_escape_string($link, $_POST['term']);
$subject_id = mysqli_real_escape_string($link, $_POST['subject_id']);

// Get max_score for this subject from settings
$sql_set = "SELECT has.max_score, has.setting_id
            FROM holiday_assessment_subjects has
            INNER JOIN holiday_assessment_settings hs ON has.setting_id = hs.id
            WHERE hs.class_id='$class_id' AND hs.section_id='$section_id'
            AND hs.session_id='$session_id' AND hs.term='$term' AND hs.enabled=1
            AND has.subject_id='$subject_id'";
$res_set = mysqli_query($link, $sql_set);
if (mysqli_num_rows($res_set) == 0) {
	echo "Holiday assessment not enabled for this subject.";
	exit;
}
$set_row = mysqli_fetch_assoc($res_set);
$max_score = $set_row['max_score'];

// Get students in this class/section for the session (from student_records)
$sql_students = "SELECT sr.student_id, CONCAT(s.surname, ' ', s.othername) AS student_name
                 FROM student_records sr
                 INNER JOIN students s ON sr.student_id = s.id
                 WHERE sr.class_id = '$class_id' AND sr.section_id = '$section_id'
                 AND sr.session_id = '$session_id'
                 ORDER BY s.surname, s.othername";
$res_students = mysqli_query($link, $sql_students);

if (mysqli_num_rows($res_students) == 0) {
	echo "No students found.";
	exit;
}

// Build table
echo '<div class="table-responsive">';
echo '<table class="table table-bordered table-striped">';
echo '<thead><tr><th>Student</th><th>Score (Max: ' . $max_score . ')</th></tr></thead><tbody>';
while ($student = mysqli_fetch_assoc($res_students)) {
	$student_id = $student['student_id'];
	// Get existing score
	$sql_score = "SELECT score FROM holiday_assessment_scores
                  WHERE student_id='$student_id' AND class_id='$class_id'
                  AND section_id='$section_id' AND subject_id='$subject_id'
                  AND session_id='$session_id' AND term='$term'";
	$res_score = mysqli_query($link, $sql_score);
	$score = '-';
	if (mysqli_num_rows($res_score) > 0) {
		$score_row = mysqli_fetch_assoc($res_score);
		$score = $score_row['score'];
	}
	echo '<tr data-student-id="' . $student_id . '">';
	echo '<td>' . $student['student_name'] . '</td>';
	echo '<td class="editable" data-score="' . $score . '">' . $score . '</td>';
	echo '</tr>';
}
echo '</tbody></table>';
echo '</div>';
