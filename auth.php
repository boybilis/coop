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
    return current_user_status() === 'SuperAdmin';
}

function require_login()
{
    if (!is_logged_in()) {
        header("Location: login.php");
        exit;
    }
}

function require_admin()
{
    require_login();

    if (!is_admin_user()) {
        http_response_code(403);
        exit("Access denied");
    }
}

function require_superadmin()
{
    require_login();

    if (!is_superadmin_user()) {
        http_response_code(403);
        exit("Access denied");
    }
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

