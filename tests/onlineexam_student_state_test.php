<?php
define('BASEPATH', __DIR__);
require __DIR__ . '/../application/helpers/onlineexam_student_helper.php';

function student_state_assert($condition, $message)
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

$exam = (object) array('exam_from' => '2026-09-06 10:00:00', 'exam_to' => '2026-09-06 11:00:00', 'is_active' => 1,
    'lifecycle_status' => 'published', 'feedback_status' => 'held', 'target_max_score' => 20, 'passing_percentage' => 50);
$paper = (object) array('delivery_mode' => 'cbt');
$during = strtotime('2026-09-06 10:30:00');
$after = strtotime('2026-09-06 11:00:00');
$state = onlineexam_student_paper_state($exam, $paper, null, $during);
student_state_assert($state['can_launch'] && $state['action'] === 'Start', 'Assigned paper is startable during its window.');
$exam->lifecycle_status = 'in_progress';
student_state_assert(onlineexam_student_paper_state($exam, $paper, null, $during)['can_launch'], 'Starting one paper or student does not block the next paper/student when the assessment becomes in_progress.');
$exam->lifecycle_status = 'completed';
student_state_assert(!onlineexam_student_paper_state($exam, $paper, null, $during)['can_launch'], 'A completed assessment without a personal schedule is not launchable.');
$exam->lifecycle_status = 'published';
$state = onlineexam_student_paper_state($exam, $paper, null, $after);
student_state_assert(!$state['can_launch'] && $state['key'] === 'missed', 'Unstarted closed paper is missed and has no Start action.');
$paper->attempt_paper_status = 'in_progress';
$attempt = (object) array('status' => 'in_progress', 'final_score' => null);
$state = onlineexam_student_paper_state($exam, $paper, $attempt, $after);
student_state_assert(!$state['can_launch'] && $state['key'] === 'incomplete', 'Closed ongoing paper has no Resume action.');
$paper->attempt_paper_deadline = '2026-09-06 10:20:00';
student_state_assert(!onlineexam_student_paper_state($exam, $paper, $attempt, $during)['can_launch'], 'An elapsed personal deadline prevents Resume even inside the general window.');
$paper->attempt_paper_status = 'pending';
$paper->effective_starts_at = '2026-09-07 10:00:00';
$paper->effective_ends_at = '2026-09-07 11:00:00';
$paper->is_rescheduled = true;
$state = onlineexam_student_paper_state($exam, $paper, $attempt, strtotime('2026-09-07 10:05:00'));
student_state_assert($state['can_launch'], 'A rescheduled candidate paper uses its new window after the original assessment ends.');
foreach (array('in_progress', 'marking', 'completed') as $lifecycle) {
    $exam->lifecycle_status = $lifecycle;
    student_state_assert(onlineexam_student_paper_state($exam, $paper, $attempt, strtotime('2026-09-07 10:05:00'))['can_launch'], 'A rescheduled open paper matches server eligibility while assessment lifecycle is ' . $lifecycle . '.');
}
$exam->lifecycle_status = 'published';
$state = onlineexam_student_paper_state($exam, $paper, $attempt, $after);
student_state_assert(!$state['can_launch'] && $state['label'] === 'Rescheduled', 'A future rescheduled paper does not expose Start yet.');
$exam->deleted_at = '2026-09-06 09:00:00';
student_state_assert(!onlineexam_student_paper_state($exam, $paper, $attempt, strtotime('2026-09-07 10:05:00'))['can_launch'], 'Deleted assessments never expose Start.');
unset($exam->deleted_at);
$paper->effective_starts_at = 'invalid';
student_state_assert(!onlineexam_student_paper_state($exam, $paper, $attempt, $during)['can_launch'], 'Malformed schedules are not launchable.');
$attempt->status = 'completed';
$attempt->final_score = 10;
$result = onlineexam_student_result($exam, $attempt);
student_state_assert(!$result['visible'] && $result['score'] === null && $result['outcome'] === null, 'Held feedback never leaks score or pass/fail.');
$exam->feedback_status = 'released';
$result = onlineexam_student_result($exam, $attempt);
student_state_assert($result['visible'] && $result['outcome'] === 'Pass', 'Released component score uses its actual maximum for pass percentage.');
$attempt->final_score = 9.99;
student_state_assert(onlineexam_student_result($exam, $attempt)['outcome'] === 'Fail', 'Scores below the configured threshold show Fail.');
$exam->passing_percentage = 0;
student_state_assert(onlineexam_student_result($exam, $attempt)['outcome'] === null, 'No pass threshold is invented when none is configured.');
$attempt->status = 'marking';
student_state_assert(!onlineexam_student_result($exam, $attempt)['visible'], 'Partial marking results are not exposed even when feedback is released.');
$paper->attempt_paper_status = 'completed';
$paper->completion_source = 'timed_out';
$paper->question_count = 5;
$paper->answered_count = 5;
student_state_assert(onlineexam_student_paper_state($exam, $paper, $attempt, $after)['key'] === 'completed', 'A timeout which submitted every answer is completed.');
$paper->answered_count = 2;
student_state_assert(onlineexam_student_paper_state($exam, $paper, $attempt, $after)['key'] === 'incomplete', 'A timeout with unfinished questions is incomplete.');
$paper->requirements_met = true;
student_state_assert(onlineexam_student_paper_state($exam, $paper, $attempt, $after)['key'] === 'completed', 'A timeout that satisfies an answer-any rule is complete even with unused optional questions.');
unset($paper->requirements_met);
$paper->answered_count = 0;
student_state_assert(onlineexam_student_paper_state($exam, $paper, $attempt, $after)['key'] === 'missed', 'A timeout without any answers is missed.');
$paper->completion_source = null;
$paper->attempt_paper_deadline = '2026-09-06 10:20:00';
$paper->attempt_paper_submitted_at = '2026-09-06 10:20:10';
student_state_assert(onlineexam_student_paper_state($exam, $paper, $attempt, $after)['key'] === 'missed', 'Historical timeout inference agrees with staff review before completion_source existed.');
$paper->answered_count = 2;
student_state_assert(onlineexam_student_paper_state($exam, $paper, $attempt, $after)['key'] === 'incomplete', 'Historical partial timeout is incomplete.');
$paper->completion_source = 'submitted';
student_state_assert(onlineexam_student_paper_state($exam, $paper, $attempt, $after)['key'] === 'completed', 'An explicit normal submission committed during grace is not misclassified as a historical timeout.');
$paper->completion_source = 'manual';
student_state_assert(onlineexam_student_paper_state($exam, $paper, $attempt, $after)['key'] === 'completed', 'An explicit teacher-recorded replacement remains completed.');
$receipt = onlineexam_student_submission_receipt(array('status' => true, 'attempt_id' => 8,
    'final_answers_applied' => true, 'paper_score' => array('raw_score' => 17),
    'finalization' => array('calculation' => array('final_score' => 17)),
    'result_sync' => array('value' => 17), 'unexpected_private_field' => 'private'));
student_state_assert($receipt === array('status' => true, 'attempt_id' => 8, 'final_answers_applied' => true),
    'Submission JSON acknowledges saved answers without exposing held scores or posting internals.');
echo "Online Examination student state and result visibility tests passed.\n";
