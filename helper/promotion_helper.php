<?php

/**
 * Shared promotion and result-summary helpers.
 *
 * This file deliberately has no CodeIgniter dependency so the legacy result
 * pages and the newer administration module can use the same calculations.
 */

if (!function_exists('promotion_score_expression')) {
    function promotion_score_expression($alias = '')
    {
        $prefix = $alias !== '' ? rtrim($alias, '.') . '.' : '';
        $columns = ['exam', 'ca1', 'ca2', 'ca3', 'ca4', 'ca5', 'ca6', 'ca7', 'ca8', 'ca9', 'ca10'];
        $parts = [];

        foreach ($columns as $column) {
            $parts[] = 'COALESCE(' . $prefix . '`' . $column . '`, 0)';
        }

        return '(' . implode(' + ', $parts) . ')';
    }
}

if (!function_exists('promotion_scored_row_condition')) {
    function promotion_scored_row_condition($alias = '')
    {
        return promotion_score_expression($alias) . ' != 0';
    }
}

if (!function_exists('promotion_decimal')) {
    function promotion_decimal($value)
    {
        if ($value === null || $value === '' || !is_numeric($value)) {
            return null;
        }

        return round((float) $value, 2);
    }
}

if (!function_exists('promotion_number_label')) {
    function promotion_number_label($value)
    {
        $number = promotion_decimal($value);
        if ($number === null) {
            return '';
        }

        return rtrim(rtrim(number_format($number, 2, '.', ''), '0'), '.');
    }
}

if (!function_exists('build_result_brand_palette')) {
    /**
     * Convert a configured school colour into a small print-safe palette.
     * Light brand colours retain their identity while receiving dark heading
     * text and a darker border colour with sufficient visual separation.
     */
    function build_result_brand_palette($configuredColor)
    {
        $brand = strtolower(trim((string) $configuredColor));
        if (!preg_match('/^#[0-9a-f]{6}$/', $brand)) {
            $brand = '#1f4e78';
        }

        $red = hexdec(substr($brand, 1, 2));
        $green = hexdec(substr($brand, 3, 2));
        $blue = hexdec(substr($brand, 5, 2));
        $channels = [$red, $green, $blue];
        $linear = [];
        foreach ($channels as $channel) {
            $value = $channel / 255;
            $linear[] = $value <= 0.03928
                ? $value / 12.92
                : pow(($value + 0.055) / 1.055, 2.4);
        }
        $luminance = (0.2126 * $linear[0]) + (0.7152 * $linear[1]) + (0.0722 * $linear[2]);

        $strongChannels = $luminance > 0.45
            ? array_map(function ($channel) {
                return (int) round($channel * 0.55);
            }, $channels)
            : $channels;
        $softChannels = array_map(function ($channel) {
            return (int) round(255 - ((255 - $channel) * 0.12));
        }, $channels);

        $toHex = function (array $rgb) {
            return sprintf('#%02x%02x%02x', $rgb[0], $rgb[1], $rgb[2]);
        };

        return [
            'brand' => $brand,
            'strong' => $toHex($strongChannels),
            'soft' => $toHex($softChannels),
            // WCAG contrast against pure black is >= 4.5 from this
            // luminance boundary upward; white covers the darker side.
            'contrast' => $luminance > 0.179 ? '#000000' : '#ffffff',
        ];
    }
}

if (!function_exists('promotion_target_label')) {
    function promotion_target_label(array $assignment)
    {
        $customLabel = trim((string) ($assignment['promoted_to_label'] ?? ''));
        if ($customLabel !== '') {
            return $customLabel;
        }

        return trim((string) ($assignment['promoted_to_class_name'] ?? ''));
    }
}

