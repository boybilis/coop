<?php
include '../db.php';
include '../auth.php';
require_member();

header('Content-Type: application/json');

$borrowerId = active_borrower_id();
$submissionId = (int)($_POST['submission_id'] ?? 0);
$paymentDate = $_POST['payment_date'] ?? '';
$capitalContribution = (float)($_POST['capital_contribution'] ?? 0);
$loanPayment = (float)($_POST['loan_payment'] ?? 0);
$referenceNumber = trim($_POST['reference_number'] ?? '');

if (!$submissionId || !$paymentDate || $referenceNumber === '') {
    echo json_encode(["error" => "Payment date and reference number are required"]);
    exit;
}

if ($capitalContribution <= 0 && $loanPayment <= 0) {
    echo json_encode(["error" => "Enter a capital contribution or loan payment amount"]);
    exit;
}

$stmt = $conn->prepare("
    SELECT proof_image
    FROM payment_submissions
    WHERE id = ?
    AND borrower_id = ?
    AND status = 'Pending'
    LIMIT 1
");
$stmt->bind_param("ii", $submissionId, $borrowerId);
$stmt->execute();
$submission = $stmt->get_result()->fetch_assoc();

if (!$submission) {
    echo json_encode(["error" => "Only pending payment submissions can be edited"]);
    exit;
}

$proofPath = $submission['proof_image'];

if (isset($_FILES['proof_image']) && $_FILES['proof_image']['error'] === UPLOAD_ERR_OK) {
    $fileInfo = finfo_open(FILEINFO_MIME_TYPE);
    $mimeType = finfo_file($fileInfo, $_FILES['proof_image']['tmp_name']);
    finfo_close($fileInfo);

    $allowedTypes = [
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
        'image/webp' => 'webp'
    ];

    if (!isset($allowedTypes[$mimeType])) {
        echo json_encode(["error" => "Only JPG, PNG, or WEBP images are allowed"]);
        exit;
    }

    $uploadDir = realpath(__DIR__ . '/../uploads/payment_proofs');

    if (!$uploadDir) {
        echo json_encode(["error" => "Upload directory is missing"]);
        exit;
    }

    $fileName = 'payment_' . $borrowerId . '_' . time() . '_' . bin2hex(random_bytes(4)) . '.' . $allowedTypes[$mimeType];
    $targetPath = $uploadDir . DIRECTORY_SEPARATOR . $fileName;

    if (!move_uploaded_file($_FILES['proof_image']['tmp_name'], $targetPath)) {
        echo json_encode(["error" => "Unable to save uploaded image"]);
        exit;
    }

    $proofPath = 'uploads/payment_proofs/' . $fileName;
}

$updateStmt = $conn->prepare("
    UPDATE payment_submissions
    SET payment_date = ?,
        cutoff_date = ?,
        capital_contribution = ?,
        loan_payment = ?,
        reference_number = ?,
        proof_image = ?
    WHERE id = ?
    AND borrower_id = ?
    AND status = 'Pending'
");
$updateStmt->bind_param(
    "ssddssii",
    $paymentDate,
    $paymentDate,
    $capitalContribution,
    $loanPayment,
    $referenceNumber,
    $proofPath,
    $submissionId,
    $borrowerId
);
$updateStmt->execute();

echo json_encode(["ok" => true]);
