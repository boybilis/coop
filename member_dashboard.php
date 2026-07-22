<?php
include 'db.php';
include 'auth.php';
include 'layout.php';
require_member();

$borrowerId = active_borrower_id();
$activeMemberUserId = active_member_user_id();
$currentUserId = (int)($_SESSION['user_id'] ?? 0);

$memberStmt = $conn->prepare("
    SELECT
        borrowers.name,
        borrowers.first_name,
        borrowers.last_name,
        borrowers.gcash_name,
        borrowers.gcash_number,
        borrowers.status,
        borrowers.savings_closed,
        borrowers.created_at,
        users.username
    FROM borrowers
    JOIN users ON users.borrower_id = borrowers.id
    WHERE borrowers.id = ?
    AND users.id = ?
    LIMIT 1
");
$memberStmt->bind_param("ii", $borrowerId, $activeMemberUserId);
$memberStmt->execute();
$member = $memberStmt->get_result()->fetch_assoc();

if (!$member) {
    http_response_code(404);
    exit("Member profile not found");
}

$profileFirstName = $member['first_name'] ?? '';
$profileLastName = $member['last_name'] ?? '';
$hasMemberGcashProfile = trim($member['gcash_name'] ?? '') !== '' && trim($member['gcash_number'] ?? '') !== '';

if ($profileFirstName === '' && $profileLastName === '') {
    $nameParts = preg_split('/\s+/', trim($member['name']), 2);
    $profileFirstName = $nameParts[0] ?? '';
    $profileLastName = $nameParts[1] ?? '';
}

$savingsStmt = $conn->prepare("
    SELECT
        IFNULL(SUM(CASE WHEN type = 'DEPOSIT' THEN amount ELSE 0 END),0) AS deposits,
        IFNULL(SUM(CASE WHEN type = 'WITHDRAWAL' THEN amount ELSE 0 END),0) AS withdrawals
    FROM savings_transactions
    WHERE borrower_id = ?
");
$savingsStmt->bind_param("i", $borrowerId);
$savingsStmt->execute();
$savings = $savingsStmt->get_result()->fetch_assoc();
$netSavings = $savings['deposits'] - $savings['withdrawals'];
$savingsClosed = (int)$member['savings_closed'] === 1 || ((float)$netSavings <= 0 && (float)$savings['withdrawals'] > 0);
$canDepositSavings = !$savingsClosed;
$canWithdrawSavings = !$savingsClosed && (float)$netSavings > 0;
$withdrawalNotice = $_GET['withdrawal_notice'] ?? '';
$withdrawalNoticeTitle = '';
$withdrawalNoticeMessage = '';
$withdrawalNoticeClass = 'text-success';

if ($withdrawalNotice === 'submitted') {
    $withdrawalNoticeTitle = 'Withdrawal Submitted';
    $withdrawalNoticeMessage = 'Withdrawal request submitted for admin approval.';
} elseif ($withdrawalNotice === 'closing') {
    $withdrawalNoticeTitle = 'Full Withdrawal Submitted';
    $withdrawalNoticeMessage = 'This is a full withdrawal. The savings account will be closed after admin verification.';
    $withdrawalNoticeClass = 'text-warning';
} elseif ($withdrawalNotice === 'error') {
    $withdrawalNoticeTitle = 'Withdrawal Not Submitted';
    $withdrawalNoticeMessage = $_GET['withdrawal_message'] ?? 'Unable to submit withdrawal request.';
    $withdrawalNoticeClass = 'text-danger';
}

$loanSummaryStmt = $conn->prepare("
    SELECT
        COUNT(*) AS total_loans,
        IFNULL(SUM(CASE WHEN status = 'Active' THEN total_payable ELSE 0 END),0) AS active_total
    FROM loans
    WHERE borrower_id = ?
");
$loanSummaryStmt->bind_param("i", $borrowerId);
$loanSummaryStmt->execute();
$loanSummary = $loanSummaryStmt->get_result()->fetch_assoc();

$capitalStmt = $conn->prepare("
    SELECT IFNULL(SUM(amount),0) AS total
    FROM capital_contributions
    WHERE borrower_id = ?
");
$capitalStmt->bind_param("i", $borrowerId);
$capitalStmt->execute();
$capital = $capitalStmt->get_result()->fetch_assoc()['total'];

$loanableBreakdown = cooperative_loanable_amount_breakdown($conn);
$availableLoanCutoff = $loanableBreakdown['available_amount'];
$currentCutoffDate = $loanableBreakdown['cutoff_date'];
$currentScheduleSetting = cooperative_effective_payment_schedule_setting($conn, date('Y-m-d'));
$nextCutoffDate = cooperative_next_cutoff_after($currentCutoffDate, $currentScheduleSetting)->format('Y-m-d');
$expectedLoanableTriggerDate = (new DateTimeImmutable($currentCutoffDate))->modify('+10 days')->format('Y-m-d');
$showExpectedNextCutoffLoanable = date('Y-m-d') >= $expectedLoanableTriggerDate && date('Y-m-d') < $nextCutoffDate;
$activeMembers = (int)$conn->query("
    SELECT COUNT(*) AS total
    FROM borrowers
    WHERE status = 'Active'
")->fetch_assoc()['total'];
$expectedNextCutoffCapcon = (float)$activeMembers * 500;
$nextCutoffLoanDuesStmt = $conn->prepare("
    SELECT IFNULL(SUM(amount),0) AS total
    FROM payments
    WHERE due_date = ?
");
$nextCutoffLoanDuesStmt->bind_param("s", $nextCutoffDate);
$nextCutoffLoanDuesStmt->execute();
$expectedNextCutoffLoanDues = (float)$nextCutoffLoanDuesStmt->get_result()->fetch_assoc()['total'];
$expectedNextCutoffLoanable = $expectedNextCutoffCapcon + $expectedNextCutoffLoanDues;

$paymentSummaryStmt = $conn->prepare("
    SELECT
        IFNULL(SUM(CASE WHEN payments.paid = 0 THEN payments.amount ELSE 0 END),0) AS unpaid,
        IFNULL(SUM(CASE WHEN payments.paid = 1 THEN payments.amount ELSE 0 END),0) AS paid
    FROM payments
    JOIN loans ON loans.id = payments.loan_id
    WHERE loans.borrower_id = ?
");
$paymentSummaryStmt->bind_param("i", $borrowerId);
$paymentSummaryStmt->execute();
$payments = $paymentSummaryStmt->get_result()->fetch_assoc();

$cutoffStmt = $conn->prepare("
    SELECT payments.due_date, IFNULL(SUM(payments.amount),0) AS amount_due
    FROM payments
    JOIN loans ON loans.id = payments.loan_id
    WHERE loans.borrower_id = ?
    AND payments.paid = 0
    GROUP BY payments.due_date
    ORDER BY payments.due_date ASC
");
$cutoffStmt->bind_param("i", $borrowerId);
$cutoffStmt->execute();
$cutoffs = $cutoffStmt->get_result();
$cutoffAmounts = [];

while ($cutoff = $cutoffs->fetch_assoc()) {
    $cutoffAmounts[$cutoff['due_date']] = (float)$cutoff['amount_due'];
}

$linkedAccountsStmt = $conn->prepare("
    SELECT users.id, users.username, borrowers.name
    FROM users
    JOIN borrowers ON borrowers.id = users.borrower_id
    WHERE users.id = ?
    UNION
    SELECT linked_users.id, linked_users.username, borrowers.name
    FROM user_account_links
    JOIN users linked_users ON linked_users.id = user_account_links.linked_user_id
    JOIN borrowers ON borrowers.id = linked_users.borrower_id
    WHERE user_account_links.user_id = ?
    ORDER BY username ASC
");
$linkedAccountsStmt->bind_param("ii", $currentUserId, $currentUserId);
$linkedAccountsStmt->execute();
$linkedAccounts = $linkedAccountsStmt->get_result();
?>
<!DOCTYPE html>
<html>
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Member Dashboard</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
<link rel="stylesheet" href="assets/css/mobile.css">
<link rel="stylesheet" href="assets/css/theme.css?v=20260722-placeholders">
</head>

<body class="bg-light">
<?php render_navbar(); ?>
<div class="container mt-4 mb-5">

<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h3 class="mb-1">Member Dashboard</h3>
        <div class="text-muted">
            <span id="memberDisplayName"><?= htmlspecialchars($member['name']) ?></span> &bull; <?= htmlspecialchars($member['status']) ?> &bull; Member since <?= date('M d, Y', strtotime($member['created_at'])) ?>
        </div>
    </div>
    <div class="d-flex align-items-center gap-2">
        <form method="POST" action="ajax/switch_member_account.php" class="d-flex align-items-center gap-2">
            <select name="selected_user_id" class="form-control" onchange="this.form.submit()">
                <?php while($account = $linkedAccounts->fetch_assoc()): ?>
                    <option value="<?= $account['id'] ?>" <?= (int)$account['id'] === $activeMemberUserId ? 'selected' : '' ?>>
                        <?= htmlspecialchars($account['username']) ?>
                    </option>
                <?php endwhile; ?>
            </select>
        </form>
        <button class="btn btn-outline-primary" data-bs-toggle="modal" data-bs-target="#linkAccountModal">
            Link Account
        </button>
    </div>
</div>

<?php if(isset($_GET['payment_submitted'])): ?>
    <script>window.appToasts = window.appToasts || []; window.appToasts.push({type:'success', message:'Payment submitted for admin review.'});</script>
<?php endif; ?>

<?php if(isset($_GET['savings_submitted'])): ?>
    <script>window.appToasts = window.appToasts || []; window.appToasts.push({type:'success', message:'Savings submitted for admin verification.'});</script>
<?php endif; ?>

<?php if(isset($_GET['loan_requested'])): ?>
    <script>window.appToasts = window.appToasts || []; window.appToasts.push({type:'success', message:'Loan request submitted. Requests are reviewed first come, first served.'});</script>
<?php endif; ?>

<?php if(isset($_GET['error'])): ?>
    <script>window.appToasts = window.appToasts || []; window.appToasts.push({type:'error', message:<?= json_encode($_GET['error']) ?>});</script>
<?php endif; ?>

<div class="row">
    <div class="col-md-3 mb-3">
        <div class="card glass-card glass-info">
            <div class="card-body">
                <h6>Savings Balance</h6>
                <h3 class="text-info">&#8369;<?= number_format($netSavings,2) ?></h3>
                <?php if($savingsClosed): ?>
                    <div><small class="text-danger">Savings account is closed after full withdrawal.</small></div>
                <?php else: ?>
                    <div><small class="text-muted">Maintaining balance: &#8369;500.00</small></div>
                <?php endif; ?>
            </div>
            <div class="card-footer">
                <div class="d-flex gap-2">
                    <button class="btn btn-info w-50" data-bs-toggle="modal" data-bs-target="#savingsModal" <?= $canDepositSavings ? '' : 'disabled' ?>>
                        Deposit
                    </button>
                    <button class="btn btn-outline-info w-50" data-bs-toggle="modal" data-bs-target="#withdrawModal" <?= $canWithdrawSavings ? '' : 'disabled' ?>>
                        Withdraw
                    </button>
                </div>
            </div>
        </div>
    </div>

    <div class="col-md-3 mb-3">
        <div class="card glass-card glass-primary">
            <div class="card-body">
                <h6>Loans</h6>
                <h3 class="text-primary"><?= number_format($loanSummary['total_loans']) ?></h3>
                <small class="text-muted">
                    Active payable: &#8369;<?= number_format($loanSummary['active_total'],2) ?>
                </small>
            </div>
            <div class="card-footer">
                <?php if($hasMemberGcashProfile): ?>
                    <button class="btn btn-primary w-100" data-bs-toggle="modal" data-bs-target="#loanRequestModal">
                        Loan Request
                    </button>
                <?php else: ?>
                    <button type="button" class="btn btn-primary w-100" onclick="appShowToast('Please update your profile with GCash name and GCash number before filing a loan request.', 'warning')">
                        Loan Request
                    </button>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <div class="col-md-3 mb-3">
        <div class="card glass-card glass-warning">
            <div class="card-body">
                <h6>Capital Contribution</h6>
                <h3 class="text-warning">&#8369;<?= number_format($capital,2) ?></h3>
                <small class="text-muted d-block">Total posted capital</small>
            </div>
            <div class="card-footer">
                <small class="text-muted d-block">Total Available Loanable Amount</small>
                <strong class="d-block">&#8369;<?= number_format($availableLoanCutoff,2) ?></strong>
                <?php if($showExpectedNextCutoffLoanable): ?>
                    <small class="text-muted d-block mt-2">Expected Loanable Amount Next Cut-off</small>
                    <strong class="text-success d-block">&#8369;<?= number_format($expectedNextCutoffLoanable,2) ?></strong>
                    <small class="text-muted d-block"><?= date('M d, Y', strtotime($nextCutoffDate)) ?></small>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <div class="col-md-3 mb-3">
        <div class="card glass-card glass-danger">
            <div class="card-body">
                <h6>Payment Balance</h6>
                <h3 class="text-danger">&#8369;<?= number_format($payments['unpaid'],2) ?></h3>
                <small class="text-muted">
                    Paid: &#8369;<?= number_format($payments['paid'],2) ?>
                </small>
            </div>
            <div class="card-footer">
                <button class="btn btn-danger w-100" data-bs-toggle="modal" data-bs-target="#paymentModal">
                    Payment
                </button>
            </div>
        </div>
    </div>
</div>

<div class="card member-tabs-card shadow-sm mb-4">
    <div class="card-header">
        <ul class="nav nav-tabs card-header-tabs" id="memberDashboardTabs" role="tablist">
            <li class="nav-item" role="presentation">
                <button class="nav-link active" id="savings-tab" data-bs-toggle="tab" data-bs-target="#savings-pane" type="button" role="tab">
                    Savings
                </button>
            </li>
            <li class="nav-item" role="presentation">
                <button class="nav-link" id="loans-tab" data-bs-toggle="tab" data-bs-target="#loans-pane" type="button" role="tab">
                    Loans
                </button>
            </li>
            <li class="nav-item" role="presentation">
                <button class="nav-link" id="capital-tab" data-bs-toggle="tab" data-bs-target="#capital-pane" type="button" role="tab">
                    Cap Con
                </button>
            </li>
            <li class="nav-item" role="presentation">
                <button class="nav-link" id="payments-tab" data-bs-toggle="tab" data-bs-target="#payments-pane" type="button" role="tab">
                    Payments
                </button>
            </li>
            <li class="nav-item" role="presentation">
                <button class="nav-link" id="others-tab" data-bs-toggle="tab" data-bs-target="#others-pane" type="button" role="tab">
                    Others
                </button>
            </li>
        </ul>
    </div>

    <div class="card-body">
        <div class="tab-content">
            <div class="tab-pane fade show active" id="savings-pane" role="tabpanel" aria-labelledby="savings-tab">
                <h5>Recent Savings Transactions</h5>
                <div class="table-responsive">
                    <table class="table table-bordered table-hover mb-0">
                        <thead class="table-dark">
                            <tr>
                                <th>Date</th>
                                <th>Type</th>
                                <th>Deposit</th>
                                <th>Withdrawal</th>
                                <th>Balance</th>
                                <th>Remarks</th>
                            </tr>
                        </thead>
                        <tbody id="savingsHistoryTableBody" data-table="savings_history" data-columns="6">
                            <tr><td colspan="6" class="text-center text-muted">Loading savings history...</td></tr>
                        </tbody>
                    </table>
                </div>
                <div id="savingsHistoryPagination" class="mb-4"></div>

                <h5>Savings Submissions</h5>
                <div class="table-responsive">
                    <table class="table table-bordered table-hover mb-0">
                        <thead class="table-dark">
                            <tr>
                                <th>Date Submitted</th>
                                <th>Amount</th>
                                <th>Reference</th>
                                <th>Status</th>
                                <th width="160">Action</th>
                            </tr>
                        </thead>
                        <tbody id="savingsSubmissionsTableBody" data-table="savings_submissions" data-columns="5">
                            <tr><td colspan="5" class="text-center text-muted">Loading savings submissions...</td></tr>
                        </tbody>
                    </table>
                </div>
                <div id="savingsSubmissionsPagination" class="mb-4"></div>

                <h5>Withdrawal Requests</h5>
                <div class="table-responsive">
                    <table class="table table-bordered table-hover mb-0">
                        <thead class="table-dark">
                            <tr>
                                <th>Date Requested</th>
                                <th>Amount</th>
                                <th>GCash Name</th>
                                <th>GCash Number</th>
                                <th>Admin Reference</th>
                                <th>View File</th>
                                <th>Status</th>
                                <th width="160">Action</th>
                            </tr>
                        </thead>
                        <tbody id="withdrawalRequestsTableBody" data-table="withdrawal_requests" data-columns="8">
                            <tr><td colspan="8" class="text-center text-muted">Loading withdrawal requests...</td></tr>
                        </tbody>
                    </table>
                </div>
                <div id="withdrawalRequestsPagination"></div>
            </div>

            <div class="tab-pane fade" id="loans-pane" role="tabpanel" aria-labelledby="loans-tab">
                <h5>My Loans</h5>
                <div class="table-responsive mb-4">
                    <table class="table table-bordered table-hover align-middle mb-0">
                        <thead class="table-dark">
                            <tr>
                                <th>Loan ID</th>
                                <th>Amount</th>
                                <th>Interest</th>
                                <th>Total Payable</th>
                                <th>Progress</th>
                                <th>Status</th>
                                <th width="120">Action</th>
                            </tr>
                        </thead>
                        <tbody id="loansTableBody" data-table="loans" data-columns="7">
                            <tr><td colspan="7" class="text-center text-muted">Loading loans...</td></tr>
                        </tbody>
                    </table>
                </div>
                <div id="loansPagination" class="mb-4"></div>

                <h5>Loan Request Monitoring</h5>
                <div class="table-responsive">
                    <table class="table table-bordered table-hover align-middle mb-0">
                        <thead class="table-dark">
                            <tr>
                                <th>Request ID</th>
                                <th>Requested Amount</th>
                                <th>Months</th>
                                <th>Borrower For</th>
                                <th>Approved Amount</th>
                                <th>Date Requested</th>
                                <th>Status</th>
                                <th width="160">Action</th>
                            </tr>
                        </thead>
                        <tbody id="loanRequestsTableBody" data-table="loan_requests" data-columns="8">
                            <tr><td colspan="8" class="text-center text-muted">Loading loan requests...</td></tr>
                        </tbody>
                    </table>
                </div>
                <div id="loanRequestsPagination"></div>
            </div>

            <div class="tab-pane fade" id="capital-pane" role="tabpanel" aria-labelledby="capital-tab">
                <h5>Recent Capital Contributions</h5>
                <div class="table-responsive">
                    <table class="table table-bordered table-hover mb-0">
                        <thead class="table-dark">
                            <tr>
                                <th>Date</th>
                                <th>Type</th>
                                <th>Amount</th>
                                <th>Period</th>
                                <th>File</th>
                            </tr>
                        </thead>
                        <tbody id="capitalHistoryTableBody" data-table="capital_history" data-columns="5">
                            <tr><td colspan="5" class="text-center text-muted">Loading capital history...</td></tr>
                        </tbody>
                    </table>
                </div>
                <div id="capitalHistoryPagination"></div>
            </div>

            <div class="tab-pane fade" id="payments-pane" role="tabpanel" aria-labelledby="payments-tab">
                <h5>Upcoming Payments</h5>
                <div class="table-responsive mb-4">
                    <table class="table table-bordered table-hover align-middle mb-0">
                        <thead class="table-dark">
                            <tr>
                                <th>Loan</th>
                                <th>#</th>
                                <th>Amount</th>
                                <th>Due Date</th>
                            </tr>
                        </thead>
                        <tbody id="upcomingPaymentsTableBody" data-table="upcoming_payments" data-columns="4">
                            <tr><td colspan="4" class="text-center text-muted">Loading payments...</td></tr>
                        </tbody>
                    </table>
                </div>
                <div id="upcomingPaymentsPagination" class="mb-4"></div>

                <h5>Recent Payment Submissions</h5>
                <div class="table-responsive">
                    <table class="table table-bordered table-hover mb-0">
                        <thead class="table-dark">
                            <tr>
                                <th>Date</th>
                                <th>Cutoff</th>
                                <th>Capital</th>
                                <th>Loan Payment</th>
                                <th>Reference</th>
                                <th>Status</th>
                                <th width="160">Action</th>
                            </tr>
                        </thead>
                        <tbody id="paymentSubmissionsTableBody" data-table="payment_submissions" data-columns="7">
                            <tr><td colspan="7" class="text-center text-muted">Loading payment submissions...</td></tr>
                        </tbody>
                    </table>
                </div>
                <div id="paymentSubmissionsPagination"></div>
            </div>

            <div class="tab-pane fade" id="others-pane" role="tabpanel" aria-labelledby="others-tab">
                <h5>Profile Settings</h5>
                <p class="text-muted">Edit the selected linked account profile and login username.</p>

                <div class="alert alert-success d-none" id="profileSuccess"></div>
                <div class="alert alert-danger d-none" id="profileError"></div>

                <form id="profileForm" class="row g-3">
                    <input type="hidden" name="selected_member_user_id" value="<?= $activeMemberUserId ?>">

                    <div class="col-md-6">
                        <label>Username</label>
                        <input type="text" name="username" id="profileUsername" class="form-control" value="<?= htmlspecialchars($member['username']) ?>" required>
                    </div>

                    <div class="col-md-6">
                        <label>First Name</label>
                        <input type="text" name="first_name" id="profileFirstName" class="form-control" value="<?= htmlspecialchars($profileFirstName) ?>" required>
                    </div>

                    <div class="col-md-6">
                        <label>Last Name</label>
                        <input type="text" name="last_name" id="profileLastName" class="form-control" value="<?= htmlspecialchars($profileLastName) ?>" required>
                    </div>

                    <div class="col-md-6">
                        <label>GCash Name</label>
                        <input type="text" name="gcash_name" id="profileGcashName" class="form-control" value="<?= htmlspecialchars($member['gcash_name'] ?? '') ?>">
                    </div>

                    <div class="col-md-6">
                        <label>GCash Number</label>
                        <input type="text" name="gcash_number" id="profileGcashNumber" class="form-control" value="<?= htmlspecialchars($member['gcash_number'] ?? '') ?>">
                    </div>

                    <div class="col-12">
                        <button type="submit" class="btn btn-primary">Save Profile</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

</div>

<div class="modal fade" id="paymentModal">
  <div class="modal-dialog modal-lg">
    <div class="modal-content">
      <form method="POST" action="ajax/submit_member_payment.php" enctype="multipart/form-data">
        <div class="modal-header">
            <h5 class="modal-title">Submit Payment</h5>
            <button class="btn-close" data-bs-dismiss="modal"></button>
        </div>

        <div class="modal-body">
            <div class="row g-3">
                <div class="col-md-6">
                    <label class="fw-bold">Payment Cut-Off Date</label>
                    <input type="date" name="payment_date" id="paymentDate" class="form-control" value="<?= date('Y-m-d') ?>" required onchange="updateDueAmount()">
                    <small class="text-muted" id="dueAmountText">Amount due: &#8369;0.00</small>
                </div>

                <div class="col-md-6">
                    <label>Capital Contribution</label>
                    <input type="number" step="0.01" min="0" name="capital_contribution" id="capitalContributionInput" class="form-control" value="0" oninput="updatePaymentTotal()">
                </div>

                <div class="col-md-6">
                    <label>Loan Payment</label>
                    <input type="number" step="0.01" min="0" name="loan_payment" id="loanPaymentInput" class="form-control" value="0" oninput="updatePaymentTotal()">
                    <small class="text-muted">Loan amount due is based on the selected payment cut-off date.</small>
                </div>

                <div class="col-md-6">
                    <label>Reference Payment Number</label>
                    <input type="text" name="reference_number" class="form-control" required>
                </div>

                <div class="col-md-6">
                    <label>GCash Reference Image</label>
                    <input type="file" name="proof_image" class="form-control" accept="image/jpeg,image/png,image/webp" required>
                </div>

                <div class="col-12">
                    <div class="alert alert-info mb-0">
                        <strong>TOTAL Amount:</strong>
                        <span id="paymentTotalText">&#8369;0.00</span>
                        <small class="d-block text-muted">Loan payment + capital contribution</small>
                    </div>
                </div>
            </div>
        </div>

        <div class="modal-footer">
            <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
            <button class="btn btn-danger">Submit Payment</button>
        </div>
      </form>
    </div>
  </div>
</div>

<div class="modal fade" id="savingsModal">
  <div class="modal-dialog">
    <div class="modal-content">
      <form method="POST" action="ajax/submit_savings.php" enctype="multipart/form-data">
        <input type="hidden" name="selected_member_user_id" value="<?= $activeMemberUserId ?>">
        <div class="modal-header">
            <h5 class="modal-title">Deposit Savings</h5>
            <button class="btn-close" data-bs-dismiss="modal"></button>
        </div>

        <div class="modal-body">
            <div class="alert alert-info py-2">
                This deposit will be submitted for <strong><?= htmlspecialchars($member['name']) ?></strong>.
            </div>

            <div class="mb-3">
                <label>Amount to Deposit</label>
                <input type="number" step="0.01" min="1" name="amount" class="form-control" required>
            </div>

            <div class="mb-3">
                <label>Reference Number</label>
                <input type="text" name="reference_number" class="form-control" required>
            </div>

            <div class="mb-3">
                <label>Reference Image</label>
                <input type="file" name="proof_image" class="form-control" accept="image/jpeg,image/png,image/webp" required>
            </div>
        </div>

        <div class="modal-footer">
            <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
            <button class="btn btn-info">Submit Deposit</button>
        </div>
      </form>
    </div>
  </div>
</div>

<div class="modal fade" id="withdrawModal">
  <div class="modal-dialog">
    <div class="modal-content">
      <form method="POST" action="ajax/withdraw_savings.php" id="withdrawSavingsForm">
        <input type="hidden" name="selected_member_user_id" value="<?= $activeMemberUserId ?>">
        <div class="modal-header">
            <h5 class="modal-title">Withdraw Savings</h5>
            <button class="btn-close" data-bs-dismiss="modal"></button>
        </div>

        <div class="modal-body">
            <div class="alert alert-info py-2">
                This withdrawal will be submitted for <strong><?= htmlspecialchars($member['name']) ?></strong>.
                A withdrawal must leave at least <strong>&#8369;500.00</strong>, unless you withdraw the full balance to close the savings account.
            </div>

            <div class="mb-3">
                <label>Amount to Withdraw</label>
                <input type="number" step="0.01" min="1" max="<?= $netSavings ?>" name="amount" id="withdrawSavingsAmount" class="form-control" required>
                <small class="text-muted">Available savings: &#8369;<?= number_format($netSavings,2) ?></small>
            </div>

            <div class="mb-3">
                <label>GCash Name</label>
                <input type="text" name="gcash_name" id="withdrawGcashName" class="form-control" value="<?= htmlspecialchars($member['gcash_name'] ?? '') ?>" required>
            </div>

            <div class="mb-3">
                <label>GCash Number</label>
                <input type="text" name="gcash_number" id="withdrawGcashNumber" class="form-control" value="<?= htmlspecialchars($member['gcash_number'] ?? '') ?>" required>
            </div>
        </div>

        <div class="modal-footer">
            <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
            <button class="btn btn-info">Withdraw</button>
        </div>
      </form>
    </div>
  </div>
</div>

<div class="modal fade" id="editPaymentSubmissionModal">
  <div class="modal-dialog">
    <div class="modal-content">
        <div class="modal-header">
            <h5 class="modal-title">Edit Payment Submission</h5>
            <button class="btn-close" data-bs-dismiss="modal"></button>
        </div>

        <div class="modal-body">
            <input type="hidden" id="editPaymentSubmissionId">

            <div class="mb-3">
                <label>Payment Cut-Off Date</label>
                <input type="date" id="editPaymentDate" class="form-control" required>
            </div>

            <div class="mb-3">
                <label>Capital Contribution</label>
                <input type="number" step="0.01" min="0" id="editPaymentCapital" class="form-control" required>
            </div>

            <div class="mb-3">
                <label>Loan Payment</label>
                <input type="number" step="0.01" min="0" id="editPaymentLoan" class="form-control" required>
            </div>

            <div class="mb-3">
                <label>Reference Payment Number</label>
                <input type="text" id="editPaymentReference" class="form-control" required>
            </div>

            <div class="mb-3">
                <label>Replace Reference Image</label>
                <input type="file" id="editPaymentImage" class="form-control" accept="image/jpeg,image/png,image/webp">
                <small class="text-muted">Leave blank to keep the current image.</small>
            </div>
        </div>

        <div class="modal-footer">
            <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
            <button type="button" class="btn btn-info" onclick="savePaymentSubmissionEdit()">Save Changes</button>
        </div>
    </div>
  </div>
</div>

<div class="modal fade" id="editSavingsSubmissionModal">
  <div class="modal-dialog">
    <div class="modal-content">
        <div class="modal-header">
            <h5 class="modal-title">Edit Savings Deposit</h5>
            <button class="btn-close" data-bs-dismiss="modal"></button>
        </div>

        <div class="modal-body">
            <div class="alert alert-danger d-none" id="savingsSubmissionEditError"></div>
            <input type="hidden" id="editSavingsSubmissionId">

            <div class="mb-3">
                <label>Amount to Deposit</label>
                <input type="number" step="0.01" min="1" id="editSavingsSubmissionAmount" class="form-control" required>
            </div>

            <div class="mb-3">
                <label>Reference Number</label>
                <input type="text" id="editSavingsSubmissionReference" class="form-control" required>
            </div>

            <div class="mb-3">
                <label>Replace Reference Image</label>
                <input type="file" id="editSavingsSubmissionImage" class="form-control" accept="image/jpeg,image/png,image/webp">
                <small class="text-muted">Leave blank to keep the current image.</small>
            </div>
        </div>

        <div class="modal-footer">
            <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
            <button type="button" class="btn btn-info" onclick="saveSavingsSubmissionEdit()">Save Changes</button>
        </div>
    </div>
  </div>
</div>

<div class="modal fade" id="editWithdrawalRequestModal">
  <div class="modal-dialog">
    <div class="modal-content">
        <div class="modal-header">
            <h5 class="modal-title">Edit Withdrawal Request</h5>
            <button class="btn-close" data-bs-dismiss="modal"></button>
        </div>

        <div class="modal-body">
            <div class="alert alert-danger d-none" id="withdrawalRequestEditError"></div>
            <input type="hidden" id="editWithdrawalRequestId">

            <div class="mb-3">
                <label>Amount to Withdraw</label>
                <input type="number" step="0.01" min="1" id="editWithdrawalRequestAmount" class="form-control" required>
            </div>

            <div class="mb-3">
                <label>GCash Name</label>
                <input type="text" id="editWithdrawalRequestGcashName" class="form-control" required>
            </div>

            <div class="mb-3">
                <label>GCash Number</label>
                <input type="text" id="editWithdrawalRequestGcashNumber" class="form-control" required>
            </div>
        </div>

        <div class="modal-footer">
            <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
            <button type="button" class="btn btn-info" onclick="saveWithdrawalRequestEdit()">Save Changes</button>
        </div>
    </div>
  </div>
</div>

<div class="modal fade" id="loanRequestModal">
  <div class="modal-dialog">
    <div class="modal-content">
      <form method="POST" action="ajax/submit_loan_request.php">
        <div class="modal-header">
            <h5 class="modal-title">Loan Request</h5>
            <button class="btn-close" data-bs-dismiss="modal"></button>
        </div>

        <div class="modal-body">
            <div class="mb-3">
                <label>Amount of Loan</label>
                <input type="number" step="0.01" min="1" name="amount" id="loanRequestAmount" class="form-control" required>
                <small class="text-muted">Available Loanable Amount to date: &#8369;<?= number_format($availableLoanCutoff, 2) ?></small>
            </div>

            <div class="mb-3">
                <label>Months to Pay</label>
                <input type="number" step="0.1" min="0.1" max="6" name="months" class="form-control" required>
                <small class="text-muted">Maximum of 6 months.</small>
            </div>

            <div class="form-check mb-3">
                <input class="form-check-input" type="checkbox" name="is_guarantor" value="1" id="loanRequestGuarantor" onchange="toggleGuestBorrowerInput()">
                <label class="form-check-label" for="loanRequestGuarantor">Act as Guarantor</label>
            </div>

            <div class="mb-3 d-none" id="guestBorrowerGroup">
                <label>Guest Borrower Name</label>
                <input type="text" name="guest_borrower_name" id="guestBorrowerName" class="form-control">
                <div class="mt-3">
                    <label>Guest GCash Name</label>
                    <input type="text" name="guest_gcash_name" id="guestGcashName" class="form-control">
                </div>
                <div class="mt-3">
                    <label>Guest GCash Number</label>
                    <input type="text" name="guest_gcash_number" id="guestGcashNumber" class="form-control">
                </div>
                <small class="text-muted">Leave unchecked if this loan is for your own account.</small>
            </div>
        </div>

        <div class="modal-footer">
            <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
            <button class="btn btn-primary" id="loanRequestSubmitButton">Submit Request</button>
        </div>
      </form>
    </div>
  </div>
</div>

<div class="modal fade" id="editLoanRequestModal">
  <div class="modal-dialog">
    <div class="modal-content">
        <div class="modal-header">
            <h5 class="modal-title">Edit Loan Request</h5>
            <button class="btn-close" data-bs-dismiss="modal"></button>
        </div>

        <div class="modal-body">
            <div class="alert alert-danger d-none" id="loanRequestEditError"></div>
            <input type="hidden" id="editLoanRequestId">

            <div class="mb-3">
                <label>Amount of Loan</label>
                <input type="number" step="0.01" min="1" id="editLoanRequestAmount" class="form-control" required>
                <small class="text-muted">Available Loanable Amount to date: &#8369;<?= number_format($availableLoanCutoff, 2) ?></small>
            </div>

            <div class="mb-3">
                <label>Months to Pay</label>
                <input type="number" step="0.1" min="0.1" max="6" id="editLoanRequestMonths" class="form-control" required>
                <small class="text-muted">Maximum of 6 months.</small>
            </div>

            <div class="form-check mb-3">
                <input class="form-check-input" type="checkbox" id="editLoanRequestGuarantor" onchange="toggleEditGuestBorrowerInput()">
                <label class="form-check-label" for="editLoanRequestGuarantor">Act as Guarantor</label>
            </div>

            <div class="mb-3 d-none" id="editGuestBorrowerGroup">
                <label>Guest Borrower Name</label>
                <input type="text" id="editGuestBorrowerName" class="form-control">
                <div class="mt-3">
                    <label>Guest GCash Name</label>
                    <input type="text" id="editGuestGcashName" class="form-control">
                </div>
                <div class="mt-3">
                    <label>Guest GCash Number</label>
                    <input type="text" id="editGuestGcashNumber" class="form-control">
                </div>
            </div>
        </div>

        <div class="modal-footer">
            <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
            <button type="button" class="btn btn-primary" id="editLoanRequestSaveButton" onclick="saveLoanRequestEdit()">Save Changes</button>
        </div>
    </div>
  </div>
</div>

<div class="modal fade" id="linkAccountModal">
  <div class="modal-dialog">
    <div class="modal-content">
        <div class="modal-header">
            <h5 class="modal-title">Link Another Account</h5>
            <button class="btn-close" data-bs-dismiss="modal"></button>
        </div>

        <div class="modal-body">
            <div class="alert alert-danger d-none" id="linkAccountError"></div>
            <div class="alert alert-success d-none" id="linkAccountSuccess"></div>

            <div class="mb-3">
                <label>Other Account Username</label>
                <input type="text" id="linkUsername" class="form-control" required>
            </div>

            <div class="mb-3">
                <label>Other Account Password</label>
                <input type="password" id="linkPassword" class="form-control" required>
                <small class="text-muted">The other account password must already be set.</small>
            </div>
        </div>

        <div class="modal-footer">
            <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
            <button type="button" class="btn btn-primary" onclick="linkAccount()">Link Account</button>
        </div>
    </div>
  </div>
</div>

<?php if($withdrawalNoticeTitle): ?>
<div class="modal fade" id="withdrawalNoticeModal" tabindex="-1">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content">
        <div class="modal-header">
            <h5 class="modal-title <?= $withdrawalNoticeClass ?>"><?= htmlspecialchars($withdrawalNoticeTitle) ?></h5>
            <button class="btn-close" data-bs-dismiss="modal"></button>
        </div>

        <div class="modal-body">
            <?= htmlspecialchars($withdrawalNoticeMessage) ?>
        </div>

        <div class="modal-footer">
            <button type="button" class="btn btn-primary" data-bs-dismiss="modal">OK</button>
        </div>
    </div>
  </div>
</div>
<?php endif; ?>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
const cutoffAmounts = <?= json_encode($cutoffAmounts) ?>;
const currentSavingsBalance = <?= json_encode((float)$netSavings) ?>;
const dashboardTables = [
    { bodyId: 'loansTableBody', paginationId: 'loansPagination' },
    { bodyId: 'loanRequestsTableBody', paginationId: 'loanRequestsPagination' },
    { bodyId: 'upcomingPaymentsTableBody', paginationId: 'upcomingPaymentsPagination' },
    { bodyId: 'savingsHistoryTableBody', paginationId: 'savingsHistoryPagination' },
    { bodyId: 'savingsSubmissionsTableBody', paginationId: 'savingsSubmissionsPagination' },
    { bodyId: 'withdrawalRequestsTableBody', paginationId: 'withdrawalRequestsPagination' },
    { bodyId: 'capitalHistoryTableBody', paginationId: 'capitalHistoryPagination' },
    { bodyId: 'paymentSubmissionsTableBody', paginationId: 'paymentSubmissionsPagination' }
];

function updateDueAmount(){
    let paymentDate = document.getElementById('paymentDate').value;
    let amount = parseFloat(cutoffAmounts[paymentDate] || '0');

    document.getElementById('dueAmountText').innerText = 'Amount due: \u20B1' + amount.toLocaleString(undefined, {
        minimumFractionDigits: 2,
        maximumFractionDigits: 2
    });

    if(amount > 0){
        document.getElementById('loanPaymentInput').value = amount.toFixed(2);
    }

    updatePaymentTotal();
}

function updatePaymentTotal(){
    let capitalAmount = parseFloat(document.getElementById('capitalContributionInput').value || '0');
    let loanAmount = parseFloat(document.getElementById('loanPaymentInput').value || '0');
    let totalAmount = capitalAmount + loanAmount;

    document.getElementById('paymentTotalText').innerText = '\u20B1' + totalAmount.toLocaleString(undefined, {
        minimumFractionDigits: 2,
        maximumFractionDigits: 2
    });
}

document.getElementById('paymentModal').addEventListener('shown.bs.modal', updateDueAmount);

function loadDashboardTable(config, page = 1){
    let body = document.getElementById(config.bodyId);
    let pagination = document.getElementById(config.paginationId);
    let table = body.dataset.table;
    let columns = body.dataset.columns;

    body.innerHTML = `<tr><td colspan="${columns}" class="text-center text-muted">Loading...</td></tr>`;
    pagination.innerHTML = '';

    fetch(`ajax/member_dashboard_table.php?table=${encodeURIComponent(table)}&page=${encodeURIComponent(page)}`)
        .then(res => res.json())
        .then(data => {
            if(data.error){
                body.innerHTML = `<tr><td colspan="${columns}" class="text-center text-danger">${data.error}</td></tr>`;
                return;
            }

            body.innerHTML = data.html;
            pagination.innerHTML = data.pagination;

            pagination.querySelectorAll('button[data-page]').forEach(button => {
                button.addEventListener('click', () => {
                    let nextPage = parseInt(button.dataset.page, 10);
                    if(nextPage > 0){
                        loadDashboardTable(config, nextPage);
                    }
                });
            });
        })
        .catch(() => {
            body.innerHTML = `<tr><td colspan="${columns}" class="text-center text-danger">Unable to load data.</td></tr>`;
        });
}

document.addEventListener('DOMContentLoaded', () => {
    dashboardTables.forEach(config => loadDashboardTable(config));

    let withdrawalNoticeModal = document.getElementById('withdrawalNoticeModal');
    if(withdrawalNoticeModal){
        new bootstrap.Modal(withdrawalNoticeModal).show();
    }

    let withdrawForm = document.getElementById('withdrawSavingsForm');
    if(withdrawForm){
        withdrawForm.addEventListener('submit', event => {
            let amount = parseFloat(document.getElementById('withdrawSavingsAmount').value || '0');
            if(Math.round(amount * 100) === Math.round(currentSavingsBalance * 100)){
                if(withdrawForm.dataset.confirmed === '1'){
                    return;
                }

                event.preventDefault();

                appConfirm('You are withdrawing the full savings balance. After admin verification, this savings account will be closed and Deposit/Withdraw will be disabled. Continue?', {
                    okText: 'Continue',
                    okClass: 'btn-warning'
                }).then(confirmed => {
                    if(confirmed){
                        withdrawForm.dataset.confirmed = '1';
                        withdrawForm.submit();
                    }
                });
            }
        });
    }

    let profileForm = document.getElementById('profileForm');
    if(profileForm){
        profileForm.addEventListener('submit', event => {
            event.preventDefault();

            let submitButton = profileForm.querySelector('button[type="submit"]');

            submitButton.disabled = true;
            submitButton.innerText = 'Saving...';

            fetch('ajax/update_member_profile.php', {
                method: 'POST',
                body: new FormData(profileForm)
            })
            .then(res => res.json())
            .then(data => {
                if(data.error){
                    appShowToast(data.error, 'error');
                    return;
                }

                appShowToast(data.message || 'Profile updated successfully.', 'success');
                document.getElementById('memberDisplayName').innerText = data.profile.name;
                document.getElementById('withdrawGcashName').value = data.profile.gcash_name || '';
                document.getElementById('withdrawGcashNumber').value = data.profile.gcash_number || '';
            })
            .catch(() => {
                appShowToast('Unable to update profile.', 'error');
            })
            .finally(() => {
                submitButton.disabled = false;
                submitButton.innerText = 'Save Profile';
            });
        });
    }

});

function loanRequestsConfig(){
    return dashboardTables.find(config => config.bodyId === 'loanRequestsTableBody');
}

function toggleGuestBorrowerInput(){
    let checkbox = document.getElementById('loanRequestGuarantor');
    let group = document.getElementById('guestBorrowerGroup');
    let inputs = [
        document.getElementById('guestBorrowerName'),
        document.getElementById('guestGcashName'),
        document.getElementById('guestGcashNumber')
    ];

    group.classList.toggle('d-none', !checkbox.checked);
    inputs.forEach(input => input.required = checkbox.checked);

    if(!checkbox.checked){
        inputs.forEach(input => input.value = '');
    }
}

function toggleEditGuestBorrowerInput(){
    let checkbox = document.getElementById('editLoanRequestGuarantor');
    let group = document.getElementById('editGuestBorrowerGroup');
    let inputs = [
        document.getElementById('editGuestBorrowerName'),
        document.getElementById('editGuestGcashName'),
        document.getElementById('editGuestGcashNumber')
    ];

    group.classList.toggle('d-none', !checkbox.checked);
    inputs.forEach(input => input.required = checkbox.checked);

    if(!checkbox.checked){
        inputs.forEach(input => input.value = '');
    }
}

function openLoanRequestEdit(id, amount, months, isGuarantor, guestBorrowerName, guestGcashName, guestGcashNumber){
    document.getElementById('editLoanRequestId').value = id;
    document.getElementById('editLoanRequestAmount').value = amount;
    document.getElementById('editLoanRequestMonths').value = months;
    document.getElementById('editLoanRequestGuarantor').checked = parseInt(isGuarantor || 0) === 1;
    document.getElementById('editGuestBorrowerName').value = guestBorrowerName || '';
    document.getElementById('editGuestGcashName').value = guestGcashName || '';
    document.getElementById('editGuestGcashNumber').value = guestGcashNumber || '';
    toggleEditGuestBorrowerInput();
    document.getElementById('loanRequestEditError').classList.add('d-none');

    new bootstrap.Modal(document.getElementById('editLoanRequestModal')).show();
}

function saveLoanRequestEdit(){
    let requestId = document.getElementById('editLoanRequestId').value;
    let amount = document.getElementById('editLoanRequestAmount').value;
    let months = document.getElementById('editLoanRequestMonths').value;
    let isGuarantor = document.getElementById('editLoanRequestGuarantor').checked ? '1' : '0';
    let guestBorrowerName = document.getElementById('editGuestBorrowerName').value;
    let guestGcashName = document.getElementById('editGuestGcashName').value;
    let guestGcashNumber = document.getElementById('editGuestGcashNumber').value;
    let errorBox = document.getElementById('loanRequestEditError');

    errorBox.classList.add('d-none');

    fetch('ajax/update_loan_request.php', {
        method: 'POST',
        headers: {'Content-Type':'application/x-www-form-urlencoded'},
        body: 'request_id=' + encodeURIComponent(requestId)
            + '&amount=' + encodeURIComponent(amount)
            + '&months=' + encodeURIComponent(months)
            + '&is_guarantor=' + encodeURIComponent(isGuarantor)
            + '&guest_borrower_name=' + encodeURIComponent(guestBorrowerName)
            + '&guest_gcash_name=' + encodeURIComponent(guestGcashName)
            + '&guest_gcash_number=' + encodeURIComponent(guestGcashNumber)
    })
    .then(res => res.json())
    .then(data => {
        if(data.error){
            appShowToast(data.error, 'error');
            return;
        }

        bootstrap.Modal.getInstance(document.getElementById('editLoanRequestModal')).hide();
        appShowToast('Loan request updated.', 'success');
        loadDashboardTable(loanRequestsConfig());
    });
}

function deleteLoanRequest(id){
    appConfirm('Delete this pending loan request?', {
        okText: 'Delete',
        okClass: 'btn-danger'
    }).then(confirmed => {
        if(!confirmed){
            return;
        }

        fetch('ajax/delete_loan_request.php', {
            method: 'POST',
            headers: {'Content-Type':'application/x-www-form-urlencoded'},
            body: 'request_id=' + encodeURIComponent(id)
        })
        .then(res => res.json())
        .then(data => {
            if(data.error){
                appShowToast(data.error, 'error');
                return;
            }

            appShowToast('Loan request deleted.', 'success');
            loadDashboardTable(loanRequestsConfig());
        });
    });
}

function savingsSubmissionsConfig(){
    return dashboardTables.find(config => config.bodyId === 'savingsSubmissionsTableBody');
}

function paymentSubmissionsConfig(){
    return dashboardTables.find(config => config.bodyId === 'paymentSubmissionsTableBody');
}

function withdrawalRequestsConfig(){
    return dashboardTables.find(config => config.bodyId === 'withdrawalRequestsTableBody');
}

function openPaymentSubmissionEdit(id, paymentDate, capitalContribution, loanPayment, referenceNumber){
    document.getElementById('editPaymentSubmissionId').value = id;
    document.getElementById('editPaymentDate').value = paymentDate;
    document.getElementById('editPaymentCapital').value = capitalContribution;
    document.getElementById('editPaymentLoan').value = loanPayment;
    document.getElementById('editPaymentReference').value = referenceNumber;
    document.getElementById('editPaymentImage').value = '';

    new bootstrap.Modal(document.getElementById('editPaymentSubmissionModal')).show();
}

function savePaymentSubmissionEdit(){
    let formData = new FormData();
    let image = document.getElementById('editPaymentImage').files[0];

    formData.append('submission_id', document.getElementById('editPaymentSubmissionId').value);
    formData.append('payment_date', document.getElementById('editPaymentDate').value);
    formData.append('capital_contribution', document.getElementById('editPaymentCapital').value);
    formData.append('loan_payment', document.getElementById('editPaymentLoan').value);
    formData.append('reference_number', document.getElementById('editPaymentReference').value);

    if(image){
        formData.append('proof_image', image);
    }

    fetch('ajax/update_payment_submission.php', {
        method: 'POST',
        body: formData
    })
    .then(res => res.json())
    .then(data => {
        if(data.error){
            appShowToast(data.error, 'error');
            return;
        }

        bootstrap.Modal.getInstance(document.getElementById('editPaymentSubmissionModal')).hide();
        appShowToast('Payment submission updated.', 'success');
        loadDashboardTable(paymentSubmissionsConfig());
    });
}

function cancelPaymentSubmission(id){
    appConfirm('Cancel this pending payment submission?', {
        okText: 'Cancel Payment',
        okClass: 'btn-danger'
    }).then(confirmed => {
        if(!confirmed){
            return;
        }

        fetch('ajax/delete_payment_submission.php', {
            method: 'POST',
            headers: {'Content-Type':'application/x-www-form-urlencoded'},
            body: 'submission_id=' + encodeURIComponent(id)
        })
        .then(res => res.json())
        .then(data => {
            if(data.error){
                appShowToast(data.error, 'error');
                return;
            }

            appShowToast('Payment submission cancelled.', 'success');
            loadDashboardTable(paymentSubmissionsConfig());
        });
    });
}

function openSavingsSubmissionEdit(id, amount, referenceNumber){
    document.getElementById('editSavingsSubmissionId').value = id;
    document.getElementById('editSavingsSubmissionAmount').value = amount;
    document.getElementById('editSavingsSubmissionReference').value = referenceNumber;
    document.getElementById('editSavingsSubmissionImage').value = '';
    document.getElementById('savingsSubmissionEditError').classList.add('d-none');

    new bootstrap.Modal(document.getElementById('editSavingsSubmissionModal')).show();
}

function saveSavingsSubmissionEdit(){
    let formData = new FormData();
    let image = document.getElementById('editSavingsSubmissionImage').files[0];
    let errorBox = document.getElementById('savingsSubmissionEditError');

    errorBox.classList.add('d-none');
    formData.append('submission_id', document.getElementById('editSavingsSubmissionId').value);
    formData.append('amount', document.getElementById('editSavingsSubmissionAmount').value);
    formData.append('reference_number', document.getElementById('editSavingsSubmissionReference').value);

    if(image){
        formData.append('proof_image', image);
    }

    fetch('ajax/update_savings_submission.php', {
        method: 'POST',
        body: formData
    })
    .then(res => res.json())
    .then(data => {
        if(data.error){
            appShowToast(data.error, 'error');
            return;
        }

        bootstrap.Modal.getInstance(document.getElementById('editSavingsSubmissionModal')).hide();
        appShowToast('Savings submission updated.', 'success');
        loadDashboardTable(savingsSubmissionsConfig());
    });
}

function deleteSavingsSubmission(id){
    appConfirm('Delete this pending savings submission?', {
        okText: 'Delete',
        okClass: 'btn-danger'
    }).then(confirmed => {
        if(!confirmed){
            return;
        }

        fetch('ajax/delete_savings_submission.php', {
            method: 'POST',
            headers: {'Content-Type':'application/x-www-form-urlencoded'},
            body: 'submission_id=' + encodeURIComponent(id)
        })
        .then(res => res.json())
        .then(data => {
            if(data.error){
                appShowToast(data.error, 'error');
                return;
            }

            appShowToast('Savings submission deleted.', 'success');
            loadDashboardTable(savingsSubmissionsConfig());
        });
    });
}

function openWithdrawalRequestEdit(id, amount, gcashName, gcashNumber){
    document.getElementById('editWithdrawalRequestId').value = id;
    document.getElementById('editWithdrawalRequestAmount').value = amount;
    document.getElementById('editWithdrawalRequestGcashName').value = gcashName;
    document.getElementById('editWithdrawalRequestGcashNumber').value = gcashNumber;
    document.getElementById('withdrawalRequestEditError').classList.add('d-none');

    new bootstrap.Modal(document.getElementById('editWithdrawalRequestModal')).show();
}

function saveWithdrawalRequestEdit(){
    let requestId = document.getElementById('editWithdrawalRequestId').value;
    let amount = document.getElementById('editWithdrawalRequestAmount').value;
    let gcashName = document.getElementById('editWithdrawalRequestGcashName').value;
    let gcashNumber = document.getElementById('editWithdrawalRequestGcashNumber').value;
    let errorBox = document.getElementById('withdrawalRequestEditError');

    errorBox.classList.add('d-none');

    fetch('ajax/update_withdrawal_request.php', {
        method: 'POST',
        headers: {'Content-Type':'application/x-www-form-urlencoded'},
        body: 'request_id=' + encodeURIComponent(requestId)
            + '&amount=' + encodeURIComponent(amount)
            + '&gcash_name=' + encodeURIComponent(gcashName)
            + '&gcash_number=' + encodeURIComponent(gcashNumber)
    })
    .then(res => res.json())
    .then(data => {
        if(data.error){
            appShowToast(data.error, 'error');
            return;
        }

        bootstrap.Modal.getInstance(document.getElementById('editWithdrawalRequestModal')).hide();
        appShowToast('Withdrawal request updated.', 'success');
        loadDashboardTable(withdrawalRequestsConfig());
    });
}

function deleteWithdrawalRequest(id){
    appConfirm('Delete this pending withdrawal request?', {
        okText: 'Delete',
        okClass: 'btn-danger'
    }).then(confirmed => {
        if(!confirmed){
            return;
        }

        fetch('ajax/delete_withdrawal_request.php', {
            method: 'POST',
            headers: {'Content-Type':'application/x-www-form-urlencoded'},
            body: 'request_id=' + encodeURIComponent(id)
        })
        .then(res => res.json())
        .then(data => {
            if(data.error){
                appShowToast(data.error, 'error');
                return;
            }

            appShowToast('Withdrawal request deleted.', 'success');
            loadDashboardTable(withdrawalRequestsConfig());
        });
    });
}

function linkAccount(){
    let username = document.getElementById('linkUsername').value.trim();
    let password = document.getElementById('linkPassword').value;

    fetch('ajax/link_member_account.php', {
        method: 'POST',
        headers: {'Content-Type':'application/x-www-form-urlencoded'},
        body: 'username=' + encodeURIComponent(username)
            + '&password=' + encodeURIComponent(password)
    })
    .then(res => res.json())
    .then(data => {
        if(data.error){
            appShowToast(data.error, 'error');
            return;
        }

        appShowToast('Account linked. Reloading dashboard...', 'success');
        setTimeout(() => window.location.reload(), 700);
    });
}
</script>
<?php render_footer(); ?>
</body>
</html>

