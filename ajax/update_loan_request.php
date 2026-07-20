<?php
include '../db.php';
include '../auth.php';
require_member();

header('Content-Type: application/json');

$borrowerId = active_borrower_id();
$requestId = (int)($_POST['request_id'] ?? 0);
$amount = (float)($_POST['amount'] ?? 0);
$months = (float)($_POST['months'] ?? 0);
$isGuarantor = (int)($_POST['is_guarantor'] ?? 0) === 1 ? 1 : 0;
$guestBorrowerName = trim($_POST['guest_borrower_name'] ?? '');
$guestGcashName = trim($_POST['guest_gcash_name'] ?? '');
$guestGcashNumber = trim($_POST['guest_gcash_number'] ?? '');

if (!$requestId || $amount <= 0 || $months <= 0) {
    echo json_encode(["error" => "Amount and months are required"]);
    exit;
}

if ($months > 6) {
    echo json_encode(["error" => "Maximum payment term is 6 months"]);
    exit;
}

if ($isGuarantor && ($guestBorrowerName === '' || $guestGcashName === '' || $guestGcashNumber === '')) {
    echo json_encode(["error" => "Guest borrower name, GCash name, and GCash number are required"]);
    exit;
}

if (!$isGuarantor) {
    $guestBorrowerName = null;
    $guestGcashName = null;
    $guestGcashNumber = null;
}

$stmt = $conn->prepare("
    UPDATE loan_requests
    SET requested_amount = ?,
        requested_months = ?,
        is_guarantor = ?,
        guest_borrower_name = ?,
        guest_gcash_name = ?,
        guest_gcash_number = ?
    WHERE id = ?
    AND borrower_id = ?
    AND status = 'Pending'
");
$stmt->bind_param("ddisssii", $amount, $months, $isGuarantor, $guestBorrowerName, $guestGcashName, $guestGcashNumber, $requestId, $borrowerId);
$stmt->execute();

if ($stmt->affected_rows === 0) {
    echo json_encode(["error" => "Only pending loan requests can be edited"]);
    exit;
}

echo json_encode(["ok" => true]);

