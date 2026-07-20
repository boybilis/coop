<?php
include '../db.php';
include '../auth.php';
require_member();

header('Content-Type: application/json');

$borrowerId = active_borrower_id();
$submissionId = (int)($_POST['submission_id'] ?? 0);

if (!$submissionId) {
    echo json_encode(["error" => "Invalid savings submission"]);
    exit;
}

$stmt = $conn->prepare("
    DELETE FROM savings_submissions
    WHERE id = ?
    AND borrower_id = ?
    AND status = 'Pending'
");
$stmt->bind_param("ii", $submissionId, $borrowerId);
$stmt->execute();

if ($stmt->affected_rows === 0) {
    echo json_encode(["error" => "Only pending savings submissions can be deleted"]);
    exit;
}

audit_log($conn, 'delete_savings_deposit', 'Member deleted a pending savings deposit submission.', 'savings_submissions', $submissionId, [
    'borrower_id' => $borrowerId
]);

echo json_encode(["ok" => true]);

