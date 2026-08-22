<?php

include('../database/config.php');
require_once('../helper/resultdownload_helper.php');

function result_image_proxy_error($statusCode, $message)
{
    http_response_code((int) $statusCode);
    header('Content-Type: text/plain; charset=UTF-8');
    header('X-Content-Type-Options: nosniff');
    echo $message;
    exit;
}

if (!isset($_SERVER['REQUEST_METHOD']) || $_SERVER['REQUEST_METHOD'] !== 'GET') {
    header('Allow: GET');
    result_image_proxy_error(405, 'Method not allowed.');
}

if (empty($id) || !in_array((string) ($rolefirst ?? ''), array('staff', 'student', 'parent'), true)) {
    result_image_proxy_error(403, 'You are not authorized to load result images.');
}

$assetUrl = result_download_validate_image_proxy_url($_GET['url'] ?? '');
if ($assetUrl === null) {
    result_image_proxy_error(400, 'Invalid result image URL.');
}

if (!function_exists('curl_init')) {
    result_image_proxy_error(503, 'The result image service is unavailable.');
}

$maximumBytes = 8 * 1024 * 1024;
$contents = '';
$tooLarge = false;
$curl = curl_init($assetUrl);
$curlOptions = array(
    CURLOPT_FOLLOWLOCATION => false,
    CURLOPT_CONNECTTIMEOUT => 5,
    CURLOPT_TIMEOUT => 15,
    CURLOPT_MAXREDIRS => 0,
    CURLOPT_HTTPHEADER => array('Accept: image/*', 'Accept-Encoding: identity'),
    CURLOPT_USERAGENT => 'SchoolLift Result PDF Image Proxy/1.0',
    CURLOPT_WRITEFUNCTION => static function ($handle, $chunk) use (&$contents, &$tooLarge, $maximumBytes) {
        $chunkLength = strlen($chunk);
        if ((strlen($contents) + $chunkLength) > $maximumBytes) {
            $tooLarge = true;
            return 0;
        }

        $contents .= $chunk;
        return $chunkLength;
    },
);
if (defined('CURLOPT_PROTOCOLS') && defined('CURLPROTO_HTTPS')) {
    $curlOptions[CURLOPT_PROTOCOLS] = CURLPROTO_HTTPS;
}
if (defined('CURLOPT_REDIR_PROTOCOLS') && defined('CURLPROTO_HTTPS')) {
    $curlOptions[CURLOPT_REDIR_PROTOCOLS] = CURLPROTO_HTTPS;
}
curl_setopt_array($curl, $curlOptions);

$curlResult = curl_exec($curl);
$statusCode = (int) curl_getinfo($curl, CURLINFO_HTTP_CODE);
$curlError = curl_errno($curl);
curl_close($curl);

if ($tooLarge) {
    result_image_proxy_error(413, 'The result image is too large.');
}
if ($curlResult === false || $curlError !== 0 || $statusCode < 200 || $statusCode >= 300) {
    result_image_proxy_error(502, 'The result image could not be loaded.');
}

$mimeType = result_download_detect_proxy_image_type($contents);
if ($mimeType === null) {
    result_image_proxy_error(415, 'The requested asset is not a supported result image.');
}

$responseType = strtolower(trim((string) ($_GET['responseType'] ?? 'blob')));
header('X-Content-Type-Options: nosniff');
header('Cache-Control: private, max-age=3600');

if ($responseType === 'text') {
    $dataUrl = 'data:' . $mimeType . ';base64,' . base64_encode($contents);
    header('Content-Type: text/plain; charset=UTF-8');
    header('Content-Length: ' . strlen($dataUrl));
    echo $dataUrl;
    exit;
}

header('Content-Type: ' . $mimeType);
header('Content-Length: ' . strlen($contents));
echo $contents;
