<?php
require_once 'db.php';

header('Content-Type: application/json');

if (!isset($_GET['barcode']) || !isset($_GET['store_id']) || !isset($_GET['size'])) {
    echo json_encode(['success' => false, 'message' => 'Missing required parameters']);
    exit;
}

$barcode = trim($_GET['barcode']);
$store_id = intval($_GET['store_id']);
$size = trim($_GET['size']);

// Get stock quantity for the specific product and size
$stmt = $conn->prepare("SELECT i.quantity 
                       FROM products p 
                       JOIN inventory i ON p.id = i.product_id 
                       WHERE p.barcode = ? AND i.store_id = ? AND i.size = ?");
$stmt->bind_param("sis", $barcode, $store_id, $size);
$stmt->execute();
$result = $stmt->get_result();

if ($row = $result->fetch_assoc()) {
    echo json_encode([
        'success' => true,
        'quantity' => $row['quantity']
    ]);
} else {
    echo json_encode([
        'success' => false,
        'message' => 'Product not found'
    ]);
} 