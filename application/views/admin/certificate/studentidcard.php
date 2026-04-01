<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8" />
    <title>Student ID Card</title>
    <?php $this->load->view('admin/idcard/_shared_styles'); ?>
</head>
<body style="margin: 24px; background: #f8fafc;">
<?php
$card = $idcardlist[0];
$student_row = isset($resultlist[0]) ? $resultlist[0] : array();
$student = array(
    'student_name' => trim((isset($student_row['firstname']) ? $student_row['firstname'] : '') . ' ' . (isset($student_row['lastname']) ? $student_row['lastname'] : '')),
    'admission_no' => isset($student_row['admission_no']) ? $student_row['admission_no'] : '',
    'class_section' => trim((isset($student_row['class']) ? $student_row['class'] : '') . ' - ' . (isset($student_row['section']) ? $student_row['section'] : '')),
    'father_name' => isset($student_row['father_name']) ? $student_row['father_name'] : '',
    'mother_name' => isset($student_row['mother_name']) ? $student_row['mother_name'] : '',
    'address' => isset($student_row['permanent_address']) ? $student_row['permanent_address'] : '',
    'phone' => isset($student_row['father_phone']) ? $student_row['father_phone'] : '',
    'dob' => !empty($student_row['dob']) ? date('d-m-Y', strtotime($student_row['dob'])) : '',
    'blood_group' => isset($student_row['blood_group']) ? $student_row['blood_group'] : '',
    'student_session_id' => isset($student_row['student_session_id']) ? $student_row['student_session_id'] : '',
    'image' => isset($student_row['image']) ? $student_row['image'] : '',
    'gender' => isset($student_row['gender']) ? $student_row['gender'] : '',
);
?>
<div class="id-card-sheet">
    <div class="id-card-grid">
        <?php $this->load->view('admin/idcard/_student_card_item', array('card' => $card, 'student' => $student)); ?>
    </div>
</div>
</body>
</html>
