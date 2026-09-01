<?php
require_once __DIR__ . '/../db.php';
require_once __DIR__ . '/../auth.php';
require_admin();

header('Content-Type: application/json');

$borrowerId = (int)($_GET['borrower_id'] ?? 0);

if (!$borrowerId) {
    echo json_encode(['error' => 'Please select a member.']);
    exit;
}

$memberStmt = $conn->prepare("SELECT id FROM borrowers WHERE id = ? AND status = 'Active' LIMIT 1");
$memberStmt->bind_param('i', $borrowerId);
$memberStmt->execute();

if (!$memberStmt->get_result()->fetch_assoc()) {
    echo json_encode(['error' => 'Selected member is unavailable.']);
    exit;
}

$cutoffOptions = cooperative_member_payment_cutoff_options($conn, $borrowerId);
$loanOptions = [];
$stmt = $conn->prepare("
    SELECT
        payments.loan_id,
        payments.due_date,
        IFNULL(SUM(payments.amount),0) AS amount_due,
        loans.is_guarantor,
        loans.guest_borrower_name
    FROM payments
    JOIN loans ON loans.id = payments.loan_id
    WHERE loans.borrower_id = ?
    AND payments.paid = 0
    GROUP BY payments.loan_id, payments.due_date, loans.is_guarantor, loans.guest_borrower_name
    ORDER BY payments.due_date ASC, payments.loan_id ASC
");
$stmt->bind_param('i', $borrowerId);
$stmt->execute();
$rows = $stmt->get_result();

while ($row = $rows->fetch_assoc()) {
    $label = 'Loan #' . (int)$row['loan_id'];

    if ((int)($row['is_guarantor'] ?? 0) === 1) {
        $label .= ' - Co-maker';
        if (!empty($row['guest_borrower_name'])) {
            $label .= ' for ' . $row['guest_borrower_name'];
        }
    } else {
        $label .= ' - Personal loan';
    }

    $loanOptions[$row['due_date']][] = [
        'id' => (int)$row['loan_id'],
        'label' => $label,
        'amount' => (float)$row['amount_due'],
    ];
}

echo json_encode([
    'cutoffs' => $cutoffOptions,
    'loans_by_cutoff' => $loanOptions,
], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
