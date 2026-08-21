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
        $this->setMetadata('schema_version', '2', new DateTimeImmutable('now'));
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
        return [
            'provider_cursor' => $this->providerCursor(),
            'provider_next_poll_at' => $this->metadata('provider_next_poll_at'),
            'provider_failure_count' => (int) ($this->metadata('provider_failure_count') ?? 0),
            'pending' => $counts['pending'],
            'delivered' => $counts['delivered'],
            'dead' => $counts['dead'],
        ];
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
