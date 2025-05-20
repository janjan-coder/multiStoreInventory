<?php
session_start();
include 'db.php';

// Check if user is logged in
if (!isset($_SESSION['username'])) {
    header("Location: index.php");
    exit();
}

// Initialize store access based on user role
$userRole = $_SESSION['role'] ?? '';
$userStoreId = $_SESSION['store_id'] ?? 0;

// Restrict access to superadmin only
if ($userRole !== 'superadmin') {
    header("Location: dashboard.php");
    exit();
}

// Fetch all stores for the dropdown (superadmin only)
$stores = $conn->query("SELECT id, name FROM stores ORDER BY name");

// Handle batch deletion
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_selected']) && isset($_POST['selected_records'])) {
    $selectedIds = array_map('intval', $_POST['selected_records']);
    
    // Begin transaction
    $conn->begin_transaction();
    
    try {
        // First delete from inventory
        $inventoryStmt = $conn->prepare("DELETE FROM inventory WHERE product_id = ?");
        
        // Then delete from products
        $productStmt = $conn->prepare("DELETE FROM products WHERE id = ?");
        
        $deleteCount = 0;
        foreach ($selectedIds as $id) {
            $inventoryStmt->bind_param("i", $id);
            $inventoryStmt->execute();
            
            $productStmt->bind_param("i", $id);
            if ($productStmt->execute()) {
                $deleteCount++;
            }
        }
        
        // Commit transaction
        $conn->commit();
        
        if ($deleteCount > 0) {
            $success_message = "Successfully deleted $deleteCount products";
        }
    } catch (Exception $e) {
        // Rollback on error
        $conn->rollback();
        $error_message = "Error deleting products: " . $e->getMessage();
    }
}

// Fetch stores for the dropdown
$stores = $conn->query("SELECT id, name FROM stores");

// Handle single product addition
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_product'])) {
    // Retrieve and sanitize input values
    $name = trim($_POST['name'] ?? '');
    $barcode = trim($_POST['barcode'] ?? '');
    $size = trim($_POST['size'] ?? '');
    $store_id = isset($_POST['store_id']) ? (int)$_POST['store_id'] : 0;
    $quantity = isset($_POST['quantity']) ? (int)$_POST['quantity'] : 0;

    // Validate input
    
    // Handle price with proper decimal formatting
    $price = isset($_POST['price']) ? str_replace(',', '.', $_POST['price']) : '0.00';
    $price = number_format((float)$price, 2, '.', '');
    
    // Validate input
    if (empty($name) || empty($barcode) || empty($size) || $store_id <= 0 || $quantity <= 0 || (float)$price <= 0) {
        $error_message = "All fields are required and must be valid.";
    } else {
        // First check if product with this barcode and size exists
        $check = $conn->prepare("SELECT p.id FROM products p WHERE p.barcode = ? AND p.size = ?");
        $check->bind_param("ss", $barcode, $size);
        $check->execute();
        $result = $check->get_result();
        
        if ($result->num_rows > 0) {
            // Product with this barcode and size exists
            $product_id = $result->fetch_assoc()['id'];
            
            // Check if this product is already in the target store
            $store_check = $conn->prepare("SELECT id FROM inventory WHERE product_id = ? AND store_id = ?");
            $store_check->bind_param("ii", $product_id, $store_id);
            $store_check->execute();
            $store_result = $store_check->get_result();
            
            if ($store_result->num_rows > 0) {
                $error_message = "This product with the same size already exists in the selected store.";
            } else {
                // Add inventory record for existing product with price
                $inventory_stmt = $conn->prepare("INSERT INTO inventory (product_id, store_id, quantity, price) VALUES (?, ?, ?, CAST(? AS DECIMAL(10,2)))");
                $inventory_stmt->bind_param("iiis", $product_id, $store_id, $quantity, $price);
                
                if ($inventory_stmt->execute()) {
                    $success_message = "Product added to store successfully.";
                } else {
                    $error_message = "Error adding inventory: " . $inventory_stmt->error;
                }
            }
        } else {
            // Insert new product (without price)
            $stmt = $conn->prepare("INSERT INTO products (name, barcode, size) VALUES (?, ?, ?)");
            $stmt->bind_param("sss", $name, $barcode, $size);
            
            if ($stmt->execute()) {
                $product_id = $conn->insert_id;
                
                // Insert inventory with price
                $inventory_stmt = $conn->prepare("INSERT INTO inventory (product_id, store_id, quantity, price) VALUES (?, ?, ?, CAST(? AS DECIMAL(10,2)))");
                $inventory_stmt->bind_param("iiis", $product_id, $store_id, $quantity, $price);
                
                if ($inventory_stmt->execute()) {
                    $success_message = "Product added successfully.";
                } else {
                    $error_message = "Error adding inventory: " . $inventory_stmt->error;
                }
            } else {
                if ($stmt->errno == 1062) {
                    // Duplicate entry error
                    $error_message = "A product with this barcode and size combination already exists.";
                } else {
                    $error_message = "Error adding product: " . $stmt->error;
                }
            }
        }
    }
}

