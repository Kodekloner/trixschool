<?php

/**
 * Browser smoke test for the shared A4 result report assets.
 *
 * Run with:
 *   php tests/result_report_a4_assets_test.php
 *
 * RESULT_REPORT_BROWSER may point to a specific Chrome, Chromium, or Edge
 * executable. The test exits successfully with an explicit SKIP message when
 * no compatible browser is installed.
 */

$assertions = 0;
$temporaryDirectory = null;

function result_report_a4_fail($message)
{
    fwrite(STDERR, 'FAIL: ' . $message . PHP_EOL);
    exit(1);
}

function result_report_a4_assert($condition, $message)
{
    global $assertions;
    $assertions++;

    if (!$condition) {
        result_report_a4_fail($message);
    }
}

function result_report_a4_remove_tree($path)
{
    if (!is_string($path) || $path === '' || !file_exists($path)) {
        return;
    }

    if (is_file($path) || is_link($path)) {
        @unlink($path);
        return;
    }

    $entries = scandir($path);
    if ($entries === false) {
        return;
    }

    foreach ($entries as $entry) {
        if ($entry === '.' || $entry === '..') {
            continue;
        }

        result_report_a4_remove_tree($path . DIRECTORY_SEPARATOR . $entry);
    }

    @rmdir($path);
}

register_shutdown_function(function () use (&$temporaryDirectory) {
    if ($temporaryDirectory !== null) {
        result_report_a4_remove_tree($temporaryDirectory);
    }
});

function result_report_a4_run_process(array $arguments, $timeoutSeconds)
{
    $command = implode(' ', array_map('escapeshellarg', $arguments));
    $descriptorSpec = array(
        0 => array('pipe', 'r'),
        1 => array('pipe', 'w'),
        2 => array('pipe', 'w'),
    );
    $pipes = array();
    $process = proc_open($command, $descriptorSpec, $pipes);

    if (!is_resource($process)) {
        return array('exit_code' => 127, 'stdout' => '', 'stderr' => 'Unable to start process.');
    }

    fclose($pipes[0]);
    stream_set_blocking($pipes[1], false);
    stream_set_blocking($pipes[2], false);

    $stdout = '';
    $stderr = '';
    $startedAt = microtime(true);
    $exitCode = null;
    $timedOut = false;

    while (true) {
        $stdout .= stream_get_contents($pipes[1]);
        $stderr .= stream_get_contents($pipes[2]);
        $status = proc_get_status($process);

        if (!$status['running']) {
            $exitCode = $status['exitcode'];
            break;
        }

        if ((microtime(true) - $startedAt) >= $timeoutSeconds) {
            $timedOut = true;
            proc_terminate($process);
            usleep(250000);
            $status = proc_get_status($process);
            if ($status['running']) {
                proc_terminate($process, 9);
            }
            break;
        }

        usleep(50000);
    }

    $stdout .= stream_get_contents($pipes[1]);
    $stderr .= stream_get_contents($pipes[2]);
    fclose($pipes[1]);
    fclose($pipes[2]);
    $closeCode = proc_close($process);

    if ($exitCode === null || $exitCode < 0) {
        $exitCode = $closeCode;
    }

    if ($timedOut) {
        $exitCode = 124;
        $stderr .= PHP_EOL . 'Browser process exceeded ' . $timeoutSeconds . ' seconds.';
    }

    return array(
        'exit_code' => $exitCode,
        'stdout' => $stdout,
        'stderr' => $stderr,
    );
}

function result_report_a4_command_path($name)
{
    $output = array();
    $exitCode = 0;
    exec('command -v ' . escapeshellarg($name) . ' 2>/dev/null', $output, $exitCode);

    if ($exitCode !== 0 || empty($output)) {
        return null;
    }

    return trim($output[0]);
}

