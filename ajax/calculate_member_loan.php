<?php
require_once __DIR__ . '/../db.php';
require_once __DIR__ . '/../auth.php';
require_member();

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Invalid calculator request.']);
    exit;
}

$amount = (float)($_POST['amount'] ?? 0);
$months = (float)($_POST['months'] ?? 0);

if (!is_finite($amount) || $amount <= 0) {
    echo json_encode(['error' => 'Enter a valid loan amount greater than zero.']);
    exit;
}
if (!is_finite($months) || $months < 0.1 || $months > 6) {
    echo json_encode(['error' => 'Months to pay must be between 0.1 and 6.']);
    exit;
}

$calculationDate = date('Y-m-d');
$effectiveRate = cooperative_effective_interest_rate($conn, $calculationDate);
$effectiveServiceFeeRate = cooperative_effective_service_fee_rate($conn, $calculationDate);
$paymentScheduleSetting = cooperative_effective_payment_schedule_setting($conn, $calculationDate);

$monthlyRate = (float)$effectiveRate['monthly_rate'];
$serviceFeeRate = (float)$effectiveServiceFeeRate['service_fee_rate'];
$interest = (int)ceil($amount * ($monthlyRate / 100) * $months);
$serviceFee = (int)ceil($amount * ($serviceFeeRate / 100));
$totalPayable = (int)ceil($amount + $interest + $serviceFee);
$dueDates = cooperative_generate_loan_due_dates($calculationDate, $months, $paymentScheduleSetting);
$paymentCount = count($dueDates);

if ($paymentCount < 1) {
    echo json_encode(['error' => 'Unable to generate the payment schedule.']);
    exit;
}

$basePayment = floor($totalPayable / $paymentCount);
$remainder = $totalPayable - ($basePayment * $paymentCount);
$payments = [];

foreach ($dueDates as $index => $dueDate) {
    $paymentAmount = $index === $paymentCount - 1
        ? (int)ceil($basePayment + $remainder)
        : (int)$basePayment;
    $payments[] = [
        'payment_no' => $index + 1,
        'due_date' => $dueDate,
        'amount' => $paymentAmount,
    ];
}

echo json_encode([
    'principal' => round($amount, 2),
    'months' => $months,
    'monthly_rate' => $monthlyRate,
    'interest' => $interest,
    'service_fee_rate' => $serviceFeeRate,
    'service_fee' => $serviceFee,
    'total_payable' => $totalPayable,
    'payments' => $payments,
], JSON_UNESCAPED_SLASHES);
