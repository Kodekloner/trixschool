<?php

declare(strict_types=1);

namespace SchoolLift\BiometricGateway;

use RuntimeException;

final class FileLock
{
    /** @var resource|null */
    private $handle = null;

    public function __construct(string $path)
    {
        $directory = dirname($path);
        if (!is_dir($directory) && !mkdir($directory, 0770, true) && !is_dir($directory)) {
            throw new RuntimeException('Unable to create gateway lock directory: ' . $directory);
        }
        $handle = fopen($path, 'c+');
        if ($handle === false) {
            throw new RuntimeException('Unable to open gateway lock file: ' . $path);
        }
        $this->handle = $handle;
    }

    public function acquire(): bool
    {
        if (!is_resource($this->handle)) {
            return false;
        }
        return flock($this->handle, LOCK_EX | LOCK_NB);
    }

    public function release(): void
    {
        if (is_resource($this->handle)) {
            flock($this->handle, LOCK_UN);
        }
    }

    public function __destruct()
    {
        $this->release();
        if (is_resource($this->handle)) {
            fclose($this->handle);
        }
    }
}
