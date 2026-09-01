<?php
include 'db.php';
include 'auth.php';
include 'layout.php';
require_admin();

$cutoffDates = $conn->query("
    SELECT cutoff_date
    FROM (
        SELECT DISTINCT payment_submissions.cutoff_date
        FROM payment_submissions

        UNION

        SELECT DISTINCT capital_contributions.contribution_date AS cutoff_date
        FROM capital_contributions
        WHERE capital_contributions.period_label = 'Admin Entry - Verified'
    ) AS available_cutoffs
    ORDER BY cutoff_date DESC
");

$adminPaymentMembers = $conn->query("
    SELECT borrowers.id, borrowers.name, users.username
    FROM borrowers
    LEFT JOIN users ON users.borrower_id = borrowers.id AND users.status = 'Member'
    WHERE borrowers.status = 'Active'
    ORDER BY users.username ASC, borrowers.name ASC
");

$selectedCutoff = $_GET['cutoff_date'] ?? '';
$submissions = null;

if ($selectedCutoff !== '') {
    $stmt = $conn->prepare("
        SELECT received_payments.*
        FROM (
            SELECT
                payment_submissions.id,
                payment_submissions.borrower_id,
                payment_submissions.payment_date,
                payment_submissions.cutoff_date,
                payment_submissions.capital_contribution,
                payment_submissions.loan_payment,
                payment_submissions.selected_loan_id,
                payment_submissions.reference_number,
                payment_submissions.proof_image,
                payment_submissions.status,
                borrowers.name,
                users.username,
                selected_loan.is_guarantor AS selected_loan_is_guarantor,
                selected_loan.guest_borrower_name AS selected_loan_guest_name,
                'member_submission' AS source_type
            FROM payment_submissions
            JOIN borrowers ON borrowers.id = payment_submissions.borrower_id
            LEFT JOIN users ON users.borrower_id = borrowers.id AND users.status = 'Member'
            LEFT JOIN loans selected_loan ON selected_loan.id = payment_submissions.selected_loan_id
            WHERE payment_submissions.cutoff_date = ?

            UNION ALL

            SELECT
                capital_contributions.id,
                capital_contributions.borrower_id,
                capital_contributions.contribution_date AS payment_date,
                capital_contributions.contribution_date AS cutoff_date,
                capital_contributions.amount AS capital_contribution,
                0.00 AS loan_payment,
                NULL AS selected_loan_id,
                COALESCE(capital_contributions.reference_number, '') AS reference_number,
                COALESCE(capital_contributions.proof_image, '') AS proof_image,
                'Approved' AS status,
                borrowers.name,
                users.username,
                NULL AS selected_loan_is_guarantor,
                NULL AS selected_loan_guest_name,
                'admin_manual' AS source_type
            FROM capital_contributions
            JOIN borrowers ON borrowers.id = capital_contributions.borrower_id
            LEFT JOIN users ON users.borrower_id = borrowers.id AND users.status = 'Member'
            WHERE capital_contributions.contribution_date = ?
            AND capital_contributions.period_label = 'Admin Entry - Verified'
        ) AS received_payments
        ORDER BY received_payments.username ASC, received_payments.name ASC, received_payments.id DESC
    ");
    $stmt->bind_param("ss", $selectedCutoff, $selectedCutoff);
    $stmt->execute();
    $submissions = $stmt->get_result();
}
?>
<!DOCTYPE html>
<html>
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Received Payments</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
<link rel="stylesheet" href="assets/css/mobile.css">
<link rel="stylesheet" href="assets/css/theme.css?v=20260722-placeholders">
</head>

<body class="bg-light">
<?php render_navbar(); ?>
<div class="container mt-4">

<div class="d-flex justify-content-between align-items-center mb-3">
    <h3 class="mb-0">Received Payments</h3>
    <div>
        <button type="button" class="btn btn-success" data-bs-toggle="modal" data-bs-target="#adminPaymentModal">
            Add Verified Payment
        </button>
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
        <div class="d-flex flex-wrap justify-content-between align-items-center gap-2">
            <h5 class="mb-0">
                Payments <?= $selectedCutoff ? 'for ' . htmlspecialchars($selectedCutoff) : '' ?>
            </h5>
            <?php if($selectedCutoff): ?>
                <a href="received_payments_pdf_preview.php?cutoff_date=<?= urlencode($selectedCutoff) ?>" target="_blank" rel="noopener" class="btn btn-outline-danger btn-sm">
                    Preview PDF Report
                </a>
            <?php endif; ?>
        </div>
    </div>

    <div class="card-body">
        <div class="table-responsive">
            <table class="table table-bordered table-hover align-middle">
                <thead class="table-dark">
                    <tr>
                        <th>Borrower</th>
                        <th>Cap Con</th>
                        <th>Loan Target</th>
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
                            <td colspan="9" class="text-center text-muted">Select a cutoff date to view payments.</td>
                        </tr>
                    <?php elseif($submissions->num_rows === 0): ?>
                        <tr>
                            <td colspan="9" class="text-center text-muted">No payments submitted for this cutoff.</td>
                        </tr>
                    <?php else: ?>
                        <?php while($row = $submissions->fetch_assoc()): ?>
                        <?php $totalAmount = $row['capital_contribution'] + $row['loan_payment']; ?>
                        <tr>
                            <td><?php render_member_identity($row['username'] ?? '', $row['name']); ?></td>
                            <td>&#8369;<?= number_format($row['capital_contribution'],2) ?></td>
                            <td>
                                <?php if((float)$row['loan_payment'] <= 0): ?>
                                    <span class="text-muted">No loan</span>
                                <?php elseif(!empty($row['selected_loan_id'])): ?>
                                    Loan #<?= (int)$row['selected_loan_id'] ?>
                                    <?php if((int)($row['selected_loan_is_guarantor'] ?? 0) === 1): ?>
                                        <small class="d-block text-muted">
                                            Co-maker<?= !empty($row['selected_loan_guest_name']) ? ' for ' . htmlspecialchars($row['selected_loan_guest_name']) : '' ?>
                                        </small>
                                    <?php else: ?>
                                        <small class="d-block text-muted">Personal loan</small>
                                    <?php endif; ?>
                                <?php else: ?>
                                    <strong>All loans</strong>
                                <?php endif; ?>
                            </td>
                            <td>&#8369;<?= number_format($row['loan_payment'],2) ?></td>
                            <td><strong>&#8369;<?= number_format($totalAmount,2) ?></strong></td>
                            <td>
                                <?= ($row['reference_number'] ?? '') !== '' ? htmlspecialchars($row['reference_number']) : '&mdash;' ?>
                                <small class="d-block text-muted">
                                    Payment Date: <?= date('M d, Y', strtotime($row['payment_date'])) ?>
                                </small>
                                <?php if(($row['source_type'] ?? '') === 'admin_manual'): ?>
                                    <small class="d-block text-success">Manual admin entry</small>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if(!empty($row['proof_image'])): ?>
                                    <a href="<?= htmlspecialchars($row['proof_image']) ?>" data-image-preview class="btn btn-outline-primary btn-sm">
                                        View Image
                                    </a>
                                <?php else: ?>
                                    <span class="text-muted">&mdash;</span>
                                <?php endif; ?>
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

<div class="modal fade" id="adminPaymentModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <form method="POST" action="ajax/save_admin_payment.php" enctype="multipart/form-data" id="adminPaymentForm">
                <div class="modal-header">
                    <h5 class="modal-title">Add Verified Payment</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="alert alert-info">
                        Payments entered here are verified immediately. Select one loan or all loans due for the chosen cut-off.
                    </div>
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label">Member</label>
                            <select name="borrower_id" id="adminPaymentBorrower" class="form-control" required>
                                <option value="">Select member</option>
                                <?php while($memberOption = $adminPaymentMembers->fetch_assoc()): ?>
                                    <option value="<?= (int)$memberOption['id'] ?>">
                                        <?= htmlspecialchars(($memberOption['username'] ?: $memberOption['name']) . ' - ' . $memberOption['name']) ?>
                                    </option>
                                <?php endwhile; ?>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Payment Cut-Off Date</label>
                            <select name="cutoff_date" id="adminPaymentCutoff" class="form-control" required disabled>
                                <option value="">Select member first</option>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Capital Contribution</label>
                            <input type="number" step="0.01" min="0" name="capital_contribution" id="adminPaymentCapital" class="form-control" value="0">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Loan to Pay</label>
                            <select name="selected_loan_id" id="adminPaymentLoanTarget" class="form-control" disabled>
                                <option value="">No loan payment</option>
                            </select>
                            <small class="text-muted">Loan amount: <strong id="adminPaymentLoanAmount">&#8369;0.00</strong></small>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Reference Number <span class="text-muted">(Optional)</span></label>
                            <input type="text" name="reference_number" class="form-control" maxlength="100">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Proof Image <span class="text-muted">(Optional)</span></label>
                            <input type="file" name="proof_image" class="form-control" accept="image/jpeg,image/png,image/webp">
                            <small class="text-muted">JPG, PNG, or WEBP; maximum 5 MB.</small>
                        </div>
                        <div class="col-12">
                            <div class="alert alert-success mb-0">
                                <strong>Total Verified Payment:</strong>
                                <span id="adminPaymentTotal">&#8369;0.00</span>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-success">Save and Verify</button>
                </div>
            </form>
        </div>
    </div>
</div>

</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
const adminPaymentPreferredCutoff = <?= json_encode($selectedCutoff) ?>;
let adminPaymentLoansByCutoff = {};

function adminPaymentMoney(amount){
    return '\u20B1' + Number(amount || 0).toLocaleString(undefined, {
        minimumFractionDigits: 2,
        maximumFractionDigits: 2
    });
}

function updateAdminPaymentTotal(){
    const capital = Number(document.getElementById('adminPaymentCapital').value || 0);
    const selected = document.getElementById('adminPaymentLoanTarget').selectedOptions[0];
    const loan = Number(selected?.dataset.amount || 0);
    document.getElementById('adminPaymentLoanAmount').textContent = adminPaymentMoney(loan);
    document.getElementById('adminPaymentTotal').textContent = adminPaymentMoney(capital + loan);
}

function populateAdminPaymentLoans(){
    const cutoff = document.getElementById('adminPaymentCutoff').value;
    const select = document.getElementById('adminPaymentLoanTarget');
    const loans = adminPaymentLoansByCutoff[cutoff] || [];
    select.innerHTML = '<option value="">No loan payment</option>';

    loans.forEach(loan => {
        const option = document.createElement('option');
        option.value = String(loan.id);
        option.dataset.amount = String(loan.amount);
        option.textContent = loan.label + ' — ' + adminPaymentMoney(loan.amount);
        select.appendChild(option);
    });

    if(loans.length > 0){
        const total = loans.reduce((sum, loan) => sum + Number(loan.amount), 0);
        const option = document.createElement('option');
        option.value = 'all';
        option.dataset.amount = String(total);
        option.textContent = 'All loans total — ' + adminPaymentMoney(total);
        select.appendChild(option);
    }

    select.disabled = false;
    updateAdminPaymentTotal();
}

document.getElementById('adminPaymentBorrower').addEventListener('change', function(){
    const borrowerId = this.value;
    const cutoffSelect = document.getElementById('adminPaymentCutoff');
    const loanSelect = document.getElementById('adminPaymentLoanTarget');
    cutoffSelect.disabled = true;
    loanSelect.disabled = true;
    cutoffSelect.innerHTML = '<option value="">Loading cut-offs...</option>';
    loanSelect.innerHTML = '<option value="">No loan payment</option>';
    adminPaymentLoansByCutoff = {};
    updateAdminPaymentTotal();

    if(!borrowerId){
        cutoffSelect.innerHTML = '<option value="">Select member first</option>';
        return;
    }

    fetch('ajax/admin_member_payment_options.php?borrower_id=' + encodeURIComponent(borrowerId), {cache: 'no-store'})
        .then(response => response.json())
        .then(data => {
            if(data.error){
                window.appShowToast(data.error, 'error');
                cutoffSelect.innerHTML = '<option value="">Unable to load cut-offs</option>';
                return;
            }

            adminPaymentLoansByCutoff = data.loans_by_cutoff || {};
            cutoffSelect.innerHTML = '<option value="">Select payment cut-off date</option>';
            (data.cutoffs || []).forEach(cutoff => {
                const option = document.createElement('option');
                option.value = cutoff;
                option.textContent = new Date(cutoff + 'T00:00:00').toLocaleDateString(undefined, {
                    year: 'numeric', month: 'short', day: '2-digit'
                });
                cutoffSelect.appendChild(option);
            });
            cutoffSelect.disabled = false;

            if(adminPaymentPreferredCutoff && (data.cutoffs || []).includes(adminPaymentPreferredCutoff)){
                cutoffSelect.value = adminPaymentPreferredCutoff;
                populateAdminPaymentLoans();
            }
        })
        .catch(() => {
            cutoffSelect.innerHTML = '<option value="">Unable to load cut-offs</option>';
            window.appShowToast('Unable to load member payment options.', 'error');
        });
});

document.getElementById('adminPaymentCutoff').addEventListener('change', populateAdminPaymentLoans);
document.getElementById('adminPaymentLoanTarget').addEventListener('change', updateAdminPaymentTotal);
document.getElementById('adminPaymentCapital').addEventListener('input', updateAdminPaymentTotal);
</script>
<?php render_footer(); ?>
</body>
</html>

