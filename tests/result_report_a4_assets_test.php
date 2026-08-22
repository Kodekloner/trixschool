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
            <thead><tr><th>Subject</th><th>CA 1</th><th>CA 2</th><th>Exam</th><th>Total</th><th>1st</th><th>2nd</th><th>Cumulative Average</th><th>Grade</th><th class="result-report__cell--comment">Remark</th></tr></thead>
            <tbody>' . $rows . '</tbody>
          </table>
        </div>
        <section class="result-report__summary result-report__summary--grade-key" data-result-summary-section="grade-key" aria-label="Key to grades">
          <div class="result-report__panel"><p class="result-report__panel-title">Key to Grades</p><div class="result-report__panel-body"><ul class="result-report__grade-key"><li class="result-report__grade-item"><b class="result-report__grade-symbol">A:</b> 70% and above</li><li class="result-report__grade-item"><b class="result-report__grade-symbol">B:</b> 60%–69.9%</li><li class="result-report__grade-item"><b class="result-report__grade-symbol">C:</b> 50%–59.9%</li><li class="result-report__grade-item"><b class="result-report__grade-symbol">D:</b> 45%–49.9%</li><li class="result-report__grade-item"><b class="result-report__grade-symbol">E:</b> 40%–44.9%</li><li class="result-report__grade-item"><b class="result-report__grade-symbol">F:</b> 0%–39.9%</li></ul></div></div>
        </section>
        <section class="result-report__chart result-report__chart--decorative" data-result-decorative-chart><svg viewBox="0 0 100 16" role="img"><rect width="100" height="16" fill="#eceff3"></rect><path d="M0 14 L20 8 L40 10 L60 3 L80 7 L100 1" fill="none" stroke="#6d247f"></path></svg></section>
        <section class="result-report__comments"><div class="result-report__comment"><strong class="result-report__label">Teacher&apos;s Comment</strong><p class="result-report__comment-text">A focused learner who has made steady progress throughout the academic year.</p></div><div class="result-report__comment"><strong class="result-report__label">Head Teacher&apos;s Comment</strong><p class="result-report__comment-text">An excellent result. Continue the good work.</p></div></section>
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
            stripedRowsDiffer: firstRowStyle.backgroundColor !== secondRowStyle.backgroundColor,
            stripedRowIsConsistent: secondRowCellsConsistent,
            gradeKeyFontSize: parseFloat(gradeItemStyle.fontSize),
            promotionEmphasized: parseInt(promotionLabelStyle.fontWeight, 10) >= 700
              && parseInt(promotionValueStyle.fontWeight, 10) >= 700
              && parseFloat(promotionValueStyle.fontSize) > parseFloat(promotionLabelStyle.fontSize)
              && promotion !== null,
            legacyHeaderTitleSeparated: legacyTitleRect.top >= legacyHeaderRect.bottom - tolerance,
            watermarkBehindContent: watermarkStyle.display !== "none"
              && parseFloat(watermarkStyle.opacity) > 0
              && parseInt(watermarkStyle.zIndex, 10) < parseInt(cardBodyStyle.zIndex, 10)
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
    result_report_a4_assert($payload['watermarkBehindContent'] === true, 'The ' . $rowCount . '-row watermark must remain faintly visible behind the legacy result content.');

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
