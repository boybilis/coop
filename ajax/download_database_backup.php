<?php
include __DIR__ . '/../db.php';
include __DIR__ . '/../auth.php';
require_superadmin();

set_time_limit(0);

function sql_identifier($name)
{
    return '`' . str_replace('`', '``', $name) . '`';
}

function sql_value($conn, $value)
{
    if ($value === null) {
        return 'NULL';
    }

    return "'" . $conn->real_escape_string((string)$value) . "'";
}

function output_sql_line($line = '')
{
    echo $line . "\n";
}

$databaseName = $conn->query("SELECT DATABASE() AS db_name")->fetch_assoc()['db_name'] ?? 'database';
$timestamp = date('Ymd_His');
$fileName = preg_replace('/[^A-Za-z0-9_-]/', '_', $databaseName) . "_full_backup_{$timestamp}.sql";

audit_log($conn, 'download_database_backup', 'SuperAdmin downloaded a full database SQL backup.', 'database', null, [
    'database' => $databaseName,
    'filename' => $fileName
]);

while (ob_get_level() > 0) {
    ob_end_clean();
}

header('Content-Type: application/sql; charset=utf-8');
header('Content-Disposition: attachment; filename="' . $fileName . '"');
header('Pragma: no-cache');
header('Expires: 0');

output_sql_line('-- Cooperative Management System Full Database Backup');
output_sql_line('-- Database: ' . $databaseName);
output_sql_line('-- Generated: ' . date('Y-m-d H:i:s'));
output_sql_line();
output_sql_line('SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";');
output_sql_line('SET time_zone = "+00:00";');
output_sql_line('SET FOREIGN_KEY_CHECKS = 0;');
output_sql_line();

$tables = [];
$tableResult = $conn->query("SHOW FULL TABLES WHERE Table_type = 'BASE TABLE'");

while ($table = $tableResult->fetch_array()) {
    $tables[] = $table[0];
}

foreach ($tables as $tableName) {
    $quotedTable = sql_identifier($tableName);
    $createResult = $conn->query("SHOW CREATE TABLE {$quotedTable}");
    $createRow = $createResult->fetch_assoc();
    $createSql = $createRow['Create Table'] ?? '';

    output_sql_line('-- --------------------------------------------------------');
    output_sql_line('-- Table structure for table ' . $quotedTable);
    output_sql_line('-- --------------------------------------------------------');
    output_sql_line();
    output_sql_line('DROP TABLE IF EXISTS ' . $quotedTable . ';');
    output_sql_line($createSql . ';');
    output_sql_line();

    $columns = [];
    $columnResult = $conn->query("SHOW COLUMNS FROM {$quotedTable}");

    while ($column = $columnResult->fetch_assoc()) {
        $columns[] = $column['Field'];
    }

    if (!$columns) {
        continue;
    }

    $columnList = implode(', ', array_map('sql_identifier', $columns));
    $rowResult = $conn->query("SELECT * FROM {$quotedTable}");

    if ($rowResult->num_rows === 0) {
        continue;
    }

    output_sql_line('--');
    output_sql_line('-- Data for table ' . $quotedTable);
    output_sql_line('--');
    output_sql_line();

    while ($row = $rowResult->fetch_assoc()) {
        $values = [];

        foreach ($columns as $columnName) {
            $values[] = sql_value($conn, $row[$columnName]);
        }

        output_sql_line("INSERT INTO {$quotedTable} ({$columnList}) VALUES (" . implode(', ', $values) . ');');
    }

    output_sql_line();
}

output_sql_line('SET FOREIGN_KEY_CHECKS = 1;');
exit;
