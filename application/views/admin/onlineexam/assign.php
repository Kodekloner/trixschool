<?php
$can_edit_roster = $this->rbac->hasPrivilege('online_assign_view_student', 'can_edit');
$roster_column_count = 5 + (!empty($sch_setting->father_name) ? 1 : 0) + (!empty($sch_setting->category) ? 1 : 0);
$this->load->view('admin/onlineexam/_assessment_styles');
?>
<div class="content-wrapper onlineexam-ui onlineexam-roster-page">
    <!-- Main content -->
    <section class="content-header"><h1>Candidate roster <small><?php echo html_escape($onlineexam->exam); ?></small></h1></section>
    <section class="content">  
        <div class="row">
            <div class="col-md-12">
                <div class="box box-primary">
                    <div class="box-header with-border">
                        <h3 class="box-title"><i class="fa fa-search"></i> <?php echo $this->lang->line('select_criteria'); ?></h3>
                    </div>
                    <div class="box-body">
                        <form role="form" action="<?php echo site_url('admin/onlineexam/assign/' . $id) ?>" method="post" class="row">

                            <?php echo $this->customlib->getCSRF(); ?>
                          
                            <input type="hidden" name="onlineexam_id" value="<?php echo $onlineexam->id; ?>">
                            <?php $is_workflow = isset($onlineexam->workflow_version) && (int) $onlineexam->workflow_version === 2; ?>
                           
                                <div class="col-md-6">
                                    <div class="form-group">
                                    <label for="class_id"><?php echo $this->lang->line('class'); ?></label>  <small class="req"> *</small>
                                    <select autofocus="" id="class_id" name="class_id" class="form-control" <?php echo $is_workflow ? 'disabled' : ''; ?>>
                                        <option value=""><?php echo $this->lang->line('select'); ?></option>
                                        <?php
                                        foreach ($classlist as $class) {
                                            ?>
                                            <option value="<?php echo $class['id'] ?>" <?php
                                            if(set_value('class_id', $is_workflow ? $onlineexam->class_id : '') == $class['id']) {
                                                echo "selected=selected";
                                            }
                                            ?>><?php echo html_escape($class['class']); ?></option>
                                                    <?php
                                                }
                                                ?>
                                    </select>
                                    <?php if ($is_workflow) { ?><input type="hidden" name="class_id" value="<?php echo (int) $onlineexam->class_id; ?>"><?php } ?>
                                    <span class="text-danger"><?php echo form_error('class_id'); ?></span>
                                </div>
                                </div>

                                <div class="col-md-6">

                                    <div class="form-group">
                                        <label for="section_id"><?php echo $this->lang->line('section'); ?></label>
                                        <select  id="section_id" name="section_id" class="form-control" >
                                            <option value=""><?php echo $this->lang->line('select'); ?></option>
                                            <?php if ($is_workflow && !empty($workflow_sections)) { foreach ($workflow_sections as $workflow_section) { if (in_array((int) $workflow_section['id'], array_map('intval', $onlineexam->section_ids), true)) { ?>
                                                <option value="<?php echo (int) $workflow_section['id']; ?>" <?php echo set_select('section_id', $workflow_section['id']); ?>><?php echo html_escape($workflow_section['section']); ?></option>
                                            <?php } } } ?>
                                        </select>
                                        <span class="text-danger"><?php echo form_error('section_id'); ?></span>
                                    </div>
                                </div>
                          

                          
                            <div class="col-md-12 single-action-footer">
                                <button type="submit" name="search" value="search_filter" class="btn btn-primary btn-sm checkbox-toggle"><i class="fa fa-search"></i> <?php echo $this->lang->line('search'); ?></button>
                            </div>
                        </form>

                    </div>
                
                <form method="post" action="<?php echo site_url('admin/onlineexam/addstudent') ?>" id="assign_form">

                    <?php echo $this->customlib->getCSRF(); ?>
                    <?php if (!empty($is_workflow)) { ?><input type="hidden" name="onlineexam_workflow_token" value="<?php echo html_escape($workflow_csrf); ?>"><?php } ?>


                    <?php
                    if (isset($resultlist)) {
                        ?>
                      <div class="box-header ptbnull"></div>  
                        <div class="">
                            <div class="box-header with-border">
                                <h3 class="box-title"><i class="fa fa-users"></i> <?php echo $this->lang->line('assign')." ".$this->lang->line('online')." ".$this->lang->line('exam');?></h3>
                                <div class="box-tools pull-right">
                                </div>
                            </div>
                            <div class="box-body">
                                <input type="hidden" name="onlineexam_id" value="<?php echo $onlineexam->id; ?>">
                                <input type="hidden" name="post_class_id" value="<?php echo $class_id; ?>">
                                <input type="hidden" name="post_section_id" value="<?php echo $section_id; ?>">
                                <h4><a href="#" data-toggle="popover" class="detail_popover"><?php echo html_escape($onlineexam->exam); ?></a></h4>
                                <div class="table-responsive onlineexam-scroll" role="region" aria-label="Candidate roster" tabindex="0">
                                                <table class="table table-striped table-bordered candidate-roster-table">
                                                    <caption class="sr-only">Students eligible for this online assessment</caption>
                                                    <thead>
                                                        <tr>
                                                            <th scope="col"><input style="vertical-align: text-top;" type="checkbox" id="select_all" aria-label="Select all candidates" <?php echo $can_edit_roster ? '' : 'disabled'; ?>/> <?php echo $this->lang->line('all'); ?></th>

                                                            <th scope="col"><?php echo $this->lang->line('admission_no'); ?></th>
                                                            <th scope="col"><?php echo $this->lang->line('student_name'); ?></th>

                                                            <th scope="col"><?php echo $this->lang->line('class'); ?></th>
                                                            <?php if($sch_setting->father_name){ ?>
                                                            <th scope="col"><?php echo $this->lang->line('father_name'); ?></th><?php }   if($sch_setting->category){ ?>
                                                            <th scope="col"><?php echo $this->lang->line('category'); ?></th>
                                                        <?php } ?>
                                                            <th scope="col" class="text-right"><?php echo $this->lang->line('gender'); ?></th>
                                                      
                                                        </tr>
                                                    </thead>
                                                    <tbody>
                                                        <?php
                                                        if (empty($resultlist)) {
                                                            ?>
                                                            <tr>
                                                                <td colspan="<?php echo (int) $roster_column_count; ?>" class="text-danger text-center"><?php echo $this->lang->line('no_record_found'); ?></td>
                                                            </tr>
                                                            <?php
                                                        } else {
                                                            $count = 1;
                                                            foreach ($resultlist as $student) {
                                                                $student_name = $this->customlib->getFullName($student['firstname'], $student['middlename'], $student['lastname'], $sch_setting->middlename, $sch_setting->lastname);
                                                     
                                                                ?>
                                                                <tr>

                                                                    <td>
                                                                        <?php
                                                                        if ($student['onlineexam_student_session_id'] != 0) {
                                                                            $sel = "checked='checked'";
                                                                        } else {
                                                                            $sel = "";
                                                                        }
                                                                        ?>
                                                                        <input type="hidden" name="all_students[]" value="<?php echo $student['onlineexam_student_session_id']; ?>">

                                                                        <input class="checkbox" type="checkbox" name="students_id[]" value="<?php echo $student['student_session_id']; ?>" aria-label="Assign <?php echo html_escape($student_name); ?>" <?php echo $sel; ?> <?php echo $can_edit_roster ? '' : 'disabled'; ?>/>


                                                                    </td>

                                                                    <td><?php echo html_escape($student['admission_no']); ?></td>

                 <td><?php echo html_escape($student_name); ?></td>
                                                                    <td><?php echo html_escape($student['class']." (".$student['section'].")"); ?></td><?php if($sch_setting->father_name){ ?>
                                                                    <td><?php echo html_escape($student['father_name']); ?></td>
                                                                <?php } if($sch_setting->category){ ?>
                                                                    <td><?php echo html_escape($student['category']); ?></td>
                                                                <?php } ?>
                                                                    <td class="text-right"><?php echo html_escape($student['gender']); ?></td>

                                                                </tr>
                                                                <?php
                                                            }
                                                            $count++;
                                                        }
                                                        ?>
                                                    </tbody>
                                                </table>
                                </div>
                                <?php if($can_edit_roster){ ?>
                                    <div class="single-action-footer"><button type="submit" class="allot-fees btn btn-primary btn-sm" id="load" data-loading-text="<i class='fa fa-spinner fa-spin '></i> Please Wait.."><?php echo $this->lang->line('save'); ?></button></div>
                                <?php } ?>
                                <div class="clearfix"></div>

                            </div>
                        </div>
                        <?php
                    }
                    ?>
                   
                </form>
            </div>

        </div>

    </section>
