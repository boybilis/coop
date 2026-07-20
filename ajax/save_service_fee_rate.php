<?php
require_once __DIR__ . '/../db.php';
require_once __DIR__ . '/../auth.php';
require_superadmin();

$serviceFeeRate = (float)($_POST['service_fee_rate'] ?? 0);
$implementationDate = $_POST['implementation_date'] ?? '';
$userId = (int)($_SESSION['user_id'] ?? 0);

if ($serviceFeeRate < 0 || $implementationDate === '') {
    header("Location: ../system_settings.php?error=" . urlencode("Service fee rate and implementation date are required."));
    exit;
}

$date = DateTime::createFromFormat('Y-m-d', $implementationDate);
if (!$date || $date->format('Y-m-d') !== $implementationDate) {
    header("Location: ../system_settings.php?error=" . urlencode("Invalid implementation date."));
    exit;
}

$stmt = $conn->prepare("
    INSERT INTO loan_service_fee_rates (service_fee_rate, implementation_date, created_by)
    VALUES (?, ?, ?)
    ON DUPLICATE KEY UPDATE
        service_fee_rate = VALUES(service_fee_rate),
        created_by = VALUES(created_by),
        created_at = CURRENT_TIMESTAMP
");
$stmt->bind_param("dsi", $serviceFeeRate, $implementationDate, $userId);
$stmt->execute();

audit_log($conn, 'save_service_fee_rate', 'SuperAdmin saved a loan service fee rate setting.', 'loan_service_fee_rates', null, [
    'service_fee_rate' => $serviceFeeRate,
    'implementation_date' => $implementationDate
]);

header("Location: ../system_settings.php?service_fee_saved=1");
exit;
