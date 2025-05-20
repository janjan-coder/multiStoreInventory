<?php
// Database connection settings
$host = 'localhost';
$user = 'root';
$pass = '';
$dbname = 'inventory_multistore';

// Create connection
$conn = new mysqli($host, $user, $pass, $dbname);

// Check connection
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Set charset to utf8mb4
$conn->set_charset("utf8mb4");
?>