</div>





<script type="text/javascript">
    var date_format = '<?php echo $result = strtr($this->customlib->getSchoolDateFormat(), ['d' => 'dd', 'm' => 'mm', 'Y' => 'yyyy']) ?>';
    var is_workflow = <?php echo !empty($is_workflow) ? 'true' : 'false'; ?>;
    var class_id = '<?php echo set_value('class_id', !empty($is_workflow) ? $onlineexam->class_id : 0) ?>';
    var section_id = '<?php echo set_value('section_id', 0) ?>';
    if (!is_workflow) {
        getSectionByClass(class_id, section_id);
    }
    $(document).on('change', '#class_id', function (e) {
        if (is_workflow) {
            return;
        }
        $('#section_id').html("");
        var class_id = $(this).val();
        getSectionByClass(class_id, 0);
    });


    function getSectionByClass(class_id, section_id) {

        if (class_id != "") {
            $('#section_id').html("");
            var base_url = '<?php echo base_url() ?>';
            var div_data = '<option value=""><?php echo $this->lang->line('select'); ?></option>';


            $.ajax({
                type: "GET",
                url: base_url + "sections/getByClass",
                data: {'class_id': class_id},
                dataType: "json",
                beforeSend: function () {
                    $('#section_id').addClass('dropdownloading');
                },
                success: function (data) {
                    $.each(data, function (i, obj)
                    {
                        var sel = "";
                        if (section_id == obj.section_id) {
                            sel = "selected";
                        }
                        div_data += "<option value=" + obj.section_id + " " + sel + ">" + obj.section + "</option>";
                    });
                    $('#section_id').append(div_data);
                },
                complete: function () {
                    $('#section_id').removeClass('dropdownloading');
                }
            });
        }
    }



