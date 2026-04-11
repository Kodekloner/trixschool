<?php

if (!function_exists('normalize_publishresult_reltype')) {
    function normalize_publishresult_reltype($value)
    {
        $value = strtolower(trim((string) $value));

        return in_array($value, ['midterm', 'termly', 'cummulative'], true) ? $value : '';
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
