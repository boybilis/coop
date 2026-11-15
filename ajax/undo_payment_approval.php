<?php
require_once __DIR__ . '/../db.php';
require_once __DIR__ . '/../auth.php';
require_superadmin();

$submissionId = (int)($_POST['submission_id'] ?? 0);
$csrfToken = (string)($_POST['csrf_token'] ?? '');
$sessionToken = (string)($_SESSION['superadmin_payment_undo_token'] ?? '');

if ($_SERVER['REQUEST_METHOD'] !== 'POST'
    || $submissionId <= 0
    || $sessionToken === ''
    || !hash_equals($sessionToken, $csrfToken)) {
    header('Location: ../received_payments.php?error=' . urlencode('Invalid undo request. Please reload the page and try again.'));
    exit;
}

function normalized_payment_undo_state(array $row): array
{
    return [
        'loan_id' => (int)$row['loan_id'],
        'amount' => number_format((float)$row['amount'], 2, '.', ''),
        'paid' => (int)$row['paid'],
        'paid_at' => $row['paid_at'],
    ];
}

$conn->begin_transaction();

try {
    $submissionStmt = $conn->prepare("
        SELECT *
        FROM payment_submissions
        WHERE id = ?
        LIMIT 1
        FOR UPDATE
    ");
    $submissionStmt->bind_param('i', $submissionId);
    $submissionStmt->execute();
    $submission = $submissionStmt->get_result()->fetch_assoc();

    if (!$submission || $submission['status'] !== 'Approved') {
        throw new LogicException('Only an approved payment submission can be undone.');
    }

    $auditStmt = $conn->prepare("
        SELECT metadata
        FROM audit_trails
        WHERE action = 'verify_payment'
        AND entity_type = 'payment_submissions'
        AND entity_id = ?
        ORDER BY id DESC
        LIMIT 1
        FOR UPDATE
    ");
    $auditStmt->bind_param('i', $submissionId);
    $auditStmt->execute();
    $audit = $auditStmt->get_result()->fetch_assoc();
    $metadata = $audit ? json_decode((string)$audit['metadata'], true) : null;
    $effects = is_array($metadata) ? ($metadata['undo_effects'] ?? null) : null;

    if (!is_array($effects) || (int)($effects['version'] ?? 0) !== 1) {
        throw new LogicException('This older approval does not have a safe undo record and cannot be reversed automatically.');
    }

    foreach (($effects['payments'] ?? []) as $change) {
        $paymentId = (int)($change['id'] ?? 0);
        $paymentStmt = $conn->prepare("
            SELECT id, loan_id, amount, paid, paid_at
            FROM payments
            WHERE id = ?
            LIMIT 1
            FOR UPDATE
        ");
        $paymentStmt->bind_param('i', $paymentId);
        $paymentStmt->execute();
        $payment = $paymentStmt->get_result()->fetch_assoc();

        if (!$payment || normalized_payment_undo_state($payment) !== ($change['after'] ?? null)) {
            throw new LogicException('A loan installment changed after this approval. Undo was stopped to protect newer payment records.');
        }
    }

    foreach (($effects['loans'] ?? []) as $change) {
        $loanId = (int)($change['id'] ?? 0);
        $loanStmt = $conn->prepare("SELECT id, status FROM loans WHERE id = ? LIMIT 1 FOR UPDATE");
        $loanStmt->bind_param('i', $loanId);
        $loanStmt->execute();
        $loan = $loanStmt->get_result()->fetch_assoc();

        if (!$loan || ['status' => $loan['status']] !== ($change['after'] ?? null)) {
            throw new LogicException('A loan changed after this approval. Undo was stopped to protect newer records.');
        }
    }

    $capitalContributionId = (int)($effects['capital_contribution_id'] ?? 0);
    if ($capitalContributionId > 0) {
        $capitalStmt = $conn->prepare("
            SELECT borrower_id, amount, contribution_date, reference_number, proof_image
            FROM capital_contributions
            WHERE id = ?
            LIMIT 1
            FOR UPDATE
        ");
        $capitalStmt->bind_param('i', $capitalContributionId);
        $capitalStmt->execute();
        $capital = $capitalStmt->get_result()->fetch_assoc();

        if (!$capital
            || (int)$capital['borrower_id'] !== (int)$submission['borrower_id']
            || abs((float)$capital['amount'] - (float)$submission['capital_contribution']) > 0.009
            || $capital['contribution_date'] !== $submission['cutoff_date']
            || (string)$capital['reference_number'] !== (string)$submission['reference_number']
            || (string)$capital['proof_image'] !== (string)$submission['proof_image']) {
            throw new LogicException('The capital contribution changed after this approval. Undo was stopped to protect newer records.');
        }
    }

    foreach (($effects['payments'] ?? []) as $change) {
        $before = $change['before'];
        $paymentId = (int)$change['id'];
        $amount = (float)$before['amount'];
        $paid = (int)$before['paid'];
        $paidAt = $before['paid_at'];
        $restoreStmt = $conn->prepare("UPDATE payments SET amount = ?, paid = ?, paid_at = ? WHERE id = ?");
        $restoreStmt->bind_param('disi', $amount, $paid, $paidAt, $paymentId);
        $restoreStmt->execute();
    }

    foreach (($effects['loans'] ?? []) as $change) {
        $loanId = (int)$change['id'];
        $status = (string)$change['before']['status'];
        $restoreLoanStmt = $conn->prepare("UPDATE loans SET status = ? WHERE id = ?");
        $restoreLoanStmt->bind_param('si', $status, $loanId);
        $restoreLoanStmt->execute();
    }

    if ($capitalContributionId > 0) {
        $deleteCapitalStmt = $conn->prepare("DELETE FROM capital_contributions WHERE id = ?");
        $deleteCapitalStmt->bind_param('i', $capitalContributionId);
        $deleteCapitalStmt->execute();
    }

    $pendingStmt = $conn->prepare("UPDATE payment_submissions SET status = 'Pending' WHERE id = ? AND status = 'Approved'");
    $pendingStmt->bind_param('i', $submissionId);
    $pendingStmt->execute();
    if ($pendingStmt->affected_rows !== 1) {
        throw new RuntimeException('The payment submission status could not be restored.');
    }

    audit_log($conn, 'undo_payment_approval', 'SuperAdmin undid an approved payment submission.', 'payment_submissions', $submissionId, [
        'borrower_id' => (int)$submission['borrower_id'],
        'cutoff_date' => $submission['cutoff_date'],
        'capital_contribution_id' => $capitalContributionId ?: null,
        'restored_payment_count' => count($effects['payments'] ?? []),
        'restored_loan_count' => count($effects['loans'] ?? []),
    ]);

    $conn->commit();
} catch (Throwable $exception) {
    $conn->rollback();
    $message = $exception instanceof LogicException
        ? $exception->getMessage()
        : 'Unable to undo the payment approval.';
    error_log('Unable to undo payment approval: ' . $exception->getMessage());
    header('Location: ../received_payments.php?error=' . urlencode($message));
    exit;
}

$redirectCutoff = urlencode((string)$submission['cutoff_date']);
header("Location: ../received_payments.php?cutoff_date={$redirectCutoff}&approval_undone=1");
exit;
