<?php
$is_edit = !empty($exam);
$selected_sections = (array) ($this->input->post('section_ids') !== null ? $this->input->post('section_ids') : ($is_edit ? $exam->section_ids : array()));
$selected_adapter = set_value('result_adapter', $is_edit ? $exam->result_adapter : 'unlinked_practice');
$selected_component = set_value('target_component', $is_edit ? $exam->target_component : '');
$duration_minutes = 60;
if ($is_edit && !empty($exam->duration)) {
    $duration_parts = array_map('intval', explode(':', $exam->duration));
    $duration_minutes = ($duration_parts[0] * 60) + $duration_parts[1];
}
$format_datetime = function ($value) {
    return $value ? $this->customlib->dateyyyymmddToDateTimeformat($value, false) : '';
};
?>
<div class="content-wrapper">
    <section class="content-header">
        <h1>Nigerian Online Assessment <small><?php echo $is_edit ? 'Edit academic context' : 'Create assessment'; ?></small></h1>
    </section>
    <section class="content">
        <?php if (!empty($workflow_error)) { ?>
            <div class="alert alert-danger"><?php echo $workflow_error; ?></div>
        <?php } ?>
        <?php echo validation_errors('<div class="alert alert-danger">', '</div>'); ?>

        <form method="post" action="<?php echo site_url('admin/onlineexam/workflow/' . ($is_edit ? $exam->id : 0)); ?>" id="academic-assessment-form">
            <?php echo $this->customlib->getCSRF(); ?>
            <input type="hidden" name="onlineexam_workflow_token" value="<?php echo html_escape($workflow_csrf); ?>">
            <div class="box box-primary">
                <div class="box-header with-border">
                    <h3 class="box-title">1. Academic context</h3>
                    <div class="box-tools">
                        <span class="label label-primary">Workflow v2</span>
                        <?php if ($is_edit) { ?><span class="label label-default"><?php echo html_escape(ucwords($exam->lifecycle_status)); ?></span><?php } ?>
                    </div>
                </div>
                <div class="box-body">
                    <div class="row">
                        <div class="col-md-8">
                            <div class="form-group">
                                <label>Assessment title <small class="req">*</small></label>
                                <input type="text" name="exam" class="form-control" value="<?php echo html_escape(set_value('exam', $is_edit ? $exam->exam : '')); ?>" required>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="form-group">
                                <label>Purpose <small class="req">*</small></label>
                                <select name="purpose" id="purpose" class="form-control" required>
                                    <?php foreach ($purposes as $value => $label) { ?>
                                        <option value="<?php echo $value; ?>" <?php echo set_select('purpose', $value, $is_edit && $exam->purpose === $value); ?>><?php echo html_escape($label); ?></option>
                                    <?php } ?>
                                </select>
                            </div>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-3">
                            <div class="form-group">
                                <label>Academic session <small class="req">*</small></label>
                                <select id="academic_session_id" name="session_id" class="form-control" required>
                                    <?php foreach ($sessionList as $session) { ?>
                                        <option value="<?php echo $session['id']; ?>" <?php echo set_select('session_id', $session['id'], (int) ($is_edit ? $exam->session_id : $current_session_id) === (int) $session['id']); ?>><?php echo html_escape($session['session']); ?></option>
                                    <?php } ?>
                                </select>
                            </div>
                        </div>
                        <div class="col-md-2">
                            <div class="form-group">
                                <label>Term <small class="req">*</small></label>
                                <select name="term" class="form-control" required>
                                    <option value="1st" <?php echo set_select('term', '1st', $is_edit && $exam->term === '1st'); ?>>1st Term</option>
                                    <option value="2nd" <?php echo set_select('term', '2nd', $is_edit && $exam->term === '2nd'); ?>>2nd Term</option>
                                    <option value="3rd" <?php echo set_select('term', '3rd', $is_edit && $exam->term === '3rd'); ?>>3rd Term</option>
                                </select>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="form-group">
                                <label>Class <small class="req">*</small></label>
                                <select name="class_id" id="academic_class_id" class="form-control" required>
                                    <option value="">Select</option>
                                    <?php foreach ($classList as $class) { ?>
                                        <option value="<?php echo $class['id']; ?>" <?php echo set_select('class_id', $class['id'], $is_edit && (int) $exam->class_id === (int) $class['id']); ?>><?php echo html_escape($class['class']); ?></option>
                                    <?php } ?>
                                </select>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="form-group">
                                <label>Subject <small class="req">*</small></label>
                                <select name="subject_id" id="academic_subject_id" class="form-control" required>
                                    <option value="">Select</option>
                                    <?php foreach ($subjectList as $subject) { ?>
                                        <option value="<?php echo $subject['id']; ?>" <?php echo set_select('subject_id', $subject['id'], $is_edit && (int) $exam->subject_id === (int) $subject['id']); ?>><?php echo html_escape($subject['name'] . ($subject['code'] ? ' (' . $subject['code'] . ')' : '')); ?></option>
                                    <?php } ?>
                                </select>
                            </div>
                        </div>
                    </div>
                    <div class="form-group">
                        <label>Class arms / sections <small class="req">*</small></label>
                        <div id="academic_sections" class="well well-sm" style="margin-bottom:0">
                            <?php if (empty($sections)) { ?><span class="text-muted">Select a class to load its arms.</span><?php } ?>
                            <?php foreach ($sections as $section) { ?>
                                <label class="checkbox-inline" style="margin-left:0;margin-right:18px">
                                    <input type="checkbox" name="section_ids[]" value="<?php echo $section['id']; ?>" <?php echo in_array((string) $section['id'], array_map('strval', $selected_sections), true) ? 'checked' : ''; ?>> <?php echo html_escape($section['section']); ?>
                                </label>
                            <?php } ?>
                        </div>
                        <p class="help-block">The candidate roster will be built from these arms in the selected session.</p>
                    </div>
                </div>
            </div>

            <div class="box box-info">
                <div class="box-header with-border"><h3 class="box-title">2. Existing result destination</h3></div>
                <div class="box-body">
                    <div id="result_configuration_message" class="alert alert-info" style="display:none"></div>
                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group">
                                <label>Destination <small class="req">*</small></label>
                                <select name="result_adapter" id="result_adapter" class="form-control" required>
                                    <option value="unlinked_practice" <?php echo $selected_adapter === 'unlinked_practice' ? 'selected' : ''; ?>>Unlinked practice / internal online result only</option>
                                    <?php if ($academic_configuration && $academic_configuration['adapter'] !== 'unlinked_practice') { ?>
                                        <option value="<?php echo html_escape($academic_configuration['adapter']); ?>" <?php echo $selected_adapter === $academic_configuration['adapter'] ? 'selected' : ''; ?>><?php echo html_escape(ucwords(str_replace('_', ' ', $academic_configuration['adapter']))); ?></option>
                                    <?php } ?>
                                </select>
                                <p class="help-block">Posting a score here does not publish the report card.</p>
                            </div>
                        </div>
                        <div class="col-md-6" id="target_component_group" style="<?php echo $selected_adapter === 'standard_component' ? '' : 'display:none'; ?>">
                            <div class="form-group">
                                <label>CA / Examination component <small class="req">*</small></label>
                                <select name="target_component" id="target_component" class="form-control">
                                    <option value="">Select</option>
                                    <?php if ($academic_configuration) { foreach ($academic_configuration['components'] as $component) { ?>
                                        <option value="<?php echo html_escape($component['value']); ?>" <?php echo $selected_component === $component['value'] ? 'selected' : ''; ?>><?php echo html_escape($component['label']); ?> — max <?php echo number_format($component['maximum'], 2); ?></option>
                                    <?php }} ?>
                                </select>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="box box-default">
                <div class="box-header with-border"><h3 class="box-title">3. Delivery window and rules</h3></div>
                <div class="box-body">
                    <div class="row">
                        <div class="col-md-3"><div class="form-group"><label>Opens <small class="req">*</small></label><input type="text" name="exam_from" class="form-control datetime_twelve_hour" value="<?php echo html_escape(set_value('exam_from', $is_edit ? $format_datetime($exam->exam_from) : '')); ?>" required></div></div>
                        <div class="col-md-3"><div class="form-group"><label>Closes <small class="req">*</small></label><input type="text" name="exam_to" class="form-control datetime_twelve_hour" value="<?php echo html_escape(set_value('exam_to', $is_edit ? $format_datetime($exam->exam_to) : '')); ?>" required></div></div>
                        <div class="col-md-2"><div class="form-group"><label>Duration (minutes) <small class="req">*</small></label><input type="number" name="duration_minutes" min="1" max="1439" class="form-control" value="<?php echo html_escape(set_value('duration_minutes', $duration_minutes)); ?>" required></div></div>
                        <div class="col-md-2"><div class="form-group"><label>Pass percentage <small class="req">*</small></label><input type="number" name="passing_percentage" min="0" max="100" step="0.01" class="form-control" value="<?php echo html_escape(set_value('passing_percentage', $is_edit ? $exam->passing_percentage : 40)); ?>" required></div></div>
                        <div class="col-md-2"><div class="form-group"><label>Practice attempts</label><input type="number" name="attempt" min="1" class="form-control" value="<?php echo html_escape(set_value('attempt', $is_edit ? $exam->attempt : 1)); ?>"><p class="help-block">Result-bearing work is always one official attempt.</p></div></div>
                    </div>
                    <div class="form-group">
                        <label>Description / general instructions</label>
                        <textarea name="description" class="form-control" rows="5"><?php echo html_escape(set_value('description', $is_edit ? $exam->description : '')); ?></textarea>
                    </div>
                    <div class="form-group">
                        <label class="checkbox-inline"><input type="checkbox" name="is_random_question" value="1" <?php echo set_checkbox('is_random_question', '1', $is_edit && $exam->is_random_question); ?>> Randomize questions within their paper/section</label>
                        <label class="checkbox-inline"><input type="checkbox" name="is_neg_marking" value="1" <?php echo set_checkbox('is_neg_marking', '1', $is_edit && $exam->is_neg_marking); ?>> Enable negative marking for attempted wrong objective answers</label>
                        <label class="checkbox-inline"><input type="checkbox" name="is_marks_display" value="1" <?php echo set_checkbox('is_marks_display', '1', $is_edit && $exam->is_marks_display); ?>> Display marks during review</label>
                    </div>
                </div>
                <div class="box-footer">
                    <a href="<?php echo site_url('admin/onlineexam'); ?>" class="btn btn-default">Cancel</a>
                    <button type="submit" class="btn btn-primary pull-right"><i class="fa fa-save"></i> Save and build papers</button>
                </div>
            </div>
        </form>
    </section>
