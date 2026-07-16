<?php
$conn = new mysqli("localhost", "root", "", "loan_db_repaired");

if (!$conn->connect_error) {
    $borrowersTableCheck = $conn->query("
        SELECT 1
        FROM INFORMATION_SCHEMA.TABLES
        WHERE TABLE_SCHEMA = DATABASE()
        AND TABLE_NAME = 'borrowers'
        LIMIT 1
    ");

    $savingsTableCheck = $conn->query("
        SELECT 1
        FROM INFORMATION_SCHEMA.TABLES
        WHERE TABLE_SCHEMA = DATABASE()
        AND TABLE_NAME = 'savings_transactions'
        LIMIT 1
    ");

    $columnCheck = $conn->query("
        SELECT 1
        FROM INFORMATION_SCHEMA.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE()
        AND TABLE_NAME = 'borrowers'
        AND COLUMN_NAME = 'savings_closed'
        LIMIT 1
    ");

    if ($borrowersTableCheck && $borrowersTableCheck->num_rows > 0 && $columnCheck && $columnCheck->num_rows === 0) {
        $conn->query("ALTER TABLE borrowers ADD COLUMN savings_closed TINYINT(1) NOT NULL DEFAULT 0 AFTER status");
    }

    if (
        $borrowersTableCheck && $borrowersTableCheck->num_rows > 0 &&
        $savingsTableCheck && $savingsTableCheck->num_rows > 0
    ) {
        $conn->query("
            UPDATE borrowers
            JOIN (
                SELECT
                    borrower_id,
                    IFNULL(SUM(CASE WHEN type = 'DEPOSIT' THEN amount ELSE 0 END), 0) AS deposits,
                    IFNULL(SUM(CASE WHEN type = 'WITHDRAWAL' THEN amount ELSE 0 END), 0) AS withdrawals
                FROM savings_transactions
                GROUP BY borrower_id
            ) AS savings_summary
                ON savings_summary.borrower_id = borrowers.id
            SET borrowers.savings_closed = 1
            WHERE borrowers.savings_closed = 0
            AND savings_summary.withdrawals > 0
            AND savings_summary.deposits - savings_summary.withdrawals <= 0
        ");
    }
}
?>
