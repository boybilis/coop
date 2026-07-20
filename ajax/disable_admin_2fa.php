<?php
include __DIR__ . '/../db.php';
include __DIR__ . '/../auth.php';
require_admin();

$userId = (int)$_SESSION['user_id'];
$password = $_POST['current_password'] ?? '';

$stmt = $conn->prepare("
    SELECT password
    FROM users
    WHERE id = ?
    AND status IN ('Admin', 'SuperAdmin')
    LIMIT 1
");
$stmt->bind_param("i", $userId);
$stmt->execute();
$user = $stmt->get_result()->fetch_assoc();

if (!$user || !password_verify($password, $user['password'])) {
    audit_log($conn, 'fail_disable_admin_2fa', 'Admin failed to disable 2FA due to invalid password.', 'users', $userId);
    header("Location: ../admin_settings.php?error=" . urlencode("Current password is incorrect."));
    exit;
}

$updateStmt = $conn->prepare("
    UPDATE users
    SET two_factor_secret = NULL,
        two_factor_enabled = 0,
        two_factor_confirmed_at = NULL
    WHERE id = ?
");
$updateStmt->bind_param("i", $userId);
$updateStmt->execute();

audit_log($conn, 'disable_admin_2fa', 'Admin disabled authenticator app 2FA.', 'users', $userId);

header("Location: ../admin_settings.php?two_factor_disabled=1");
exit;
