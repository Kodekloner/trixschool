<?php
include('../database/config.php');

$sql = "SELECT has.*, c.class, s.section, sess.session
        FROM holiday_assessment_settings has
        INNER JOIN classes c ON has.class_id = c.id
        INNER JOIN sections s ON has.section_id = s.id
        INNER JOIN sessions sess ON has.session_id = sess.id
        ORDER BY sess.session DESC, c.class, s.section, has.term";
$res = mysqli_query($link, $sql);

if (mysqli_num_rows($res) == 0) {
	echo '<p class="text-muted">No settings found.</p>';
	exit;
}

echo '<table class="table table-bordered table-sm">';
echo '<thead><tr><th>Session</th><th>Term</th><th>Class</th><th>Section</th><th>Status</th><th>Subjects</th><th>Actions</th></tr></thead><tbody>';
while ($row = mysqli_fetch_assoc($res)) {
	$status = $row['enabled'] ? '<span class="badge badge-success">Enabled</span>' : '<span class="badge badge-secondary">Disabled</span>';
	// Fetch subjects for this setting
	$sub_sql = "SELECT sub.name, hasub.max_score FROM holiday_assessment_subjects hasub
                INNER JOIN subjects sub ON hasub.subject_id = sub.id
                WHERE hasub.setting_id = " . $row['id'];
	$sub_res = mysqli_query($link, $sub_sql);
	$subjects = '';
	while ($sub = mysqli_fetch_assoc($sub_res)) {
		$subjects .= $sub['name'] . ' (' . $sub['max_score'] . ')<br>';
	}
	if ($subjects == '') $subjects = 'None';
	echo '<tr>';
	echo '<td>' . $row['session'] . '</td>';
	echo '<td>' . $row['term'] . '</td>';
	echo '<td>' . $row['class'] . '</td>';
	echo '<td>' . $row['section_name'] . '</td>';
	echo '<td>' . $status . '</td>';
	echo '<td>' . $subjects . '</td>';
	echo '<td>
            <button class="btn btn-sm btn-primary edit-setting" data-id="' . $row['id'] . '"><i class="fa fa-edit"></i></button>
            <button class="btn btn-sm btn-danger delete-setting" data-id="' . $row['id'] . '"><i class="fa fa-trash"></i></button>
          </td>';
	echo '</tr>';
}
echo '</tbody></table>';

// For simplicity, edit and delete actions are not fully implemented in this example.
// You can add AJAX handlers for edit/delete.
