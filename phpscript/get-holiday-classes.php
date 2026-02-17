<?php
include('../database/config.php');

$session_id = mysqli_real_escape_string($link, $_POST['session_id']);
$term = mysqli_real_escape_string($link, $_POST['term']);

$sql = "SELECT DISTINCT c.id, c.class
        FROM holiday_assessment_settings has
        INNER JOIN classes c ON has.class_id = c.id
        WHERE has.session_id = '$session_id' AND has.term = '$term' AND has.enabled = 1
        ORDER BY c.class";
$res = mysqli_query($link, $sql);
while ($row = mysqli_fetch_assoc($res)) {
	echo '<option value="' . $row['id'] . '">' . $row['class'] . '</option>';
}
