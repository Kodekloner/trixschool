<?php
$decode = function ($value, $fallback = array()) {
    if ($value === null || $value === '') {
        return $fallback;
    }

    $decoded = json_decode($value, true);
    return json_last_error() === JSON_ERROR_NONE ? $decoded : $value;
};

$current_section = '__first__';
$current_passage = '__first__';
$question_count = !empty($questions) ? count($questions) : 0;
?>
<div class="v2-paper" data-attempt-id="<?php echo (int) $attempt->id; ?>" data-paper-id="<?php echo (int) $paper->id; ?>" data-submission-key="<?php echo html_escape($attempt->submission_key); ?>">
    <div class="alert alert-info v2-paper-intro">
        <strong class="v2-paper-title"><?php echo html_escape($paper->title); ?></strong>
        <?php if (!empty($paper->instructions)) { ?>
            <div class="v2-paper-instructions"><?php echo $this->security->xss_clean($paper->instructions); ?></div>
        <?php } ?>
    </div>

    <?php if (empty($questions)) { ?>
        <div class="alert alert-warning">No questions are available. Please contact the examination officer.</div>
    <?php } ?>

    <?php foreach ($questions as $number => $question) {
        $section_key = $question->paper_section_id ? (string) $question->paper_section_id : 'none';
        if ($section_key !== $current_section) {
            $current_section = $section_key;
            $current_passage = '__first__';
            $answer_count = (int) $question->answer_count;
            $answer_rule = (string) $question->answer_rule;
            ?>
            <div class="v2-section-heading" data-section-id="<?php echo (int) $question->paper_section_id; ?>" data-answer-rule="<?php echo html_escape($answer_rule); ?>" data-answer-count="<?php echo $answer_count; ?>">
                <?php if (!empty($question->section_title)) { ?><h4><?php echo html_escape($question->section_title); ?></h4><?php } ?>
                <?php if (!empty($question->section_instructions)) { ?>
                    <div class="v2-section-instructions"><?php echo $this->security->xss_clean($question->section_instructions); ?></div>
                <?php } ?>
                <?php if ($answer_rule !== 'all' && $answer_count > 0) { ?>
                    <div class="v2-section-rule">
                        <span class="label label-warning">
                            <?php if ($answer_rule === 'compulsory_plus_choice') { ?>
                                Answer every compulsory question and any <?php echo $answer_count; ?> other question<?php echo $answer_count === 1 ? '' : 's'; ?>.
                            <?php } else { ?>
                                Answer any <?php echo $answer_count; ?> question<?php echo $answer_count === 1 ? '' : 's'; ?> in this section.
                            <?php } ?>
                        </span>
                        <span class="label label-info v2-section-counter" aria-live="polite">0 / <?php echo $answer_count; ?> answered</span>
                    </div>
                <?php } ?>
            </div>
            <?php
        }

        $options = $decode($question->options_json, array());
        if (!is_array($options)) {
            $options = array();
        }
        if (!empty($options['dynamic']) && is_array($options['dynamic'])) {
            foreach ($options['dynamic'] as $dynamic_option) {
                if (isset($dynamic_option['id'], $dynamic_option['option'])) {
                    $options[(string) $dynamic_option['id']] = $dynamic_option['option'];
                }
            }
        }

        $saved = $decode($question->response_json, null);
        $type = strtolower((string) $question->question_type);
        $passage_key = !empty($question->passage_group_key) ? (string) $question->passage_group_key : '';
        if ($passage_key !== '' && $passage_key !== $current_passage) {
            $current_passage = $passage_key;
            ?>
            <div class="panel panel-info v2-passage" data-passage-group="<?php echo html_escape($passage_key); ?>">
                <?php if (!empty($question->passage_title)) { ?><div class="panel-heading"><strong><?php echo html_escape($question->passage_title); ?></strong></div><?php } ?>
                <div class="panel-body"><?php echo $this->security->xss_clean($question->passage_text); ?></div>
            </div>
        <?php } elseif ($passage_key === '') {
            $current_passage = '';
        }
        ?>

        <div class="panel panel-default v2-question" data-question-id="<?php echo (int) $question->id; ?>" data-question-type="<?php echo html_escape($type); ?>" data-section-id="<?php echo (int) $question->paper_section_id; ?>" data-compulsory="<?php echo (int) $question->is_compulsory; ?>" data-client-sequence="<?php echo isset($question->answer_client_sequence) ? (int) $question->answer_client_sequence : 0; ?>">
            <div class="panel-heading v2-question-heading">
                <strong>Question <?php echo (int) $number + 1; ?><?php echo (int) $question->is_compulsory === 1 ? ' (Compulsory)' : ''; ?></strong>
                <span class="v2-question-marks"><?php echo number_format((float) $question->marks, 2); ?> mark<?php echo (float) $question->marks === 1.0 ? '' : 's'; ?></span>
            </div>
            <div class="panel-body">
                <div class="v2-question-text"><?php echo $question->question_text; ?></div>

                <?php if ($type === 'singlechoice' || $type === 'single_choice' || $type === '') { ?>
                    <?php foreach ($options as $key => $label) {
                        if ($key === 'dynamic' || is_array($label)) {
                            continue;
                        }
                        $checked = (string) $saved === (string) $key || (string) $saved === 'opt_' . (string) $key;
                        ?>
                        <label class="v2-option"><input class="v2-answer-control" type="radio" name="answer_<?php echo (int) $question->id; ?>" value="<?php echo html_escape($key); ?>" <?php echo $checked ? 'checked' : ''; ?>> <span><?php echo html_escape($label); ?></span></label>
                    <?php } ?>
                <?php } elseif ($type === 'true_false' || $type === 'true/false') { ?>
                    <label class="v2-option"><input class="v2-answer-control" type="radio" name="answer_<?php echo (int) $question->id; ?>" value="true" <?php echo strtolower((string) $saved) === 'true' ? 'checked' : ''; ?>> <span>True</span></label>
                    <label class="v2-option"><input class="v2-answer-control" type="radio" name="answer_<?php echo (int) $question->id; ?>" value="false" <?php echo strtolower((string) $saved) === 'false' ? 'checked' : ''; ?>> <span>False</span></label>
                <?php } elseif ($type === 'multichoice' || $type === 'multiple_choice') {
                    $selected = is_array($saved) ? $saved : array();
                    foreach ($options as $key => $label) {
                        if ($key === 'dynamic' || is_array($label)) {
                            continue;
                        }
                        ?>
                        <label class="v2-option"><input class="v2-answer-control" type="checkbox" value="<?php echo html_escape($key); ?>" <?php echo in_array($key, $selected, true) || in_array('opt_' . $key, $selected, true) ? 'checked' : ''; ?>> <span><?php echo html_escape($label); ?></span></label>
                    <?php } ?>
                <?php } elseif ($type === 'short_answer' || $type === 'fill_blank') { ?>
                    <label class="sr-only" for="answer_<?php echo (int) $question->id; ?>">Answer to question <?php echo (int) $number + 1; ?></label>
                    <input id="answer_<?php echo (int) $question->id; ?>" class="form-control v2-answer-control" type="text" value="<?php echo html_escape(is_array($saved) ? '' : $saved); ?>" placeholder="Enter your answer" autocomplete="off">
                <?php } elseif ($type === 'numeric') { ?>
                    <label class="sr-only" for="answer_<?php echo (int) $question->id; ?>">Numeric answer to question <?php echo (int) $number + 1; ?></label>
                    <input id="answer_<?php echo (int) $question->id; ?>" class="form-control v2-answer-control" type="number" step="any" inputmode="decimal" value="<?php echo html_escape(is_array($saved) ? '' : $saved); ?>" placeholder="Enter your numeric answer" autocomplete="off">
                <?php } elseif ($type === 'ordering' && !empty($options['dynamic']) && is_array($options['dynamic'])) {
                    $saved_order = is_array($saved) ? $saved : array();
                    $ordering_choices = $options['dynamic'];
                    usort($ordering_choices, function ($left, $right) use ($question) {
                        $left_id = isset($left['id']) ? $left['id'] : $left['option'];
                        $right_id = isset($right['id']) ? $right['id'] : $right['option'];
                        return strcmp(hash('sha256', $question->id . ':' . $left_id), hash('sha256', $question->id . ':' . $right_id));
                    });
                    foreach ($options['dynamic'] as $position => $option) { ?>
                        <div class="form-group v2-order-row">
                            <label for="order_<?php echo (int) $question->id; ?>_<?php echo (int) $position; ?>">Position <?php echo (int) $position + 1; ?></label>
                            <select id="order_<?php echo (int) $question->id; ?>_<?php echo (int) $position; ?>" class="form-control v2-answer-control v2-order-control" data-position="<?php echo (int) $position; ?>">
                                <option value="">Select an item</option>
                                <?php foreach ($ordering_choices as $choice) {
                                    $choice_id = isset($choice['id']) ? $choice['id'] : $choice['option']; ?>
                                    <option value="<?php echo html_escape($choice_id); ?>" <?php echo isset($saved_order[$position]) && (string) $saved_order[$position] === (string) $choice_id ? 'selected' : ''; ?>><?php echo html_escape($choice['option']); ?></option>
                                <?php } ?>
                            </select>
                        </div>
                    <?php } ?>
                <?php } elseif ($type === 'matching' && !empty($options['matching_left']) && !empty($options['matching_right'])) {
                    $saved_matches = is_array($saved) ? $saved : array();
                    $right_choices = $options['matching_right'];
                    usort($right_choices, function ($left, $right) use ($question) {
                        return strcmp(hash('sha256', $question->id . ':' . $left['id']), hash('sha256', $question->id . ':' . $right['id']));
                    });
                    foreach ($options['matching_left'] as $left_item) { ?>
                        <div class="row form-group v2-match-row" data-left-id="<?php echo html_escape($left_item['id']); ?>">
                            <div class="col-sm-6 v2-match-label"><strong><?php echo html_escape($left_item['label']); ?></strong></div>
                            <div class="col-sm-6">
                                <select class="form-control v2-answer-control v2-match-control" data-left-id="<?php echo html_escape($left_item['id']); ?>" aria-label="Select a match for <?php echo html_escape($left_item['label']); ?>">
                                    <option value="">Select match</option>
                                    <?php foreach ($right_choices as $right_item) { ?>
                                        <option value="<?php echo html_escape($right_item['id']); ?>" <?php echo isset($saved_matches[$left_item['id']]) && (string) $saved_matches[$left_item['id']] === (string) $right_item['id'] ? 'selected' : ''; ?>><?php echo html_escape($right_item['label']); ?></option>
                                    <?php } ?>
                                </select>
                            </div>
                        </div>
                    <?php } ?>
                <?php } elseif ($type === 'long_answer') { ?>
                    <label class="sr-only" for="answer_<?php echo (int) $question->id; ?>">Written answer to question <?php echo (int) $number + 1; ?></label>
                    <textarea id="answer_<?php echo (int) $question->id; ?>" class="form-control v2-answer-control" rows="8" placeholder="Type your answer here"><?php echo html_escape(is_array($saved) ? json_encode($saved) : $saved); ?></textarea>
                <?php } else { ?>
                    <div class="alert alert-danger v2-unsupported-question" role="alert">This historical question type cannot be answered in the current online assessment. Please contact the examination officer.</div>
                <?php } ?>

                <?php if (in_array($type, array('singlechoice', 'single_choice', 'true_false', 'true/false', 'multichoice', 'multiple_choice', 'short_answer', 'fill_blank', 'numeric', 'ordering', 'matching', 'long_answer'), true) || $type === '') { ?>
                <div class="v2-question-actions">
                    <button type="button" class="btn btn-default btn-sm v2-clear-answer"><i class="fa fa-eraser" aria-hidden="true"></i> Clear answer</button>
                    <small class="v2-save-state text-muted" role="status" aria-live="polite">No changes yet</small>
                </div>
                <?php } ?>
            </div>
        </div>
    <?php } ?>

    <div class="v2-submit-bar">
        <div class="v2-progress-summary" role="status" aria-live="polite">
            <strong class="v2-progress-count">0 of <?php echo $question_count; ?> answered</strong>
            <span>Check your answers before submitting.</span>
        </div>
        <button type="button" class="btn btn-success btn-lg v2-submit-paper" <?php echo empty($questions) ? 'disabled' : ''; ?>>Submit answers</button>
    </div>
</div>
