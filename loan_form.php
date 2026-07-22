<?php
include 'db.php';
include 'auth.php';
include 'layout.php';
require_admin();
?>
<!DOCTYPE html>
<html>
<head>
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Add Member Loan</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
<link rel="stylesheet" href="assets/css/mobile.css">
<link rel="stylesheet" href="assets/css/theme.css?v=20260722-placeholders">
</head>

<body class="bg-light">
<?php render_navbar(); ?>

<div class="container mt-5">

<div class="card shadow">
<div class="card-body">

<h4>Add Member Loan</h4>

<form id="loanForm">

<!-- BORROWER SELECT -->
<div class="mb-3">
<label>Member</label>
<select name="borrower_id" class="form-control" required>
    <option value="">-- Select Member --</option>
    <?php
    $res = $conn->query("
        SELECT borrowers.*, users.username
        FROM borrowers
        LEFT JOIN users ON users.borrower_id = borrowers.id AND users.status = 'Member'
        ORDER BY users.username ASC, borrowers.name ASC
    ");
    while($b = $res->fetch_assoc()):
    ?>
        <option value="<?= $b['id'] ?>">
            <?= htmlspecialchars(($b['username'] ?: $b['name']) . ' - ' . $b['name']) ?>
        </option>
    <?php endwhile; ?>
</select>
</div>

<!-- AMOUNT -->
<div class="mb-3">
<label>Amount</label>
<input type="number" name="amount" class="form-control" required>
</div>

<!-- MONTHS -->
<div class="mb-3">
<label>Months (can be decimal)</label>
<input type="number" step="0.1" name="months" class="form-control" required>
</div>

<!-- START DATE -->
<div class="mb-3">
<label>Start Date</label>
<input type="date" name="start_date" class="form-control" required>
</div>

<button class="btn btn-success w-100">Save Loan</button>

</form>

</div>
</div>

</div>

<script>
document.getElementById("loanForm").onsubmit = function(e){
    e.preventDefault();

    let formData = new FormData(this);

    fetch('ajax/save_loan.php', {
        method: 'POST',
        body: formData
    })
    .then(res => res.text())
    .then(() => {
        sessionStorage.setItem('appToastMessage', 'Loan saved successfully.');
        sessionStorage.setItem('appToastType', 'success');
        window.location = "index.php";
    });
}
</script>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>

<?php render_footer(); ?>
</body>
</html>

