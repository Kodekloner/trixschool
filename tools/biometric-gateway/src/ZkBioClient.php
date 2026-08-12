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
            $url = is_string($next) && $next !== '' ? $this->safePaginationUrl($next) : null;
        }

        return $events;
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
        $token = $this->token($timeoutSeconds);
        $response = $this->http->request('GET', $url, [
            'Accept: application/json',
            'Authorization: ' . (string) $this->config['authorization_scheme'] . ' ' . $token,
        ], null, $timeoutSeconds);
        if ($response['status'] === 401) {
            $this->token = null;
            $token = $this->token($timeoutSeconds);
            $response = $this->http->request('GET', $url, [
                'Accept: application/json',
                'Authorization: ' . (string) $this->config['authorization_scheme'] . ' ' . $token,
            ], null, $timeoutSeconds);
        }
        return $response;
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
            $message = (string) ($response['json']['detail'] ?? $response['json']['message'] ?? '');
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
