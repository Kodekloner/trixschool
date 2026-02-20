<?php
include('../database/config.php');
?>
<!DOCTYPE html>
<html lang="en">

<head>
	<!-- Required meta tags -->
	<meta charset="utf-8">
	<meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">

	<script src="../assets/js/jquery-3.5.1.min.js"></script>

	<!-- Bootstrap CSS -->
	<link rel="stylesheet" href="../assets/bootstrap/css/bootstrap.min.css">
	<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/4.7.0/css/font-awesome.min.css">
	<!--My New Stylesheet CSS -->
	<link rel="stylesheet" href="../assets/css/myStyleSheet.css">

	<!--The result stylesheet -->
	<link rel="stylesheet" href="../assets/css/resultStyleSheet.css">

	<script src="https://cdnjs.cloudflare.com/ajax/libs/Chart.js/2.9.3/Chart.min.js"></script>

	<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/4.7.0/css/font-awesome.min.css">
	<title>Kindergarten Result</title>
	<style>
		@media only screen and (max-width: 767px) {
			/* CSS rules for smaller screens */

			/* Adjust the width of the result sheet to fit the screen */
			#result-body {
				width: 1300px;
				overflow: auto;
			}

			/* Apply scaling transformation to the result sheet */
			#printable {
				transform-origin: top left;
				transform: scale(0.25);
				width: 1200px;

			}
		}

		@page {
			margin: 0;
		}

		@media print {

			.container-fluid {
				max-height: 1582px;
			}

			/* Scale the printable container down to ensure it fits on one page */
			#printable.resize-for-print {
				/* transform: scale(0.80);
				transform-origin: top left; */
				width: 1080px;
			}

			/* Apply padding adjustments for compact display */
			#printable.resize-for-print .tab td,
			#printable.resize-for-print .tab th {
				padding: 0.2rem;
			}

			/* Adjust font sizes for printing */
			#printable.resize-for-print body,
			#printable.resize-for-print .card {
				font-size: 13px;
			}

			/* Prevent page breaks inside cards and tables */
			#printable.resize-for-print .card,
			#printable.resize-for-print .table-responsive {
				page-break-inside: avoid;
			}

			canvas.sunygraph {
				min-height: 200px;
				max-width: 98%;
				max-height: 100%;
				height: auto !important;
				width: auto !important;
			}

		}

		.tab {
			table-layout: auto;
			/* Keeps column widths uniform */
			word-wrap: break-word;
			/* Breaks long text properly */
			white-space: normal;
			/* Allows wrapping at spaces */
		}

		.tab td,
		.tab th {
			padding: 4px 6px;
			/* Reduces padding for a compact design */
			text-align: center;
			/* Centers content for better alignment */
			vertical-align: middle;
			/* Ensures content stays vertically aligned */
			/* font-size: 14px; */
			/* Adjusts font size for numbers */
		}

		.tab th {
			text-align: left;
			/* Aligns headers to the left for better readability */
			font-weight: bold;
			/* Keeps headers distinct */
		}

		.signature-container {
			height: 56px;
			width: 100%;
			/* Optionally, use position: relative; if you need it for layout */
		}

		.signature-img {
			width: 100%;
			height: 100%;
			object-fit: contain;
			/* Ensures the entire image is visible without distortion */
		}
	</style>
</head>
<?php include('../layout/style.php'); ?>

<body style="background: rgb(236, 234, 234);">
	<?php
	$classsection = $_GET['classsection'];
	$classsectionactual = $_GET['classsectionactual'];
	$classid = $_GET['classid'];
	$term = $_GET['term'];
	$session = $_GET['session'];
	$student_id = $_GET['id'];
	$assessment_id = $_GET['assessment_id']; // passed from the list

	// Get student details
	$sql_student = "SELECT * FROM students WHERE id = '$student_id'";
	$res_student = mysqli_query($link, $sql_student);
	$student = mysqli_fetch_assoc($res_student);
	$student_name = $student['lastname'] . ' ' . $student['middlename'] . ' ' . $student['firstname'];
	$student_gender = $student['gender'];
	$student_image = $student['image'];

	// Get class & section names
	$sql_class = "SELECT class FROM classes WHERE id = '$classid'";
	$res_class = mysqli_query($link, $sql_class);
	$class_name = mysqli_fetch_assoc($res_class)['class'];

	$sql_section = "SELECT section FROM sections WHERE id = '$classsectionactual'";
	$res_section = mysqli_query($link, $sql_section);
	$section_name = mysqli_fetch_assoc($res_section)['section'];

	// Get session name
	$sql_sess = "SELECT session FROM sessions WHERE id = '$session'";
	$res_sess = mysqli_query($link, $sql_sess);
	$session_name = mysqli_fetch_assoc($res_sess)['session'];

	// Get assessment header
	$sql_header = "SELECT * FROM kindergarten_assessment_header WHERE id = '$assessment_id'";
	$res_header = mysqli_query($link, $sql_header);
	$header = mysqli_fetch_assoc($res_header);
	$assessment_label = $header['assessment_label'];
	$num_labels = $header['num_result_labels'];
	$result_labels = json_decode($header['result_labels_json'], true);

	// Get all subjects and concepts for this assessment, ordered
	$sql_items = "
    SELECT 
        kas.subject_id,
        s.name AS subject_name,
        kac.id AS concept_id,
        kac.concept_text
    FROM kindergarten_assessment_subjects kas
    INNER JOIN subjects s ON kas.subject_id = s.id
    INNER JOIN kindergarten_assessment_concepts kac ON kas.id = kac.assessment_subject_id
    WHERE kas.assessment_id = '$assessment_id'
    ORDER BY kas.display_order, kac.display_order
