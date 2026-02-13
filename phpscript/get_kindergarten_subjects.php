<?php
include('../database/config.php');
$classid = intval($_POST['classid']);
$sectionid = intval($_POST['sectionid']);
$session = intval($_POST['session']);
$term = mysqli_real_escape_string($link, $_POST['term']);

// Find assessment assigned to this class
$sql = "SELECT assessment_id FROM kindergarten_assignment WHERE class_id = '$classid' LIMIT 1";
$res = mysqli_query($link, $sql);
if (mysqli_num_rows($res) == 0) {
	echo '<option value="0">No assessment assigned</option>';
	exit;
}
$row = mysqli_fetch_assoc($res);
$assessment_id = $row['assessment_id'];

// Get subjects for that assessment
$sql = "SELECT s.id, s.name 
        FROM kindergarten_assessment_subjects kas
        INNER JOIN subjects s ON kas.subject_id = s.id
        WHERE kas.assessment_id = '$assessment_id'
        ORDER BY kas.display_order, s.name";
$res = mysqli_query($link, $sql);
echo '<option value="0">Select Subject</option>';
while ($row = mysqli_fetch_assoc($res)) {
	echo '<option value="' . $row['id'] . '">' . $row['name'] . '</option>';
}
