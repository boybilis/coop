<?php
include __DIR__ . '/../db.php';
include __DIR__ . '/../auth.php';
include __DIR__ . '/../totp.php';
require_admin();

$userId = (int)$_SESSION['user_id'];
$secret = totp_generate_secret();

$stmt = $conn->prepare("
    UPDATE users
    SET two_factor_secret = ?,
        two_factor_enabled = 0,
        two_factor_confirmed_at = NULL
    WHERE id = ?
    AND status IN ('Admin', 'SuperAdmin')
");
$stmt->bind_param("si", $secret, $userId);
$stmt->execute();

audit_log($conn, 'start_admin_2fa_setup', 'Admin started authenticator app 2FA setup.', 'users', $userId);

header("Location: ../admin_settings.php?two_factor_setup=1");
exit;
