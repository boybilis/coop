<?php
include 'db.php';
include 'auth.php';
include 'layout.php';
require_admin();

if (empty($_SESSION['admin_loan_request_edit_token'])) {
    $_SESSION['admin_loan_request_edit_token'] = bin2hex(random_bytes(32));
}

$loanableBreakdown = cooperative_loanable_amount_breakdown($conn);
$availableLoanAmount = (float)($loanableBreakdown['approval_available_amount'] ?? $loanableBreakdown['available_amount']);
$cutoffColumnCheck = $conn->query("SHOW COLUMNS FROM loan_requests LIKE 'first_payment_cutoff'");
$hasFirstPaymentCutoff = $cutoffColumnCheck && $cutoffColumnCheck->num_rows > 0;
$adminLoanCutoffs = cooperative_admin_loan_cutoff_options($conn, date('Y-m-d'));
$currentMonthStart = date('Y-m-01');
$memberOptions = $conn->query("
    SELECT borrowers.id, borrowers.name, users.username
    FROM borrowers
    JOIN users ON users.borrower_id = borrowers.id AND users.status = 'Member'
    WHERE borrowers.status = 'Active'
    ORDER BY users.username, borrowers.name
");

$requests = $conn->query("
    SELECT loan_requests.*, borrowers.name, borrowers.gcash_name, borrowers.gcash_number, users.username
    FROM loan_requests
    JOIN borrowers ON borrowers.id = loan_requests.borrower_id
    LEFT JOIN users ON users.borrower_id = borrowers.id AND users.status = 'Member'
    ORDER BY
        CASE WHEN loan_requests.status = 'Pending' THEN 0 ELSE 1 END ASC,
        CASE WHEN loan_requests.status = 'Pending' THEN loan_requests.created_at END ASC,
        CASE WHEN loan_requests.status <> 'Pending' THEN COALESCE(loan_requests.processed_at, loan_requests.created_at) END DESC,
        loan_requests.id DESC
");
?>
<!DOCTYPE html>
<html>
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Loan Requests</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
<link rel="stylesheet" href="assets/css/mobile.css">
<link rel="stylesheet" href="assets/css/theme.css?v=20260722-placeholders">
<style>
    .loan-requests-table {
        min-width: 1120px;
    }

    .loan-approval-cell {
        min-width: 220px;
    }

    .loan-action-group {
        display: flex;
        gap: .5rem;
        align-items: center;
        min-width: 200px;
    }
</style>
</head>

<body class="bg-light">
<?php render_navbar(); ?>
<div class="container mt-4">

<div class="d-flex justify-content-between align-items-center mb-3">
    <h3 class="mb-0">Loan Requests</h3>
    <div>
        <?php if ($hasFirstPaymentCutoff): ?>
            <button type="button" class="btn btn-success" data-bs-toggle="modal" data-bs-target="#addLoanRequestModal">Add Loan Request</button>
        <?php endif; ?>
        <a href="index.php" class="btn btn-outline-secondary">Dashboard</a>
        <a href="loans.php" class="btn btn-outline-primary">Loan Management</a>
    </div>
</div>

<?php if(isset($_GET['approved'])): ?>
    <script>window.appToasts = window.appToasts || []; window.appToasts.push({type:'success', message:'Loan request approved and saved as a loan.'});</script>
<?php endif; ?>

<?php if(isset($_GET['rejected'])): ?>
    <script>window.appToasts = window.appToasts || []; window.appToasts.push({type:'warning', message:'Loan request rejected.'});</script>
<?php endif; ?>

<?php if(isset($_GET['updated'])): ?>
    <script>window.appToasts = window.appToasts || []; window.appToasts.push({type:'success', message:'Loan request updated.'});</script>
<?php endif; ?>

<?php if(isset($_GET['added'])): ?>
    <script>window.appToasts = window.appToasts || []; window.appToasts.push({type:'success', message:'Loan request added to the pending queue.'});</script>
<?php endif; ?>

<?php if(isset($_GET['error'])): ?>
    <script>window.appToasts = window.appToasts || []; window.appToasts.push({type:'error', message:<?= json_encode($_GET['error']) ?>});</script>
<?php endif; ?>

<?php if (!$hasFirstPaymentCutoff): ?>
    <div class="alert alert-warning">The first-payment-cutoff database migration must be applied before new admin loan requests can be added.</div>
<?php endif; ?>

<div class="card shadow">
    <div class="card-header">
        <h5 class="mb-0">First Come, First Served Queue</h5>
    </div>

    <div class="card-body">
        <div class="table-responsive">
            <table class="table table-bordered table-hover align-middle loan-requests-table">
                <thead class="table-dark">
                    <tr>
                        <th>Queue</th>
                        <th>Member</th>
                        <th>Borrower For</th>
                        <th>Requested Amount</th>
                        <th>Requested Months</th>
                        <th>First Payment Cutoff</th>
                        <th>Date Requested</th>
                        <th>Status</th>
                        <th class="loan-approval-cell">Approval</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if($requests->num_rows === 0): ?>
                        <tr>
                            <td colspan="9" class="text-center text-muted">No loan requests yet.</td>
                        </tr>
                    <?php endif; ?>

                    <?php $queue = 1; ?>
                    <?php while($row = $requests->fetch_assoc()): ?>
                    <tr>
                        <td><?= $row['status'] === 'Pending' ? $queue++ : '&mdash;' ?></td>
                        <td><?php render_member_identity($row['username'] ?? '', $row['name']); ?></td>
                        <td>
                            <?php if((int)($row['is_guarantor'] ?? 0) === 1): ?>
                                <span class="badge bg-info text-dark">Guest Borrower</span><br>
                                <small><?= htmlspecialchars($row['guest_borrower_name'] ?? '') ?></small><br>
                                <small class="text-muted">
                                    GCash: <?= htmlspecialchars($row['guest_gcash_name'] ?? '') ?> /
                                    <?= htmlspecialchars($row['guest_gcash_number'] ?? '') ?>
                                </small>
                            <?php else: ?>
                                <span class="badge bg-secondary">Member</span><br>
                                <small class="text-muted">
                                    GCash: <?= htmlspecialchars($row['gcash_name'] ?? '') ?> /
                                    <?= htmlspecialchars($row['gcash_number'] ?? '') ?>
                                </small>
                            <?php endif; ?>
                        </td>
                        <td>&#8369;<?= number_format($row['requested_amount'],2) ?></td>
                        <td><?= $row['requested_months'] ?></td>
                        <td><?= !empty($row['first_payment_cutoff']) ? htmlspecialchars($row['first_payment_cutoff']) : '<span class="text-muted">Automatic</span>' ?></td>
                        <td><?= $row['created_at'] ?></td>
                        <td>
                            <span class="badge bg-<?= $row['status'] === 'Approved' ? 'success' : ($row['status'] === 'Rejected' ? 'danger' : 'warning text-dark') ?>">
                                <?= $row['status'] ?>
                            </span>
                        </td>
                        <td class="loan-approval-cell">
                            <?php if($row['status'] === 'Pending'): ?>
                                <div class="loan-action-group">
                                    <button type="button" class="btn btn-warning btn-sm edit-loan-request-button"
                                        data-request-id="<?= (int)$row['id'] ?>"
                                        data-amount="<?= htmlspecialchars((string)$row['requested_amount'], ENT_QUOTES, 'UTF-8') ?>"
                                        data-months="<?= htmlspecialchars((string)$row['requested_months'], ENT_QUOTES, 'UTF-8') ?>"
                                        data-first-payment-cutoff="<?= htmlspecialchars((string)($row['first_payment_cutoff'] ?? ''), ENT_QUOTES, 'UTF-8') ?>"
                                        data-is-guarantor="<?= (int)($row['is_guarantor'] ?? 0) ?>"
                                        data-guest-borrower-name="<?= htmlspecialchars((string)($row['guest_borrower_name'] ?? ''), ENT_QUOTES, 'UTF-8') ?>"
                                        data-guest-gcash-name="<?= htmlspecialchars((string)($row['guest_gcash_name'] ?? ''), ENT_QUOTES, 'UTF-8') ?>"
                                        data-guest-gcash-number="<?= htmlspecialchars((string)($row['guest_gcash_number'] ?? ''), ENT_QUOTES, 'UTF-8') ?>">Edit</button>
                                    <button type="button" class="btn btn-success btn-sm"
                                        onclick="openApproveLoanRequestModal(<?= (int)$row['id'] ?>, <?= (float)$row['requested_amount'] ?>, <?= (float)$row['requested_months'] ?>)">
                                        Approve
                                    </button>
                                    <form method="POST" action="ajax/reject_loan_request.php" class="m-0" data-confirm="Reject this loan request?" data-confirm-ok="Reject" data-confirm-class="btn-danger">
                                        <input type="hidden" name="request_id" value="<?= (int)$row['id'] ?>">
                                        <button class="btn btn-outline-danger btn-sm">
                                            Reject
                                        </button>
                                    </form>
                                </div>
                            <?php else: ?>
                                <?php if($row['approved_loan_id']): ?>
                                    <a href="loan_view.php?id=<?= $row['approved_loan_id'] ?>" class="btn btn-outline-info btn-sm">
                                        View Loan
                                    </a>
                                <?php else: ?>
                                    <span class="text-muted">Processed</span>
                                <?php endif; ?>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endwhile; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

</div>

<div class="modal fade" id="addLoanRequestModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog">
    <div class="modal-content">
      <form method="POST" action="ajax/admin_add_loan_request.php">
        <div class="modal-header">
            <h5 class="modal-title">Add Loan Request</h5>
            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
        </div>
        <div class="modal-body">
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['admin_loan_request_edit_token'], ENT_QUOTES, 'UTF-8') ?>">
            <div class="mb-3">
                <label for="addRequestMember" class="form-label">Member Requesting the Loan</label>
                <select name="borrower_id" id="addRequestMember" class="form-select" required>
                    <option value="">Select member</option>
                    <?php while ($member = $memberOptions->fetch_assoc()): ?>
                        <option value="<?= (int)$member['id'] ?>"><?= htmlspecialchars(($member['username'] ?: $member['name']) . ' - ' . $member['name']) ?></option>
                    <?php endwhile; ?>
                </select>
            </div>
            <div class="mb-3">
                <label for="addRequestAmount" class="form-label">Requested Amount</label>
                <input type="number" step="0.01" min="0.01" name="amount" id="addRequestAmount" class="form-control" required>
            </div>
            <div class="mb-3">
                <label for="addRequestMonths" class="form-label">Requested Months</label>
                <input type="number" step="0.1" min="0.1" max="6" name="months" id="addRequestMonths" class="form-control" required>
            </div>
            <div class="mb-3">
                <label for="addRequestCutoff" class="form-label">First Payment Cutoff</label>
                <select name="first_payment_cutoff" id="addRequestCutoff" class="form-select" required>
                    <option value="">Select first payment cutoff</option>
                    <?php foreach ($adminLoanCutoffs as $cutoff): ?>
                        <option value="<?= htmlspecialchars($cutoff) ?>"><?= htmlspecialchars(date('M d, Y', strtotime($cutoff))) ?><?= $cutoff < $currentMonthStart ? ' (previous month correction)' : '' ?></option>
                    <?php endforeach; ?>
                </select>
                <small class="text-muted">For corrections, choose a cutoff from the previous month. The first installment uses this date; later installments follow the payment schedule.</small>
            </div>
            <div class="form-check mb-3">
                <input type="checkbox" class="form-check-input" name="is_guarantor" value="1" id="addRequestGuarantor">
                <label class="form-check-label" for="addRequestGuarantor">Member acts as co-maker for a guest borrower</label>
            </div>
            <div id="addRequestGuestFields" class="d-none">
                <div class="mb-3">
                    <label for="addRequestGuestName" class="form-label">Guest Borrower Name</label>
                    <input type="text" name="guest_borrower_name" id="addRequestGuestName" class="form-control" maxlength="150">
                </div>
                <div class="mb-3">
                    <label for="addRequestGcashName" class="form-label">Guest GCash Name</label>
                    <input type="text" name="guest_gcash_name" id="addRequestGcashName" class="form-control" maxlength="150">
                </div>
                <div class="mb-3">
                    <label for="addRequestGcashNumber" class="form-label">Guest GCash Number</label>
                    <input type="text" name="guest_gcash_number" id="addRequestGcashNumber" class="form-control" maxlength="50">
                </div>
            </div>
        </div>
        <div class="modal-footer">
            <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
            <button type="submit" class="btn btn-success">Add Pending Request</button>
        </div>
      </form>
    </div>
  </div>
</div>

<div class="modal fade" id="editLoanRequestModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog">
    <div class="modal-content">
      <form method="POST" action="ajax/admin_update_loan_request.php">
        <div class="modal-header">
            <h5 class="modal-title">Edit Pending Loan Request</h5>
            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
        </div>
        <div class="modal-body">
            <input type="hidden" name="request_id" id="editRequestId">
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['admin_loan_request_edit_token'], ENT_QUOTES, 'UTF-8') ?>">
            <div class="mb-3">
                <label for="editRequestAmount" class="form-label">Requested Amount</label>
                <input type="number" step="0.01" min="0.01" name="amount" id="editRequestAmount" class="form-control" required>
            </div>
            <div class="mb-3">
                <label for="editRequestMonths" class="form-label">Requested Months</label>
                <input type="number" step="0.1" min="0.1" max="6" name="months" id="editRequestMonths" class="form-control" required>
            </div>
            <div class="mb-3">
                <label for="editRequestCutoff" class="form-label">First Payment Cutoff</label>
                <select name="first_payment_cutoff" id="editRequestCutoff" class="form-select">
                    <option value="">Automatic next cutoff (member request)</option>
                    <?php foreach ($adminLoanCutoffs as $cutoff): ?>
                        <option value="<?= htmlspecialchars($cutoff) ?>"><?= htmlspecialchars(date('M d, Y', strtotime($cutoff))) ?><?= $cutoff < $currentMonthStart ? ' (previous month correction)' : '' ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-check mb-3">
                <input type="checkbox" class="form-check-input" name="is_guarantor" value="1" id="editRequestGuarantor">
                <label class="form-check-label" for="editRequestGuarantor">Member acts as co-maker for a guest borrower</label>
            </div>
            <div id="editRequestGuestFields" class="d-none">
                <div class="mb-3">
                    <label for="editRequestGuestName" class="form-label">Guest Borrower Name</label>
                    <input type="text" name="guest_borrower_name" id="editRequestGuestName" class="form-control" maxlength="150">
                </div>
                <div class="mb-3">
                    <label for="editRequestGcashName" class="form-label">Guest GCash Name</label>
                    <input type="text" name="guest_gcash_name" id="editRequestGcashName" class="form-control" maxlength="150">
                </div>
                <div class="mb-3">
                    <label for="editRequestGcashNumber" class="form-label">Guest GCash Number</label>
                    <input type="text" name="guest_gcash_number" id="editRequestGcashNumber" class="form-control" maxlength="50">
                </div>
            </div>
        </div>
        <div class="modal-footer">
            <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
            <button type="submit" class="btn btn-primary">Save Changes</button>
        </div>
      </form>
    </div>
  </div>
