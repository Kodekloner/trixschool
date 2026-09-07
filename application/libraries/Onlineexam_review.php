<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/** Pure, shared rules for the staff review matrix and completed-assessment removal. */
class Onlineexam_review
{
    /**
     * Count the answers actually required by the frozen section rules.
     * Each group contains section_id, is_compulsory, question_count and
     * answered_count; this avoids loading every answer into review matrices.
     */
    public function requirementProgress(array $groups, array $sections = array())
    {
        $rules = array();
        foreach ($sections as $section) {
            if (!isset($section['id'])) {
                continue;
            }
            $rules[(int) $section['id']] = array(
                'answer_rule' => isset($section['answer_rule']) ? $section['answer_rule'] : 'all',
                'answer_count' => isset($section['answer_count']) ? max(0, (int) $section['answer_count']) : 0,
            );
        }

        $counts = array();
        foreach ($groups as $group) {
            $section_id = empty($group['section_id']) ? 0 : (int) $group['section_id'];
            $compulsory = !empty($group['is_compulsory']) ? 1 : 0;
            if (!isset($counts[$section_id])) {
                $counts[$section_id] = array(
                    0 => array('questions' => 0, 'answered' => 0),
                    1 => array('questions' => 0, 'answered' => 0),
                );
            }
            $counts[$section_id][$compulsory]['questions'] += max(0, (int) $group['question_count']);
            $counts[$section_id][$compulsory]['answered'] += max(0, (int) $group['answered_count']);
        }

        $required = 0;
        $completed = 0;
        foreach ($counts as $section_id => $section_counts) {
            $all_questions = $section_counts[0]['questions'] + $section_counts[1]['questions'];
            $all_answered = $section_counts[0]['answered'] + $section_counts[1]['answered'];
            $rule = $section_id && isset($rules[$section_id]) ? $rules[$section_id] : array('answer_rule' => 'all', 'answer_count' => 0);
            if ($rule['answer_rule'] === 'answer_any') {
                $needed = min($all_questions, $rule['answer_count']);
                $required += $needed;
                $completed += min($all_answered, $needed);
            } elseif ($rule['answer_rule'] === 'compulsory_plus_choice') {
                $optional_needed = min($section_counts[0]['questions'], $rule['answer_count']);
                $required += $section_counts[1]['questions'] + $optional_needed;
                $completed += min($section_counts[1]['answered'], $section_counts[1]['questions'])
                    + min($section_counts[0]['answered'], $optional_needed);
            } else {
                $required += $all_questions;
                $completed += min($all_answered, $all_questions);
            }
        }

        return array(
            'required' => $required,
            'completed' => $completed,
            'met' => $required > 0 && $completed >= $required,
        );
    }

    public function describe(array $paper, $assigned, $now = null)
    {
        $now = $now === null ? time() : (int) $now;
        $result = array('key' => 'assigned', 'label' => 'Assigned', 'style' => 'default', 'recoverable' => true, 'complete' => false);
        if (!$assigned) {
            return array_merge($result, array('key' => 'unassigned', 'label' => 'Unassigned'));
        }
        $status = isset($paper['attempt_status']) ? $paper['attempt_status'] : 'pending';
        $source = isset($paper['completion_source']) ? $paper['completion_source'] : '';
        $answered = isset($paper['answered_count']) ? (int) $paper['answered_count'] : 0;
        $total = isset($paper['question_count']) ? (int) $paper['question_count'] : 0;
        $deadline = !empty($paper['deadline_at']) ? strtotime($paper['deadline_at']) : false;
        $submitted = !empty($paper['submitted_at']) ? strtotime($paper['submitted_at']) : false;
        $timed_out = $source === 'timed_out' || (!$source && $deadline && $submitted && $submitted >= $deadline);
        $requirements_met = array_key_exists('requirements_met', $paper)
            ? (bool) $paper['requirements_met'] : ($total > 0 && $answered >= $total);
        if (in_array($status, array('submitted', 'completed'), true)) {
            if ($timed_out && !$requirements_met) {
                return array_merge($result, array('key' => $answered ? 'incomplete' : 'missed', 'label' => $answered ? 'Incomplete' : 'Missed', 'style' => 'warning'));
            }
            if (isset($paper['manual_marking_status']) && $paper['manual_marking_status'] === 'pending') {
                return array_merge($result, array('key' => 'marking', 'label' => 'Awaiting marking', 'style' => 'info', 'recoverable' => false));
            }
            return array_merge($result, array('key' => 'completed', 'label' => number_format((float) $paper['raw_score'], 2) . ' / ' . number_format((float) $paper['raw_max_score'], 2), 'style' => 'success', 'recoverable' => false, 'complete' => true));
        }
        if ($status === 'in_progress' && $deadline && $now < $deadline + 15) {
            return array_merge($result, array('key' => 'in_progress', 'label' => 'In progress', 'style' => 'info', 'recoverable' => false));
        }
        $end = !empty($paper['effective_ends_at']) ? strtotime($paper['effective_ends_at']) : false;
        if (($status === 'in_progress' && $deadline && $now >= $deadline + 15) || ($end && $now >= $end)) {
            return array_merge($result, array('key' => $answered ? 'incomplete' : 'missed', 'label' => $answered ? 'Incomplete' : 'Missed', 'style' => 'warning'));
        }
        if (!empty($paper['is_rescheduled'])) {
            return array_merge($result, array('key' => 'rescheduled', 'label' => 'Rescheduled', 'style' => 'primary'));
        }
        $start = !empty($paper['effective_starts_at']) ? strtotime($paper['effective_starts_at']) : false;
        if ($start && $now < $start) {
            return array_merge($result, array('key' => 'upcoming', 'label' => 'Upcoming'));
        }
        return $result;
    }
}
