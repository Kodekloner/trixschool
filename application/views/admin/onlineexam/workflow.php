<?php
$is_edit = !empty($exam);
$form_value = function ($name, $default = '') { return set_value($name, $default, false); };
$is_post = $this->input->server('REQUEST_METHOD') === 'POST';
$selected_sections = (array) ($is_post ? $this->input->post('section_ids') : ($is_edit ? $exam->section_ids : array()));
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
<?php $this->load->view('admin/onlineexam/_assessment_styles'); ?>
<div class="content-wrapper onlineexam-ui onlineexam-assessment-form-page">
    <section class="content-header">
        <h1>Online Assessment <small><?php echo $is_edit ? 'Edit academic context' : 'Create assessment'; ?></small></h1>
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
                <div class="box-header with-border assessment-box-header">
                    <h3 class="box-title">1. Academic context</h3>
                    <div class="box-tools">
                        <?php if ($is_edit) { ?><span class="label label-default"><?php echo html_escape(ucwords($exam->lifecycle_status)); ?></span><?php } ?>
                    </div>
                </div>
                <div class="box-body">
                    <div class="row">
                        <div class="col-md-8">
                            <div class="form-group">
                                <label for="assessment_title">Assessment title <small class="req">*</small></label>
                                <input type="text" id="assessment_title" name="exam" class="form-control" value="<?php echo html_escape($form_value('exam', $is_edit ? $exam->exam : '')); ?>" required>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="form-group">
                                <label for="purpose">Purpose <small class="req">*</small></label>
                                <select name="purpose" id="purpose" class="form-control" required>
                                    <option value="">Select purpose</option>
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
                                <label for="academic_session_id">Academic session <small class="req">*</small></label>
                                <select id="academic_session_id" name="session_id" class="form-control" required>
                                    <?php foreach ($sessionList as $session) { ?>
                                        <option value="<?php echo $session['id']; ?>" <?php echo set_select('session_id', $session['id'], (int) ($is_edit ? $exam->session_id : $current_session_id) === (int) $session['id']); ?>><?php echo html_escape($session['session']); ?></option>
                                    <?php } ?>
                                </select>
                            </div>
                        </div>
                        <div class="col-md-2">
                            <div class="form-group">
                                <label for="academic_term">Term <small class="req">*</small></label>
                                <select name="term" id="academic_term" class="form-control" required>
                                    <option value="1st" <?php echo set_select('term', '1st', $is_edit && $exam->term === '1st'); ?>>1st Term</option>
                                    <option value="2nd" <?php echo set_select('term', '2nd', $is_edit && $exam->term === '2nd'); ?>>2nd Term</option>
                                    <option value="3rd" <?php echo set_select('term', '3rd', $is_edit && $exam->term === '3rd'); ?>>3rd Term</option>
                                </select>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="form-group">
                                <label for="academic_class_id">Class <small class="req">*</small></label>
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
                                <label for="academic_subject_id">Subject <small class="req">*</small></label>
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
                        <label id="academic_sections_label">Class arms / sections <small class="req">*</small></label>
                        <div id="academic_sections" class="well well-sm assessment-rule-options" style="margin-bottom:0" role="group" aria-labelledby="academic_sections_label" aria-live="polite" aria-busy="false">
                            <?php if (empty($sections)) { ?><span class="text-muted">Select a class to load its arms.</span><?php } ?>
                            <?php foreach ($sections as $section) { ?>
                                <label class="checkbox-inline">
                                    <input type="checkbox" name="section_ids[]" value="<?php echo $section['id']; ?>" <?php echo in_array((string) $section['id'], array_map('strval', $selected_sections), true) ? 'checked' : ''; ?>> <?php echo html_escape($section['section']); ?>
                                </label>
                            <?php } ?>
                        </div>
                        <p class="help-block">The candidate roster will be built from these arms in the selected session.</p>
                    </div>
                </div>
            </div>

            <div class="box box-info">
                <div class="box-header with-border"><h3 class="box-title">2. Assessment component</h3></div>
                <div class="box-body">
                    <div id="result_configuration_message" class="alert alert-info" role="status" aria-live="polite" style="<?php echo !empty($academic_configuration['message']) ? '' : 'display:none'; ?>"><?php echo !empty($academic_configuration['message']) ? html_escape($academic_configuration['message']) : ''; ?></div>
                    <div class="row">
                        <div class="col-md-6" id="target_component_group" style="<?php echo $academic_configuration && !empty($academic_configuration['valid']) && !empty($academic_configuration['components']) ? '' : 'display:none'; ?>">
                            <div class="form-group">
                                <label for="target_component">Result component <small class="req">*</small></label>
                                <select name="target_component" id="target_component" class="form-control">
                                    <option value="">Select</option>
                                    <?php if ($academic_configuration && !empty($academic_configuration['valid'])) { foreach ($academic_configuration['components'] as $component) { ?>
                                        <option value="<?php echo html_escape($component['value']); ?>" <?php echo $selected_component === $component['value'] ? 'selected' : ''; ?>><?php echo html_escape($component['label']); ?> — max <?php echo number_format($component['maximum'], 2); ?></option>
                                    <?php }} ?>
                                </select>
                                <p class="help-block">The available component is selected from this class's existing assessment setting.</p>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <p class="help-block">Completed scores are sent automatically to the result area configured for the selected purpose and class. Official report-card publication remains separate.</p>
                        </div>
                    </div>
                </div>
            </div>

            <div class="box box-default">
                <div class="box-header with-border"><h3 class="box-title">3. Delivery window and rules</h3></div>
                <div class="box-body">
                    <div class="row">
                        <div class="col-md-3"><div class="form-group"><label for="assessment_opens">Opens <small class="req">*</small></label><input type="text" id="assessment_opens" name="exam_from" class="form-control datetime_twelve_hour" value="<?php echo html_escape($form_value('exam_from', $is_edit ? $format_datetime($exam->exam_from) : '')); ?>" required></div></div>
                        <div class="col-md-3"><div class="form-group"><label for="assessment_closes">Closes <small class="req">*</small></label><input type="text" id="assessment_closes" name="exam_to" class="form-control datetime_twelve_hour" value="<?php echo html_escape($form_value('exam_to', $is_edit ? $format_datetime($exam->exam_to) : '')); ?>" required></div></div>
                        <div class="col-md-3"><div class="form-group"><label for="assessment_duration">Duration (minutes) <small class="req">*</small></label><input type="number" id="assessment_duration" name="duration_minutes" min="1" max="1439" class="form-control" value="<?php echo html_escape($form_value('duration_minutes', $duration_minutes)); ?>" required></div></div>
                        <div class="col-md-3"><div class="form-group"><label for="assessment_pass_mark">Pass percentage <small class="req">*</small></label><input type="number" id="assessment_pass_mark" name="passing_percentage" min="0" max="100" step="0.01" class="form-control" value="<?php echo html_escape($form_value('passing_percentage', $is_edit ? $exam->passing_percentage : 40)); ?>" required></div></div>
                    </div>
                    <div class="form-group">
                        <label for="assessment_description">Description / general instructions</label>
                        <textarea id="assessment_description" name="description" class="form-control" rows="5"><?php echo html_escape($form_value('description', $is_edit ? $exam->description : '')); ?></textarea>
                    </div>
                    <div class="form-group assessment-rule-options">
                        <label class="checkbox-inline"><input type="checkbox" name="is_random_question" value="1" <?php echo ($is_post ? $this->input->post('is_random_question') : ($is_edit && $exam->is_random_question)) ? 'checked' : ''; ?>> Randomize questions within their paper/section</label>
                        <label class="checkbox-inline"><input type="checkbox" name="is_neg_marking" value="1" <?php echo ($is_post ? $this->input->post('is_neg_marking') : ($is_edit && $exam->is_neg_marking)) ? 'checked' : ''; ?>> Enable negative marking for attempted wrong objective answers</label>
                        <label class="checkbox-inline"><input type="checkbox" name="is_marks_display" value="1" <?php echo ($is_post ? $this->input->post('is_marks_display') : ($is_edit && $exam->is_marks_display)) ? 'checked' : ''; ?>> Display marks during review</label>
                    </div>
                </div>
                <div class="box-footer assessment-form-actions">
                    <a href="<?php echo site_url('admin/onlineexam'); ?>" class="btn btn-default">Cancel</a>
                    <button type="submit" class="btn btn-primary"><i class="fa fa-save"></i> Save and build papers</button>
                </div>
            </div>
        </form>
    </section>
