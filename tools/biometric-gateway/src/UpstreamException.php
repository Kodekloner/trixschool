<?php

declare(strict_types=1);

namespace SchoolLift\BiometricGateway;

use RuntimeException;

final class UpstreamException extends RuntimeException
{
    private int $statusCode;
    private ?int $retryAfterSeconds;

    public function __construct(string $message, int $statusCode = 0, ?int $retryAfterSeconds = null)
    {
        parent::__construct($message);
        $this->statusCode = $statusCode;
        $this->retryAfterSeconds = $retryAfterSeconds;
    }

    public function statusCode(): int
    {
        return $this->statusCode;
    }

    public function retryAfterSeconds(): ?int
    {
        return $this->retryAfterSeconds;
    }
}
