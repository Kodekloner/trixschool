<?php

declare(strict_types=1);

namespace SchoolLift\BiometricGateway;

use DateTimeImmutable;
use PDO;
use RuntimeException;
use Throwable;

final class GatewayStore
{
    private PDO $database;

    public function __construct(string $path)
    {
        if (!extension_loaded('pdo_sqlite')) {
            throw new RuntimeException('The PHP pdo_sqlite extension is required.');
        }
        $directory = dirname($path);
        if (!is_dir($directory) && !mkdir($directory, 0770, true) && !is_dir($directory)) {
            throw new RuntimeException('Unable to create gateway database directory: ' . $directory);
        }
        $this->database = new PDO('sqlite:' . $path, null, null, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]);
        $this->database->exec('PRAGMA journal_mode = WAL');
        $this->database->exec('PRAGMA synchronous = FULL');
        $this->database->exec('PRAGMA busy_timeout = 5000');
        $this->migrate();
    }

    private function migrate(): void
    {
        $this->database->exec(
            'CREATE TABLE IF NOT EXISTS gateway_metadata (
                metadata_key TEXT PRIMARY KEY,
                metadata_value TEXT NOT NULL,
                updated_at TEXT NOT NULL
            )'
        );
        $this->database->exec(
            'CREATE TABLE IF NOT EXISTS event_queue (
                external_event_id TEXT PRIMARY KEY,
                payload_json TEXT NOT NULL,
                provider_cursor TEXT NOT NULL DEFAULT \'\',
                state TEXT NOT NULL DEFAULT \'pending\',
                attempts INTEGER NOT NULL DEFAULT 0,
                available_at TEXT NOT NULL,
                delivery_status TEXT NULL,
                last_http_status INTEGER NULL,
                last_error TEXT NULL,
                created_at TEXT NOT NULL,
                updated_at TEXT NOT NULL,
                delivered_at TEXT NULL
            )'
        );
        $columns = $this->database->query('PRAGMA table_info(event_queue)')->fetchAll();
        $hasProviderCursor = false;
        foreach ($columns as $column) {
            if ((string) ($column['name'] ?? '') === 'provider_cursor') {
                $hasProviderCursor = true;
                break;
            }
        }
        if (!$hasProviderCursor) {
            $this->database->exec(
                'ALTER TABLE event_queue ADD COLUMN provider_cursor TEXT NOT NULL DEFAULT \'\''
            );
            $this->database->exec(
                'UPDATE event_queue
                 SET provider_cursor = COALESCE(
                    (SELECT metadata_value FROM gateway_metadata WHERE metadata_key = \'provider_cursor\'),
                    \'\'
                 )'
            );
        }
        $this->database->exec(
            'CREATE INDEX IF NOT EXISTS idx_event_queue_delivery
             ON event_queue (state, available_at, created_at)'
        );
        $this->database->exec(
            'CREATE TABLE IF NOT EXISTS gateway_commands (
                command_uuid TEXT PRIMARY KEY,
                command_type TEXT NOT NULL,
                expires_at TEXT NOT NULL,
                state TEXT NOT NULL DEFAULT \'claimed\',
                result_status TEXT NULL,
                result_json TEXT NULL,
                created_at TEXT NOT NULL,
                updated_at TEXT NOT NULL,
                reported_at TEXT NULL
            )'
        );
        $this->database->exec(
            'CREATE INDEX IF NOT EXISTS idx_gateway_commands_state
             ON gateway_commands (state, created_at)'
        );
        $this->setMetadata('schema_version', '3', new DateTimeImmutable('now'));
    }

    /**
     * Atomically persists normalized events and advances the polling cursor.
     * A crash before commit advances neither; a crash after commit loses neither.
     *
     * @param array<int, array<string, mixed>> $events
     * @return array{inserted:int,duplicates:int}
     */
    public function enqueueAndAdvance(array $events, string $cursor, DateTimeImmutable $now): array
    {
        $timestamp = $now->format(DATE_ATOM);
        $inserted = 0;
        $duplicates = 0;
        $this->database->beginTransaction();
        try {
            $statement = $this->database->prepare(
                'INSERT OR IGNORE INTO event_queue
                 (external_event_id, payload_json, provider_cursor, state, attempts, available_at, created_at, updated_at)
                 VALUES (:external_event_id, :payload_json, :provider_cursor, \'pending\', 0, :available_at, :created_at, :updated_at)'
            );
            foreach ($events as $event) {
                $externalId = (string) ($event['external_event_id'] ?? '');
                if ($externalId === '') {
                    throw new RuntimeException('A normalized event is missing external_event_id.');
                }
                $json = json_encode($event, JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE);
                if ($json === false) {
                    throw new RuntimeException('Unable to encode normalized event ' . $externalId . '.');
                }
                $statement->execute([
                    ':external_event_id' => $externalId,
                    ':payload_json' => $json,
                    ':provider_cursor' => $cursor,
                    ':available_at' => $timestamp,
                    ':created_at' => $timestamp,
                    ':updated_at' => $timestamp,
                ]);
                if ($statement->rowCount() === 1) {
                    $inserted++;
                } else {
                    $duplicates++;
                }
            }
            $this->setMetadata('provider_cursor', $cursor, $now);
            $this->database->commit();
        } catch (Throwable $error) {
            if ($this->database->inTransaction()) {
                $this->database->rollBack();
            }
            throw $error;
        }

        return ['inserted' => $inserted, 'duplicates' => $duplicates];
    }

    public function providerCursor(): ?string
    {
        return $this->metadata('provider_cursor');
    }

    /**
     * Returns one replay-stable batch. Rows from different provider polls are
     * deliberately not mixed because their committed cursor is part of the
     * SchoolLift batch request and idempotency hash.
     *
     * @return array<int, array{external_event_id:string,payload:array<string,mixed>,attempts:int,provider_cursor:string}>
     */
    public function pending(int $limit, DateTimeImmutable $now): array
    {
        $cursorStatement = $this->database->prepare(
            'SELECT provider_cursor
             FROM event_queue
             WHERE state = \'pending\' AND available_at <= :available_at
             ORDER BY created_at ASC, external_event_id ASC
             LIMIT 1'
        );
        $cursorStatement->execute([':available_at' => $now->format(DATE_ATOM)]);
        $providerCursor = $cursorStatement->fetchColumn();
        if ($providerCursor === false) {
            return [];
        }

        $statement = $this->database->prepare(
            'SELECT external_event_id, payload_json, attempts, provider_cursor
             FROM event_queue
             WHERE state = \'pending\' AND available_at <= :available_at
               AND provider_cursor = :provider_cursor
             ORDER BY created_at ASC, external_event_id ASC
             LIMIT :limit'
        );
        $statement->bindValue(':available_at', $now->format(DATE_ATOM));
        $statement->bindValue(':provider_cursor', (string) $providerCursor);
        $statement->bindValue(':limit', max(1, $limit), PDO::PARAM_INT);
        $statement->execute();
        $rows = [];
        foreach ($statement->fetchAll() as $row) {
            $payload = json_decode((string) $row['payload_json'], true);
            if (!is_array($payload)) {
                throw new RuntimeException('Queued event contains invalid JSON: ' . $row['external_event_id']);
            }
            $rows[] = [
                'external_event_id' => (string) $row['external_event_id'],
                'payload' => $payload,
                'attempts' => (int) $row['attempts'],
                'provider_cursor' => (string) $row['provider_cursor'],
            ];
        }
        return $rows;
    }

    public function markDelivered(
        string $externalId,
        string $deliveryStatus,
        ?string $message,
        ?int $httpStatus,
        DateTimeImmutable $now
    ): void {
        $statement = $this->database->prepare(
            'UPDATE event_queue
             SET state = \'delivered\', delivery_status = :delivery_status,
                 last_http_status = :http_status, last_error = :last_error,
                 updated_at = :updated_at, delivered_at = :delivered_at
             WHERE external_event_id = :external_event_id'
        );
        $statement->execute([
            ':delivery_status' => $deliveryStatus,
            ':http_status' => $httpStatus,
            ':last_error' => $this->bounded($message),
            ':updated_at' => $now->format(DATE_ATOM),
            ':delivered_at' => $now->format(DATE_ATOM),
            ':external_event_id' => $externalId,
        ]);
    }

    /** @param array<int, string> $externalIds */
    public function defer(
        array $externalIds,
        string $message,
        ?int $httpStatus,
        DateTimeImmutable $availableAt,
        int $maximumAttempts,
        DateTimeImmutable $now
    ): void {
        $select = $this->database->prepare(
            'SELECT attempts FROM event_queue WHERE external_event_id = :external_event_id'
        );
        $update = $this->database->prepare(
            'UPDATE event_queue
             SET state = :state, attempts = :attempts, available_at = :available_at,
                 last_http_status = :http_status, last_error = :last_error, updated_at = :updated_at
             WHERE external_event_id = :external_event_id'
        );
        $this->database->beginTransaction();
        try {
            foreach ($externalIds as $externalId) {
                $select->execute([':external_event_id' => $externalId]);
                $attempts = ((int) ($select->fetchColumn() ?: 0)) + 1;
                $update->execute([
                    ':state' => $attempts >= $maximumAttempts ? 'dead' : 'pending',
                    ':attempts' => $attempts,
                    ':available_at' => $availableAt->format(DATE_ATOM),
                    ':http_status' => $httpStatus,
                    ':last_error' => $this->bounded($message),
                    ':updated_at' => $now->format(DATE_ATOM),
                    ':external_event_id' => $externalId,
                ]);
            }
            $this->database->commit();
        } catch (Throwable $error) {
            if ($this->database->inTransaction()) {
                $this->database->rollBack();
            }
            throw $error;
        }
    }

    /** @param array<int, string> $externalIds */
    public function markDead(
        array $externalIds,
        string $message,
        ?int $httpStatus,
        DateTimeImmutable $now
    ): void {
        $statement = $this->database->prepare(
            'UPDATE event_queue
             SET state = \'dead\', attempts = attempts + 1, last_http_status = :http_status,
                 last_error = :last_error, updated_at = :updated_at
             WHERE external_event_id = :external_event_id'
        );
        foreach ($externalIds as $externalId) {
            $statement->execute([
                ':http_status' => $httpStatus,
                ':last_error' => $this->bounded($message),
                ':updated_at' => $now->format(DATE_ATOM),
                ':external_event_id' => $externalId,
            ]);
        }
    }

    public function retryDead(DateTimeImmutable $now): int
    {
        $statement = $this->database->prepare(
            'UPDATE event_queue
             SET state = \'pending\', attempts = 0, available_at = :available_at,
                 last_error = NULL, updated_at = :updated_at
             WHERE state = \'dead\''
        );
        $statement->execute([
            ':available_at' => $now->format(DATE_ATOM),
            ':updated_at' => $now->format(DATE_ATOM),
        ]);
        return $statement->rowCount();
    }

    /** @return array<string, int|string|null> */
    public function status(): array
    {
        $counts = ['pending' => 0, 'delivered' => 0, 'dead' => 0];
        foreach ($this->database->query('SELECT state, COUNT(*) AS total FROM event_queue GROUP BY state') as $row) {
            $counts[(string) $row['state']] = (int) $row['total'];
        }
        $retry = (int) $this->database->query(
            "SELECT COUNT(*) FROM event_queue WHERE state = 'pending' AND attempts > 0"
        )->fetchColumn();
        return [
            'provider_cursor' => $this->providerCursor(),
            'provider_next_poll_at' => $this->metadata('provider_next_poll_at'),
            'provider_failure_count' => (int) ($this->metadata('provider_failure_count') ?? 0),
            'pending' => $counts['pending'],
            'retry' => $retry,
            'delivered' => $counts['delivered'],
            'dead' => $counts['dead'],
        ];
    }

    /** @param array<string, mixed> $summary */
    public function recordSyncResult(array $summary, DateTimeImmutable $now): void
    {
        $provider = isset($summary['provider']) && is_array($summary['provider'])
            ? $summary['provider'] : [];
        if (($provider['polled'] ?? false) === true) {
            $this->setMetadata('provider_reachable', '1', $now);
        } elseif (isset($provider['error'])) {
            $this->setMetadata('provider_reachable', '0', $now);
        }

        $backingOff = ($provider['backoff'] ?? false) === true;
        $ok = ($summary['ok'] ?? false) === true && !$backingOff;
        $error = '';
        if (isset($summary['delivery']['error']) && is_scalar($summary['delivery']['error'])) {
            $error = trim((string) $summary['delivery']['error']);
        }
        if ($error === '' && isset($provider['error']) && is_scalar($provider['error'])) {
            $error = trim((string) $provider['error']);
        }
        if ($error === '' && $backingOff) {
            $error = 'ZKBio polling is waiting for its automatic retry time.';
        }
        $this->setMetadata('last_sync_ok', $ok ? '1' : '0', $now);
        $this->setMetadata('last_sync_at', $now->setTimezone(new \DateTimeZone('UTC'))->format(DATE_ATOM), $now);
        $this->setMetadata('last_sync_error', $this->bounded($error) ?? '', $now);
    }

    /** @return array<string, mixed> */
    public function heartbeatStatus(bool $canAcceptCommand): array
    {
        $queue = $this->status();
        $providerReachable = $this->storedBoolean('provider_reachable');
        $lastSyncOk = $this->storedBoolean('last_sync_ok');
        $lastSyncAt = $this->metadata('last_sync_at');
        $lastError = $this->metadata('last_sync_error');

        return [
            'can_accept_command' => $canAcceptCommand,
            'provider_reachable' => $providerReachable,
            'last_sync_ok' => $lastSyncOk,
            'last_sync_at' => $lastSyncAt === null || $lastSyncAt === '' ? null : $lastSyncAt,
            'queue' => [
                'pending' => (int) $queue['pending'],
                'retry' => (int) $queue['retry'],
                'dead' => (int) $queue['dead'],
            ],
            'cursor' => $queue['provider_cursor'] === null || $queue['provider_cursor'] === ''
                ? null : (string) $queue['provider_cursor'],
            'last_error' => $lastError === null || $lastError === '' ? null : $lastError,
        ];
    }

    /** @param array{command_uuid:string,type:string,expires_at:string} $command */
    public function claimCommand(array $command, DateTimeImmutable $now): void
    {
        $statement = $this->database->prepare(
            'INSERT OR IGNORE INTO gateway_commands
             (command_uuid, command_type, expires_at, state, created_at, updated_at)
             VALUES (:command_uuid, :command_type, :expires_at, \'claimed\', :created_at, :updated_at)'
        );
        $statement->execute([
            ':command_uuid' => $command['command_uuid'],
            ':command_type' => $command['type'],
            ':expires_at' => $command['expires_at'],
            ':created_at' => $now->format(DATE_ATOM),
            ':updated_at' => $now->format(DATE_ATOM),
        ]);
    }

    /** @return array<string, mixed>|null */
    public function claimedCommand(): ?array
    {
        $row = $this->database->query(
            "SELECT command_uuid, command_type, expires_at, state
             FROM gateway_commands WHERE state = 'claimed'
             ORDER BY created_at ASC LIMIT 1"
        )->fetch();
        return is_array($row) ? $row : null;
    }

    public function hasOutstandingCommand(): bool
    {
        return (int) $this->database->query(
            "SELECT COUNT(*) FROM gateway_commands WHERE state IN ('claimed', 'result_pending')"
        )->fetchColumn() > 0;
    }

    /** @param array<string, mixed> $result */
    public function completeCommand(
        string $commandUuid,
        string $status,
        array $result,
        DateTimeImmutable $now
    ): void {
        $encoded = json_encode($result, JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE);
        if ($encoded === false) {
            throw new RuntimeException('Unable to encode the gateway command result.');
        }
        $statement = $this->database->prepare(
            "UPDATE gateway_commands
             SET state = 'result_pending', result_status = :result_status,
                 result_json = :result_json, updated_at = :updated_at
             WHERE command_uuid = :command_uuid AND state = 'claimed'"
        );
        $statement->execute([
            ':result_status' => $status,
            ':result_json' => $encoded,
            ':updated_at' => $now->format(DATE_ATOM),
            ':command_uuid' => $commandUuid,
        ]);
    }

    /** @return array<string, mixed>|null */
    public function pendingCommandResult(): ?array
    {
        $row = $this->database->query(
            "SELECT command_uuid, result_status, result_json
             FROM gateway_commands WHERE state = 'result_pending'
             ORDER BY created_at ASC LIMIT 1"
        )->fetch();
        if (!is_array($row)) {
            return null;
        }
        $result = json_decode((string) $row['result_json'], true);
        if (!is_array($result)) {
            throw new RuntimeException('A stored gateway command result is invalid.');
        }
        return [
            'command_uuid' => (string) $row['command_uuid'],
            'status' => (string) $row['result_status'],
            'result' => $result,
        ];
    }

    public function markCommandReported(string $commandUuid, DateTimeImmutable $now): void
    {
        $statement = $this->database->prepare(
            "UPDATE gateway_commands SET state = 'reported', reported_at = :reported_at,
                 updated_at = :updated_at WHERE command_uuid = :command_uuid
                 AND state = 'result_pending'"
        );
        $statement->execute([
            ':reported_at' => $now->format(DATE_ATOM),
            ':updated_at' => $now->format(DATE_ATOM),
            ':command_uuid' => $commandUuid,
        ]);
    }

    public function providerCanPoll(DateTimeImmutable $now): bool
    {
        $next = $this->metadata('provider_next_poll_at');
        if ($next === null || $next === '') {
            return true;
        }
        try {
            return new DateTimeImmutable($next) <= $now;
        } catch (Throwable $error) {
            return true;
        }
    }

    public function recordProviderFailure(DateTimeImmutable $nextPollAt, DateTimeImmutable $now): int
    {
        $count = ((int) ($this->metadata('provider_failure_count') ?? 0)) + 1;
        $this->setMetadata('provider_failure_count', (string) $count, $now);
        $this->setMetadata('provider_next_poll_at', $nextPollAt->format(DATE_ATOM), $now);
        return $count;
    }

    public function clearProviderFailure(DateTimeImmutable $now): void
    {
        $this->setMetadata('provider_failure_count', '0', $now);
        $this->setMetadata('provider_next_poll_at', '', $now);
    }

    public function providerFailureCount(): int
    {
        return (int) ($this->metadata('provider_failure_count') ?? 0);
    }

    public function purgeDeliveredBefore(DateTimeImmutable $cutoff): int
    {
        $statement = $this->database->prepare(
            'DELETE FROM event_queue WHERE state = \'delivered\' AND delivered_at < :cutoff'
        );
        $statement->execute([':cutoff' => $cutoff->format(DATE_ATOM)]);
        return $statement->rowCount();
    }

    private function metadata(string $key): ?string
    {
        $statement = $this->database->prepare(
            'SELECT metadata_value FROM gateway_metadata WHERE metadata_key = :metadata_key'
        );
        $statement->execute([':metadata_key' => $key]);
        $value = $statement->fetchColumn();
        return $value === false ? null : (string) $value;
    }

    private function storedBoolean(string $key): ?bool
    {
        $value = $this->metadata($key);
        if ($value === '1') {
            return true;
        }
        if ($value === '0') {
            return false;
        }
        return null;
    }

    private function setMetadata(string $key, string $value, DateTimeImmutable $now): void
    {
        $statement = $this->database->prepare(
            'INSERT INTO gateway_metadata (metadata_key, metadata_value, updated_at)
             VALUES (:metadata_key, :metadata_value, :updated_at)
             ON CONFLICT(metadata_key) DO UPDATE
             SET metadata_value = excluded.metadata_value, updated_at = excluded.updated_at'
        );
        $statement->execute([
            ':metadata_key' => $key,
            ':metadata_value' => $value,
            ':updated_at' => $now->format(DATE_ATOM),
        ]);
    }

    private function bounded(?string $message): ?string
    {
        if ($message === null) {
            return null;
        }
        return function_exists('mb_substr') ? mb_substr($message, 0, 1000) : substr($message, 0, 1000);
    }
}
