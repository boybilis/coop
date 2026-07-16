<?php
include '../db.php';
include '../auth.php';
require_admin();

$submissionId = (int)($_POST['submission_id'] ?? 0);

if (!$submissionId) {
    header("Location: ../received_payments.php?error=Invalid payment submission");
    exit;
}

$stmt = $conn->prepare("
    SELECT *
    FROM payment_submissions
    WHERE id = ?
    LIMIT 1
");
$stmt->bind_param("i", $submissionId);
$stmt->execute();
$submission = $stmt->get_result()->fetch_assoc();

if (!$submission) {
    header("Location: ../received_payments.php?error=Payment submission not found");
    exit;
}

$redirectCutoff = urlencode($submission['cutoff_date']);

if ($submission['status'] !== 'Pending') {
    header("Location: ../received_payments.php?cutoff_date={$redirectCutoff}&error=Payment submission already processed");
    exit;
}

$conn->begin_transaction();

try {
    if ((float)$submission['capital_contribution'] > 0) {
        $periodLabel = 'GCash Ref: ' . $submission['reference_number'];

        $capitalStmt = $conn->prepare("
            INSERT INTO capital_contributions
            (borrower_id, amount, type, contribution_date, period_label)
            VALUES (?, ?, 'CUTOFF', ?, ?)
        ");
        $capitalStmt->bind_param(
            "idss",
            $submission['borrower_id'],
            $submission['capital_contribution'],
            $submission['cutoff_date'],
            $periodLabel
        );
        $capitalStmt->execute();
    }

    if ((float)$submission['loan_payment'] > 0) {
        $paymentStmt = $conn->prepare("
            UPDATE payments
            JOIN loans ON loans.id = payments.loan_id
            SET payments.paid = 1,
                payments.paid_at = NOW()
            WHERE loans.borrower_id = ?
            AND payments.due_date = ?
            AND payments.paid = 0
        ");
        $paymentStmt->bind_param("is", $submission['borrower_id'], $submission['cutoff_date']);
        $paymentStmt->execute();

        $loanStatusStmt = $conn->prepare("
            UPDATE loans
            SET status = 'Completed'
            WHERE borrower_id = ?
            AND NOT EXISTS (
                SELECT 1
                FROM payments
                WHERE payments.loan_id = loans.id
                AND payments.paid = 0
            )
        ");
        $loanStatusStmt->bind_param("i", $submission['borrower_id']);
        $loanStatusStmt->execute();
    }

    $submissionStmt = $conn->prepare("
        UPDATE payment_submissions
        SET status = 'Approved'
        WHERE id = ?
    ");
    $submissionStmt->bind_param("i", $submissionId);
    $submissionStmt->execute();

    $conn->commit();
} catch (Throwable $e) {
    $conn->rollback();
    header("Location: ../received_payments.php?cutoff_date={$redirectCutoff}&error=" . urlencode("Unable to verify payment"));
    exit;
}

header("Location: ../received_payments.php?cutoff_date={$redirectCutoff}&verified=1");
exit;
