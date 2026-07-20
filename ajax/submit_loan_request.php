<?php
require_once __DIR__ . '/../db.php';
require_once __DIR__ . '/../auth.php';
require_member();

$borrowerId = active_borrower_id();
$amount = (float)($_POST['amount'] ?? 0);
$months = (float)($_POST['months'] ?? 0);
$isGuarantor = isset($_POST['is_guarantor']) ? 1 : 0;
$guestBorrowerName = trim($_POST['guest_borrower_name'] ?? '');
$guestGcashName = trim($_POST['guest_gcash_name'] ?? '');
$guestGcashNumber = trim($_POST['guest_gcash_number'] ?? '');

if (!$borrowerId || $amount <= 0 || $months <= 0) {
    header("Location: ../member_dashboard.php?error=" . urlencode("Amount and months are required"));
    exit;
}

if ($months > 6) {
    header("Location: ../member_dashboard.php?error=" . urlencode("Maximum payment term is 6 months"));
    exit;
}

$loanableBreakdown = cooperative_loanable_amount_breakdown($conn);
$availableLoanAmount = (float)$loanableBreakdown['available_amount'];

if ($amount > $availableLoanAmount) {
    audit_log($conn, 'block_loan_request_over_loanable', 'Member attempted to request a loan above the available loanable amount.', 'borrowers', $borrowerId, [
        'requested_amount' => $amount,
        'available_loanable_amount' => $availableLoanAmount
    ]);

    header("Location: ../member_dashboard.php?error=" . urlencode("Loan request amount cannot exceed the Available Loanable Amount to date. Available: " . number_format($availableLoanAmount, 2) . "."));
    exit;
}

$memberProfileStmt = $conn->prepare("
    SELECT gcash_name, gcash_number
    FROM borrowers
    WHERE id = ?
    LIMIT 1
");
$memberProfileStmt->bind_param("i", $borrowerId);
$memberProfileStmt->execute();
$memberProfile = $memberProfileStmt->get_result()->fetch_assoc();

if (!$memberProfile || trim($memberProfile['gcash_name'] ?? '') === '' || trim($memberProfile['gcash_number'] ?? '') === '') {
    header("Location: ../member_dashboard.php?error=" . urlencode("Please update your profile with GCash name and GCash number before filing a loan request."));
    exit;
}

if ($isGuarantor && ($guestBorrowerName === '' || $guestGcashName === '' || $guestGcashNumber === '')) {
    header("Location: ../member_dashboard.php?error=" . urlencode("Guest borrower name, GCash name, and GCash number are required"));
    exit;
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
$requestId = $stmt->insert_id;

audit_log($conn, 'submit_loan_request', 'Member submitted a loan request.', 'loan_requests', $requestId, [
    'borrower_id' => $borrowerId,
    'requested_amount' => $amount,
    'requested_months' => $months,
    'is_guarantor' => $isGuarantor,
    'guest_borrower_name' => $guestBorrowerName
]);

header("Location: ../member_dashboard.php?loan_requested=1");
exit;

