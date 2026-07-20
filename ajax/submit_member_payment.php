<?php
include '../db.php';
include '../auth.php';
require_member();

$borrowerId = active_borrower_id();
$paymentDate = $_POST['payment_date'] ?? '';
$cutoffDate = $paymentDate;
$capitalContribution = (float)($_POST['capital_contribution'] ?? 0);
$loanPayment = (float)($_POST['loan_payment'] ?? 0);
$referenceNumber = trim($_POST['reference_number'] ?? '');

if (!$borrowerId || !$paymentDate) {
    exit("Missing payment details");
}

if ($referenceNumber === '') {
    exit("Reference payment number is required");
}

if (!isset($_FILES['proof_image']) || $_FILES['proof_image']['error'] !== UPLOAD_ERR_OK) {
    exit("GCash reference image is required");
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
    (borrower_id, payment_date, cutoff_date, capital_contribution, loan_payment, reference_number, proof_image)
    VALUES (?, ?, ?, ?, ?, ?, ?)
");

$stmt->bind_param(
    "issddss",
    $borrowerId,
    $paymentDate,
    $cutoffDate,
    $capitalContribution,
    $loanPayment,
    $referenceNumber,
    $proofPath
);
$stmt->execute();
$submissionId = $stmt->insert_id;

audit_log($conn, 'submit_payment', 'Member submitted payment for admin verification.', 'payment_submissions', $submissionId, [
    'borrower_id' => $borrowerId,
    'payment_date' => $paymentDate,
    'capital_contribution' => $capitalContribution,
    'loan_payment' => $loanPayment,
    'reference_number' => $referenceNumber
]);

header("Location: ../member_dashboard.php?payment_submitted=1");
exit;

