<?php
header('Content-Type: application/json');

include 'db_connect.php';
if ($conn->connect_error) {
    echo json_encode([]);
    exit;
}

$sql = "SELECT id, name, price, stock FROM products ORDER BY id ASC";
$result = $conn->query($sql);

$products = [];

while ($row = $result->fetch_assoc()) {
    $status = ($row['stock'] > 0) ? 'In Stock' : 'No Stock';
    $products[] = [
        'product_no' => $row['id'],
        'name' => $row['name'],
        'price' => $row['price'],
        'quantity' => $row['stock'],
        'status' => $status
    ];
}

echo json_encode($products);