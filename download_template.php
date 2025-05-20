<?php
// Set headers for CSV download
header('Content-Type: text/csv');
header('Content-Disposition: attachment;filename="product_import_template.csv"');

// Create output stream
$output = fopen('php://output', 'w');

// Add UTF-8 BOM for proper Excel encoding
fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));

// Add headers
fputcsv($output, ['Product Name', 'Barcode', 'Size', 'Store Name', 'Quantity']);

// Add sample data
$sampleData = [
    ['T-Shirt', 'TS-001', 'M', 'Main Store', '10'],
    ['T-Shirt', 'TS-001', 'L', 'Main Store', '15'],
    ['Jeans', 'JN-002', '32', 'Branch Store', '20'],
    ['Sneakers', 'SN-003', '42', 'Main Store', '8']
];

foreach ($sampleData as $row) {
    fputcsv($output, $row);
}

// Close the output stream
fclose($output);
exit; 