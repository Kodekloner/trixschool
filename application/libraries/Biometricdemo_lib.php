<?php

if (!defined('BASEPATH')) {
    exit('No direct script access allowed');
}

$biometric_scenario_factory = dirname(dirname(__DIR__)) . '/tools/biometric-sandbox/src/ScenarioFactory.php';
if (!class_exists('SchoolLift\\BiometricSandbox\\ScenarioFactory', false)) {
    require_once $biometric_scenario_factory;
}

use SchoolLift\BiometricSandbox\ScenarioFactory;

/**
 * Builds a stateless, synthetic biometric demonstration for the school UI.
 *
 * This library deliberately has no database dependency and never calls the
 * production /biometric receiver. It previews how a future event-ledger based
 * integration should validate, deduplicate and reconcile IN/OUT transactions.
 */
class Biometricdemo_lib
{
    /** @var DateTimeZone */
    private $timezone;

    /** @var ScenarioFactory */
    private $factory;

    /** @var array<string, string> */
    private $labels = array(
        'normal'          => 'Normal school day',
        'duplicate'       => 'Duplicate event delivery',
        'delayed'         => 'Delayed checkout event',
        'out-of-order'    => 'Out-of-order delivery',
        'unknown-student' => 'Unknown student identity',
        'unknown-device'  => 'Unknown terminal',
        'invalid-event'   => 'Malformed event',
        'server-error'    => 'Vendor server error (503)',
        'rate-limit'      => 'API rate limit (429)',
        'auth-error'      => 'Authentication failure (401)',
    );

    /** @var array<string, string> */
    private $expected_behaviour = array(
        'normal'          => 'Accept both unique events and derive the first entry and final checkout.',
        'duplicate'       => 'Accept the event once, ignore the repeated vendor event ID, and avoid duplicate attendance.',
        'delayed'         => 'Keep the entry visible, retry later, then attach the delayed checkout to the same attendance day.',
        'out-of-order'    => 'Preserve the vendor events, order the daily summary by occurrence time, and derive the correct entry and checkout.',
        'unknown-student' => 'Quarantine the event for identity mapping; do not create attendance for an unknown student.',
        'unknown-device'  => 'Quarantine the event from an unregistered terminal; do not trust the terminal serial by itself.',
        'invalid-event'   => 'Reject the malformed record, retain diagnostics, and do not change attendance.',
        'server-error'    => 'Make no attendance change, retain the polling cursor, and retry the vendor request safely.',
        'rate-limit'      => 'Make no attendance change and retry with controlled exponential backoff.',
        'auth-error'      => 'Stop polling, protect credentials, and alert an administrator to repair the integration configuration.',
    );

    public function __construct()
    {
        $this->timezone = new DateTimeZone('Africa/Lagos');
        $this->factory  = new ScenarioFactory($this->timezone);
    }

    /**
     * @return array<string, array{label: string, description: string}>
     */
    public function scenarios()
    {
        $result = array();
        $today  = (new DateTimeImmutable('now', $this->timezone))->format('Y-m-d');

        foreach (ScenarioFactory::names() as $name) {
            $sample = $this->factory->make(
                $name,
                'DEMO/STUDENT/0001',
                $today,
                'SIM-IN-001',
                'SIM-OUT-001',
                5
            );
            $result[$name] = array(
                'label'       => isset($this->labels[$name]) ? $this->labels[$name] : $name,
                'description' => $sample['description'],
            );
        }

        return $result;
    }

    /**
     * @return array<string, mixed>
     */
    public function defaults()
    {
        return array(
            'scenario'      => 'normal',
            'employee'      => 'DEMO/STUDENT/0001',
            'event_date'    => (new DateTimeImmutable('now', $this->timezone))->format('Y-m-d'),
            'entry_serial'  => 'SIM-IN-001',
            'exit_serial'   => 'SIM-OUT-001',
            'delay_seconds' => 5,
        );
    }

