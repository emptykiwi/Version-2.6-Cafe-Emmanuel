<?php
session_start();
include 'db_connect.php';

if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Check if request is POST and required fields exist
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['type'], $_POST['bulk_action'])) {
    
    $type = $_POST['type']; // 'orders', 'products', or 'users'
    $action = $_POST['bulk_action']; // 'restore' or 'delete'
    $ids = $_POST['ids'] ?? []; // Array of IDs

    if (empty($ids)) {
        // No items selected, redirect back
        header("Location: recently_deleted.php?error=No items selected");
        exit;
    }

    // Ensure IDs are integers for security
    $sanitized_ids = array_map('intval', $ids);
    
    // Prepare a comma-separated string for "IN (...)" queries
    // Although we process items one by one for Restore (to handle inserts), 
    // we can use bulk delete for permanent deletion.
    
    // -------------------------
    // PROCESS ORDERS
    // -------------------------
    if ($type === 'orders') {
        foreach ($sanitized_ids as $id) {
            if ($action === 'restore') {
                // Fetch record from recently_deleted
                $stmt = $conn->prepare("SELECT * FROM recently_deleted WHERE id = ?");
                $stmt->bind_param("i", $id);
                $stmt->execute();
                $res = $stmt->get_result();
                
                if ($row = $res->fetch_assoc()) {
                    // Restore to 'cart' table (Based on restore_delete.php logic)
                    $insert = $conn->prepare("INSERT INTO cart (fullname, contact, address, cart, total, status) VALUES (?, ?, ?, ?, ?, ?)");
                    $insert->bind_param("ssssds", 
                        $row['fullname'], 
                        $row['contact'], 
                        $row['address'], 
                        $row['cart'], 
                        $row['total'], 
                        $row['status']
                    );
                    $insert->execute();
                    $insert->close();
                    
                    // Delete from recently_deleted
                    $del = $conn->prepare("DELETE FROM recently_deleted WHERE id = ?");
                    $del->bind_param("i", $id);
                    $del->execute();
                    $del->close();
                }
                $stmt->close();
            } elseif ($action === 'delete') {
                // Permanent Delete
                $del = $conn->prepare("DELETE FROM recently_deleted WHERE id = ?");
                $del->bind_param("i", $id);
                $del->execute();
                $del->close();
            }
        }
    }

    // -------------------------
    // PROCESS PRODUCTS
    // -------------------------
    elseif ($type === 'products') {
        foreach ($sanitized_ids as $id) {
            if ($action === 'restore') {
                // Fetch record
                $stmt = $conn->prepare("SELECT * FROM recently_deleted_products WHERE id = ?");
                $stmt->bind_param("i", $id);
                $stmt->execute();
                $res = $stmt->get_result();

                if ($row = $res->fetch_assoc()) {
                    // Restore to 'products' table
                    $insert = $conn->prepare("INSERT INTO products (product_no, name, quantity, price) VALUES (?, ?, ?, ?)");
                    $insert->bind_param("isid", 
                        $row['product_id'], 
                        $row['name'], 
                        $row['stock'], 
                        $row['price']
                    );
                    $insert->execute();
                    $insert->close();

                    // Delete from recycle bin
                    $del = $conn->prepare("DELETE FROM recently_deleted_products WHERE id = ?");
                    $del->bind_param("i", $id);
                    $del->execute();
                    $del->close();
                }
                $stmt->close();
            } elseif ($action === 'delete') {
                $del = $conn->prepare("DELETE FROM recently_deleted_products WHERE id = ?");
                $del->bind_param("i", $id);
                $del->execute();
                $del->close();
            }
        }
    }

    // -------------------------
    // PROCESS USERS
    // -------------------------
    elseif ($type === 'users') {
        foreach ($sanitized_ids as $id) {
            if ($action === 'restore') {
                // Fetch record
                $stmt = $conn->prepare("SELECT * FROM recently_deleted_users WHERE id = ?");
                $stmt->bind_param("i", $id);
                $stmt->execute();
                $res = $stmt->get_result();

                if ($row = $res->fetch_assoc()) {
                    // Restore to 'users' table (Assuming 'users' is the table name, change if it is 'user_accounts')
                    // Columns based on standard user tables: fullname, username, email, password, etc.
                    // We need to verify if password exists in deleted record, otherwise it might be lost.
                    
                    // Note: If you have different column names, adjust them below.
                    $insert = $conn->prepare("INSERT INTO users (fullname, username, email, password, user_type) VALUES (?, ?, ?, ?, ?)");
                    $insert->bind_param("sssss", 
                        $row['fullname'], 
                        $row['username'], 
                        $row['email'], 
                        $row['password'], // Ensure this column exists in recently_deleted_users
                        $row['user_type'] // Ensure this column exists
                    );
                    
                    // If insert fails (maybe duplicate entry), we might want to skip deletion
                    if ($insert->execute()) {
                        $insert->close();
                        
                        // Delete from recycle bin
                        $del = $conn->prepare("DELETE FROM recently_deleted_users WHERE id = ?");
                        $del->bind_param("i", $id);
                        $del->execute();
                        $del->close();
                    }
                }
                $stmt->close();
            } elseif ($action === 'delete') {
                $del = $conn->prepare("DELETE FROM recently_deleted_users WHERE id = ?");
                $del->bind_param("i", $id);
                $del->execute();
                $del->close();
            }
        }
    }
}

$conn->close();

// Redirect back to recently_deleted.php
header("Location: recently_deleted.php");
exit;
?>