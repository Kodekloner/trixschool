<style type="text/css">
    
    .table .pull-right {text-align: initial; width: auto; float: right !important;}
</style>
<?php
$can_manage_users = !empty($can_manage_users);
$whatsapp_messaging_can_view = !empty($whatsapp_messaging_can_view);
$school_name = isset($sch_setting->name) ? $sch_setting->name : '';

$format_whatsapp_number = function ($phone) {
    $digits = preg_replace('/[^0-9]/', '', (string) $phone);

    if (strlen($digits) === 11 && substr($digits, 0, 1) === '0') {
        $digits = '234' . substr($digits, 1);
    }

    return $digits;
};

$render_whatsapp_button = function ($phone, $person_name, $person_type) use ($whatsapp_messaging_can_view, $format_whatsapp_number, $school_name) {
    if (!$whatsapp_messaging_can_view) {
        return '';
    }

    $number = $format_whatsapp_number($phone);
    if ($number === '' || strlen($number) < 8) {
        return ' <span class="label label-warning">WhatsApp phone needed</span>';
    }

    $message = 'Hello ' . trim((string) $person_name) . ', this is ' . $school_name . '.';
    if ($person_type === 'parent') {
        $message .= ' We are contacting you about your child from the school.';
    }

    $url = 'https://wa.me/' . $number . '?text=' . rawurlencode($message);

    return ' <a class="btn btn-xs btn-success" href="' . html_escape($url) . '" target="_blank" rel="noopener" title="Message on WhatsApp"><i class="fa fa-whatsapp"></i> WhatsApp</a>';
};
?>

