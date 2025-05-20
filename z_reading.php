<?php
session_start();
require_once 'db.php';
require_once 'vendor/autoload.php'; // Include Dompdf library

// Check if user is logged in
if (!isset($_SESSION['username'])) {
    header("Location: login.php");
    exit();
}

// Get store details
$store_id = isset($_GET['store_id']) ? intval($_GET['store_id']) : $_SESSION['store_id'];
$store_query = "SELECT * FROM stores WHERE id = ?";
$stmt = $conn->prepare($store_query);
$stmt->bind_param("i", $store_id);
$stmt->execute();
$store = $stmt->get_result()->fetch_assoc();

if (!$store) {
    $_SESSION['error'] = "Store not found.";
    header("Location: dashboard.php");
    exit();
}

// Get current year's sales data
$current_year = date('Y');
$sales_query = "SELECT 
    COUNT(*) as total_transactions,
    COALESCE(SUM(total_amount), 0) as total_sales,
    COALESCE(SUM(CASE WHEN payment_method = 'cash' THEN total_amount ELSE 0 END), 0) as cash_sales,
    COALESCE(SUM(CASE WHEN payment_method = 'card' THEN total_amount ELSE 0 END), 0) as card_sales,
    COALESCE(SUM(CASE WHEN payment_method = 'gcash' THEN total_amount ELSE 0 END), 0) as gcash_sales,
    COUNT(DISTINCT DATE(created_at)) as business_days,
    COUNT(DISTINCT MONTH(created_at)) as business_months
    FROM sales 
    WHERE store_id = ? AND YEAR(created_at) = ?";
$stmt = $conn->prepare($sales_query);
$stmt->bind_param("is", $store_id, $current_year);
$stmt->execute();
$sales_summary = $stmt->get_result()->fetch_assoc();

// Get monthly sales breakdown
$monthly_query = "SELECT 
    MONTH(created_at) as month,
    COUNT(*) as transactions,
    COALESCE(SUM(total_amount), 0) as amount
    FROM sales 
    WHERE store_id = ? AND YEAR(created_at) = ?
    GROUP BY MONTH(created_at)
    ORDER BY month";
$stmt = $conn->prepare($monthly_query);
$stmt->bind_param("is", $store_id, $current_year);
$stmt->execute();
$monthly_sales = $stmt->get_result();

// Get product sales for the year
$product_query = "SELECT 
    p.name as product_name,
    SUM(si.quantity) as total_quantity,
    SUM(si.quantity * si.price) as total_amount
    FROM sale_items si
    JOIN products p ON si.product_id = p.id
    JOIN sales s ON si.sale_id = s.id
    WHERE s.store_id = ? AND YEAR(s.created_at) = ?
    GROUP BY p.id, p.name
    ORDER BY total_amount DESC";
$stmt = $conn->prepare($product_query);
$stmt->bind_param("is", $store_id, $current_year);
$stmt->execute();
$product_sales = $stmt->get_result();

// Get payment method trends
$payment_query = "SELECT 
    payment_method,
    COUNT(*) as transactions,
    COALESCE(SUM(total_amount), 0) as amount
    FROM sales 
    WHERE store_id = ? AND YEAR(created_at) = ?
    GROUP BY payment_method
    ORDER BY amount DESC";
$stmt = $conn->prepare($payment_query);
$stmt->bind_param("is", $store_id, $current_year);
$stmt->execute();
$payment_trends = $stmt->get_result();

// Get top selling days
$top_days_query = "SELECT 
    DATE(created_at) as sale_date,
    COUNT(*) as transactions,
    COALESCE(SUM(total_amount), 0) as amount
    FROM sales 
    WHERE store_id = ? AND YEAR(created_at) = ?
    GROUP BY DATE(created_at)
    ORDER BY amount DESC
    LIMIT 5";
$stmt = $conn->prepare($top_days_query);
$stmt->bind_param("is", $store_id, $current_year);
$stmt->execute();
$top_days = $stmt->get_result();

// Handle Z Reading submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'save_z_reading') {
    $cashier_id = $_SESSION['user_id'];
    $total_transactions = $sales_summary['total_transactions'];
    $total_sales = $sales_summary['total_sales'];
    $cash_sales = $sales_summary['cash_sales'];
    $card_sales = $sales_summary['card_sales'];
    $gcash_sales = $sales_summary['gcash_sales'];
    $business_days = $sales_summary['business_days'];
    $business_months = $sales_summary['business_months'];
    $remarks = trim($_POST['remarks']);

    $insert_query = "INSERT INTO z_readings (
        store_id, cashier_id, reading_year, total_transactions, 
        total_sales, cash_sales, card_sales, gcash_sales, 
        business_days, business_months, remarks
    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
    
    $stmt = $conn->prepare($insert_query);
    $stmt->bind_param("iisdddddiis", 
        $store_id, $cashier_id, $current_year, $total_transactions, 
        $total_sales, $cash_sales, $card_sales, $gcash_sales, 
        $business_days, $business_months, $remarks
    );
    
    if ($stmt->execute()) {
        $_SESSION['success'] = "Z Reading saved successfully.";
    } else {
        $_SESSION['error'] = "Error saving Z Reading: " . $stmt->error;
    }
    
    header("Location: " . $_SERVER['PHP_SELF'] . "?store_id=" . $store_id);
    exit();
}

