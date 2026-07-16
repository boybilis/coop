<?php
include '../db.php';
include '../auth.php';
require_admin();

$requestId = (int)($_POST['request_id'] ?? 0);
$adminReferenceNumber = trim($_POST['admin_reference_number'] ?? '');

if (!$requestId || $adminReferenceNumber === '') {
    header("Location: ../received_withdrawals.php?error=" . urlencode("Reference number is required"));
    exit;
}

if (!isset($_FILES['admin_proof_image']) || $_FILES['admin_proof_image']['error'] !== UPLOAD_ERR_OK) {
    header("Location: ../received_withdrawals.php?error=" . urlencode("GCash transaction image is required"));
    exit;
}

$stmt = $conn->prepare("
    SELECT *
    FROM savings_withdrawal_requests
    WHERE id = ?
    LIMIT 1
");
$stmt->bind_param("i", $requestId);
$stmt->execute();
$request = $stmt->get_result()->fetch_assoc();

if (!$request) {
    header("Location: ../received_withdrawals.php?error=" . urlencode("Withdrawal request not found"));
    exit;
}

if ($request['status'] !== 'Pending') {
    header("Location: ../received_withdrawals.php?error=" . urlencode("Withdrawal request already processed"));
    exit;
}

$accountStmt = $conn->prepare("SELECT savings_closed FROM borrowers WHERE id = ? LIMIT 1");
$accountStmt->bind_param("i", $request['borrower_id']);
$accountStmt->execute();
$account = $accountStmt->get_result()->fetch_assoc();

if (!$account || (int)$account['savings_closed'] === 1) {
    header("Location: ../received_withdrawals.php?error=" . urlencode("Savings account is already closed"));
    exit;
}

$fileInfo = finfo_open(FILEINFO_MIME_TYPE);
$mimeType = finfo_file($fileInfo, $_FILES['admin_proof_image']['tmp_name']);
finfo_close($fileInfo);

$allowedTypes = [
    'image/jpeg' => 'jpg',
    'image/png' => 'png',
    'image/webp' => 'webp'
];

if (!isset($allowedTypes[$mimeType])) {
    header("Location: ../received_withdrawals.php?error=" . urlencode("Only JPG, PNG, or WEBP images are allowed"));
    exit;
}

$balanceStmt = $conn->prepare("
    SELECT
        IFNULL(SUM(CASE WHEN type = 'DEPOSIT' THEN amount ELSE 0 END),0) -
        IFNULL(SUM(CASE WHEN type = 'WITHDRAWAL' THEN amount ELSE 0 END),0) AS balance
    FROM savings_transactions
    WHERE borrower_id = ?
");
$balanceStmt->bind_param("i", $request['borrower_id']);
$balanceStmt->execute();
$balance = (float)$balanceStmt->get_result()->fetch_assoc()['balance'];

$balanceCents = (int)round($balance * 100);
$amountCents = (int)round((float)$request['amount'] * 100);
$remainingCents = $balanceCents - $amountCents;
$isFullWithdrawal = $amountCents === $balanceCents;

if ($amountCents > $balanceCents) {
    header("Location: ../received_withdrawals.php?error=" . urlencode("Withdrawal amount exceeds available savings"));
    exit;
}

if (!$isFullWithdrawal && $remainingCents < 50000) {
    header("Location: ../received_withdrawals.php?error=" . urlencode("Withdrawal must leave at least ₱500 maintaining balance, unless withdrawing the full savings balance to close the account"));
    exit;
}

$uploadDir = realpath(__DIR__ . '/../uploads/withdrawal_proofs');

if (!$uploadDir) {
    header("Location: ../received_withdrawals.php?error=" . urlencode("Upload directory is missing"));
    exit;
}

$fileName = 'withdrawal_' . $request['borrower_id'] . '_' . time() . '_' . bin2hex(random_bytes(4)) . '.' . $allowedTypes[$mimeType];
$targetPath = $uploadDir . DIRECTORY_SEPARATOR . $fileName;

if (!move_uploaded_file($_FILES['admin_proof_image']['tmp_name'], $targetPath)) {
    header("Location: ../received_withdrawals.php?error=" . urlencode("Unable to save uploaded image"));
    exit;
}

$proofPath = 'uploads/withdrawal_proofs/' . $fileName;

$conn->begin_transaction();

try {
    $remarks = 'Approved withdrawal to GCash: ' . $request['gcash_name'] . ' / ' . $request['gcash_number'] . ' Ref: ' . $adminReferenceNumber;
    $transactionDate = date('Y-m-d');

    $savingsStmt = $conn->prepare("
        INSERT INTO savings_transactions
        (borrower_id, amount, type, transaction_date, remarks)
        VALUES (?, ?, 'WITHDRAWAL', ?, ?)
    ");
    $savingsStmt->bind_param("idss", $request['borrower_id'], $request['amount'], $transactionDate, $remarks);
    $savingsStmt->execute();

    $updateStmt = $conn->prepare("
        UPDATE savings_withdrawal_requests
        SET status = 'Approved',
            admin_reference_number = ?,
            admin_proof_image = ?,
            approved_at = NOW()
        WHERE id = ?
    ");
    $updateStmt->bind_param("ssi", $adminReferenceNumber, $proofPath, $requestId);
    $updateStmt->execute();

    if ($isFullWithdrawal) {
        $closeStmt = $conn->prepare("UPDATE borrowers SET savings_closed = 1 WHERE id = ?");
        $closeStmt->bind_param("i", $request['borrower_id']);
        $closeStmt->execute();
    }

    $conn->commit();
} catch (Throwable $e) {
    $conn->rollback();
    header("Location: ../received_withdrawals.php?error=" . urlencode("Unable to approve withdrawal"));
    exit;
}

header("Location: ../received_withdrawals.php?verified=1");
exit;
