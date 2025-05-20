<?php
session_start();
require_once 'db.php';

// Check if user is logged in
if (!isset($_SESSION['username'])) {
    header("Location: login.php");
    exit();
}

// Get user's store
$stmt = $conn->prepare("SELECT store_id FROM users WHERE id = ?");
$stmt->bind_param("i", $_SESSION['user_id']);
$stmt->execute();
$result = $stmt->get_result();
$user = $result->fetch_assoc();

if (!$user || !$user['store_id']) {
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

// Handle date range filtering
$start_date = isset($_GET['start_date']) ? $_GET['start_date'] : date('Y-m-d', strtotime('-30 days'));
$end_date = isset($_GET['end_date']) ? $_GET['end_date'] : date('Y-m-d');

// Get sales data
$query = "SELECT 
            s.id,
            s.created_at,
            s.total_amount,
            u.username as cashier_name,
            COUNT(si.id) as item_count,
            GROUP_CONCAT(CONCAT(p.name, ' (', si.quantity, ')') SEPARATOR ', ') as items
          FROM sales s
          JOIN users u ON s.user_id = u.id
          JOIN sale_items si ON s.id = si.sale_id
          JOIN products p ON si.product_id = p.id
          WHERE s.store_id = ? 
          AND DATE(s.created_at) BETWEEN ? AND ?
          GROUP BY s.id
          ORDER BY s.created_at DESC";

$stmt = $conn->prepare($query);
$stmt->bind_param("iss", $store_id, $start_date, $end_date);
$stmt->execute();
$sales = $stmt->get_result();

// Calculate totals
$total_sales = 0;
$total_items = 0;
$total_transactions = 0;

while ($sale = $sales->fetch_assoc()) {
    $total_sales += $sale['total_amount'];
    $total_items += $sale['item_count'];
    $total_transactions++;
}

// Reset pointer for display
$sales->data_seek(0);

// Handle export
if (isset($_GET['export'])) {
    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="sales_report_' . date('Y-m-d') . '.csv"');
    
    $output = fopen('php://output', 'w');
    
    // Add headers
    fputcsv($output, ['Sales Report for ' . $store['name']]);
    fputcsv($output, ['Period: ' . $start_date . ' to ' . $end_date]);
    fputcsv($output, ['']);
    
    // Add column headers
    fputcsv($output, ['Date', 'Time', 'Items', 'Total Amount', 'Cashier']);
    
    // Add data
    while ($sale = $sales->fetch_assoc()) {
        fputcsv($output, [
            date('Y-m-d', strtotime($sale['created_at'])),
            date('H:i:s', strtotime($sale['created_at'])),
            $sale['items'],
            number_format($sale['total_amount'], 2),
            $sale['cashier_name']
        ]);
    }
    
    // Add summary
    fputcsv($output, ['']);
    fputcsv($output, ['Total Transactions', $total_transactions]);
    fputcsv($output, ['Total Items Sold', $total_items]);
    fputcsv($output, ['Total Sales', number_format($total_sales, 2)]);
    
    fclose($output);
    exit();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sales Report - <?= htmlspecialchars($store['name']) ?></title>
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
        .summary-card {
            background: #fff;
            border-radius: 1rem;
            padding: 1.5rem;
            margin-bottom: 1rem;
            box-shadow: 0 0.25rem 0.5rem rgba(0, 0, 0, 0.1);
        }
        .summary-card h3 {
            color: #4e73df;
            margin-bottom: 1rem;
        }
        .summary-value {
            font-size: 2rem;
            font-weight: bold;
            color: #2e59d9;
        }
    </style>
</head>
<body>
    <div class="container">
        <!-- Navigation -->
        <nav class="mb-4">
            <a href="dashboard.php" class="btn btn-outline-primary">
                <i class="bi bi-speedometer2 me-2"></i>Dashboard
            </a>
            <a href="pos.php" class="btn btn-outline-primary">
                <i class="bi bi-cash-register me-2"></i>Point of Sale
            </a>
            <a href="logout.php" class="btn btn-outline-danger float-end">
                <i class="bi bi-box-arrow-right me-2"></i>Logout
            </a>
        </nav>

        <div class="card">
            <div class="card-header">
                <h3><i class="bi bi-graph-up me-2"></i>Sales Report - <?= htmlspecialchars($store['name']) ?></h3>
            </div>
            <div class="card-body">
                <!-- Date Range Filter -->
                <form method="GET" class="row g-3 mb-4">
                    <div class="col-md-4">
                        <label for="start_date" class="form-label">Start Date</label>
                        <input type="date" class="form-control" id="start_date" name="start_date" value="<?= $start_date ?>">
                    </div>
                    <div class="col-md-4">
                        <label for="end_date" class="form-label">End Date</label>
                        <input type="date" class="form-control" id="end_date" name="end_date" value="<?= $end_date ?>">
                    </div>
                    <div class="col-md-4 d-flex align-items-end">
                        <button type="submit" class="btn btn-primary me-2">
                            <i class="bi bi-search me-2"></i>Filter
                        </button>
                        <a href="?start_date=<?= $start_date ?>&end_date=<?= $end_date ?>&export=1" class="btn btn-success">
                            <i class="bi bi-file-earmark-excel me-2"></i>Export
                        </a>
                    </div>
                </form>

                <!-- Summary Cards -->
                <div class="row mb-4">
                    <div class="col-md-4">
                        <div class="summary-card">
                            <h3>Total Sales</h3>
                            <div class="summary-value">₱<?= number_format($total_sales, 2) ?></div>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="summary-card">
                            <h3>Total Transactions</h3>
                            <div class="summary-value"><?= $total_transactions ?></div>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="summary-card">
                            <h3>Total Items Sold</h3>
                            <div class="summary-value"><?= $total_items ?></div>
                        </div>
                    </div>
                </div>

                <!-- Sales Table -->
                <div class="table-responsive">
                    <table class="table table-striped">
                        <thead>
                            <tr>
                                <th>Date & Time</th>
                                <th>Items</th>
                                <th>Total Amount</th>
                                <th>Cashier</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php while ($sale = $sales->fetch_assoc()): ?>
                                <tr>
                                    <td><?= date('M d, Y H:i', strtotime($sale['created_at'])) ?></td>
                                    <td><?= htmlspecialchars($sale['items']) ?></td>
                                    <td>₱<?= number_format($sale['total_amount'], 2) ?></td>
                                    <td><?= htmlspecialchars($sale['cashier_name']) ?></td>
                                </tr>
                            <?php endwhile; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.6/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html> 