<?php
include '../db.php';
include '../auth.php';
require_admin();

$requestId = (int)($_POST['request_id'] ?? 0);
$amount = (float)($_POST['amount'] ?? 0);
$months = (float)($_POST['months'] ?? 0);

if (!$requestId || $amount <= 0 || $months <= 0) {
    header("Location: ../loan_requests.php?error=" . urlencode("Amount and months are required"));
    exit;
}

if ($months > 6) {
    header("Location: ../loan_requests.php?error=" . urlencode("Maximum payment term is 6 months"));
    exit;
}

$requestStmt = $conn->prepare("
    SELECT *
    FROM loan_requests
    WHERE id = ?
    AND status = 'Pending'
    LIMIT 1
");
$requestStmt->bind_param("i", $requestId);
$requestStmt->execute();
$request = $requestStmt->get_result()->fetch_assoc();

if (!$request) {
    header("Location: ../loan_requests.php?error=" . urlencode("Pending loan request not found"));
    exit;
}

$start = date('Y-m-d');
$rate = 0.02;
$interest = (int) ceil($amount * $rate * $months);
$totalPayable = (int) ceil($amount + $interest);
$totalPayments = (int) ceil($months * 2);
$basePayment = floor($totalPayable / $totalPayments);
$remainder = $totalPayable - ($basePayment * $totalPayments);

$conn->begin_transaction();

try {
    $loanStmt = $conn->prepare("
        INSERT INTO loans
        (borrower_id, amount, interest, months, total_payable, start_date)
        VALUES (?, ?, ?, ?, ?, ?)
    ");
    $loanStmt->bind_param(
        "idddds",
        $request['borrower_id'],
        $amount,
        $interest,
        $months,
        $totalPayable,
        $start
    );
    $loanStmt->execute();
    $loanId = $loanStmt->insert_id;

    $payStmt = $conn->prepare("
        INSERT INTO payments
        (loan_id, payment_no, amount, due_date)
        VALUES (?, ?, ?, ?)
    ");

    $startDate = new DateTime($start);
    $cut15 = new DateTime($startDate->format('Y-m-15'));
    $cutEOM = new DateTime($startDate->format('Y-m-t'));
    $cursor = ($cut15 > $startDate) ? $cut15 : $cutEOM;

    for ($i = 1; $i <= $totalPayments; $i++) {
        $amountPay = ($i == $totalPayments)
            ? (int) ceil($basePayment + $remainder)
            : (int) $basePayment;

        $dueDate = $cursor->format('Y-m-d');

        if ($cursor->format('d') == '15') {
            $cursor->modify('last day of this month');
        } else {
            $cursor->modify('first day of next month');
            $cursor->setDate(
                $cursor->format('Y'),
                $cursor->format('m'),
                15
            );
        }

        $payStmt->bind_param("iids", $loanId, $i, $amountPay, $dueDate);
        $payStmt->execute();
    }

    $updateStmt = $conn->prepare("
        UPDATE loan_requests
        SET status = 'Approved',
            approved_amount = ?,
            approved_months = ?,
            approved_loan_id = ?,
            processed_at = NOW()
        WHERE id = ?
    ");
    $updateStmt->bind_param("ddii", $amount, $months, $loanId, $requestId);
    $updateStmt->execute();

    $conn->commit();
} catch (Throwable $e) {
    $conn->rollback();
    header("Location: ../loan_requests.php?error=" . urlencode("Unable to approve loan request"));
    exit;
}

header("Location: ../loan_requests.php?approved=1");
exit;
