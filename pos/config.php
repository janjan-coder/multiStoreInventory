<?php
// Database configuration
$db_host = 'localhost';
$db_user = 'root';
$db_pass = '';
$db_name = 'inventory_multistore';

// Create database connection
$conn = new mysqli($db_host, $db_user, $db_pass, $db_name);

// Check connection
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Set charset to utf8mb4
$conn->set_charset("utf8mb4");

// Function to get store details
function getStoreDetails($store_id) {
    global $conn;
    
    try {
        $stmt = $conn->prepare("SELECT * FROM stores WHERE id = ?");
        $stmt->bind_param("i", $store_id);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($store = $result->fetch_assoc()) {
            return $store;
        }
        
        return null;
    } catch (Exception $e) {
        error_log("Error in getStoreDetails: " . $e->getMessage());
        return null;
    }
}

// Function to sanitize input
function sanitize($input) {
    global $conn;
    return $conn->real_escape_string(trim($input));
}

// Function to check if user is logged in
function isLoggedIn() {
    return isset($_SESSION['user_id']);
}

// Function to check if user has access to store
function hasStoreAccess($store_id) {
    return isset($_SESSION['store_id']) && $_SESSION['store_id'] == $store_id;
}

// Function to format price
function formatPrice($price) {
    return number_format($price, 2, '.', '');
}

// Function to get user details
function getUserDetails($user_id) {
    global $conn;
    
    try {
        $stmt = $conn->prepare("SELECT * FROM users WHERE id = ?");
        $stmt->bind_param("i", $user_id);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($user = $result->fetch_assoc()) {
            return $user;
        }
        
        return null;
    } catch (Exception $e) {
        error_log("Error in getUserDetails: " . $e->getMessage());
        return null;
    }
}

// Function to get product details
function getProductDetails($product_id, $store_id) {
    global $conn;
    
    try {
        $stmt = $conn->prepare("SELECT p.*, c.name as category_name 
                               FROM products p 
                               LEFT JOIN categories c ON p.category_id = c.id 
                               WHERE p.id = ? AND p.store_id = ?");
        $stmt->bind_param("ii", $product_id, $store_id);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($product = $result->fetch_assoc()) {
            return $product;
        }
        
        return null;
    } catch (Exception $e) {
        error_log("Error in getProductDetails: " . $e->getMessage());
        return null;
    }
}

// Function to get recent sales
function getRecentSales($store_id, $limit = 5) {
    global $conn;
    
    try {
        $stmt = $conn->prepare("SELECT s.*, u.username as cashier_name,
                               (SELECT COUNT(*) FROM sale_items WHERE sale_id = s.id) as items_count
                               FROM sales s
                               LEFT JOIN users u ON s.user_id = u.id
                               WHERE s.store_id = ?
                               ORDER BY s.created_at DESC
                               LIMIT ?");
        $stmt->bind_param("ii", $store_id, $limit);
        $stmt->execute();
        $result = $stmt->get_result();
        
        $sales = [];
        while ($sale = $result->fetch_assoc()) {
            $sales[] = $sale;
        }
        
        return $sales;
    } catch (Exception $e) {
        error_log("Error in getRecentSales: " . $e->getMessage());
        return [];
    }
}

// Function to get today's sales
function getTodaySales($store_id) {
    global $conn;
    
    try {
        $stmt = $conn->prepare("SELECT COUNT(*) as total, COALESCE(SUM(total_amount), 0) as amount 
                               FROM sales 
                               WHERE DATE(created_at) = CURDATE() 
                               AND store_id = ?");
        $stmt->bind_param("i", $store_id);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($sales = $result->fetch_assoc()) {
            return $sales;
        }
        
        return ['total' => 0, 'amount' => 0];
    } catch (Exception $e) {
        error_log("Error in getTodaySales: " . $e->getMessage());
        return ['total' => 0, 'amount' => 0];
    }
} 