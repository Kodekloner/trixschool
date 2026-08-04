<?php

declare(strict_types=1);

namespace SchoolLift\BiometricSandbox;

use DateTimeImmutable;
use DateTimeZone;
use RuntimeException;
use Throwable;

final class JsonFileStore
{
    /** @var string */
    private $path;

    /** @var DateTimeZone */
    private $timezone;

    public function __construct(string $path, ?DateTimeZone $timezone = null)
    {
        if ($path === '') {
            throw new RuntimeException('The sandbox state path cannot be empty.');
        }

        $directory = dirname($path);
        if (!is_dir($directory) && !mkdir($directory, 0775, true) && !is_dir($directory)) {
            throw new RuntimeException('Unable to create sandbox state directory: ' . $directory);
        }

        $this->path = $path;
        $this->timezone = $timezone ?: new DateTimeZone('Africa/Lagos');
    }

    public function reset(): void
    {
        $this->writeState($this->initialState());
    }

    /**
     * @param array<int, array<string, mixed>> $transactions
     * @return array<int, array<string, mixed>>
     */
    public function appendTransactions(array $transactions): array
    {
        return $this->mutateState(function (array &$state) use ($transactions): array {
            $created = [];

            foreach ($transactions as $transaction) {
                if (!is_array($transaction)) {
                    throw new RuntimeException('Every transaction must be a JSON object.');
                }

                if (!array_key_exists('id', $transaction)) {
                    $transaction['id'] = $state['next_id'];
                    $state['next_id']++;
                } elseif (is_numeric($transaction['id'])) {
                    $state['next_id'] = max($state['next_id'], ((int) $transaction['id']) + 1);
                }

                if (!array_key_exists('upload_time', $transaction)) {
                    $transaction['upload_time'] = (new DateTimeImmutable('now', $this->timezone))
                        ->format('Y-m-d H:i:s');
                }

                $transaction['_sandbox_sequence'] = $state['next_sequence'];
                $state['next_sequence']++;
                $state['transactions'][] = $transaction;
                $created[] = $this->withoutInternalFields($transaction);
            }

            return $created;
        });
    }

    /**
     * @param array<string, string|int|null> $filters
     * @return array<int, array<string, mixed>>
     */
    public function listTransactions(array $filters = [], ?DateTimeImmutable $now = null): array
    {
        $state = $this->readState();
        $now = $now ?: new DateTimeImmutable('now', $this->timezone);

        $transactions = array_values(array_filter(
            $state['transactions'],
            function (array $transaction) use ($filters, $now): bool {
                if (isset($transaction['_sandbox_visible_at'])) {
                    try {
                        $visibleAt = new DateTimeImmutable(
                            (string) $transaction['_sandbox_visible_at'],
                            $this->timezone
                        );
                    } catch (Throwable $error) {
                        return false;
                    }

                    if ($visibleAt > $now) {
                        return false;
                    }
                }

                foreach (['emp_code', 'terminal_sn', 'punch_state'] as $field) {
                    if (isset($filters[$field]) && $filters[$field] !== ''
                        && (string) ($transaction[$field] ?? '') !== (string) $filters[$field]) {
                        return false;
                    }
                }

                $punchTimestamp = $this->parseTimestamp($transaction['punch_time'] ?? null);
                if (isset($filters['start_time']) && $filters['start_time'] !== '') {
                    $startTimestamp = $this->parseTimestamp($filters['start_time']);
                    if ($punchTimestamp === null || $startTimestamp === null || $punchTimestamp < $startTimestamp) {
                        return false;
                    }
                }

                if (isset($filters['end_time']) && $filters['end_time'] !== '') {
                    $endTimestamp = $this->parseTimestamp($filters['end_time']);
                    if ($punchTimestamp === null || $endTimestamp === null || $punchTimestamp > $endTimestamp) {
                        return false;
                    }
                }

                return true;
            }
        ));

        $ordering = isset($filters['ordering']) ? (string) $filters['ordering'] : '';
        if ($ordering !== '') {
            $descending = $ordering[0] === '-';
            $field = ltrim($ordering, '-');
            if (in_array($field, ['id', 'punch_time', 'upload_time'], true)) {
                usort($transactions, static function (array $left, array $right) use ($field, $descending): int {
                    $comparison = ($left[$field] ?? '') <=> ($right[$field] ?? '');
                    if ($comparison === 0) {
                        $comparison = ($left['_sandbox_sequence'] ?? 0) <=> ($right['_sandbox_sequence'] ?? 0);
                    }

                    return $descending ? -$comparison : $comparison;
                });
            }
        }

        return array_map([$this, 'withoutInternalFields'], $transactions);
    }

