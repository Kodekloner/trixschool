<?php
include('../database/config.php');

$session = $_POST['session'];
$term = $_POST['term'];
$class_id = $_POST['class_id'];
$section_id = $_POST['section_id'];
$subject_id = $_POST['subject_id'];
$max_score = $_POST['max_score'];

// Get class_sections id if needed (not directly used, but kept for consistency)
$sqlClassSec = "SELECT id FROM class_sections WHERE class_id='$class_id' AND section_id='$section_id'";
$resultClassSec = mysqli_query($link, $sqlClassSec);
$classSection = mysqli_fetch_assoc($resultClassSec);
$class_section_id = $classSection['id'];

// Build query to get students with their scores (LEFT JOIN)
$sql = "SELECT ss.student_id, CONCAT(stud.lastname, ' ', COALESCE(stud.middlename,''), ' ', stud.firstname) AS full_name,
               stud.admission_no, sc.id AS score_id, sc.score
        FROM student_session ss
        INNER JOIN students stud ON ss.student_id = stud.id
        LEFT JOIN holiday_assessment_scores sc ON sc.student_id = stud.id
            AND sc.session_id = ss.session_id
            AND sc.class_id = ss.class_id
            AND sc.section_id = ss.section_id
            AND sc.subject_id = '$subject_id'
            AND sc.term = '$term'
        WHERE ss.session_id = '$session' AND ss.class_id = '$class_id' AND ss.section_id = '$section_id'
        ORDER BY full_name";

$result = mysqli_query($link, $sql);
if (!$result) {
	echo "Query error: " . mysqli_error($link);
	exit;
}

$output = '
<div class="alert alert-info">Max score for this subject: <strong>' . $max_score . '</strong></div>
<table class="table table-striped table-bordered" id="holiday-scores-table" style="width:100%">
    <thead>
        <tr>
            <th>S/N</th>
            <th>Full Name</th>
            <th>Admission No.</th>
            <th>Score (Max ' . $max_score . ')</th>
            <th>Action</th>
        </tr>
    </thead>
    <tbody>';

$sn = 1;
while ($row = mysqli_fetch_assoc($result)) {
	$score_id = $row['score_id'] ?: 'new'; // if no record, we may handle, but insert-holiday-scores should have created it
	$score = $row['score'] !== null ? number_format($row['score'], 2) : '0.00';
	$student_id = $row['student_id'];
	$full_name = htmlspecialchars($row['full_name']);
	$admission_no = htmlspecialchars($row['admission_no']);

	$output .= '<tr id="' . $score_id . '" class="edit_tr">';
	$output .= '<td>' . $sn++ . '</td>';
	$output .= '<td>' . $full_name . '</td>';
	$output .= '<td>' . $admission_no . '</td>';
	$output .= '<td>';
	$output .= '<span id="score_' . $score_id . '" class="text">' . $score . '</span>';
	$output .= '<input type="text" value="' . $score . '" class="editbox" id="score_input_' . $score_id . '" />';
	$output .= '<input type="hidden" id="max_score_' . $score_id . '" value="' . $max_score . '" />';
	$output .= '<input type="hidden" id="student_name_' . $score_id . '" value="' . $full_name . '" />';
	$output .= '</td>';
	$output .= '<td>';
	$output .= '<a href="#" class="delbtn" data-id="' . $score_id . '" data-name="' . $full_name . '"><i class="fa fa-trash text-danger"></i></a>';
	$output .= '</td>';
	$output .= '</tr>';
}
$output .= '</tbody></table>';

echo $output;
