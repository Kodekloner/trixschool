<?php
define('BASEPATH', __DIR__);
require_once __DIR__ . '/../application/libraries/Onlineexam_review.php';
function review_state_assert($expected, $actual, $message)
{
    if ($expected !== $actual) {
        throw new RuntimeException($message . ': ' . var_export($actual, true));
    }
}
$review = new Onlineexam_review();
$now = strtotime('2026-09-07 12:00:00');
$choice_progress = $review->requirementProgress(array(
    array('section_id' => 7, 'is_compulsory' => 0, 'question_count' => 4, 'answered_count' => 2),
), array(array('id' => 7, 'answer_rule' => 'answer_any', 'answer_count' => 2)));
review_state_assert(true, $choice_progress['met'], 'Answer-any completion must use the required count, not every optional question');
$mixed_progress = $review->requirementProgress(array(
    array('section_id' => 8, 'is_compulsory' => 1, 'question_count' => 2, 'answered_count' => 2),
    array('section_id' => 8, 'is_compulsory' => 0, 'question_count' => 3, 'answered_count' => 1),
), array(array('id' => 8, 'answer_rule' => 'compulsory_plus_choice', 'answer_count' => 1)));
review_state_assert(true, $mixed_progress['met'], 'Compulsory-plus-choice completion must include all compulsory and the selected optional count');
$paper = array('raw_score' => 12, 'raw_max_score' => 20, 'question_count' => 4, 'answered_count' => 0,
    'effective_starts_at' => '2026-09-07 10:00:00', 'effective_ends_at' => '2026-09-07 11:00:00');
review_state_assert('unassigned', $review->describe($paper, false, $now)['key'], 'Enrollment without assignment must stay unassigned');
review_state_assert('missed', $review->describe($paper, true, $now)['key'], 'Never-started assigned closed paper is missed');
$paper['attempt_status'] = 'completed';
$paper['completion_source'] = 'timed_out';
review_state_assert('missed', $review->describe($paper, true, $now)['key'], 'Unanswered timeout stays missed after finalization');
$paper['answered_count'] = 2;
review_state_assert('incomplete', $review->describe($paper, true, $now)['key'], 'Partial timed-out paper is incomplete');
$paper['requirements_met'] = true;
review_state_assert('completed', $review->describe($paper, true, $now)['key'], 'A timed-out answer-any paper is complete when its frozen requirement is met');
unset($paper['requirements_met']);
$paper['answered_count'] = 4;
review_state_assert('completed', $review->describe($paper, true, $now)['key'], 'Fully answered timeout is complete');
$paper['manual_marking_status'] = 'pending';
review_state_assert('marking', $review->describe($paper, true, $now)['key'], 'Finished theory cannot display an unfinalized score');
review_state_assert(false, $review->describe($paper, true, $now)['complete'], 'Pending marking blocks deletion');
$paper['completion_source'] = 'manual';
$paper['manual_marking_status'] = 'finalized';
$paper['answered_count'] = 0;
review_state_assert('12.00 / 20.00', $review->describe($paper, true, $now)['label'], 'Manual totals do not need online answers');
$paper['completion_source'] = 'submitted';
review_state_assert(false, $review->describe($paper, true, $now)['recoverable'], 'An intentional submitted paper cannot be replaced');
$paper['completion_source'] = null;
$paper['deadline_at'] = '2026-09-07 11:00:00';
$paper['submitted_at'] = '2026-09-07 11:02:00';
review_state_assert('missed', $review->describe($paper, true, $now)['key'], 'Historical timeout metadata is recognized');
$paper['attempt_status'] = 'pending';
$paper['is_rescheduled'] = true;
$paper['effective_starts_at'] = '2026-09-07 13:00:00';
$paper['effective_ends_at'] = '2026-09-07 14:00:00';
review_state_assert('rescheduled', $review->describe($paper, true, $now)['key'], 'New individual window supersedes the old closed window');
$paper['attempt_status'] = 'in_progress';
$paper['deadline_at'] = '2026-09-07 12:30:00';
review_state_assert(false, $review->describe($paper, true, $now)['recoverable'], 'A running paper cannot be rescheduled');
echo "Online Examination review status tests passed.\n";
