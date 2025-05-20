<?php
session_start();
if (!isset($_SESSION['username'])) {
    header("Location: login.php");
    exit();
}

// Database connection
require_once 'db.php';

// Get user details including store assignment
$username = $_SESSION['username'];
$user_query = "SELECT u.id, u.role, u.store_id, s.name as store_name 
               FROM users u 
               LEFT JOIN stores s ON u.store_id = s.id 
               WHERE u.username = ?";
$stmt = $conn->prepare($user_query);
$stmt->bind_param("s", $username);
$stmt->execute();
$user_result = $stmt->get_result();
$user = $user_result->fetch_assoc();

// Store user details in session
$_SESSION['user_id'] = $user['id'];
$_SESSION['role'] = $user['role'];
$_SESSION['store_id'] = $user['store_id'];

// For admin users, get all stores
$stores = [];
if ($user['role'] === 'admin') {
    $stores_query = "SELECT * FROM stores ORDER BY name";
    $stores_result = $conn->query($stores_query);
    while ($store = $stores_result->fetch_assoc()) {
        $stores[] = $store;
    }
}

// Debug information
error_log("User Role: " . $user['role']);
error_log("Username: " . $username);
error_log("Store ID: " . $user['store_id']);

// Get total products count
$query = "SELECT COUNT(*) as total FROM products";
$result = mysqli_query($conn, $query);
$total_products = mysqli_fetch_assoc($result)['total'];

// Get total stores count
$query = "SELECT COUNT(*) as total FROM stores";
$result = mysqli_query($conn, $query);
$total_stores = mysqli_fetch_assoc($result)['total'];

// Get total users count
$query = "SELECT COUNT(*) as total FROM users";
$result = mysqli_query($conn, $query);
$total_users = mysqli_fetch_assoc($result)['total'];

// Get low stock products (less than 10 items)
$query = "SELECT COUNT(*) as total FROM inventory WHERE quantity < 10";
if (isset($_SESSION['store_id'])) {
    $query .= " AND store_id = " . intval($_SESSION['store_id']);
}
$result = mysqli_query($conn, $query);
$low_stock = mysqli_fetch_assoc($result)['total'];

// Get today's sales
$query = "SELECT COUNT(*) as total, COALESCE(SUM(total_amount), 0) as amount 
          FROM sales 
          WHERE DATE(created_at) = CURDATE()";
if (isset($_SESSION['store_id'])) {
    $query .= " AND store_id = " . intval($_SESSION['store_id']);
}
$result = mysqli_query($conn, $query);
$today_sales = mysqli_fetch_assoc($result);

// Get recent sales
$query = "SELECT s.*, u.username as cashier_name,
          (SELECT COUNT(*) FROM sale_items WHERE sale_id = s.id) as items_count
          FROM sales s
          LEFT JOIN users u ON s.user_id = u.id";
if (isset($_SESSION['store_id'])) {
    $query .= " WHERE s.store_id = " . intval($_SESSION['store_id']);
}
$query .= " ORDER BY s.created_at DESC LIMIT 5";
$recent_sales = mysqli_query($conn, $query);

// Get recent activities
$query = "SELECT 'inventory_update' as type, p.name as product_name, i.quantity
          FROM inventory i
          JOIN products p ON i.product_id = p.id";
if (isset($_SESSION['store_id'])) {
    $query .= " WHERE i.store_id = " . intval($_SESSION['store_id']);
}
$query .= " LIMIT 5";
$recent_activities = mysqli_query($conn, $query);

