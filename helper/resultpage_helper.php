<?php

if (!function_exists('normalize_result_page_reltype')) {
    function normalize_result_page_reltype($value)
    {
        $normalized = strtolower(trim((string) $value));

        if ($normalized === 'midterm' || $normalized === 'mid-term') {
            return 'midterm';
        }

        if ($normalized === 'termly') {
            return 'termly';
        }

        if ($normalized === 'cummulative' || $normalized === 'cumulative') {
            return 'cummulative';
        }

        return '';
    }
}

if (!function_exists('safe_result_average')) {
    function safe_result_average($total, $count, $precision = 2)
    {
        $count = (int) $count;

        if ($count <= 0) {
            return 0.0;
        }

        return round((float) $total / $count, (int) $precision);
    }
}

if (!function_exists('result_domain_row_has_score')) {
    function result_domain_row_has_score(array $row, $scorePrefix, $scoreCount = 15)
    {
        for ($index = 1; $index <= (int) $scoreCount; $index++) {
            if ((float) ($row[$scorePrefix . $index] ?? 0) !== 0.0) {
                return true;
            }
        }

        return false;
    }
}

if (!function_exists('result_term_rank')) {
    function result_term_rank($term)
    {
        $ranks = [
            '1st' => 1,
            '2nd' => 2,
            '3rd' => 3,
        ];

        return $ranks[strtolower(trim((string) $term))] ?? 0;
    }
}

if (!function_exists('find_latest_scored_result_domain_row')) {
    function find_latest_scored_result_domain_row(array $rows, $scorePrefix, $scoreCount = 15)
    {
        $latestRow = null;
        $latestTermRank = -1;
        $latestId = -1;

        foreach ($rows as $row) {
            if (!is_array($row) || !result_domain_row_has_score($row, $scorePrefix, $scoreCount)) {
                continue;
            }

            $termRank = result_term_rank($row['term'] ?? '');
            if ($termRank <= 0) {
                continue;
            }

            $rowId = (int) ($row['id'] ?? 0);

            if ($termRank > $latestTermRank || ($termRank === $latestTermRank && $rowId > $latestId)) {
                $latestRow = $row;
                $latestTermRank = $termRank;
                $latestId = $rowId;
            }
        }

        return $latestRow;
    }
}

if (!function_exists('get_latest_scored_result_domain_row')) {
    function get_latest_scored_result_domain_row($link, $domainType, $studentId, $classId, $sectionId, $sessionId)
    {
        $domainConfig = [
            'affective' => [
                'table' => 'affective_domain_score',
                'prefix' => 'domain',
            ],
            'psycomotor' => [
                'table' => 'psycomotor_score',
                'prefix' => 'psycomotor',
            ],
        ];

        if (!isset($domainConfig[$domainType])) {
            return null;
        }

        $studentId = (int) $studentId;
        $classId = (int) $classId;
        $sectionId = (int) $sectionId;
        $sessionId = (int) $sessionId;

        if ($studentId <= 0 || $classId <= 0 || $sectionId <= 0 || $sessionId <= 0) {
            return null;
        }

        $table = $domainConfig[$domainType]['table'];
        $sql = "SELECT *
                FROM `$table`
                WHERE studentid = '$studentId'
                  AND classid = '$classId'
                  AND sectionid = '$sectionId'
                  AND session = '$sessionId'";
        $result = mysqli_query($link, $sql);

        if (!$result) {
            return null;
        }

        $rows = [];
        while ($row = mysqli_fetch_assoc($result)) {
            $rows[] = $row;
        }

        return find_latest_scored_result_domain_row(
            $rows,
            $domainConfig[$domainType]['prefix']
        );
    }
}
