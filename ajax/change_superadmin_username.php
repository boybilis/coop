<?php
require_once __DIR__ . '/../db.php';
require_once __DIR__ . '/../auth.php';
require_superadmin();

$userId = (int)($_SESSION['user_id'] ?? 0);
$username = trim($_POST['username'] ?? '');

if ($username === '') {
    header("Location: ../admin_settings.php?error=" . urlencode("Username is required."));
    exit;
}

$existingStmt = $conn->prepare("
    SELECT id
    FROM users
    WHERE LOWER(username) = LOWER(?)
    AND id != ?
    LIMIT 1
");
$existingStmt->bind_param("si", $username, $userId);
$existingStmt->execute();

if ($existingStmt->get_result()->fetch_assoc()) {
    header("Location: ../admin_settings.php?error=" . urlencode("Username already exists."));
    exit;
}

$oldUsername = $_SESSION['username'] ?? '';
$updateStmt = $conn->prepare("
    UPDATE users
    SET username = ?
    WHERE id = ?
    AND status = 'SuperAdmin'
");
$updateStmt->bind_param("si", $username, $userId);
$updateStmt->execute();

if ($updateStmt->affected_rows === 0 && $oldUsername !== $username) {
    header("Location: ../admin_settings.php?error=" . urlencode("Unable to update SuperAdmin username."));
    exit;
}

$_SESSION['username'] = $username;

audit_log($conn, 'change_superadmin_username', 'SuperAdmin changed own username.', 'users', $userId, [
    'old_username' => $oldUsername,
    'new_username' => $username
]);

header("Location: ../admin_settings.php?username_updated=1");
exit;
