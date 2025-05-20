<?php
session_start();
require_once 'db.php';

$product_id = isset($_GET['id']) ? intval($_GET['id']) : 0;

// Fetch product details
$sql = "SELECT p.*, c.name as category_name,
        (SELECT price FROM product_prices WHERE product_id = p.id ORDER BY effective_date DESC LIMIT 1) as price,
        (SELECT GROUP_CONCAT(image_url) FROM product_images WHERE product_id = p.id) as images
        FROM products p
        LEFT JOIN categories c ON p.category_id = c.id
        WHERE p.id = $product_id";

$product = $conn->query($sql)->fetch_assoc();

if (!$product) {
    header("Location: products.php");
    exit();
}

// Fetch product reviews
$sql = "SELECT r.*, c.first_name, c.last_name
        FROM reviews r
        JOIN customers c ON r.customer_id = c.id
        WHERE r.product_id = $product_id
        ORDER BY r.created_at DESC";
$reviews = $conn->query($sql);

// Calculate average rating
$sql = "SELECT AVG(rating) as avg_rating, COUNT(*) as review_count
        FROM reviews
        WHERE product_id = $product_id";
$rating_stats = $conn->query($sql)->fetch_assoc();

// Handle add to cart
if (isset($_POST['add_to_cart'])) {
    if (!isset($_SESSION['customer_id'])) {
        header("Location: login.php?redirect=product.php?id=" . $product_id);
        exit();
    }

    $quantity = intval($_POST['quantity']);
    $customer_id = $_SESSION['customer_id'];

    // Check if product is already in cart
    $sql = "SELECT id, quantity FROM cart 
            WHERE customer_id = $customer_id AND product_id = $product_id";
    $cart_item = $conn->query($sql)->fetch_assoc();

    if ($cart_item) {
        // Update quantity
        $sql = "UPDATE cart SET quantity = quantity + $quantity 
                WHERE id = {$cart_item['id']}";
    } else {
        // Add new item
        $sql = "INSERT INTO cart (customer_id, product_id, quantity) 
                VALUES ($customer_id, $product_id, $quantity)";
    }

    if ($conn->query($sql)) {
        $success_message = "Product added to cart successfully!";
    } else {
        $error_message = "Error adding product to cart: " . $conn->error;
    }
}

