<?php
include '../db.php';
include '../auth.php';
require_admin();

$submissionId = (int)($_POST['submission_id'] ?? 0);

if (!$submissionId) {
    header("Location: ../received_savings.php?error=" . urlencode("Invalid savings submission"));
    exit;
}

$stmt = $conn->prepare("
    SELECT *
    FROM savings_submissions
    WHERE id = ?
    LIMIT 1
");
$stmt->bind_param("i", $submissionId);
$stmt->execute();
$submission = $stmt->get_result()->fetch_assoc();

if (!$submission) {
    header("Location: ../received_savings.php?error=" . urlencode("Savings submission not found"));
    exit;
}

if ($submission['status'] !== 'Pending') {
    header("Location: ../received_savings.php?error=" . urlencode("Savings submission already processed"));
    exit;
}

$accountStmt = $conn->prepare("SELECT savings_closed FROM borrowers WHERE id = ? LIMIT 1");
$accountStmt->bind_param("i", $submission['borrower_id']);
$accountStmt->execute();
$account = $accountStmt->get_result()->fetch_assoc();

if (!$account || (int)$account['savings_closed'] === 1) {
    header("Location: ../received_savings.php?error=" . urlencode("Savings account is closed"));
    exit;
}

$conn->begin_transaction();

try {
    $remarks = 'Verified savings ref: ' . $submission['reference_number'];

    $savingsStmt = $conn->prepare("
        INSERT INTO savings_transactions
        (borrower_id, amount, type, transaction_date, remarks)
        VALUES (?, ?, 'DEPOSIT', CURDATE(), ?)
    ");
    $savingsStmt->bind_param("ids", $submission['borrower_id'], $submission['amount'], $remarks);
    $savingsStmt->execute();

    $updateStmt = $conn->prepare("
        UPDATE savings_submissions
        SET status = 'Approved',
            processed_at = NOW()
        WHERE id = ?
    ");
    $updateStmt->bind_param("i", $submissionId);
    $updateStmt->execute();

    audit_log($conn, 'verify_savings_deposit', 'Admin verified savings deposit submission.', 'savings_submissions', $submissionId, [
        'borrower_id' => $submission['borrower_id'],
        'amount' => $submission['amount'],
        'reference_number' => $submission['reference_number']
    ]);

    $conn->commit();
} catch (Throwable $e) {
    $conn->rollback();
    header("Location: ../received_savings.php?error=" . urlencode("Unable to verify savings"));
    exit;
}

header("Location: ../received_savings.php?verified=1");
exit;

