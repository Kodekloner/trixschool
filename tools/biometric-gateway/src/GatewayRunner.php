<?php

declare(strict_types=1);

namespace SchoolLift\BiometricGateway;

use DateTimeImmutable;
use DateTimeZone;
use Throwable;

final class GatewayRunner
{
    private GatewayStore $store;
    private ZkBioClient $provider;
    private SchoolLiftClient $schoolLift;
    private EventNormalizer $normalizer;
    private Clock $clock;
    private JsonLogger $logger;
    /** @var array<string, mixed> */
    private array $config;

    /** @param array<string, mixed> $config */
    public function __construct(
        GatewayStore $store,
        ZkBioClient $provider,
        SchoolLiftClient $schoolLift,
        EventNormalizer $normalizer,
        Clock $clock,
        JsonLogger $logger,
        array $config
    ) {
        $this->store = $store;
        $this->provider = $provider;
        $this->schoolLift = $schoolLift;
        $this->normalizer = $normalizer;
        $this->clock = $clock;
        $this->logger = $logger;
        $this->config = $config;
    }

    /** @return array<string, mixed> */
    public function runOnce(): array
    {
        $started = $this->clock->now();
        $summary = [
            'ok' => true,
            'started_at' => $started->format(DATE_ATOM),
            'provider' => ['polled' => false, 'received' => 0, 'inserted' => 0, 'duplicates' => 0],
            'delivery' => ['delivered' => 0, 'deferred' => 0, 'dead' => 0],
        ];

        $firstDelivery = $this->flushQueue();
        $summary['delivery'] = $this->mergeCounts($summary['delivery'], $firstDelivery);
        if ($firstDelivery['error'] !== null) {
            $summary['ok'] = false;
        }

        $now = $this->clock->now();
        if ($this->store->providerCanPoll($now)) {
            try {
                $cursor = $this->store->providerCursor();
                $cursorTime = $cursor !== null
                    ? new DateTimeImmutable($cursor)
                    : $now->modify('-' . (int) $this->config['provider']['initial_lookback_seconds'] . ' seconds');
                $queryStart = $cursorTime
                    ->modify('-' . (int) $this->config['provider']['overlap_seconds'] . ' seconds')
                    ->setTimezone(new DateTimeZone((string) $this->config['timezone']))
                    ->format('Y-m-d H:i:s');
                $transactions = $this->provider->fetchTransactions(
                    $queryStart,
                    (int) $this->config['request_timeout_seconds']
                );
                $events = [];
                foreach ($transactions as $transaction) {
                    $events[] = $this->normalizer->normalize($transaction);
                }
                $pollCursor = $now->format(DATE_ATOM);
                $stored = $this->store->enqueueAndAdvance($events, $pollCursor, $now);
                $this->store->clearProviderFailure($now);
                $summary['provider'] = [
                    'polled' => true,
                    'query_start' => $queryStart,
                    'cursor' => $pollCursor,
                    'received' => count($transactions),
                    'inserted' => $stored['inserted'],
                    'duplicates' => $stored['duplicates'],
                ];
                $this->logger->log('info', 'ZKBio polling completed.', $summary['provider']);
            } catch (Throwable $error) {
                $summary['ok'] = false;
                $summary['provider']['error'] = $this->bounded($error->getMessage());
                $status = $error instanceof UpstreamException ? $error->statusCode() : 0;
                $retryAfter = $error instanceof UpstreamException ? $error->retryAfterSeconds() : null;
                $next = $now->modify('+' . $this->providerBackoff($retryAfter) . ' seconds');
                $failureCount = $this->store->recordProviderFailure($next, $now);
                $this->logger->log('error', 'ZKBio polling failed; cursor was not advanced.', [
                    'http_status' => $status,
                    'failure_count' => $failureCount,
                    'next_poll_at' => $next->format(DATE_ATOM),
                    'error' => $this->bounded($error->getMessage()),
                ]);
            }
        } else {
            $summary['provider']['backoff'] = true;
            $summary['provider']['next_poll_at'] = $this->store->status()['provider_next_poll_at'];
        }

        $secondDelivery = $this->flushQueue();
        $summary['delivery'] = $this->mergeCounts($summary['delivery'], $secondDelivery);
        if ($secondDelivery['error'] !== null) {
            $summary['ok'] = false;
        }

        $retentionDays = (int) $this->config['delivered_retention_days'];
        if ($retentionDays > 0) {
            $summary['purged'] = $this->store->purgeDeliveredBefore(
                $this->clock->now()->modify('-' . $retentionDays . ' days')
            );
        }
        $summary['finished_at'] = $this->clock->now()->format(DATE_ATOM);
        $summary['queue'] = $this->store->status();
        return $summary;
    }

