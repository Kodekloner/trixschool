<?php

/** Contract coverage for individual, selected, and all-result downloads. */

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
$bulkPage = file_get_contents($root . '/admin/resultBulkDownload.php');
$listScript = file_get_contents($root . '/assets/js/exam-result-download.js');
$bulkScript = file_get_contents($root . '/assets/js/result-bulk-download.js');
$styles = file_get_contents($root . '/assets/css/exam-result-download.css');

foreach (array(
    'exam result page' => $page,
    'student result list' => $list,
    'bulk download page' => $bulkPage,
    'list download script' => $listScript,
    'bulk download script' => $bulkScript,
    'download stylesheet' => $styles,
) as $name => $source) {
    result_download_assert($source !== false, ucfirst($name) . ' must be readable.');
}

result_download_assert(
    strpos($page, 'exam-result-download.css') !== false
        && strpos($page, 'exam-result-download.js') !== false,
    'The result list page must load the responsive download assets.'
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
) as $contract) {
    result_download_assert(
        strpos($list, $contract) !== false,
        'The student result list is missing the control: ' . $contract
    );
}

result_download_assert(
    strpos($list, "'kindergarten_result_page.php' : 'resultPage.php'") !== false
        && strpos($list, 'http_build_query') !== false,
    'Each row must build its download from the correct existing result page.'
);
result_download_assert(
    strpos($list, 'class="btn btn-sm btn-outline-primary result-download-row-actions__download"') !== false
        && strpos($list, 'fa fa-download') !== false,
    'Every row must expose a compact download icon button.'
);

result_download_assert(
    strpos($listScript, "form.target = '_blank'") !== false
        && strpos($listScript, "form.action = 'resultBulkDownload.php'") !== false
        && strpos($listScript, "input.name = 'result_urls'") !== false,
    'Downloads must open a dedicated PDF preparation view through a POST request.'
);
result_download_assert(
    strpos($listScript, 'selectedUrls(list)') !== false
        && strpos($listScript, 'allUrls(list)') !== false
        && strpos($listScript, "one.getAttribute('href')") !== false,
    'The list script must support one, selected, and all-result downloads.'
);

result_download_assert(
    strpos($bulkPage, "REQUEST_METHOD'] !== 'POST'") !== false
        && strpos($bulkPage, 'count($decodedUrls) > 500') !== false,
    'The bulk view must reject invalid methods and unreasonable batch sizes.'
);
result_download_assert(
    strpos($bulkPage, "array('resultPage.php', 'kindergarten_result_page.php')") !== false
        && strpos($bulkPage, "isset(\$parts['scheme'])") !== false
        && strpos($bulkPage, "isset(\$parts['host'])") !== false,
    'The bulk view must allow only local result-page URLs.'
);
result_download_assert(
    strpos($bulkPage, 'JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT') !== false,
    'The download payload must be safely embedded in the preparation page.'
);

result_download_assert(
    strpos($bulkScript, 'output.appendChild(await loadReport') !== false,
    'Bulk results must load sequentially to avoid excessive simultaneous requests.'
);
result_download_assert(
    strpos($bulkScript, "querySelector('[data-result-report]')") !== false
        && strpos($bulkScript, "data-result-report-ready") !== false,
    'Each result must finish its shared A4 fitting before it is copied.'
);
result_download_assert(
    strpos($bulkScript, "sourceCanvas.toDataURL('image/png')") !== false,
    'Rendered charts must be retained in the combined result output.'
);
result_download_assert(
    strpos($bulkScript, 'window.print()') !== false
        && strpos($bulkScript, 'waitForOutputImages') !== false,
    'The print/PDF dialog must wait until result images have loaded.'
);

result_download_assert(
    strpos($styles, '.result-download-toolbar__actions') !== false
        && strpos($styles, '.result-download-toolbar__dropdown') !== false
        && strpos($styles, '@media (max-width: 575.98px)') !== false,
    'Desktop buttons must collapse into a compact mobile dropdown.'
);
result_download_assert(
    preg_match('/\.result-download-table__select\s*\{[^}]*width:\s*38px;/s', $styles) === 1
        && preg_match('/\.result-download-row-actions__download\s*\{[^}]*width:\s*31px;[^}]*height:\s*31px;/s', $styles) === 1,
    'Selection and row download controls must remain compact.'
);
result_download_assert(
    strpos($styles, '.result-bulk-download-sheet') !== false
        && strpos($styles, 'page-break-after: always') !== false
        && strpos($styles, 'width: 210mm !important') !== false
        && strpos($styles, 'height: 297mm !important') !== false,
    'Every downloaded student result must occupy one A4 page.'
);

echo 'result download contract tests passed (' . $assertions . ' assertions)' . PHP_EOL;