</div>

<div class="modal fade" id="approveLoanRequestModal" tabindex="-1">
  <div class="modal-dialog">
    <div class="modal-content">
      <form method="POST" action="ajax/approve_loan_request.php" enctype="multipart/form-data" id="approveLoanRequestForm" data-confirm="Approve and mark this loan as disbursed?" data-confirm-ok="Approve Loan" data-confirm-class="btn-success">
        <div class="modal-header">
            <h5 class="modal-title">Approve Loan Request</h5>
            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
        </div>
        <div class="modal-body">
            <input type="hidden" name="request_id" id="approveRequestId">

            <div class="mb-3">
                <label class="form-label">Approved Amount</label>
                <input type="number" step="0.01" min="1" name="amount" id="approveAmount" class="form-control" required>
                <small class="text-muted">Remaining approvable loanable amount: &#8369;<?= number_format($availableLoanAmount, 2) ?></small>
                <div class="text-danger small d-none" id="approveAmountWarning">
                    Approved amount cannot exceed the remaining approvable loanable amount.
                </div>
            </div>

            <div class="mb-3">
                <label class="form-label">Approved Months</label>
                <input type="number" step="0.1" min="0.1" max="6" name="months" id="approveMonths" class="form-control" required>
                <small class="text-muted">Maximum payment term is 6 months.</small>
            </div>

            <div class="mb-3">
                <label class="form-label">GCash Disbursement Reference Number</label>
                <input type="text" name="disbursement_reference_number" class="form-control" required>
            </div>

            <div class="mb-3">
                <label class="form-label">GCash Disbursement Proof Image</label>
                <input type="file" name="disbursement_proof_image" class="form-control" accept="image/jpeg,image/png,image/webp" required>
            </div>
        </div>
        <div class="modal-footer">
            <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
            <button class="btn btn-success" id="approveLoanButton">Approve Loan</button>
        </div>
      </form>
    </div>
  </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
