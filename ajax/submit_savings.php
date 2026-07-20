<?php
include '../db.php';
include '../auth.php';
require_member();

$selectedMemberUserId = (int)($_POST['selected_member_user_id'] ?? active_member_user_id());
$borrowerId = member_borrower_id_for_user($conn, $selectedMemberUserId);
$amount = (float)($_POST['amount'] ?? 0);
$referenceNumber = trim($_POST['reference_number'] ?? '');

if (!$borrowerId) {
    exit("Selected account is invalid");
}

$accountStmt = $conn->prepare("SELECT savings_closed FROM borrowers WHERE id = ? LIMIT 1");
$accountStmt->bind_param("i", $borrowerId);
$accountStmt->execute();
$account = $accountStmt->get_result()->fetch_assoc();

if (!$account || (int)$account['savings_closed'] === 1) {
    exit("Savings account is closed");
}

if (!$borrowerId || $amount <= 0) {
    exit("Savings amount is required");
}

if ($referenceNumber === '') {
    exit("Reference number is required");
}

if (!isset($_FILES['proof_image']) || $_FILES['proof_image']['error'] !== UPLOAD_ERR_OK) {
    exit("Reference image is required");
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

$uploadDir = realpath(__DIR__ . '/../uploads/savings_proofs');

if (!$uploadDir) {
    exit("Upload directory is missing");
}

$fileName = 'savings_' . $borrowerId . '_' . time() . '_' . bin2hex(random_bytes(4)) . '.' . $allowedTypes[$mimeType];
$targetPath = $uploadDir . DIRECTORY_SEPARATOR . $fileName;

if (!move_uploaded_file($_FILES['proof_image']['tmp_name'], $targetPath)) {
    exit("Unable to save uploaded image");
}

$proofPath = 'uploads/savings_proofs/' . $fileName;

$stmt = $conn->prepare("
    INSERT INTO savings_submissions
    (borrower_id, amount, reference_number, proof_image)
    VALUES (?, ?, ?, ?)
");
$stmt->bind_param("idss", $borrowerId, $amount, $referenceNumber, $proofPath);
$stmt->execute();

header("Location: ../member_dashboard.php?savings_submitted=1");
exit;

