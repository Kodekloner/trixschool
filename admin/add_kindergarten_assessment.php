<?php
include('../database/config.php');
?>
<!doctype html>
<html lang="en">

<head>
	<meta charset="utf-8">
	<meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
	<script src="../assets/js/jquery-3.5.1.min.js"></script>
	<link rel="stylesheet" href="https://cdn.datatables.net/1.13.1/css/jquery.dataTables.min.css">
	<link rel="stylesheet" href="../assets/bootstrap/css/bootstrap.min.css">
	<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/4.7.0/css/font-awesome.min.css">
	<link rel="stylesheet" href="../assets/css/myStyleSheet.css">
	<title>Kindergarten Assessment Setup</title>
</head>
<?php include('../layout/style.php'); ?>

<body style="background: rgb(236, 234, 234);">
	<div class="menu-wrapper">
		<div class="sidebar-header">
			<?php include('../layout/sidebar.php'); ?>
			<div class="backdrop"></div>
			<div class="content">
				<?php include('../layout/header.php'); ?>
				<div class="content-data">
					<div class="messagetoo"></div>
					<div class="row" style="margin: 15px;">
						<!-- LEFT: FORM -->
						<div class="col-sm-4 col-md-4">
							<div>
								<h3 style="margin-bottom: 30px;">Kindergarten Assessment</h3>
								<form id="kindergartenForm">
									<div class="form-group">
										<label for="assessmentName">Assessment Name</label>
										<input type="text" class="form-control" id="assessmentName" placeholder="e.g. Midterm Progress Report">
									</div>
									<div class="form-group">
										<label for="assessmentLabel">Assessment Label <small>(displayed on result)</small></label>
										<input type="text" class="form-control" id="assessmentLabel" value="CONCEPT">
									</div>
									<div class="form-group">
										<label for="numResultLabels">Number of Result Labels</label>
										<select class="form-control" id="numResultLabels">
											<option value="2" selected>2</option>
											<option value="3">3</option>
											<option value="4">4</option>
											<option value="5">5</option>
										</select>
									</div>
									<div id="resultLabelsContainer">
										<!-- loaded via AJAX -->
									</div>
									<hr>
									<h5>Subjects & Concepts</h5>
									<div id="subjectsContainer">
										<!-- subject blocks will be added here -->
									</div>
									<button type="button" class="btn btn-info" id="addSubjectBtn"><i class="fa fa-plus"></i> Add Subject</button>
									<hr>
									<input type="hidden" id="assessmentId" value="">
									<button type="submit" class="btn btn-primary submitbtn">Save Assessment</button>
								</form>
							</div>
						</div>
						<!-- RIGHT: LIST -->
						<div class="col-sm-8 col-md-8">
							<div class="table-responsive data_table">
								<h3>Existing Assessments</h3>
								<hr>
								<table id="assessmentTable" class="table table-striped table-sm" style="width:100%;">
									<thead>
										<tr>
											<th>Assessment Name</th>
											<th>Label</th>
											<th>Result Labels</th>
											<th>Classes Assigned</th>
											<th>Action</th>
										</tr>
									</thead>
									<tbody>
										<?php
										$sql = "SELECT * FROM kindergarten_assessment_header ORDER BY id DESC";
										$result = mysqli_query($link, $sql);
										while ($row = mysqli_fetch_assoc($result)) {
											$labels = json_decode($row['result_labels_json'], true);
											$labelsDisplay = implode(', ', $labels);
											$assignCount = mysqli_num_rows(mysqli_query($link, "SELECT id FROM kindergarten_assignment WHERE assessment_id='{$row['id']}'"));
											echo '<tr>
                                            <td>' . $row['assessment_name'] . '</td>
                                            <td>' . $row['assessment_label'] . '</td>
                                            <td>' . $labelsDisplay . '</td>
                                            <td>' . $assignCount . '</td>
                                            <td>
                                                <button class="btn-edit" data-id="' . $row['id'] . '" style="border:none; background:none;"><i class="fa fa-pencil" title="Edit"></i></button>
                                                <button class="btn-assign" data-id="' . $row['id'] . '" style="border:none; background:none;"><i class="fa fa-tag" title="Assign to Classes"></i></button>
                                                <button class="btn-delete" data-id="' . $row['id'] . '" style="border:none; background:none;"><i class="fa fa-trash" title="Delete"></i></button>
                                            </td>
                                        </tr>';
										}
										?>
									</tbody>
								</table>
							</div>
						</div>
					</div>
				</div>
			</div>
		</div>
	</div>

	<!-- Assign to Classes Modal -->
	<div class="modal fade" id="assignModal" tabindex="-1" aria-hidden="true">
		<div class="modal-dialog">
			<div class="modal-content">
				<div class="modal-header">
					<h5 class="modal-title">Assign Assessment to Classes</h5>
					<button type="button" class="close" data-dismiss="modal">&times;</button>
				</div>
				<div class="modal-body" id="assignModalBody">
					<!-- loaded via AJAX -->
				</div>
				<div class="modal-footer">
					<button type="button" class="btn btn-secondary" data-dismiss="modal">Close</button>
					<button type="button" class="btn btn-primary" id="saveAssignBtn">Save Changes</button>
				</div>
			</div>
		</div>
	</div>

	<script>
		$(document).ready(function() {
			// Initial load of result labels (default 2)
			loadResultLabels(2);

			// When number of result labels changes
			$('#numResultLabels').change(function() {
				var num = $(this).val();
				loadResultLabels(num);
			});

			function loadResultLabels(num) {
				$.ajax({
					url: '../phpscript/load_result_labels.php',
					method: 'POST',
					data: {
						num: num
					},
					success: function(data) {
						$('#resultLabelsContainer').html(data);
					}
				});
			}

			// Add Subject block
			$('#addSubjectBtn').click(function() {
				$.ajax({
					url: '../phpscript/add_subject_block.php',
					method: 'POST',
					data: {
						index: new Date().getTime()
					}, // unique identifier
					success: function(data) {
						$('#subjectsContainer').append(data);
					}
				});
			});

			// Add Concept row inside a subject block
			$(document).on('click', '.add-concept-btn', function() {
				var subjectBlock = $(this).closest('.subject-block');
				var subjectIndex = subjectBlock.data('index');
				$.ajax({
					url: '../phpscript/add_concept_row.php',
					method: 'POST',
					data: {
						subjectIndex: subjectIndex
					},
					success: function(data) {
						subjectBlock.find('.concepts-list').append(data);
					}
				});
			});

			// Remove Concept row
			$(document).on('click', '.remove-concept-btn', function() {
				$(this).closest('.concept-row').remove();
			});

			// Remove Subject block
			$(document).on('click', '.remove-subject-btn', function() {
				$(this).closest('.subject-block').remove();
			});

			// Save Assessment
			$('#kindergartenForm').submit(function(e) {
				e.preventDefault();
				var submitBtn = $('.submitbtn');
				submitBtn.html('<i class="fa fa-circle-o-notch fa-spin"></i> Saving...');

				var assessmentId = $('#assessmentId').val();
				var assessmentName = $('#assessmentName').val();
				var assessmentLabel = $('#assessmentLabel').val();
				var numResultLabels = $('#numResultLabels').val();

				// Collect result labels
				var resultLabels = [];
				$('.result-label-input').each(function() {
					resultLabels.push($(this).val());
				});

				// Collect subjects & concepts
				var subjects = [];
				$('.subject-block').each(function() {
					var subjectId = $(this).find('.subject-select').val();
					if (!subjectId || subjectId === '0') return;
					var concepts = [];
					$(this).find('.concept-text').each(function() {
						var concept = $(this).val();
						if (concept.trim() !== '') {
							concepts.push(concept);
						}
					});
					if (concepts.length > 0) {
						subjects.push({
							subject_id: subjectId,
							concepts: concepts
						});
					}
				});

				var data = {
					assessment_id: assessmentId,
					assessment_name: assessmentName,
					assessment_label: assessmentLabel,
					num_result_labels: numResultLabels,
					result_labels: resultLabels,
					subjects: subjects
				};

				$.ajax({
					url: '../phpscript/save_kindergarten_assessment.php',
					method: 'POST',
					data: {
						data: JSON.stringify(data)
					},
					success: function(response) {
						$('.messagetoo').html(response);
						submitBtn.html('Save Assessment');
						if (response.includes('success')) {
							setTimeout(function() {
								location.reload();
							}, 2000);
						}
					},
					error: function(xhr, status, error) {
						$('.messagetoo').html('<div class="alert alert-danger">AJAX Error: ' + error + '</div>');
					}
				});
			});

			// EDIT: load assessment data
			$(document).on('click', '.btn-edit', function() {
				var id = $(this).data('id');
				$.ajax({
					url: '../phpscript/get_kindergarten_assessment.php',
					method: 'POST',
					data: {
						id: id
					},
					dataType: 'json',
					success: function(data) {
						// Clear existing subjects
						$('#subjectsContainer').empty();
						$('#assessmentId').val(data.id);
						$('#assessmentName').val(data.assessment_name);
						$('#assessmentLabel').val(data.assessment_label);
						$('#numResultLabels').val(data.num_result_labels);
						loadResultLabels(data.num_result_labels);
						// Set result label values after loading
						setTimeout(function() {
							$.each(data.result_labels, function(i, label) {
								$('.result-label-input').eq(i).val(label);
							});
						}, 100);
						// Load subjects & concepts
						$.each(data.subjects, function(i, subj) {
							// Add subject block
							$.ajax({
								url: '../phpscript/add_subject_block.php',
								method: 'POST',
								data: {
									index: i,
									subject_id: subj.subject_id
								},
								async: false,
								success: function(block) {
									$('#subjectsContainer').append(block);
									var blockElem = $('#subjectsContainer .subject-block').last();
									// Add concepts
									$.each(subj.concepts, function(j, concept) {
										$.ajax({
											url: '../phpscript/add_concept_row.php',
											method: 'POST',
											data: {
												subjectIndex: i,
												concept_text: concept
											},
											async: false,
											success: function(row) {
												blockElem.find('.concepts-list').append(row);
											}
										});
									});
								}
							});
						});
					}
				});
			});

			// ASSIGN: open modal and load classes
			$(document).on('click', '.btn-assign', function() {
				var assessmentId = $(this).data('id');
				$.ajax({
					url: '../phpscript/assign_kindergarten_classes.php',
					method: 'POST',
					data: {
						assessment_id: assessmentId
					},
					success: function(data) {
						$('#assignModalBody').html(data);
						$('#assignModal').modal('show');
					}
				});
			});

			// Save assignments
			$(document).on('click', '#saveAssignBtn', function() {
				var btn = $(this);
				btn.html('<i class="fa fa-circle-o-notch fa-spin"></i> Saving...');
				var assessmentId = $('#assign_assesment_id').val();
				var classIds = [];
				$('.class-checkbox:checked').each(function() {
					classIds.push($(this).val());
				});
				$.ajax({
					url: '../phpscript/save_kindergarten_assignment.php',
					method: 'POST',
					data: {
						assessment_id: assessmentId,
						class_ids: classIds
					},
					success: function(response) {
						$('#assignModalBody').prepend(response);
						btn.html('Save Changes');
						setTimeout(function() {
							location.reload();
						}, 2000);
					}
				});
			});

			// DELETE
			$(document).on('click', '.btn-delete', function() {
				if (confirm('Are you sure you want to delete this assessment? This action cannot be undone.')) {
					var id = $(this).data('id');
					$.ajax({
						url: '../phpscript/delete_kindergarten_assessment.php',
						method: 'POST',
						data: {
							id: id
						},
						success: function(response) {
							$('.messagetoo').html(response);
							setTimeout(function() {
								location.reload();
							}, 2000);
						}
					});
				}
			});

			// DataTable
			$('#assessmentTable').DataTable();
		});
	</script>
	<script src="../assets/bootstrap/js/bootstrap.bundle.min.js"></script>
	<script src="../assets/js/jquery.dataTables.min.js"></script>
</body>

</html>