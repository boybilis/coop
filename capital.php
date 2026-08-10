<?php
include 'db.php';
include 'auth.php';
include 'layout.php';
require_admin();
?>

<!DOCTYPE html>
<html>
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Capital Contributions</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
<link rel="stylesheet" href="assets/css/mobile.css">
<link rel="stylesheet" href="assets/css/theme.css?v=20260722-placeholders">
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

<form method="POST" action="ajax/save_capital.php" enctype="multipart/form-data" class="row g-2" id="capitalForm">

<div class="col-md-4">
<select name="borrower_id" class="form-control" required>
<option value="">Select Member</option>
<?php
$res = $conn->query("
    SELECT borrowers.*, users.username
    FROM borrowers
    LEFT JOIN users ON users.borrower_id = borrowers.id AND users.status = 'Member'
    ORDER BY users.username ASC, borrowers.name ASC
");
while($b = $res->fetch_assoc()):
?>
<option value="<?= $b['id'] ?>"><?= htmlspecialchars(($b['username'] ?: $b['name']) . ' - ' . $b['name']) ?></option>
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

<div class="col-md-6">
<input type="text" name="reference_number" class="form-control" maxlength="100" placeholder="Reference Number (Optional)">
</div>

<div class="col-md-6">
<input type="file" name="proof_image" class="form-control" accept="image/jpeg,image/png,image/webp">
<div class="form-text">Optional. JPG, PNG, or WEBP; maximum 5 MB.</div>
</div>

<div class="col-md-12">
<button class="btn btn-success w-100">Save Capital</button>
</div>

</form>

</div>
</div>

<!-- ================= SUMMARY ================= -->
<div class="card mb-4">
<div class="card-body">

<?php
$total = $conn->query("SELECT SUM(amount) as t FROM capital_contributions")->fetch_assoc()['t'] ?? 0;
$count = $conn->query("SELECT COUNT(DISTINCT borrower_id) as t FROM capital_contributions")->fetch_assoc()['t'] ?? 1;
$average = $count > 0 ? $total / $count : 0;
?>

<h5>Total Capital Pool: <span id="capitalTotalText">&#8369;<?= number_format($total,2) ?></span></h5>
<h6>Average per Member: <span id="capitalAverageText">&#8369;<?= number_format($average,2) ?></span></h6>

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
<th>Reference Number</th>
<th>Proof</th>
<th>Date</th>
</tr>
</thead>

<tbody>

<?php
$res = $conn->query("
SELECT capital_contributions.*, borrowers.name, users.username,
    COALESCE(
        NULLIF(capital_contributions.reference_number, ''),
        (
            SELECT payment_submissions.reference_number
            FROM payment_submissions
            WHERE payment_submissions.borrower_id = capital_contributions.borrower_id
            AND payment_submissions.cutoff_date = capital_contributions.contribution_date
            AND payment_submissions.capital_contribution = capital_contributions.amount
            AND payment_submissions.status = 'Approved'
            AND capital_contributions.period_label = CONCAT('GCash Ref: ', payment_submissions.reference_number)
            ORDER BY payment_submissions.id DESC
            LIMIT 1
        )
    ) AS display_reference_number,
    COALESCE(
        NULLIF(capital_contributions.proof_image, ''),
        (
            SELECT payment_submissions.proof_image
            FROM payment_submissions
            WHERE payment_submissions.borrower_id = capital_contributions.borrower_id
            AND payment_submissions.cutoff_date = capital_contributions.contribution_date
            AND payment_submissions.capital_contribution = capital_contributions.amount
            AND payment_submissions.status = 'Approved'
            AND capital_contributions.period_label = CONCAT('GCash Ref: ', payment_submissions.reference_number)
            ORDER BY payment_submissions.id DESC
            LIMIT 1
        )
    ) AS display_proof_image
FROM capital_contributions
JOIN borrowers ON borrowers.id = capital_contributions.borrower_id
LEFT JOIN users ON users.borrower_id = borrowers.id AND users.status = 'Member'
ORDER BY contribution_date DESC
");

while($row = $res->fetch_assoc()):
?>

<tr>
<td><?php render_member_identity($row['username'] ?? '', $row['name']); ?></td>
<td>&#8369;<?= number_format($row['amount'],2) ?></td>
<td>
<span class="badge bg-<?= $row['type']=='INITIAL'?'primary':'success' ?>">
<?= $row['type'] ?>
</span>
</td>
<td><?= ($row['display_reference_number'] ?? '') !== '' ? htmlspecialchars($row['display_reference_number']) : '<span class="text-muted">—</span>' ?></td>
<td>
<?php if(!empty($row['display_proof_image'])): ?>
<a href="<?= htmlspecialchars($row['display_proof_image']) ?>" data-image-preview class="btn btn-outline-primary btn-sm">View</a>
<?php else: ?>
<span class="text-muted">—</span>
<?php endif; ?>
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
        order: [[5, 'desc']] // sort by date
    });

    function money(amount){
        return '\u20B1' + Number(amount).toLocaleString(undefined, {
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
            data: new FormData(form),
            processData: false,
            contentType: false,
            dataType: 'json'
        }).done(function(response){
            if(response.error){
                showCapitalToast(response.error, true);
                return;
            }

            let badgeClass = response.row.type === 'INITIAL' ? 'primary' : 'success';
            let memberDisplayName = response.row.username || response.row.name;
            capitalTable.row.add([
                '<strong>' + escapeHtml(memberDisplayName) + '</strong><small class="d-block text-dark-emphasis">' + escapeHtml(response.row.name) + '</small>',
                money(response.row.amount),
                '<span class="badge bg-' + badgeClass + '">' + response.row.type + '</span>',
                response.row.reference_number
                    ? escapeHtml(response.row.reference_number)
                    : '<span class="text-muted">—</span>',
                response.row.proof_image
                    ? '<a href="' + escapeHtml(response.row.proof_image) + '" data-image-preview class="btn btn-outline-primary btn-sm">View</a>'
                    : '<span class="text-muted">—</span>',
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

function escapeHtml(value){
    return String(value).replace(/[&<>"']/g, function(character){
        return {
            '&': '&amp;',
            '<': '&lt;',
            '>': '&gt;',
            '"': '&quot;',
            "'": '&#039;'
        }[character];
    });
}
</script>
<?php render_footer(); ?>
</body>
</html>

