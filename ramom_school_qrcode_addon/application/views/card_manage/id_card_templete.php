
<section class="panel">
	<div class="tabs-custom">
		<ul class="nav nav-tabs">
			<li class="active">
                <a href="#list" data-toggle="tab">
                    <i class="fas fa-list-ul"></i> <?=translate('id_card') ." ". translate('list')?>
                </a>
			</li>
<?php if (get_permission('id_card_templete', 'is_add')): ?>
			<li>
                <a href="#add" data-toggle="tab">
                   <i class="far fa-edit"></i> <?=translate('add') . " ". translate('id_card')?>
                </a>
			</li>
<?php endif; ?>
		</ul>
		<div class="tab-content">
			<div class="tab-pane box active mb-md" id="list">
				<table class="table table-bordered table-hover mb-none table-condensed table-export">
					<thead>
						<tr>
							<th><?=translate('sl')?></th>
<?php if (is_superadmin_loggedin()): ?>
							<th><?=translate('branch')?></th>
<?php endif; ?>
							<th><?=translate('name')?></th>
							<th><?=translate('applicable_user')?></th>
							<th><?=translate('page_layout')?></th>
							<th class="no-sort"><?=translate('background') . " " . translate('image')?></th>
							<th><?=translate('created_at')?></th>
							<th><?=translate('action')?></th>
						</tr>
					</thead>
					<tbody>
						<?php
						$count = 1;
						foreach ($certificatelist as $row):
						?>
						<tr>
							<td><?php echo $count++; ?></td>
<?php if (is_superadmin_loggedin()): ?>
							<td><?php echo $row['branchname'];?></td>
<?php endif; ?>
							<td><?php echo $row['name']; ?></td>
							<td><?php
							if ($row['user_type'] == 1) {
								echo translate('student');
							} else {
								echo translate('employee');
							} 
							?></td>
							<td><?php echo translate('width') . ' <strong>' . $row['layout_width'] . 'mm</strong> x ' . translate('height') . ' <strong>' . $row['layout_height'] . 'mm</strong>'; ?></td>
							<td>
								<?php
							        $imgPath = 'uploads/certificate/' . $row['background'];
							        if (file_exists($imgPath) && !empty($row['background'])) {
							            $imgPath = base_url($imgPath);
							        } else {
							            $imgPath = base_url('uploads/language_flags/defualt.png');
							        }

								 ?>
								<img class="" src="<?=$imgPath?>" height="50">
							</td>
							<td><?php echo _d($row['created_at']);?></td>
							<td class="min-w-c">
								<!-- view link -->
								<a href="javascript:void(0);" class="btn btn-circle btn-default icon" data-toggle="tooltip" data-original-title="<?=translate('view')?>" 
								onclick="getIDCard('<?=$row['id'] ?>');">
									<i class="fas fa-bars"></i>
								</a>
							<?php if (get_permission('id_card_templete', 'is_edit')) { ?>
								<a href="<?=base_url('card_manage/id_card_templete_edit/' . $row['id']);?>" class="btn btn-circle btn-default icon">
									<i class="fas fa-pen-nib"></i>
								</a>
							<?php } if (get_permission('id_card_templete', 'is_delete')) { ?>
								<!-- deletion link -->
								<?php echo btn_delete('card_manage/id_card_delete/'.$row['id']);?>
							<?php } ?>
							</td>
						</tr>
						<?php endforeach; ?>
					</tbody>
				</table>
			</div>
