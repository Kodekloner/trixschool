<div class="row">
	<div class="col-md-12">
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
		<section class="panel">
			<header class="panel-heading">
				<h4 class="panel-title"><i class="fas fa-qrcode"></i> <?php echo translate('qr_code') . " " . translate('settings'); ?></h4>
			</header>
            <?php echo form_open('qrcode_attendance/settings_save' . get_request_url(), array('class' => 'form-horizontal form-bordered frm-submit')); ?>
				<div class="panel-body">
					<div class="form-group mt-md">
						<label  class="col-md-3 control-label"><?php echo translate('camera'); ?> <span class="required">*</span></label>
						<div class="col-md-6">
							<?php
								$array = array(
									"" => translate('select'),
									"user" => translate("front-camera"),
									"environment" => translate("back-camera")
								);
								echo form_dropdown("camera", $array, set_value('camera', $setting['camera']), "class='form-control' data-plugin-selectTwo
								data-width='100%' id='captchaStatus' data-minimum-results-for-search='Infinity'");
							?>
							<span class="error"></span>
						</div>
					</div>
					<div class="form-group">
						<label  class="col-md-3 control-label"><?php echo translate('confirmation_popup'); ?> <span class="required">*</span></label>
						<div class="col-md-6">
							<?php
								$array = array(
									"0" => translate('no'),
									"1" => translate('yes')
								);
								echo form_dropdown("confirmation_popup", $array, set_value('confirmation_popup', $setting['confirmation_popup']), "class='form-control' data-plugin-selectTwo
								data-width='100%' data-minimum-results-for-search='Infinity'");
							?>
							<span class="error"></span>
						</div>
					</div>
					<div class="form-group">
						<label  class="col-md-3 control-label"><?php echo translate('auto_late_detect'); ?> <span class="required">*</span></label>
						<div class="col-md-6">
							<?php
								$array = array(
									"0" => translate('no'),
									"1" => translate('yes')
								);
								echo form_dropdown("auto_late_detect", $array, set_value('auto_late_detect', $setting['auto_late_detect']), "class='form-control' data-plugin-selectTwo
								data-width='100%' data-minimum-results-for-search='Infinity'");
							?>
							<span class="error"></span>
						</div>
					</div>
					<div class="form-group">
						<label class="col-md-3 control-label"><?php echo translate('staff_in_time'); ?> <span class="required">*</span></label>
						<div class="col-md-6">
							<div class="input-group">
								<span class="input-group-addon"><i class="far fa-clock"></i></span>
								<input type="text" name="staff_in_time" class="form-control timepicker" value="<?php echo  $setting['staff_in_time'] ?>" />
							</div>
							<span class="error"></span>
						</div>
					</div>
					<div class="form-group">
						<label class="col-md-3 control-label"><?php echo translate('staff_out_time'); ?> <span class="required">*</span></label>
						<div class="col-md-6">
							<div class="input-group">
								<span class="input-group-addon"><i class="far fa-clock"></i></span>
								<input type="text" name="staff_out_time" class="form-control timepicker" value="<?php echo  $setting['staff_out_time'] ?>" />
							</div>
							<span class="error"></span>
						</div>
					</div>

					<div class="form-group">
						<label class="col-md-3 control-label"><?php echo translate('student_in_time'); ?> <span class="required">*</span></label>
						<div class="col-md-6">
							<div class="input-group">
								<span class="input-group-addon"><i class="far fa-clock"></i></span>
								<input type="text" name="student_in_time" class="form-control timepicker" value="<?php echo  $setting['student_in_time'] ?>" />
							</div>
							<span class="error"></span>
						</div>
					</div>
					<div class="form-group">
						<label class="col-md-3 control-label"><?php echo translate('student_out_time'); ?> <span class="required">*</span></label>
						<div class="col-md-6 mb-md">
							<div class="input-group">
								<span class="input-group-addon"><i class="far fa-clock"></i></span>
								<input type="text" name="student_out_time" class="form-control timepicker" value="<?php echo  $setting['student_out_time'] ?>" />
							</div>
							<span class="error"></span>
						</div>
					</div>
				</div>
				<div class="panel-footer">
					<div class="row">
						<div class="col-md-2 col-md-offset-3">
							<button type="submit" class="btn btn-default btn-block" data-loading-text="<i class='fas fa-spinner fa-spin'></i> Processing">
								<i class="fas fa-plus-circle"></i> <?php echo translate('save'); ?>
							</button>
						</div>
					</div>
				</div>
			<?php echo form_close(); ?>
		</section>
	</div>
</div>

<script type="text/javascript">


$(document).ready(function () {
	$('.timepicker').timepicker({
	    minuteStep: 15,
	    defaultTime: null
	});
});
</script>

<?php endif; ?>