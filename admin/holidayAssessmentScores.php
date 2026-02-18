<?php include('../database/config.php'); ?>
<!doctype html>
<html lang="en">

<head>
	<meta charset="utf-8">
	<meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">

	<script src="../assets/js/jquery-3.5.1.min.js"></script>

	<!-- DataTable Style -->
	<link rel="stylesheet" href="https://cdn.datatables.net/1.13.1/css/jquery.dataTables.min.css">
	<link rel="stylesheet" href="../assets/css/datatables.min.css">
	<!-- Bootstrap CSS -->
	<link rel="stylesheet" href="../assets/bootstrap/css/bootstrap.min.css">
	<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/4.7.0/css/font-awesome.min.css">
	<!-- My New Stylesheet CSS -->
	<link rel="stylesheet" href="../assets/css/myStyleSheet.css">
	<title>Enter Holiday Assessment Scores</title>
	<style type="text/css">
		.editbox {
			display: none;
			width: 50px;
			font-size: 14px;
			width: 60px;
			background-color: #FFFFFF;
			border: solid 1px #FFFFFF;
			padding: 5px 5px;
		}

		.edit_tr:hover {
			background-color: #80C8E5;
			cursor: pointer;
		}

		.max-score-note {
			font-size: 0.9em;
			color: #6c757d;
			margin-left: 10px;
		}
	</style>
</head>

<?php
// Assume $id (staff id) and $rolefirst are set by ../layout/style.php
// If not, you may need to start a session and retrieve them.
// For safety, we set defaults if not defined.
if (!isset($id)) $id = 0;
if (!isset($rolefirst)) $rolefirst = '';
?>