<?php if (get_permission('id_card_templete', 'is_add')): ?>
			<div class="tab-pane" id="add">
					<?php echo form_open($this->uri->uri_string(), array('class' => 'form-bordered form-horizontal frm-submit-data'));?>
					<?php if (is_superadmin_loggedin()): ?>
						<div class="form-group">
							<label class="control-label col-md-3"><?=translate('branch')?> <span class="required">*</span></label>
							<div class="col-md-8">
								<?php
									$arrayBranch = $this->app_lib->getSelectList('branch');
									echo form_dropdown("branch_id", $arrayBranch, set_value('branch_id'), "class='form-control' data-width='100%' onchange='getClassByBranch(this.value)'
									data-plugin-selectTwo  data-minimum-results-for-search='Infinity'");
								?>
								<span class="error"></span>
							</div>
						</div>
					<?php endif; ?>
					<div class="form-group">
						<label class="col-md-3 control-label"><?=translate('id_card') . " " . translate('name')?> <span class="required">*</span></label>
						<div class="col-md-8">
							<input type="text" class="form-control" name="card_name" value="" />
							<span class="error"></span>
						</div>
					</div>
					<div class="form-group">
						<label class="control-label col-md-3"><?=translate('applicable_user')?> <span class="required">*</span></label>
						<div class="col-md-8">
							<?php
								$arrayType = array(
									'' => translate('select'),
									'1' => translate('student'),
									'2' => translate('employee')
								);
								echo form_dropdown("user_type", $arrayType, set_value('user_type'), "class='form-control' data-width='100%' id='userType'
								data-plugin-selectTwo  data-minimum-results-for-search='Infinity'");
							?>
							<span class="error"></span>
						</div>
					</div>
					<div class="form-group">
						<label class="control-label col-md-3">Page Layout <span class="required">*</span></label>
						<div class="col-md-8">
							<div class="row">
								<div class="col-xs-6">
									<input type="text" class="form-control" name="layout_width" value="" placeholder="Layout Width (mm)" />
									<span class="error"></span>
								</div>
								<div class="col-xs-6">
									<input type="text" class="form-control" name="layout_height" value="" placeholder="Layout Height (mm)" />
								</div>
							</div>
						</div>
					</div>
					<div class="form-group studenttags" style="display: none;">
						<label class="control-label col-md-3">QR Code Text <span class="required">*</span></label>
						<div class="col-md-8">
							<?php
								$arrayType = array(
									'' => translate('select'),
									'name' => translate('name'),
									'birthday' => translate('birthday'),
									'register_no' => translate('register_no'),
									'roll' => translate('roll'),
						
								);
								if ($this->app_lib->isExistingAddon('qrcode') && moduleIsEnabled('qr_code_attendance')) {
									$arrayType['attendance'] = translate('qr_code') . " " . translate('attendance');
								}
								echo form_dropdown("stu_qr_code", $arrayType, set_value('qr_code'), "class='form-control' data-width='100%'
								data-plugin-selectTwo  data-minimum-results-for-search='Infinity'");
							?>
							<span class="error"></span>
						</div>
					</div>
					<div class="form-group stafftag" style="display: none;">
						<label class="control-label col-md-3">QR Code Text <span class="required">*</span></label>
						<div class="col-md-8">
							<?php
								$arrayType = array(
									'' => translate('select'),
									'staff_id' => translate('staff_id'),
									'birthday' => translate('birthday'),
									'joining_date' => translate('joining_date'),
								);
								if ($this->app_lib->isExistingAddon('qrcode') && moduleIsEnabled('qr_code_attendance')) {
									$arrayType['attendance'] = translate('qr_code') . " " . translate('attendance');
								}
								echo form_dropdown("emp_qr_code", $arrayType, set_value('qr_code'), "class='form-control' data-width='100%'
								data-plugin-selectTwo  data-minimum-results-for-search='Infinity'");
							?>
							<span class="error"></span>
						</div>
					</div>
					<div class="form-group">
						<label class="col-md-3 control-label">User Photo Style <span class="required">*</span></label>
						<div class="col-md-8">
							<div class="row">
								<div class="col-xs-6">
									<?php
										$arrayType = array(
											'1' => "Square",
											'2' => "Round"
										);
										echo form_dropdown("photo_style", $arrayType, set_value('photo_style'), "class='form-control' data-width='100%'
										data-plugin-selectTwo  data-minimum-results-for-search='Infinity'");
									?>
								</div>
								<div class="col-xs-6">
									<input type="text" class="form-control" name="photo_size" value="" placeholder="Photo Size (px)" />
								</div>
							</div>
							<span class="error"></span>
						</div>
					</div>

					<div class="form-group">
						<label class="col-md-3 control-label">Layout Spacing <span class="required">*</span></label>
						<div class="col-md-8">
							<div class="row">
								<div class="col-xs-6">
									<input type="text" class="form-control" name="top_space" value="" placeholder="Top Space (px)" />
								</div>
								<div class="col-xs-6">
									<input type="text" class="form-control" name="bottom_space" value="" placeholder="Bottom Space (px)" />
								</div>
							</div>
						</div>
						<div class="mt-md col-md-offset-3 col-md-8">
							<div class="row">
								<div class="col-xs-6">
									<input type="text" class="form-control" name="right_space" value="" placeholder="Right Space (px)" />
								</div>
								<div class="col-xs-6">
									<input type="text" class="form-control" name="left_space" value="" placeholder="Left Space (px)" />
								</div>
							</div>
							<span class="error"></span>
						</div>
					</div>
					<div class="form-group">
						<label class="col-md-3 control-label"><?=translate('signature') . " " . translate('image')?></label>
						<div class="col-md-8">
							<div class="fileupload fileupload-new" data-provides="fileupload">
								<div class="input-append">
									<div class="uneditable-input">
										<i class="fas fa-file fileupload-exists"></i>
										<span class="fileupload-preview"></span>
									</div>
									<span class="btn btn-default btn-file">
										<span class="fileupload-exists">Change</span>
										<span class="fileupload-new">Select file</span>
										<input type="file" name="signature_file" />
									</span>
									<a href="#" class="btn btn-default fileupload-exists" data-dismiss="fileupload">Remove</a>
								</div>
							</div>
							<span class="error"></span>
						</div>
					</div>
					<div class="form-group">
						<label class="col-md-3 control-label"><?=translate('logo') . " " . translate('image')?></label>
						<div class="col-md-8">
							<div class="fileupload fileupload-new" data-provides="fileupload">
								<div class="input-append">
									<div class="uneditable-input">
										<i class="fas fa-file fileupload-exists"></i>
										<span class="fileupload-preview"></span>
									</div>
									<span class="btn btn-default btn-file">
										<span class="fileupload-exists">Change</span>
										<span class="fileupload-new">Select file</span>
										<input type="file" name="logo_file" />
									</span>
									<a href="#" class="btn btn-default fileupload-exists" data-dismiss="fileupload">Remove</a>
								</div>
							</div>
							<span class="error"></span>
						</div>
					</div>
					<div class="form-group">
						<label class="col-md-3 control-label"><?=translate('background') . " " . translate('image')?></label>
						<div class="col-md-8">
							<div class="fileupload fileupload-new" data-provides="fileupload">
								<div class="input-append">
									<div class="uneditable-input">
										<i class="fas fa-file fileupload-exists"></i>
										<span class="fileupload-preview"></span>
									</div>
									<span class="btn btn-default btn-file">
										<span class="fileupload-exists">Change</span>
										<span class="fileupload-new">Select file</span>
										<input type="file" name="background_file" />
									</span>
									<a href="#" class="btn btn-default fileupload-exists" data-dismiss="fileupload">Remove</a>
								</div>
							</div>
							<span class="error"></span>
						</div>
					</div>
					<div class="form-group">
						<label class="col-md-3 control-label">Certificate Content <span class="required">*</span></label>
						<div class="col-md-8">
							<textarea name="content" class="form-control" id="certificateConten" rows="10"></textarea>
							<span class="error"></span>
							<div class="studenttags" style="display: none;">
							<?php 
							$tagsList = $this->card_manage_model->tagsList(1); 
							foreach ($tagsList as $key => $value) {
								?>
								<a data-value=" <?=$value?> " class="btn btn-default mt-sm btn-xs btn_tag"><?=$value?></a>
							<?php } ?>
							</div>
							<div class="stafftag" style="display: none;">
							<?php 
							$tagsList = $this->card_manage_model->tagsList(2); 
							foreach ($tagsList as $key => $value) {
								?>
								<a data-value=" <?=$value?> " class="btn btn-default mt-sm btn-xs btn_tag"><?=$value?></a>
							<?php } ?>
							</div>
						</div>
					</div>
					<footer class="panel-footer">
						<div class="row">
							<div class="col-md-offset-3 col-md-2">
								<button type="submit" class="btn btn-default btn-block" data-loading-text="<i class='fas fa-spinner fa-spin'></i> Processing">
									<i class="fas fa-plus-circle"></i> <?=translate('save')?>
								</button>
							</div>
						</div>
					</footer>
				<?php echo form_close(); ?>
			</div>
<?php endif; ?>
		</div>
	</div>
</section>

<div class="zoom-anim-dialog modal-block modal-block-lg mfp-hide" id="modal">
	<section class="panel">
		<header class="panel-heading">
			<h4 class="panel-title"><i class="fas fa-id-card-alt"></i> <?php echo translate('id_card') . " " . translate('view'); ?></h4>
		</header>
		<div class="panel-body">
			<div id="quick_view"></div>
		</div>
		<footer class="panel-footer">
			<div class="row">
				<div class="col-md-12 text-right">
					<button class="btn btn-default modal-dismiss"><?php echo translate('close'); ?></button>
				</div>
			</div>
		</footer>
	</section>
</div>