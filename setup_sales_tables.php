<?php
require_once 'db.php';

// Read the SQL file
$sql = file_get_contents('create_sales_tables.sql');

// Execute the SQL commands
if ($conn->multi_query($sql)) {
    echo "Sales tables created successfully!";
} else {
    echo "Error creating tables: " . $conn->error;
}

$conn->close();
?> 