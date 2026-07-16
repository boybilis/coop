<?php
include '../db.php';
include '../auth.php';
require_member();

header('Content-Type: application/json');

$borrowerId = active_borrower_id();
$requestId = (int)($_POST['request_id'] ?? 0);

if (!$requestId) {
    echo json_encode(["error" => "Invalid withdrawal request"]);
    exit;
}

$stmt = $conn->prepare("
    DELETE FROM savings_withdrawal_requests
    WHERE id = ?
    AND borrower_id = ?
    AND status = 'Pending'
");
$stmt->bind_param("ii", $requestId, $borrowerId);
$stmt->execute();

if ($stmt->affected_rows === 0) {
    echo json_encode(["error" => "Only pending withdrawal requests can be deleted"]);
    exit;
}

echo json_encode(["ok" => true]);
