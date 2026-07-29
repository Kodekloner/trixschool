<?php
$subjectIndex = $_POST['subjectIndex'];
$conceptText = isset($_POST['concept_text']) ? htmlspecialchars($_POST['concept_text'], ENT_QUOTES, 'UTF-8') : '';
$conceptId = isset($_POST['concept_id']) ? (int) $_POST['concept_id'] : 0;
?>
<div class="concept-row form-group row" style="margin-bottom: 10px;" data-concept-id="<?php echo $conceptId; ?>">
	<div class="col-sm-10">
		<input type="text" class="form-control concept-text" placeholder="Enter concept" value="<?php echo $conceptText; ?>">
	</div>
	<div class="col-sm-2">
		<button type="button" class="btn btn-sm btn-outline-danger remove-concept-btn"><i class="fa fa-times"></i></button>
	</div>
</div>
