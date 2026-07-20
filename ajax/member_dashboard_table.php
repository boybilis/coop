<?php
include '../db.php';
include '../auth.php';
require_member();

header('Content-Type: application/json');

$borrowerId = active_borrower_id();
$table = $_GET['table'] ?? '';
$page = max(1, (int)($_GET['page'] ?? 1));
$perPage = 5;
$offset = ($page - 1) * $perPage;
$allowedTables = ['loans', 'loan_requests', 'upcoming_payments', 'savings_history', 'savings_submissions', 'withdrawal_requests', 'capital_history', 'payment_submissions'];

if (!in_array($table, $allowedTables, true)) {
    echo json_encode(["error" => "Invalid table"]);
    exit;
}

function dashboard_badge_class($status)
{
    if ($status === 'Approved' || $status === 'Completed') {
        return 'success';
    }

    if ($status === 'Rejected') {
        return 'danger';
    }

    return 'warning text-dark';
}

function dashboard_pagination($page, $totalPages)
{
    if ($totalPages <= 1) {
        return '';
    }

    $html = '<div class="d-flex justify-content-between align-items-center mt-2">';
    $html .= '<button class="btn btn-outline-secondary btn-sm" data-page="' . ($page - 1) . '" ' . ($page <= 1 ? 'disabled' : '') . '>Previous</button>';
    $html .= '<small class="text-muted">Page ' . $page . ' of ' . $totalPages . '</small>';
    $html .= '<button class="btn btn-outline-secondary btn-sm" data-page="' . ($page + 1) . '" ' . ($page >= $totalPages ? 'disabled' : '') . '>Next</button>';
    $html .= '</div>';

    return $html;
}

function dashboard_empty_row($columns, $message)
{
    return '<tr><td colspan="' . $columns . '" class="text-center text-muted">' . htmlspecialchars($message) . '</td></tr>';
}

$html = '';
$totalRows = 0;

