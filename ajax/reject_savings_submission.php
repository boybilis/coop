<?php
include '../db.php';
include '../auth.php';
require_admin();

$submissionId = (int)($_POST['submission_id'] ?? 0);

if (!$submissionId) {
    header("Location: ../received_savings.php?error=" . urlencode("Invalid savings submission"));
    exit;
}

$stmt = $conn->prepare("
    UPDATE savings_submissions
    SET status = 'Rejected',
        processed_at = NOW()
    WHERE id = ?
    AND status = 'Pending'
");
$stmt->bind_param("i", $submissionId);
$stmt->execute();

if ($stmt->affected_rows === 0) {
    header("Location: ../received_savings.php?error=" . urlencode("Only pending savings submissions can be rejected"));
    exit;
}

audit_log($conn, 'reject_savings_deposit', 'Admin rejected a savings deposit submission.', 'savings_submissions', $submissionId);

header("Location: ../received_savings.php?rejected=1");
exit;

