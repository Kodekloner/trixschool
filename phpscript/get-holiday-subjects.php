<?php
include('../database/config.php');

$class_id = mysqli_real_escape_string($link, $_POST['class_id']);
$section_id = mysqli_real_escape_string($link, $_POST['section_id']);
$session_id = mysqli_real_escape_string($link, $_POST['session_id']);
$term = mysqli_real_escape_string($link, $_POST['term']);

// Get class_sections.id
$sql_cs = "SELECT id FROM class_sections WHERE class_id='$class_id' AND section_id='$section_id'";
$res_cs = mysqli_query($link, $sql_cs);
if (mysqli_num_rows($res_cs) == 0) {
	echo "No class section found.";
	exit;
}
$row_cs = mysqli_fetch_assoc($res_cs);
$class_section_id = $row_cs['id'];

// Get all subjects for this class section in the given session
$sql_sub = "SELECT sub.id, sub.name
            FROM subject_group_class_sections sgcs
            INNER JOIN subject_group_subjects sgs ON sgcs.subject_group_id = sgs.subject_group_id
            INNER JOIN subjects sub ON sgs.subject_id = sub.id
            WHERE sgcs.class_section_id = '$class_section_id'
            AND sgcs.session_id = '$session_id'
            AND sgs.session_id = '$session_id'
            ORDER BY sub.name";
$res_sub = mysqli_query($link, $sql_sub);

// Load existing settings if any
$setting_id = 0;
$enabled = 0;
$sql_set = "SELECT id, enabled FROM holiday_assessment_settings
            WHERE class_id='$class_id' AND section_id='$section_id'
            AND session_id='$session_id' AND term='$term'";
$res_set = mysqli_query($link, $sql_set);
if (mysqli_num_rows($res_set) > 0) {
	$row_set = mysqli_fetch_assoc($res_set);
	$setting_id = $row_set['id'];
	$enabled = $row_set['enabled'];
}

// Build list
echo '<div class="form-check">
        <input class="form-check-input" type="checkbox" id="enable_all" ' . ($enabled ? 'checked' : '') . '>
        <label class="form-check-label" for="enable_all"><strong>Enable Holiday Assessment</strong></label>
      </div><hr>';
echo '<div id="subject_checkboxes">';
while ($sub = mysqli_fetch_assoc($res_sub)) {
	$checked = '';
	$max_val = 20; // default
	if ($setting_id) {
		$sql_ss = "SELECT max_score FROM holiday_assessment_subjects WHERE setting_id='$setting_id' AND subject_id='{$sub['id']}'";
		$res_ss = mysqli_query($link, $sql_ss);
		if (mysqli_num_rows($res_ss) > 0) {
			$row_ss = mysqli_fetch_assoc($res_ss);
			$checked = 'checked';
			$max_val = $row_ss['max_score'];
		}
	}
	echo '<div class="form-row mb-2">';
	echo '<div class="col-sm-6">';
	echo '<div class="form-check">';
	echo '<input class="form-check-input subject-check" type="checkbox" name="subjects[]" value="' . $sub['id'] . '" ' . $checked . '>';
	echo '<label class="form-check-label">' . $sub['name'] . '</label>';
	echo '</div></div>';
	echo '<div class="col-sm-4">';
	echo '<input type="number" class="form-control form-control-sm" id="max_' . $sub['id'] . '" placeholder="Max Score" value="' . $max_val . '">';
	echo '</div></div>';
}
echo '</div>';
