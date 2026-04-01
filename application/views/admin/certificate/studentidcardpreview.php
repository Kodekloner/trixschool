<?php
$sample_student = array(
    'student_name' => 'Student Name',
    'admission_no' => 'ADM-1001',
    'class_section' => 'Class 6 - A',
    'father_name' => 'Father Name',
    'mother_name' => 'Mother Name',
    'address' => 'No. 1 Street Name, City',
    'phone' => '0800 000 0000',
    'dob' => '25-06-2006',
    'blood_group' => 'A+',
    'student_session_id' => 1,
    'photo_url' => get_student_image_url('', 'male'),
);
?>
<?php $this->load->view('admin/idcard/_shared_styles'); ?>
<div class="id-card-grid">
    <?php $this->load->view('admin/idcard/_student_card_item', array('card' => $idcard, 'student' => $sample_student)); ?>
</div>