function result_report_a4_browser_candidates()
{
    $candidates = array();
    $override = getenv('RESULT_REPORT_BROWSER');

    if (is_string($override) && trim($override) !== '') {
        $candidates[] = trim($override);
    }

    foreach (array(
        'chromium',
        'chromium-browser',
        'google-chrome',
        'google-chrome-stable',
        'microsoft-edge',
        'microsoft-edge-stable',
    ) as $command) {
        $path = result_report_a4_command_path($command);
        if ($path !== null) {
            $candidates[] = $path;
        }
    }

    foreach (array(
        '/mnt/c/Program Files/Google/Chrome/Application/chrome.exe',
        '/mnt/c/Program Files (x86)/Google/Chrome/Application/chrome.exe',
        '/mnt/c/Program Files/Microsoft/Edge/Application/msedge.exe',
        '/mnt/c/Program Files (x86)/Microsoft/Edge/Application/msedge.exe',
    ) as $path) {
        if (is_file($path)) {
            $candidates[] = $path;
        }
    }

    return array_values(array_unique($candidates));
}

function result_report_a4_find_browser()
{
    foreach (result_report_a4_browser_candidates() as $candidate) {
        $isWindowsBrowser = (bool) preg_match('/\.exe$/i', $candidate);

        /*
         * Windows Chrome may forward --version to an already-running desktop
         * profile instead of printing a version. Avoid touching that profile;
         * the isolated headless fixture below is the real compatibility check.
         */
        if ($isWindowsBrowser) {
            return array(
                'path' => $candidate,
                'windows' => true,
                'version' => basename($candidate),
            );
        }

        $version = result_report_a4_run_process(array($candidate, '--version'), 10);
        if ($version['exit_code'] === 0) {
            return array(
                'path' => $candidate,
                'windows' => false,
                'version' => trim($version['stdout'] . ' ' . $version['stderr']),
            );
        }
    }

    return null;
}

function result_report_a4_windows_to_linux_path($path)
{
    $path = trim(str_replace("\r", '', $path));
    if (!preg_match('/^([A-Za-z]):[\\\\\/](.*)$/', $path, $matches)) {
        return null;
    }

    return '/mnt/' . strtolower($matches[1]) . '/' . str_replace('\\', '/', $matches[2]);
}

function result_report_a4_windows_temp_directory()
{
    foreach (array(
        '/mnt/c/Windows/System32/cmd.exe',
        '/mnt/c/WINDOWS/System32/cmd.exe',
    ) as $commandPrompt) {
        if (!is_file($commandPrompt)) {
            continue;
        }

        $result = result_report_a4_run_process(
            array($commandPrompt, '/d', '/c', 'echo', '%TEMP%'),
            10
        );
        if ($result['exit_code'] !== 0) {
            continue;
        }

        $linuxPath = result_report_a4_windows_to_linux_path($result['stdout']);
        if ($linuxPath !== null && is_dir($linuxPath) && is_writable($linuxPath)) {
            return $linuxPath;
        }
    }

    return null;
}

function result_report_a4_to_browser_path($path, $windowsBrowser)
{
    if (!$windowsBrowser) {
        return $path;
    }

    if (preg_match('#^/mnt/([A-Za-z])/(.*)$#', $path, $matches)) {
        return strtoupper($matches[1]) . ':\\' . str_replace('/', '\\', $matches[2]);
    }

    return $path;
}

function result_report_a4_file_url($path, $windowsBrowser)
{
    $browserPath = result_report_a4_to_browser_path($path, $windowsBrowser);
    $browserPath = str_replace('\\', '/', $browserPath);
    $parts = explode('/', $browserPath);

    foreach ($parts as $index => $part) {
        $parts[$index] = rawurlencode($part);
    }

    $encoded = implode('/', $parts);
    $encoded = str_replace('%3A', ':', $encoded);

    if ($windowsBrowser) {
        return 'file:///' . ltrim($encoded, '/');
    }

    return 'file://' . ($encoded[0] === '/' ? '' : '/') . $encoded;
}

