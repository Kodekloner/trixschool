<?php include('../database/config.php'); ?>
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
	<title>Kindergarten Result Entry</title>
	<style>
		.concept-radio-group {
			display: flex;
			gap: 10px;
			justify-content: center;
		}

		.concept-radio-group label {
			margin-right: 5px;
			font-weight: normal;
		}

		.concept-cell {
			text-align: center;
		}
	</style>
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
					<div class="row" style="margin: 15px;">
						<div class="col-sm-12 cardBoxSty">
							<form style="margin: 0px;">
								<div class="form-row">
									<div class="form-group col-sm">
										<select class="form-control" id="session">
											<option value="0">Session</option>
											<?php
											$sql = "SELECT * FROM sessions ORDER BY session DESC";
											$res = mysqli_query($link, $sql);
											while ($row = mysqli_fetch_assoc($res)) {
												echo '<option value="' . $row['id'] . '">' . $row['session'] . '</option>';
											}
											?>
										</select>
									</div>
									<div class="form-group col-sm">
										<select class="form-control" id="term">
											<option value="0">Select Term</option>
											<option value="1st">1st Term</option>
											<option value="2nd">2nd Term</option>
											<option value="3rd">3rd Term</option>
										</select>
									</div>
									<div class="form-group col-sm">
										<select class="form-control" id="class">
											<option value="0">Class</option>
											<?php
											// Only show classes that have a kindergarten assessment assigned
											$sql = "SELECT DISTINCT classes.id, classes.class 
                                                FROM classes 
                                                INNER JOIN kindergarten_assignment ON classes.id = kindergarten_assignment.class_id
                                                ORDER BY classes.class";
											$res = mysqli_query($link, $sql);
											while ($row = mysqli_fetch_assoc($res)) {
												echo '<option value="' . $row['id'] . '">' . $row['class'] . '</option>';
											}
											?>
										</select>
									</div>
									<div class="form-group col-sm">
										<select class="form-control" id="classsection">
											<option value="0">Section</option>
										</select>
									</div>
									<div class="form-group col-sm">
										<select class="form-control" id="subjects">
											<option value="0">Subject</option>
										</select>
									</div>
									<div class="col-md-12" align="right">
										<button type="button" class="btn btn-primary" style="border-radius: 20px;" id="loadBtn">
											<i class="fa fa-search"></i> Load
										</button>
									</div>
								</div>
							</form>
						</div>
					</div>

					<div class="card cardBoxSty" style="margin: 15px;">
						<div class="card-body">
							<div class="row">
								<div class="col-sm-12 col-md-12 cardBoxSty">
									<div id="tbl_data">
										<div class="alert alert-primary">Please filter to proceed.</div>
									</div>
								</div>
							</div>
						</div>
					</div>
				</div>
			</div>
		</div>
	</div>

	<!-- Loading indicator -->
	<div id="loading" style="display:none; position:fixed; top:0; left:0; width:100%; height:100%; background:rgba(0,0,0,0.5); z-index:9999; text-align:center; padding-top:20%;">
		<i class="fa fa-spinner fa-pulse fa-3x fa-fw" style="color:#fff;"></i>
		<span style="color:#fff; font-size:20px;">Loading...</span>
	</div>

	<script src="../assets/bootstrap/js/bootstrap.bundle.min.js"></script>
	<script src="../assets/js/jquery.dataTables.min.js"></script>
	<script>
		$(document).ready(function() {
			// Load sections when class changes
			$('#class').change(function() {
				var classid = $(this).val();
				$('#classsection').html('<option value="0">Loading...</option>');
				if (classid != 0) {
					$.ajax({
						url: '../phpscript/get_sections_by_class.php',
						method: 'POST',
						data: {
							classid: classid
						},
						success: function(data) {
							$('#classsection').html(data);
						}
					});
				} else {
					$('#classsection').html('<option value="0">Section</option>');
				}
			});

			// Load subjects when section changes
			$('#classsection').change(function() {
				var classid = $('#class').val();
				var sectionid = $(this).val();
				var session = $('#session').val();
				var term = $('#term').val();
				$('#subjects').html('<option value="0">Loading...</option>');
				if (classid != 0 && sectionid != 0 && session != 0 && term != 0) {
					$.ajax({
						url: '../phpscript/get_kindergarten_subjects.php',
						method: 'POST',
						data: {
							classid: classid,
							sectionid: sectionid,
							session: session,
							term: term
						},
						success: function(data) {
							$('#subjects').html(data);
						}
					});
				} else {
					$('#subjects').html('<option value="0">Subject</option>');
				}
			});

			// Load the result entry table
			$('#loadBtn').click(function() {
				var session = $('#session').val();
				var term = $('#term').val();
				var classid = $('#class').val();
				var sectionid = $('#classsection').val();
				var subjectid = $('#subjects').val();

				if (session == 0 || term == 0 || classid == 0 || sectionid == 0 || subjectid == 0) {
					alert('Please select all filters.');
					return;
				}

				$('#loading').show();
				$.ajax({
					url: '../phpscript/load_kindergarten_table.php',
					method: 'POST',
					data: {
						session: session,
						term: term,
						classid: classid,
						sectionid: sectionid,
						subjectid: subjectid
					},
					success: function(data) {
						$('#tbl_data').html(data);
						$('#loading').hide();
						// Initialize any DataTable if needed
						$('#kindergartenTable').DataTable({
							scrollX: true,
							paging: true,
							pageLength: 25,
							ordering: false
						});
					},
					error: function() {
						$('#loading').hide();
						alert('Error loading data.');
					}
				});
			});

			// Save changes when radio is changed
			$(document).on('change', '.concept-radio', function() {
				var studentId = $(this).data('student');
				var conceptId = $(this).data('concept');
				var labelIndex = $(this).val();
				var session = $('#session').val();
				var term = $('#term').val();
				var subjectId = $('#subjects').val();

				// Visual feedback
				var row = $(this).closest('tr');
				row.css('background-color', '#fff3cd');

				$.ajax({
					url: '../phpscript/save_kindergarten_result.php',
					method: 'POST',
					data: {
						student_id: studentId,
						session: session,
						term: term,
						subject_id: subjectId,
						concept_id: conceptId,
						label_index: labelIndex
					},
					success: function(response) {
						if (response.trim() === 'success') {
							row.css('background-color', '#d4edda');
							setTimeout(function() {
								row.css('background-color', '');
							}, 1000);
						} else {
							row.css('background-color', '#f8d7da');
							alert('Error saving: ' + response);
						}
					},
					error: function() {
						row.css('background-color', '#f8d7da');
						alert('Server error.');
					}
				});
			});
		});
	</script>
</body>

</html>