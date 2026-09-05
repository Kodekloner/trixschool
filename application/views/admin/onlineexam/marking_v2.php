<?php
defined('BASEPATH') OR exit('No direct script access allowed');
$attempt = $detail['attempt'];
$can_edit_marking = $this->rbac->hasPrivilege('online_examination', 'can_edit');
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
                <?php if (!$can_edit_marking) { ?><div class="alert alert-info">You have read-only access to this marking review.</div><?php } ?>
            </div>
        </div>

        <?php if ($exam->result_adapter === 'british_outcome' && $british_profile_mode === 'teacher_selection') { ?>
            <div class="box box-info">
                <div class="box-header with-border"><h3 class="box-title">Final British outcome</h3></div>
                <div class="box-body">
                    <p>Historical British outcome assessments are read-only. Existing British additional comments are preserved.</p>
                    <p><strong>Recorded outcome:</strong> <?php echo !empty($attempt['outcome_value']) ? html_escape($attempt['outcome_value']) : 'Not recorded'; ?></p>
                </div>
            </div>
        <?php } ?>

        <?php foreach ($detail['answers'] as $index => $answer) {
            $history = isset($answer['history']) ? $answer['history'] : array();
            $latest = empty($history) ? array() : $history[0];
            $answer_id = (int) $answer['id']; ?>
            <div class="box box-default">
                <div class="box-header with-border assessment-box-header">
                    <h3 class="box-title"><?php echo html_escape($answer['paper_title']); ?> — Theory question <?php echo $index + 1; ?> <small><?php echo html_escape($answer['question_type']); ?></small></h3>
                    <span class="label label-<?php echo $answer['marking_status'] === 'finalized' ? 'success' : 'warning'; ?>"><?php echo html_escape($answer['marking_status']); ?></span>
                </div>
                <div class="box-body">
                    <div class="well well-sm"><?php echo nl2br(html_escape(strip_tags($answer['question_text']))); ?></div>
                    <h5><strong>Candidate response</strong></h5>
                    <pre class="theory-response"><?php echo html_escape($render_answer($answer['response'])); ?></pre>
                    <?php if (!empty($answer['attachment_path'])) { ?>
                        <p class="theory-attachment"><a class="btn btn-default btn-xs" href="<?php echo base_url('admin/onlineexam/answerattachment/' . (int) $exam->id . '/' . $answer_id); ?>"><i class="fa fa-paperclip"></i> <?php echo html_escape($answer['attachment_name']); ?></a></p>
                    <?php } ?>
                    <?php if (!empty($answer['marking_scheme'])) { ?><div class="callout callout-info"><strong>Marking scheme</strong><br><?php echo nl2br(html_escape($answer['marking_scheme'])); ?></div><?php } ?>
                    <?php if ($can_edit_marking) { ?>
                    <form method="post" action="<?php echo base_url('admin/onlineexam/operationManualMark/' . $exam->id); ?>">
                        <?php echo $this->customlib->getCSRF(); ?>
                        <input type="hidden" name="onlineexam_workflow_token" value="<?php echo html_escape($workflow_csrf); ?>">
                        <input type="hidden" name="attempt_id" value="<?php echo (int) $attempt['id']; ?>">
                        <input type="hidden" name="attempt_answer_id" value="<?php echo $answer_id; ?>">
                        <div class="row">
                            <div class="form-group col-md-2"><label for="answer-mark-<?php echo $answer_id; ?>">Mark / <?php echo number_format((float) $answer['question_max'], 2); ?></label><input id="answer-mark-<?php echo $answer_id; ?>" class="form-control" type="number" step="0.01" min="0" max="<?php echo html_escape($answer['question_max']); ?>" required name="marks" value="<?php echo html_escape(isset($answer['manual_mark']) ? $answer['manual_mark'] : ''); ?>"></div>
                            <div class="form-group col-md-3"><label for="answer-status-<?php echo $answer_id; ?>">Status</label><select id="answer-status-<?php echo $answer_id; ?>" class="form-control" name="mark_status"><option value="draft" <?php echo isset($latest['status']) && $latest['status'] === 'draft' ? 'selected' : ''; ?>>Draft</option><option value="finalized" <?php echo isset($latest['status']) && $latest['status'] === 'finalized' ? 'selected' : ''; ?>>Finalized</option></select></div>
                            <div class="form-group col-md-7"><label for="answer-remark-<?php echo $answer_id; ?>">Marker's remark</label><input id="answer-remark-<?php echo $answer_id; ?>" class="form-control" maxlength="5000" name="remark" value="<?php echo html_escape(isset($latest['remark']) ? $latest['remark'] : ''); ?>"></div>
                        </div>
                        <div class="form-group"><label for="answer-rubric-<?php echo $answer_id; ?>">Rubric breakdown (optional JSON)</label><textarea id="answer-rubric-<?php echo $answer_id; ?>" class="form-control" rows="2" name="rubric_json" placeholder='{"accuracy":5,"method":3}'><?php echo html_escape(isset($latest['rubric_json']) ? $latest['rubric_json'] : ''); ?></textarea></div>
                        <button class="btn btn-primary" type="submit">Save reviewed mark</button>
                        <?php if (!empty($history)) { ?><span class="text-muted marking-history-note">Version <?php echo (int) $latest['marking_version']; ?>; history retained</span><?php } ?>
                    </form>
                    <?php } else { ?>
                        <dl class="dl-horizontal">
                            <dt>Reviewed mark</dt><dd><?php echo isset($answer['manual_mark']) && $answer['manual_mark'] !== null ? number_format((float) $answer['manual_mark'], 2) : 'Not marked'; ?> / <?php echo number_format((float) $answer['question_max'], 2); ?></dd>
                            <dt>Review status</dt><dd><?php echo html_escape(ucfirst(isset($answer['marking_status']) ? $answer['marking_status'] : 'pending')); ?></dd>
                            <?php if (!empty($latest['remark'])) { ?><dt>Marker's remark</dt><dd><?php echo nl2br(html_escape($latest['remark'])); ?></dd><?php } ?>
                            <?php if (!empty($latest['rubric_json'])) { ?><dt>Rubric breakdown</dt><dd><code><?php echo html_escape($latest['rubric_json']); ?></code></dd><?php } ?>
                            <?php if (!empty($history)) { ?><dt>History</dt><dd>Version <?php echo (int) $latest['marking_version']; ?>; history retained</dd><?php } ?>
                        </dl>
                    <?php } ?>
                </div>
            </div>
        <?php } ?>

        <?php if (empty($detail['answers'])) { ?>
            <div class="alert alert-info">There are no submitted Theory answers to review. Objective-only attempts are finalized automatically on submission.</div>
        <?php } ?>

        <?php if ($can_edit_marking) { ?>
        <div class="box box-success marking-finalize">
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
        <?php } ?>
    </section>
</div>