// Handle inventory form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    switch ($_POST['action']) {
        case 'add_inventory':
            $product_id = intval($_POST['product_id']);
            $store_id = intval($_POST['store_id']);
            $size = $_POST['size'];
            $quantity = intval($_POST['quantity']);
            
            // Check if inventory record exists
            $check = $conn->prepare("SELECT id FROM inventory WHERE product_id = ? AND store_id = ? AND size = ?");
            $check->bind_param("iis", $product_id, $store_id, $size);
            $check->execute();
            $result = $check->get_result();
            
            if ($result->num_rows > 0) {
                $_SESSION['error'] = "Inventory record already exists for this product in the selected store and size.";
            } else {
                $stmt = $conn->prepare("INSERT INTO inventory (product_id, store_id, size, quantity) VALUES (?, ?, ?, ?)");
                $stmt->bind_param("iisi", $product_id, $store_id, $size, $quantity);
                if ($stmt->execute()) {
                    $_SESSION['success'] = "Inventory record added successfully.";
                } else {
                    $_SESSION['error'] = "Error adding inventory record.";
                }
            }
            break;

        case 'update_inventory':
            $inventory_id = intval($_POST['inventory_id']);
            $size = $_POST['size'];
            $quantity = intval($_POST['quantity']);
            
            $stmt = $conn->prepare("UPDATE inventory SET size = ?, quantity = ? WHERE id = ?");
            $stmt->bind_param("sii", $size, $quantity, $inventory_id);
            if ($stmt->execute()) {
                $_SESSION['success'] = "Inventory updated successfully.";
            } else {
                $_SESSION['error'] = "Error updating inventory.";
            }
            break;

        case 'delete_inventory':
            $inventory_id = intval($_POST['inventory_id']);
            
            $stmt = $conn->prepare("DELETE FROM inventory WHERE id = ?");
            $stmt->bind_param("i", $inventory_id);
            if ($stmt->execute()) {
                $_SESSION['success'] = "Inventory record deleted successfully.";
            } else {
                $_SESSION['error'] = "Error deleting inventory record.";
            }
            break;
    }
}

// Get all inventory records with product and store details
$inventory_query = "SELECT i.*, p.name as product_name, p.barcode, s.name as store_name 
                   FROM inventory i 
                   JOIN products p ON i.product_id = p.id 
                   JOIN stores s ON i.store_id = s.id 
                   ORDER BY s.name, p.name";
$inventory = $conn->query($inventory_query);

// Check if size column exists, if not add it
$check_size_column = $conn->query("SHOW COLUMNS FROM inventory LIKE 'size'");
if ($check_size_column->num_rows == 0) {
    $conn->query("ALTER TABLE inventory ADD COLUMN size VARCHAR(10) DEFAULT NULL");
}

// Get all products for the add form
$products = $conn->query("SELECT * FROM products ORDER BY name");

// Get all stores for the add form
$stores = $conn->query("SELECT * FROM stores ORDER BY name");

// Get sales statistics
$stats = [
    'today' => [
        'sales' => 0,
        'amount' => 0
    ],
    'week' => [
        'sales' => 0,
        'amount' => 0
    ],
    'month' => [
        'sales' => 0,
        'amount' => 0
    ]
];

