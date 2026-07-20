<?php
include '../db.php';
include '../auth.php';
require_admin();

$requestId = (int)($_POST['request_id'] ?? 0);
$amount = (float)($_POST['amount'] ?? 0);
$months = (float)($_POST['months'] ?? 0);
$disbursementReferenceNumber = trim($_POST['disbursement_reference_number'] ?? '');

if (!$requestId || $amount <= 0 || $months <= 0) {
    header("Location: ../loan_requests.php?error=" . urlencode("Amount and months are required"));
    exit;
}

if ($months > 6) {
    header("Location: ../loan_requests.php?error=" . urlencode("Maximum payment term is 6 months"));
    exit;
}

if ($disbursementReferenceNumber === '') {
    header("Location: ../loan_requests.php?error=" . urlencode("GCash disbursement reference number is required"));
    exit;
}

if (!isset($_FILES['disbursement_proof_image']) || $_FILES['disbursement_proof_image']['error'] !== UPLOAD_ERR_OK) {
    header("Location: ../loan_requests.php?error=" . urlencode("GCash disbursement proof image is required"));
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

$loanableBreakdown = cooperative_loanable_amount_breakdown($conn);
$availableLoanAmount = (float)$loanableBreakdown['available_amount'];

if ($amount > $availableLoanAmount) {
    audit_log($conn, 'block_loan_approval_over_loanable', 'Admin attempted to approve a loan above the available loanable amount.', 'loan_requests', $requestId, [
        'borrower_id' => $request['borrower_id'],
        'requested_approval_amount' => $amount,
        'available_loanable_amount' => $availableLoanAmount
    ]);

    header("Location: ../loan_requests.php?error=" . urlencode("Approved loan amount cannot exceed the Available Loanable Amount to date. Available: " . number_format($availableLoanAmount, 2) . "."));
    exit;
}

$fileInfo = finfo_open(FILEINFO_MIME_TYPE);
$mimeType = finfo_file($fileInfo, $_FILES['disbursement_proof_image']['tmp_name']);
finfo_close($fileInfo);

$allowedTypes = [
    'image/jpeg' => 'jpg',
    'image/png' => 'png',
    'image/webp' => 'webp'
];

if (!isset($allowedTypes[$mimeType])) {
    header("Location: ../loan_requests.php?error=" . urlencode("Only JPG, PNG, or WEBP images are allowed"));
    exit;
}

$uploadDirPath = __DIR__ . '/../uploads/loan_disbursements';

if (!is_dir($uploadDirPath) && !mkdir($uploadDirPath, 0775, true)) {
    header("Location: ../loan_requests.php?error=" . urlencode("Unable to create loan disbursement upload directory"));
    exit;
}

$uploadDir = realpath($uploadDirPath);

$fileName = 'loan_disbursement_' . $request['borrower_id'] . '_' . time() . '_' . bin2hex(random_bytes(4)) . '.' . $allowedTypes[$mimeType];
$targetPath = $uploadDir . DIRECTORY_SEPARATOR . $fileName;

if (!move_uploaded_file($_FILES['disbursement_proof_image']['tmp_name'], $targetPath)) {
    header("Location: ../loan_requests.php?error=" . urlencode("Unable to save disbursement proof image"));
    exit;
}

$proofPath = 'uploads/loan_disbursements/' . $fileName;

$start = date('Y-m-d');
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
$basePayment = floor($totalPayable / $totalPayments);
$remainder = $totalPayable - ($basePayment * $totalPayments);

$conn->begin_transaction();

try {
    $loanStmt = $conn->prepare("
        INSERT INTO loans
        (borrower_id, amount, interest, service_fee, months, total_payable, start_date, is_guarantor, guest_borrower_name, guest_gcash_name, guest_gcash_number, disbursement_reference_number, disbursement_proof_image)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
    ");
    $loanStmt->bind_param(
        "idddddsisssss",
        $request['borrower_id'],
        $amount,
        $interest,
        $serviceFee,
        $months,
        $totalPayable,
        $start,
        $request['is_guarantor'],
        $request['guest_borrower_name'],
        $request['guest_gcash_name'],
        $request['guest_gcash_number'],
        $disbursementReferenceNumber,
        $proofPath
    );
    $loanStmt->execute();
    $loanId = $loanStmt->insert_id;

    $payStmt = $conn->prepare("
        INSERT INTO payments
        (loan_id, payment_no, amount, due_date)
        VALUES (?, ?, ?, ?)
    ");

    for ($i = 1; $i <= $totalPayments; $i++) {
        $amountPay = ($i == $totalPayments)
            ? (int) ceil($basePayment + $remainder)
            : (int) $basePayment;

        $dueDate = $dueDates[$i - 1];

        $payStmt->bind_param("iids", $loanId, $i, $amountPay, $dueDate);
        $payStmt->execute();
    }

    $updateStmt = $conn->prepare("
        UPDATE loan_requests
        SET status = 'Approved',
            approved_amount = ?,
            approved_months = ?,
            approved_loan_id = ?,
            disbursement_reference_number = ?,
            disbursement_proof_image = ?,
            processed_at = NOW()
        WHERE id = ?
    ");
    $updateStmt->bind_param("ddissi", $amount, $months, $loanId, $disbursementReferenceNumber, $proofPath, $requestId);
    $updateStmt->execute();

    audit_log($conn, 'approve_loan_request', 'Admin approved loan request and marked loan as disbursed.', 'loan_requests', $requestId, [
        'borrower_id' => $request['borrower_id'],
        'loan_id' => $loanId,
        'approved_amount' => $amount,
        'approved_months' => $months,
        'monthly_rate' => $effectiveRate['monthly_rate'],
        'rate_implementation_date' => $effectiveRate['implementation_date'],
        'service_fee_rate' => $effectiveServiceFeeRate['service_fee_rate'],
        'service_fee_implementation_date' => $effectiveServiceFeeRate['implementation_date'],
        'payment_schedule' => $paymentScheduleSetting,
        'service_fee' => $serviceFee,
        'disbursement_reference_number' => $disbursementReferenceNumber
    ]);

    $conn->commit();
} catch (Throwable $e) {
    $conn->rollback();
    header("Location: ../loan_requests.php?error=" . urlencode("Unable to approve loan request"));
    exit;
}

header("Location: ../loan_requests.php?approved=1");
exit;