// Handle PDF download
if (isset($_GET['download_pdf'])) {
    $html = '<h1>Z Reading Report</h1>';
    $html .= '<p>Total Transactions: ' . $sales_summary['total_transactions'] . '</p>';
    $html .= '<p>Total Sales: ' . $sales_summary['total_sales'] . '</p>';
    $html .= '<p>Cash Sales: ' . $sales_summary['cash_sales'] . '</p>';
    $html .= '<p>Card Sales: ' . $sales_summary['card_sales'] . '</p>';
    $html .= '<p>GCash Sales: ' . $sales_summary['gcash_sales'] . '</p>';

    $dompdf = new Dompdf\Dompdf();
    $dompdf->loadHtml($html);
    $dompdf->setPaper('A4', 'portrait');
    $dompdf->render();
    $dompdf->stream('z_reading_report.pdf');
    exit();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Z Reading - <?php echo htmlspecialchars($store['name']); ?></title>
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
            margin-bottom: 1.5rem;
        }
        .card-header {
            background-color: #e74a3b;
            color: white;
            border-radius: 0.5rem 0.5rem 0 0 !important;
            padding: 1rem 1.25rem;
        }
        .table th {
            background-color: #f8f9fc;
        }
        @media print {
            .no-print {
                display: none;
            }
            .card {
                box-shadow: none;
            }
        }
    </style>
