<?php
require_once 'db.php';

// Add price column to inventory table if it doesn't exist
$sql = "ALTER TABLE inventory ADD COLUMN IF NOT EXISTS price DECIMAL(10,2) DEFAULT 0.00";
if ($conn->query($sql)) {
    echo "Price field added successfully to inventory table.";
} else {
    echo "Error adding price field: " . $conn->error;
}

// Add price column to products table if it doesn't exist
$sql = "ALTER TABLE products ADD COLUMN IF NOT EXISTS price DECIMAL(10,2) DEFAULT 0.00";
if ($conn->query($sql)) {
    echo "Price field added successfully to products table.";
} else {
    echo "Error adding price field: " . $conn->error;
}
?> 