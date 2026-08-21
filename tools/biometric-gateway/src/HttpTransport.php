<?php

declare(strict_types=1);

namespace SchoolLift\BiometricGateway;

interface HttpTransport
{
    /**
     * @param array<int, string> $headers
     * @return array{status:int,headers:array<string,string>,body:string,json:mixed}
     */
    public function request(
        string $method,
        string $url,
        array $headers = [],
        ?string $body = null,
        int $timeoutSeconds = 20
    ): array;
}
