<?php
include('../database/config.php');

$session = intval($_POST['session']);
$term = mysqli_real_escape_string($link, $_POST['term']);
$classid = intval($_POST['classid']);
$sectionid = intval($_POST['sectionid']);
$subjectid = intval($_POST['subjectid']);

// 1. Get assessment_id for this class
$sql = "SELECT assessment_id FROM kindergarten_assignment WHERE class_id = '$classid' LIMIT 1";
$res = mysqli_query($link, $sql);
if (mysqli_num_rows($res) == 0) {
	echo '<div class="alert alert-warning">No kindergarten assessment assigned to this class.</div>';
	exit;
}
$row = mysqli_fetch_assoc($res);
$assessment_id = $row['assessment_id'];

// 2. Get assessment header (result labels)
$sql = "SELECT assessment_label, num_result_labels, result_labels_json FROM kindergarten_assessment_header WHERE id = '$assessment_id'";
$res = mysqli_query($link, $sql);
$header = mysqli_fetch_assoc($res);
$result_labels = json_decode($header['result_labels_json'], true);
$num_labels = $header['num_result_labels'];

// 3. Get concepts for this subject, ordered
$sql = "SELECT kac.id, kac.concept_text 
        FROM kindergarten_assessment_concepts kac
        INNER JOIN kindergarten_assessment_subjects kas ON kac.assessment_subject_id = kas.id
        WHERE kas.assessment_id = '$assessment_id' AND kas.subject_id = '$subjectid'
        ORDER BY kac.display_order, kac.id";
$concepts = mysqli_query($link, $sql);
$conceptList = [];
while ($c = mysqli_fetch_assoc($concepts)) {
	$conceptList[] = $c;
}

if (empty($conceptList)) {
	echo '<div class="alert alert-info">No concepts defined for this subject in the assessment.</div>';
	exit;
}

// 4. Get active students in this class/section for this session
$sqlStudents = "SELECT ss.student_id, CONCAT(s.lastname, ' ', COALESCE(s.middlename,''), ' ', s.firstname) AS full_name, s.admission_no
                FROM student_session ss
                INNER JOIN students s ON ss.student_id = s.id
                WHERE ss.session_id = '$session' AND ss.class_id = '$classid' AND ss.section_id = '$sectionid'
                  AND s.is_active = 'yes'
                ORDER BY full_name";
$students = mysqli_query($link, $sqlStudents);

// 5. Build a lookup of existing results for this subject
//    key: student_id . '_' . concept_id => label_index
$existing = [];
$sqlRes = "SELECT student_id, concept_id, result_label_index 
           FROM kindergarten_result 
           WHERE session_id = '$session' AND term = '$term' 
             AND assessment_id = '$assessment_id' 
             AND subject_id = '$subjectid'";
$resRes = mysqli_query($link, $sqlRes);
while ($r = mysqli_fetch_assoc($resRes)) {
	$existing[$r['student_id'] . '_' . $r['concept_id']] = $r['result_label_index'];
}

// 6. Build the table
echo '<form id="kindergartenResultForm">';
echo '<table class="table table-striped table-bordered" id="kindergartenTable" style="width:100%;">';
echo '<thead>';
echo '<tr>';
echo '<th>Student</th>';
echo '<th>Admission No.</th>';
foreach ($conceptList as $c) {
	echo '<th>' . htmlspecialchars($c['concept_text']) . '</th>';
}
echo '</tr>';
echo '</thead>';
echo '<tbody>';

while ($student = mysqli_fetch_assoc($students)) {
	$student_id = $student['student_id'];
	echo '<tr>';
	echo '<td>' . htmlspecialchars($student['full_name']) . '</td>';
	echo '<td>' . htmlspecialchars($student['admission_no']) . '</td>';

	foreach ($conceptList as $c) {
		$concept_id = $c['id'];
		$key = $student_id . '_' . $concept_id;
		$selected = isset($existing[$key]) ? $existing[$key] : null;
		echo '<td class="concept-cell">';
		// Radio buttons for each result label
		for ($i = 0; $i < $num_labels; $i++) {
			$label = isset($result_labels[$i]) ? $result_labels[$i] : 'Option ' . ($i + 1);
			$checked = ($selected !== null && $selected == $i) ? 'checked' : '';
			echo '<label style="margin-right:5px;">';
			echo '<input type="radio" name="student_' . $student_id . '_concept_' . $concept_id . '" 
                         class="concept-radio" 
                         data-student="' . $student_id . '" 
                         data-concept="' . $concept_id . '" 
                         value="' . $i . '" ' . $checked . '> ';
			echo htmlspecialchars($label);
			echo '</label> ';
		}
		echo '</td>';
	}
	echo '</tr>';
}
echo '</tbody>';
echo '</table>';
echo '</form>';
