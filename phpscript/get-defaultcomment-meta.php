<?php
include('../database/config.php');
require_once('../helper/defaultcomment_helper.php');

header('Content-Type: application/json');

$classid = (int) ($_POST['classid'] ?? 0);
$resultType = normalize_defaultcomment_result_subtype($_POST['resultType'] ?? 'termly');
$meta = get_defaultcomment_max_score($link, $classid, $resultType);

echo json_encode([
    'success' => $meta['success'],
    'maxScore' => $meta['maxScore'],
    'message' => $meta['message'],
    'resultType' => $resultType
]);
