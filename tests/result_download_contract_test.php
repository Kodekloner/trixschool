<?php

/** Contract coverage for direct individual PDF and selected/all ZIP downloads. */

$assertions = 0;

function result_download_assert($condition, $message)
{
    global $assertions;
    $assertions++;

    if (!$condition) {
        fwrite(STDERR, 'FAIL: ' . $message . PHP_EOL);
        exit(1);
    }
}

$root = dirname(__DIR__);
$page = file_get_contents($root . '/admin/examResult.php');
$list = file_get_contents($root . '/phpscript/view-studentsresulttbl.php');
$imageProxy = file_get_contents($root . '/admin/resultImageProxy.php');
$imageProxyHelper = file_get_contents($root . '/helper/resultdownload_helper.php');
$script = file_get_contents($root . '/assets/js/exam-result-download.js');
$styles = file_get_contents($root . '/assets/css/exam-result-download.css');

foreach (array(
    'exam result page' => $page,
    'student result list' => $list,
    'result image proxy' => $imageProxy,
    'result image proxy helper' => $imageProxyHelper,
    'direct download script' => $script,
    'download stylesheet' => $styles,
) as $name => $source) {
    result_download_assert($source !== false, ucfirst($name) . ' must be readable.');
}

result_download_assert(
    !file_exists($root . '/admin/resultBulkDownload.php')
        && !file_exists($root . '/assets/js/result-bulk-download.js'),
    'The obsolete navigation-based bulk download page and script must be removed.'
);
result_download_assert(
    strpos($page, 'resultBulkDownload.php') === false
        && strpos($script, 'resultBulkDownload.php') === false
        && strpos($script, "form.target = '_blank'") === false
        && strpos($script, 'window.print()') === false,
    'Downloads must not navigate to another page or open a print dialog.'
);

$html2canvasPosition = strpos($page, 'html2canvas/1.4.1/html2canvas.min.js');
$jsPdfPosition = strpos($page, 'jspdf-2.5.2.umd.min.js');
$jsZipPosition = strpos($page, 'jszip.min.js');
$downloadScriptPosition = strpos($page, 'exam-result-download.js');
result_download_assert(
    $html2canvasPosition !== false
        && $jsPdfPosition !== false
        && $jsZipPosition !== false
        && $downloadScriptPosition !== false
        && $html2canvasPosition < $downloadScriptPosition
        && $jsPdfPosition < $downloadScriptPosition
        && $jsZipPosition < $downloadScriptPosition,
    'The direct renderer, PDF, and ZIP libraries must load before the result download script.'
);
result_download_assert(
    strpos($page, 'exam-result-download.css') !== false,
    'The result list page must retain the responsive download stylesheet.'
);
result_download_assert(
    substr_count($page, 'ResultDownloadList.refresh') === 2,
    'Both AJAX result-loading branches must initialize the inserted download controls.'
);

foreach (array(
    'data-result-select-all',
    'data-result-select',
    'data-result-download-one',
    'data-result-download-selected',
    'data-result-download-all',
    'data-result-filename',
    'data-result-archive-filename',
) as $contract) {
    result_download_assert(
        strpos($list, $contract) !== false,
        'The student result list is missing the control or metadata: ' . $contract
    );
}

result_download_assert(
    strpos($list, "'kindergarten_result_page.php' : 'resultPage.php'") !== false
        && strpos($list, 'http_build_query') !== false,
    'Each row must use the correct existing result page.'
);
result_download_assert(
    strpos($list, 'function build_result_download_filename') !== false
        && strpos($list, "'pdf'") !== false
        && strpos($list, "'zip'") !== false,
    'PDF and ZIP filenames must be generated from the result metadata.'
);
result_download_assert(
    strpos($list, 'Download result as PDF') !== false
        && strpos($list, 'fa fa-download') !== false,
    'Every row must expose a compact, clearly labelled PDF download icon.'
);

result_download_assert(
    strpos($script, 'function isAllowedResultUrl') !== false
        && strpos($script, "page !== 'resultPage.php'") !== false
        && strpos($script, "page !== 'kindergarten_result_page.php'") !== false
        && strpos($script, 'parsed.origin !== window.location.origin') !== false,
    'The browser renderer must reject cross-origin and non-result URLs.'
);
result_download_assert(
    strpos($script, "['classsection', 'classsectionactual', 'classid', 'session', 'id']") !== false
        && strpos($script, "['1st', '2nd', '3rd']") !== false
        && strpos($script, "['midterm', 'termly', 'cummulative']") !== false,
    'Result URLs must retain strict identifier, term, and result-type validation.'
);
result_download_assert(
    strpos($script, 'MAX_RESULTS_PER_DOWNLOAD = 500') !== false,
    'Unreasonable client-side batch sizes must be rejected.'
);
result_download_assert(
    strpos($script, 'window.jQuery.fn.dataTable.isDataTable(table)') !== false
        && strpos($script, 'DataTable().rows().nodes().toArray()') !== false,
    'Download all must include DataTables rows that are not on the current pagination page.'
);

