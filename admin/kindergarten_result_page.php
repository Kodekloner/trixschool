<?php
include('../database/config.php');
require_once('../helper/defaultcomment_helper.php');
require_once('../helper/promotion_helper.php');
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
	<link rel="stylesheet" href="../assets/css/result-report.css">

	<script src="https://cdnjs.cloudflare.com/ajax/libs/Chart.js/2.9.3/Chart.min.js"></script>

	<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/4.7.0/css/font-awesome.min.css">
	<title>Kindergarten Result</title>
	<style>
		.signature-container {
			height: 56px;
			width: 100%;
		}

		.signature-img {
			width: 100%;
			height: 100%;
			object-fit: contain;
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
	$resultSubTypeRaw = strtolower(trim($_GET['reltype'] ?? 'termly'));
	$resultSubType = ($resultSubTypeRaw === 'midterm' || $resultSubTypeRaw === 'mid-term') ? 'midterm' : 'termly';

	// Get student details
	$sql_student = "SELECT * FROM students WHERE id = '$student_id'";
	$res_student = mysqli_query($link, $sql_student);
	$student = mysqli_fetch_assoc($res_student);
	$student_name = $student['lastname'] . ' ' . $student['middlename'] . ' ' . $student['firstname'];
	$student_gender = $student['gender'];
	$studimage = $student['image'];

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
      AND (
        (kas.is_active = 1 AND kac.is_active = 1)
        OR EXISTS (
            SELECT 1 FROM kindergarten_result kr
            WHERE kr.student_id = '$student_id'
              AND kr.session_id = '$session'
              AND kr.term = '$term'
              AND kr.assessment_id = '$assessment_id'
              AND kr.concept_id = kac.id
        )
      )
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
    WHERE student_id = '$student_id'
      AND session_id = '$session'
      AND term = '$term'
      AND assessment_id = '$assessment_id'
";
	$res_results = mysqli_query($link, $sql_results);
	$results_map = [];
	while ($row = mysqli_fetch_assoc($res_results)) {
		$results_map[$row['concept_id']] = $row['result_label_index'];
	}

	// Fetch teacher remark
	$sql_teacher_remark = "
    SELECT remark FROM remark
    WHERE RemarkType = 'teacher' AND StudentID = '$student_id' AND Session = '$session' AND Term = '$term' AND ResultSubType = '$resultSubType'
";
	$res_teacher = mysqli_query($link, $sql_teacher_remark);
	$teacher_remark = (mysqli_num_rows($res_teacher) > 0) ? mysqli_fetch_assoc($res_teacher)['remark'] : '';

	// Fetch principal remark
	$sql_principal_remark = "
    SELECT remark, StaffID FROM remark
    WHERE RemarkType = 'SchoolHead' AND StudentID = '$student_id' AND Session = '$session' AND Term = '$term' AND ResultSubType = '$resultSubType'
";
	$res_principal = mysqli_query($link, $sql_principal_remark);
	$principal_row = (mysqli_num_rows($res_principal) > 0) ? mysqli_fetch_assoc($res_principal) : [];
	$principal_remark = $principal_row['remark'] ?? '';

	// Get signatures (optional)
	$class_teacher = get_result_class_teacher($link, $classid, $classsectionactual, $session);
	$teacher_signature_row = get_staff_signature_row($link, $class_teacher['staff_id'] ?? 0);
	$teacher_sign = !empty($teacher_signature_row['Signature']) ? build_staff_signature_html($teacher_signature_row['Signature'], '../img/signature/', 'signature-img') : '';

	$principal_staff_id = resolve_school_head_staff_id($link, $principal_row['StaffID'] ?? 0);
	$principal_sign = get_school_head_signature_html($link, $principal_staff_id, '../img/signature/');

	// Attendance (simplified – you can reuse the logic from resultPage.php)
	// For now, just placeholder
	$days_present = 0;
	$days_absent = 0;
	$total_days = 0;

	$resultSummaryContext = get_result_summary_context(
		$link,
		$student_id,
		$session,
		$classid,
		$classsectionactual,
		$term,
		$resultSubType,
		'kindergarten',
		$assessment_id
	);
	$showPromotionOutcome = $resultSubType === 'termly' && $term === '3rd';
	$showCumulativeAverage = false;
	$promotionOutcome = $showPromotionOutcome
		? get_final_promotion_outcome($link, $student_id, $session, $classid, $classsectionactual, true)
		: build_promotion_outcome('pending', '', 'system', 'not_applicable');
	$resultAcademicRowCount = count($items);
	$resultBrandPalette = build_result_brand_palette($rowsch_settings['app_primary_color_code'] ?? '#1f4e78');
	?>
	<div class="container-fluid result-report-controls" data-result-no-print>
		<div class="row" id="non-printable" style="margin-top: 20px;">
			<div class="col-md-10">
				<a href="<?php echo $defRUladmin; ?>/admin/examResult.php" style="color: black; font-size: 20px;"><i class="fa fa-angle-double-left"></i> Back</a>
			</div>
			<div class="col-md-2">
				<a href="" style="color: #000000; font-weight: 600;" onclick="window.print()"><i class="fa fa-print"></i> Print</a>
			</div>
		</div>
    </div>

	<div class="result-report-preview" data-result-report-preview>
		<div class="card result-report" id="printable" data-result-report data-academic-row-count="<?php echo (int) $resultAcademicRowCount; ?>" style="--result-brand: <?php echo $resultBrandPalette['brand']; ?>; --result-brand-strong: <?php echo $resultBrandPalette['strong']; ?>; --result-brand-soft: <?php echo $resultBrandPalette['soft']; ?>; --result-brand-contrast: <?php echo $resultBrandPalette['contrast']; ?>;">
			<div class="result-report__content" data-result-report-content>
			<img class="watermark-logo result-report__watermark" src="https://schoollift.s3.us-east-2.amazonaws.com/<?php echo htmlspecialchars($rowsch_settings['app_logo'], ENT_QUOTES, 'UTF-8'); ?>" alt="">

			<div class="card-body" style="color: black;">
				<div class="rel">
					<!-- School header -->
					<div class="row result-report__legacy-header">
						<div class="col">
							<div align="center">
								<img src="https://schoollift.s3.us-east-2.amazonaws.com/<?php echo htmlspecialchars($rowsch_settings['app_logo'], ENT_QUOTES, 'UTF-8'); ?>" class="img-fluid" style="margin: 10px; width: 50%;" alt="School logo">
							</div>
						</div>
						<div class="col-6">
							<p class="schname" style="font-size:25px"><?php echo htmlspecialchars($rowsch_settings['name'], ENT_QUOTES, 'UTF-8'); ?></p>
							<p class="schloc" style="color: rgb(185, 7, 7);font-size:16px;margin-top:-20px;"><?php echo htmlspecialchars($rowsch_settings['address'], ENT_QUOTES, 'UTF-8'); ?>.</p>
							<div style="margin-top:-10px;text-align:center">
								<span>Email: <?php echo htmlspecialchars($rowsch_settings['email'], ENT_QUOTES, 'UTF-8'); ?></span><br />
								<span>Website: <?php echo htmlspecialchars($defRUlsec, ENT_QUOTES, 'UTF-8'); ?></span>
							</div>
						</div>
						<div class="col">
							<img src="https://schoollift.s3.us-east-2.amazonaws.com/<?php echo htmlspecialchars($studimage, ENT_QUOTES, 'UTF-8'); ?>" align="center" class="img-fluid" style="margin: 10px; width: 45%;height:120px" alt="Student photograph">
						</div>
					</div><br>

					<div align="center" class="result-report__legacy-title">
						<h5 class="report-title" style="font-size: 17px; font-weight: 500;margin-top:-40px"><?php echo $resultSubType === 'midterm' ? 'MIDTERM PROGRESS REPORT' : 'TERM PROGRESS REPORT'; ?> FOR <?php echo htmlspecialchars($term, ENT_QUOTES, 'UTF-8'); ?> TERM, <?php echo htmlspecialchars($session_name, ENT_QUOTES, 'UTF-8'); ?> SESSION</h5>
					</div>

					<!-- Student info -->
					<div class="container-motto">
						<div class="row" style="margin: 10px;">
							<div class="col-4">
								<h5>NAME: <b><?php echo htmlspecialchars($student_name, ENT_QUOTES, 'UTF-8'); ?></b></h5>
							</div>
							<div class="col-4">
								<h5>CLASS: <b><?php echo htmlspecialchars($class_name . ' ' . $section_name, ENT_QUOTES, 'UTF-8'); ?></b></h5>
							</div>
							<div class="col-4">
								<h5>GENDER: <b><?php echo htmlspecialchars($student_gender, ENT_QUOTES, 'UTF-8'); ?></b></h5>
							</div>
						</div>
						<?php
						$resultSummaryPanelSections = array('statistics');
						include __DIR__ . '/partials/result-summary-panel.php';
						unset($resultSummaryPanelSections);
						?>
					</div>

					<!-- Result table -->
					<div class="result table-responsive result-report__academic-table-wrap" style="margin: 10px; margin-top: 5px;">
						<table class="table-bordered table-striped tab table-sm tb-result-border result-report__academic-table" style="width:98%;">
							<thead>
							<tr>
								<th>SUBJECT</th>
								<th><?php echo $assessment_label; ?></th>
								<?php foreach ($result_labels as $label): ?>
									<th><?php echo htmlspecialchars($label); ?></th>
								<?php endforeach; ?>
							</tr>
							</thead>
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

					<div class="result-report__legacy-comments" aria-label="Result comments">
						<div class="row mt-4">
							<div class="col-sm-10 col-md-10">
								<p class="pl-3" style="text-align: justify;"><b>CLASS TEACHER'S REMARK:</b> <?php echo htmlspecialchars($teacher_remark, ENT_QUOTES, 'UTF-8'); ?></p>
							</div>
							<div class="col-sm-2 col-md-2 signature-container" aria-label="Class teacher's signature">
								<?php echo $teacher_sign; ?>
							</div>
						</div>

						<div class="row mt-2">
							<div class="col-sm-10 col-md-10">
								<p class="pl-3" style="text-align: justify;"><b>PRINCIPAL/HEAD TEACHER'S COMMENT:</b> <?php echo htmlspecialchars($principal_remark, ENT_QUOTES, 'UTF-8'); ?></p>
							</div>
							<div class="col-sm-2 col-md-2 signature-container" aria-label="Head teacher's signature">
								<?php echo $principal_sign; ?>
							</div>
						</div>
					</div>

					<!-- Next term begins (optional) -->
					<?php
					// You can add next term date logic if needed
					?>
					<?php
					$resultSummaryPanelSections = array('promotion');
					include __DIR__ . '/partials/result-summary-panel.php';
					unset($resultSummaryPanelSections);
					?>
				</div>
			</div>
			</div>
		</div>
	</div>

	<script src="../assets/js/result-report-print.js"></script>
</body>

</html>
