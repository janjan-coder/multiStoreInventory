<?php
session_start();
require_once 'db.php';
require 'vendor/autoload.php'; // Make sure you have PHPSpreadsheet installed

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Dompdf\Dompdf;
use Dompdf\Options;

// Check if user is logged in
if (!isset($_SESSION['username'])) {
    header("Location: index.php");
    exit();
}

// Get filter parameters
$start_date = isset($_GET['start_date']) ? $_GET['start_date'] : date('Y-m-d', strtotime('-30 days'));
$end_date = isset($_GET['end_date']) ? $_GET['end_date'] : date('Y-m-d');
$store_id = isset($_GET['store_id']) ? (int)$_GET['store_id'] : 0;
$format = isset($_GET['format']) ? $_GET['format'] : 'excel';

// Build the query
$sql = "SELECT 
            s.id as sale_id,
            s.created_at as date,
            s.total_amount,
            s.payment_method,
            p.name as product_name,
            p.barcode,
            p.size,
            sd.quantity,
            sd.price,
            st.name as store_name,
            u.username as cashier_name
        FROM sales s
        JOIN sale_details sd ON s.id = sd.sale_id
        JOIN products p ON sd.product_id = p.id
        JOIN stores st ON s.store_id = st.id
        JOIN users u ON s.user_id = u.id
        WHERE DATE(s.created_at) BETWEEN ? AND ?";

$params = [$start_date, $end_date];
$types = "ss";

if ($store_id > 0) {
    $sql .= " AND s.store_id = ?";
    $params[] = $store_id;
    $types .= "i";
}

$sql .= " ORDER BY s.created_at DESC, s.id DESC";

$stmt = $conn->prepare($sql);
$stmt->bind_param($types, ...$params);
$stmt->execute();
$result = $stmt->get_result();

// Calculate totals
$total_sales = 0;
$total_quantity = 0;
$sales_by_store = [];
$sales_by_product = [];

while ($row = $result->fetch_assoc()) {
    $total_sales += $row['total_amount'];
    $total_quantity += $row['quantity'];
    
    // Group by store
    if (!isset($sales_by_store[$row['store_name']])) {
        $sales_by_store[$row['store_name']] = 0;
    }
    $sales_by_store[$row['store_name']] += $row['total_amount'];
    
    // Group by product
    $product_key = $row['product_name'] . ' (' . $row['size'] . ')';
    if (!isset($sales_by_product[$product_key])) {
        $sales_by_product[$product_key] = [
            'quantity' => 0,
            'amount' => 0
        ];
    }
    $sales_by_product[$product_key]['quantity'] += $row['quantity'];
    $sales_by_product[$product_key]['amount'] += ($row['price'] * $row['quantity']);
}

// Reset result pointer
$result->data_seek(0);

