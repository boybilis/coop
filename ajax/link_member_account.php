<?php
include '../db.php';
include '../auth.php';
require_member();

header('Content-Type: application/json');

$currentUserId = (int)($_SESSION['user_id'] ?? 0);
$username = trim($_POST['username'] ?? '');
$password = $_POST['password'] ?? '';

if ($username === '' || $password === '') {
    echo json_encode(["error" => "Username and password are required"]);
    exit;
}

$stmt = $conn->prepare("
    SELECT id, username, password, status, borrower_id
    FROM users
    WHERE username = ?
    LIMIT 1
");
$stmt->bind_param("s", $username);
$stmt->execute();
$linkedUser = $stmt->get_result()->fetch_assoc();

if (!$linkedUser || $linkedUser['status'] !== 'Member' || !$linkedUser['borrower_id']) {
    echo json_encode(["error" => "Member account not found"]);
    exit;
}

if ((int)$linkedUser['id'] === $currentUserId) {
    echo json_encode(["error" => "This account is already your current login"]);
    exit;
}

if ($linkedUser['password'] === '') {
    echo json_encode(["error" => "The account password is not yet set"]);
    exit;
}

if (!password_verify($password, $linkedUser['password'])) {
    echo json_encode(["error" => "Invalid linked account password"]);
    exit;
}

$linkStmt = $conn->prepare("
    INSERT IGNORE INTO user_account_links (user_id, linked_user_id)
    VALUES (?, ?), (?, ?)
");
$linkStmt->bind_param(
    "iiii",
    $currentUserId,
    $linkedUser['id'],
    $linkedUser['id'],
    $currentUserId
);
$linkStmt->execute();

echo json_encode([
    "ok" => true,
    "linked_user_id" => $linkedUser['id'],
    "username" => $linkedUser['username']
]);

