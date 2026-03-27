<?php

if (!function_exists('normalize_defaultcomment_result_subtype')) {
    function normalize_defaultcomment_result_subtype($value)
    {
        $normalized = strtolower(trim((string) $value));

        return ($normalized === 'midterm' || $normalized === 'mid-term') ? 'midterm' : 'termly';
    }
}

if (!function_exists('get_defaultcomment_max_score')) {
    function get_defaultcomment_max_score($link, $classId, $resultSubType)
    {
        $classId = (int) $classId;
        $resultSubType = normalize_defaultcomment_result_subtype($resultSubType);

        if ($classId <= 0) {
            return [
                'success' => false,
                'maxScore' => 0,
                'message' => 'Select a class first.'
            ];
        }

        if ($resultSubType === 'termly') {
            return [
                'success' => true,
                'maxScore' => 100,
                'message' => 'Termly default comments use a raw score range from 0 to 100.'
            ];
        }

        $sql = "SELECT ac.ResultSettingID, rs.*
                FROM `assigncatoclass` ac
                LEFT JOIN `resultsetting` rs ON ac.ResultSettingID = rs.ResultSettingID
                WHERE ac.ClassID = '$classId'
                LIMIT 1";
        $result = mysqli_query($link, $sql);
        $row = $result ? mysqli_fetch_assoc($result) : null;

        if (empty($row['ResultSettingID'])) {
            return [
                'success' => false,
                'maxScore' => 0,
                'message' => 'No result setting is assigned to this class.'
            ];
        }

        $midtermIndicesStr = trim((string) ($row['MidTermCaToUse'] ?? ''));
        if ($midtermIndicesStr === '') {
            return [
                'success' => false,
                'maxScore' => 0,
                'message' => 'Mid-term CA settings are not configured for this class.'
            ];
        }

        $maxScore = 0;
        $midtermIndices = array_filter(array_map('trim', explode(',', $midtermIndicesStr)), 'strlen');

        foreach ($midtermIndices as $index) {
            $scoreKey = 'CA' . $index . 'Score';
            $maxScore += (float) ($row[$scoreKey] ?? 0);
        }

        if ($maxScore <= 0) {
            return [
                'success' => false,
                'maxScore' => 0,
                'message' => 'Mid-term max score could not be determined for this class.'
            ];
        }

        return [
            'success' => true,
            'maxScore' => $maxScore,
            'message' => 'Mid-term default comments use a raw score range from 0 to ' . $maxScore . '.'
        ];
    }
}

if (!function_exists('find_defaultcomment_match')) {
    function find_defaultcomment_match($link, $commentType, $classId, $resultSubType, $score, $ownerId = null)
    {
        $classId = (int) $classId;
        $score = (float) $score;
        $resultSubType = normalize_defaultcomment_result_subtype($resultSubType);
        $commentTypeSafe = mysqli_real_escape_string($link, $commentType);
        $ownerCondition = $ownerId !== null ? " AND PrincipalOrDeanOrHeadTeacherUserID = '" . (int) $ownerId . "'" : '';

        $queries = [];

        if ($classId > 0) {
            $queries[] = "SELECT *
                          FROM `defaultcomment`
                          WHERE CommentType = '$commentTypeSafe'
                            AND ClassID = '$classId'
                            AND ResultSubType = '$resultSubType'
                            AND $score BETWEEN RangeStart AND RangeEnd
                            $ownerCondition
                          ORDER BY defaultcommentID DESC
                          LIMIT 1";
        }

        if ($resultSubType === 'termly') {
            $queries[] = "SELECT *
                          FROM `defaultcomment`
                          WHERE CommentType = '$commentTypeSafe'
                            AND ClassID IS NULL
                            AND ResultSubType = 'termly'
                            AND $score BETWEEN RangeStart AND RangeEnd
                            $ownerCondition
                          ORDER BY defaultcommentID DESC
                          LIMIT 1";
        }

        foreach ($queries as $sql) {
            $result = mysqli_query($link, $sql);
            if ($result && mysqli_num_rows($result) > 0) {
                return mysqli_fetch_assoc($result);
            }
        }

        return null;
    }
}

