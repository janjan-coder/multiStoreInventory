<?php
session_start();
require_once 'db.php';

// Get filter parameters
$category_id = isset($_GET['category']) ? intval($_GET['category']) : null;
$search = isset($_GET['search']) ? $conn->real_escape_string($_GET['search']) : '';
$sort = isset($_GET['sort']) ? $conn->real_escape_string($_GET['sort']) : 'name_asc';
$min_price = isset($_GET['min_price']) ? floatval($_GET['min_price']) : null;
$max_price = isset($_GET['max_price']) ? floatval($_GET['max_price']) : null;

// Build the query
$sql = "SELECT p.*, c.name as category_name,
        (SELECT price FROM product_prices WHERE product_id = p.id ORDER BY effective_date DESC LIMIT 1) as price,
        (SELECT image_url FROM product_images WHERE product_id = p.id AND is_primary = 1 LIMIT 1) as image_url
        FROM products p
        LEFT JOIN categories c ON p.category_id = c.id
        WHERE p.id IN (SELECT product_id FROM inventory WHERE quantity > 0)";

$where_conditions = [];

if ($category_id) {
    $where_conditions[] = "p.category_id = $category_id";
}

if ($search) {
    $where_conditions[] = "(p.name LIKE '%$search%' OR p.barcode LIKE '%$search%')";
}

if ($min_price !== null) {
    $where_conditions[] = "(SELECT price FROM product_prices WHERE product_id = p.id ORDER BY effective_date DESC LIMIT 1) >= $min_price";
}

if ($max_price !== null) {
    $where_conditions[] = "(SELECT price FROM product_prices WHERE product_id = p.id ORDER BY effective_date DESC LIMIT 1) <= $max_price";
}

if (!empty($where_conditions)) {
    $sql .= " AND " . implode(" AND ", $where_conditions);
}

// Add sorting
switch ($sort) {
    case 'price_asc':
        $sql .= " ORDER BY (SELECT price FROM product_prices WHERE product_id = p.id ORDER BY effective_date DESC LIMIT 1) ASC";
        break;
    case 'price_desc':
        $sql .= " ORDER BY (SELECT price FROM product_prices WHERE product_id = p.id ORDER BY effective_date DESC LIMIT 1) DESC";
        break;
    case 'name_desc':
        $sql .= " ORDER BY p.name DESC";
        break;
    default: // name_asc
        $sql .= " ORDER BY p.name ASC";
}

$products = $conn->query($sql);

// Fetch categories for filter
$categories = $conn->query("SELECT * FROM categories ORDER BY name");

// Get price range for filter
$price_range = $conn->query("SELECT 
    MIN(price) as min_price,
    MAX(price) as max_price
    FROM product_prices")->fetch_assoc();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Products - E-commerce Store</title>
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

        .product-card {
            border: none;
            border-radius: 0.5rem;
            transition: all 0.3s ease;
            height: 100%;
        }

        .product-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 0.5rem 1rem rgba(0, 0, 0, 0.15);
        }

        .product-image {
            height: 200px;
            object-fit: cover;
            border-radius: 0.5rem 0.5rem 0 0;
        }

        .filter-section {
            background: white;
            border-radius: 0.5rem;
            padding: 1.5rem;
            box-shadow: 0 0.15rem 1.75rem 0 rgba(58, 59, 69, 0.15);
        }

        .price-range {
            width: 100%;
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
                        <a class="nav-link active" href="products.php">All Products</a>
                    </li>
                    <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle" href="#" id="categoriesDropdown" role="button" data-bs-toggle="dropdown">
                            Categories
                        </a>
                        <ul class="dropdown-menu">
                            <?php while ($category = $categories->fetch_assoc()): ?>
                            <li>
                                <a class="dropdown-item" href="products.php?category=<?php echo $category['id']; ?>">
                                    <?php echo htmlspecialchars($category['name']); ?>
                                </a>
                            </li>
                            <?php endwhile; ?>
                        </ul>
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
        <div class="row">
            <!-- Filters -->
            <div class="col-lg-3">
                <div class="filter-section mb-4">
                    <h5 class="mb-3">Filters</h5>
                    <form method="GET" id="filterForm">
                        <?php if ($category_id): ?>
                        <input type="hidden" name="category" value="<?php echo $category_id; ?>">
                        <?php endif; ?>

                        <!-- Search -->
                        <div class="mb-3">
                            <label class="form-label">Search</label>
                            <input type="text" class="form-control" name="search" value="<?php echo htmlspecialchars($search); ?>" placeholder="Search products...">
                        </div>

                        <!-- Categories -->
                        <div class="mb-3">
                            <label class="form-label">Categories</label>
                            <div class="list-group">
                                <?php 
                                $categories->data_seek(0);
                                while ($category = $categories->fetch_assoc()): 
                                ?>
                                <a href="?category=<?php echo $category['id']; ?>" 
                                   class="list-group-item list-group-item-action <?php echo $category_id == $category['id'] ? 'active' : ''; ?>">
                                    <?php echo htmlspecialchars($category['name']); ?>
                                </a>
                                <?php endwhile; ?>
                            </div>
                        </div>

                        <!-- Price Range -->
                        <div class="mb-3">
                            <label class="form-label">Price Range</label>
                            <div class="row g-2">
                                <div class="col">
                                    <input type="number" class="form-control" name="min_price" placeholder="Min" value="<?php echo $min_price; ?>">
                                </div>
                                <div class="col">
                                    <input type="number" class="form-control" name="max_price" placeholder="Max" value="<?php echo $max_price; ?>">
                                </div>
                            </div>
                        </div>

                        <!-- Sort -->
                        <div class="mb-3">
                            <label class="form-label">Sort By</label>
                            <select class="form-select" name="sort" onchange="this.form.submit()">
                                <option value="name_asc" <?php echo $sort === 'name_asc' ? 'selected' : ''; ?>>Name (A-Z)</option>
                                <option value="name_desc" <?php echo $sort === 'name_desc' ? 'selected' : ''; ?>>Name (Z-A)</option>
                                <option value="price_asc" <?php echo $sort === 'price_asc' ? 'selected' : ''; ?>>Price (Low to High)</option>
                                <option value="price_desc" <?php echo $sort === 'price_desc' ? 'selected' : ''; ?>>Price (High to Low)</option>
                            </select>
                        </div>

                        <button type="submit" class="btn btn-primary w-100">Apply Filters</button>
                    </form>
                </div>
            </div>

            <!-- Products Grid -->
            <div class="col-lg-9">
                <div class="row g-4">
                    <?php if ($products->num_rows > 0): ?>
                        <?php while ($product = $products->fetch_assoc()): ?>
                        <div class="col-md-4">
                            <div class="card product-card">
                                <img src="<?php echo $product['image_url'] ?? 'images/products/default.jpg'; ?>" 
                                     class="product-image" 
                                     alt="<?php echo htmlspecialchars($product['name']); ?>">
                                <div class="card-body">
                                    <h5 class="card-title"><?php echo htmlspecialchars($product['name']); ?></h5>
                                    <p class="card-text text-muted"><?php echo htmlspecialchars($product['category_name']); ?></p>
                                    <p class="card-text fw-bold">$<?php echo number_format($product['price'], 2); ?></p>
                                    <a href="product.php?id=<?php echo $product['id']; ?>" class="btn btn-primary w-100">View Details</a>
                                </div>
                            </div>
                        </div>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <div class="col-12">
                            <div class="alert alert-info">
                                No products found matching your criteria.
                            </div>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.6/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html> 