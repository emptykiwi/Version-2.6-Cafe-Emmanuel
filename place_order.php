<?php
// 1. ENABLE ERROR REPORTING (Remove these 3 lines once the website is live)
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Start session to get user_id
session_start();

// 2. CONNECT TO DATABASE
require_once 'db_connect.php'; // Ensure this points to your actual DB connection file

// Set header to return JSON so JavaScript can read it easily
header('Content-Type: application/json');

try {
    // 3. GET JSON DATA FROM JAVASCRIPT
    $json_data = file_get_contents('php://input');
    $data = json_decode($json_data, true);

    // Check if data actually arrived
    if (!$data || !isset($data['cart'])) {
        throw new Exception("No cart data received.");
    }

    $cart = $data['cart'];
    
    // Retrieve all values passed from checkout.php
    $total = $data['total'] ?? 0;
    $fullname = $data['fullname'] ?? '';
    $contact = $data['contact'] ?? '';
    $address = $data['address'] ?? '';
    $payment_method = $data['payment'] ?? '';
    
    // Check for user ID (Fallback to 0 or a Guest ID if you allow guest checkout)
    $user_id = $_SESSION['user_id'] ?? null; 
    
    if (!$user_id) {
        throw new Exception("User is not logged in. Missing session user_id.");
    }

    // 4. START DATABASE TRANSACTION
    $conn->begin_transaction();

    // 5. INSERT INTO ORDERS TABLE
    // Fixed column names to match the `orders` table schema
    $stmt = $conn->prepare("INSERT INTO orders (user_id, fullname, contact, address, payment_method, total, status, created_at) VALUES (?, ?, ?, ?, ?, ?, 'Pending', NOW())");
    
    if (!$stmt) {
        throw new Exception("SQL Prepare Error (Orders): " . $conn->error);
    }

    // Bind parameters: i = integer, s = string, d = double (decimal)
    $stmt->bind_param("issssd", $user_id, $fullname, $contact, $address, $payment_method, $total);
    
    if (!$stmt->execute()) {
        throw new Exception("SQL Execute Error (Orders): " . $stmt->error);
    }

    // Get the ID of the order we just created
    $order_id = $conn->insert_id;

    // 5b. ALSO INSERT INTO CART TABLE (For Admin Dashboard visibility)
    // The Admin Dashboard uses the 'cart' table to display and manage orders.
    $cart_json = json_encode($cart);
    $stmt_cart = $conn->prepare("INSERT INTO cart (user_id, fullname, contact, address, cart, total, status, created_at) VALUES (?, ?, ?, ?, ?, ?, 'Pending', NOW())");
    
    if (!$stmt_cart) {
        throw new Exception("SQL Prepare Error (Cart): " . $conn->error);
    }

    $stmt_cart->bind_param("issssd", $user_id, $fullname, $contact, $address, $cart_json, $total);
    
    if (!$stmt_cart->execute()) {
        throw new Exception("SQL Execute Error (Cart): " . $stmt_cart->error);
    }

    // 6. INSERT CART ITEMS INTO ORDER_ITEMS TABLE
    // Check if the new columns exist in order_items table to avoid SQL errors
    $column_check = $conn->query("SHOW COLUMNS FROM `order_items` LIKE 'size'");
    $has_custom_columns = ($column_check && $column_check->num_rows > 0);

    if ($has_custom_columns) {
        // Modern Schema: Use structured columns
        $stmt_items = $conn->prepare("INSERT INTO order_items (order_id, product_id, product_name, size, temperature, addon_details, quantity, price) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
        if (!$stmt_items) throw new Exception("SQL Prepare Error (Modern Items): " . $conn->error);
    } else {
        // Legacy Schema: Fallback to appending details to the product name
        $stmt_items = $conn->prepare("INSERT INTO order_items (order_id, product_id, product_name, quantity, price) VALUES (?, ?, ?, ?, ?)");
        if (!$stmt_items) throw new Exception("SQL Prepare Error (Legacy Items): " . $conn->error);
    }

    foreach ($cart as $item) {
        $product_id = $item['id'];
        $base_name = $item['name'] ?? 'Product';
        $size = $item['size'] ?? 'Standard';
        $temperature = $item['temperature'] ?? 'N/A';
        $addon = $item['selectedAddon'] ?? null;
        $quantity = $item['quantity'];
        $price = (float)$item['price'] + (float)($item['selectedAddonPrice'] ?? 0);

        if ($has_custom_columns) {
            $stmt_items->bind_param("iissssid", $order_id, $product_id, $base_name, $size, $temperature, $addon, $quantity, $price);
        } else {
            // Format name for legacy dashboard visibility
            $display_name = $base_name;
            if ($size !== 'Standard') $display_name .= " ({$size})";
            if ($temperature !== 'N/A') $display_name .= " [{$temperature}]";
            if ($addon) $display_name .= " + {$addon}";

            $stmt_items->bind_param("iisid", $order_id, $product_id, $display_name, $quantity, $price);
        }
        
        if (!$stmt_items->execute()) {
            throw new Exception("SQL Execute Error on Product ID {$product_id}: " . $stmt_items->error);
        }
    }

    // 7. COMMIT TRANSACTION (Save everything permanently)
    $conn->commit();

    // Send success back to Javascript
    // Changed "success" to "status" to match what checkout.php is expecting
    echo json_encode([
        "status" => "success", 
        "message" => "Order placed successfully!",
        "order_id" => $order_id
    ]);

} catch (Exception $e) {
    // If ANYTHING goes wrong, cancel the database save and send the exact error back
    if (isset($conn) && $conn->ping()) {
        $conn->rollback(); 
    }
    
    // Log the error for you to read
    error_log("Checkout Error: " . $e->getMessage());
    
    // Send the error back to the browser console
    echo json_encode([
        "status" => "error", 
        "message" => $e->getMessage()
    ]);
}
?>