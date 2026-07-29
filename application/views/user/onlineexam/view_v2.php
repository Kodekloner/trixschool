<div class="content-wrapper">
    <section class="content-header">
        <h1><i class="fa fa-laptop"></i> Nigerian Online Assessment</h1>
    </section>
    <section class="content">
        <div class="box box-primary">
            <div class="box-header with-border">
                <h3 class="box-title"><?php echo html_escape($exam->exam); ?></h3>
            </div>
            <div class="box-body">
                <div class="row">
                    <div class="col-md-8">
                        <dl class="dl-horizontal">
                            <dt>Session / Term</dt><dd><?php echo html_escape($exam->term); ?> term</dd>
                            <dt>Purpose</dt><dd><?php echo html_escape(ucwords(str_replace('_', ' ', $exam->purpose))); ?></dd>
                            <dt>Result destination</dt><dd><?php echo html_escape(ucwords(str_replace('_', ' ', $exam->result_adapter))); ?><?php echo $exam->target_component ? ' — ' . html_escape(strtoupper($exam->target_component)) : ''; ?></dd>
                            <dt>Assessment window</dt><dd><?php echo $this->customlib->dateyyyymmddToDateTimeformat($exam->exam_from, false); ?> — <?php echo $this->customlib->dateyyyymmddToDateTimeformat($exam->exam_to, false); ?></dd>
                        </dl>
                    </div>
                    <div class="col-md-4">
                        <div class="well well-sm">
                            <strong><?php echo html_escape($this->customlib->getFullname($student['firstname'], $student['middlename'], $student['lastname'], $sch_setting->middlename, $sch_setting->lastname)); ?></strong><br>
                            <?php echo html_escape($student['admission_no']); ?><br>
                            <?php echo html_escape($student['class'] . ' (' . $student['section'] . ')'); ?>
                        </div>
                    </div>
                </div>

                <?php if (!empty($exam->description)) { ?>
                    <div class="alert alert-info"><?php echo $this->security->xss_clean($exam->description); ?></div>
                <?php } ?>

                <?php if ($attempt && in_array($attempt->status, array('submitted', 'marking'), true)) { ?>
                    <div class="alert alert-warning">Your online papers were submitted. <?php echo $attempt->status === 'marking' ? 'Manual marking is still in progress.' : 'The result is being finalized.'; ?></div>
                <?php } elseif ($attempt && $attempt->status === 'completed') { ?>
                    <div class="alert alert-success">
                        This assessment is complete.
                        <?php if ($exam->feedback_status === 'released' && $attempt->final_score !== null) { ?>
                            Final score: <strong><?php echo number_format((float) $attempt->final_score, 2); ?><?php echo $exam->target_max_score ? ' / ' . number_format((float) $exam->target_max_score, 2) : ''; ?></strong>
                        <?php } else { ?>
                            Detailed feedback has not been released. Official report-card publication remains separate.
                        <?php } ?>
                    </div>
                <?php } ?>

                <?php if (!empty($released_feedback['questions'])) {
                    $feedback_value = function ($value) {
                        if ($value === null || $value === '') { return 'No answer'; }
                        if (is_bool($value)) { return $value ? 'True' : 'False'; }
                        if (is_array($value)) { return json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); }
                        return (string) $value;
                    }; ?>
                    <div class="box box-success">
                        <div class="box-header with-border"><h3 class="box-title">Released answer feedback</h3></div>
                        <div class="box-body table-responsive">
                            <table class="table table-bordered table-condensed">
                                <thead><tr><th>Paper / question</th><th>Your answer</th><th>Expected answer / scheme</th><th>Mark</th></tr></thead>
                                <tbody>
                                <?php foreach ($released_feedback['questions'] as $feedback_question) { ?>
                                    <tr>
                                        <td><strong><?php echo html_escape($feedback_question['paper_title']); ?></strong><?php echo !empty($feedback_question['section_title']) ? ' / ' . html_escape($feedback_question['section_title']) : ''; ?><br><?php echo nl2br(html_escape(strip_tags($feedback_question['question_text']))); ?></td>
                                        <td><pre style="white-space:pre-wrap"><?php echo html_escape($feedback_value($feedback_question['response'])); ?></pre></td>
                                        <td>
                                            <?php if ($feedback_question['correct_answer'] !== null && $feedback_question['correct_answer'] !== '') { ?><div><?php echo html_escape($feedback_value($feedback_question['correct_answer'])); ?></div><?php } ?>
                                            <?php if (!empty($feedback_question['marking_scheme'])) { ?><small><?php echo nl2br(html_escape(strip_tags($feedback_question['marking_scheme']))); ?></small><?php } ?>
                                        </td>
                                        <td><?php echo number_format((float) $feedback_question['final_mark'], 2); ?> / <?php echo number_format((float) $feedback_question['marks'], 2); ?></td>
                                    </tr>
                                <?php } ?>
                                </tbody>
                            </table>
                            <p class="help-block">This answer review is separate from official report-card publication.</p>
                        </div>
                    </div>
                <?php } ?>

                <h4>Assessment papers</h4>
                <div class="table-responsive">
                    <table class="table table-bordered table-striped">
                        <thead><tr><th>Paper</th><th>Type</th><th>Delivery</th><th>Schedule</th><th>Duration</th><th>Status</th><th class="text-right">Action</th></tr></thead>
                        <tbody>
                        <?php foreach ($papers as $paper) {
                            $paper_status = $paper->attempt_paper_status ?: 'not started';
                            $now = time();
                            $starts = $paper->starts_at ? strtotime($paper->starts_at) : strtotime($exam->exam_from);
                            $is_makeup = $attempt && (int) $attempt->attempt_no > 1 && !empty($exam->makeup_expires_at);
                            $ends = $is_makeup ? strtotime($exam->makeup_expires_at) : ($paper->ends_at ? strtotime($paper->ends_at) : strtotime($exam->exam_to));
                            if ($ends && !$is_makeup && !empty($exam->accommodation_extra_time_minutes)) { $ends += (int) $exam->accommodation_extra_time_minutes * 60; }
                            $available = (!$starts || $now >= $starts) && (!$ends || $now < $ends);
                            ?>
                            <tr>
                                <td><?php echo html_escape($paper->title); ?><?php echo $paper->paper_code ? ' (' . html_escape($paper->paper_code) . ')' : ''; ?></td>
                                <td><?php echo html_escape(ucfirst($paper->paper_type)); ?></td>
                                <td><?php echo html_escape(strtoupper($paper->delivery_mode)); ?></td>
                                <td><?php echo $paper->starts_at ? $this->customlib->dateyyyymmddToDateTimeformat($paper->starts_at, false) : 'Assessment window'; ?></td>
                                <td><?php echo (int) $paper->duration_minutes; ?> minutes</td>
                                <td><?php echo html_escape(ucwords(str_replace('_', ' ', $paper_status))); ?></td>
                                <td class="text-right">
                                    <?php if (in_array($paper->delivery_mode, array('cbt', 'hybrid'), true) && $paper_status !== 'submitted' && (!$attempt || !in_array($attempt->status, array('submitted', 'marking', 'completed'), true))) { ?>
                                        <button type="button" class="btn btn-primary btn-xs v2-start-paper" data-exam-id="<?php echo (int) $exam->id; ?>" data-paper-id="<?php echo (int) $paper->id; ?>" <?php echo $available ? '' : 'disabled'; ?>><?php echo $paper_status === 'in_progress' ? 'Resume' : 'Start'; ?></button>
                                    <?php } elseif ($paper->delivery_mode === 'paper') { ?>
                                        <span class="text-muted">Completed at school</span>
                                    <?php } ?>
                                </td>
                            </tr>
                        <?php } ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </section>
