<?php
require_once __DIR__ . '/../db.php';
require_once __DIR__ . '/../auth.php';
require_superadmin();

$username = trim($_POST['username'] ?? '');
$password = $_POST['password'] ?? '';
$confirmPassword = $_POST['confirm_password'] ?? '';

if ($username === '' || strlen($password) < 6) {
    header("Location: ../admin_settings.php?error=" . urlencode("Username and a password of at least 6 characters are required."));
    exit;
}

if ($password !== $confirmPassword) {
    header("Location: ../admin_settings.php?error=" . urlencode("Password confirmation does not match."));
    exit;
}

$existingStmt = $conn->prepare("SELECT id FROM users WHERE username = ? LIMIT 1");
$existingStmt->bind_param("s", $username);
$existingStmt->execute();

if ($existingStmt->get_result()->fetch_assoc()) {
    header("Location: ../admin_settings.php?error=" . urlencode("Username already exists."));
    exit;
}

$hash = password_hash($password, PASSWORD_DEFAULT);
$stmt = $conn->prepare("
    INSERT INTO users (username, password, status, borrower_id)
    VALUES (?, ?, 'Admin', NULL)
");
$stmt->bind_param("ss", $username, $hash);
$stmt->execute();
$newAdminId = $stmt->insert_id;

audit_log($conn, 'create_admin_user', 'SuperAdmin created a new admin user.', 'users', $newAdminId, [
    'created_username' => $username
]);

header("Location: ../admin_settings.php?admin_created=1");
exit;
