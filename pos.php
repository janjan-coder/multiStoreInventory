<?php
session_start();
require_once 'db.php';

// Check if user is logged in
if (!isset($_SESSION['username'])) {
    header("Location: login.php");
    exit();
}

// Get user details
$username = $_SESSION['username'];
$stmt = $conn->prepare("SELECT u.id, u.role, u.store_id, s.name as store_name 
                       FROM users u 
                       LEFT JOIN stores s ON u.store_id = s.id 
                       WHERE u.username = ?");
$stmt->bind_param("s", $username);
$stmt->execute();
$result = $stmt->get_result();
$user = $result->fetch_assoc();

if (!$user) {
    $_SESSION['error'] = "User not found.";
    header("Location: dashboard.php");
    exit();
}

// Handle store selection for admin users
if ($user['role'] === 'admin') {
    if (isset($_GET['store_id'])) {
        $store_id = intval($_GET['store_id']);
        // Verify store exists
        $stmt = $conn->prepare("SELECT * FROM stores WHERE id = ?");
        $stmt->bind_param("i", $store_id);
        $stmt->execute();
        $store = $stmt->get_result()->fetch_assoc();
        
        if (!$store) {
            $_SESSION['error'] = "Selected store not found.";
            header("Location: dashboard.php");
            exit();
        }
    } else {
        $_SESSION['error'] = "Please select a store.";
        header("Location: dashboard.php");
        exit();
    }
} else {
    // For regular users, use their assigned store
    if (!$user['store_id']) {
        $_SESSION['error'] = "User is not assigned to any store.";
        header("Location: dashboard.php");
        exit();
    }
    $store_id = $user['store_id'];
    
    // Get store details
    $stmt = $conn->prepare("SELECT * FROM stores WHERE id = ?");
    $stmt->bind_param("i", $store_id);
    $stmt->execute();
    $store = $stmt->get_result()->fetch_assoc();
    
    if (!$store) {
        $_SESSION['error'] = "Store not found.";
        header("Location: dashboard.php");
        exit();
    }
}

// Store the current store ID in session
$_SESSION['current_store_id'] = $store_id;

$_SESSION['user_id'] = $user['id'];
$_SESSION['role'] = $user['role'];
$_SESSION['store_id'] = $store_id;

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        switch ($_POST['action']) {
            case 'add_to_cart':
                $barcode = $_POST['barcode'];
                $quantity = intval($_POST['quantity']);
                
                // Get product details with store-specific inventory
                $stmt = $conn->prepare("SELECT p.*, i.quantity as available_quantity 
                                      FROM products p 
                                      LEFT JOIN inventory i ON p.id = i.product_id 
                                      WHERE p.barcode = ? AND i.store_id = ?");
                $stmt->bind_param("si", $barcode, $store_id);
                $stmt->execute();
                $product = $stmt->get_result()->fetch_assoc();
                
                if (!$product) {
                    $_SESSION['error'] = "Product not found in this store.";
                    break;
                }
                
                if ($product['available_quantity'] < $quantity) {
                    $_SESSION['error'] = "Insufficient stock. Only " . $product['available_quantity'] . " items available.";
                    break;
                }
                
                // Initialize cart if not exists
                if (!isset($_SESSION['cart'])) {
                    $_SESSION['cart'] = [];
                }
                
                // Add or update item in cart
                if (isset($_SESSION['cart'][$barcode])) {
                    $new_quantity = $_SESSION['cart'][$barcode]['quantity'] + $quantity;
                    if ($new_quantity > $product['available_quantity']) {
                        $_SESSION['error'] = "Cannot add more items. Only " . $product['available_quantity'] . " items available.";
                        break;
                    }
                    $_SESSION['cart'][$barcode]['quantity'] = $new_quantity;
                } else {
                    $_SESSION['cart'][$barcode] = [
                        'name' => $product['name'],
                        'price' => $product['price'],
                        'quantity' => $quantity
                    ];
                }
                $_SESSION['success'] = "Item added to cart.";
                break;
                
            case 'update_quantity':
                $barcode = $_POST['barcode'];
                $quantity = intval($_POST['quantity']);
                
                if (!isset($_SESSION['cart'][$barcode])) {
                    $_SESSION['error'] = "Item not found in cart.";
                    break;
                }
                
                // Check available quantity
                $stmt = $conn->prepare("SELECT i.quantity as available_quantity 
                                      FROM products p 
                                      JOIN inventory i ON p.id = i.product_id 
                                      WHERE p.barcode = ? AND i.store_id = ?");
                $stmt->bind_param("si", $barcode, $store_id);
                $stmt->execute();
                $result = $stmt->get_result();
                $inventory = $result->fetch_assoc();
                
                if ($quantity > $inventory['available_quantity']) {
                    $_SESSION['error'] = "Cannot update quantity. Only " . $inventory['available_quantity'] . " items available.";
                    break;
                }
                
                $_SESSION['cart'][$barcode]['quantity'] = $quantity;
                $_SESSION['success'] = "Cart updated.";
                break;
                
            case 'remove_item':
                $barcode = $_POST['barcode'];
                if (isset($_SESSION['cart'][$barcode])) {
                    unset($_SESSION['cart'][$barcode]);
                    $_SESSION['success'] = "Item removed from cart.";
                }
                break;
                
            case 'checkout':
                if (empty($_SESSION['cart'])) {
                    $_SESSION['error'] = "Cart is empty.";
                    break;
                }
                
                $total_amount = 0;
                foreach ($_SESSION['cart'] as $item) {
                    $total_amount += $item['price'] * $item['quantity'];
                }
                
                // Start transaction
                $conn->begin_transaction();
                
                try {
                    // Create sale record
                    $stmt = $conn->prepare("INSERT INTO sales (user_id, store_id, total_amount, payment_method) VALUES (?, ?, ?, ?)");
                    $stmt->bind_param("iids", $_SESSION['user_id'], $store_id, $total_amount, $_POST['payment_method']);
                    $stmt->execute();
                    $sale_id = $conn->insert_id;
                    
                    // Add sale items and update inventory
                    foreach ($_SESSION['cart'] as $barcode => $item) {
                        // Get product ID
                        $stmt = $conn->prepare("SELECT id FROM products WHERE barcode = ?");
                        $stmt->bind_param("s", $barcode);
                        $stmt->execute();
                        $product = $stmt->get_result()->fetch_assoc();
                        
                        // Add sale item
                        $stmt = $conn->prepare("INSERT INTO sale_items (sale_id, product_id, quantity, price) VALUES (?, ?, ?, ?)");
                        $stmt->bind_param("iiid", $sale_id, $product['id'], $item['quantity'], $item['price']);
                        $stmt->execute();
                        
                        // Update inventory
                        $stmt = $conn->prepare("UPDATE inventory SET quantity = quantity - ? WHERE product_id = ? AND store_id = ?");
                        $stmt->bind_param("iii", $item['quantity'], $product['id'], $store_id);
                        $stmt->execute();
                    }
                    
                    $conn->commit();
                    unset($_SESSION['cart']);
                    $_SESSION['success'] = "Sale completed successfully.";
                    
                } catch (Exception $e) {
                    $conn->rollback();
                    $_SESSION['error'] = "Error processing sale: " . $e->getMessage();
                }
                break;
        }
        
        // Redirect to prevent form resubmission
        header("Location: " . $_SERVER['PHP_SELF']);
        exit();
    }
}

// Get recent sales for the current store
$recent_sales = $conn->query("SELECT s.*, u.username as cashier_name,
                             (SELECT COUNT(*) FROM sale_items WHERE sale_id = s.id) as item_count
                             FROM sales s 
                             JOIN users u ON s.user_id = u.id 
                             WHERE s.store_id = $store_id
                             ORDER BY s.created_at DESC 
                             LIMIT 10");
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Point of Sale - <?= htmlspecialchars($store['name']) ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.6/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    <style>
        body {
            background: linear-gradient(135deg, #f8f9fc 0%, #e3e6f0 100%);
            min-height: 100vh;
        }

        .container {
            max-width: 1400px;
            margin: 2rem auto;
            padding: 0 1rem;
        }

        .card {
            background: #fff;
            border-radius: 1rem;
            box-shadow: 0 0.5rem 1rem rgba(0, 0, 0, 0.15);
            border: none;
            margin-bottom: 2rem;
        }

        .card-header {
            background: linear-gradient(135deg, #4e73df 0%, #224abe 100%);
            color: white;
            border-radius: 1rem 1rem 0 0 !important;
            padding: 1.5rem;
        }

        .barcode-input {
            font-size: 1.5rem;
            padding: 1rem;
            text-align: center;
        }

        .cart-item {
            transition: all 0.3s ease;
        }

        .cart-item:hover {
            background-color: #f8f9fc;
        }

        .total-section {
            background-color: #f8f9fc;
            border-top: 2px solid #e3e6f0;
            padding: 1rem;
        }

        .nav-link {
            color: #4e73df;
            text-decoration: none;
            padding: 0.5rem 1rem;
            border-radius: 0.35rem;
            transition: all 0.2s;
        }

        .nav-link:hover {
            background-color: #eaecf4;
        }

        .nav-link.active {
            background-color: #4e73df;
            color: white;
        }

        .recent-sales {
            max-height: 300px;
            overflow-y: auto;
        }

        .void-btn {
            color: #e74a3b;
            cursor: pointer;
        }

        .void-btn:hover {
            color: #be2617;
        }
    </style>
</head>
<body>
    <div class="container">
        <!-- Navigation -->
        <nav class="mb-4">
            <a href="dashboard.php" class="nav-link">
                <i class="bi bi-speedometer2 me-2"></i>Dashboard
            </a>
            <?php if ($_SESSION['role'] === 'admin'): ?>
                <a href="manage_inventory.php" class="nav-link">
                    <i class="bi bi-box-seam me-2"></i>Manage Inventory
                </a>
                <a href="manage_users.php" class="nav-link">
                    <i class="bi bi-people me-2"></i>Manage Users
                </a>
            <?php endif; ?>
            <a href="pos.php" class="nav-link active">
                <i class="bi bi-cash-register me-2"></i>Point of Sale
            </a>
            <a href="logout.php" class="nav-link float-end">
                <i class="bi bi-box-arrow-right me-2"></i>Logout
            </a>
        </nav>

        <div class="row">
            <!-- Left side - Product scanning -->
            <div class="col-md-8">
                <div class="card">
                    <div class="card-header">
                        <h3><i class="bi bi-upc-scan me-2"></i>Scan Products - <?= htmlspecialchars($store['name']) ?></h3>
                    </div>
                    <div class="card-body">
                        <?php if (isset($_SESSION['success'])): ?>
                            <div class="alert alert-success alert-dismissible fade show" role="alert">
                                <?php 
                                echo $_SESSION['success'];
                                unset($_SESSION['success']);
                                ?>
                                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                            </div>
                        <?php endif; ?>

                        <?php if (isset($_SESSION['error'])): ?>
                            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                                <?php 
                                echo $_SESSION['error'];
                                unset($_SESSION['error']);
                                ?>
                                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                            </div>
                        <?php endif; ?>

                        <form method="POST" id="scanForm">
                            <input type="hidden" name="action" value="add_to_cart">
                            <div class="row g-3">
                                <div class="col-md-6">
                                    <label for="barcode" class="form-label">Scan Barcode</label>
                                    <input type="text" class="form-control barcode-input" id="barcode" name="barcode" required autofocus>
                                    <div id="productInfo" class="mt-2" style="display: none;">
                                        <small class="text-muted">Product: <span id="productName"></span></small>
                                        <small class="text-muted d-block">Price: ₱<span id="productPrice"></span></small>
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <label for="quantity" class="form-label">Quantity</label>
                                    <input type="number" class="form-control" id="quantity" name="quantity" value="1" min="1" required>
                                </div>
                                <div class="col-md-2 d-flex align-items-end">
                                    <button type="submit" class="btn btn-primary w-100">
                                        <i class="bi bi-cart-plus me-2"></i>Add to Cart
                                    </button>
                                </div>
                            </div>
                        </form>

                        <div class="mt-4">
                            <h4>Current Cart</h4>
                            <div class="table-responsive">
                                <table class="table">
                                    <thead>
                                        <tr>
                                            <th>Product</th>
                                            <th>Price</th>
                                            <th>Quantity</th>
                                            <th>Subtotal</th>
                                            <th>Action</th>
                                        </tr>
                                    </thead>
                                    <tbody id="cartItems">
                                        <?php if (isset($_SESSION['cart']) && !empty($_SESSION['cart'])): ?>
                                            <?php foreach ($_SESSION['cart'] as $barcode => $item): ?>
                                                <tr>
                                                    <td><?= htmlspecialchars($item['name']) ?></td>
                                                    <td>₱<?= number_format($item['price'], 2) ?></td>
                                                    <td>
                                                        <form method="POST" class="d-inline">
                                                            <input type="hidden" name="action" value="update_quantity">
                                                            <input type="hidden" name="barcode" value="<?= $barcode ?>">
                                                            <div class="input-group input-group-sm" style="width: 120px;">
                                                                <button type="submit" class="btn btn-outline-secondary" name="quantity" value="<?= $item['quantity'] - 1 ?>">-</button>
                                                                <input type="number" class="form-control text-center" value="<?= $item['quantity'] ?>" min="1" readonly>
                                                                <button type="submit" class="btn btn-outline-secondary" name="quantity" value="<?= $item['quantity'] + 1 ?>">+</button>
                                                            </div>
                                                        </form>
                                                    </td>
                                                    <td>₱<?= number_format($item['price'] * $item['quantity'], 2) ?></td>
                                                    <td>
                                                        <form method="POST" class="d-inline">
                                                            <input type="hidden" name="action" value="remove_item">
                                                            <input type="hidden" name="barcode" value="<?= $barcode ?>">
                                                            <button type="submit" class="btn btn-sm btn-danger">
                                                                <i class="bi bi-trash"></i>
                                                            </button>
                                                        </form>
                                                    </td>
                                                </tr>
                                            <?php endforeach; ?>
                                        <?php else: ?>
                                            <tr>
                                                <td colspan="5" class="text-center">No items in cart</td>
                                            </tr>
                                        <?php endif; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Right side - Checkout -->
            <div class="col-md-4">
                <div class="card">
                    <div class="card-header">
                        <h5 class="mb-0">Cart</h5>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table">
                                <thead>
                                    <tr>
                                        <th>Item</th>
                                        <th>Price</th>
                                        <th>Qty</th>
                                        <th>Total</th>
                                        <th></th>
                                    </tr>
                                </thead>
                                <tbody id="cartItems">
                                    <?php if (isset($_SESSION['cart']) && !empty($_SESSION['cart'])): ?>
                                        <?php foreach ($_SESSION['cart'] as $barcode => $item): ?>
                                            <tr>
                                                <td><?= htmlspecialchars($item['name']) ?></td>
                                                <td>₱<?= number_format($item['price'], 2) ?></td>
                                                <td>
                                                    <form method="POST" class="d-inline">
                                                        <input type="hidden" name="action" value="update_quantity">
                                                        <input type="hidden" name="barcode" value="<?= $barcode ?>">
                                                        <div class="input-group input-group-sm" style="width: 120px;">
                                                            <button type="submit" class="btn btn-outline-secondary" name="quantity" value="<?= $item['quantity'] - 1 ?>">-</button>
                                                            <input type="number" class="form-control text-center" value="<?= $item['quantity'] ?>" min="1" readonly>
                                                            <button type="submit" class="btn btn-outline-secondary" name="quantity" value="<?= $item['quantity'] + 1 ?>">+</button>
                                                        </div>
                                                    </form>
                                                </td>
                                                <td>₱<?= number_format($item['price'] * $item['quantity'], 2) ?></td>
                                                <td>
                                                    <form method="POST" class="d-inline">
                                                        <input type="hidden" name="action" value="remove_item">
                                                        <input type="hidden" name="barcode" value="<?= $barcode ?>">
                                                        <button type="submit" class="btn btn-sm btn-danger">
                                                            <i class="bi bi-trash"></i>
                                                        </button>
                                                    </form>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    <?php else: ?>
                                        <tr>
                                            <td colspan="5" class="text-center">No items in cart</td>
                                        </tr>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                        
                        <div class="mt-3">
                            <h5>Total: ₱<span id="totalAmount">0.00</span></h5>
                            <input type="hidden" id="totalAmountInput" name="total_amount" value="0">
                        </div>
                        
                        <form method="POST" class="mt-3">
                            <input type="hidden" name="action" value="checkout">
                            <div class="mb-3">
                                <label class="form-label">Payment Method</label>
                                <div class="form-check">
                                    <input class="form-check-input" type="radio" name="payment_method" id="cash" value="cash" checked>
                                    <label class="form-check-label" for="cash">Cash</label>
                                </div>
                                <div class="form-check">
                                    <input class="form-check-input" type="radio" name="payment_method" id="card" value="card">
                                    <label class="form-check-label" for="card">Card</label>
                                </div>
                            </div>
                            
                            <div id="cashPaymentSection">
                                <div class="mb-3">
                                    <label class="form-label">Cash Amount</label>
                                    <input type="number" class="form-control" id="cashAmount" step="0.01" min="0">
                                </div>
                                <div class="mb-3">
                                    <label class="form-label">Change</label>
                                    <input type="text" class="form-control" id="changeAmount" readonly>
                                </div>
                            </div>
                            
                            <button type="submit" class="btn btn-primary w-100" <?= empty($_SESSION['cart']) ? 'disabled' : '' ?>>
                                Complete Sale
                            </button>
                        </form>
                    </div>
                </div>
            </div>

            <!-- Recent Sales Section -->
            <div class="col-12 mt-4">
                <div class="card">
                    <div class="card-header">
                        <h3><i class="bi bi-clock-history me-2"></i>Recent Sales - <?= htmlspecialchars($store['name']) ?></h3>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table">
                                <thead>
                                    <tr>
                                        <th>Date</th>
                                        <th>Items</th>
                                        <th>Total</th>
                                        <th>Cashier</th>
                                        <th>Action</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php while ($sale = $recent_sales->fetch_assoc()): ?>
                                        <tr>
                                            <td><?= date('M d, Y H:i', strtotime($sale['created_at'])) ?></td>
                                            <td><?= $sale['item_count'] ?> items</td>
                                            <td>₱<?= number_format($sale['total_amount'], 2) ?></td>
                                            <td><?= htmlspecialchars($sale['cashier_name']) ?></td>
                                            <td>
                                                <?php if ($_SESSION['role'] === 'admin'): ?>
                                                    <button type="button" class="btn btn-sm btn-danger void-btn" 
                                                            data-bs-toggle="modal" 
                                                            data-bs-target="#voidOrderModal"
                                                            data-sale-id="<?= $sale['id'] ?>">
                                                        <i class="bi bi-x-circle"></i> Void
                                                    </button>
                                                <?php endif; ?>
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
    </div>

    <!-- Add Void Order Modal -->
    <div class="modal fade" id="voidOrderModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Void Order</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST">
                    <div class="modal-body">
                        <input type="hidden" name="action" value="void_order">
                        <input type="hidden" name="sale_id" id="void_sale_id">
                        <div class="mb-3">
                            <label for="admin_password" class="form-label">Admin Password</label>
                            <input type="password" class="form-control" id="admin_password" name="admin_password" required>
                        </div>
                        <div class="alert alert-warning">
                            <i class="bi bi-exclamation-triangle me-2"></i>
                            This action cannot be undone. Please verify your admin password to void this order.
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-danger">Void Order</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.6/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const barcodeInput = document.getElementById('barcode');
            const quantityInput = document.getElementById('quantity');
            const scanForm = document.getElementById('scanForm');
            const checkoutTotalAmount = document.getElementById('checkout_total_amount');
            const productInfo = document.getElementById('productInfo');
            const productNameSpan = document.getElementById('productName');
            const productPriceSpan = document.getElementById('productPrice');
            let barcodeTimeout;

            // Handle barcode scanning
            barcodeInput.addEventListener('input', function() {
                clearTimeout(barcodeTimeout);
                const barcode = this.value.trim();
                
                if (barcode.length > 0) {
                    barcodeTimeout = setTimeout(() => {
                        // Fetch product info using AJAX
                        fetch('get_product_by_barcode.php?barcode=' + encodeURIComponent(barcode))
                            .then(response => response.json())
                            .then(data => {
                                if (data.success) {
                                    productNameSpan.textContent = data.product.name;
                                    productPriceSpan.textContent = data.product.price;
                                    productInfo.style.display = 'block';
                                } else {
                                    productNameSpan.textContent = 'Product not found';
                                    productPriceSpan.textContent = '0.00';
                                    productInfo.style.display = 'block';
                                }
                            })
                            .catch(error => {
                                console.error('Error:', error);
                                productInfo.style.display = 'none';
                            });
                    }, 500);
                } else {
                    productInfo.style.display = 'none';
                }
            });

            // Handle form submission
            scanForm.addEventListener('submit', function(e) {
                e.preventDefault();
                
                const barcode = barcodeInput.value.trim();
                const quantity = quantityInput.value;
                
                if (!barcode) {
                    alert('Please scan a barcode');
                    barcodeInput.focus();
                    return;
                }
                
                if (!quantity || quantity < 1) {
                    alert('Please enter a valid quantity');
                    quantityInput.focus();
                    return;
                }

                // Submit the form
                this.submit();
            });

            // Update total amount
            function updateTotalAmount() {
                let total = 0;
                document.querySelectorAll('#cartItems tr').forEach(row => {
                    const price = parseFloat(row.querySelector('td:nth-child(2)').textContent.replace('₱', '').replace(',', ''));
                    const quantity = parseInt(row.querySelector('td:nth-child(3) input').value);
                    if (!isNaN(price) && !isNaN(quantity)) {
                        total += price * quantity;
                    }
                });
                
                document.getElementById('totalAmount').textContent = total.toFixed(2);
                document.getElementById('totalAmountInput').value = total.toFixed(2);
                
                // Update change if cash payment
                if (document.getElementById('cash').checked) {
                    updateChange();
                }
            }

            // Update change amount
            function updateChange() {
                const total = parseFloat(document.getElementById('totalAmountInput').value);
                const cash = parseFloat(document.getElementById('cashAmount').value) || 0;
                const change = cash - total;
                
                document.getElementById('changeAmount').value = change >= 0 ? change.toFixed(2) : '0.00';
            }

            // Handle payment method change
            document.querySelectorAll('input[name="payment_method"]').forEach(radio => {
                radio.addEventListener('change', function() {
                    const cashSection = document.getElementById('cashPaymentSection');
                    cashSection.style.display = this.value === 'cash' ? 'block' : 'none';
                });
            });

            // Handle cash amount input
            document.getElementById('cashAmount').addEventListener('input', updateChange);

            // Update total when page loads
            updateTotalAmount();
        });
    </script>
</body>
</html> 