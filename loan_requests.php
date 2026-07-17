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
    <div class="alert alert-success">Loan request approved and saved as a loan.</div>
<?php endif; ?>

<?php if(isset($_GET['error'])): ?>
    <div class="alert alert-danger"><?= htmlspecialchars($_GET['error']) ?></div>
<?php endif; ?>

<div class="card shadow">
    <div class="card-header">
        <h5 class="mb-0">First Come, First Served Queue</h5>
    </div>

    <div class="card-body">
        <div class="table-responsive">
            <table class="table table-bordered table-hover align-middle">
                <thead class="table-dark">
                    <tr>
                        <th>Queue</th>
                        <th>Member</th>
                        <th>Requested Amount</th>
                        <th>Requested Months</th>
                        <th>Date Requested</th>
                        <th>Status</th>
                        <th width="320">Approval</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if($requests->num_rows === 0): ?>
                        <tr>
                            <td colspan="7" class="text-center text-muted">No loan requests yet.</td>
                        </tr>
                    <?php endif; ?>

                    <?php $queue = 1; ?>
                    <?php while($row = $requests->fetch_assoc()): ?>
                    <tr>
                        <td><?= $row['status'] === 'Pending' ? $queue++ : '—' ?></td>
                        <td><?php render_member_identity($row['username'] ?? '', $row['name']); ?></td>
                        <td>₱<?= number_format($row['requested_amount'],2) ?></td>
                        <td><?= $row['requested_months'] ?></td>
                        <td><?= $row['created_at'] ?></td>
                        <td>
                            <span class="badge bg-<?= $row['status'] === 'Approved' ? 'success' : ($row['status'] === 'Rejected' ? 'danger' : 'warning text-dark') ?>">
                                <?= $row['status'] ?>
                            </span>
                        </td>
                        <td>
                            <?php if($row['status'] === 'Pending'): ?>
                                <form method="POST" action="ajax/approve_loan_request.php" class="row g-2">
                                    <input type="hidden" name="request_id" value="<?= $row['id'] ?>">

                                    <div class="col-md-5">
                                        <input type="number" step="0.01" min="1" name="amount" class="form-control form-control-sm" value="<?= $row['requested_amount'] ?>" required>
                                    </div>

                                    <div class="col-md-4">
                                        <input type="number" step="0.1" min="0.1" max="6" name="months" class="form-control form-control-sm" value="<?= $row['requested_months'] ?>" required>
                                    </div>

                                    <div class="col-md-3">
                                        <button class="btn btn-success btn-sm w-100" onclick="return confirm('Approve this loan request?')">
                                            Approve
                                        </button>
                                    </div>
                                </form>
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
<?php render_footer(); ?>
</body>
</html>
