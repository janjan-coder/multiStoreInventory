<?php
session_start();
require_once 'db.php';

// Only allow access to user's own store if not superadmin
if ($_SESSION['role'] === 'admin') {
    $user_store_id = $_SESSION['store_id'];
    // Only fetch the user's store
    $stores = $conn->query("SELECT * FROM stores WHERE id = $user_store_id");
} else {
    // Superadmin can see all stores
    $stores = $conn->query("SELECT * FROM stores ORDER BY name");
}

// Check if user is logged in and is admin
if (!isset($_SESSION['username']) || $_SESSION['role'] !== 'admin') {
    header("Location: login.php");
    exit();
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action']) && $_POST['action'] === 'transfer') {
        $source_store = intval($_POST['source_store']);
        $destination_store = intval($_POST['destination_store']);
        $product_id = intval($_POST['product_id']);
        $quantity = intval($_POST['quantity']);
        
        // When handling transfers, restrict source/destination stores for admin
        if ($_SESSION['role'] === 'admin') {
            // Only allow source or destination to be the user's store
            if (
                (isset($_POST['source_store']) && $_POST['source_store'] != $user_store_id) ||
                (isset($_POST['destination_store']) && $_POST['destination_store'] != $user_store_id)
            ) {
                $_SESSION['error'] = "You can only transfer stocks from/to your own store.";
                header("Location: " . $_SERVER['PHP_SELF']);
                exit();
            }
        }

        // Validate input
        if ($source_store === $destination_store) {
            $_SESSION['error'] = "Source and destination stores cannot be the same.";
        } elseif ($quantity <= 0) {
            $_SESSION['error'] = "Transfer quantity must be greater than 0.";
        } else {
            // Start transaction
            $conn->begin_transaction();
            
            try {
                // Check if source store has enough stock
                $stmt = $conn->prepare("SELECT quantity FROM inventory WHERE store_id = ? AND product_id = ?");
                $stmt->bind_param("ii", $source_store, $product_id);
                $stmt->execute();
                $result = $stmt->get_result();
                $source_stock = $result->fetch_assoc();
                
                if (!$source_stock || $source_stock['quantity'] < $quantity) {
                    throw new Exception("Insufficient stock in source store.");
                }
                
                // Check if destination store has inventory record
                $stmt = $conn->prepare("SELECT id FROM inventory WHERE store_id = ? AND product_id = ?");
                $stmt->bind_param("ii", $destination_store, $product_id);
                $stmt->execute();
                $result = $stmt->get_result();
                $dest_inventory = $result->fetch_assoc();
                
                // Update source store inventory
                $stmt = $conn->prepare("UPDATE inventory SET quantity = quantity - ? WHERE store_id = ? AND product_id = ?");
                $stmt->bind_param("iii", $quantity, $source_store, $product_id);
                $stmt->execute();
                
                // Update or create destination store inventory
                if ($dest_inventory) {
                    $stmt = $conn->prepare("UPDATE inventory SET quantity = quantity + ? WHERE store_id = ? AND product_id = ?");
                    $stmt->bind_param("iii", $quantity, $destination_store, $product_id);
                    $stmt->execute();
                } else {
                    $stmt = $conn->prepare("INSERT INTO inventory (store_id, product_id, quantity) VALUES (?, ?, ?)");
                    $stmt->bind_param("iii", $destination_store, $product_id, $quantity);
                    $stmt->execute();
                }
                
                // Record transfer in transfer_logs table
                $stmt = $conn->prepare("INSERT INTO transfer_logs (source_store_id, destination_store_id, product_id, quantity, transferred_by) VALUES (?, ?, ?, ?, ?)");
                $stmt->bind_param("iiiii", $source_store, $destination_store, $product_id, $quantity, $_SESSION['user_id']);
                $stmt->execute();
                
                $conn->commit();
                $_SESSION['success'] = "Stock transfer completed successfully.";
                
            } catch (Exception $e) {
                $conn->rollback();
                $_SESSION['error'] = "Error during transfer: " . $e->getMessage();
            }
        }
        
        // Redirect to prevent form resubmission
        header("Location: " . $_SERVER['PHP_SELF']);
        exit();
    }
}