if ($format === 'excel') {
    // Create new Spreadsheet object
    $spreadsheet = new Spreadsheet();
    $sheet = $spreadsheet->getActiveSheet();

    // Set document properties
    $spreadsheet->getProperties()
        ->setCreator('Inventory System')
        ->setLastModifiedBy('Inventory System')
        ->setTitle('Sales Report')
        ->setSubject('Sales Report from ' . $start_date . ' to ' . $end_date);

    // Set column widths
    $sheet->getColumnDimension('A')->setWidth(15);
    $sheet->getColumnDimension('B')->setWidth(20);
    $sheet->getColumnDimension('C')->setWidth(30);
    $sheet->getColumnDimension('D')->setWidth(10);
    $sheet->getColumnDimension('E')->setWidth(15);
    $sheet->getColumnDimension('F')->setWidth(15);
    $sheet->getColumnDimension('G')->setWidth(15);
    $sheet->getColumnDimension('H')->setWidth(20);

    // Add headers
    $sheet->setCellValue('A1', 'Sales Report');
    $sheet->setCellValue('A2', 'Period: ' . $start_date . ' to ' . $end_date);
    $sheet->setCellValue('A4', 'Summary');
    $sheet->setCellValue('A5', 'Total Sales: ₱' . number_format($total_sales, 2));
    $sheet->setCellValue('A6', 'Total Items Sold: ' . number_format($total_quantity));
    $sheet->setCellValue('A7', 'Average Sale: ₱' . number_format($total_sales / max(1, $total_quantity), 2));

    // Add sales by store
    $sheet->setCellValue('A9', 'Sales by Store');
    $sheet->setCellValue('A10', 'Store');
    $sheet->setCellValue('B10', 'Total Sales');
    $sheet->setCellValue('C10', 'Percentage');
    $row = 11;
    foreach ($sales_by_store as $store => $amount) {
        $sheet->setCellValue('A' . $row, $store);
        $sheet->setCellValue('B' . $row, '₱' . number_format($amount, 2));
        $sheet->setCellValue('C' . $row, number_format(($amount / $total_sales) * 100, 1) . '%');
        $row++;
    }

    // Add sales by product
    $row += 2;
    $sheet->setCellValue('A' . $row, 'Sales by Product');
    $sheet->setCellValue('A' . ($row + 1), 'Product');
    $sheet->setCellValue('B' . ($row + 1), 'Quantity Sold');
    $sheet->setCellValue('C' . ($row + 1), 'Total Amount');
    $row += 2;
    foreach ($sales_by_product as $product => $data) {
        $sheet->setCellValue('A' . $row, $product);
        $sheet->setCellValue('B' . $row, number_format($data['quantity']));
        $sheet->setCellValue('C' . $row, '₱' . number_format($data['amount'], 2));
        $row++;
    }

    // Add detailed sales
    $row += 2;
    $sheet->setCellValue('A' . $row, 'Detailed Sales');
    $sheet->setCellValue('A' . ($row + 1), 'Date');
    $sheet->setCellValue('B' . ($row + 1), 'Store');
    $sheet->setCellValue('C' . ($row + 1), 'Product');
    $sheet->setCellValue('D' . ($row + 1), 'Size');
    $sheet->setCellValue('E' . ($row + 1), 'Quantity');
    $sheet->setCellValue('F' . ($row + 1), 'Price');
    $sheet->setCellValue('G' . ($row + 1), 'Total');
    $sheet->setCellValue('H' . ($row + 1), 'Payment Method');
    $row += 2;

    while ($row_data = $result->fetch_assoc()) {
        $sheet->setCellValue('A' . $row, date('M d, Y', strtotime($row_data['date'])));
        $sheet->setCellValue('B' . $row, $row_data['store_name']);
        $sheet->setCellValue('C' . $row, $row_data['product_name']);
        $sheet->setCellValue('D' . $row, $row_data['size']);
        $sheet->setCellValue('E' . $row, number_format($row_data['quantity']));
        $sheet->setCellValue('F' . $row, '₱' . number_format($row_data['price'], 2));
        $sheet->setCellValue('G' . $row, '₱' . number_format($row_data['price'] * $row_data['quantity'], 2));
        $sheet->setCellValue('H' . $row, $row_data['payment_method']);
        $row++;
    }

    // Set headers for Excel download
    header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    header('Content-Disposition: attachment;filename="sales_report.xlsx"');
    header('Cache-Control: max-age=0');

    // Save file to PHP output
    $writer = new Xlsx($spreadsheet);
    $writer->save('php://output');
} else {
    // Create PDF
    $options = new Options();
    $options->set('isHtml5ParserEnabled', true);
    $options->set('isPhpEnabled', true);

    $dompdf = new Dompdf($options);

    // Generate HTML content
    $html = '
    <html>
    <head>
        <style>
            body { font-family: Arial, sans-serif; }
            table { width: 100%; border-collapse: collapse; margin-bottom: 20px; }
            th, td { border: 1px solid #ddd; padding: 8px; text-align: left; }
            th { background-color: #f5f5f5; }
            .summary { margin-bottom: 20px; }
            .summary-item { margin-bottom: 10px; }
            h1, h2 { color: #333; }
        </style>
    </head>
    <body>
        <h1>Sales Report</h1>
        <p>Period: ' . $start_date . ' to ' . $end_date . '</p>

        <div class="summary">
            <h2>Summary</h2>
            <div class="summary-item">Total Sales: ₱' . number_format($total_sales, 2) . '</div>
            <div class="summary-item">Total Items Sold: ' . number_format($total_quantity) . '</div>
            <div class="summary-item">Average Sale: ₱' . number_format($total_sales / max(1, $total_quantity), 2) . '</div>
        </div>

        <h2>Sales by Store</h2>
        <table>
            <tr>
                <th>Store</th>
                <th>Total Sales</th>
                <th>Percentage</th>
            </tr>';
    
    foreach ($sales_by_store as $store => $amount) {
        $html .= '
            <tr>
                <td>' . htmlspecialchars($store) . '</td>
                <td>₱' . number_format($amount, 2) . '</td>
                <td>' . number_format(($amount / $total_sales) * 100, 1) . '%</td>
            </tr>';
    }
    
    $html .= '
        </table>

        <h2>Sales by Product</h2>
        <table>
            <tr>
                <th>Product</th>
                <th>Quantity Sold</th>
                <th>Total Amount</th>
            </tr>';
    
    foreach ($sales_by_product as $product => $data) {
        $html .= '
            <tr>
                <td>' . htmlspecialchars($product) . '</td>
                <td>' . number_format($data['quantity']) . '</td>
                <td>₱' . number_format($data['amount'], 2) . '</td>
            </tr>';
    }
    
    $html .= '
        </table>

        <h2>Detailed Sales</h2>
        <table>
            <tr>
                <th>Date</th>
                <th>Store</th>
                <th>Product</th>
                <th>Size</th>
                <th>Quantity</th>
                <th>Price</th>
                <th>Total</th>
                <th>Payment Method</th>
            </tr>';
    
    while ($row = $result->fetch_assoc()) {
        $html .= '
            <tr>
                <td>' . date('M d, Y', strtotime($row['date'])) . '</td>
                <td>' . htmlspecialchars($row['store_name']) . '</td>
                <td>' . htmlspecialchars($row['product_name']) . '</td>
                <td>' . htmlspecialchars($row['size']) . '</td>
                <td>' . number_format($row['quantity']) . '</td>
                <td>₱' . number_format($row['price'], 2) . '</td>
                <td>₱' . number_format($row['price'] * $row['quantity'], 2) . '</td>
                <td>' . htmlspecialchars($row['payment_method']) . '</td>
            </tr>';
    }
    
    $html .= '
        </table>
    </body>
    </html>';

    $dompdf->loadHtml($html);
    $dompdf->setPaper('A4', 'landscape');
    $dompdf->render();

    // Output PDF
    header('Content-Type: application/pdf');
    header('Content-Disposition: attachment; filename="sales_report.pdf"');
    echo $dompdf->output();
}
?> 