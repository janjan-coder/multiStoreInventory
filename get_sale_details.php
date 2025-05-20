<?php
session_start();
require_once 'db.php';

// Check if user is logged in
if (!isset($_SESSION['username'])) {
    die("Unauthorized access");
}

$sale_id = intval($_GET['sale_id']);

// Fetch sale details
$sql = "SELECT s.*, u.username, st.name as store_name
        FROM sales s
        JOIN users u ON s.user_id = u.id
        JOIN stores st ON s.store_id = st.id
        WHERE s.id = $sale_id";

$sale = $conn->query($sql)->fetch_assoc();

// Fetch sale items
$sql = "SELECT si.*, p.name as product_name
        FROM sale_items si
        JOIN products p ON si.product_id = p.id
        WHERE si.sale_id = $sale_id
        ORDER BY p.name";

$items = $conn->query($sql);
?>

<div class="sale-details">
    <div class="row mb-4">
        <div class="col-md-6">
            <p class="mb-1"><strong>Store:</strong> <?php echo htmlspecialchars($sale['store_name']); ?></p>
            <p class="mb-1"><strong>Cashier:</strong> <?php echo htmlspecialchars($sale['username']); ?></p>
            <p class="mb-1"><strong>Date:</strong> <?php echo date('M d, Y H:i', strtotime($sale['transaction_date'])); ?></p>
        </div>
        <div class="col-md-6 text-md-end">
            <p class="mb-1"><strong>Payment Method:</strong> 
                <span class="payment-badge payment-<?php echo $sale['payment_method']; ?>">
                    <?php echo ucfirst($sale['payment_method']); ?>
                </span>
            </p>
            <p class="mb-1"><strong>Total Amount:</strong> $<?php echo number_format($sale['total_amount'], 2); ?></p>
        </div>
    </div>

    <div class="table-responsive">
        <table class="table">
            <thead>
                <tr>
                    <th>Product</th>
                    <th class="text-end">Price</th>
                    <th class="text-end">Quantity</th>
                    <th class="text-end">Subtotal</th>
                </tr>
            </thead>
            <tbody>
                <?php while ($item = $items->fetch_assoc()): ?>
                <tr>
                    <td><?php echo htmlspecialchars($item['product_name']); ?></td>
                    <td class="text-end">$<?php echo number_format($item['unit_price'], 2); ?></td>
                    <td class="text-end"><?php echo $item['quantity']; ?></td>
                    <td class="text-end">$<?php echo number_format($item['subtotal'], 2); ?></td>
                </tr>
                <?php endwhile; ?>
            </tbody>
            <tfoot>
                <tr>
                    <td colspan="3" class="text-end"><strong>Total:</strong></td>
                    <td class="text-end"><strong>$<?php echo number_format($sale['total_amount'], 2); ?></strong></td>
                </tr>
            </tfoot>
        </table>
    </div>

    <div class="text-center mt-4">
        <button type="button" class="btn btn-primary" onclick="window.print()">
            <i class="bi bi-printer me-2"></i>Print Receipt
        </button>
    </div>
</div>

<style>
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

    @media print {
        .btn {
            display: none;
        }
    }
</style> 