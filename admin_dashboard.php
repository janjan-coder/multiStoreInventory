<?php
session_start();
if (!isset($_SESSION['username'])) {
    header("Location: login.php");
    exit();
}

// Debugging session variables
error_log("Session username: " . ($_SESSION['username'] ?? 'Not set'));
error_log("Session role: " . ($_SESSION['role'] ?? 'Not set'));

// Database connection
require_once 'db.php';

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
$result = mysqli_query($conn, $query);
$low_stock = mysqli_fetch_assoc($result)['total'];

// Get today's sales across all stores
$query = "SELECT COUNT(*) as total, COALESCE(SUM(total_amount), 0) as amount 
          FROM sales 
          WHERE DATE(created_at) = CURDATE()";
$result = mysqli_query($conn, $query);
$today_sales = mysqli_fetch_assoc($result);

// Get recent sales across all stores
$query = "SELECT s.*, u.username as cashier_name, st.name as store_name,
          (SELECT COUNT(*) FROM sale_items WHERE sale_id = s.id) as items_count
          FROM sales s
          LEFT JOIN users u ON s.user_id = u.id
          LEFT JOIN stores st ON s.store_id = st.id
          ORDER BY s.created_at DESC
          LIMIT 5";
$recent_sales = mysqli_query($conn, $query);

// Get recent activities
$query = "SELECT p.name as product_name, i.quantity, s.name as store_name, i.last_updated 
          FROM inventory i 
          JOIN products p ON i.product_id = p.id 
          JOIN stores s ON i.store_id = s.id 
          ORDER BY i.last_updated DESC LIMIT 5";