if (!function_exists('build_promotion_outcome')) {
    function build_promotion_outcome($decision, $targetLabel = '', $source = 'system', $reasonCode = '', array $extra = [])
    {
        $decision = strtolower(trim((string) $decision));
        if (!in_array($decision, ['promoted', 'not_promoted', 'pending'], true)) {
            $decision = 'pending';
        }

        $targetLabel = trim((string) $targetLabel);
        if ($decision === 'promoted' && $targetLabel !== '') {
            $note = 'PROMOTED TO: ' . $targetLabel;
        } elseif ($decision === 'promoted') {
            $note = 'PROMOTED';
        } elseif ($decision === 'not_promoted') {
            $note = 'NOT PROMOTED';
        } else {
            $note = 'PROMOTION PENDING';
        }

        return array_merge([
            'decision' => $decision,
            'note' => $note,
            'target_label' => $decision === 'promoted' ? $targetLabel : '',
            'source' => $source === 'override' ? 'override' : 'system',
            'reason_code' => (string) $reasonCode,
            'overall_average' => null,
            'priority_results' => [],
            'has_data' => false,
            'criteria_id' => 0,
            'override' => null,
        ], $extra);
    }
}

if (!function_exists('evaluate_promotion_rule')) {
    /**
     * @param float|null $overallAverage Rounded annual average shown on the result.
     * @param bool       $hasAcademicData Whether any non-placeholder score exists.
     * @param float      $minimumAverage Required overall average.
     * @param array      $priorityRequirements Rows containing subject_id/name/minimum_average.
     * @param array      $subjectAverages Map of subject id to annual average.
     */
    function evaluate_promotion_rule(
        $overallAverage,
        $hasAcademicData,
        $minimumAverage,
        array $priorityRequirements,
        array $subjectAverages,
        $targetLabel = ''
    ) {
        $overallAverage = promotion_decimal($overallAverage);
        $minimumAverage = promotion_decimal($minimumAverage);
        $priorityResults = [];
        $hasMissingPriority = false;
        $hasFailedPriority = false;

        foreach ($priorityRequirements as $requirement) {
            $subjectId = (int) ($requirement['subject_id'] ?? 0);
            $requiredAverage = promotion_decimal($requirement['minimum_average'] ?? null);
            $subjectAverage = array_key_exists($subjectId, $subjectAverages)
                ? promotion_decimal($subjectAverages[$subjectId])
                : null;
            $missing = $subjectAverage === null;
            $passed = !$missing && $requiredAverage !== null && $subjectAverage >= $requiredAverage;

            if ($missing) {
                $hasMissingPriority = true;
            } elseif (!$passed) {
                $hasFailedPriority = true;
            }

            $priorityResults[] = [
                'subject_id' => $subjectId,
                'subject_name' => trim((string) ($requirement['subject_name'] ?? '')),
                'minimum_average' => $requiredAverage,
                'average' => $subjectAverage,
                'missing' => $missing,
                'passed' => $passed,
            ];
        }

        $baseExtra = [
            'overall_average' => $overallAverage,
            'priority_results' => $priorityResults,
            'has_data' => (bool) $hasAcademicData,
        ];

        if (!$hasAcademicData || $overallAverage === null) {
            return build_promotion_outcome('pending', '', 'system', 'academic_data_missing', $baseExtra);
        }

        if ($hasMissingPriority) {
            return build_promotion_outcome('pending', '', 'system', 'priority_score_missing', $baseExtra);
        }

        if ($minimumAverage === null) {
            return build_promotion_outcome('pending', '', 'system', 'criteria_invalid', $baseExtra);
        }

        if ($overallAverage < $minimumAverage || $hasFailedPriority) {
            return build_promotion_outcome('not_promoted', '', 'system', 'criteria_not_met', $baseExtra);
        }

        if (trim((string) $targetLabel) === '') {
            return build_promotion_outcome('pending', '', 'system', 'target_missing', $baseExtra);
        }

        return build_promotion_outcome('promoted', $targetLabel, 'system', 'criteria_met', $baseExtra);
    }
}

if (!function_exists('apply_promotion_override')) {
    function apply_promotion_override(array $automaticOutcome, $override)
    {
        if (!is_array($override) || strtolower((string) ($override['action'] ?? '')) === 'clear') {
            $automaticOutcome['override'] = is_array($override) ? $override : null;
            return $automaticOutcome;
        }

        $decision = strtolower(trim((string) ($override['decision'] ?? '')));
        if (!in_array($decision, ['promoted', 'not_promoted'], true)) {
            return $automaticOutcome;
        }

        $targetLabel = trim((string) ($override['target_label'] ?? ''));
        if ($targetLabel === '') {
            $targetLabel = trim((string) ($override['target_class_name'] ?? ''));
        }
        if ($targetLabel === '' && $decision === 'promoted') {
            $targetLabel = (string) ($automaticOutcome['target_label'] ?? '');
        }

        return build_promotion_outcome($decision, $targetLabel, 'override', 'manual_override', [
            'overall_average' => $automaticOutcome['overall_average'] ?? null,
            'priority_results' => $automaticOutcome['priority_results'] ?? [],
            'has_data' => (bool) ($automaticOutcome['has_data'] ?? false),
            'criteria_id' => (int) ($automaticOutcome['criteria_id'] ?? 0),
            'override' => $override,
        ]);
    }
}

