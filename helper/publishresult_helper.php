<?php

if (!function_exists('normalize_publishresult_reltype')) {
    function normalize_publishresult_reltype($value)
    {
        $value = strtolower(trim((string) $value));

        if ($value === 'midterm' || $value === 'mid-term') {
            return 'midterm';
        }

        if ($value === 'termly') {
            return 'termly';
        }

        if ($value === 'cummulative' || $value === 'cumulative') {
            return 'cummulative';
        }

        return '';
    }
}

if (!function_exists('normalize_publishresult_term')) {
    function normalize_publishresult_term($value, $reltype)
    {
        $reltype = normalize_publishresult_reltype($reltype);

        if ($reltype === 'cummulative') {
            // The annual cumulative card is session-wide, but its term-specific
            // attendance, comments and resumption metadata come from third term.
            return '3rd';
        }

        $value = strtolower(trim((string) $value));

        return in_array($value, ['1st', '2nd', '3rd'], true) ? $value : '';
    }
}

if (!function_exists('is_valid_publishresult_date')) {
    function is_valid_publishresult_date($value)
    {
        $value = trim((string) $value);
        $date = DateTime::createFromFormat('!Y-m-d', $value);

        return $date instanceof DateTime && $date->format('Y-m-d') === $value;
    }
}

if (!function_exists('get_publishresult_validation_error')) {
    function get_publishresult_validation_error(
        $session,
        $term,
        $reltype,
        $classId,
        $sectionId,
        $displayDate = null,
        $allowLegacyGlobalScope = false
    ) {
        $normalizedReltype = normalize_publishresult_reltype($reltype);

        if ($normalizedReltype === '') {
            return 'Please select a valid result type.';
        }

        $classId = (int) $classId;
        $sectionId = (int) $sectionId;
        $hasExactScope = $classId > 0 && $sectionId > 0;
        $hasLegacyGlobalScope = $allowLegacyGlobalScope && $classId === 0 && $sectionId === 0;

        if ((int) $session <= 0 || (!$hasExactScope && !$hasLegacyGlobalScope)) {
            return 'Please select a valid session, class and section.';
        }

        if ($normalizedReltype !== 'cummulative' && normalize_publishresult_term($term, $normalizedReltype) === '') {
            return 'Please select a valid term.';
        }

        if ($displayDate !== null && !is_valid_publishresult_date($displayDate)) {
            return 'Please select a valid publication date.';
        }

        return '';
    }
}

if (!function_exists('get_publish_result_default_roles')) {
    function get_publish_result_default_roles()
    {
        return ['Admin', 'Super Admin', 'Head Teacher'];
    }
}