if ($table === 'loans') {
    $countStmt = $conn->prepare("SELECT COUNT(*) AS total FROM loans WHERE borrower_id = ?");
    $countStmt->bind_param("i", $borrowerId);
    $countStmt->execute();
    $totalRows = (int)$countStmt->get_result()->fetch_assoc()['total'];

    $stmt = $conn->prepare("
        SELECT
            loans.*,
            (SELECT COUNT(*) FROM payments WHERE loan_id = loans.id) AS total_payments,
            (SELECT COUNT(*) FROM payments WHERE loan_id = loans.id AND paid = 1) AS paid_payments
        FROM loans
        WHERE borrower_id = ?
        ORDER BY loans.id DESC
        LIMIT ? OFFSET ?
    ");
    $stmt->bind_param("iii", $borrowerId, $perPage, $offset);
    $stmt->execute();
    $rows = $stmt->get_result();

    if ($totalRows === 0) {
        $html = dashboard_empty_row(7, 'No loan records yet.');
    }

    while ($loan = $rows->fetch_assoc()) {
        $progress = ($loan['total_payments'] > 0)
            ? round(($loan['paid_payments'] / $loan['total_payments']) * 100)
            : 0;
        $badgeClass = $loan['status'] === 'Active' ? 'warning text-dark' : 'success';

        $html .= '<tr>';
        $html .= '<td>#' . $loan['id'] . '</td>';
        $html .= '<td>₱' . number_format($loan['amount'], 2) . '</td>';
        $html .= '<td>₱' . number_format($loan['interest'], 2) . '</td>';
        $html .= '<td><strong>₱' . number_format($loan['total_payable'], 2) . '</strong></td>';
        $html .= '<td width="170"><div class="progress" style="height:20px;"><div class="progress-bar bg-success" style="width:' . $progress . '%">' . $progress . '%</div></div></td>';
        $html .= '<td><span class="badge bg-' . $badgeClass . '">' . htmlspecialchars($loan['status']) . '</span></td>';
        $html .= '<td><a href="member_loan_view.php?id=' . $loan['id'] . '" class="btn btn-info btn-sm w-100">View Details</a></td>';
        $html .= '</tr>';
    }
} elseif ($table === 'loan_requests') {
    $countStmt = $conn->prepare("SELECT COUNT(*) AS total FROM loan_requests WHERE borrower_id = ?");
    $countStmt->bind_param("i", $borrowerId);
    $countStmt->execute();
    $totalRows = (int)$countStmt->get_result()->fetch_assoc()['total'];

    $stmt = $conn->prepare("
        SELECT *
        FROM loan_requests
        WHERE borrower_id = ?
        ORDER BY created_at DESC
        LIMIT ? OFFSET ?
    ");
    $stmt->bind_param("iii", $borrowerId, $perPage, $offset);
    $stmt->execute();
    $rows = $stmt->get_result();

    if ($totalRows === 0) {
        $html = dashboard_empty_row(8, 'No loan requests yet.');
    }

    while ($request = $rows->fetch_assoc()) {
        $badgeClass = dashboard_badge_class($request['status']);
        $action = '<span class="text-muted">—</span>';
        $isGuarantor = (int)($request['is_guarantor'] ?? 0);
        $guestBorrowerName = trim($request['guest_borrower_name'] ?? '');
        $borrowerFor = $isGuarantor
            ? '<span class="badge bg-info text-dark">Guest</span><br><small>' . htmlspecialchars($guestBorrowerName) . '</small>'
            : '<span class="badge bg-secondary">Member</span>';

        if ($request['status'] === 'Pending') {
            $action = '
                <button class="btn btn-warning btn-sm" onclick="openLoanRequestEdit(' . $request['id'] . ', ' . (float)$request['requested_amount'] . ', ' . (float)$request['requested_months'] . ', ' . $isGuarantor . ', ' . htmlspecialchars(json_encode($guestBorrowerName), ENT_QUOTES, 'UTF-8') . ')">Edit</button>
                <button class="btn btn-outline-danger btn-sm" onclick="deleteLoanRequest(' . $request['id'] . ')">Delete</button>
            ';
        } elseif ($request['approved_loan_id']) {
            $action = '<span class="text-muted">Loan #' . $request['approved_loan_id'] . '</span>';
        }

        $html .= '<tr>';
        $html .= '<td>#' . $request['id'] . '</td>';
        $html .= '<td>₱' . number_format($request['requested_amount'], 2) . '</td>';
        $html .= '<td>' . $request['requested_months'] . '</td>';
        $html .= '<td>' . $borrowerFor . '</td>';
        $html .= '<td>' . ($request['approved_amount'] !== null ? '₱' . number_format($request['approved_amount'], 2) : '—') . '</td>';
        $html .= '<td>' . htmlspecialchars($request['created_at']) . '</td>';
        $html .= '<td><span class="badge bg-' . $badgeClass . '">' . htmlspecialchars($request['status']) . '</span></td>';
        $html .= '<td>' . $action . '</td>';
        $html .= '</tr>';
    }
} elseif ($table === 'upcoming_payments') {
    $countStmt = $conn->prepare("
        SELECT COUNT(*) AS total
        FROM payments
        JOIN loans ON loans.id = payments.loan_id
        WHERE loans.borrower_id = ?
        AND payments.paid = 0
    ");
    $countStmt->bind_param("i", $borrowerId);
    $countStmt->execute();
    $totalRows = (int)$countStmt->get_result()->fetch_assoc()['total'];

    $stmt = $conn->prepare("
        SELECT payments.*, loans.id AS loan_id
        FROM payments
        JOIN loans ON loans.id = payments.loan_id
        WHERE loans.borrower_id = ?
        AND payments.paid = 0
        ORDER BY payments.due_date ASC, payments.payment_no ASC
        LIMIT ? OFFSET ?
    ");
    $stmt->bind_param("iii", $borrowerId, $perPage, $offset);
    $stmt->execute();
    $rows = $stmt->get_result();

    if ($totalRows === 0) {
        $html = dashboard_empty_row(4, 'No unpaid payments.');
    }

    while ($payment = $rows->fetch_assoc()) {
        $html .= '<tr>';
        $html .= '<td>#' . $payment['loan_id'] . '</td>';
        $html .= '<td>' . $payment['payment_no'] . '</td>';
        $html .= '<td>₱' . number_format($payment['amount'], 2) . '</td>';
        $html .= '<td>' . htmlspecialchars($payment['due_date']) . '</td>';
        $html .= '</tr>';
    }
} elseif ($table === 'savings_history') {
    $countStmt = $conn->prepare("SELECT COUNT(*) AS total FROM savings_transactions WHERE borrower_id = ?");
    $countStmt->bind_param("i", $borrowerId);
    $countStmt->execute();
    $totalRows = (int)$countStmt->get_result()->fetch_assoc()['total'];

    $stmt = $conn->prepare("
        SELECT
            savings_transactions.*,
            (
                SELECT IFNULL(SUM(CASE WHEN prior_transactions.type = 'DEPOSIT' THEN prior_transactions.amount ELSE -prior_transactions.amount END), 0)
                FROM savings_transactions prior_transactions
                WHERE prior_transactions.borrower_id = savings_transactions.borrower_id
                AND (
                    prior_transactions.transaction_date < savings_transactions.transaction_date
                    OR (
                        prior_transactions.transaction_date = savings_transactions.transaction_date
                        AND prior_transactions.id <= savings_transactions.id
                    )
                )
            ) AS running_balance
        FROM savings_transactions
        WHERE borrower_id = ?
        ORDER BY transaction_date DESC, id DESC
        LIMIT ? OFFSET ?
    ");
    $stmt->bind_param("iii", $borrowerId, $perPage, $offset);
    $stmt->execute();
    $rows = $stmt->get_result();

    if ($totalRows === 0) {
        $html = dashboard_empty_row(6, 'No savings transactions yet.');
    }

    while ($row = $rows->fetch_assoc()) {
        $isDeposit = $row['type'] === 'DEPOSIT';
        $badgeClass = $isDeposit ? 'success' : 'danger';
        $depositAmount = $isDeposit ? '₱' . number_format($row['amount'], 2) : '—';
        $withdrawalAmount = !$isDeposit ? '₱' . number_format($row['amount'], 2) : '—';

        $html .= '<tr>';
        $html .= '<td>' . htmlspecialchars($row['transaction_date']) . '</td>';
        $html .= '<td><span class="badge bg-' . $badgeClass . '">' . htmlspecialchars($row['type']) . '</span></td>';
        $html .= '<td class="text-success">' . $depositAmount . '</td>';
        $html .= '<td class="text-danger">' . $withdrawalAmount . '</td>';
        $html .= '<td><strong>₱' . number_format($row['running_balance'], 2) . '</strong></td>';
        $html .= '<td>' . htmlspecialchars($row['remarks'] ?? '') . '</td>';
        $html .= '</tr>';
    }
} elseif ($table === 'savings_submissions') {
    $countStmt = $conn->prepare("SELECT COUNT(*) AS total FROM savings_submissions WHERE borrower_id = ?");
    $countStmt->bind_param("i", $borrowerId);
    $countStmt->execute();
    $totalRows = (int)$countStmt->get_result()->fetch_assoc()['total'];

    $stmt = $conn->prepare("
        SELECT *
        FROM savings_submissions
        WHERE borrower_id = ?
        ORDER BY created_at DESC
        LIMIT ? OFFSET ?
    ");
    $stmt->bind_param("iii", $borrowerId, $perPage, $offset);
    $stmt->execute();
    $rows = $stmt->get_result();

    if ($totalRows === 0) {
        $html = dashboard_empty_row(5, 'No savings submissions yet.');
    }

    while ($submission = $rows->fetch_assoc()) {
        $badgeClass = dashboard_badge_class($submission['status']);

        $html .= '<tr>';
        $html .= '<td>' . htmlspecialchars($submission['created_at']) . '</td>';
        $html .= '<td>₱' . number_format($submission['amount'], 2) . '</td>';
        $html .= '<td>' . htmlspecialchars($submission['reference_number']) . '</td>';
        $action = '<span class="text-muted">—</span>';

        if ($submission['status'] === 'Pending') {
            $referenceArg = htmlspecialchars(json_encode($submission['reference_number']), ENT_QUOTES);
            $action = '
                <button class="btn btn-warning btn-sm" onclick="openSavingsSubmissionEdit(' . $submission['id'] . ', ' . (float)$submission['amount'] . ', ' . $referenceArg . ')">Edit</button>
                <button class="btn btn-outline-danger btn-sm" onclick="deleteSavingsSubmission(' . $submission['id'] . ')">Delete</button>
            ';
        }

        $html .= '<td><span class="badge bg-' . $badgeClass . '">' . htmlspecialchars($submission['status']) . '</span></td>';
        $html .= '<td>' . $action . '</td>';
        $html .= '</tr>';
    }
} elseif ($table === 'withdrawal_requests') {
    $countStmt = $conn->prepare("SELECT COUNT(*) AS total FROM savings_withdrawal_requests WHERE borrower_id = ?");
    $countStmt->bind_param("i", $borrowerId);
    $countStmt->execute();
    $totalRows = (int)$countStmt->get_result()->fetch_assoc()['total'];

    $stmt = $conn->prepare("
        SELECT *
        FROM savings_withdrawal_requests
        WHERE borrower_id = ?
        ORDER BY created_at DESC
        LIMIT ? OFFSET ?
    ");
    $stmt->bind_param("iii", $borrowerId, $perPage, $offset);
    $stmt->execute();
    $rows = $stmt->get_result();

    if ($totalRows === 0) {
        $html = dashboard_empty_row(8, 'No withdrawal requests yet.');
    }

    while ($request = $rows->fetch_assoc()) {
        $badgeClass = dashboard_badge_class($request['status']);
        $adminReference = $request['admin_reference_number'] ?: '—';

        $html .= '<tr>';
        $html .= '<td>' . htmlspecialchars($request['created_at']) . '</td>';
        $html .= '<td>₱' . number_format($request['amount'], 2) . '</td>';
        $html .= '<td>' . htmlspecialchars($request['gcash_name']) . '</td>';
        $html .= '<td>' . htmlspecialchars($request['gcash_number']) . '</td>';
        $html .= '<td>' . htmlspecialchars($adminReference) . '</td>';
        if (!empty($request['admin_proof_image'])) {
            $html .= '<td><a href="' . htmlspecialchars($request['admin_proof_image']) . '" data-image-preview class="btn btn-outline-primary btn-sm">View File</a></td>';
        } else {
            $html .= '<td><span class="text-muted">—</span></td>';
        }
        $action = '<span class="text-muted">—</span>';

        if ($request['status'] === 'Pending') {
            $gcashNameArg = htmlspecialchars(json_encode($request['gcash_name']), ENT_QUOTES);
            $gcashNumberArg = htmlspecialchars(json_encode($request['gcash_number']), ENT_QUOTES);
            $action = '
                <button class="btn btn-warning btn-sm" onclick="openWithdrawalRequestEdit(' . $request['id'] . ', ' . (float)$request['amount'] . ', ' . $gcashNameArg . ', ' . $gcashNumberArg . ')">Edit</button>
                <button class="btn btn-outline-danger btn-sm" onclick="deleteWithdrawalRequest(' . $request['id'] . ')">Delete</button>
            ';
        }

        $html .= '<td><span class="badge bg-' . $badgeClass . '">' . htmlspecialchars($request['status']) . '</span></td>';
        $html .= '<td>' . $action . '</td>';
        $html .= '</tr>';
    }
} elseif ($table === 'capital_history') {
    $countStmt = $conn->prepare("SELECT COUNT(*) AS total FROM capital_contributions WHERE borrower_id = ?");
    $countStmt->bind_param("i", $borrowerId);
    $countStmt->execute();
    $totalRows = (int)$countStmt->get_result()->fetch_assoc()['total'];

    $stmt = $conn->prepare("
        SELECT *
        FROM capital_contributions
        WHERE borrower_id = ?
        ORDER BY contribution_date DESC, id DESC
        LIMIT ? OFFSET ?
    ");
    $stmt->bind_param("iii", $borrowerId, $perPage, $offset);
    $stmt->execute();
    $rows = $stmt->get_result();

    if ($totalRows === 0) {
        $html = dashboard_empty_row(4, 'No capital contributions yet.');
    }

    while ($row = $rows->fetch_assoc()) {
        $badgeClass = $row['type'] === 'INITIAL' ? 'primary' : 'success';

        $html .= '<tr>';
        $html .= '<td>' . htmlspecialchars($row['contribution_date']) . '</td>';
        $html .= '<td><span class="badge bg-' . $badgeClass . '">' . htmlspecialchars($row['type']) . '</span></td>';
        $html .= '<td>₱' . number_format($row['amount'], 2) . '</td>';
        $html .= '<td>' . htmlspecialchars($row['period_label'] ?? '') . '</td>';
        $html .= '</tr>';
    }
} elseif ($table === 'payment_submissions') {
    $countStmt = $conn->prepare("SELECT COUNT(*) AS total FROM payment_submissions WHERE borrower_id = ?");
    $countStmt->bind_param("i", $borrowerId);
    $countStmt->execute();
    $totalRows = (int)$countStmt->get_result()->fetch_assoc()['total'];

    $stmt = $conn->prepare("
        SELECT *
        FROM payment_submissions
        WHERE borrower_id = ?
        ORDER BY created_at DESC
        LIMIT ? OFFSET ?
    ");
    $stmt->bind_param("iii", $borrowerId, $perPage, $offset);
    $stmt->execute();
    $rows = $stmt->get_result();

    if ($totalRows === 0) {
        $html = dashboard_empty_row(6, 'No payment submissions yet.');
    }

    while ($submission = $rows->fetch_assoc()) {
        $badgeClass = dashboard_badge_class($submission['status']);

        $html .= '<tr>';
        $html .= '<td>' . htmlspecialchars($submission['payment_date']) . '</td>';
        $html .= '<td>' . htmlspecialchars($submission['cutoff_date']) . '</td>';
        $html .= '<td>₱' . number_format($submission['capital_contribution'], 2) . '</td>';
        $html .= '<td>₱' . number_format($submission['loan_payment'], 2) . '</td>';
        $html .= '<td>' . htmlspecialchars($submission['reference_number']) . '</td>';
        $html .= '<td><span class="badge bg-' . $badgeClass . '">' . htmlspecialchars($submission['status']) . '</span></td>';
        $html .= '</tr>';
    }
}

$totalPages = max(1, (int)ceil($totalRows / $perPage));

echo json_encode([
    "html" => $html,
    "pagination" => dashboard_pagination($page, $totalPages),
    "page" => $page,
    "total_pages" => $totalPages,
    "total_rows" => $totalRows
]);