if (!function_exists('promotion_scope_authorized')) {
    function promotion_scope_authorized($isSenior, $isTeacher, $hasExactTeacherAssignment)
    {
        return (bool) $isSenior || ((bool) $isTeacher && (bool) $hasExactTeacherAssignment);
    }
}

if (!function_exists('get_promotion_class_assignment')) {
    function get_promotion_class_assignment($link, $sessionId, $classId)
    {
        $sessionId = (int) $sessionId;
        $classId = (int) $classId;
        if ($sessionId <= 0 || $classId <= 0) {
            return null;
        }

        $sql = "SELECT pcc.*, pc.name AS criteria_name, pc.minimum_average,
                       pc.is_active AS criteria_active,
                       target_class.class AS promoted_to_class_name
                FROM `promotion_criteria_classes` pcc
                INNER JOIN `promotion_criteria` pc ON pc.id = pcc.criteria_id
                LEFT JOIN `classes` target_class ON target_class.id = pcc.promoted_to_class_id
                WHERE pcc.session_id = '$sessionId'
                  AND pcc.class_id = '$classId'
                  AND pc.session_id = '$sessionId'
                  AND pc.is_active = 1
                ORDER BY pcc.id DESC
                LIMIT 1";
        $result = @mysqli_query($link, $sql);

        return ($result && mysqli_num_rows($result) > 0) ? mysqli_fetch_assoc($result) : null;
    }
}

if (!function_exists('get_promotion_priority_requirements')) {
    function get_promotion_priority_requirements($link, $criteriaId)
    {
        $criteriaId = (int) $criteriaId;
        if ($criteriaId <= 0) {
            return [];
        }

        $sql = "SELECT pcs.subject_id, pcs.minimum_average, subjects.name AS subject_name
                FROM `promotion_criteria_subjects` pcs
                INNER JOIN `subjects` ON subjects.id = pcs.subject_id
                WHERE pcs.criteria_id = '$criteriaId'
                ORDER BY subjects.name ASC, pcs.id ASC";
        $result = @mysqli_query($link, $sql);
        $rows = [];

        if ($result) {
            while ($row = mysqli_fetch_assoc($result)) {
                $rows[] = $row;
            }
        }

        return $rows;
    }
}

if (!function_exists('get_student_annual_score_data')) {
    function get_student_annual_score_data($link, $studentId, $sessionId, $classId, $sectionId)
    {
        $studentId = (int) $studentId;
        $sessionId = (int) $sessionId;
        $classId = (int) $classId;
        $sectionId = (int) $sectionId;
        $empty = ['has_data' => false, 'total_score' => 0.0, 'score_count' => 0, 'overall_average' => null, 'subject_averages' => []];

        if ($studentId <= 0 || $sessionId <= 0 || $classId <= 0 || $sectionId <= 0) {
            return $empty;
        }

        $expression = promotion_score_expression('score');
        $scored = promotion_scored_row_condition('score');
        $scope = "score.StudentID = '$studentId'
                  AND score.Session = '$sessionId'
                  AND score.ClassID = '$classId'
                  AND score.SectionID = '$sectionId'
                  AND score.SubjectID != 0
                  AND $scored";
        $overallResult = mysqli_query(
            $link,
            "SELECT SUM($expression) AS total_score, COUNT(*) AS score_count
             FROM `score`
             WHERE $scope"
        );
        $overall = $overallResult ? mysqli_fetch_assoc($overallResult) : null;
        $scoreCount = (int) ($overall['score_count'] ?? 0);

        if ($scoreCount <= 0) {
            return $empty;
        }

        $subjectResult = mysqli_query(
            $link,
            "SELECT score.SubjectID AS subject_id,
                    SUM($expression) AS total_score,
                    COUNT(DISTINCT score.Term) AS term_count
             FROM `score`
             WHERE $scope
             GROUP BY score.SubjectID"
        );
        $subjectAverages = [];

        if ($subjectResult) {
            while ($row = mysqli_fetch_assoc($subjectResult)) {
                $count = (int) ($row['term_count'] ?? 0);
                if ($count > 0) {
                    $subjectAverages[(int) $row['subject_id']] = round((float) $row['total_score'] / $count, 2);
                }
            }
        }

        $totalScore = (float) ($overall['total_score'] ?? 0);

        return [
            'has_data' => true,
            'total_score' => round($totalScore, 2),
            'score_count' => $scoreCount,
            'overall_average' => round($totalScore / $scoreCount, 2),
            'subject_averages' => $subjectAverages,
        ];
    }
}