</div>

<script>
(function ($) {
    'use strict';
    var selectedSections = <?php echo json_encode(array_values(array_map('intval', $selected_sections))); ?>;
    var selectedAdapter = <?php echo json_encode($selected_adapter); ?>;
    var selectedComponent = <?php echo json_encode($selected_component); ?>;

    function renderConfiguration(payload) {
        var sectionsHtml = '';
        $.each(payload.sections || [], function (_, section) {
            var checked = $.inArray(parseInt(section.id, 10), selectedSections) !== -1 ? ' checked' : '';
            sectionsHtml += '<label class="checkbox-inline" style="margin-left:0;margin-right:18px"><input type="checkbox" name="section_ids[]" value="' + section.id + '"' + checked + '> ' + $('<div>').text(section.section).html() + '</label>';
        });
        $('#academic_sections').html(sectionsHtml || '<span class="text-danger">No active class arms are configured for this class.</span>');

        var config = payload.configuration || {};
        var adapter = $('#result_adapter');
        adapter.find('option:not([value="unlinked_practice"])').remove();
        if (config.adapter && config.adapter !== 'unlinked_practice') {
            var adapterLabel = config.adapter.replace(/_/g, ' ').replace(/\b\w/g, function (letter) { return letter.toUpperCase(); });
            adapter.append($('<option>', {value: config.adapter, text: adapterLabel}));
            if (selectedAdapter === config.adapter) {
                adapter.val(selectedAdapter);
            }
        }
        var component = $('#target_component').empty().append($('<option>', {value: '', text: 'Select'}));
        $.each(config.components || [], function (_, item) {
            component.append($('<option>', {value: item.value, text: item.label + ' — max ' + parseFloat(item.maximum).toFixed(2)}));
        });
        component.val(selectedComponent);
        var message = config.message || (config.adapter === 'standard_component' ? 'The CA names and maximum scores above come from the class CA Setting.' : '');
        $('#result_configuration_message').toggle(!!message).text(message);
        toggleComponent();
    }

    function loadConfiguration() {
        var classId = $('#academic_class_id').val();
        if (!classId) {
            return;
        }
        $.getJSON('<?php echo site_url('admin/onlineexam/academicconfiguration'); ?>', {
            class_id: classId,
            subject_id: $('#academic_subject_id').val(),
            session_id: $('#academic_session_id').val()
        }).done(function (response) {
            if (response.status) {
                renderConfiguration(response);
                selectedSections = [];
                selectedAdapter = 'unlinked_practice';
                selectedComponent = '';
            }
        });
    }

    function toggleComponent() {
        $('#target_component_group').toggle($('#result_adapter').val() === 'standard_component');
    }

    $('#academic_class_id, #academic_subject_id, #academic_session_id').on('change', loadConfiguration);
    $('#result_adapter').on('change', toggleComponent);
    toggleComponent();
})(jQuery);
</script>
