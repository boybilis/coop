<?php
include 'db.php';
include 'auth.php';
include 'layout.php';
require_admin();
?>
<?php
$deposits = $conn->query("
    SELECT IFNULL(SUM(amount),0) AS total
    FROM savings_transactions
    WHERE type = 'DEPOSIT'
")->fetch_assoc()['total'];

$withdrawals = $conn->query("
    SELECT IFNULL(SUM(amount),0) AS total
    FROM savings_transactions
    WHERE type = 'WITHDRAWAL'
")->fetch_assoc()['total'];

$netSavings = $deposits - $withdrawals;
?>
<!DOCTYPE html>
<html>
<head>
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Member Savings</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
<link rel="stylesheet" href="assets/css/mobile.css">
<link rel="stylesheet" href="assets/css/theme.css?v=20260720-ui">
<link rel="stylesheet" href="https://cdn.datatables.net/1.13.6/css/dataTables.bootstrap5.min.css">
</head>

<body class="bg-light">
<?php render_navbar(); ?>

<div class="container mt-4">

<div class="d-flex justify-content-between align-items-center mb-3">
    <h3 class="mb-0">Member Savings</h3>
    <div>
        <a href="received_savings.php" class="btn btn-outline-success">Received Savings</a>
        <a href="received_withdrawals.php" class="btn btn-outline-danger">Received Withdrawals</a>
        <a href="index.php" class="btn btn-outline-secondary">Dashboard</a>
    </div>
</div>

<div class="row mb-4">
    <div class="col-md-4">
        <div class="card border-success shadow-sm">
            <div class="card-body">
                <h6>Total Deposits</h6>
                <h3 class="text-success">₱<?= number_format($deposits,2) ?></h3>
            </div>
        </div>
    </div>

    <div class="col-md-4">
        <div class="card border-danger shadow-sm">
            <div class="card-body">
                <h6>Total Withdrawals</h6>
                <h3 class="text-danger">₱<?= number_format($withdrawals,2) ?></h3>
            </div>
        </div>
    </div>

    <div class="col-md-4">
        <div class="card border-primary shadow-sm">
            <div class="card-body">
                <h6>Net Savings</h6>
                <h3 class="text-primary">₱<?= number_format($netSavings,2) ?></h3>
            </div>
        </div>
    </div>
</div>

<div class="card mb-4">
<div class="card-body">

<h5>Add Savings Transaction</h5>

<form method="POST" action="ajax/save_savings.php" class="row g-2">

<div class="col-md-3">
<select name="borrower_id" class="form-control" required>
<option value="">Select Member</option>
<?php
$members = $conn->query("
    SELECT borrowers.*, users.username
    FROM borrowers
    LEFT JOIN users ON users.borrower_id = borrowers.id AND users.status = 'Member'
    ORDER BY users.username ASC, borrowers.name ASC
");
while($member = $members->fetch_assoc()):
?>
<option value="<?= $member['id'] ?>"><?= htmlspecialchars(($member['username'] ?: $member['name']) . ' - ' . $member['name']) ?></option>
<?php endwhile; ?>
</select>
</div>

<div class="col-md-2">
<input type="number" step="0.01" name="amount" class="form-control" placeholder="Amount" required>
</div>

<div class="col-md-2">
<select name="type" class="form-control">
<option value="DEPOSIT">Deposit</option>
<option value="WITHDRAWAL">Withdrawal</option>
</select>
</div>

<div class="col-md-2">
<input type="date" name="date" class="form-control" required>
</div>

<div class="col-md-3">
<input type="text" name="remarks" class="form-control" placeholder="Remarks">
</div>

<div class="col-md-12">
<button class="btn btn-success w-100">Save Savings Transaction</button>
</div>

</form>

</div>
</div>

<div class="card">
<div class="card-body">

<div class="table-responsive">
<table class="table table-bordered table-hover" id="savingsTable">
<thead class="table-dark">
<tr>
<th>Member</th>
<th>Amount</th>
<th>Type</th>
<th>Date</th>
<th>Remarks</th>
</tr>
</thead>

<tbody>
<?php
$transactions = $conn->query("
    SELECT savings_transactions.*, borrowers.name, users.username
    FROM savings_transactions
    JOIN borrowers ON borrowers.id = savings_transactions.borrower_id
    LEFT JOIN users ON users.borrower_id = borrowers.id AND users.status = 'Member'
    ORDER BY transaction_date DESC, savings_transactions.id DESC
");

while($row = $transactions->fetch_assoc()):
?>
<tr>
<td><?php render_member_identity($row['username'] ?? '', $row['name']); ?></td>
<td>₱<?= number_format($row['amount'],2) ?></td>
<td>
<span class="badge bg-<?= $row['type'] == 'DEPOSIT' ? 'success' : 'danger' ?>">
<?= $row['type'] ?>
</span>
</td>
<td><?= $row['transaction_date'] ?></td>
<td><?= htmlspecialchars($row['remarks'] ?? '') ?></td>
</tr>
<?php endwhile; ?>
</tbody>
</table>
</div>

</div>
</div>

</div>

<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
<script src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"></script>
<script src="https://cdn.datatables.net/1.13.6/js/dataTables.bootstrap5.min.js"></script>
<script>
$(document).ready(function () {
    $('#savingsTable').DataTable({
        pageLength: 10,
        order: [[3, 'desc']]
    });
});
</script>
<?php render_footer(); ?>
</body>
</html>
