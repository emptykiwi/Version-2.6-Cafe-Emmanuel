<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
header('Content-Type: application/json');

include 'db_connect.php';

if ($conn->connect_error) {
    http_response_code(500);
    echo json_encode([
        "success" => false,
        "error" => "Database connection failed: " . $conn->connect_error
    ]);
    exit;
}

// Kunin lang yung cart na Completed
$sql = "SELECT cart FROM cart WHERE status='Delivered'";
$result = $conn->query($sql);

if (!$result) {
    http_response_code(500);
    echo json_encode([
        "success" => false,
        "error" => "SQL Error: " . $conn->error
    ]);
    exit;
}

$product_totals = [];

while ($row = $result->fetch_assoc()) {
    $cart_items = json_decode($row['cart'], true);
    if ($cart_items && is_array($cart_items)) {
        foreach ($cart_items as $item) {
            $name = $item['name'] ?? null;
            $quantity = intval($item['quantity'] ?? 1);
            $price = floatval($item['price'] ?? 0);

            if ($name) {
                if (!isset($product_totals[$name])) {
                    $product_totals[$name] = [
                        'product_name' => $name,
                        'total_sold' => $quantity,
                        'price' => $price
                    ];
                } else {
                    $product_totals[$name]['total_sold'] += $quantity;
                }
            }
        }
    }
}

// ** FIX APPLIED HERE **
// Filter out products na may total-sold < 1 (Original was >= 3)
// This will now show any product with at least 1 sale.
$product_totals = array_filter($product_totals, function($p) {
    return $p['total_sold'] >= 1;
});

// Convert to array para masort
$product_totals = array_values($product_totals);

// Sort descending by total-sold
usort($product_totals, function($a, $b) {
    return $b['total_sold'] - $a['total_sold'];
});

// Limit to top 10 (or top 3 kung gusto mo)
$top_selling = array_slice($product_totals, 0, 10);

echo json_encode([
    "success" => true,
    "data" => $top_selling
]);

$conn->close();
?>