<?php

declare(strict_types=1);

namespace SchoolLift\BiometricGateway;

use RuntimeException;

final class ZkBioClient
{
    private HttpTransport $http;
    /** @var array<string, mixed> */
    private array $config;
    private ?string $token = null;

    /** @param array<string, mixed> $config */
    public function __construct(HttpTransport $http, array $config)
    {
        $this->http = $http;
        $this->config = $config;
    }

    /** @return array<int, array<string, mixed>> */
    public function fetchTransactions(string $startTime, int $timeoutSeconds): array
    {
        $query = [
            'start_time' => $startTime,
            'terminal_sn' => (string) $this->config['terminal_serial'],
            'ordering' => 'id',
            'page_size' => (int) $this->config['page_size'],
            'page' => 1,
        ];
        $url = $this->url((string) $this->config['transactions_path']) . '?' . http_build_query($query);
        $events = [];
        $pages = 0;

        while ($url !== null) {
            $pages++;
            if ($pages > (int) $this->config['max_pages']) {
                throw new UpstreamException('ZKBio pagination exceeded configured max_pages; cursor was not advanced.');
            }
            $response = $this->authorizedGet($url, $timeoutSeconds);
            if ($response['status'] < 200 || $response['status'] > 299 || !is_array($response['json'])) {
                throw $this->failure('ZKBio transactions request failed', $response);
            }
            $field = (string) $this->config['data_field'];
            $data = $response['json'][$field] ?? $response['json']['results'] ?? null;
            if (!is_array($data)) {
                throw new UpstreamException('ZKBio response does not contain a transaction array.');
            }
            foreach ($data as $transaction) {
                if (is_array($transaction)) {
                    $events[] = $transaction;
                }
            }
            $next = $response['json']['next'] ?? null;
            $url = is_string($next) && $next !== '' && strtolower($next) !== 'null'
                ? $this->safePaginationUrl($next) : null;
        }

        return $events;
    }

    /** @return array<int, array<string, mixed>> */
    public function listEmployees(int $timeoutSeconds): array
    {
        return $this->fetchCollection((string) $this->config['employees_path'], [], $timeoutSeconds);
    }

    /** @return array<int, array<string, mixed>> */
    public function listDepartments(int $timeoutSeconds): array
    {
        return $this->fetchCollection((string) $this->config['departments_path'], [], $timeoutSeconds);
    }

    /** @return array<int, array<string, mixed>> */
    public function listPositions(int $timeoutSeconds): array
    {
        return $this->fetchCollection((string) $this->config['positions_path'], [], $timeoutSeconds);
    }

    /** @return array<int, array<string, mixed>> */
    public function listAreas(int $timeoutSeconds): array
    {
        return $this->fetchCollection((string) $this->config['areas_path'], [], $timeoutSeconds);
    }

    /** @param array<string, mixed> $payload @return array<string, mixed> */
    public function createDepartment(array $payload, int $timeoutSeconds): array
    {
        $path = (string) $this->config['departments_path'];
        $response = $this->writeJson('POST', $path, $payload, $timeoutSeconds);
        return $this->resolveCreatedResource(
            $response,
            $path,
            'dept_code',
            (string) ($payload['dept_code'] ?? ''),
            $timeoutSeconds
        );
    }

    /** @param array<string, mixed> $payload @return array<string, mixed> */
    public function updateDepartment(string $id, array $payload, int $timeoutSeconds): array
    {
        return $this->withResourceId(
            $this->writeJson('PUT', $this->itemPath((string) $this->config['departments_path'], $id), $payload, $timeoutSeconds),
            $id
        );
    }

    /** @param array<string, mixed> $payload @return array<string, mixed> */
    public function createPosition(array $payload, int $timeoutSeconds): array
    {
        $path = (string) $this->config['positions_path'];
        $response = $this->writeJson('POST', $path, $payload, $timeoutSeconds);
        return $this->resolveCreatedResource(
            $response,
            $path,
            'position_code',
            (string) ($payload['position_code'] ?? ''),
            $timeoutSeconds
        );
    }

    /** @param array<string, mixed> $payload @return array<string, mixed> */
    public function updatePosition(string $id, array $payload, int $timeoutSeconds): array
    {
        return $this->withResourceId(
            $this->writeJson('PUT', $this->itemPath((string) $this->config['positions_path'], $id), $payload, $timeoutSeconds),
            $id
        );
    }

    /** @param array<string, mixed> $payload @return array<string, mixed> */
    public function createEmployee(array $payload, int $timeoutSeconds): array
    {
        $path = (string) $this->config['employees_path'];
        $response = $this->writeJson('POST', $path, $payload, $timeoutSeconds);
        return $this->resolveCreatedResource(
            $response,
            $path,
            'emp_code',
            (string) ($payload['emp_code'] ?? ''),
            $timeoutSeconds
        );
    }

    /** @param array<string, mixed> $payload @return array<string, mixed> */
    public function updateEmployee(string $id, array $payload, int $timeoutSeconds): array
    {
        return $this->withResourceId(
            $this->writeJson('PUT', $this->itemPath((string) $this->config['employees_path'], $id), $payload, $timeoutSeconds),
            $id
        );
    }

