<?php include 'db.php'; ?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Summary inventory</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.6/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-4Q6Gf2aSP4eDXB8Miphtr37CMZZQ5oXLH2yaXMJ2w8e2ZtHTl7GptT4jmndRuHDT" crossorigin="anonymous">
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.6/dist/js/bootstrap.bundle.min.js" integrity="sha384-j1CDi7MgGQ12Z7Qab0qlWQ/Qqz24Gc6BM0thvEMVjHnfYGF0rmFCozFSxQBxwHKO" crossorigin="anonymous"></script>
</head>
<body>
    
</body>
</html>
<h2>Inventory Overview</h2>
<table border="1">
<tr><th>Store</th><th>Product</th><th>Quantity</th></tr>
<?php
$sql = "SELECT s.name as store, p.name as product, i.quantity
        FROM inventory i
        JOIN stores s ON s.id = i.store_id
        JOIN products p ON p.id = i.product_id";
$res = $conn->query($sql);
while ($row = $res->fetch_assoc()) {
    echo "<tr><td>{$row['store']}</td><td>{$row['product']}</td><td>{$row['quantity']}</td></tr>";
}
?>
</table>
