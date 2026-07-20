<?php
include '../db.php';
include '../auth.php';
require_admin();

header('Content-Type: application/json');

$id = (int)($_POST['id'] ?? 0);
$name = trim($_POST['name'] ?? '');
$status = $_POST['status'] ?? 'Active';

if (!$id || $name === '') {
    echo json_encode(["error" => "Member name is required"]);
    exit;
}

if (!in_array($status, ['Active', 'Inactive'], true)) {
    echo json_encode(["error" => "Invalid member status"]);
    exit;
}

$check = $conn->prepare("SELECT id FROM borrowers WHERE LOWER(name) = LOWER(?) AND id != ? LIMIT 1");
$check->bind_param("si", $name, $id);
$check->execute();

if ($check->get_result()->num_rows > 0) {
    echo json_encode(["error" => "Member already exists"]);
    exit;
}

$stmt = $conn->prepare("UPDATE borrowers SET name = ?, status = ? WHERE id = ?");
$stmt->bind_param("ssi", $name, $status, $id);
$stmt->execute();

$userStmt = $conn->prepare("UPDATE users SET username = ?, status = 'Member' WHERE borrower_id = ?");
$userStmt->bind_param("si", $name, $id);
$userStmt->execute();

if ($userStmt->affected_rows === 0) {
    $createUserStmt = $conn->prepare("
        INSERT INTO users (username, password, status, borrower_id)
        VALUES (?, '', 'Member', ?)
    ");
    $createUserStmt->bind_param("si", $name, $id);
    $createUserStmt->execute();
}

echo json_encode([
    "id" => $id,
    "name" => $name,
    "status" => $status
]);

