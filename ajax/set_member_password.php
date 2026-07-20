<?php
include '../db.php';
include '../auth.php';

header('Content-Type: application/json');

$userId = (int)($_SESSION['pending_member_user_id'] ?? 0);
$password = trim($_POST['password'] ?? '');

if (!$userId) {
    echo json_encode(["error" => "Password setup session expired"]);
    exit;
}

if (!preg_match('/^\d{8}$/', $password)) {
    echo json_encode(["error" => "Use MMDDYYYY format"]);
    exit;
}

$hash = password_hash($password, PASSWORD_DEFAULT);

$stmt = $conn->prepare("UPDATE users SET password = ? WHERE id = ? AND status = 'Member'");
$stmt->bind_param("si", $hash, $userId);
$stmt->execute();

$userStmt = $conn->prepare("SELECT id, username, status, borrower_id FROM users WHERE id = ? LIMIT 1");
$userStmt->bind_param("i", $userId);
$userStmt->execute();
$user = $userStmt->get_result()->fetch_assoc();

if (!$user) {
    echo json_encode(["error" => "Member account not found"]);
    exit;
}

unset($_SESSION['pending_member_user_id'], $_SESSION['pending_member_username']);

$_SESSION['user_id'] = $user['id'];
$_SESSION['username'] = $user['username'];
$_SESSION['user_status'] = $user['status'];
$_SESSION['borrower_id'] = $user['borrower_id'];
$_SESSION['active_member_user_id'] = $user['id'];
$_SESSION['active_borrower_id'] = $user['borrower_id'];

echo json_encode(["ok" => true]);

