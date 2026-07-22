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
$serviceFeeRates = $conn->query("
    SELECT loan_service_fee_rates.*, users.username AS created_by_username
    FROM loan_service_fee_rates
    LEFT JOIN users ON users.id = loan_service_fee_rates.created_by
    ORDER BY implementation_date DESC, id DESC
");
$currentServiceFeeRate = cooperative_effective_service_fee_rate($conn, date('Y-m-d'));
$lastBackup = $conn->query("
    SELECT created_at, username
    FROM audit_trails
    WHERE action = 'download_database_backup'
    ORDER BY created_at DESC, id DESC
    LIMIT 1
")->fetch_assoc();
$paymentScheduleSettings = $conn->query("
    SELECT loan_payment_schedule_settings.*, users.username AS created_by_username
    FROM loan_payment_schedule_settings
    LEFT JOIN users ON users.id = loan_payment_schedule_settings.created_by
    ORDER BY implementation_date DESC, id DESC
");
$currentPaymentSchedule = cooperative_effective_payment_schedule_setting($conn, date('Y-m-d'));

function payment_schedule_label($setting)
{
    if ($setting['payment_type'] === 'monthly') {
        return 'Monthly - Day ' . (int)$setting['monthly_day'];
    }

    if ($setting['payment_type'] === 'weekly') {
        $days = [1 => 'Monday', 2 => 'Tuesday', 3 => 'Wednesday', 4 => 'Thursday', 5 => 'Friday', 6 => 'Saturday', 7 => 'Sunday'];
        return 'Weekly - ' . ($days[(int)$setting['weekly_day']] ?? 'Friday');
    }

    return 'Semi-monthly - Day ' . (int)$setting['semi_monthly_day_one'] . ' and Day ' . (int)$setting['semi_monthly_day_two'];
}
?>
<!DOCTYPE html>
<html>
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>System Settings</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
<link rel="stylesheet" href="assets/css/mobile.css">
<link rel="stylesheet" href="assets/css/theme.css?v=20260722-placeholders">
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
    <?php if(isset($_GET['service_fee_saved'])): ?>
        <script>window.appToasts = window.appToasts || []; window.appToasts.push({type:'success', message:'Loan service fee rate saved.'});</script>
    <?php endif; ?>
    <?php if(isset($_GET['payment_schedule_saved'])): ?>
        <script>window.appToasts = window.appToasts || []; window.appToasts.push({type:'success', message:'Loan payment schedule saved.'});</script>
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

    <div class="row">
        <div class="col-lg-5 mb-3">
            <div class="card shadow">
                <div class="card-header">
                    <h5 class="mb-0">Loan Service Fee</h5>
                </div>
                <div class="card-body">
                    <p class="text-muted">
                        Current effective one-time service fee:
                        <strong><?= number_format($currentServiceFeeRate['service_fee_rate'], 2) ?>%</strong>
                        since <?= htmlspecialchars($currentServiceFeeRate['implementation_date']) ?>.
                    </p>

                    <form method="POST" action="ajax/save_service_fee_rate.php">
                        <div class="mb-3">
                            <label class="form-label">Service Fee Rate (%)</label>
                            <input type="number" step="0.01" min="0" name="service_fee_rate" class="form-control" required>
                            <small class="text-muted">One-time fee computed from principal loan amount.</small>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Date of Implementation</label>
                            <input type="date" name="implementation_date" class="form-control" required>
                        </div>
                        <button class="btn btn-primary">Save Service Fee</button>
                    </form>
                </div>
            </div>
        </div>

        <div class="col-lg-7 mb-3">
            <div class="card shadow">
                <div class="card-header">
                    <h5 class="mb-0">Service Fee History</h5>
                </div>
                <div class="card-body table-responsive">
                    <table class="table table-bordered table-hover align-middle">
                        <thead class="table-dark">
                            <tr>
                                <th>Service Fee Rate</th>
                                <th>Implementation Date</th>
                                <th>Created By</th>
                                <th>Created At</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php while($serviceFeeRate = $serviceFeeRates->fetch_assoc()): ?>
                                <tr>
                                    <td><?= number_format($serviceFeeRate['service_fee_rate'], 2) ?>%</td>
                                    <td><?= htmlspecialchars($serviceFeeRate['implementation_date']) ?></td>
                                    <td><?= htmlspecialchars($serviceFeeRate['created_by_username'] ?? 'System') ?></td>
                                    <td><?= htmlspecialchars($serviceFeeRate['created_at']) ?></td>
                                </tr>
                            <?php endwhile; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <div class="row">
        <div class="col-lg-5 mb-3">
            <div class="card shadow">
                <div class="card-header">
                    <h5 class="mb-0">Loan Payment Schedule</h5>
                </div>
                <div class="card-body">
                    <p class="text-muted">
                        Current effective schedule:
                        <strong><?= htmlspecialchars(payment_schedule_label($currentPaymentSchedule)) ?></strong>
                        since <?= htmlspecialchars($currentPaymentSchedule['implementation_date']) ?>.
                    </p>

                    <form method="POST" action="ajax/save_payment_schedule_setting.php" id="paymentScheduleForm">
                        <div class="mb-3">
                            <label class="form-label">Payment Type</label>
                            <select name="payment_type" id="paymentType" class="form-select" required>
                                <option value="monthly">Monthly</option>
                                <option value="semi_monthly" selected>Semi-monthly</option>
                                <option value="weekly">Weekly</option>
                            </select>
                        </div>

                        <div class="mb-3 payment-schedule-field" data-payment-field="monthly">
                            <label class="form-label">Monthly Cut-off Day</label>
                            <input type="number" min="1" max="31" name="monthly_day" class="form-control" value="15">
                            <small class="text-muted">If the selected day is beyond the month length, the system uses month-end.</small>
                        </div>

                        <div class="row payment-schedule-field" data-payment-field="semi_monthly">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">First Cut-off Day</label>
                                <input type="number" min="1" max="31" name="semi_monthly_day_one" class="form-control" value="15">
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Second Cut-off Day</label>
                                <input type="number" min="1" max="31" name="semi_monthly_day_two" class="form-control" value="31">
                            </div>
                            <div class="col-12 mb-3">
                                <small class="text-muted">Example: 15/31 means 15th and month-end. 15/30 means 15th and 30th, but February uses month-end.</small>
                            </div>
                        </div>

                        <div class="mb-3 payment-schedule-field" data-payment-field="weekly">
                            <label class="form-label">Weekly Cut-off Day</label>
                            <select name="weekly_day" class="form-select">
                                <option value="1">Monday</option>
                                <option value="2">Tuesday</option>
                                <option value="3">Wednesday</option>
                                <option value="4">Thursday</option>
                                <option value="5" selected>Friday</option>
                                <option value="6">Saturday</option>
                                <option value="7">Sunday</option>
                            </select>
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Date of Implementation</label>
                            <input type="date" name="implementation_date" class="form-control" required>
                        </div>
                        <button class="btn btn-primary">Save Payment Schedule</button>
                    </form>
                </div>
            </div>
        </div>

        <div class="col-lg-7 mb-3">
            <div class="card shadow">
                <div class="card-header">
                    <h5 class="mb-0">Payment Schedule History</h5>
                </div>
                <div class="card-body table-responsive">
                    <table class="table table-bordered table-hover align-middle">
                        <thead class="table-dark">
                            <tr>
                                <th>Schedule</th>
                                <th>Implementation Date</th>
                                <th>Created By</th>
                                <th>Created At</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php while($paymentSchedule = $paymentScheduleSettings->fetch_assoc()): ?>
                                <tr>
                                    <td><?= htmlspecialchars(payment_schedule_label(cooperative_normalize_payment_schedule_setting($paymentSchedule))) ?></td>
                                    <td><?= htmlspecialchars($paymentSchedule['implementation_date']) ?></td>
                                    <td><?= htmlspecialchars($paymentSchedule['created_by_username'] ?? 'System') ?></td>
                                    <td><?= htmlspecialchars($paymentSchedule['created_at']) ?></td>
                                </tr>
                            <?php endwhile; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <div class="row">
        <div class="col-12 mb-3">
            <div class="card shadow border-danger">
                <div class="card-header bg-danger text-white">
                    <h5 class="mb-0">Full Data Backup</h5>
                </div>
                <div class="card-body">
                    <p class="text-muted mb-3">
                        Download a full MySQL backup containing all database tables, structure, and data.
                        This file can be imported later through phpMyAdmin if restoration is needed.
                    </p>
                    <a href="ajax/download_database_backup.php" class="btn btn-danger" id="downloadDatabaseBackupBtn">
                        Download Full MySQL Backup
                    </a>
                    <small class="text-muted ms-2 d-inline-block">
                        SuperAdmin only. Keep this file private.
                    </small>
                    <div class="mt-3">
                        <strong>Last Backup:</strong>
                        <span id="lastBackupText">
                            <?php if($lastBackup): ?>
                                <?= htmlspecialchars(date('M d, Y h:i A', strtotime($lastBackup['created_at']))) ?>
                                by <?= htmlspecialchars($lastBackup['username'] ?? 'System') ?>
                            <?php else: ?>
                                No backup recorded yet.
                            <?php endif; ?>
                        </span>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function () {
    const paymentType = document.getElementById('paymentType');
    const fields = document.querySelectorAll('.payment-schedule-field');
    const backupButton = document.getElementById('downloadDatabaseBackupBtn');
    const lastBackupText = document.getElementById('lastBackupText');

    function togglePaymentScheduleFields() {
        fields.forEach(function (field) {
            const active = field.dataset.paymentField === paymentType.value;
            field.classList.toggle('d-none', !active);
            field.querySelectorAll('input, select').forEach(function (input) {
                input.disabled = !active;
            });
        });
    }

    paymentType.addEventListener('change', togglePaymentScheduleFields);
    togglePaymentScheduleFields();

    if (backupButton) {
        backupButton.addEventListener('click', async function (event) {
            if (!window.fetch || !window.URL || !window.Blob) {
                return;
            }

            event.preventDefault();
            const originalText = backupButton.textContent;
            backupButton.classList.add('disabled');
            backupButton.textContent = 'Preparing Backup...';

            try {
                const response = await fetch(backupButton.href, {
                    credentials: 'same-origin',
                    cache: 'no-store'
                });

                if (!response.ok) {
                    throw new Error('Backup download failed.');
                }

                const blob = await response.blob();
                const disposition = response.headers.get('Content-Disposition') || '';
                const match = disposition.match(/filename="([^"]+)"/);
                const filename = match ? match[1] : 'cooperative_database_backup.sql';
                const downloadUrl = window.URL.createObjectURL(blob);
                const link = document.createElement('a');
                link.href = downloadUrl;
                link.download = filename;
                document.body.appendChild(link);
                link.click();
                link.remove();
                window.URL.revokeObjectURL(downloadUrl);

                const now = new Date();
                if (lastBackupText) {
                    lastBackupText.textContent = now.toLocaleString([], {
                        year: 'numeric',
                        month: 'short',
                        day: '2-digit',
                        hour: '2-digit',
                        minute: '2-digit'
                    }) + ' by ' + <?= json_encode($_SESSION['username'] ?? 'System') ?>;
                }

                if (window.appShowToast) {
                    window.appShowToast('Full database backup downloaded.', 'success');
                }
            } catch (error) {
                if (window.appShowToast) {
                    window.appShowToast(error.message || 'Backup download failed.', 'error');
                }
            } finally {
                backupButton.classList.remove('disabled');
                backupButton.textContent = originalText;
            }
        });
    }
});
</script>
<?php render_footer(); ?>
</body>
</html>
