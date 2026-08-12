<?php

declare(strict_types=1);

namespace SchoolLift\BiometricGateway;

use RuntimeException;

final class JsonLogger
{
    private ?string $path;
    private bool $stdout;

    public function __construct(?string $path, bool $stdout = true)
    {
        $this->path = $path === '' ? null : $path;
        $this->stdout = $stdout;
        if ($this->path !== null) {
            $directory = dirname($this->path);
            if (!is_dir($directory) && !mkdir($directory, 0770, true) && !is_dir($directory)) {
                throw new RuntimeException('Unable to create gateway log directory: ' . $directory);
            }
            $handle = @fopen($this->path, 'ab');
            if ($handle === false) {
                throw new RuntimeException('Gateway log is not writable: ' . $this->path);
            }
            fclose($handle);
        }
    }

    /** @param array<string, mixed> $context */
    public function log(string $level, string $message, array $context = []): void
    {
        $record = [
            'time' => gmdate(DATE_ATOM),
            'level' => $level,
            'message' => $message,
            'context' => $this->redact($context),
        ];
        $line = json_encode($record, JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE);
        if ($line === false) {
            $line = '{"level":"error","message":"Unable to encode gateway log record"}';
        }
        $line .= PHP_EOL;
        if ($this->stdout) {
            fwrite(STDOUT, $line);
        }
        if ($this->path !== null) {
            if (file_put_contents($this->path, $line, FILE_APPEND | LOCK_EX) === false) {
                throw new RuntimeException('Unable to append the gateway log.');
            }
        }
    }

    /** @param array<string, mixed> $values @return array<string, mixed> */
    private function redact(array $values): array
    {
        $redacted = [];
        foreach ($values as $key => $value) {
            if (preg_match('/token|password|secret|authorization/i', (string) $key)) {
                $redacted[$key] = '[REDACTED]';
            } elseif (is_array($value)) {
                $redacted[$key] = $this->redact($value);
            } else {
                $redacted[$key] = $value;
            }
        }
        return $redacted;
    }
}
