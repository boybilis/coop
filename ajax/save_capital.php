<?php
include '../db.php';
include '../auth.php';
require_admin();

header('Content-Type: application/json');

$borrower_id = (int)($_POST['borrower_id'] ?? 0);
$amount = (float)($_POST['amount'] ?? 0);
$type = $_POST['type'] ?? '';
$date = $_POST['date'] ?? '';

if (!$borrower_id || $amount <= 0 || !in_array($type, ['INITIAL', 'CUTOFF'], true) || !$date) {
    echo json_encode(["error" => "Please complete all capital contribution fields."]);
    exit;
}

$stmt = $conn->prepare("
INSERT INTO capital_contributions 
(borrower_id, amount, type, contribution_date)
VALUES (?, ?, ?, ?)
");

$stmt->bind_param("idss", $borrower_id, $amount, $type, $date);
$stmt->execute();

$summary = $conn->query("
    SELECT
        IFNULL(SUM(amount),0) AS total,
        COUNT(DISTINCT borrower_id) AS member_count
    FROM capital_contributions
")->fetch_assoc();

$memberStmt = $conn->prepare("
    SELECT borrowers.name, users.username
    FROM borrowers
    LEFT JOIN users ON users.borrower_id = borrowers.id AND users.status = 'Member'
    WHERE borrowers.id = ?
    LIMIT 1
");
$memberStmt->bind_param("i", $borrower_id);
$memberStmt->execute();
$member = $memberStmt->get_result()->fetch_assoc();

$total = (float)$summary['total'];
$memberCount = (int)$summary['member_count'];
$average = $memberCount > 0 ? $total / $memberCount : 0;

echo json_encode([
    "ok" => true,
    "message" => "Capital Contribution Recording Successful.",
    "row" => [
        "name" => $member['name'] ?? '',
        "username" => $member['username'] ?? '',
        "amount" => $amount,
        "type" => $type,
        "date" => $date
    ],
    "summary" => [
        "total" => $total,
        "average" => $average
    ]
]);

