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
<title>Capital Contributions</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
<link rel="stylesheet" href="assets/css/mobile.css">
<link rel="stylesheet" href="assets/css/theme.css">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
<link rel="stylesheet" href="https://cdn.datatables.net/1.13.6/css/dataTables.bootstrap5.min.css">
</head>

<body class="bg-light">
<?php render_navbar(); ?>

<div class="container mt-4">

<div class="d-flex justify-content-between align-items-center mb-3">
<h3 class="mb-0">Capital Contributions</h3>
<a href="index.php" class="btn btn-outline-secondary">Dashboard</a>
</div>

<!-- ================= ADD CAPITAL FORM ================= -->
<div class="card mb-4">
<div class="card-body">

<h5>Add Capital Contribution</h5>

<form method="POST" action="ajax/save_capital.php" class="row g-2" id="capitalForm">

<div class="col-md-4">
<select name="borrower_id" class="form-control" required>
<option value="">Select Member</option>
<?php
$res = $conn->query("SELECT * FROM borrowers ORDER BY name ASC");
while($b = $res->fetch_assoc()):
?>
<option value="<?= $b['id'] ?>"><?= $b['name'] ?></option>
<?php endwhile; ?>
</select>
</div>

<div class="col-md-2">
<input type="number" name="amount" class="form-control" placeholder="Amount" required>
</div>

<div class="col-md-3">
<select name="type" class="form-control">
<option value="INITIAL">Initial</option>
<option value="CUTOFF">Cutoff</option>
</select>
</div>

<div class="col-md-3">
<input type="date" name="date" class="form-control" required>
</div>

<div class="col-md-12">
<button class="btn btn-success w-100">Save Capital</button>
</div>

</form>

</div>
</div>

<div class="col-md-12">
<a href="admin/generate_capital.php" class="btn btn-warning mb-3">
    Generate Capital Cutoffs
</a>
</div>
<!-- ================= SUMMARY ================= -->
<div class="card mb-4">
<div class="card-body">

<?php
$total = $conn->query("SELECT SUM(amount) as t FROM capital_contributions")->fetch_assoc()['t'] ?? 0;
$count = $conn->query("SELECT COUNT(DISTINCT borrower_id) as t FROM capital_contributions")->fetch_assoc()['t'] ?? 1;
$average = $count > 0 ? $total / $count : 0;
?>

<h5>Total Capital Pool: <span id="capitalTotalText">₱<?= number_format($total,2) ?></span></h5>
<h6>Average per Member: <span id="capitalAverageText">₱<?= number_format($average,2) ?></span></h6>

</div>
</div>

<!-- ================= TABLE ================= -->
<div class="card">
<div class="card-body">

<div class="table-responsive">
<table class="table table-bordered table-hover" id="capitalTable">
<thead class="table-dark">
<tr>
<th>Member</th>
<th>Amount</th>
<th>Type</th>
<th>Date</th>
</tr>
</thead>

<tbody>

<?php
$res = $conn->query("
SELECT capital_contributions.*, borrowers.name
FROM capital_contributions
JOIN borrowers ON borrowers.id = capital_contributions.borrower_id
ORDER BY contribution_date DESC
");

while($row = $res->fetch_assoc()):
?>

<tr>
<td><?= $row['name'] ?></td>
<td>₱<?= number_format($row['amount'],2) ?></td>
<td>
<span class="badge bg-<?= $row['type']=='INITIAL'?'primary':'success' ?>">
<?= $row['type'] ?>
</span>
</td>
<td><?= $row['contribution_date'] ?></td>
</tr>

<?php endwhile; ?>

</tbody>
</table>
</div>

</div>
</div>

</div>

<div class="toast-container position-fixed top-0 end-0 p-3">
    <div id="capitalToast" class="toast text-bg-success border-0" role="alert" aria-live="assertive" aria-atomic="true">
        <div class="d-flex">
            <div class="toast-body" id="capitalToastMessage">
                Capital Contribution Recording Successful.
            </div>
            <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast"></button>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>

<script src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"></script>
<script src="https://cdn.datatables.net/1.13.6/js/dataTables.bootstrap5.min.js"></script>

<script>
$(document).ready(function () {
    let capitalTable = $('#capitalTable').DataTable({
        pageLength: 10,
        order: [[3, 'desc']] // sort by date
    });

    function money(amount){
        return '₱' + Number(amount).toLocaleString(undefined, {
            minimumFractionDigits: 2,
            maximumFractionDigits: 2
        });
    }

    function showCapitalToast(message, isError = false){
        let toast = document.getElementById('capitalToast');
        document.getElementById('capitalToastMessage').innerText = message;
        toast.classList.toggle('text-bg-success', !isError);
        toast.classList.toggle('text-bg-danger', isError);
        new bootstrap.Toast(toast).show();
    }

    $('#capitalForm').on('submit', function(event){
        event.preventDefault();

        let form = this;
        let submitButton = $(form).find('button[type="submit"], button:not([type])').last();
        submitButton.prop('disabled', true).text('Saving...');

        $.ajax({
            url: form.action,
            method: 'POST',
            data: $(form).serialize(),
            dataType: 'json'
        }).done(function(response){
            if(response.error){
                showCapitalToast(response.error, true);
                return;
            }

            let badgeClass = response.row.type === 'INITIAL' ? 'primary' : 'success';
            capitalTable.row.add([
                response.row.name,
                money(response.row.amount),
                '<span class="badge bg-' + badgeClass + '">' + response.row.type + '</span>',
                response.row.date
            ]).draw(false);

            $('#capitalTotalText').text(money(response.summary.total));
            $('#capitalAverageText').text(money(response.summary.average));
            form.reset();
            showCapitalToast(response.message || 'Capital Contribution Recording Successful.');
        }).fail(function(){
            showCapitalToast('Unable to save capital contribution.', true);
        }).always(function(){
            submitButton.prop('disabled', false).text('Save Capital');
        });
    });
});
</script>
<?php render_footer(); ?>
</body>
</html>
