<?php
include '../db.php';
include '../auth.php';
require_admin();

$requestId = (int)($_POST['request_id'] ?? 0);

if (!$requestId) {
    header("Location: ../loan_requests.php?error=" . urlencode("Invalid loan request"));
    exit;
}

$stmt = $conn->prepare("
    UPDATE loan_requests
    SET status = 'Rejected',
        processed_at = NOW()
    WHERE id = ?
    AND status = 'Pending'
");
$stmt->bind_param("i", $requestId);
$stmt->execute();

if ($stmt->affected_rows === 0) {
    header("Location: ../loan_requests.php?error=" . urlencode("Only pending loan requests can be rejected"));
    exit;
}

audit_log($conn, 'reject_loan_request', 'Admin rejected a pending loan request.', 'loan_requests', $requestId);

header("Location: ../loan_requests.php?rejected=1");
exit;

