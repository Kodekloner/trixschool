<?php $widget = (is_superadmin_loggedin() ? 4 : 6); ?>
<section class="panel">
	<header class="panel-heading">
		<h4 class="panel-title"><?=translate('select_ground')?></h4>
	</header>
	<?php echo form_open($this->uri->uri_string());?>
	<div class="panel-body">
		<div class="row mb-sm">
		<?php if (is_superadmin_loggedin() ): ?>
			<div class="col-md-4 mb-sm">
				<div class="form-group">
					<label class="control-label"><?=translate('branch')?> <span class="required">*</span></label>
					<?php
						$arrayBranch = $this->app_lib->getSelectList('branch');
						echo form_dropdown("branch_id", $arrayBranch, set_value('branch_id'), "class='form-control' id='branchID'
						data-plugin-selectTwo data-width='100%' data-minimum-results-for-search='Infinity'");
					?>
				</div>
				<span class="error"><?=form_error('branch_id')?></span>
			</div>
		<?php endif; ?>
			<div class="col-md-<?php echo $widget; ?> mb-sm">
				<div class="form-group">
					<label class="control-label"><?=translate('role')?></label>
					<?php
						$role_list = $this->app_lib->getRoles();
						echo form_dropdown("staff_role", $role_list, set_value('staff_role'), "class='form-control'
						data-plugin-selectTwo data-width='100%' data-minimum-results-for-search='Infinity' ");
					?>
				</div>
			</div>
			<div class="col-md-<?php echo $widget; ?> mb-sm">
				<div class="form-group <?php if (form_error('date')) echo 'has-error'; ?>">
					<label class="control-label"><?=translate('date')?> <span class="required">*</span></label>
					<div class="input-group">
						<input type="text" class="form-control" name="date" id="attDate" value="<?=set_value('date', date("Y-m-d"))?>" autocomplete="off"/>
						<span class="input-group-addon"><i class="icon-event icons"></i></span>
					</div>
					<span class="error"><?=form_error('date')?></span>
				</div>
			</div>
		</div>
	</div>
	<footer class="panel-footer">
		<div class="row">
			<div class="col-md-offset-10 col-md-2">
				<button type="submit" name="search" value="1" class="btn btn btn-default btn-block"> <i class="fas fa-filter"></i> <?=translate('filter')?></button>
			</div>
		</div>
	</footer>
	<?php echo form_close();?>
</section>

<?php if (isset($attendancelist)): ?>
	<section class="panel appear-animation mt-sm" data-appear-animation="<?=$global_config['animations'] ?>" data-appear-animation-delay="100">
		<header class="panel-heading">
			<h4 class="panel-title"><i class="fas fa-users"></i> <?=translate('attendance_report')?></h4>
		</header>
		<div class="panel-body">
			<style type="text/css">
				table.dataTable.table-condensed > thead > tr > th {
				  padding-right: 3px !important;
				}
			</style>
			<!-- hidden school information prints -->
			<div class="export_title">Staff Daily QR code Attendance Sheet on <?=_d($date); ?></div>
			<div class="row">
				<div class="col-md-12">
					<div class="mb-lg">
						<table class="table table-bordered table-hover table-condensed mb-none text-dark table-export">
							<thead>
								<tr>
									<th>#</th>
									<th><?=translate('name')?></th>
									<th><?=translate('staff_id')?></th>
									<th><?=translate('role')?></th>
									<th><?=translate('department')?></th>
									<th><?=translate('attendance')?></th>
									<th><?=translate('in_time')?></th>
									<th><?=translate('out_time')?></th>
									<th><?=translate('remarks')?></th>
								</tr>
							</thead>
							<tbody>
							<?php
							$count = 1; $totalStudent = $totalPresent = $totalAbsent = 0;
							foreach ($attendancelist as $row):
							?>
								<tr>
									<td><?php echo $count++ ?></td>
									<td><?php echo $row->name; ?></td>
									<td><?php echo $row->staffID; ?></td>
									<td><?php echo $row->role_name; ?></td>
									<td><?php echo $row->department_name; ?></td>
									<td><?php
									if($row->status == "P")
										echo '<span class="label label-success">'.translate('present').'</span>';
									if($row->status == "A")
										echo '<span class="label label-danger">'.translate('absent').'</span>';
									 if($row->status == "L")
										echo '<span class="label label-warning">'.translate('late').'</span>';
									 if($row->status == "HD")
										echo '<span class="label label-primary">'.translate('half_day').'</span>';
									?></td>
									<td><?php echo empty($row->in_time) ? '-' : date('h:i A', strtotime($row->in_time)) ?></td>
									<td><?php echo empty($row->out_time) ? '-' : date('h:i A', strtotime($row->out_time)) ?></td>
									<td><?php echo empty($row->remark) ? '-' : $row->remark; ?></td>
									<?php endforeach; ?>
								</tr>
							</tbody>
				
						</table>
					</div>
				</div>
			</div>
		</div>
	</section>
<?php endif; ?>


<script type="text/javascript">
	var dayOfWeekDisabled = "<?php echo $getWeekends ?>";
	$(document).ready(function () {
		var datePicker = $("#attDate").datepicker({
		    orientation: 'bottom',
		    todayHighlight: true,
		    autoclose: true,
		    format: 'yyyy-mm-dd',
		    daysOfWeekDisabled: dayOfWeekDisabled,
		    datesDisabled: ["<?php echo $getHolidays ?>"],
		});   
    });
    
	$('select#branchID').change(function() {
		var branchID = $(this).val();
		$.ajax({
			url: base_url + "attendance/getWeekendsHolidays",
			type: 'POST',
			dataType: "json",
			data: {
				branch_id: branchID,
			},
			success: function (data) {
				$('#attDate').val("");
				$('#attDate').datepicker('setDaysOfWeekDisabled', data.getWeekends);
				$('#attDate').datepicker('setDatesDisabled', JSON.parse(data.getHolidays));
			}
		});
	});
</script>