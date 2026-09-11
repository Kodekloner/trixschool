<?php
$details = $cell['details'];
$can_recover = $can_edit && $cell['recoverable'] && !$cell['excluded'] && ($cell['assigned'] || $can_assign);
?>
<h4><?php echo html_escape($student['student_name']); ?></h4>
<p><?php echo html_escape($exam['exam'] . ' — ' . $paper['title']); ?></p>
<p><span class="label label-<?php echo html_escape($cell['style']); ?>"><?php echo html_escape($cell['label']); ?></span></p>
<?php if ($exam['result_adapter'] === 'british_outcome') { ?>
    <p><strong>British outcome:</strong> <?php echo !empty($details['outcome_value']) ? html_escape($details['outcome_value']) : 'Not selected yet'; ?></p>
<?php } ?>
<p><?php echo (int) $paper['duration_minutes']; ?> minutes · Maximum <?php echo number_format((float) $paper['raw_max_score'], 2); ?></p>
<p><strong>Opens:</strong> <?php echo html_escape($details['effective_starts_at']); ?><br><strong>Closes:</strong> <?php echo html_escape($details['effective_ends_at']); ?></p>
<?php if ($cell['key'] === 'incomplete') { ?><p><?php echo (int) $details['completed_required_count']; ?> of <?php echo (int) $details['required_answer_count']; ?> required answers completed before time ended.</p><?php } ?>
<?php if ($cell['key'] === 'in_progress') { ?><p>The student is still taking this paper. It cannot be rescheduled while their time is running.</p><?php } ?>
<div class="review-actions">
    <?php if ($cell['attempt_id']) { ?><a class="btn btn-default btn-sm" href="<?php echo site_url('admin/onlineexam/attemptmarking/' . (int) $exam['id'] . '/' . (int) $cell['attempt_id']); ?>"><?php echo $cell['key'] === 'marking' && $can_edit ? 'Mark answers' : 'View answers'; ?></a><?php } ?>
    <?php if ($this->rbac->hasPrivilege('online_assign_view_student', 'can_view')) { ?><a class="btn btn-default btn-sm" href="<?php echo site_url('admin/onlineexam/assign/' . (int) $exam['id']); ?>">Manage assigned students</a><?php } ?>
</div>
<?php if (in_array($cell['posting_status'], array('conflict', 'failed', 'error', 'pending'), true)) { ?>
    <div class="alert alert-warning">This student's result needs attention. Check the existing result-entry screen before retrying; unrelated manual scores are not overwritten.</div>
    <?php if ($can_edit) { ?><form method="post" action="<?php echo site_url('admin/onlineexam/operationRetrySync/' . (int) $exam['id']); ?>">
        <?php echo $this->customlib->getCSRF(); ?><input type="hidden" name="onlineexam_workflow_token" value="<?php echo html_escape($workflow_csrf); ?>">
        <input type="hidden" name="attempt_id" value="<?php echo (int) $cell['attempt_id']; ?>"><button class="btn btn-warning btn-sm" type="submit">Retry result update</button>
    </form><?php } ?>
<?php } ?>
<?php if ($can_edit && !empty($posting_conflicts)) { foreach ($posting_conflicts as $posting_conflict) {
    if (!in_array($posting_conflict['adapter'], array('standard_component', 'holiday_assessment'), true)) { continue; } ?>
    <form method="post" action="<?php echo site_url('admin/onlineexam/operationAuthorizeSyncReplacement/' . (int) $exam['id']); ?>" class="well well-sm">
        <?php echo $this->customlib->getCSRF(); ?><input type="hidden" name="onlineexam_workflow_token" value="<?php echo html_escape($workflow_csrf); ?>">
        <input type="hidden" name="sync_id" value="<?php echo (int) $posting_conflict['id']; ?>">
        <p><?php echo html_escape($posting_conflict['conflict_reason'] ?: 'An existing result was found and was not overwritten.'); ?></p>
        <div class="form-group"><label>Reason for using the online score</label><textarea class="form-control" name="override_reason" maxlength="5000" rows="2" required></textarea></div>
        <button class="btn btn-warning btn-sm" type="submit" onclick="return confirm('Replace the reviewed existing result with this completed online assessment score?')">Use online score instead</button>
    </form>
<?php }} ?>
<?php if ($cell['excluded']) { ?><p>This student is excluded. Assign the student before rescheduling this paper.</p><?php } ?>
<?php if ($can_recover) { ?>
    <hr>
    <form method="post" class="review-action-form" data-duration="<?php echo (int) $paper['duration_minutes']; ?>" action="<?php echo site_url('admin/onlineexam/reviewaction/' . (int) $exam['id']); ?>">
        <?php echo $this->customlib->getCSRF(); ?>
        <input type="hidden" name="onlineexam_workflow_token" value="<?php echo html_escape($workflow_csrf); ?>">
        <input type="hidden" name="student_session_id" value="<?php echo (int) $student['id']; ?>">
        <input type="hidden" name="paper_id" value="<?php echo (int) $paper['id']; ?>">
        <div class="form-group"><label for="review-action">Action</label><select class="form-control" id="review-action" name="review_action">
            <option value="reschedule"><?php echo $cell['assigned'] ? 'Reschedule this paper' : 'Assign student and schedule paper'; ?></option>
            <?php if ($can_record_manual) { ?><option value="manual_score">Record score from a supervised paper exam</option><?php } ?>
        </select></div>
        <?php if (!$can_record_manual) { ?><p class="help-block">This Kindergarten paper covers separate concepts, so use rescheduling or record each concept in Kindergarten results.</p><?php } ?>
        <div data-review-fields="reschedule"><div class="row">
            <div class="form-group col-sm-6"><label for="review-starts">New start</label><input class="form-control" type="datetime-local" id="review-starts" name="starts_at" required></div>
            <div class="form-group col-sm-6"><label for="review-ends">New end</label><input class="form-control" type="datetime-local" id="review-ends" name="ends_at" required></div>
        </div></div>
        <div data-review-fields="manual_score" hidden><div class="form-group"><label for="review-score">Score / <?php echo number_format((float) $paper['raw_max_score'], 2); ?></label><input class="form-control" id="review-score" name="raw_marks" type="number" min="0" max="<?php echo html_escape($paper['raw_max_score']); ?>" step="0.01" required disabled></div></div>
        <div class="form-group"><label for="review-reason">Reason</label><textarea class="form-control" id="review-reason" name="reason" rows="2" maxlength="5000" required></textarea></div>
        <p class="text-danger" data-review-error role="alert"></p>
        <button class="btn btn-primary" type="submit" data-review-save>Save schedule</button>
    </form>
<?php } ?>
