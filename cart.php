<?php
session_start();
require_once 'db.php';

// Check if user is logged in
if (!isset($_SESSION['customer_id'])) {
    header("Location: login.php?redirect=cart.php");
    exit();
}

$customer_id = $_SESSION['customer_id'];

// Handle quantity updates
if (isset($_POST['update_quantity'])) {
    $cart_id = intval($_POST['cart_id']);
    $quantity = intval($_POST['quantity']);
    
    if ($quantity > 0) {
        $sql = "UPDATE cart SET quantity = $quantity WHERE id = $cart_id AND customer_id = $customer_id";
        $conn->query($sql);
    }
}

// Handle item removal
if (isset($_POST['remove_item'])) {
    $cart_id = intval($_POST['cart_id']);
    $sql = "DELETE FROM cart WHERE id = $cart_id AND customer_id = $customer_id";
    $conn->query($sql);
}

// Fetch cart items with product details
$sql = "SELECT c.*, p.name, p.barcode,
        (SELECT price FROM product_prices WHERE product_id = p.id ORDER BY effective_date DESC LIMIT 1) as price,
        (SELECT image_url FROM product_images WHERE product_id = p.id AND is_primary = 1 LIMIT 1) as image_url
        FROM cart c
        JOIN products p ON c.product_id = p.id
        WHERE c.customer_id = $customer_id
        ORDER BY c.created_at DESC";
$cart_items = $conn->query($sql);

// Calculate totals
$subtotal = 0;
$items_count = 0;
while ($item = $cart_items->fetch_assoc()) {
    $subtotal += $item['price'] * $item['quantity'];
    $items_count += $item['quantity'];
}
$shipping = $items_count > 0 ? 10.00 : 0; // Example shipping cost
$total = $subtotal + $shipping;

// Reset the result pointer
$cart_items->data_seek(0);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Shopping Cart - E-commerce Store</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.6/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    <style>
        :root {
            --primary-color: #4e73df;
            --secondary-color: #858796;
            --success-color: #1cc88a;
        }

        body {
            font-family: 'Nunito', -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif;
            background-color: #f8f9fc;
        }

        .cart-item {
            background: white;
            border-radius: 0.5rem;
            box-shadow: 0 0.15rem 1.75rem 0 rgba(58, 59, 69, 0.15);
            transition: all 0.3s ease;
        }

        .cart-item:hover {
            transform: translateY(-2px);
        }

        .product-image {
            width: 100px;
            height: 100px;
            object-fit: cover;
            border-radius: 0.35rem;
        }

        .quantity-input {
            width: 80px;
        }

        .summary-card {
            background: white;
            border-radius: 0.5rem;
            box-shadow: 0 0.15rem 1.75rem 0 rgba(58, 59, 69, 0.15);
        }

        .btn-primary {
            background-color: var(--primary-color);
            border-color: var(--primary-color);
        }

        .btn-primary:hover {
            background-color: #2e59d9;
            border-color: #2e59d9;
        }
    </style>
</head>
<body>
    <!-- Navigation -->
    <nav class="navbar navbar-expand-lg navbar-light bg-white shadow-sm">
        <div class="container">
            <a class="navbar-brand" href="index.php">
                <i class="bi bi-shop me-2"></i>E-Commerce Store
            </a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav me-auto">
                    <li class="nav-item">
                        <a class="nav-link" href="products.php">All Products</a>
                    </li>
                </ul>
                <div class="d-flex align-items-center">
                    <a href="cart.php" class="btn btn-outline-primary me-2">
                        <i class="bi bi-cart"></i>
                        <span class="badge bg-primary"><?php echo $items_count; ?></span>
                    </a>
                    <a href="account.php" class="btn btn-outline-primary me-2">My Account</a>
                    <a href="logout.php" class="btn btn-outline-danger">Logout</a>
                </div>
            </div>
        </div>
    </nav>

    <div class="container py-4">
        <h1 class="mb-4">Shopping Cart</h1>

        <?php if ($cart_items->num_rows > 0): ?>
        <div class="row">
            <!-- Cart Items -->
            <div class="col-lg-8">
                <?php while ($item = $cart_items->fetch_assoc()): ?>
                <div class="cart-item mb-3">
                    <div class="p-3">
                        <div class="row align-items-center">
                            <div class="col-auto">
                                <img src="<?php echo $item['image_url'] ?? 'images/products/default.jpg'; ?>" 
                                     class="product-image" 
                                     alt="<?php echo htmlspecialchars($item['name']); ?>">
                            </div>
                            <div class="col">
                                <h5 class="mb-1"><?php echo htmlspecialchars($item['name']); ?></h5>
                                <p class="text-muted mb-0">Barcode: <?php echo htmlspecialchars($item['barcode']); ?></p>
                                <p class="text-primary mb-0">$<?php echo number_format($item['price'], 2); ?></p>
                            </div>
                            <div class="col-auto">
                                <form method="POST" class="d-flex align-items-center">
                                    <input type="hidden" name="cart_id" value="<?php echo $item['id']; ?>">
                                    <input type="number" class="form-control quantity-input me-2" 
                                           name="quantity" value="<?php echo $item['quantity']; ?>" 
                                           min="1" onchange="this.form.submit()">
                                    <button type="submit" name="update_quantity" class="btn btn-outline-primary me-2">
                                        <i class="bi bi-arrow-clockwise"></i>
                                    </button>
                                    <button type="submit" name="remove_item" class="btn btn-outline-danger">
                                        <i class="bi bi-trash"></i>
                                    </button>
                                </form>
                            </div>
                            <div class="col-auto text-end">
                                <h5 class="mb-0">$<?php echo number_format($item['price'] * $item['quantity'], 2); ?></h5>
                            </div>
                        </div>
                    </div>
                </div>
                <?php endwhile; ?>
            </div>

            <!-- Order Summary -->
            <div class="col-lg-4">
                <div class="summary-card p-4">
                    <h4 class="mb-4">Order Summary</h4>
                    
                    <div class="d-flex justify-content-between mb-2">
                        <span>Subtotal (<?php echo $items_count; ?> items)</span>
                        <span>$<?php echo number_format($subtotal, 2); ?></span>
                    </div>
                    
                    <div class="d-flex justify-content-between mb-2">
                        <span>Shipping</span>
                        <span>$<?php echo number_format($shipping, 2); ?></span>
                    </div>
                    
                    <hr>
                    
                    <div class="d-flex justify-content-between mb-4">
                        <strong>Total</strong>
                        <strong class="text-primary">$<?php echo number_format($total, 2); ?></strong>
                    </div>

                    <a href="checkout.php" class="btn btn-primary w-100">
                        Proceed to Checkout
                    </a>
                </div>
            </div>
        </div>
        <?php else: ?>
        <div class="text-center py-5">
            <i class="bi bi-cart-x display-1 text-muted mb-3"></i>
            <h3>Your cart is empty</h3>
            <p class="text-muted">Add some products to your cart to continue shopping.</p>
            <a href="products.php" class="btn btn-primary">
                Continue Shopping
            </a>
        </div>
        <?php endif; ?>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.6/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html> 