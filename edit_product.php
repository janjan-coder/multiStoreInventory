<?php
session_start();
include 'db.php';

// Add debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Test database connection
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
} else {
    echo "<!-- Database connection successful -->";
}

// Initialize variables
$product = null;
$search_error = '';
$success = '';
$error = '';

// Debugging
function debug($data) {
    echo "<pre>";
    var_dump($data);
    echo "</pre>";
}

// Handle search functionality
if (isset($_POST['search_product'])) {
    $search_barcode = $conn->real_escape_string(trim($_POST['search_barcode']));

    // Search for the product using the barcode
    $sql = "SELECT * FROM products WHERE barcode = '$search_barcode'";
    $result = $conn->query($sql);

    // Debugging
    if (!$result) {
        $error = "Error with the query: " . $conn->error;
    } else {
        if ($result->num_rows > 0) {
            $product = $result->fetch_assoc();
            // Store product in session
            $_SESSION['current_product'] = $product;
        } else {
            $search_error = "Product not found.";
        }
    }
}

// Handle form submission for updating the product details
if (isset($_POST['update_product'])) {
    // Get product from session if not in current scope
    if (!isset($product) && isset($_SESSION['current_product'])) {
        $product = $_SESSION['current_product'];
    }

    if (!isset($product)) {
        $error = "No product selected for update. Please search for a product first.";
    } else {
        // Get the updated values
        $barcode = $conn->real_escape_string(trim($_POST['barcode']));
        $product_name = $conn->real_escape_string(trim($_POST['product_name']));
        $store_id = (int)$_POST['store_id'];
        $quantity = (int)$_POST['quantity'];
        $size = $conn->real_escape_string(trim($_POST['size']));

        // Debugging
        echo "<div style='background: #f8f9fa; padding: 15px; margin: 15px 0; border: 1px solid #ddd;'>";
        echo "<h3>Debug Information:</h3>";
        echo "<strong>POST data:</strong><br>";
        debug($_POST);
        echo "<strong>Current product:</strong><br>";
        debug($product);
        echo "<strong>Session data:</strong><br>";
        debug($_SESSION);

        // Update the product in the database
        $update_sql = "UPDATE products SET barcode = '$barcode', product_name = '$product_name', store_id = '$store_id', quantity = '$quantity', size = '$size' WHERE id = {$product['id']}";

        echo "<strong>SQL Query:</strong><br>";
        echo $update_sql;

        $result = $conn->query($update_sql);
        if ($result === TRUE) {
            $success = "Product updated successfully!";
            echo "<div style='color: green; margin-top: 10px;'>Update successful!</div>";
            // Clear the session product
            unset($_SESSION['current_product']);
            // Only redirect if there's no error
            if (empty($error)) {
                header("Location: view_inventory.php");
                exit;
            }
        } else {
            $error = "Error updating product: " . $conn->error;
            echo "<div style='color: red; margin-top: 10px;'>Error: " . $conn->error . "</div>";
        }
        echo "</div>";
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Product</title>
    <!-- Bootstrap CSS -->
    <link href="https://stackpath.bootstrapcdn.com/bootstrap/4.3.1/css/bootstrap.min.css" rel="stylesheet">
</head>
<body>
    <div class="container mt-5">
        <h2>Edit Product Details</h2>

        <!-- Search Form -->
        <form action="" method="POST" class="mb-4">
            <div class="form-row">
                <div class="col-md-8">
                    <input type="text" class="form-control" name="search_barcode" placeholder="Enter product barcode" required>
                </div>
                <div class="col-md-4">
                    <button type="submit" name="search_product" class="btn btn-primary w-100">Search</button>
                </div>
            </div>
        </form>

        <?php if ($search_error): ?>
            <div class="alert alert-danger"><?= $search_error ?></div>
        <?php endif; ?>

        <?php if ($product): ?>
            <!-- Update Product Form -->
            <form action="" method="POST">
                <?php if (isset($error)) { echo "<div class='alert alert-danger'>$error</div>"; } ?>
                <?php if (isset($success)) { echo "<div class='alert alert-success'>$success</div>"; } ?>

                <div class="form-group">
                    <label for="barcode">Barcode:</label>
                    <input type="text" class="form-control" name="barcode" id="barcode" value="<?= htmlspecialchars($product['barcode']); ?>" required>
                </div>

                <div class="form-group">
                    <label for="product_name">Product Name (Optional):</label>
                    <input type="text" class="form-control" name="product_name" id="product_name" value="<?= htmlspecialchars($product['product_name']); ?>">
                </div>

                <div class="form-group">
                    <label for="store_id">Store:</label>
                    <select class="form-control" name="store_id" id="store_id" required>
                        <option value="">Select Store</option>
                        <?php
                        // Fetch all stores for the dropdown
                        $store_result = $conn->query("SELECT id, name FROM stores");
                        while ($store = $store_result->fetch_assoc()) {
                            // Mark the store as selected if it matches the current product's store_id
                            $selected = ($store['id'] == $product['store_id']) ? 'selected' : '';
                            echo "<option value='{$store['id']}' {$selected}>{$store['name']}</option>";
                        }
                        ?>
                    </select>
                </div>

                <div class="form-group">
                    <label for="quantity">Quantity:</label>
                    <input type="number" class="form-control" name="quantity" id="quantity" value="<?= htmlspecialchars($product['quantity']); ?>" required>
                </div>

                <div class="form-group">
                    <label for="size">Size (Optional):</label>
                    <input type="text" class="form-control" name="size" id="size" value="<?= htmlspecialchars($product['size']); ?>">
                </div>

                <button type="submit" name="update_product" class="btn btn-primary">Update Product</button>
            </form>
        <?php endif; ?>

    </div>

    <!-- Bootstrap JS and dependencies -->
    <script src="https://code.jquery.com/jquery-3.5.1.slim.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/@popperjs/core@2.9.2/dist/umd/popper.min.js"></script>
    <script src="https://stackpath.bootstrapcdn.com/bootstrap/4.3.1/js/bootstrap.min.js"></script>
</body>
</html>