//select all checkboxes
    $("#select_all").change(function () {  //"select all" change
        $(".checkbox").prop('checked', $(this).prop("checked")); //change all ".checkbox" checked status
    });


    $('.checkbox').change(function () {
        //uncheck "select all", if one of the listed checkbox item is unchecked
        if (false == $(this).prop("checked")) { //if this item is unchecked
            $("#select_all").prop('checked', false); //change "select all" checked status to false
        }
       
        if ($('.checkbox:checked').length == $('.checkbox').length) {
            $("#select_all").prop('checked', true);
        }
    });
    $("#assign_form").submit(function (e) {
        if (confirm("<?php echo $this->lang->line('are_you_sure');?>")) {
            var $this = $('.allot-fees');
            $.ajax({
                type: "POST",
                dataType: 'Json',
                url: $("#assign_form").attr('action'),
                data: $("#assign_form").serialize(), // serializes the form's elements.
                beforeSend: function () {
                    $this.button('loading');

                },
                success: function (data)
                {
                    if (data.status == "fail") {
                        var message = "";
                        $.each(data.error, function (index, value) {

                            message += value;
                        });
                        errorMsg(message);
                    } else {
                        successMsg(data.message);
                    }

                    $this.button('reset');
                },
                complete: function () {
                    $this.button('reset');
                }
            });

        }
        e.preventDefault();

    });


</script>