// Today's sales
$stmt = $conn->prepare("SELECT COUNT(*) as count, COALESCE(SUM(total_amount), 0) as total 
                       FROM sales 
                       WHERE DATE(created_at) = CURDATE()");
$stmt->execute();
$result = $stmt->get_result();
$today = $result->fetch_assoc();
$stats['today']['sales'] = (int)$today['count'];
$stats['today']['amount'] = (float)$today['total'];

// This week's sales
$stmt = $conn->prepare("SELECT COUNT(*) as count, COALESCE(SUM(total_amount), 0) as total 
                       FROM sales 
                       WHERE YEARWEEK(created_at) = YEARWEEK(CURDATE())");
$stmt->execute();
$result = $stmt->get_result();
$week = $result->fetch_assoc();
$stats['week']['sales'] = (int)$week['count'];
$stats['week']['amount'] = (float)$week['total'];

// This month's sales
$stmt = $conn->prepare("SELECT COUNT(*) as count, COALESCE(SUM(total_amount), 0) as total 
                       FROM sales 
                       WHERE MONTH(created_at) = MONTH(CURDATE()) 
                       AND YEAR(created_at) = YEAR(CURDATE())");
$stmt->execute();
$result = $stmt->get_result();
$month = $result->fetch_assoc();
$stats['month']['sales'] = (int)$month['count'];
$stats['month']['amount'] = (float)$month['total'];

// Get low stock items
$low_stock = $conn->query("SELECT p.name, p.barcode, i.quantity, s.name as store_name 
                          FROM products p 
                          JOIN inventory i ON p.id = i.product_id 
                          JOIN stores s ON i.store_id = s.id 
                          WHERE i.quantity < 10 
                          ORDER BY i.quantity ASC 
                          LIMIT 5");

// Get recent sales
$recent_sales = $conn->query("SELECT s.*, u.username as cashier_name 
                             FROM sales s 
                             JOIN users u ON s.user_id = u.id 
                             ORDER BY s.created_at DESC 
                             LIMIT 5");
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard - Inventory Management</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.6/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    <style>
        :root {
            --primary-color: #4e73df;
            --secondary-color: #858796;
            --success-color: #1cc88a;
            --info-color: #36b9cc;
            --warning-color: #f6c23e;
            --danger-color: #e74a3b;
        }

        body {
            background-color: #f8f9fc;
            font-family: 'Nunito', -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif;
        }

        .sidebar {
            min-height: 100vh;
            background: linear-gradient(180deg, var(--primary-color) 10%, #224abe 100%);
            box-shadow: 0 0.15rem 1.75rem 0 rgba(58, 59, 69, 0.15);
            position: fixed;
            width: 250px;
            z-index: 100;
        }

        .sidebar .nav-link {
            color: rgba(255, 255, 255, 0.8);
            padding: 1rem;
            margin: 0.2rem 0;
            border-radius: 0.35rem;
            transition: all 0.3s ease;
        }

        .sidebar .nav-link:hover {
            color: #fff;
            background-color: rgba(255, 255, 255, 0.1);
            transform: translateX(5px);
        }

        .sidebar .nav-link.active {
            color: #fff;
            background-color: rgba(255, 255, 255, 0.2);
            font-weight: 600;
        }

        .sidebar .nav-link i {
            margin-right: 0.5rem;
            width: 1.5rem;
            text-align: center;
        }

        .main-content {
            margin-left: 250px;
            padding: 1.5rem;
        }

        .card {
            border: none;
            border-radius: 0.35rem;
            box-shadow: 0 0.15rem 1.75rem 0 rgba(58, 59, 69, 0.1);
            transition: transform 0.2s ease-in-out, box-shadow 0.2s ease-in-out;
            margin-bottom: 1.5rem;
        }

        .card:hover {
            transform: translateY(-5px);
            box-shadow: 0 0.5rem 2rem 0 rgba(58, 59, 69, 0.15);
        }

        .card-header {
            background: linear-gradient(135deg, var(--primary-color) 0%, #224abe 100%);
            color: white;
            border-radius: 0.35rem 0.35rem 0 0 !important;
            padding: 1.5rem;
        }

        .card-header h3 {
            margin: 0;
            font-size: 1.5rem;
            font-weight: 600;
        }

        .card-body {
            padding: 2rem;
        }

        .stat-card {
            text-align: center;
            padding: 1.5rem;
        }

        .stat-card i {
            font-size: 2rem;
            margin-bottom: 1rem;
            color: var(--primary-color);
        }

        .stat-card h4 {
            font-size: 1.5rem;
            margin-bottom: 0.5rem;
            color: var(--secondary-color);
        }

        .stat-card p {
            color: var(--primary-color);
            font-size: 1.25rem;
            font-weight: 600;
            margin: 0;
        }

        .welcome-text {
            color: #fff;
            font-size: 0.85rem;
            margin-top: 0.5rem;
        }

        .system-title {
            color: #fff;
            font-size: 1.2rem;
            font-weight: 700;
            margin-bottom: 0;
        }

        .user-profile {
            padding: 1rem;
            border-bottom: 1px solid rgba(255, 255, 255, 0.1);
            margin-bottom: 1rem;
        }

        .user-profile img {
            width: 50px;
            height: 50px;
            border-radius: 50%;
            margin-bottom: 0.5rem;
        }

        .btn-logout {
            color: #fff;
            background-color: rgba(255, 255, 255, 0.1);
            border: none;
            padding: 0.5rem 1rem;
            border-radius: 0.35rem;
            transition: all 0.3s ease;
        }

        .btn-logout:hover {
            background-color: rgba(255, 255, 255, 0.2);
            color: #fff;
        }

        .table th {
            background-color: #f8f9fc;
            color: var(--primary-color);
            font-weight: 600;
            border-bottom: 2px solid #e3e6f0;
        }

        .table td {
            vertical-align: middle;
        }

        .badge-admin {
            background-color: var(--primary-color);
            color: white;
        }

        .badge-user {
            background-color: var(--secondary-color);
            color: white;
        }
    </style>
</head>
<body>
    <div class="container-fluid">
        <div class="row">
            <!-- Sidebar -->
            <div class="col-md-3 col-lg-2 d-md-block sidebar collapse">
                <div class="position-sticky pt-4">
                    <div class="text-center mb-4">
                        <h4 class="system-title">Inventory System</h4>
                        <p class="welcome-text">Welcome, <?php echo htmlspecialchars($_SESSION['username']); ?></p>
                    </div>
                    <ul class="nav flex-column">
                        <li class="nav-item">
                            <a class="nav-link active" href="dashboard.php">
                                <i class="bi bi-speedometer2"></i>Dashboard
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="pos.php">
                                <i class="bi bi-cash-register"></i>Point of Sale
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="view_inventory.php">
                                <i class="bi bi-box-seam"></i>View Inventory
                            </a>
                        </li>
                        <?php if ($user['role'] === 'admin'): ?>
                        <li class="nav-item">
                            <a class="nav-link" href="manage_inventory.php">
                                <i class="bi bi-gear"></i>Manage Inventory
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="add_store.php">
                                <i class="bi bi-shop"></i>Add Store
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="add_product.php">
                                <i class="bi bi-plus-circle"></i>Add Product
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="create_user.php">
                                <i class="bi bi-person-plus"></i>Register User
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="manage_users.php">
                                <i class="bi bi-people"></i>Manage Users
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="transfer_stocks.php">
                                <i class="bi bi-arrow-left-right"></i>Transfer Stocks
                            </a>
                        </li>
                        <?php endif; ?>
                        <li class="nav-item mt-auto">
                            <a class="nav-link btn-logout" href="logout.php">
                                <i class="bi bi-box-arrow-right"></i>Logout
                            </a>
                        </li>
                    </ul>
                </div>
            </div>

            <!-- Main Content -->
            <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4 main-content">
                <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                    <h1 class="h2">Dashboard</h1>
                </div>

                <!-- Navigation -->
                <nav class="mb-4">
                    <a href="dashboard.php" class="nav-link">
                        <i class="bi bi-speedometer2 me-2"></i>Dashboard
                    </a>
                    <?php if ($user['role'] === 'admin'): ?>
                        <a href="manage_inventory.php" class="nav-link">
                            <i class="bi bi-box-seam me-2"></i>Manage Inventory
                        </a>
                        <a href="manage_users.php" class="nav-link">
                            <i class="bi bi-people me-2"></i>Manage Users
                        </a>
                        <a href="transfer_stocks.php" class="nav-link">
                            <i class="bi bi-arrow-left-right me-2"></i>Transfer Stocks
                        </a>
                        <?php if (!empty($stores)): ?>
                            <div class="dropdown d-inline-block">
                                <button class="btn btn-link nav-link dropdown-toggle" type="button" id="storeDropdown" data-bs-toggle="dropdown">
                                    <i class="bi bi-shop me-2"></i>Select Store for POS
                                </button>
                                <ul class="dropdown-menu" aria-labelledby="storeDropdown">
                                    <?php foreach ($stores as $store): ?>
                                        <li>
                                            <a class="dropdown-item" href="pos.php?store_id=<?= $store['id'] ?>">
                                                <?= htmlspecialchars($store['name']) ?>
                                            </a>
                                        </li>
                                    <?php endforeach; ?>
                                </ul>
                            </div>
                        <?php endif; ?>
                    <?php else: ?>
                        <a href="pos.php" class="nav-link">
                            <i class="bi bi-cash-register me-2"></i>Point of Sale
                        </a>
                    <?php endif; ?>
                    <a href="logout.php" class="nav-link float-end">
                        <i class="bi bi-box-arrow-right me-2"></i>Logout
                    </a>
                </nav>

                <!-- Quick Stats -->
                <div class="row">
                    <div class="col-md-3">
                        <?php if ($user['role'] === 'admin'): ?>
                            <div class="dropdown">
                                <a href="#" class="card text-decoration-none dropdown-toggle" data-bs-toggle="dropdown">
                                    <div class="card-body text-center">
                                        <i class="bi bi-cash-register display-4 text-primary mb-3"></i>
                                        <h5 class="card-title">Point of Sale</h5>
                                        <p class="card-text text-muted">Select store to start</p>
                                    </div>
                                </a>
                                <ul class="dropdown-menu w-100">
                                    <?php foreach ($stores as $store): ?>
                                        <li>
                                            <a class="dropdown-item" href="pos.php?store_id=<?= $store['id'] ?>">
                                                <?= htmlspecialchars($store['name']) ?>
                                            </a>
                                        </li>
                                    <?php endforeach; ?>
                                </ul>
                            </div>
                        <?php else: ?>
                            <a href="pos.php" class="card text-decoration-none">
                                <div class="card-body text-center">
                                    <i class="bi bi-cash-register display-4 text-primary mb-3"></i>
                                    <h5 class="card-title">Point of Sale</h5>
                                    <p class="card-text text-muted">Start a new sale</p>
                                </div>
                            </a>
                        <?php endif; ?>
                    </div>
                    <div class="col-md-3">
                        <a href="view_inventory.php" class="card text-decoration-none">
                            <div class="card-body text-center">
                                <i class="bi bi-box-seam display-4 text-primary mb-3"></i>
                                <h5 class="card-title">View Inventory</h5>
                                <p class="card-text text-muted">Check stock levels</p>
                            </div>
                        </a>
                    </div>
                    <div class="col-md-3">
                        <a href="sales_report.php" class="card text-decoration-none">
                            <div class="card-body text-center">
                                <i class="bi bi-graph-up display-4 text-success mb-3"></i>
                                <h5 class="card-title">Sales Report</h5>
                                <p class="card-text text-muted">View sales analytics</p>
                            </div>
                        </a>
                    </div>
                    <?php if ($user['role'] === 'admin'): ?>
                    <div class="col-md-3">
                        <a href="manage_inventory.php" class="card text-decoration-none">
                            <div class="card-body text-center">
                                <i class="bi bi-gear display-4 text-primary mb-3"></i>
                                <h5 class="card-title">Manage Inventory</h5>
                                <p class="card-text text-muted">Update stock levels</p>
                            </div>
                        </a>
                    </div>
                    <?php endif; ?>
                </div>

                <!-- Sales Statistics -->
                <div class="row mt-4">
                    <div class="col-xl-4 col-md-6 mb-4">
                        <div class="card">
                            <div class="card-header">
                                <h3><i class="bi bi-calendar-check me-2"></i>Today's Sales</h3>
                            </div>
                            <div class="card-body">
                                <div class="row">
                                    <div class="col-6">
                                        <h5>Amount</h5>
                                        <p class="h3">₱<?php echo number_format($stats['today']['amount'], 2); ?></p>
                                    </div>
                                    <div class="col-6">
                                        <h5>Transactions</h5>
                                        <p class="h3"><?php echo number_format($stats['today']['sales']); ?></p>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-xl-4 col-md-6 mb-4">
                        <div class="card">
                            <div class="card-header">
                                <h3><i class="bi bi-calendar-week me-2"></i>This Week</h3>
                            </div>
                            <div class="card-body">
                                <div class="row">
                                    <div class="col-6">
                                        <h5>Amount</h5>
                                        <p class="h3">₱<?php echo number_format($stats['week']['amount'], 2); ?></p>
                                    </div>
                                    <div class="col-6">
                                        <h5>Transactions</h5>
                                        <p class="h3"><?php echo number_format($stats['week']['sales']); ?></p>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-xl-4 col-md-6 mb-4">
                        <div class="card">
                            <div class="card-header">
                                <h3><i class="bi bi-calendar-month me-2"></i>This Month</h3>
                            </div>
                            <div class="card-body">
                                <div class="row">
                                    <div class="col-6">
                                        <h5>Amount</h5>
                                        <p class="h3">₱<?php echo number_format($stats['month']['amount'], 2); ?></p>
                                    </div>
                                    <div class="col-6">
                                        <h5>Transactions</h5>
                                        <p class="h3"><?php echo number_format($stats['month']['sales']); ?></p>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Recent Sales and Low Stock -->
                <div class="row">
                    <div class="col-xl-8 mb-4">
                        <div class="card">
                            <div class="card-header">
                                <h3><i class="bi bi-clock-history me-2"></i>Recent Sales</h3>
                            </div>
                            <div class="card-body">
                                <div class="table-responsive">
                                    <table class="table">
                                        <thead>
                                            <tr>
                                                <th>Sale ID</th>
                                                <th>Date</th>
                                                <th>Cashier</th>
                                                <th>Amount</th>
                                                <?php if ($user['role'] === 'admin'): ?>
                                                <th>Action</th>
                                                <?php endif; ?>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php while ($sale = $recent_sales->fetch_assoc()): ?>
                                            <tr>
                                                <td>#<?php echo $sale['id']; ?></td>
                                                <td><?php echo date('M d, Y H:i', strtotime($sale['created_at'])); ?></td>
                                                <td><?php echo htmlspecialchars($sale['cashier_name']); ?></td>
                                                <td>₱<?php echo number_format($sale['total_amount'], 2); ?></td>
                                                <?php if ($user['role'] === 'admin'): ?>
                                                <td>
                                                    <button type="button" class="btn btn-link text-danger p-0" 
                                                            data-bs-toggle="modal" 
                                                            data-bs-target="#voidModal<?= $sale['id'] ?>">
                                                        <i class="bi bi-x-circle"></i> Void
                                                    </button>
                                                </td>
                                                <?php endif; ?>
                                            </tr>
                                            <?php endwhile; ?>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-xl-4 mb-4">
                        <div class="card">
                            <div class="card-header">
                                <h3><i class="bi bi-exclamation-triangle me-2"></i>Low Stock Items</h3>
                            </div>
                            <div class="card-body">
                                <div class="table-responsive">
                                    <table class="table">
                                        <thead>
                                            <tr>
                                                <th>Product</th>
                                                <th>Quantity</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php while ($item = $low_stock->fetch_assoc()): ?>
                                            <tr>
                                                <td><?php echo htmlspecialchars($item['name']); ?></td>
                                                <td>
                                                    <span class="badge bg-danger">
                                                        <?php echo $item['quantity']; ?>
                                                    </span>
                                                </td>
                                            </tr>
                                            <?php endwhile; ?>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </main>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.6/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Function to show alerts
        function showAlert(message, type = 'success') {
            const alertDiv = document.createElement('div');
            alertDiv.className = `alert alert-${type} alert-dismissible fade show`;
            alertDiv.innerHTML = `
                ${message}
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            `;
            document.querySelector('.card-body').insertBefore(alertDiv, document.querySelector('.card-body').firstChild);
            
            // Auto dismiss after 3 seconds
            setTimeout(() => {
                alertDiv.remove();
            }, 3000);
        }

        // Function to update inventory row
        function updateInventoryRow(inventoryId, size, quantity) {
            const row = document.querySelector(`tr[data-inventory-id="${inventoryId}"]`);
            if (row) {
                // Update size badge
                const sizeCell = row.querySelector('.size-badge');
                if (sizeCell) {
                    sizeCell.textContent = size || 'N/A';
                }

                // Update quantity badge
                const quantityCell = row.querySelector('.quantity-badge');
                if (quantityCell) {
                    quantityCell.textContent = quantity;
                    quantityCell.className = `badge bg-${quantity < 10 ? 'danger' : 'success'} quantity-badge`;
                }
            }
        }

        // Handle form submissions with AJAX
        document.querySelectorAll('form').forEach(form => {
            form.addEventListener('submit', function(e) {
                e.preventDefault();
                
                const formData = new FormData(this);
                const action = formData.get('action');
                const inventoryId = formData.get('inventory_id');
                
                fetch(window.location.href, {
                    method: 'POST',
                    body: formData
                })
                .then(response => response.text())
                .then(html => {
                    // Create a temporary div to parse the response
                    const tempDiv = document.createElement('div');
                    tempDiv.innerHTML = html;
                    
                    // Check for success/error messages
                    const successMsg = tempDiv.querySelector('.alert-success');
                    const errorMsg = tempDiv.querySelector('.alert-danger');
                    
                    if (successMsg) {
                        showAlert(successMsg.textContent.trim());
                        
                        // Close modal if it's open
                        const modal = bootstrap.Modal.getInstance(document.querySelector('.modal.show'));
                        if (modal) {
                            modal.hide();
                        }

                        // If it's an update, update the table row directly
                        if (action === 'update_inventory') {
                            const size = formData.get('size');
                            const quantity = formData.get('quantity');
                            updateInventoryRow(inventoryId, size, quantity);
                        } else {
                            // For add/delete operations, reload after a short delay
                            setTimeout(() => {
                                window.location.reload();
                            }, 1000);
                        }
                    } else if (errorMsg) {
                        showAlert(errorMsg.textContent.trim(), 'danger');
                    }
                })
                .catch(error => {
                    showAlert('An error occurred. Please try again.', 'danger');
                });
            });
        });

        // Handle delete confirmations
        document.querySelectorAll('[data-bs-target^="#deleteModal"]').forEach(button => {
            button.addEventListener('click', function() {
                const modalId = this.getAttribute('data-bs-target');
                const modal = document.querySelector(modalId);
                const form = modal.querySelector('form');
                
                form.addEventListener('submit', function(e) {
                    e.preventDefault();
                    
                    const formData = new FormData(this);
                    
                    fetch(window.location.href, {
                        method: 'POST',
                        body: formData
                    })
                    .then(response => response.text())
                    .then(html => {
                        const tempDiv = document.createElement('div');
                        tempDiv.innerHTML = html;
                        
                        const successMsg = tempDiv.querySelector('.alert-success');
                        const errorMsg = tempDiv.querySelector('.alert-danger');
                        
                        if (successMsg) {
                            showAlert(successMsg.textContent.trim());
                            const modal = bootstrap.Modal.getInstance(document.querySelector('.modal.show'));
                            if (modal) {
                                modal.hide();
                            }
                            setTimeout(() => {
                                window.location.reload();
                            }, 1000);
                        } else if (errorMsg) {
                            showAlert(errorMsg.textContent.trim(), 'danger');
                        }
                    })
                    .catch(error => {
                        showAlert('An error occurred. Please try again.', 'danger');
                    });
                });
            });
        });
    </script>
</body>
</html>
