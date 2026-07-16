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

}
?>