</head>
<body>
    <div class="container-fluid py-4">
        <!-- Navigation -->
        <nav class="mb-4 no-print">
            <a href="dashboard.php" class="btn btn-link">
                <i class="bi bi-arrow-left"></i> Back to Dashboard
            </a>
        </nav>

        <div class="row">
            <div class="col-md-10">
                <!-- Sales Summary -->
                <div class="card">
                    <div class="card-header">
                        <h5 class="mb-0">Z Reading - <?php echo htmlspecialchars($store['name']); ?></h5>
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

                        <div class="row mb-4">
                            <div class="col-md-6">
                                <h6>Year: <?php echo $current_year; ?></h6>
                                <h6>Store: <?php echo htmlspecialchars($store['name']); ?></h6>
                            </div>
                            <div class="col-md-6 text-end">
                                <h6>Generated by: <?php echo htmlspecialchars($_SESSION['username']); ?></h6>
                                <h6>Date: <?php echo date('F d, Y h:i A'); ?></h6>
                            </div>
                        </div>

                        <div class="d-flex justify-content-end mb-4">
                            <a href="?store_id=<?php echo $store_id; ?>&download_pdf=1" class="btn btn-success me-2">
                                <i class="bi bi-file-earmark-pdf"></i> Download Z Reading Report
                            </a>
                            <button type="button" class="btn btn-secondary" onclick="window.print()">
                                <i class="bi bi-printer"></i> Print
                            </button>
                        </div>

                        <div class="table-responsive">
                            <table class="table table-bordered">
                                <thead>
                                    <tr>
                                        <th>Description</th>
                                        <th class="text-end">Amount</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr>
                                        <td>Total Transactions</td>
                                        <td class="text-end"><?php echo number_format($sales_summary['total_transactions']); ?></td>
                                    </tr>
                                    <tr>
                                        <td>Total Sales</td>
                                        <td class="text-end">₱<?php echo number_format($sales_summary['total_sales'], 2); ?></td>
                                    </tr>
                                    <tr>
                                        <td>Cash Sales</td>
                                        <td class="text-end">₱<?php echo number_format($sales_summary['cash_sales'], 2); ?></td>
                                    </tr>
                                    <tr>
                                        <td>Card Sales</td>
                                        <td class="text-end">₱<?php echo number_format($sales_summary['card_sales'], 2); ?></td>
                                    </tr>
                                    <tr>
                                        <td>GCash Sales</td>
                                        <td class="text-end">₱<?php echo number_format($sales_summary['gcash_sales'], 2); ?></td>
                                    </tr>
                                    <tr>
                                        <td>Business Days</td>
                                        <td class="text-end"><?php echo number_format($sales_summary['business_days']); ?></td>
                                    </tr>
                                    <tr>
                                        <td>Business Months</td>
                                        <td class="text-end"><?php echo number_format($sales_summary['business_months']); ?></td>
                                    </tr>
                                    <tr>
                                        <td>Average Daily Sales</td>
                                        <td class="text-end">₱<?php echo number_format($sales_summary['total_sales'] / max(1, $sales_summary['business_days']), 2); ?></td>
                                    </tr>
                                    <tr>
                                        <td>Average Monthly Sales</td>
                                        <td class="text-end">₱<?php echo number_format($sales_summary['total_sales'] / max(1, $sales_summary['business_months']), 2); ?></td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>

                        <!-- Monthly Sales Breakdown -->
                        <h5 class="mt-4 mb-3">Monthly Sales Breakdown</h5>
                        <div class="table-responsive">
                            <table class="table table-bordered">
                                <thead>
                                    <tr>
                                        <th>Month</th>
                                        <th class="text-end">Transactions</th>
                                        <th class="text-end">Amount</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php while ($month = $monthly_sales->fetch_assoc()): ?>
                                    <tr>
                                        <td><?php echo date('F', mktime(0, 0, 0, $month['month'], 1)); ?></td>
                                        <td class="text-end"><?php echo number_format($month['transactions']); ?></td>
                                        <td class="text-end">₱<?php echo number_format($month['amount'], 2); ?></td>
                                    </tr>
                                    <?php endwhile; ?>
                                </tbody>
                            </table>
                        </div>

                        <!-- Top Selling Days -->
                        <h5 class="mt-4 mb-3">Top Selling Days</h5>
                        <div class="table-responsive">
                            <table class="table table-bordered">
                                <thead>
                                    <tr>
                                        <th>Date</th>
                                        <th class="text-end">Transactions</th>
                                        <th class="text-end">Amount</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php while ($day = $top_days->fetch_assoc()): ?>
                                    <tr>
                                        <td><?php echo date('F d, Y', strtotime($day['sale_date'])); ?></td>
                                        <td class="text-end"><?php echo number_format($day['transactions']); ?></td>
                                        <td class="text-end">₱<?php echo number_format($day['amount'], 2); ?></td>
                                    </tr>
                                    <?php endwhile; ?>
                                </tbody>
                            </table>
                        </div>

                        <!-- Product Sales -->
                        <h5 class="mt-4 mb-3">Product Sales</h5>
                        <div class="table-responsive">
                            <table class="table table-bordered">
                                <thead>
                                    <tr>
                                        <th>Product</th>
                                        <th class="text-end">Quantity</th>
                                        <th class="text-end">Amount</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php while ($product = $product_sales->fetch_assoc()): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($product['product_name']); ?></td>
                                        <td class="text-end"><?php echo number_format($product['total_quantity']); ?></td>
                                        <td class="text-end">₱<?php echo number_format($product['total_amount'], 2); ?></td>
                                    </tr>
                                    <?php endwhile; ?>
                                </tbody>
                            </table>
                        </div>

                        <!-- Payment Method Trends -->
                        <h5 class="mt-4 mb-3">Payment Method Trends</h5>
                        <div class="table-responsive">
                            <table class="table table-bordered">
                                <thead>
                                    <tr>
                                        <th>Payment Method</th>
                                        <th class="text-end">Transactions</th>
                                        <th class="text-end">Amount</th>
                                        <th class="text-end">Percentage</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php while ($payment = $payment_trends->fetch_assoc()): ?>
                                    <tr>
                                        <td><?php echo ucfirst($payment['payment_method']); ?></td>
                                        <td class="text-end"><?php echo number_format($payment['transactions']); ?></td>
                                        <td class="text-end">₱<?php echo number_format($payment['amount'], 2); ?></td>
                                        <td class="text-end"><?php echo number_format(($payment['amount'] / $sales_summary['total_sales']) * 100, 1); ?>%</td>
                                    </tr>
                                    <?php endwhile; ?>
                                </tbody>
                            </table>
                        </div>

                        <!-- Save Z Reading Form -->
                        <form method="POST" class="mt-4 no-print">
                            <input type="hidden" name="action" value="save_z_reading">
                            <div class="mb-3">
                                <label for="remarks" class="form-label">Remarks</label>
                                <textarea class="form-control" id="remarks" name="remarks" rows="3"></textarea>
                            </div>
                            <div class="d-flex gap-2">
                                <button type="submit" class="btn btn-danger">
                                    <i class="bi bi-save"></i> Save Z Reading
                                </button>
                                <button type="button" class="btn btn-secondary" onclick="window.print()">
                                    <i class="bi bi-printer"></i> Print
                                </button>
                                <a href="?store_id=<?php echo $store_id; ?>&download_pdf=1" class="btn btn-success">
                                    <i class="bi bi-file-earmark-pdf"></i> Download PDF
                                </a>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.6/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>