<?php include('../database/config.php'); ?>
<!doctype html>
<html lang="en">

<head>
	<meta charset="utf-8">
	<meta name="viewport" content="width=device-width, initial-scale=1">
	<script src="../assets/js/jquery-3.5.1.min.js"></script>
	<link rel="stylesheet" href="../assets/bootstrap/css/bootstrap.min.css">
	<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/4.7.0/css/font-awesome.min.css">
	<link rel="stylesheet" href="../assets/css/myStyleSheet.css">
	<title>Holiday Assessment Settings</title>
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
					<div class="row" style="margin:15px;">
						<div class="col-sm-4 col-md-4">
							<h3 style="margin-bottom:20px;">Holiday Assessment Settings</h3>
							<form id="holidaySettingsForm">
								<div class="form-group">
									<label>Session</label>
									<select class="form-control" id="session_id" required>
										<option value="">Select Session</option>
										<?php
										$sql = "SELECT * FROM sessions ORDER BY session";
										$res = mysqli_query($link, $sql);
										while ($row = mysqli_fetch_assoc($res)) {
											echo "<option value='{$row['id']}'>{$row['session']}</option>";
										}
										?>
									</select>
								</div>
								<div class="form-group">
									<label>Term</label>
									<select class="form-control" id="term" required>
										<option value="">Select Term</option>
										<option value="1st">1st Term</option>
										<option value="2nd">2nd Term</option>
										<option value="3rd">3rd Term</option>
									</select>
								</div>
								<div class="form-group">
									<label>Class</label>
									<select class="form-control" id="class_id" required>
										<option value="">Select Class</option>
										<?php
										$sql = "SELECT * FROM classes ORDER BY class";
										$res = mysqli_query($link, $sql);
										while ($row = mysqli_fetch_assoc($res)) {
											echo "<option value='{$row['id']}'>{$row['class']}</option>";
										}
										?>
									</select>
								</div>
								<div class="form-group">
									<label>Section</label>
									<select class="form-control" id="section_id" required>
										<option value="">First select class</option>
									</select>
								</div>
								<div class="form-check mb-3">
									<input type="checkbox" class="form-check-input" id="enabled" value="1">
									<label class="form-check-label" for="enabled">Enable Holiday Assessment for this class</label>
								</div>
								<div id="subjectsContainer" style="display:none;">
									<hr>
									<h5>Select Subjects & Set Maximum Score</h5>
									<div id="subjectList"></div>
								</div>
								<button type="submit" class="btn btn-primary mt-3">Save Settings</button>
							</form>
						</div>
						<div class="col-sm-8 col-md-8">
							<h3>Existing Holiday Assessment Settings</h3>
							<hr>
							<div id="settingsList">Loading...</div>
						</div>
					</div>
				</div>
			</div>
		</div>
	</div>

	<script>
		$(document).ready(function() {
			// Load sections when class changes
			$('#class_id').on('change', function() {
				var classid = $(this).val();
				$('#section_id').html('<option value="">Loading...</option>');
				if (classid) {
					$.ajax({
						url: '../../../phpscript/get-sections-by-class.php',
						method: 'POST',
						data: {
							classid: classid
						},
						success: function(data) {
							$('#section_id').html(data);
						}
					});
				}
			});

			// Load subjects when class & section & session & term are selected
			function loadSubjects() {
				var classid = $('#class_id').val();
				var sectionid = $('#section_id').val();
				var sessionid = $('#session_id').val();
				var term = $('#term').val();
				if (classid && sectionid && sessionid && term) {
					$.ajax({
						url: '../../../phpscript/get-holiday-subjects.php',
						method: 'POST',
						data: {
							class_id: classid,
							section_id: sectionid,
							session_id: sessionid,
							term: term
						},
						success: function(data) {
							$('#subjectList').html(data);
							$('#subjectsContainer').show();
						}
					});
				}
			}

			$('#class_id, #section_id, #session_id, #term').on('change', loadSubjects);

			// Load existing settings into right panel
			function loadSettingsList() {
				$.ajax({
					url: '../../../phpscript/get-holiday-settings-list.php',
					method: 'GET',
					success: function(data) {
						$('#settingsList').html(data);
					}
				});
			}
			loadSettingsList();

			// Save settings
			$('#holidaySettingsForm').on('submit', function(e) {
				e.preventDefault();
				var btn = $(this).find('button[type=submit]');
				btn.html('<i class="fa fa-circle-o-notch fa-spin"></i> Saving...');
				var subjects = [];
				$('#subjectList input[type=checkbox]:checked').each(function() {
					subjects.push({
						subject_id: $(this).val(),
						max_score: $('#max_' + $(this).val()).val()
					});
				});
				var data = {
					session_id: $('#session_id').val(),
					term: $('#term').val(),
					class_id: $('#class_id').val(),
					section_id: $('#section_id').val(),
					enabled: $('#enabled').is(':checked') ? 1 : 0,
					subjects: subjects
				};
				$.ajax({
					url: '../../../phpscript/save-holiday-settings.php',
					method: 'POST',
					data: {
						data: JSON.stringify(data)
					},
					success: function(res) {
						$('.messagetoo').html(res);
						btn.html('Save Settings');
						loadSettingsList();
					}
				});
			});
		});
	</script>
	<script src="../assets/bootstrap/js/bootstrap.bundle.min.js"></script>
</body>

</html>