<?php
session_start();

// 1. Enable error reporting
ini_set('display_errors', 1);
error_reporting(E_ALL);

// 2. Connect to Database using your specific file
require_once 'db_connect.php';

// Check connection
if (!isset($conn) || $conn->connect_error) {
    die("Connection failed: " . ($conn->connect_error ?? "Database variable missing"));
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id = intval($_POST['id']);
    $action = $_POST['action'];

    // Get the record from recently_deleted (orders)
    $sql = "SELECT * FROM recently_deleted WHERE id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($row = $result->fetch_assoc()) {
        if ($action === 'restore') {
            // Restore back to 'cart' table using dynamic column mapping
            // This ensures we copy all columns, including new ones like 'user_id'
            
            // Get columns from 'cart' table
            $columns = [];
            $res = $conn->query("SHOW COLUMNS FROM cart");
            while ($c = $res->fetch_assoc()) {
                // Include 'id' to preserve original ID and maintain relationships
                $columns[] = "`" . $c['Field'] . "`";
            }
            
            // Build query dynamically
            $col_names = [];
            $placeholders = [];
            $types = "";
            $values = [];

            foreach ($columns as $quoted_col) {
                $col = trim($quoted_col, "`");
                // Check if the column exists in the source row or map from order_id to id
                if (array_key_exists($col, $row)) {
                    $col_names[] = $quoted_col;
                    $placeholders[] = "?";
                    $values[] = $row[$col];
                    $types .= "s"; 
                } elseif ($col === 'id' && array_key_exists('order_id', $row)) {
                    // Map 'order_id' from recently_deleted back to 'id' in cart
                    $col_names[] = "`id`";
                    $placeholders[] = "?";
                    $values[] = $row['order_id'];
                    $types .= "i";
                }
            }
            
            $sql_insert = "INSERT INTO cart (" . implode(", ", $col_names) . ") VALUES (" . implode(", ", $placeholders) . ")";
            $insert_stmt = $conn->prepare($sql_insert);
            
            if ($insert_stmt) {
                $insert_stmt->bind_param($types, ...$values);
                
                if ($insert_stmt->execute()) {
                    $insert_stmt->close();

                    // Remove from recently_deleted
                    $delete_sql = "DELETE FROM recently_deleted WHERE id = ?";
                    $delete_stmt = $conn->prepare($delete_sql);
                    $delete_stmt->bind_param("i", $id);
                    $delete_stmt->execute();
                    $delete_stmt->close();
                } else {
                    die("Error restoring order: " . $conn->error);
                }
            } else {
                die("Error preparing restore: " . $conn->error);
            }

        } elseif ($action === 'permanent_delete') {
            // Permanently delete
            $delete_sql = "DELETE FROM recently_deleted WHERE id = ?";
            $delete_stmt = $conn->prepare($delete_sql);
            $delete_stmt->bind_param("i", $id);
            $delete_stmt->execute();
            $delete_stmt->close();
        }
    }

    $stmt->close();
    header("Location: recently_deleted.php");
    exit();
}

$conn->close();
?>