<?php
include 'db.php';
include 'auth.php';
include 'layout.php';
require_admin();

$loans = $conn->query("
    SELECT loans.*, borrowers.name, users.username,
    (SELECT COUNT(*) FROM payments WHERE loan_id = loans.id) AS total_payments,
    (SELECT COUNT(*) FROM payments WHERE loan_id = loans.id AND paid = 1) AS paid_payments,
    (
        SELECT COUNT(*)
        FROM payment_submissions
        WHERE payment_submissions.borrower_id = loans.borrower_id
        AND payment_submissions.cutoff_date IN (
            SELECT payments.due_date
            FROM payments
            WHERE payments.loan_id = loans.id
        )
    ) AS submission_count
    FROM loans
    JOIN borrowers ON borrowers.id = loans.borrower_id
    LEFT JOIN users ON users.borrower_id = borrowers.id AND users.status = 'Member'
    ORDER BY loans.id DESC
");
?>
<!DOCTYPE html>
<html>
<head>
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Loan Management</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
<link rel="stylesheet" href="assets/css/mobile.css">
<link rel="stylesheet" href="assets/css/theme.css">
</head>

<body class="bg-light">
<?php render_navbar(); ?>
<div class="container mt-4">

<div class="d-flex justify-content-between align-items-center mb-3">
    <h3 class="mb-0">Loan Management</h3>
    <div>
        <a href="index.php" class="btn btn-outline-secondary">Dashboard</a>
        <a href="loan_requests.php" class="btn btn-outline-success">Loan Requests</a>
        <a href="loan_form.php" class="btn btn-primary">+ Add Loan</a>
    </div>
</div>

<div class="card shadow">
    <div class="card-body">
        <div class="table-responsive">
<table class="table table-bordered table-hover align-middle">
            <thead class="table-dark">
                <tr>
                    <th>Member</th>
                    <th>Borrower For</th>
                    <th>Loan</th>
                    <th>Interest</th>
                    <th>Total</th>
                    <th>Progress</th>
                    <th>Submissions</th>
                    <th>Status</th>
                    <th width="120">Action</th>
                </tr>
            </thead>
            <tbody>
                <?php while($row = $loans->fetch_assoc()): ?>
                <?php
                    $progress = ($row['total_payments'] > 0)
                        ? round(($row['paid_payments'] / $row['total_payments']) * 100)
                        : 0;
                ?>
                <tr>
                    <td><?php render_member_identity($row['username'] ?? '', $row['name']); ?></td>
                    <td>
                        <?php if((int)($row['is_guarantor'] ?? 0) === 1): ?>
                            <span class="badge bg-info text-dark">Guest Borrower</span><br>
                            <small><?= htmlspecialchars($row['guest_borrower_name'] ?? '') ?></small>
                        <?php else: ?>
                            <span class="badge bg-secondary">Member</span>
                        <?php endif; ?>
                    </td>
                    <td>₱<?= number_format($row['amount'],2) ?></td>
                    <td class="text-success">₱<?= number_format($row['interest'],2) ?></td>
                    <td><strong>₱<?= number_format($row['total_payable'],2) ?></strong></td>
                    <td width="180">
                        <div class="progress" style="height:20px;">
                            <div class="progress-bar bg-success" style="width:<?= $progress ?>%">
                                <?= $progress ?>%
                            </div>
                        </div>
                    </td>
                    <td>
                        <span class="badge bg-<?= $row['submission_count'] > 0 ? 'info' : 'secondary' ?>">
                            <?= $row['submission_count'] ?>
                        </span>
                    </td>
                    <td>
                        <?php if($row['status'] == 'Active'): ?>
                            <span class="badge bg-warning text-dark">Active</span>
                        <?php else: ?>
                            <span class="badge bg-success">Completed</span>
                        <?php endif; ?>
                    </td>
                    <td>
                        <a href="loan_view.php?id=<?= $row['id'] ?>" class="btn btn-info btn-sm w-100">
                            View
                        </a>
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
