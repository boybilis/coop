<?php
include '../db.php';
include '../auth.php';
require_member();

function redirect_withdrawal_notice($notice, $message = '')
{
    $query = 'withdrawal_notice=' . urlencode($notice);

    if ($message !== '') {
        $query .= '&withdrawal_message=' . urlencode($message);
    }

    header("Location: ../member_dashboard.php?" . $query);
    exit;
}

$selectedMemberUserId = (int)($_POST['selected_member_user_id'] ?? active_member_user_id());
$borrowerId = member_borrower_id_for_user($conn, $selectedMemberUserId);
$amount = (float)($_POST['amount'] ?? 0);
$gcashName = trim($_POST['gcash_name'] ?? '');
$gcashNumber = trim($_POST['gcash_number'] ?? '');

if (!$borrowerId) {
    redirect_withdrawal_notice('error', 'Selected account is invalid');
}

$accountStmt = $conn->prepare("SELECT savings_closed FROM borrowers WHERE id = ? LIMIT 1");
$accountStmt->bind_param("i", $borrowerId);
$accountStmt->execute();
$account = $accountStmt->get_result()->fetch_assoc();

if (!$account || (int)$account['savings_closed'] === 1) {
    redirect_withdrawal_notice('error', 'Savings account is closed');
}

if (!$borrowerId || $amount <= 0) {
    redirect_withdrawal_notice('error', 'Withdrawal amount is required');
}

if ($gcashName === '' || $gcashNumber === '') {
    redirect_withdrawal_notice('error', 'GCash name and number are required');
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
    redirect_withdrawal_notice('error', 'Withdrawal amount exceeds available savings');
}

if (!$isFullWithdrawal && $remainingCents < 50000) {
    redirect_withdrawal_notice('error', 'Withdrawal must leave at least ₱500 maintaining balance, unless withdrawing the full savings balance to close the account');
}

$stmt = $conn->prepare("
    INSERT INTO savings_withdrawal_requests
    (borrower_id, amount, gcash_name, gcash_number)
    VALUES (?, ?, ?, ?)
");
$stmt->bind_param("idss", $borrowerId, $amount, $gcashName, $gcashNumber);
$stmt->execute();

redirect_withdrawal_notice($isFullWithdrawal ? 'closing' : 'submitted');
