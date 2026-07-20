<?php
include '../db.php';
include '../auth.php';
require_admin();

$id = $_GET['id'];
$conn->query("UPDATE payments SET paid=1 WHERE id=$id");
audit_log($conn, 'mark_payment_paid', 'Admin manually marked payment paid.', 'payments', (int)$id);
echo "ok";

