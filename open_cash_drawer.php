<?php
header('Content-Type: application/json');

// Check if user is logged in
session_start();
if (!isset($_SESSION['username'])) {
    echo json_encode(['success' => false, 'message' => 'Not authorized']);
    exit();
}

// Get store details
require_once 'db.php';
$stmt = $conn->prepare("SELECT * FROM stores WHERE id = ?");
$stmt->bind_param("i", $_SESSION['store_id']);
$stmt->execute();
$store = $stmt->get_result()->fetch_assoc();

if (!$store) {
    echo json_encode(['success' => false, 'message' => 'Store not found']);
    exit();
}

// Check if store has a cash drawer configured
if (empty($store['cash_drawer_port'])) {
    echo json_encode(['success' => false, 'message' => 'Cash drawer not configured for this store']);
    exit();
}

try {
    // Open cash drawer using ESC/POS commands
    $port = $store['cash_drawer_port'];
    
    // ESC/POS command to open drawer (pulse pin 2 for 100ms)
    $command = chr(27) . chr(112) . chr(0) . chr(100) . chr(250);
    
    // Open port
    $fp = fopen($port, 'w+b');
    if (!$fp) {
        throw new Exception("Could not open port $port");
    }
    
    // Send command
    fwrite($fp, $command);
    fclose($fp);
    
    echo json_encode(['success' => true]);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?> 