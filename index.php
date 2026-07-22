<?php
include 'db.php';
include 'auth.php';
include 'layout.php';
require_admin();

$activeMembers = $conn->query("
    SELECT COUNT(*) AS total
    FROM borrowers
    WHERE status = 'Active'
")->fetch_assoc()['total'];

$totalMembers = $conn->query("
    SELECT COUNT(*) AS total
    FROM borrowers
")->fetch_assoc()['total'];

$capital = $conn->query("
    SELECT IFNULL(SUM(amount),0) AS total
    FROM capital_contributions
")->fetch_assoc()['total'];

$serviceFees = $conn->query("
    SELECT IFNULL(SUM(service_fee),0) AS total
    FROM loans
")->fetch_assoc()['total'];

$loanableBreakdown = cooperative_loanable_amount_breakdown($conn);
$currentCutoffDate = $loanableBreakdown['cutoff_date'];
$initialCapital = $loanableBreakdown['initial_capital'];
$cutoffCapitalToDate = $loanableBreakdown['cutoff_capital_to_date'];
$paidLoanPrincipalToDate = $loanableBreakdown['paid_loans_this_cutoff'];
$approvedLoanPrincipal = $loanableBreakdown['approved_loan_principal'];
$availableLoanCutoff = $loanableBreakdown['available_amount'];

$outstanding = $conn->query("
    SELECT IFNULL(SUM(amount),0) AS total
    FROM payments
    WHERE paid = 0
")->fetch_assoc()['total'];

$savingsDeposits = $conn->query("
    SELECT IFNULL(SUM(amount),0) AS total
    FROM savings_transactions
    WHERE type = 'DEPOSIT'
")->fetch_assoc()['total'];

$savingsWithdrawals = $conn->query("
    SELECT IFNULL(SUM(amount),0) AS total
    FROM savings_transactions
    WHERE type = 'WITHDRAWAL'
")->fetch_assoc()['total'];

$memberSavings = $savingsDeposits - $savingsWithdrawals;

$pendingLoanRequests = (int)$conn->query("
    SELECT COUNT(*) AS total
    FROM loan_requests
    WHERE status = 'Pending'
")->fetch_assoc()['total'];

$pendingReceivedPayments = (int)$conn->query("
    SELECT COUNT(*) AS total
    FROM payment_submissions
    WHERE status = 'Pending'
")->fetch_assoc()['total'];

$pendingReceivedSavings = (int)$conn->query("
    SELECT COUNT(*) AS total
    FROM savings_submissions
    WHERE status = 'Pending'
")->fetch_assoc()['total'];

$pendingReceivedWithdrawals = (int)$conn->query("
    SELECT COUNT(*) AS total
    FROM savings_withdrawal_requests
    WHERE status = 'Pending'
")->fetch_assoc()['total'];

$pendingSavingsActions = $pendingReceivedSavings + $pendingReceivedWithdrawals;

function notification_badge($count)
{
    if ($count <= 0) {
        return '';
    }

    return '<span class="notification-badge">' . number_format($count) . '</span>';
}
?>
<!DOCTYPE html>
<html>
<head>
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Cooperative Dashboard</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
<link rel="stylesheet" href="assets/css/mobile.css">
<link rel="stylesheet" href="assets/css/theme.css?v=20260722-navbar">
<style>
.notification-badge {
    position: absolute;
    top: -10px;
    right: -10px;
    min-width: 30px;
    height: 30px;
    padding: 0 8px;
    border-radius: 999px;
    background: #dc3545;
    color: #fff;
    font-size: .85rem;
    font-weight: 700;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    box-shadow: 0 0 0 3px #fff;
}

.btn .badge {
    vertical-align: middle;
}
</style>
</head>

<body class="bg-light">
<?php render_navbar(); ?>
<div class="container mt-4">

<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h3 class="mb-1">Cooperative Loan and Savings Management System</h3>
        <div class="text-muted">Admin dashboard</div>
    </div>
</div>

<div class="row mb-4">
    <div class="col-md-3 mb-3">
        <div class="card glass-card glass-success">
            <div class="card-body">
                <h6>Active Members</h6>
                <h3 class="text-success"><?= number_format($activeMembers) ?></h3>
                <small class="text-muted">Total members: <?= number_format($totalMembers) ?></small>
            </div>
            <div class="card-footer">
                <a href="members.php" class="btn btn-success w-100">Member Management</a>
            </div>
        </div>
    </div>

    <div class="col-md-3 mb-3">
        <div class="card glass-card glass-primary position-relative">
            <?= notification_badge($pendingLoanRequests) ?>
            <div class="card-body">
                <h6>Total Outstanding Loans</h6>
                <h3 class="text-primary">&#8369;<?= number_format($outstanding,2) ?></h3>
                <small class="text-muted">Includes unpaid principal and interest</small>
            </div>
            <div class="card-footer">
                <a href="loans.php" class="btn btn-primary w-100">Loan Management</a>
                <a href="loan_requests.php" class="btn btn-outline-primary w-100 mt-2">
                    Loan Requests
                    <?php if($pendingLoanRequests > 0): ?>
                        <span class="badge bg-danger ms-1"><?= number_format($pendingLoanRequests) ?></span>
                    <?php endif; ?>
                </a>
            </div>
        </div>
    </div>

    <div class="col-md-3 mb-3">
        <div class="card glass-card glass-info position-relative">
            <?= notification_badge($pendingSavingsActions) ?>
            <div class="card-body">
                <h6>Member Savings</h6>
                <h3 class="text-info">&#8369;<?= number_format($memberSavings,2) ?></h3>
                <small class="text-muted">Deposits less withdrawals</small>
            </div>
            <div class="card-footer">
                <a href="savings.php" class="btn btn-info w-100">Savings Management</a>
                <div class="row g-2 mt-1">
                    <div class="col-6">
                        <a href="received_savings.php" class="btn btn-outline-info w-100">
                            Received Savings
                            <?php if($pendingReceivedSavings > 0): ?>
                                <span class="badge bg-danger ms-1"><?= number_format($pendingReceivedSavings) ?></span>
                            <?php endif; ?>
                        </a>
                    </div>
                    <div class="col-6">
                        <a href="received_withdrawals.php" class="btn btn-outline-secondary w-100">
                            Received Withdrawals
                            <?php if($pendingReceivedWithdrawals > 0): ?>
                                <span class="badge bg-danger ms-1"><?= number_format($pendingReceivedWithdrawals) ?></span>
                            <?php endif; ?>
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="col-md-3 mb-3">
        <div class="card glass-card glass-warning">
            <div class="card-body">
                <h6>Available Loanable Amount to Date</h6>
                <h3 class="text-warning">&#8369;<?= number_format($availableLoanCutoff,2) ?></h3>
                <small class="text-muted d-block">As of <?= date('M d, Y', strtotime($currentCutoffDate)) ?></small>
                <small class="text-muted d-block">Initial contribution: &#8369;<?= number_format($initialCapital,2) ?></small>
                <small class="text-muted d-block">Capcon to date: &#8369;<?= number_format($cutoffCapitalToDate,2) ?></small>
                <small class="text-muted d-block">Paid loans this cutoff: &#8369;<?= number_format($paidLoanPrincipalToDate,2) ?></small>
                <small class="text-muted d-block">Less approved principal loans: &#8369;<?= number_format($approvedLoanPrincipal,2) ?></small>
            </div>
            <div class="card-footer">
                <a href="capital.php" class="btn btn-warning w-100">Capital Contributions</a>
            </div>
        </div>
    </div>
</div>

<div class="row">
    <div class="col-md-4 mb-3">
        <div class="card glass-card glass-midnight">
            <div class="card-body">
                <h5>Capital Fund</h5>
                <h2>&#8369;<?= number_format($capital,2) ?></h2>
                <p class="text-muted mb-0">Total capital contributions of all members.</p>
            </div>
        </div>
    </div>

    <div class="col-md-4 mb-3">
        <div class="card glass-card glass-warning">
            <div class="card-body">
                <h5>Service Fees</h5>
                <h2>&#8369;<?= number_format($serviceFees,2) ?></h2>
                <p class="text-muted mb-0">Total service fees from all approved loans.</p>
            </div>
        </div>
    </div>

    <div class="col-md-4 mb-3">
        <div class="card glass-card glass-success position-relative">
            <?= notification_badge($pendingReceivedPayments) ?>
            <div class="card-body">
                <h5>Received Payments</h5>
                <p class="text-muted">Review member GCash references by cutoff date and verify posted payments.</p>
                <a href="received_payments.php" class="btn btn-success">
                    Open Received Payments
                    <?php if($pendingReceivedPayments > 0): ?>
                        <span class="badge bg-danger ms-1"><?= number_format($pendingReceivedPayments) ?></span>
                    <?php endif; ?>
                </a>
            </div>
        </div>
    </div>
</div>

</div>
<?php render_footer(); ?>
</body>
</html>

