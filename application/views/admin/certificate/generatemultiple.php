<?php $school = $sch_setting[0]; ?>
<?php $this->load->view('admin/idcard/_shared_styles'); ?>
<div class="id-card-sheet">
    <div class="id-card-grid">
        <?php foreach ($students as $student) {
            $student_data = array(
                'student_name' => $this->customlib->getFullName($student->firstname, $student->middlename, $student->lastname, $sch_settingdata->middlename, $sch_settingdata->lastname),
                'admission_no' => $student->admission_no,
                'class_section' => $student->class . ' - ' . $student->section . ' (' . $school['current_session']['session'] . ')',
                'father_name' => $student->father_name,
                'mother_name' => $student->mother_name,
                'address' => $student->current_address,
                'phone' => $student->mobileno,
                'dob' => ($student->dob != '0000-00-00' && !empty($student->dob)) ? date($this->customlib->getSchoolDateFormat(), $this->customlib->dateYYYYMMDDtoStrtotime($student->dob)) : '',
                'blood_group' => $student->blood_group,
                'student_session_id' => $student->student_session_id,
                'image' => $student->image,
                'gender' => $student->gender,
            );
            $this->load->view('admin/idcard/_student_card_item', array('card' => $id_card[0], 'student' => $student_data));
        } ?>
    </div>
</div>
