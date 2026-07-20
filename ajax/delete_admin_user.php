<?php
require_once __DIR__ . '/../db.php';
require_once __DIR__ . '/../auth.php';
require_superadmin();

$adminId = (int)($_POST['admin_id'] ?? 0);

if (!$adminId) {
    header("Location: ../admin_settings.php?error=" . urlencode("Invalid admin user."));
    exit;
}

if ($adminId === (int)($_SESSION['user_id'] ?? 0)) {
    header("Location: ../admin_settings.php?error=" . urlencode("You cannot delete your own account."));
    exit;
}

$stmt = $conn->prepare("SELECT id, username, status FROM users WHERE id = ? AND status IN ('Admin', 'SuperAdmin') LIMIT 1");
$stmt->bind_param("i", $adminId);
$stmt->execute();
$admin = $stmt->get_result()->fetch_assoc();

if (!$admin) {
    header("Location: ../admin_settings.php?error=" . urlencode("Admin user not found."));
    exit;
}

if ($admin['status'] === 'SuperAdmin') {
    header("Location: ../admin_settings.php?error=" . urlencode("SuperAdmin account cannot be deleted."));
    exit;
}

$deleteStmt = $conn->prepare("DELETE FROM users WHERE id = ? AND status = 'Admin'");
$deleteStmt->bind_param("i", $adminId);
$deleteStmt->execute();

audit_log($conn, 'delete_admin_user', 'SuperAdmin deleted an admin user.', 'users', $adminId, [
    'deleted_username' => $admin['username']
]);

header("Location: ../admin_settings.php?admin_deleted=1");
exit;