    public function scheduleFailure(int $status, int $count, string $message): void
    {
        if ($status < 400 || $status > 599) {
            throw new RuntimeException('Scheduled failure status must be between 400 and 599.');
        }
        if ($count < 1 || $count > 100) {
            throw new RuntimeException('Scheduled failure count must be between 1 and 100.');
        }

        $this->mutateState(static function (array &$state) use ($status, $count, $message): void {
            $state['failure'] = [
                'status' => $status,
                'remaining' => $count,
                'message' => $message,
            ];
        });
    }

    /** @return array<string, mixed>|null */
    public function consumeFailure(): ?array
    {
        return $this->mutateState(static function (array &$state): ?array {
            if (!isset($state['failure']) || !is_array($state['failure'])) {
                return null;
            }

            $failure = $state['failure'];
            $failure['remaining'] = (int) ($failure['remaining'] ?? 1) - 1;
            $state['failure'] = $failure['remaining'] > 0 ? $failure : null;

            return $failure;
        });
    }

    /** @return array<string, mixed> */
    public function getState(): array
    {
        $state = $this->readState();
        $state['transactions'] = array_map([$this, 'withoutInternalFields'], $state['transactions']);

        return $state;
    }

    /** @return array<string, mixed> */
    private function initialState(): array
    {
        return [
            'next_id' => 1,
            'next_sequence' => 1,
            'transactions' => [],
            'failure' => null,
        ];
    }

    /** @return array<string, mixed> */
    private function readState(): array
    {
        $handle = fopen($this->path, 'c+');
        if ($handle === false) {
            throw new RuntimeException('Unable to open sandbox state file: ' . $this->path);
        }

        try {
            if (!flock($handle, LOCK_SH)) {
                throw new RuntimeException('Unable to lock sandbox state file for reading.');
            }
            rewind($handle);
            $contents = stream_get_contents($handle);
            $state = $this->decodeState($contents === false ? '' : $contents);
            flock($handle, LOCK_UN);

            return $state;
        } finally {
            fclose($handle);
        }
    }

    /**
     * @template T
     * @param callable(array<string, mixed>&): T $callback
     * @return T
     */
    private function mutateState(callable $callback)
    {
        $handle = fopen($this->path, 'c+');
        if ($handle === false) {
            throw new RuntimeException('Unable to open sandbox state file: ' . $this->path);
        }

        try {
            if (!flock($handle, LOCK_EX)) {
                throw new RuntimeException('Unable to lock sandbox state file for writing.');
            }
            rewind($handle);
            $contents = stream_get_contents($handle);
            $state = $this->decodeState($contents === false ? '' : $contents);
            $result = call_user_func_array($callback, [&$state]);
            $this->writeToHandle($handle, $state);
            flock($handle, LOCK_UN);

            return $result;
        } finally {
            fclose($handle);
        }
    }

    /** @param array<string, mixed> $state */
    private function writeState(array $state): void
    {
        $handle = fopen($this->path, 'c+');
        if ($handle === false) {
            throw new RuntimeException('Unable to open sandbox state file: ' . $this->path);
        }

        try {
            if (!flock($handle, LOCK_EX)) {
                throw new RuntimeException('Unable to lock sandbox state file for writing.');
            }
            $this->writeToHandle($handle, $state);
            flock($handle, LOCK_UN);
        } finally {
            fclose($handle);
        }
    }

    /** @param resource $handle @param array<string, mixed> $state */
    private function writeToHandle($handle, array $state): void
    {
        $json = json_encode($state, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
        if ($json === false) {
            throw new RuntimeException('Unable to encode sandbox state.');
        }

        rewind($handle);
        if (!ftruncate($handle, 0) || fwrite($handle, $json . PHP_EOL) === false || !fflush($handle)) {
            throw new RuntimeException('Unable to persist sandbox state.');
        }
    }

    /** @return array<string, mixed> */
    private function decodeState(string $contents): array
    {
        if (trim($contents) === '') {
            return $this->initialState();
        }

        $state = json_decode($contents, true);
        if (!is_array($state) || !isset($state['transactions']) || !is_array($state['transactions'])) {
            throw new RuntimeException('Sandbox state is not valid JSON state. Reset or remove: ' . $this->path);
        }

        $state += $this->initialState();

        return $state;
    }

    /** @param mixed $value */
    private function parseTimestamp($value): ?int
    {
        if (!is_string($value) || trim($value) === '') {
            return null;
        }

        try {
            return (new DateTimeImmutable($value, $this->timezone))->getTimestamp();
        } catch (Throwable $error) {
            return null;
        }
    }

    /**
     * @param array<string, mixed> $transaction
     * @return array<string, mixed>
     */
    private function withoutInternalFields(array $transaction): array
    {
        unset($transaction['_sandbox_visible_at'], $transaction['_sandbox_sequence']);

        return $transaction;
    }
}
