<?php
include('../database/config.php');

$session = $_POST['session'];
$term = $_POST['term'];
$staff_id = $_POST['staff_id'];
$role = $_POST['role'];

$sql = "SELECT DISTINCT c.id, c.class
        FROM holiday_assessment_settings s
        INNER JOIN classes c ON s.class_id = c.id
        WHERE s.session_id = '$session' AND s.term = '$term' AND s.enabled = 1";

// If user is a teacher, further restrict to classes they teach
if ($role == 'Teacher') {
        $sql .= " AND s.class_id IN (
                SELECT DISTINCT class_id FROM subjecttables 
                WHERE staff_id = '$staff_id'
            )";
}

$sql .= " ORDER BY c.class";

$result = mysqli_query($link, $sql);
$output = '<option value="">Class</option>';
while ($row = mysqli_fetch_assoc($result)) {
        $output .= '<option value="' . $row['id'] . '">' . $row['class'] . '</option>';
}
echo $output;
