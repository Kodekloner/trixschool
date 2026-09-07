<?php

defined('BASEPATH') OR exit('No direct script access allowed');

/** Shared setup rules; all timestamps are parsed in the school's server timezone. */
class Onlineexam_setup
{
    public function windowError($starts_at, $ends_at, $duration_minutes, $outer_start = null, $outer_end = null)
    {
        $start = is_numeric($starts_at) ? (int) $starts_at : strtotime((string) $starts_at);
        $end = is_numeric($ends_at) ? (int) $ends_at : strtotime((string) $ends_at);
        $duration = filter_var($duration_minutes, FILTER_VALIDATE_INT);
        if (!$start || !$end || $end <= $start) {
            return 'The closing date/time must be after the opening date/time.';
        }
        if ($duration === false || $duration <= 0 || ($end - $start) < $duration * 60) {
            return 'The time between opening and closing must be at least the full duration.';
        }
        if ($outer_start !== null && $outer_end !== null
            && ($start < strtotime((string) $outer_start) || $end > strtotime((string) $outer_end))) {
            return 'Paper dates must fall inside the assessment window.';
        }
        return null;
    }

    public function paperMaximum($target_maximum)
    {
        return is_numeric($target_maximum) && is_finite((float) $target_maximum) && round((float) $target_maximum, 2) > 0
            ? round((float) $target_maximum, 2) : null;
    }
}