    public function deleteEmployee(string $id, int $timeoutSeconds): void
    {
        $response = $this->authorizedRequest('DELETE', $this->url($this->itemPath((string) $this->config['employees_path'], $id)), null, $timeoutSeconds);
        if ($response['status'] < 200 || $response['status'] > 299) {
            throw $this->failure('ZKBio employee delete failed', $response);
        }
    }

    /** @param array<int, string|int> $employeeIds */
    public function resyncEmployees(array $employeeIds, int $timeoutSeconds): void
    {
        if ($employeeIds === []) {
            return;
        }
        $this->writeJson('POST', (string) $this->config['resync_path'], [
            'employees' => array_values(array_map('intval', $employeeIds)),
        ], $timeoutSeconds);
    }

    /** @return array<string, int> */
    public function directoryCapabilities(int $timeoutSeconds): array
    {
        return [
            'employees' => count($this->fetchCollection((string) $this->config['employees_path'], ['page_size' => 1], $timeoutSeconds, 1)),
            'departments' => count($this->fetchCollection((string) $this->config['departments_path'], ['page_size' => 1], $timeoutSeconds, 1)),
            'positions' => count($this->fetchCollection((string) $this->config['positions_path'], ['page_size' => 1], $timeoutSeconds, 1)),
            'areas' => count($this->fetchCollection((string) $this->config['areas_path'], ['page_size' => 1], $timeoutSeconds, 1)),
        ];
    }

    /** @return array{status:int,headers:array<string,string>,body:string,json:mixed} */
    public function health(int $timeoutSeconds): array
    {
        $url = $this->url('/health');
        return $this->http->request('GET', $url, ['Accept: application/json'], null, $timeoutSeconds);
    }

    /** @return array{status:int,headers:array<string,string>,body:string,json:mixed} */
    private function authorizedGet(string $url, int $timeoutSeconds): array
    {
        return $this->authorizedRequest('GET', $url, null, $timeoutSeconds);
    }

    /** @return array{status:int,headers:array<string,string>,body:string,json:mixed} */
    private function authorizedRequest(string $method, string $url, ?string $body, int $timeoutSeconds): array
    {
        $token = $this->token($timeoutSeconds);
        $headers = [
            'Accept: application/json',
            'Authorization: ' . (string) $this->config['authorization_scheme'] . ' ' . $token,
        ];
        if ($body !== null) {
            $headers[] = 'Content-Type: application/json';
        }
        $response = $this->http->request($method, $url, $headers, $body, $timeoutSeconds);
        if ($response['status'] === 401) {
            $this->token = null;
            $token = $this->token($timeoutSeconds);
            $headers = [
                'Accept: application/json',
                'Authorization: ' . (string) $this->config['authorization_scheme'] . ' ' . $token,
            ];
            if ($body !== null) {
                $headers[] = 'Content-Type: application/json';
            }
            $response = $this->http->request($method, $url, $headers, $body, $timeoutSeconds);
        }
        return $response;
    }

    /**
     * @param array<string, scalar> $query
     * @return array<int, array<string, mixed>>
     */
    private function fetchCollection(string $path, array $query, int $timeoutSeconds, ?int $maximumPages = null): array
    {
        $query = array_merge(['page_size' => (int) $this->config['page_size'], 'page' => 1], $query);
        $url = $this->url($path) . '?' . http_build_query($query);
        $items = [];
        $pages = 0;
        $maximumPages = $maximumPages ?? (int) $this->config['max_pages'];
        while ($url !== null) {
            $pages++;
            if ($pages > $maximumPages) {
                throw new UpstreamException('ZKBio directory pagination exceeded configured max_pages.');
            }
            $response = $this->authorizedGet($url, $timeoutSeconds);
            if ($response['status'] < 200 || $response['status'] > 299 || !is_array($response['json'])) {
                throw $this->failure('ZKBio directory request failed', $response);
            }
            $data = $response['json'][(string) $this->config['data_field']] ?? $response['json']['results'] ?? null;
            if (!is_array($data)) {
                throw new UpstreamException('ZKBio directory response does not contain a data array.');
            }
            foreach ($data as $item) {
                if (is_array($item)) {
                    $items[] = $item;
                }
            }
            $next = $response['json']['next'] ?? null;
            $url = is_string($next) && $next !== '' && strtolower($next) !== 'null' && $pages < $maximumPages
                ? $this->safePaginationUrl($next) : null;
        }
        return $items;
    }