const availableLoanAmount = <?= json_encode($availableLoanAmount) ?>;

function toggleLoanRequestGuestFields(prefix){
    const isGuarantor = document.getElementById(prefix + 'RequestGuarantor').checked;
    document.getElementById(prefix + 'RequestGuestFields').classList.toggle('d-none', !isGuarantor);
    [prefix + 'RequestGuestName', prefix + 'RequestGcashName', prefix + 'RequestGcashNumber'].forEach(id => {
        document.getElementById(id).required = isGuarantor;
    });
}

document.getElementById('addRequestGuarantor').addEventListener('change', () => toggleLoanRequestGuestFields('add'));

function toggleAdminLoanRequestGuestFields(){
    toggleLoanRequestGuestFields('edit');
}

document.querySelectorAll('.edit-loan-request-button').forEach(button => {
    button.addEventListener('click', () => {
        document.getElementById('editRequestId').value = button.dataset.requestId;
        document.getElementById('editRequestAmount').value = button.dataset.amount;
        document.getElementById('editRequestMonths').value = button.dataset.months;
        const cutoffSelect = document.getElementById('editRequestCutoff');
        const previousCutoff = cutoffSelect.querySelector('[data-previous-cutoff]');
        if (previousCutoff) previousCutoff.remove();
        const cutoff = button.dataset.firstPaymentCutoff || '';
        if (cutoff && !Array.from(cutoffSelect.options).some(option => option.value === cutoff)) {
            const option = new Option(cutoff + ' (past cutoff; select a new one)', cutoff);
            option.dataset.previousCutoff = '1';
            cutoffSelect.add(option);
        }
        cutoffSelect.value = cutoff;
        document.getElementById('editRequestGuarantor').checked = button.dataset.isGuarantor === '1';
        document.getElementById('editRequestGuestName').value = button.dataset.guestBorrowerName || '';
        document.getElementById('editRequestGcashName').value = button.dataset.guestGcashName || '';
        document.getElementById('editRequestGcashNumber').value = button.dataset.guestGcashNumber || '';
        toggleAdminLoanRequestGuestFields();
        new bootstrap.Modal(document.getElementById('editLoanRequestModal')).show();
    });
});
document.getElementById('editRequestGuarantor').addEventListener('change', toggleAdminLoanRequestGuestFields);

function validateApproveAmount(){
    const amountInput = document.getElementById('approveAmount');
    const warning = document.getElementById('approveAmountWarning');
    const approveButton = document.getElementById('approveLoanButton');
    const amount = parseFloat(amountInput.value || '0');
    const isTooHigh = amount > availableLoanAmount;

    warning.classList.toggle('d-none', !isTooHigh);
    approveButton.disabled = isTooHigh;
}

function openApproveLoanRequestModal(requestId, amount, months){
    document.getElementById('approveRequestId').value = requestId;
    document.getElementById('approveAmount').value = amount;
    document.getElementById('approveMonths').value = months;
    validateApproveAmount();
    new bootstrap.Modal(document.getElementById('approveLoanRequestModal')).show();
}

document.getElementById('approveAmount').addEventListener('input', validateApproveAmount);
document.getElementById('approveLoanRequestForm').addEventListener('submit', function(event){
    const amount = parseFloat(document.getElementById('approveAmount').value || '0');

    if (amount > availableLoanAmount) {
        event.preventDefault();
        event.stopImmediatePropagation();
        validateApproveAmount();
        appShowToast('Approved amount cannot exceed the remaining approvable loanable amount.', 'error');
    }
});
</script>
<?php render_footer(); ?>
</body>
</html>

