<?php
include __DIR__ . '/../db.php';
include __DIR__ . '/../auth.php';
include __DIR__ . '/../totp.php';
require_admin();

$userId = (int)$_SESSION['user_id'];
$code = $_POST['two_factor_code'] ?? '';

$stmt = $conn->prepare("
    SELECT two_factor_secret
    FROM users
    WHERE id = ?
    AND status IN ('Admin', 'SuperAdmin')
    LIMIT 1
");
$stmt->bind_param("i", $userId);
$stmt->execute();
$user = $stmt->get_result()->fetch_assoc();

if (!$user || empty($user['two_factor_secret'])) {
    header("Location: ../admin_settings.php?error=" . urlencode("Start authenticator setup first."));
    exit;
}

if (!totp_verify($user['two_factor_secret'], $code)) {
    audit_log($conn, 'fail_admin_2fa_setup', 'Admin entered an invalid authenticator setup code.', 'users', $userId);
    header("Location: ../admin_settings.php?error=" . urlencode("Invalid authenticator code."));
    exit;
}

$updateStmt = $conn->prepare("
    UPDATE users
    SET two_factor_enabled = 1,
        two_factor_confirmed_at = NOW()
    WHERE id = ?
");
$updateStmt->bind_param("i", $userId);
$updateStmt->execute();

audit_log($conn, 'enable_admin_2fa', 'Admin enabled authenticator app 2FA.', 'users', $userId);

header("Location: ../admin_settings.php?two_factor_enabled=1");
exit;