function result_report_a4_fixture_html($rowCount, $css, $javascript)
{
    $rows = '';
    $longSubject = 'AdvancedInterdisciplinaryResearchAndAppliedCommunicationSubjectWithAnExceptionallyLongName';

    for ($index = 1; $index <= $rowCount; $index++) {
        $subject = $longSubject . ' ' . $index;
        $rows .= '<tr data-academic-row>'
            . '<td>' . htmlspecialchars($subject, ENT_QUOTES, 'UTF-8') . '</td>'
            . '<td>18</td><td>17</td><td>62</td><td>97</td>'
            . '<td>91</td><td>88</td><td>92.00</td><td>A</td>'
            . '<td>Excellent and consistently thoughtful performance</td>'
            . '</tr>';
    }

    $javascript = str_ireplace('</script', '<\\/script', $javascript);

    return '<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <style>' . $css . '</style>
  <title>A4 result fixture</title>
</head>
<body>
  <div class="result-report-preview" data-result-report-preview>
    <article class="result-report" data-result-report data-academic-row-count="' . $rowCount . '">
      <div class="result-report__content" data-result-report-content>
        <header class="result-report__header">
          <div></div>
          <div class="result-report__school">
            <h1 class="result-report__school-name">The International Academy for Science, Technology, Languages and Creative Leadership</h1>
            <p class="result-report__school-address">Plot 12345, An Intentionally Long Crescent Name, Beside the Metropolitan Community Development Centre, Lagos State, Nigeria</p>
            <p class="result-report__school-contact">school@example.test · www.an-intentionally-long-school-domain.example.test</p>
          </div>
          <div></div>
        </header>
        <h2 class="result-report__title">Third Term Cumulative Academic Performance Report</h2>
        <section class="result-report__student-details">
          <div class="result-report__detail"><span class="result-report__label">Student</span><span class="result-report__value">A Student With Several Long Names</span></div>
          <div class="result-report__detail"><span class="result-report__label">Class</span><span class="result-report__value">JSS 1 — Sapphire</span></div>
          <div class="result-report__detail"><span class="result-report__label">Session</span><span class="result-report__value">2025/2026</span></div>
          <div class="result-report__detail"><span class="result-report__label">NO.</span><span class="result-report__value">128</span></div>
        </section>
        <section class="result-report__section">
          <h3 class="result-report__section-title">Academic Performance</h3>
          <div class="result-report__table-wrap">
            <table id="academic-performance" class="result-report__table">
              <thead><tr><th class="result-report__cell--subject">Subject</th><th>CA 1</th><th>CA 2</th><th>Exam</th><th>Total</th><th>1st</th><th>2nd</th><th>Cumulative Average</th><th>Grade</th><th class="result-report__cell--comment">Remark</th></tr></thead>
              <tbody>' . $rows . '</tbody>
            </table>
          </div>
        </section>
        <div class="result-report__summary-grid">
          <section class="result-report__panel"><h3 class="result-report__panel-title">Key to Grades</h3><div class="result-report__panel-body"><ul class="result-report__grade-key"><li class="result-report__grade-item">A: 70% and above</li><li class="result-report__grade-item">B: 60%–69.9%</li><li class="result-report__grade-item">C: 50%–59.9%</li><li class="result-report__grade-item">F: 0%–49.9%</li></ul></div></section>
          <section class="result-report__panel"><h3 class="result-report__panel-title">Grade Summary</h3><div class="result-report__panel-body"><ul class="result-report__grade-summary"><li class="result-report__grade-count"><strong>' . $rowCount . '</strong>A</li></ul></div></section>
        </div>
        <section class="result-report__promotion" data-promotion-status="promoted"><span class="result-report__promotion-label">Promotion Status</span><strong class="result-report__promotion-value">PROMOTED TO: JSS 2</strong></section>
        <section class="result-report__chart result-report__chart--decorative" data-result-decorative-chart><svg viewBox="0 0 100 16" role="img"><rect width="100" height="16" fill="#eceff3"></rect><path d="M0 14 L20 8 L40 10 L60 3 L80 7 L100 1" fill="none" stroke="#6d247f"></path></svg></section>
        <section class="result-report__comments"><div class="result-report__comment"><strong class="result-report__label">Teacher&apos;s Comment</strong><p class="result-report__comment-text">A focused learner who has made steady progress throughout the academic year.</p></div><div class="result-report__comment"><strong class="result-report__label">Head Teacher&apos;s Comment</strong><p class="result-report__comment-text">An excellent result. Continue the good work.</p></div></section>
      </div>
    </article>
  </div>
  <script>' . $javascript . '</script>
  <script>
  (function () {
    function publishResult() {
      window.ResultReportPrint.fitAll();
      window.requestAnimationFrame(function () {
        window.requestAnimationFrame(function () {
          var root = document.querySelector("[data-result-report]");
          var content = root.querySelector("[data-result-report-content]");
          var table = document.getElementById("academic-performance");
          var rootRect = root.getBoundingClientRect();
          var contentRect = content.getBoundingClientRect();
          var tableRect = table.getBoundingClientRect();
          var tolerance = 1;
          var payload = {
            density: root.getAttribute("data-result-density"),
            rowCount: table.querySelectorAll("[data-academic-row]").length,
            fitScale: parseFloat(root.getAttribute("data-result-fit-scale")),
            tableWithinContent: tableRect.left >= contentRect.left - tolerance && tableRect.right <= contentRect.right + tolerance,
            tableWithinReport: tableRect.left >= rootRect.left - tolerance && tableRect.right <= rootRect.right + tolerance,
            viewportContained: document.documentElement.scrollWidth <= document.documentElement.clientWidth + tolerance
          };
          var output = document.createElement("pre");
          output.id = "result-report-test-output";
          output.textContent = JSON.stringify(payload);
          document.body.appendChild(output);
        });
      });
    }

    window.ResultReportPrint.initAll(document);
    publishResult();
  }());
  </script>
</body>
</html>';
}

