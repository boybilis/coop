<?php
include '../db.php';
include '../auth.php';
require_member();

$currentUserId = (int)($_SESSION['user_id'] ?? 0);
$selectedUserId = (int)($_POST['selected_user_id'] ?? 0);

if (!$selectedUserId) {
    header("Location: ../member_dashboard.php?error=Invalid account");
    exit;
}

if ($selectedUserId === $currentUserId) {
    $stmt = $conn->prepare("SELECT id, borrower_id FROM users WHERE id = ? AND status = 'Member' LIMIT 1");
    $stmt->bind_param("i", $selectedUserId);
} else {
    $stmt = $conn->prepare("
        SELECT users.id, users.borrower_id
        FROM users
        JOIN user_account_links
            ON user_account_links.linked_user_id = users.id
        WHERE user_account_links.user_id = ?
        AND users.id = ?
        AND users.status = 'Member'
        LIMIT 1
    ");
    $stmt->bind_param("ii", $currentUserId, $selectedUserId);
}

$stmt->execute();
$selectedUser = $stmt->get_result()->fetch_assoc();

if (!$selectedUser || !$selectedUser['borrower_id']) {
    header("Location: ../member_dashboard.php?error=Account is not linked");
    exit;
}

$_SESSION['active_member_user_id'] = $selectedUser['id'];
$_SESSION['active_borrower_id'] = $selectedUser['borrower_id'];

audit_log($conn, 'switch_member_account', 'Member switched active dashboard account.', 'users', $selectedUser['id'], [
    'borrower_id' => $selectedUser['borrower_id']
]);

header("Location: ../member_dashboard.php");
exit;