if (!function_exists('is_qualitative_promotion_class')) {
    function is_qualitative_promotion_class($link, $classId)
    {
        $classId = (int) $classId;
        if ($classId <= 0) {
            return false;
        }

        $resultTypeResult = mysqli_query(
            $link,
            "SELECT ResultType FROM `assigncatoclass` WHERE ClassID = '$classId' ORDER BY id DESC LIMIT 1"
        );
        $resultTypeRow = $resultTypeResult ? mysqli_fetch_assoc($resultTypeResult) : null;
        if (strtolower(trim((string) ($resultTypeRow['ResultType'] ?? ''))) === 'british') {
            return true;
        }

        $kindergartenResult = @mysqli_query(
            $link,
            "SELECT id FROM `kindergarten_assignment` WHERE class_id = '$classId' LIMIT 1"
        );

        return $kindergartenResult && mysqli_num_rows($kindergartenResult) > 0;
    }
}

if (!function_exists('get_promotion_automatic_outcome')) {
    function get_promotion_automatic_outcome($link, $studentId, $sessionId, $classId, $sectionId, $qualitative = null)
    {
        if ($qualitative === null) {
            $qualitative = is_qualitative_promotion_class($link, $classId);
        }

        if ($qualitative) {
            return build_promotion_outcome('pending', '', 'system', 'qualitative_result');
        }

        $assignment = get_promotion_class_assignment($link, $sessionId, $classId);
        if (!$assignment) {
            return build_promotion_outcome('pending', '', 'system', 'criteria_missing');
        }

        $criteriaId = (int) ($assignment['criteria_id'] ?? 0);
        $scoreData = get_student_annual_score_data($link, $studentId, $sessionId, $classId, $sectionId);
        $requirements = get_promotion_priority_requirements($link, $criteriaId);
        $outcome = evaluate_promotion_rule(
            $scoreData['overall_average'],
            $scoreData['has_data'],
            $assignment['minimum_average'] ?? null,
            $requirements,
            $scoreData['subject_averages'],
            promotion_target_label($assignment)
        );
        $outcome['criteria_id'] = $criteriaId;

        return $outcome;
    }
}

if (!function_exists('get_latest_promotion_override')) {
    function get_latest_promotion_override($link, $studentId, $sessionId, $classId, $sectionId)
    {
        $studentId = (int) $studentId;
        $sessionId = (int) $sessionId;
        $classId = (int) $classId;
        $sectionId = (int) $sectionId;
        if ($studentId <= 0 || $sessionId <= 0 || $classId <= 0 || $sectionId <= 0) {
            return null;
        }

        $sql = "SELECT pno.*, target_class.class AS target_class_name
                FROM `promotion_note_overrides` pno
                LEFT JOIN `classes` target_class ON target_class.id = pno.target_class_id
                WHERE pno.student_id = '$studentId'
                  AND pno.session_id = '$sessionId'
                  AND pno.class_id = '$classId'
                  AND pno.section_id = '$sectionId'
                ORDER BY pno.id DESC
                LIMIT 1";
        $result = @mysqli_query($link, $sql);

        return ($result && mysqli_num_rows($result) > 0) ? mysqli_fetch_assoc($result) : null;
    }
}