function result_report_a4_parse_payload($html)
{
    if (!preg_match('#<pre id="result-report-test-output">(.*?)</pre>#s', $html, $matches)) {
        return null;
    }

    $json = html_entity_decode($matches[1], ENT_QUOTES | ENT_HTML5, 'UTF-8');
    $payload = json_decode($json, true);
    return is_array($payload) ? $payload : null;
}

function result_report_a4_pdf_details($pdfPath)
{
    $pdf = file_get_contents($pdfPath);
    if ($pdf === false) {
        return null;
    }

    preg_match_all('/\/Type\s*\/Page\b/', $pdf, $pageMatches);
    $width = null;
    $height = null;

    if (preg_match('/\/MediaBox\s*\[\s*[-0-9.]+\s+[-0-9.]+\s+([0-9.]+)\s+([0-9.]+)\s*\]/', $pdf, $mediaBox)) {
        $width = (float) $mediaBox[1];
        $height = (float) $mediaBox[2];
    }

    return array(
        'pages' => count($pageMatches[0]),
        'width' => $width,
        'height' => $height,
    );
}

$browser = result_report_a4_find_browser();
if ($browser === null) {
    echo 'SKIP: result report A4 browser test (no compatible Chrome, Chromium, or Edge executable found).' . PHP_EOL;
    exit(0);
}

$repositoryRoot = dirname(__DIR__);
$cssPath = $repositoryRoot . '/assets/css/result-report.css';
$javascriptPath = $repositoryRoot . '/assets/js/result-report-print.js';
$css = file_get_contents($cssPath);
$javascript = file_get_contents($javascriptPath);

result_report_a4_assert($css !== false, 'The shared result report stylesheet must be readable.');
result_report_a4_assert($javascript !== false, 'The shared result report print script must be readable.');

$temporaryBase = $browser['windows'] ? result_report_a4_windows_temp_directory() : sys_get_temp_dir();
if ($temporaryBase === null || !is_dir($temporaryBase) || !is_writable($temporaryBase)) {
    result_report_a4_fail('A writable temporary directory visible to the selected browser is required.');
}

$temporaryDirectory = rtrim($temporaryBase, '/\\')
    . DIRECTORY_SEPARATOR
    . 'result-report-a4-test-'
    . getmypid()
    . '-'
    . bin2hex(random_bytes(4));

if (!mkdir($temporaryDirectory, 0700, true) && !is_dir($temporaryDirectory)) {
    result_report_a4_fail('Unable to create the result report test directory.');
}

$profileDirectory = $temporaryDirectory . DIRECTORY_SEPARATOR . 'browser-profile';
if (!mkdir($profileDirectory, 0700, true) && !is_dir($profileDirectory)) {
    result_report_a4_fail('Unable to create the isolated browser profile.');
}

$fixtures = array(
    array('rows' => 5, 'density' => 'standard'),
    array('rows' => 12, 'density' => 'standard'),
    array('rows' => 18, 'density' => 'compact'),
    array('rows' => 25, 'density' => 'ultra'),
);

