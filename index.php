<?php
include 'db.php';
include 'auth.php';
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
?>
<!DOCTYPE html>
<html>
<head>
<title>Cooperative Dashboard</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>

<body class="bg-light">
<div class="container mt-4">

<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h3 class="mb-1">Cooperative Loan and Savings Management System</h3>
        <div class="text-muted">Admin dashboard</div>
    </div>
    <a href="logout.php" class="btn btn-outline-danger">Logout</a>
</div>

<div class="row mb-4">
    <div class="col-md-3 mb-3">
        <div class="card border-success shadow-sm">
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
        <div class="card border-primary shadow-sm">
            <div class="card-body">
                <h6>Total Outstanding Loans</h6>
                <h3 class="text-primary">₱<?= number_format($outstanding,2) ?></h3>
                <small class="text-muted">Includes unpaid principal and interest</small>
            </div>
            <div class="card-footer">
                <a href="loans.php" class="btn btn-primary w-100">Loan Management</a>
                <a href="loan_requests.php" class="btn btn-outline-primary w-100 mt-2">Loan Requests</a>
            </div>
        </div>
    </div>

    <div class="col-md-3 mb-3">
        <div class="card border-info shadow-sm">
            <div class="card-body">
                <h6>Member Savings</h6>
                <h3 class="text-info">₱<?= number_format($memberSavings,2) ?></h3>
                <small class="text-muted">Deposits less withdrawals</small>
            </div>
            <div class="card-footer">
                <a href="savings.php" class="btn btn-info w-100">Savings Management</a>
                <div class="row g-2 mt-1">
                    <div class="col-6">
                        <a href="received_savings.php" class="btn btn-outline-info w-100">Received Savings</a>
                    </div>
                    <div class="col-6">
                        <a href="received_withdrawals.php" class="btn btn-outline-secondary w-100">Received Withdrawals</a>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="col-md-3 mb-3">
        <div class="card border-warning shadow-sm">
            <div class="card-body">
                <h6>Total Capital Contributions</h6>
                <h3 class="text-warning">₱<?= number_format($capital,2) ?></h3>
                <small class="text-muted">All member capital contributions</small>
            </div>
            <div class="card-footer">
                <a href="capital.php" class="btn btn-warning w-100">Capital Contributions</a>
            </div>
        </div>
    </div>
</div>

<div class="row">
    <div class="col-md-6 mb-3">
        <div class="card shadow-sm">
            <div class="card-body">
                <h5>Capital Fund</h5>
                <h2>₱<?= number_format($capital,2) ?></h2>
                <p class="text-muted mb-0">Total capital contributions of all members.</p>
            </div>
        </div>
    </div>

    <div class="col-md-6 mb-3">
        <div class="card shadow-sm border-success">
            <div class="card-body">
                <h5>Received Payments</h5>
                <p class="text-muted">Review member GCash references by cutoff date and verify posted payments.</p>
                <a href="received_payments.php" class="btn btn-success">Open Received Payments</a>
            </div>
        </div>
    </div>
</div>

</div>
</body>
</html>
