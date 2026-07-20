<?php
include '../db.php';
include '../auth.php';
require_superadmin();

$paymentType = $_POST['payment_type'] ?? '';
$implementationDate = $_POST['implementation_date'] ?? '';
$monthlyDay = isset($_POST['monthly_day']) ? (int)$_POST['monthly_day'] : null;
$semiMonthlyDayOne = isset($_POST['semi_monthly_day_one']) ? (int)$_POST['semi_monthly_day_one'] : null;
$semiMonthlyDayTwo = isset($_POST['semi_monthly_day_two']) ? (int)$_POST['semi_monthly_day_two'] : null;
$weeklyDay = isset($_POST['weekly_day']) ? (int)$_POST['weekly_day'] : null;

if (!in_array($paymentType, ['monthly', 'semi_monthly', 'weekly'], true) || !$implementationDate) {
    header("Location: ../system_settings.php?error=" . urlencode("Payment type and implementation date are required."));
    exit;
}

$date = DateTime::createFromFormat('Y-m-d', $implementationDate);

if (!$date || $date->format('Y-m-d') !== $implementationDate) {
    header("Location: ../system_settings.php?error=" . urlencode("Invalid implementation date."));
    exit;
}

if ($paymentType === 'monthly') {
    if ($monthlyDay < 1 || $monthlyDay > 31) {
        header("Location: ../system_settings.php?error=" . urlencode("Monthly cut-off day must be from 1 to 31."));
        exit;
    }

    $semiMonthlyDayOne = null;
    $semiMonthlyDayTwo = null;
    $weeklyDay = null;
} elseif ($paymentType === 'semi_monthly') {
    if ($semiMonthlyDayOne < 1 || $semiMonthlyDayOne > 31 || $semiMonthlyDayTwo < 1 || $semiMonthlyDayTwo > 31) {
        header("Location: ../system_settings.php?error=" . urlencode("Semi-monthly cut-off days must be from 1 to 31."));
        exit;
    }

    if ($semiMonthlyDayOne === $semiMonthlyDayTwo) {
        header("Location: ../system_settings.php?error=" . urlencode("Semi-monthly cut-off days must be different."));
        exit;
    }

    $monthlyDay = null;
    $weeklyDay = null;
} else {
    if ($weeklyDay < 1 || $weeklyDay > 7) {
        header("Location: ../system_settings.php?error=" . urlencode("Weekly cut-off day is required."));
        exit;
    }

    $monthlyDay = null;
    $semiMonthlyDayOne = null;
    $semiMonthlyDayTwo = null;
}

$createdBy = $_SESSION['user_id'] ?? null;

$stmt = $conn->prepare("
    INSERT INTO loan_payment_schedule_settings
    (payment_type, monthly_day, semi_monthly_day_one, semi_monthly_day_two, weekly_day, implementation_date, created_by)
    VALUES (?, ?, ?, ?, ?, ?, ?)
    ON DUPLICATE KEY UPDATE
        payment_type = VALUES(payment_type),
        monthly_day = VALUES(monthly_day),
        semi_monthly_day_one = VALUES(semi_monthly_day_one),
        semi_monthly_day_two = VALUES(semi_monthly_day_two),
        weekly_day = VALUES(weekly_day),
        created_by = VALUES(created_by),
        created_at = CURRENT_TIMESTAMP
");
$stmt->bind_param(
    "siiiisi",
    $paymentType,
    $monthlyDay,
    $semiMonthlyDayOne,
    $semiMonthlyDayTwo,
    $weeklyDay,
    $implementationDate,
    $createdBy
);
$stmt->execute();

audit_log($conn, 'save_payment_schedule_setting', 'SuperAdmin saved a loan payment schedule setting.', 'loan_payment_schedule_settings', null, [
    'payment_type' => $paymentType,
    'monthly_day' => $monthlyDay,
    'semi_monthly_day_one' => $semiMonthlyDayOne,
    'semi_monthly_day_two' => $semiMonthlyDayTwo,
    'weekly_day' => $weeklyDay,
    'implementation_date' => $implementationDate
]);

header("Location: ../system_settings.php?payment_schedule_saved=1");
exit;
