<?php
include('../database/config.php');

$session = $_POST['session'];
$term = $_POST['term'];
$class_id = $_POST['class_id'];
$section_id = $_POST['section_id'];
$staff_id = $_POST['staff_id'];
$role = $_POST['role'];

// First get the holiday_assessment_settings id for this combination
$sqlSetting = "SELECT id FROM holiday_assessment_settings
               WHERE session_id = '$session' AND term = '$term'
                 AND class_id = '$class_id' AND section_id = '$section_id'
                 AND enabled = 1 LIMIT 1";
$resultSetting = mysqli_query($link, $sqlSetting);
if (mysqli_num_rows($resultSetting) == 0) {
        echo '<option value="">No enabled setting</option>';
        exit;
}
$setting = mysqli_fetch_assoc($resultSetting);
$setting_id = $setting['id'];

// Now fetch subjects from holiday_assessment_subjects
$sqlSubj = "SELECT s.id AS subject_id, s.name, ha.max_score
            FROM holiday_assessment_subjects ha
            INNER JOIN subjects s ON ha.subject_id = s.id
            WHERE ha.setting_id = '$setting_id'";

// If teacher, restrict to subjects they teach (via subjecttables)
if ($role == 'Teacher') {
        $sqlSubj .= " AND ha.subject_id IN (
                    SELECT DISTINCT sub.subject_id
                    FROM subjecttables st
                    INNER JOIN subject_group_subjects sub ON st.subject_group_subject_id = sub.id
                    WHERE st.staff_id = '$staff_id' AND st.class_id = '$class_id' AND st.section_id = '$section_id'
                  )";
}

$sqlSubj .= " ORDER BY s.name";
$resultSubj = mysqli_query($link, $sqlSubj);
$output = '<option value="">Subject</option>';
while ($row = mysqli_fetch_assoc($resultSubj)) {
        $output .= '<option value="' . $row['subject_id'] . '" data-maxscore="' . $row['max_score'] . '">'
                . $row['name'] . ' (Max: ' . $row['max_score'] . ')</option>';
}
echo $output;
