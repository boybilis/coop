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
        $dueStmt = $conn->prepare("
            SELECT IFNULL(SUM(payments.amount),0) AS total_due
            FROM payments
            JOIN loans ON loans.id = payments.loan_id
            WHERE loans.borrower_id = ?
            AND payments.due_date = ?
            AND payments.paid = 0
        ");
        $dueStmt->bind_param("is", $submission['borrower_id'], $submission['cutoff_date']);
        $dueStmt->execute();
        $totalDue = (float)$dueStmt->get_result()->fetch_assoc()['total_due'];
        $overpayment = max(0, round((float)$submission['loan_payment'] - $totalDue, 2));

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

        if ($overpayment > 0) {
            $currentPaymentStmt = $conn->prepare("
                SELECT payments.id
                FROM payments
                JOIN loans ON loans.id = payments.loan_id
                WHERE loans.borrower_id = ?
                AND payments.due_date = ?
                AND payments.paid = 1
                ORDER BY payments.payment_no DESC, payments.id DESC
                LIMIT 1
            ");
            $currentPaymentStmt->bind_param("is", $submission['borrower_id'], $submission['cutoff_date']);
            $currentPaymentStmt->execute();
            $currentPayment = $currentPaymentStmt->get_result()->fetch_assoc();

            if ($currentPayment) {
                $paidAdjustmentStmt = $conn->prepare("
                    UPDATE payments
                    SET amount = amount + ?
                    WHERE id = ?
                ");
                $paidAdjustmentStmt->bind_param("di", $overpayment, $currentPayment['id']);
                $paidAdjustmentStmt->execute();
            }

            $remainingOverpayment = $overpayment;
            $lastPaymentsStmt = $conn->prepare("
                SELECT payments.id, payments.amount
                FROM payments
                JOIN loans ON loans.id = payments.loan_id
                WHERE loans.borrower_id = ?
                AND payments.paid = 0
                ORDER BY payments.due_date DESC, payments.payment_no DESC, payments.id DESC
            ");
            $lastPaymentsStmt->bind_param("i", $submission['borrower_id']);
            $lastPaymentsStmt->execute();
            $lastPayments = $lastPaymentsStmt->get_result();

            while ($remainingOverpayment > 0 && $lastPayment = $lastPayments->fetch_assoc()) {
                $paymentAmount = (float)$lastPayment['amount'];
                $adjustment = min($remainingOverpayment, $paymentAmount);
                $newPaymentAmount = round($paymentAmount - $adjustment, 2);

                if ($newPaymentAmount <= 0) {
                    $adjustPaymentStmt = $conn->prepare("
                        UPDATE payments
                        SET amount = 0,
                            paid = 1,
                            paid_at = NOW()
                        WHERE id = ?
                    ");
                    $adjustPaymentStmt->bind_param("i", $lastPayment['id']);
                    $adjustPaymentStmt->execute();
                } else {
                    $adjustPaymentStmt = $conn->prepare("
                        UPDATE payments
                        SET amount = ?
                        WHERE id = ?
                    ");
                    $adjustPaymentStmt->bind_param("di", $newPaymentAmount, $lastPayment['id']);
                    $adjustPaymentStmt->execute();
                }

                $remainingOverpayment = round($remainingOverpayment - $adjustment, 2);
            }
        }

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

    audit_log($conn, 'verify_payment', 'Admin verified member payment submission.', 'payment_submissions', $submissionId, [
        'borrower_id' => $submission['borrower_id'],
        'cutoff_date' => $submission['cutoff_date'],
        'capital_contribution' => $submission['capital_contribution'],
        'loan_payment' => $submission['loan_payment'],
        'reference_number' => $submission['reference_number']
    ]);

    $conn->commit();
} catch (Throwable $e) {
    $conn->rollback();
    header("Location: ../received_payments.php?cutoff_date={$redirectCutoff}&error=" . urlencode("Unable to verify payment"));
    exit;
}

header("Location: ../received_payments.php?cutoff_date={$redirectCutoff}&verified=1");
exit;