    /** @param array<string, mixed> $payload @return array<string, mixed> */
    private function writeJson(string $method, string $path, array $payload, int $timeoutSeconds): array
    {
        $body = json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE);
        if ($body === false) {
            throw new RuntimeException('Unable to encode a ZKBio directory request.');
        }
        $response = $this->authorizedRequest($method, $this->url($path), $body, $timeoutSeconds);
        if ($response['status'] < 200 || $response['status'] > 299 || !is_array($response['json'])) {
            throw $this->failure('ZKBio directory write failed', $response);
        }
        return $response['json'];
    }

    /**
     * ZKBio Time 9.0.6 can accept a create request while echoing only the
     * submitted fields, without the generated primary key. Resolve that key
     * through the resource's unique SchoolLift-owned code before continuing.
     *
     * @param array<string, mixed> $response
     * @return array<string, mixed>
     */
    private function resolveCreatedResource(
        array $response,
        string $collectionPath,
        string $codeField,
        string $code,
        int $timeoutSeconds
    ): array {
        if ($this->resourceId($response) !== null) {
            return $response;
        }
        if ($code === '') {
            throw new UpstreamException('ZKBio create response omitted its resource ID and unique code.');
        }
        $matches = [];
        foreach ($this->fetchCollection($collectionPath, [$codeField => $code], $timeoutSeconds) as $resource) {
            if (trim((string) ($resource[$codeField] ?? '')) === $code) {
                $matches[] = $resource;
            }
        }
        if (count($matches) !== 1 || $this->resourceId($matches[0]) === null) {
            throw new UpstreamException('ZKBio accepted a create request but its generated resource ID could not be resolved uniquely.');
        }
        return $matches[0];
    }

    /** @param array<string, mixed> $response @return array<string, mixed> */
    private function withResourceId(array $response, string $knownId): array
    {
        if ($this->resourceId($response) === null) {
            $response['id'] = $knownId;
        }
        return $response;
    }

    /** @param array<string, mixed> $resource */
    private function resourceId(array $resource): ?string
    {
        $id = $resource['id'] ?? ($resource['data']['id'] ?? null);
        return $id !== null && preg_match('/^[A-Za-z0-9._-]{1,64}$/', (string) $id)
            ? (string) $id
            : null;
    }

    private function itemPath(string $collectionPath, string $id): string
    {
        if (!preg_match('/^[A-Za-z0-9._-]{1,64}$/', $id)) {
            throw new RuntimeException('ZKBio resource ID is invalid.');
        }
        return rtrim($collectionPath, '/') . '/' . rawurlencode($id) . '/';
    }

    private function token(int $timeoutSeconds): string
    {
        if ($this->token !== null) {
            return $this->token;
        }
        $credentials = [
            'username' => (string) $this->config['username'],
            'password' => (string) $this->config['password'],
        ];
        if ($this->config['auth_body_format'] === 'form') {
            $body = http_build_query($credentials);
            $contentType = 'Content-Type: application/x-www-form-urlencoded';
        } else {
            $body = json_encode($credentials, JSON_UNESCAPED_SLASHES);
            if ($body === false) {
                throw new RuntimeException('Unable to encode ZKBio credentials.');
            }
            $contentType = 'Content-Type: application/json';
        }
        $response = $this->http->request('POST', $this->url((string) $this->config['auth_path']), [
            'Accept: application/json',
            $contentType,
        ], $body, $timeoutSeconds);
        if ($response['status'] < 200 || $response['status'] > 299 || !is_array($response['json'])) {
            throw $this->failure('ZKBio authentication failed', $response);
        }
        $field = (string) $this->config['token_field'];
        $token = $response['json'][$field] ?? $response['json']['access'] ?? null;
        if (!is_string($token) || trim($token) === '') {
            throw new UpstreamException('ZKBio authentication response did not contain a token.');
        }
        $this->token = trim($token);
        return $this->token;
    }

    private function url(string $path): string
    {
        return rtrim((string) $this->config['base_url'], '/') . '/' . ltrim($path, '/');
    }

    private function safePaginationUrl(string $next): string
    {
        if (str_starts_with($next, '/')) {
            return $this->url($next);
        }
        $base = parse_url((string) $this->config['base_url']);
        $candidate = parse_url($next);
        if (!is_array($candidate) || !isset($candidate['scheme'], $candidate['host'])) {
            throw new UpstreamException('ZKBio supplied an invalid pagination URL.');
        }
        $basePort = (int) ($base['port'] ?? ($base['scheme'] === 'https' ? 443 : 80));
        $nextPort = (int) ($candidate['port'] ?? ($candidate['scheme'] === 'https' ? 443 : 80));
        if (strtolower((string) $candidate['scheme']) !== strtolower((string) $base['scheme'])
            || strtolower((string) $candidate['host']) !== strtolower((string) $base['host'])
            || $nextPort !== $basePort) {
            throw new UpstreamException('Refusing cross-origin ZKBio pagination URL.');
        }
        return $next;
    }

    /** @param array{status:int,headers:array<string,string>,body:string,json:mixed} $response */
    private function failure(string $prefix, array $response): UpstreamException
    {
        $message = '';
        if (is_array($response['json'])) {
            $message = (string) ($response['json']['detail'] ?? $response['json']['message'] ?? $response['json']['msg'] ?? '');
        }
        $retry = isset($response['headers']['retry-after']) && ctype_digit($response['headers']['retry-after'])
            ? (int) $response['headers']['retry-after']
            : null;
        return new UpstreamException(
            $prefix . ' (HTTP ' . $response['status'] . ')' . ($message !== '' ? ': ' . $message : ''),
            $response['status'],
            $retry
        );
    }
}
