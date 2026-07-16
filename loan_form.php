<?php
include 'db.php';
include 'auth.php';
require_admin();
?>
<!DOCTYPE html>
<html>
<head>
<title>Add Member Loan</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>

<body class="bg-light">

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
    $res = $conn->query("SELECT * FROM borrowers ORDER BY name ASC");
    while($b = $res->fetch_assoc()):
    ?>
        <option value="<?= $b['id'] ?>">
            <?= $b['name'] ?>
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
        alert("Loan Saved!");
        window.location = "index.php";
    });
}
</script>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>

</body>
</html>
