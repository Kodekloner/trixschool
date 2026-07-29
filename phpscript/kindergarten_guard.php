<?php

/** Security bridge for legacy Kindergarten setup pages outside CodeIgniter. */
function kindergartenDecodeCiSession()
{
    if (empty($_COOKIE['ci_session']) || !preg_match('/^[A-Za-z0-9,-]{20,128}$/', (string) $_COOKIE['ci_session'])) {
        return array();
    }
    $path = rtrim(sys_get_temp_dir(), DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . 'ci_session' . $_COOKIE['ci_session'];
    if (!is_file($path) || filemtime($path) < time() - 7200) {
        return array();
    }
    $serialized = @file_get_contents($path);
    if ($serialized === false || $serialized === '') {
        return array();
    }
    $native = isset($_SESSION) ? $_SESSION : array();
    $_SESSION = array();
    $decoded = @session_decode($serialized) ? $_SESSION : array();
    $_SESSION = $native;
    return is_array($decoded) ? $decoded : array();
}

function kindergartenRequireStaff($requirePost = false, $requireCsrf = false, $capability = 'can_view')
{
    global $link, $rolefirst, $rowstaff;
    if ($requirePost && (!isset($_SERVER['REQUEST_METHOD']) || $_SERVER['REQUEST_METHOD'] !== 'POST')) {
        http_response_code(405);
        exit('Method not allowed.');
    }
    $ci_session = kindergartenDecodeCiSession();
    $admin = isset($ci_session['admin']) && is_array($ci_session['admin']) ? $ci_session['admin'] : array();
    $staff_id = isset($admin['id']) ? (int) $admin['id'] : 0;
    $staff_email = isset($admin['email']) ? (string) $admin['email'] : '';
    $valid_staff = $rolefirst === 'staff'
        && $staff_id > 0
        && !empty($rowstaff)
        && (int) $rowstaff['id'] === $staff_id
        && hash_equals(strtolower((string) $rowstaff['email']), strtolower($staff_email));
    if (!$valid_staff) {
        http_response_code(403);
        exit('A current staff login is required.');
    }
    $capability = in_array($capability, array('can_view', 'can_add', 'can_edit', 'can_delete'), true) ? $capability : 'can_view';
    $permission_sql = "SELECT r.is_superadmin, COALESCE(rp.`{$capability}`, 0) AS allowed "
        . "FROM staff_roles sr INNER JOIN roles r ON r.id = sr.role_id "
        . "LEFT JOIN permission_category pc ON pc.short_code = 'online_examination' "
        . "LEFT JOIN roles_permissions rp ON rp.role_id = sr.role_id AND rp.perm_cat_id = pc.id "
        . "WHERE sr.staff_id = '" . (int) $staff_id . "'";
    $permission_result = mysqli_query($link, $permission_sql);
    $authorized = false;
    while ($permission_result && ($permission = mysqli_fetch_assoc($permission_result))) {
        if ((int) $permission['is_superadmin'] === 1 || (int) $permission['allowed'] === 1) {
            $authorized = true;
            break;
        }
    }
    if (!$authorized) {
        http_response_code(403);
        exit('You do not have permission to manage examination settings.');
    }
    if ($requireCsrf) {
        $expected = isset($_SESSION['kindergarten_csrf_token']) ? (string) $_SESSION['kindergarten_csrf_token'] : '';
        $received = isset($_POST['csrf_token']) ? (string) $_POST['csrf_token'] : '';
        if ($expected === '' || $received === '' || !hash_equals($expected, $received)) {
            http_response_code(403);
            exit('The form expired or failed the security check. Reload the page and try again.');
        }
    }
    return $staff_id;
}

function kindergartenCsrfToken()
{
    if (empty($_SESSION['kindergarten_csrf_token'])) {
        $_SESSION['kindergarten_csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['kindergarten_csrf_token'];
}