    /**
     * Run a single in-memory demonstration. No file or database state is used.
     *
     * @param array<string, mixed> $input
     * @return array<string, mixed>
     */
    public function simulate(array $input)
    {
        $scenario_name = trim((string) (isset($input['scenario']) ? $input['scenario'] : ''));
        if (!in_array($scenario_name, ScenarioFactory::names(), true)) {
            throw new InvalidArgumentException('Select a supported biometric demonstration scenario.');
        }

        $employee     = $this->identifier(isset($input['employee']) ? $input['employee'] : '', 'Synthetic student code');
        $entry_serial = $this->identifier(isset($input['entry_serial']) ? $input['entry_serial'] : '', 'Entry terminal serial');
        $exit_serial  = $this->identifier(isset($input['exit_serial']) ? $input['exit_serial'] : '', 'Exit terminal serial');
        $event_date   = $this->dateValue(isset($input['event_date']) ? $input['event_date'] : '');
        $delay        = filter_var(
            isset($input['delay_seconds']) ? $input['delay_seconds'] : 5,
            FILTER_VALIDATE_INT,
            array('options' => array('min_range' => 0, 'max_range' => 300))
        );
        if ($delay === false) {
            throw new InvalidArgumentException('Delay must be a whole number between 0 and 300 seconds.');
        }

        $scenario = $this->factory->make(
            $scenario_name,
            $employee,
            $event_date,
            $entry_serial,
            $exit_serial,
            (int) $delay
        );

        $events = $this->providerEvents($scenario['events']);
        $provider = $this->providerResult($scenario);
        $processed = $this->processEvents($events, $employee, $entry_serial, $exit_serial);

        return array(
            'sandbox'            => true,
            'persisted'          => false,
            'database_writes'    => 0,
            'scenario'           => $scenario_name,
            'scenario_label'     => isset($this->labels[$scenario_name]) ? $this->labels[$scenario_name] : $scenario_name,
            'description'        => $scenario['description'],
            'expected_behaviour' => $this->expected_behaviour[$scenario_name],
            'generated_at'       => (new DateTimeImmutable('now', $this->timezone))->format(DATE_ATOM),
            'input'              => array(
                'employee'      => $employee,
                'event_date'    => $event_date,
                'entry_serial'  => $entry_serial,
                'exit_serial'   => $exit_serial,
                'delay_seconds' => (int) $delay,
            ),
            'provider'           => $provider,
            'delivery_batches'   => $this->deliveryBatches($scenario_name, $events, $provider, (int) $delay),
            'events'             => $processed['events'],
            'normalized_events'  => $processed['normalized_events'],
            'processing_log'     => $processed['processing_log'],
            'counts'             => $processed['counts'],
            'attendance_preview' => $processed['attendance_preview'],
            'safety_notice'      => 'Synthetic preview only. No student lookup, device connection, biometric template, attendance row, or school database was used.',
        );
    }

    /**
     * @param mixed $value
     * @return string
     */
    private function identifier($value, $label)
    {
        $identifier = trim((string) $value);
        if ($identifier === '' || strlen($identifier) > 64
            || preg_match('/\A[A-Za-z0-9][A-Za-z0-9._\/-]{0,63}\z/', $identifier) !== 1) {
            throw new InvalidArgumentException($label . ' must contain 1-64 letters, numbers, dots, slashes, underscores, or dashes.');
        }

        return $identifier;
    }

    /**
     * @param mixed $value
     * @return string
     */
    private function dateValue($value)
    {
        $date = trim((string) $value);
        $parsed = DateTimeImmutable::createFromFormat('!Y-m-d', $date, $this->timezone);
        $errors = DateTimeImmutable::getLastErrors();
        $has_errors = is_array($errors)
            && ((int) $errors['warning_count'] > 0 || (int) $errors['error_count'] > 0);

        if ($parsed === false || $has_errors || $parsed->format('Y-m-d') !== $date) {
            throw new InvalidArgumentException('Event date must be a valid date in YYYY-MM-DD format.');
        }

        return $date;
    }

    /**
     * Emulate the external API assigning an ID to every received record.
     * Existing IDs are preserved so the duplicate scenario remains exact.
     *
     * @param array<int, array<string, mixed>> $events
     * @return array<int, array<string, mixed>>
     */
    private function providerEvents(array $events)
    {
        foreach ($events as $index => &$event) {
            if (!array_key_exists('id', $event)) {
                $event['id'] = 100001 + $index;
            }
        }
        unset($event);

        return $events;
    }

