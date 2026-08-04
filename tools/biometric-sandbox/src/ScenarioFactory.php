<?php

declare(strict_types=1);

namespace SchoolLift\BiometricSandbox;

use DateTimeImmutable;
use DateTimeZone;
use InvalidArgumentException;

final class ScenarioFactory
{
    /** @var DateTimeZone */
    private $timezone;

    public function __construct(?DateTimeZone $timezone = null)
    {
        $this->timezone = $timezone ?: new DateTimeZone('Africa/Lagos');
    }

    /** @return array<int, string> */
    public static function names(): array
    {
        return [
            'normal',
            'duplicate',
            'delayed',
            'out-of-order',
            'unknown-student',
            'unknown-device',
            'invalid-event',
            'server-error',
            'rate-limit',
            'auth-error',
        ];
    }

    /**
     * @return array{
     *   events: array<int, array<string, mixed>>,
     *   failure: array<string, mixed>|null,
     *   auth_error: bool,
     *   description: string
     * }
     */
    public function make(
        string $name,
        string $employee,
        string $date,
        string $entrySerial,
        string $exitSerial,
        int $delaySeconds = 5,
        ?DateTimeImmutable $now = null
    ): array {
        if (!in_array($name, self::names(), true)) {
            throw new InvalidArgumentException(
                'Unknown scenario "' . $name . '". Choose: ' . implode(', ', self::names())
            );
        }

        $day = (new DateTimeImmutable($date, $this->timezone))->format('Y-m-d');
        $now = $now ?: new DateTimeImmutable('now', $this->timezone);
        $delaySeconds = max(0, min($delaySeconds, 86400));

        $in = $this->transaction($employee, $day . ' 07:30:00', '0', $entrySerial, 'Main Gate - IN');
        $out = $this->transaction($employee, $day . ' 15:10:00', '1', $exitSerial, 'Main Gate - OUT');

        switch ($name) {
            case 'normal':
                return $this->result(
                    [$in, $out],
                    'A normal school day: ENTRY is returned before EXIT.'
                );

            case 'duplicate':
                $in['id'] = 900001;
                $duplicate = $in;
                $duplicate['upload_time'] = $day . ' 07:31:00';

                return $this->result(
                    [$in, $duplicate, $out],
                    'The exact same external transaction ID is delivered twice.'
                );

            case 'delayed':
                $out['upload_time'] = $day . ' 17:30:00';
                $out['_sandbox_visible_at'] = $now->modify('+' . $delaySeconds . ' seconds')->format(DATE_ATOM);

                return $this->result(
                    [$in, $out],
                    'EXIT occurred earlier but remains hidden from API polling until the configured delay expires.'
                );

            case 'out-of-order':
                return $this->result(
                    [$out, $in],
                    'EXIT receives the lower API sequence and is returned before the earlier ENTRY event.'
                );

            case 'unknown-student':
                return $this->result(
                    [
                        $this->transaction('UNKNOWN-STUDENT', $day . ' 07:35:00', '0', $entrySerial, 'Main Gate - IN'),
                    ],
                    'The device user code has no corresponding student mapping.'
                );

            case 'unknown-device':
                return $this->result(
                    [
                        $this->transaction($employee, $day . ' 07:36:00', '0', 'UNKNOWN-DEVICE', 'Unregistered Terminal'),
                    ],
                    'The terminal serial number is not registered by the consuming application.'
                );

            case 'invalid-event':
                return $this->result(
                    [[
                        'punch_time' => 'not-a-date',
                        'punch_state' => '9',
                        'terminal_sn' => $entrySerial,
                        'terminal_alias' => 'Malformed Terminal Event',
                        'verify_type' => 15,
                        'upload_time' => $day . ' 07:40:00',
                    ]],
                    'A malformed vendor record has no employee code, an invalid timestamp and an unsupported state.'
                );

            case 'server-error':
                return $this->result(
                    [],
                    'The next transactions request receives HTTP 503 and should be retried safely.',
                    ['status' => 503, 'count' => 1, 'message' => 'Simulated ZKBio Time maintenance window.']
                );

            case 'rate-limit':
                return $this->result(
                    [],
                    'The next transactions request receives HTTP 429 and should use backoff.',
                    ['status' => 429, 'count' => 1, 'message' => 'Simulated API rate limit.']
                );

            case 'auth-error':
                $result = $this->result([], 'Token authentication is attempted with an intentionally bad password.');
                $result['auth_error'] = true;

                return $result;
        }

        throw new InvalidArgumentException('Unhandled scenario: ' . $name);
    }

    /**
     * @return array<string, mixed>
     */
    private function transaction(
        string $employee,
        string $punchTime,
        string $punchState,
        string $serial,
        string $alias
    ): array {
        return [
            'emp_code' => $employee,
            'punch_time' => $punchTime,
            'punch_state' => $punchState,
            'verify_type' => 15,
            'terminal_sn' => $serial,
            'terminal_alias' => $alias,
            'area_alias' => 'School Main Gate',
            'is_attendance' => 1,
            'upload_time' => $punchTime,
        ];
    }

    /**
     * @param array<int, array<string, mixed>> $events
     * @param array<string, mixed>|null $failure
     * @return array{events: array<int, array<string, mixed>>, failure: array<string, mixed>|null, auth_error: bool, description: string}
     */
    private function result(array $events, string $description, ?array $failure = null): array
    {
        return [
            'events' => $events,
            'failure' => $failure,
            'auth_error' => false,
            'description' => $description,
        ];
    }
}
