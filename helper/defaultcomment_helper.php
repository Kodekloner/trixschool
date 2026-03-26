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
