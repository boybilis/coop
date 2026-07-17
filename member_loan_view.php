<?php
include 'db.php';
include 'auth.php';
include 'layout.php';
require_member();

$borrowerId = active_borrower_id();
$loanId = (int)($_GET['id'] ?? 0);

if (!$loanId) {
    http_response_code(404);
    exit("Loan not found");
}

$loanStmt = $conn->prepare("
    SELECT
        loans.*,
        borrowers.name,
        (SELECT COUNT(*) FROM payments WHERE loan_id = loans.id) AS total_payments,
        (SELECT COUNT(*) FROM payments WHERE loan_id = loans.id AND paid = 1) AS paid_payments
    FROM loans
    JOIN borrowers ON borrowers.id = loans.borrower_id
    WHERE loans.id = ?
    AND loans.borrower_id = ?
    LIMIT 1
");
$loanStmt->bind_param("ii", $loanId, $borrowerId);
$loanStmt->execute();
$loanInfo = $loanStmt->get_result()->fetch_assoc();

if (!$loanInfo) {
    http_response_code(404);
    exit("Loan not found");
}

$progress = ((int)$loanInfo['total_payments'] > 0)
    ? round(((int)$loanInfo['paid_payments'] / (int)$loanInfo['total_payments']) * 100)
    : 0;

$paymentsStmt = $conn->prepare("
    SELECT
        payments.*,
        payment_submissions.payment_date,
        payment_submissions.capital_contribution,
        payment_submissions.loan_payment,
        payment_submissions.reference_number,
        payment_submissions.proof_image,
        payment_submissions.status AS submission_status
    FROM payments
    LEFT JOIN payment_submissions
        ON payment_submissions.id = (
            SELECT latest_submission.id
            FROM payment_submissions latest_submission
            WHERE latest_submission.borrower_id = ?
            AND latest_submission.cutoff_date = payments.due_date
            ORDER BY latest_submission.id DESC
            LIMIT 1
        )
    WHERE payments.loan_id = ?
    ORDER BY payments.due_date ASC, payments.payment_no ASC
");
$paymentsStmt->bind_param("ii", $borrowerId, $loanId);
$paymentsStmt->execute();
$payments = $paymentsStmt->get_result();
?>
<!DOCTYPE html>
<html>
<head>
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>My Loan Details</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
<link rel="stylesheet" href="assets/css/mobile.css">
<link rel="stylesheet" href="assets/css/theme.css">
<link rel="stylesheet" href="https://cdn.datatables.net/1.13.6/css/dataTables.bootstrap5.min.css">
</head>

<body class="bg-light">
<?php render_navbar(); ?>

<div class="container mt-4">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h3 class="mb-0">My Loan Details</h3>
        <a href="member_dashboard.php" class="btn btn-outline-secondary">Back to Dashboard</a>
    </div>

    <div class="card shadow-sm mb-3">
        <div class="card-body">
            <div class="row g-3">
                <div class="col-md-4">
                    <small class="text-muted">Loan ID</small>
                    <h5>#<?= $loanInfo['id'] ?></h5>
                </div>
                <div class="col-md-4">
                    <small class="text-muted">Member</small>
                    <h5><?= htmlspecialchars($loanInfo['name']) ?></h5>
                </div>
                <div class="col-md-4">
                    <small class="text-muted">Status</small><br>
                    <span class="badge bg-<?= $loanInfo['status'] === 'Active' ? 'warning text-dark' : 'success' ?>">
                        <?= htmlspecialchars($loanInfo['status']) ?>
                    </span>
                </div>
                <div class="col-md-4">
                    <small class="text-muted">Borrower For</small>
                    <h5>
                        <?php if((int)($loanInfo['is_guarantor'] ?? 0) === 1): ?>
                            Guest: <?= htmlspecialchars($loanInfo['guest_borrower_name'] ?? '') ?>
                        <?php else: ?>
                            Member
                        <?php endif; ?>
                    </h5>
                </div>
                <div class="col-md-3">
                    <small class="text-muted">Loan Amount</small>
                    <h5>₱<?= number_format($loanInfo['amount'], 2) ?></h5>
                </div>
                <div class="col-md-3">
                    <small class="text-muted">Interest</small>
                    <h5 class="text-success">₱<?= number_format($loanInfo['interest'], 2) ?></h5>
                </div>
                <div class="col-md-3">
                    <small class="text-muted">Total Payable</small>
                    <h5>₱<?= number_format($loanInfo['total_payable'], 2) ?></h5>
                </div>
                <div class="col-md-3">
                    <small class="text-muted">Months</small>
                    <h5><?= number_format($loanInfo['months'], 1) ?></h5>
                </div>
                <div class="col-12">
                    <small class="text-muted">Payment Progress</small>
                    <div class="progress" style="height:24px;">
                        <div class="progress-bar bg-success" style="width:<?= $progress ?>%">
                            <?= $progress ?>%
                        </div>
                    </div>
                    <small class="text-muted">
                        <?= number_format($loanInfo['paid_payments']) ?> of <?= number_format($loanInfo['total_payments']) ?> payments paid
                    </small>
                </div>
            </div>
        </div>
    </div>

    <div class="card shadow-sm">
        <div class="card-header">
            <h5 class="mb-0">Payment Schedule</h5>
        </div>
        <div class="card-body">
            <div class="table-responsive">
                <table id="memberLoanPaymentTable" class="table table-bordered table-hover align-middle">
                    <thead class="table-dark">
                        <tr>
                            <th>#</th>
                            <th>Amount</th>
                            <th>Due Date</th>
                            <th>Submitted</th>
                            <th>Reference</th>
                            <th>Proof Image</th>
                            <th>Submission Status</th>
                            <th>Payment Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php while($payment = $payments->fetch_assoc()): ?>
                            <tr>
                                <td><?= $payment['payment_no'] ?></td>
                                <td>₱<?= number_format($payment['amount'], 2) ?></td>
                                <td><?= htmlspecialchars($payment['due_date']) ?></td>
                                <td>
                                    <?php if($payment['reference_number']): ?>
                                        <small>
                                            Capital: ₱<?= number_format($payment['capital_contribution'], 2) ?><br>
                                            Loan: ₱<?= number_format($payment['loan_payment'], 2) ?><br>
                                            Date: <?= htmlspecialchars($payment['payment_date']) ?>
                                        </small>
                                    <?php else: ?>
                                        <span class="text-muted">—</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?= $payment['reference_number'] ? htmlspecialchars($payment['reference_number']) : '<span class="text-muted">—</span>' ?>
                                </td>
                                <td>
                                    <?php if($payment['proof_image']): ?>
                                        <a href="<?= htmlspecialchars($payment['proof_image']) ?>" target="_blank" class="btn btn-outline-primary btn-sm">
                                            View Image
                                        </a>
                                    <?php else: ?>
                                        <span class="text-muted">—</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if($payment['submission_status']): ?>
                                        <span class="badge bg-<?= $payment['submission_status'] === 'Approved' ? 'success' : ($payment['submission_status'] === 'Rejected' ? 'danger' : 'warning text-dark') ?>">
                                            <?= htmlspecialchars($payment['submission_status']) ?>
                                        </span>
                                    <?php else: ?>
                                        <span class="text-muted">—</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <span class="badge bg-<?= $payment['paid'] ? 'success' : 'danger' ?>">
                                        <?= $payment['paid'] ? 'Paid' : 'Unpaid' ?>
                                    </span>
                                </td>
                            </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
<script src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"></script>
<script src="https://cdn.datatables.net/1.13.6/js/dataTables.bootstrap5.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
$(document).ready(function () {
    $('#memberLoanPaymentTable').DataTable({
        pageLength: 10,
        order: [[2, 'asc']]
    });
});
</script>

<?php render_footer(); ?>
</body>
</html>
