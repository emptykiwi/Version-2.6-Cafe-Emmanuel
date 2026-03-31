<?php
require_once 'db_connect.php';

echo "Starting migration...\n";

// 1. Update order_items table schema in 'addproduct' database
$queries = [
    "ALTER TABLE `order_items` ADD COLUMN IF NOT EXISTS `size` varchar(50) DEFAULT 'Standard' AFTER `product_name`",
    "ALTER TABLE `order_items` ADD COLUMN IF NOT EXISTS `temperature` varchar(50) DEFAULT 'N/A' AFTER `size`",
    "ALTER TABLE `order_items` ADD COLUMN IF NOT EXISTS `addon_details` text DEFAULT NULL AFTER `temperature`"
];

foreach ($queries as $query) {
    if ($conn->query($query)) {
        echo "Successfully executed: " . substr($query, 0, 50) . "...\n";
    } else {
        echo "Error executing query: " . $conn->error . "\n";
    }
}

// 2. Ensure suggested products exist in 'products' table
$suggested_products = [
    [43, 'Nachos', 260.00, 'uploads/68f4c03d61c02_5D2F6E42-1049-4073-A645-FD39ADA21F99_4_5005_c.jpeg', 5, 10, 'dessert'],
    [44, 'Kani Salad', 240.00, 'uploads/68f4c06db115e_9918BF56-DF46-469E-BCA9-BEBAEF1B31CE_4_5005_c.jpeg', 5, 12, 'pasta'],
    [45, 'Carbonara', 260.00, 'uploads/68f4c0b4df829_4486ADD2-EACF-4E7D-80CC-D1913869ED82_1_105_c.jpeg', 5, 10, 'dessert'],
    [46, 'Tonkatsu', 280.00, 'uploads/68f4c0eedf72e_93C2B5D1-EBB5-41AC-98E4-CF747DE7FE13_4_5005_c.jpeg', 5, 6, 'burger']
];

foreach ($suggested_products as $p) {
    $check = $conn->query("SELECT id FROM products WHERE id = " . $p[0]);
    if ($check && $check->num_rows == 0) {
        $stmt = $conn->prepare("INSERT INTO products (id, name, price, image, rating, stock, category) VALUES (?, ?, ?, ?, ?, ?, ?)");
        $stmt->bind_param("isdsiis", $p[0], $p[1], $p[2], $p[3], $p[4], $p[5], $p[6]);
        if ($stmt->execute()) {
            echo "Inserted missing product: {$p[1]} (ID: {$p[0]})\n";
        } else {
            echo "Error inserting product {$p[1]}: " . $stmt->error . "\n";
        }
    } else {
        echo "Product already exists: {$p[1]} (ID: {$p[0]})\n";
    }
}

echo "Migration complete.\n";
?>