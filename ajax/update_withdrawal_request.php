<?php
include '../db.php';
include '../auth.php';
require_member();

header('Content-Type: application/json');

$borrowerId = active_borrower_id();
$requestId = (int)($_POST['request_id'] ?? 0);
$amount = (float)($_POST['amount'] ?? 0);
$gcashName = trim($_POST['gcash_name'] ?? '');
$gcashNumber = trim($_POST['gcash_number'] ?? '');

if (!$requestId || $amount <= 0 || $gcashName === '' || $gcashNumber === '') {
    echo json_encode(["error" => "Amount, GCash name, and GCash number are required"]);
    exit;
}

$accountStmt = $conn->prepare("SELECT savings_closed FROM borrowers WHERE id = ? LIMIT 1");
$accountStmt->bind_param("i", $borrowerId);
$accountStmt->execute();
$account = $accountStmt->get_result()->fetch_assoc();

if (!$account || (int)$account['savings_closed'] === 1) {
    echo json_encode(["error" => "Savings account is closed"]);
    exit;
}

$balanceStmt = $conn->prepare("
    SELECT
        IFNULL(SUM(CASE WHEN type = 'DEPOSIT' THEN amount ELSE 0 END),0) -
        IFNULL(SUM(CASE WHEN type = 'WITHDRAWAL' THEN amount ELSE 0 END),0) AS balance
    FROM savings_transactions
    WHERE borrower_id = ?
");
$balanceStmt->bind_param("i", $borrowerId);
$balanceStmt->execute();
$balance = (float)$balanceStmt->get_result()->fetch_assoc()['balance'];

$balanceCents = (int)round($balance * 100);
$amountCents = (int)round($amount * 100);
$remainingCents = $balanceCents - $amountCents;
$isFullWithdrawal = $amountCents === $balanceCents;

if ($amountCents > $balanceCents) {
    echo json_encode(["error" => "Withdrawal amount exceeds available savings"]);
    exit;
}

if (!$isFullWithdrawal && $remainingCents < 50000) {
    echo json_encode(["error" => "Withdrawal must leave at least &#8369;500 maintaining balance, unless withdrawing the full savings balance to close the account"]);
    exit;
}

$stmt = $conn->prepare("
    UPDATE savings_withdrawal_requests
    SET amount = ?,
        gcash_name = ?,
        gcash_number = ?
    WHERE id = ?
    AND borrower_id = ?
    AND status = 'Pending'
");
$stmt->bind_param("dssii", $amount, $gcashName, $gcashNumber, $requestId, $borrowerId);
$stmt->execute();

if ($stmt->affected_rows === 0) {
    echo json_encode(["error" => "Only pending withdrawal requests can be edited"]);
    exit;
}

echo json_encode(["ok" => true]);