foreach ($fixtures as $fixture) {
    $rowCount = $fixture['rows'];
    $htmlPath = $temporaryDirectory . DIRECTORY_SEPARATOR . 'result-' . $rowCount . '.html';
    $pdfPath = $temporaryDirectory . DIRECTORY_SEPARATOR . 'result-' . $rowCount . '.pdf';
    $html = result_report_a4_fixture_html($rowCount, $css, $javascript);

    result_report_a4_assert(
        file_put_contents($htmlPath, $html) !== false,
        'The ' . $rowCount . '-row HTML fixture must be writable.'
    );

    $commonArguments = array(
        $browser['path'],
        '--headless=new',
        '--disable-gpu',
        '--disable-dev-shm-usage',
        '--no-sandbox',
        '--no-first-run',
        '--no-default-browser-check',
        '--allow-file-access-from-files',
        '--run-all-compositor-stages-before-draw',
        '--window-size=1024,1400',
        '--user-data-dir=' . result_report_a4_to_browser_path($profileDirectory, $browser['windows']),
        '--virtual-time-budget=4000',
    );
    $fixtureUrl = result_report_a4_file_url($htmlPath, $browser['windows']);

    $domResult = result_report_a4_run_process(
        array_merge($commonArguments, array('--dump-dom', $fixtureUrl)),
        45
    );
    result_report_a4_assert(
        $domResult['exit_code'] === 0,
        'Chromium must render the ' . $rowCount . '-row fixture. ' . trim($domResult['stderr'])
    );

    $payload = result_report_a4_parse_payload($domResult['stdout']);
    result_report_a4_assert(
        is_array($payload),
        'The ' . $rowCount . '-row fixture must publish browser measurements. Browser output tail: '
            . substr(trim($domResult['stdout']), -1200)
    );
    result_report_a4_assert($payload['rowCount'] === $rowCount, 'The browser must render all ' . $rowCount . ' academic rows.');
    result_report_a4_assert($payload['density'] === $fixture['density'], 'The ' . $rowCount . '-row fixture must use ' . $fixture['density'] . ' density.');
    result_report_a4_assert($payload['fitScale'] > 0 && $payload['fitScale'] <= 1, 'The ' . $rowCount . '-row fit scale must be valid.');
    result_report_a4_assert($payload['tableWithinContent'] === true, 'The ' . $rowCount . '-row academic table must stay within report content.');
    result_report_a4_assert($payload['tableWithinReport'] === true, 'The ' . $rowCount . '-row academic table must stay within the A4 border.');
    result_report_a4_assert($payload['viewportContained'] === true, 'The ' . $rowCount . '-row preview must not create horizontal viewport overflow.');

    $printArguments = array_merge(
        $commonArguments,
        array(
            '--no-pdf-header-footer',
            '--print-to-pdf=' . result_report_a4_to_browser_path($pdfPath, $browser['windows']),
            $fixtureUrl,
        )
    );
    $printResult = result_report_a4_run_process($printArguments, 45);
    result_report_a4_assert(
        $printResult['exit_code'] === 0 && is_file($pdfPath),
        'Chromium must print the ' . $rowCount . '-row fixture to PDF. ' . trim($printResult['stderr'])
    );

    $pdfDetails = result_report_a4_pdf_details($pdfPath);
    result_report_a4_assert(is_array($pdfDetails), 'The ' . $rowCount . '-row PDF must be readable.');
    result_report_a4_assert($pdfDetails['pages'] === 1, 'The ' . $rowCount . '-row PDF must contain exactly one page.');
    result_report_a4_assert(
        $pdfDetails['width'] !== null && abs($pdfDetails['width'] - 595.28) <= 2,
        'The ' . $rowCount . '-row PDF width must be A4.'
    );
    result_report_a4_assert(
        $pdfDetails['height'] !== null && abs($pdfDetails['height'] - 841.89) <= 2,
        'The ' . $rowCount . '-row PDF height must be A4.'
    );

    echo 'PASS: ' . $rowCount . '-row A4 fixture (' . $fixture['density'] . ' density).' . PHP_EOL;
}

echo 'Result report A4 browser tests passed (' . $assertions . ' assertions) using '
    . $browser['version']
    . '.'
    . PHP_EOL;
