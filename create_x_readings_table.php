<?php
require_once 'db.php';

// Create x_readings table
$sql = "CREATE TABLE IF NOT EXISTS x_readings (
    id INT AUTO_INCREMENT PRIMARY KEY,
    store_id INT NOT NULL,
    cashier_id INT NOT NULL,
    reading_date DATE NOT NULL,
    total_transactions INT NOT NULL,
    total_sales DECIMAL(10,2) NOT NULL,
    cash_sales DECIMAL(10,2) NOT NULL,
    card_sales DECIMAL(10,2) NOT NULL,
    gcash_sales DECIMAL(10,2) NOT NULL,
    remarks TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (store_id) REFERENCES stores(id),
    FOREIGN KEY (cashier_id) REFERENCES users(id)
)";

if ($conn->query($sql) === TRUE) {
    echo "Table x_readings created successfully";
} else {
    echo "Error creating table: " . $conn->error;
}

$conn->close();
?> 