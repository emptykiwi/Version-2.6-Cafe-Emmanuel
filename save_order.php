<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
header('Content-Type: application/json'); // Return JSON

// 1. Session start is required to grab the logged-in user's ID
session_start();
$conn = new mysqli("localhost", "root", "", "addproduct");

if ($conn->connect_error) {
    http_response_code(500);
    echo json_encode(["success" => false, "error" => "Database connection failed: " . $conn->connect_error]);
    exit;
}

$data = json_decode(file_get_contents("php://input"), true);

// Validate input
if (
    !$data ||
    empty($data['fullname']) ||
    empty($data['contact']) ||
    empty($data['address']) ||
    empty($data['cart']) ||
    !isset($data['total'])
) {
    http_response_code(400);
    echo json_encode(["success" => false, "error" => "Invalid input."]);
    exit;
}

$fullname = $conn->real_escape_string($data['fullname']);
$contact  = $conn->real_escape_string($data['contact']);
$address  = $conn->real_escape_string($data['address']);
$cart     = $data['cart']; // Array of cart items
$total    = floatval($data['total']);
$cart_json = json_encode($cart);

// ==========================================
// 🚨 CRITICAL FIX: GRABBING THE USER ID
// ==========================================
// Double check if your login script uses 'id' instead of 'user_id'. 
// If it uses 'id', change it to: $_SESSION['id'] ?? null;
$uid = $_SESSION['user_id'] ?? null; 

if ($uid) {
    // If user is logged in, insert the order WITH the user_id
    $stmt = $conn->prepare("INSERT INTO cart (fullname, contact, address, cart, total, status, created_at, user_id) VALUES (?, ?, ?, ?, ?, 'Pending', NOW(), ?)");
    $stmt->bind_param("ssssdi", $fullname, $contact, $address, $cart_json, $total, $uid);
} else {
    // Fallback just in case a guest checks out (if your system allows guests)
    $stmt = $conn->prepare("INSERT INTO cart (fullname, contact, address, cart, total, status, created_at) VALUES (?, ?, ?, ?, ?, 'Pending', NOW())");
    $stmt->bind_param("ssssd", $fullname, $contact, $address, $cart_json, $total);
}

if ($stmt->execute()) {
    // Deduct stock in products table
    foreach ($cart as $item) {
        $quantity = intval($item['quantity'] ?? 0);
        if ($quantity <= 0) continue;

        $product_id = null;
        if (isset($item['id'])) {
            $product_id = intval($item['id']);
        } elseif (isset($item['name'])) {
            $name = $conn->real_escape_string($item['name']);
            $res = $conn->query("SELECT id FROM products WHERE name = '$name' LIMIT 1");
            if ($res && $res->num_rows > 0) {
                $row = $res->fetch_assoc();
                $product_id = intval($row['id']);
            }
        }

        // Deduct stock if the product ID was found
        if ($product_id) {
            $conn->query("UPDATE products SET stock = stock - $quantity WHERE id = $product_id");
        }
    }
    
    // Return success to the frontend
    echo json_encode(["success" => true, "message" => "Order placed successfully."]);
} else {
    echo json_encode(["success" => false, "error" => "Failed to place order."]);
}
?>