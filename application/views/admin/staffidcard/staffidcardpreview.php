<?php
$sample_staff = array(
    'name' => 'Staff Name',
    'employee_id' => 'STF-9000',
    'designation' => 'Administrator',
    'department' => 'Admin',
    'father_name' => 'Father Name',
    'mother_name' => 'Mother Name',
    'date_of_joining' => date($this->customlib->getSchoolDateFormat(), strtotime('2020-01-01')),
    'phone' => '0800 000 0000',
    'dob' => date($this->customlib->getSchoolDateFormat(), strtotime('1990-01-01')),
    'address' => 'No. 1 Street Name, City',
    'staff_id' => 1,
    'photo_url' => get_staff_photo_url(''),
);
?>
<?php $this->load->view('admin/idcard/_shared_styles'); ?>
<div class="id-card-sheet">
    <div class="id-card-grid">
        <?php $this->load->view('admin/idcard/_staff_card_item', array('card' => $idcard, 'staff' => $sample_staff)); ?>
    </div>
</div>
