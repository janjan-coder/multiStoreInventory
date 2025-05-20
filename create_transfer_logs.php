<?php
require_once 'db.php';

// Create transfer_logs table
$sql = "CREATE TABLE IF NOT EXISTS transfer_logs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    source_store_id INT NOT NULL,
    destination_store_id INT NOT NULL,
    product_id INT NOT NULL,
    quantity INT NOT NULL CHECK (quantity > 0),
    transferred_by INT NOT NULL,
    transfer_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (source_store_id) REFERENCES stores(id),
    FOREIGN KEY (destination_store_id) REFERENCES stores(id),
    FOREIGN KEY (product_id) REFERENCES products(id),
    FOREIGN KEY (transferred_by) REFERENCES users(id)
)";

if ($conn->query($sql) === TRUE) {
    echo "Table transfer_logs created successfully";
} else {
    echo "Error creating table: " . $conn->error;
}

$conn->close();
?> 