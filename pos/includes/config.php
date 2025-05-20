<?php
// Database configuration
define('DB_HOST', 'localhost');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_NAME', 'inventory_multistore');

// Create database connection
$conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);

// Check connection
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Set charset to utf8mb4
$conn->set_charset("utf8mb4");

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// POS specific settings
define('SITE_NAME', 'POS System');
define('SITE_URL', 'http://localhost/inventoryMultistore/pos');
define('CURRENCY', '$');
define('TAX_RATE', 0.10); // 10% tax rate

// Function to check if user is logged in
function isLoggedIn() {
    return isset($_SESSION['user_id']);
}

// Function to get user details
function getUserDetails($user_id) {
    global $conn;
    $sql = "SELECT * FROM users WHERE id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    return $stmt->get_result()->fetch_assoc();
}

// Function to get store details
function getStoreDetails($store_id) {
    global $conn;
    $query = "SELECT * FROM stores WHERE id = ?";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("i", $store_id);
    $stmt->execute();
    $result = $stmt->get_result();
    return $result->fetch_assoc();
}

// Function to get product details with current price
function getProductDetails($product_id, $store_id) {
    global $conn;
    $sql = "SELECT p.*, c.name as category_name,
            (SELECT price FROM product_prices WHERE product_id = p.id ORDER BY effective_date DESC LIMIT 1) as price,
            (SELECT quantity FROM inventory WHERE product_id = p.id AND store_id = ?) as stock_quantity
            FROM products p
            LEFT JOIN categories c ON p.category_id = c.id
            WHERE p.id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("ii", $store_id, $product_id);
    $stmt->execute();
    return $stmt->get_result()->fetch_assoc();
}

// Function to search products
function searchProducts($search_term, $store_id, $category_id = null) {
    global $conn;
    $search_term = "%$search_term%";
    
    $sql = "SELECT p.*, c.name as category_name,
            (SELECT price FROM product_prices WHERE product_id = p.id ORDER BY effective_date DESC LIMIT 1) as price,
            (SELECT quantity FROM inventory WHERE product_id = p.id AND store_id = ?) as stock_quantity
            FROM products p
            LEFT JOIN categories c ON p.category_id = c.id
            WHERE (p.name LIKE ? OR p.sku LIKE ?)
            AND EXISTS (SELECT 1 FROM inventory WHERE product_id = p.id AND store_id = ? AND quantity > 0)";
    
    if ($category_id) {
        $sql .= " AND p.category_id = ?";
    }
    
    $sql .= " ORDER BY p.name LIMIT 50";
    
    $stmt = $conn->prepare($sql);
    if ($category_id) {
        $stmt->bind_param("isssi", $store_id, $search_term, $search_term, $store_id, $category_id);
    } else {
        $stmt->bind_param("isss", $store_id, $search_term, $search_term, $store_id);
    }
    
    $stmt->execute();
    return $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
}

// Function to create a new sale
function createSale($store_id, $user_id, $items, $payment_method, $customer_id = null) {
    global $conn;
    
    try {
        $conn->begin_transaction();
        
        // Create sale record
        $sql = "INSERT INTO sales (store_id, user_id, customer_id, payment_method, total_amount, tax_amount, status)
                VALUES (?, ?, ?, ?, ?, ?, 'completed')";
        $stmt = $conn->prepare($sql);
        
        $total_amount = 0;
        foreach ($items as $item) {
            $total_amount += $item['price'] * $item['quantity'];
        }
        $tax_amount = $total_amount * TAX_RATE;
        
        $stmt->bind_param("iiisdd", $store_id, $user_id, $customer_id, $payment_method, $total_amount, $tax_amount);
        $stmt->execute();
        $sale_id = $conn->insert_id;
        
        // Add sale items
        $sql = "INSERT INTO sale_items (sale_id, product_id, quantity, price) VALUES (?, ?, ?, ?)";
        $stmt = $conn->prepare($sql);
        
        foreach ($items as $item) {
            $stmt->bind_param("iiid", $sale_id, $item['product_id'], $item['quantity'], $item['price']);
            $stmt->execute();
            
            // Update inventory
            $sql = "UPDATE inventory SET quantity = quantity - ? WHERE product_id = ? AND store_id = ?";
            $update_stmt = $conn->prepare($sql);
            $update_stmt->bind_param("iii", $item['quantity'], $item['product_id'], $store_id);
            $update_stmt->execute();
        }
        
        $conn->commit();
        return $sale_id;
        
    } catch (Exception $e) {
        $conn->rollback();
        throw $e;
    }
}

// Function to format price
function formatPrice($price) {
    return CURRENCY . number_format($price, 2);
}

// Function to sanitize input
function sanitize($input) {
    global $conn;
    return $conn->real_escape_string(trim($input));
}

// Function to handle errors
function handleError($message, $redirect = null) {
    $_SESSION['error'] = $message;
    if ($redirect) {
        header("Location: $redirect");
        exit();
    }
}

// Function to handle success messages
function handleSuccess($message, $redirect = null) {
    $_SESSION['success'] = $message;
    if ($redirect) {
        header("Location: $redirect");
        exit();
    }
}
?> 