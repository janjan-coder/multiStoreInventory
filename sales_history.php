<?php
session_start();
require_once 'db.php';

// Check if user is logged in
if (!isset($_SESSION['username'])) {
    header("Location: login.php");
    exit();
}

$store_id = $_SESSION['store_id'];
$where_clause = "";

// Apply store filter for non-admin users
if ($_SESSION['role'] !== 'admin') {
    $where_clause = "WHERE s.store_id = $store_id";
}

// Handle date filtering
$start_date = isset($_GET['start_date']) ? $_GET['start_date'] : date('Y-m-d', strtotime('-30 days'));
$end_date = isset($_GET['end_date']) ? $_GET['end_date'] : date('Y-m-d');

if ($where_clause) {
    $where_clause .= " AND DATE(s.transaction_date) BETWEEN '$start_date' AND '$end_date'";
} else {
    $where_clause = "WHERE DATE(s.transaction_date) BETWEEN '$start_date' AND '$end_date'";
}

// Fetch sales with details
$sql = "SELECT s.*, u.username, st.name as store_name,
        (SELECT COUNT(*) FROM sale_items WHERE sale_id = s.id) as item_count
        FROM sales s
        JOIN users u ON s.user_id = u.id
        JOIN stores st ON s.store_id = st.id
        $where_clause
        ORDER BY s.transaction_date DESC";
$sales = $conn->query($sql);

// Calculate totals
$sql = "SELECT 
        COUNT(*) as total_transactions,
        SUM(total_amount) as total_revenue,
        AVG(total_amount) as average_sale
        FROM sales s
        $where_clause";
$totals = $conn->query($sql)->fetch_assoc();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sales History - Inventory Management</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.6/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    <style>
        :root {
            --primary-color: #4e73df;
            --secondary-color: #858796;
            --success-color: #1cc88a;
        }

        body {
            background-color: #f8f9fc;
            font-family: 'Nunito', -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif;
        }

        .card {
            border: none;
            border-radius: 0.5rem;
            box-shadow: 0 0.15rem 1.75rem 0 rgba(58, 59, 69, 0.15);
            margin-bottom: 1.5rem;
        }

        .card-header {
            background: linear-gradient(135deg, var(--primary-color) 0%, #224abe 100%);
            color: white;
            border-radius: 0.5rem 0.5rem 0 0 !important;
            padding: 1.5rem;
        }

        .card-header h3 {
            margin: 0;
            font-size: 1.5rem;
            font-weight: 600;
        }

        .stats-card {
            background: white;
            border-radius: 0.5rem;
            padding: 1.5rem;
            text-align: center;
            box-shadow: 0 0.15rem 1.75rem 0 rgba(58, 59, 69, 0.15);
        }

        .stats-card h4 {
            color: var(--primary-color);
            font-size: 2rem;
            margin-bottom: 0.5rem;
        }

        .stats-card p {
            color: var(--secondary-color);
            margin: 0;
        }

        .table th {
            background-color: #f8f9fc;
            color: var(--primary-color);
            font-weight: 600;
        }

        .btn-view {
            color: var(--primary-color);
            background: none;
            border: none;
            padding: 0.25rem 0.5rem;
            transition: all 0.2s ease-in-out;
        }

        .btn-view:hover {
            color: #2e59d9;
            transform: scale(1.1);
        }

        .modal-header {
            background: linear-gradient(135deg, var(--primary-color) 0%, #224abe 100%);
            color: white;
        }

        .payment-badge {
            padding: 0.5rem 1rem;
            border-radius: 0.35rem;
            font-weight: 600;
        }

        .payment-cash {
            background-color: #e3e6f0;
            color: #4e73df;
        }

        .payment-card {
            background-color: #e3e6f0;
            color: #1cc88a;
        }

        .payment-mobile {
            background-color: #e3e6f0;
            color: #36b9cc;
        }
    </style>
</head>
<body>
    <div class="container-fluid py-4">
        <!-- Stats Cards -->
        <div class="row mb-4">
            <div class="col-md-4">
                <div class="stats-card">
                    <h4><?php echo number_format($totals['total_transactions']); ?></h4>
                    <p>Total Transactions</p>
                </div>
            </div>
            <div class="col-md-4">
                <div class="stats-card">
                    <h4>$<?php echo number_format($totals['total_revenue'], 2); ?></h4>
                    <p>Total Revenue</p>
                </div>
            </div>
            <div class="col-md-4">
                <div class="stats-card">
                    <h4>$<?php echo number_format($totals['average_sale'], 2); ?></h4>
                    <p>Average Sale</p>
                </div>
            </div>
        </div>

        <!-- Filter Form -->
        <div class="card mb-4">
            <div class="card-body">
                <form method="GET" class="row g-3">
                    <div class="col-md-4">
                        <label for="start_date" class="form-label">Start Date</label>
                        <input type="date" class="form-control" id="start_date" name="start_date" value="<?php echo $start_date; ?>">
                    </div>
                    <div class="col-md-4">
                        <label for="end_date" class="form-label">End Date</label>
                        <input type="date" class="form-control" id="end_date" name="end_date" value="<?php echo $end_date; ?>">
                    </div>
                    <div class="col-md-4 d-flex align-items-end">
                        <button type="submit" class="btn btn-primary">
                            <i class="bi bi-filter me-2"></i>Apply Filter
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <!-- Sales Table -->
        <div class="card">
            <div class="card-header">
                <h3><i class="bi bi-clock-history me-2"></i>Sales History</h3>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-hover">
                        <thead>
                            <tr>
                                <th>Date & Time</th>
                                <th>Store</th>
                                <th>Cashier</th>
                                <th>Items</th>
                                <th>Total Amount</th>
                                <th>Payment Method</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php while ($sale = $sales->fetch_assoc()): ?>
                            <tr>
                                <td><?php echo date('M d, Y H:i', strtotime($sale['transaction_date'])); ?></td>
                                <td><?php echo htmlspecialchars($sale['store_name']); ?></td>
                                <td><?php echo htmlspecialchars($sale['username']); ?></td>
                                <td><?php echo $sale['item_count']; ?> items</td>
                                <td>$<?php echo number_format($sale['total_amount'], 2); ?></td>
                                <td>
                                    <span class="payment-badge payment-<?php echo $sale['payment_method']; ?>">
                                        <?php echo ucfirst($sale['payment_method']); ?>
                                    </span>
                                </td>
                                <td>
                                    <button type="button" class="btn-view" onclick="viewSaleDetails(<?php echo $sale['id']; ?>)">
                                        <i class="bi bi-eye"></i>
                                    </button>
                                </td>
                            </tr>
                            <?php endwhile; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <!-- Sale Details Modal -->
    <div class="modal fade" id="saleDetailsModal" tabindex="-1">
        <div class="modal-dialog modal-dialog-centered modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Sale Details</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body" id="saleDetailsContent">
                    <!-- Content will be loaded dynamically -->
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.6/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        let saleDetailsModal;
        
        document.addEventListener('DOMContentLoaded', function() {
            saleDetailsModal = new bootstrap.Modal(document.getElementById('saleDetailsModal'));
        });

        function viewSaleDetails(saleId) {
            // Fetch sale details via AJAX
            fetch(`get_sale_details.php?sale_id=${saleId}`)
                .then(response => response.text())
                .then(html => {
                    document.getElementById('saleDetailsContent').innerHTML = html;
                    saleDetailsModal.show();
                })
                .catch(error => {
                    console.error('Error:', error);
                    alert('Error loading sale details');
                });
        }
    </script>
</body>
</html> 