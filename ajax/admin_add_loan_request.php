<?php
require_once __DIR__ . '/../db.php';
require_once __DIR__ . '/../auth.php';
require_admin();

function admin_add_loan_error($message)
{
    header('Location: ../loan_requests.php?error=' . urlencode($message));
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST'
    || empty($_SESSION['admin_loan_request_edit_token'])
    || !hash_equals($_SESSION['admin_loan_request_edit_token'], (string)($_POST['csrf_token'] ?? ''))) {
    admin_add_loan_error('Invalid request. Please reload the page and try again.');
}

$borrowerId = filter_var($_POST['borrower_id'] ?? null, FILTER_VALIDATE_INT);
$amount = filter_var($_POST['amount'] ?? null, FILTER_VALIDATE_FLOAT);
$months = filter_var($_POST['months'] ?? null, FILTER_VALIDATE_FLOAT);
$firstCutoff = (string)($_POST['first_payment_cutoff'] ?? '');
$isGuarantor = isset($_POST['is_guarantor']) ? 1 : 0;
$guestBorrowerName = trim((string)($_POST['guest_borrower_name'] ?? ''));
$guestGcashName = trim((string)($_POST['guest_gcash_name'] ?? ''));
$guestGcashNumber = trim((string)($_POST['guest_gcash_number'] ?? ''));

if (!$borrowerId || $amount === false || $amount <= 0 || $months === false || $months <= 0 || $months > 6) {
    admin_add_loan_error('Select a member, enter a valid amount, and use a term of up to 6 months.');
}

if (!in_array($firstCutoff, cooperative_admin_loan_cutoff_options($conn, date('Y-m-d')), true)) {
    admin_add_loan_error('Select a valid first payment cutoff from the previous month or upcoming dates.');
}

if ($isGuarantor && ($guestBorrowerName === '' || $guestGcashName === '' || $guestGcashNumber === '')) {
    admin_add_loan_error('Guest borrower name, GCash name, and GCash number are required.');
}

if (strlen($guestBorrowerName) > 150 || strlen($guestGcashName) > 150 || strlen($guestGcashNumber) > 50) {
    admin_add_loan_error('Guest borrower details exceed the allowed length.');
}

if (!$isGuarantor) {
    $guestBorrowerName = null;
    $guestGcashName = null;
    $guestGcashNumber = null;
}

$memberStmt = $conn->prepare("
    SELECT borrowers.id FROM borrowers
    JOIN users ON users.borrower_id = borrowers.id AND users.status = 'Member'
    WHERE borrowers.id = ? AND borrowers.status = 'Active'
    LIMIT 1
");
$memberStmt->bind_param('i', $borrowerId);
$memberStmt->execute();

if (!$memberStmt->get_result()->fetch_assoc()) {
    admin_add_loan_error('Selected member is unavailable.');
}

try {
    $conn->begin_transaction();
    $stmt = $conn->prepare("
        INSERT INTO loan_requests
        (borrower_id, requested_amount, requested_months, first_payment_cutoff,
         is_guarantor, guest_borrower_name, guest_gcash_name, guest_gcash_number)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?)
    ");
    $stmt->bind_param('iddsisss', $borrowerId, $amount, $months, $firstCutoff,
        $isGuarantor, $guestBorrowerName, $guestGcashName, $guestGcashNumber);
    $stmt->execute();
    $requestId = $stmt->insert_id;

    audit_log($conn, 'admin_add_loan_request', 'Admin filed a loan request for a member.', 'loan_requests', $requestId, [
        'borrower_id' => $borrowerId,
        'requested_amount' => $amount,
        'requested_months' => $months,
        'first_payment_cutoff' => $firstCutoff,
        'is_guarantor' => $isGuarantor,
        'guest_borrower_name' => $guestBorrowerName
    ]);
    $conn->commit();
} catch (Throwable $exception) {
    $conn->rollback();
    error_log('Unable to add admin loan request: ' . $exception->getMessage());
    admin_add_loan_error('Unable to add loan request. Make sure the first-payment-cutoff migration has been applied.');
}

header('Location: ../loan_requests.php?added=1');
exit;
