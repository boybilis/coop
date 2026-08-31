<?php
include '../db.php';
include '../auth.php';
require_member();

$borrowerId = active_borrower_id();
$cutoffDate = $_POST['payment_date'] ?? '';
$paymentDate = date('Y-m-d');
$capitalContribution = (float)($_POST['capital_contribution'] ?? 0);
$selectedLoanValue = $_POST['selected_loan_id'] ?? '';
$referenceNumber = trim($_POST['reference_number'] ?? '');

if (!$borrowerId || !$cutoffDate) {
    exit("Missing payment details");
}

if (!in_array($cutoffDate, cooperative_member_payment_cutoff_options($conn, $borrowerId), true)) {
    exit("Please select a valid payment cut-off date");
}

try {
    $loanTarget = cooperative_member_loan_payment_target($conn, $borrowerId, $cutoffDate, $selectedLoanValue);
} catch (InvalidArgumentException $exception) {
    exit($exception->getMessage());
}

$selectedLoanId = $loanTarget['selected_loan_id'];
$loanPayment = $loanTarget['amount'];

if ($referenceNumber === '') {
    exit("Reference payment number is required");
}

if (!isset($_FILES['proof_image']) || $_FILES['proof_image']['error'] !== UPLOAD_ERR_OK) {
    exit("GCash reference image is required");
}

if ((int)$_FILES['proof_image']['size'] > 5 * 1024 * 1024) {
    exit("Image must not exceed 5 MB");
}

if ($capitalContribution <= 0 && $loanPayment <= 0) {
    exit("Enter a capital contribution or loan payment amount");
}

$fileInfo = finfo_open(FILEINFO_MIME_TYPE);
$mimeType = finfo_file($fileInfo, $_FILES['proof_image']['tmp_name']);
finfo_close($fileInfo);

$allowedTypes = [
    'image/jpeg' => 'jpg',
    'image/png' => 'png',
    'image/webp' => 'webp'
];

if (!isset($allowedTypes[$mimeType])) {
    exit("Only JPG, PNG, or WEBP images are allowed");
}

$uploadDir = realpath(__DIR__ . '/../uploads/payment_proofs');

if (!$uploadDir) {
    exit("Upload directory is missing");
}

$fileName = 'payment_' . $borrowerId . '_' . time() . '_' . bin2hex(random_bytes(4)) . '.' . $allowedTypes[$mimeType];
$targetPath = $uploadDir . DIRECTORY_SEPARATOR . $fileName;

if (!move_uploaded_file($_FILES['proof_image']['tmp_name'], $targetPath)) {
    exit("Unable to save uploaded image");
}

$proofPath = 'uploads/payment_proofs/' . $fileName;

$stmt = $conn->prepare("
    INSERT INTO payment_submissions
    (borrower_id, payment_date, cutoff_date, capital_contribution, loan_payment, selected_loan_id, reference_number, proof_image)
    VALUES (?, ?, ?, ?, ?, ?, ?, ?)
");

$stmt->bind_param(
    "issddiss",
    $borrowerId,
    $paymentDate,
    $cutoffDate,
    $capitalContribution,
    $loanPayment,
    $selectedLoanId,
    $referenceNumber,
    $proofPath
);
$stmt->execute();
$submissionId = $stmt->insert_id;

audit_log($conn, 'submit_payment', 'Member submitted payment for admin verification.', 'payment_submissions', $submissionId, [
    'borrower_id' => $borrowerId,
    'payment_date' => $paymentDate,
    'cutoff_date' => $cutoffDate,
    'capital_contribution' => $capitalContribution,
    'loan_payment' => $loanPayment,
    'selected_loan_id' => $selectedLoanId,
    'reference_number' => $referenceNumber
]);

header("Location: ../member_dashboard.php?payment_submitted=1");
exit;