if (!function_exists('ensure_publish_result_permission_setup')) {
    function ensure_publish_result_permission_setup($link)
    {
        $groupShortCode = 'exam_setting';
        $groupName = 'Exam Setting';
        $permissionShortCode = 'publish_result';
        $permissionName = 'Publish Result';
        $createdAt = date('Y-m-d H:i:s');

        $groupShortCodeSafe = mysqli_real_escape_string($link, $groupShortCode);
        $groupNameSafe = mysqli_real_escape_string($link, $groupName);
        $permissionShortCodeSafe = mysqli_real_escape_string($link, $permissionShortCode);
        $permissionNameSafe = mysqli_real_escape_string($link, $permissionName);

        $groupId = 0;
        $groupResult = mysqli_query(
            $link,
            "SELECT `id`
             FROM `permission_group`
             WHERE `short_code` = '$groupShortCodeSafe'
             LIMIT 1"
        );

        if ($groupResult && mysqli_num_rows($groupResult) > 0) {
            $groupRow = mysqli_fetch_assoc($groupResult);
            $groupId = (int) ($groupRow['id'] ?? 0);
        }

        if ($groupId <= 0) {
            mysqli_query(
                $link,
                "INSERT INTO `permission_group`(`name`, `short_code`, `is_active`, `system`, `created_at`)
                 VALUES ('$groupNameSafe', '$groupShortCodeSafe', '1', '0', '$createdAt')"
            );
            $groupId = (int) mysqli_insert_id($link);
        }

        $permissionId = 0;
        $permissionResult = mysqli_query(
            $link,
            "SELECT `id`
             FROM `permission_category`
             WHERE `short_code` = '$permissionShortCodeSafe'
             LIMIT 1"
        );

        if ($permissionResult && mysqli_num_rows($permissionResult) > 0) {
            $permissionRow = mysqli_fetch_assoc($permissionResult);
            $permissionId = (int) ($permissionRow['id'] ?? 0);
            mysqli_query(
                $link,
                "UPDATE `permission_category`
                 SET `perm_group_id` = '$groupId',
                     `name` = '$permissionNameSafe',
                     `enable_view` = '0',
                     `enable_add` = '0',
                     `enable_edit` = '1',
                     `enable_delete` = '0'
                 WHERE `id` = '$permissionId'"
            );
        } else {
            mysqli_query(
                $link,
                "INSERT INTO `permission_category`(`perm_group_id`, `name`, `short_code`, `enable_view`, `enable_add`, `enable_edit`, `enable_delete`, `created_at`)
                 VALUES ('$groupId', '$permissionNameSafe', '$permissionShortCodeSafe', '0', '0', '1', '0', '$createdAt')"
            );
            $permissionId = (int) mysqli_insert_id($link);
        }

        if ($permissionId <= 0) {
            return 0;
        }

        $roleNames = get_publish_result_default_roles();
        $escapedRoleNames = [];
        foreach ($roleNames as $roleName) {
            $escapedRoleNames[] = "'" . mysqli_real_escape_string($link, $roleName) . "'";
        }

        $rolesResult = mysqli_query(
            $link,
            "SELECT `id`
             FROM `roles`
             WHERE `name` IN (" . implode(', ', $escapedRoleNames) . ")"
        );

        if (!$rolesResult) {
            return $permissionId;
        }

        $defaultRoleIds = [];
        while ($roleRow = mysqli_fetch_assoc($rolesResult)) {
            $defaultRoleIds[] = (int) ($roleRow['id'] ?? 0);
        }

        if (empty($defaultRoleIds)) {
            return $permissionId;
        }

        $existingResult = mysqli_query(
            $link,
            "SELECT `role_id`
             FROM `roles_permissions`
             WHERE `perm_cat_id` = '$permissionId'"
        );

        $existingRoleIds = [];
        if ($existingResult) {
            while ($existingRow = mysqli_fetch_assoc($existingResult)) {
                $existingRoleIds[] = (int) ($existingRow['role_id'] ?? 0);
            }
        }

        foreach ($defaultRoleIds as $roleId) {
            if ($roleId <= 0 || in_array($roleId, $existingRoleIds, true)) {
                continue;
            }

            mysqli_query(
                $link,
                "INSERT INTO `roles_permissions`(`role_id`, `perm_cat_id`, `can_view`, `can_add`, `can_edit`, `can_delete`, `created_at`)
                 VALUES ('$roleId', '$permissionId', '0', '0', '1', '0', '$createdAt')"
            );
        }

        return $permissionId;
    }
}

if (!function_exists('can_staff_publish_result')) {
    function can_staff_publish_result($link, $staffId, $rolefirst)
    {
        $staffId = (int) $staffId;

        if ($staffId <= 0) {
            return false;
        }

        $staffCheck = mysqli_query(
            $link,
            "SELECT `id`
             FROM `staff`
             WHERE `id` = '$staffId'
               AND `is_active` = '1'
             LIMIT 1"
        );

        if (!$staffCheck || mysqli_num_rows($staffCheck) === 0) {
            return false;
        }

        ensure_publish_result_permission_setup($link);

        $superAdminResult = mysqli_query(
            $link,
            "SELECT 1
             FROM `staff_roles`
             INNER JOIN `roles` ON `staff_roles`.`role_id` = `roles`.`id`
             WHERE `staff_roles`.`staff_id` = '$staffId'
               AND `roles`.`name` = 'Super Admin'
             LIMIT 1"
        );

        if ($superAdminResult && mysqli_num_rows($superAdminResult) > 0) {
            return true;
        }

        $sql = "SELECT 1
                FROM `staff_roles`
                INNER JOIN `roles_permissions` ON `staff_roles`.`role_id` = `roles_permissions`.`role_id`
                INNER JOIN `permission_category` ON `roles_permissions`.`perm_cat_id` = `permission_category`.`id`
                WHERE `staff_roles`.`staff_id` = '$staffId'
                  AND `permission_category`.`short_code` = 'publish_result'
                  AND `roles_permissions`.`can_edit` = '1'
                LIMIT 1";
        $result = mysqli_query($link, $sql);

        return $result && mysqli_num_rows($result) > 0;
    }
}

