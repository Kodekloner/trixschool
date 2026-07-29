<?php

defined('BASEPATH') OR exit('No direct script access allowed');

/**
 * Deterministic scoring rules for workflow-v2 online assessments.
 *
 * The class intentionally has no database dependencies so the same rules are
 * used by CBT submission, manual marking and result synchronization.
 */
class Onlineexam_scoring
{
    const MONEY_PRECISION = 2;
    const WORKING_PRECISION = 4;

    public function scoreObjective($response, $correct_answer, $marks, $negative_marks = 0, $negative_enabled = false, $question_type = '')
    {
        $marks = (float) $marks;
        $negative_marks = max(0, (float) $negative_marks);

        if (!$this->isAnswered($response)) {
            return array(
                'answered' => false,
                'correct' => null,
                'mark' => 0.0,
            );
        }

        $correct = $this->answersMatch($response, $correct_answer, $question_type);
        $mark = $correct ? $marks : ($negative_enabled ? -$negative_marks : 0.0);

        return array(
            'answered' => true,
            'correct' => $correct,
            'mark' => round($mark, self::MONEY_PRECISION),
        );
    }

    public function calculatePaper($earned_mark, $raw_max_score, $contribution_score)
    {
        $earned_mark = (float) $earned_mark;
        $raw_max_score = (float) $raw_max_score;
        $contribution_score = (float) $contribution_score;

        if ($raw_max_score <= 0) {
            throw new InvalidArgumentException('Paper raw maximum score must be greater than zero.');
        }
        if ($contribution_score < 0) {
            throw new InvalidArgumentException('Paper contribution cannot be negative.');
        }

        // A correction must never give more than the paper maximum. Negative
        // totals are retained here and floored only at the final assessment.
        $bounded_earned = min($earned_mark, $raw_max_score);
        $weighted = ($bounded_earned / $raw_max_score) * $contribution_score;

        return array(
            'raw_score' => round($earned_mark, self::WORKING_PRECISION),
            'raw_max_score' => round($raw_max_score, self::WORKING_PRECISION),
            'contribution_score' => round($weighted, self::WORKING_PRECISION),
            'contribution_max_score' => round($contribution_score, self::WORKING_PRECISION),
        );
    }

    public function calculateAssessment(array $papers, $target_max_score)
    {
        $target_max_score = (float) $target_max_score;
        if ($target_max_score <= 0) {
            throw new InvalidArgumentException('Assessment target maximum score must be greater than zero.');
        }
        if (empty($papers)) {
            throw new InvalidArgumentException('At least one paper is required to calculate an assessment.');
        }

        $calculated = array();
        $raw_score = 0.0;
        $raw_max_score = 0.0;
        $weighted_score = 0.0;
        $weighted_max_score = 0.0;

        foreach ($papers as $key => $paper) {
            foreach (array('earned', 'raw_max', 'contribution') as $required) {
                if (!array_key_exists($required, $paper)) {
                    throw new InvalidArgumentException('Paper calculation is missing ' . $required . '.');
                }
            }

            $paper_result = $this->calculatePaper($paper['earned'], $paper['raw_max'], $paper['contribution']);
            $calculated[$key] = $paper_result;
            $raw_score += $paper_result['raw_score'];
            $raw_max_score += $paper_result['raw_max_score'];
            $weighted_score += $paper_result['contribution_score'];
            $weighted_max_score += $paper_result['contribution_max_score'];
        }

        if ($weighted_max_score <= 0) {
            throw new InvalidArgumentException('The combined paper contribution must be greater than zero.');
        }

        $scaled = ($weighted_score / $weighted_max_score) * $target_max_score;
        $scaled = max(0, min($scaled, $target_max_score));

        return array(
            'papers' => $calculated,
            'raw_score' => round($raw_score, self::WORKING_PRECISION),
            'raw_max_score' => round($raw_max_score, self::WORKING_PRECISION),
            'weighted_score' => round($weighted_score, self::WORKING_PRECISION),
            'weighted_max_score' => round($weighted_max_score, self::WORKING_PRECISION),
            'final_score' => round($scaled, self::MONEY_PRECISION),
        );
    }

