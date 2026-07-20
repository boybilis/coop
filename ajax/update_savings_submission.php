<?php
include '../db.php';
include '../auth.php';
require_member();

header('Content-Type: application/json');

$borrowerId = active_borrower_id();
$submissionId = (int)($_POST['submission_id'] ?? 0);
$amount = (float)($_POST['amount'] ?? 0);
$referenceNumber = trim($_POST['reference_number'] ?? '');

if (!$submissionId || $amount <= 0 || $referenceNumber === '') {
    echo json_encode(["error" => "Amount and reference number are required"]);
    exit;
}

$accountStmt = $conn->prepare("SELECT savings_closed FROM borrowers WHERE id = ? LIMIT 1");
$accountStmt->bind_param("i", $borrowerId);
$accountStmt->execute();
$account = $accountStmt->get_result()->fetch_assoc();

if (!$account || (int)$account['savings_closed'] === 1) {
    echo json_encode(["error" => "Savings account is closed"]);
    exit;
}

$stmt = $conn->prepare("
    SELECT proof_image
    FROM savings_submissions
    WHERE id = ?
    AND borrower_id = ?
    AND status = 'Pending'
    LIMIT 1
");
$stmt->bind_param("ii", $submissionId, $borrowerId);
$stmt->execute();
$submission = $stmt->get_result()->fetch_assoc();

if (!$submission) {
    echo json_encode(["error" => "Only pending savings submissions can be edited"]);
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

    $uploadDir = realpath(__DIR__ . '/../uploads/savings_proofs');

    if (!$uploadDir) {
        echo json_encode(["error" => "Upload directory is missing"]);
        exit;
    }

    $fileName = 'savings_' . $borrowerId . '_' . time() . '_' . bin2hex(random_bytes(4)) . '.' . $allowedTypes[$mimeType];
    $targetPath = $uploadDir . DIRECTORY_SEPARATOR . $fileName;

    if (!move_uploaded_file($_FILES['proof_image']['tmp_name'], $targetPath)) {
        echo json_encode(["error" => "Unable to save uploaded image"]);
        exit;
    }

    $proofPath = 'uploads/savings_proofs/' . $fileName;
}

$updateStmt = $conn->prepare("
    UPDATE savings_submissions
    SET amount = ?,
        reference_number = ?,
        proof_image = ?
    WHERE id = ?
    AND borrower_id = ?
    AND status = 'Pending'
");
$updateStmt->bind_param("dssii", $amount, $referenceNumber, $proofPath, $submissionId, $borrowerId);
$updateStmt->execute();

echo json_encode(["ok" => true]);

