<?php

declare(strict_types=1);

namespace SchoolLift\BiometricGateway;

use DateTimeImmutable;
use DateTimeZone;
use Throwable;

final class EventNormalizer
{
    private DateTimeZone $providerTimezone;
    /** @var array<string, string> */
    private array $verificationMap;

    /** @param array<string, string> $verificationMap */
    public function __construct(string $providerTimezone, array $verificationMap)
    {
        $this->providerTimezone = new DateTimeZone($providerTimezone);
        $this->verificationMap = $verificationMap;
    }

    /** @param array<string, mixed> $transaction @return array<string, mixed> */
    public function normalize(array $transaction): array
    {
        $person = $this->scalar($transaction['emp_code'] ?? '', 100);
        $serial = $this->scalar($transaction['terminal_sn'] ?? '', 100);
        $punchState = $this->scalar($transaction['punch_state'] ?? '', 32);
        $rawTime = $this->scalar($transaction['punch_time'] ?? '', 64);
        $occurredAt = $rawTime;
        try {
            if ($rawTime !== '') {
                $occurredAt = (new DateTimeImmutable($rawTime, $this->providerTimezone))
                    ->setTimezone(new DateTimeZone('UTC'))
                    ->format(DATE_ATOM);
            }
        } catch (Throwable $error) {
            // Preserve a bounded malformed value so the authoritative API can
            // quarantine it; never invent a timestamp.
        }

        $providerId = $this->scalar($transaction['id'] ?? '', 128);
        if (strlen($providerId) > 64) {
            $providerId = 'hash-' . hash('sha256', $providerId);
        }
        if ($providerId === '') {
            $providerId = 'hash-' . hash('sha256', implode('|', [
                $person,
                $rawTime,
                $serial,
                $punchState,
                $this->scalar($transaction['verify_type'] ?? '', 32),
            ]));
        }
        $verificationCode = $this->scalar($transaction['verify_type'] ?? '', 32);
        $verification = $this->verificationMap[$verificationCode] ?? 'unknown';
        if (!in_array($verification, ['face', 'fingerprint', 'card', 'pin', 'qr', 'unknown'], true)) {
            $verification = 'unknown';
        }

        // Deliberate allowlist: no face image, fingerprint template, photo,
        // binary blob, or arbitrary provider metadata can leave this gateway.
        return [
            'external_event_id' => 'zkbio:' . ($serial !== '' ? $serial : 'unknown') . ':' . $providerId,
            'person_code' => $person,
            'occurred_at' => $occurredAt,
            'device_serial' => $serial,
            'punch_state' => $punchState,
            'verification_method' => $verification,
        ];
    }

    /** @param mixed $value */
    private function scalar($value, int $maximum): string
    {
        if (!is_scalar($value) && $value !== null) {
            return '';
        }
        $text = trim((string) $value);
        return function_exists('mb_substr') ? mb_substr($text, 0, $maximum) : substr($text, 0, $maximum);
    }
}
