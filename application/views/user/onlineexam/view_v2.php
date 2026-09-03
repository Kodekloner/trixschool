<?php
$purpose_labels = array(
    'ca'                    => 'Continuous Assessment',
    'continuous_assessment' => 'Continuous Assessment',
    'midterm'               => 'Midterm Assessment',
    'mid_term'              => 'Midterm Assessment',
    'holiday'               => 'Holiday Assessment',
    'kindergarten'          => 'Kindergarten Assessment',
);
$paper_type_labels = array(
    'objective' => 'Objective',
    'theory'    => 'Theory',
    'essay'     => 'Theory',
);
$paper_status_classes = array(
    'not_started' => 'label-info',
    'in_progress' => 'label-warning',
    'submitted'   => 'label-primary',
    'completed'   => 'label-success',
    'timed_out'   => 'label-danger',
);
$purpose_key = isset($exam->purpose) ? strtolower((string) $exam->purpose) : '';
$purpose_label = isset($purpose_labels[$purpose_key]) ? $purpose_labels[$purpose_key] : ($purpose_key !== '' ? ucwords(str_replace('_', ' ', $purpose_key)) : 'Online Assessment');
?>
<div class="content-wrapper online-assessment-page">
    <section class="content-header">
        <h1><i class="fa fa-laptop" aria-hidden="true"></i> Online Assessment</h1>
    </section>

    <section class="content">
        <div class="box box-primary assessment-overview">
            <div class="box-header with-border">
                <h3 class="box-title"><?php echo html_escape($exam->exam); ?></h3>
            </div>
            <div class="box-body">
                <div class="row assessment-summary">
                    <div class="col-sm-6 col-md-3">
                        <div class="assessment-summary-item">
                            <span class="assessment-summary-label">Term</span>
                            <strong><?php echo !empty($exam->term) ? html_escape(ucwords($exam->term) . ' term') : 'Not specified'; ?></strong>
                        </div>
                    </div>
                    <div class="col-sm-6 col-md-3">
                        <div class="assessment-summary-item">
                            <span class="assessment-summary-label">Assessment type</span>
                            <strong><?php echo html_escape($purpose_label); ?></strong>
                        </div>
                    </div>
                    <div class="col-sm-6 col-md-3">
                        <div class="assessment-summary-item">
                            <span class="assessment-summary-label">Opens</span>
                            <strong><?php echo $this->customlib->dateyyyymmddToDateTimeformat($exam->exam_from, false); ?></strong>
                        </div>
                    </div>
                    <div class="col-sm-6 col-md-3">
                        <div class="assessment-summary-item">
                            <span class="assessment-summary-label">Closes</span>
                            <strong><?php echo $this->customlib->dateyyyymmddToDateTimeformat($exam->exam_to, false); ?></strong>
                        </div>
                    </div>
                </div>

                <div class="candidate-card">
                    <i class="fa fa-user-circle" aria-hidden="true"></i>
                    <div>
                        <strong><?php echo html_escape($this->customlib->getFullname($student['firstname'], $student['middlename'], $student['lastname'], $sch_setting->middlename, $sch_setting->lastname)); ?></strong>
                        <span><?php echo html_escape($student['admission_no']); ?> · <?php echo html_escape($student['class'] . ' (' . $student['section'] . ')'); ?></span>
                    </div>
                </div>

                <?php if (!empty($exam->description)) { ?>
                    <div class="alert alert-info assessment-description"><?php echo $this->security->xss_clean($exam->description); ?></div>
                <?php } ?>

                <?php if ($attempt && in_array($attempt->status, array('submitted', 'marking'), true)) { ?>
                    <div class="alert alert-warning" role="status">
                        Your answers have been submitted. <?php echo $attempt->status === 'marking' ? 'Written responses are being reviewed.' : 'Your result is being finalised.'; ?>
                    </div>
                <?php } elseif ($attempt && $attempt->status === 'timed_out') { ?>
                    <div class="alert alert-warning" role="status">Time has ended and your saved answers have been submitted.</div>
                <?php } elseif ($attempt && $attempt->status === 'completed') { ?>
                    <div class="alert alert-success" role="status">
                        This assessment is complete.
                        <?php if ($exam->feedback_status === 'released' && $attempt->final_score !== null) { ?>
                            Your score is <strong><?php echo number_format((float) $attempt->final_score, 2); ?><?php echo $exam->target_max_score ? ' / ' . number_format((float) $exam->target_max_score, 2) : ''; ?></strong>.
                        <?php } else { ?>
                            Your school will release the result when it is ready.
                        <?php } ?>
                    </div>
                <?php } ?>

                <?php if (!empty($released_feedback['questions'])) {
                    $feedback_value = function ($value) {
                        if ($value === null || $value === '') {
                            return 'No answer';
                        }
                        if (is_bool($value)) {
                            return $value ? 'True' : 'False';
                        }
                        if (is_array($value)) {
                            return json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
                        }
                        return (string) $value;
                    };
                    ?>
                    <div class="box box-success assessment-feedback-box">
                        <div class="box-header with-border"><h3 class="box-title">Answer review</h3></div>
                        <div class="box-body">
                            <div class="table-responsive assessment-feedback-wrap">
                                <table class="table table-bordered table-condensed assessment-feedback-table">
                                    <thead>
                                        <tr><th>Question</th><th>Your answer</th><th>Expected answer</th><th>Mark</th></tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($released_feedback['questions'] as $feedback_question) { ?>
                                            <tr>
                                                <td data-label="Question">
                                                    <strong><?php echo html_escape($feedback_question['paper_title']); ?></strong><?php echo !empty($feedback_question['section_title']) ? ' / ' . html_escape($feedback_question['section_title']) : ''; ?>
                                                    <div class="feedback-question-text"><?php echo nl2br(html_escape(strip_tags($feedback_question['question_text']))); ?></div>
                                                </td>
                                                <td data-label="Your answer"><pre class="feedback-value"><?php echo html_escape($feedback_value($feedback_question['response'])); ?></pre></td>
                                                <td data-label="Expected answer">
                                                    <?php if ($feedback_question['correct_answer'] !== null && $feedback_question['correct_answer'] !== '') { ?><div><?php echo html_escape($feedback_value($feedback_question['correct_answer'])); ?></div><?php } ?>
                                                    <?php if (!empty($feedback_question['marking_scheme'])) { ?><small class="feedback-scheme"><?php echo nl2br(html_escape(strip_tags($feedback_question['marking_scheme']))); ?></small><?php } ?>
                                                </td>
                                                <td data-label="Mark" class="feedback-mark"><?php echo number_format((float) $feedback_question['final_mark'], 2); ?> / <?php echo number_format((float) $feedback_question['marks'], 2); ?></td>
                                            </tr>
                                        <?php } ?>
                                    </tbody>
                                </table>
                            </div>
                            <p class="help-block">This review does not change when your school releases the official result.</p>
                        </div>
                    </div>
                <?php } ?>

                <div class="assessment-parts-heading">
                    <h4>Assessment parts</h4>
                    <span class="text-muted">Start each available part below.</span>
                </div>
                <div class="table-responsive assessment-parts-wrap">
                    <table class="table table-bordered table-striped assessment-parts-table">
                        <thead>
                            <tr><th>Part</th><th>Type</th><th>Schedule</th><th>Duration</th><th>Status</th><th class="text-right">Action</th></tr>
                        </thead>
                        <tbody>
                            <?php if (!empty($papers)) { ?>
                                <?php foreach ($papers as $paper) {
                                    $paper_status_key = !empty($paper->attempt_paper_status) ? strtolower((string) $paper->attempt_paper_status) : 'not_started';
                                    $paper_status_label = $paper_status_key === 'not_started' ? 'Not started' : ucwords(str_replace('_', ' ', $paper_status_key));
                                    $paper_status_class = isset($paper_status_classes[$paper_status_key]) ? $paper_status_classes[$paper_status_key] : 'label-default';
                                    $paper_type_key = strtolower((string) $paper->paper_type);
                                    $paper_type_label = isset($paper_type_labels[$paper_type_key]) ? $paper_type_labels[$paper_type_key] : 'Assessment';
                                    $now = time();
                                    $starts = $paper->starts_at ? strtotime($paper->starts_at) : strtotime($exam->exam_from);
                                    $is_makeup = $attempt && (int) $attempt->attempt_no > 1 && !empty($exam->makeup_expires_at);
                                    $ends = $is_makeup ? strtotime($exam->makeup_expires_at) : ($paper->ends_at ? strtotime($paper->ends_at) : strtotime($exam->exam_to));
                                    if ($ends && !$is_makeup && !empty($exam->accommodation_extra_time_minutes)) {
                                        $ends += (int) $exam->accommodation_extra_time_minutes * 60;
                                    }
                                    $available = (!$starts || $now >= $starts) && (!$ends || $now < $ends);
                                    $is_cbt = !isset($paper->delivery_mode) || strtolower((string) $paper->delivery_mode) === 'cbt';
                                    $launchable_status = in_array($paper_status_key, array('not_started', 'in_progress'), true);
                                    $can_launch = $is_cbt && $launchable_status && (!$attempt || !in_array($attempt->status, array('submitted', 'timed_out', 'marking', 'completed'), true));
                                    ?>
                                    <tr>
                                        <td data-label="Part" class="assessment-part-name">
                                            <strong><?php echo html_escape($paper->title); ?></strong>
                                            <?php if (!empty($paper->paper_code)) { ?><span><?php echo html_escape($paper->paper_code); ?></span><?php } ?>
                                        </td>
                                        <td data-label="Type"><?php echo html_escape($paper_type_label); ?></td>
                                        <td data-label="Schedule"><?php echo $paper->starts_at ? $this->customlib->dateyyyymmddToDateTimeformat($paper->starts_at, false) : 'Assessment window'; ?></td>
                                        <td data-label="Duration"><?php echo (int) $paper->duration_minutes; ?> minutes</td>
                                        <td data-label="Status"><span class="label <?php echo $paper_status_class; ?> assessment-part-status"><?php echo html_escape($paper_status_label); ?></span></td>
                                        <td data-label="Action" class="text-right assessment-part-action">
                                            <?php if ($can_launch) { ?>
                                                <button type="button" class="btn btn-primary btn-sm v2-start-paper" data-exam-id="<?php echo (int) $exam->id; ?>" data-paper-id="<?php echo (int) $paper->id; ?>" <?php echo $available ? '' : 'disabled aria-disabled="true" title="This assessment part is not currently available"'; ?>>
                                                    <i class="fa <?php echo $paper_status_key === 'in_progress' ? 'fa-play-circle' : 'fa-play'; ?>" aria-hidden="true"></i>
                                                    <?php echo $paper_status_key === 'in_progress' ? 'Resume' : 'Start'; ?>
                                                </button>
                                            <?php } elseif (!$is_cbt) { ?>
                                                <span class="text-muted">Unavailable online</span>
                                            <?php } ?>
                                        </td>
                                    </tr>
                                <?php } ?>
                            <?php } else { ?>
                                <tr><td colspan="6" class="text-center text-muted">No assessment parts are available.</td></tr>
                            <?php } ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </section>
