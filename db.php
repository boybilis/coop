<?php
$dbHost = "localhost";
$dbUser = "root";
$dbPassword = "";
$dbName = "loan_db_repaired";

$localConfig = __DIR__ . '/db_config.php';

if (file_exists($localConfig)) {
    $config = require $localConfig;

    $dbHost = $config['host'] ?? $dbHost;
    $dbUser = $config['user'] ?? $dbUser;
    $dbPassword = $config['password'] ?? $dbPassword;
    $dbName = $config['database'] ?? $dbName;
}

$conn = new mysqli($dbHost, $dbUser, $dbPassword, $dbName);

if (!$conn->connect_error) {
    $borrowersTableCheck = $conn->query("
        SELECT 1
        FROM INFORMATION_SCHEMA.TABLES
        WHERE TABLE_SCHEMA = DATABASE()
        AND TABLE_NAME = 'borrowers'
        LIMIT 1
    ");

    if ($borrowersTableCheck && $borrowersTableCheck->num_rows > 0) {
        $borrowerColumns = [
            'savings_closed' => "ALTER TABLE borrowers ADD COLUMN savings_closed TINYINT(1) NOT NULL DEFAULT 0 AFTER status",
            'first_name' => "ALTER TABLE borrowers ADD COLUMN first_name VARCHAR(100) DEFAULT NULL AFTER name",
            'last_name' => "ALTER TABLE borrowers ADD COLUMN last_name VARCHAR(100) DEFAULT NULL AFTER first_name",
            'gcash_name' => "ALTER TABLE borrowers ADD COLUMN gcash_name VARCHAR(150) DEFAULT NULL AFTER last_name",
            'gcash_number' => "ALTER TABLE borrowers ADD COLUMN gcash_number VARCHAR(50) DEFAULT NULL AFTER gcash_name"
        ];

        foreach ($borrowerColumns as $columnName => $alterSql) {
            $columnCheck = $conn->query("
                SELECT 1
                FROM INFORMATION_SCHEMA.COLUMNS
                WHERE TABLE_SCHEMA = DATABASE()
                AND TABLE_NAME = 'borrowers'
                AND COLUMN_NAME = '{$columnName}'
                LIMIT 1
            ");

            if ($columnCheck && $columnCheck->num_rows === 0) {
                $conn->query($alterSql);
            }
        }
    }

    $loanSchemaColumns = [
        'loan_requests' => [
            'is_guarantor' => "ALTER TABLE loan_requests ADD COLUMN is_guarantor TINYINT(1) NOT NULL DEFAULT 0 AFTER approved_loan_id",
            'guest_borrower_name' => "ALTER TABLE loan_requests ADD COLUMN guest_borrower_name VARCHAR(150) DEFAULT NULL AFTER is_guarantor",
            'guest_gcash_name' => "ALTER TABLE loan_requests ADD COLUMN guest_gcash_name VARCHAR(150) DEFAULT NULL AFTER guest_borrower_name",
            'guest_gcash_number' => "ALTER TABLE loan_requests ADD COLUMN guest_gcash_number VARCHAR(50) DEFAULT NULL AFTER guest_gcash_name",
            'disbursement_reference_number' => "ALTER TABLE loan_requests ADD COLUMN disbursement_reference_number VARCHAR(100) DEFAULT NULL AFTER guest_gcash_number",
            'disbursement_proof_image' => "ALTER TABLE loan_requests ADD COLUMN disbursement_proof_image VARCHAR(255) DEFAULT NULL AFTER disbursement_reference_number"
        ],
        'loans' => [
            'is_guarantor' => "ALTER TABLE loans ADD COLUMN is_guarantor TINYINT(1) NOT NULL DEFAULT 0 AFTER status",
            'guest_borrower_name' => "ALTER TABLE loans ADD COLUMN guest_borrower_name VARCHAR(150) DEFAULT NULL AFTER is_guarantor",
            'guest_gcash_name' => "ALTER TABLE loans ADD COLUMN guest_gcash_name VARCHAR(150) DEFAULT NULL AFTER guest_borrower_name",
            'guest_gcash_number' => "ALTER TABLE loans ADD COLUMN guest_gcash_number VARCHAR(50) DEFAULT NULL AFTER guest_gcash_name",
            'disbursement_reference_number' => "ALTER TABLE loans ADD COLUMN disbursement_reference_number VARCHAR(100) DEFAULT NULL AFTER guest_gcash_number",
            'disbursement_proof_image' => "ALTER TABLE loans ADD COLUMN disbursement_proof_image VARCHAR(255) DEFAULT NULL AFTER disbursement_reference_number"
        ]
    ];

    foreach ($loanSchemaColumns as $tableName => $columns) {
        $tableCheck = $conn->query("
            SELECT 1
            FROM INFORMATION_SCHEMA.TABLES
            WHERE TABLE_SCHEMA = DATABASE()
            AND TABLE_NAME = '{$tableName}'
            LIMIT 1
        ");

        if (!$tableCheck || $tableCheck->num_rows === 0) {
            continue;
        }

        foreach ($columns as $columnName => $alterSql) {
            $columnCheck = $conn->query("
                SELECT 1
                FROM INFORMATION_SCHEMA.COLUMNS
                WHERE TABLE_SCHEMA = DATABASE()
                AND TABLE_NAME = '{$tableName}'
                AND COLUMN_NAME = '{$columnName}'
                LIMIT 1
            ");

            if ($columnCheck && $columnCheck->num_rows === 0) {
                $conn->query($alterSql);
            }
        }
    }

}

function cooperative_current_cutoff_date()
{
    $today = new DateTimeImmutable('today');
    $day = (int)$today->format('j');
    $lastDay = (int)$today->format('t');

    if ($day >= $lastDay) {
        return $today->format('Y-m-t');
    }

    if ($day >= 15) {
        return $today->format('Y-m-15');
    }

    return $today->modify('first day of previous month')->format('Y-m-t');
}

function cooperative_loanable_amount_breakdown($conn)
{
    $currentCutoffDate = cooperative_current_cutoff_date();

    $initialCapital = (float)$conn->query("
        SELECT IFNULL(SUM(amount),0) AS total
        FROM capital_contributions
        WHERE type = 'INITIAL'
    ")->fetch_assoc()['total'];

    $cutoffCapitalStmt = $conn->prepare("
        SELECT IFNULL(SUM(amount),0) AS total
        FROM capital_contributions
        WHERE type = 'CUTOFF'
        AND contribution_date <= ?
    ");
    $cutoffCapitalStmt->bind_param("s", $currentCutoffDate);
    $cutoffCapitalStmt->execute();
    $cutoffCapitalToDate = (float)$cutoffCapitalStmt->get_result()->fetch_assoc()['total'];

    $cutoffPaidLoansStmt = $conn->prepare("
        SELECT IFNULL(SUM(payments.amount),0) AS total
        FROM payments
        WHERE payments.due_date = ?
        AND payments.paid = 1
    ");
    $cutoffPaidLoansStmt->bind_param("s", $currentCutoffDate);
    $cutoffPaidLoansStmt->execute();
    $paidLoansThisCutoff = (float)$cutoffPaidLoansStmt->get_result()->fetch_assoc()['total'];

    $approvedLoanPrincipal = (float)$conn->query("
        SELECT IFNULL(SUM(amount),0) AS total
        FROM loans
    ")->fetch_assoc()['total'];

    return [
        'cutoff_date' => $currentCutoffDate,
        'initial_capital' => $initialCapital,
        'cutoff_capital_to_date' => $cutoffCapitalToDate,
        'paid_loans_this_cutoff' => $paidLoansThisCutoff,
        'approved_loan_principal' => $approvedLoanPrincipal,
        'available_amount' => $initialCapital + $cutoffCapitalToDate + $paidLoansThisCutoff - $approvedLoanPrincipal
    ];
}

