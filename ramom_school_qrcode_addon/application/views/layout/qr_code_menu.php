<li class="nav-parent <?php if ($main_menu == 'qr_attendance' || $main_menu == 'qr_attendance_report') echo 'nav-expanded nav-active';?>">
	<a>
		<i class="fas fa-qrcode"></i><span><?=translate('qr_code') . " " . translate('attendance')?></span>
	</a>
	<ul class="nav nav-children">
		<?php if(get_permission('qr_code_student_attendance', 'is_add') || get_permission('qr_code_employee_attendance', 'is_add')) { ?>
		<li class="<?php if ($sub_page == 'qrcode_attendance/take') echo 'nav-active';?>">
			<a href="<?=base_url('qrcode_attendance/take')?>">
				<span><i class="fas fa-caret-right"></i><?=translate('attendance')?></span>
			</a>
		</li>
		<?php } if(get_permission('qr_code_settings', 'is_view')) { ?>
		<li class="<?php if ($sub_page == 'qrcode_attendance/setting') echo 'nav-active';?>">
			<a href="<?=base_url('qrcode_attendance/settings')?>">
				<span><i class="fas fa-caret-right"></i><?=translate('settings')?></span>
			</a>
		</li>
		<li class="<?php if ($sub_page == 'qrcode_auto_submission/settings') echo 'nav-active';?>">
			<a href="<?=base_url('qrcode_auto_submission/settings')?>">
				<span><i class="fas fa-caret-right"></i><?=translate('submission') . " " . translate('settings')?></span>
			</a>
		</li>
		<?php } ?>
		<?php if(get_permission('qr_code_student_attendance_report', 'is_view') || get_permission('qr_code_employee_attendance_report', 'is_view')) { ?>
		<li class="nav-parent <?php if ($main_menu == 'qr_attendance_report') echo 'nav-expanded nav-active'; ?>">
			<a><i class="fas fa-print"></i><span><?php echo translate('reports'); ?></span></a>
			<ul class="nav nav-children">
			<?php if(get_permission('qr_code_student_attendance_report', 'is_view')) { ?>
				<li class="<?php if ($sub_page == 'qrcode_attendance/studentbydate') echo 'nav-active';?>">
					<a href="<?=base_url('qrcode_attendance/studentbydate')?>">Student Reports By Date</a>
				</li>
			<?php } if(get_permission('qr_code_employee_attendance_report', 'is_view')) { ?>
				<li class="<?php if ($sub_page == 'qrcode_attendance/staffbydate') echo 'nav-active';?>">
					<a href="<?=base_url('qrcode_attendance/staffbydate')?>">Employee Reports By Date</a>
				</li>
			<?php } ?>
			</ul>
		</li>
		<?php } ?>
	</ul>
</li>