if (!function_exists('get_final_promotion_outcome')) {
    function get_final_promotion_outcome($link, $studentId, $sessionId, $classId, $sectionId, $qualitative = null)
    {
        $automatic = get_promotion_automatic_outcome(
            $link,
            $studentId,
            $sessionId,
            $classId,
            $sectionId,
            $qualitative
        );
        $override = get_latest_promotion_override($link, $studentId, $sessionId, $classId, $sectionId);

        return apply_promotion_override($automatic, $override);
    }
}

if (!function_exists('get_result_grading_rows')) {
    function get_result_grading_rows($link, $classId, $gradingType = 'term')
    {
        $classId = (int) $classId;
        $gradingType = strtolower(trim((string) $gradingType)) === 'midterm' ? 'midterm' : 'term';
        $sql = "SELECT DISTINCT gs.GradingStructureID, gs.Grade, gs.Remark, gs.RangeStart, gs.RangeEnd
                FROM `gradingstructure` gs
                INNER JOIN `assigngradingtclass` agc ON agc.GradingTitle = gs.GradingTitle
                WHERE agc.ClassID = '$classId'
                  AND gs.Type = '$gradingType'
                ORDER BY gs.RangeStart DESC, gs.RangeEnd DESC, gs.GradingStructureID ASC";
        $result = mysqli_query($link, $sql);
        $rows = [];

        if ($result) {
            while ($row = mysqli_fetch_assoc($result)) {
                $rows[] = $row;
            }
        }

        return $rows;
    }
}

if (!function_exists('format_result_grade_range')) {
    function format_result_grade_range(array $gradeRow, $percentageScale = true, $isTopBand = false)
    {
        $start = promotion_number_label($gradeRow['RangeStart'] ?? null);
        $end = promotion_number_label($gradeRow['RangeEnd'] ?? null);
        $suffix = $percentageScale ? '%' : '';

        if ($isTopBand && $start !== '') {
            return $start . $suffix . ' and Above';
        }

        return $start . $suffix . ' - ' . $end . $suffix;
    }
}

if (!function_exists('build_result_grade_key')) {
    function build_result_grade_key(array $gradingRows)
    {
        if (empty($gradingRows)) {
            return [];
        }

        $maximumEnd = 0.0;
        foreach ($gradingRows as $row) {
            $maximumEnd = max($maximumEnd, (float) ($row['RangeEnd'] ?? 0));
        }
        $percentageScale = $maximumEnd >= 99.9;
        $key = [];

        foreach ($gradingRows as $index => $row) {
            $key[] = [
                'grade' => trim((string) ($row['Grade'] ?? '')),
                'range' => format_result_grade_range($row, $percentageScale, $percentageScale && $index === 0),
                'remark' => trim((string) ($row['Remark'] ?? '')),
            ];
        }

        return $key;
    }
}

if (!function_exists('find_result_grade')) {
    function find_result_grade($score, array $gradingRows)
    {
        $score = promotion_decimal($score);
        if ($score === null) {
            return '';
        }

        foreach ($gradingRows as $row) {
            if ($score >= (float) $row['RangeStart'] && $score <= (float) $row['RangeEnd']) {
                return trim((string) $row['Grade']);
            }
        }

        return '';
    }
}

if (!function_exists('build_result_grade_summary')) {
    function build_result_grade_summary(array $grades, array $gradingRows = [])
    {
        $counts = [];
        foreach ($grades as $grade) {
            $grade = trim((string) $grade);
            if ($grade === '' || strtoupper($grade) === 'NA' || strtoupper($grade) === 'N/A') {
                continue;
            }
            $counts[$grade] = ($counts[$grade] ?? 0) + 1;
        }

        $orderedGrades = [];
        foreach ($gradingRows as $row) {
            $grade = trim((string) ($row['Grade'] ?? ''));
            if ($grade !== '' && !in_array($grade, $orderedGrades, true)) {
                $orderedGrades[] = $grade;
            }
        }
        foreach (array_keys($counts) as $grade) {
            if (!in_array($grade, $orderedGrades, true)) {
                $orderedGrades[] = $grade;
            }
        }

        $summary = [];
        foreach ($orderedGrades as $grade) {
            if (!empty($counts[$grade])) {
                $summary[] = $counts[$grade] . $grade;
            }
        }

        return implode(', ', $summary);
    }
}

