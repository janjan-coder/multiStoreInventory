<?php
include 'db.php';
$barcode = $_GET['barcode'];
$result = $conn->query("SELECT name FROM products WHERE barcode = '$barcode'");
echo json_encode($result->fetch_assoc());
?>
