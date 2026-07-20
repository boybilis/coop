<?php
include 'db.php';
include 'auth.php';
include 'layout.php';
require_superadmin();

$rates = $conn->query("
    SELECT loan_interest_rates.*, users.username AS created_by_username
    FROM loan_interest_rates
    LEFT JOIN users ON users.id = loan_interest_rates.created_by
    ORDER BY implementation_date DESC, id DESC
");
$currentRate = cooperative_effective_interest_rate($conn, date('Y-m-d'));
?>
<!DOCTYPE html>
<html>
<head>
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>System Settings</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
<link rel="stylesheet" href="assets/css/mobile.css">
<link rel="stylesheet" href="assets/css/theme.css?v=20260720-ui">
</head>
<body class="bg-light">
<?php render_navbar(); ?>

<div class="container mt-4">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h3 class="mb-0">System Settings</h3>
        <a href="index.php" class="btn btn-outline-secondary">Dashboard</a>
    </div>

    <?php if(isset($_GET['rate_saved'])): ?>
        <script>window.appToasts = window.appToasts || []; window.appToasts.push({type:'success', message:'Loan interest rate saved.'});</script>
    <?php endif; ?>
    <?php if(isset($_GET['error'])): ?>
        <script>window.appToasts = window.appToasts || []; window.appToasts.push({type:'error', message:<?= json_encode($_GET['error']) ?>});</script>
    <?php endif; ?>

    <div class="row">
        <div class="col-lg-5 mb-3">
            <div class="card shadow">
                <div class="card-header">
                    <h5 class="mb-0">Loan Interest Rate</h5>
                </div>
                <div class="card-body">
                    <p class="text-muted">
                        Current effective monthly rate:
                        <strong><?= number_format($currentRate['monthly_rate'], 2) ?>%</strong>
                        since <?= htmlspecialchars($currentRate['implementation_date']) ?>.
                    </p>

                    <form method="POST" action="ajax/save_interest_rate.php">
                        <div class="mb-3">
                            <label class="form-label">Monthly Interest Rate (%)</label>
                            <input type="number" step="0.01" min="0" name="monthly_rate" class="form-control" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Date of Implementation</label>
                            <input type="date" name="implementation_date" class="form-control" required>
                        </div>
                        <button class="btn btn-primary">Save Interest Rate</button>
                    </form>
                </div>
            </div>
        </div>

        <div class="col-lg-7 mb-3">
            <div class="card shadow">
                <div class="card-header">
                    <h5 class="mb-0">Interest Rate History</h5>
                </div>
                <div class="card-body table-responsive">
                    <table class="table table-bordered table-hover align-middle">
                        <thead class="table-dark">
                            <tr>
                                <th>Monthly Rate</th>
                                <th>Implementation Date</th>
                                <th>Created By</th>
                                <th>Created At</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php while($rate = $rates->fetch_assoc()): ?>
                                <tr>
                                    <td><?= number_format($rate['monthly_rate'], 2) ?>%</td>
                                    <td><?= htmlspecialchars($rate['implementation_date']) ?></td>
                                    <td><?= htmlspecialchars($rate['created_by_username'] ?? 'System') ?></td>
                                    <td><?= htmlspecialchars($rate['created_at']) ?></td>
                                </tr>
                            <?php endwhile; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<?php render_footer(); ?>
</body>
</html>