    /**
     * @param array<string, mixed> $scenario
     * @return array<string, mixed>
     */
    private function providerResult(array $scenario)
    {
        if (!empty($scenario['auth_error'])) {
            return array(
                'http_status'   => 401,
                'status'        => 'authentication_failed',
                'message'       => 'The mock provider rejected the credentials.',
                'retryable'     => false,
                'retry_message' => 'Correct the API credentials before polling again.',
            );
        }

        if (!empty($scenario['failure']) && is_array($scenario['failure'])) {
            $status = (int) $scenario['failure']['status'];
            return array(
                'http_status'   => $status,
                'status'        => $status === 429 ? 'rate_limited' : 'temporarily_unavailable',
                'message'       => (string) $scenario['failure']['message'],
                'retryable'     => true,
                'retry_message' => $status === 429
                    ? 'Back off, preserve the cursor, and retry later.'
                    : 'Preserve the cursor and retry without duplicating earlier events.',
            );
        }

        return array(
            'http_status'   => 200,
            'status'        => 'available',
            'message'       => 'The mock provider returned synthetic transactions.',
            'retryable'     => false,
            'retry_message' => '',
        );
    }

    /**
     * @param string $scenario_name
     * @param array<int, array<string, mixed>> $events
     * @param array<string, mixed> $provider
     * @param int $delay
     * @return array<int, array<string, mixed>>
     */
    private function deliveryBatches($scenario_name, array $events, array $provider, $delay)
    {
        if ($scenario_name === 'auth-error') {
            return array(array(
                'poll'        => 1,
                'http_status' => 401,
                'event_count' => 0,
                'message'     => 'Authentication fails; transaction polling must stop.',
            ));
        }

        if ($scenario_name === 'server-error' || $scenario_name === 'rate-limit') {
            return array(
                array(
                    'poll'        => 1,
                    'http_status' => (int) $provider['http_status'],
                    'event_count' => 0,
                    'message'     => (string) $provider['message'],
                ),
                array(
                    'poll'        => 2,
                    'http_status' => 200,
                    'event_count' => 0,
                    'message'     => 'A later retry succeeds without advancing past unseen transactions.',
                ),
            );
        }

        if ($scenario_name === 'delayed') {
            return array(
                array(
                    'poll'        => 1,
                    'http_status' => 200,
                    'event_count' => count($events) > 0 ? 1 : 0,
                    'message'     => 'The first poll returns only the entry event.',
                ),
                array(
                    'poll'        => 2,
                    'http_status' => 200,
                    'event_count' => count($events) > 1 ? 1 : 0,
                    'message'     => 'After ' . (int) $delay . ' seconds, the checkout becomes visible.',
                ),
            );
        }

        return array(array(
            'poll'        => 1,
            'http_status' => (int) $provider['http_status'],
            'event_count' => count($events),
            'message'     => 'The provider returns this scenario in one polling response.',
        ));
    }

    /**
     * @param array<int, array<string, mixed>> $events
     * @return array<string, mixed>
     */
    private function processEvents(array $events, $employee, $entry_serial, $exit_serial)
    {
        $seen_ids = array();
        $accepted = array();
        $display_events = array();
        $processing_log = array();
        $counts = array(
            'received'    => count($events),
            'accepted'    => 0,
            'duplicates'  => 0,
            'quarantined' => 0,
            'rejected'    => 0,
        );

        foreach ($events as $index => $event) {
            $decision = $this->eventDecision($event, $seen_ids, $employee, $entry_serial, $exit_serial);
            $event['_demo_status']    = $decision['status'];
            $event['_demo_message']   = $decision['message'];
            $event['_demo_direction'] = $decision['direction'];
            unset($event['_sandbox_visible_at']);
            $display_events[] = $event;

            $processing_log[] = array(
                'event'   => 'Event ' . ($index + 1),
                'status'  => $decision['status'],
                'message' => $decision['message'],
            );

            if ($decision['status'] === 'accepted') {
                $counts['accepted']++;
                $accepted[] = array(
                    'external_event_id' => (string) $event['id'],
                    'student_code'      => (string) $event['emp_code'],
                    'occurred_at'       => (string) $event['punch_time'],
                    'direction'         => $decision['direction'],
                    'terminal_serial'   => (string) $event['terminal_sn'],
                    'terminal_alias'    => (string) (isset($event['terminal_alias']) ? $event['terminal_alias'] : ''),
                );
            } elseif ($decision['status'] === 'duplicate') {
                $counts['duplicates']++;
            } elseif ($decision['status'] === 'quarantined') {
                $counts['quarantined']++;
            } else {
                $counts['rejected']++;
            }
        }

        usort($accepted, function (array $left, array $right) {
            $comparison = strcmp($left['occurred_at'], $right['occurred_at']);
            if ($comparison === 0) {
                return strcmp($left['external_event_id'], $right['external_event_id']);
            }

            return $comparison;
        });

        $entry = null;
        $checkout = null;
        foreach ($accepted as $event) {
            if ($event['direction'] === 'IN' && ($entry === null || $event['occurred_at'] < $entry['occurred_at'])) {
                $entry = $event;
            }
            if ($event['direction'] === 'OUT' && ($checkout === null || $event['occurred_at'] > $checkout['occurred_at'])) {
                $checkout = $event;
            }
        }

        if ($entry !== null && $checkout !== null) {
            $summary_status = 'Complete entry and checkout';
        } elseif ($entry !== null) {
            $summary_status = 'Entry recorded; checkout pending';
        } elseif ($checkout !== null) {
            $summary_status = 'Checkout received; entry needs reconciliation';
        } else {
            $summary_status = 'No attendance change';
        }

        return array(
            'events'            => $display_events,
            'normalized_events' => $accepted,
            'processing_log'    => $processing_log,
            'counts'            => $counts,
            'attendance_preview' => array(
                'student_code' => $employee,
                'date'         => $entry !== null
                    ? substr($entry['occurred_at'], 0, 10)
                    : ($checkout !== null ? substr($checkout['occurred_at'], 0, 10) : null),
                'entry_time'   => $entry !== null ? substr($entry['occurred_at'], 11, 8) : null,
                'checkout_time'=> $checkout !== null ? substr($checkout['occurred_at'], 11, 8) : null,
                'status'       => $summary_status,
            ),
        );
    }

