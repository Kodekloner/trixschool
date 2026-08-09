<?php

declare(strict_types=1);

$options = getopt('', ['host:', 'port:', 'allow-network', 'help']);
if (isset($options['help'])) {
    echo "Usage: php bin/serve.php [--host=127.0.0.1] [--port=8787] [--allow-network]" . PHP_EOL;
    exit(0);
}

$host = (string) ($options['host'] ?? '127.0.0.1');
$port = (int) ($options['port'] ?? 8787);
$loopbackHosts = ['127.0.0.1', 'localhost', '::1'];

if (!in_array($host, $loopbackHosts, true) && !isset($options['allow-network'])) {
    fwrite(
        STDERR,
        "Refusing to expose the simulator outside loopback. Use --allow-network only on a trusted test LAN." . PHP_EOL
    );
    exit(2);
}
if (isset($options['allow-network'])) {
    putenv('BIOMETRIC_SANDBOX_ALLOW_REMOTE=1');
}
if ($port < 1 || $port > 65535) {
    fwrite(STDERR, "Port must be between 1 and 65535." . PHP_EOL);
    exit(2);
}

$root = dirname(__DIR__);
$address = strpos($host, ':') !== false ? '[' . $host . ']:' . $port : $host . ':' . $port;
$command = escapeshellarg(PHP_BINARY)
    . ' -S ' . escapeshellarg($address)
    . ' -t ' . escapeshellarg($root . '/public')
    . ' ' . escapeshellarg($root . '/public/router.php');

echo 'SchoolLift biometric sandbox: http://' . $address . PHP_EOL;
echo 'Synthetic data only; press Ctrl+C to stop.' . PHP_EOL;
passthru($command, $exitCode);
exit((int) $exitCode);
