<?php
include '../db.php';
include '../auth.php';
require_admin();

$borrower_id = (int)($_POST['borrower_id'] ?? 0);
$amount = (float)($_POST['amount'] ?? 0);
$type = $_POST['type'] ?? 'DEPOSIT';
$date = $_POST['date'] ?? '';
$remarks = trim($_POST['remarks'] ?? '');

if(!$borrower_id || $amount <= 0 || !$date){
    exit("Missing fields");
}

if(!in_array($type, ['DEPOSIT', 'WITHDRAWAL'], true)){
    exit("Invalid transaction type");
}

$stmt = $conn->prepare("
    INSERT INTO savings_transactions
    (borrower_id, amount, type, transaction_date, remarks)
    VALUES (?, ?, ?, ?, ?)
");

$stmt->bind_param("idsss", $borrower_id, $amount, $type, $date, $remarks);
$stmt->execute();

header("Location: ../savings.php");
exit;

