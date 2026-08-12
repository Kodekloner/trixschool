<?php

declare(strict_types=1);

$statePath = (string) getenv('GATEWAY_TEST_RECEIVER_STATE');
$expectedToken = (string) getenv('GATEWAY_TEST_RECEIVER_TOKEN');
$method = strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? 'GET'));
$path = (string) parse_url((string) ($_SERVER['REQUEST_URI'] ?? '/'), PHP_URL_PATH);

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

if (!preg_match('/^Bearer\s+(.+)$/i', (string) ($_SERVER['HTTP_AUTHORIZATION'] ?? ''), $matches)
    || !hash_equals($expectedToken, trim($matches[1]))) {
    respond(401, ['detail' => 'Invalid gateway token.']);
}
if ($method === 'GET' && $path === '/api/biometric/v2/health') {
    respond(200, ['status' => 'ok', 'mode' => 'simulation']);
}

if ($method !== 'POST' || $path !== '/api/biometric/v2/events') {
    respond(404, ['detail' => 'Not found.']);
}

$body = file_get_contents('php://input');
$payload = json_decode($body === false ? '' : $body, true);
if (!is_array($payload) || !isset($payload['events']) || !is_array($payload['events'])) {
    respond(422, ['detail' => 'Invalid batch.']);
}

$result = mutateState($statePath, static function (array &$state) use ($payload): array {
    $failure = $state['failure'] ?? null;
    if (is_array($failure) && (int) ($failure['remaining'] ?? 0) > 0) {
        $failure['remaining'] = (int) $failure['remaining'] - 1;
        $state['failure'] = $failure;
        return [
            'failure' => true,
            'status' => (int) ($failure['status'] ?? 503),
            'retry_after' => (int) ($failure['retry_after'] ?? 1),
        ];
    }

    $known = isset($state['known']) && is_array($state['known']) ? $state['known'] : [];
    $results = [];
    foreach ($payload['events'] as $event) {
        $externalId = is_array($event) ? (string) ($event['external_event_id'] ?? '') : '';
        $status = isset($known[$externalId]) ? 'duplicate' : 'accepted';
        if ($externalId !== '') {
            $known[$externalId] = true;
        }
        $results[] = ['external_event_id' => $externalId, 'status' => $status];
    }
    $state['known'] = $known;
    $state['batches'][] = $payload;
    return ['failure' => false, 'results' => $results];
});

if ($result['failure']) {
    header('Retry-After: ' . $result['retry_after']);
    respond((int) $result['status'], ['detail' => 'Scheduled receiver failure.']);
}
respond(200, ['results' => $result['results']]);

/** @param array<string, mixed> $payload */
function respond(int $status, array $payload): void
{
    http_response_code($status);
    echo json_encode($payload, JSON_UNESCAPED_SLASHES) . PHP_EOL;
    exit;
}

/** @template T @param callable(array<string, mixed>&):T $callback @return T */
function mutateState(string $path, callable $callback)
{
    $handle = fopen($path, 'c+');
    if ($handle === false || !flock($handle, LOCK_EX)) {
        throw new RuntimeException('Unable to lock receiver test state.');
    }
    try {
        rewind($handle);
        $raw = stream_get_contents($handle);
        $state = json_decode($raw === false ? '' : $raw, true);
        if (!is_array($state)) {
            $state = ['known' => [], 'batches' => [], 'failure' => null];
        }
        $result = $callback($state);
        rewind($handle);
        ftruncate($handle, 0);
        fwrite($handle, (string) json_encode($state, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
        fflush($handle);
        flock($handle, LOCK_UN);
        return $result;
    } finally {
        fclose($handle);
    }
}
