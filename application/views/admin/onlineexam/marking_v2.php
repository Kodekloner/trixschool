<?php
defined('BASEPATH') OR exit('No direct script access allowed');
$attempt = $detail['attempt'];
$render_answer = function ($value) {
    if (is_array($value) || is_object($value)) {
        return json_encode($value, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }
    return (string) $value;
};
?>
<?php $this->load->view('admin/onlineexam/_assessment_styles'); ?>
<div class="content-wrapper onlineexam-ui onlineexam-theory-review-page">
    <section class="content-header">
        <h1><i class="fa fa-pencil-square-o"></i> Theory Answer Review <small><?php echo html_escape($exam->exam); ?></small></h1>
    </section>
    <section class="content">
        <?php echo $this->session->flashdata('msg'); ?>
        <div class="box box-primary">
            <div class="box-header with-border assessment-box-header">
                <h3 class="box-title"><?php echo html_escape(isset($student['student_name']) ? $student['student_name'] : 'Candidate'); ?></h3>
                <div class="box-tools pull-right"><a class="btn btn-default btn-sm" href="<?php echo base_url('admin/onlineexam/operations/' . $exam->id); ?>"><i class="fa fa-arrow-left"></i> Operations</a></div>
            </div>
            <div class="box-body">
                <p>
                    <?php echo html_escape(isset($student['admission_no']) ? $student['admission_no'] : ''); ?> &middot;
                    <?php echo html_escape(isset($student['section']) ? $student['section'] : ''); ?> &middot;
                    Attempt #<?php echo (int) $attempt['attempt_no']; ?> &middot; Revision <?php echo (int) $attempt['revision']; ?>
                    <span class="label label-<?php echo $attempt['status'] === 'completed' ? 'success' : 'warning'; ?>"><?php echo html_escape(ucwords(str_replace('_', ' ', $attempt['status']))); ?></span>
                </p>
                <p class="help-block">Draft reviews remain incomplete. Finalized marks are included in calculation. Finalizing the attempt automatically posts to its configured result component but does not publish the report card.</p>
            </div>
        </div>

        <?php if ($exam->result_adapter === 'british_outcome' && $british_profile_mode === 'teacher_selection') { ?>
            <div class="box box-info">
                <div class="box-header with-border"><h3 class="box-title">Final British outcome</h3></div>
                <div class="box-body">
                    <p>This assessment profile requires a teacher-selected qualitative result. Existing British additional comments are preserved.</p>
                    <form class="form-inline" method="post" action="<?php echo base_url('admin/onlineexam/operationBritishOutcome/' . $exam->id); ?>">
                        <?php echo $this->customlib->getCSRF(); ?>
                        <input type="hidden" name="onlineexam_workflow_token" value="<?php echo html_escape($workflow_csrf); ?>">
                        <input type="hidden" name="attempt_id" value="<?php echo (int) $attempt['id']; ?>">
                        <select class="form-control" name="outcome_value" required>
                            <option value="">Select outcome</option>
                            <?php foreach (array('Emerging', 'Expected', 'Exceeding') as $outcome) { ?><option value="<?php echo $outcome; ?>" <?php echo $attempt['outcome_value'] === $outcome ? 'selected' : ''; ?>><?php echo $outcome; ?></option><?php } ?>
                        </select>
                        <button class="btn btn-info" type="submit">Finalize outcome</button>
                    </form>
                </div>
            </div>
        <?php } ?>

        <?php foreach ($detail['answers'] as $index => $answer) {
            $history = isset($answer['history']) ? $answer['history'] : array();
            $latest = empty($history) ? array() : $history[0]; ?>
            <div class="box box-default">
                <div class="box-header with-border assessment-box-header">
                    <h3 class="box-title"><?php echo html_escape($answer['paper_title']); ?> — Theory question <?php echo $index + 1; ?> <small><?php echo html_escape($answer['question_type']); ?></small></h3>
                    <span class="label label-<?php echo $answer['marking_status'] === 'finalized' ? 'success' : 'warning'; ?>"><?php echo html_escape($answer['marking_status']); ?></span>
                </div>
                <div class="box-body">
                    <div class="well well-sm"><?php echo nl2br(html_escape(strip_tags($answer['question_text']))); ?></div>
                    <h5><strong>Candidate response</strong></h5>
                    <pre style="white-space:pre-wrap"><?php echo html_escape($render_answer($answer['response'])); ?></pre>
                    <?php if (!empty($answer['attachment_path'])) { ?>
                        <p><a class="btn btn-default btn-xs" href="<?php echo base_url('admin/onlineexam/answerattachment/' . (int) $exam->id . '/' . (int) $answer['id']); ?>"><i class="fa fa-paperclip"></i> <?php echo html_escape($answer['attachment_name']); ?></a></p>
                    <?php } ?>
                    <?php if (!empty($answer['marking_scheme'])) { ?><div class="callout callout-info"><strong>Marking scheme</strong><br><?php echo nl2br(html_escape($answer['marking_scheme'])); ?></div><?php } ?>
                    <form method="post" action="<?php echo base_url('admin/onlineexam/operationManualMark/' . $exam->id); ?>">
                        <?php echo $this->customlib->getCSRF(); ?>
                        <input type="hidden" name="onlineexam_workflow_token" value="<?php echo html_escape($workflow_csrf); ?>">
                        <input type="hidden" name="attempt_id" value="<?php echo (int) $attempt['id']; ?>">
                        <input type="hidden" name="attempt_answer_id" value="<?php echo (int) $answer['id']; ?>">
                        <div class="row">
                            <div class="form-group col-md-2"><label>Mark / <?php echo number_format((float) $answer['question_max'], 2); ?></label><input class="form-control" type="number" step="0.01" min="0" max="<?php echo html_escape($answer['question_max']); ?>" required name="marks" value="<?php echo html_escape(isset($answer['manual_mark']) ? $answer['manual_mark'] : ''); ?>"></div>
                            <div class="form-group col-md-3"><label>Status</label><select class="form-control" name="mark_status"><option value="draft" <?php echo isset($latest['status']) && $latest['status'] === 'draft' ? 'selected' : ''; ?>>Draft</option><option value="finalized" <?php echo isset($latest['status']) && $latest['status'] === 'finalized' ? 'selected' : ''; ?>>Finalized</option></select></div>
                            <div class="form-group col-md-7"><label>Marker's remark</label><input class="form-control" maxlength="5000" name="remark" value="<?php echo html_escape(isset($latest['remark']) ? $latest['remark'] : ''); ?>"></div>
                        </div>
                        <div class="form-group"><label>Rubric breakdown (optional JSON)</label><textarea class="form-control" rows="2" name="rubric_json" placeholder='{"accuracy":5,"method":3}'><?php echo html_escape(isset($latest['rubric_json']) ? $latest['rubric_json'] : ''); ?></textarea></div>
                        <button class="btn btn-primary" type="submit">Save reviewed mark</button>
                        <?php if (!empty($history)) { ?><span class="text-muted">Version <?php echo (int) $latest['marking_version']; ?>; history retained</span><?php } ?>
                    </form>
                </div>
            </div>
        <?php } ?>

        <?php if (empty($detail['answers'])) { ?>
            <div class="alert alert-info">There are no submitted Theory answers to review. Objective-only attempts are finalized automatically on submission.</div>
        <?php } ?>

        <div class="box box-success">
            <div class="box-body">
                <form method="post" action="<?php echo base_url('admin/onlineexam/operationFinalize/' . $exam->id); ?>" onsubmit="return confirm('Finalize all reviewed marks and automatically synchronize this result?');">
                    <?php echo $this->customlib->getCSRF(); ?>
                    <input type="hidden" name="onlineexam_workflow_token" value="<?php echo html_escape($workflow_csrf); ?>">
                    <input type="hidden" name="attempt_id" value="<?php echo (int) $attempt['id']; ?>">
                    <button class="btn btn-success" type="submit"><i class="fa fa-check"></i> Finalize review and synchronize result</button>
                    <span class="help-block">If an unrelated score already exists in the result component, synchronization records a conflict and does not overwrite it.</span>
                </form>
            </div>
        </div>
    </section>
</div>