if (!function_exists('find_defaultcomment_overlap')) {
    function find_defaultcomment_overlap($link, $ownerId, $classId, $commentType, $resultSubType, $rangeStart, $rangeEnd, $excludeId = 0)
    {
        $ownerId = (int) $ownerId;
        $classId = (int) $classId;
        $excludeId = (int) $excludeId;
        $rangeStart = (float) $rangeStart;
        $rangeEnd = (float) $rangeEnd;
        $commentTypeSafe = mysqli_real_escape_string($link, $commentType);
        $resultSubType = normalize_defaultcomment_result_subtype($resultSubType);
        $classCondition = $classId > 0 ? "ClassID = '$classId'" : 'ClassID IS NULL';
        $excludeCondition = $excludeId > 0 ? " AND defaultcommentID != '$excludeId'" : '';

        $sql = "SELECT *
                FROM `defaultcomment`
                WHERE PrincipalOrDeanOrHeadTeacherUserID = '$ownerId'
                  AND $classCondition
                  AND CommentType = '$commentTypeSafe'
                  AND ResultSubType = '$resultSubType'
                  AND NOT (RangeEnd < '$rangeStart' OR RangeStart > '$rangeEnd')
                  $excludeCondition
                LIMIT 1";

        $result = mysqli_query($link, $sql);

        return ($result && mysqli_num_rows($result) > 0) ? mysqli_fetch_assoc($result) : null;
    }
}

if (!function_exists('get_result_class_teacher')) {
    function get_result_class_teacher($link, $classId, $sectionId, $sessionId)
    {
        $classId = (int) $classId;
        $sectionId = (int) $sectionId;
        $sessionId = (int) $sessionId;

        $queries = [
            "SELECT * FROM `class_teacher` WHERE class_id = '$classId' AND section_id = '$sectionId' AND session_id = '$sessionId' ORDER BY id DESC LIMIT 1",
            "SELECT * FROM `class_teacher` WHERE class_id = '$classId' AND section_id = '$sectionId' ORDER BY id DESC LIMIT 1",
            "SELECT * FROM `class_teacher` WHERE class_id = '$classId' ORDER BY id DESC LIMIT 1",
        ];

        foreach ($queries as $sql) {
            $result = mysqli_query($link, $sql);
            if ($result && mysqli_num_rows($result) > 0) {
                return mysqli_fetch_assoc($result);
            }
        }

        return null;
    }
}

if (!function_exists('get_staff_signature_row')) {
    function get_staff_signature_row($link, $staffId)
    {
        $staffId = (int) $staffId;

        if ($staffId <= 0) {
            return null;
        }

        $sql = "SELECT * FROM `staffsignature`
                WHERE `staff_id` = '$staffId'
                  AND TRIM(COALESCE(`Signature`, '')) != ''
                ORDER BY `id` DESC
                LIMIT 1";
        $result = mysqli_query($link, $sql);

        return ($result && mysqli_num_rows($result) > 0) ? mysqli_fetch_assoc($result) : null;
    }
}

if (!function_exists('find_staff_by_roles')) {
    function find_staff_by_roles($link, array $roleNames, $requireSignature = false)
    {
        $roleNames = array_values(array_filter(array_map('trim', $roleNames), 'strlen'));

        if (empty($roleNames)) {
            return null;
        }

        $escapedRoleNames = [];
        $roleOrderCases = [];

        foreach ($roleNames as $index => $roleName) {
            $escapedRoleName = mysqli_real_escape_string($link, $roleName);
            $escapedRoleNames[] = "'" . $escapedRoleName . "'";
            $roleOrderCases[] = "WHEN '" . $escapedRoleName . "' THEN " . ($index + 1);
        }

        $inClause = implode(', ', $escapedRoleNames);
        $orderCase = 'CASE r.name ' . implode(' ', $roleOrderCases) . ' ELSE 999 END';
        $signatureHaving = $requireSignature ? "HAVING TRIM(COALESCE(`Signature`, '')) != ''" : '';

        $sql = "SELECT
                    s.id AS staff_id,
                    r.name AS role_name,
                    (
                        SELECT ss.Signature
                        FROM `staffsignature` ss
                        WHERE ss.staff_id = s.id
                          AND TRIM(COALESCE(ss.Signature, '')) != ''
                        ORDER BY ss.id DESC
                        LIMIT 1
                    ) AS `Signature`
                FROM `staff_roles` sr
                INNER JOIN `roles` r ON r.id = sr.role_id
                INNER JOIN `staff` s ON s.id = sr.staff_id
                WHERE r.name IN ($inClause)
                GROUP BY s.id, r.name
                $signatureHaving
                ORDER BY $orderCase, s.id ASC
                LIMIT 1";
        $result = mysqli_query($link, $sql);

        return ($result && mysqli_num_rows($result) > 0) ? mysqli_fetch_assoc($result) : null;
    }
}

