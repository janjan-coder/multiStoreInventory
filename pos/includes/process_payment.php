<?php
session_start();
require_once '../../db.php';

// Check if user is logged in
if (!isset($_SESSION['username'])) {
    echo json_encode(['success' => false, 'message' => 'Please log in to continue']);
    exit();
}

// Check if store_id is set
if (!isset($_SESSION['store_id'])) {
    echo json_encode(['success' => false, 'message' => 'No store assigned to your account']);
    exit();
}

// Get JSON data from request
$json = file_get_contents('php://input');
$data = json_decode($json, true);

// Validate required fields
if (!isset($data['items']) || !isset($data['payment_method']) || !isset($data['amount_received'])) {
    echo json_encode(['success' => false, 'message' => 'Missing required fields']);
    exit();
}

// Validate items array
if (empty($data['items'])) {
    echo json_encode(['success' => false, 'message' => 'Cart is empty']);
    exit();
}

try {
    // Start transaction
    $conn->begin_transaction();

    // Create sale record
    $stmt = $conn->prepare("INSERT INTO sales (store_id, user_id, payment_method, total_amount, tax_amount, status, created_at) 
                           VALUES (?, ?, ?, ?, ?, 'completed', NOW())");
    
    $store_id = $_SESSION['store_id'];
    $user_id = $_SESSION['user_id'];
    $payment_method = $data['payment_method'];
    $total_amount = $data['total'];
    $tax_amount = $total_amount * 0.1; // 10% tax

    $stmt->bind_param("iisdd", $store_id, $user_id, $payment_method, $total_amount, $tax_amount);
    $stmt->execute();
    $sale_id = $conn->insert_id;

    // Insert sale items and update inventory
    $stmt = $conn->prepare("INSERT INTO sale_items (sale_id, product_id, quantity, price) VALUES (?, ?, ?, ?)");
    $update_inventory = $conn->prepare("UPDATE inventory SET quantity = quantity - ? WHERE product_id = ? AND store_id = ?");

    foreach ($data['items'] as $item) {
        // Validate item data
        if (!isset($item['id']) || !isset($item['quantity']) || !isset($item['price'])) {
            throw new Exception('Invalid item data');
        }

        // Check stock availability
        $check_stock = $conn->prepare("SELECT quantity FROM inventory WHERE product_id = ? AND store_id = ?");
        $check_stock->bind_param("ii", $item['id'], $store_id);
        $check_stock->execute();
        $stock_result = $check_stock->get_result();
        $stock = $stock_result->fetch_assoc();

        if (!$stock || $stock['quantity'] < $item['quantity']) {
            throw new Exception('Insufficient stock for one or more items');
        }

        // Insert sale item
        $stmt->bind_param("iiid", $sale_id, $item['id'], $item['quantity'], $item['price']);
        $stmt->execute();

        // Update inventory
        $update_inventory->bind_param("iii", $item['quantity'], $item['id'], $store_id);
        $update_inventory->execute();
    }

    // Commit transaction
    $conn->commit();

    echo json_encode([
        'success' => true,
        'message' => 'Payment processed successfully',
        'sale_id' => $sale_id
    ]);

} catch (Exception $e) {
    // Rollback transaction on error
    $conn->rollback();
    error_log("Error in process_payment.php: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => 'An error occurred while processing payment'
    ]);
}
?> 