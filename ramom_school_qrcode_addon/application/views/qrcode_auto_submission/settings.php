<style type="text/css">
	.table-bordered > tbody > tr > td {
		vertical-align: middle;
	}
</style>
<?php $widget = (is_superadmin_loggedin() ? "col-md-6" : "col-md-offset-3 col-md-6"); ?>
<div class="row">
	<div class="col-md-12">
		<section class="panel">
			<header class="panel-heading">
				<h4 class="panel-title"><?=translate('select_ground')?></h4>
			</header>
			<?php echo form_open($this->uri->uri_string(), array('class' => 'validate'));?>
			<div class="panel-body">
				<div class="row mb-sm">
				<?php if (is_superadmin_loggedin() ): ?>
					<div class="col-md-6">
						<div class="form-group">
							<label class="control-label"><?=translate('branch')?> <span class="required">*</span></label>
							<?php
								$arrayBranch = $this->app_lib->getSelectList('branch');
								echo form_dropdown("branch_id", $arrayBranch, set_value('branch_id'), "class='form-control' onchange='getClassByBranch(this.value)'
								data-plugin-selectTwo data-width='100%' ");
							?>
							<span class="error"><?=form_error('branch_id')?></span>
						</div>
					</div>
				<?php endif; ?>
					<div class="<?php echo $widget; ?> mb-sm">
						<div class="form-group">
							<label class="control-label"><?=translate('class')?> <span class="required">*</span></label>
							<?php
								$arrayClass = $this->app_lib->getClass($branch_id);
								echo form_dropdown("class_id", $arrayClass, set_value('class_id'), "class='form-control' id='class_id' onchange='getSectionByClass(this.value,1)'
								data-plugin-selectTwo data-width='100%' ");
							?>
							<span class="error"><?=form_error('class_id')?></span>
						</div>
					</div>
				</div>
			</div>
			<footer class="panel-footer">
				<div class="row">
					<div class="col-md-offset-10 col-md-2">
						<button type="submit" name="search" value="1" class="btn btn-default btn-block"> <i class="fas fa-filter"></i> <?=translate('filter')?></button>
					</div>
				</div>
			</footer>
			<?php echo form_close();?>
		</section>
<?php if (isset($getSections)): ?>
		<section class="panel appear-animation" data-appear-animation="<?=$global_config['animations']?>" data-appear-animation-delay="100">
			<header class="panel-heading">
				<h4 class="panel-title"><i class="far fa-clock"></i> <?php echo translate('auto_attendance_submission_time'); ?></h4>
			</header>
            <?php echo form_open('qrcode_auto_submission/save', array('class' => 'frm-submit-msg')); ?>
            	<input type="hidden" name="branch_id" value="<?php echo html_escape($branch_id) ?>">
            	<input type="hidden" name="class_id" value="<?php echo html_escape($class_id) ?>">
				<div class="panel-body">
			<?php if (!empty($getSections[0]->submission_id)) { ?>
			 		<div class="alert alert-success">Today's attendance has already been submitted, would you like to edit it?</div>
			<?php } ?>
					<div class="form-group">
						<div class="mt-lg">
							<div class="checkbox-replace">
								<label class="i-checks"><input type="checkbox" name="copy_first_for_all" id="copy_first_for_all" value="1"><i></i>  <?=translate('copy_first_details_for_all')?></label>
							</div>
						</div>
					</div>
					<div class="table-responsive">
						<table class="table table-bordered table-condensed table-hover mt-md">
							<thead>
								<th>#</th>
								<th><?=translate('section')?> <span class="required">*</span></th>
								<th><?=translate('time')?> <span class="required">*</span></th>
								<th><?=translate('status')?></th>
							</thead>
							<tbody>
								<?php
								$count = 1;
								foreach ($getSections as $key => $value) {
									?>
								<tr>
									<?php echo form_hidden(array("attendance[$key][section_id]" => html_escape($value->section_id))); ?>
									<td width="50"><?php echo $count++; ?></td>
									<td><?php echo $value->class_name . " (" .  $value->section_name . ")"; ?></td>
									<td width="300">
										<div class="form-group">
											<div class="input-group">
												<span class="input-group-addon"><i class="far fa-clock"></i></span>
												<input type="text" name="attendance[<?php echo $key ?>][time]" class="form-control sch_timepicker" placeholder="<?=translate('enter_time')?>" value="<?php echo html_escape($value->time) ?>" />
											</div>
											<span class="error"></span>
										</div>
									</td>
									<td width="150">
				                        <div class="material-switch ml-xs">
				                            <input class="schedule_cb" id="switch_<?php echo $key ?>" data-menu-id="<?php echo $key ?>" <?php echo (($value->status !== null && $value->status == 0) ? "" : "checked"); ?> name="attendance[<?php echo $key ?>][status]" value="1" type="checkbox" />
				                            <label for="switch_<?php echo $key ?>" class="label-primary"></label>
				                        </div>
									</td>
								</tr>
							<?php } ?>
							</tbody>
						</table>
					</div>
				</div>
				<div class="panel-footer">
					<div class="row">
						<div class="col-md-offset-10 col-md-2">
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
	$("#copy_first_for_all").on("change", function() {	
		if ($(this).is(":checked"))
		{
            var firstTimeValue= $('form.frm-submit-msg').find('input.sch_timepicker').first().val();
			$('.sch_timepicker').not(':first').each(function() {
				$(this).timepicker('setTime', firstTimeValue);
			});
			
			var isFirstChecked= $('form.frm-submit-msg').find('input.schedule_cb').first().prop('checked');
			// Update all other switches to match the first one
			$('input.schedule_cb').not(':first').each(function() {
			    // Set the checked property
			    $(this).prop('checked', isFirstChecked);
			    // Trigger 'change' in case you have other logic 
			    // or UI plugins (like Bootstrap Switch) listening
			    $(this).trigger('change'); 
			});
		}
	});

	$('input.sch_timepicker').first().on('change', function () {
	    if ($('#copy_first_for_all').is(':checked')) {
	        var newTime = $(this).val();
	        // Update all except the first one (to avoid redundant calls)
	        $('.sch_timepicker').not(this).timepicker('setTime', newTime);
	    }
	});

	// Optional: Sync switches in real-time when the first one is toggled
	$('input.schedule_cb').first().on('change', function () {
	    if ($('#copy_first_for_all').is(':checked')) {
	        var isChecked = $(this).prop('checked');
	        $('input.schedule_cb').not(':first').prop('checked', isChecked).trigger('change');
	    }
	});

	$(document).ready(function () {
		$('.sch_timepicker').timepicker({
			minuteStep: 5,
			defaultTime: null
		});
	});
</script>
<?php endif; ?>