<?php
$card = isset($card) ? $card : null;
$student = isset($student) ? $student : array();
$dimensions = get_id_card_dimension_config($card);
$layout = get_id_card_layout_config(isset($card->layout_json) ? $card->layout_json : '', 'student', $dimensions['portrait']);
$header_color = !empty($card->header_color) ? $card->header_color : '#1f3c88';
$background_url = !empty($card->background) ? get_storage_file_url($card->background) : '';
$logo_url = !empty($card->logo) ? get_storage_file_url($card->logo) : '';
$signature_url = !empty($card->sign_image) ? get_storage_file_url($card->sign_image) : '';
$photo_url = isset($student['photo_url']) ? $student['photo_url'] : get_student_image_url(isset($student['image']) ? $student['image'] : '', isset($student['gender']) ? $student['gender'] : '');
$qr_url = !empty($student['student_session_id']) ? get_student_attendance_qr_image_url($student['student_session_id']) : '';
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
            <img class="<?php echo $photo_shape; ?>" src="<?php echo $photo_url; ?>" alt="Student Photo">
        </div>

        <?php if (!empty($card->enable_student_name)) { ?>
            <div class="id-card-layer id-card-text id-card-panel<?php echo $designer_mode ? ' designer-element' : ''; ?>"<?php echo $designer_mode ? ' data-element="student_name"' : ''; ?> style="<?php echo get_id_card_box_style($layout, 'student_name'); ?>">
                <span class="id-card-field-line"><span class="label"><?php echo $this->lang->line('student_name'); ?>:</span> <span class="value"><?php echo html_escape(isset($student['student_name']) ? $student['student_name'] : ''); ?></span></span>
            </div>
        <?php } ?>

        <?php if (!empty($card->enable_admission_no)) { ?>
            <div class="id-card-layer id-card-text id-card-panel<?php echo $designer_mode ? ' designer-element' : ''; ?>"<?php echo $designer_mode ? ' data-element="admission_no"' : ''; ?> style="<?php echo get_id_card_box_style($layout, 'admission_no'); ?>">
                <span class="id-card-field-line"><span class="label"><?php echo $this->lang->line('admission_no'); ?>:</span> <span class="value"><?php echo html_escape(isset($student['admission_no']) ? $student['admission_no'] : ''); ?></span></span>
            </div>
        <?php } ?>

        <?php if (!empty($card->enable_class)) { ?>
            <div class="id-card-layer id-card-text id-card-panel<?php echo $designer_mode ? ' designer-element' : ''; ?>"<?php echo $designer_mode ? ' data-element="class"' : ''; ?> style="<?php echo get_id_card_box_style($layout, 'class'); ?>">
                <span class="id-card-field-line"><span class="label"><?php echo $this->lang->line('class'); ?>:</span> <span class="value"><?php echo html_escape(isset($student['class_section']) ? $student['class_section'] : ''); ?></span></span>
            </div>
        <?php } ?>

        <?php if (!empty($card->enable_fathers_name)) { ?>
            <div class="id-card-layer id-card-text id-card-panel<?php echo $designer_mode ? ' designer-element' : ''; ?>"<?php echo $designer_mode ? ' data-element="father_name"' : ''; ?> style="<?php echo get_id_card_box_style($layout, 'father_name'); ?>">
                <span class="id-card-field-line"><span class="label"><?php echo $this->lang->line('father_name'); ?>:</span> <span class="value"><?php echo html_escape(isset($student['father_name']) ? $student['father_name'] : ''); ?></span></span>
            </div>
        <?php } ?>

        <?php if (!empty($card->enable_mothers_name)) { ?>
            <div class="id-card-layer id-card-text id-card-panel<?php echo $designer_mode ? ' designer-element' : ''; ?>"<?php echo $designer_mode ? ' data-element="mother_name"' : ''; ?> style="<?php echo get_id_card_box_style($layout, 'mother_name'); ?>">
                <span class="id-card-field-line"><span class="label"><?php echo $this->lang->line('mother_name'); ?>:</span> <span class="value"><?php echo html_escape(isset($student['mother_name']) ? $student['mother_name'] : ''); ?></span></span>
            </div>
        <?php } ?>

        <?php if (!empty($card->enable_address)) { ?>
            <div class="id-card-layer id-card-text block id-card-panel<?php echo $designer_mode ? ' designer-element' : ''; ?>"<?php echo $designer_mode ? ' data-element="address"' : ''; ?> style="<?php echo get_id_card_box_style($layout, 'address'); ?> font-size: 8px;">
                <span class="id-card-field-line"><span class="label"><?php echo $this->lang->line('address'); ?>:</span> <span class="value"><?php echo html_escape(isset($student['address']) ? $student['address'] : ''); ?></span></span>
            </div>
        <?php } ?>

        <?php if (!empty($card->enable_phone)) { ?>
            <div class="id-card-layer id-card-text id-card-panel<?php echo $designer_mode ? ' designer-element' : ''; ?>"<?php echo $designer_mode ? ' data-element="phone"' : ''; ?> style="<?php echo get_id_card_box_style($layout, 'phone'); ?>">
                <span class="id-card-field-line"><span class="label"><?php echo $this->lang->line('phone'); ?>:</span> <span class="value"><?php echo html_escape(isset($student['phone']) ? $student['phone'] : ''); ?></span></span>
            </div>
        <?php } ?>

        <?php if (!empty($card->enable_dob)) { ?>
            <div class="id-card-layer id-card-text id-card-panel<?php echo $designer_mode ? ' designer-element' : ''; ?>"<?php echo $designer_mode ? ' data-element="dob"' : ''; ?> style="<?php echo get_id_card_box_style($layout, 'dob'); ?>">
                <span class="id-card-field-line"><span class="label"><?php echo $this->lang->line('date_of_birth'); ?>:</span> <span class="value"><?php echo html_escape(isset($student['dob']) ? $student['dob'] : ''); ?></span></span>
            </div>
        <?php } ?>

        <?php if (!empty($card->enable_blood_group)) { ?>
            <div class="id-card-layer id-card-text id-card-panel<?php echo $designer_mode ? ' designer-element' : ''; ?>"<?php echo $designer_mode ? ' data-element="blood_group"' : ''; ?> style="<?php echo get_id_card_box_style($layout, 'blood_group'); ?>">
                <span class="id-card-field-line"><span class="label"><?php echo $this->lang->line('blood_group'); ?>:</span> <span class="value"><?php echo html_escape(isset($student['blood_group']) ? $student['blood_group'] : ''); ?></span></span>
            </div>
        <?php } ?>

        <?php if ($signature_url !== '') { ?>
            <div class="id-card-layer id-card-signature<?php echo $designer_mode ? ' designer-element' : ''; ?>"<?php echo $designer_mode ? ' data-element="signature"' : ''; ?> style="<?php echo get_id_card_box_style($layout, 'signature'); ?>">
                <img src="<?php echo $signature_url; ?>" alt="Signature">
            </div>
        <?php } ?>

        <?php if ($qr_url !== '') { ?>
            <div class="id-card-layer id-card-qr<?php echo $designer_mode ? ' designer-element' : ''; ?>"<?php echo $designer_mode ? ' data-element="qr"' : ''; ?> style="<?php echo get_id_card_box_style($layout, 'qr'); ?>">
                <div class="id-card-qr-media">
                    <img src="<?php echo $qr_url; ?>" alt="Attendance QR">
                </div>
                <div class="id-card-qr-label">Scan For Attendance</div>
            </div>
        <?php } ?>
    </div>
</div>
