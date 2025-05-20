<?php
require_once 'db.php';

header('Content-Type: application/json');

if (!isset($_GET['barcode']) || !isset($_GET['store_id'])) {
    echo json_encode(['success' => false, 'message' => 'Missing required parameters']);
    exit;
}

$barcode = trim($_GET['barcode']);
$store_id = intval($_GET['store_id']);

// Get available sizes and quantities for the product
$stmt = $conn->prepare("SELECT i.size, i.quantity 
                       FROM products p 
                       JOIN inventory i ON p.id = i.product_id 
                       WHERE p.barcode = ? AND i.store_id = ? AND i.quantity > 0 
                       ORDER BY i.size");
$stmt->bind_param("si", $barcode, $store_id);
$stmt->execute();
$result = $stmt->get_result();

$sizes = [];
while ($row = $result->fetch_assoc()) {
    $sizes[] = [
        'size' => $row['size'],
        'quantity' => $row['quantity']
    ];
}

echo json_encode([
    'success' => true,
    'sizes' => $sizes
]); 