    /**
     * @param array<string, mixed> $event
     * @param array<string, bool> $seen_ids
     * @return array{status: string, message: string, direction: string}
     */
    private function eventDecision(array $event, array &$seen_ids, $employee, $entry_serial, $exit_serial)
    {
        $id = isset($event['id']) && is_scalar($event['id']) ? trim((string) $event['id']) : '';
        if ($id === '') {
            return array('status' => 'rejected', 'message' => 'Rejected: missing stable vendor event ID.', 'direction' => 'UNKNOWN');
        }
        if (isset($seen_ids[$id])) {
            return array('status' => 'duplicate', 'message' => 'Ignored: this stable vendor event ID was already processed.', 'direction' => 'DUPLICATE');
        }
        $seen_ids[$id] = true;

        $event_employee = isset($event['emp_code']) ? trim((string) $event['emp_code']) : '';
        if ($event_employee === '') {
            return array('status' => 'rejected', 'message' => 'Rejected: employee/student code is missing.', 'direction' => 'UNKNOWN');
        }
        if (!hash_equals($employee, $event_employee)) {
            return array('status' => 'quarantined', 'message' => 'Quarantined: no SchoolLift student mapping exists for this code.', 'direction' => 'UNKNOWN');
        }

        $serial = isset($event['terminal_sn']) ? trim((string) $event['terminal_sn']) : '';
        if (!in_array($serial, array($entry_serial, $exit_serial), true)) {
            return array('status' => 'quarantined', 'message' => 'Quarantined: the terminal is not registered for this school.', 'direction' => 'UNKNOWN');
        }

        $timestamp = isset($event['punch_time']) ? trim((string) $event['punch_time']) : '';
        $parsed = DateTimeImmutable::createFromFormat('!Y-m-d H:i:s', $timestamp, $this->timezone);
        $errors = DateTimeImmutable::getLastErrors();
        $has_errors = is_array($errors)
            && ((int) $errors['warning_count'] > 0 || (int) $errors['error_count'] > 0);
        if ($parsed === false || $has_errors || $parsed->format('Y-m-d H:i:s') !== $timestamp) {
            return array('status' => 'rejected', 'message' => 'Rejected: punch_time is not a valid local date and time.', 'direction' => 'UNKNOWN');
        }

        $state = isset($event['punch_state']) ? (string) $event['punch_state'] : '';
        if (!in_array($state, array('0', '1'), true)) {
            return array('status' => 'rejected', 'message' => 'Rejected: punch_state must identify IN (0) or OUT (1).', 'direction' => 'UNKNOWN');
        }

        $direction = $state === '0' ? 'IN' : 'OUT';
        return array(
            'status'    => 'accepted',
            'message'   => 'Accepted as a unique ' . $direction . ' event after identity, terminal, timestamp, and direction validation.',
            'direction' => $direction,
        );
    }
}
