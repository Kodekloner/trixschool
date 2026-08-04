<?php

declare(strict_types=1);

namespace SchoolLift\BiometricSandbox;

use RuntimeException;

final class HttpClient
{
    /**
     * @param array<string, mixed>|null $body
     * @param array<int, string> $headers
     * @return array{status: int, headers: array<int, string>, body: string, json: mixed}
     */
    public function request(string $method, string $url, ?array $body = null, array $headers = []): array
    {
        $method = strtoupper($method);
        $content = '';
        if ($body !== null) {
            $content = json_encode($body, JSON_UNESCAPED_SLASHES);
            if ($content === false) {
                throw new RuntimeException('Unable to encode HTTP request body.');
            }
            $headers[] = 'Content-Type: application/json';
        }
        $headers[] = 'Accept: application/json';

        $context = stream_context_create([
            'http' => [
                'method' => $method,
                'header' => implode("\r\n", $headers),
                'content' => $content,
                'ignore_errors' => true,
                'timeout' => 5,
            ],
        ]);

        $bodyText = @file_get_contents($url, false, $context);
        /** @var array<int, string> $responseHeaders */
        $responseHeaders = isset($http_response_header) ? $http_response_header : [];
        $status = 0;
        if (isset($responseHeaders[0]) && preg_match('/\s(\d{3})\s/', $responseHeaders[0], $matches)) {
            $status = (int) $matches[1];
        }

        if ($bodyText === false) {
            $error = error_get_last();
            $bodyText = '';
            if ($status === 0) {
                throw new RuntimeException(
                    'HTTP request failed: ' . ($error['message'] ?? 'unknown connection error')
                );
            }
        }

        $decoded = json_decode($bodyText, true);

        return [
            'status' => $status,
            'headers' => $responseHeaders,
            'body' => $bodyText,
            'json' => $decoded,
        ];
    }
}
