<?php
include('../database/config.php');

$session = $_POST['session'];
$term = $_POST['term'];
$class_id = $_POST['class_id'];
$section_id = $_POST['section_id'];
$subject_id = $_POST['subject_id'];
$max_score = $_POST['max_score'];

// Get all active students in this class/section for this session
$sqlStudents = "SELECT student_id FROM student_session
                WHERE session_id = '$session' AND class_id = '$class_id' AND section_id = '$section_id'";
$resultStudents = mysqli_query($link, $sqlStudents);

while ($row = mysqli_fetch_assoc($resultStudents)) {
	$student_id = $row['student_id'];

	// Insert if not exists (unique key prevents duplicate)
	$sqlInsert = "INSERT IGNORE INTO holiday_assessment_scores
                  (student_id, class_id, section_id, subject_id, session_id, term, score, max_score)
                  VALUES ('$student_id', '$class_id', '$section_id', '$subject_id',
                          '$session', '$term', 0.00, '$max_score')";
	mysqli_query($link, $sqlInsert);
}

// Cleanup: delete scores for students no longer in this class/section
$sqlDelete = "DELETE FROM holiday_assessment_scores
              WHERE session_id = '$session' AND term = '$term'
                AND class_id = '$class_id' AND section_id = '$section_id'
                AND subject_id = '$subject_id'
                AND student_id NOT IN (
                    SELECT student_id FROM student_session
                    WHERE session_id = '$session' AND class_id = '$class_id' AND section_id = '$section_id'
                )";
mysqli_query($link, $sqlDelete);

echo "done";
