<?php
include 'db.php';
include 'auth.php';
include 'layout.php';
require_admin();

$requests = $conn->query("
    SELECT loan_requests.*, borrowers.name, users.username
    FROM loan_requests
    JOIN borrowers ON borrowers.id = loan_requests.borrower_id
    LEFT JOIN users ON users.borrower_id = borrowers.id AND users.status = 'Member'
    ORDER BY
        CASE loan_requests.status WHEN 'Pending' THEN 0 WHEN 'Approved' THEN 1 ELSE 2 END,
        loan_requests.created_at ASC
");
?>
<!DOCTYPE html>
<html>
<head>
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Loan Requests</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
<link rel="stylesheet" href="assets/css/mobile.css">
<link rel="stylesheet" href="assets/css/theme.css">
<style>
    .loan-requests-table {
        min-width: 1120px;
    }

    .loan-approval-cell {
        min-width: 220px;
    }

    .loan-action-group {
        display: flex;
        gap: .5rem;
        align-items: center;
        min-width: 200px;
    }
</style>
</head>

<body class="bg-light">
<?php render_navbar(); ?>
<div class="container mt-4">

<div class="d-flex justify-content-between align-items-center mb-3">
    <h3 class="mb-0">Loan Requests</h3>
    <div>
        <a href="index.php" class="btn btn-outline-secondary">Dashboard</a>
        <a href="loans.php" class="btn btn-outline-primary">Loan Management</a>
    </div>
</div>

<?php if(isset($_GET['approved'])): ?>
    <script>window.appToasts = window.appToasts || []; window.appToasts.push({type:'success', message:'Loan request approved and saved as a loan.'});</script>
<?php endif; ?>

<?php if(isset($_GET['rejected'])): ?>
    <script>window.appToasts = window.appToasts || []; window.appToasts.push({type:'warning', message:'Loan request rejected.'});</script>
<?php endif; ?>

<?php if(isset($_GET['error'])): ?>
    <script>window.appToasts = window.appToasts || []; window.appToasts.push({type:'error', message:<?= json_encode($_GET['error']) ?>});</script>
<?php endif; ?>

<div class="card shadow">
    <div class="card-header">
        <h5 class="mb-0">First Come, First Served Queue</h5>
    </div>

    <div class="card-body">
        <div class="table-responsive">
            <table class="table table-bordered table-hover align-middle loan-requests-table">
                <thead class="table-dark">
                    <tr>
                        <th>Queue</th>
                        <th>Member</th>
                        <th>Borrower For</th>
                        <th>Requested Amount</th>
                        <th>Requested Months</th>
                        <th>Date Requested</th>
                        <th>Status</th>
                        <th class="loan-approval-cell">Approval</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if($requests->num_rows === 0): ?>
                        <tr>
                            <td colspan="8" class="text-center text-muted">No loan requests yet.</td>
                        </tr>
                    <?php endif; ?>

                    <?php $queue = 1; ?>
                    <?php while($row = $requests->fetch_assoc()): ?>
                    <tr>
                        <td><?= $row['status'] === 'Pending' ? $queue++ : '—' ?></td>
                        <td><?php render_member_identity($row['username'] ?? '', $row['name']); ?></td>
                        <td>
                            <?php if((int)($row['is_guarantor'] ?? 0) === 1): ?>
                                <span class="badge bg-info text-dark">Guest Borrower</span><br>
                                <small><?= htmlspecialchars($row['guest_borrower_name'] ?? '') ?></small>
                            <?php else: ?>
                                <span class="badge bg-secondary">Member</span>
                            <?php endif; ?>
                        </td>
                        <td>₱<?= number_format($row['requested_amount'],2) ?></td>
                        <td><?= $row['requested_months'] ?></td>
                        <td><?= $row['created_at'] ?></td>
                        <td>
                            <span class="badge bg-<?= $row['status'] === 'Approved' ? 'success' : ($row['status'] === 'Rejected' ? 'danger' : 'warning text-dark') ?>">
                                <?= $row['status'] ?>
                            </span>
                        </td>
                        <td class="loan-approval-cell">
                            <?php if($row['status'] === 'Pending'): ?>
                                <div class="loan-action-group">
                                    <button type="button" class="btn btn-success btn-sm"
                                        onclick="openApproveLoanRequestModal(<?= (int)$row['id'] ?>, <?= (float)$row['requested_amount'] ?>, <?= (float)$row['requested_months'] ?>)">
                                        Approve
                                    </button>
                                    <form method="POST" action="ajax/reject_loan_request.php" class="m-0" data-confirm="Reject this loan request?" data-confirm-ok="Reject" data-confirm-class="btn-danger">
                                        <input type="hidden" name="request_id" value="<?= (int)$row['id'] ?>">
                                        <button class="btn btn-outline-danger btn-sm">
                                            Reject
                                        </button>
                                    </form>
                                </div>
                            <?php else: ?>
                                <?php if($row['approved_loan_id']): ?>
                                    <a href="loan_view.php?id=<?= $row['approved_loan_id'] ?>" class="btn btn-outline-info btn-sm">
                                        View Loan
                                    </a>
                                <?php else: ?>
                                    <span class="text-muted">Processed</span>
                                <?php endif; ?>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endwhile; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

</div>

<div class="modal fade" id="approveLoanRequestModal" tabindex="-1">
  <div class="modal-dialog">
    <div class="modal-content">
      <form method="POST" action="ajax/approve_loan_request.php" enctype="multipart/form-data" data-confirm="Approve and mark this loan as disbursed?" data-confirm-ok="Approve Loan" data-confirm-class="btn-success">
        <div class="modal-header">
            <h5 class="modal-title">Approve Loan Request</h5>
            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
        </div>
        <div class="modal-body">
            <input type="hidden" name="request_id" id="approveRequestId">

            <div class="mb-3">
                <label class="form-label">Approved Amount</label>
                <input type="number" step="0.01" min="1" name="amount" id="approveAmount" class="form-control" required>
            </div>

            <div class="mb-3">
                <label class="form-label">Approved Months</label>
                <input type="number" step="0.1" min="0.1" max="6" name="months" id="approveMonths" class="form-control" required>
                <small class="text-muted">Maximum payment term is 6 months.</small>
            </div>

            <div class="mb-3">
                <label class="form-label">GCash Disbursement Reference Number</label>
                <input type="text" name="disbursement_reference_number" class="form-control" required>
            </div>

            <div class="mb-3">
                <label class="form-label">GCash Disbursement Proof Image</label>
                <input type="file" name="disbursement_proof_image" class="form-control" accept="image/jpeg,image/png,image/webp" required>
            </div>
        </div>
        <div class="modal-footer">
            <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
            <button class="btn btn-success">Approve Loan</button>
        </div>
      </form>
    </div>
  </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
function openApproveLoanRequestModal(requestId, amount, months){
    document.getElementById('approveRequestId').value = requestId;
    document.getElementById('approveAmount').value = amount;
    document.getElementById('approveMonths').value = months;
    new bootstrap.Modal(document.getElementById('approveLoanRequestModal')).show();
}
</script>
<?php render_footer(); ?>
</body>
</html>
