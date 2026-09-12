<?php
$paper_types = array('objective' => 'Objective', 'theory' => 'Theory / Essay');
$allowed_question_types = array('singlechoice', 'multichoice', 'true_false', 'short_answer', 'numeric', 'matching', 'ordering', 'grouped_passage', 'long_answer');
$native_question_types = array_intersect_key((array) $native_question_types, array_flip($allowed_question_types));
$question_type = array_intersect_key((array) $question_type, array_flip($allowed_question_types));
$can_edit_assessment = !empty($editable) && $this->rbac->hasPrivilege('online_examination', 'can_edit');
$can_edit_questions = !empty($editable) && $this->rbac->hasPrivilege('add_questions_in_exam', 'can_edit');
$can_view_roster = !empty($compact_supported) && $this->rbac->hasPrivilege('online_assign_view_student', 'can_view');
$can_view_operations = !empty($compact_supported) && $exam->lifecycle_status !== 'draft' && $this->rbac->hasPrivilege('online_examination', 'can_view');
$component_label = $exam->result_adapter === 'british_outcome'
    ? 'British outcome (Emerging / Expected / Exceeding)'
    : (!empty($exam->target_component)
        ? strtoupper($exam->target_component) . ' (max ' . number_format($exam->target_max_score, 2) . ')'
        : ucwords(str_replace('_', ' ', $exam->purpose)) . (!empty($exam->target_max_score) ? ' (max ' . number_format($exam->target_max_score, 2) . ')' : ''));
