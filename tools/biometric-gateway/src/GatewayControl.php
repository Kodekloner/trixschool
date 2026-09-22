<?php

declare(strict_types=1);

namespace SchoolLift\BiometricGateway;

use DateTimeImmutable;
use RuntimeException;

/**
 * Outbound-only control channel for the school website.
 *
 * This class accepts only three named operations. It never evaluates command
 * text, starts a process, opens a shell, or accepts command arguments.
 */
final class GatewayControl
{
    private const ALLOWED_COMMANDS = ['connection_test', 'sync_now', 'retry_failed'];

    private GatewayStore $store;
    private SchoolLiftClient $schoolLift;
    private Clock $clock;
    private int $timeoutSeconds;

    public function __construct(
        GatewayStore $store,
        SchoolLiftClient $schoolLift,
        Clock $clock,
        int $timeoutSeconds
    ) {
        $this->store = $store;
        $this->schoolLift = $schoolLift;
        $this->clock = $clock;
        $this->timeoutSeconds = max(1, min(120, $timeoutSeconds));
    }

    /** @return array<string, mixed>|null */
    public function poll(bool $canAcceptCommand): ?array
    {
        $canAcceptCommand = $canAcceptCommand && !$this->store->hasOutstandingCommand();
        $response = $this->schoolLift->pollGatewayControl(
            $this->store->heartbeatStatus($canAcceptCommand),
            $this->timeoutSeconds
        );
        $this->requireSuccess($response, 'Gateway heartbeat');
        if (!is_array($response['json']) || !array_key_exists('command', $response['json'])) {
            throw new RuntimeException('Gateway heartbeat response omitted the command field.');
        }
        $command = $response['json']['command'];
        if ($command === null) {
            return null;
        }
        if (!$canAcceptCommand) {
            throw new RuntimeException('SchoolLift returned a command when this gateway was not ready to claim one.');
        }
        $validated = $this->validateCommand($command);
        $this->store->claimCommand($validated, $this->clock->now());
        return $this->store->claimedCommand();
    }

    /** @return array<string, mixed>|null */
    public function claimedCommand(): ?array
    {
        return $this->store->claimedCommand();
    }

    /** @param array<string, mixed> $result */
    public function complete(string $commandUuid, bool $succeeded, array $result): void
    {
        $this->store->completeCommand(
            $commandUuid,
            $succeeded ? 'succeeded' : 'failed',
            $this->safeResult($result),
            $this->clock->now()
        );
    }

    /** Report one durable result. Returns true only when a result was acknowledged. */
    public function reportPending(): bool
    {
        $pending = $this->store->pendingCommandResult();
        if ($pending === null) {
            return false;
        }
        $response = $this->schoolLift->reportGatewayCommand(
            (string) $pending['command_uuid'],
            (string) $pending['status'],
            $pending['result'],
            $this->timeoutSeconds
        );
        $this->requireSuccess($response, 'Gateway command result');
        $this->store->markCommandReported((string) $pending['command_uuid'], $this->clock->now());
        return true;
    }

    /** @param mixed $command @return array{command_uuid:string,type:string,expires_at:string} */
    private function validateCommand($command): array
    {
        if (!is_array($command)) {
            throw new RuntimeException('Gateway command must be a JSON object.');
        }
        $uuid = strtolower(trim((string) ($command['command_uuid'] ?? '')));
        $type = strtolower(trim((string) ($command['type'] ?? '')));
        $expiresAt = trim((string) ($command['expires_at'] ?? ''));
        if (!preg_match('/^[a-f0-9]{32}$/', $uuid)) {
            throw new RuntimeException('SchoolLift returned an invalid gateway command identifier.');
        }
        if (!in_array($type, self::ALLOWED_COMMANDS, true)) {
            throw new RuntimeException('SchoolLift returned an unsupported gateway command type.');
        }
        if (!preg_match('/(?:Z|[+-](?:0\d|1\d|2[0-3]):[0-5]\d)$/i', $expiresAt)) {
            throw new RuntimeException('SchoolLift returned a gateway command expiry without a UTC offset.');
        }
        try {
            $expiry = new DateTimeImmutable($expiresAt);
        } catch (\Throwable $error) {
            throw new RuntimeException('SchoolLift returned an invalid gateway command expiry.');
        }
        // SchoolLift enforces this deadline before its first durable claim. A
        // claimed command may be re-delivered after the displayed deadline if
        // a gateway crashed before reporting; accepting the same UUID makes
        // that recovery idempotent instead of wedging the control channel.
        return ['command_uuid' => $uuid, 'type' => $type, 'expires_at' => $expiry->format(DATE_ATOM)];
    }

    /** @param array{status:int,headers:array<string,string>,body:string,json:mixed} $response */
    private function requireSuccess(array $response, string $operation): void
    {
        if ($response['status'] < 200 || $response['status'] > 299) {
            throw new RuntimeException($operation . ' failed with HTTP ' . (int) $response['status'] . '.');
        }
    }

    /** @param array<string, mixed> $result @return array<string, mixed> */
    private function safeResult(array $result): array
    {
        $allowed = ['summary', 'provider', 'schoollift', 'queue', 'retried', 'message'];
        $safe = [];
        foreach ($allowed as $key) {
            if (array_key_exists($key, $result)) {
                $safe[$key] = $this->safeValue($result[$key], 0);
            }
        }
        return $safe;
    }

    /** @param mixed $value @return mixed */
    private function safeValue($value, int $depth)
    {
        if ($depth >= 3) {
            return null;
        }
        if ($value === null || is_bool($value) || is_int($value) || is_float($value)) {
            return $value;
        }
        if (is_string($value)) {
            $value = preg_replace('/[\x00-\x1F\x7F]+/', ' ', $value) ?? '';
            return function_exists('mb_substr') ? mb_substr($value, 0, 500) : substr($value, 0, 500);
        }
        if (!is_array($value)) {
            return null;
        }
        $safe = [];
        foreach (array_slice($value, 0, 40, true) as $key => $item) {
            $safeKey = is_int($key) ? $key : substr((string) $key, 0, 64);
            if (is_string($safeKey) && preg_match('/token|password|secret|authorization/i', $safeKey)) {
                continue;
            }
            $safe[$safeKey] = $this->safeValue($item, $depth + 1);
        }
        return $safe;
    }
}
