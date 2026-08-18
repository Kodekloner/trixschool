<?php
$editClassMap = array();
$editSubjectMap = array();
if (!empty($edit_criterion)) {
    foreach ($edit_criterion['classes'] as $assignment) {
        $editClassMap[(int) $assignment['class_id']] = $assignment;
    }
    foreach ($edit_criterion['subjects'] as $subjectRule) {
        $editSubjectMap[(int) $subjectRule['subject_id']] = $subjectRule;
    }
}

$classNameMap = array();
foreach ($classes as $classRow) {
    $classNameMap[(int) $classRow['id']] = $classRow['class'];
}

$scopeRows = array();
if ($is_senior) {
    foreach ($class_sections as $scopeRow) {
        $scopeRows[] = array(
            'session_id' => 0,
            'class_id' => (int) $scopeRow['class_id'],
            'section_id' => (int) $scopeRow['section_id'],
            'class' => $scopeRow['class'],
            'section' => $scopeRow['section'],
        );
    }
} else {
    foreach ($teacher_scopes as $scopeRow) {
        $scopeRows[] = array(
            'session_id' => (int) $scopeRow['session_id'],
            'class_id' => (int) $scopeRow['class_id'],
            'section_id' => (int) $scopeRow['section_id'],
            'class' => $scopeRow['class'],
            'section' => $scopeRow['section'],
        );
    }
}

$decisionClass = function ($decision) {
    $decision = strtolower((string) $decision);
    if ($decision === 'promoted') {
        return 'success';
    }
    if ($decision === 'not_promoted') {
        return 'danger';
    }
    return 'warning';
};

$decisionLabel = function ($decision) {
    $decision = strtolower((string) $decision);
    if ($decision === 'promoted') {
        return 'Promoted';
    }
    if ($decision === 'not_promoted') {
        return 'Not promoted';
    }
    return 'Pending';
};
?>

