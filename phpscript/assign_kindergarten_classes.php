<?php
include('../database/config.php');
$assessment_id = intval($_POST['assessment_id']);
?>
<input type="hidden" id="assign_assesment_id" value="<?php echo $assessment_id; ?>">
<div class="form-group">
	<label>Select Classes (Kindergarten)</label>
	<div class="form-check">
		<input class="form-check-input" type="checkbox" id="selectAllClasses">
		<label class="form-check-label" for="selectAllClasses">Select All</label>
	</div>
	<hr>
	<?php
	// Fetch classes – adjust condition if you have a separate Kindergarten category
	$sql = "SELECT * FROM classes ORDER BY class";
	$res = mysqli_query($link, $sql);
	$assigned = [];
	$assigned_res = mysqli_query($link, "SELECT class_id FROM kindergarten_assignment WHERE assessment_id='$assessment_id'");
	while ($row = mysqli_fetch_assoc($assigned_res)) {
		$assigned[] = $row['class_id'];
	}
	while ($row = mysqli_fetch_assoc($res)) {
		$checked = in_array($row['id'], $assigned) ? 'checked' : '';
		echo '<div class="form-check">
                <input class="form-check-input class-checkbox" type="checkbox" name="classes[]" value="' . $row['id'] . '" ' . $checked . '>
                <label class="form-check-label">' . $row['class'] . '</label>
              </div>';
	}
	?>
</div>
<script>
	$('#selectAllClasses').click(function() {
		$('.class-checkbox').prop('checked', this.checked);
	});
</script>