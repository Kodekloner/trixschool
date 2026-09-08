<?php $this->load->view('admin/onlineexam/_assessment_styles'); ?>
<style>
.onlineexam-review .review-criteria { display:flex; flex-wrap:wrap; gap:12px; align-items:flex-end; }
.onlineexam-review .review-criteria .form-group { flex:1 1 150px; min-width:0; margin:0; }
.onlineexam-review .review-criteria .form-control { width:100%; }
.onlineexam-review .review-table { width:max-content; min-width:100%; table-layout:auto; border-collapse:separate; border-spacing:0; }
.onlineexam-review .review-table th, .onlineexam-review .review-table td { min-width:105px; max-width:155px; width:auto; white-space:normal; overflow-wrap:anywhere; vertical-align:middle; text-align:center; }
.onlineexam-review .review-table .review-student { position:sticky; left:0; z-index:1; background:#fff; width:220px; min-width:180px; border-right:2px solid #d2d6de; }
.onlineexam-review .review-table thead th { background:#f6f8fa; }
.onlineexam-review .review-table thead .review-student { z-index:2; background:#f6f8fa; }
.onlineexam-review .review-cell { display:inline-block; width:auto; max-width:100%; min-height:0; white-space:nowrap; padding:4px 9px; }
.onlineexam-review .review-subtitle { display:block; margin-top:4px; font-weight:normal; }
.onlineexam-review .review-scroll { max-height:70vh; }
.onlineexam-review .review-table thead th { position:sticky; top:0; z-index:1; }
#onlineexam-review-modal .modal-dialog { max-width:640px; width:calc(100% - 20px); margin:24px auto; }
#onlineexam-review-modal .modal-body { overflow-wrap:anywhere; }
#onlineexam-review-modal .review-actions { display:flex; flex-wrap:wrap; gap:8px; margin:14px 0; }
#onlineexam-review-modal .review-actions .btn { white-space:normal; }
#onlineexam-review-modal .form-control { width:100%; min-width:0; }
@media(max-width:480px) { .onlineexam-review .review-criteria .form-group { flex-basis:100%; } .onlineexam-review .review-table .review-student { min-width:130px; width:130px; } }
</style>
<div class="content-wrapper onlineexam-ui onlineexam-review">
    <section class="content">
        <?php echo $this->session->flashdata('msg'); ?>
        <div class="box box-primary">
            <div class="box-header with-border assessment-box-header">
                <h3 class="box-title">Online Examination Review</h3>
                <a class="btn btn-default btn-sm" href="<?php echo site_url('admin/onlineexam'); ?>">Assessments</a>
            </div>
            <div class="box-body">
                <h4>Select Criteria</h4>
                <form method="get" action="<?php echo site_url('admin/onlineexam/review'); ?>" class="review-criteria" id="review-criteria">
                    <div class="form-group"><label for="review-session">Session</label><select class="form-control" id="review-session" name="session_id" required>
                        <option value="">Select</option>
                        <?php foreach ($sessionList as $session) { ?><option value="<?php echo (int) $session['id']; ?>" <?php echo (int) $criteria['session_id'] === (int) $session['id'] ? 'selected' : ''; ?>><?php echo html_escape($session['session']); ?></option><?php } ?>
                    </select></div>
                    <div class="form-group"><label for="review-term">Term</label><select class="form-control" id="review-term" name="term" required>
                        <option value="">Select</option>
                        <?php foreach (array('1st', '2nd', '3rd') as $term) { ?><option value="<?php echo $term; ?>" <?php echo $criteria['term'] === $term ? 'selected' : ''; ?>><?php echo $term; ?> Term</option><?php } ?>
                    </select></div>
                    <div class="form-group"><label for="review-type">Assessment type</label><select class="form-control" id="review-type" name="assessment_type" required>
                        <option value="">Select</option>
                        <?php foreach (array('term' => 'Term', 'midterm' => 'Midterm', 'holiday' => 'Holiday') as $value => $label) { ?><option value="<?php echo $value; ?>" <?php echo $criteria['assessment_type'] === $value ? 'selected' : ''; ?>><?php echo $label; ?></option><?php } ?>
                    </select></div>
                    <div class="form-group"><label for="review-class">Class</label><select class="form-control" id="review-class" name="class_id" required>
                        <option value="">Select</option>
                        <?php foreach ($classList as $class) { ?><option value="<?php echo (int) $class['id']; ?>" <?php echo (int) $criteria['class_id'] === (int) $class['id'] ? 'selected' : ''; ?>><?php echo html_escape($class['class']); ?></option><?php } ?>
                    </select></div>
                    <div class="form-group"><label for="review-section">Section / arm</label><select class="form-control" id="review-section" name="section_id" required>
                        <option value="">Select</option>
                        <?php foreach ($sectionList as $section) { ?><option value="<?php echo (int) $section['id']; ?>" <?php echo (int) $criteria['section_id'] === (int) $section['id'] ? 'selected' : ''; ?>><?php echo html_escape($section['section']); ?></option><?php } ?>
                    </select></div>
                    <div class="form-group"><label for="review-component">CA / Exam component</label><select class="form-control" id="review-component" name="component" required>
                        <option value="">Select</option>
                        <?php foreach ($componentList as $component) { ?><option value="<?php echo html_escape($component['value']); ?>" <?php echo $criteria['component'] === $component['value'] ? 'selected' : ''; ?>><?php echo html_escape($component['label']); ?></option><?php } ?>
                    </select></div>
                    <button class="btn btn-primary" type="submit" id="review-search" <?php echo empty($componentList) ? 'disabled' : ''; ?>><i class="fa fa-search" aria-hidden="true"></i> Show</button>
                </form>
                <p class="text-danger" id="review-section-error" role="status"></p>
            </div>
        </div>
        <?php if ($ready) { ?>
            <div class="box box-primary">
                <div class="box-header with-border"><h3 class="box-title">Students and subjects</h3></div>
                <?php if (empty($review['columns'])) { ?><div class="box-body"><p>No published online examinations match these criteria.</p></div>
                <?php } elseif (empty($review['students'])) { ?><div class="box-body"><p>No active students were found in this class arm.</p></div>
                <?php } else { ?>
                    <div class="box-body">
                        <p class="text-muted">Select a result to review the student's work, reschedule it or record a score.</p>
                        <div class="table-responsive onlineexam-scroll review-scroll" role="region" aria-label="Students and examination papers" tabindex="0">
                            <table class="table table-bordered review-table">
                                <caption class="sr-only">Online examination attendance and paper scores for the selected class arm</caption>
                                <thead><tr><th scope="col" class="review-student">Student</th>
                                    <?php foreach ($review['columns'] as $column) { ?><th scope="col">
                                        <?php echo html_escape($column['subject']); ?>
                                    </th><?php } ?>
                                </tr></thead>
                                <tbody><?php foreach ($review['students'] as $student) { ?><tr>
                                    <th scope="row" class="review-student"><?php echo html_escape($student['student_name']); ?><span class="review-subtitle"><?php echo html_escape($student['admission_no']); ?></span></th>
                                    <?php foreach ($review['columns'] as $key => $column) { $cell = $review['cells'][$student['student_session_id']][$key]; ?><td>
                                        <button type="button" class="btn btn-<?php echo html_escape($cell['style']); ?> btn-sm review-cell" data-review-url="<?php echo site_url('admin/onlineexam/reviewcell/' . $column['exam_id'] . '/' . (int) $student['student_session_id'] . '/' . $column['paper_id']); ?>" aria-label="<?php echo html_escape($student['student_name'] . ', ' . $column['title'] . ': ' . $cell['label']); ?>"><?php echo html_escape($cell['label']); ?></button>
                                        <?php if (in_array($cell['posting_status'], array('conflict', 'failed', 'error', 'pending'), true)) { ?><small class="text-warning">Result needs attention</small><?php } ?>
                                    </td><?php } ?>
                                </tr><?php } ?></tbody>
                            </table>
                        </div>
                    </div>
                <?php } ?>
            </div>
        <?php } ?>
    </section>
</div>
<div class="modal fade" id="onlineexam-review-modal" tabindex="-1" role="dialog" aria-labelledby="onlineexam-review-title">
    <div class="modal-dialog" role="document"><div class="modal-content">
        <div class="modal-header"><button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button><h4 class="modal-title" id="onlineexam-review-title">Review paper</h4></div>
        <div class="modal-body" aria-live="polite"></div>
    </div></div>
</div>
<script>
(function ($) {
    'use strict';
    var classesRequest = null, sectionsRequest = null, componentsRequest = null, cellRequest = null;
    var $modal = $('#onlineexam-review-modal');
    function updateAction() {
        var manual = $modal.find('[name="review_action"]').val() === 'manual_score';
        $modal.find('[data-review-fields="reschedule"]').prop('hidden', manual).find(':input').prop('disabled', manual);
        $modal.find('[data-review-fields="manual_score"]').prop('hidden', !manual).find(':input').prop('disabled', !manual);
        $modal.find('[data-review-save]').text(manual ? 'Save score' : 'Save schedule');
    }
    function openCell(url, values) {
        if (cellRequest) { cellRequest.abort(); }
        $modal.find('.modal-body').text('Loading paper…');
        $modal.modal('show');
        cellRequest = $.get(url).done(function (html) {
            $modal.find('.modal-body').html(html);
            if (values) {
                $.each(values, function (name, value) {
                    $modal.find(':input').filter(function () { return this.name === name; }).val(value);
                });
            }
            updateAction();
        }).fail(function (xhr, status) {
            if (status !== 'abort') { $modal.find('.modal-body').text('The paper could not be loaded. Close this window and try again.'); }
        });
    }
    $(document).on('click', '[data-review-url]', function () { openCell($(this).attr('data-review-url')); });
    $modal.on('change', '[name="review_action"]', updateAction);
    $modal.on('submit', '.review-action-form', function () {
        var $form = $(this), $start = $form.find('[name="starts_at"]'), $end = $form.find('[name="ends_at"]');
        // Native datetime-local validity plus explicit numeric timestamps; DateJS overrides Date.now().
        if (!$start.prop('disabled')) {
            var start = new Date($start.val()).getTime(), end = new Date($end.val()).getTime();
            var duration = Number($form.attr('data-duration')) * 60000;
            if (!isFinite(start) || !isFinite(end) || end - start < duration) {
                $form.find('[data-review-error]').text('The start-to-end interval must allow the full paper duration.');
                return false;
            }
        }
        $form.find('[data-review-save]').prop('disabled', true);
    });

    function clearComponents(message) {
        $('#review-component').empty().append($('<option>', {value:'', text:'Select'})).prop('disabled', true);
        $('#review-search').prop('disabled', true);
        $('#review-section-error').text(message || '');
    }

    function loadComponents(keepSelection) {
        if (componentsRequest) { componentsRequest.abort(); }
        var selected = keepSelection ? $('#review-component').val() : '';
        clearComponents('');
        var criteria = {
            session_id: $('#review-session').val(), term: $('#review-term').val(),
            assessment_type: $('#review-type').val(), class_id: $('#review-class').val(),
            section_id: $('#review-section').val()
        };
        if (!criteria.session_id || !criteria.term || !criteria.assessment_type || !criteria.class_id || !criteria.section_id) { return; }
        componentsRequest = $.getJSON(<?php echo json_encode(site_url('admin/onlineexam/reviewcomponents')); ?>, criteria)
            .done(function (rows) {
                var $component = $('#review-component');
                $.each(rows, function (_, row) { $component.append($('<option>', {value:row.value, text:row.label})); });
                $component.prop('disabled', false).val(selected);
                $('#review-search').prop('disabled', !$component.val());
                if (!rows.length) { $('#review-section-error').text('No published online examination matches this academic selection.'); }
            }).fail(function (xhr, status) {
                if (status !== 'abort') { $('#review-section-error').text('CA / Exam components could not be loaded. Change the selection to retry.'); }
            });
    }

    function resetReviewAcademicChildren() {
        if (sectionsRequest) { sectionsRequest.abort(); }
        var $section = $('#review-section');
        $section.empty().append($('<option>', {value:'', text:'Select'})).prop('disabled', true);
        clearComponents('');
        $('#review-section-error').text('');
        $('#review-search').prop('disabled', true);
    }

    function loadReviewSections() {
        resetReviewAcademicChildren();
        var $section = $('#review-section');
        if (!$('#review-session').val() || !$('#review-class').val()) { return; }
        sectionsRequest = $.getJSON(<?php echo json_encode(site_url('admin/onlineexam/reviewsections')); ?>, {session_id:$('#review-session').val(), class_id:$('#review-class').val()})
            .done(function (rows) {
                $.each(rows, function (_, row) { $section.append($('<option>', {value:row.id, text:row.section})); });
                $section.prop('disabled', false);
                if (!rows.length) { $('#review-section-error').text('No enrolled class arms are available for this selection.'); }
            }).fail(function (xhr, status) {
                if (status !== 'abort') { $('#review-section-error').text('Class arms could not be loaded. Change the selection to retry.'); }
            });
    }

    $('#review-session').on('change', function () {
        if (classesRequest) { classesRequest.abort(); }
        resetReviewAcademicChildren();
        var $classes = $('#review-class').empty().append($('<option>', {value:'', text:'Loading classes…'})).prop('disabled', true);
        if (!$('#review-session').val()) {
            $classes.empty().append($('<option>', {value:'', text:'Select a session first'}));
            return;
        }
        classesRequest = $.getJSON(<?php echo json_encode(site_url('admin/onlineexam/academicclasses')); ?>, {session_id:$('#review-session').val()})
            .done(function (response) {
                $classes.empty().append($('<option>', {value:'', text:'Select'}));
                $.each(response.classes || [], function (_, row) {
                    $classes.append($('<option>', {value:row.id, text:row['class']}));
                });
                $classes.prop('disabled', false);
                if (!(response.classes || []).length) { $('#review-section-error').text('No class and subject assignment is available in this session.'); }
            }).fail(function (xhr, status) {
                if (status !== 'abort') { $('#review-section-error').text('Classes could not be loaded for this session.'); }
            });
    });
    $('#review-class').on('change', loadReviewSections);
    $('#review-term, #review-type').on('change', function () { loadComponents(false); });
    $('#review-section').on('change', function () { loadComponents(false); });
    $('#review-component').on('change', function () { $('#review-search').prop('disabled', !this.value); });
    var recovery = <?php echo json_encode($review_input ?: null, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT); ?>;
    $(function () {
        if (recovery) {
            openCell(<?php echo json_encode(site_url('admin/onlineexam/reviewcell') . '/'); ?> + recovery.exam_id + '/' + recovery.student_session_id + '/' + recovery.paper_id, recovery.values);
        }
    });
})(jQuery);
</script>