    public function validateBritishProfile(array $profile)
    {
        $mode = isset($profile['mode']) ? $profile['mode'] : '';
        if (!in_array($mode, array('thresholds', 'teacher_selection'), true)) {
            return array('valid' => false, 'error' => 'British outcome mode must be thresholds or teacher_selection.');
        }
        if ($mode === 'teacher_selection') {
            return array('valid' => true, 'error' => null);
        }

        $allowed = array('Emerging', 'Expected', 'Exceeding');
        $outcomes = isset($profile['outcomes']) && is_array($profile['outcomes']) ? $profile['outcomes'] : array();
        if (count($outcomes) !== 3) {
            return array('valid' => false, 'error' => 'British threshold mode requires Emerging, Expected and Exceeding ranges.');
        }

        $normalized = array();
        foreach ($outcomes as $outcome) {
            if (!isset($outcome['value'], $outcome['min'], $outcome['max']) || !in_array($outcome['value'], $allowed, true)) {
                return array('valid' => false, 'error' => 'British outcome ranges contain an invalid value.');
            }
            $min = (float) $outcome['min'];
            $max = (float) $outcome['max'];
            if ($min < 0 || $max > 100 || $min > $max) {
                return array('valid' => false, 'error' => 'British outcome ranges must be within 0 to 100.');
            }
            $normalized[] = array('value' => $outcome['value'], 'min' => $min, 'max' => $max);
        }

        usort($normalized, function ($a, $b) {
            return $a['min'] <=> $b['min'];
        });
        if ($normalized[0]['min'] != 0.0 || $normalized[2]['max'] != 100.0) {
            return array('valid' => false, 'error' => 'British outcome ranges must cover 0 through 100.');
        }
        for ($i = 1; $i < count($normalized); $i++) {
            if ($normalized[$i]['min'] > $normalized[$i - 1]['max'] + 0.011 || $normalized[$i]['min'] <= $normalized[$i - 1]['max']) {
                return array('valid' => false, 'error' => 'British outcome ranges must not overlap or leave gaps.');
            }
        }

        return array('valid' => true, 'error' => null);
    }

    public function resolveBritishOutcome($percentage, array $profile, $teacher_selection = null)
    {
        $validation = $this->validateBritishProfile($profile);
        if (!$validation['valid']) {
            throw new InvalidArgumentException($validation['error']);
        }

        $allowed = array('Emerging', 'Expected', 'Exceeding');
        if ($profile['mode'] === 'teacher_selection') {
            if (!in_array($teacher_selection, $allowed, true)) {
                throw new InvalidArgumentException('A finalized British outcome is required.');
            }
            return $teacher_selection;
        }

        $percentage = max(0, min((float) $percentage, 100));
        foreach ($profile['outcomes'] as $outcome) {
            if ($percentage >= (float) $outcome['min'] && $percentage <= (float) $outcome['max']) {
                return $outcome['value'];
            }
        }

        throw new RuntimeException('The score is not covered by the configured British outcome ranges.');
    }

    public function resolveKindergartenLabel($percentage, array $profile, $label_count, $teacher_selection = null)
    {
        $label_count = (int) $label_count;
        if ($label_count < 1) {
            throw new InvalidArgumentException('Kindergarten assessment has no result labels.');
        }

        if ($teacher_selection !== null && $teacher_selection !== '') {
            $index = filter_var($teacher_selection, FILTER_VALIDATE_INT);
            if ($index === false || $index < 0 || $index >= $label_count) {
                throw new InvalidArgumentException('The selected Kindergarten result label is invalid.');
            }
            return (int) $index;
        }

        $labels = isset($profile['labels']) && is_array($profile['labels']) ? $profile['labels'] : array();
        $percentage = max(0, min((float) $percentage, 100));
        foreach ($labels as $label) {
            if (!isset($label['index'], $label['min'], $label['max'])) {
                continue;
            }
            $index = (int) $label['index'];
            if ($index >= 0 && $index < $label_count && $percentage >= (float) $label['min'] && $percentage <= (float) $label['max']) {
                return $index;
            }
        }

        throw new InvalidArgumentException('A finalized Kindergarten label or a complete outcome profile is required.');
    }

    protected function isAnswered($response)
    {
        $response = $this->decodeJsonValue($response);
        if ($response === null) {
            return false;
        }
        if (is_string($response)) {
            return trim($response) !== '';
        }
        if (is_array($response)) {
            return count($response) > 0;
        }
        return true;
    }

    protected function answersMatch($response, $correct_answer, $question_type)
    {
        $response = $this->decodeJsonValue($response);
        $correct_answer = $this->decodeJsonValue($correct_answer);

        if ($question_type === 'numeric' && is_numeric($response) && is_numeric($correct_answer)) {
            return abs((float) $response - (float) $correct_answer) < 0.000001;
        }
        if (in_array($question_type, array('true_false', 'true/false'), true)) {
            return strtolower(trim((string) $response)) === strtolower(trim((string) $correct_answer));
        }

        $preserve_order = $question_type === 'ordering';
        return $this->canonicalize($response, $preserve_order) === $this->canonicalize($correct_answer, $preserve_order);
    }

    protected function decodeJsonValue($value)
    {
        if (!is_string($value)) {
            return $value;
        }
        $trimmed = trim($value);
        if ($trimmed === '' || !in_array(substr($trimmed, 0, 1), array('[', '{', '"'), true)) {
            return $value;
        }
        $decoded = json_decode($trimmed, true);
        return json_last_error() === JSON_ERROR_NONE ? $decoded : $value;
    }

    protected function canonicalize($value, $preserve_order)
    {
        if (is_array($value)) {
            $is_list = array_keys($value) === range(0, count($value) - 1);
            if (!$is_list) {
                ksort($value);
            }
            foreach ($value as $key => $item) {
                $value[$key] = $this->canonicalize($item, $preserve_order);
            }
            if ($is_list && !$preserve_order) {
                sort($value);
            }
            return json_encode($value);
        }
        if (is_bool($value)) {
            return $value ? 'true' : 'false';
        }
        if ($value === null) {
            return 'null';
        }
        return trim((string) $value);
    }
}