// Handle bulk import
if (isset($_POST['bulk_import']) && isset($_FILES['csv_file'])) {
    $file = $_FILES['csv_file'];
    
    // Check for upload errors
    if ($file['error'] !== UPLOAD_ERR_OK) {
        $error_message = "File upload failed. Error code: " . $file['error'];
    } else {
        // Check file type
        $fileType = mime_content_type($file['tmp_name']);
        if ($fileType !== 'text/csv' && $fileType !== 'text/plain') {
            $error_message = "Invalid file type. Please upload a CSV file.";
        } else {
            // Open the file
            $handle = fopen($file['tmp_name'], 'r');
            
            // Skip UTF-8 BOM if present
            $bom = fread($handle, 3);
            if ($bom !== chr(0xEF).chr(0xBB).chr(0xBF)) {
                // If not BOM, rewind to start
                rewind($handle);
            }
            
            // Skip header row
            fgetcsv($handle);
            
            $successCount = 0;
            $errorCount = 0;
            $updateCount = 0;
            $errors = [];
            
            // Process each row
            while (($data = fgetcsv($handle)) !== FALSE) {
                if (count($data) < 5) {
                    $errorCount++;
                    $errors[] = "Row " . ($successCount + $errorCount) . ": Invalid data format";
                    continue;
                }
                
                $name = trim($data[0]);
                $barcode = trim($data[1]);
                $size = trim($data[2]);
                $storeName = trim($data[3]);
                $quantity = (int)$data[4];
                $price = isset($data[5]) ? number_format((float)$data[5], 2, '.', '') : '0.00';
                
                // Validate data
                if (empty($name) || empty($barcode) || empty($size) || empty($storeName) || $quantity <= 0 || (float)$price <= 0) {
                    $errorCount++;
                    $errors[] = "Row " . ($successCount + $errorCount) . ": Missing or invalid required fields";
                    continue;
                }
                  // Get store ID from store name
                $storeStmt = $conn->prepare("SELECT id FROM stores WHERE name = ?");
                $storeStmt->bind_param("s", $storeName);
                $storeStmt->execute();
                $storeResult = $storeStmt->get_result();
                
                if ($storeResult->num_rows === 0) {
                    $errorCount++;
                    $errors[] = "Row " . ($successCount + $errorCount) . ": Store '$storeName' not found";
                    continue;
                }
                
                $storeId = $storeResult->fetch_assoc()['id'];
                  // Store ID validation already done by superadmin check at the top of the file
                
                // Check if product exists with same barcode and size in any store
                $checkStmt = $conn->prepare("SELECT p.id FROM products p WHERE p.barcode = ? AND p.size = ?");
                $checkStmt->bind_param("ss", $barcode, $size);
                $checkStmt->execute();
                $checkResult = $checkStmt->get_result();
                
                if ($checkResult->num_rows > 0) {
                    // Product exists with this barcode and size, get its ID
                    $productId = $checkResult->fetch_assoc()['id'];
                    
                    // Check if this product is already in the target store
                    $storeCheck = $conn->prepare("SELECT id, quantity FROM inventory WHERE product_id = ? AND store_id = ?");
                    $storeCheck->bind_param("ii", $productId, $storeId);
                    $storeCheck->execute();
                    $storeResult = $storeCheck->get_result();
                    
                    if ($storeResult->num_rows > 0) {
                        // Update quantity and price in this store
                        $existingInventory = $storeResult->fetch_assoc();
                        $newQuantity = $existingInventory['quantity'] + $quantity;
                        
                        $updateStmt = $conn->prepare("UPDATE inventory SET quantity = ?, price = CAST(? AS DECIMAL(10,2)) WHERE id = ?");
                        $updateStmt->bind_param("isi", $newQuantity, $price, $existingInventory['id']);
                        
                        if ($updateStmt->execute()) {
                            $updateCount++;
                        } else {
                            $errorCount++;
                            $errors[] = "Row " . ($successCount + $errorCount) . ": Failed to update quantity and price - " . $updateStmt->error;
                        }
                    } else {
                        // Add inventory record for existing product in new store
                        $inventoryStmt = $conn->prepare("INSERT INTO inventory (product_id, store_id, quantity, price) VALUES (?, ?, ?, CAST(? AS DECIMAL(10,2)))");
                        $inventoryStmt->bind_param("iiis", $productId, $storeId, $quantity, $price);
                        
                        if ($inventoryStmt->execute()) {
                            $successCount++;
                        } else {
                            $errorCount++;
                            $errors[] = "Row " . ($successCount + $errorCount) . ": Failed to add inventory - " . $inventoryStmt->error;
                        }
                    }
                } else {
                    // New product, insert both product and inventory
                    $productStmt = $conn->prepare("INSERT INTO products (name, barcode, size) VALUES (?, ?, ?)");
                    $productStmt->bind_param("sss", $name, $barcode, $size);
                    
                    if ($productStmt->execute()) {
                        $productId = $conn->insert_id;                        $inventoryStmt = $conn->prepare("INSERT INTO inventory (product_id, store_id, quantity, price) VALUES (?, ?, ?, CAST(? AS DECIMAL(10,2)))");
                        $inventoryStmt->bind_param("iiis", $productId, $storeId, $quantity, $price);
                        
                        if ($inventoryStmt->execute()) {
                            $successCount++;
                        } else {
                            $errorCount++;
                            $errors[] = "Row " . ($successCount + $errorCount) . ": Failed to add inventory - " . $inventoryStmt->error;
                        }
                    } else {
                        $errorCount++;
                        $errors[] = "Row " . ($successCount + $errorCount) . ": Failed to add product - " . $productStmt->error;
                    }
                }
            }
            
            fclose($handle);
            
            $messages = [];
            if ($successCount > 0) {
                $messages[] = "Successfully added $successCount new products.";
            }
            if ($updateCount > 0) {
                $messages[] = "Updated quantity for $updateCount existing products.";
            }
            if ($errorCount > 0) {
                $messages[] = "Failed to process $errorCount products. " . implode("<br>", $errors);
            }
            
            if (!empty($messages)) {
                $success_message = implode("<br>", $messages);
            }
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Add Product - Inventory Management</title>
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
            max-width: 800px;
            margin: 2rem auto;
        }

        .card {
            background: #fff;
            border-radius: 1rem;
            box-shadow: 0 0.5rem 1rem rgba(0, 0, 0, 0.15);
            border: none;
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

        .form-label {
            font-weight: 600;
            color: var(--secondary-color);
            margin-bottom: 0.5rem;
        }

        .form-control {
            border-radius: 0.5rem;
            padding: 0.75rem 1rem;
            border: 1px solid #d1d3e2;
            transition: all 0.2s ease-in-out;
        }

        .form-control:focus {
            border-color: var(--primary-color);
            box-shadow: 0 0 0 0.25rem rgba(78, 115, 223, 0.25);
        }

        .btn-primary {
            background-color: var(--primary-color);
            border-color: var(--primary-color);
            padding: 0.75rem 1.5rem;
            font-weight: 600;
            border-radius: 0.5rem;
            transition: all 0.2s ease-in-out;
        }

        .btn-primary:hover {
            background-color: #2e59d9;
            border-color: #2e59d9;
            transform: translateY(-1px);
        }

        .alert {
            border-radius: 0.5rem;
            margin-bottom: 1rem;
        }

        .back-link {
            color: var(--primary-color);
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            margin-bottom: 1rem;
            font-weight: 600;
            transition: all 0.2s ease-in-out;
        }

        .back-link:hover {
            color: #2e59d9;
            transform: translateX(-5px);
        }
        .table th {
            background-color: #f8f9fa;
        }
        .checkbox-column {
            width: 50px;
            text-align: center;
        }
        .loading-overlay {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(255, 255, 255, 0.8);
            z-index: 9999;
            justify-content: center;
            align-items: center;
        }
        .table-primary {
            --bs-table-bg: rgba(13, 110, 253, 0.1);
            --bs-table-striped-bg: rgba(13, 110, 253, 0.15);
        }
        .table-primary>td {
            background-color: var(--bs-table-bg);
        }
        .table-striped>tbody>tr.table-primary:nth-of-type(odd)>* {
            background-color: var(--bs-table-striped-bg);
        }
    </style>
    
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const selectAllCheckbox = document.getElementById('selectAll');
            const recordCheckboxes = document.getElementsByClassName('record-checkbox');
            const deleteButton = document.getElementById('deleteSelected');

            // Handle "Select All" checkbox
            selectAllCheckbox?.addEventListener('change', function() {
                Array.from(recordCheckboxes).forEach(checkbox => {
                    checkbox.checked = selectAllCheckbox.checked;
                });
                updateDeleteButton();
            });

            // Handle individual checkboxes
            Array.from(recordCheckboxes).forEach(checkbox => {
                checkbox.addEventListener('change', function() {
                    updateDeleteButton();
                    const allChecked = Array.from(recordCheckboxes).every(cb => cb.checked);
                    if (selectAllCheckbox) {
                        selectAllCheckbox.checked = allChecked;
                    }
                });
            });

            // Update delete button state
            function updateDeleteButton() {
                const checkedCount = Array.from(recordCheckboxes).filter(cb => cb.checked).length;
                if (deleteButton) {
                    deleteButton.disabled = checkedCount === 0;
                    deleteButton.textContent = checkedCount > 0 
                        ? `Delete Selected (${checkedCount})` 
                        : 'Delete Selected';
                }
            }

            // Handle delete form submission
            const deleteForm = document.getElementById('deleteForm');
            deleteForm?.addEventListener('submit', function(e) {
                const checkedBoxes = document.querySelectorAll('.record-checkbox:checked');
                if (checkedBoxes.length === 0) {
                    e.preventDefault();
                    alert('Please select at least one item to delete.');
                    return;
                }

                if (!confirm(`Are you sure you want to delete ${checkedBoxes.length} item(s)?`)) {
                    e.preventDefault();
                    return;
                }
            });
        });
    </script>
</head>
<body>
    <div id="loadingOverlay" class="loading-overlay">
        <div class="spinner-border text-primary" role="status">
            <span class="visually-hidden">Loading...</span>
        </div>
    </div>
    
    <div class="container">
        <a href="dashboard.php" class="back-link">
            <i class="bi bi-arrow-left me-2"></i>Back to Dashboard
        </a>

        <div class="card">
            <div class="card-header">
                <h3><i class="bi bi-plus-circle me-2"></i>Add New Product</h3>
            </div>
            <div class="card-body">
                <?php if (isset($success_message)): ?>
                    <div class="alert alert-success" role="alert">
                        <i class="bi bi-check-circle me-2"></i><?php echo $success_message; ?>
                    </div>
                <?php endif; ?>

                <?php if (isset($error_message)): ?>
                    <div class="alert alert-danger" role="alert">
                        <i class="bi bi-exclamation-circle me-2"></i><?php echo $error_message; ?>
                    </div>
                <?php endif; ?>                <!-- Excel Upload Form -->
                <div class="mb-4">
                    <h4>Import Products from CSV</h4>
                    <p class="text-muted">Upload a CSV file with the following columns:</p>
                    <ul class="text-muted">
                        <li>Column A: Product Name (required)</li>
                        <li>Column B: Barcode (required)</li>
                        <li>Column C: Size (required)</li>
                        <li>Column D: Store Name (required)</li>
                        <li>Column E: Quantity (required)</li>
                    </ul>
                    <div class="mb-3">
                        <a href="download_template.php" class="btn btn-info">
                            <i class="bi bi-download me-2"></i>Download CSV Template
                        </a>
                    </div>
                    <form id="csvForm" method="POST" enctype="multipart/form-data">
                        <div class="mb-3">
                            <label for="csv_file" class="form-label">Upload CSV File</label>
                            <div class="input-group">
                                <input type="file" class="form-control" id="csv_file" name="csv_file" accept=".csv">
                                <button type="submit" class="btn btn-primary" name="bulk_import">
                                    <i class="bi bi-upload me-2"></i>Upload CSV
                                </button>
                            </div>
                            <div class="form-text">Select a CSV file and click Upload to import products.</div>
                        </div>
                    </form>
                    <hr>
                </div>

                <!-- Add loading overlay -->
                <div id="loadingOverlay" style="display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); z-index: 9999;">
                    <div style="position: absolute; top: 50%; left: 50%; transform: translate(-50%, -50%); text-align: center; color: white;">
                        <div class="spinner-border" role="status">
                            <span class="visually-hidden">Loading...</span>
                        </div>
                        <div class="mt-2">Processing CSV file...</div>
                    </div>
                </div>

                <!-- Single Product Form -->
                <h4>Add Single Product</h4>
                <form method="POST">
                    <div class="mb-3">
                        <label for="name" class="form-label">Product Name</label>
                        <input type="text" class="form-control" id="name" name="name" required>
                    </div>
                    <div class="mb-3">
                        <label for="barcode" class="form-label">Barcode</label>
                        <input type="text" class="form-control" id="barcode" name="barcode" required>
                    </div>
                    <div class="mb-3">
                        <label for="size" class="form-label">Size</label>
                        <input type="text" class="form-control" id="size" name="size" required>
                    </div>
                    <div class="mb-3">
                        <label for="store_id" class="form-label">Store</label>
                        <select class="form-select" id="store_id" name="store_id" required>
                            <option value="">Select a store</option>
                            <?php
                            $stores = $conn->query("SELECT id, name FROM stores ORDER BY name");
                            while ($store = $stores->fetch_assoc()) {
                                echo "<option value='{$store['id']}'>{$store['name']}</option>";
                            }
                            ?>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label for="quantity" class="form-label">Initial Quantity</label>
                        <input type="number" class="form-control" id="quantity" name="quantity" min="1" required>
                    </div>
                    <div class="mb-3">
                        <label for="price" class="form-label">Price</label>
                        <div class="input-group">
                            <span class="input-group-text">₱</span>
                            <input type="number" class="form-control" id="price" name="price" min="0" step="0.01" required>
                        </div>
                    </div>
                    <div class="d-grid">
                        <button type="submit" name="add_product" class="btn btn-primary">
                            <i class="bi bi-plus-circle me-2"></i>Add Product
                        </button>
                    </div>                </form>                <hr class="my-4">
                
                <!-- Product List Table -->
                <h4 class="d-flex justify-content-between align-items-center">
                    <span>Manage Products</span>
                    <div class="btn-group">
                        <button type="button" class="btn btn-outline-primary" id="markSelected" disabled>
                            <i class="bi bi-check2-circle me-2"></i>Mark
                        </button>
                        <button type="submit" form="deleteForm" name="delete_selected" class="btn btn-danger" id="deleteSelected" disabled>
                            <i class="bi bi-trash me-2"></i>Delete Selected
                        </button>
                    </div>
                </h4>
                <form method="POST" id="deleteForm">
                    <div class="table-responsive">
                    <?php
                    // Fetch all products with their inventory information
                    $query = "SELECT p.id, p.name, p.barcode, p.size, GROUP_CONCAT(CONCAT(s.name, ': ', i.quantity) SEPARATOR ', ') as store_quantities
                            FROM products p
                            LEFT JOIN inventory i ON p.id = i.product_id
                            LEFT JOIN stores s ON i.store_id = s.id
                            GROUP BY p.id, p.name, p.barcode, p.size
                            ORDER BY p.name";
                    $result = $conn->query($query);
                    
                    if ($result && $result->num_rows > 0): ?>                    <table class="table table-striped">
                        <thead>
                            <tr>
                                <th class="text-center" style="width: 100px;">
                                    <div class="d-flex justify-content-center align-items-center">
                                        <input type="checkbox" id="selectAll" class="form-check-input me-2">
                                        <button type="button" class="btn btn-sm btn-outline-primary" id="markSelected" disabled>
                                            Mark
                                        </button>
                                    </div>
                                </th>
                                <th>Name</th>
                                <th>Barcode</th>
                                <th>Size</th>
                                <th>Store Quantities</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php while ($row = $result->fetch_assoc()): ?>
                            <tr>
                                <td class="text-center">
                                    <input type="checkbox" name="selected_records[]" value="<?php echo $row['id']; ?>" 
                                           class="form-check-input record-checkbox">
                                <td><?php echo htmlspecialchars($row['name']); ?></td>
                                <td><?php echo htmlspecialchars($row['barcode']); ?></td>
                                <td><?php echo htmlspecialchars($row['size']); ?></td>
                                <td><?php echo htmlspecialchars($row['store_quantities']); ?></td>
                            </tr>
                            <?php endwhile; ?>
                        </tbody>
                    </table>
                    <?php else: ?>
                    <p class="text-muted">No products found.</p>
                    <?php endif; ?>                </div>
                </form>
            </div>
        </div>
    </div>    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.6/dist/js/bootstrap.bundle.min.js"></script>
    <script>
    document.addEventListener('DOMContentLoaded', function() {
        // Get all required elements
        const markButton = document.getElementById('markSelected');
        const deleteButton = document.getElementById('deleteSelected');
        const selectAllCheckbox = document.getElementById('selectAll');
        const recordCheckboxes = document.getElementsByClassName('record-checkbox');
        const deleteForm = document.getElementById('deleteForm');

        // Handle checkbox selection
        function updateButtons() {
            const checkedCount = document.querySelectorAll('.record-checkbox:checked').length;
            markButton.disabled = checkedCount === 0;
            deleteButton.disabled = checkedCount === 0;
            deleteButton.innerHTML = checkedCount > 0 
                ? `<i class="bi bi-trash me-2"></i>Delete Selected (${checkedCount})` 
                : '<i class="bi bi-trash me-2"></i>Delete Selected';
        }

        // "Select All" checkbox
        selectAllCheckbox?.addEventListener('change', function() {
            Array.from(recordCheckboxes).forEach(checkbox => {
                checkbox.checked = selectAllCheckbox.checked;
            });
            updateButtons();
        });

        // Individual checkboxes
        Array.from(recordCheckboxes).forEach(checkbox => {
            checkbox.addEventListener('change', function() {
                updateButtons();
                const allChecked = Array.from(recordCheckboxes).every(cb => cb.checked);
                if (selectAllCheckbox) {
                    selectAllCheckbox.checked = allChecked;
                }
            });
        });

        // Mark button
        markButton?.addEventListener('click', function() {
            const checkedBoxes = document.querySelectorAll('.record-checkbox:checked');
            if (checkedBoxes.length === 0) {
                alert('Please select at least one item to mark.');
                return;
            }
            checkedBoxes.forEach(checkbox => {
                const row = checkbox.closest('tr');
                row.classList.toggle('table-primary');
            });
        });        // CSV form submission
        const csvForm = document.getElementById('csvForm');
        if (csvForm) {
            csvForm.addEventListener('submit', function(e) {
                const fileInput = document.getElementById('csv_file');
                if (fileInput.files.length > 0) {
                    document.getElementById('loadingOverlay').style.display = 'block';
                } else {
                    e.preventDefault();
                    alert('Please select a CSV file first.');
                }
            });
        }

        // Delete form submission
        if (deleteForm) {
            deleteForm.addEventListener('submit', function(e) {
                e.preventDefault();
                const checkedBoxes = document.querySelectorAll('.record-checkbox:checked');
                if (checkedBoxes.length === 0) {
                    alert('Please select at least one item to delete.');
                    return;
                }
                if (confirm(`Are you sure you want to delete ${checkedBoxes.length} item(s)?`)) {
                    this.submit();
                }
            });
        }
    });
    </script>
</body>
</html>