// Get product images
$product_images = $product['images'] ? explode(',', $product['images']) : ['images/products/default.jpg'];
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($product['name']); ?> - E-commerce Store</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.6/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/lightbox2@2.11.3/dist/css/lightbox.min.css">
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

        .product-image {
            width: 100%;
            height: 400px;
            object-fit: cover;
            border-radius: 0.5rem;
        }

        .thumbnail {
            width: 80px;
            height: 80px;
            object-fit: cover;
            border-radius: 0.35rem;
            cursor: pointer;
            transition: all 0.2s ease;
        }

        .thumbnail:hover {
            transform: scale(1.1);
        }

        .thumbnail.active {
            border: 2px solid var(--primary-color);
        }

        .rating {
            color: #ffc107;
        }

        .review-card {
            border: none;
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
                    <li class="nav-item">
                        <a class="nav-link" href="products.php?category=<?php echo $product['category_id']; ?>">
                            <?php echo htmlspecialchars($product['category_name']); ?>
                        </a>
                    </li>
                </ul>
                <div class="d-flex align-items-center">
                    <a href="cart.php" class="btn btn-outline-primary me-2">
                        <i class="bi bi-cart"></i>
                        <span class="badge bg-primary">0</span>
                    </a>
                    <?php if (isset($_SESSION['customer_id'])): ?>
                        <a href="account.php" class="btn btn-outline-primary me-2">My Account</a>
                        <a href="logout.php" class="btn btn-outline-danger">Logout</a>
                    <?php else: ?>
                        <a href="login.php" class="btn btn-outline-primary me-2">Login</a>
                        <a href="register.php" class="btn btn-primary">Register</a>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </nav>

    <div class="container py-4">
        <?php if (isset($success_message)): ?>
            <div class="alert alert-success alert-dismissible fade show" role="alert">
                <?php echo $success_message; ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <?php if (isset($error_message)): ?>
            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                <?php echo $error_message; ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <div class="row">
            <!-- Product Images -->
            <div class="col-md-6 mb-4">
                <a href="<?php echo $product_images[0]; ?>" data-lightbox="product-gallery">
                    <img src="<?php echo $product_images[0]; ?>" class="product-image mb-3" alt="<?php echo htmlspecialchars($product['name']); ?>">
                </a>
                <div class="d-flex gap-2">
                    <?php foreach ($product_images as $index => $image): ?>
                    <a href="<?php echo $image; ?>" data-lightbox="product-gallery" class="d-none">
                        <img src="<?php echo $image; ?>" class="thumbnail <?php echo $index === 0 ? 'active' : ''; ?>" 
                             alt="<?php echo htmlspecialchars($product['name']); ?> - Image <?php echo $index + 1; ?>">
                    </a>
                    <?php endforeach; ?>
                </div>
            </div>

            <!-- Product Details -->
            <div class="col-md-6">
                <h1 class="mb-3"><?php echo htmlspecialchars($product['name']); ?></h1>
                
                <!-- Rating -->
                <div class="mb-3">
                    <div class="rating">
                        <?php
                        $avg_rating = round($rating_stats['avg_rating'] ?? 0);
                        for ($i = 1; $i <= 5; $i++) {
                            echo '<i class="bi bi-star' . ($i <= $avg_rating ? '-fill' : '') . '"></i>';
                        }
                        ?>
                        <span class="text-muted ms-2">
                            <?php echo number_format($rating_stats['avg_rating'] ?? 0, 1); ?> 
                            (<?php echo $rating_stats['review_count'] ?? 0; ?> reviews)
                        </span>
                    </div>
                </div>

                <!-- Price -->
                <h2 class="text-primary mb-4">$<?php echo number_format($product['price'], 2); ?></h2>

                <!-- Add to Cart Form -->
                <form method="POST" class="mb-4">
                    <div class="row g-3 align-items-center">
                        <div class="col-auto">
                            <label for="quantity" class="form-label">Quantity:</label>
                            <input type="number" class="form-control" id="quantity" name="quantity" value="1" min="1" style="width: 80px;">
                        </div>
                        <div class="col-auto">
                            <button type="submit" name="add_to_cart" class="btn btn-primary btn-lg">
                                <i class="bi bi-cart-plus me-2"></i>Add to Cart
                            </button>
                        </div>
                    </div>
                </form>

                <!-- Description -->
                <div class="mb-4">
                    <h4>Description</h4>
                    <p><?php echo nl2br(htmlspecialchars($product['description'] ?? 'No description available.')); ?></p>
                </div>

                <!-- Additional Info -->
                <div class="mb-4">
                    <h4>Additional Information</h4>
                    <ul class="list-unstyled">
                        <li><strong>Category:</strong> <?php echo htmlspecialchars($product['category_name']); ?></li>
                        <li><strong>Barcode:</strong> <?php echo htmlspecialchars($product['barcode']); ?></li>
                    </ul>
                </div>
            </div>
        </div>

        <!-- Reviews Section -->
        <div class="mt-5">
            <h3>Customer Reviews</h3>
            
            <?php if (isset($_SESSION['customer_id'])): ?>
            <!-- Review Form -->
            <div class="card review-card mb-4">
                <div class="card-body">
                    <h5 class="card-title">Write a Review</h5>
                    <form method="POST" action="add_review.php">
                        <input type="hidden" name="product_id" value="<?php echo $product_id; ?>">
                        <div class="mb-3">
                            <label class="form-label">Rating</label>
                            <div class="rating-input">
                                <?php for ($i = 5; $i >= 1; $i--): ?>
                                <input type="radio" name="rating" value="<?php echo $i; ?>" id="rating<?php echo $i; ?>" required>
                                <label for="rating<?php echo $i; ?>"><i class="bi bi-star"></i></label>
                                <?php endfor; ?>
                            </div>
                        </div>
                        <div class="mb-3">
                            <label for="comment" class="form-label">Your Review</label>
                            <textarea class="form-control" id="comment" name="comment" rows="3" required></textarea>
                        </div>
                        <button type="submit" class="btn btn-primary">Submit Review</button>
                    </form>
                </div>
            </div>
            <?php endif; ?>

            <!-- Reviews List -->
            <div class="reviews-list">
                <?php if ($reviews->num_rows > 0): ?>
                    <?php while ($review = $reviews->fetch_assoc()): ?>
                    <div class="card review-card mb-3">
                        <div class="card-body">
                            <div class="d-flex justify-content-between align-items-center mb-2">
                                <h5 class="card-title mb-0">
                                    <?php echo htmlspecialchars($review['first_name'] . ' ' . $review['last_name']); ?>
                                </h5>
                                <small class="text-muted">
                                    <?php echo date('M d, Y', strtotime($review['created_at'])); ?>
                                </small>
                            </div>
                            <div class="rating mb-2">
                                <?php for ($i = 1; $i <= 5; $i++): ?>
                                    <i class="bi bi-star<?php echo $i <= $review['rating'] ? '-fill' : ''; ?>"></i>
                                <?php endfor; ?>
                            </div>
                            <p class="card-text"><?php echo nl2br(htmlspecialchars($review['comment'])); ?></p>
                        </div>
                    </div>
                    <?php endwhile; ?>
                <?php else: ?>
                    <div class="alert alert-info">
                        No reviews yet. Be the first to review this product!
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.6/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/lightbox2@2.11.3/dist/js/lightbox.min.js"></script>
    <script>
        // Image gallery functionality
        document.querySelectorAll('.thumbnail').forEach(thumb => {
            thumb.addEventListener('click', function() {
                document.querySelector('.product-image').src = this.src;
                document.querySelectorAll('.thumbnail').forEach(t => t.classList.remove('active'));
                this.classList.add('active');
            });
        });

        // Rating input functionality
        document.querySelectorAll('.rating-input input').forEach(input => {
            input.addEventListener('change', function() {
                const rating = this.value;
                document.querySelectorAll('.rating-input label i').forEach((star, index) => {
                    star.className = index < rating ? 'bi bi-star-fill' : 'bi bi-star';
                });
            });
        });
    </script>
</body>
</html> 