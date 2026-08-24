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
    $subjects = array(
        'Mathematics',
        'Basic Science and Technology',
        'Cultural and Creative Art',
        'English Language',
        'Information and Communication Technology',
        'Advanced Mathematics',
        'Agricultural Science',
        'Entrepreneurship and Business Studies',
        'Quantitative Reasoning',
    );

    for ($index = 1; $index <= $rowCount; $index++) {
        $subject = $subjects[($index - 1) % count($subjects)];
        $rows .= '<tr data-academic-row>'
            . '<td data-subject-cell>' . htmlspecialchars($subject, ENT_QUOTES, 'UTF-8') . '</td>'
            . '<td>18</td><td>17</td><td>62</td><td>97</td>'
            . '<td>91</td><td>88</td><td>92.00</td><td>A</td>'
            . '<td class="result-report__cell--remark" data-remark-cell>Excellent and consistently thoughtful performance</td>'
            . '</tr>';
    }

    $affectiveRows = '<tr><th data-domain-label>Attentiveness</th><td>5</td><th data-domain-label>Relationship with Mates</th><td>5</td></tr>'
        . '<tr><th data-domain-label>Class Participation</th><td>4</td><th data-domain-label>Organisational Ability</th><td>4</td></tr>'
        . '<tr><th data-domain-label>Honesty</th><td>5</td><th data-domain-label>Politeness</th><td>5</td></tr>'
        . '<tr><th data-domain-label>Neatness</th><td>4</td><th data-domain-label>Self Control</th><td>4</td></tr>'
        . '<tr><th data-domain-label>Responsibility</th><td>5</td><th data-domain-label>Cooperation</th><td>5</td></tr>'
        . '<tr><th data-domain-label>Leadership</th><td>4</td><td></td><td></td></tr>';
    $psychomotorRows = '<tr><th data-domain-label>Handwriting</th><td>5</td><td></td><td></td></tr>'
        . '<tr><th data-domain-label>Verbal Fluency</th><td>4</td><td></td><td></td></tr>'
        . '<tr><th data-domain-label>Gymnastic Skills</th><td>4</td><td></td><td></td></tr>'
        . '<tr><th data-domain-label>Handling of Equipment</th><td>5</td><td></td><td></td></tr>'
        . '<tr><th data-domain-label>Drawing and Painting</th><td>4</td><td></td><td></td></tr>'
        . '<tr><th data-domain-label>Musical Skills</th><td>5</td><td></td><td></td></tr>';
    $attendanceRows = '<tr><th data-attendance-label>TOTAL DAYS</th><td>120</td></tr>'
        . '<tr><th data-attendance-label>PRESENT</th><td>116</td></tr>'
        . '<tr><th data-attendance-label>ABSENT</th><td>3</td></tr>'
        . '<tr><th data-attendance-label>LATE</th><td>1</td></tr>';

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
        <svg class="watermark-logo result-report__watermark" viewBox="0 0 100 100" aria-hidden="true"><circle cx="50" cy="50" r="42" fill="#6d247f"></circle></svg>
        <div class="card-body">
        <div class="rel">
        <div class="row result-report__legacy-header">
          <div class="col"><div align="center"><svg viewBox="0 0 100 100" class="img-fluid" style="margin:10px;width:50%" aria-label="School logo"><circle cx="50" cy="50" r="42" fill="#6d247f"></circle></svg></div></div>
          <div class="col-6">
            <p class="schname" style="font-size:25px">The International Academy for Science, Technology, Languages and Creative Leadership</p>
            <p class="schloc" style="color:rgb(185,7,7);font-size:16px;margin-top:-20px">Plot 12345, An Intentionally Long Crescent Name, Beside the Metropolitan Community Development Centre, Lagos State, Nigeria.</p>
            <div style="margin-top:-10px;text-align:center"><span>school@example.test</span><br><span>www.an-intentionally-long-school-domain.example.test</span></div>
          </div>
          <div class="col"><svg viewBox="0 0 80 100" class="img-fluid" style="margin:10px;width:45%;height:120px" aria-label="Student photograph"><rect width="80" height="100" fill="#ddd"></rect></svg></div>
        </div><br>
        <div align="center" class="result-report__legacy-title"><h5 class="report-title" style="font-size:17px;font-weight:500;margin-top:-40px">Third Term Cumulative Academic Performance Report</h5></div>
        <div class="container-motto">
          <div class="row">
            <div class="col-4"><h5>NAME: <b>A Student With Several Long Names</b></h5></div>
            <div class="col-4"><h5>CLASS: <b>JSS 1 — Sapphire</b></h5></div>
            <div class="col-4"><h5>SESSION: <b>2025/2026</b></h5></div>
          </div>
          <section class="result-report__summary result-report__summary--statistics" data-result-summary-section="statistics" aria-label="Result statistics">
            <div class="result-report__statistics">
              <div class="result-report__stat"><h5 class="result-report__stat-line"><span class="result-report__label">NO. IN CLASS:</span> <b class="result-report__value">128</b></h5></div>
              <div class="result-report__stat"><h5 class="result-report__stat-line"><span class="result-report__label">GRADE SUMMARY:</span> <b class="result-report__value">' . $rowCount . 'A</b></h5></div>
              <div class="result-report__stat"><h5 class="result-report__stat-line"><span class="result-report__label">CUMULATIVE AVERAGE SCORE:</span> <b class="result-report__value">92.00</b></h5></div>
            </div>
          </section>
        </div>
        <div align="center"><h5 style="font-size:18px;font-weight:800;color:#000;margin-bottom:0">ACADEMIC PERFORMANCE</h5></div>
        <div class="result table-responsive result-report__academic-table-wrap result-report__table-wrap">
          <table id="academic-performance" class="table-bordered table-striped tab table-sm tb-result-border result-report__academic-table result-report__table">
            <thead><tr><th>Subject</th><th>CA 1</th><th>CA 2</th><th>Exam</th><th>Total</th><th>1st</th><th>2nd</th><th>Cumulative Average</th><th>Grade</th><th class="result-report__cell--remark">Remark</th></tr></thead>
            <tbody>' . $rows . '</tbody>
          </table>
        </div>
        <section class="result-report__summary result-report__summary--grade-key" data-result-summary-section="grade-key" aria-label="Key to grades">
          <div class="result-report__panel"><p class="result-report__panel-title">Key to Grades</p><div class="result-report__panel-body"><ul class="result-report__grade-key"><li class="result-report__grade-item"><b class="result-report__grade-symbol">A:</b> 70% and above</li><li class="result-report__grade-item"><b class="result-report__grade-symbol">B:</b> 60%–69.9%</li><li class="result-report__grade-item"><b class="result-report__grade-symbol">C:</b> 50%–59.9%</li><li class="result-report__grade-item"><b class="result-report__grade-symbol">D:</b> 45%–49.9%</li><li class="result-report__grade-item"><b class="result-report__grade-symbol">E:</b> 40%–44.9%</li><li class="result-report__grade-item"><b class="result-report__grade-symbol">F:</b> 0%–39.9%</li></ul></div></div>
        </section>
        <section class="performance result-report__performance" id="performance-fixture">
          <div class="row result-report__performance-row">
            <div class="col-4 result-report__chart-column">
              <div class="containerForChart result-report__chart result-report__chart--performance" data-result-decorative-chart><canvas class="newgraph" id="fixture-chart"></canvas></div>
            </div>
            <div class="col-8 result-report__domains-column" id="domain-column-fixture">
              <div class="container-motto result-report__domain-panel">
                <div class="result result-report__domain-tables" id="domain-group-fixture">
                  <table id="affective-fixture" class="tab table-sm result-report__domain-table" data-result-domain-kind="affective"><thead><tr><th class="result-report__domain-title" colspan="4">AFFECTIVE DOMAIN</th></tr></thead><tbody>' . $affectiveRows . '</tbody></table>
                  <table id="attendance-fixture" class="tab table-sm result-report__attendance-table" data-result-domain-kind="attendance"><thead><tr><th class="result-report__domain-title result-report__attendance-title" colspan="2">ATTENDANCE</th></tr></thead><tbody>' . $attendanceRows . '</tbody></table>
                  <table id="psychomotor-fixture" class="tab table-sm result-report__domain-table" data-result-domain-kind="psychomotor"><thead><tr><th class="result-report__domain-title" colspan="4">PSYCOMOTOR</th></tr></thead><tbody>' . $psychomotorRows . '</tbody></table>
                </div>
              </div>
            </div>
            <div class="col-12 result-report__remarks-section" data-result-remarks-section id="legacy-remarks-fixture">
              <div class="container-motto">
                <div style="margin:20px">
                  <div class="row">
                    <div class="col-sm-10 col-md-10"><p><b>CLASS TEACHER&apos;S COMMENT:</b> A focused learner who has made steady progress.</p></div>
                    <div class="col-sm-2 col-md-2 signature-container" style="height:56px"><svg class="signature-img" data-teacher-signature viewBox="0 0 80 24" aria-label="Teacher signature" style="width:100%;height:100%"><path d="M3 18 C20 2, 31 23, 47 8 S65 19, 77 5" fill="none" stroke="#222" stroke-width="2"></path></svg></div>
                  </div>
                  <div class="row">
                    <div class="col-sm-10 col-md-10"><p><b>PRINCIPAL/HEAD TEACHER&apos;S COMMENT:</b> An excellent result. Continue the good work.</p></div>
                    <div class="col-sm-2 col-md-2 signature-container" style="height:56px"><svg class="signature-img" data-principal-signature viewBox="0 0 80 24" aria-label="Principal signature" style="width:100%;height:100%"><path d="M4 17 C17 4, 29 22, 44 7 S62 18, 76 6" fill="none" stroke="#222" stroke-width="2"></path></svg></div>
                  </div>
                </div>
              </div>
            </div>
          </div>
        </section>
        <section class="result-report__summary result-report__summary--promotion" data-result-summary-section="promotion" aria-label="Promotion status">
          <div class="result-report__promotion" data-promotion-status="promoted"><span class="result-report__promotion-label">Promotion Status</span><strong class="result-report__promotion-value">PROMOTED TO: JSS 2</strong></div>
        </section>
        </div>
        </div>
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
          var watermark = root.querySelector(".result-report__watermark");
          var cardBody = root.querySelector(".card-body");
          var preview = root.closest("[data-result-report-preview]");
          var performance = document.getElementById("performance-fixture");
          var performanceChart = root.querySelector(".result-report__chart--performance");
          var performanceCanvas = document.getElementById("fixture-chart");
          var affectiveTable = document.getElementById("affective-fixture");
          var attendanceTable = document.getElementById("attendance-fixture");
          var psychomotorTable = document.getElementById("psychomotor-fixture");
          var domainGroup = document.getElementById("domain-group-fixture");
          var domainColumn = document.getElementById("domain-column-fixture");
          var remarksSection = document.getElementById("legacy-remarks-fixture");
          var teacherSignature = remarksSection.querySelector("[data-teacher-signature]");
          var principalSignature = remarksSection.querySelector("[data-principal-signature]");
          var legacyHeader = root.querySelector(".result-report__legacy-header");
          var legacyTitle = root.querySelector(".result-report__legacy-title");
          var studentInfo = root.querySelector(".container-motto");
          var statisticsSection = root.querySelector("[data-result-summary-section=statistics]");
          var statistics = root.querySelector(".result-report__statistics");
          var statistic = root.querySelector(".result-report__stat");
          var statisticLine = root.querySelector(".result-report__stat-line");
          var studentInfoLine = studentInfo.querySelector(":scope > .row h5");
          var tableWrap = table.closest(".result-report__table-wrap");
          var gradeKeySection = root.querySelector("[data-result-summary-section=grade-key]");
          var promotion = root.querySelector(".result-report__promotion");
          var promotionLabel = root.querySelector(".result-report__promotion-label");
          var promotionValue = root.querySelector(".result-report__promotion-value");
          var gradeItem = root.querySelector(".result-report__grade-item");
          var headerCell = table.tHead.rows[0].cells[0];
          var firstBodyRow = table.tBodies[0].rows[0];
          var secondBodyRow = table.tBodies[0].rows[1];
          var watermarkStyle = window.getComputedStyle(watermark);
          var cardBodyStyle = window.getComputedStyle(cardBody);
          var headerCellStyle = window.getComputedStyle(headerCell);
          var firstBodyCellStyle = window.getComputedStyle(firstBodyRow.cells[0]);
          var firstRowStyle = window.getComputedStyle(firstBodyRow.cells[1]);
          var secondRowStyle = window.getComputedStyle(secondBodyRow.cells[1]);
          var secondRowCellsConsistent = Array.prototype.every.call(secondBodyRow.cells, function (cell) {
            return window.getComputedStyle(cell).backgroundColor === secondRowStyle.backgroundColor;
          });
          var promotionLabelStyle = window.getComputedStyle(promotionLabel);
          var promotionValueStyle = window.getComputedStyle(promotionValue);
          var gradeItemStyle = window.getComputedStyle(gradeItem);
          var statisticsStyle = window.getComputedStyle(statistics);
          var statisticStyle = window.getComputedStyle(statistic);
          var statisticLineStyle = window.getComputedStyle(statisticLine);
          var studentInfoLineStyle = window.getComputedStyle(studentInfoLine);
          var rootRect = root.getBoundingClientRect();
          var contentRect = content.getBoundingClientRect();
          var tableRect = table.getBoundingClientRect();
          var watermarkRect = watermark.getBoundingClientRect();
          var performanceRect = performance.getBoundingClientRect();
          var performanceChartRect = performanceChart.getBoundingClientRect();
          var performanceCanvasRect = performanceCanvas.getBoundingClientRect();
          var affectiveRect = affectiveTable.getBoundingClientRect();
          var attendanceRect = attendanceTable.getBoundingClientRect();
          var psychomotorRect = psychomotorTable.getBoundingClientRect();
          var domainGroupRect = domainGroup.getBoundingClientRect();
          var domainColumnRect = domainColumn.getBoundingClientRect();
          var remarksRect = remarksSection.getBoundingClientRect();
          var teacherSignatureRect = teacherSignature.getBoundingClientRect();
          var principalSignatureRect = principalSignature.getBoundingClientRect();
          var legacyHeaderRect = legacyHeader.getBoundingClientRect();
          var legacyTitleRect = legacyTitle.getBoundingClientRect();
          var tolerance = 1;
          var contentLeftGap = contentRect.left - rootRect.left;
          var contentRightGap = rootRect.right - contentRect.right;
          var subjectWordsRemainWhole = Array.prototype.every.call(table.querySelectorAll("[data-subject-cell]"), function (cell) {
            var textNode = cell.firstChild;
            var wordPattern = /\S+/g;
            var match;

            if (!textNode || textNode.nodeType !== Node.TEXT_NODE) {
              return false;
            }

            while ((match = wordPattern.exec(textNode.nodeValue)) !== null) {
              var range = document.createRange();
              range.setStart(textNode, match.index);
              range.setEnd(textNode, match.index + match[0].length);
              if (range.getClientRects().length !== 1) {
                return false;
              }
            }

            return cell.scrollWidth <= cell.clientWidth + tolerance;
          });
          function wordsRemainWhole(selector) {
            return Array.prototype.every.call(root.querySelectorAll(selector), function (cell) {
              var walker = document.createTreeWalker(cell, NodeFilter.SHOW_TEXT);
              var textNode;
              var cellRect = cell.getBoundingClientRect();

              while ((textNode = walker.nextNode())) {
                var wordPattern = /\S+/g;
                var match;

                while ((match = wordPattern.exec(textNode.nodeValue)) !== null) {
                  var range = document.createRange();
                  range.setStart(textNode, match.index);
                  range.setEnd(textNode, match.index + match[0].length);
                  if (range.getClientRects().length !== 1) {
                    return false;
                  }
                  var wordRect = range.getClientRects()[0];
                  if (wordRect.left < cellRect.left - tolerance
                    || wordRect.right > cellRect.right + tolerance
                    || wordRect.top < cellRect.top - tolerance
                    || wordRect.bottom > cellRect.bottom + tolerance) {
                    return false;
                  }
                }
              }

              return cell.scrollWidth <= cell.clientWidth + tolerance
                && cell.scrollHeight <= cell.clientHeight + tolerance;
            });
          }
          var visibleDomainTables = Array.prototype.filter.call(domainGroup.children, function (table) {
            return window.getComputedStyle(table).display !== "none";
          });
          var visibleDomainRects = visibleDomainTables.map(function (table) {
            return table.getBoundingClientRect();
          }).sort(function (left, right) {
            return left.left - right.left;
          });
          var domainTablesDoNotOverlap = visibleDomainRects.every(function (rect, index) {
            return index === 0 || visibleDomainRects[index - 1].right <= rect.left + tolerance;
          });
          var domainTitles = Array.prototype.map.call(
            domainGroup.querySelectorAll(".result-report__domain-title"),
            function (title) { return title.getBoundingClientRect().top; }
          );
          var attendanceLabel = attendanceTable.querySelector("[data-attendance-label]");
          var attendanceLabelRect = attendanceLabel.getBoundingClientRect();
          var remarkHeader = table.querySelector(".result-report__cell--remark");
          var watermarkContentCenterX = contentRect.left + (contentRect.width / 2);
          var watermarkContentCenterY = contentRect.top + (contentRect.height / 2);
          var watermarkCenterX = watermarkRect.left + (watermarkRect.width / 2);
          var watermarkCenterY = watermarkRect.top + (watermarkRect.height / 2);
          var payload = {
            density: root.getAttribute("data-result-density"),
            rowCount: table.querySelectorAll("[data-academic-row]").length,
            fitScale: parseFloat(root.getAttribute("data-result-fit-scale")),
            tableWithinContent: tableRect.left >= contentRect.left - tolerance && tableRect.right <= contentRect.right + tolerance,
            tableWithinReport: tableRect.left >= rootRect.left - tolerance && tableRect.right <= rootRect.right + tolerance,
            viewportContained: document.documentElement.scrollWidth <= document.documentElement.clientWidth + tolerance,
            contentHorizontallyCentered: Math.abs(contentLeftGap - contentRightGap) <= tolerance,
            statisticsInsideStudentInfo: studentInfo.contains(statisticsSection),
            statisticsUnboxed: parseFloat(statisticsStyle.borderTopWidth) === 0
              && parseFloat(statisticStyle.borderRightWidth) === 0,
            statisticsMatchStudentText: statisticLineStyle.fontSize === studentInfoLineStyle.fontSize
              && statisticLineStyle.lineHeight === studentInfoLineStyle.lineHeight,
            gradeKeyDirectlyAfterTable: tableWrap.nextElementSibling === gradeKeySection,
            academicTableContract: table.classList.contains("table-striped")
              && table.classList.contains("result-report__academic-table")
              && table.tHead !== null,
            subjectUsesBodyStyling: firstBodyCellStyle.backgroundColor !== headerCellStyle.backgroundColor,
            subjectColumnWidthRatio: headerCell.getBoundingClientRect().width / tableRect.width,
            subjectWordsRemainWhole: subjectWordsRemainWhole,
            remarkColumnWidthRatio: remarkHeader.getBoundingClientRect().width / tableRect.width,
            remarkWordsRemainWhole: wordsRemainWhole("[data-remark-cell]"),
            domainWordsRemainWhole: wordsRemainWhole("[data-domain-label]"),
            attendanceWordsRemainWhole: attendanceTable.querySelectorAll("[data-attendance-label]").length === 4
              && wordsRemainWhole("[data-attendance-label]"),
            attendanceLabelWidthRatio: attendanceLabelRect.width / attendanceRect.width,
            attendanceTableWidthRatio: attendanceRect.width / domainGroupRect.width,
            attendanceInsideGroup: attendanceRect.left >= domainGroupRect.left - tolerance
              && attendanceRect.right <= domainGroupRect.right + tolerance,
            allDomainTablesAvailable: domainGroup.getAttribute("data-result-panel-count") === "3"
              && visibleDomainTables.length === 3,
            domainTablesFillGroup: visibleDomainRects.length === 3
              && Math.abs(visibleDomainRects[0].left - domainGroupRect.left) <= tolerance
              && Math.abs(visibleDomainRects[visibleDomainRects.length - 1].right - domainGroupRect.right) <= tolerance,
            domainTablesDoNotOverlap: domainTablesDoNotOverlap,
            domainTitlesAligned: Math.max.apply(Math, domainTitles) - Math.min.apply(Math, domainTitles) <= tolerance,
            psychomotorRows: psychomotorTable.tBodies[psychomotorTable.tBodies.length - 1].rows.length,
            domainTablesCompacted: affectiveTable.getAttribute("data-result-domain-compacted") === "true"
              && psychomotorTable.getAttribute("data-result-domain-compacted") === "true",
            performanceWithinContent: performanceRect.left >= contentRect.left - tolerance
              && performanceRect.right <= contentRect.right + tolerance,
            performancePanelCount: parseInt(root.querySelector(".result-report__performance-row").getAttribute("data-result-panel-count"), 10),
            domainColumnWidthRatio: domainColumnRect.width / performanceRect.width,
            remarksVisibleAndContained: window.getComputedStyle(remarksSection).display !== "none"
              && remarksRect.width > 0
              && remarksRect.height > 0
              && remarksRect.left >= rootRect.left - tolerance
              && remarksRect.right <= rootRect.right + tolerance
              && remarksRect.top >= rootRect.top - tolerance
              && remarksRect.bottom <= rootRect.bottom + tolerance,
            remarksBelowPerformanceColumns: remarksRect.top >= Math.max(
              performanceChartRect.bottom,
              affectiveRect.bottom,
              attendanceRect.bottom,
              psychomotorRect.bottom
            ) - tolerance,
            remarksTextPreserved: remarksSection.textContent.indexOf("CLASS TEACHER\\u0027S COMMENT:") !== -1
              && remarksSection.textContent.indexOf("PRINCIPAL/HEAD TEACHER\\u0027S COMMENT:") !== -1,
            signaturesVisible: teacherSignatureRect.width > 0
              && teacherSignatureRect.height > 0
              && principalSignatureRect.width > 0
              && principalSignatureRect.height > 0,
            chartVisible: window.getComputedStyle(performanceChart).display !== "none",
            chartWidthRatio: performanceChartRect.width / performanceRect.width,
            chartHeight: performanceChartRect.height,
            chartCssHeight: parseFloat(window.getComputedStyle(performanceChart).height),
            canvasFillsChart: Math.abs(performanceCanvasRect.width - performanceChartRect.width) <= tolerance
              && Math.abs(performanceCanvasRect.height - performanceChartRect.height) <= tolerance,
            stripedRowsDiffer: firstRowStyle.backgroundColor !== secondRowStyle.backgroundColor,
            stripedRowIsConsistent: secondRowCellsConsistent,
            gradeKeyFontSize: parseFloat(gradeItemStyle.fontSize),
            promotionEmphasized: parseInt(promotionLabelStyle.fontWeight, 10) >= 700
              && parseInt(promotionValueStyle.fontWeight, 10) >= 700
              && parseFloat(promotionValueStyle.fontSize) > parseFloat(promotionLabelStyle.fontSize)
              && promotion !== null,
            legacyHeaderTitleSeparated: legacyTitleRect.top >= legacyHeaderRect.bottom - tolerance,
            watermarkVisibleAndCentered: watermarkStyle.display !== "none"
              && parseFloat(watermarkStyle.opacity) > 0
              && parseFloat(watermarkStyle.opacity) <= 0.1
              && parseInt(watermarkStyle.zIndex, 10) > parseInt(cardBodyStyle.zIndex, 10)
              && watermarkRect.width / contentRect.width >= 0.4
              && watermarkRect.width / contentRect.width <= 0.7
              && Math.abs(watermarkCenterX - watermarkContentCenterX) <= contentRect.width * 0.03
              && Math.abs(watermarkCenterY - watermarkContentCenterY) <= contentRect.height * 0.03,
            previewScale: parseFloat(preview.getAttribute("data-result-preview-scale")),
            previewWidth: preview.clientWidth,
            reportLayoutWidth: root.offsetWidth,
            viewportWidth: document.documentElement.clientWidth,
            reportInsideViewport: rootRect.left >= -tolerance
              && rootRect.right <= document.documentElement.clientWidth + tolerance
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

function result_report_panel_variants_fixture_html($css, $javascript)
{
    $table = function ($kind, $state) {
        $isAttendance = $kind === 'attendance';
        $title = $kind === 'affective'
            ? 'AFFECTIVE DOMAIN'
            : ($isAttendance ? 'ATTENDANCE' : 'PSYCOMOTOR');
        $class = $isAttendance
            ? 'result-report__attendance-table'
            : 'result-report__domain-table';
        $colspan = $isAttendance ? 2 : 4;
        $rows = '';

        if ($state === 'data') {
            $rows = $isAttendance
                ? '<tr><th data-variant-word>TOTAL DAYS</th><td>120</td></tr><tr><th data-variant-word>PRESENT</th><td>116</td></tr>'
                : '<tr><th data-variant-word>Relationship with Mates</th><td>5</td><th data-variant-word>Class Participation</th><td>4</td></tr>';
        } elseif ($state === 'pending') {
            $rows = '<tr class="result-report__availability-row"><td colspan="' . $colspan . '"><div class="alert">No Result Yet</div></td></tr>';
        }

        return '<table class="tab table-sm ' . $class . '" data-result-domain-kind="' . $kind . '">'
            . '<thead><tr><th class="result-report__domain-title' . ($isAttendance ? ' result-report__attendance-title' : '') . '" colspan="' . $colspan . '">' . $title . '</th></tr></thead>'
            . '<tbody>' . $rows . '</tbody></table>';
    };

    $chart = function ($available) {
        return '<div class="result-report__chart-column">'
            . ($available ? '<div class="result-report__chart result-report__chart--performance" data-result-decorative-chart><canvas></canvas></div>' : '')
            . '</div>';
    };

    $grouped = function ($id, $chartAvailable, $affective, $attendance, $psychomotor, $expectedTop, $expectedTables) use ($table, $chart) {
        return '<section class="performance result-report__performance" data-panel-variant="' . $id . '" data-expected-top="' . $expectedTop . '" data-expected-tables="' . $expectedTables . '">'
            . '<div class="row result-report__performance-row">'
            . $chart($chartAvailable)
            . '<div class="result-report__domains-column"><div class="result-report__domain-panel"><div class="result result-report__domain-tables">'
            . $table('affective', $affective)
            . $table('attendance', $attendance)
            . $table('psychomotor', $psychomotor)
            . '</div></div></div></div></section>';
    };

    $separate = function ($id, $chartAvailable, $affective, $psychomotor, $expectedTop) use ($table, $chart) {
        return '<section class="performance result-report__performance" data-panel-variant="' . $id . '" data-expected-top="' . $expectedTop . '">'
            . '<div class="row result-report__performance-row">'
            . $chart($chartAvailable)
            . '<div class="result-report__domain-column"><div class="result-report__domain-panel">' . $table('affective', $affective) . '</div></div>'
            . '<div class="result-report__domain-column"><div class="result-report__domain-panel">' . $table('psychomotor', $psychomotor) . '</div></div>'
            . '</div></section>';
    };

    $combined = function ($id, $state, $expectedTop, $expectedTitles) use ($chart) {
        $dataRows = '<tr><td data-variant-word>Attentiveness</td><td>5</td><td data-variant-word>Cooperation</td><td>4</td><td data-variant-word>Handwriting</td><td>5</td><td data-variant-word>Verbal Fluency</td><td>4</td></tr>';
        return '<section class="performance result-report__performance" data-panel-variant="' . $id . '" data-expected-top="' . $expectedTop . '" data-expected-combined-titles="' . $expectedTitles . '">'
            . '<div class="row result-report__performance-row">'
            . $chart(true)
            . '<div class="result-report__domains-column"><div class="result-report__domain-panel">'
            . '<table class="result-report__domain-table result-report__domain-table--combined" data-result-domain-state="' . $state . '">'
            . '<thead><tr><th class="result-report__domain-title" colspan="4">AFFECTIVE DOMAIN</th><th class="result-report__domain-title" colspan="4">PSYCOMOTOR</th></tr></thead>'
            . '<tbody><tr class="result-report__availability-row"><td colspan="4"><div class="alert">No Result Yet</div></td><td colspan="4"></td></tr>' . $dataRows . '</tbody></table>'
            . '</div></div></div></section>';
    };

    $variants = $grouped('group-all', true, 'data', 'data', 'data', 2, 3)
        . $grouped('group-no-affective', true, 'empty', 'data', 'data', 2, 2)
        . $grouped('group-no-psychomotor', true, 'data', 'data', 'empty', 2, 2)
        . $grouped('group-attendance-only', true, 'empty', 'data', 'empty', 2, 1)
        . $grouped('group-domains-only', true, 'data', 'empty', 'data', 2, 2)
        . $grouped('group-chart-only', true, 'empty', 'empty', 'empty', 1, 0)
        . $grouped('group-no-chart', false, 'data', 'data', 'data', 1, 3)
        . $grouped('group-pending-kept', true, 'pending', 'data', 'empty', 2, 2)
        . $separate('separate-affective', true, 'data', 'empty', 2)
        . $separate('separate-psychomotor', true, 'empty', 'data', 2)
        . $separate('separate-no-chart', false, 'data', 'data', 2)
        . $combined('combined-both', 'both', 2, 2)
        . $combined('combined-affective', 'affective', 2, 1)
        . $combined('combined-psychomotor', 'psychomotor', 2, 1)
        . $combined('combined-none', 'none', 1, 0);

    $javascript = str_ireplace('</script', '<\\/script', $javascript);

    return '<!doctype html><html lang="en"><head><meta charset="utf-8"><style>' . $css . '</style></head><body>'
        . '<div class="result-report-preview" data-result-report-preview><article class="result-report" data-result-report data-academic-row-count="0">'
        . '<div class="result-report__content" data-result-report-content><div class="card-body"><div class="rel">' . $variants . '</div></div></div>'
        . '</article></div><script>' . $javascript . '</script><script>(function(){'
        . 'function visible(element){var rect=element.getBoundingClientRect();return window.getComputedStyle(element).display!=="none"&&rect.width>0&&rect.height>0;}'
        . 'function rectsDoNotOverlap(rects){rects.sort(function(a,b){return a.left-b.left;});return rects.every(function(rect,index){return index===0||rects[index-1].right<=rect.left+1;});}'
        . 'function fills(container,elements){var outer=container.getBoundingClientRect(),rects=elements.map(function(item){return item.getBoundingClientRect();}).sort(function(a,b){return a.left-b.left;});return !rects.length||Math.abs(rects[0].left-outer.left)<=1&&Math.abs(rects[rects.length-1].right-outer.right)<=1;}'
        . 'function wordsWhole(scope){return Array.prototype.every.call(scope.querySelectorAll("[data-variant-word]"),function(cell){if(!visible(cell)){return true;}var walker=document.createTreeWalker(cell,NodeFilter.SHOW_TEXT),node,box=cell.getBoundingClientRect();while((node=walker.nextNode())){var expression=/\\S+/g,match;while((match=expression.exec(node.nodeValue))){var range=document.createRange();range.setStart(node,match.index);range.setEnd(node,match.index+match[0].length);var pieces=range.getClientRects();if(pieces.length!==1||pieces[0].left<box.left-1||pieces[0].right>box.right+1){return false;}}}return cell.scrollWidth<=cell.clientWidth+1;});}'
        . 'function publish(){window.ResultReportPrint.fitAll();requestAnimationFrame(function(){requestAnimationFrame(function(){var variants=Array.prototype.slice.call(document.querySelectorAll("[data-panel-variant]")),result={scenarioCount:variants.length,counts:true,topFill:true,groupFill:true,noOverlap:true,words:true,wordFailures:[],attendanceOnlyFills:true,noChartFills:true,pendingKept:true,combinedStates:true};variants.forEach(function(variant){var row=variant.querySelector(".result-report__performance-row"),top=Array.prototype.filter.call(row.children,function(child){return /result-report__(?:chart|domain|domains)-column/.test(child.className)&&visible(child);}),expectedTop=parseInt(variant.getAttribute("data-expected-top"),10),variantWordsWhole=wordsWhole(variant);result.counts=result.counts&&top.length===expectedTop&&parseInt(row.getAttribute("data-result-panel-count"),10)===expectedTop;result.topFill=result.topFill&&fills(row,top);result.noOverlap=result.noOverlap&&rectsDoNotOverlap(top.map(function(item){return item.getBoundingClientRect();}));result.words=result.words&&variantWordsWhole;if(!variantWordsWhole){result.wordFailures.push(variant.getAttribute("data-panel-variant"));}var group=variant.querySelector(".result-report__domain-tables");if(group){var tables=Array.prototype.filter.call(group.children,visible),expectedTables=parseInt(variant.getAttribute("data-expected-tables"),10);result.counts=result.counts&&tables.length===expectedTables&&parseInt(group.getAttribute("data-result-panel-count"),10)===expectedTables;result.groupFill=result.groupFill&&fills(group,tables);result.noOverlap=result.noOverlap&&rectsDoNotOverlap(tables.map(function(item){return item.getBoundingClientRect();}));if(variant.getAttribute("data-panel-variant")==="group-attendance-only"){result.attendanceOnlyFills=tables.length===1&&tables[0].getBoundingClientRect().width/group.getBoundingClientRect().width>=0.98;}if(variant.getAttribute("data-panel-variant")==="group-pending-kept"){result.pendingKept=tables.length===2&&visible(variant.querySelector("[data-result-domain-kind=affective]"));}}if(variant.getAttribute("data-panel-variant")==="group-no-chart"){result.noChartFills=top.length===1&&top[0].getBoundingClientRect().width/row.getBoundingClientRect().width>=0.98;}var combined=variant.querySelector(".result-report__domain-table--combined");if(combined){var titles=Array.prototype.filter.call(combined.querySelectorAll(".result-report__domain-title"),visible),expectedTitles=parseInt(variant.getAttribute("data-expected-combined-titles"),10),state=combined.getAttribute("data-result-domain-state");result.combinedStates=result.combinedStates&&titles.length===expectedTitles;if(state==="affective"||state==="psychomotor"){result.combinedStates=result.combinedStates&&combined.querySelectorAll("colgroup col").length===4;}}});var output=document.createElement("pre");output.id="result-panel-variants-output";output.textContent=JSON.stringify(result);document.body.appendChild(output);});});}'
        . 'window.ResultReportPrint.initAll(document);publish();}());</script></body></html>';
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

function result_download_source_fixture_html($resultCss)
{
    return '<!doctype html>
<html lang="en">
<head><meta charset="utf-8"><title>Result source</title><style>' . $resultCss . '</style></head>
<body class="result-report-page">
  <div class="result-report-preview" data-result-report-preview>
    <article class="result-report result-report--standard" data-result-report data-result-report-ready="true" data-result-fit-scale="1" style="--result-fit-scale:1;--result-brand:#6d247f;--result-brand-strong:#42154d;--result-brand-soft:#f4eaf7;--result-brand-contrast:#fff">
      <div class="result-report__content" data-result-report-content>
        <div class="card-body"><div class="rel" style="padding:12mm">
          <h1 style="text-align:center">Student Result</h1>
          <p style="text-align:center">One fitted A4 result page</p>
        </div></div>
      </div>
    </article>
  </div>
</body>
</html>';
}

function result_download_list_fixture_html($downloadCss, $listScript)
{
    $listScript = str_ireplace('</script', '<\/script', $listScript);

    return '<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <style>' . $downloadCss . '</style>
</head>
<body>
  <div class="result-download-list" data-result-download-list>
    <div class="result-download-toolbar" data-result-download-toolbar>
      <div class="result-download-toolbar__summary"><i class="fa fa-download"></i><span>Result downloads</span><small data-result-selected-count>0 selected</small></div>
      <div class="result-download-toolbar__actions">
        <button type="button" data-result-download-selected disabled>Download selected</button>
        <button type="button" data-result-download-all>Download all</button>
      </div>
      <div class="dropdown result-download-toolbar__dropdown">
        <button type="button">Download</button>
        <div class="dropdown-menu">
          <button type="button" data-result-download-selected disabled>Download selected</button>
          <button type="button" data-result-download-all>Download all</button>
        </div>
      </div>
      <div class="result-download-toolbar__message" data-result-download-message></div>
    </div>
    <div style="overflow-x:auto">
      <table class="result-download-table"><thead><tr><th class="result-download-table__select"><input type="checkbox" data-result-select-all></th><th>Name</th><th class="result-download-table__action">Action</th></tr></thead>
        <tbody>
          <tr data-result-row data-result-student-id="1"><td><input type="checkbox" data-result-select></td><td>Student One</td><td><div class="result-download-row-actions"><a href="resultPage.php?classsection=1&amp;classsectionactual=1&amp;classid=1&amp;session=1&amp;term=3rd&amp;id=1&amp;reltype=cummulative">View</a><a href="resultPage.php?classsection=1&amp;classsectionactual=1&amp;classid=1&amp;session=1&amp;term=3rd&amp;id=1&amp;reltype=cummulative" data-result-download-one data-result-filename="Student One Result.pdf" data-result-archive-filename="Class Results.zip" class="result-download-row-actions__download">↓</a></div></td></tr>
          <tr data-result-row data-result-student-id="2"><td><input type="checkbox" data-result-select></td><td>Student Two</td><td><div class="result-download-row-actions"><a href="resultPage.php?classsection=1&amp;classsectionactual=1&amp;classid=1&amp;session=1&amp;term=3rd&amp;id=2&amp;reltype=cummulative">View</a><a href="resultPage.php?classsection=1&amp;classsectionactual=1&amp;classid=1&amp;session=1&amp;term=3rd&amp;id=2&amp;reltype=cummulative" data-result-download-one data-result-filename="Student Two Result.pdf" data-result-archive-filename="Class Results.zip" class="result-download-row-actions__download">↓</a></div></td></tr>
          <tr data-result-row data-result-student-id="3"><td><input type="checkbox" data-result-select></td><td>Student Three</td><td><div class="result-download-row-actions"><a href="resultPage.php?classsection=1&amp;classsectionactual=1&amp;classid=1&amp;session=1&amp;term=3rd&amp;id=3&amp;reltype=cummulative">View</a><a href="resultPage.php?classsection=1&amp;classsectionactual=1&amp;classid=1&amp;session=1&amp;term=3rd&amp;id=3&amp;reltype=cummulative" data-result-download-one data-result-filename="Student Three Result.pdf" data-result-archive-filename="Class Results.zip" class="result-download-row-actions__download">↓</a></div></td></tr>
        </tbody>
      </table>
    </div>
  </div>
  <script>' . $listScript . '</script>
  <script>
  (function(){
    var list=document.querySelector("[data-result-download-list]");
    var checks=list.querySelectorAll("[data-result-select]");
    checks[0].checked=true;
    checks[1].checked=true;
    window.ResultDownloadList.refresh(document);
    setTimeout(function(){
      var toolbar=list.querySelector("[data-result-download-toolbar]");
      var actions=list.querySelector(".result-download-toolbar__actions");
      var dropdown=list.querySelector(".result-download-toolbar__dropdown");
      var icon=list.querySelector(".result-download-row-actions__download");
      var rect=toolbar.getBoundingClientRect();
      var iconRect=icon.getBoundingClientRect();
      var payload={
        viewportWidth:window.innerWidth,
        selectedCount:list.querySelector("[data-result-selected-count]").textContent,
        selectedEnabled:Array.prototype.every.call(list.querySelectorAll("[data-result-download-selected]"),function(button){return !button.disabled;}),
        inlineActionsVisible:getComputedStyle(actions).display!=="none",
        dropdownVisible:getComputedStyle(dropdown).display!=="none",
        toolbarContained:rect.left>=0&&rect.right<=window.innerWidth+1,
        compactIcon:Math.abs(iconRect.width-31)<=1&&Math.abs(iconRect.height-31)<=1
      };
      var output=document.createElement("pre");
      output.id="result-download-list-output";
      output.textContent=JSON.stringify(payload);
      document.body.appendChild(output);
    },50);
  }());
  </script>
</body>
</html>';
}

function result_download_direct_fixture_html($downloadCss, $listScript, $jsPdfUrl, $jsZipUrl)
{
    $listScript = str_ireplace('</script', '<\/script', $listScript);
    $jsPdfUrl = htmlspecialchars($jsPdfUrl, ENT_QUOTES, 'UTF-8');
    $jsZipUrl = htmlspecialchars($jsZipUrl, ENT_QUOTES, 'UTF-8');

    return '<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <style>' . $downloadCss . '</style>
</head>
<body>
  <div class="result-download-list" data-result-download-list>
    <div class="result-download-toolbar" data-result-download-toolbar>
      <div class="result-download-toolbar__summary"><i class="fa fa-download"></i><span>Result downloads</span><small data-result-selected-count>0 selected</small></div>
      <div class="result-download-toolbar__actions"><button type="button" data-result-download-selected disabled>Download selected</button><button type="button" data-result-download-all>Download all</button></div>
      <div class="dropdown result-download-toolbar__dropdown"><button type="button">Download</button><div class="dropdown-menu"><button type="button" data-result-download-selected disabled>Download selected</button><button type="button" data-result-download-all>Download all</button></div></div>
      <div class="result-download-toolbar__message" data-result-download-message></div>
    </div>
    <table class="result-download-table"><tbody>
      <tr data-result-row data-result-student-id="11"><td><input type="checkbox" data-result-select></td><td>Student One</td><td><a href="resultPage.php/index.html?classsection=1&amp;classsectionactual=1&amp;classid=1&amp;session=1&amp;term=3rd&amp;id=11&amp;reltype=cummulative" data-result-download-one data-result-filename="Student One Result.pdf" data-result-archive-filename="Primary One Results.zip">Download</a></td></tr>
      <tr data-result-row data-result-student-id="12"><td><input type="checkbox" data-result-select></td><td>Student Two</td><td><a href="resultPage.php/index.html?classsection=1&amp;classsectionactual=1&amp;classid=1&amp;session=1&amp;term=3rd&amp;id=12&amp;reltype=cummulative" data-result-download-one data-result-filename="Student Two Result.pdf" data-result-archive-filename="Primary One Results.zip">Download</a></td></tr>
    </tbody></table>
  </div>
  <script src="' . $jsPdfUrl . '"></script>
  <script src="' . $jsZipUrl . '"></script>
  <script>
    window.__resultRenderCalls=0;
    window.__resultPrintCalls=0;
    window.__resultOpenCalls=0;
    window.print=function(){window.__resultPrintCalls+=1;};
    window.open=function(){window.__resultOpenCalls+=1;return null;};
    HTMLAnchorElement.prototype.click=function(){};
    window.html2canvas=function(){
      window.__resultRenderCalls+=1;
      var canvas=document.createElement("canvas");
      canvas.width=794;canvas.height=1123;
      var context=canvas.getContext("2d");
      context.fillStyle="#fff";context.fillRect(0,0,canvas.width,canvas.height);
      context.fillStyle="#6d247f";context.fillRect(30,30,734,90);
      context.fillStyle="#111";context.font="30px Arial";context.fillText("Student Result",280,200);
      return Promise.resolve(canvas);
    };
  </script>
  <script>' . $listScript . '</script>
  <script>
  (function(){
    var list=document.querySelector("[data-result-download-list]");
    var checks=list.querySelectorAll("[data-result-select]");
    var payload={zip:null,pdf:null};
    var finished=false;

    function bytesToString(buffer){
      var bytes=new Uint8Array(buffer),text="",offset=0,chunk=8192;
      while(offset<bytes.length){text+=String.fromCharCode.apply(null,bytes.subarray(offset,Math.min(bytes.length,offset+chunk)));offset+=chunk;}
      return text;
    }
    function pdfDetails(buffer){
      var text=bytesToString(buffer),pages=(text.match(/\/Type\s*\/Page\b/g)||[]).length;
      var media=text.match(/\/MediaBox\s*\[\s*0\s+0\s+([0-9.]+)\s+([0-9.]+)/);
      return {starts:text.slice(0,5)==="%PDF-",pages:pages,a4:!!media&&Math.abs(parseFloat(media[1])-595.28)<2&&Math.abs(parseFloat(media[2])-841.89)<2};
    }
    function publish(){
      if(finished||!payload.zip||!payload.pdf){return;}
      finished=true;
      payload.renderCalls=window.__resultRenderCalls;
      payload.printCalls=window.__resultPrintCalls;
      payload.openCalls=window.__resultOpenCalls;
      payload.captureFrames=document.querySelectorAll(".result-download-capture-frame").length;
      var output=document.createElement("pre");output.id="result-download-direct-output";output.textContent=JSON.stringify(payload);document.body.appendChild(output);
    }
    document.addEventListener("resultdownloadready",function(event){
      var detail=event.detail||{};
      if(detail.blob&&detail.blob.type==="application/zip"){
        window.JSZip.loadAsync(detail.blob).then(function(zip){
          var names=Object.keys(zip.files).filter(function(name){return !zip.files[name].dir;});
          var pdfNames=names.filter(function(name){return /\.pdf$/i.test(name);});
          return Promise.all(pdfNames.map(function(name){return zip.file(name).async("arraybuffer").then(pdfDetails);})).then(function(details){
            payload.zip={name:detail.filename,type:detail.blob.type,entries:names,pdfCount:pdfNames.length,allPdf:details.every(function(item){return item.starts&&item.pages===1&&item.a4;})};
            window.setTimeout(function(){list.querySelector("[data-result-download-one]").dispatchEvent(new MouseEvent("click",{bubbles:true,cancelable:true}));},80);
          });
        });
      }else if(detail.blob&&detail.blob.type==="application/pdf"){
        detail.blob.arrayBuffer().then(function(buffer){payload.pdf={name:detail.filename,type:detail.blob.type,details:pdfDetails(buffer)};publish();});
      }
    });
    checks[0].checked=true;checks[1].checked=true;
    window.ResultDownloadList.refresh(document);
    list.querySelector(".result-download-toolbar__actions [data-result-download-selected]").click();
    window.setTimeout(function(){if(!finished){var output=document.createElement("pre");output.id="result-download-direct-output";output.textContent=JSON.stringify({error:list.querySelector("[data-result-download-message]").textContent,renderCalls:window.__resultRenderCalls});document.body.appendChild(output);}},12000);
  }());
  </script>
</body>
</html>';
}

function result_download_parse_payload($html, $elementId)
{
    if (!preg_match('#<pre id="' . preg_quote($elementId, '#') . '">(.*?)</pre>#s', $html, $matches)) {
        return null;
    }

    $payload = json_decode(html_entity_decode($matches[1], ENT_QUOTES | ENT_HTML5, 'UTF-8'), true);
    return is_array($payload) ? $payload : null;
}

$browser = result_report_a4_find_browser();
if ($browser === null) {
    echo 'SKIP: result report A4 browser test (no compatible Chrome, Chromium, or Edge executable found).' . PHP_EOL;
    exit(0);
}

$repositoryRoot = dirname(__DIR__);
$cssPath = $repositoryRoot . '/assets/css/result-report.css';
$javascriptPath = $repositoryRoot . '/assets/js/result-report-print.js';
$downloadCssPath = $repositoryRoot . '/assets/css/exam-result-download.css';
$listJavascriptPath = $repositoryRoot . '/assets/js/exam-result-download.js';
$jsPdfPath = $repositoryRoot . '/backend/idcard-studio/vendor/jspdf-2.5.2.umd.min.js';
$jsZipPath = $repositoryRoot . '/backend/dist/datatables/js/jszip.min.js';
$css = file_get_contents($cssPath);
$javascript = file_get_contents($javascriptPath);
$downloadCss = file_get_contents($downloadCssPath);
$listJavascript = file_get_contents($listJavascriptPath);

result_report_a4_assert($css !== false, 'The shared result report stylesheet must be readable.');
result_report_a4_assert($javascript !== false, 'The shared result report print script must be readable.');
result_report_a4_assert($downloadCss !== false, 'The result download stylesheet must be readable.');
result_report_a4_assert($listJavascript !== false, 'The result-list download script must be readable.');
result_report_a4_assert(is_file($jsPdfPath), 'The local jsPDF library must be available.');
result_report_a4_assert(is_file($jsZipPath), 'The local JSZip library must be available.');

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
    if (!is_array($payload)) {
        /* Windows Chrome can occasionally return before its first isolated
         * headless profile has executed inline JavaScript. Retry that first
         * render once; a persistent rendering failure still fails below. */
        $domResult = result_report_a4_run_process(
            array_merge($commonArguments, array('--dump-dom', $fixtureUrl)),
            45
        );
        $payload = result_report_a4_parse_payload($domResult['stdout']);
    }
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
    result_report_a4_assert($payload['contentHorizontallyCentered'] === true, 'The ' . $rowCount . '-row fitted result must retain balanced left and right A4 margins.');
    result_report_a4_assert($payload['statisticsInsideStudentInfo'] === true, 'The ' . $rowCount . '-row result statistics must stay inside the legacy student-information box.');
    result_report_a4_assert($payload['statisticsUnboxed'] === true, 'The ' . $rowCount . '-row result statistics must not render as bordered table cells.');
    result_report_a4_assert($payload['statisticsMatchStudentText'] === true, 'The ' . $rowCount . '-row result statistics must inherit the surrounding student-information typography.');
    result_report_a4_assert($payload['gradeKeyDirectlyAfterTable'] === true, 'The ' . $rowCount . '-row key to grades must appear directly below the academic table.');
    result_report_a4_assert($payload['academicTableContract'] === true, 'The ' . $rowCount . '-row academic table must use a real header and the striped-table contract.');
    result_report_a4_assert($payload['subjectUsesBodyStyling'] === true, 'The ' . $rowCount . '-row first subject must not use the dark table-header styling.');
    result_report_a4_assert($payload['subjectColumnWidthRatio'] >= 0.19, 'The ' . $rowCount . '-row subject column must reserve enough width to contain complete words.');
    result_report_a4_assert($payload['subjectWordsRemainWhole'] === true, 'The ' . $rowCount . '-row subject names must wrap only between complete words.');
    result_report_a4_assert($payload['remarkColumnWidthRatio'] >= 0.115, 'The ' . $rowCount . '-row remark column must reserve enough width for complete words.');
    result_report_a4_assert($payload['remarkWordsRemainWhole'] === true, 'The ' . $rowCount . '-row remarks must wrap only between complete words.');
    result_report_a4_assert(
        $payload['domainWordsRemainWhole'] === true,
        'The ' . $rowCount . '-row affective and psychomotor labels must not split inside words. Measurements: ' . json_encode($payload)
    );
    result_report_a4_assert($payload['attendanceWordsRemainWhole'] === true, 'The ' . $rowCount . '-row Attendance labels must wrap only between complete words.');
    result_report_a4_assert(
        $payload['attendanceLabelWidthRatio'] >= 0.68 && $payload['attendanceLabelWidthRatio'] <= 0.76,
        'The ' . $rowCount . '-row Attendance label/value columns must retain a readable 72/28 split.'
    );
    result_report_a4_assert(
        $payload['attendanceTableWidthRatio'] >= 0.18 && $payload['attendanceTableWidthRatio'] <= 0.22,
        'The ' . $rowCount . '-row Attendance table must receive its balanced share when all three tables exist.'
    );
    result_report_a4_assert($payload['attendanceInsideGroup'] === true, 'The ' . $rowCount . '-row Attendance table must remain inside its domain group.');
    result_report_a4_assert($payload['allDomainTablesAvailable'] === true, 'The ' . $rowCount . '-row complete fixture must retain all three available performance tables.');
    result_report_a4_assert($payload['domainTablesFillGroup'] === true, 'The ' . $rowCount . '-row performance tables must fill their complete available width.');
    result_report_a4_assert($payload['domainTablesDoNotOverlap'] === true, 'The ' . $rowCount . '-row performance tables must not overlap.');
    result_report_a4_assert($payload['domainTitlesAligned'] === true, 'The ' . $rowCount . '-row Affective, Attendance, and Psychomotor headings must align along the top.');
    result_report_a4_assert($payload['psychomotorRows'] === 3, 'The ' . $rowCount . '-row fixture must pair six psychomotor entries into three compact rows.');
    result_report_a4_assert($payload['domainTablesCompacted'] === true, 'The ' . $rowCount . '-row domain tables must use the shared compaction pass.');
    result_report_a4_assert($payload['performanceWithinContent'] === true, 'The ' . $rowCount . '-row performance panel must stay within the A4 content box.');
    result_report_a4_assert(
        $payload['remarksVisibleAndContained'] === true,
        'The ' . $rowCount . '-row teacher/principal remarks must remain visible inside the A4 page. Measurements: ' . json_encode($payload)
    );
    result_report_a4_assert($payload['remarksBelowPerformanceColumns'] === true, 'The ' . $rowCount . '-row remarks and signatures must remain below the chart and domain tables.');
    result_report_a4_assert($payload['remarksTextPreserved'] === true, 'The ' . $rowCount . '-row result must retain both teacher and principal comment labels.');
    result_report_a4_assert($payload['signaturesVisible'] === true, 'The ' . $rowCount . '-row result must retain visible teacher and principal signature areas.');
    if ($fixture['density'] === 'ultra') {
        result_report_a4_assert($payload['chartVisible'] === false, 'The ultra-dense fixture may remove the decorative graph to protect one-page A4 output.');
        result_report_a4_assert($payload['performancePanelCount'] === 1, 'The ultra-dense fixture must remove the entire graph column, not leave a blank gap.');
        result_report_a4_assert($payload['domainColumnWidthRatio'] >= 0.98, 'The ultra-dense performance tables must reclaim the graph column width.');
    } else {
        $minimumChartHeight = $fixture['density'] === 'standard' ? 150 : 115;
        result_report_a4_assert($payload['chartVisible'] === true, 'The ' . $rowCount . '-row graph must remain visible.');
        result_report_a4_assert($payload['chartWidthRatio'] >= 0.32, 'The ' . $rowCount . '-row graph must receive a useful share of the performance row.');
        result_report_a4_assert($payload['chartCssHeight'] >= $minimumChartHeight, 'The ' . $rowCount . '-row graph must have a useful rendered height.');
        result_report_a4_assert($payload['canvasFillsChart'] === true, 'The ' . $rowCount . '-row graph canvas must fill its wrapper.');
        result_report_a4_assert($payload['performancePanelCount'] === 2, 'The ' . $rowCount . '-row result must retain the graph and grouped performance-table panels.');
        result_report_a4_assert($payload['domainColumnWidthRatio'] >= 0.62, 'The ' . $rowCount . '-row grouped tables must retain their familiar share beside the graph.');
    }
    result_report_a4_assert($payload['stripedRowsDiffer'] === true, 'The ' . $rowCount . '-row academic table must visibly alternate row colours.');
    result_report_a4_assert($payload['stripedRowIsConsistent'] === true, 'The ' . $rowCount . '-row stripe must cover every cell in the row.');
    $minimumGradeKeyFontSize = $fixture['density'] === 'standard'
        ? 10
        : ($fixture['density'] === 'compact' ? 9 : 7);
    result_report_a4_assert(
        $payload['gradeKeyFontSize'] >= $minimumGradeKeyFontSize,
        'The ' . $rowCount . '-row key-to-grades text must remain readable at its adaptive A4 density.'
    );
    result_report_a4_assert($payload['promotionEmphasized'] === true, 'The ' . $rowCount . '-row promotion status must use a bold, larger decision value.');
    result_report_a4_assert($payload['legacyHeaderTitleSeparated'] === true, 'The ' . $rowCount . '-row title must not overlap a long legacy school header.');
    result_report_a4_assert($payload['watermarkVisibleAndCentered'] === true, 'The ' . $rowCount . '-row watermark must remain large, faint, centred, and visible over the result paper.');
    result_report_a4_assert($payload['reportInsideViewport'] === true, 'The ' . $rowCount . '-row A4 preview must stay inside the desktop viewport.');

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

$panelVariantsPath = $temporaryDirectory . DIRECTORY_SEPARATOR . 'result-panel-variants.html';
result_report_a4_assert(
    file_put_contents(
        $panelVariantsPath,
        result_report_panel_variants_fixture_html($css, $javascript)
    ) !== false,
    'The adaptive performance-panel fixture must be writable.'
);
$panelVariantsUrl = result_report_a4_file_url($panelVariantsPath, $browser['windows']);
$panelVariantsArguments = array(
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
    '--virtual-time-budget=5000',
    '--dump-dom',
    $panelVariantsUrl,
);
$panelVariantsResult = result_report_a4_run_process($panelVariantsArguments, 45);
$panelVariantsPayload = result_download_parse_payload(
    $panelVariantsResult['stdout'],
    'result-panel-variants-output'
);
if (!is_array($panelVariantsPayload)) {
    $panelVariantsResult = result_report_a4_run_process($panelVariantsArguments, 45);
    $panelVariantsPayload = result_download_parse_payload(
        $panelVariantsResult['stdout'],
        'result-panel-variants-output'
    );
}
result_report_a4_assert(
    $panelVariantsResult['exit_code'] === 0 && is_array($panelVariantsPayload),
    'Chromium must render all adaptive performance-panel combinations. Browser output tail: '
        . substr(trim($panelVariantsResult['stdout']), -1200)
);
result_report_a4_assert($panelVariantsPayload['scenarioCount'] === 15, 'All 15 performance-panel availability combinations must be exercised.');
foreach (array(
    'counts' => 'Available and unavailable panels must be detected exactly.',
    'topFill' => 'Available top-level panels must fill the complete performance row.',
    'groupFill' => 'Available Affective, Attendance, and Psychomotor tables must fill their group.',
    'noOverlap' => 'Adaptive performance panels and tables must never overlap.',
    'words' => 'Performance-table labels must wrap only at word boundaries.',
    'attendanceOnlyFills' => 'Attendance must expand to the full table area when it is the only available table.',
    'noChartFills' => 'Available tables must reclaim the complete row when the graph is unavailable.',
    'pendingKept' => 'Configured panels with no score must retain a compact pending-data message.',
    'combinedStates' => 'A single available cumulative domain must reclaim the unavailable half.',
) as $measurement => $message) {
    result_report_a4_assert(
        $panelVariantsPayload[$measurement] === true,
        $message . ($measurement === 'words' ? ' Measurements: ' . json_encode($panelVariantsPayload) : '')
    );
}

echo 'PASS: adaptive performance-panel availability matrix.' . PHP_EOL;

$responsiveResultViewports = array(
    array('name' => 'mobile', 'size' => '390,900', 'scaled' => true),
    array('name' => 'tablet', 'size' => '768,1100', 'scaled' => true),
    array('name' => 'large desktop', 'size' => '1600,1400', 'scaled' => false),
);
$responsiveFixturePath = $temporaryDirectory . DIRECTORY_SEPARATOR . 'result-12.html';
$responsiveFixtureUrl = result_report_a4_file_url($responsiveFixturePath, $browser['windows']);

foreach ($responsiveResultViewports as $responsiveViewport) {
    $responsiveArguments = array(
        $browser['path'],
        '--headless=new',
        '--disable-gpu',
        '--disable-dev-shm-usage',
        '--no-sandbox',
        '--no-first-run',
        '--no-default-browser-check',
        '--allow-file-access-from-files',
        '--run-all-compositor-stages-before-draw',
        '--window-size=' . $responsiveViewport['size'],
        '--user-data-dir=' . result_report_a4_to_browser_path($profileDirectory, $browser['windows']),
        '--virtual-time-budget=4000',
        '--dump-dom',
        $responsiveFixtureUrl,
    );
    $responsiveResult = result_report_a4_run_process($responsiveArguments, 45);
    $responsivePayload = result_report_a4_parse_payload($responsiveResult['stdout']);

    result_report_a4_assert(
        $responsiveResult['exit_code'] === 0 && is_array($responsivePayload),
        'Chromium must render the result at the ' . $responsiveViewport['name'] . ' viewport. ' . trim($responsiveResult['stderr'])
    );
    result_report_a4_assert(
        $responsivePayload['viewportContained'] === true && $responsivePayload['reportInsideViewport'] === true,
        'The A4 result preview must stay horizontally contained at the ' . $responsiveViewport['name'] . ' viewport.'
    );
    $expectedResponsiveScale = min(
        1,
        min($responsivePayload['previewWidth'], $responsivePayload['viewportWidth'] - 16)
            / $responsivePayload['reportLayoutWidth']
    );
    result_report_a4_assert(
        abs($responsivePayload['previewScale'] - $expectedResponsiveScale) <= 0.01,
        'The A4 result preview must use the available ' . $responsiveViewport['name'] . ' width proportionally.'
    );
    result_report_a4_assert(
        $responsiveViewport['scaled']
            ? $responsivePayload['previewScale'] < 1
            : abs($responsivePayload['previewScale'] - 1) <= 0.001,
        'The A4 result preview must use the expected fitted state at the ' . $responsiveViewport['name'] . ' viewport.'
    );
}

echo 'PASS: responsive A4 result preview.' . PHP_EOL;

$listHtmlPath = $temporaryDirectory . DIRECTORY_SEPARATOR . 'result-download-list.html';
result_report_a4_assert(
    file_put_contents(
        $listHtmlPath,
        result_download_list_fixture_html($downloadCss, $listJavascript)
    ) !== false,
    'The responsive result-download list fixture must be writable.'
);
$listUrl = result_report_a4_file_url($listHtmlPath, $browser['windows']);

foreach (array(
    array('name' => 'desktop', 'width' => 1024, 'height' => 900, 'inline' => true, 'dropdown' => false),
    array('name' => 'mobile', 'width' => 390, 'height' => 844, 'inline' => false, 'dropdown' => true),
) as $listViewport) {
    $listProfile = $temporaryDirectory . DIRECTORY_SEPARATOR . 'list-profile-' . $listViewport['name'];
    if (!mkdir($listProfile, 0700, true) && !is_dir($listProfile)) {
        result_report_a4_fail('Unable to create the result-download list browser profile.');
    }
    $listResult = result_report_a4_run_process(
        array(
            $browser['path'],
            '--headless=new',
            '--disable-gpu',
            '--disable-dev-shm-usage',
            '--no-sandbox',
            '--no-first-run',
            '--no-default-browser-check',
            '--allow-file-access-from-files',
            '--run-all-compositor-stages-before-draw',
            '--window-size=' . $listViewport['width'] . ',' . $listViewport['height'],
            '--user-data-dir=' . result_report_a4_to_browser_path($listProfile, $browser['windows']),
            '--virtual-time-budget=1500',
            '--dump-dom',
            $listUrl,
        ),
        30
    );
    result_report_a4_assert(
        $listResult['exit_code'] === 0,
        'Chromium must render the ' . $listViewport['name'] . ' result-download controls.'
    );
    $listPayload = result_download_parse_payload($listResult['stdout'], 'result-download-list-output');
    result_report_a4_assert(is_array($listPayload), 'The ' . $listViewport['name'] . ' result-download fixture must publish measurements.');
    result_report_a4_assert($listPayload['selectedCount'] === '2 selected', 'The selected-result count must include exactly the two checked results on ' . $listViewport['name'] . '.');
    result_report_a4_assert($listPayload['selectedEnabled'] === true, 'Download selected must be enabled for the two checked results on ' . $listViewport['name'] . '.');
    result_report_a4_assert($listPayload['inlineActionsVisible'] === $listViewport['inline'], 'The inline download buttons must have the expected ' . $listViewport['name'] . ' visibility.');
    result_report_a4_assert($listPayload['dropdownVisible'] === $listViewport['dropdown'], 'The compact download dropdown must have the expected ' . $listViewport['name'] . ' visibility.');
    result_report_a4_assert($listPayload['toolbarContained'] === true, 'The download toolbar must remain inside the ' . $listViewport['name'] . ' viewport.');
    result_report_a4_assert($listPayload['compactIcon'] === true, 'The per-row download icon must remain compact on ' . $listViewport['name'] . '.');
}

echo 'PASS: responsive result-download controls.' . PHP_EOL;

$directSourceDirectory = $temporaryDirectory . DIRECTORY_SEPARATOR . 'resultPage.php';
$directSourcePath = $directSourceDirectory . DIRECTORY_SEPARATOR . 'index.html';
$directHtmlPath = $temporaryDirectory . DIRECTORY_SEPARATOR . 'result-download-direct.html';
$directProfile = $temporaryDirectory . DIRECTORY_SEPARATOR . 'direct-download-profile';
if (!mkdir($directSourceDirectory, 0700, true) && !is_dir($directSourceDirectory)) {
    result_report_a4_fail('Unable to create the direct-download source directory.');
}
if (!mkdir($directProfile, 0700, true) && !is_dir($directProfile)) {
    result_report_a4_fail('Unable to create the direct-download browser profile.');
}

result_report_a4_assert(
    file_put_contents($directSourcePath, result_download_source_fixture_html($css)) !== false,
    'The direct-download source fixture must be writable.'
);
result_report_a4_assert(
    file_put_contents(
        $directHtmlPath,
        result_download_direct_fixture_html(
            $downloadCss,
            $listJavascript,
            result_report_a4_file_url($jsPdfPath, $browser['windows']),
            result_report_a4_file_url($jsZipPath, $browser['windows'])
        )
    ) !== false,
    'The direct PDF/ZIP download fixture must be writable.'
);

$directArguments = array(
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
    '--user-data-dir=' . result_report_a4_to_browser_path($directProfile, $browser['windows']),
    '--virtual-time-budget=15000',
    '--dump-dom',
    result_report_a4_file_url($directHtmlPath, $browser['windows']),
);
$directResult = result_report_a4_run_process($directArguments, 60);

result_report_a4_assert(
    $directResult['exit_code'] === 0,
    'Chromium must complete the direct PDF/ZIP fixture. ' . trim($directResult['stderr'])
);
$directPayload = result_download_parse_payload($directResult['stdout'], 'result-download-direct-output');
result_report_a4_assert(
    is_array($directPayload) && !isset($directPayload['error']),
    'The direct PDF/ZIP fixture must publish successful download details. Browser output tail: '
        . substr(trim($directResult['stdout']), -1800)
);
result_report_a4_assert(
    $directPayload['zip']['type'] === 'application/zip'
        && substr($directPayload['zip']['name'], -4) === '.zip'
        && $directPayload['zip']['pdfCount'] === 2,
    'Two selected results must download directly as one ZIP containing two PDFs.'
);
result_report_a4_assert(
    $directPayload['zip']['allPdf'] === true,
    'Every ZIP entry must be a valid, single-page A4 PDF.'
);
result_report_a4_assert(
    $directPayload['pdf']['type'] === 'application/pdf'
        && substr($directPayload['pdf']['name'], -4) === '.pdf'
        && $directPayload['pdf']['details']['starts'] === true
        && $directPayload['pdf']['details']['pages'] === 1
        && $directPayload['pdf']['details']['a4'] === true,
    'A row action must directly download one valid, single-page A4 PDF.'
);
result_report_a4_assert(
    $directPayload['renderCalls'] === 3
        && $directPayload['captureFrames'] === 0,
    'The two ZIP PDFs and one row PDF must render sequentially and clean up their hidden frames.'
);
result_report_a4_assert(
    $directPayload['printCalls'] === 0 && $directPayload['openCalls'] === 0,
    'Direct result downloads must not open another page or invoke browser printing.'
);

echo 'PASS: direct PDF and selected-results ZIP fixture.' . PHP_EOL;

echo 'Result report A4 browser tests passed (' . $assertions . ' assertions) using '
    . $browser['version']
    . '.'
    . PHP_EOL;
