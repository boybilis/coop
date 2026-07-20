<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

function is_logged_in()
{
    return isset($_SESSION['user_id']);
}

function current_user_status()
{
    return $_SESSION['user_status'] ?? null;
}

function is_admin_user()
{
    return in_array(current_user_status(), ['Admin', 'SuperAdmin'], true);
}

function is_superadmin_user()
{
    return current_user_status() === 'SuperAdmin'
        || (($_SESSION['username'] ?? '') === 'admin' && current_user_status() === 'Admin');
}

function require_login()
{
    if (!is_logged_in()) {
        header("Location: login.php");
        exit;
    }

    if (isset($GLOBALS['conn'])) {
        refresh_logged_in_user($GLOBALS['conn']);
    }
}

function refresh_logged_in_user($conn)
{
    $userId = (int)($_SESSION['user_id'] ?? 0);

    if (!$userId) {
        return;
    }

    $stmt = $conn->prepare("SELECT username, status, borrower_id, two_factor_enabled FROM users WHERE id = ? LIMIT 1");
    $stmt->bind_param("i", $userId);
    $stmt->execute();
    $user = $stmt->get_result()->fetch_assoc();

    if (!$user) {
        return;
    }

    $_SESSION['username'] = $user['username'];
    $_SESSION['user_status'] = $user['status'];
    $_SESSION['borrower_id'] = $user['borrower_id'];
    $_SESSION['two_factor_enabled'] = (int)($user['two_factor_enabled'] ?? 0);
}

function current_script_name()
{
    return basename(parse_url($_SERVER['SCRIPT_NAME'] ?? '', PHP_URL_PATH) ?: '');
}

function admin_two_factor_setup_allowed_page()
{
    $script = current_script_name();
    $requestUri = str_replace('\\', '/', $_SERVER['REQUEST_URI'] ?? '');

    if (in_array($script, ['admin_settings.php', 'logout.php'], true)) {
        return true;
    }

    return strpos($requestUri, '/ajax/start_admin_2fa_setup.php') !== false
        || strpos($requestUri, '/ajax/confirm_admin_2fa.php') !== false;
}

function enforce_admin_two_factor_setup()
{
    if (!is_admin_user()) {
        return;
    }

    if ((int)($_SESSION['two_factor_enabled'] ?? 0) === 1) {
        return;
    }

    if (admin_two_factor_setup_allowed_page()) {
        return;
    }

    $prefix = strpos(str_replace('\\', '/', $_SERVER['REQUEST_URI'] ?? ''), '/ajax/') !== false ? '../' : '';
    header("Location: {$prefix}admin_settings.php?force_2fa=1#twoFactorSetupCard");
    exit;
}

function require_admin()
{
    require_login();

    if (!is_admin_user()) {
        http_response_code(403);
        exit("Access denied");
    }

    enforce_admin_two_factor_setup();
}

function require_superadmin()
{
    require_login();

    if (!is_superadmin_user()) {
        http_response_code(403);
        exit("Access denied");
    }

    enforce_admin_two_factor_setup();
}

function require_member()
{
    require_login();

    if (current_user_status() !== 'Member') {
        http_response_code(403);
        exit("Access denied");
    }
}

function active_member_user_id()
{
    return (int)($_SESSION['active_member_user_id'] ?? $_SESSION['user_id'] ?? 0);
}

function active_borrower_id()
{
    return (int)($_SESSION['active_borrower_id'] ?? $_SESSION['borrower_id'] ?? 0);
}

function member_borrower_id_for_user($conn, $selectedUserId)
{
    $currentUserId = (int)($_SESSION['user_id'] ?? 0);
    $selectedUserId = (int)$selectedUserId;

    if (!$currentUserId || !$selectedUserId) {
        return 0;
    }

    if ($selectedUserId === $currentUserId) {
        $stmt = $conn->prepare("SELECT borrower_id FROM users WHERE id = ? AND status = 'Member' LIMIT 1");
        $stmt->bind_param("i", $selectedUserId);
    } else {
        $stmt = $conn->prepare("
            SELECT users.borrower_id
            FROM users
            JOIN user_account_links
                ON user_account_links.linked_user_id = users.id
            WHERE user_account_links.user_id = ?
            AND users.id = ?
            AND users.status = 'Member'
            LIMIT 1
        ");
        $stmt->bind_param("ii", $currentUserId, $selectedUserId);
    }

    $stmt->execute();
    $user = $stmt->get_result()->fetch_assoc();

    return (int)($user['borrower_id'] ?? 0);
}

