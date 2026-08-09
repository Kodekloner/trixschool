<?php

declare(strict_types=1);

use SchoolLift\BiometricSandbox\JsonFileStore;

require_once dirname(__DIR__) . '/bootstrap.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
header('X-Biometric-Sandbox: true');

$statePath = getenv('BIOMETRIC_SANDBOX_STATE') ?: dirname(__DIR__) . '/var/state.json';
$username = getenv('BIOMETRIC_SANDBOX_USERNAME') ?: 'sandbox_admin';
$password = getenv('BIOMETRIC_SANDBOX_PASSWORD') ?: 'sandbox_password';
$token = getenv('BIOMETRIC_SANDBOX_TOKEN') ?: 'sandbox-token';
$controlKey = getenv('BIOMETRIC_SANDBOX_CONTROL_KEY') ?: 'sandbox-control-key';
$store = new JsonFileStore($statePath);

$method = strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');
$path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);
$path = is_string($path) ? (rtrim($path, '/') ?: '/') : '/';

try {
    $remoteAddress = (string) ($_SERVER['REMOTE_ADDR'] ?? '');
    if (getenv('BIOMETRIC_SANDBOX_ALLOW_REMOTE') !== '1' && !isLoopbackAddress($remoteAddress)) {
        respond(403, [
            'detail' => 'Remote sandbox access is disabled. Use the loopback interface.',
        ]);
    }

    if ($method === 'OPTIONS') {
        http_response_code(204);
        exit;
    }

    if ($method === 'GET' && $path === '/') {
        respond(200, [
            'name' => 'SchoolLift no-device biometric sandbox',
            'sandbox' => true,
            'warning' => 'Synthetic attendance data only. Bind this server to 127.0.0.1.',
            'endpoints' => [
                'health' => 'GET /health',
                'token' => 'POST /api-token-auth/ or /jwt-api-token-auth/',
                'transactions' => 'GET /iclock/api/transactions/',
                'inject' => 'POST /sandbox/events (X-Sandbox-Key required)',
                'fail_next' => 'POST /sandbox/fail-next (X-Sandbox-Key required)',
                'state' => 'GET /sandbox/state (X-Sandbox-Key required)',
                'reset' => 'POST /sandbox/reset (X-Sandbox-Key required)',
            ],
        ]);
    }

    if ($method === 'GET' && $path === '/health') {
        respond(200, [
            'status' => 'ok',
            'sandbox' => true,
            'time' => gmdate(DATE_ATOM),
        ]);
    }

    if ($method === 'POST' && in_array($path, ['/api-token-auth', '/jwt-api-token-auth'], true)) {
        $input = requestBody();
        if (!hash_equals($username, (string) ($input['username'] ?? ''))
            || !hash_equals($password, (string) ($input['password'] ?? ''))) {
            respond(401, ['detail' => 'Unable to log in with provided credentials.']);
        }

        respond(200, [
            'token' => $token,
            'access' => $token,
            'token_type' => 'sandbox',
        ]);
    }

    if ($method === 'GET' && $path === '/iclock/api/transactions') {
        requireApiToken($token);
        $failure = $store->consumeFailure();
        if ($failure !== null) {
            respond((int) $failure['status'], [
                'detail' => (string) $failure['message'],
                'sandbox_failure' => true,
                'remaining_after_this_response' => max(0, (int) $failure['remaining']),
            ]);
        }

        $filters = [];
        foreach (['emp_code', 'terminal_sn', 'punch_state', 'start_time', 'end_time', 'ordering'] as $name) {
            if (isset($_GET[$name]) && is_scalar($_GET[$name])) {
                $filters[$name] = (string) $_GET[$name];
            }
        }

        $transactions = $store->listTransactions($filters);
        $count = count($transactions);
        $page = max(1, (int) ($_GET['page'] ?? 1));
        $pageSize = max(1, min(1000, (int) ($_GET['page_size'] ?? 100)));
        $offset = ($page - 1) * $pageSize;
        $data = array_slice($transactions, $offset, $pageSize);

        respond(200, [
            'count' => $count,
            'next' => $offset + $pageSize < $count ? paginationLink($page + 1, $pageSize) : null,
            'previous' => $page > 1 && $count > 0 ? paginationLink($page - 1, $pageSize) : null,
            'data' => $data,
        ]);
    }

    if ($path === '/sandbox/events' && $method === 'POST') {
        requireControlKey($controlKey);
        $input = requestBody();
        if (!isset($input['events']) || !is_array($input['events']) || !isList($input['events'])) {
            respond(422, ['detail' => 'Body must contain an events JSON array.']);
        }

        $created = $store->appendTransactions($input['events']);
        respond(201, [
            'created' => count($created),
            'events' => $created,
        ]);
    }

    if ($path === '/sandbox/fail-next' && $method === 'POST') {
        requireControlKey($controlKey);
        $input = requestBody();
        $status = (int) ($input['status'] ?? 503);
        $count = (int) ($input['count'] ?? 1);
        $message = (string) ($input['message'] ?? 'Simulated upstream failure.');
        $store->scheduleFailure($status, $count, $message);
        respond(202, [
            'scheduled' => true,
            'status' => $status,
            'count' => $count,
            'message' => $message,
        ]);
    }

    if ($path === '/sandbox/reset' && $method === 'POST') {
        requireControlKey($controlKey);
        $store->reset();
        respond(200, ['reset' => true]);
    }

    if ($path === '/sandbox/state' && $method === 'GET') {
        requireControlKey($controlKey);
        respond(200, $store->getState());
    }

    respond(404, ['detail' => 'Sandbox endpoint not found.']);
} catch (Throwable $error) {
    respond(500, [
        'detail' => 'Sandbox request failed.',
        'error' => $error->getMessage(),
    ]);
}