result_download_assert(
    strpos($script, "frame.className = 'result-download-capture-frame'") !== false
        && strpos($script, "querySelector('[data-result-report]')") !== false
        && strpos($script, "data-result-report-ready") !== false,
    'Each existing fitted A4 report must load invisibly before conversion.'
);
result_download_assert(
    strpos($script, 'frameDocument.fonts.status') !== false
        && strpos($script, 'imagesReady(report)') !== false,
    'PDF capture must wait for result fonts and images.'
);
result_download_assert(
    strpos($script, 'window.html2canvas(report') !== false
        && strpos($script, "proxy: 'resultImageProxy.php'") !== false
        && strpos($script, 'useCORS: false') !== false
        && strpos($script, 'scale: 2') !== false,
    'The original result sheet must render at readable resolution through the same-origin image proxy.'
);
result_download_assert(
    strpos($imageProxyHelper, "schoollift.s3.us-east-2.amazonaws.com") !== false
        && strpos($imageProxyHelper, "strtolower((string) (\$parts['scheme'] ?? '')) !== 'https'") !== false
        && strpos($imageProxyHelper, "isset(\$parts['user'])") !== false
        && strpos($imageProxy, 'CURLOPT_FOLLOWLOCATION => false') !== false
        && strpos($imageProxy, 'CURLOPT_WRITEFUNCTION') !== false
        && strpos($imageProxy, '8 * 1024 * 1024') !== false,
    'The result image proxy must be HTTPS-only, host-allowlisted, non-redirecting, and streaming-size-limited.'
);
result_download_assert(
    strpos($imageProxy, "in_array((string) (\$rolefirst ?? ''), array('staff', 'student', 'parent'), true)") !== false
        && strpos($imageProxyHelper, "'image/jpeg'") !== false
        && strpos($imageProxyHelper, "'image/png'") !== false,
    'Only authenticated result users and verified raster image payloads may use the image proxy.'
);
result_download_assert(
    strpos($script, 'new window.jspdf.jsPDF') !== false
        && strpos($script, "format: 'a4'") !== false
        && strpos($script, "pdf.addImage(image, 'JPEG', 0, 0, 210, 297") !== false
        && strpos($script, "pdf.output('arraybuffer')") !== false,
    'Every generated PDF must contain exactly one 210mm by 297mm A4 result image.'
);
result_download_assert(
    strpos($script, "new window.Blob([buffer], { type: 'application/pdf' })") !== false
        && strpos($script, 'downloadBlob(') !== false,
    'A row download must stream a PDF Blob directly to the browser.'
);

result_download_assert(
    strpos($script, 'new window.JSZip()') !== false
        && strpos($script, "compression: 'STORE'") !== false
        && strpos($script, 'zip.generateAsync') !== false
        && strpos($script, "mimeType: 'application/zip'") !== false,
    'Selected/all result PDFs must be packaged into a browser-generated ZIP.'
);
result_download_assert(
    strpos($script, 'uniqueArchiveFilename') !== false
        && strpos($script, "zip.file(\n                'download-errors.txt'") !== false,
    'ZIP entries must remain unique and report any partial conversion failures.'
);
result_download_assert(
    strpos($script, 'startDownload(item ? [item] : [], list, false)') !== false
        && strpos($script, 'startDownload(selectedItems(list), list, true)') !== false
        && strpos($script, 'startDownload(allItems(list), list, true)') !== false,
    'One result must download as PDF while selected and all results download as ZIP.'
);
result_download_assert(
    strpos($script, "new window.CustomEvent('resultdownloadready'") !== false,
    'Completed browser downloads must publish an observable readiness event.'
);

result_download_assert(
    strpos($styles, '.result-download-toolbar__actions') !== false
        && strpos($styles, '.result-download-toolbar__dropdown') !== false
        && strpos($styles, '@media (max-width: 575.98px)') !== false,
    'Desktop buttons must still collapse into a compact mobile dropdown.'
);
result_download_assert(
    preg_match('/\.result-download-table__select\s*\{[^}]*width:\s*38px;/s', $styles) === 1
        && preg_match('/\.result-download-row-actions__download\s*\{[^}]*width:\s*31px;[^}]*height:\s*31px;/s', $styles) === 1,
    'Selection and row download controls must remain compact.'
);
result_download_assert(
    strpos($styles, '.result-download-capture-frame') !== false
        && strpos($styles, 'left: -12000px') !== false
        && strpos($styles, 'opacity: 0') !== false,
    'Background result rendering must not consume visible page space.'
);
result_download_assert(
    strpos($styles, '.result-bulk-download-page') === false
        && strpos($styles, '.result-bulk-download-sheet') === false,
    'Unused bulk-page preview and print styles must be removed.'
);

echo 'result download contract tests passed (' . $assertions . ' assertions)' . PHP_EOL;
