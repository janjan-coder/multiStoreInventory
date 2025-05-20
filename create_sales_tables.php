<?php
require_once 'db.php';

// Create sales table
$sql = "CREATE TABLE IF NOT EXISTS sales (
    id INT PRIMARY KEY AUTO_INCREMENT,
    store_id INT NOT NULL,
    user_id INT NOT NULL,
    customer_id INT NULL,
    payment_method ENUM('cash', 'card', 'mobile') NOT NULL,
    total_amount DECIMAL(10,2) NOT NULL,
    tax_amount DECIMAL(10,2) NOT NULL,
    status ENUM('completed', 'cancelled', 'refunded') NOT NULL DEFAULT 'completed',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (store_id) REFERENCES stores(id),
    FOREIGN KEY (user_id) REFERENCES users(id),
    FOREIGN KEY (customer_id) REFERENCES customers(id)
)";

if ($conn->query($sql) === TRUE) {
    echo "Table 'sales' created successfully<br>";
} else {
    echo "Error creating table 'sales': " . $conn->error . "<br>";
}

// Create sale_items table
$sql = "CREATE TABLE IF NOT EXISTS sale_items (
    id INT PRIMARY KEY AUTO_INCREMENT,
    sale_id INT NOT NULL,
    product_id INT NOT NULL,
    quantity INT NOT NULL,
    price DECIMAL(10,2) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (sale_id) REFERENCES sales(id),
    FOREIGN KEY (product_id) REFERENCES products(id)
)";

if ($conn->query($sql) === TRUE) {
    echo "Table 'sale_items' created successfully<br>";
} else {
    echo "Error creating table 'sale_items': " . $conn->error . "<br>";
}

// Create customers table if it doesn't exist
$sql = "CREATE TABLE IF NOT EXISTS customers (
    id INT PRIMARY KEY AUTO_INCREMENT,
    name VARCHAR(100) NOT NULL,
    email VARCHAR(100) NULL,
    phone VARCHAR(20) NULL,
    address TEXT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
)";

if ($conn->query($sql) === TRUE) {
    echo "Table 'customers' created successfully<br>";
} else {
    echo "Error creating table 'customers': " . $conn->error . "<br>";
}

// Create product_prices table if it doesn't exist
$sql = "CREATE TABLE IF NOT EXISTS product_prices (
    id INT PRIMARY KEY AUTO_INCREMENT,
    product_id INT NOT NULL,
    price DECIMAL(10,2) NOT NULL,
    effective_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (product_id) REFERENCES products(id)
)";

if ($conn->query($sql) === TRUE) {
    echo "Table 'product_prices' created successfully<br>";
} else {
    echo "Error creating table 'product_prices': " . $conn->error . "<br>";
}

// Insert initial prices for existing products
$sql = "INSERT INTO product_prices (product_id, price)
        SELECT id, price FROM products
        WHERE id NOT IN (SELECT product_id FROM product_prices)";

if ($conn->query($sql) === TRUE) {
    echo "Initial product prices inserted successfully<br>";
} else {
    echo "Error inserting initial product prices: " . $conn->error . "<br>";
}

$conn->close();
?> 