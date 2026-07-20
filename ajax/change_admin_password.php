<?php
require_once __DIR__ . '/../db.php';
require_once __DIR__ . '/../auth.php';
require_admin();

$userId = (int)($_SESSION['user_id'] ?? 0);
$currentPassword = $_POST['current_password'] ?? '';
$newPassword = $_POST['new_password'] ?? '';
$confirmPassword = $_POST['confirm_password'] ?? '';

if ($newPassword === '' || strlen($newPassword) < 6) {
    header("Location: ../admin_settings.php?error=" . urlencode("New password must be at least 6 characters."));
    exit;
}

if ($newPassword !== $confirmPassword) {
    header("Location: ../admin_settings.php?error=" . urlencode("New password confirmation does not match."));
    exit;
}

$stmt = $conn->prepare("SELECT id, username, password FROM users WHERE id = ? AND status IN ('Admin', 'SuperAdmin') LIMIT 1");
$stmt->bind_param("i", $userId);
$stmt->execute();
$user = $stmt->get_result()->fetch_assoc();

if (!$user || !password_verify($currentPassword, $user['password'])) {
    header("Location: ../admin_settings.php?error=" . urlencode("Current password is incorrect."));
    exit;
}

$hash = password_hash($newPassword, PASSWORD_DEFAULT);
$updateStmt = $conn->prepare("UPDATE users SET password = ? WHERE id = ?");
$updateStmt->bind_param("si", $hash, $userId);
$updateStmt->execute();

audit_log($conn, 'change_admin_password', 'Admin changed own password.', 'users', $userId, [
    'username' => $user['username']
]);

header("Location: ../admin_settings.php?password_updated=1");
exit;