<style>
    .promotion-page .nav-tabs { margin-bottom: 18px; }
    .promotion-page .box-title .fa { margin-right: 6px; }
    .promotion-page .scope-table,
    .promotion-page .subject-table { max-height: 340px; overflow: auto; border: 1px solid #e5e5e5; }
    .promotion-page .scope-table table,
    .promotion-page .subject-table table { margin-bottom: 0; }
    .promotion-page .criteria-chip { display: inline-block; margin: 2px 3px 2px 0; padding: 3px 7px; background: #edf4f7; border-radius: 12px; color: #24556b; }
    .promotion-page .priority-rule { white-space: nowrap; }
    .promotion-page .review-table td { vertical-align: middle; }
    .promotion-page .review-table .note-cell { min-width: 190px; max-width: 280px; white-space: normal; }
    .promotion-page .review-table .priority-cell { min-width: 170px; white-space: normal; }
    .promotion-page .form-inline.compact-form .form-control { max-width: 185px; }
    .promotion-page .empty-state { padding: 28px; text-align: center; color: #777; }
    .promotion-page .help-panel { border-left: 3px solid #3c8dbc; padding: 8px 12px; background: #f7fbfd; margin-bottom: 15px; }
    .promotion-page .target-or { display: block; color: #888; text-align: center; font-size: 11px; line-height: 18px; }
    .promotion-page .modal .help-block { margin-bottom: 0; }
    @media (max-width: 767px) {
        .promotion-page .form-inline.compact-form .form-control { display: inline-block; width: auto; max-width: 145px; }
    }
</style>

<div class="content-wrapper promotion-page" style="min-height: 946px;">
    <section class="content-header">
        <h1><i class="fa fa-level-up"></i> Promotion Criteria <small>Advisory result notes only</small></h1>
    </section>

    <section class="content">
        <?php if (is_array($promotion_message) && !empty($promotion_message['message'])) { ?>
            <div class="alert alert-<?php echo html_escape($promotion_message['type']); ?> alert-dismissible">
                <button type="button" class="close" data-dismiss="alert" aria-hidden="true">&times;</button>
                <?php echo html_escape($promotion_message['message']); ?>
            </div>
        <?php } ?>

        <div class="alert alert-info">
            <i class="fa fa-info-circle"></i>
            Promotion decisions here control the note printed on third-term results. They do not move students to another class or session.
        </div>

        <div class="nav-tabs-custom">
            <ul class="nav nav-tabs">
                <?php if ($can_view_criteria) { ?>
                    <li class="<?php echo $tab === 'criteria' ? 'active' : ''; ?>">
                        <a href="<?php echo site_url('admin/promotioncriteria?tab=criteria&criteria_session_id=' . (int) $criteria_session_id); ?>">
                            <i class="fa fa-sliders"></i> Criteria
                        </a>
                    </li>
                <?php } ?>
                <?php if ($can_view_review) { ?>
                    <li class="<?php echo $tab === 'review' ? 'active' : ''; ?>">
                        <a href="<?php echo site_url('admin/promotioncriteria?tab=review&session_id=' . (int) $review_session_id); ?>">
                            <i class="fa fa-check-square-o"></i> Promotion Review
                        </a>
                    </li>
                <?php } ?>
            </ul>

            <div class="tab-content">
                <?php if ($tab === 'criteria' && $can_view_criteria) { ?>
                    <div class="tab-pane active" id="promotion-criteria-tab">
                        <div class="row">
                            <div class="col-md-12">
                                <form class="form-inline pull-right" method="get" action="<?php echo site_url('admin/promotioncriteria'); ?>">
                                    <input type="hidden" name="tab" value="criteria">
                                    <label for="criteria-session-filter">Session</label>
                                    <select id="criteria-session-filter" name="criteria_session_id" class="form-control input-sm" onchange="this.form.submit()">
                                        <?php foreach ($sessions as $session) { ?>
                                            <option value="<?php echo (int) $session['id']; ?>" <?php echo (int) $criteria_session_id === (int) $session['id'] ? 'selected' : ''; ?>>
                                                <?php echo html_escape($session['session']); ?>
                                            </option>
                                        <?php } ?>
                                    </select>
                                </form>
                                <div class="clearfix"></div>
                            </div>
                        </div>

                        <?php if ($can_add_criteria || !empty($edit_criterion)) { ?>
                            <div class="box box-primary">
                                <div class="box-header with-border">
                                    <h3 class="box-title">
                                        <i class="fa <?php echo !empty($edit_criterion) ? 'fa-pencil' : 'fa-plus-circle'; ?>"></i>
                                        <?php echo !empty($edit_criterion) ? 'Edit Promotion Criterion' : 'Create Promotion Criterion'; ?>
                                    </h3>
                                    <?php if (!empty($edit_criterion)) { ?>
                                        <div class="box-tools pull-right">
                                            <a class="btn btn-default btn-xs" href="<?php echo site_url('admin/promotioncriteria?tab=criteria&criteria_session_id=' . (int) $criteria_session_id); ?>">Cancel edit</a>
                                        </div>
                                    <?php } ?>
                                </div>
                                <form method="post" action="<?php echo site_url('admin/promotioncriteria/save'); ?>" autocomplete="off">
                                    <?php echo $this->customlib->getCSRF(); ?>
                                    <input type="hidden" name="promotion_csrf" value="<?php echo html_escape($promotion_csrf); ?>">
                                    <input type="hidden" name="id" value="<?php echo !empty($edit_criterion) ? (int) $edit_criterion['id'] : 0; ?>">
                                    <div class="box-body">
                                        <div class="row">
                                            <div class="col-md-5">
                                                <div class="form-group">
                                                    <label for="criterion-name">Criteria name <small class="req">*</small></label>
                                                    <input id="criterion-name" name="name" maxlength="191" required class="form-control" value="<?php echo !empty($edit_criterion) ? html_escape($edit_criterion['name']) : ''; ?>" placeholder="e.g. Basic School Promotion">
                                                </div>
                                            </div>
                                            <div class="col-md-3">
                                                <div class="form-group">
                                                    <label for="criterion-session">Academic session <small class="req">*</small></label>
                                                    <select id="criterion-session" <?php echo !empty($edit_criterion) ? 'disabled' : 'name="session_id" required'; ?> class="form-control">
                                                        <?php foreach ($sessions as $session) { ?>
                                                            <?php $selectedSession = !empty($edit_criterion) ? (int) $edit_criterion['session_id'] : (int) $criteria_session_id; ?>
                                                            <option value="<?php echo (int) $session['id']; ?>" <?php echo $selectedSession === (int) $session['id'] ? 'selected' : ''; ?>>
                                                                <?php echo html_escape($session['session']); ?>
                                                            </option>
                                                        <?php } ?>
                                                    </select>
                                                    <?php if (!empty($edit_criterion)) { ?><input type="hidden" name="session_id" value="<?php echo (int) $edit_criterion['session_id']; ?>"><?php } ?>
                                                    <?php if (!empty($edit_criterion)) { ?><p class="help-block">Clone this criterion to use it in another session.</p><?php } ?>
                                                </div>
                                            </div>
                                            <div class="col-md-2">
                                                <div class="form-group">
                                                    <label for="criterion-average">Minimum average (%) <small class="req">*</small></label>
                                                    <input id="criterion-average" name="minimum_average" type="number" min="0" max="100" step="0.01" required class="form-control" value="<?php echo !empty($edit_criterion) ? html_escape($edit_criterion['minimum_average']) : '50.00'; ?>">
                                                </div>
                                            </div>
                                            <div class="col-md-2">
                                                <div class="form-group">
                                                    <label>Status</label>
                                                    <input type="hidden" name="is_active" value="0">
                                                    <div class="checkbox">
                                                        <label>
                                                            <input name="is_active" type="checkbox" value="1" <?php echo empty($edit_criterion) || !empty($edit_criterion['is_active']) ? 'checked' : ''; ?>> Active
                                                        </label>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>

                                        <hr>
                                        <h4>Assigned classes and display-only destination</h4>
                                        <p class="text-muted">One criterion can serve several classes. For a final class, use a custom label such as “Graduated”.</p>
                                        <div class="scope-table">
                                            <table class="table table-striped table-condensed">
                                                <thead>
                                                    <tr>
                                                        <th style="width:50px">Use</th>
                                                        <th>Current class</th>
                                                        <th style="width:34%">Promoted-to class</th>
                                                        <th style="width:34%">Or custom label</th>
                                                    </tr>
                                                </thead>
                                                <tbody>
                                                <?php foreach ($classes as $class) { ?>
                                                    <?php
                                                    $classId = (int) $class['id'];
                                                    $assigned = isset($editClassMap[$classId]);
                                                    $assignment = $assigned ? $editClassMap[$classId] : array();
                                                    $targetId = !empty($assignment['promoted_to_class_id']) ? (int) $assignment['promoted_to_class_id'] : 0;
                                                    $targetLabel = !empty($assignment['promoted_to_label']) ? $assignment['promoted_to_label'] : '';
                                                    ?>
                                                    <tr>
                                                        <td><input class="criterion-class-check" type="checkbox" name="class_ids[]" value="<?php echo $classId; ?>" <?php echo $assigned ? 'checked' : ''; ?>></td>
                                                        <td><?php echo html_escape($class['class']); ?></td>
                                                        <td>
                                                            <select name="target_class_id[<?php echo $classId; ?>]" class="form-control input-sm promoted-target-select">
                                                                <option value="">Select next class</option>
                                                                <?php foreach ($classes as $targetClass) { ?>
                                                                    <option value="<?php echo (int) $targetClass['id']; ?>" <?php echo $targetId === (int) $targetClass['id'] ? 'selected' : ''; ?>>
                                                                        <?php echo html_escape($targetClass['class']); ?>
                                                                    </option>
                                                                <?php } ?>
                                                            </select>
                                                        </td>
                                                        <td><input name="target_label[<?php echo $classId; ?>]" maxlength="191" class="form-control input-sm promoted-target-label" value="<?php echo html_escape($targetLabel); ?>" placeholder="e.g. Graduated"></td>
                                                    </tr>
                                                <?php } ?>
                                                </tbody>
                                            </table>
                                        </div>

                                        <hr>
                                        <h4>Priority subject rules <small class="text-muted">(optional)</small></h4>
                                        <p class="text-muted">When selected, every priority subject must meet its own annual minimum as well as the overall minimum.</p>
                                        <div class="form-group" style="max-width:360px">
                                            <input id="priority-subject-search" type="search" class="form-control input-sm" placeholder="Filter subjects">
                                        </div>
                                        <div class="subject-table">
                                            <table class="table table-striped table-condensed" id="priority-subject-table">
                                                <thead><tr><th style="width:50px">Use</th><th>Subject</th><th style="width:220px">Minimum annual score (%)</th></tr></thead>
                                                <tbody>
                                                <?php foreach ($subjects as $subject) { ?>
                                                    <?php
                                                    $subjectId = (int) $subject['id'];
                                                    $hasRule = isset($editSubjectMap[$subjectId]);
                                                    $subjectMinimum = $hasRule ? $editSubjectMap[$subjectId]['minimum_average'] : '50.00';
                                                    ?>
                                                    <tr data-search="<?php echo html_escape(strtolower($subject['name'] . ' ' . $subject['code'])); ?>">
                                                        <td><input type="checkbox" name="subject_ids[]" value="<?php echo $subjectId; ?>" <?php echo $hasRule ? 'checked' : ''; ?>></td>
                                                        <td><?php echo html_escape($subject['name']); ?> <small class="text-muted"><?php echo html_escape($subject['code']); ?></small></td>
                                                        <td><input name="subject_minimum[<?php echo $subjectId; ?>]" type="number" min="0" max="100" step="0.01" class="form-control input-sm" value="<?php echo html_escape($subjectMinimum); ?>"></td>
                                                    </tr>
                                                <?php } ?>
                                                </tbody>
                                            </table>
                                        </div>
                                    </div>
                                    <div class="box-footer">
                                        <button type="submit" class="btn btn-primary pull-right"><i class="fa fa-save"></i> <?php echo !empty($edit_criterion) ? 'Update criterion' : 'Create criterion'; ?></button>
                                        <div class="clearfix"></div>
                                    </div>
                                </form>
                            </div>
                        <?php } ?>

                        <div class="box box-info">
                            <div class="box-header with-border">
                                <h3 class="box-title"><i class="fa fa-list"></i> Criteria for selected session</h3>
                            </div>
                            <div class="box-body table-responsive no-padding">
                                <table class="table table-hover">
                                    <thead>
                                        <tr><th>Name</th><th>Minimum</th><th>Classes and destinations</th><th>Priority subjects</th><th>Status</th><th style="min-width:280px">Actions</th></tr>
                                    </thead>
                                    <tbody>
                                    <?php if (empty($criteria_list)) { ?>
                                        <tr><td colspan="6" class="empty-state">No promotion criterion has been configured for this session.</td></tr>
                                    <?php } ?>
                                    <?php foreach ($criteria_list as $criterion) { ?>
                                        <tr>
                                            <td><strong><?php echo html_escape($criterion['name']); ?></strong><br><small class="text-muted"><?php echo html_escape($criterion['session']); ?></small></td>
                                            <td><?php echo number_format((float) $criterion['minimum_average'], 2); ?>%</td>
                                            <td>
                                                <?php foreach ($criterion['classes'] as $assignment) { ?>
                                                    <?php $destination = !empty($assignment['promoted_to_label']) ? $assignment['promoted_to_label'] : $assignment['promoted_to_class']; ?>
                                                    <span class="criteria-chip"><?php echo html_escape($assignment['class'] . ' → ' . $destination); ?></span>
                                                <?php } ?>
                                            </td>
                                            <td>
                                                <?php if (empty($criterion['subjects'])) { ?>
                                                    <span class="text-muted">None</span>
                                                <?php } else { ?>
                                                    <?php foreach ($criterion['subjects'] as $rule) { ?>
                                                        <div class="priority-rule"><?php echo html_escape($rule['subject_name']); ?> ≥ <?php echo number_format((float) $rule['minimum_average'], 2); ?>%</div>
                                                    <?php } ?>
                                                <?php } ?>
                                            </td>
                                            <td><span class="label label-<?php echo !empty($criterion['is_active']) ? 'success' : 'default'; ?>"><?php echo !empty($criterion['is_active']) ? 'Active' : 'Archived'; ?></span></td>
                                            <td>
                                                <?php if ($can_edit_criteria) { ?>
                                                    <a class="btn btn-default btn-xs" href="<?php echo site_url('admin/promotioncriteria?tab=criteria&criteria_session_id=' . (int) $criterion['session_id'] . '&edit=' . (int) $criterion['id']); ?>"><i class="fa fa-pencil"></i> Edit</a>
                                                <?php } ?>
                                                <?php if ($can_add_criteria) { ?>
                                                    <form class="form-inline compact-form" method="post" action="<?php echo site_url('admin/promotioncriteria/clonecriterion'); ?>" style="display:inline-block">
                                                        <?php echo $this->customlib->getCSRF(); ?>
                                                        <input type="hidden" name="promotion_csrf" value="<?php echo html_escape($promotion_csrf); ?>">
                                                        <input type="hidden" name="id" value="<?php echo (int) $criterion['id']; ?>">
                                                        <select name="target_session_id" required class="form-control input-xs">
                                                            <option value="">Clone to…</option>
                                                            <?php foreach ($sessions as $session) { ?>
                                                                <?php if ((int) $session['id'] !== (int) $criterion['session_id']) { ?>
                                                                    <option value="<?php echo (int) $session['id']; ?>"><?php echo html_escape($session['session']); ?></option>
                                                                <?php } ?>
                                                            <?php } ?>
                                                        </select>
                                                        <button class="btn btn-info btn-xs" title="Clone"><i class="fa fa-copy"></i></button>
                                                    </form>
                                                <?php } ?>
                                                <?php if ($can_edit_criteria && !empty($criterion['is_active'])) { ?>
                                                    <form method="post" action="<?php echo site_url('admin/promotioncriteria/archive'); ?>" style="display:inline-block" onsubmit="return confirm('Archive this criterion? It will stop producing automatic decisions.');">
                                                        <?php echo $this->customlib->getCSRF(); ?>
                                                        <input type="hidden" name="promotion_csrf" value="<?php echo html_escape($promotion_csrf); ?>">
                                                        <input type="hidden" name="id" value="<?php echo (int) $criterion['id']; ?>">
                                                        <button class="btn btn-warning btn-xs"><i class="fa fa-archive"></i> Archive</button>
                                                    </form>
                                                <?php } ?>
                                            </td>
                                        </tr>
                                    <?php } ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                <?php } ?>

                <?php if ($tab === 'review' && $can_view_review) { ?>
                    <div class="tab-pane active" id="promotion-review-tab">
                        <div class="box box-primary">
                            <div class="box-header with-border"><h3 class="box-title"><i class="fa fa-search"></i> Select class</h3></div>
                            <form method="get" action="<?php echo site_url('admin/promotioncriteria'); ?>">
                                <input type="hidden" name="tab" value="review">
                                <div class="box-body">
                                    <div class="row">
                                        <div class="col-md-4">
                                            <div class="form-group">
                                                <label for="review-session">Session <small class="req">*</small></label>
                                                <select id="review-session" name="session_id" required class="form-control">
                                                    <?php foreach ($sessions as $session) { ?>
                                                        <option value="<?php echo (int) $session['id']; ?>" <?php echo (int) $review_session_id === (int) $session['id'] ? 'selected' : ''; ?>><?php echo html_escape($session['session']); ?></option>
                                                    <?php } ?>
                                                </select>
                                            </div>
                                        </div>
                                        <div class="col-md-4">
                                            <div class="form-group">
                                                <label for="review-class">Class <small class="req">*</small></label>
                                                <select id="review-class" name="class_id" required class="form-control" data-selected="<?php echo (int) $review_class_id; ?>">
                                                    <option value="">Select</option>
                                                </select>
                                            </div>
                                        </div>
                                        <div class="col-md-4">
                                            <div class="form-group">
                                                <label for="review-section">Section <small class="req">*</small></label>
                                                <select id="review-section" name="section_id" required class="form-control" data-selected="<?php echo (int) $review_section_id; ?>">
                                                    <option value="">Select</option>
                                                </select>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                <div class="box-footer"><button class="btn btn-primary pull-right"><i class="fa fa-search"></i> Review outcomes</button><div class="clearfix"></div></div>
                            </form>
                        </div>

                        <?php if ($review_error !== '') { ?>
                            <div class="alert alert-danger"><i class="fa fa-ban"></i> <?php echo html_escape($review_error); ?></div>
                        <?php } ?>

                        <?php if ($review_scope) { ?>
                            <div class="box box-info">
                                <div class="box-header with-border">
                                    <h3 class="box-title">
                                        <i class="fa fa-users"></i>
                                        <?php echo html_escape($review_scope['class'] . ' ' . $review_scope['section'] . ' — ' . $review_scope['session']); ?>
                                    </h3>
                                </div>
                                <div class="box-body table-responsive no-padding">
                                    <table class="table table-striped table-hover review-table">
                                        <thead>
                                            <tr>
                                                <th>Student</th><th>Annual average</th><th>Priority subjects</th><th>Automatic decision</th><th>Final result note</th><th>Target</th><th>Source</th><?php if ($can_override) { ?><th>Action</th><?php } ?>
                                            </tr>
                                        </thead>
                                        <tbody>
                                        <?php if (empty($review_rows)) { ?>
                                            <tr><td colspan="<?php echo $can_override ? 8 : 7; ?>" class="empty-state">No active students were found in this class and section.</td></tr>
                                        <?php } ?>
                                        <?php foreach ($review_rows as $student) { ?>
                                            <?php
                                            $automatic = is_array($student['automatic']) ? $student['automatic'] : array();
                                            $final = is_array($student['final']) ? $student['final'] : array();
                                            $automaticDecision = isset($automatic['decision']) ? $automatic['decision'] : 'pending';
                                            $finalDecision = isset($final['decision']) ? $final['decision'] : 'pending';
                                            $finalSource = strtolower((string) (isset($final['source']) ? $final['source'] : 'system'));
                                            $targetLabel = isset($final['target_label']) ? (string) $final['target_label'] : '';
                                            $priorityResults = !empty($automatic['priority_results']) && is_array($automatic['priority_results']) ? $automatic['priority_results'] : array();
                                            ?>
                                            <tr class="<?php echo empty($automatic['criteria_id']) ? 'warning' : ''; ?>">
                                                <td><strong><?php echo html_escape($student['student_name']); ?></strong><br><small class="text-muted"><?php echo html_escape($student['admission_no']); ?></small></td>
                                                <td>
                                                    <?php if (isset($automatic['overall_average']) && $automatic['overall_average'] !== null && $automatic['overall_average'] !== '') { ?>
                                                        <?php echo number_format((float) $automatic['overall_average'], 2); ?>%
                                                    <?php } else { ?>
                                                        <span class="text-muted">—</span>
                                                    <?php } ?>
                                                </td>
                                                <td class="priority-cell">
                                                    <?php if (empty($priorityResults)) { ?>
                                                        <span class="text-muted">None</span>
                                                    <?php } else { ?>
                                                        <?php foreach ($priorityResults as $priority) { ?>
                                                            <?php
                                                            $priorityName = isset($priority['subject_name']) ? $priority['subject_name'] : (isset($priority['name']) ? $priority['name'] : 'Priority subject');
                                                            $priorityAverage = isset($priority['average']) ? $priority['average'] : (isset($priority['score']) ? $priority['score'] : null);
                                                            $priorityMinimum = isset($priority['minimum_average']) ? $priority['minimum_average'] : (isset($priority['minimum']) ? $priority['minimum'] : null);
                                                            $priorityPassed = isset($priority['met']) ? (bool) $priority['met'] : (isset($priority['passed']) ? (bool) $priority['passed'] : false);
                                                            ?>
                                                            <div>
                                                                <i class="fa <?php echo $priorityPassed ? 'fa-check text-green' : 'fa-times text-red'; ?>"></i>
                                                                <?php echo html_escape($priorityName); ?>:
                                                                <?php echo $priorityAverage === null ? '—' : number_format((float) $priorityAverage, 2) . '%'; ?>
                                                                <?php if ($priorityMinimum !== null) { ?><small>(min <?php echo number_format((float) $priorityMinimum, 2); ?>%)</small><?php } ?>
                                                            </div>
                                                        <?php } ?>
                                                    <?php } ?>
                                                </td>
                                                <td class="note-cell">
                                                    <span class="label label-<?php echo $decisionClass($automaticDecision); ?>"><?php echo $decisionLabel($automaticDecision); ?></span><br>
                                                    <small><?php echo html_escape(isset($automatic['note']) ? $automatic['note'] : 'PROMOTION PENDING'); ?></small>
                                                </td>
                                                <td class="note-cell">
                                                    <span class="label label-<?php echo $decisionClass($finalDecision); ?>"><?php echo $decisionLabel($finalDecision); ?></span><br>
                                                    <strong><?php echo html_escape(isset($final['note']) ? $final['note'] : 'PROMOTION PENDING'); ?></strong>
                                                </td>
                                                <td><?php echo $targetLabel !== '' ? html_escape($targetLabel) : '<span class="text-muted">—</span>'; ?></td>
                                                <td><span class="label label-<?php echo $finalSource === 'override' ? 'primary' : 'default'; ?>"><?php echo $finalSource === 'override' ? 'Override' : 'System'; ?></span></td>
                                                <?php if ($can_override) { ?>
                                                    <td style="white-space:nowrap">
                                                        <button type="button" class="btn btn-primary btn-xs promotion-override-button" data-toggle="modal" data-target="#promotion-override-modal" data-student-id="<?php echo (int) $student['id']; ?>" data-student-name="<?php echo html_escape($student['student_name']); ?>" data-target-label="<?php echo html_escape($targetLabel); ?>"><i class="fa fa-pencil"></i> Override</button>
                                                        <?php if ($finalSource === 'override') { ?>
                                                            <button type="button" class="btn btn-warning btn-xs promotion-clear-button" data-toggle="modal" data-target="#promotion-clear-modal" data-student-id="<?php echo (int) $student['id']; ?>" data-student-name="<?php echo html_escape($student['student_name']); ?>"><i class="fa fa-undo"></i> Clear</button>
                                                        <?php } ?>
                                                    </td>
                                                <?php } ?>
                                            </tr>
                                        <?php } ?>
                                        </tbody>
                                    </table>
                                </div>
                                <div class="box-footer text-muted">
                                    <i class="fa fa-exclamation-triangle"></i> Highlighted rows have no active criterion or are otherwise awaiting an automatic result.
                                </div>
                            </div>
                        <?php } ?>
                    </div>
                <?php } ?>
            </div>
        </div>
    </section>
</div>

<?php if ($tab === 'review' && $can_override) { ?>
    <div class="modal fade" id="promotion-override-modal" tabindex="-1" role="dialog" aria-labelledby="promotion-override-title">
        <div class="modal-dialog" role="document">
            <div class="modal-content">
                <form method="post" action="<?php echo site_url('admin/promotioncriteria/setoverride'); ?>">
                    <?php echo $this->customlib->getCSRF(); ?>
                    <input type="hidden" name="promotion_csrf" value="<?php echo html_escape($promotion_csrf); ?>">
                    <input type="hidden" name="student_id" id="override-student-id">
                    <input type="hidden" name="session_id" value="<?php echo (int) $review_session_id; ?>">
                    <input type="hidden" name="class_id" value="<?php echo (int) $review_class_id; ?>">
                    <input type="hidden" name="section_id" value="<?php echo (int) $review_section_id; ?>">
                    <div class="modal-header">
                        <button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button>
                        <h4 class="modal-title" id="promotion-override-title">Override promotion note — <span id="override-student-name"></span></h4>
                    </div>
                    <div class="modal-body">
                        <div class="form-group">
                            <label for="override-decision">Decision <small class="req">*</small></label>
                            <select id="override-decision" name="decision" required class="form-control">
                                <option value="promoted">Promoted</option>
                                <option value="not_promoted">Not promoted</option>
                            </select>
                        </div>
                        <div class="row" id="override-target-fields">
                            <div class="col-sm-6">
                                <div class="form-group">
                                    <label for="override-target-class">Promoted-to class</label>
                                    <select id="override-target-class" name="target_class_id" class="form-control promoted-target-select">
                                        <option value="">No class selected</option>
                                        <?php foreach ($classes as $class) { ?><option value="<?php echo (int) $class['id']; ?>"><?php echo html_escape($class['class']); ?></option><?php } ?>
                                    </select>
                                </div>
                            </div>
                            <div class="col-sm-6">
                                <div class="form-group">
                                    <label for="override-target-label">Or custom label</label>
                                    <input id="override-target-label" name="target_label" maxlength="191" class="form-control promoted-target-label" placeholder="e.g. Graduated">
                                </div>
                            </div>
                        </div>
                        <div class="form-group">
                            <label for="override-reason">Reason <small class="req">*</small></label>
                            <textarea id="override-reason" name="reason" rows="3" maxlength="1000" required class="form-control" placeholder="Explain why the system decision is being changed"></textarea>
                            <p class="help-block">This action is retained in the audit history and applies to both third-term result formats.</p>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-default" data-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary">Record override</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <div class="modal fade" id="promotion-clear-modal" tabindex="-1" role="dialog" aria-labelledby="promotion-clear-title">
        <div class="modal-dialog" role="document">
            <div class="modal-content">
                <form method="post" action="<?php echo site_url('admin/promotioncriteria/clearoverride'); ?>">
                    <?php echo $this->customlib->getCSRF(); ?>
                    <input type="hidden" name="promotion_csrf" value="<?php echo html_escape($promotion_csrf); ?>">
                    <input type="hidden" name="student_id" id="clear-student-id">
                    <input type="hidden" name="session_id" value="<?php echo (int) $review_session_id; ?>">
                    <input type="hidden" name="class_id" value="<?php echo (int) $review_class_id; ?>">
                    <input type="hidden" name="section_id" value="<?php echo (int) $review_section_id; ?>">
                    <div class="modal-header">
                        <button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button>
                        <h4 class="modal-title" id="promotion-clear-title">Clear override — <span id="clear-student-name"></span></h4>
                    </div>
                    <div class="modal-body">
                        <p>The current automatic promotion decision will become active again.</p>
                        <div class="form-group">
                            <label for="clear-reason">Reason <small class="req">*</small></label>
                            <textarea id="clear-reason" name="reason" rows="3" maxlength="1000" required class="form-control" placeholder="Explain why the override is being cleared"></textarea>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-default" data-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-warning">Clear override</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
<?php } ?>

<script>
(function () {
    'use strict';

    var scopeRows = <?php echo json_encode($scopeRows, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>;
    var sessionSelect = document.getElementById('review-session');
    var classSelect = document.getElementById('review-class');
    var sectionSelect = document.getElementById('review-section');

    function option(value, label) {
        var item = document.createElement('option');
        item.value = value;
        item.textContent = label;
        return item;
    }

    function matchingScopes() {
        if (!sessionSelect) { return []; }
        var sessionId = parseInt(sessionSelect.value, 10) || 0;
        return scopeRows.filter(function (scope) {
            return parseInt(scope.session_id, 10) === 0 || parseInt(scope.session_id, 10) === sessionId;
        });
    }

    function rebuildClasses(keepSelected) {
        if (!classSelect) { return; }
        var selected = keepSelected ? parseInt(classSelect.getAttribute('data-selected') || classSelect.value, 10) : 0;
        var seen = {};
        classSelect.innerHTML = '';
        classSelect.appendChild(option('', 'Select'));
        matchingScopes().forEach(function (scope) {
            var id = parseInt(scope.class_id, 10);
            if (!seen[id]) {
                classSelect.appendChild(option(id, scope.class));
                seen[id] = true;
            }
        });
        if (selected && seen[selected]) { classSelect.value = String(selected); }
        rebuildSections(keepSelected);
    }

    function rebuildSections(keepSelected) {
        if (!sectionSelect || !classSelect) { return; }
        var classId = parseInt(classSelect.value, 10) || 0;
        var selected = keepSelected ? parseInt(sectionSelect.getAttribute('data-selected') || sectionSelect.value, 10) : 0;
        var seen = {};
        sectionSelect.innerHTML = '';
        sectionSelect.appendChild(option('', 'Select'));
        matchingScopes().forEach(function (scope) {
            var id = parseInt(scope.section_id, 10);
            if (parseInt(scope.class_id, 10) === classId && !seen[id]) {
                sectionSelect.appendChild(option(id, scope.section));
                seen[id] = true;
            }
        });
        if (selected && seen[selected]) { sectionSelect.value = String(selected); }
    }

    if (sessionSelect && classSelect && sectionSelect) {
        rebuildClasses(true);
        sessionSelect.addEventListener('change', function () {
            classSelect.setAttribute('data-selected', '');
            sectionSelect.setAttribute('data-selected', '');
            rebuildClasses(false);
        });
        classSelect.addEventListener('change', function () {
            sectionSelect.setAttribute('data-selected', '');
            rebuildSections(false);
        });
    }

    Array.prototype.forEach.call(document.querySelectorAll('.promoted-target-select'), function (select) {
        select.addEventListener('change', function () {
            if (!select.value) { return; }
            var row = select.closest ? select.closest('tr, .row, .modal-body') : null;
            var label = row ? row.querySelector('.promoted-target-label') : null;
            if (label) { label.value = ''; }
        });
    });
    Array.prototype.forEach.call(document.querySelectorAll('.promoted-target-label'), function (input) {
        input.addEventListener('input', function () {
            if (!input.value) { return; }
            var row = input.closest ? input.closest('tr, .row, .modal-body') : null;
            var select = row ? row.querySelector('.promoted-target-select') : null;
            if (select) { select.value = ''; }
        });
    });

    var subjectSearch = document.getElementById('priority-subject-search');
    if (subjectSearch) {
        subjectSearch.addEventListener('input', function () {
            var query = subjectSearch.value.toLowerCase().trim();
            Array.prototype.forEach.call(document.querySelectorAll('#priority-subject-table tbody tr'), function (row) {
                row.style.display = !query || row.getAttribute('data-search').indexOf(query) !== -1 ? '' : 'none';
            });
        });
    }

    Array.prototype.forEach.call(document.querySelectorAll('.promotion-override-button'), function (button) {
        button.addEventListener('click', function () {
            document.getElementById('override-student-id').value = button.getAttribute('data-student-id');
            document.getElementById('override-student-name').textContent = button.getAttribute('data-student-name');
            document.getElementById('override-target-class').value = '';
            document.getElementById('override-target-label').value = button.getAttribute('data-target-label') || '';
            document.getElementById('override-reason').value = '';
            updateOverrideTargetFields();
        });
    });
    Array.prototype.forEach.call(document.querySelectorAll('.promotion-clear-button'), function (button) {
        button.addEventListener('click', function () {
            document.getElementById('clear-student-id').value = button.getAttribute('data-student-id');
            document.getElementById('clear-student-name').textContent = button.getAttribute('data-student-name');
            document.getElementById('clear-reason').value = '';
        });
    });

    function updateOverrideTargetFields() {
        var decision = document.getElementById('override-decision');
        var fields = document.getElementById('override-target-fields');
        var targetClass = document.getElementById('override-target-class');
        var targetLabel = document.getElementById('override-target-label');
        if (!decision || !fields) { return; }
        var promoted = decision.value === 'promoted';
        fields.style.display = promoted ? '' : 'none';
        if (!promoted) {
            targetClass.value = '';
            targetLabel.value = '';
        }
    }
    var overrideDecision = document.getElementById('override-decision');
    if (overrideDecision) {
        overrideDecision.addEventListener('change', updateOverrideTargetFields);
        updateOverrideTargetFields();
    }
}());
</script>
