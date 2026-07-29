<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title><?php echo html_escape($exam->exam); ?></title>
    <style>
        body{font-family:Arial,sans-serif;color:#111;font-size:13px;line-height:1.45;margin:24px}.school{text-align:center;border-bottom:2px solid #111;padding-bottom:10px}.school h1,.school h2{margin:3px}.meta{width:100%;border-collapse:collapse;margin:12px 0}.meta td{border:1px solid #555;padding:6px}.candidate{margin:15px 0}.candidate span{display:inline-block;border-bottom:1px solid #222;min-width:210px;height:18px;margin-right:20px}.paper{page-break-after:always}.paper:last-child{page-break-after:auto}.instructions{border:1px solid #777;padding:8px;margin:10px 0;background:#fafafa}.passage{border:1px solid #444;padding:10px;margin:12px 0;background:#fcfcfc;page-break-inside:avoid}.passage h4{margin:0 0 6px}.section-title{border-bottom:1px solid #222;margin-top:20px;padding-bottom:5px}.question{margin:13px 0;page-break-inside:avoid}.number{font-weight:bold;display:inline-block;width:26px;vertical-align:top}.question-body{display:inline-block;width:calc(100% - 35px);vertical-align:top}.options{margin:6px 0 0 26px}.option{display:inline-block;width:46%;vertical-align:top;margin:3px 2% 3px 0}.marks{float:right;font-weight:bold}.answer-lines{height:90px;background:repeating-linear-gradient(to bottom,transparent 0,transparent 22px,#bbb 23px)}.toolbar{text-align:right;margin-bottom:12px}@media print{body{margin:0.45in}.toolbar{display:none}}
    </style>
</head>
<body>
    <div class="toolbar"><button onclick="window.print()">Print</button></div>
    <?php foreach ($papers as $paper_index => $paper) { ?>
    <section class="paper">
        <div class="school">
            <h1><?php echo html_escape(isset($setting->name) ? $setting->name : 'School'); ?></h1>
            <?php if (!empty($setting->address)) { ?><div><?php echo html_escape($setting->address); ?></div><?php } ?>
            <h2><?php echo html_escape($exam->exam); ?></h2>
        </div>
        <table class="meta">
            <tr><td><strong>Session:</strong> <?php echo html_escape($exam->session_name); ?></td><td><strong>Term:</strong> <?php echo html_escape(strtoupper($exam->term)); ?></td><td><strong>Class:</strong> <?php echo html_escape($exam->class_name); ?></td></tr>
            <tr><td><strong>Subject:</strong> <?php echo html_escape($exam->subject_name); ?></td><td><strong>Paper:</strong> <?php echo html_escape($paper['title']); ?><?php if ($paper['paper_code']) { ?> (<?php echo html_escape($paper['paper_code']); ?>)<?php } ?></td><td><strong>Time:</strong> <?php echo (int) $paper['duration_minutes']; ?> minutes</td></tr>
        </table>
        <div class="candidate"><strong>Candidate name:</strong> <span></span> <strong>Admission no.:</strong> <span></span></div>
        <?php if ($paper['instructions']) { ?><div class="instructions"><strong>Instructions</strong><br><?php echo nl2br(html_escape(strip_tags($paper['instructions']))); ?></div><?php } ?>

        <?php
        $paper_questions = array_values(array_filter($questions, function ($question) use ($paper) {
            return (int) $question['paper_id'] === (int) $paper['id'];
        }));
        $groups = array();
        foreach ($paper_questions as $question) {
            $group_key = empty($question['paper_section_id']) ? 0 : (int) $question['paper_section_id'];
            if (!isset($groups[$group_key])) {
                $groups[$group_key] = array('title' => $question['section_title'], 'instructions' => $question['section_instructions'], 'answer_rule' => $question['answer_rule'], 'answer_count' => $question['answer_count'], 'questions' => array());
            }
            $groups[$group_key]['questions'][] = $question;
        }
        if (empty($groups)) {
            $groups[0] = array('title' => null, 'instructions' => null, 'answer_rule' => null, 'answer_count' => null, 'questions' => array());
        }
        $question_number = 1;
        foreach ($groups as $group) {
            $current_passage = null;
            if ($group['title']) {
                ?><h3 class="section-title"><?php echo html_escape($group['title']); ?></h3><?php
            }
            if ($group['answer_rule']) {
                $rule = $group['answer_rule'] === 'all' ? 'Answer all questions.' : ($group['answer_rule'] === 'answer_any' ? 'Answer any ' . (int) $group['answer_count'] . ' questions.' : 'Answer all compulsory questions and any ' . (int) $group['answer_count'] . ' other questions.');
                ?><div class="instructions"><?php echo html_escape($rule); ?> <?php echo nl2br(html_escape(strip_tags($group['instructions']))); ?></div><?php
            }
            foreach ($group['questions'] as $question) {
                if (!empty($question['passage_group_key']) && $question['passage_group_key'] !== $current_passage) {
                    $current_passage = $question['passage_group_key']; ?>
                    <div class="passage"><h4><?php echo html_escape($question['passage_title']); ?></h4><?php echo $this->security->xss_clean($question['passage_text']); ?></div>
                <?php } elseif (empty($question['passage_group_key'])) {
                    $current_passage = null;
                }
                ?><div class="question">
                    <span class="marks">[<?php echo number_format($question['marks'], 2); ?> marks]</span>
                    <span class="number"><?php echo $question_number++; ?>.</span>
                    <div class="question-body">
                        <?php echo $this->security->xss_clean($question['question_text']); ?>
                        <?php
                        $option_data = isset($question['options']) && is_array($question['options']) ? $question['options'] : array();
                        if ($question['question_type'] === 'matching' && !empty($option_data['matching_left']) && !empty($option_data['matching_right'])) { ?>
                            <table class="meta"><tr><th>Column A</th><th>Column B</th></tr><?php
                            $row_count = max(count($option_data['matching_left']), count($option_data['matching_right']));
                            for ($match_index = 0; $match_index < $row_count; $match_index++) { ?>
                                <tr><td><?php echo isset($option_data['matching_left'][$match_index]['label']) ? html_escape($option_data['matching_left'][$match_index]['label']) : ''; ?></td><td><?php echo isset($option_data['matching_right'][$match_index]['label']) ? html_escape($option_data['matching_right'][$match_index]['label']) : ''; ?></td></tr>
                            <?php } ?></table>
                        <?php } else {
                        $non_empty_options = array_filter($option_data, function ($option) { return is_scalar($option) && trim(strip_tags((string) $option)) !== ''; });
                        if (!empty($non_empty_options)) { ?>
                            <div class="options"><?php foreach ($non_empty_options as $letter => $option) { ?><div class="option"><strong><?php echo html_escape($letter); ?>.</strong> <?php echo $this->security->xss_clean($option); ?></div><?php } ?></div>
                        <?php } elseif (in_array($question['question_type'], array('descriptive', 'long_answer', 'short_answer', 'numeric', 'practical', 'project'), true)) { ?>
                            <div class="answer-lines"></div>
                        <?php } } ?>
                    </div>
                </div><?php
            }
        }
        ?>
    </section>
    <?php } ?>
</body>
</html>