if (!function_exists('resolve_school_head_staff_id')) {
    function resolve_school_head_staff_id($link, $preferredStaffId = 0)
    {
        $preferredStaffId = (int) $preferredStaffId;

        if ($preferredStaffId > 0) {
            return $preferredStaffId;
        }

        $rolePriority = ['Head Teacher', 'Principal', 'Dean'];
        $staffWithSignature = find_staff_by_roles($link, $rolePriority, true);

        if (!empty($staffWithSignature['staff_id'])) {
            return (int) $staffWithSignature['staff_id'];
        }

        $staffWithoutSignature = find_staff_by_roles($link, $rolePriority, false);

        return !empty($staffWithoutSignature['staff_id']) ? (int) $staffWithoutSignature['staff_id'] : 0;
    }
}

if (!function_exists('build_staff_signature_url')) {
    function build_staff_signature_url($signature, $localPrefix = '../img/signature/', $s3Prefix = 'https://schoollift.s3.us-east-2.amazonaws.com/')
    {
        $signature = trim((string) $signature);

        if ($signature === '') {
            return '';
        }

        if (preg_match('#^https?://#i', $signature)) {
            return $signature;
        }

        $encodePath = function ($path) {
            $parts = array_values(array_filter(explode('/', trim((string) $path, '/')), 'strlen'));

            return implode('/', array_map('rawurlencode', $parts));
        };

        if (stripos($signature, 'uploads/') === 0) {
            return rtrim($s3Prefix, '/') . '/' . $encodePath($signature);
        }

        if (stripos($signature, 'img/signature/') === 0) {
            return '../' . $encodePath($signature);
        }

        return rtrim($localPrefix, '/') . '/' . rawurlencode($signature);
    }
}

if (!function_exists('build_staff_signature_html')) {
    function build_staff_signature_html($signature, $localPrefix = '../img/signature/', $className = 'img-fluid signature-img')
    {
        $signatureUrl = build_staff_signature_url($signature, $localPrefix);

        if ($signatureUrl === '') {
            return '';
        }

        return '<img src="' . htmlspecialchars($signatureUrl, ENT_QUOTES) . '" align="center" class="' . htmlspecialchars($className, ENT_QUOTES) . '">';
    }
}

if (!function_exists('get_school_head_signature_html')) {
    function get_school_head_signature_html($link, $preferredStaffId = 0, $localPrefix = '../img/signature/')
    {
        $signatureRow = get_staff_signature_row($link, $preferredStaffId);

        if (empty($signatureRow) && (int) $preferredStaffId <= 0) {
            $fallbackStaff = find_staff_by_roles($link, ['Head Teacher', 'Principal', 'Dean'], true);

            if (!empty($fallbackStaff['Signature'])) {
                $signatureRow = $fallbackStaff;
            }
        }

        return !empty($signatureRow['Signature']) ? build_staff_signature_html($signatureRow['Signature'], $localPrefix) : '';
    }
}

if (!function_exists('get_school_head_comment_data')) {
    function get_school_head_comment_data($link, $studentId, $sessionId, $term, $resultSubType, $classId, $score, $localPrefix = '../img/signature/')
    {
        $studentId = (int) $studentId;
        $sessionId = (int) $sessionId;
        $termSafe = mysqli_real_escape_string($link, (string) $term);
        $resultSubType = normalize_defaultcomment_result_subtype($resultSubType);
        $resultSubTypeSafe = mysqli_real_escape_string($link, $resultSubType);
        $fixedRemarkSql = "SELECT *
                           FROM `remark`
                           WHERE `RemarkType` = 'SchoolHead'
                             AND `StudentID` = '$studentId'
                             AND `Session` = '$sessionId'
                             AND `Term` = '$termSafe'
                             AND `ResultSubType` = '$resultSubTypeSafe'
                             AND TRIM(COALESCE(`remark`, '')) != ''
                           ORDER BY `id` DESC
                           LIMIT 1";
        $fixedRemarkResult = mysqli_query($link, $fixedRemarkSql);
        $fixedRemarkRow = ($fixedRemarkResult && mysqli_num_rows($fixedRemarkResult) > 0) ? mysqli_fetch_assoc($fixedRemarkResult) : null;

        if (!empty($fixedRemarkRow)) {
            $staffId = resolve_school_head_staff_id($link, $fixedRemarkRow['StaffID'] ?? 0);

            return [
                'remark' => $fixedRemarkRow['remark'],
                'signatureHtml' => get_school_head_signature_html($link, $staffId, $localPrefix),
                'staffId' => $staffId,
            ];
        }

        $defaultCommentRow = find_defaultcomment_match($link, 'SchoolHead', $classId, $resultSubType, $score);

        if (!empty($defaultCommentRow)) {
            $staffId = resolve_school_head_staff_id($link, $defaultCommentRow['PrincipalOrDeanOrHeadTeacherUserID'] ?? 0);

            return [
                'remark' => $defaultCommentRow['DefaultComment'],
                'signatureHtml' => get_school_head_signature_html($link, $staffId, $localPrefix),
                'staffId' => $staffId,
            ];
        }

        return [
            'remark' => 'N/A',
            'signatureHtml' => '',
            'staffId' => 0,
        ];
    }
}

