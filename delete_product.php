<?php
// 1. ENABLE ERROR REPORTING (Helps fix the 500 error)
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// 2. CONNECT TO DATABASE
include 'db_connect.php'; 
require_once 'audit_log.php';

// 3. CHECK IF ID IS PRESENT
if (isset($_GET['id'])) {
    $product_id = $_GET['id'];

    // VALIDATE ID (Make sure it's a number to prevent SQL injection)
    if (!is_numeric($product_id)) {
        die("Invalid ID");
    }

    try {
        // --- FIX: USE CORRECT TABLE 'recently_deleted_products' ---
        $target_table = "recently_deleted_products";

        // STEP A: CREATE TABLE IF NOT EXISTS (Clone structure from products)
        // Note: CREATE TABLE LIKE does not verify specific columns if table already exists.
        $conn->query("CREATE TABLE IF NOT EXISTS `$target_table` LIKE products");

        // STEP B: ADD 'deleted_at' COLUMN IF MISSING
        $cols = $conn->query("SHOW COLUMNS FROM `$target_table` LIKE 'deleted_at'");
        if ($cols->num_rows == 0) {
            $conn->query("ALTER TABLE `$target_table` ADD COLUMN deleted_at DATETIME DEFAULT CURRENT_TIMESTAMP");
        }

        // STEP C: COPY to 'recently_deleted_products'
        // We need to handle column mismatch if 'deleted_at' is present in target but not source.
        // Option 1: Insert into all columns except 'deleted_at', then update 'deleted_at'.
        // But getting all columns dynamically is tedious.
        // Option 2: Insert using SELECT *, NOW(). (Works if deleted_at is the only extra column at the end).
        
        // Let's check column count or just try the simple insert first.
        // If target has extra column, simple INSERT ... SELECT * fails.
        
        // Robust approach: Get column names from products
        $columns = [];
        $res = $conn->query("SHOW COLUMNS FROM products");
        while ($row = $res->fetch_assoc()) {
            $columns[] = $row['Field'];
        }
        $col_list = implode(", ", $columns);
        
        // Insert with explicit columns + deleted_at
        $copy_query = "INSERT INTO `$target_table` ($col_list, deleted_at) SELECT $col_list, NOW() FROM products WHERE id = ?";
        
        $stmt_copy = $conn->prepare($copy_query);
        $stmt_copy->bind_param("i", $product_id);
        $stmt_copy->execute();
        $stmt_copy->close();

        // STEP D: DELETE from 'products'
        $delete_query = "DELETE FROM products WHERE id = ?";
        $stmt_delete = $conn->prepare($delete_query);
        $stmt_delete->bind_param("i", $product_id);
        
        if ($stmt_delete->execute()) {
            // STEP E: LOG ACTION
            $p_name = $product['name'] ?? "ID #$product_id";
            logAdminAction($conn, $_SESSION['user_id'] ?? 0, $_SESSION['fullname'] ?? 'Admin', 'product_to_recycle', "Moved product '$p_name' to Recycle Bin", 'products', $product_id);
            
            // STEP F: REDIRECT back to the main page
            header("Location: practiceaddproduct.php?msg=ProductMovedToBin");
            exit();
        } else {
            echo "Error deleting product.";
        }
        $stmt_delete->close();

    } catch (Exception $e) {
        // This will print the specific error if something goes wrong
        echo "Error: " . $e->getMessage();
    }

} else {
    // If no ID is provided, go back
    header("Location: practiceaddproduct.php?error=NoID");
    exit();
}

$conn->close();
?>