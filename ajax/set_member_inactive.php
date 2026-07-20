<?php
include '../db.php';
include '../auth.php';
require_admin();

header('Content-Type: application/json');

$id = (int)($_POST['id'] ?? 0);

if (!$id) {
    echo json_encode(["error" => "Invalid member"]);
    exit;
}

$stmt = $conn->prepare("UPDATE borrowers SET status = 'Inactive' WHERE id = ?");
$stmt->bind_param("i", $id);
$stmt->execute();

echo json_encode([
    "id" => $id,
    "status" => "Inactive"
]);

