<?php

declare(strict_types=1);

namespace SchoolLift\BiometricGateway;

use RuntimeException;

final class SchoolLiftClient
{
    public const GATEWAY_VERSION = '1.1.0';

    private HttpTransport $http;
    /** @var array<string, mixed> */
    private array $config;
    private string $gatewayId;

    /** @param array<string, mixed> $config */
    public function __construct(HttpTransport $http, array $config, string $gatewayId)
    {
        $this->http = $http;
        $this->config = $config;
        $this->gatewayId = $gatewayId;
    }

    /**
     * @param array<int, array<string, mixed>> $events
     * @return array{status:int,headers:array<string,string>,body:string,json:mixed}
     */
    public function send(array $events, string $cursor, int $timeoutSeconds): array
    {
        $ids = array_map(static fn (array $event): string => (string) $event['external_event_id'], $events);
        sort($ids, SORT_STRING);
        $payload = [
            'batch_id' => $this->batchId($ids, $cursor),
            'gateway_version' => self::GATEWAY_VERSION,
            'cursor' => $cursor,
            'events' => $events,
        ];
        $body = json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE);
        if ($body === false) {
            throw new RuntimeException('Unable to encode SchoolLift event batch.');
        }
        return $this->http->request('POST', $this->url((string) $this->config['events_path']), [
            'Accept: application/json',
            'Content-Type: application/json',
            'Authorization: Bearer ' . (string) $this->config['bearer_token'],
            'X-SchoolLift-Gateway: ' . $this->gatewayId,
        ], $body, $timeoutSeconds);
    }

    /** @return array{status:int,headers:array<string,string>,body:string,json:mixed} */
    public function health(int $timeoutSeconds): array
    {
        return $this->http->request('GET', $this->url((string) $this->config['health_path']), [
            'Accept: application/json',
            'Authorization: Bearer ' . (string) $this->config['bearer_token'],
            'X-SchoolLift-Gateway: ' . $this->gatewayId,
        ], null, $timeoutSeconds);
    }

    /** @param array<string, mixed> $status
     *  @return array{status:int,headers:array<string,string>,body:string,json:mixed}
     */
    public function pollGatewayControl(array $status, int $timeoutSeconds): array
    {
        return $this->postJson(
            (string) ($this->config['control_poll_path'] ?? '/api/biometric/v2/gateway/poll'),
            [
                'gateway_id' => $this->gatewayId,
                'gateway_version' => self::GATEWAY_VERSION,
                'status' => $status,
            ],
            $timeoutSeconds
        );
    }

    /** @param array<string, mixed> $result
     *  @return array{status:int,headers:array<string,string>,body:string,json:mixed}
     */
    public function reportGatewayCommand(
        string $commandUuid,
        string $status,
        array $result,
        int $timeoutSeconds
    ): array {
        return $this->postJson(
            (string) ($this->config['control_result_path'] ?? '/api/biometric/v2/gateway/result'),
            [
                'gateway_id' => $this->gatewayId,
                'command_uuid' => $commandUuid,
                'status' => $status,
                'result' => $result,
            ],
            $timeoutSeconds
        );
    }

    /** @param array<string, mixed> $payload
     *  @return array{status:int,headers:array<string,string>,body:string,json:mixed}
     */
    private function postJson(string $path, array $payload, int $timeoutSeconds): array
    {
        $body = json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE);
        if ($body === false) {
            throw new RuntimeException('Unable to encode a SchoolLift gateway request.');
        }
        return $this->http->request('POST', $this->url($path), [
            'Accept: application/json',
            'Content-Type: application/json',
            'Authorization: Bearer ' . (string) $this->config['bearer_token'],
            'X-SchoolLift-Gateway: ' . $this->gatewayId,
        ], $body, $timeoutSeconds);
    }

    /** @param array<int, string> $eventIds */
    private function batchId(array $eventIds, string $cursor): string
    {
        // Cursor is part of the request hash enforced by SchoolLift. Including
        // it here prevents reuse of one batch ID if polling legitimately
        // advances while an older queue row remains in backoff.
        $hex = hash('sha256', $this->gatewayId . "\n" . $cursor . "\n" . implode("\n", $eventIds));
        return substr($hex, 0, 8) . '-' . substr($hex, 8, 4) . '-5' . substr($hex, 13, 3)
            . '-a' . substr($hex, 17, 3) . '-' . substr($hex, 20, 12);
    }

    private function url(string $path): string
    {
        return rtrim((string) $this->config['base_url'], '/') . '/' . ltrim($path, '/');
    }
}