$format_datetime = function ($value) {
    return $value ? $this->customlib->dateyyyymmddToDateTimeformat($value, false) : '';
};
$paper_sections_json = array();
$paper_types_json = array();
foreach ($papers as $paper) {
    $paper_types_json[(int) $paper['id']] = $paper['paper_type'];
    $paper_sections_json[(int) $paper['id']] = array_map(function ($section) {
        return array('id' => (int) $section['id'], 'title' => $section['title']);
    }, $paper['sections']);
}
$authored_question_json = array();
foreach ((array) $authored_questions as $authored_question) {
    $definition = json_decode($authored_question['authoring_json'], true);
    if (!is_array($definition)) {
        continue;
    }
    $authored_question_json[(int) $authored_question['id']] = array(
        'id' => (int) $authored_question['id'],
        'paper_id' => (int) $authored_question['paper_id'],
        'paper_section_id' => empty($authored_question['paper_section_id']) ? '' : (int) $authored_question['paper_section_id'],
        'question' => $authored_question['question'],
        'marks' => $authored_question['marks'],
        'neg_marks' => $authored_question['neg_marks'],
        'display_order' => (int) $authored_question['display_order'],
        'is_compulsory' => (int) $authored_question['is_compulsory'],
        'marking_scheme' => $authored_question['marking_scheme'],
        'definition' => $definition,
    );
}
?>
<?php $this->load->view('admin/onlineexam/_assessment_styles'); ?>
<div class="content-wrapper onlineexam-ui onlineexam-builder-page">
    <section class="content-header">
        <h1><?php echo html_escape($exam->exam); ?> <small>Paper and section builder</small></h1>
    </section>
    <section class="content">
        <?php if ($this->session->flashdata('msg')) { echo $this->session->flashdata('msg'); } ?>

        <div class="box box-primary">
            <div class="box-header with-border assessment-box-header">
                <h3 class="box-title">Academic assessment</h3>
                <div class="box-tools">
                    <span class="label label-<?php echo $exam->lifecycle_status === 'draft' ? 'warning' : 'success'; ?>"><?php echo html_escape(ucwords(str_replace('_', ' ', $exam->lifecycle_status))); ?></span>
                    <span class="label label-default">Revision <?php echo (int) $exam->revision; ?></span>
                </div>
            </div>
            <div class="box-body no-padding">
                <div class="onlineexam-scroll">
                    <table class="table table-condensed assessment-meta-table">
                        <tbody><tr>
                            <td><strong>Session / Term</strong><br><?php echo html_escape($exam->session_name . ' / ' . strtoupper($exam->term) . ' Term'); ?></td>
                            <td><strong>Class / Arms</strong><br><?php echo html_escape($exam->class_name . ' (' . implode(', ', $exam->section_names) . ')'); ?></td>
                            <td><strong>Subject</strong><br><?php echo html_escape($exam->subject_name); ?></td>
                            <td><strong>Result destination</strong><br><?php echo html_escape($component_label); ?></td>
                        </tr></tbody>
                    </table>
                </div>
            </div>
            <div class="box-footer assessment-toolbar">
                <a href="<?php echo site_url('admin/onlineexam'); ?>" class="btn btn-default btn-sm"><i class="fa fa-arrow-left"></i> Examination list</a>
                <?php if ($can_view_roster) { ?><a href="<?php echo site_url('admin/onlineexam/assign/' . $exam->id); ?>" class="btn btn-default btn-sm"><i class="fa fa-users"></i> Candidate roster</a><?php } ?>
                <?php if ($can_view_operations) { ?><a href="<?php echo site_url('admin/onlineexam/operations/' . $exam->id); ?>" class="btn btn-default btn-sm"><i class="fa fa-table"></i> Review</a><?php } ?>
                <?php if ($can_edit_assessment) { ?><a href="<?php echo site_url('admin/onlineexam/workflow/' . $exam->id); ?>" class="btn btn-default btn-sm"><i class="fa fa-pencil"></i> Edit context</a><?php } ?>
            </div>
        </div>

        <?php if ($exam->lifecycle_status !== 'draft') { ?>
        <div class="box box-default">
            <div class="box-header with-border"><h3 class="box-title">Candidate feedback</h3></div>
            <div class="box-body">
                <p>Current online feedback: <strong><?php echo html_escape(ucwords($exam->feedback_status)); ?></strong>. This control never publishes the official report card.</p>
                <?php if ($this->rbac->hasPrivilege('online_examination', 'can_edit')) { ?>
                <form method="post" action="<?php echo site_url('admin/onlineexam/feedback/' . $exam->id); ?>" class="single-action-footer">
                    <?php echo $this->customlib->getCSRF(); ?>
                    <input type="hidden" name="onlineexam_workflow_token" value="<?php echo html_escape($workflow_csrf); ?>">
                    <input type="hidden" name="feedback_action" value="<?php echo $exam->feedback_status === 'released' ? 'hide' : 'release'; ?>">
                    <button class="btn btn-<?php echo $exam->feedback_status === 'released' ? 'default' : 'info'; ?>"><i class="fa fa-<?php echo $exam->feedback_status === 'released' ? 'eye-slash' : 'eye'; ?>"></i> <?php echo $exam->feedback_status === 'released' ? 'Hide online feedback' : 'Release online feedback'; ?></button>
                </form>
                <?php } ?>
            </div>
        </div>
        <?php } ?>

        <?php if (!$editable) { ?>
            <div class="alert alert-info"><i class="fa fa-lock"></i> This revision is frozen. Papers, sections, questions, marks, and result mappings cannot be edited.</div>
        <?php } elseif (!$can_edit_assessment && !$can_edit_questions) { ?>
            <div class="alert alert-info"><i class="fa fa-eye"></i> You have view-only access to this draft assessment.</div>
        <?php } ?>

        <?php if ($exam->result_adapter === 'british_outcome') {
            $british_profile = !empty($result_profile) ? json_decode($result_profile['configuration_json'], true) : array('mode' => 'teacher_selection');
            if (!is_array($british_profile) || !in_array(isset($british_profile['mode']) ? $british_profile['mode'] : '', array('teacher_selection', 'thresholds'), true)) {
                $british_profile = array('mode' => 'teacher_selection');
            }
            $british_ranges = array('Emerging' => array(0, 39.99), 'Expected' => array(40, 69.99), 'Exceeding' => array(70, 100));
            foreach (isset($british_profile['outcomes']) ? $british_profile['outcomes'] : array() as $range) {
                if (isset($british_ranges[$range['value']])) { $british_ranges[$range['value']] = array($range['min'], $range['max']); }
            }
        ?>
        <div class="box box-purple" style="border-top-color:#605ca8">
            <div class="box-header with-border"><h3 class="box-title">British result outcome</h3></div>
            <form method="post" action="<?php echo site_url('admin/onlineexam/britishProfileSave'); ?>">
                <?php echo $this->customlib->getCSRF(); ?>
                <input type="hidden" name="onlineexam_workflow_token" value="<?php echo html_escape($workflow_csrf); ?>">
                <input type="hidden" name="onlineexam_id" value="<?php echo (int) $exam->id; ?>">
                <div class="box-body">
                    <div class="row">
                        <div class="col-md-4"><div class="form-group"><label for="british_profile_mode">How is the outcome decided?</label><select name="profile_mode" id="british_profile_mode" class="form-control" <?php echo $can_edit_assessment ? '' : 'disabled'; ?>><option value="teacher_selection" <?php echo $british_profile['mode'] === 'teacher_selection' ? 'selected' : ''; ?>>Teacher selects the outcome</option><option value="thresholds" <?php echo $british_profile['mode'] === 'thresholds' ? 'selected' : ''; ?>>Convert the score automatically</option></select></div></div>
                        <div class="col-md-8"><p class="help-block">This follows the existing British result screen: the official subject result is Emerging, Expected or Exceeding. Teacher selection is the default. Automatic conversion uses the school-defined percentage ranges below. Existing additional comments are never replaced.</p></div>
                    </div>
                    <div id="british_thresholds" class="row" style="<?php echo $british_profile['mode'] === 'thresholds' ? '' : 'display:none'; ?>">
                        <?php foreach ($british_ranges as $outcome => $range) { ?><div class="col-md-4"><div class="well well-sm"><strong><?php echo $outcome; ?></strong><div class="row"><div class="col-xs-6"><label>Minimum</label><input type="number" name="outcome_min[<?php echo $outcome; ?>]" min="0" max="100" step="0.01" class="form-control" value="<?php echo html_escape($range[0]); ?>"></div><div class="col-xs-6"><label>Maximum</label><input type="number" name="outcome_max[<?php echo $outcome; ?>]" min="0" max="100" step="0.01" class="form-control" value="<?php echo html_escape($range[1]); ?>"></div></div></div></div><?php } ?>
                        <div class="col-md-12"><p class="help-block">The displayed ranges are editable examples, not a national rule. Confirm and save the ranges approved by the school before publishing.</p></div>
                    </div>
                </div>
                <?php if ($can_edit_assessment) { ?><div class="box-footer single-action-footer"><button class="btn btn-primary"><i class="fa fa-save"></i> Save outcome profile</button></div><?php } ?>
            </form>
        </div>
        <?php } ?>

        <?php if ($exam->result_adapter === 'kindergarten_concept') {
            $kindergarten_label_map = array();
            foreach ($kindergarten_options as $mapping_option) {
                $kindergarten_label_map[(int) $mapping_option['concept_id']] = array_values($mapping_option['result_labels']);
            }
        ?>
        <div class="box box-purple" style="border-top-color:#605ca8">
            <div class="box-header with-border"><h3 class="box-title">Kindergarten paper-to-concept mapping</h3></div>
            <div class="box-body">
                <p class="help-block">Each mapped paper or section produces one qualitative concept result using the assessment labels already configured for this class.</p>
                <?php if (empty($kindergarten_options)) { ?><div class="alert alert-danger">No active Kindergarten concept for <?php echo html_escape($exam->subject_name); ?> is assigned to this class. Correct the Kindergarten Assessment Setting first.</div><?php } ?>
                <?php if (!empty($kindergarten_mappings)) { ?>
                <div class="table-responsive onlineexam-scroll" role="region" aria-label="Kindergarten result mappings" tabindex="0"><table class="table table-bordered table-condensed"><thead><tr><th scope="col">Assessment / Concept</th><th scope="col">Paper / Section</th><th scope="col">Conversion</th><?php if ($can_edit_assessment) { ?><th scope="col"><span class="sr-only">Actions</span></th><?php } ?></tr></thead><tbody><?php foreach ($kindergarten_mappings as $mapping) { ?><tr><td><?php echo html_escape($mapping['assessment_name'] . ' / ' . $mapping['concept_text']); ?></td><td><?php echo html_escape($mapping['paper_title'] . ($mapping['section_title'] ? ' / ' . $mapping['section_title'] : '')); ?></td><td>Mapped paper/section percentage thresholds</td><?php if ($can_edit_assessment) { ?><td><form method="post" action="<?php echo site_url('admin/onlineexam/kindergartenMappingDelete'); ?>" onsubmit="return confirm('Remove this concept mapping?');"><?php echo $this->customlib->getCSRF(); ?><input type="hidden" name="onlineexam_id" value="<?php echo (int) $exam->id; ?>"><input type="hidden" name="mapping_id" value="<?php echo (int) $mapping['id']; ?>"><button class="btn btn-danger btn-xs" type="submit" aria-label="Remove Kindergarten concept mapping" title="Remove mapping"><i class="fa fa-remove" aria-hidden="true"></i></button></form></td><?php } ?></tr><?php } ?></tbody></table></div>
                <?php } ?>
                <?php if ($can_edit_assessment && !empty($kindergarten_options) && !empty($papers)) { ?>
                <form method="post" action="<?php echo site_url('admin/onlineexam/kindergartenMappingSave'); ?>" class="well well-sm">
                    <?php echo $this->customlib->getCSRF(); ?><input type="hidden" name="onlineexam_id" value="<?php echo (int) $exam->id; ?>">
                    <div class="row">
                        <div class="col-md-3"><div class="form-group"><label>Paper</label><select name="paper_id" id="kg_paper_id" class="form-control"><?php foreach ($papers as $paper) { ?><option value="<?php echo (int) $paper['id']; ?>"><?php echo html_escape($paper['title']); ?></option><?php } ?></select></div></div>
                        <div class="col-md-3"><div class="form-group"><label>Section (optional)</label><select name="paper_section_id" id="kg_section_id" class="form-control"><option value="">Whole paper</option></select></div></div>
                        <div class="col-md-3"><div class="form-group"><label>Assessment concept</label><select name="concept_id" id="kg_concept_id" class="form-control"><?php foreach ($kindergarten_options as $mapping_option) { ?><option value="<?php echo (int) $mapping_option['concept_id']; ?>"><?php echo html_escape($mapping_option['assessment_name'] . ' / ' . $mapping_option['concept_text']); ?></option><?php } ?></select></div></div>
                        <div class="col-md-3"><div class="form-group"><label>Conversion</label><select name="conversion_mode" id="kg_conversion_mode" class="form-control"><option value="thresholds">Mapped score thresholds</option></select></div></div>
                    </div>
                    <div id="kg_threshold_fields" class="row"></div>
                    <div class="single-action-footer"><button class="btn btn-primary"><i class="fa fa-link"></i> Save concept mapping</button></div>
                </form>
                <?php } ?>
            </div>
        </div>
        <?php } ?>

        <div class="row">
            <?php if ($can_edit_assessment && empty($papers)) { ?>
            <div class="col-md-12">
                <div class="box box-info">
                    <div class="box-header with-border"><h3 class="box-title">Add paper</h3></div>
                    <form method="post" action="<?php echo site_url('admin/onlineexam/paperSave'); ?>" data-builder-form="paper">
                        <?php echo $this->customlib->getCSRF(); ?>
                        <input type="hidden" name="onlineexam_id" value="<?php echo (int) $exam->id; ?>">
                        <input type="hidden" name="delivery_mode" value="cbt">
                        <div class="box-body">
                            <div class="paper-form-scroll">
                                <div class="paper-form-grid">
                                    <div class="form-group"><label>Paper title *</label><input type="text" name="title" class="form-control" placeholder="e.g. Paper 1 — Objective" required></div>
                                    <div class="form-group"><label>Code</label><input type="text" name="paper_code" class="form-control" placeholder="P1"></div>
                                    <div class="form-group"><label>Paper type *</label><select name="paper_type" class="form-control"><?php foreach ($paper_types as $value => $label) { ?><option value="<?php echo $value; ?>"><?php echo $label; ?></option><?php } ?></select></div>
                                    <div class="form-group"><label>Minutes *</label><input type="number" name="duration_minutes" min="1" value="<?php $duration_parts = explode(':', $exam->duration); echo (int) $duration_parts[0] * 60 + (int) $duration_parts[1]; ?>" class="form-control" required></div>
                                    <div class="form-group"><label>Display order</label><input type="number" name="display_order" min="0" value="0" class="form-control"></div>
                                </div>
                            </div>
                            <div class="paper-form-scroll">
                                <div class="paper-form-grid paper-form-grid-secondary">
                                    <div class="form-group"><label>Starts</label><div class="onlineexam-datetime-anchor"><input type="text" name="starts_at" class="form-control datetime_twelve_hour" value="<?php echo html_escape($format_datetime($exam->exam_from)); ?>"></div></div>
                                    <div class="form-group"><label>Ends</label><div class="onlineexam-datetime-anchor"><input type="text" name="ends_at" class="form-control datetime_twelve_hour" value="<?php echo html_escape($format_datetime($exam->exam_to)); ?>"></div></div>
                                    <div class="form-group"><label>Instructions</label><textarea name="instructions" rows="2" class="form-control" placeholder="Instructions candidates see before starting"></textarea></div>
                                    <div class="form-group"><label>&nbsp;</label><div><label><input type="checkbox" name="is_active" value="1" checked> Active</label></div></div>
                                    <div class="form-group"><label>&nbsp;</label><button class="btn btn-info btn-block" type="submit"><i class="fa fa-plus"></i> Add</button></div>
                                </div>
                            </div>
                        </div>
                    </form>
                </div>
            </div>
            <?php } ?>

            <div class="col-md-12">
                <?php if (empty($papers)) { ?>
                    <div class="alert alert-warning">No paper has been created. Add an Objective or Theory / Essay paper.</div>
                <?php } ?>
                <?php foreach ($papers as $paper) { ?>
                    <div class="box box-solid box-default">
                        <div class="box-header with-border assessment-box-header">
                            <h3 class="box-title"><?php echo html_escape($paper['title']); ?> <?php if ($paper['paper_code']) { ?><small><?php echo html_escape($paper['paper_code']); ?></small><?php } ?></h3>
                            <div class="box-tools paper-header-labels">
                                <span class="label label-info"><?php echo html_escape(isset($paper_types[$paper['paper_type']]) ? $paper_types[$paper['paper_type']] : ucwords($paper['paper_type'])); ?></span>
                                <span class="label label-default">Online CBT</span>
                                <span class="badge"><?php echo (int) $paper['question_count']; ?> question(s)</span>
                                <?php if ($can_edit_assessment) { ?><button type="button" class="btn btn-box-tool" data-toggle="collapse" data-target="#paper-edit-<?php echo (int) $paper['id']; ?>" aria-controls="paper-edit-<?php echo (int) $paper['id']; ?>" aria-expanded="false" aria-label="Edit <?php echo html_escape($paper['title']); ?>" title="Edit paper"><i class="fa fa-pencil" aria-hidden="true"></i></button><?php } ?>
                            </div>
                        </div>
                        <div class="box-body no-padding">
                            <div class="onlineexam-scroll">
                                <table class="table table-condensed paper-summary-table"><tbody><tr>
                                    <td><strong>Maximum score</strong><br><?php echo number_format($paper['raw_max_score'], 2); ?></td>
                                    <td><strong>Contribution</strong><br><?php echo number_format($paper['contribution_score'], 2); ?>%</td>
                                    <td><strong>Duration</strong><br><?php echo (int) $paper['duration_minutes']; ?> minutes</td>
                                    <td><strong>Schedule</strong><br><small><?php echo html_escape($format_datetime($paper['starts_at'])); ?><br><?php echo html_escape($format_datetime($paper['ends_at'])); ?></small></td>
                                </tr></tbody></table>
                            </div>
                            <div style="padding:0 10px 10px">
                            <?php if ($paper['instructions']) { ?><div class="well well-sm" style="margin-top:12px;margin-bottom:8px"><?php echo nl2br(html_escape(strip_tags($paper['instructions']))); ?></div><?php } ?>

                            <h4>Sections</h4>
                            <?php if (empty($paper['sections'])) { ?><p class="text-muted">No named section. Questions may still be assigned directly to this paper.</p><?php } ?>
                            <ul class="list-group">
                                <?php foreach ($paper['sections'] as $section) { ?>
                                    <li class="list-group-item paper-section-item">
                                        <div class="paper-section-main"><strong><?php echo html_escape($section['title']); ?></strong> — <?php echo html_escape(ucwords(str_replace('_', ' ', $section['answer_rule']))); ?><?php if ($section['answer_count']) { ?> <?php echo (int) $section['answer_count']; ?><?php } ?></div>
                                        <div class="paper-section-actions">
                                            <span class="badge"><?php echo (int) $section['question_count']; ?> questions</span>
                                            <?php if ($can_edit_assessment) { ?>
                                            <form method="post" action="<?php echo site_url('admin/onlineexam/paperSectionDelete'); ?>" onsubmit="return confirm('Remove this section? Questions in it must be reassigned.');">
                                                <?php echo $this->customlib->getCSRF(); ?><input type="hidden" name="onlineexam_id" value="<?php echo (int) $exam->id; ?>"><input type="hidden" name="paper_id" value="<?php echo (int) $paper['id']; ?>"><input type="hidden" name="paper_section_id" value="<?php echo (int) $section['id']; ?>"><button class="btn btn-danger btn-xs" type="submit" aria-label="Remove section <?php echo html_escape($section['title']); ?>" title="Remove section"><i class="fa fa-remove" aria-hidden="true"></i></button>
                                            </form>
                                            <?php } ?>
                                        </div>
                                    </li>
                                <?php } ?>
                            </ul>

                            <?php if ($can_edit_assessment) { ?>
                            <form method="post" action="<?php echo site_url('admin/onlineexam/paperSectionSave'); ?>" class="well well-sm" data-builder-form="section">
                                <?php echo $this->customlib->getCSRF(); ?>
                                <input type="hidden" name="onlineexam_id" value="<?php echo (int) $exam->id; ?>"><input type="hidden" name="paper_id" value="<?php echo (int) $paper['id']; ?>">
                                <div class="paper-section-scroll">
                                    <div class="paper-section-grid">
                                        <input type="text" name="title" class="form-control" placeholder="Section A" aria-label="Section title" required>
                                        <select name="answer_rule" class="form-control" aria-label="Answer rule"><option value="all">Answer all</option><option value="answer_any">Answer any N</option><option value="compulsory_plus_choice">Compulsory + choice</option></select>
                                        <input type="number" name="answer_count" min="0" value="0" class="form-control" title="Number to answer" aria-label="Number to answer">
                                        <input type="text" name="instructions" class="form-control" placeholder="Section instructions" aria-label="Section instructions">
                                        <div><input type="hidden" name="display_order" value="<?php echo count($paper['sections']); ?>"><button class="btn btn-default" title="Add section" aria-label="Add section"><i class="fa fa-plus"></i></button></div>
                                    </div>
                                </div>
                            </form>
                            <?php } ?>
                            </div>
                        </div>

                        <?php if ($can_edit_assessment) { ?>
                        <div id="paper-edit-<?php echo (int) $paper['id']; ?>" class="collapse">
                            <form method="post" action="<?php echo site_url('admin/onlineexam/paperSave'); ?>" class="box-footer" data-builder-form="paper">
                                <?php echo $this->customlib->getCSRF(); ?><input type="hidden" name="onlineexam_id" value="<?php echo (int) $exam->id; ?>"><input type="hidden" name="paper_id" value="<?php echo (int) $paper['id']; ?>"><input type="hidden" name="delivery_mode" value="cbt">
                                <div class="paper-form-scroll">
                                    <div class="paper-form-grid">
                                        <div class="form-group"><label>Title</label><input type="text" name="title" class="form-control" value="<?php echo html_escape($paper['title']); ?>" required></div>
                                        <div class="form-group"><label>Code</label><input type="text" name="paper_code" class="form-control" value="<?php echo html_escape($paper['paper_code']); ?>"></div>
                                        <div class="form-group"><label>Type</label><select name="paper_type" class="form-control"><?php foreach ($paper_types as $value => $label) { ?><option value="<?php echo $value; ?>" <?php echo $paper['paper_type'] === $value ? 'selected' : ''; ?>><?php echo $label; ?></option><?php } ?></select></div>
                                        <div class="form-group"><label>Minutes</label><input type="number" name="duration_minutes" min="1" class="form-control" value="<?php echo (int) $paper['duration_minutes']; ?>"></div>
                                        <div class="form-group"><label>Order</label><input type="number" name="display_order" min="0" class="form-control" value="<?php echo (int) $paper['display_order']; ?>"></div>
                                    </div>
                                </div>
                                <div class="paper-form-scroll">
                                    <div class="paper-form-grid paper-form-grid-secondary">
                                        <div class="form-group"><label>Starts</label><div class="onlineexam-datetime-anchor"><input type="text" name="starts_at" class="form-control datetime_twelve_hour" value="<?php echo html_escape($format_datetime($paper['starts_at'])); ?>"></div></div>
                                        <div class="form-group"><label>Ends</label><div class="onlineexam-datetime-anchor"><input type="text" name="ends_at" class="form-control datetime_twelve_hour" value="<?php echo html_escape($format_datetime($paper['ends_at'])); ?>"></div></div>
                                        <div class="form-group"><label>Instructions</label><textarea name="instructions" rows="2" class="form-control" placeholder="Instructions candidates see before starting"><?php echo html_escape(strip_tags($paper['instructions'])); ?></textarea></div>
                                        <div class="form-group"><label>&nbsp;</label><div><label><input type="checkbox" name="is_active" value="1" <?php echo $paper['is_active'] ? 'checked' : ''; ?>> Active</label></div></div>
                                        <div class="form-group"><label>&nbsp;</label><button class="btn btn-primary btn-block">Save</button></div>
                                    </div>
                                </div>
                            </form>
                            <form method="post" action="<?php echo site_url('admin/onlineexam/paperDelete'); ?>" class="box-footer" onsubmit="return confirm('Remove this paper and all of its draft sections/question assignments?');">
                                <?php echo $this->customlib->getCSRF(); ?><input type="hidden" name="onlineexam_id" value="<?php echo (int) $exam->id; ?>"><input type="hidden" name="paper_id" value="<?php echo (int) $paper['id']; ?>"><button class="btn btn-danger btn-xs"><i class="fa fa-trash"></i> Delete paper</button>
                            </form>
                        </div>
                        <?php } ?>
                    </div>
                <?php } ?>
                <?php if ($can_edit_assessment && count($papers) > 1) { ?>
                    <div class="alert alert-danger">This older draft contains more than one paper. Delete the extra papers before publishing; one subject assessment can contain only one paper.</div>
                <?php } ?>
            </div>
        </div>

        <?php if ($can_edit_questions && !empty($papers)) { ?>
        <div class="box box-primary" id="author-question">
            <div class="box-header with-border assessment-box-header">
                <h3 class="box-title" id="workflow-author-title">Create a structured question</h3>
                <div class="box-tools"><button type="button" class="btn btn-box-tool" data-widget="collapse" aria-expanded="true" aria-label="Collapse structured question form" title="Collapse or expand"><i class="fa fa-minus" aria-hidden="true"></i></button></div>
            </div>
            <?php if (!empty($authored_question_json)) { ?>
            <div class="box-body no-padding"><div class="table-responsive onlineexam-scroll"><table class="table table-condensed table-striped structured-question-table" style="margin-bottom:0"><thead><tr><th>Structured question</th><th>Paper / section</th><th>Type</th><th>Marks</th><th></th></tr></thead><tbody><?php foreach ((array) $authored_questions as $authored_question) { if (!isset($authored_question_json[(int) $authored_question['id']])) { continue; } $definition = $authored_question_json[(int) $authored_question['id']]['definition']; ?><tr><td><?php echo html_escape(mb_substr(trim(strip_tags($authored_question['question'])), 0, 100)); ?></td><td><?php echo html_escape($authored_question['paper_title'] . (!empty($authored_question['section_title']) ? ' / ' . $authored_question['section_title'] : '')); ?></td><td><?php echo html_escape(isset($native_question_types[$definition['presentation_type']]) ? $native_question_types[$definition['presentation_type']] : $definition['question_type']); ?></td><td><?php echo number_format((float) $authored_question['marks'], 2); ?></td><td><button type="button" class="btn btn-default btn-xs workflow-edit-authored" data-assignment-id="<?php echo (int) $authored_question['id']; ?>"><i class="fa fa-pencil"></i> Edit</button></td></tr><?php } ?></tbody></table></div></div>
            <?php } ?>
            <form method="post" action="<?php echo site_url('admin/onlineexam/workflowQuestionAuthor'); ?>" id="workflow-author-form">
                <?php echo $this->customlib->getCSRF(); ?>
                <input type="hidden" name="onlineexam_id" value="<?php echo (int) $exam->id; ?>">
                <input type="hidden" name="onlineexam_workflow_token" value="<?php echo html_escape($workflow_csrf); ?>">
                <input type="hidden" name="onlineexam_question_id" value="">
                <div class="box-body">
                    <p class="help-block">This creates the question in the existing bank, assigns it to this assessment, and preserves its structured answer definition when the revision is frozen.</p>
                    <div class="row">
                        <div class="col-md-3"><div class="form-group"><label>Paper *</label><select name="paper_id" id="author_paper_id" class="form-control" required><option value="">Select paper</option><?php foreach ($papers as $paper) { ?><option value="<?php echo (int) $paper['id']; ?>"><?php echo html_escape($paper['title']); ?></option><?php } ?></select></div></div>
                        <div class="col-md-3"><div class="form-group"><label>Section</label><select name="paper_section_id" id="author_section_id" class="form-control"><option value="">No named section</option></select></div></div>
                        <div class="col-md-6"><div class="form-group"><label>Question type *</label><select name="authored_question_type" id="author_question_type" class="form-control" required><option value="">Select type</option><?php foreach ($native_question_types as $value => $label) { ?><option value="<?php echo html_escape($value); ?>"><?php echo html_escape($label); ?></option><?php } ?></select></div></div>
                    </div>

                    <div class="workflow-passage-fields well well-sm" style="display:none">
                        <div class="row">
                            <div class="col-md-3"><div class="form-group"><label>Passage group key *</label><input type="text" name="passage_group_key" maxlength="64" class="form-control" placeholder="e.g. passage-1"><p class="help-block">Use the same key and text for every child question in this passage.</p></div></div>
                            <div class="col-md-3"><div class="form-group"><label>Passage title *</label><input type="text" name="passage_title" maxlength="191" class="form-control" placeholder="Read the passage below"></div></div>
                            <div class="col-md-3"><div class="form-group"><label>Child response *</label><select name="passage_response_type" id="passage_response_type" class="form-control"><option value="singlechoice">Single choice</option><option value="multichoice">Multiple choice</option><option value="true_false">True / False</option><option value="short_answer">Short answer</option><option value="numeric">Numeric</option><option value="long_answer">Long answer</option></select></div></div>
                        </div>
                        <div class="form-group"><label>Passage text *</label><textarea name="passage_text" rows="6" class="form-control" placeholder="Enter the source passage once; questions with the same group key display beneath it."></textarea></div>
                    </div>

                    <div class="form-group"><label>Question / prompt *</label><textarea name="question_text" rows="4" class="form-control" required></textarea></div>

                    <div class="workflow-author-fields workflow-choice-fields" style="display:none">
                        <div class="row">
                            <div class="col-md-8"><div class="form-group"><label>Options *</label><textarea name="answer_options" rows="6" class="form-control" placeholder="Enter one option per line"></textarea></div></div>
                            <div class="col-md-4"><div class="form-group"><label>Correct option number(s) *</label><input type="text" name="correct_choice" class="form-control" placeholder="1 or 1,3"><p class="help-block">For single choice enter one option number. For multiple choice separate all correct option numbers with commas.</p></div></div>
                        </div>
                    </div>
                    <div class="workflow-author-fields workflow-true-false-fields" style="display:none"><div class="form-group"><label>Correct answer *</label><select name="true_false_answer" class="form-control"><option value="">Select</option><option value="true">True</option><option value="false">False</option></select></div></div>
                    <div class="workflow-author-fields workflow-short-answer-fields" style="display:none"><div class="form-group"><label>Accepted answers *</label><textarea name="accepted_answers" rows="4" class="form-control" placeholder="Enter one accepted answer per line"></textarea><p class="help-block">Comparison ignores surrounding spaces and letter case.</p></div></div>
                    <div class="workflow-author-fields workflow-numeric-fields" style="display:none"><div class="row"><div class="col-md-6"><div class="form-group"><label>Correct numeric value *</label><input type="number" step="any" name="numeric_answer" class="form-control"></div></div><div class="col-md-6"><div class="form-group"><label>Accepted tolerance *</label><input type="number" step="any" min="0" name="numeric_tolerance" value="0" class="form-control"><p class="help-block">Example: answer 12.5 with tolerance 0.1 accepts 12.4 through 12.6.</p></div></div></div></div>
                    <div class="workflow-author-fields workflow-matching-fields" style="display:none"><div class="form-group"><label>Matching pairs *</label><textarea name="matching_pairs" rows="6" class="form-control" placeholder="Item A => Match 1&#10;Item B => Match 2"></textarea><p class="help-block">Enter one pair per line using <code>Left =&gt; Right</code>. Candidates receive a dropdown for each left item.</p></div></div>
                    <div class="workflow-author-fields workflow-ordering-fields" style="display:none"><div class="form-group"><label>Items in the correct order *</label><textarea name="ordering_items" rows="6" class="form-control" placeholder="First step&#10;Second step&#10;Third step"></textarea><p class="help-block">The candidate sees a shuffled choice list and selects the item for each position.</p></div></div>
                    <div class="workflow-author-fields workflow-manual-fields" style="display:none"><div class="alert alert-info">Theory responses are reviewed and marked by an authorized teacher after submission.</div></div>

                    <div class="row">
                        <div class="col-md-2"><div class="form-group"><label>Marks *</label><input type="number" name="marks" min="0.01" max="10000" step="0.01" value="1" class="form-control" required></div></div>
                        <div class="col-md-2"><div class="form-group"><label>Negative mark</label><input type="number" name="neg_marks" min="0" step="0.01" value="0" class="form-control" <?php echo empty($exam->is_neg_marking) ? 'disabled' : ''; ?>></div></div>
                        <div class="col-md-2"><div class="form-group"><label>Display order</label><input type="number" name="display_order" min="0" value="0" class="form-control"></div></div>
                        <div class="col-md-2"><div class="form-group"><label>&nbsp;</label><div><label><input type="hidden" name="is_compulsory" value="0"><input type="checkbox" name="is_compulsory" value="1" checked> Compulsory</label></div></div></div>
                        <div class="col-md-4"><div class="form-group"><label>Marking scheme / rubric</label><textarea name="marking_scheme" rows="3" class="form-control" placeholder="Required for Theory answer review"></textarea></div></div>
                    </div>
                </div>
                <div class="box-footer workflow-author-actions"><button type="button" class="btn btn-default workflow-author-reset" style="display:none"><i class="fa fa-times"></i> Cancel editing</button><button type="submit" class="btn btn-primary workflow-author-submit"><i class="fa fa-plus"></i> Create and assign question</button></div>
            </form>
        </div>
        <?php } ?>

        <?php if (!empty($papers)) { ?>
        <div class="box box-success">
            <div class="box-header with-border"><h3 class="box-title">Question bank — <?php echo html_escape($exam->subject_name . ', ' . $exam->class_name); ?></h3></div>
            <div class="box-body">
                <div class="alert alert-info">Select a paper and optional section, then assign or update questions below. The server restricts results to this assessment’s class and subject.</div>
                <form id="builder_question_filter" class="question-bank-filter-grid" role="search">
                    <div class="form-group"><label for="builder_paper_id">Paper *</label><select id="builder_paper_id" class="form-control"><option value="">Select paper</option><?php foreach ($papers as $paper) { ?><option value="<?php echo (int) $paper['id']; ?>"><?php echo html_escape($paper['title']); ?></option><?php } ?></select></div>
                    <div class="form-group"><label for="builder_section_id">Section</label><select id="builder_section_id" class="form-control"><option value="">No named section</option></select></div>
                    <div class="form-group"><label for="builder_keyword">Keyword</label><input type="search" id="builder_keyword" class="form-control" autocomplete="off"></div>
                    <div class="form-group"><label for="builder_question_type">Question type</label><select id="builder_question_type" class="form-control"><option value="">All types</option><?php foreach ($question_type as $value => $label) { ?><option value="<?php echo html_escape($value); ?>"><?php echo html_escape($label); ?></option><?php } ?></select></div>
                    <div class="form-group question-bank-search-group"><button type="submit" id="builder_search" class="btn btn-success btn-block"><i class="fa fa-search" aria-hidden="true"></i> Search</button></div>
                </form>
                <div id="builder_question_status" class="question-bank-status text-muted" role="status" aria-live="polite"></div>
                <div id="builder_question_results" aria-busy="false"></div>
                <div id="builder_question_pagination" class="clearfix"></div>
            </div>
        </div>
        <?php } ?>

        <div class="box box-warning">
            <div class="box-header with-border"><h3 class="box-title">Freeze and publish assessment</h3></div>
            <div class="box-body">
                <p>Publishing freezes an immutable copy of the questions, marks, paper rules, and result component. It does <strong>not</strong> publish the official report card.</p>
                <div id="builder_publish_errors" role="status" aria-live="polite" <?php echo empty($publish_errors) || !in_array($exam->lifecycle_status, array('draft', 'scheduled'), true) ? 'style="display:none"' : ''; ?>>
                    <?php if (!empty($publish_errors) && in_array($exam->lifecycle_status, array('draft', 'scheduled'), true)) { ?>
                        <ul class="text-danger"><?php foreach ($publish_errors as $error) { ?><li><?php echo html_escape($error); ?></li><?php } ?></ul>
                    <?php } ?>
                </div>
            </div>
            <div class="box-footer single-action-footer">
                <?php if ($exam->lifecycle_status === 'draft') { ?>
                    <?php if ($can_edit_assessment) { ?>
                    <form method="post" action="<?php echo site_url('admin/onlineexam/lifecycle/' . $exam->id); ?>" onsubmit="return confirm('Freeze this revision and release the assessment to candidates?');">
                        <?php echo $this->customlib->getCSRF(); ?><input type="hidden" name="workflow_action" value="publish"><button id="builder_publish_button" class="btn btn-warning" aria-disabled="<?php echo empty($publish_errors) ? 'false' : 'true'; ?>" <?php echo empty($publish_errors) ? '' : 'disabled'; ?>><i class="fa fa-lock"></i> Freeze and publish</button>
                    </form>
                    <?php } ?>
                <?php } elseif (in_array($exam->lifecycle_status, array('scheduled', 'published', 'in_progress', 'marking', 'completed'), true)) { ?>
                    <?php if ($this->rbac->hasPrivilege('online_examination', 'can_edit')) { ?>
                    <form method="post" action="<?php echo site_url('admin/onlineexam/lifecycle/' . $exam->id); ?>" onsubmit="return confirm('Open a new editable revision? The frozen revision and all earlier attempts will remain unchanged.');">
                        <?php echo $this->customlib->getCSRF(); ?><input type="hidden" name="workflow_action" value="new_revision"><button class="btn btn-default"><i class="fa fa-code-fork"></i> Create new revision</button>
                    </form>
                    <?php } ?>
                <?php } ?>
            </div>
        </div>
    </section>
