<?php
include '../db.php';
include '../auth.php';
require_admin();

if(isset($_POST['name'])){

    $name = trim($_POST['name']);
    $name = $conn->real_escape_string($name);

    if($name == ''){
        echo json_encode(["error" => "Name is required"]);
        exit;
    }

    // ✅ CHECK DUPLICATE (case-insensitive)
    $check = $conn->query("SELECT id FROM borrowers WHERE LOWER(name) = LOWER('$name')");

    if($check->num_rows > 0){
        echo json_encode(["error" => "Member already exists"]);
        exit;
    }

    // ✅ INSERT
    $conn->query("INSERT INTO borrowers(name) VALUES('$name')");
    $id = $conn->insert_id;

    $userStmt = $conn->prepare("
        INSERT INTO users (username, password, status, borrower_id)
        VALUES (?, '', 'Member', ?)
    ");
    $userStmt->bind_param("si", $name, $id);
    $userStmt->execute();

    echo json_encode([
        "id" => $id,
        "name" => $name
    ]);
}
?>

