<?php
include('../database/config.php');
$classid = intval($_POST['classid']);
$sql = "SELECT class_sections.section_id AS id, sections.section 
        FROM class_sections 
        INNER JOIN sections ON class_sections.section_id = sections.id 
        WHERE class_sections.class_id = '$classid'
        ORDER BY sections.section";
$res = mysqli_query($link, $sql);
echo '<option value="0">Select Section</option>';
while ($row = mysqli_fetch_assoc($res)) {
	echo '<option value="' . $row['id'] . '">' . $row['section'] . '</option>';
}
