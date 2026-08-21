<?php

declare(strict_types=1);

if (PHP_VERSION_ID < 80200) {
    fwrite(STDERR, "SchoolLift biometric gateway requires PHP 8.2 or newer." . PHP_EOL);
    exit(2);
}

spl_autoload_register(static function (string $class): void {
    $prefix = 'SchoolLift\\BiometricGateway\\';
    if (strncmp($class, $prefix, strlen($prefix)) !== 0) {
        return;
    }

    $relative = substr($class, strlen($prefix));
    $path = __DIR__ . '/src/' . str_replace('\\', '/', $relative) . '.php';
    if (is_file($path)) {
        require_once $path;
    }
});
