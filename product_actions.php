<?php
session_start(); 

// 1. Enable error reporting to debug 500 errors
ini_set('display_errors', 1);
error_reporting(E_ALL);

// 2. Connect to Database using your specific file
require_once 'db_connect.php';

// Check connection
if (!isset($conn) || $conn->connect_error) {
    die("Connection failed: " . ($conn->connect_error ?? "Database variable missing"));
}

// 3. Include Audit Log (Safely)
if (file_exists('audit_log.php')) {
    include 'audit_log.php';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id = intval($_POST['id']);
    $action = $_POST['action'];

    // Use the single $conn from db_connect.php
    $conn->begin_transaction();
    try {
        // Get the deleted product record
        $stmt = $conn->prepare("SELECT * FROM recently_deleted_products WHERE id = ?");
        $stmt->bind_param("i", $id);
        $stmt->execute();
        $result = $stmt->get_result();
        $product = $result->fetch_assoc();

        if (!$product) {
            throw new Exception("Product not found in recently deleted items.");
        }

        if ($action === 'restore') {
            // Restore: Insert it back into the main 'products' table
            // Note: We use the column names from your specific code
            $insert_stmt = $conn->prepare("INSERT INTO products (name, price, stock, image, category, rating) VALUES (?, ?, ?, ?, ?, ?)");
            $insert_stmt->bind_param("sdissi", $product['name'], $product['price'], $product['stock'], $product['image'], $product['category'], $product['rating']);
            $insert_stmt->execute();
            $new_product_id = $conn->insert_id; 

            // Now, delete from the 'recently_deleted_products' table
            $delete_stmt = $conn->prepare("DELETE FROM recently_deleted_products WHERE id = ?");
            $delete_stmt->bind_param("i", $id);
            $delete_stmt->execute();
            
            $_SESSION['success_message'] = "Product restored successfully!";

            // Log Action (if function exists)
            if (function_exists('logAdminAction')) {
                logAdminAction(
                    $conn, // Use the same connection
                    $_SESSION['user_id'] ?? 0,
                    $_SESSION['fullname'] ?? 'Admin',
                    'product_restore',
                    "Restored product: {$product['name']} (New ID: {$new_product_id})",
                    'products',
                    $new_product_id
                );
            }

        } elseif ($action === 'permanent_delete') {
            // Permanently delete: first, remove the image file if it exists
            if (!empty($product['image']) && file_exists($product['image'])) {
                @unlink($product['image']); 
            }
            // Delete the record permanently
            $delete_stmt = $conn->prepare("DELETE FROM recently_deleted_products WHERE id = ?");
            $delete_stmt->bind_param("i", $id);
            $delete_stmt->execute();
            
            $_SESSION['success_message'] = "Product permanently deleted!";

            // Log Action
            if (function_exists('logAdminAction')) {
                logAdminAction(
                    $conn,
                    $_SESSION['user_id'] ?? 0,
                    $_SESSION['fullname'] ?? 'Admin',
                    'product_delete_permanent',
                    "Permanently deleted product: {$product['name']}",
                    'recently_deleted_products',
                    $id
                );
            }
        }

        $conn->commit();

    } catch (Exception $e) {
        $conn->rollback();
        $_SESSION['error_message'] = "An error occurred: " . $e->getMessage();
    }
}

// Close connection
$conn->close();

// Redirect back
header("Location: recently_deleted.php");
exit();
?>