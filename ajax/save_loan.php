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
$interest = (int) ceil($amount * $rate * $months);
$totalPayable = (int) ceil($amount + $interest);

// payments (2 per month)
$totalPayments = (int) ceil($months * 2);

// whole number base payment
$basePayment = floor($totalPayable / $totalPayments);
$remainder = $totalPayable - ($basePayment * $totalPayments);

// =============================
// INSERT LOAN (PREPARED)
// =============================
$loanStmt = $conn->prepare("
    INSERT INTO loans
    (borrower_id, amount, interest, months, total_payable, start_date)
    VALUES (?, ?, ?, ?, ?, ?)
");

$loanStmt->bind_param(
    "idddds",
    $borrower_id,
    $amount,
    $interest,
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
// FIND FIRST CUT-OFF AFTER START DATE
// =============================
$startDate = new DateTime($start);

// possible cutoffs in start month
$cut15 = new DateTime($startDate->format('Y-m-15'));
$cutEOM = new DateTime($startDate->format('Y-m-t'));

// choose first valid cutoff AFTER start
if($cut15 > $startDate){
    $cursor = $cut15;
} else {
    $cursor = $cutEOM;
}

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

    // =============================
    // CURRENT CUT-OFF DATE
    // =============================
    $dueDate = $cursor->format('Y-m-d');

    // =============================
    // MOVE TO NEXT CUT-OFF
    // =============================
    if($cursor->format('d') == '15'){
        // go to end of same month
        $cursor->modify('last day of this month');
    } else {
        // go to next month's 15
        $cursor->modify('first day of next month');
        $cursor->setDate(
            $cursor->format('Y'),
            $cursor->format('m'),
            15
        );
    }

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
    'interest' => $interest,
    'months' => $months,
    'total_payable' => $totalPayable,
    'start_date' => $start
]);

echo "ok";

