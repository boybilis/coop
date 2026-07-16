<?php
include '../db.php';
include '../auth.php';
require_admin();

$id = $_GET['id'];
$conn->query("UPDATE payments SET paid=1 WHERE id=$id");
echo "ok";
