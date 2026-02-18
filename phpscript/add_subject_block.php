<?php
include('../database/config.php');
$index = $_POST['index'];
$selected = isset($_POST['subject_id']) ? $_POST['subject_id'] : '';
?>
<div class="subject-block card mb-3" data-index="<?php echo $index; ?>">
	<div class="card-header d-flex justify-content-between align-items-center">
		<select class="form-control subject-select" style="width:70%;" required>
			<option value="0">Select Subject</option>
			<?php
			$sql = "SELECT id, name FROM subjects ORDER BY name ASC";
			$res = mysqli_query($link, $sql);
			while ($row = mysqli_fetch_assoc($res)) {
				$sel = ($row['id'] == $selected) ? 'selected' : '';
				echo '<option value="' . $row['id'] . '" ' . $sel . '>' . $row['name'] . '</option>';
			}
			?>
		</select>
		<button type="button" class="btn btn-sm btn-danger remove-subject-btn"><i class="fa fa-trash"></i></button>
	</div>
	<div class="card-body">
		<div class="concepts-list">
			<!-- concept rows will be appended here -->
		</div>
		<button type="button" class="btn btn-sm btn-success add-concept-btn"><i class="fa fa-plus"></i> Add Concept</button>
	</div>
</div>