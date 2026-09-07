<?php

defined('BASEPATH') or exit('No direct script access allowed');

function onlineexam_student_submission_receipt(array $result)
{
    return array_intersect_key($result, array_flip(array('status', 'attempt_id', 'onlineexam_id', 'attempt_status',
        'timed_out', 'idempotent', 'final_answers_applied', 'final_answer_counts')));
}

/** Presentation only: authorization and deadlines remain enforced by the attempt model. */
function onlineexam_student_timestamp($value)
{
    if (!is_string($value) || trim($value) === '') {
        return null;
    }
    $parsed = strtotime($value);
    return $parsed !== false && $parsed > 0 ? $parsed : null;
}

function onlineexam_student_paper_state($exam, $paper, $attempt, $now = null)
{
    $now = $now === null ? time() : (int) $now;
    $status = !empty($paper->attempt_paper_status) ? $paper->attempt_paper_status : 'not_started';
    $status = $status === 'pending' ? 'not_started' : $status;
    $starts = onlineexam_student_timestamp(isset($paper->effective_starts_at) ? $paper->effective_starts_at : (!empty($paper->starts_at) ? $paper->starts_at : $exam->exam_from));
    $ends = onlineexam_student_timestamp(isset($paper->effective_ends_at) ? $paper->effective_ends_at : (!empty($paper->ends_at) ? $paper->ends_at : $exam->exam_to));
    if (!isset($paper->effective_ends_at) && $ends && !empty($exam->accommodation_extra_time_minutes)) {
        $ends += (int) $exam->accommodation_extra_time_minutes * 60;
    }
    $deadline = onlineexam_student_timestamp(isset($paper->attempt_paper_deadline) ? $paper->attempt_paper_deadline : null);
    $closed = ($ends !== null && $now >= $ends) || ($status === 'in_progress' && $deadline !== null && $now >= $deadline);
    $upcoming = $starts !== null && $now < $starts;
    $valid_window = $starts !== null && $ends !== null && $ends > $starts;
    $terminal_attempt = $attempt && in_array($attempt->status, array('submitted', 'timed_out', 'marking', 'completed', 'voided'), true);
    $allowed_lifecycles = !empty($paper->is_rescheduled)
        ? array('scheduled', 'published', 'in_progress', 'marking', 'completed')
        : array('scheduled', 'published', 'in_progress');
    $launchable = !$terminal_attempt && empty($exam->deleted_at) && !empty($exam->is_active)
        && in_array(isset($exam->lifecycle_status) ? $exam->lifecycle_status : '', $allowed_lifecycles, true)
        && (!isset($paper->delivery_mode) || $paper->delivery_mode === 'cbt')
        && in_array($status, array('not_started', 'in_progress'), true)
        && $valid_window && !$closed && !$upcoming;
    $label = 'Unavailable';
    $class = 'label-default';
    $key = 'unavailable';
    $source = isset($paper->completion_source) ? $paper->completion_source : null;
    $submitted = onlineexam_student_timestamp(isset($paper->attempt_paper_submitted_at) ? $paper->attempt_paper_submitted_at : null);
    // Migration 137 does not rewrite historical attempts. Infer their timeout
    // only when no explicit submission source exists; a normal submission may
    // legitimately finish committing during the server's answer-save grace.
    $historical_timeout = !$source && $deadline !== null && $submitted !== null && $submitted >= $deadline;
    $answered = isset($paper->answered_count) ? (int) $paper->answered_count : null;
    $requirements_met = isset($paper->requirements_met)
        ? (bool) $paper->requirements_met
        : (!empty($paper->question_count) && $answered !== null && $answered >= (int) $paper->question_count);
    $expired_unfinished = ($status === 'timed_out' || $source === 'timed_out' || $historical_timeout) && !$requirements_met;
    if ($expired_unfinished || ($closed && in_array($status, array('not_started', 'in_progress'), true))) {
        $key = $status === 'not_started' || $answered === 0 ? 'missed' : 'incomplete';
        $label = $key === 'missed' ? 'Missed' : 'Incomplete';
        $class = 'label-danger';
    } elseif ($source === 'manual') {
        $key = 'completed';
        $label = 'Completed';
        $class = 'label-success';
    } elseif (in_array($status, array('submitted', 'completed', 'timed_out'), true)) {
        $key = 'completed';
        $label = 'Completed';
        $class = 'label-success';
        if (isset($paper->manual_marking_status) && $paper->manual_marking_status === 'pending') {
            $key = 'marking';
            $label = 'Awaiting marking';
            $class = 'label-warning';
        }
    } elseif ($upcoming && $valid_window && !$terminal_attempt) {
        $key = 'upcoming';
        $label = !empty($paper->is_rescheduled) ? 'Rescheduled' : 'Upcoming';
    } elseif ($launchable) {
        $key = $status === 'in_progress' ? 'in_progress' : 'available';
        $label = $status === 'in_progress' ? 'In progress' : 'Available';
        $class = $status === 'in_progress' ? 'label-warning' : 'label-info';
    }
    return array('key' => $key, 'label' => $label, 'class' => $class, 'can_launch' => $launchable,
        'action' => $status === 'in_progress' ? 'Resume' : 'Start', 'starts_at' => $starts, 'ends_at' => $ends);
}

function onlineexam_student_result($exam, $attempt)
{
    $hidden = array('visible' => false, 'score' => null, 'maximum' => null, 'percentage' => null, 'outcome' => null);
    if (!$attempt || $attempt->status !== 'completed' || !isset($exam->feedback_status) || $exam->feedback_status !== 'released'
        || !isset($attempt->final_score) || !is_numeric($attempt->final_score)) {
        return $hidden;
    }
    $score = (float) $attempt->final_score;
    $maximum = isset($exam->target_max_score) && is_numeric($exam->target_max_score) ? (float) $exam->target_max_score : 0;
    $percentage = $maximum > 0 ? $score / $maximum * 100 : null;
    $passing = isset($exam->passing_percentage) && is_numeric($exam->passing_percentage) ? (float) $exam->passing_percentage : 0;
    return array('visible' => true, 'score' => $score, 'maximum' => $maximum > 0 ? $maximum : null,
        'percentage' => $percentage, 'outcome' => $percentage !== null && $passing > 0 && $passing <= 100 ? ($percentage >= $passing ? 'Pass' : 'Fail') : null);
}

function onlineexam_student_assessment_state($attempt, array $paper_states)
{
    $keys = array_column($paper_states, 'key');
    if (in_array('in_progress', $keys, true)) {
        return array('label' => 'In progress', 'class' => 'label-warning');
    }
    if (in_array('available', $keys, true)) {
        return array('label' => 'Available', 'class' => 'label-info');
    }
    if (in_array('upcoming', $keys, true)) {
        return array('label' => 'Upcoming', 'class' => 'label-default');
    }
    if (in_array('incomplete', $keys, true) || (in_array('missed', $keys, true) && in_array('completed', $keys, true))) {
        return array('label' => 'Incomplete', 'class' => 'label-danger');
    }
    if (in_array('missed', $keys, true)) {
        return array('label' => 'Missed', 'class' => 'label-danger');
    }
    if ($attempt && in_array($attempt->status, array('submitted', 'marking', 'timed_out'), true)) {
        return array('label' => 'Awaiting marking', 'class' => 'label-warning');
    }
    if ($attempt && $attempt->status === 'completed') {
        return array('label' => 'Completed', 'class' => 'label-success');
    }
    return array('label' => 'Unavailable', 'class' => 'label-default');
}
