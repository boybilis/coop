<?php
include '../db.php';
include '../auth.php';
require_member();

header('Content-Type: application/json');

$selectedMemberUserId = (int)($_POST['selected_member_user_id'] ?? active_member_user_id());
$borrowerId = member_borrower_id_for_user($conn, $selectedMemberUserId);
$username = trim($_POST['username'] ?? '');
$firstName = trim($_POST['first_name'] ?? '');
$lastName = trim($_POST['last_name'] ?? '');
$gcashName = trim($_POST['gcash_name'] ?? '');
$gcashNumber = trim($_POST['gcash_number'] ?? '');

if (!$borrowerId) {
    echo json_encode(["error" => "Selected account is invalid"]);
    exit;
}

if ($username === '' || $firstName === '' || $lastName === '') {
    echo json_encode(["error" => "Username, first name, and last name are required"]);
    exit;
}

$fullName = trim($firstName . ' ' . $lastName);

$nameCheck = $conn->prepare("
    SELECT id
    FROM borrowers
    WHERE LOWER(name) = LOWER(?)
    AND id != ?
    LIMIT 1
");
$nameCheck->bind_param("si", $fullName, $borrowerId);
$nameCheck->execute();

if ($nameCheck->get_result()->num_rows > 0) {
    echo json_encode(["error" => "Member name is already used by another account"]);
    exit;
}

$usernameCheck = $conn->prepare("
    SELECT id
    FROM users
    WHERE LOWER(username) = LOWER(?)
    AND id != ?
    LIMIT 1
");
$usernameCheck->bind_param("si", $username, $selectedMemberUserId);
$usernameCheck->execute();

if ($usernameCheck->get_result()->num_rows > 0) {
    echo json_encode(["error" => "Username is already used by another account"]);
    exit;
}

$conn->begin_transaction();

try {
    $borrowerStmt = $conn->prepare("
        UPDATE borrowers
        SET name = ?,
            first_name = ?,
            last_name = ?,
            gcash_name = ?,
            gcash_number = ?
        WHERE id = ?
    ");
    $borrowerStmt->bind_param("sssssi", $fullName, $firstName, $lastName, $gcashName, $gcashNumber, $borrowerId);
    $borrowerStmt->execute();

    $userStmt = $conn->prepare("
        UPDATE users
        SET username = ?
        WHERE id = ?
        AND borrower_id = ?
        AND status = 'Member'
    ");
    $userStmt->bind_param("sii", $username, $selectedMemberUserId, $borrowerId);
    $userStmt->execute();

    $_SESSION['active_borrower_id'] = $borrowerId;

    if ((int)($_SESSION['user_id'] ?? 0) === $selectedMemberUserId) {
        $_SESSION['username'] = $username;
        $_SESSION['borrower_id'] = $borrowerId;
    }

    $conn->commit();
} catch (Throwable $e) {
    $conn->rollback();
    echo json_encode(["error" => "Unable to update profile"]);
    exit;
}

echo json_encode([
    "ok" => true,
    "message" => "Profile updated successfully.",
    "profile" => [
        "username" => $username,
        "name" => $fullName,
        "first_name" => $firstName,
        "last_name" => $lastName,
        "gcash_name" => $gcashName,
        "gcash_number" => $gcashNumber
    ]
]);

