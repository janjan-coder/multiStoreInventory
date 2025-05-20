<?php
session_start();
include 'db.php';

// Check if user is logged in
if (!isset($_SESSION['username'])) {
    header("Location: index.php");
    exit();
}

$success_message = '';
$error_message = '';

if (isset($_GET['barcode'])) {
    $barcode = $conn->real_escape_string($_GET['barcode']);
    $query = "SELECT p.*, s.name as store_name FROM products p 
              JOIN stores s ON p.store_id = s.id 
              WHERE p.barcode = '$barcode'";
    $result = $conn->query($query);

    if ($result->num_rows > 0) {
        $product = $result->fetch_assoc();
    } else {
        $error_message = "Product not found.";
    }
}

if (isset($_POST['update'])) {
    $barcode = $conn->real_escape_string($_POST['barcode']);
    $add_quantity = (int)$_POST['add_quantity'];

    if ($add_quantity <= 0) {
        $error_message = "Please enter a positive quantity.";
    } else {
        // Update by adding to existing quantity
        $sql = "UPDATE products SET quantity = quantity + $add_quantity WHERE barcode = '$barcode'";

        if ($conn->query($sql)) {
            $success_message = "Quantity successfully increased by $add_quantity!";
            // Refresh product data
            $query = "SELECT p.*, s.name as store_name FROM products p 
                     JOIN stores s ON p.store_id = s.id 
                     WHERE p.barcode = '$barcode'";
            $result = $conn->query($query);
            $product = $result->fetch_assoc();
        } else {
            $error_message = "Error updating quantity: " . $conn->error;
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Update Inventory - Inventory Management</title>
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

        .form-control:read-only {
            background-color: #f8f9fc;
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

        .form-label {
            font-weight: 600;
            color: var(--secondary-color);
            margin-bottom: 0.5rem;
        }

        .product-info {
            background-color: #f8f9fc;
            border-radius: 0.5rem;
            padding: 1.5rem;
            margin-bottom: 1.5rem;
        }

        .product-info p {
            margin-bottom: 0.5rem;
            color: var(--secondary-color);
        }

        .product-info strong {
            color: var(--primary-color);
        }

        .quantity-badge {
            font-size: 1.1rem;
            padding: 0.5rem 1rem;
        }
    </style>
</head>
<body>
    <div class="container">
        <a href="view_inventory.php" class="back-link">
            <i class="bi bi-arrow-left me-2"></i>Back to Inventory
        </a>

        <div class="card">
            <div class="card-header">
                <h3><i class="bi bi-box-seam me-2"></i>Update Inventory</h3>
            </div>
            <div class="card-body">
                <?php if ($error_message): ?>
                    <div class="alert alert-danger" role="alert">
                        <i class="bi bi-exclamation-circle me-2"></i><?php echo $error_message; ?>
                    </div>
                <?php endif; ?>

                <?php if ($success_message): ?>
                    <div class="alert alert-success" role="alert">
                        <i class="bi bi-check-circle me-2"></i><?php echo $success_message; ?>
                    </div>
                <?php endif; ?>

                <?php if (isset($product)): ?>
                    <div class="product-info">
                        <p><strong>Barcode:</strong> <?php echo htmlspecialchars($product['barcode']); ?></p>
                        <p><strong>Product Name:</strong> <?php echo htmlspecialchars($product['product_name']); ?></p>
                        <p><strong>Store:</strong> <?php echo htmlspecialchars($product['store_name']); ?></p>
                        <p><strong>Current Quantity:</strong> 
                            <span class="badge bg-<?php echo $product['quantity'] < 10 ? 'danger' : 'success'; ?> quantity-badge">
                                <?php echo htmlspecialchars($product['quantity']); ?>
                            </span>
                        </p>
                    </div>

                    <form method="POST" action="">
                        <input type="hidden" name="barcode" value="<?php echo htmlspecialchars($product['barcode']); ?>">
                        
                        <div class="mb-4">
                            <label for="add_quantity" class="form-label">Quantity to Add</label>
                            <div class="input-group">
                                <span class="input-group-text">
                                    <i class="bi bi-plus-circle"></i>
                                </span>
                                <input type="number" class="form-control" id="add_quantity" name="add_quantity" 
                                       value="0" min="1" required>
                            </div>
                        </div>

                        <div class="d-grid">
                            <button type="submit" name="update" class="btn btn-primary">
                                <i class="bi bi-plus-circle me-2"></i>Update Quantity
                            </button>
                        </div>
                    </form>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.6/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
