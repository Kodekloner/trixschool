<?php
include('../database/config.php');

$class_id = mysqli_real_escape_string($link, $_POST['class_id']);
$session_id = mysqli_real_escape_string($link, $_POST['session_id']);
$term = mysqli_real_escape_string($link, $_POST['term']);

$sql = "SELECT s.id, s.section_name
        FROM holiday_assessment_settings has
        INNER JOIN sections s ON has.section_id = s.id
        WHERE has.class_id = '$class_id' AND has.session_id = '$session_id' AND has.term = '$term' AND has.enabled = 1
        ORDER BY s.section_name";
$res = mysqli_query($link, $sql);
while ($row = mysqli_fetch_assoc($res)) {
	echo '<option value="' . $row['id'] . '">' . $row['section_name'] . '</option>';
}
