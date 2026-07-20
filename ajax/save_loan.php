<?php
include '../db.php';
include '../auth.php';
require_admin();

// =============================
// INPUTS
// =============================
$borrower_id = (int)($_POST['borrower_id'] ?? 0);
$amount = (float)($_POST['amount'] ?? 0);
$months = (float)($_POST['months'] ?? 0);
$start = $_POST['start_date'] ?? null;

if(!$borrower_id || !$amount || !$months || !$start){
    exit("Missing fields");
}

// =============================
// LOAN COMPUTATION
// =============================
$effectiveRate = cooperative_effective_interest_rate($conn, $start);
$rate = ((float)$effectiveRate['monthly_rate']) / 100;
$effectiveServiceFeeRate = cooperative_effective_service_fee_rate($conn, $start);
$serviceFeeRate = ((float)$effectiveServiceFeeRate['service_fee_rate']) / 100;
$paymentScheduleSetting = cooperative_effective_payment_schedule_setting($conn, $start);
$interest = (int) ceil($amount * $rate * $months);
$serviceFee = (int) ceil($amount * $serviceFeeRate);
$totalPayable = (int) ceil($amount + $interest + $serviceFee);

$dueDates = cooperative_generate_loan_due_dates($start, $months, $paymentScheduleSetting);
$totalPayments = count($dueDates);

// whole number base payment
$basePayment = floor($totalPayable / $totalPayments);
$remainder = $totalPayable - ($basePayment * $totalPayments);

// =============================
// INSERT LOAN (PREPARED)
// =============================
$loanStmt = $conn->prepare("
    INSERT INTO loans
    (borrower_id, amount, interest, service_fee, months, total_payable, start_date)
    VALUES (?, ?, ?, ?, ?, ?, ?)
");

$loanStmt->bind_param(
    "iddddds",
    $borrower_id,
    $amount,
    $interest,
    $serviceFee,
    $months,
    $totalPayable,
    $start
);

$loanStmt->execute();
$loan_id = $loanStmt->insert_id;

// =============================
// PAYMENT INSERT (PREPARED)
// =============================
$payStmt = $conn->prepare("
    INSERT INTO payments
    (loan_id, payment_no, amount, due_date)
    VALUES (?, ?, ?, ?)
");

// =============================
// GENERATE PAYMENTS
// =============================
for($i = 1; $i <= $totalPayments; $i++){

    // =============================
    // PAYMENT AMOUNT RULE
    // =============================
    $amountPay = ($i == $totalPayments)
        ? (int) ceil($basePayment + $remainder)
        : (int) $basePayment;

    $dueDate = $dueDates[$i - 1];

    // =============================
    // INSERT PAYMENT
    // =============================
    $payStmt->bind_param(
        "iids",
        $loan_id,
        $i,
        $amountPay,
        $dueDate
    );

    $payStmt->execute();
}

audit_log($conn, 'save_loan', 'Admin created a direct loan record.', 'loans', $loan_id, [
    'borrower_id' => $borrower_id,
    'amount' => $amount,
    'monthly_rate' => $effectiveRate['monthly_rate'],
    'rate_implementation_date' => $effectiveRate['implementation_date'],
    'service_fee_rate' => $effectiveServiceFeeRate['service_fee_rate'],
    'service_fee_implementation_date' => $effectiveServiceFeeRate['implementation_date'],
    'payment_schedule' => $paymentScheduleSetting,
    'interest' => $interest,
    'service_fee' => $serviceFee,
    'months' => $months,
    'total_payable' => $totalPayable,
    'start_date' => $start
]);

echo "ok";

