<?php
require_once __DIR__ . '/../db.php';
require_once __DIR__ . '/../auth.php';
require_superadmin();

$monthlyRate = (float)($_POST['monthly_rate'] ?? 0);
$implementationDate = $_POST['implementation_date'] ?? '';
$userId = (int)($_SESSION['user_id'] ?? 0);

if ($monthlyRate < 0 || $implementationDate === '') {
    header("Location: ../system_settings.php?error=" . urlencode("Interest rate and implementation date are required."));
    exit;
}

$date = DateTime::createFromFormat('Y-m-d', $implementationDate);
if (!$date || $date->format('Y-m-d') !== $implementationDate) {
    header("Location: ../system_settings.php?error=" . urlencode("Invalid implementation date."));
    exit;
}

$stmt = $conn->prepare("
    INSERT INTO loan_interest_rates (monthly_rate, implementation_date, created_by)
    VALUES (?, ?, ?)
    ON DUPLICATE KEY UPDATE
        monthly_rate = VALUES(monthly_rate),
        created_by = VALUES(created_by),
        created_at = CURRENT_TIMESTAMP
");
$stmt->bind_param("dsi", $monthlyRate, $implementationDate, $userId);
$stmt->execute();

audit_log($conn, 'save_interest_rate', 'SuperAdmin saved a loan interest rate setting.', 'loan_interest_rates', null, [
    'monthly_rate' => $monthlyRate,
    'implementation_date' => $implementationDate
]);

header("Location: ../system_settings.php?rate_saved=1");
exit;
