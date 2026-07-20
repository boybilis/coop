<?php
include 'db.php';
include 'auth.php';
include 'layout.php';
require_admin();

$statusFilter = $_GET['status'] ?? 'Pending';

if (!in_array($statusFilter, ['Pending', 'Approved', 'Rejected', 'All'], true)) {
    $statusFilter = 'Pending';
}

if ($statusFilter === 'All') {
    $requests = $conn->query("
        SELECT savings_withdrawal_requests.*, borrowers.name, users.username
        FROM savings_withdrawal_requests
        JOIN borrowers ON borrowers.id = savings_withdrawal_requests.borrower_id
        LEFT JOIN users ON users.borrower_id = borrowers.id AND users.status = 'Member'
        ORDER BY savings_withdrawal_requests.created_at DESC
    ");
} else {
    $stmt = $conn->prepare("
        SELECT savings_withdrawal_requests.*, borrowers.name, users.username
        FROM savings_withdrawal_requests
        JOIN borrowers ON borrowers.id = savings_withdrawal_requests.borrower_id
        LEFT JOIN users ON users.borrower_id = borrowers.id AND users.status = 'Member'
        WHERE savings_withdrawal_requests.status = ?
        ORDER BY savings_withdrawal_requests.created_at ASC
    ");
    $stmt->bind_param("s", $statusFilter);
    $stmt->execute();
    $requests = $stmt->get_result();
}
?>
<!DOCTYPE html>
<html>
<head>
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Received Withdrawals</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
<link rel="stylesheet" href="assets/css/mobile.css">
<link rel="stylesheet" href="assets/css/theme.css?v=20260720-ui">
</head>

<body class="bg-light">
<?php render_navbar(); ?>
<div class="container mt-4">

<div class="d-flex justify-content-between align-items-center mb-3">
    <h3 class="mb-0">Received Withdrawals</h3>
    <div>
        <a href="index.php" class="btn btn-outline-secondary">Dashboard</a>
        <a href="savings.php" class="btn btn-outline-primary">Savings Management</a>
    </div>
</div>

<?php if(isset($_GET['verified'])): ?>
    <script>window.appToasts = window.appToasts || []; window.appToasts.push({type:'success', message:'Withdrawal approved and deducted from member savings.'});</script>
<?php endif; ?>

<?php if(isset($_GET['error'])): ?>
    <script>window.appToasts = window.appToasts || []; window.appToasts.push({type:'error', message:<?= json_encode($_GET['error']) ?>});</script>
<?php endif; ?>

<div class="card shadow-sm mb-4">
    <div class="card-body">
        <form method="GET" class="row g-2 align-items-end">
            <div class="col-md-3">
                <label>Status</label>
                <select name="status" class="form-control">
                    <?php foreach(['Pending', 'Approved', 'Rejected', 'All'] as $status): ?>
                        <option value="<?= $status ?>" <?= $statusFilter === $status ? 'selected' : '' ?>>
                            <?= $status ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="col-md-2">
                <button class="btn btn-primary w-100">View</button>
            </div>
        </form>
    </div>
</div>

<div class="card shadow">
    <div class="card-header">
        <h5 class="mb-0">Withdrawal Requests</h5>
    </div>

    <div class="card-body">
        <div class="table-responsive">
            <table class="table table-bordered table-hover align-middle">
                <thead class="table-dark">
                    <tr>
                        <th>Borrower</th>
                        <th>Amount</th>
                        <th>GCash Name</th>
                        <th>GCash Number</th>
                        <th>Admin Reference</th>
                        <th>Image File</th>
                        <th>Approved Date</th>
                        <th>Status</th>
                        <th width="130">Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if($requests->num_rows === 0): ?>
                        <tr>
                            <td colspan="9" class="text-center text-muted">No withdrawal requests found.</td>
                        </tr>
                    <?php endif; ?>

                    <?php while($row = $requests->fetch_assoc()): ?>
                    <tr>
                        <td><?php render_member_identity($row['username'] ?? '', $row['name']); ?></td>
                        <td>&#8369;<?= number_format($row['amount'],2) ?></td>
                        <td><?= htmlspecialchars($row['gcash_name']) ?></td>
                        <td><?= htmlspecialchars($row['gcash_number']) ?></td>
                        <td><?= htmlspecialchars($row['admin_reference_number'] ?? '—') ?></td>
                        <td>
                            <?php if($row['admin_proof_image']): ?>
                                <a href="<?= htmlspecialchars($row['admin_proof_image']) ?>" data-image-preview class="btn btn-outline-primary btn-sm">
                                    View Image
                                </a>
                            <?php else: ?>
                                <span class="text-muted">—</span>
                            <?php endif; ?>
                        </td>
                        <td><?= $row['approved_at'] ?: '—' ?></td>
                        <td>
                            <span class="badge bg-<?= $row['status'] === 'Approved' ? 'success' : ($row['status'] === 'Rejected' ? 'danger' : 'warning text-dark') ?>">
                                <?= $row['status'] ?>
                            </span>
                        </td>
                        <td>
                            <?php if($row['status'] === 'Pending'): ?>
                                <button
                                    class="btn btn-success btn-sm w-100"
                                    data-bs-toggle="modal"
                                    data-bs-target="#verifyWithdrawalModal"
                                    onclick="setWithdrawalRequest(<?= $row['id'] ?>)">
                                    Verified
                                </button>
                            <?php else: ?>
                                <span class="text-muted">Verified</span>
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

<div class="modal fade" id="verifyWithdrawalModal">
  <div class="modal-dialog">
    <div class="modal-content">
      <form method="POST" action="ajax/verify_withdrawal_request.php" enctype="multipart/form-data" data-confirm="Approve this withdrawal and deduct member savings?" data-confirm-ok="Approve Withdrawal" data-confirm-class="btn-success">
        <div class="modal-header">
            <h5 class="modal-title">Verify Withdrawal</h5>
            <button class="btn-close" data-bs-dismiss="modal"></button>
        </div>

        <div class="modal-body">
            <input type="hidden" name="request_id" id="withdrawalRequestId">

            <div class="mb-3">
                <label>GCash Transaction Reference Number</label>
                <input type="text" name="admin_reference_number" class="form-control" required>
            </div>

            <div class="mb-3">
                <label>GCash Transaction Image</label>
                <input type="file" name="admin_proof_image" class="form-control" accept="image/jpeg,image/png,image/webp" required>
            </div>
        </div>

        <div class="modal-footer">
            <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
            <button class="btn btn-success">Approve Withdrawal</button>
        </div>
      </form>
    </div>
  </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
function setWithdrawalRequest(id){
    document.getElementById('withdrawalRequestId').value = id;
}
</script>
<?php render_footer(); ?>
</body>
</html>