    /** @return array{delivered:int,deferred:int,dead:int,error:?string} */
    private function flushQueue(): array
    {
        $counts = ['delivered' => 0, 'deferred' => 0, 'dead' => 0, 'error' => null];
        $batchSize = (int) $this->config['schoollift']['batch_size'];
        $maximumBatches = (int) $this->config['schoollift']['max_batches_per_run'];
        for ($batchNumber = 0; $batchNumber < $maximumBatches; $batchNumber++) {
            $now = $this->clock->now();
            $rows = $this->store->pending($batchSize, $now);
            if ($rows === []) {
                break;
            }
            $events = array_map(static fn (array $row): array => $row['payload'], $rows);
            $ids = array_map(static fn (array $row): string => $row['external_event_id'], $rows);
            $providerCursor = (string) ($rows[0]['provider_cursor'] ?? '');
            if ($providerCursor === '') {
                $providerCursor = $this->store->providerCursor() ?? $now->format(DATE_ATOM);
            }
            try {
                $response = $this->schoolLift->send(
                    $events,
                    $providerCursor,
                    (int) $this->config['request_timeout_seconds']
                );
            } catch (Throwable $error) {
                $delay = $this->deliveryBackoff($rows, null);
                $this->store->defer(
                    $ids,
                    $error->getMessage(),
                    null,
                    $now->modify('+' . $delay . ' seconds'),
                    (int) $this->config['retry']['maximum_attempts'],
                    $now
                );
                $counts['deferred'] += count($ids);
                $counts['error'] = $this->bounded($error->getMessage());
                $this->logger->log('error', 'SchoolLift delivery connection failed; batch deferred.', [
                    'event_count' => count($ids),
                    'retry_seconds' => $delay,
                    'error' => $this->bounded($error->getMessage()),
                ]);
                break;
            }

            if ($response['status'] >= 200 && $response['status'] <= 299) {
                $results = $this->resultMap($response['json']);
                $missing = [];
                foreach ($ids as $externalId) {
                    $result = $results[$externalId] ?? null;
                    if ($result === null) {
                        $missing[] = $externalId;
                        continue;
                    }
                    $status = strtolower((string) ($result['status'] ?? ''));
                    if (!in_array($status, ['accepted', 'duplicate', 'quarantined', 'rejected'], true)) {
                        $missing[] = $externalId;
                        continue;
                    }
                    $this->store->markDelivered(
                        $externalId,
                        $status,
                        isset($result['message']) ? (string) $result['message'] : null,
                        $response['status'],
                        $now
                    );
                    $counts['delivered']++;
                }
                if ($missing !== []) {
                    $delay = $this->deliveryBackoff($rows, null);
                    $this->store->defer(
                        $missing,
                        'Successful response omitted a recognized outcome for this event.',
                        $response['status'],
                        $now->modify('+' . $delay . ' seconds'),
                        (int) $this->config['retry']['maximum_attempts'],
                        $now
                    );
                    $counts['deferred'] += count($missing);
                    $counts['error'] = 'SchoolLift response omitted one or more event outcomes.';
                    break;
                }
                $this->logger->log('info', 'SchoolLift event batch delivered.', [
                    'http_status' => $response['status'],
                    'event_count' => count($ids),
                ]);
                continue;
            }

            $message = $this->responseMessage($response);
            if ($response['status'] === 429 || $response['status'] >= 500 || $response['status'] === 0
                || in_array($response['status'], [401, 403, 408], true)) {
                $retryAfter = $this->retryAfter($response['headers']);
                $delay = $this->deliveryBackoff($rows, $retryAfter);
                $this->store->defer(
                    $ids,
                    $message,
                    $response['status'],
                    $now->modify('+' . $delay . ' seconds'),
                    (int) $this->config['retry']['maximum_attempts'],
                    $now
                );
                $counts['deferred'] += count($ids);
                $counts['error'] = $message;
                $this->logger->log('error', 'SchoolLift event batch deferred.', [
                    'http_status' => $response['status'],
                    'event_count' => count($ids),
                    'retry_seconds' => $delay,
                ]);
            } else {
                $this->store->markDead($ids, $message, $response['status'], $now);
                $counts['dead'] += count($ids);
                $counts['error'] = $message;
                $this->logger->log('error', 'SchoolLift permanently rejected a whole batch.', [
                    'http_status' => $response['status'],
                    'event_count' => count($ids),
                ]);
            }
            break;
        }
        return $counts;
    }

