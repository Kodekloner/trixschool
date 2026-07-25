<style type="text/css">
.inpwidth40{width: 50px;height: 20px;}
</style>
<?php

if (!empty($workflow_exam) && !empty($questionList)) {
    $paper_names = array();
    $section_names = array();
    foreach ((array) $workflow_papers as $paper) {
        $paper_names[(int) $paper['id']] = $paper['title'];
        foreach ((array) $paper['sections'] as $paper_section) {
            $section_names[(int) $paper_section['id']] = $paper_section['title'];
        }
    }

    foreach ($questionList as $question_value) {
        $assigned = (int) $question_value->onlineexam_question_id > 0;
        $assignment_text = '';
        if ($assigned) {
            $assignment_text = isset($paper_names[(int) $question_value->onlineexam_paper_id]) ? $paper_names[(int) $question_value->onlineexam_paper_id] : 'Unknown paper';
            if (!empty($question_value->onlineexam_paper_section_id) && isset($section_names[(int) $question_value->onlineexam_paper_section_id])) {
                $assignment_text .= ' / ' . $section_names[(int) $question_value->onlineexam_paper_section_id];
            }
        }
        ?>
        <div class="panel panel-<?php echo $assigned ? 'success' : 'default'; ?> workflow-question-row" data-question-id="<?php echo (int) $question_value->id; ?>">
            <div class="panel-heading">
                <strong>Question <?php echo (int) $question_value->id; ?></strong>
                <span class="label label-default"><?php echo html_escape(isset($question_type[$question_value->question_type]) ? $question_type[$question_value->question_type] : $question_value->question_type); ?></span>
                <?php if ($assigned) { ?><span class="label label-success pull-right">Assigned: <?php echo html_escape($assignment_text); ?></span><?php } ?>
            </div>
            <div class="panel-body">
                <div class="workflow-question-text"><?php echo readmorelink($question_value->question, site_url('admin/question/read/' . $question_value->id)); ?></div>
                <div class="row pt10">
                    <div class="col-md-2"><div class="form-group"><label>Marks</label><input type="number" step="0.01" min="0.01" class="form-control question-marks" value="<?php echo html_escape($question_value->onlineexam_question_marks); ?>"></div></div>
                    <div class="col-md-2"><div class="form-group"><label>Negative mark</label><input type="number" step="0.01" min="0" class="form-control question-neg-marks" value="<?php echo html_escape($question_value->onlineexam_question_neg_marks); ?>" <?php echo empty($workflow_exam->is_neg_marking) ? 'disabled' : ''; ?>></div></div>
                    <div class="col-md-2"><div class="form-group"><label>Order</label><input type="number" min="0" class="form-control question-order" value="<?php echo (int) $question_value->onlineexam_question_display_order; ?>"></div></div>
                    <div class="col-md-6"><div class="form-group"><label>Marking scheme / rubric note</label><input type="text" class="form-control question-scheme" value="<?php echo html_escape($question_value->onlineexam_question_marking_scheme); ?>"></div></div>
                </div>
                <div class="clearfix">
                    <label class="checkbox-inline"><input type="checkbox" class="question-compulsory" value="1" <?php echo $question_value->onlineexam_question_is_compulsory ? 'checked' : ''; ?>> Compulsory question</label>
                    <?php if ($workflow_editable && $this->rbac->hasPrivilege('add_questions_in_exam', 'can_edit')) { ?>
                        <button type="button" class="btn btn-primary btn-sm pull-right workflow-question-save" data-question-id="<?php echo (int) $question_value->id; ?>"><i class="fa fa-save"></i> <?php echo $assigned ? 'Update assignment' : 'Assign question'; ?></button>
                        <?php if ($assigned) { ?>
                            <button type="button" class="btn btn-danger btn-sm pull-right workflow-question-remove" style="margin-right:8px" data-assignment-id="<?php echo (int) $question_value->onlineexam_question_id; ?>"><i class="fa fa-remove"></i> Remove</button>
                        <?php } ?>
                    <?php } ?>
                </div>
            </div>
        </div>
        <?php
    }
}

if (empty($workflow_exam) && !empty($questionList)) {

    foreach ($questionList as $question_key => $question_value) {
        $checkbox_status = "";
        if ($question_value->onlineexam_question_id != 0) {
            $checkbox_status = "checked";
        }

        ?>
                 <div class="">
                 <div class="row">
                    <div class="col-xs-12 col-md-12 section-box">
                        <?php if ($this->rbac->hasPrivilege('add_questions_in_exam', 'can_edit')) {?>
                         <div class="checkbox" style="margin-left: 20px"><input type="checkbox" class="question_chk" value="<?php echo $question_value->id; ?>" <?php echo $checkbox_status; ?>></div>
                     <?php }?>
                       <div class="rltpaddleft">
                      <span class="font-weight-bold"> <?php echo $this->lang->line('q_id')?>: <?php echo $question_value->id;?></span><br/>
                        <?php echo readmorelink($question_value->question, site_url('admin/question/read/' . $question_value->id)); ?>
                       <div class="pt5">
        <div class="row">
        <div class="col-lg-2 col-md-6 col-sm-12">
            <div>
               <label for="email"><?php echo $this->lang->line('marks')?>:</label>
               <input type="text" name="question_marks" value="<?php echo $question_value->onlineexam_question_marks; ?>" placeholder="question marks" class="inpwidth40">
            </div>

        </div>
         <div class="col-lg-2 col-md-6 col-sm-12">
            <div>
               <label for="email"><?php echo $this->lang->line('negative_marks')?>:</label>
               <input type="text" name="question_neg_marks" value="<?php echo $question_value->onlineexam_question_neg_marks; ?>" placeholder="question marks" class="inpwidth40">
            </div>

        </div>
        <div class="col-lg-2 col-md-6 col-sm-12">
        <label for="email"><?php echo $this->lang->line('question_type')?>:</label>
        <?php echo ($question_value->question_type != "")?$question_type[$question_value->question_type]:""; ?>
       </div>
        <div class="col-lg-2 col-md-6 col-sm-12">
        <label for="email"><?php echo $this->lang->line('level')?>:</label>
        <?php echo ($question_value->level !="")? $question_level[$question_value->level]:""; ?>
        </div>

        <div class="col-lg-4 col-md-6 col-sm-12">
            <label for="email"><?php echo $this->lang->line('subject') ?>:</label>
                <?php echo $question_value->subject_name; ?>
        </div>
    </div><!--./row-->
</div>

                     </div>

                    </div>
                </div>
                <div class="hrexam"></div>
            </div>
    <?php
}

}

?>
</form>