if (!function_exists('build_publishresult_where_clause')) {
    function build_publishresult_where_clause(
        $link,
        $session,
        $term,
        $reltype,
        $classId,
        $sectionId,
        $dateLimit = null,
        $allowLegacyGlobalScope = false
    )
    {
        $session = (int) $session;
        $classId = (int) $classId;
        $sectionId = (int) $sectionId;
        $reltype = normalize_publishresult_reltype($reltype);
        $term = normalize_publishresult_term($term, $reltype);
        $validationError = get_publishresult_validation_error(
            $session,
            $term,
            $reltype,
            $classId,
            $sectionId,
            null,
            $allowLegacyGlobalScope
        );

        if ($validationError !== '' || ($dateLimit !== null && !is_valid_publishresult_date($dateLimit))) {
            return '1 = 0';
        }

        $termSafe = mysqli_real_escape_string($link, $term);
        $reltypeSafe = mysqli_real_escape_string($link, $reltype);

        $conditions = [
            "`Session` = '$session'",
            "`ClassID` = '$classId'",
            "`SectionID` = '$sectionId'",
        ];

        if ($reltype === 'cummulative') {
            $cumulativeAliasSafe = mysqli_real_escape_string($link, 'cumulative');
            $conditions[] = "`ResultType` IN ('$reltypeSafe', '$cumulativeAliasSafe')";
        } else {
            $conditions[] = "`ResultType` = '$reltypeSafe'";
            $conditions[] = "`Term` = '$termSafe'";
        }

        if ($dateLimit !== null) {
            $dateLimitSafe = mysqli_real_escape_string($link, trim((string) $dateLimit));
            $conditions[] = "`Date` <= '$dateLimitSafe'";
        }

        return implode(' AND ', $conditions);
    }
}

if (!function_exists('find_publishresult_record')) {
    function find_publishresult_record(
        $link,
        $session,
        $term,
        $reltype,
        $classId,
        $sectionId,
        $dateLimit = null,
        $allowLegacyGlobalFallback = true
    )
    {
        $whereClause = build_publishresult_where_clause($link, $session, $term, $reltype, $classId, $sectionId, $dateLimit);
        $sql = "SELECT *
                FROM `publishresult`
                WHERE $whereClause
                ORDER BY `id` DESC
                LIMIT 1";
        $result = mysqli_query($link, $sql);

        if ($result && mysqli_num_rows($result) > 0) {
            return mysqli_fetch_assoc($result);
        }

        if (!$allowLegacyGlobalFallback || (int) $classId <= 0 || (int) $sectionId <= 0) {
            return null;
        }

        if ($dateLimit !== null) {
            $undatedExactWhereClause = build_publishresult_where_clause(
                $link,
                $session,
                $term,
                $reltype,
                $classId,
                $sectionId
            );
            $undatedExactSql = "SELECT `id`
                                FROM `publishresult`
                                WHERE $undatedExactWhereClause
                                ORDER BY `id` DESC
                                LIMIT 1";
            $undatedExactResult = mysqli_query($link, $undatedExactSql);

            // A scheduled exact publication must shadow an older global record
            // until its own publication date arrives.
            if ($undatedExactResult && mysqli_num_rows($undatedExactResult) > 0) {
                return null;
            }
        }

        $legacyWhereClause = build_publishresult_where_clause(
            $link,
            $session,
            $term,
            $reltype,
            0,
            0,
            $dateLimit,
            true
        );
        $legacySql = "SELECT *
                      FROM `publishresult`
                      WHERE $legacyWhereClause
                      ORDER BY `id` DESC
                      LIMIT 1";
        $legacyResult = mysqli_query($link, $legacySql);

        return ($legacyResult && mysqli_num_rows($legacyResult) > 0)
            ? mysqli_fetch_assoc($legacyResult)
            : null;
    }
}

if (!function_exists('save_publishresult_record')) {
    function save_publishresult_record($link, $session, $term, $reltype, $classId, $sectionId, $displayDate)
    {
        $session = (int) $session;
        $classId = (int) $classId;
        $sectionId = (int) $sectionId;
        $reltype = normalize_publishresult_reltype($reltype);
        $term = normalize_publishresult_term($term, $reltype);
        $displayDate = trim((string) $displayDate);

        if (get_publishresult_validation_error(
            $session,
            $term,
            $reltype,
            $classId,
            $sectionId,
            $displayDate
        ) !== '') {
            return false;
        }

        $termSafe = mysqli_real_escape_string($link, $term);
        $reltypeSafe = mysqli_real_escape_string($link, $reltype);
        $displayDateSafe = mysqli_real_escape_string($link, $displayDate);

        $existing = find_publishresult_record(
            $link,
            $session,
            $term,
            $reltype,
            $classId,
            $sectionId,
            null,
            false
        );

        if (!empty($existing['id'])) {
            $publishId = (int) $existing['id'];
            $sql = "UPDATE `publishresult`
                    SET `Term` = '$termSafe',
                        `ResultType` = '$reltypeSafe',
                        `Date` = '$displayDateSafe'
                    WHERE `id` = '$publishId'";

            return mysqli_query($link, $sql);
        }

        $sql = "INSERT INTO `publishresult`
                    (`Session`, `Term`, `ClassID`, `SectionID`, `ResultType`, `Date`)
                VALUES
                    ('$session', '$termSafe', '$classId', '$sectionId', '$reltypeSafe', '$displayDateSafe')";

        return mysqli_query($link, $sql);
    }
}