// Update the product dropdown to include size in the display
$products = $conn->query("SELECT p.id, CONCAT(p.name, ' (', p.size, ')') as product_description, s.name as store_name, i.quantity, p.barcode 
                         FROM products p 
                         LEFT JOIN inventory i ON p.id = i.product_id 
                         LEFT JOIN stores s ON i.store_id = s.id 
                         ORDER BY p.name");

// Update the transfer logs query to include size in the product description
$transfers = $conn->query("SELECT t.*, 
                          s1.name as source_store, 
                          s2.name as destination_store, 
                          CONCAT(p.name, ' (', p.size, ')') as product_description, 
                          u.username as transferred_by
                          FROM transfer_logs t
                          JOIN stores s1 ON t.source_store_id = s1.id
                          JOIN stores s2 ON t.destination_store_id = s2.id
                          JOIN products p ON t.product_id = p.id
                          JOIN users u ON t.transferred_by = u.id
                          ORDER BY t.transfer_date DESC
                          LIMIT 10");
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Transfer Stocks - Inventory Management</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.6/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    <style>
        body {
            background-color: #f8f9fc;
        }
        .card {
            border: none;
            border-radius: 0.5rem;
            box-shadow: 0 0.15rem 1.75rem 0 rgba(58, 59, 69, 0.15);
        }
        .card-header {
            background-color: #4e73df;
            color: white;
            border-radius: 0.5rem 0.5rem 0 0 !important;
        }
    </style>
</head>
<body>
    <div class="container-fluid py-4">
        <!-- Navigation -->
        <nav class="mb-4">
            <a href="dashboard.php" class="btn btn-link">
                <i class="bi bi-arrow-left"></i> Back to Dashboard
            </a>
        </nav>

        <div class="row">
            <!-- Transfer Form -->
            <div class="col-md-6">
                <div class="card">
                    <div class="card-header">
                        <h5 class="mb-0">Transfer Stocks</h5>
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

                        <form method="POST" id="transferForm">
                            <input type="hidden" name="action" value="transfer">
                            
                            <div class="mb-3">
                                <label class="form-label">Source Store</label>
                                <select class="form-select" name="source_store" required>
                                    <option value="">Select Source Store</option>
                                    <?php while ($store = $stores->fetch_assoc()): ?>
                                        <option value="<?= $store['id'] ?>"><?= htmlspecialchars($store['name']) ?></option>
                                    <?php endwhile; ?>
                                </select>
                            </div>

                            <div class="mb-3">
                                <label class="form-label">Destination Store</label>
                                <select class="form-select" name="destination_store" required>
                                    <option value="">Select Destination Store</option>
                                    <?php 
                                    $stores->data_seek(0);
                                    while ($store = $stores->fetch_assoc()): 
                                    ?>
                                        <option value="<?= $store['id'] ?>"><?= htmlspecialchars($store['name']) ?></option>
                                    <?php endwhile; ?>
                                </select>
                            </div>

                            <!-- Add a search bar for barcode -->
                            <div class="mb-3">
                                <label for="barcodeSearch" class="form-label">Search by Barcode</label>
                                <input type="text" class="form-control" id="barcodeSearch" placeholder="Enter barcode to search">
                            </div>

                            <div class="mb-3">
                                <label class="form-label">Product</label>
                                <select class="form-select" name="product_id" id="productDropdown" required>
                                    <option value="">Select Product</option>
                                    <?php while ($product = $products->fetch_assoc()): ?>
                                        <option value="<?= $product['id'] ?>" 
                                                data-quantity="<?= $product['quantity'] ?? 0 ?>"
                                                data-store="<?= $product['store_id'] ?? '' ?>"
                                                data-barcode="<?= $product['barcode'] ?? '' ?>">
                                            <?= htmlspecialchars($product['product_description']) ?> 
                                            (<?= $product['store_name'] ? htmlspecialchars($product['store_name']) . ': ' . $product['quantity'] : 'No stock' ?>)
                                        </option>
                                    <?php endwhile; ?>
                                </select>
                            </div>

                            <div class="mb-3">
                                <label class="form-label">Quantity</label>
                                <input type="number" class="form-control" name="quantity" min="1" required>
                                <small class="text-muted">Available: <span id="availableQuantity">0</span></small>
                            </div>

                            <button type="submit" class="btn btn-primary">
                                <i class="bi bi-arrow-left-right me-2"></i>Transfer Stock
                            </button>
                        </form>
                    </div>
                </div>
            </div>

            <!-- Recent Transfers -->
            <div class="col-md-6">
                <div class="card">
                    <div class="card-header">
                        <h5 class="mb-0">Recent Transfers</h5>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table">
                                <thead>
                                    <tr>
                                        <th>Date</th>
                                        <th>Product</th>
                                        <th>From</th>
                                        <th>To</th>
                                        <th>Quantity</th>
                                        <th>By</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php while ($transfer = $transfers->fetch_assoc()): ?>
                                        <tr>
                                            <td><?= date('M d, Y H:i', strtotime($transfer['transfer_date'])) ?></td>
                                            <td><?= htmlspecialchars($transfer['product_description']) ?></td>
                                            <td><?= htmlspecialchars($transfer['source_store']) ?></td>
                                            <td><?= htmlspecialchars($transfer['destination_store']) ?></td>
                                            <td><?= $transfer['quantity'] ?></td>
                                            <td><?= htmlspecialchars($transfer['transferred_by']) ?></td>
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

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.6/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const sourceStore = document.querySelector('select[name="source_store"]');
            const productSelect = document.querySelector('select[name="product_id"]');
            const quantityInput = document.querySelector('input[name="quantity"]');
            const availableQuantitySpan = document.getElementById('availableQuantity');

            // Remove the restriction on max quantity for transfer
            quantityInput.removeAttribute('max');

            // Update available quantity display only
            function updateAvailableQuantity() {
                const selectedProduct = productSelect.options[productSelect.selectedIndex];
                const selectedStore = sourceStore.value;

                if (selectedProduct && selectedStore) {
                    const storeId = selectedProduct.dataset.store;
                    const quantity = selectedProduct.dataset.quantity;

                    if (storeId === selectedStore) {
                        availableQuantitySpan.textContent = quantity;
                    } else {
                        availableQuantitySpan.textContent = '0';
                    }
                } else {
                    availableQuantitySpan.textContent = '0';
                }
            }

            sourceStore.addEventListener('change', updateAvailableQuantity);
            productSelect.addEventListener('change', updateAvailableQuantity);

            // Form validation
            document.getElementById('transferForm').addEventListener('submit', function(e) {
                const source = sourceStore.value;
                const destination = document.querySelector('select[name="destination_store"]').value;
                
                if (source === destination) {
                    e.preventDefault();
                    alert('Source and destination stores cannot be the same.');
                }
            });

            // Barcode search functionality
            const barcodeSearch = document.getElementById('barcodeSearch');
            const productDropdown = document.getElementById('productDropdown'); // Assuming this is the ID of the product dropdown

            barcodeSearch.addEventListener('input', function() {
                const searchValue = barcodeSearch.value.toLowerCase();
                Array.from(productDropdown.options).forEach(option => {
                    const barcode = option.getAttribute('data-barcode'); // Assuming barcode is stored as a data attribute
                    if (barcode && barcode.toLowerCase().includes(searchValue)) {
                        option.style.display = '';
                    } else {
                        option.style.display = 'none';
                    }
                });
            });
        });
    </script>
</body>
</html>