<?php

if (!function_exists('normalize_publishresult_reltype')) {
    function normalize_publishresult_reltype($value)
    {
        $value = strtolower(trim((string) $value));

        return in_array($value, ['midterm', 'termly', 'cummulative'], true) ? $value : '';
    }
}

if (!function_exists('build_publishresult_where_clause')) {
    function build_publishresult_where_clause($link, $session, $term, $reltype, $classId, $sectionId, $dateLimit = null)
    {
        $session = (int) $session;
        $classId = (int) $classId;
        $sectionId = (int) $sectionId;
        $reltype = normalize_publishresult_reltype($reltype);
        $termSafe = mysqli_real_escape_string($link, trim((string) $term));
        $reltypeSafe = mysqli_real_escape_string($link, $reltype);

        $conditions = [
            "`Session` = '$session'",
            "`ClassID` = '$classId'",
            "`SectionID` = '$sectionId'",
            "`ResultType` = '$reltypeSafe'",
        ];

        if ($reltype !== 'cummulative') {
            $conditions[] = "`Term` = '$termSafe'";
        }

        if ($dateLimit !== null && trim((string) $dateLimit) !== '') {
            $dateLimitSafe = mysqli_real_escape_string($link, trim((string) $dateLimit));
            $conditions[] = "`Date` <= '$dateLimitSafe'";
        }

        return implode(' AND ', $conditions);
    }
}

if (!function_exists('find_publishresult_record')) {
    function find_publishresult_record($link, $session, $term, $reltype, $classId, $sectionId, $dateLimit = null)
    {
        $whereClause = build_publishresult_where_clause($link, $session, $term, $reltype, $classId, $sectionId, $dateLimit);
        $sql = "SELECT *
                FROM `publishresult`
                WHERE $whereClause
                ORDER BY `id` DESC
                LIMIT 1";
        $result = mysqli_query($link, $sql);

        return ($result && mysqli_num_rows($result) > 0) ? mysqli_fetch_assoc($result) : null;
    }
}

if (!function_exists('save_publishresult_record')) {
    function save_publishresult_record($link, $session, $term, $reltype, $classId, $sectionId, $displayDate)
    {
        $session = (int) $session;
        $classId = (int) $classId;
        $sectionId = (int) $sectionId;
        $reltype = normalize_publishresult_reltype($reltype);
        $termSafe = mysqli_real_escape_string($link, trim((string) $term));
        $reltypeSafe = mysqli_real_escape_string($link, $reltype);
        $displayDateSafe = mysqli_real_escape_string($link, trim((string) $displayDate));

        $existing = find_publishresult_record($link, $session, $term, $reltype, $classId, $sectionId);

        if (!empty($existing['id'])) {
            $publishId = (int) $existing['id'];
            $sql = "UPDATE `publishresult`
                    SET `Date` = '$displayDateSafe'
                    WHERE `id` = '$publishId'";

            return mysqli_query($link, $sql);
        }

        $columns = "`Session`, `ClassID`, `SectionID`, `ResultType`, `Date`";
        $values = "'$session', '$classId', '$sectionId', '$reltypeSafe', '$displayDateSafe'";

        if ($reltype !== 'cummulative') {
            $columns = "`Session`, `Term`, `ClassID`, `SectionID`, `ResultType`, `Date`";
            $values = "'$session', '$termSafe', '$classId', '$sectionId', '$reltypeSafe', '$displayDateSafe'";
        }

        $sql = "INSERT INTO `publishresult`($columns) VALUES ($values)";

        return mysqli_query($link, $sql);
    }
}
