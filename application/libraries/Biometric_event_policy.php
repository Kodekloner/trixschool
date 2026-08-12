<?php

defined('BASEPATH') OR exit('No direct script access allowed');

/**
 * Dependency-free policy helpers shared by the HTTP/API and website flows.
 */
class Biometric_event_policy
{
    public function sourceAllowedInMode($source, $mode)
    {
        $allowed = array(
            'disabled' => array(),
            'simulation' => array('simulator', 'qr'),
            'shadow' => array('gateway'),
            'live' => array('gateway', 'qr'),
        );
        return isset($allowed[$mode]) && in_array($source, $allowed[$mode], true);
    }

    public function recordScope($mode)
    {
        return in_array($mode, array('simulation', 'shadow', 'live'), true)
            ? $mode
            : 'disabled';
    }

    public function directionFromState($state, array $map)
    {
        $key = trim((string) $state);
        if (!array_key_exists($key, $map)) {
            return null;
        }
        $direction = strtoupper(trim((string) $map[$key]));
        return in_array($direction, array('IN', 'OUT'), true) ? $direction : null;
    }

    public function normalizeTimestamp($value, $timezone, $maxAgeDays, $now = null)
    {
        $value = trim((string) $value);
        if (!preg_match('/(?:Z|[+-](?:0\d|1\d|2[0-3]):[0-5]\d)$/i', $value)) {
            return array('valid' => false, 'code' => 'TIMESTAMP_OFFSET_REQUIRED', 'message' => 'occurred_at must include an ISO-8601 UTC offset.');
        }
        try {
            $localZone = new DateTimeZone($timezone);
            $occurred = new DateTimeImmutable($value);
            $nowTime = $now instanceof DateTimeImmutable ? $now : new DateTimeImmutable('now', new DateTimeZone('UTC'));
        } catch (Exception $exception) {
            return array('valid' => false, 'code' => 'INVALID_TIMESTAMP', 'message' => 'occurred_at must be a valid ISO-8601 timestamp.');
        }

        $utc = $occurred->setTimezone(new DateTimeZone('UTC'));
        $local = $occurred->setTimezone($localZone);
        if ($utc->getTimestamp() > $nowTime->getTimestamp() + 600) {
            return array('valid' => false, 'code' => 'FUTURE_TIMESTAMP', 'message' => 'occurred_at is more than ten minutes in the future.');
        }
        if ($maxAgeDays > 0 && $utc->getTimestamp() < $nowTime->getTimestamp() - ((int) $maxAgeDays * 86400)) {
            return array('valid' => false, 'code' => 'STALE_TIMESTAMP', 'message' => 'occurred_at is outside the configured event age window.');
        }

        return array(
            'valid' => true,
            'utc' => $utc->format('Y-m-d H:i:s'),
            'local' => $local->format('Y-m-d H:i:s'),
            'date' => $local->format('Y-m-d'),
        );
    }

    public function aggregateDay(array $events, $lateAfter)
    {
        $firstIn = null;
        $lastOut = null;
        foreach ($events as $event) {
            if (empty($event['occurred_at_local'])) {
                continue;
            }
            $time = (string) $event['occurred_at_local'];
            if ($event['direction'] === 'IN' && ($firstIn === null || $time < $firstIn)) {
                $firstIn = $time;
            }
            if ($event['direction'] === 'OUT' && ($lastOut === null || $time > $lastOut)) {
                $lastOut = $time;
            }
        }

        $duration = null;
        $status = 'incomplete';
        $problem = null;
        if ($firstIn === null && $lastOut !== null) {
            $problem = 'OUT_WITHOUT_IN';
        } elseif ($firstIn !== null && $lastOut !== null && $lastOut < $firstIn) {
            $problem = 'OUT_BEFORE_IN';
        } elseif ($firstIn !== null) {
            $status = substr($firstIn, 11, 8) > $lateAfter ? 'late' : 'present';
            if ($lastOut !== null) {
                $duration = max(0, (int) floor((strtotime($lastOut) - strtotime($firstIn)) / 60));
            }
        }

        return array(
            'first_in_at' => $firstIn,
            'last_out_at' => $lastOut,
            'duration_minutes' => $duration,
            'attendance_status' => $status,
            'missing_checkout' => $firstIn !== null && $lastOut === null ? 1 : 0,
            'problem' => $problem,
        );
    }

    public function sanitizedMetadata(array $event)
    {
        $allowed = array('provider_record_id', 'work_code', 'temperature', 'gateway_received_at', 'provider');
        $metadata = array();
        foreach ($allowed as $key) {
            if (isset($event[$key]) && is_scalar($event[$key])) {
                $value = (string) $event[$key];
                $metadata[$key] = substr($value, 0, 191);
            }
        }
        return $metadata;
    }
}