</div>

<?php $this->load->view('admin/onlineexam/_setup_validation'); ?>
<script>
(function ($) {
    'use strict';
    var examId = <?php echo (int) $exam->id; ?>;
    var workflowToken = <?php echo json_encode($workflow_csrf); ?>;
    var paperSections = <?php echo json_encode($paper_sections_json); ?>;
    var paperTypes = <?php echo json_encode($paper_types_json); ?>;
    var negativeMarkingEnabled = <?php echo empty($exam->is_neg_marking) ? 'false' : 'true'; ?>;
    var canEditQuestions = <?php echo $can_edit_questions ? 'true' : 'false'; ?>;
    var questionRequest = null;
    var retainedInput = <?php echo json_encode(!empty($builder_input) ? $builder_input : null, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>;
    var authoredQuestions = <?php echo json_encode($authored_question_json, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>;
    var kindergartenLabels = <?php echo isset($kindergarten_label_map) ? json_encode($kindergarten_label_map) : '{}'; ?>;
    $('form[method="post"]').each(function () {
        if (!$(this).find('input[name="onlineexam_workflow_token"]').length) {
            $(this).append($('<input>', {type: 'hidden', name: 'onlineexam_workflow_token', value: workflowToken}));
        }
    });
    $('form[data-builder-form="paper"]').on('submit', function (event) {
        if (!window.onlineexamValidateWindow(this, 'starts_at', 'ends_at')) { event.preventDefault(); }
    });

    function updateSections() {
        var paperId = parseInt($('#builder_paper_id').val(), 10);
        var select = $('#builder_section_id').empty().append($('<option>', {value: '', text: 'No named section'}));
        $.each(paperSections[paperId] || [], function (_, section) {
            select.append($('<option>', {value: section.id, text: section.title}));
        });
        updateBankNegativeMarks();
    }

    function updateBankNegativeMarks() {
        var paperId = parseInt($('#builder_paper_id').val(), 10);
        var allowNegative = canEditQuestions && negativeMarkingEnabled && paperTypes[paperId] === 'objective';
        $('.question-neg-marks').prop('disabled', !allowNegative);
    }

    function renderPublishReadiness(readiness) {
        if (!readiness || typeof readiness.valid === 'undefined') { return; }
        var errors = $.isArray(readiness.errors) ? readiness.errors : [];
        var publishButton = $('#builder_publish_button');
        var errorContainer = $('#builder_publish_errors').empty();

        publishButton.prop('disabled', !readiness.valid)
            .attr('aria-disabled', readiness.valid ? 'false' : 'true');
        if (!errors.length) {
            errorContainer.hide();
            return;
        }

        var list = $('<ul>', {'class': 'text-danger'});
        $.each(errors, function (_, error) {
            list.append($('<li>').text(error));
        });
        errorContainer.append(list).show();
    }

    function beginQuestionMutation() {
        var publishButton = $('#builder_publish_button');
        var wasDisabled = publishButton.prop('disabled');
        publishButton.prop('disabled', true).attr('aria-disabled', 'true');
        $('.workflow-question-save, .workflow-question-remove').prop('disabled', true);
        return wasDisabled;
    }

    function finishQuestionMutation(readiness, restoreDisabledState) {
        if (readiness) {
            renderPublishReadiness(readiness);
        } else if (restoreDisabledState !== null) {
            $('#builder_publish_button').prop('disabled', restoreDisabledState)
                .attr('aria-disabled', restoreDisabledState ? 'true' : 'false');
        }
        $('.workflow-question-save, .workflow-question-remove').prop('disabled', false);
    }

    function updateAuthorSections() {
        var paperId = parseInt($('#author_paper_id').val(), 10);
        var select = $('#author_section_id').empty().append($('<option>', {value: '', text: 'No named section'}));
        $.each(paperSections[paperId] || [], function (_, section) {
            select.append($('<option>', {value: section.id, text: section.title}));
        });
        var allowNegative = negativeMarkingEnabled && paperTypes[paperId] === 'objective';
        $('#workflow-author-form input[name="neg_marks"]').prop('disabled', !allowNegative);
        if (!allowNegative) { $('#workflow-author-form input[name="neg_marks"]').val('0'); }
    }

    function updateAuthorFields() {
        var selectedType = $('#author_question_type').val();
        var isPassage = selectedType === 'grouped_passage';
        var responseType = isPassage ? $('#passage_response_type').val() : selectedType;
        $('.workflow-passage-fields').toggle(isPassage)
            .find('input, textarea, select').prop('required', isPassage);
        $('.workflow-author-fields').hide().find('input, textarea, select').prop('required', false);
        $('textarea[name="marking_scheme"]').prop('required', false);

        if (responseType === 'singlechoice' || responseType === 'multichoice') {
            $('.workflow-choice-fields').show().find('textarea, input').prop('required', true);
            $('input[name="correct_choice"]').attr('placeholder', responseType === 'singlechoice' ? 'e.g. 2' : 'e.g. 1,3');
        } else if (responseType === 'true_false') {
            $('.workflow-true-false-fields').show().find('select').prop('required', true);
        } else if (responseType === 'short_answer') {
            $('.workflow-short-answer-fields').show().find('textarea').prop('required', true);
        } else if (responseType === 'numeric') {
            $('.workflow-numeric-fields').show().find('input').prop('required', true);
        } else if (responseType === 'matching') {
            $('.workflow-matching-fields').show().find('textarea').prop('required', true);
        } else if (responseType === 'ordering') {
            $('.workflow-ordering-fields').show().find('textarea').prop('required', true);
        } else if (responseType === 'long_answer') {
            $('.workflow-manual-fields').show();
            $('textarea[name="marking_scheme"]').prop('required', true);
        }
    }

    function resetAuthorForm() {
        var form = $('#workflow-author-form')[0];
        if (!form) { return; }
        form.reset();
        $('#workflow-author-form input[name="onlineexam_question_id"]').val('');
        $('#workflow-author-title').text('Create a structured question');
        $('.workflow-author-submit').html('<i class="fa fa-plus"></i> Create and assign question');
        $('.workflow-author-reset').hide();
        updateAuthorSections();
        updateAuthorFields();
    }

    function editAuthoredQuestion(assignmentId) {
        var record = authoredQuestions[assignmentId], form = $('#workflow-author-form');
        if (!record || !record.definition) { errorMsg('The structured question definition could not be loaded.'); return; }
        resetAuthorForm();
        var definition = record.definition, options = definition.options || {}, correct = definition.correct_answer;
        form.find('input[name="onlineexam_question_id"]').val(record.id);
        form.find('[name="paper_id"]').val(record.paper_id);
        updateAuthorSections();
        form.find('[name="paper_section_id"]').val(record.paper_section_id || '');
        var presentationType = definition.presentation_type === 'grouped_passage' ? 'grouped_passage' : definition.question_type;
        form.find('[name="authored_question_type"]').val(presentationType);
        form.find('[name="passage_response_type"]').val(definition.question_type);
        updateAuthorFields();
        form.find('[name="question_text"]').val(record.question || '');
        form.find('[name="marks"]').val(record.marks);
        form.find('[name="neg_marks"]').val(record.neg_marks || 0);
        form.find('[name="display_order"]').val(record.display_order || 0);
        form.find('[name="is_compulsory"]').prop('checked', parseInt(record.is_compulsory || 0, 10) === 1);
        form.find('[name="marking_scheme"]').val(record.marking_scheme || '');

        if (definition.passage) {
            form.find('[name="passage_group_key"]').val(definition.passage.group_key || '');
            form.find('[name="passage_title"]').val(definition.passage.title || '');
            form.find('[name="passage_text"]').val(definition.passage.text || '');
        }
        if (definition.question_type === 'singlechoice' || definition.question_type === 'multichoice') {
            var optionKeys = Object.keys(options), optionLabels = [];
            $.each(optionKeys, function (_, key) { optionLabels.push(options[key]); });
            form.find('[name="answer_options"]').val(optionLabels.join('\n'));
            var correctKeys = $.isArray(correct) ? correct : [correct], correctNumbers = [];
            $.each(correctKeys, function (_, key) { var index = $.inArray(key, optionKeys); if (index >= 0) { correctNumbers.push(index + 1); } });
            form.find('[name="correct_choice"]').val(correctNumbers.join(','));
        } else if (definition.question_type === 'true_false') {
            form.find('[name="true_false_answer"]').val(correct);
        } else if (definition.question_type === 'short_answer') {
            form.find('[name="accepted_answers"]').val(($.isArray(correct) ? correct : [correct]).join('\n'));
        } else if (definition.question_type === 'numeric') {
            form.find('[name="numeric_answer"]').val(correct && correct.value !== undefined ? correct.value : '');
            form.find('[name="numeric_tolerance"]').val(correct && correct.tolerance !== undefined ? correct.tolerance : 0);
        } else if (definition.question_type === 'matching') {
            var rightLabels = {}, pairs = [];
            $.each(options.matching_right || [], function (_, item) { rightLabels[item.id] = item.label; });
            $.each(options.matching_left || [], function (_, item) { pairs.push(item.label + ' => ' + (rightLabels[correct[item.id]] || '')); });
            form.find('[name="matching_pairs"]').val(pairs.join('\n'));
        } else if (definition.question_type === 'ordering') {
            var itemLabels = {}, ordered = [];
            $.each(options.dynamic || [], function (_, item) { itemLabels[item.id] = item.option; });
            $.each($.isArray(correct) ? correct : [], function (_, itemId) { if (itemLabels[itemId] !== undefined) { ordered.push(itemLabels[itemId]); } });
            form.find('[name="ordering_items"]').val(ordered.join('\n'));
        }
        $('#workflow-author-title').text('Edit structured question');
        $('.workflow-author-submit').html('<i class="fa fa-save"></i> Save structured question');
        $('.workflow-author-reset').show();
        $('html, body').animate({scrollTop: $('#author-question').offset().top - 20}, 250);
    }

    function loadQuestions(page) {
        if (questionRequest) {
            questionRequest.abort();
        }
        var searchButton = $('#builder_search');
        var request = questionRequest = $.ajax({
            type: 'POST',
            url: '<?php echo site_url('admin/onlineexam/searchQuestionByExamID'); ?>',
            dataType: 'json',
            data: {page: page || 1, exam_id: examId, search: '', keyword: $('#builder_keyword').val(), question_type: $('#builder_question_type').val(), class_id: '', section_id: '', onlineexam_workflow_token: workflowToken},
            beforeSend: function () {
                searchButton.prop('disabled', true);
                $('#builder_question_results').attr('aria-busy', 'true');
                $('#builder_question_status').text('Loading questions…');
            },
            success: function (response) {
                $('#builder_question_results').html(response.content || '<div class="alert alert-warning">No matching questions.</div>');
                $('#builder_question_pagination').html(response.navigation || '');
                $('#builder_question_status').text(response.content ? 'Question list updated.' : 'No matching questions found.');
                updateBankNegativeMarks();
            },
            error: function (xhr, status) {
                if (status === 'abort') { return; }
                var message = xhr.responseJSON && xhr.responseJSON.message
                    ? xhr.responseJSON.message
                    : 'The question bank could not be loaded.';
                $('#builder_question_status').text(message);
                errorMsg(message);
            },
            complete: function () {
                if (questionRequest === request) {
                    questionRequest = null;
                    searchButton.prop('disabled', false);
                    $('#builder_question_results').attr('aria-busy', 'false');
                }
            }
        });
    }

    $('#builder_paper_id').on('change', updateSections);
    $(document).on('shown.bs.collapse hidden.bs.collapse', '[id^="paper-edit-"]', function (event) {
        $('[aria-controls="' + this.id + '"]').attr('aria-expanded', event.type === 'shown');
    });
    $('#author_paper_id').on('change', updateAuthorSections);
    $('#author_question_type, #passage_response_type').on('change', updateAuthorFields);
    $(document).on('click', '.workflow-edit-authored', function () { editAuthoredQuestion(parseInt($(this).data('assignment-id'), 10)); });
    $(document).on('click', '.workflow-author-reset', resetAuthorForm);
    $('#builder_question_filter').on('submit', function (event) { event.preventDefault(); loadQuestions(1); });
    $(document).on('click', '#builder_question_pagination li.activee', function (event) { event.preventDefault(); loadQuestions($(this).attr('p')); });

    $(document).on('click', '.workflow-question-save', function () {
        var button = $(this);
        var row = button.closest('.workflow-question-row');
        var paperId = $('#builder_paper_id').val();
        if (!paperId) { errorMsg('Select a paper first.'); return; }
        button.button('loading');
        var previousPublishDisabled = beginQuestionMutation();
        var publishReadiness = null;
        var restorePublishState = false;
        $.post('<?php echo site_url('admin/onlineexam/workflowQuestionSave'); ?>', {
            onlineexam_id: examId,
            paper_id: paperId,
            paper_section_id: $('#builder_section_id').val(),
            question_id: button.data('question-id'),
            marks: row.find('.question-marks').val(),
            neg_marks: row.find('.question-neg-marks').val() || 0,
            display_order: row.find('.question-order').val(),
            is_compulsory: row.find('.question-compulsory').is(':checked') ? 1 : 0,
            marking_scheme: row.find('.question-scheme').val()
            ,onlineexam_workflow_token: workflowToken
        }, null, 'json').done(function (response) {
            publishReadiness = response.publish_readiness || null;
            if (response.status) {
                successMsg(response.message);
                loadQuestions(1);
            } else {
                restorePublishState = true;
                errorMsg(response.message);
            }
        }).fail(function () {
            errorMsg('Question assignment failed. Refresh the page before publishing to confirm the saved question total.');
        }).always(function () {
            button.button('reset');
            finishQuestionMutation(publishReadiness, restorePublishState ? previousPublishDisabled : null);
        });
    });

    $(document).on('click', '.workflow-question-remove', function () {
        if (!confirm('Remove this question from the draft assessment?')) { return; }
        var button = $(this);
        var previousPublishDisabled = beginQuestionMutation();
        var publishReadiness = null;
        var restorePublishState = false;
        $.post('<?php echo site_url('admin/onlineexam/workflowQuestionDelete'); ?>', {onlineexam_id: examId, onlineexam_question_id: button.data('assignment-id'), onlineexam_workflow_token: workflowToken}, null, 'json').done(function (response) {
            publishReadiness = response.publish_readiness || null;
            if (response.status) {
                successMsg(response.message);
                loadQuestions(1);
            } else {
                restorePublishState = true;
                errorMsg(response.message);
            }
        }).fail(function () {
            errorMsg('Question removal failed. Refresh the page before publishing to confirm the saved question total.');
        }).always(function () {
            finishQuestionMutation(publishReadiness, restorePublishState ? previousPublishDisabled : null);
        });
    });

    $('#british_profile_mode').on('change', function () { $('#british_thresholds').toggle($(this).val() === 'thresholds'); });
    function updateKindergartenSections() {
        var paperId = parseInt($('#kg_paper_id').val(), 10);
        var select = $('#kg_section_id').empty().append($('<option>', {value: '', text: 'Whole paper'}));
        $.each(paperSections[paperId] || [], function (_, section) { select.append($('<option>', {value: section.id, text: section.title})); });
    }
    function updateKindergartenThresholds() {
        var container = $('#kg_threshold_fields').empty();
        if ($('#kg_conversion_mode').val() !== 'thresholds') { container.hide(); return; }
        var labels = kindergartenLabels[parseInt($('#kg_concept_id').val(), 10)] || [];
        var width = labels.length ? Math.max(2, Math.floor(12 / labels.length)) : 3;
        $.each(labels, function (index, label) {
            var minimum = index === 0 ? 0 : (100 / labels.length * index).toFixed(2);
            var maximum = index === labels.length - 1 ? 100 : (100 / labels.length * (index + 1) - 0.01).toFixed(2);
            container.append('<div class="col-md-' + width + '"><div class="well well-sm"><strong>' + $('<div>').text(label).html() + '</strong><div class="row"><div class="col-xs-6"><label>Min</label><input type="number" class="form-control" name="label_min[]" min="0" max="100" step="0.01" value="' + minimum + '"></div><div class="col-xs-6"><label>Max</label><input type="number" class="form-control" name="label_max[]" min="0" max="100" step="0.01" value="' + maximum + '"></div></div></div></div>');
        });
        container.show();
    }
    $('#kg_paper_id').on('change', updateKindergartenSections);
    $('#kg_concept_id, #kg_conversion_mode').on('change', updateKindergartenThresholds);
    updateKindergartenSections();
    updateKindergartenThresholds();
    updateAuthorSections();
    updateAuthorFields();

    if (retainedInput && retainedInput.values && parseInt(retainedInput.values.onlineexam_id, 10) === examId) {
        var restored = retainedInput.values;
        var $restoreForm = retainedInput.form === 'author' ? $('#workflow-author-form')
            : $('form[data-builder-form="' + retainedInput.form + '"]').filter(function () {
                return parseInt($(this).find('[name="paper_id"]').val() || 0, 10) === parseInt(restored.paper_id || 0, 10);
            }).first();
        function restoreValues() {
            $restoreForm.find('input, textarea, select').each(function () {
                if (this.type === 'hidden' && this.name !== 'onlineexam_question_id') { return; }
                if (this.type === 'checkbox') {
                    $(this).prop('checked', String(restored[this.name] || '') === this.value);
                } else if (Object.prototype.hasOwnProperty.call(restored, this.name)) {
                    $(this).val(restored[this.name]);
                }
            });
        }
        restoreValues();
        if (retainedInput.form === 'author') {
            updateAuthorSections();
            restoreValues();
            updateAuthorFields();
            if (parseInt(restored.onlineexam_question_id || 0, 10) > 0) {
                $('#workflow-author-title').text('Edit structured question');
                $('.workflow-author-submit').html('<i class="fa fa-save"></i> Save structured question');
                $('.workflow-author-reset').show();
            }
        }
        $restoreForm.closest('.collapse').addClass('in');
        $restoreForm.find('input:visible, textarea:visible, select:visible').first().focus();
    }

    <?php if (!empty($papers)) { ?>
    $('#builder_paper_id').val('<?php echo (int) $papers[0]['id']; ?>');
    updateSections();
    loadQuestions(1);
    <?php } ?>
})(jQuery);
</script>
