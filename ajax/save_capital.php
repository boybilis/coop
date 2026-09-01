<?php
include '../db.php';
include '../auth.php';
require_admin();

header('Content-Type: application/json');

$borrower_id = (int)($_POST['borrower_id'] ?? 0);
$amount = (float)($_POST['amount'] ?? 0);
$type = $_POST['type'] ?? '';
$date = $_POST['date'] ?? '';
$referenceNumber = trim($_POST['reference_number'] ?? '');
$referenceNumberValue = $referenceNumber !== '' ? $referenceNumber : null;
$periodLabel = 'Admin Entry - Verified';

if (!$borrower_id || $amount <= 0 || !in_array($type, ['INITIAL', 'CUTOFF'], true) || !$date) {
    echo json_encode(["error" => "Please complete all capital contribution fields."]);
    exit;
}

if (strlen($referenceNumber) > 100) {
    echo json_encode(["error" => "Reference number must not exceed 100 characters."]);
    exit;
}

$proofPath = null;
$targetPath = null;
$uploadError = $_FILES['proof_image']['error'] ?? UPLOAD_ERR_NO_FILE;

if ($uploadError !== UPLOAD_ERR_NO_FILE) {
    if ($uploadError !== UPLOAD_ERR_OK) {
        echo json_encode(["error" => "Unable to process uploaded image."]);
        exit;
    }

    if ((int)$_FILES['proof_image']['size'] > 5 * 1024 * 1024) {
        echo json_encode(["error" => "Image must not exceed 5 MB."]);
        exit;
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
        echo json_encode(["error" => "Only JPG, PNG, or WEBP images are allowed."]);
        exit;
    }

    $uploadDir = realpath(__DIR__ . '/../uploads/capital_proofs');

    if (!$uploadDir) {
        echo json_encode(["error" => "Capital proof upload directory is missing."]);
        exit;
    }

    $fileName = 'capital_' . $borrower_id . '_' . time() . '_' . bin2hex(random_bytes(4)) . '.' . $allowedTypes[$mimeType];
    $targetPath = $uploadDir . DIRECTORY_SEPARATOR . $fileName;

    if (!move_uploaded_file($_FILES['proof_image']['tmp_name'], $targetPath)) {
        echo json_encode(["error" => "Unable to save uploaded image."]);
        exit;
    }

    $proofPath = 'uploads/capital_proofs/' . $fileName;
}

$stmt = $conn->prepare("
INSERT INTO capital_contributions 
(borrower_id, amount, type, contribution_date, period_label, reference_number, proof_image)
VALUES (?, ?, ?, ?, ?, ?, ?)
");

$stmt->bind_param("idsssss", $borrower_id, $amount, $type, $date, $periodLabel, $referenceNumberValue, $proofPath);

try {
    $stmt->execute();
} catch (mysqli_sql_exception $exception) {
    if ($targetPath) {
        @unlink($targetPath);
    }
    error_log('Unable to save capital contribution: ' . $exception->getMessage());
    echo json_encode(["error" => "Unable to save capital contribution."]);
    exit;
}
$capitalId = $stmt->insert_id;

audit_log($conn, 'save_capital_contribution', 'Admin recorded a capital contribution.', 'capital_contributions', $capitalId, [
    'borrower_id' => $borrower_id,
    'amount' => $amount,
    'type' => $type,
    'date' => $date,
    'period_label' => $periodLabel,
    'reference_number' => $referenceNumber,
    'proof_image' => $proofPath
]);

$summary = $conn->query("
    SELECT
        IFNULL(SUM(amount),0) AS total,
        COUNT(DISTINCT borrower_id) AS member_count
    FROM capital_contributions
")->fetch_assoc();

$memberStmt = $conn->prepare("
    SELECT borrowers.name, users.username
    FROM borrowers
    LEFT JOIN users ON users.borrower_id = borrowers.id AND users.status = 'Member'
    WHERE borrowers.id = ?
    LIMIT 1
");
$memberStmt->bind_param("i", $borrower_id);
$memberStmt->execute();
$member = $memberStmt->get_result()->fetch_assoc();

$total = (float)$summary['total'];
$memberCount = (int)$summary['member_count'];
$average = $memberCount > 0 ? $total / $memberCount : 0;

echo json_encode([
    "ok" => true,
    "message" => "Capital Contribution Recording Successful.",
    "row" => [
        "name" => $member['name'] ?? '',
        "username" => $member['username'] ?? '',
        "amount" => $amount,
        "type" => $type,
        "date" => $date,
        "period_label" => $periodLabel,
        "reference_number" => $referenceNumber,
        "proof_image" => $proofPath
    ],
    "summary" => [
        "total" => $total,
        "average" => $average
    ]
]);

