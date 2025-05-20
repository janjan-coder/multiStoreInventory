<?php
session_start();
require_once 'db.php';

// Check if user is logged in
if (!isset($_SESSION['username'])) {
    header("Location: login.php");
    exit();
}

// Only allow access to superadmins and admins
if ($_SESSION['role'] !== 'superadmin' && $_SESSION['role'] !== 'admin') {
    header("Location: dashboard.php");
    exit();
}

// Store user's store_id for access control
$user_store_id = $_SESSION['store_id'];

// Success and error messages
$success_message = isset($_SESSION['success']) ? $_SESSION['success'] : '';
$error_message = isset($_SESSION['error']) ? $_SESSION['error'] : '';
unset($_SESSION['success'], $_SESSION['error']);

// Function to validate store access
function validateStoreAccess($conn, $inventory_id) {
    if ($_SESSION['role'] === 'superadmin') {
        return true;
    }
    
    $stmt = $conn->prepare("SELECT store_id FROM inventory WHERE id = ?");
    $stmt->bind_param("i", $inventory_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $inventory = $result->fetch_assoc();
    
    return $inventory && $inventory['store_id'] == $_SESSION['store_id'];
}

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        switch ($_POST['action']) {
            case 'add':
                $barcode = trim($_POST['barcode']);
                $store_id = intval($_POST['store_id']);
                $quantity = intval($_POST['quantity']);
                $price = floatval($_POST['price']);

                // Validate store access for admin users
                if ($_SESSION['role'] === 'admin' && $store_id !== $_SESSION['store_id']) {
                    $_SESSION['error'] = "You can only manage inventory for your assigned store.";
                    break;
                }
                
                // Get product ID from barcode
                $check_product = $conn->prepare("SELECT id FROM products WHERE barcode = ?");
                $check_product->bind_param("s", $barcode);
                $check_product->execute();
                $product_result = $check_product->get_result();
                
                if ($product_result->num_rows === 0) {
                    $_SESSION['error'] = "Product with this barcode not found.";
                } else {
                    $product = $product_result->fetch_assoc();
                    $product_id = $product['id'];
                    
                    // Check if inventory record exists
                    $check = $conn->prepare("SELECT id FROM inventory WHERE product_id = ? AND store_id = ?");
                    $check->bind_param("ii", $product_id, $store_id);
                    $check->execute();
                    $result = $check->get_result();
                    
                    if ($result->num_rows > 0) {
                        $_SESSION['error'] = "Inventory record already exists for this product in the selected store.";
                    } else {
                        $stmt = $conn->prepare("INSERT INTO inventory (product_id, store_id, quantity, price) VALUES (?, ?, ?, ?)");
                        $stmt->bind_param("iiid", $product_id, $store_id, $quantity, $price);
                        if ($stmt->execute()) {
                            $_SESSION['success'] = "Inventory record added successfully.";
                        } else {
                            $_SESSION['error'] = "Error adding inventory record.";
                        }
                    }
                }
                break;

            case 'update':
                $inventory_id = intval($_POST['inventory_id']);
                
                // Validate store access
                if (!validateStoreAccess($conn, $inventory_id)) {
                    $_SESSION['error'] = "You don't have permission to update this inventory record.";
                    break;
                }
                
                $quantity = intval($_POST['quantity']);
                $price = floatval($_POST['price']);

                // Validate store access
                if (!validateStoreAccess($conn, $inventory_id)) {
                    $_SESSION['error'] = "You do not have access to this inventory record.";
                    break;
                }
                
                // First get the product_id for this inventory item
                $get_product = $conn->prepare("SELECT product_id FROM inventory WHERE id = ?");
                $get_product->bind_param("i", $inventory_id);
                $get_product->execute();
                $product_result = $get_product->get_result();
                $product_data = $product_result->fetch_assoc();
                $product_id = $product_data['product_id'];
                
                // Update price in products table
                $update_product = $conn->prepare("UPDATE products SET price = ? WHERE id = ?");
                $update_product->bind_param("di", $price, $product_id);
                $update_product->execute();
                
                // Update quantity in inventory table
                $stmt = $conn->prepare("UPDATE inventory SET quantity = ? WHERE id = ?");
                $stmt->bind_param("ii", $quantity, $inventory_id);
                if ($stmt->execute()) {
                    $_SESSION['success'] = "Inventory updated successfully.";
                } else {
                    $_SESSION['error'] = "Error updating inventory.";
                }
                break;

            case 'update_product_name':
                $product_id = intval($_POST['product_id']);
                $new_name = trim($_POST['new_name']);
                
                if (empty($new_name)) {
                    $_SESSION['error'] = "Product name cannot be empty.";
                } else {
                    // First check if the product exists
                    $check = $conn->prepare("SELECT id FROM products WHERE id = ?");
                    $check->bind_param("i", $product_id);
                    $check->execute();
                    $result = $check->get_result();
                    
                    if ($result->num_rows === 0) {
                        $_SESSION['error'] = "Product not found.";
                    } else {
                        $stmt = $conn->prepare("UPDATE products SET name = ? WHERE id = ?");
                        $stmt->bind_param("si", $new_name, $product_id);
                        if ($stmt->execute()) {
                            $_SESSION['success'] = "Product name updated successfully.";
                        } else {
                            $_SESSION['error'] = "Error updating product name: " . $stmt->error;
                        }
                    }
                }
                break;

            case 'delete':
                $inventory_id = intval($_POST['inventory_id']);
                
                // Validate store access
                if (!validateStoreAccess($conn, $inventory_id)) {
                    $_SESSION['error'] = "You don't have permission to delete this inventory record.";
                    break;
                }

                // Validate store access
                if (!validateStoreAccess($conn, $inventory_id)) {
                    $_SESSION['error'] = "You do not have access to this inventory record.";
                    break;
                }
                
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
}

// Get inventory records filtered by store for admin users
$query = "SELECT i.*, p.id as product_id, p.name as product_name, p.barcode, p.size, p.price, s.name as store_name 
          FROM inventory i 
          JOIN products p ON i.product_id = p.id 
          JOIN stores s ON i.store_id = s.id";

// Filter by store for admin users
if ($_SESSION['role'] === 'admin') {
    $query .= " WHERE i.store_id = " . intval($_SESSION['store_id']);
}

$query .= " ORDER BY p.name";
$inventory = $conn->query($query);

// Get all products for the add form
$products = $conn->query("SELECT * FROM products ORDER BY name");

// Get stores based on user role
if ($_SESSION['role'] === 'admin') {
    $stores = $conn->query("SELECT * FROM stores WHERE id = " . intval($_SESSION['store_id']));
} else {
    // Superadmin can see all stores
    $stores = $conn->query("SELECT * FROM stores ORDER BY name");
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Inventory - Inventory Management</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.6/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    <style>
        :root {
            --primary-color: #4e73df;
            --secondary-color: #858796;
            --success-color: #1cc88a;
        }

        body {
            background: linear-gradient(135deg, #f8f9fc 0%, #e3e6f0 100%);
            min-height: 100vh;
            font-family: 'Nunito', -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif;
        }

        .container {
            max-width: 1200px;
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
            background: linear-gradient(135deg, var(--primary-color) 0%, #224abe 100%);
            color: white;
            border-radius: 1rem 1rem 0 0 !important;
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

        .table th {
            background-color: #f8f9fc;
            color: var(--primary-color);
            font-weight: 600;
            border-bottom: 2px solid #e3e6f0;
        }

        .table td {
            vertical-align: middle;
        }

        .btn-action {
            padding: 0.25rem 0.5rem;
            font-size: 0.875rem;
        }

        .form-control, .form-select {
            border-radius: 0.5rem;
            padding: 0.75rem 1rem;
            border: 1px solid #d1d3e2;
        }

        .form-control:focus, .form-select:focus {
            border-color: var(--primary-color);
            box-shadow: 0 0 0 0.25rem rgba(78, 115, 223, 0.25);
        }

        .back-link {
            color: var(--primary-color);
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            margin-bottom: 1rem;
            font-weight: 600;
        }

        .back-link:hover {
            color: #2e59d9;
            transform: translateX(-5px);
        }

        .badge-stock {
            font-size: 0.875rem;
            padding: 0.5rem 0.75rem;
        }

        .product-name {
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
        }
        
        .product-name:hover {
            color: var(--primary-color);
        }
        
        .product-name i {
            opacity: 0;
            transition: opacity 0.2s;
        }
        
        .product-name:hover i {
            opacity: 1;
        }
    </style>
</head>
<body>
    <div class="container">
        <a href="dashboard.php" class="back-link">
            <i class="bi bi-arrow-left me-2"></i>Back to Dashboard
        </a>

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

        <div class="card">
            <div class="card-header">
                <h3><i class="bi bi-box-seam me-2"></i>Manage Inventory</h3>
            </div>
            <div class="card-body">
                <!-- Add New Inventory Form -->
                <form method="POST" class="mb-4">
                    <input type="hidden" name="action" value="add">
                    <div class="row g-3">
                        <div class="col-md-4">
                            <label for="barcode" class="form-label">Scan Barcode</label>
                            <input type="text" class="form-control" id="barcode" name="barcode" required autofocus>                            <div id="productInfo" class="mt-2" style="display: none;">
                                <small class="text-muted">Product: <span id="productName"></span></small>
                                <small class="text-muted d-block">Size: <span id="productSize"></span></small>
                                <small class="text-muted d-block">Price: ₱<span id="productPrice"></span></small>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <label for="store_id" class="form-label">Store</label>
                            <select name="store_id" id="store_id" class="form-select" required>
                                <option value="">Select Store</option>
                                <?php while ($store = $stores->fetch_assoc()): ?>
                                    <option value="<?= $store['id'] ?>">
                                        <?= htmlspecialchars($store['name']) ?>
                                    </option>
                                <?php endwhile; ?>
                            </select>
                        </div>
                        <div class="col-md-2">
                            <label for="quantity" class="form-label">Quantity</label>
                            <input type="number" class="form-control" id="quantity" name="quantity" min="0" required>
                        </div>
                        <div class="col-md-2">
                            <label for="price" class="form-label">Price</label>
                            <input type="number" class="form-control" id="price" name="price" min="0" step="0.01" required>
                        </div>
                        <div class="col-md-1 d-flex align-items-end">
                            <button type="submit" class="btn btn-primary w-100">
                                <i class="bi bi-plus-circle me-2"></i>Add
                            </button>
                        </div>
                    </div>
                </form>

                <!-- Inventory Table -->
                <div class="table-responsive">
                    <table class="table table-hover">
                        <thead>                                <tr>
                                    <th>Store</th>
                                    <th>Product</th>
                                    <th>Size</th>
                                    <th>Barcode</th>
                                    <th>Price</th>
                                    <th>Quantity</th>
                                    <th>Actions</th>
                                </tr>
                        </thead>
                        <tbody>
                            <?php while ($item = $inventory->fetch_assoc()): ?>
                                <tr>                                    <td><?= htmlspecialchars($item['store_name']) ?></td>
                                    <td>
                                        <span class="product-name" 
                                              data-bs-toggle="modal" 
                                              data-bs-target="#editNameModal"
                                              data-product-id="<?= $item['product_id'] ?>"
                                              data-product-name="<?= htmlspecialchars($item['product_name']) ?>">
                                            <?= htmlspecialchars($item['product_name']) ?>
                                            <i class="bi bi-pencil"></i>
                                        </span>
                                    </td>
                                    <td><?= htmlspecialchars($item['size']) ?></td>
                                    <td><?= htmlspecialchars($item['barcode']) ?></td>
                                    <td>₱<?= number_format($item['price'], 2) ?></td>
                                    <td>
                                        <span class="badge bg-<?= $item['quantity'] < 10 ? 'danger' : 'success' ?> badge-stock">
                                            <?= $item['quantity'] ?>
                                        </span>
                                    </td>
                                    <td>
                                        <button type="button" class="btn btn-sm btn-primary btn-action" 
                                                data-bs-toggle="modal" 
                                                data-bs-target="#editModal<?= $item['id'] ?>">
                                            <i class="bi bi-pencil"></i>
                                        </button>
                                        <button type="button" class="btn btn-sm btn-danger btn-action" 
                                                data-bs-toggle="modal" 
                                                data-bs-target="#deleteModal<?= $item['id'] ?>">
                                            <i class="bi bi-trash"></i>
                                        </button>
                                    </td>
                                </tr>

                                <!-- Edit Quantity Modal -->
                                <div class="modal fade" id="editModal<?= $item['id'] ?>" tabindex="-1">
                                    <div class="modal-dialog">
                                        <div class="modal-content">
                                            <div class="modal-header">
                                                <h5 class="modal-title">Edit Item</h5>
                                                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                            </div>
                                            <form method="POST">
                                                <div class="modal-body">
                                                    <input type="hidden" name="action" value="update">
                                                    <input type="hidden" name="inventory_id" value="<?= $item['id'] ?>">                                    <div class="mb-3">
                                        <label class="form-label">Product</label>
                                        <input type="text" class="form-control" value="<?= htmlspecialchars($item['product_name']) ?>" readonly>
                                    </div>
                                    <div class="mb-3">
                                        <label class="form-label">Size</label>
                                        <input type="text" class="form-control" value="<?= htmlspecialchars($item['size']) ?>" readonly>
                                    </div>
                                    <div class="mb-3">
                                        <label class="form-label">Store</label>
                                        <input type="text" class="form-control" value="<?= htmlspecialchars($item['store_name']) ?>" readonly>
                                    </div>
                                                    <div class="mb-3">
                                                        <label for="edit_quantity<?= $item['id'] ?>" class="form-label">Quantity</label>
                                                        <input type="number" class="form-control" id="edit_quantity<?= $item['id'] ?>" 
                                                               name="quantity" value="<?= $item['quantity'] ?>" min="0" required>
                                                    </div>
                                                    <div class="mb-3">
                                                        <label for="edit_price<?= $item['id'] ?>" class="form-label">Price</label>
                                                        <input type="number" class="form-control" id="edit_price<?= $item['id'] ?>" 
                                                               name="price" value="<?= $item['price'] ?>" min="0" step="0.01" required>
                                                    </div>
                                                </div>
                                                <div class="modal-footer">
                                                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                                                    <button type="submit" class="btn btn-primary">Save Changes</button>
                                                </div>
                                            </form>
                                        </div>
                                    </div>
                                </div>

                                <!-- Delete Modal -->
                                <div class="modal fade" id="deleteModal<?= $item['id'] ?>" tabindex="-1">
                                    <div class="modal-dialog">
                                        <div class="modal-content">
                                            <div class="modal-header">
                                                <h5 class="modal-title">Confirm Delete</h5>
                                                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                            </div>
                                            <div class="modal-body">
                                                <p>Are you sure you want to delete this inventory record?</p>
                                                <p><strong>Product:</strong> <?= htmlspecialchars($item['product_name']) ?></p>
                                                <p><strong>Store:</strong> <?= htmlspecialchars($item['store_name']) ?></p>
                                                <p><strong>Quantity:</strong> <?= $item['quantity'] ?></p>
                                            </div>
                                            <div class="modal-footer">
                                                <form method="POST">
                                                    <input type="hidden" name="action" value="delete">
                                                    <input type="hidden" name="inventory_id" value="<?= $item['id'] ?>">
                                                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                                                    <button type="submit" class="btn btn-danger">Delete</button>
                                                </form>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            <?php endwhile; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <!-- Edit Name Modal -->
    <div class="modal fade" id="editNameModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Edit Product Name</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST">
                    <div class="modal-body">
                        <input type="hidden" name="action" value="update_product_name">
                        <input type="hidden" name="product_id" id="edit_product_id">
                        <div class="mb-3">
                            <label for="new_name" class="form-label">Product Name</label>
                            <input type="text" class="form-control" id="new_name" name="new_name" required>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary">Save Changes</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.6/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Handle product name edit modal
            const editNameModal = document.getElementById('editNameModal');
            if (editNameModal) {
                editNameModal.addEventListener('show.bs.modal', function(event) {
                    const button = event.relatedTarget;
                    const productId = button.getAttribute('data-product-id');
                    const productName = button.getAttribute('data-product-name');
                    
                    const modalProductId = document.getElementById('edit_product_id');
                    const modalProductName = document.getElementById('new_name');
                    
                    modalProductId.value = productId;
                    modalProductName.value = productName;
                });
            }

            // Handle barcode scanning
            const barcodeInput = document.getElementById('barcode');            const productInfo = document.getElementById('productInfo');
            const productNameSpan = document.getElementById('productName');
            const productSizeSpan = document.getElementById('productSize');
            const productPriceSpan = document.getElementById('productPrice');
            const priceInput = document.getElementById('price');
            let barcodeTimeout;

            barcodeInput.addEventListener('input', function() {
                clearTimeout(barcodeTimeout);
                const barcode = this.value.trim();
                
                if (barcode.length > 0) {
                    barcodeTimeout = setTimeout(() => {
                        fetch('get_product_by_barcode.php?barcode=' + encodeURIComponent(barcode))
                            .then(response => response.json())
                            .then(data => {
                                if (data.success) {
                                    productNameSpan.textContent = data.product.name;
                                    productSizeSpan.textContent = data.product.size;
                                    productPriceSpan.textContent = data.product.price;
                                    priceInput.value = data.product.price;
                                    productInfo.style.display = 'block';
                                } else {
                                    productNameSpan.textContent = 'Product not found';
                                    productSizeSpan.textContent = '';
                                    productPriceSpan.textContent = '0.00';
                                    priceInput.value = '';
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
        });
    </script>
</body>
</html>