if (!function_exists('validate_defaultcomment_payload')) {
    function validate_defaultcomment_payload($link, $ownerId, $classId, $commentType, $resultSubType, $rangeStart, $rangeEnd, $comment, $excludeId = 0)
    {
        $ownerId = (int) $ownerId;
        $classId = (int) $classId;
        $rangeStart = trim((string) $rangeStart);
        $rangeEnd = trim((string) $rangeEnd);
        $comment = trim((string) $comment);
        $excludeId = (int) $excludeId;
        $resultSubType = normalize_defaultcomment_result_subtype($resultSubType);

        if ($ownerId <= 0) {
            return [
                'success' => false,
                'message' => 'Select a staff record first.',
                'resultSubType' => $resultSubType,
            ];
        }

        if ($classId <= 0) {
            return [
                'success' => false,
                'message' => 'Select a class first.',
                'resultSubType' => $resultSubType,
            ];
        }

        if ($rangeStart === '' || $rangeEnd === '' || $comment === '') {
            return [
                'success' => false,
                'message' => 'Complete the score range and comment before submitting.',
                'resultSubType' => $resultSubType,
            ];
        }

        $rangeStartFloat = (float) $rangeStart;
        $rangeEndFloat = (float) $rangeEnd;

        if ($rangeStartFloat < 0 || $rangeEndFloat < 0 || $rangeStartFloat > $rangeEndFloat) {
            return [
                'success' => false,
                'message' => 'The score range is invalid. Make sure the start is not greater than the end.',
                'resultSubType' => $resultSubType,
            ];
        }

        $maxScoreMeta = get_defaultcomment_max_score($link, $classId, $resultSubType);
        if (!$maxScoreMeta['success']) {
            return [
                'success' => false,
                'message' => $maxScoreMeta['message'],
                'resultSubType' => $resultSubType,
                'maxScoreMeta' => $maxScoreMeta,
            ];
        }

        if ($rangeEndFloat > (float) $maxScoreMeta['maxScore']) {
            return [
                'success' => false,
                'message' => 'The range cannot exceed the class max score of ' . $maxScoreMeta['maxScore'] . '.',
                'resultSubType' => $resultSubType,
                'maxScoreMeta' => $maxScoreMeta,
            ];
        }

        $overlap = find_defaultcomment_overlap(
            $link,
            $ownerId,
            $classId,
            $commentType,
            $resultSubType,
            $rangeStartFloat,
            $rangeEndFloat,
            $excludeId
        );

        if (!empty($overlap)) {
            return [
                'success' => false,
                'message' => 'That score range overlaps an existing default comment for this class and result type.',
                'resultSubType' => $resultSubType,
                'maxScoreMeta' => $maxScoreMeta,
                'overlap' => $overlap,
            ];
        }

        return [
            'success' => true,
            'message' => '',
            'resultSubType' => $resultSubType,
            'maxScoreMeta' => $maxScoreMeta,
            'rangeStart' => $rangeStartFloat,
            'rangeEnd' => $rangeEndFloat,
            'comment' => $comment,
        ];
    }
}

if (!function_exists('defaultcomment_reload_state_markup')) {
    function defaultcomment_reload_state_markup($staffId, $classId, $resultSubType)
    {
        $staffId = (int) $staffId;
        $classId = (int) $classId;
        $resultSubType = htmlspecialchars(normalize_defaultcomment_result_subtype($resultSubType), ENT_QUOTES);

        return '<input type="hidden" id="reloadStaffID" value="' . $staffId . '">' .
            '<input type="hidden" id="reloadClassID" value="' . $classId . '">' .
            '<input type="hidden" id="reloadResultType" value="' . $resultSubType . '">';
    }
}

if (!function_exists('defaultcomment_alert_markup')) {
    function defaultcomment_alert_markup($type, $message, $staffId, $classId, $resultSubType)
    {
        $class = $type === 'success' ? 'success' : 'warning';

        return "<div align='left' class='alert alert-$class'>
                <button type='button' class='close' data-dismiss='alert' aria-label='Close'> <span aria-hidden='true'>×</span> </button>" .
            htmlspecialchars($message, ENT_QUOTES) .
            "</div>" .
            defaultcomment_reload_state_markup($staffId, $classId, $resultSubType);
    }
}