</div>

<div id="v2PaperModal" class="modal fade" role="dialog">
    <div class="modal-dialog modal-dialogfullwidth">
        <div class="modal-content modal-contentfull">
            <div class="modal-header">
                <button type="button" class="close" data-dismiss="modal" aria-label="Save and leave"><span>&times;</span></button>
                <h4 class="modal-title">Assessment paper <span class="pull-right"><i class="fa fa-clock-o"></i> <span id="v2PaperTimer">--:--:--</span></span></h4>
            </div>
            <div class="modal-body" id="v2PaperContainer"></div>
        </div>
    </div>
</div>

<style>
.v2-option{display:block;padding:8px;border:1px solid #eee;margin:5px 0;border-radius:3px;font-weight:normal}.v2-option:hover{background:#f7f7f7}.v2-section-heading{border-left:4px solid #3c8dbc;padding:8px 12px;margin:20px 0 10px;background:#f5f5f5}.v2-question-text{font-size:16px;margin-bottom:15px}.v2-save-state{display:block;margin-top:8px}.modal-dialogfullwidth{width:96%;margin:15px auto}.modal-contentfull{min-height:94vh}
</style>

<script>
(function ($) {
    'use strict';
    var token = <?php echo json_encode($workflow_token); ?>;
    var timerHandle = null;
    var active = null;
    var saveTimers = {};
    var queuePrefix = 'onlineexam-v2-save-queue:<?php echo (int) $assignment->student_session_id; ?>:';

    function message(text, type) {
        if (typeof successMsg === 'function' && type === 'success') { successMsg(text); return; }
        if (typeof errorMsg === 'function' && type !== 'success') { errorMsg(text); return; }
        alert(text);
    }

    function post(url, data) {
        data.workflow_token = token;
        return $.ajax({url: url, type: 'POST', data: data, dataType: 'json'});
    }

    function queueKey(attemptId) { return queuePrefix + parseInt(attemptId || 0, 10); }
    function readQueue(attemptId) {
        try { return JSON.parse(localStorage.getItem(queueKey(attemptId)) || '{}'); } catch (e) { return {}; }
    }
    function writeQueue(attemptId, queue) {
        var key = queueKey(attemptId);
        if ($.isEmptyObject(queue)) { localStorage.removeItem(key); } else { localStorage.setItem(key, JSON.stringify(queue)); }
    }
    function queueSave(payload) {
        payload = $.extend({}, payload);
        delete payload.workflow_token;
        var queue = readQueue(payload.attempt_id);
        queue[payload.attempt_id + ':' + payload.question_snapshot_id] = payload;
        writeQueue(payload.attempt_id, queue);
    }
    function removeQueued(payload) {
        var queue = readQueue(payload.attempt_id);
        delete queue[payload.attempt_id + ':' + payload.question_snapshot_id];
        writeQueue(payload.attempt_id, queue);
    }
    function clearAttemptQueue(attemptId) { localStorage.removeItem(queueKey(attemptId)); }

    function valueFor($question) {
        var type = $question.data('question-type');
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

    function questionAnswered($question) {
        if (parseInt($question.attr('data-has-attachment') || '0', 10) === 1) { return true; }
        var value = valueFor($question);
        if ($.isArray(value)) {
            return $.grep(value, function (item) { return $.trim(String(item || '')) !== ''; }).length > 0;
        }
        if ($.isPlainObject(value)) {
            var answered = false;
            $.each(value, function (_, item) { if ($.trim(String(item || '')) !== '') { answered = true; } });
            return answered;
        }
        return $.trim(String(value || '')) !== '';
    }

    function refreshSectionRules() {
        $('.v2-section-heading').each(function () {
            var $rule = $(this), sectionId = String($rule.data('section-id'));
            var rule = String($rule.data('answer-rule') || 'all');
            if (rule === 'all') { return; }
            var maximum = parseInt($rule.data('answer-count') || '0', 10);
            var $eligible = $('.v2-question').filter(function () {
                if (String($(this).data('section-id')) !== sectionId) { return false; }
                return rule !== 'compulsory_plus_choice' || parseInt($(this).data('compulsory') || '0', 10) !== 1;
            });
            var answered = $eligible.filter(function () { return questionAnswered($(this)); }).length;
            $rule.find('.v2-section-counter').text(answered + ' / ' + maximum + ' selected')
                .toggleClass('label-danger', answered > maximum).toggleClass('label-info', answered <= maximum);
            $eligible.each(function () {
                var $question = $(this), lock = maximum > 0 && answered >= maximum && !questionAnswered($question);
                $question.find('.v2-answer-control, .v2-attachment').prop('disabled', lock);
                $question.toggleClass('v2-choice-locked', lock);
            });
        });
    }

    function saveQuestion($question) {
        if (!active) { return $.Deferred().reject().promise(); }
        var previousSequence = parseInt($question.attr('data-client-sequence') || '0', 10);
        var clientSequence = Math.max(Date.now(), previousSequence + 1);
        $question.attr('data-client-sequence', clientSequence);
        var payload = {
            attempt_id: active.attempt_id,
            question_snapshot_id: $question.data('question-id'),
            response: valueFor($question),
            client_sequence: clientSequence,
            workflow_token: token
        };
        var $state = $question.find('.v2-save-state').text('Saving…').removeClass('text-danger text-success').addClass('text-muted');
        return $.ajax({url: baseurl + 'user/onlineexam/autosave', type: 'POST', data: payload, dataType: 'json'})
            .done(function (data) {
                removeQueued(payload);
                if (data.client_sequence) {
                    $question.attr('data-client-sequence', Math.max(parseInt($question.attr('data-client-sequence') || '0', 10), parseInt(data.client_sequence, 10)));
                }
                $state.text('Saved ' + data.saved_at).removeClass('text-muted text-danger').addClass('text-success');
            })
            .fail(function (xhr) {
                if (xhr.status === 0 || xhr.status >= 500) {
                    queueSave(payload);
                    $state.text('Waiting for connection — kept on this device').removeClass('text-muted text-success').addClass('text-danger');
                } else {
                    removeQueued(payload);
                    $state.text((xhr.responseJSON && xhr.responseJSON.message) || 'This answer was rejected; reload the paper.').removeClass('text-muted text-success').addClass('text-danger');
                }
            });
    }

    function flushQueue(attemptId) {
        attemptId = attemptId || (active && active.attempt_id);
        if (!attemptId) { return $.Deferred().resolve().promise(); }
        var queue = readQueue(attemptId), requests = [];
        $.each(queue, function (_, payload) {
            payload.workflow_token = token;
            requests.push($.ajax({url: baseurl + 'user/onlineexam/autosave', type: 'POST', data: payload, dataType: 'json'})
                .done(function () { removeQueued(payload); })
                .fail(function (xhr) { if (xhr.status >= 400 && xhr.status < 500) { removeQueued(payload); } }));
        });
        return requests.length ? $.when.apply($, requests) : $.Deferred().resolve().promise();
    }

    function startTimer(seconds) {
        clearInterval(timerHandle);
        function render() {
            seconds = Math.max(0, seconds);
            var h = Math.floor(seconds / 3600), m = Math.floor((seconds % 3600) / 60), s = seconds % 60;
            $('#v2PaperTimer').text([h, m, s].map(function (n) { return n < 10 ? '0' + n : n; }).join(':'));
            if (seconds === 0) { clearInterval(timerHandle); submitPaper(true); }
            seconds--;
        }
        render(); timerHandle = setInterval(render, 1000);
    }

    $(document).on('click', '.v2-start-paper', function () {
        var $button = $(this).prop('disabled', true);
        post(baseurl + 'user/onlineexam/startpaper', {onlineexam_id: $button.data('exam-id'), paper_id: $button.data('paper-id')})
            .done(function (data) {
                active = data;
                $('#v2PaperContainer').html(data.page);
                $('#v2PaperModal').modal({show: true, backdrop: 'static', keyboard: false});
                startTimer(parseInt(data.remaining_seconds, 10));
                refreshSectionRules();
                flushQueue(active.attempt_id);
            })
            .fail(function (xhr) { message((xhr.responseJSON && xhr.responseJSON.message) || 'The paper could not be started.'); })
            .always(function () { $button.prop('disabled', false); });
    });

    $(document).on('change input', '.v2-answer-control', function () {
        var $question = $(this).closest('.v2-question'), id = $question.data('question-id');
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

    $(document).on('change', '.v2-attachment', function () {
        if (!active || !this.files.length) { return; }
        var $question = $(this).closest('.v2-question'), form = new FormData();
        form.append('workflow_token', token); form.append('attempt_id', active.attempt_id);
        form.append('question_snapshot_id', $question.data('question-id')); form.append('attachment', this.files[0]);
        $question.find('.v2-save-state').text('Uploading attachment…');
        $.ajax({url: baseurl + 'user/onlineexam/uploadanswer', type: 'POST', data: form, dataType: 'json', processData: false, contentType: false})
            .done(function (data) { $question.attr('data-has-attachment', '1'); refreshSectionRules(); $question.find('.v2-save-state').text('Attachment saved ' + data.saved_at).addClass('text-success'); })
            .fail(function (xhr) { $question.find('.v2-save-state').text((xhr.responseJSON && xhr.responseJSON.message) || 'Attachment failed').addClass('text-danger'); });
    });

    function submitPaper(timedOut) {
        if (!active) { return; }
        $('.v2-submit-paper').prop('disabled', true).text('Submitting…');
        var sendSubmission = function () {
            post(baseurl + 'user/onlineexam/submitpaper', {attempt_id: active.attempt_id, paper_id: active.paper_id, submission_key: active.submission_key})
                .done(function () { clearInterval(timerHandle); clearAttemptQueue(active.attempt_id); message(timedOut ? 'Time elapsed and the paper was submitted.' : 'Paper submitted successfully.', 'success'); setTimeout(function () { window.location.reload(); }, 900); })
                .fail(function (xhr) { message((xhr.responseJSON && xhr.responseJSON.message) || 'The paper could not be submitted.'); $('.v2-submit-paper').prop('disabled', false).text('Submit this paper'); });
        };
        var saveFailed = function () {
            if (timedOut) {
                // The server will retain every previously saved answer and
                // create zero-score rows for unanswered questions.
                sendSubmission();
                return;
            }
            message('One or more answers are still waiting to save. Reconnect and submit again so no answer is lost.');
            $('.v2-submit-paper').prop('disabled', false).text('Submit this paper');
        };
        var saves = [];
        $('.v2-question').each(function () { saves.push(saveQuestion($(this))); });
        $.when.apply($, saves).done(function () {
            flushQueue(active.attempt_id).done(sendSubmission).fail(saveFailed);
        }).fail(saveFailed);
    }
    $(document).on('click', '.v2-submit-paper', function () {
        if (confirm('Submit this paper? You cannot change its answers afterwards.')) { submitPaper(false); }
    });
    $(window).on('online', function () { flushQueue(); });
    setInterval(function () { flushQueue(); }, 10000);
})(jQuery);
</script>
