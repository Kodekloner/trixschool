<?php

declare(strict_types=1);

namespace SchoolLift\BiometricGateway;

use DateTimeImmutable;

interface Clock
{
    public function now(): DateTimeImmutable;
}