    /** @param mixed $json @return array<string, array<string, mixed>> */
    private function resultMap($json): array
    {
        if (!is_array($json)) {
            return [];
        }
        $rows = $json['results'] ?? $json['events'] ?? null;
        if (!is_array($rows) && isset($json['data']) && is_array($json['data'])) {
            $rows = $json['data']['results'] ?? null;
        }
        if (!is_array($rows)) {
            return [];
        }
        $result = [];
        foreach ($rows as $row) {
            if (!is_array($row)) {
                continue;
            }
            $externalId = (string) ($row['external_event_id'] ?? '');
            if ($externalId !== '') {
                $result[$externalId] = $row;
            }
        }
        return $result;
    }

    /** @param array<int, array{attempts:int}> $rows */
    private function deliveryBackoff(array $rows, ?int $retryAfter): int
    {
        $attempts = 0;
        foreach ($rows as $row) {
            $attempts = max($attempts, (int) $row['attempts']);
        }
        $base = (int) $this->config['retry']['base_seconds'];
        $maximum = (int) $this->config['retry']['maximum_seconds'];
        $calculated = min($maximum, $base * (2 ** min(10, $attempts)));
        return max(1, min($maximum, max($calculated, (int) ($retryAfter ?? 0))));
    }

    private function providerBackoff(?int $retryAfter): int
    {
        $attempts = $this->store->providerFailureCount();
        $base = (int) $this->config['retry']['provider_base_seconds'];
        $maximum = (int) $this->config['retry']['provider_maximum_seconds'];
        $calculated = min($maximum, $base * (2 ** min(10, $attempts)));
        return max(1, min($maximum, max($calculated, (int) ($retryAfter ?? 0))));
    }

    /** @param array<string, string> $headers */
    private function retryAfter(array $headers): ?int
    {
        $value = $headers['retry-after'] ?? null;
        if ($value === null) {
            return null;
        }
        if (ctype_digit($value)) {
            return (int) $value;
        }
        $timestamp = strtotime($value);
        return $timestamp === false ? null : max(0, $timestamp - $this->clock->now()->getTimestamp());
    }

    /** @param array{status:int,headers:array<string,string>,body:string,json:mixed} $response */
    private function responseMessage(array $response): string
    {
        $detail = '';
        if (is_array($response['json'])) {
            $detail = (string) ($response['json']['detail'] ?? $response['json']['message'] ?? '');
        }
        return 'SchoolLift delivery failed with HTTP ' . $response['status']
            . ($detail !== '' ? ': ' . $this->bounded($detail) : '.');
    }

    /** @param array<string, mixed> $left @param array<string, mixed> $right @return array<string, mixed> */
    private function mergeCounts(array $left, array $right): array
    {
        return [
            'delivered' => (int) $left['delivered'] + (int) $right['delivered'],
            'deferred' => (int) $left['deferred'] + (int) $right['deferred'],
            'dead' => (int) $left['dead'] + (int) $right['dead'],
            'error' => $right['error'] ?? $left['error'] ?? null,
        ];
    }

    private function bounded(string $message): string
    {
        return function_exists('mb_substr') ? mb_substr($message, 0, 1000) : substr($message, 0, 1000);
    }
}