/** @param array<string, mixed> $payload */
function respond(int $status, array $payload): void
{
    http_response_code($status);
    $json = json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    echo ($json === false ? '{"detail":"JSON encoding failed."}' : $json) . PHP_EOL;
    exit;
}

/** @return array<string, mixed> */
function requestBody(): array
{
    $raw = file_get_contents('php://input');
    $contentType = strtolower((string) ($_SERVER['CONTENT_TYPE'] ?? ''));

    if (strpos($contentType, 'application/x-www-form-urlencoded') !== false) {
        parse_str($raw === false ? '' : $raw, $form);

        return is_array($form) ? $form : [];
    }

    $body = json_decode($raw === false ? '' : $raw, true);
    if (!is_array($body)) {
        respond(400, ['detail' => 'Request body must be a valid JSON object.']);
    }

    return $body;
}

function requireApiToken(string $expected): void
{
    $authorization = (string) (
        $_SERVER['HTTP_AUTHORIZATION']
        ?? $_SERVER['REDIRECT_HTTP_AUTHORIZATION']
        ?? ''
    );

    if (!preg_match('/^(?:Token|JWT|Bearer)\s+(.+)$/i', trim($authorization), $matches)
        || !hash_equals($expected, trim($matches[1]))) {
        respond(401, ['detail' => 'Authentication credentials were not provided or are invalid.']);
    }
}

function requireControlKey(string $expected): void
{
    $provided = (string) ($_SERVER['HTTP_X_SANDBOX_KEY'] ?? '');
    if (!hash_equals($expected, $provided)) {
        respond(403, ['detail' => 'A valid X-Sandbox-Key is required.']);
    }
}

function paginationLink(int $page, int $pageSize): string
{
    $query = $_GET;
    $query['page'] = $page;
    $query['page_size'] = $pageSize;

    return '/iclock/api/transactions/?' . http_build_query($query);
}

/** @param array<mixed> $values */
function isList(array $values): bool
{
    $expectedKey = 0;
    foreach ($values as $key => $value) {
        if ($key !== $expectedKey) {
            return false;
        }
        $expectedKey++;
    }

    return true;
}

function isLoopbackAddress(string $address): bool
{
    return $address === '::1'
        || $address === 'localhost'
        || preg_match('/^127(?:\.\d{1,3}){3}$/', $address) === 1;
}
