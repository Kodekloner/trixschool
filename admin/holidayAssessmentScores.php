<?php include('../database/config.php'); ?>
<!doctype html>
<html lang="en">

<head>
	<!-- same headers as computeExam.php -->
	<title>Enter Holiday Assessment Scores</title>
	<style>
		.editbox {
			display: none;
			width: 50px;
		}
	</style>
</head>
<?php include('../layout/style.php'); ?>

<body>
	<div class="menu-wrapper"> ... sidebar & header ... </div>
	<div class="content-data">
		<div class="row" style="margin:15px;">
			<div class="col-sm-12 cardBoxSty">
				<form id="filterForm">
					<div class="form-row">
						<div class="form-group col-sm">
							<select class="form-control" id="session" required>
								<option value="">Session</option>
								<!-- populate sessions -->
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
								<!-- populate only classes with holiday assessment enabled -->
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
		<div id="scoresTable" class="mt-3"></div>
	</div>

	<script>
		// AJAX handlers to load class, section, subject, and display editable table
		// Similar to computeExam.js but using holiday_assessment_scores table
		// and respecting per-subject max_score from settings.
	</script>
</body>

</html>