";
	$res_items = mysqli_query($link, $sql_items);
	$items = [];
	while ($row = mysqli_fetch_assoc($res_items)) {
		$items[] = $row;
	}

	// Fetch existing results for this student
	$sql_results = "
    SELECT concept_id, result_label_index
    FROM kindergarten_result
    WHERE student_id = '$student_id' AND session_id = '$session' AND term = '$term'
";
	$res_results = mysqli_query($link, $sql_results);
	$results_map = [];
	while ($row = mysqli_fetch_assoc($res_results)) {
		$results_map[$row['concept_id']] = $row['result_label_index'];
	}

	// Fetch teacher remark
	$sql_teacher_remark = "
    SELECT remark FROM remark
    WHERE RemarkType = 'teacher' AND StudentID = '$student_id' AND Session = '$session' AND Term = '$term'
";
	$res_teacher = mysqli_query($link, $sql_teacher_remark);
	$teacher_remark = (mysqli_num_rows($res_teacher) > 0) ? mysqli_fetch_assoc($res_teacher)['remark'] : '';

	// Fetch principal remark
	$sql_principal_remark = "
    SELECT remark FROM remark
    WHERE RemarkType = 'SchoolHead' AND StudentID = '$student_id' AND Session = '$session' AND Term = '$term'
";
	$res_principal = mysqli_query($link, $sql_principal_remark);
	$principal_remark = (mysqli_num_rows($res_principal) > 0) ? mysqli_fetch_assoc($res_principal)['remark'] : '';

	// Get signatures (optional)
	$sql_teacher_sign = "SELECT Signature FROM staffsignature WHERE staff_id = (SELECT StaffID FROM class_teacher WHERE class_id = '$classid' AND section_id = '$classsectionactual' LIMIT 1)";
	$res_teacher_sign = mysqli_query($link, $sql_teacher_sign);
	$teacher_sign = (mysqli_num_rows($res_teacher_sign) > 0) ? '<img src="../img/signature/' . mysqli_fetch_assoc($res_teacher_sign)['Signature'] . '" class="signature-img">' : '';

	$sql_principal_sign = "SELECT Signature FROM staffsignature WHERE staff_id = (SELECT id FROM staff WHERE role = 'Principal' LIMIT 1)";
	$res_principal_sign = mysqli_query($link, $sql_principal_sign);
	$principal_sign = (mysqli_num_rows($res_principal_sign) > 0) ? '<img src="../img/signature/' . mysqli_fetch_assoc($res_principal_sign)['Signature'] . '" class="signature-img">' : '';

	// Attendance (simplified – you can reuse the logic from resultPage.php)
	// For now, just placeholder
	$days_present = 0;
	$days_absent = 0;
	$total_days = 0;
	?>
	<div class="container-fluid">
		<div class="row" id="non-printable" style="margin-top: 20px;">
			<div class="col-md-10">
				<a href="<?php echo $defRUladmin; ?>/admin/examResult.php" style="color: black; font-size: 20px;"><i class="fa fa-angle-double-left"></i> Back</a>
			</div>
			<div class="col-md-2">
				<a href="" style="color: #000000; font-weight: 600;" onclick="window.print()"><i class="fa fa-print"></i> Print</a>
			</div>
		</div>

		<div class="card" id="printable">
			<img class="watermark-logo" src="https://schoollift.s3.us-east-2.amazonaws.com/<?php echo $rowsch_settings['app_logo']; ?>">

			<div class="card-body" style="color: black;">
				<div class="rel">
					<!-- School header -->
					<div class="row">
						<div class="col">
							<div align="center">
								<img src="https://schoollift.s3.us-east-2.amazonaws.com/<?php echo $rowsch_settings['app_logo']; ?>" class="img-fluid" style="margin: 10px; width: 50%;">
							</div>
						</div>
						<div class="col-6">
							<p class="schname" style="font-size:25px"><?php echo $rowsch_settings['name']; ?></p>
							<p class="schloc" style="color: rgb(185, 7, 7);font-size:16px;margin-top:-20px;"><?php echo $rowsch_settings['address']; ?>.</p>
							<div style="margin-top:-10px;text-align:center">
								<span>Email: <?php echo $rowsch_settings['email']; ?></span><br />
								<span>Website: <?php echo $defRUlsec; ?></span>
							</div>
						</div>
						<div class="col">
							<img src="https://schoollift.s3.us-east-2.amazonaws.com/<?php echo $studimage; ?>" align="center" class="img-fluid" style="margin: 10px; width: 45%;height:120px">
						</div>
					</div><br>

					<div align="center">
						<h5 style="font-size: 17px; font-weight: 500;margin-top:-40px">PROGRESS REPORT FOR <?php echo $term; ?> TERM, <?php echo $session_name; ?> SESSION</h5>
					</div>

					<!-- Student info -->
					<div class="row" style="margin: 10px;">
						<div class="col-4">
							<h5>NAME: <b><?php echo $student_name; ?></b></h5>
						</div>
						<div class="col-4">
							<h5>CLASS: <b><?php echo $class_name . ' ' . $section_name; ?></b></h5>
						</div>
						<div class="col-4">
							<h5>GENDER: <b><?php echo $student_gender; ?></b></h5>
						</div>
					</div>

					<!-- Result table -->
					<div class="result table-responsive" style="margin: 10px; margin-top: 5px;">
						<table class="table-bordered tab table-sm tb-result-border" style="width:98%;">
							<tr>
								<th>SUBJECT</th>
								<th><?php echo $assessment_label; ?></th>
								<?php foreach ($result_labels as $label): ?>
									<th><?php echo htmlspecialchars($label); ?></th>
								<?php endforeach; ?>
							</tr>
							<tbody>
								<?php
								$current_subject = '';
								$concept_count = 0;
								foreach ($items as $item):
									if ($item['subject_name'] != $current_subject):
										if ($current_subject != ''): ?>
											<!-- Optionally a blank row or just continue -->
									<?php endif;
										$current_subject = $item['subject_name'];
										$concept_count = 0;
									endif;
									$concept_id = $item['concept_id'];
									$selected_index = isset($results_map[$concept_id]) ? $results_map[$concept_id] : null;
									?>
									<tr>
										<?php if ($concept_count == 0): ?>
											<td rowspan="<?php echo count(array_filter($items, function ($i) use ($current_subject) {
																return $i['subject_name'] == $current_subject;
															})); ?>">
												<?php echo $current_subject; ?>
											</td>
										<?php endif; ?>
										<td><?php echo htmlspecialchars($item['concept_text']); ?></td>
										<?php for ($i = 0; $i < $num_labels; $i++): ?>
											<td>
												<?php if ($selected_index !== null && $selected_index == $i): ?>
													<span class="tick-mark">✓</span>
												<?php endif; ?>
											</td>
										<?php endfor; ?>
									</tr>
								<?php
									$concept_count++;
								endforeach;
								?>
							</tbody>
						</table>
					</div>

					<!-- Teacher's remark -->
					<div class="row">
						<div class="col-sm-10 col-md-10">
							<p style="text-align: justify;"><b>CLASS TEACHER'S REMARK:</b> <?php echo $teacher_remark; ?></p>
						</div>
						<div class="col-sm-2 col-md-2 signature-container">
							<?php echo $teacher_sign; ?>
						</div>
					</div>

					<!-- Principal's remark -->
					<div class="row">
						<div class="col-sm-10 col-md-10">
							<p style="text-align: justify;"><b>PRINCIPAL'S REMARK:</b> <?php echo $principal_remark; ?></p>
						</div>
						<div class="col-sm-2 col-md-2 signature-container">
							<?php echo $principal_sign; ?>
						</div>
					</div>

					<!-- Next term begins (optional) -->
					<?php
					// You can add next term date logic if needed
					?>
				</div>
			</div>
		</div>
	</div>

	<script>
		function adjustPrintLayout() {
			const printable = document.getElementById('printable');
			if (printable.scrollHeight > 1547) {
				printable.classList.add('resize-for-print');
			} else {
				printable.classList.remove('resize-for-print');
			}
		}
		window.onload = adjustPrintLayout;
	</script>
</body>

</html>