if (!function_exists('get_result_midterm_ca_indices')) {
    function get_result_midterm_ca_indices($link, $classId)
    {
        $classId = (int) $classId;
        if ($classId <= 0) {
            return [];
        }

        $settingResult = mysqli_query(
            $link,
            "SELECT rs.MidTermCaToUse
             FROM `resultsetting` rs
             INNER JOIN `assigncatoclass` acc ON acc.ResultSettingID = rs.ResultSettingID
             WHERE acc.ClassID = '$classId'
             LIMIT 1"
        );
        $setting = $settingResult ? mysqli_fetch_assoc($settingResult) : null;
        $indices = array_filter(array_map('intval', explode(',', (string) ($setting['MidTermCaToUse'] ?? ''))));

        return array_values(array_unique(array_filter($indices, function ($index) {
            return $index >= 1 && $index <= 10;
        })));
    }
}

if (!function_exists('result_midterm_score_expression')) {
    function result_midterm_score_expression(array $indices, $alias = 'score')
    {
        $prefix = $alias !== '' ? rtrim($alias, '.') . '.' : '';
        $parts = [];
        $seen = [];
        foreach ($indices as $index) {
            $index = (int) $index;
            if ($index >= 1 && $index <= 10 && !isset($seen[$index])) {
                $parts[] = 'COALESCE(' . $prefix . '`ca' . $index . '`, 0)';
                $seen[$index] = true;
            }
        }

        return empty($parts) ? '' : '(' . implode(' + ', $parts) . ')';
    }
}

if (!function_exists('get_result_subject_scores')) {
    function get_result_subject_scores($link, $studentId, $sessionId, $classId, $sectionId, $term, $resultSubtype)
    {
        $studentId = (int) $studentId;
        $sessionId = (int) $sessionId;
        $classId = (int) $classId;
        $sectionId = (int) $sectionId;
        $termSafe = mysqli_real_escape_string($link, (string) $term);
        $resultSubtype = strtolower(trim((string) $resultSubtype));
        $expression = promotion_score_expression('score');
        $scored = promotion_scored_row_condition('score');
        $scope = "score.StudentID = '$studentId'
                  AND score.Session = '$sessionId'
                  AND score.ClassID = '$classId'
                  AND score.SectionID = '$sectionId'
                  AND score.SubjectID != 0";

        if ($resultSubtype === 'cummulative' || $resultSubtype === 'cumulative') {
            $sql = "SELECT score.SubjectID AS subject_id,
                           SUM($expression) / COUNT(DISTINCT score.Term) AS subject_score
                    FROM `score`
                    WHERE $scope AND $scored
                    GROUP BY score.SubjectID";
        } elseif ($resultSubtype === 'midterm' || $resultSubtype === 'mid-term') {
            $midtermExpression = result_midterm_score_expression(
                get_result_midterm_ca_indices($link, $classId),
                'score'
            );
            if ($midtermExpression === '') {
                return [];
            }
            $sql = "SELECT score.SubjectID AS subject_id,
                           SUM($midtermExpression) AS subject_score
                    FROM `score`
                    WHERE $scope
                      AND score.Term = '$termSafe'
                      AND $midtermExpression != 0
                    GROUP BY score.SubjectID";
        } else {
            $sql = "SELECT score.SubjectID AS subject_id,
                           SUM($expression) AS subject_score
                    FROM `score`
                    WHERE $scope
                      AND score.Term = '$termSafe'
                      AND $scored
                    GROUP BY score.SubjectID";
        }

        $result = mysqli_query($link, $sql);
        $scores = [];
        if ($result) {
            while ($row = mysqli_fetch_assoc($result)) {
                $scores[(int) $row['subject_id']] = round((float) $row['subject_score'], 2);
            }
        }

        return $scores;
    }
}