$recent_activities = mysqli_query($conn, $query);
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
            overflow-y: auto;
        }

        .sidebar .nav-link {
            color: rgba(255, 255, 255, 0.8);
            padding: 0.8rem 1rem;
            margin: 0.2rem 0;
            border-radius: 0.35rem;
            transition: all 0.3s ease;
            display: flex;
            align-items: center;
            white-space: nowrap;
        }

        .sidebar .nav-link i {
            margin-right: 0.75rem;
            width: 1.25rem;
            text-align: center;
            font-size: 1.1rem;
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

        .sidebar .nav {
            padding-bottom: 1rem;
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

        .stats-card {
            border-left: 0.25rem solid;
            border-radius: 0.35rem;
        }

        .stats-card.primary {
            border-left-color: var(--primary-color);
        }

        .stats-card.success {
            border-left-color: var(--success-color);
        }

        .stats-card.warning {
            border-left-color: var(--warning-color);
        }

        .stats-card.info {
            border-left-color: var(--info-color);
        }

        .stats-card .card-body {
            padding: 1.25rem;
        }

        .stats-card .card-title {
            text-transform: uppercase;
            font-size: 0.7rem;
            font-weight: 700;
            color: rgba(255, 255, 255, 0.8);
        }

        .stats-card .card-text {
            font-size: 1.5rem;
            font-weight: 700;
            color: #fff;
        }

        .activity-card {
            height: 100%;
        }

        .activity-card .card-header {
            background-color: #fff;
            border-bottom: 1px solid #e3e6f0;
            padding: 1rem 1.25rem;
        }

        .activity-card .card-header h5 {
            color: var(--primary-color);
            font-weight: 700;
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
            font-weight: 600;
            color: var(--primary-color);
        }

        .table td {
            vertical-align: middle;
        }

        .quick-action-btn {
            transition: all 0.3s ease;
        }

        .quick-action-btn:hover {
            transform: translateY(-2px);
        }
    </style>
</head>
<body>
    <!-- Sidebar -->
    <div class="sidebar">
        <div class="user-profile text-center">
            <img src="https://ui-avatars.com/api/?name=<?php echo urlencode($_SESSION['username']); ?>&background=random" alt="User Avatar">
            <h6 class="text-white mb-0"><?php echo htmlspecialchars($_SESSION['username']); ?></h6>
            <small class="welcome-text"><?php echo ucfirst($_SESSION['role']); ?></small>
        </div>
        <ul class="nav flex-column px-3">
            <li class="nav-item">
                <a class="nav-link active" href="admin_dashboard.php">
                    <i class="bi bi-speedometer2"></i>Dashboard
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link" href="view_inventory.php">
                    <i class="bi bi-box-seam"></i>View Inventory
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link" href="manage_inventory.php">
                    <i class="bi bi-gear"></i>Manage Inventory
                </a>
            </li>
             <li class="nav-item">
                <a class="nav-link" href="manage_users.php">
                    <i class="bi bi-users"></i>Manage users
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link" href="pos/">
                    <i class="bi bi-cash-register"></i>Point of Sale
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link" href="x_reading.php">
                    <i class="bi bi-receipt"></i>X Reading
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link" href="y_reading.php">
                    <i class="bi bi-calendar-check"></i>Y Reading
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link" href="z_reading.php">
                    <i class="bi bi-calendar-week"></i>Z Reading
                </a>
            </li>
            <?php if ($_SESSION['role'] === 'admin'): ?>
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
            <?php endif; ?>
            <li class="nav-item mt-auto">
                <a class="nav-link btn-logout" href="logout.php">
                    <i class="bi bi-box-arrow-right"></i>Logout
                </a>
            </li>
        </ul>
    </div>

    <!-- Main Content -->
    <main class="main-content">
        <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center mb-4">
            <h1 class="h2 mb-0">Dashboard</h1>
            <div class="btn-toolbar mb-2 mb-md-0">
                <button type="button" class="btn btn-sm btn-outline-primary">
                    <i class="bi bi-download me-1"></i>Export
                </button>
            </div>
        </div>

        <!-- Quick Stats -->
        <div class="row">
            <div class="col-md-3">
                <div class="card stats-card primary bg-gradient-primary">
                    <div class="card-body">
                        <h5 class="card-title">Total Products</h5>
                        <p class="card-text"><?php echo $total_products; ?></p>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card stats-card success bg-gradient-success">
                    <div class="card-body">
                        <h5 class="card-title">Today's Sales</h5>
                        <p class="card-text">$<?php echo number_format($today_sales['amount'] ?? 0, 2); ?></p>
                        <small class="text-white-50"><?php echo $today_sales['total'] ?? 0; ?> transactions</small>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card stats-card info bg-gradient-info">
                    <div class="card-body">
                        <h5 class="card-title">Total Users</h5>
                        <p class="card-text"><?php echo $total_users; ?></p>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card stats-card warning bg-gradient-warning">
                    <div class="card-body">
                        <h5 class="card-title">Low Stock Items</h5>
                        <p class="card-text"><?php echo $low_stock; ?></p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Recent Activities and Sales -->
        <div class="row">
            <div class="col-md-8">
                <div class="card activity-card">
                    <div class="card-header">
                        <h5><i class="bi bi-activity me-2"></i>Recent Activities</h5>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-hover">
                                <thead>
                                    <tr>
                                        <th>Product</th>
                                        <th>Store</th>
                                        <th>Quantity</th>
                                        <th>Last Updated</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php while($activity = mysqli_fetch_assoc($recent_activities)): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($activity['product_name']); ?></td>
                                        <td><?php echo htmlspecialchars($activity['store_name']); ?></td>
                                        <td><?php echo htmlspecialchars($activity['quantity']); ?></td>
                                        <td><?php echo date('M d, Y H:i', strtotime($activity['last_updated'])); ?></td>
                                    </tr>
                                    <?php endwhile; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="card activity-card">
                    <div class="card-header">
                        <h5><i class="bi bi-cash-stack me-2"></i>Recent Sales</h5>
                    </div>
                    <div class="card-body">
                        <div class="list-group">
                            <?php while($sale = mysqli_fetch_assoc($recent_sales)): ?>
                            <div class="list-group-item">
                                <div class="d-flex justify-content-between align-items-center">
                                    <div>
                                        <h6 class="mb-1">Sale #<?php echo $sale['id']; ?></h6>
                                        <small class="text-muted">
                                            <?php echo $sale['store_name']; ?> - <?php echo $sale['items_count']; ?> items
                                        </small>
                                    </div>
                                    <div class="text-end">
                                        <h6 class="mb-1">$<?php echo number_format($sale['total_amount'], 2); ?></h6>
                                        <small class="text-muted">
                                            <?php echo date('M d, H:i', strtotime($sale['created_at'])); ?>
                                        </small>
                                    </div>
                                </div>
                            </div>
                            <?php endwhile; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </main>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.6/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
</body>
</html>