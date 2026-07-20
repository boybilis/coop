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
            'service_fee' => "ALTER TABLE loans ADD COLUMN service_fee DECIMAL(12,2) NOT NULL DEFAULT 0.00 AFTER interest",
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

    $usersTableCheck = $conn->query("
        SELECT 1
        FROM INFORMATION_SCHEMA.TABLES
        WHERE TABLE_SCHEMA = DATABASE()
        AND TABLE_NAME = 'users'
        LIMIT 1
    ");

    if ($usersTableCheck && $usersTableCheck->num_rows > 0) {
        $conn->query("ALTER TABLE users MODIFY status ENUM('SuperAdmin','Admin','Member') NOT NULL DEFAULT 'Member'");

        $superAdminCheck = $conn->query("SELECT id FROM users WHERE status = 'SuperAdmin' LIMIT 1");

        if (!$superAdminCheck || $superAdminCheck->num_rows === 0) {
            $conn->query("
                UPDATE users
                SET status = 'SuperAdmin'
                WHERE username = 'admin'
                AND (status = 'Admin' OR status = '' OR status IS NULL)
                LIMIT 1
            ");
        }

        $superAdminCheck = $conn->query("SELECT id FROM users WHERE status = 'SuperAdmin' LIMIT 1");

        if (!$superAdminCheck || $superAdminCheck->num_rows === 0) {
            $conn->query("
                UPDATE users
                SET status = 'SuperAdmin'
                WHERE status = 'Admin'
                ORDER BY id ASC
                LIMIT 1
            ");
        }
    }

    $conn->query("
        CREATE TABLE IF NOT EXISTS audit_trails (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            user_id INT UNSIGNED NULL,
            username VARCHAR(100) NULL,
            user_status VARCHAR(30) NULL,
            action VARCHAR(100) NOT NULL,
            description TEXT NOT NULL,
            entity_type VARCHAR(100) NULL,
            entity_id INT UNSIGNED NULL,
            metadata TEXT NULL,
            ip_address VARCHAR(45) NULL,
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            INDEX audit_trails_user_id_index (user_id),
            INDEX audit_trails_action_index (action),
            INDEX audit_trails_entity_index (entity_type, entity_id),
            INDEX audit_trails_created_at_index (created_at)
        )
    ");

    $conn->query("
        CREATE TABLE IF NOT EXISTS loan_interest_rates (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            monthly_rate DECIMAL(8,4) NOT NULL,
            implementation_date DATE NOT NULL,
            created_by INT UNSIGNED NULL,
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            UNIQUE KEY loan_interest_rates_implementation_unique (implementation_date),
            INDEX loan_interest_rates_date_index (implementation_date)
        )
    ");

    $rateCheck = $conn->query("SELECT id FROM loan_interest_rates LIMIT 1");

    if (!$rateCheck || $rateCheck->num_rows === 0) {
        $conn->query("
            INSERT INTO loan_interest_rates (monthly_rate, implementation_date)
            VALUES (2.0000, '2026-06-30')
        ");
    }

    $conn->query("
        CREATE TABLE IF NOT EXISTS loan_service_fee_rates (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            service_fee_rate DECIMAL(8,4) NOT NULL,
            implementation_date DATE NOT NULL,
            created_by INT UNSIGNED NULL,
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            UNIQUE KEY loan_service_fee_rates_implementation_unique (implementation_date),
            INDEX loan_service_fee_rates_date_index (implementation_date)
        )
    ");

    $serviceFeeRateCheck = $conn->query("SELECT id FROM loan_service_fee_rates LIMIT 1");

    if (!$serviceFeeRateCheck || $serviceFeeRateCheck->num_rows === 0) {
        $conn->query("
            INSERT INTO loan_service_fee_rates (service_fee_rate, implementation_date)
            VALUES (0.0000, '2026-06-30')
        ");
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

function cooperative_effective_interest_rate($conn, $loanDate)
{
    $stmt = $conn->prepare("
        SELECT monthly_rate, implementation_date
        FROM loan_interest_rates
        WHERE implementation_date <= ?
        ORDER BY implementation_date DESC, id DESC
        LIMIT 1
    ");
    $stmt->bind_param("s", $loanDate);
    $stmt->execute();
    $rate = $stmt->get_result()->fetch_assoc();

    if ($rate) {
        return [
            'monthly_rate' => (float)$rate['monthly_rate'],
            'implementation_date' => $rate['implementation_date']
        ];
    }

    $fallback = $conn->query("
        SELECT monthly_rate, implementation_date
        FROM loan_interest_rates
        ORDER BY implementation_date ASC, id ASC
        LIMIT 1
    ")->fetch_assoc();

    return [
        'monthly_rate' => (float)($fallback['monthly_rate'] ?? 2.0000),
        'implementation_date' => $fallback['implementation_date'] ?? '2026-06-30'
    ];
}

function cooperative_effective_service_fee_rate($conn, $loanDate)
{
    $stmt = $conn->prepare("
        SELECT service_fee_rate, implementation_date
        FROM loan_service_fee_rates
        WHERE implementation_date <= ?
        ORDER BY implementation_date DESC, id DESC
        LIMIT 1
    ");
    $stmt->bind_param("s", $loanDate);
    $stmt->execute();
    $rate = $stmt->get_result()->fetch_assoc();

    if ($rate) {
        return [
            'service_fee_rate' => (float)$rate['service_fee_rate'],
            'implementation_date' => $rate['implementation_date']
        ];
    }

    $fallback = $conn->query("
        SELECT service_fee_rate, implementation_date
        FROM loan_service_fee_rates
        ORDER BY implementation_date ASC, id ASC
        LIMIT 1
    ")->fetch_assoc();

    return [
        'service_fee_rate' => (float)($fallback['service_fee_rate'] ?? 0.0000),
        'implementation_date' => $fallback['implementation_date'] ?? '2026-06-30'
    ];
}

function audit_log($conn, $action, $description, $entityType = null, $entityId = null, $metadata = [])
{
    if (!$conn || $conn->connect_error) {
        return;
    }

    $tableCheck = $conn->query("
        SELECT 1
        FROM INFORMATION_SCHEMA.TABLES
        WHERE TABLE_SCHEMA = DATABASE()
        AND TABLE_NAME = 'audit_trails'
        LIMIT 1
    ");

    if (!$tableCheck || $tableCheck->num_rows === 0) {
        return;
    }

    $userId = isset($_SESSION['user_id']) ? (int)$_SESSION['user_id'] : null;
    $username = $_SESSION['username'] ?? null;
    $userStatus = $_SESSION['user_status'] ?? null;
    $metadataJson = $metadata ? json_encode($metadata, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) : null;
    $ipAddress = $_SERVER['REMOTE_ADDR'] ?? null;

    $stmt = $conn->prepare("
        INSERT INTO audit_trails
        (user_id, username, user_status, action, description, entity_type, entity_id, metadata, ip_address)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
    ");
    $stmt->bind_param(
        "isssssiss",
        $userId,
        $username,
        $userStatus,
        $action,
        $description,
        $entityType,
        $entityId,
        $metadataJson,
        $ipAddress
    );
    $stmt->execute();
}