if (!function_exists('get_result_bearing_class_count')) {
    function get_result_bearing_class_count(
        $link,
        $sessionId,
        $classId,
        $sectionId,
        $term,
        $resultSubtype,
        $format = 'numeric',
        $assessmentId = 0
    ) {
        $sessionId = (int) $sessionId;
        $classId = (int) $classId;
        $sectionId = (int) $sectionId;
        $assessmentId = (int) $assessmentId;
        $termSafe = mysqli_real_escape_string($link, (string) $term);
        $format = strtolower(trim((string) $format));
        $resultSubtype = strtolower(trim((string) $resultSubtype));

        if ($format === 'british') {
            $join = "INNER JOIN `britishresult` result_rows
                        ON result_rows.StudentID = ss.student_id
                       AND result_rows.Session = ss.session_id
                       AND result_rows.ClassID = ss.class_id
                       AND result_rows.SectionID = ss.section_id
                       AND result_rows.Term = '$termSafe'
                       AND (
                           TRIM(COALESCE(result_rows.Remark, '')) != ''
                           OR TRIM(COALESCE(result_rows.AdditionalComments, '')) != ''
                       )";
        } elseif ($format === 'kindergarten') {
            $assessmentCondition = $assessmentId > 0 ? " AND result_rows.assessment_id = '$assessmentId'" : '';
            $join = "INNER JOIN `kindergarten_result` result_rows
                       ON result_rows.student_id = ss.student_id
                       AND result_rows.session_id = ss.session_id
                       AND result_rows.term = '$termSafe'
                       AND result_rows.result_label_index IS NOT NULL
                       $assessmentCondition";
        } else {
            $termCondition = ($resultSubtype === 'cummulative' || $resultSubtype === 'cumulative')
                ? ''
                : " AND result_rows.Term = '$termSafe'";
            if ($resultSubtype === 'midterm' || $resultSubtype === 'mid-term') {
                $midtermExpression = result_midterm_score_expression(
                    get_result_midterm_ca_indices($link, $classId),
                    'result_rows'
                );
                if ($midtermExpression === '') {
                    return 0;
                }
                $scored = $midtermExpression . ' != 0';
            } else {
                $scored = promotion_scored_row_condition('result_rows');
            }
            $join = "INNER JOIN `score` result_rows
                        ON result_rows.StudentID = ss.student_id
                       AND result_rows.Session = ss.session_id
                       AND result_rows.ClassID = ss.class_id
                       AND result_rows.SectionID = ss.section_id
                       AND result_rows.SubjectID != 0
                       $termCondition
                       AND $scored";
        }

        $sql = "SELECT COUNT(DISTINCT ss.student_id) AS student_count
                FROM `student_session` ss
                INNER JOIN `students` students ON students.id = ss.student_id
                $join
                WHERE ss.session_id = '$sessionId'
                  AND ss.class_id = '$classId'
                  AND ss.section_id = '$sectionId'
                  AND students.is_active = 'yes'";
        $result = mysqli_query($link, $sql);
        $row = $result ? mysqli_fetch_assoc($result) : null;

        return (int) ($row['student_count'] ?? 0);
    }
}

if (!function_exists('get_result_summary_context')) {
    function get_result_summary_context(
        $link,
        $studentId,
        $sessionId,
        $classId,
        $sectionId,
        $term,
        $resultSubtype,
        $format = 'numeric',
        $assessmentId = 0
    ) {
        $format = strtolower(trim((string) $format));
        $resultSubtype = strtolower(trim((string) $resultSubtype));
        $context = [
            'number_in_class' => get_result_bearing_class_count(
                $link,
                $sessionId,
                $classId,
                $sectionId,
                $term,
                $resultSubtype,
                $format,
                $assessmentId
            ),
            'grade_key' => [],
            'grade_summary' => '',
            'cumulative_average' => null,
            'academic_row_count' => 0,
        ];

        if ($format === 'british' || $format === 'kindergarten') {
            return $context;
        }

        $gradingType = ($resultSubtype === 'midterm' || $resultSubtype === 'mid-term') ? 'midterm' : 'term';
        $gradingRows = get_result_grading_rows($link, $classId, $gradingType);
        $scores = get_result_subject_scores(
            $link,
            $studentId,
            $sessionId,
            $classId,
            $sectionId,
            $term,
            $resultSubtype
        );
        $grades = [];
        foreach ($scores as $score) {
            $grades[] = find_result_grade($score, $gradingRows);
        }
        $annualData = get_student_annual_score_data($link, $studentId, $sessionId, $classId, $sectionId);

        $context['grade_key'] = build_result_grade_key($gradingRows);
        $context['grade_summary'] = build_result_grade_summary($grades, $gradingRows);
        $context['cumulative_average'] = $annualData['overall_average'];
        $context['academic_row_count'] = count($scores);

        return $context;
    }
}