<body style="background: rgb(236, 234, 234);">

	<div class="menu-wrapper">
		<div class="sidebar-header">
			<?php include('../layout/sidebar.php'); ?>
			<div class="backdrop"></div>
			<div class="content">
				<?php include('../layout/header.php'); ?>

				<div class="content-data">
					<div class="row" style="margin:15px;">
						<div class="col-sm-12 cardBoxSty">
							<form id="filterForm">
								<div class="form-row">
									<div class="form-group col-sm">
										<select class="form-control" id="session" required>
											<option value="">Session</option>
											<?php
											$sqlsessions = "SELECT * FROM `sessions` ORDER BY session DESC";
											$resultsessions = mysqli_query($link, $sqlsessions);
											while ($row = mysqli_fetch_assoc($resultsessions)) {
												echo '<option value="' . $row['id'] . '">' . $row['session'] . '</option>';
											}
											?>
										</select>
									</div>
									<div class="form-group col-sm">
										<select class="form-control" id="term" required>
											<option value="">Term</option>
											<option value="1st">1st Term</option>
											<option value="2nd">2nd Term</option>
											<option value="3rd">3rd Term</option>
										</select>
									</div>
									<div class="form-group col-sm">
										<select class="form-control" id="class" required>
											<option value="">Class</option>
											<!-- Populated via AJAX after session & term are selected -->
										</select>
									</div>
									<div class="form-group col-sm">
										<select class="form-control" id="classsection" required>
											<option value="">Section</option>
										</select>
									</div>
									<div class="form-group col-sm">
										<select class="form-control" id="subject" required>
											<option value="">Subject</option>
										</select>
									</div>
									<div class="col-md-12" align="right">
										<button type="button" class="btn btn-primary" id="loadScores">Load Students</button>
									</div>
								</div>
							</form>
						</div>
					</div>

					<div id="scoresTable" class="mt-3">
						<!-- Editable table will be loaded here -->
					</div>
				</div>
			</div>
		</div>
	</div>

	<!-- Hidden field to store max_score for the selected subject -->
	<input type="hidden" id="current_max_score" value="0">

	<!-- Scripts for editable table and DataTables -->
	<script src="../assets/plugins/jquery-datatables-editable/jquery.dataTables.js"></script>
	<script src="../assets/plugins/datatables/dataTables.bootstrap.js"></script>
	<script src="../assets/plugins/tiny-editable/mindmup-editabletable.js"></script>
	<script src="../assets/plugins/tiny-editable/numeric-input-example.js"></script>
	<script src="../assets/bootstrap/js/bootstrap.bundle.min.js"></script>

	<script>
		$(document).ready(function() {

			// When session OR term changes, reload class dropdown (if both are selected)
			$('#session, #term').on('change', function() {
				var session = $('#session').val();
				var term = $('#term').val();
				if (session && term) {
					loadClasses(session, term);
				} else {
					$('#class').html('<option value="">Class</option>');
					$('#classsection').html('<option value="">Section</option>');
					$('#subject').html('<option value="">Subject</option>');
				}
			});

			// When class changes, load sections (if session, term, class are selected)
			$('#class').on('change', function() {
				var session = $('#session').val();
				var term = $('#term').val();
				var classId = $(this).val();
				if (session && term && classId) {
					loadSections(session, term, classId);
				} else {
					$('#classsection').html('<option value="">Section</option>');
					$('#subject').html('<option value="">Subject</option>');
				}
			});

			// When section changes, load subjects (if all previous are selected)
			$('#classsection').on('change', function() {
				var session = $('#session').val();
				var term = $('#term').val();
				var classId = $('#class').val();
				var sectionId = $(this).val();
				if (session && term && classId && sectionId) {
					loadSubjects(session, term, classId, sectionId);
				} else {
					$('#subject').html('<option value="">Subject</option>');
				}
			});

			// Load Students button
			$('#loadScores').on('click', function() {
				var session = $('#session').val();
				var term = $('#term').val();
				var classId = $('#class').val();
				var sectionId = $('#classsection').val();
				var subjectId = $('#subject').val();
				var maxScore = $('#current_max_score').val(); // from subject dropdown

				if (!session || !term || !classId || !sectionId || !subjectId) {
					alert('Please select all filters.');
					return;
				}

				// Show loading
				$('#scoresTable').html('<i class="fa fa-circle-o-notch fa-spin"></i> Loading...');

				// First, ensure score records exist for all students in this class/section/subject
				$.ajax({
					url: '../phpscript/insert-holiday-scores.php',
					method: 'POST',
					data: {
						session: session,
						term: term,
						class_id: classId,
						section_id: sectionId,
						subject_id: subjectId,
						max_score: maxScore
					},
					success: function(response) {
						// After insertion, load the editable table
						loadScoresTable(session, term, classId, sectionId, subjectId, maxScore);
					},
					error: function() {
						$('#scoresTable').html('<div class="alert alert-danger">Error creating score records.</div>');
					}
				});
			});

			// Function to load classes based on session, term and staff role
			function loadClasses(session, term) {
				$.ajax({
					url: '../phpscript/get-holiday-classes.php',
					method: 'POST',
					data: {
						session: session,
						term: term,
						staff_id: '<?php echo $id; ?>',
						role: '<?php echo $rolefirst; ?>'
					},
					success: function(data) {
						$('#class').html(data);
					}
				});
			}

			// Function to load sections based on session, term, class and staff role
			function loadSections(session, term, classId) {
				$.ajax({
					url: '../phpscript/get-holiday-sections.php',
					method: 'POST',
					data: {
						session: session,
						term: term,
						class_id: classId,
						staff_id: '<?php echo $id; ?>',
						role: '<?php echo $rolefirst; ?>'
					},
					success: function(data) {
						$('#classsection').html(data);
					}
				});
			}

			// Function to load subjects based on session, term, class, section and staff role
			function loadSubjects(session, term, classId, sectionId) {
				$.ajax({
					url: '../phpscript/get-holiday-subjects-for-score.php',
					method: 'POST',
					data: {
						session: session,
						term: term,
						class_id: classId,
						section_id: sectionId,
						staff_id: '<?php echo $id; ?>',
						role: '<?php echo $rolefirst; ?>'
					},
					success: function(data) {
						$('#subject').html(data);
						// The max_score is sent as a data attribute; store it in the hidden field
						var selected = $('#subject option:selected');
						var max = selected.data('maxscore');
						$('#current_max_score').val(max ? max : 0);
					}
				});
			}

			// Function to load the editable scores table
			function loadScoresTable(session, term, classId, sectionId, subjectId, maxScore) {
				$.ajax({
					url: '../phpscript/get-holiday-scores-table.php',
					method: 'POST',
					data: {
						session: session,
						term: term,
						class_id: classId,
						section_id: sectionId,
						subject_id: subjectId,
						max_score: maxScore
					},
					success: function(data) {
						$('#scoresTable').html(data);
						// Initialize DataTable on the new table
						if ($.fn.DataTable.isDataTable('#holiday-scores-table')) {
							$('#holiday-scores-table').DataTable().destroy();
						}
						$('#holiday-scores-table').DataTable({
							"paging": true,
							"ordering": true,
							"info": true,
							"autoWidth": false
						});
					},
					error: function() {
						$('#scoresTable').html('<div class="alert alert-danger">Error loading scores.</div>');
					}
				});
			}

			// Editable table functionality (click on row to edit, then update on change)
			$(document).on('click', '.edit_tr', function() {
				var ID = $(this).attr('id'); // score record ID
				// Hide the static score span and show the input box
				$("#score_" + ID).hide();
				$("#score_input_" + ID).fadeIn(1000);
			});

			$(document).on('change', '.edit_tr', function() {
				var ID = $(this).attr('id');
				var newScore = parseFloat($("#score_input_" + ID).val());
				var maxScore = parseFloat($("#max_score_" + ID).val()); // hidden field in each row
				var studentName = $("#student_name_" + ID).val();

				if (isNaN(newScore)) newScore = 0;

				// Validate against max_score
				if (newScore > maxScore) {
					alert('Score cannot exceed ' + maxScore + ' for ' + studentName);
					// Reset input to old value
					var oldScore = $("#score_" + ID).text();
					$("#score_input_" + ID).val(oldScore);
					$("#score_" + ID).show();
					$("#score_input_" + ID).hide();
					return;
				}

				var session = $('#session').val();
				var term = $('#term').val();

				$.ajax({
					url: '../phpscript/update-holiday-score.php',
					method: 'POST',
					data: {
						id: ID,
						score: newScore,
						session: session,
						term: term
					},
					success: function(response) {
						if (response.trim() === 'success') {
							$("#score_" + ID).text(newScore.toFixed(2));
						} else {
							alert('Update failed: ' + response);
						}
						$("#score_" + ID).show();
						$("#score_input_" + ID).hide();
					},
					error: function() {
						alert('AJAX error while updating.');
						$("#score_" + ID).show();
						$("#score_input_" + ID).hide();
					}
				});
			});

			// Delete score (similar to computeExam)
			$(document).on('click', '.delbtn', function() {
				var scoreId = $(this).data('id');
				var studentName = $(this).data('name');
				$('#selDeleteID').val(scoreId);
				$('#deleteStudentName').html(studentName);
				$('#delScore').modal('show');
			});

			$('#confirmDelete').on('click', function() {
				var scoreId = $('#selDeleteID').val();
				$.ajax({
					url: '../phpscript/delete-holiday-score.php',
					method: 'POST',
					data: {
						id: scoreId
					},
					success: function(response) {
						$('#delScore').modal('hide');
						if (response.trim() === 'success') {
							// Reload the table
							$('#loadScores').click();
						} else {
							alert('Delete failed: ' + response);
						}
					}
				});
			});

		}); // document ready
	</script>

	<!-- Delete Confirmation Modal -->
	<div class="modal fade" id="delScore" tabindex="-1" role="dialog">
		<div class="modal-dialog modal-md" role="document">
			<div class="modal-content">
				<div class="modal-header">
					<h5 class="modal-title">Confirm Delete</h5>
					<button type="button" class="close" data-dismiss="modal">&times;</button>
				</div>
				<div class="modal-body">
					<p>Are you sure you want to delete the score for <b><span id="deleteStudentName"></span></b>?</p>
					<p class="text-danger">This action cannot be undone.</p>
					<input type="hidden" id="selDeleteID">
				</div>
				<div class="modal-footer">
					<button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
					<button type="button" class="btn btn-danger" id="confirmDelete">Delete</button>
				</div>
			</div>
		</div>
	</div>

</body>

</html>