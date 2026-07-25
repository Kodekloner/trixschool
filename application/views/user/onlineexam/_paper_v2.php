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
?>
<div class="v2-paper" data-attempt-id="<?php echo (int) $attempt->id; ?>" data-paper-id="<?php echo (int) $paper->id; ?>" data-submission-key="<?php echo html_escape($attempt->submission_key); ?>">
    <div class="alert alert-info">
        <strong><?php echo html_escape($paper->title); ?></strong>
        <?php if (!empty($paper->instructions)) { ?>
            <div><?php echo $this->security->xss_clean($paper->instructions); ?></div>
        <?php } ?>
    </div>

    <?php if (empty($questions)) { ?>
        <div class="alert alert-warning">No frozen questions are available for this paper. Please contact the examination officer.</div>
    <?php } ?>

    <?php foreach ($questions as $number => $question) {
        $section_key = $question->paper_section_id ? (string) $question->paper_section_id : 'none';
        if ($section_key !== $current_section) {
            $current_section = $section_key;
            $current_passage = '__first__';
            ?>
                <div class="v2-section-heading" data-section-id="<?php echo (int) $question->paper_section_id; ?>" data-answer-rule="<?php echo html_escape($question->answer_rule); ?>" data-answer-count="<?php echo (int) $question->answer_count; ?>">
                    <?php if (!empty($question->section_title)) { ?><h4><?php echo html_escape($question->section_title); ?></h4><?php } ?>
                    <?php if (!empty($question->section_instructions)) { ?>
                        <p><?php echo $this->security->xss_clean($question->section_instructions); ?></p>
                    <?php } ?>
                    <?php if ($question->answer_rule !== 'all') { ?>
                        <span class="label label-warning">Answer <?php echo (int) $question->answer_count; ?> optional question(s)<?php echo $question->answer_rule === 'compulsory_plus_choice' ? ', plus every compulsory question' : ''; ?>.</span>
                        <span class="label label-info v2-section-counter" aria-live="polite">0 / <?php echo (int) $question->answer_count; ?> selected</span>
                    <?php } ?>
                </div>
            <?php
        }

        $options = $decode($question->options_json, array());
        if (!empty($options['dynamic']) && is_array($options['dynamic'])) {
            foreach ($options['dynamic'] as $dynamic_option) {
                if (isset($dynamic_option['id'], $dynamic_option['option'])) {
                    $options[(string) $dynamic_option['id']] = $dynamic_option['option'];
                }
            }
        }
        $saved = $decode($question->response_json, null);
        $type = strtolower((string) $question->question_type);
        $manual = in_array($type, array('descriptive', 'long_answer', 'file_upload', 'oral', 'aural', 'practical', 'project'), true);
        $passage_key = !empty($question->passage_group_key) ? (string) $question->passage_group_key : '';
        if ($passage_key !== '' && $passage_key !== $current_passage) {
            $current_passage = $passage_key; ?>
            <div class="panel panel-info v2-passage" data-passage-group="<?php echo html_escape($passage_key); ?>">
                <div class="panel-heading"><strong><?php echo html_escape($question->passage_title); ?></strong></div>
                <div class="panel-body"><?php echo $this->security->xss_clean($question->passage_text); ?></div>
            </div>
        <?php } elseif ($passage_key === '') {
            $current_passage = '';
        }
        ?>
        <div class="panel panel-default v2-question" data-question-id="<?php echo (int) $question->id; ?>" data-question-type="<?php echo html_escape($type); ?>" data-section-id="<?php echo (int) $question->paper_section_id; ?>" data-compulsory="<?php echo (int) $question->is_compulsory; ?>" data-has-attachment="<?php echo !empty($question->attachment_name) ? 1 : 0; ?>" data-client-sequence="<?php echo isset($question->answer_client_sequence) ? (int) $question->answer_client_sequence : 0; ?>">
            <div class="panel-heading">
                <strong>Question <?php echo (int) $number + 1; ?><?php echo (int) $question->is_compulsory === 1 ? ' (Compulsory)' : ''; ?></strong>
                <span class="pull-right"><?php echo number_format((float) $question->marks, 2); ?> mark(s)</span>
            </div>
            <div class="panel-body">
                <div class="v2-question-text"><?php echo $question->question_text; ?></div>

                <?php if ($type === 'singlechoice' || $type === 'single_choice' || $type === '') { ?>
                    <?php foreach ((array) $options as $key => $label) {
                        if ($key === 'dynamic') { continue; }
                        $checked = (string) $saved === (string) $key || (string) $saved === 'opt_' . (string) $key;
                        ?>
                        <label class="v2-option"><input class="v2-answer-control" type="radio" name="answer_<?php echo (int) $question->id; ?>" value="<?php echo html_escape($key); ?>" <?php echo $checked ? 'checked' : ''; ?>> <?php echo html_escape($label); ?></label>
                    <?php } ?>
                <?php } elseif ($type === 'true_false' || $type === 'true/false') { ?>
                    <label class="v2-option"><input class="v2-answer-control" type="radio" name="answer_<?php echo (int) $question->id; ?>" value="true" <?php echo strtolower((string) $saved) === 'true' ? 'checked' : ''; ?>> True</label>
                    <label class="v2-option"><input class="v2-answer-control" type="radio" name="answer_<?php echo (int) $question->id; ?>" value="false" <?php echo strtolower((string) $saved) === 'false' ? 'checked' : ''; ?>> False</label>
                <?php } elseif ($type === 'multichoice' || $type === 'multiple_choice') {
                    $selected = is_array($saved) ? $saved : array();
                    foreach ((array) $options as $key => $label) {
                        if ($key === 'dynamic') { continue; }
                        ?>
                        <label class="v2-option"><input class="v2-answer-control" type="checkbox" value="<?php echo html_escape($key); ?>" <?php echo in_array($key, $selected, true) || in_array('opt_' . $key, $selected, true) ? 'checked' : ''; ?>> <?php echo html_escape($label); ?></label>
                    <?php } ?>
                <?php } elseif ($type === 'short_answer' || $type === 'fill_blank') { ?>
                    <input class="form-control v2-answer-control" type="text" value="<?php echo html_escape($saved); ?>" placeholder="Enter your answer">
                <?php } elseif ($type === 'numeric') { ?>
                    <input class="form-control v2-answer-control" type="number" step="any" value="<?php echo html_escape($saved); ?>" placeholder="Enter your numeric answer">
                <?php } elseif ($type === 'ordering' && !empty($options['dynamic'])) {
                    $saved_order = is_array($saved) ? $saved : array();
                    $ordering_choices = $options['dynamic'];
                    usort($ordering_choices, function ($left, $right) use ($question) {
                        $left_id = isset($left['id']) ? $left['id'] : $left['option'];
                        $right_id = isset($right['id']) ? $right['id'] : $right['option'];
                        return strcmp(hash('sha256', $question->id . ':' . $left_id), hash('sha256', $question->id . ':' . $right_id));
                    });
                    foreach ($options['dynamic'] as $position => $option) { ?>
                        <div class="form-group">
                            <label>Position <?php echo (int) $position + 1; ?></label>
                            <select class="form-control v2-answer-control v2-order-control" data-position="<?php echo (int) $position; ?>">
                                <option value="">Select</option>
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
                            <div class="col-sm-6"><label><?php echo html_escape($left_item['label']); ?></label></div>
                            <div class="col-sm-6"><select class="form-control v2-answer-control v2-match-control" data-left-id="<?php echo html_escape($left_item['id']); ?>"><option value="">Select match</option><?php foreach ($right_choices as $right_item) { ?><option value="<?php echo html_escape($right_item['id']); ?>" <?php echo isset($saved_matches[$left_item['id']]) && (string) $saved_matches[$left_item['id']] === (string) $right_item['id'] ? 'selected' : ''; ?>><?php echo html_escape($right_item['label']); ?></option><?php } ?></select></div>
                        </div>
                    <?php } ?>
                <?php } elseif ($type === 'file_upload') { ?>
                    <p class="help-block">Upload your response using the attachment control below.</p>
                <?php } else { ?>
                    <textarea class="form-control v2-answer-control" rows="8" placeholder="Type your answer here"><?php echo html_escape(is_array($saved) ? json_encode($saved) : $saved); ?></textarea>
                <?php } ?>

                <?php if ($manual) { ?>
                    <div class="form-group v2-attachment-group">
                        <label><?php echo $type === 'file_upload' ? 'Response attachment' : (in_array($type, array('oral', 'aural'), true) ? 'Audio response attachment' : 'Optional supporting attachment'); ?></label>
                        <input class="form-control v2-attachment" type="file" accept="<?php echo in_array($type, array('oral', 'aural'), true) ? '.mp3,.mp4' : '.pdf,.docx,.jpg,.jpeg,.png,.txt'; ?>">
                        <?php if (!empty($question->attachment_name)) { ?><p class="help-block">Saved: <?php echo html_escape($question->attachment_name); ?></p><?php } ?>
                    </div>
                <?php } ?>
                <button type="button" class="btn btn-link btn-xs v2-clear-answer"><i class="fa fa-eraser"></i> Clear answer</button>
                <small class="v2-save-state text-muted">Not changed</small>
            </div>
        </div>
    <?php } ?>

    <div class="text-right">
        <button type="button" class="btn btn-success v2-submit-paper" <?php echo empty($questions) ? 'disabled' : ''; ?>>Submit this paper</button>
    </div>
</div>
