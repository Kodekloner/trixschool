<?php
if (!empty($workflow_exam) && !empty($questionList)) {
    $paper_names = array();
    $section_names = array();
    $can_edit_question_assignments = !empty($workflow_editable) && $this->rbac->hasPrivilege('add_questions_in_exam', 'can_edit');
    foreach ((array) $workflow_papers as $paper) {
        $paper_names[(int) $paper['id']] = $paper['title'];
        foreach ((array) $paper['sections'] as $paper_section) {
            $section_names[(int) $paper_section['id']] = $paper_section['title'];
        }
    }
    ?>
    <div class="table-responsive onlineexam-scroll" role="region" aria-label="Question assignment table" tabindex="0">
        <table class="table table-striped table-bordered table-condensed question-assignment-table">
            <caption class="sr-only">Questions available for this assessment, their paper assignment, marks, rules, and actions.</caption>
            <thead>
                <tr>
                    <th scope="col" class="question-text-cell">Question</th>
                    <th scope="col" class="question-type-cell">Type</th>
                    <th scope="col" class="question-assigned-cell">Assigned paper / section</th>
                    <th scope="col" class="question-number-cell">Marks</th>
                    <th scope="col" class="question-number-cell">Negative mark</th>
                    <th scope="col" class="question-number-cell">Order</th>
                    <th scope="col" class="question-scheme-cell">Marking scheme</th>
                    <th scope="col" class="question-required-cell">Required</th>
                    <th scope="col" class="question-actions-cell">Actions</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($questionList as $question_value) {
                $assigned = (int) $question_value->onlineexam_question_id > 0;
                $assignment_text = '';
                if ($assigned) {
                    $assignment_text = isset($paper_names[(int) $question_value->onlineexam_paper_id])
                        ? $paper_names[(int) $question_value->onlineexam_paper_id]
                        : 'Unknown paper';
                    if (!empty($question_value->onlineexam_paper_section_id)
                        && isset($section_names[(int) $question_value->onlineexam_paper_section_id])) {
                        $assignment_text .= ' / ' . $section_names[(int) $question_value->onlineexam_paper_section_id];
                    }
                }
                ?>
                <tr class="workflow-question-row <?php echo $assigned ? 'success' : ''; ?>" data-question-id="<?php echo (int) $question_value->id; ?>">
                    <td class="question-text-cell">
                        <strong>Question <?php echo (int) $question_value->id; ?></strong>
                        <div class="workflow-question-text"><?php echo readmorelink($question_value->question, site_url('admin/question/read/' . $question_value->id)); ?></div>
                    </td>
                    <td class="question-type-cell"><?php echo html_escape(isset($question_type[$question_value->question_type]) ? $question_type[$question_value->question_type] : $question_value->question_type); ?></td>
                    <td class="question-assigned-cell">
                        <?php if ($assigned) { ?>
                            <span class="label label-success"><?php echo html_escape($assignment_text); ?></span>
                        <?php } else { ?>
                            <span class="text-muted">Not assigned</span>
                        <?php } ?>
                    </td>
                    <td class="question-number-cell"><input type="number" step="0.01" min="0.01" class="form-control input-sm question-marks" aria-label="Question marks" value="<?php echo html_escape($question_value->onlineexam_question_marks); ?>" <?php echo $can_edit_question_assignments ? '' : 'disabled'; ?>></td>
                    <td class="question-number-cell"><input type="number" step="0.01" min="0" class="form-control input-sm question-neg-marks" aria-label="Negative mark" value="<?php echo html_escape($question_value->onlineexam_question_neg_marks); ?>" <?php echo empty($workflow_exam->is_neg_marking) || !$can_edit_question_assignments ? 'disabled' : ''; ?>></td>
                    <td class="question-number-cell"><input type="number" min="0" class="form-control input-sm question-order" aria-label="Display order" value="<?php echo (int) $question_value->onlineexam_question_display_order; ?>" <?php echo $can_edit_question_assignments ? '' : 'disabled'; ?>></td>
                    <td class="question-scheme-cell"><input type="text" class="form-control input-sm question-scheme" aria-label="Marking scheme" value="<?php echo html_escape($question_value->onlineexam_question_marking_scheme); ?>" <?php echo $can_edit_question_assignments ? '' : 'disabled'; ?>></td>
                    <td class="question-required-cell"><label class="checkbox-inline"><input type="checkbox" class="question-compulsory" value="1" aria-label="Make question <?php echo (int) $question_value->id; ?> compulsory" <?php echo $question_value->onlineexam_question_is_compulsory ? 'checked' : ''; ?> <?php echo $can_edit_question_assignments ? '' : 'disabled'; ?>> Compulsory</label></td>
                    <td class="question-actions-cell">
                        <?php if ($can_edit_question_assignments) { ?>
                            <button type="button" class="btn btn-primary btn-xs workflow-question-save" data-question-id="<?php echo (int) $question_value->id; ?>"><i class="fa fa-save"></i> <?php echo $assigned ? 'Update' : 'Assign'; ?></button>
                            <?php if ($assigned) { ?><button type="button" class="btn btn-danger btn-xs workflow-question-remove" data-assignment-id="<?php echo (int) $question_value->onlineexam_question_id; ?>"><i class="fa fa-remove"></i> Remove</button><?php } ?>
                        <?php } else { ?>—<?php } ?>
                    </td>
                </tr>
            <?php } ?>
            </tbody>
        </table>
    </div>
    <?php
}
?>
