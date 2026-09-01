<?php
require_once __DIR__ . '/../db.php';
require_once __DIR__ . '/../auth.php';
require_admin();

$borrowerId = (int)($_POST['borrower_id'] ?? 0);
$cutoffDate = $_POST['cutoff_date'] ?? '';
$capitalContribution = (float)($_POST['capital_contribution'] ?? 0);
$selectedLoanValue = $_POST['selected_loan_id'] ?? '';
$referenceNumber = trim($_POST['reference_number'] ?? '');

if (!$borrowerId || $cutoffDate === '') {
    header('Location: ../received_payments.php?error=' . urlencode('Member and cut-off date are required.'));
    exit;
}

if (strlen($referenceNumber) > 100) {
    header('Location: ../received_payments.php?error=' . urlencode('Reference number must not exceed 100 characters.'));
    exit;
}

$memberStmt = $conn->prepare("SELECT id FROM borrowers WHERE id = ? AND status = 'Active' LIMIT 1");
$memberStmt->bind_param('i', $borrowerId);
$memberStmt->execute();

if (!$memberStmt->get_result()->fetch_assoc()) {
    header('Location: ../received_payments.php?error=' . urlencode('Selected member is unavailable.'));
    exit;
}

if (!in_array($cutoffDate, cooperative_member_payment_cutoff_options($conn, $borrowerId), true)) {
    header('Location: ../received_payments.php?error=' . urlencode('Please select a valid payment cut-off date.'));
    exit;
}

try {
    $loanTarget = cooperative_member_loan_payment_target($conn, $borrowerId, $cutoffDate, $selectedLoanValue);
} catch (InvalidArgumentException $exception) {
    header('Location: ../received_payments.php?error=' . urlencode($exception->getMessage()));
    exit;
}

$selectedLoanId = $loanTarget['selected_loan_id'];
$loanPayment = $loanTarget['amount'];

if ($capitalContribution <= 0 && $loanPayment <= 0) {
    header('Location: ../received_payments.php?error=' . urlencode('Enter a capital contribution or select a loan to pay.'));
    exit;
}

$proofPath = '';
$targetPath = '';
$uploadError = $_FILES['proof_image']['error'] ?? UPLOAD_ERR_NO_FILE;

if ($uploadError !== UPLOAD_ERR_NO_FILE) {
    if ($uploadError !== UPLOAD_ERR_OK) {
        header('Location: ../received_payments.php?error=' . urlencode('Unable to process uploaded image.'));
        exit;
    }

    if ((int)$_FILES['proof_image']['size'] > 5 * 1024 * 1024) {
        header('Location: ../received_payments.php?error=' . urlencode('Image must not exceed 5 MB.'));
        exit;
    }

    $fileInfo = finfo_open(FILEINFO_MIME_TYPE);
    $mimeType = finfo_file($fileInfo, $_FILES['proof_image']['tmp_name']);
    finfo_close($fileInfo);
    $allowedTypes = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'];

    if (!isset($allowedTypes[$mimeType])) {
        header('Location: ../received_payments.php?error=' . urlencode('Only JPG, PNG, or WEBP images are allowed.'));
        exit;
    }

    $uploadDir = realpath(__DIR__ . '/../uploads/payment_proofs');
    if (!$uploadDir) {
        header('Location: ../received_payments.php?error=' . urlencode('Upload directory is missing.'));
        exit;
    }

    $fileName = 'admin_payment_' . $borrowerId . '_' . time() . '_' . bin2hex(random_bytes(4)) . '.' . $allowedTypes[$mimeType];
    $targetPath = $uploadDir . DIRECTORY_SEPARATOR . $fileName;

    if (!move_uploaded_file($_FILES['proof_image']['tmp_name'], $targetPath)) {
        header('Location: ../received_payments.php?error=' . urlencode('Unable to save uploaded image.'));
        exit;
    }

    $proofPath = 'uploads/payment_proofs/' . $fileName;
}

$paymentDate = date('Y-m-d');
$status = 'Pending';
$stmt = $conn->prepare("
    INSERT INTO payment_submissions
    (borrower_id, payment_date, cutoff_date, capital_contribution, loan_payment, selected_loan_id, reference_number, proof_image, status)
    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
");
$stmt->bind_param(
    'issddisss',
    $borrowerId,
    $paymentDate,
    $cutoffDate,
    $capitalContribution,
    $loanPayment,
    $selectedLoanId,
    $referenceNumber,
    $proofPath,
    $status
);

try {
    $stmt->execute();
} catch (mysqli_sql_exception $exception) {
    if ($targetPath !== '' && is_file($targetPath)) {
        @unlink($targetPath);
    }
    error_log('Unable to create direct admin payment: ' . $exception->getMessage());
    header('Location: ../received_payments.php?error=' . urlencode('Unable to record payment.'));
    exit;
}

$submissionId = (int)$stmt->insert_id;
$GLOBALS['admin_direct_submission_id'] = $submissionId;
$GLOBALS['admin_direct_proof_file'] = $targetPath;
$_POST['submission_id'] = $submissionId;

require __DIR__ . '/verify_payment_submission.php';