</div>

<?php $this->load->view('admin/onlineexam/_setup_validation'); ?>
<script>
(function ($) {
    'use strict';
    var selectedSections = <?php echo json_encode(array_values(array_map('intval', $selected_sections))); ?>;
    var selectedComponent = <?php echo json_encode($selected_component); ?>;
    var configurationRequest = null;

    function clearConfiguration(message) {
        $('#target_component').empty().append($('<option>', {value: '', text: 'Select'})).prop('required', false);
        $('#target_component_group').hide();
        $('#result_configuration_message').toggle(!!message).text(message || '');
        $('#academic_sections').attr('aria-busy', 'false');
    }

    function renderConfiguration(payload) {
        var subjectId = $('#academic_subject_id').val();
        var $subjects = $('#academic_subject_id').empty().append($('<option>', {value: '', text: 'Select subject'}));
        $.each(payload.subjects || [], function (_, subject) {
            $subjects.append($('<option>', {value: subject.id, text: subject.name + (subject.code ? ' (' + subject.code + ')' : '')}));
        });
        $subjects.val(subjectId);
        var sectionsHtml = '';
        $.each(payload.sections || [], function (_, section) {
            var checked = $.inArray(parseInt(section.id, 10), selectedSections) !== -1 ? ' checked' : '';
            sectionsHtml += '<label class="checkbox-inline"><input type="checkbox" name="section_ids[]" value="' + section.id + '"' + checked + '> ' + $('<div>').text(section.section).html() + '</label>';
        });
        $('#academic_sections').html(sectionsHtml || '<span class="text-danger">No active class arms are configured for this class.</span>');
        selectedSections = selectedSectionIds();

        var config = payload.configuration || {};
        var availableComponents = config.valid ? (config.components || []) : [];
        var component = $('#target_component').empty().append($('<option>', {value: '', text: 'Select'}));
        $.each(availableComponents, function (_, item) {
            component.append($('<option>', {value: item.value, text: item.label + ' — max ' + parseFloat(item.maximum).toFixed(2)}));
        });
        component.val(selectedComponent);
        if (!component.val()) {
            selectedComponent = '';
        }
        var hasComponents = availableComponents.length > 0;
        $('#target_component_group').toggle(hasComponents);
        component.prop('required', hasComponents);
        var message = config.message || (hasComponents ? 'The component names and maximum scores come from the class assessment setting.' : '');
        $('#result_configuration_message').toggle(!!message).text(message);
    }

    function selectedSectionIds() {
        return $('#academic_sections input[name="section_ids[]"]:checked').map(function () {
            return parseInt(this.value, 10);
        }).get();
    }

    function loadConfiguration(resetSections) {
        var classId = $('#academic_class_id').val();
        var subjectId = $('#academic_subject_id').val();
        var purpose = $('#purpose').val();
        if (resetSections) {
            selectedSections = [];
            selectedComponent = '';
            $('#academic_sections').html('<span class="text-muted">Select a class and subject to load available arms.</span>');
        } else {
            selectedSections = selectedSectionIds();
        }
        if (configurationRequest) {
            configurationRequest.abort();
            configurationRequest = null;
        }
        if (!classId) {
            $('#academic_subject_id').empty().append($('<option>', {value: '', text: 'Select a class first'}));
            clearConfiguration('Select a class to load available subjects.');
            return;
        }
        if (!resetSections) {
            selectedComponent = $('#target_component').val() || selectedComponent;
        }
        $('#academic_sections').attr('aria-busy', 'true');
        $('#result_configuration_message').show().text('Loading the class arms and result configuration…');
        var request = configurationRequest = $.getJSON('<?php echo site_url('admin/onlineexam/academicconfiguration'); ?>', {
            class_id: classId,
            subject_id: subjectId,
            session_id: $('#academic_session_id').val(),
            term: $('#academic_term').val(),
            purpose: purpose,
            section_ids: selectedSections
        }).done(function (response) {
            if (response.status) {
                renderConfiguration(response);
            } else {
                clearConfiguration(response.message || 'The selected academic configuration is unavailable.');
            }
        }).fail(function (xhr, status) {
            if (status === 'abort') {
                return;
            }
            var message = xhr.responseJSON && xhr.responseJSON.message
                ? xhr.responseJSON.message
                : 'The academic configuration could not be loaded. Check the selected class, subject and purpose.';
            clearConfiguration(message);
        }).always(function () {
            if (configurationRequest === request) {
                configurationRequest = null;
                $('#academic_sections').attr('aria-busy', 'false');
            }
        });
    }

    $('#academic_class_id, #academic_session_id, #academic_term').on('change', function () {
        $('#academic_subject_id').val('');
        loadConfiguration(true);
    });
    $('#academic_subject_id').on('change', function () { loadConfiguration(true); });
    $('#purpose').on('change', function () { loadConfiguration(false); });
    $(document).on('change', '#academic_sections input[name="section_ids[]"]', function () { loadConfiguration(false); });
    $('#academic-assessment-form').on('submit', function (event) {
        if (!window.onlineexamValidateWindow(this, 'exam_from', 'exam_to')) { event.preventDefault(); }
    });
})(jQuery);
</script>