<div class="content-wrapper">  
    <section class="content-header">
        <h1>
            <i class="fa fa-gears"></i> <?php echo $this->lang->line('system_settings'); ?>
        </h1>
    </section>
    <!-- Main content -->
    <section class="content">
        <div class="row">        

            <div class="col-md-12">            
                <div class="nav-tabs-custom theme-shadow">
                    <ul class="nav nav-tabs pull-right">

                        <li><a href="#tab_staff" data-toggle="tab"><?php echo $this->lang->line('staff') ?></a></li>
                        <?php if($sch_setting->guardian_name){ ?>
<li><a href="#tab_parent" data-toggle="tab"><?php echo $this->lang->line('parent') ?></a></li>
                      <?php  }?>
                                                
                        <li class="active"><a href="#tab_students" data-toggle="tab"><?php echo $this->lang->line('student') ?></a></li>

                        <li class="pull-left header"><?php echo $this->lang->line('users'); ?></li>
                    </ul>
                    <div class="tab-content">

                        <div class="tab-pane active table-responsive" id="tab_students">
                            <div class="download_label"><?php echo $this->lang->line('users'); ?></div>
                            <table class="table table-striped table-bordered table-hover example" cellspacing="0" width="100%">
                                <thead>
                                    <tr>
                                        <th><?php echo $this->lang->line('admission_no'); ?></th>
                                        <th><?php echo $this->lang->line('student_name'); ?></th>
                                        <th><?php echo $this->lang->line('username'); ?></th>
                                        <th><?php echo $this->lang->line('class'); ?></th>
                                        <th><?php echo $this->lang->line('father_name'); ?></th>
                                        <th><?php echo $this->lang->line('mobile_no'); ?></th>
                                        <?php if ($can_manage_users) { ?>
                                        <th class="text-right"><?php echo $this->lang->line('action'); ?></th>
                                        <?php } ?>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php
                                    if (!empty($studentList)) {
                                        $count = 1;
                                        foreach ($studentList as $student) {
                                            ?>
                                            <tr>
                                                <td><?php echo $student['admission_no']; ?></td>
                                                <td>
                                                    <a href="<?php echo base_url(); ?>student/view/<?php echo $student['id']; ?>"><?php echo $this->customlib->getFullName($student['firstname'],$student['middlename'],$student['lastname'],$sch_setting->middlename,$sch_setting->lastname); ?>
                                                    </a>
                                                </td>
                                                <td><?php echo $student['username']; ?></td>
                                                <td><?php echo $student['class'] . "(" . $student['section'] . ")" ?></td>
                                                <td><?php echo $student['father_name']; ?></td>
                                                <td><?php echo $student['mobileno']; ?></td>
                                                <?php if ($can_manage_users) { ?>
                                                <td class="relative">
                                                    <div class="material-switch pull-right">
                                                        <input id="student<?php echo $student['user_tbl_id'] ?>" name="someSwitchOption001" type="checkbox" data-role="student" class="chk" data-rowid="<?php echo $student['user_tbl_id'] ?>" value="checked" <?php if ($student['user_tbl_active'] == "yes") echo "checked='checked'"; ?> />
                                                        <label for="student<?php echo $student['user_tbl_id'] ?>" class="label-success"></label>
                                                    </div>
                                                </td>
                                                <?php } ?>
                                            </tr>
                                            <?php
                                            $count++;
                                        }
                                    }
                                    ?>
                                </tbody>
                            </table>
                        </div>
                        <!-- /.tab-pane -->
                        <div class="tab-pane table-responsive" id="tab_parent">
                            <div class="download_label"><?php echo $this->lang->line('users'); ?></div>
                            <table class="table table-striped table-bordered table-hover example" cellspacing="0" width="100%">
                                <thead>
                                    <tr>
                                        <th><?php echo $this->lang->line('guardian_name'); ?></th>
                                        <th><?php echo $this->lang->line('guardian_phone'); ?></th>
                                        <th><?php echo $this->lang->line('username'); ?></th>
                                        <?php if ($can_manage_users) { ?>
                                        <th class="text-right"><?php echo $this->lang->line('action'); ?></th>
                                        <?php } ?>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php
                                    if (!empty($parentList)) {

                                        $count = 1;
                                        foreach ($parentList as $parent) {
                                            ?>
                                            <tr>
                                                <td><?php echo html_escape($parent->guardian_name); ?></td>
                                                <td><?php echo html_escape($parent->guardian_phone); ?><?php echo $render_whatsapp_button($parent->guardian_phone, $parent->guardian_name, 'parent'); ?></td>
                                                <td><?php echo $parent->username; ?></td>
                                                <?php if ($can_manage_users) { ?>
                                                <td class="relative">
                                                    <div class="material-switch pull-right">
                                                        <input id="parent<?php echo $parent->id ?>" name="someSwitchOption001" type="checkbox" class="chk" data-rowid="<?php echo $parent->parent_id ?>" data-role="parent" value="checked" <?php if ($parent->is_active == "yes") echo "checked='checked'"; ?> />
                                                        <label for="parent<?php echo $parent->id ?>" class="label-success"></label>
                                                    </div>

                                                </td>
                                                <?php } ?>
                                            </tr>    

                                            <?php
                                        }
                                    }
                                              
                                    ?>

                                </tbody>
                            </table>
                        </div>
                        <div class="tab-pane table-responsive" id="tab_staff">
                            <div class="download_label"><?php echo $this->lang->line('users'); ?></div>
                            <table class="table table-striped table-bordered table-hover example" cellspacing="0" width="100%">
                                <thead>
                                    <tr>

                                        <th><?php echo $this->lang->line('staff_id'); ?></th>
                                        <th><?php echo $this->lang->line('name'); ?></th>
                                        <th><?php echo $this->lang->line('email'); ?></th>
                                        <th><?php echo $this->lang->line('role'); ?></th>
                                        <th><?php echo $this->lang->line('designation'); ?></th>
                                        <th><?php echo $this->lang->line('department'); ?></th>
                                        <th><?php echo $this->lang->line('phone'); ?></th>
                                        <?php if ($can_manage_users) { ?>
                                        <th class="text-right"><?php echo $this->lang->line('action'); ?>
                                        </th>
                                        <?php } ?>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php
                                    if (!empty($staffList)) {
                                        $count = 1;
                                        foreach ($staffList as $staff) {
                                            ?>
                                            <tr>
                                                <td class="mailbox-name"> <?php echo $staff['employee_id'] ?></td>
                                                <td class="mailbox-name"> <a href="<?php echo base_url(); ?>admin/staff/profile/<?php echo $staff['id']; ?>"><?php echo $staff['name'] ?></a></td>
                                                <td class="mailbox-name"> <?php echo $staff['email'] ?></td>
                                                <td class="mailbox-name"> <?php echo $staff['role'] ?></td>
                                                <td class="mailbox-name"> <?php echo $staff['designation'] ?></td>
                                                <td class="mailbox-name"> <?php echo $staff['department'] ?></td>
                                                <td class="mailbox-name"> <?php echo html_escape($staff['contact_no']) ?><?php echo $render_whatsapp_button($staff['contact_no'], $staff['name'], 'staff'); ?></td>
                                                <?php if ($can_manage_users) { ?>
                                                <td class="relative">
                                                    <div class="material-switch pull-right">
                                                        <input id="staff<?php echo $staff['id'] ?>" name="someSwitchOption001" type="checkbox" class="chk" data-rowid="<?php echo $staff['id'] ?>" data-role="staff" value="checked" <?php if ($staff['is_active'] == 1) echo "checked='checked'"; ?> />
                                                        <label for="staff<?php echo $staff['id'] ?>" class="label-success"></label>
                                                    </div>
                                                </td>
                                                <?php } ?>
                                            </tr>
                                            <?php
                                        }
                                        $count++;
                                    }
                                    ?>
                                </tbody>
                            </table>
                        </div>
                        <!-- /.tab-pane -->
                    </div>
                    <!-- /.tab-content -->
                </div>
            </div> 
        </div> 
    </section>
</div>

<script type="text/javascript">
    $(document).ready(function () {

        $(document).on('click', '.chk', function () {
            var checked = $(this).is(':checked');
            var rowid = $(this).data('rowid');
            var role = $(this).data('role');
            if (checked) {
                if (!confirm('<?php echo $this->lang->line("are_you_sure_you_active_account");?>')) {
                    $(this).removeAttr('checked');
                } else {
                    var status = "yes";
                    changeStatus(rowid, status, role);

                }
            } else if (!confirm('<?php echo $this->lang->line("are_you_sure_you_deactive_account"); ?>')) {
                $(this).prop("checked", true);
            } else {
                var status = "no";
                changeStatus(rowid, status, role);

            }
        });
    });

    function changeStatus(rowid, status, role) {


        var base_url = '<?php echo base_url() ?>';

        $.ajax({
            type: "POST",
            url: base_url + "admin/users/changeStatus",
            data: {'id': rowid, 'status': status, 'role': role},
            dataType: "json",
            success: function (data) {
                successMsg(data.msg);
            }
        });
    }


</script>
