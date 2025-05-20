<?php
session_start();
require_once 'db.php';

// Set content type to JSON
header('Content-Type: application/json');

// Check if barcode is provided
if (!isset($_GET['barcode'])) {
    echo json_encode(['success' => false, 'message' => 'No barcode provided']);
    exit;
}

$barcode = $_GET['barcode'];
$store_id = isset($_GET['store_id']) ? intval($_GET['store_id']) : null;

$query = "SELECT p.id, p.name, p.barcode, p.price, i.quantity 
          FROM products p 
          JOIN inventory i ON p.id = i.product_id 
          WHERE p.barcode = ?";

$params = [$barcode];
$types = "s";

if ($store_id) {
    $query .= " AND i.store_id = ?";
    $params[] = $store_id;
    $types .= "i";
}

$stmt = $conn->prepare($query);
$stmt->bind_param($types, ...$params);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows > 0) {
    $product = $result->fetch_assoc();
    echo json_encode([
        'success' => true,
        'product' => [
            'id' => $product['id'],
            'name' => $product['name'],
            'barcode' => $product['barcode'],
            'price' => $product['price'],
            'quantity' => $product['quantity']
        ]
    ]);
} else {
    echo json_encode(['success' => false, 'message' => 'Product not found']);
} 