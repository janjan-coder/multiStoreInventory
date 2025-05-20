<?php include 'db.php'; ?>

<form method="POST">
    Product ID: <input type="number" name="product_id"><br>
    From Store ID: <input type="number" name="from_store"><br>
    To Store ID: <input type="number" name="to_store"><br>
    Quantity: <input type="number" name="qty"><br>
    <input type="submit" name="transfer" value="Transfer">
</form>

<?php
if (isset($_POST['transfer'])) {
    $pid = $_POST['product_id'];
    $from = $_POST['from_store'];
    $to = $_POST['to_store'];
    $qty = $_POST['qty'];

    $conn->query("UPDATE inventory SET quantity = quantity - $qty WHERE product_id = $pid AND store_id = $from");

    $check = $conn->query("SELECT * FROM inventory WHERE product_id = $pid AND store_id = $to");
    if ($check->num_rows > 0) {
        $conn->query("UPDATE inventory SET quantity = quantity + $qty WHERE product_id = $pid AND store_id = $to");
    } else {
        $conn->query("INSERT INTO inventory (product_id, store_id, quantity) VALUES ($pid, $to, $qty)");
    }

    echo "Inventory transferred.";
}
?>
