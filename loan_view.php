<?php
include 'db.php';
include 'auth.php';
include 'layout.php';
require_admin();

// =============================
// SAFE LOAN ID
// =============================
$loan_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

// =============================
// GET LOAN + BORROWER INFO
// =============================
$loanStmt = $conn->prepare("
    SELECT
        loans.*,
        borrowers.name,
        users.username,
        (SELECT COUNT(*) FROM payments WHERE loan_id = loans.id) AS total_payments,
        (SELECT COUNT(*) FROM payments WHERE loan_id = loans.id AND paid = 1) AS paid_payments
    FROM loans
    JOIN borrowers ON borrowers.id = loans.borrower_id
    LEFT JOIN users ON users.borrower_id = borrowers.id AND users.status = 'Member'
    WHERE loans.id = ?
");
$loanStmt->bind_param("i", $loan_id);
$loanStmt->execute();
$loanInfo = $loanStmt->get_result()->fetch_assoc();

// =============================
// PAYMENTS
// =============================
$stmt = $conn->prepare("
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
");
$stmt->bind_param("ii", $loanInfo['borrower_id'], $loan_id);
$stmt->execute();
$res = $stmt->get_result();
?>

<!DOCTYPE html>
<html>
<head>
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Member Loan Details</title>

<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
<link rel="stylesheet" href="assets/css/mobile.css">
<link rel="stylesheet" href="assets/css/theme.css?v=20260720-ui">

<link rel="stylesheet" href="https://cdn.datatables.net/1.13.6/css/dataTables.bootstrap5.min.css">

</head>

<body class="bg-light">
<?php render_navbar(); ?>

<div class="container mt-4">

<!-- ================= LOAN SUMMARY ================= -->
<div class="card loan-summary-card member-loan-summary shadow-sm mb-3">
    <div class="card-body">
        <div class="row g-3">
            <div class="col-md-4">
                <small class="text-muted">Loan ID</small>
                <h5>#<?= $loanInfo['id'] ?></h5>
            </div>
            <div class="col-md-4">
                <small class="text-muted">Member</small>
                <div class="h5 mb-0"><?php render_member_identity($loanInfo['username'] ?? '', $loanInfo['name']); ?></div>
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
                <h5>&#8369;<?= number_format($loanInfo['amount'], 2) ?></h5>
            </div>
            <div class="col-md-3">
                <small class="text-muted">Interest</small>
                <h5 class="text-success">&#8369;<?= number_format($loanInfo['interest'], 2) ?></h5>
            </div>
            <div class="col-md-3">
                <small class="text-muted">Total Payable</small>
                <h5>&#8369;<?= number_format($loanInfo['total_payable'], 2) ?></h5>
            </div>
            <div class="col-md-3">
                <small class="text-muted">Months</small>
                <h5><?= number_format($loanInfo['months'], 1) ?></h5>
            </div>
            <div class="col-md-6">
                <small class="text-muted">Disbursement Reference</small>
                <h5><?= $loanInfo['disbursement_reference_number'] ? htmlspecialchars($loanInfo['disbursement_reference_number']) : '—' ?></h5>
            </div>
            <div class="col-md-6">
                <small class="text-muted">Disbursement Proof</small><br>
                <?php if($loanInfo['disbursement_proof_image']): ?>
                    <a href="<?= htmlspecialchars($loanInfo['disbursement_proof_image']) ?>" data-image-preview class="btn btn-outline-primary btn-sm">
                        View Image
                    </a>
                <?php else: ?>
                    <span class="text-muted">—</span>
                <?php endif; ?>
            </div>
            <div class="col-12">
                <small class="text-muted">Payment Progress</small>
                <?php $progress = ((int)$loanInfo['total_payments'] > 0) ? round(((int)$loanInfo['paid_payments'] / (int)$loanInfo['total_payments']) * 100) : 0; ?>
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
<!-- ================= PAYMENT TABLE ================= -->
<div class="card shadow mb-3">
<div class="card-body">

<div class="table-responsive">
<table id="paymentTable" class="table table-bordered table-hover">

<thead class="table-dark">
<tr>
    <th>Payment #</th>
    <th>Amount</th>
    <th>Due Date</th>
    <th>Submitted</th>
    <th>Reference</th>
    <th>Proof Image</th>
    <th>Submission Status</th>
    <th>Status</th>
    <th>Action</th>
</tr>
</thead>

<tbody>

<?php while($row = $res->fetch_assoc()): ?>
<tr>
    <td><?= $row['payment_no'] ?></td>

    <td>â‚±<?= number_format($row['amount'],2) ?></td>

    <td><?= $row['due_date'] ?></td>

    <td>
        <?php if($row['reference_number']): ?>
            <small>
                Capital: â‚±<?= number_format($row['capital_contribution'],2) ?><br>
                Loan: â‚±<?= number_format($row['loan_payment'],2) ?><br>
                Date: <?= $row['payment_date'] ?>
            </small>
        <?php else: ?>
            <span class="text-muted">â€”</span>
        <?php endif; ?>
    </td>

    <td>
        <?= $row['reference_number'] ? htmlspecialchars($row['reference_number']) : '<span class="text-muted">â€”</span>' ?>
    </td>

    <td>
        <?php if($row['proof_image']): ?>
            <a href="<?= htmlspecialchars($row['proof_image']) ?>" data-image-preview class="btn btn-outline-primary btn-sm">
                View Image
            </a>
        <?php else: ?>
            <span class="text-muted">â€”</span>
        <?php endif; ?>
    </td>

    <td>
        <?php if($row['submission_status']): ?>
            <span class="badge bg-<?= $row['submission_status'] === 'Approved' ? 'success' : ($row['submission_status'] === 'Rejected' ? 'danger' : 'warning text-dark') ?>">
                <?= $row['submission_status'] ?>
            </span>
        <?php else: ?>
            <span class="text-muted">â€”</span>
        <?php endif; ?>
    </td>

    <td>
        <span class="badge bg-<?= $row['paid'] ? 'success' : 'danger' ?>">
            <?= $row['paid'] ? 'Paid' : 'Unpaid' ?>
        </span>
    </td>

    <td>
        <?php if(!$row['paid']): ?>
            <button class="btn btn-success btn-sm"
                onclick="markPaid(<?= $row['id'] ?>)">
                Mark Paid
            </button>
        <?php else: ?>
            <span class="text-muted">â€”</span>
        <?php endif; ?>
    </td>
</tr>
<?php endwhile; ?>

</tbody>

</table>
</div>

</div>

<div class="card-footer text-end">
<a href="index.php" class="btn btn-info">
    Back to Dashboard
</a>
</div>

</div>

</div>

<!-- ================= SCRIPTS ================= -->

<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>

<script src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"></script>
<script src="https://cdn.datatables.net/1.13.6/js/dataTables.bootstrap5.min.js"></script>

<script>
$(document).ready(function () {
    $('#paymentTable').DataTable({
        pageLength: 10,
        order: [[2, 'asc']]
    });
});

function markPaid(id){
    appConfirm('Mark this payment as paid?', {
        okText: 'Mark Paid',
        okClass: 'btn-success'
    }).then(confirmed => {
        if(!confirmed){
            return;
        }

        fetch('ajax/mark_paid.php?id=' + id)
        .then(res => res.text())
        .then(() => location.reload());
    });
}
</script>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>

<?php render_footer(); ?>
</body>
</html>


