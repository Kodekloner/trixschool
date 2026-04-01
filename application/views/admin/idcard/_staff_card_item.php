<?php
$card = isset($card) ? $card : null;
$staff = isset($staff) ? $staff : array();
$dimensions = get_id_card_dimension_config($card);
$layout = get_id_card_layout_config(isset($card->layout_json) ? $card->layout_json : '', 'staff', $dimensions['portrait']);
$header_color = !empty($card->header_color) ? $card->header_color : '#0f766e';
$background_url = !empty($card->background) ? get_storage_file_url($card->background) : '';
$logo_url = !empty($card->logo) ? get_storage_file_url($card->logo) : '';
$signature_url = !empty($card->sign_image) ? get_storage_file_url($card->sign_image) : '';
$photo_url = isset($staff['photo_url']) ? $staff['photo_url'] : get_staff_photo_url(isset($staff['image']) ? $staff['image'] : '');
$qr_url = !empty($staff['staff_id']) ? get_staff_attendance_qr_image_url($staff['staff_id']) : '';
$school_name = isset($card->school_name) ? $card->school_name : '';
$school_address = isset($card->school_address) ? $card->school_address : '';
$title = isset($card->title) ? $card->title : '';
$width = format_id_card_measurement($dimensions['width']);
$height = format_id_card_measurement($dimensions['height']);
$unit = $dimensions['unit'];
$photo_shape = !empty($card->photo_style) && $card->photo_style === 'square' ? 'square' : 'round';
$designer_mode = !empty($designer_mode);
?>
<div class="id-card-item">
    <div class="id-card-canvas" style="width: <?php echo $width . $unit; ?>; height: <?php echo $height . $unit; ?>;">
        <div class="id-card-bg" style="<?php echo $background_url !== '' ? 'background-image:url(\'' . $background_url . '\');' : 'background:linear-gradient(160deg, rgba(255,255,255,0.95), rgba(226,232,240,0.9));'; ?>"></div>

        <?php if ($logo_url !== '') { ?>
            <div class="id-card-layer id-card-brand id-card-panel<?php echo $designer_mode ? ' designer-element' : ''; ?>"<?php echo $designer_mode ? ' data-element="logo"' : ''; ?> style="<?php echo get_id_card_box_style($layout, 'logo'); ?>">
                <img src="<?php echo $logo_url; ?>" alt="Logo">
            </div>
        <?php } ?>

        <div class="id-card-layer id-card-text id-card-panel<?php echo $designer_mode ? ' designer-element' : ''; ?>"<?php echo $designer_mode ? ' data-element="school_name"' : ''; ?> style="<?php echo get_id_card_box_style($layout, 'school_name'); ?> font-size: 11px; font-weight: 700;">
            <?php echo html_escape($school_name); ?>
        </div>

        <div class="id-card-layer id-card-text center id-card-panel id-card-address<?php echo $designer_mode ? ' designer-element' : ''; ?>"<?php echo $designer_mode ? ' data-element="school_address"' : ''; ?> style="<?php echo get_id_card_box_style($layout, 'school_address'); ?>">
            <?php echo nl2br(html_escape($school_address)); ?>
        </div>

        <div class="id-card-layer id-card-heading<?php echo $designer_mode ? ' designer-element' : ''; ?>"<?php echo $designer_mode ? ' data-element="title"' : ''; ?> style="<?php echo get_id_card_box_style($layout, 'title'); ?> background: <?php echo html_escape($header_color); ?>;">
            <?php echo html_escape($title); ?>
        </div>

        <div class="id-card-layer id-card-photo<?php echo $designer_mode ? ' designer-element' : ''; ?>"<?php echo $designer_mode ? ' data-element="photo"' : ''; ?> style="<?php echo get_id_card_box_style($layout, 'photo'); ?>">
            <img class="<?php echo $photo_shape; ?>" src="<?php echo $photo_url; ?>" alt="Staff Photo">
        </div>

        <?php if (!empty($card->enable_name)) { ?>
            <div class="id-card-layer id-card-text id-card-panel<?php echo $designer_mode ? ' designer-element' : ''; ?>"<?php echo $designer_mode ? ' data-element="name"' : ''; ?> style="<?php echo get_id_card_box_style($layout, 'name'); ?>">
                <span class="id-card-field-line"><span class="label"><?php echo $this->lang->line('staff'); ?> <?php echo $this->lang->line('name'); ?>:</span> <span class="value"><?php echo html_escape(isset($staff['name']) ? $staff['name'] : ''); ?></span></span>
            </div>
        <?php } ?>

        <?php if (!empty($card->enable_designation)) { ?>
            <div class="id-card-layer id-card-text id-card-panel<?php echo $designer_mode ? ' designer-element' : ''; ?>"<?php echo $designer_mode ? ' data-element="designation"' : ''; ?> style="<?php echo get_id_card_box_style($layout, 'designation'); ?>">
                <span class="id-card-field-line"><span class="label"><?php echo $this->lang->line('designation'); ?>:</span> <span class="value"><?php echo html_escape(isset($staff['designation']) ? $staff['designation'] : ''); ?></span></span>
            </div>
        <?php } ?>

        <?php if (!empty($card->enable_staff_id)) { ?>
            <div class="id-card-layer id-card-text id-card-panel<?php echo $designer_mode ? ' designer-element' : ''; ?>"<?php echo $designer_mode ? ' data-element="staff_id"' : ''; ?> style="<?php echo get_id_card_box_style($layout, 'staff_id'); ?>">
                <span class="id-card-field-line"><span class="label"><?php echo $this->lang->line('staff_id'); ?>:</span> <span class="value"><?php echo html_escape(isset($staff['employee_id']) ? $staff['employee_id'] : ''); ?></span></span>
            </div>
        <?php } ?>

        <?php if (!empty($card->enable_staff_department)) { ?>
            <div class="id-card-layer id-card-text id-card-panel<?php echo $designer_mode ? ' designer-element' : ''; ?>"<?php echo $designer_mode ? ' data-element="department"' : ''; ?> style="<?php echo get_id_card_box_style($layout, 'department'); ?>">
                <span class="id-card-field-line"><span class="label"><?php echo $this->lang->line('department'); ?>:</span> <span class="value"><?php echo html_escape(isset($staff['department']) ? $staff['department'] : ''); ?></span></span>
            </div>
        <?php } ?>

        <?php if (!empty($card->enable_fathers_name)) { ?>
            <div class="id-card-layer id-card-text id-card-panel<?php echo $designer_mode ? ' designer-element' : ''; ?>"<?php echo $designer_mode ? ' data-element="father_name"' : ''; ?> style="<?php echo get_id_card_box_style($layout, 'father_name'); ?>">
                <span class="id-card-field-line"><span class="label"><?php echo $this->lang->line('father_name'); ?>:</span> <span class="value"><?php echo html_escape(isset($staff['father_name']) ? $staff['father_name'] : ''); ?></span></span>
            </div>
        <?php } ?>

        <?php if (!empty($card->enable_mothers_name)) { ?>
            <div class="id-card-layer id-card-text id-card-panel<?php echo $designer_mode ? ' designer-element' : ''; ?>"<?php echo $designer_mode ? ' data-element="mother_name"' : ''; ?> style="<?php echo get_id_card_box_style($layout, 'mother_name'); ?>">
                <span class="id-card-field-line"><span class="label"><?php echo $this->lang->line('mother_name'); ?>:</span> <span class="value"><?php echo html_escape(isset($staff['mother_name']) ? $staff['mother_name'] : ''); ?></span></span>
            </div>
        <?php } ?>

        <?php if (!empty($card->enable_date_of_joining)) { ?>
            <div class="id-card-layer id-card-text id-card-panel<?php echo $designer_mode ? ' designer-element' : ''; ?>"<?php echo $designer_mode ? ' data-element="date_of_joining"' : ''; ?> style="<?php echo get_id_card_box_style($layout, 'date_of_joining'); ?>">
                <span class="id-card-field-line"><span class="label"><?php echo $this->lang->line('date_of_joining'); ?>:</span> <span class="value"><?php echo html_escape(isset($staff['date_of_joining']) ? $staff['date_of_joining'] : ''); ?></span></span>
            </div>
        <?php } ?>

        <?php if (!empty($card->enable_staff_phone)) { ?>
            <div class="id-card-layer id-card-text id-card-panel<?php echo $designer_mode ? ' designer-element' : ''; ?>"<?php echo $designer_mode ? ' data-element="phone"' : ''; ?> style="<?php echo get_id_card_box_style($layout, 'phone'); ?>">
                <span class="id-card-field-line"><span class="label"><?php echo $this->lang->line('phone'); ?>:</span> <span class="value"><?php echo html_escape(isset($staff['phone']) ? $staff['phone'] : ''); ?></span></span>
            </div>
        <?php } ?>

        <?php if (!empty($card->enable_staff_dob)) { ?>
            <div class="id-card-layer id-card-text id-card-panel<?php echo $designer_mode ? ' designer-element' : ''; ?>"<?php echo $designer_mode ? ' data-element="dob"' : ''; ?> style="<?php echo get_id_card_box_style($layout, 'dob'); ?>">
                <span class="id-card-field-line"><span class="label"><?php echo $this->lang->line('date_of_birth'); ?>:</span> <span class="value"><?php echo html_escape(isset($staff['dob']) ? $staff['dob'] : ''); ?></span></span>
            </div>
        <?php } ?>

        <?php if (!empty($card->enable_permanent_address)) { ?>
            <div class="id-card-layer id-card-text block id-card-panel<?php echo $designer_mode ? ' designer-element' : ''; ?>"<?php echo $designer_mode ? ' data-element="address"' : ''; ?> style="<?php echo get_id_card_box_style($layout, 'address'); ?> font-size: 8px;">
                <span class="id-card-field-line"><span class="label"><?php echo $this->lang->line('address'); ?>:</span> <span class="value"><?php echo html_escape(isset($staff['address']) ? $staff['address'] : ''); ?></span></span>
            </div>
        <?php } ?>

        <?php if ($signature_url !== '') { ?>
            <div class="id-card-layer id-card-signature<?php echo $designer_mode ? ' designer-element' : ''; ?>"<?php echo $designer_mode ? ' data-element="signature"' : ''; ?> style="<?php echo get_id_card_box_style($layout, 'signature'); ?>">
                <img src="<?php echo $signature_url; ?>" alt="Signature">
            </div>
        <?php } ?>

        <?php if ($qr_url !== '') { ?>
            <div class="id-card-layer id-card-qr<?php echo $designer_mode ? ' designer-element' : ''; ?>"<?php echo $designer_mode ? ' data-element="qr"' : ''; ?> style="<?php echo get_id_card_box_style($layout, 'qr'); ?>">
                <img src="<?php echo $qr_url; ?>" alt="Attendance QR">
                <div class="id-card-qr-label">Scan For Attendance</div>
            </div>
        <?php } ?>
    </div>
</div>
