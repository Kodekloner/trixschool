<?php $this->load->view('admin/idcard/_shared_styles'); ?>
<div class="id-card-grid">
    <?php foreach ($staffs as $staff_value) {
        $staff_data = array(
            'name' => trim($staff_value->name . ' ' . $staff_value->surname),
            'employee_id' => $staff_value->employee_id,
            'designation' => $staff_value->designation,
            'department' => $staff_value->department,
            'father_name' => $staff_value->father_name,
            'mother_name' => $staff_value->mother_name,
            'date_of_joining' => (!empty($staff_value->date_of_joining) && $staff_value->date_of_joining != '0000-00-00') ? date($this->customlib->getSchoolDateFormat(), $this->customlib->dateYYYYMMDDtoStrtotime($staff_value->date_of_joining)) : '',
            'phone' => $staff_value->contact_no,
            'dob' => (!empty($staff_value->dob) && $staff_value->dob != '0000-00-00') ? date($this->customlib->getSchoolDateFormat(), $this->customlib->dateYYYYMMDDtoStrtotime($staff_value->dob)) : '',
            'address' => $staff_value->local_address,
            'staff_id' => $staff_value->id,
            'image' => $staff_value->image,
        );
        $this->load->view('admin/idcard/_staff_card_item', array('card' => $id_card[0], 'staff' => $staff_data));
    } ?>
</div>
