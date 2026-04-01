<?php if (is_superadmin_loggedin() ): ?>
	<section class="panel">
		<header class="panel-heading">
			<h4 class="panel-title"><?=translate('select_ground')?></h4>
		</header>
		<?php echo form_open($this->uri->uri_string(), array('id' => 'frmsection', 'class' => 'validate'));?>
		<div class="panel-body">
			<div class="row mb-sm">
				<div class="col-md-offset-3 col-md-6">
					<div class="form-group">
						<label class="control-label"><?=translate('branch')?> <span class="required">*</span></label>
						<?php
							$arrayBranch = $this->app_lib->getSelectList('branch');
							echo form_dropdown("branch_id", $arrayBranch, $branch_id, "class='form-control' id='branch_id' required
							data-plugin-selectTwo data-width='100%' data-minimum-results-for-search='Infinity'");
						?>
					</div>
				</div>
			</div>
		</div>
		<footer class="panel-footer">
			<div class="row">
				<div class="col-md-offset-10 col-md-2">
					<button type="submit" class="btn btn-default btn-block"> <i class="fas fa-filter"></i> <?=translate('filter')?></button>
				</div>
			</div>
		</footer>
		<?php echo form_close();?>
	</section>
<?php endif; if (!empty($branch_id)): ?>
<section class="panel full-screen">
	<header class="panel-heading">
		<h4 class="panel-title"><i class="fas fa-qrcode"></i> <?=translate('qr_code') . " " . translate('attendance')?></h4>
	</header>
	<div class="panel-body">
	<div class="row qrcode-scan">
	    <div class="col-md-4 col-sm-12 mb-md">
	    <div class="form-group box mt-md">
	    	<span class="title"><?=translate('scan_qr_code')?></span>
	        <div class="justify-content-md-center" id="reader" width="300px" height="300px"></div>
	        <span class="text-center" id='qr_status'><?php echo translate('scanning')?></span>
					<div class="radio-custom radio-success radio-inline mt-md">
						<input type="radio" value="in_time" checked name="in_out_time" id="in_time">
						<label for="in_time"><?=translate('in_time')?></label>
					</div>
					<div class="radio-custom radio-success radio-inline mt-md">
						<input type="radio" value="out_time" name="in_out_time" id="out_time">
						<label for="out_time"><?=translate('out_time')?></label>
					</div>
	    </div>
	    </div>
	    <div class="col-md-8 col-sm-12 mt-md">
				<div class="row">
					<div class="col-md-12 mt-md">
						<h4><?php echo translate('employees_attendance') . ' ' . translate('list'); ?></h4>
						<table class="table table-bordered table-hover table-condensed table-staff nowrap"  cellpadding="0" cellspacing="0" width="100%" >
							<thead>
								<tr>
									<th>#</th>
									<th><?=translate('name')?></th>
									<th><?=translate('staff_id')?></th>
									<th><?=translate('role')?></th>
									<th><?=translate('date')?></th>
									<th><?=translate('in_time')?></th>
									<th><?=translate('out_time')?></th>
								</tr>
							</thead>
						</table>
					</div>
				</div>
				<div class="row">
					<div class="col-md-12 mt-md">
						<h4><?php echo translate('student_attendance') . ' ' . translate('list'); ?></h4>
						<div class="export_title"><?php echo translate('attendance') . " " . translate('list'); ?></div>
						<table class="table table-bordered table-hover table-condensed table-stu nowrap" cellpadding="0" cellspacing="0" width="100%" >
							<thead>
								<tr>
									<th>#</th>
									<th><?=translate('name')?></th>
									<th><?=translate('class')?></th>
									<th><?=translate('register_no')?></th>
									<th><?=translate('roll')?></th>
									<th><?=translate('date')?></th>
									<th><?=translate('in_time')?></th>
									<th><?=translate('out_time')?></th>
								</tr>
							</thead>
						</table>
					</div>
				</div>

	    </div>
	</div>
</section>
<div class="zoom-anim-dialog modal-block modal-block-primary mfp-hide" id="qr_staffDetails">
	<section class="panel">
		<header class="panel-heading">
			<h4 class="panel-title">
				<i class="far fa-user-circle"></i> <?=translate('staff_details')?>
			</h4>
		</header>
		<div class="panel-body">
			<div class="quick_image">
				<img alt="" class="user-img-circle" id="quick_image" src="<?=base_url('uploads/app_image/defualt.png')?>" width="120" height="120">
			</div>
			<div class="text-center">
				<h4 class="text-weight-semibold mb-xs" id="quick_full_name"></h4>
				<p><span id="quick_role"></p>
			</div>
			<div class="table-responsive mt-md mb-md">
				<table class="table table-striped table-bordered table-condensed mb-none">
					<tbody>
						<tr>
							<th><?=translate('staff_id')?></th>
							<td><span id="quick_staff_id"></span></td>
							<th><?=translate('joining_date')?></th>
							<td><span id="quick_joining_date"></span></td>
						</tr>
						<tr>
							<th><?=translate('department')?></th>
							<td><span id="quick_department"></span></td>
							<th><?=translate('designation')?></th>
							<td><span id="quick_designation"></span></td>
						</tr>
						<tr>
							<th><?=translate('gender')?></th>
							<td><span id="quick_gender"></span></td>
							<th><?=translate('blood_group')?></th>
							<td><span id="quick_blood_group"></span></td>
						</tr>
						<tr>
							<th><?=translate('email')?></th>
							<td colspan="3"><span id="quick_email"></span></td>
						</tr>
						<tr>
							<td colspan="4">
								<div class="mb-sm mt-sm ml-sm checkbox-replace">
									<label class="i-checks"><input type="checkbox" name="attendance" id="chkAttendance-Late" value="L"><i></i> <?=translate('late')?></label>
									<label class="i-checks"><input type="checkbox" name="attendance" id="chkAttendance-halfday" value="HD"><i></i> <?=translate('half_day')?></label>
									<div class="mt-sm">
										<input class="form-control" name="remark" id="attendanceRemark" autocomplete="off" type="text" placeholder="Remarks" value="">
									</div>
								</div>
							</td>
						</tr>
					</tbody>
				</table>
			</div>
		</div>
		<footer class="panel-footer">
			<div class="row">
				<div class="col-md-12 text-right">
					<button class="btn btn-default btn-confirm mr-xs mt-xs" data-loading-text="<i class='fas fa-spinner fa-spin'></i> Processing"><i class="far fa-check-circle"></i> <?=translate('confirm')?></button>
					<button class="btn btn-default modal-dismiss mt-xs"><?=translate('close')?></button>
				</div>
			</div>
		</footer>
	</section>
</div>

<div class="zoom-anim-dialog modal-block modal-block-primary mfp-hide" id="qr_studentDetails">
	<section class="panel">
		<header class="panel-heading">
			<h4 class="panel-title">
				<i class="far fa-user-circle"></i> <?=translate('student_details')?>
			</h4>
		</header>
		<div class="panel-body">
			<div class="quick_image">
				<img alt="" class="user-img-circle" id="quick_image" src="<?=base_url('uploads/app_image/defualt.png')?>" width="120" height="120">
			</div>
			<div class="text-center">
				<h4 class="text-weight-semibold mb-xs" id="quick_full_name"></h4>
				<p><?=translate('student')?> / <span id="quick_category"></p>
			</div>
			<div class="table-responsive mt-md mb-md">
				<table class="table table-striped table-bordered table-condensed mb-none">
					<tbody>
						<tr>
							<th><?=translate('class')?></th>
							<td><span id="quick_class_name"></span></td>
							<th><?=translate('section')?></th>
							<td><span id="quick_section_name"></span></td>
						</tr>
						<tr>
							<th><?=translate('register_no')?></th>
							<td><span id="quick_register_no"></span></td>
							<th><?=translate('roll')?></th>
							<td><span id="quick_roll"></span></td>
						</tr>
						<tr>
							<th><?=translate('admission_date')?></th>
							<td><span id="quick_admission_date"></span></td>
							<th><?=translate('date_of_birth')?></th>
							<td><span id="quick_date_of_birth"></span></td>
						</tr>
						<tr>
							<th><?=translate('email')?></th>
							<td colspan="3"><span id="quick_email"></span></td>
						</tr>
						<tr>
							<td colspan="4">
								<div class="mb-sm mt-sm ml-sm checkbox-replace">
									<label class="i-checks"><input type="checkbox" name="attendance" id="chkAttendance" value="L"><i></i> <?=translate('late')?></label>
									<div class="mt-sm">
										<input class="form-control" name="remark" id="attendanceRemark" autocomplete="off" type="text" placeholder="Remarks" value="">
									</div>
								</div>
							</td>
						</tr>
					</tbody>
				</table>
			</div>
		</div>
		<footer class="panel-footer">
			<div class="row">
				<div class="col-md-12 text-right">
					<button class="btn btn-default btn-confirm mr-xs mt-xs" data-loading-text="<i class='fas fa-spinner fa-spin'></i> Processing"><i class="far fa-check-circle"></i> <?=translate('confirm')?></button>
					<button class="btn btn-default modal-dismiss mt-xs"><?=translate('close')?></button>
				</div>
			</div>
		</footer>
	</section>
</div>

<audio id="successAudio">
  <source src="<?php echo base_url('assets/vendor/qrcode/success.mp3') ?>" type="audio/ogg">
</audio>

<script type="text/javascript">
	var setting = jQuery.parseJSON('<?php echo json_encode($getSettings) ?>');
	var camera = setting.camera;
	var confirmation_popup = setting.confirmation_popup;

	var statusMatched = "<?php echo translate('matched')?>";
	var statusScanning = "<?php echo translate('scanning')?>";

	$(document).ready(function () {
		initDatatable('.table-staff', 'qrcode_attendance/getStaffListDT');
		initDatatable('.table-stu', 'qrcode_attendance/getStuListDT');
	});

	var x = document.getElementById("successAudio");
	var lastResult, modalOpen = 0;
	const html5QrCode = new Html5Qrcode("reader");
	const qrCodeSuccessCallback = (decodedText, decodedResult) => {
		if (decodedText !== lastResult && modalOpen == 0) {
			var inOutTime = $('input[name="in_out_time"]:checked').val();
			x.play();
			lastResult = decodedText;
			modalOpen = 1;
			if (inOutTime == 'out_time') {
				$("#chkAttendance-Late").closest('label').hide();
				$("#chkAttendance-halfday").closest('label').show();
			} else {
				$("#chkAttendance-Late").closest('label').show();
				$("#chkAttendance-halfday").closest('label').hide();
			}
			$('#attendanceRemark').val('');
			$('#chkAttendance-Late').prop('checked', false);
			$('#chkAttendance-halfday').prop('checked', false);
			
			if (setting.auto_late_detect == 1) {
				$("input[name='attendance']").closest('label').hide();
			}
			staffQuickView(decodedText);
			$("#qr_status").html(statusMatched);
			html5QrCode.clear();
		}
	};

	const formatsToSupport = [
		Html5QrcodeSupportedFormats.QR_CODE,
	];

	var config = { fps: 50, qrbox: 200 };
	if ($(window).width() <= '400') {
		config = { fps: 50, qrbox: 150 };
	}
	if ($(window).width() <= '370') {
		config = { fps: 50, qrbox: 120 };
	}

	// if you want to prefer front camera
	html5QrCode.start({
		facingMode: camera
	}, config, qrCodeSuccessCallback).catch((err) => {
		$("#qr_status").css("background", "red");
		$("#reader").addClass("camera-preview").html("Back camera not found.");
		$("#qr_status").html(err);
		alert('Back camera not found.');
		console.log(err);
	});

	// attendance stored in the database
	$('.btn-confirm').on('click', function () {
		var chkAttendance = $("#chkAttendance-Late:checked").val();
		var chkAttendanceHalfday = $("#chkAttendance-halfday:checked").val();
		var attendanceRemark = $("#attendanceRemark").val();
		var btn = $(this);
		var inOutTime = $('input[name="in_out_time"]:checked').val();
		$.ajax({
			url: base_url + 'qrcode_attendance/setAttendanceByQrcode',
			type: 'POST',
			data: {
				'data': lastResult,
				'in_out_time': inOutTime,
				'late': chkAttendance,
				'halfday': chkAttendanceHalfday,
				'attendanceRemark': attendanceRemark,
			},
			dataType: 'json',
			beforeSend: function () {
				btn.button('loading');
			},
			success: function (res) {
				if (res.status == 1) {
					if (res.userType == 'staff') {
						$('.table-staff').DataTable().ajax.reload();
					}
					if (res.userType == 'student') {
						$('.table-stu').DataTable().ajax.reload();
					}	
				}
				$("#qr_status").html(statusScanning);
				modalOpen = 0;
			},
			complete: function (data) {
				btn.button('reset');
				$.magnificPopup.close();
			},
			error: function () {
				btn.button('reset');
			}
		});
	});

	function attendance_submit() {
		var chkAttendance = $("#chkAttendance-Late:checked").val();
		var chkAttendanceHalfday = $("#chkAttendance-halfday:checked").val();
		var attendanceRemark = $("#attendanceRemark").val();
		var inOutTime = $('input[name="in_out_time"]:checked').val();

		$.ajax({
			url: base_url + 'qrcode_attendance/setAttendanceByQrcode',
			type: 'POST',
			data: {
				'data': lastResult,
				'in_out_time': inOutTime,
				'late': chkAttendance,
				'halfday': chkAttendanceHalfday,
				'attendanceRemark': attendanceRemark,
			},
			dataType: 'json',
			beforeSend: function () {
				//some code here
			},
			success: function (res) {
				if (res.status == 1) {

					if (res.userType == 'staff') {
						$('.table-staff').DataTable().ajax.reload();
					}
					if (res.userType == 'student') {
						$('.table-stu').DataTable().ajax.reload();
					}

					swal({
						title: "<?php echo translate('successfully') ?>",
						html: res.message,
						type: "success",
						showCancelButton: false,
						confirmButtonClass: "btn btn-default swal2-btn-default",
						confirmButtonText: "OK",
						buttonsStyling: false,
						timer: 3000
					}).then((result) => {
						$("#qr_status").html(statusScanning);
						modalOpen = 0;
					});
				}
			},
			complete: function (data) {
				//some code here
			},
			error: function () {
				$("#qr_status").html(statusScanning);
				modalOpen = 0;
			}
		});
	}

	$('.modal-dismiss').on('click', function () {
		end_loader()
	});

	$('input[name="in_out_time"]').on('change', function () {
		end_loader()
	});
</script>
<?php endif; ?>