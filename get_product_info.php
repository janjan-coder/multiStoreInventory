<?php
include 'db.php';

$barcode = $_GET['barcode'] ?? '';
$barcode = $conn->real_escape_string(trim($barcode));

$response = ['found' => false];

$query = "SELECT product_name, size FROM products WHERE barcode = '$barcode' LIMIT 1";
$result = $conn->query($query);

if ($result->num_rows > 0) {
    $product = $result->fetch_assoc();
    $response['found'] = true;
    $response['product_name'] = $product['product_name'];
    $response['size'] = $product['size'];
}

header('Content-Type: application/json');
echo json_encode($response);
?>
