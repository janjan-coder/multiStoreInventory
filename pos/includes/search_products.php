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

// Get and validate search parameters
$search = isset($_POST['search']) ? trim($_POST['search']) : '';
$category = isset($_POST['category']) ? trim($_POST['category']) : '';

if (strlen($search) < 2) {
    echo json_encode(['success' => false, 'message' => 'Search term must be at least 2 characters']);
    exit();
}

try {
    // Build the query
    $query = "SELECT p.*, i.quantity 
              FROM products p 
              LEFT JOIN inventory i ON p.id = i.product_id AND i.store_id = ? 
              WHERE p.name LIKE ?";
    $params = [$_SESSION['store_id'], "%$search%"];
    $types = "is";

    // Add category filter if specified
    if (!empty($category)) {
        $query .= " AND p.category_id = ?";
        $params[] = $category;
        $types .= "i";
    }

    $query .= " ORDER BY p.name LIMIT 50";

    // Prepare and execute the query
    $stmt = $conn->prepare($query);
    $stmt->bind_param($types, ...$params);
    $stmt->execute();
    $result = $stmt->get_result();

    $products = [];
    while ($row = $result->fetch_assoc()) {
        // Format the price
        $row['price'] = floatval($row['price']);
        $row['quantity'] = intval($row['quantity']);
        $products[] = $row;
    }

    echo json_encode([
        'success' => true,
        'products' => $products
    ]);

} catch (Exception $e) {
    error_log("Error in search_products.php: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => 'An error occurred while searching products'
    ]);
}
?> 