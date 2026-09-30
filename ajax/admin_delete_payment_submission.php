<?php
require_once __DIR__ . '/../db.php';
require_once __DIR__ . '/../auth.php';
require_admin();

$submissionId = (int)($_POST['submission_id'] ?? 0);
$csrfToken = (string)($_POST['csrf_token'] ?? '');
$sessionToken = (string)($_SESSION['admin_payment_delete_token'] ?? '');

if ($_SERVER['REQUEST_METHOD'] !== 'POST'
    || $submissionId <= 0
    || $sessionToken === ''
    || !hash_equals($sessionToken, $csrfToken)) {
    header('Location: ../received_payments.php?error=' . urlencode('Invalid delete request. Please reload the page and try again.'));
    exit;
}

$conn->begin_transaction();

try {
    $stmt = $conn->prepare("
        SELECT borrower_id, cutoff_date, capital_contribution, loan_payment,
               selected_loan_id, reference_number, proof_image
        FROM payment_submissions
        WHERE id = ? AND status = 'Pending'
        LIMIT 1
        FOR UPDATE
    ");
    $stmt->bind_param('i', $submissionId);
    $stmt->execute();
    $submission = $stmt->get_result()->fetch_assoc();

    if (!$submission) {
        throw new LogicException('Only pending payment submissions can be deleted.');
    }

    audit_log($conn, 'delete_pending_payment_submission', 'Admin deleted a mistakenly filed pending payment submission.', 'payment_submissions', $submissionId, [
        'borrower_id' => (int)$submission['borrower_id'],
        'cutoff_date' => $submission['cutoff_date'],
        'capital_contribution' => (float)$submission['capital_contribution'],
        'loan_payment' => (float)$submission['loan_payment'],
        'selected_loan_id' => $submission['selected_loan_id'] !== null ? (int)$submission['selected_loan_id'] : null,
        'reference_number' => $submission['reference_number'],
        'proof_image' => $submission['proof_image']
    ]);

    $deleteStmt = $conn->prepare("DELETE FROM payment_submissions WHERE id = ? AND status = 'Pending'");
    $deleteStmt->bind_param('i', $submissionId);
    $deleteStmt->execute();

    if ($deleteStmt->affected_rows !== 1) {
        throw new RuntimeException('Pending payment submission was not deleted.');
    }

    $conn->commit();
} catch (Throwable $exception) {
    $conn->rollback();
    $message = $exception instanceof LogicException
        ? $exception->getMessage()
        : 'Unable to delete the pending payment submission.';
    error_log('Unable to delete payment submission: ' . $exception->getMessage());
    header('Location: ../received_payments.php?error=' . urlencode($message));
    exit;
}

$proofPath = trim((string)($submission['proof_image'] ?? ''));
if ($proofPath !== '') {
    $proofReferenceStmt = $conn->prepare("SELECT id FROM payment_submissions WHERE proof_image = ? LIMIT 1");
    $proofReferenceStmt->bind_param('s', $proofPath);
    $proofReferenceStmt->execute();
    $isStillReferenced = (bool)$proofReferenceStmt->get_result()->fetch_assoc();

    $uploadDirectory = realpath(__DIR__ . '/../uploads/payment_proofs');
    $targetPath = realpath(__DIR__ . '/../' . ltrim(str_replace('\\', '/', $proofPath), '/'));

    if (!$isStillReferenced
        && $uploadDirectory !== false
        && $targetPath !== false
        && dirname($targetPath) === $uploadDirectory
        && is_file($targetPath)
        && !unlink($targetPath)) {
        error_log('Unable to remove orphaned payment proof: ' . $targetPath);
    }
}

$redirectCutoff = urlencode((string)$submission['cutoff_date']);
header("Location: ../received_payments.php?cutoff_date={$redirectCutoff}&deleted=1");
exit;
