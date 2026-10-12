<?php

// Exercise the real controller over HTTP on loopback with fake providers.
function auto_assert($condition, $message) {
    if (!$condition) { throw new RuntimeException($message); }
    $GLOBALS['auto_assertions']++;
}
$GLOBALS['auto_assertions'] = 0;
$socket = stream_socket_server('tcp://127.0.0.1:0', $errno, $error);
if (!$socket) { throw new RuntimeException($error); }
$address = stream_socket_get_name($socket, false); fclose($socket);
$log = tempnam(sys_get_temp_dir(), 'biometric_fixture_');
$processLog = tempnam(sys_get_temp_dir(), 'biometric_server_');
$environment = getenv(); $environment['BIOMETRIC_FIXTURE_LOG'] = $log;
$server = proc_open(array(PHP_BINARY, '-S', $address, __DIR__ . '/fixtures/biometric_notification_api_router.php'),
    array(0 => array('pipe', 'r'), 1 => array('file', $processLog, 'a'), 2 => array('file', $processLog, 'a')), $pipes, null, $environment);
if (!is_resource($server)) { throw new RuntimeException('Could not start local PHP fixture.'); }
fclose($pipes[0]);
function automatic_request($address, $path, $body, $auth = true, $method = 'POST') {
    // The installed gateway uses cURL, which honors completed Content-Length.
    $headers = array('Content-Type: application/json');
    if ($auth) { $headers[] = 'Authorization: Bearer fixture'; }
    $responseHeaders = array();
    $curl = curl_init('http://' . $address . $path);
    curl_setopt_array($curl, array(CURLOPT_CUSTOMREQUEST => $method, CURLOPT_HTTPHEADER => $headers,
        CURLOPT_POSTFIELDS => $body, CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 5,
        CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1, CURLOPT_HEADERFUNCTION => function ($curl, $line) use (&$responseHeaders) {
            $responseHeaders[] = trim($line); return strlen($line);
        }));
    $start = microtime(true);
    $result = curl_exec($curl);
    $elapsed = microtime(true) - $start;
    $error = curl_error($curl); curl_close($curl);
    if ($result === false) { throw new RuntimeException($error); }
    return array('body' => $result, 'headers' => $responseHeaders, 'elapsed' => $elapsed);
}
function worker_count($log, $expected) {
    $deadline = microtime(true) + 4;
    do {
        $lines = file($log, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        if (count($lines) >= $expected) { return $lines; }
        usleep(20000);
    } while (microtime(true) < $deadline);
    return $lines;
}
try {
    $ready = false;
    for ($i = 0; $i < 100; $i++) {
        $probe = @stream_socket_client('tcp://' . $address, $errno, $error, 0.1);
        if ($probe) { fclose($probe); $ready = true; break; }
        usleep(20000);
    }
    auto_assert($ready, 'Local HTTP fixture must start.');
    $valid = json_encode(array('gateway_id' => 'school-fixture', 'gateway_version' => '1.2.2', 'status' => array('can_accept_command' => false)));
    foreach (array(
        array('/poll', $valid, true, 'GET', 405),
        array('/poll', $valid, false, 'POST', 401),
        array('/poll', '{bad-json', true, 'POST', 400),
        array('/poll', '{}', true, 'POST', 422),
    ) as $case) {
        $response = automatic_request($address, $case[0], $case[1], $case[2], $case[3]);
        auto_assert(strpos($response['headers'][0], (string) $case[4]) !== false, 'Invalid request must retain HTTP ' . $case[4]);
        auto_assert(json_decode($response['body'], true) !== null, 'Errors must retain valid JSON.');
    }
    auto_assert(filesize($log) === 0, 'Rejected requests must not trigger delivery.');
    $response = automatic_request($address, '/disabled', $valid);
    auto_assert(json_decode($response['body'], true)['success'], 'Cron-only configuration must preserve successful heartbeat.');
    clearstatcache(true, $log);
    auto_assert(filesize($log) === 0, 'Explicitly disabled automatic delivery must not run.');

    $response = automatic_request($address, '/poll', $valid);
    auto_assert($response['elapsed'] < 0.9, 'Gateway JSON must finish before the 1.2-second provider call.');
    $json = json_decode($response['body'], true);
    auto_assert($json['success'] && array_key_exists('command', $json), 'Heartbeat command envelope must remain unchanged.');
    auto_assert(strpos($response['body'], 'provider') === false, 'Provider output must never corrupt JSON.');
    $lines = worker_count($log, 1);
    auto_assert(count($lines) === 1 && json_decode($lines[0], true) === array(20, 2, 15), 'Automatic heartbeat must run bounded, integration-scoped worker once.');

    $response = automatic_request($address, '/failure', $valid);
    auto_assert($response['elapsed'] < 0.9 && json_decode($response['body'], true)['success'], 'Provider exception must not delay or invalidate heartbeat.');
    $lines = worker_count($log, 2);
    auto_assert(count($lines) === 2, 'Subsequent heartbeat must dispatch without manual sync.');
    echo 'biometric automatic notification API tests passed (' . $GLOBALS['auto_assertions'] . " assertions)\n";
} finally {
    proc_terminate($server); proc_close($server);
    unlink($log); unlink($processLog);
}
