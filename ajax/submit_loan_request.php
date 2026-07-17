<?php
include '../db.php';
include '../auth.php';
require_member();

$borrowerId = active_borrower_id();
$amount = (float)($_POST['amount'] ?? 0);
$months = (float)($_POST['months'] ?? 0);
$isGuarantor = isset($_POST['is_guarantor']) ? 1 : 0;
$guestBorrowerName = trim($_POST['guest_borrower_name'] ?? '');

if (!$borrowerId || $amount <= 0 || $months <= 0) {
    exit("Amount and months are required");
}

if ($months > 6) {
    exit("Maximum payment term is 6 months");
}

if ($isGuarantor && $guestBorrowerName === '') {
    exit("Guest borrower name is required");
}

if (!$isGuarantor) {
    $guestBorrowerName = null;
}

$stmt = $conn->prepare("
    INSERT INTO loan_requests
    (borrower_id, requested_amount, requested_months, is_guarantor, guest_borrower_name)
    VALUES (?, ?, ?, ?, ?)
");
$stmt->bind_param("iddis", $borrowerId, $amount, $months, $isGuarantor, $guestBorrowerName);
$stmt->execute();

header("Location: ../member_dashboard.php?loan_requested=1");
exit;
