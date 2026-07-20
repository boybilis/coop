<?php
include 'db.php';
include 'auth.php';
include 'layout.php';
require_admin();

$cutoffDates = $conn->query("
    SELECT DISTINCT cutoff_date
    FROM payment_submissions
    ORDER BY cutoff_date DESC
");

$selectedCutoff = $_GET['cutoff_date'] ?? '';
$submissions = null;

if ($selectedCutoff !== '') {
    $stmt = $conn->prepare("
        SELECT payment_submissions.*, borrowers.name, users.username
        FROM payment_submissions
        JOIN borrowers ON borrowers.id = payment_submissions.borrower_id
        LEFT JOIN users ON users.borrower_id = borrowers.id AND users.status = 'Member'
        WHERE payment_submissions.cutoff_date = ?
        ORDER BY users.username ASC, borrowers.name ASC, payment_submissions.id DESC
    ");
    $stmt->bind_param("s", $selectedCutoff);
    $stmt->execute();
    $submissions = $stmt->get_result();
}
?>
<!DOCTYPE html>
<html>
<head>
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Received Payments</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
<link rel="stylesheet" href="assets/css/mobile.css">
<link rel="stylesheet" href="assets/css/theme.css?v=20260720-ui">
</head>

<body class="bg-light">
<?php render_navbar(); ?>
<div class="container mt-4">

<div class="d-flex justify-content-between align-items-center mb-3">
    <h3 class="mb-0">Received Payments</h3>
    <div>
        <a href="index.php" class="btn btn-outline-secondary">Dashboard</a>
    </div>
</div>

<?php if(isset($_GET['verified'])): ?>
    <script>window.appToasts = window.appToasts || []; window.appToasts.push({type:'success', message:'Payment verified. Loan payment and capital contribution were recorded.'});</script>
<?php endif; ?>

<?php if(isset($_GET['error'])): ?>
    <script>window.appToasts = window.appToasts || []; window.appToasts.push({type:'error', message:<?= json_encode($_GET['error']) ?>});</script>
<?php endif; ?>

<div class="card shadow-sm mb-4">
    <div class="card-body">
        <form method="GET" class="row g-2 align-items-end">
            <div class="col-md-4">
                <label>Cutoff Date</label>
                <select name="cutoff_date" class="form-control" required>
                    <option value="">Select cutoff date</option>
                    <?php while($cutoff = $cutoffDates->fetch_assoc()): ?>
                        <option value="<?= $cutoff['cutoff_date'] ?>" <?= $selectedCutoff === $cutoff['cutoff_date'] ? 'selected' : '' ?>>
                            <?= $cutoff['cutoff_date'] ?>
                        </option>
                    <?php endwhile; ?>
                </select>
                <small class="text-muted">Usually every 15th and end of month.</small>
            </div>

            <div class="col-md-2">
                <button class="btn btn-primary w-100">View Payments</button>
            </div>
        </form>
    </div>
</div>

<div class="card shadow">
    <div class="card-header">
        <h5 class="mb-0">
            Payments <?= $selectedCutoff ? 'for ' . htmlspecialchars($selectedCutoff) : '' ?>
        </h5>
    </div>

    <div class="card-body">
        <div class="table-responsive">
            <table class="table table-bordered table-hover align-middle">
                <thead class="table-dark">
                    <tr>
                        <th>Borrower</th>
                        <th>Cap Con</th>
                        <th>Loan</th>
                        <th>Total Amount</th>
                        <th>Reference</th>
                        <th>Image File</th>
                        <th>Status</th>
                        <th width="130">Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if(!$submissions): ?>
                        <tr>
                            <td colspan="8" class="text-center text-muted">Select a cutoff date to view payments.</td>
                        </tr>
                    <?php elseif($submissions->num_rows === 0): ?>
                        <tr>
                            <td colspan="8" class="text-center text-muted">No payments submitted for this cutoff.</td>
                        </tr>
                    <?php else: ?>
                        <?php while($row = $submissions->fetch_assoc()): ?>
                        <?php $totalAmount = $row['capital_contribution'] + $row['loan_payment']; ?>
                        <tr>
                            <td><?php render_member_identity($row['username'] ?? '', $row['name']); ?></td>
                            <td>₱<?= number_format($row['capital_contribution'],2) ?></td>
                            <td>₱<?= number_format($row['loan_payment'],2) ?></td>
                            <td><strong>₱<?= number_format($totalAmount,2) ?></strong></td>
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
                            <td>
                                <?php if($row['status'] === 'Pending'): ?>
                                    <form method="POST" action="ajax/verify_payment_submission.php" data-confirm="Verify this payment and record it?" data-confirm-ok="Verify" data-confirm-class="btn-success">
                                        <input type="hidden" name="submission_id" value="<?= $row['id'] ?>">
                                        <button class="btn btn-success btn-sm w-100">Verified</button>
                                    </form>
                                <?php else: ?>
                                    <span class="text-muted">Verified</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endwhile; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

</div>
<?php render_footer(); ?>
</body>
</html>
