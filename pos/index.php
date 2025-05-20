<?php
session_start();
require_once '../db.php';

// Check if user is logged in
if (!isset($_SESSION['username'])) {
    header("Location: ../login.php");
    exit();
}

// Check if store_id is set in session
if (!isset($_SESSION['store_id'])) {
    $_SESSION['error'] = "No store assigned to your account. Please contact an administrator.";
    header("Location: ../dashboard.php");
    exit();
}

// Get store details
$store_id = intval($_SESSION['store_id']);
$store_query = "SELECT * FROM stores WHERE id = $store_id";
$store_result = $conn->query($store_query);

if (!$store_result || $store_result->num_rows === 0) {
    $_SESSION['error'] = "Store not found. Please contact an administrator.";
    header("Location: ../dashboard.php");
    exit();
}

$store = $store_result->fetch_assoc();

// Get recent sales for this store
$sales_query = "SELECT s.*, u.username as cashier_name,
                (SELECT COUNT(*) FROM sale_items WHERE sale_id = s.id) as items_count
                FROM sales s
                LEFT JOIN users u ON s.user_id = u.id
                WHERE s.store_id = $store_id
                ORDER BY s.created_at DESC LIMIT 5";
$recent_sales = $conn->query($sales_query);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>POS - <?php echo htmlspecialchars($store['name']); ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.6/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body>
    <div class="container-fluid">
        <div class="row">
            <!-- Sidebar -->
            <div class="col-md-3 col-lg-2 d-md-block sidebar collapse">
                <div class="position-sticky pt-4">
                    <div class="text-center mb-4">
                        <h4 class="system-title"><?php echo htmlspecialchars($store['name']); ?></h4>
                        <p class="welcome-text">Welcome, <?php echo htmlspecialchars($_SESSION['username']); ?></p>
                    </div>
                    <ul class="nav flex-column">
                        <li class="nav-item">
                            <a class="nav-link" href="../dashboard.php">
                                <i class="bi bi-speedometer2"></i>Dashboard
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link active" href="index.php">
                                <i class="bi bi-cash-register"></i>Point of Sale
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="../view_inventory.php">
                                <i class="bi bi-box-seam"></i>View Inventory
                            </a>
                        </li>
                        <li class="nav-item mt-auto">
                            <a class="nav-link btn-logout" href="../logout.php">
                                <i class="bi bi-box-arrow-right"></i>Logout
                            </a>
                        </li>
                    </ul>
                </div>
            </div>

            <!-- Main Content -->
            <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4 main-content">
                <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                    <h1 class="h2">Point of Sale</h1>
                </div>

                <!-- POS Interface -->
                <div class="row">
                    <!-- Product Search and Categories -->
                    <div class="col-md-8">
                        <div class="card mb-4">
                            <div class="card-body">
                                <div class="row mb-3">
                                    <div class="col-md-8">
                                        <div class="input-group">
                                            <input type="text" id="searchInput" class="form-control" placeholder="Search products...">
                                            <button class="btn btn-primary" type="button" id="searchButton">
                                                <i class="bi bi-search"></i>
                                            </button>
                                        </div>
                                    </div>
                                    <div class="col-md-4">
                                        <select class="form-select" id="categoryFilter">
                                            <option value="">All Categories</option>
                                            <?php
                                            $categories_query = "SELECT * FROM categories ORDER BY name";
                                            $categories = $conn->query($categories_query);
                                            while ($category = $categories->fetch_assoc()) {
                                                echo "<option value='" . $category['id'] . "'>" . htmlspecialchars($category['name']) . "</option>";
                                            }
                                            ?>
                                        </select>
                                    </div>
                                </div>
                                <div id="searchResults" class="row g-3">
                                    <!-- Search results will be displayed here -->
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Cart -->
                    <div class="col-md-4">
                        <div class="card">
                            <div class="card-header">
                                <h3 class="card-title mb-0">Current Sale</h3>
                            </div>
                            <div class="card-body">
                                <div id="cartItems">
                                    <!-- Cart items will be displayed here -->
                                </div>
                                <div id="emptyCart" class="text-center py-4">
                                    <i class="bi bi-cart-x display-4 text-muted"></i>
                                    <p class="mt-2 text-muted">Cart is empty</p>
                                </div>
                                <div class="cart-summary">
                                    <div class="d-flex justify-content-between mb-2">
                                        <span>Subtotal:</span>
                                        <span id="subtotal">$0.00</span>
                                    </div>
                                    <div class="d-flex justify-content-between mb-2">
                                        <span>Tax (10%):</span>
                                        <span id="tax">$0.00</span>
                                    </div>
                                    <div class="d-flex justify-content-between mb-3">
                                        <span class="fw-bold">Total:</span>
                                        <span id="total" class="fw-bold">$0.00</span>
                                    </div>
                                    <button class="btn btn-primary w-100" id="checkoutButton" disabled>
                                        <i class="bi bi-cash me-2"></i>Checkout
                                    </button>
                                    <button class="btn btn-outline-danger w-100 mt-2" id="clearCartButton" disabled>
                                        <i class="bi bi-trash me-2"></i>Clear Cart
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Recent Sales -->
                <div class="card mt-4">
                    <div class="card-header">
                        <h3 class="card-title mb-0">Recent Sales</h3>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table">
                                <thead>
                                    <tr>
                                        <th>Sale ID</th>
                                        <th>Items</th>
                                        <th>Total Amount</th>
                                        <th>Cashier</th>
                                        <th>Date</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php while ($sale = $recent_sales->fetch_assoc()): ?>
                                    <tr>
                                        <td>#<?php echo $sale['id']; ?></td>
                                        <td><?php echo $sale['items_count']; ?> items</td>
                                        <td>$<?php echo number_format($sale['total_amount'], 2); ?></td>
                                        <td><?php echo htmlspecialchars($sale['cashier_name']); ?></td>
                                        <td><?php echo date('M d, Y H:i', strtotime($sale['created_at'])); ?></td>
                                    </tr>
                                    <?php endwhile; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </main>
        </div>
    </div>

    <!-- Payment Modal -->
    <div class="modal fade" id="paymentModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Payment</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <form id="paymentForm">
                        <div class="mb-3">
                            <label class="form-label">Payment Method</label>
                            <select class="form-select" id="paymentMethod" required>
                                <option value="cash">Cash</option>
                                <option value="card">Card</option>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Amount Received</label>
                            <input type="number" class="form-control" id="amountReceived" step="0.01" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Change</label>
                            <input type="text" class="form-control" id="change" readonly>
                        </div>
                    </form>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="button" class="btn btn-primary" id="processPayment">Process Payment</button>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.6/dist/js/bootstrap.bundle.min.js"></script>
    <script src="assets/js/main.js"></script>
</body>
</html> 