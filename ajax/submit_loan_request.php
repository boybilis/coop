<?php
include '../db.php';
include '../auth.php';
require_member();

$borrowerId = active_borrower_id();
$amount = (float)($_POST['amount'] ?? 0);
$months = (float)($_POST['months'] ?? 0);
$isGuarantor = isset($_POST['is_guarantor']) ? 1 : 0;
$guestBorrowerName = trim($_POST['guest_borrower_name'] ?? '');
$guestGcashName = trim($_POST['guest_gcash_name'] ?? '');
$guestGcashNumber = trim($_POST['guest_gcash_number'] ?? '');

if (!$borrowerId || $amount <= 0 || $months <= 0) {
    exit("Amount and months are required");
}

if ($months > 6) {
    exit("Maximum payment term is 6 months");
}

if ($isGuarantor && ($guestBorrowerName === '' || $guestGcashName === '' || $guestGcashNumber === '')) {
    exit("Guest borrower name, GCash name, and GCash number are required");
}

if (!$isGuarantor) {
    $guestBorrowerName = null;
    $guestGcashName = null;
    $guestGcashNumber = null;
}

$stmt = $conn->prepare("
    INSERT INTO loan_requests
    (borrower_id, requested_amount, requested_months, is_guarantor, guest_borrower_name, guest_gcash_name, guest_gcash_number)
    VALUES (?, ?, ?, ?, ?, ?, ?)
");
$stmt->bind_param("iddisss", $borrowerId, $amount, $months, $isGuarantor, $guestBorrowerName, $guestGcashName, $guestGcashNumber);
$stmt->execute();

header("Location: ../member_dashboard.php?loan_requested=1");
exit;

