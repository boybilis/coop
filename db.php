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
            'disbursement_reference_number' => "ALTER TABLE loan_requests ADD COLUMN disbursement_reference_number VARCHAR(100) DEFAULT NULL AFTER guest_borrower_name",
            'disbursement_proof_image' => "ALTER TABLE loan_requests ADD COLUMN disbursement_proof_image VARCHAR(255) DEFAULT NULL AFTER disbursement_reference_number"
        ],
        'loans' => [
            'is_guarantor' => "ALTER TABLE loans ADD COLUMN is_guarantor TINYINT(1) NOT NULL DEFAULT 0 AFTER status",
            'guest_borrower_name' => "ALTER TABLE loans ADD COLUMN guest_borrower_name VARCHAR(150) DEFAULT NULL AFTER is_guarantor",
            'disbursement_reference_number' => "ALTER TABLE loans ADD COLUMN disbursement_reference_number VARCHAR(100) DEFAULT NULL AFTER guest_borrower_name",
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
?>
