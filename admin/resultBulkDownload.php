<?php
include('../database/config.php');

$resultUrls = array();
$requestError = '';
$rawUrls = $_POST['result_urls'] ?? '';

if (!isset($_SERVER['REQUEST_METHOD']) || $_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    $requestError = 'Open this page from the result download buttons.';
} else {
    $decodedUrls = json_decode((string) $rawUrls, true);

    if (!is_array($decodedUrls) || empty($decodedUrls)) {
        http_response_code(400);
        $requestError = 'No valid results were selected.';
    } elseif (count($decodedUrls) > 500) {
        http_response_code(400);
        $requestError = 'A maximum of 500 results can be prepared at once.';
    } else {
        foreach ($decodedUrls as $rawUrl) {
            if (!is_string($rawUrl) || $rawUrl === '' || strlen($rawUrl) > 1500) {
                continue;
            }

            $parts = parse_url(html_entity_decode($rawUrl, ENT_QUOTES | ENT_HTML5, 'UTF-8'));
            if (!is_array($parts)
                || isset($parts['scheme'])
                || isset($parts['host'])
                || isset($parts['user'])
                || isset($parts['pass'])
                || isset($parts['fragment'])
            ) {
                continue;
            }

            $page = basename((string) ($parts['path'] ?? ''));
            if (!in_array($page, array('resultPage.php', 'kindergarten_result_page.php'), true)) {
                continue;
            }

            $query = array();
            parse_str((string) ($parts['query'] ?? ''), $query);
            $requiredIntegerFields = array('classsection', 'classsectionactual', 'classid', 'session', 'id');
            $valid = true;
            $parameters = array();

            foreach ($requiredIntegerFields as $field) {
                $value = filter_var($query[$field] ?? null, FILTER_VALIDATE_INT);
                if ($value === false || (int) $value <= 0) {
                    $valid = false;
                    break;
                }
                $parameters[$field] = (int) $value;
            }

            $term = (string) ($query['term'] ?? '');
            $resultType = strtolower(trim((string) ($query['reltype'] ?? '')));
            if (!in_array($term, array('1st', '2nd', '3rd'), true)
                || !in_array($resultType, array('midterm', 'termly', 'cummulative'), true)
            ) {
                $valid = false;
            }

            if (!$valid) {
                continue;
            }

            $parameters['term'] = $resultType === 'cummulative' ? '3rd' : $term;
            $parameters['reltype'] = $resultType;

            if ($page === 'kindergarten_result_page.php') {
                $assessmentId = filter_var($query['assessment_id'] ?? null, FILTER_VALIDATE_INT);
                if ($assessmentId === false || (int) $assessmentId <= 0) {
                    continue;
                }
                $parameters['assessment_id'] = (int) $assessmentId;
            }

            $safeUrl = $page . '?' . http_build_query($parameters, '', '&', PHP_QUERY_RFC3986);
            $resultUrls[$safeUrl] = $safeUrl;
        }

        $resultUrls = array_values($resultUrls);
        if (empty($resultUrls)) {
            http_response_code(400);
            $requestError = 'No valid result pages were supplied.';
        }
    }
}

$payload = json_encode(
    array(
        'urls' => $resultUrls,
        'error' => $requestError,
    ),
    JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT
);
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
    <title>Download Results</title>
    <link rel="stylesheet" href="../assets/bootstrap/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/4.7.0/css/font-awesome.min.css">
    <link rel="stylesheet" href="../assets/css/myStyleSheet.css">
    <link rel="stylesheet" href="../assets/css/resultStyleSheet.css">
    <link rel="stylesheet" href="../assets/css/result-report.css">
    <link rel="stylesheet" href="../assets/css/exam-result-download.css">
</head>
<body class="result-report-page result-bulk-download-page">
    <header class="result-bulk-download-status" data-result-no-print>
        <div class="result-bulk-download-status__copy">
            <strong>Preparing result PDF</strong>
            <span data-result-bulk-status>Starting download preparation…</span>
        </div>
        <div class="result-bulk-download-status__actions">
            <button type="button" class="btn btn-sm btn-primary" data-result-bulk-print hidden>
                <i class="fa fa-download" aria-hidden="true"></i> Save as PDF
            </button>
            <button type="button" class="btn btn-sm btn-light" data-result-bulk-close>Close</button>
        </div>
    </header>

    <main class="result-bulk-download-output" data-result-bulk-output></main>

    <script type="application/json" id="result-download-payload"><?php echo $payload ?: '{"urls":[],"error":"Unable to prepare results."}'; ?></script>
    <script src="../assets/js/result-bulk-download.js"></script>
</body>
</html>
