<?php
include '../db.php';
include '../auth.php';
require_member();

$borrowerId = active_borrower_id();
$amount = (float)($_POST['amount'] ?? 0);
$months = (float)($_POST['months'] ?? 0);

if (!$borrowerId || $amount <= 0 || $months <= 0) {
    exit("Amount and months are required");
}

if ($months > 6) {
    exit("Maximum payment term is 6 months");
}

$stmt = $conn->prepare("
    INSERT INTO loan_requests
    (borrower_id, requested_amount, requested_months)
    VALUES (?, ?, ?)
");
$stmt->bind_param("idd", $borrowerId, $amount, $months);
$stmt->execute();

header("Location: ../member_dashboard.php?loan_requested=1");
exit;
