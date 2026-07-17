<?php
include 'db.php';
include 'auth.php';
require_admin();

// =============================
// SAFE LOAN ID
// =============================
$loan_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

// =============================
// GET LOAN + BORROWER INFO
// =============================
$loanStmt = $conn->prepare("
    SELECT loans.*, borrowers.name 
    FROM loans
    JOIN borrowers ON borrowers.id = loans.borrower_id
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

<link rel="stylesheet" href="https://cdn.datatables.net/1.13.6/css/dataTables.bootstrap5.min.css">

</head>

<body class="bg-light">

<div class="container mt-4">

<!-- ================= LOAN SUMMARY ================= -->
<div class="card mb-3">
<div class="card-body">

<h4>Member Loan Details</h4>

<p><strong>Loan ID:</strong> <?= $loan_id ?></p>

<p><strong>Member:</strong> <?= htmlspecialchars($loanInfo['name']) ?></p>

<p><strong>Amount:</strong> ₱<?= number_format($loanInfo['amount'],2) ?></p>

<p><strong>Months:</strong> <?= $loanInfo['months'] ?></p>

<p><strong>Interest:</strong> ₱<?= number_format($loanInfo['interest'],2) ?></p>

<p><strong>Total Payable:</strong> ₱<?= number_format($loanInfo['total_payable'],2) ?></p>

</div>
</div>

<!-- ================= PAYMENT TABLE ================= -->
<div class="card shadow mb-3">
<div class="card-body">

<div class="table-responsive">
<table id="paymentTable" class="table table-bordered table-hover">

<thead class="table-dark">
<tr>
    <th>Loan ID</th>
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
    <td><?= $loan_id ?></td>

    <td><?= $row['payment_no'] ?></td>

    <td>₱<?= number_format($row['amount'],2) ?></td>

    <td><?= $row['due_date'] ?></td>

    <td>
        <?php if($row['reference_number']): ?>
            <small>
                Capital: ₱<?= number_format($row['capital_contribution'],2) ?><br>
                Loan: ₱<?= number_format($row['loan_payment'],2) ?><br>
                Date: <?= $row['payment_date'] ?>
            </small>
        <?php else: ?>
            <span class="text-muted">—</span>
        <?php endif; ?>
    </td>

    <td>
        <?= $row['reference_number'] ? htmlspecialchars($row['reference_number']) : '<span class="text-muted">—</span>' ?>
    </td>

    <td>
        <?php if($row['proof_image']): ?>
            <a href="<?= htmlspecialchars($row['proof_image']) ?>" target="_blank" class="btn btn-outline-primary btn-sm">
                View Image
            </a>
        <?php else: ?>
            <span class="text-muted">—</span>
        <?php endif; ?>
    </td>

    <td>
        <?php if($row['submission_status']): ?>
            <span class="badge bg-<?= $row['submission_status'] === 'Approved' ? 'success' : ($row['submission_status'] === 'Rejected' ? 'danger' : 'warning text-dark') ?>">
                <?= $row['submission_status'] ?>
            </span>
        <?php else: ?>
            <span class="text-muted">—</span>
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
            <span class="text-muted">—</span>
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
        order: [[3, 'asc']]
    });
});

function markPaid(id){
    if(confirm("Mark this payment as paid?")){
        fetch('ajax/mark_paid.php?id=' + id)
        .then(res => res.text())
        .then(() => location.reload());
    }
}
</script>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>

</body>
</html>
