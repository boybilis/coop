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
    $submissions = $conn->query("
        SELECT savings_submissions.*, borrowers.name, users.username
        FROM savings_submissions
        JOIN borrowers ON borrowers.id = savings_submissions.borrower_id
        LEFT JOIN users ON users.borrower_id = borrowers.id AND users.status = 'Member'
        ORDER BY savings_submissions.created_at DESC
    ");
} else {
    $stmt = $conn->prepare("
        SELECT savings_submissions.*, borrowers.name, users.username
        FROM savings_submissions
        JOIN borrowers ON borrowers.id = savings_submissions.borrower_id
        LEFT JOIN users ON users.borrower_id = borrowers.id AND users.status = 'Member'
        WHERE savings_submissions.status = ?
        ORDER BY savings_submissions.created_at ASC
    ");
    $stmt->bind_param("s", $statusFilter);
    $stmt->execute();
    $submissions = $stmt->get_result();
}
?>
<!DOCTYPE html>
<html>
<head>
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Received Savings</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
<link rel="stylesheet" href="assets/css/mobile.css">
<link rel="stylesheet" href="assets/css/theme.css?v=20260722-navbar">
</head>

<body class="bg-light">
<?php render_navbar(); ?>
<div class="container mt-4">

<div class="d-flex justify-content-between align-items-center mb-3">
    <h3 class="mb-0">Received Savings</h3>
    <div>
        <a href="index.php" class="btn btn-outline-secondary">Dashboard</a>
        <a href="savings.php" class="btn btn-outline-primary">Savings Management</a>
    </div>
</div>

<?php if(isset($_GET['verified'])): ?>
    <script>window.appToasts = window.appToasts || []; window.appToasts.push({type:'success', message:'Savings verified and credited to member savings.'});</script>
<?php endif; ?>

<?php if(isset($_GET['rejected'])): ?>
    <script>window.appToasts = window.appToasts || []; window.appToasts.push({type:'warning', message:'Savings submission rejected.'});</script>
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
        <h5 class="mb-0">Savings Submissions</h5>
    </div>

    <div class="card-body">
        <div class="table-responsive">
            <table class="table table-bordered table-hover align-middle">
                <thead class="table-dark">
                    <tr>
                        <th>Borrower</th>
                        <th>Amount</th>
                        <th>Reference</th>
                        <th>Image File</th>
                        <th>Status</th>
                        <th>Date Submitted</th>
                        <th width="180">Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if($submissions->num_rows === 0): ?>
                        <tr>
                            <td colspan="7" class="text-center text-muted">No savings submissions found.</td>
                        </tr>
                    <?php endif; ?>

                    <?php while($row = $submissions->fetch_assoc()): ?>
                    <tr>
                        <td><?php render_member_identity($row['username'] ?? '', $row['name']); ?></td>
                        <td>&#8369;<?= number_format($row['amount'],2) ?></td>
                        <td><?= htmlspecialchars($row['reference_number']) ?></td>
                        <td>
                            <a href="<?= htmlspecialchars($row['proof_image']) ?>" data-image-preview class="btn btn-outline-primary btn-sm">
                                View Image
                            </a>
                        </td>
                        <td>
                            <span class="badge bg-<?= $row['status'] === 'Approved' ? 'success' : ($row['status'] === 'Rejected' ? 'danger' : 'warning text-dark') ?>">
                                <?= $row['status'] ?>
                            </span>
                        </td>
                        <td><?= $row['created_at'] ?></td>
                        <td>
                            <?php if($row['status'] === 'Pending'): ?>
                                <div class="d-flex gap-1">
                                    <form method="POST" action="ajax/verify_savings_submission.php" data-confirm="Verify this savings submission?" data-confirm-ok="Verify" data-confirm-class="btn-success" class="flex-fill">
                                        <input type="hidden" name="submission_id" value="<?= $row['id'] ?>">
                                        <button class="btn btn-success btn-sm w-100">Verified</button>
                                    </form>
                                    <form method="POST" action="ajax/reject_savings_submission.php" data-confirm="Reject this savings submission?" data-confirm-ok="Reject" data-confirm-class="btn-danger" class="flex-fill">
                                        <input type="hidden" name="submission_id" value="<?= $row['id'] ?>">
                                        <button class="btn btn-outline-danger btn-sm w-100">Reject</button>
                                    </form>
                                </div>
                            <?php else: ?>
                                <span class="text-muted"><?= htmlspecialchars($row['status']) ?></span>
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

