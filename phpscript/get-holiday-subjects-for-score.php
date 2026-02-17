<?php
include('../database/config.php');

$class_id = mysqli_real_escape_string($link, $_POST['class_id']);
$section_id = mysqli_real_escape_string($link, $_POST['section_id']);
$session_id = mysqli_real_escape_string($link, $_POST['session_id']);
$term = mysqli_real_escape_string($link, $_POST['term']);

// Get setting_id
$sql_set = "SELECT id FROM holiday_assessment_settings
            WHERE class_id='$class_id' AND section_id='$section_id'
            AND session_id='$session_id' AND term='$term' AND enabled=1";
$res_set = mysqli_query($link, $sql_set);
if (mysqli_num_rows($res_set) == 0) exit;
$set_row = mysqli_fetch_assoc($res_set);
$setting_id = $set_row['id'];

$sql = "SELECT sub.id, sub.name
        FROM holiday_assessment_subjects has
        INNER JOIN subjects sub ON has.subject_id = sub.id
        WHERE has.setting_id = '$setting_id'
        ORDER BY sub.name";
$res = mysqli_query($link, $sql);
while ($row = mysqli_fetch_assoc($res)) {
	echo '<option value="' . $row['id'] . '">' . $row['name'] . '</option>';
}