</div>

<div id="v2PaperModal" class="modal fade online-assessment-modal" role="dialog" aria-labelledby="v2PaperModalTitle" aria-modal="true">
    <div class="modal-dialog modal-dialogfullwidth" role="document">
        <div class="modal-content modal-contentfull">
            <div class="modal-header v2-modal-header">
                <div class="v2-modal-heading">
                    <h4 class="modal-title" id="v2PaperModalTitle">Online Assessment</h4>
                    <div class="v2-modal-statuses">
                        <span id="v2SyncState" class="label label-default" role="status" aria-live="polite"><i class="fa fa-cloud" aria-hidden="true"></i> Ready</span>
                        <span id="v2TimerState" class="v2-timer-state" role="timer" aria-live="off" aria-label="Time remaining"><i class="fa fa-clock-o" aria-hidden="true"></i> <span id="v2PaperTimer">--:--:--</span></span>
                    </div>
                </div>
                <button type="button" class="btn btn-default btn-sm v2-leave-paper" data-dismiss="modal"><i class="fa fa-arrow-left" aria-hidden="true"></i> Save &amp; leave</button>
            </div>
            <div class="modal-body" id="v2PaperContainer" tabindex="-1"></div>
        </div>
    </div>
</div>

<style>
.online-assessment-page .assessment-overview>.box-body{padding:18px}
.online-assessment-page .assessment-summary{margin:0 -7px 12px}
.online-assessment-page .assessment-summary>div{padding:0 7px;margin-bottom:14px}
.online-assessment-page .assessment-summary-item{height:100%;min-height:82px;padding:13px;border:1px solid #e0e5ea;border-radius:5px;background:#f8fafb;overflow-wrap:anywhere}
.online-assessment-page .assessment-summary-label{display:block;margin-bottom:5px;color:#66717c;font-size:12px;text-transform:uppercase;letter-spacing:.03em}
.online-assessment-page .candidate-card{display:flex;align-items:center;gap:12px;margin-bottom:16px;padding:12px 14px;border-left:4px solid #3c8dbc;background:#f5f9fc}
.online-assessment-page .candidate-card>i{flex:0 0 auto;color:#3c8dbc;font-size:28px}
.online-assessment-page .candidate-card strong,.online-assessment-page .candidate-card span{display:block;overflow-wrap:anywhere}
.online-assessment-page .candidate-card span{margin-top:2px;color:#66717c}
.online-assessment-page .assessment-description img,.online-assessment-page .feedback-question-text img{max-width:100%;height:auto}
.online-assessment-page .assessment-feedback-box{margin-top:18px}
.online-assessment-page .assessment-parts-wrap,.online-assessment-page .assessment-feedback-wrap{max-width:100%;overflow-x:auto;-webkit-overflow-scrolling:touch}
.online-assessment-page .assessment-parts-table{min-width:760px}
.online-assessment-page .assessment-feedback-table{min-width:820px}
.online-assessment-page .assessment-feedback-table th,.online-assessment-page .assessment-feedback-table td,.online-assessment-page .assessment-parts-table th,.online-assessment-page .assessment-parts-table td{vertical-align:top;white-space:normal;overflow-wrap:anywhere}
.online-assessment-page .feedback-question-text{margin-top:7px;color:#3f4952}
.online-assessment-page .feedback-value{max-width:100%;margin:0;padding:8px;white-space:pre-wrap;word-break:break-word;background:#f7f7f7}
.online-assessment-page .feedback-scheme{display:block;margin-top:7px;color:#56616c}
.online-assessment-page .feedback-mark{white-space:nowrap!important;font-weight:600}
.online-assessment-page .assessment-parts-heading{display:flex;align-items:baseline;justify-content:space-between;gap:12px;margin-top:20px}
.online-assessment-page .assessment-parts-heading h4{margin:0 0 10px}
.online-assessment-page .assessment-part-name strong,.online-assessment-page .assessment-part-name span{display:block}
.online-assessment-page .assessment-part-name span{margin-top:3px;color:#6b7280;font-size:12px}
.online-assessment-page .assessment-part-status{display:inline-block;padding:5px 8px;font-size:11px}
.online-assessment-page .assessment-part-action .btn{min-width:84px;min-height:34px}
.modal-dialogfullwidth{width:calc(100% - 30px);max-width:1280px;margin:12px auto}
.modal-contentfull{display:flex;flex-direction:column;height:calc(100vh - 24px);min-height:480px}
.online-assessment-modal .v2-modal-header{display:flex;align-items:center;justify-content:space-between;gap:15px;flex:0 0 auto;padding:11px 15px}
.online-assessment-modal .v2-modal-heading{display:flex;align-items:center;justify-content:space-between;gap:18px;min-width:0;flex:1}
.online-assessment-modal .modal-title{min-width:0;overflow:hidden;text-overflow:ellipsis;white-space:nowrap}
.online-assessment-modal .v2-modal-statuses{display:flex;align-items:center;gap:9px;white-space:nowrap}
.online-assessment-modal #v2SyncState{padding:6px 9px;font-size:11px}
.online-assessment-modal .v2-timer-state{display:inline-block;padding:5px 9px;border:1px solid #c8d0d8;border-radius:4px;background:#f8fafb;color:#263746;font-size:17px;font-weight:700;font-variant-numeric:tabular-nums}
.online-assessment-modal .v2-timer-state.v2-timer-warning{border-color:#e6a23c;background:#fff8e8;color:#8a5a00}
.online-assessment-modal .v2-timer-state.v2-timer-danger{border-color:#dd4b39;background:#fff1ef;color:#b52b1d;animation:v2-timer-pulse 1s ease-in-out infinite alternate}
.online-assessment-modal .v2-leave-paper{flex:0 0 auto;min-height:36px}
.online-assessment-modal .modal-body{flex:1 1 auto;padding:16px;overflow-y:auto;-webkit-overflow-scrolling:touch;background:#f5f7f9}
.online-assessment-modal .v2-paper{max-width:980px;margin:0 auto}
.online-assessment-modal .v2-paper-intro{overflow-wrap:anywhere}
.online-assessment-modal .v2-paper-title{display:block;font-size:17px}
.online-assessment-modal .v2-paper-instructions{margin-top:6px}
.online-assessment-modal .v2-paper-instructions,.online-assessment-modal .v2-section-instructions,.online-assessment-modal .v2-question-text,.online-assessment-modal .v2-passage .panel-body{max-width:100%;overflow-x:auto;word-wrap:break-word}
.online-assessment-modal .v2-paper-instructions img,.online-assessment-modal .v2-section-instructions img,.online-assessment-modal .v2-question-text img,.online-assessment-modal .v2-passage img,.online-assessment-modal .v2-paper-instructions iframe,.online-assessment-modal .v2-section-instructions iframe,.online-assessment-modal .v2-question-text iframe,.online-assessment-modal .v2-passage iframe{max-width:100%!important;height:auto!important}
.online-assessment-modal .v2-section-heading{margin:20px 0 12px;padding:12px 14px;border-left:4px solid #3c8dbc;border-radius:0 4px 4px 0;background:#eef5fa;overflow-wrap:anywhere}
.online-assessment-modal .v2-section-heading h4{margin:0 0 7px}
.online-assessment-modal .v2-section-rule{display:flex;align-items:flex-start;flex-wrap:wrap;gap:7px;margin-top:9px}
.online-assessment-modal .v2-section-rule .label{display:inline-block;max-width:100%;padding:6px 8px;white-space:normal;text-align:left;line-height:1.35}
.online-assessment-modal .v2-question{border-color:#d7dde3;box-shadow:0 1px 2px rgba(0,0,0,.04)}
.online-assessment-modal .v2-question.v2-question-answered{border-left:4px solid #00a65a}
.online-assessment-modal .v2-question.v2-choice-locked{opacity:.68}
.online-assessment-modal .v2-question.v2-choice-locked .panel-body{background:#f4f4f4}
.online-assessment-modal .v2-question-heading{display:flex;align-items:center;justify-content:space-between;gap:12px;overflow-wrap:anywhere}
.online-assessment-modal .v2-question-marks{flex:0 0 auto;color:#53606c;font-size:12px;font-weight:600}
.online-assessment-modal .v2-question-text{margin-bottom:16px;font-size:16px;line-height:1.55;overflow-wrap:anywhere}
.online-assessment-modal .v2-option{display:flex;align-items:flex-start;gap:10px;min-height:46px;margin:7px 0;padding:12px;border:1px solid #dce2e7;border-radius:5px;background:#fff;font-weight:normal;line-height:1.35;cursor:pointer;overflow-wrap:anywhere}
.online-assessment-modal .v2-option:hover,.online-assessment-modal .v2-option:focus-within{border-color:#3c8dbc;background:#f4f9fc}
.online-assessment-modal .v2-option input{flex:0 0 auto;margin:2px 0 0}
.online-assessment-modal .v2-answer-control.form-control{min-height:42px;font-size:16px}
.online-assessment-modal textarea.v2-answer-control{min-height:150px;resize:vertical}
.online-assessment-modal .v2-match-label{padding-top:9px;overflow-wrap:anywhere}
.online-assessment-modal .v2-question-actions{display:flex;align-items:center;justify-content:space-between;gap:10px;margin-top:12px;padding-top:10px;border-top:1px solid #edf0f2}
.online-assessment-modal .v2-clear-answer{min-height:36px}
.online-assessment-modal .v2-save-state{text-align:right;overflow-wrap:anywhere}
.online-assessment-modal .v2-submit-bar{position:sticky;bottom:-16px;z-index:4;display:flex;align-items:center;justify-content:space-between;gap:16px;margin:20px -16px -16px;padding:13px 16px;border-top:1px solid #cfd6dc;background:#fff;box-shadow:0 -2px 7px rgba(0,0,0,.08)}
.online-assessment-modal .v2-progress-summary strong,.online-assessment-modal .v2-progress-summary span{display:block}
.online-assessment-modal .v2-progress-summary span{margin-top:2px;color:#66717c;font-size:12px}
.online-assessment-modal .v2-submit-paper{flex:0 0 auto;min-width:170px}
@keyframes v2-timer-pulse{from{box-shadow:0 0 0 rgba(221,75,57,0)}to{box-shadow:0 0 0 3px rgba(221,75,57,.16)}}
@media (max-width:767px){
    .online-assessment-page .content{padding:10px}
    .online-assessment-page .assessment-overview>.box-body{padding:12px}
    .online-assessment-page .assessment-summary-item{min-height:0}
    .online-assessment-page .assessment-parts-heading{display:block}
    .online-assessment-page .assessment-part-action .btn{min-height:40px}
    .modal-dialogfullwidth{width:100%;max-width:none;height:100%;margin:0}
    .modal-contentfull{height:100vh;min-height:0;border:0;border-radius:0}
    .online-assessment-modal .v2-modal-header{align-items:stretch;padding:8px 10px}
    .online-assessment-modal .v2-modal-heading{display:block}
    .online-assessment-modal .modal-title{margin-bottom:7px;font-size:16px}
    .online-assessment-modal .v2-modal-statuses{justify-content:flex-start;gap:6px}
    .online-assessment-modal #v2SyncState{max-width:150px;overflow:hidden;text-overflow:ellipsis}
    .online-assessment-modal .v2-timer-state{font-size:15px}
    .online-assessment-modal .v2-leave-paper{align-self:center;min-height:42px;padding:9px 10px}
    .online-assessment-modal .modal-body{padding:10px}
    .online-assessment-modal .v2-question .panel-heading,.online-assessment-modal .v2-question .panel-body{padding:10px}
    .online-assessment-modal .v2-question-text{font-size:15px}
    .online-assessment-modal .v2-submit-bar{bottom:-10px;display:block;margin:18px -10px -10px;padding:10px}
    .online-assessment-modal .v2-progress-summary{margin-bottom:9px}
    .online-assessment-modal .v2-submit-paper{width:100%;min-height:46px}
}
@media (max-width:380px){
    .online-assessment-page .content-header h1{font-size:20px}
    .online-assessment-page .candidate-card{align-items:flex-start}
    .online-assessment-modal .v2-modal-header{gap:7px}
    .online-assessment-modal #v2SyncState{max-width:112px}
    .online-assessment-modal .v2-leave-paper{font-size:12px}
    .online-assessment-modal .v2-question-heading{align-items:flex-start}
    .online-assessment-modal .v2-question-actions{display:block}
    .online-assessment-modal .v2-save-state{display:block;margin-top:8px;text-align:left}
}
@media (max-width:480px){
    .online-assessment-modal .v2-modal-header{display:block}
    .online-assessment-modal .v2-modal-heading{width:100%}
    .online-assessment-modal .v2-modal-statuses{justify-content:space-between}
    .online-assessment-modal #v2SyncState{max-width:calc(100% - 105px)}
    .online-assessment-modal .v2-leave-paper{width:100%;margin-top:8px}
}
</style>

<script>
(function ($) {
    'use strict';

    var token = <?php echo json_encode($workflow_token); ?>;
    var timerHandle = null;
    var active = null;
    var saveTimers = {};
    var pendingRequests = 0;
    var isSubmitting = false;
    var finalSubmissionPending = false;
    var finalSubmissionTimedOut = false;
    var queuePrefix = 'onlineexam-v2-save-queue:<?php echo (int) $assignment->student_session_id; ?>:';

    function message(text, type) {
        if (typeof successMsg === 'function' && type === 'success') {
            successMsg(text);
            return;
        }
        if (typeof errorMsg === 'function' && type !== 'success') {
            errorMsg(text);
            return;
        }
        alert(text);
    }

    function post(url, data) {
        return $.ajax({
            url: url,
            type: 'POST',
            data: $.extend({}, data, {workflow_token: token}),
            dataType: 'json'
        });
    }

    function queueKey(attemptId) {
        return queuePrefix + parseInt(attemptId || 0, 10);
    }

    function readQueue(attemptId) {
        try {
            var queue = JSON.parse(localStorage.getItem(queueKey(attemptId)) || '{}');
            return $.isPlainObject(queue) ? queue : {};
        } catch (error) {
            return {};
        }
    }

    function setSyncState(text, type) {
        var classes = {
            success: 'label-success',
            warning: 'label-warning',
            danger: 'label-danger',
            info: 'label-info'
        };
        var icon = type === 'success' ? 'fa-check' : (type === 'danger' ? 'fa-exclamation-triangle' : (type === 'warning' ? 'fa-refresh fa-spin' : 'fa-cloud'));
        $('#v2SyncState').attr('class', 'label ' + (classes[type] || 'label-default')).html('<i class="fa ' + icon + '" aria-hidden="true"></i> ' + $('<div>').text(text).html());
    }

    function writeQueue(attemptId, queue) {
        try {
            var key = queueKey(attemptId);
            if ($.isEmptyObject(queue)) {
                localStorage.removeItem(key);
            } else {
                localStorage.setItem(key, JSON.stringify(queue));
            }
            return true;
        } catch (error) {
            setSyncState('Device storage unavailable', 'danger');
            return false;
        }
    }

    function queuedCount(attemptId) {
        return Object.keys(readQueue(attemptId)).length;
    }

    function refreshSyncState(attemptId) {
        attemptId = attemptId || (active && active.attempt_id);
        if (!attemptId) {
            return;
        }
        var count = queuedCount(attemptId);
        if (count > 0) {
            setSyncState(count + ' waiting to save', navigator.onLine ? 'warning' : 'danger');
        } else if (pendingRequests > 0) {
            setSyncState('Saving changes', 'warning');
        } else if (!navigator.onLine) {
            setSyncState('Offline', 'danger');
        } else {
            setSyncState('All changes saved', 'success');
        }
    }

    function queueSave(payload) {
        payload = $.extend({}, payload);
        delete payload.workflow_token;
        var queue = readQueue(payload.attempt_id);
        queue[payload.attempt_id + ':' + payload.question_snapshot_id] = payload;
        return writeQueue(payload.attempt_id, queue);
    }

    function removeQueued(payload) {
        var queue = readQueue(payload.attempt_id);
        var key = payload.attempt_id + ':' + payload.question_snapshot_id;
        var queuedPayload = queue[key];
        if (!queuedPayload || parseInt(queuedPayload.client_sequence || '0', 10) <= parseInt(payload.client_sequence || '0', 10)) {
            delete queue[key];
        }
        writeQueue(payload.attempt_id, queue);
    }

    function clearAttemptQueue(attemptId) {
        try {
            localStorage.removeItem(queueKey(attemptId));
        } catch (error) {
            // A successful server submission remains authoritative if browser
            // storage is unavailable.
        }
    }

    function valueFor($question) {
        var type = String($question.data('question-type') || '').toLowerCase();
        if (type === 'singlechoice' || type === 'single_choice' || type === '' || type === 'true_false' || type === 'true/false') {
            return $question.find('input[type=radio]:checked').val() || '';
        }
        if (type === 'multichoice' || type === 'multiple_choice') {
            return $question.find('input[type=checkbox]:checked').map(function () { return this.value; }).get();
        }
        if (type === 'ordering') {
            return $question.find('.v2-order-control').map(function () { return $(this).val(); }).get();
        }
        if (type === 'matching') {
            var matches = {};
            $question.find('.v2-match-control').each(function () {
                matches[String($(this).data('left-id'))] = $(this).val() || '';
            });
            return matches;
        }
        return $question.find('.v2-answer-control').first().val() || '';
    }

    function applyValue($question, value) {
        var type = String($question.data('question-type') || '').toLowerCase();
        if (type === 'singlechoice' || type === 'single_choice' || type === '' || type === 'true_false' || type === 'true/false') {
            $question.find('input[type=radio]').each(function () {
                this.checked = String(this.value) === String(value || '');
            });
            return;
        }
        if (type === 'multichoice' || type === 'multiple_choice') {
            var selected = $.isArray(value) ? value.map(String) : [];
            $question.find('input[type=checkbox]').each(function () {
                this.checked = selected.indexOf(String(this.value)) !== -1;
            });
            return;
        }
        if (type === 'ordering') {
            $question.find('.v2-order-control').each(function (index) {
                $(this).val($.isArray(value) && typeof value[index] !== 'undefined' ? value[index] : '');
            });
            return;
        }
        if (type === 'matching') {
            value = $.isPlainObject(value) ? value : {};
            $question.find('.v2-match-control').each(function () {
                $(this).val(value[String($(this).data('left-id'))] || '');
            });
            return;
        }
        $question.find('.v2-answer-control').first().val($.isArray(value) || $.isPlainObject(value) ? JSON.stringify(value) : (value || ''));
    }

    function questionAnswered($question) {
        var value = valueFor($question);
        if ($.isArray(value)) {
            return $.grep(value, function (item) { return $.trim(String(item || '')) !== ''; }).length > 0;
        }
        if ($.isPlainObject(value)) {
            var answered = false;
            $.each(value, function (_, item) {
                if ($.trim(String(item || '')) !== '') {
                    answered = true;
                }
            });
            return answered;
        }
        return $.trim(String(value || '')) !== '';
    }

    function refreshProgress() {
        var $questions = $('#v2PaperContainer .v2-question');
        var answered = $questions.filter(function () { return questionAnswered($(this)); }).length;
        $questions.each(function () { $(this).toggleClass('v2-question-answered', questionAnswered($(this))); });
        $('.v2-progress-count').text(answered + ' of ' + $questions.length + ' answered');
    }

    function refreshSectionRules() {
        $('#v2PaperContainer .v2-section-heading').each(function () {
            var $rule = $(this);
            var sectionId = String($rule.data('section-id'));
            var rule = String($rule.data('answer-rule') || 'all');
            if (rule === 'all') {
                return;
            }
            var maximum = parseInt($rule.data('answer-count') || '0', 10);
            var $eligible = $('#v2PaperContainer .v2-question').filter(function () {
                if (String($(this).data('section-id')) !== sectionId) {
                    return false;
                }
                return rule !== 'compulsory_plus_choice' || parseInt($(this).data('compulsory') || '0', 10) !== 1;
            });
            var answered = $eligible.filter(function () { return questionAnswered($(this)); }).length;
            $rule.find('.v2-section-counter').text(answered + ' / ' + maximum + ' answered')
                .toggleClass('label-danger', answered > maximum)
                .toggleClass('label-info', answered <= maximum);
            $eligible.each(function () {
                var $question = $(this);
                var lock = maximum > 0 && answered >= maximum && !questionAnswered($question);
                $question.find('.v2-answer-control').prop('disabled', lock);
                $question.toggleClass('v2-choice-locked', lock).attr('aria-disabled', lock ? 'true' : 'false');
            });
        });
        refreshProgress();
    }

    function createPayload($question) {
        var previousSequence = parseInt($question.attr('data-client-sequence') || '0', 10);
        var clientSequence = Math.max(Date.now(), previousSequence + 1);
        $question.attr('data-client-sequence', clientSequence);
        return {
            attempt_id: active.attempt_id,
            question_snapshot_id: $question.data('question-id'),
            response: valueFor($question),
            client_sequence: clientSequence,
            workflow_token: token
        };
    }

    function saveQuestion($question) {
        if (!active) {
            return $.Deferred().reject().promise();
        }

        var payload = createPayload($question);
        var $state = $question.find('.v2-save-state').text('Saving…').removeClass('text-danger text-success').addClass('text-muted');
        pendingRequests++;
        refreshSyncState(payload.attempt_id);

        return $.ajax({url: baseurl + 'user/onlineexam/autosave', type: 'POST', data: payload, dataType: 'json'})
            .done(function (data) {
                removeQueued(payload);
                if (data.client_sequence) {
                    $question.attr('data-client-sequence', Math.max(parseInt($question.attr('data-client-sequence') || '0', 10), parseInt(data.client_sequence, 10)));
                }
                $state.text('Saved' + (data.saved_at ? ' ' + data.saved_at : '')).removeClass('text-muted text-danger').addClass('text-success');
            })
            .fail(function (xhr) {
                var response = xhr.responseJSON || {};
                if (xhr.status === 0 || xhr.status >= 500 || response.preserve_local_queue || response.expired) {
                    var queued = queueSave(payload);
                    $state.text(queued ? 'Waiting for connection — kept on this device' : 'Could not save on this device').removeClass('text-muted text-success').addClass('text-danger');
                } else {
                    removeQueued(payload);
                    $state.text((xhr.responseJSON && xhr.responseJSON.message) || 'This answer was rejected. Please reopen the assessment.').removeClass('text-muted text-success').addClass('text-danger');
                }
            })
            .always(function () {
                pendingRequests = Math.max(0, pendingRequests - 1);
                refreshSyncState(payload.attempt_id);
            });
    }

    function queueCurrentAnswers() {
        if (!active || isSubmitting) {
            return;
        }
        $('#v2PaperContainer .v2-question').each(function () {
            queueSave(createPayload($(this)));
        });
        refreshSyncState(active.attempt_id);
    }

    function applyQueuedAnswers(attemptId) {
        var queue = readQueue(attemptId);
        $.each(queue, function (_, payload) {
            var $question = $('#v2PaperContainer .v2-question').filter(function () {
                return String($(this).data('question-id')) === String(payload.question_snapshot_id);
            }).first();
            if ($question.length) {
                applyValue($question, payload.response);
                $question.attr('data-client-sequence', Math.max(parseInt($question.attr('data-client-sequence') || '0', 10), parseInt(payload.client_sequence || '0', 10)));
                $question.find('.v2-save-state').text('Recovered from this device').removeClass('text-danger text-success').addClass('text-muted');
            }
        });
    }

    function flushQueue(attemptId) {
        attemptId = attemptId || (active && active.attempt_id);
        if (!attemptId) {
            return $.Deferred().resolve().promise();
        }

        var queue = readQueue(attemptId);
        var requests = [];
        if ($.isEmptyObject(queue)) {
            refreshSyncState(attemptId);
            return $.Deferred().resolve().promise();
        }

        setSyncState('Retrying saved answers', 'warning');
        $.each(queue, function (_, queuedPayload) {
            var payload = $.extend({}, queuedPayload, {workflow_token: token});
            requests.push($.ajax({url: baseurl + 'user/onlineexam/autosave', type: 'POST', data: payload, dataType: 'json'})
                .done(function () { removeQueued(payload); })
                .fail(function (xhr) {
                    var response = xhr.responseJSON || {};
                    if (xhr.status >= 400 && xhr.status < 500 && !response.preserve_local_queue && !response.expired) {
                        removeQueued(payload);
                    }
                }));
        });

        var combined = requests.length ? $.when.apply($, requests) : $.Deferred().resolve().promise();
        combined.always(function () { refreshSyncState(attemptId); });
        return combined;
    }

    function startTimer(seconds) {
        clearInterval(timerHandle);
        seconds = parseInt(seconds, 10);
        if (isNaN(seconds) || seconds < 0) {
            seconds = 0;
        }
        var displayDeadline = Date.now() + (seconds * 1000);
        var expiryHandled = false;

        function render() {
            var remaining = Math.max(0, Math.ceil((displayDeadline - Date.now()) / 1000));
            var hours = Math.floor(remaining / 3600);
            var minutes = Math.floor((remaining % 3600) / 60);
            var secs = remaining % 60;
            var displayTime = [hours, minutes, secs].map(function (number) { return number < 10 ? '0' + number : number; }).join(':');
            $('#v2PaperTimer').text(displayTime);
            $('#v2TimerState').attr('aria-label', 'Time remaining ' + displayTime);
            $('#v2TimerState').toggleClass('v2-timer-warning', remaining <= 300 && remaining > 60).toggleClass('v2-timer-danger', remaining <= 60);
            if (remaining === 0 && !expiryHandled) {
                expiryHandled = true;
                clearInterval(timerHandle);
                submitPaper(true);
            }
        }

        render();
        timerHandle = setInterval(render, 1000);
    }

    $(document).on('click', '.v2-start-paper', function () {
        var $button = $(this);
        var originalText = $button.html();
        $button.prop('disabled', true).html('<i class="fa fa-spinner fa-spin" aria-hidden="true"></i> Opening…');
        post(baseurl + 'user/onlineexam/startpaper', {onlineexam_id: $button.data('exam-id'), paper_id: $button.data('paper-id')})
            .done(function (data) {
                active = data;
                isSubmitting = false;
                finalSubmissionPending = false;
                finalSubmissionTimedOut = false;
                $('#v2PaperContainer').html(data.page);
                applyQueuedAnswers(active.attempt_id);
                refreshSectionRules();
                refreshSyncState(active.attempt_id);
                $('#v2PaperModal').modal({show: true, backdrop: 'static', keyboard: false});
                $('#v2PaperContainer').focus();
                startTimer(data.remaining_seconds);
                flushQueue(active.attempt_id);
            })
            .fail(function (xhr) {
                message((xhr.responseJSON && xhr.responseJSON.message) || 'The assessment could not be opened.');
            })
            .always(function () {
                $button.prop('disabled', false).html(originalText);
            });
    });

    $(document).on('change input', '.v2-answer-control', function () {
        var $question = $(this).closest('.v2-question');
        var id = $question.data('question-id');
        refreshSectionRules();
        clearTimeout(saveTimers[id]);
        saveTimers[id] = setTimeout(function () { saveQuestion($question); }, 700);
    });

    $(document).on('click', '.v2-clear-answer', function () {
        var $question = $(this).closest('.v2-question');
        $question.find('input[type=radio], input[type=checkbox]').prop('checked', false);
        $question.find('textarea, input[type=number], input[type=text], select').val('');
        refreshSectionRules();
        saveQuestion($question);
    });

    function resetSubmitButton() {
        $('.v2-submit-paper').prop('disabled', false).text('Submit answers');
    }

    function submitPaper(timedOut) {
        if (!active || isSubmitting) {
            return;
        }

        isSubmitting = true;
        finalSubmissionPending = true;
        finalSubmissionTimedOut = Boolean(timedOut || finalSubmissionTimedOut);
        $.each(saveTimers, function (_, saveTimer) { clearTimeout(saveTimer); });
        saveTimers = {};
        $('.v2-submit-paper').prop('disabled', true).text('Submitting…');
        var attemptId = active.attempt_id;
        var paperId = active.paper_id;
        var submissionKey = active.submission_key;
        var finalAnswers = [];
        var storedOnDevice = true;
        $('#v2PaperContainer .v2-question').each(function () {
            var payload = createPayload($(this));
            storedOnDevice = queueSave(payload) && storedOnDevice;
            finalAnswers.push({
                question_snapshot_id: payload.question_snapshot_id,
                response: payload.response,
                client_sequence: payload.client_sequence
            });
        });
        refreshSyncState(attemptId);

        post(baseurl + 'user/onlineexam/submitpaper', {
            attempt_id: attemptId,
            paper_id: paperId,
            submission_key: submissionKey,
            final_answers: JSON.stringify(finalAnswers)
        }).done(function (data) {
            clearInterval(timerHandle);
            clearAttemptQueue(attemptId);
            finalSubmissionPending = false;
            finalSubmissionTimedOut = false;
            active = null;
            var expired = Boolean(data.timed_out || timedOut);
            var confirmation = expired
                ? (data.final_answers_applied === false
                    ? 'Time elapsed. Answers already saved before the deadline were submitted.'
                    : 'Time elapsed. Your final answers were saved and submitted.')
                : 'Your answers were saved and submitted successfully.';
            message(confirmation, 'success');
            setTimeout(function () { window.location.reload(); }, 900);
        }).fail(function (xhr) {
            var response = xhr.responseJSON || {};
            isSubmitting = false;
            finalSubmissionPending = true;
            var fallback = storedOnDevice
                ? 'Your final answers are still kept on this device. Reconnect and retry submission.'
                : 'Your answers could not be submitted. Keep this page open and retry.';
            message(response.message || fallback);
            $('.v2-submit-paper').prop('disabled', false).text('Retry secure submission');
        });
    }

    $(document).on('click', '.v2-submit-paper', function () {
        if (confirm('Submit your answers now? You cannot change them after submission.')) {
            submitPaper(false);
        }
    });

    $('#v2PaperModal').on('hide.bs.modal', function () {
        clearInterval(timerHandle);
        $.each(saveTimers, function (_, saveTimer) { clearTimeout(saveTimer); });
        queueCurrentAnswers();
        flushQueue();
    });

    $(window).on('beforeunload', function () { queueCurrentAnswers(); });
    $(window).on('online', function () {
        if (active && finalSubmissionPending && !isSubmitting) {
            submitPaper(finalSubmissionTimedOut);
        } else {
            flushQueue();
        }
    });
    $(window).on('offline', function () { refreshSyncState(); });
    setInterval(function () { if (active && !isSubmitting && !finalSubmissionPending) { flushQueue(); } }, 10000);
})(jQuery);
</script>
