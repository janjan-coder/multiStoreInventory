<?php
// Start session
session_start();

// Check if user is already logged in
if (isset($_SESSION['username'])) {
    // If logged in, redirect to dashboard
    header("Location: dashboard.php");
    exit();
} else {
    // If not logged in, redirect to login page
    header("Location: login.php");
    exit();
}
?>
