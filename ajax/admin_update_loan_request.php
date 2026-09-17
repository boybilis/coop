<?php
require_once __DIR__ . '/../db.php';
require_once __DIR__ . '/../auth.php';
require_admin();

function admin_loan_edit_error($message)
{
    header('Location: ../loan_requests.php?error=' . urlencode($message));
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST'
    || !hash_equals((string)($_SESSION['admin_loan_request_edit_token'] ?? ''), (string)($_POST['csrf_token'] ?? ''))
    || empty($_SESSION['admin_loan_request_edit_token'])) {
    admin_loan_edit_error('Invalid edit request. Please reload the page and try again.');
}

$requestId = filter_var($_POST['request_id'] ?? null, FILTER_VALIDATE_INT);
$amount = filter_var($_POST['amount'] ?? null, FILTER_VALIDATE_FLOAT);
$months = filter_var($_POST['months'] ?? null, FILTER_VALIDATE_FLOAT);
$isGuarantor = isset($_POST['is_guarantor']) ? 1 : 0;
$guestBorrowerName = trim((string)($_POST['guest_borrower_name'] ?? ''));
$guestGcashName = trim((string)($_POST['guest_gcash_name'] ?? ''));
$guestGcashNumber = trim((string)($_POST['guest_gcash_number'] ?? ''));

if (!$requestId || $amount === false || $amount <= 0 || $months === false || $months <= 0 || $months > 6) {
    admin_loan_edit_error('Enter a valid amount and a payment term of up to 6 months.');
}

if ($isGuarantor && ($guestBorrowerName === '' || $guestGcashName === '' || $guestGcashNumber === '')) {
    admin_loan_edit_error('Guest borrower name, GCash name, and GCash number are required.');
}

if (strlen($guestBorrowerName) > 150 || strlen($guestGcashName) > 150 || strlen($guestGcashNumber) > 50) {
    admin_loan_edit_error('Guest borrower details exceed the allowed length.');
}

if (!$isGuarantor) {
    $guestBorrowerName = null;
    $guestGcashName = null;
    $guestGcashNumber = null;
}

$conn->begin_transaction();

try {
    $requestStmt = $conn->prepare('SELECT * FROM loan_requests WHERE id = ? AND status = ? LIMIT 1 FOR UPDATE');
    $pending = 'Pending';
    $requestStmt->bind_param('is', $requestId, $pending);
    $requestStmt->execute();
    $previous = $requestStmt->get_result()->fetch_assoc();

    if (!$previous) {
        throw new LogicException('Only pending loan requests can be edited.');
    }

    $updateStmt = $conn->prepare('
        UPDATE loan_requests
        SET requested_amount = ?, requested_months = ?, is_guarantor = ?,
            guest_borrower_name = ?, guest_gcash_name = ?, guest_gcash_number = ?
        WHERE id = ? AND status = ?
    ');
    $updateStmt->bind_param('ddisssis', $amount, $months, $isGuarantor, $guestBorrowerName, $guestGcashName, $guestGcashNumber, $requestId, $pending);
    $updateStmt->execute();

    if ($updateStmt->affected_rows > 0) {
        audit_log($conn, 'admin_update_loan_request', 'Admin edited a pending member loan request.', 'loan_requests', $requestId, [
            'borrower_id' => (int)$previous['borrower_id'],
            'before' => [
                'requested_amount' => $previous['requested_amount'],
                'requested_months' => $previous['requested_months'],
                'is_guarantor' => $previous['is_guarantor'],
                'guest_borrower_name' => $previous['guest_borrower_name'],
                'guest_gcash_name' => $previous['guest_gcash_name'],
                'guest_gcash_number' => $previous['guest_gcash_number']
            ],
            'after' => [
                'requested_amount' => $amount,
                'requested_months' => $months,
                'is_guarantor' => $isGuarantor,
                'guest_borrower_name' => $guestBorrowerName,
                'guest_gcash_name' => $guestGcashName,
                'guest_gcash_number' => $guestGcashNumber
            ]
        ]);
    }

    $conn->commit();
} catch (Throwable $exception) {
    $conn->rollback();
    error_log('Unable to edit loan request: ' . $exception->getMessage());
    admin_loan_edit_error($exception instanceof LogicException ? $exception->getMessage() : 'Unable to update loan request.');
}

header('Location: ../loan_requests.php?updated=1');
exit;
