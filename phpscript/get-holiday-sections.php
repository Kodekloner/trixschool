<?php
include('../database/config.php');

$session = $_POST['session'];
$term = $_POST['term'];
$class_id = $_POST['class_id'];
$staff_id = $_POST['staff_id'];
$role = $_POST['role'];

$sql = "SELECT DISTINCT sec.id, sec.section
        FROM holiday_assessment_settings s
        INNER JOIN sections sec ON s.section_id = sec.id
        WHERE s.session_id = '$session' AND s.term = '$term'
          AND s.class_id = '$class_id' AND s.enabled = 1";

// If teacher, restrict to sections they teach in that class
if ($role == 'Teacher') {
        $sql .= " AND s.section_id IN (
                SELECT DISTINCT section_id FROM subjecttables
                WHERE staff_id = '$staff_id' AND class_id = '$class_id'
            )";
}

$sql .= " ORDER BY sec.section";

$result = mysqli_query($link, $sql);
$output = '<option value="">Section</option>';
while ($row = mysqli_fetch_assoc($result)) {
        $output .= '<option value="' . $row['id'] . '">' . $row['section'] . '</option>';
}
echo $output;
