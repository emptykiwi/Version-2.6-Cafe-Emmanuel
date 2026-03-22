<?php
session_start();

// 1. Enable Error Reporting (Helps debug if there is still an issue)
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// 2. Connect to Database using your existing file
require_once 'db_connect.php'; 

// 3. Include Audit Log
if (file_exists('audit_log.php')) {
    require_once 'audit_log.php';
}

// Check connection validity
if (!isset($conn) || $conn->connect_error) {
    die("Connection failed: " . ($conn->connect_error ?? "Database variable not set"));
}

// Ensure the current user is an admin before proceeding
if (!isset($_SESSION['role']) || !in_array($_SESSION['role'], ['admin', 'super_admin'])) {
    die("Access denied.");
}

if (isset($_GET['action']) && isset($_GET['id'])) {
    $action = $_GET['action'];
    $user_id = intval($_GET['id']);
    $admin_id = $_SESSION['user_id'];
    $admin_name = $_SESSION['fullname'];

    // Security check: an admin cannot demote or delete their own account
    if ($user_id == $admin_id) {
        header("Location: user_accounts.php?error=cannot_modify_self");
        exit();
    }

    // Begin Transaction to ensure data integrity
    $conn->begin_transaction();
    
    try {
        if ($action === 'promote') {
            // Only super_admin can promote
            if ($_SESSION['role'] === 'super_admin') {
                $stmt = $conn->prepare("UPDATE users SET role = 'admin' WHERE id = ?");
                $stmt->bind_param("i", $user_id);
                $stmt->execute();
                $stmt->close();
                
                if (function_exists('logAdminAction')) {
                    logAdminAction($conn, $admin_id, $admin_name, 'user_role_change', "Promoted user ID #{$user_id} to admin", 'users', $user_id);
                }
            }
        } elseif ($action === 'demote') {
            // Only super_admin can demote
            if ($_SESSION['role'] === 'super_admin') {
                $stmt = $conn->prepare("UPDATE users SET role = 'user' WHERE id = ?");
                $stmt->bind_param("i", $user_id);
                $stmt->execute();
                $stmt->close();
                
                if (function_exists('logAdminAction')) {
                    logAdminAction($conn, $admin_id, $admin_name, 'user_role_change', "Demoted user ID #{$user_id} to user", 'users', $user_id);
                }
            }
        } elseif ($action === 'delete') {
            // 1. Get the user's data
            $stmt = $conn->prepare("SELECT * FROM users WHERE id = ?");
            $stmt->bind_param("i", $user_id);
            $stmt->execute();
            $result = $stmt->get_result();
            $user = $result->fetch_assoc();
            $stmt->close();

            if ($user) {
                // Security check: Admin cannot delete super_admin
                if ($user['role'] === 'super_admin' && $_SESSION['role'] !== 'super_admin') {
                     throw new Exception("Admin cannot delete Super Admin.");
                }

                // --- ROBUST RECYCLE BIN LOGIC WITH SCHEMA SYNC ---
                $target_table = 'recently_deleted_users';
                
                // A. Ensure table exists (Clone structure)
                $conn->query("CREATE TABLE IF NOT EXISTS `$target_table` LIKE users");
                
                // B. Auto-sync missing columns from users to recently_deleted_users
                $user_cols_res = $conn->query("SHOW COLUMNS FROM users");
                $target_cols_res = $conn->query("SHOW COLUMNS FROM `$target_table`");
                
                $target_cols = [];
                if ($target_cols_res) {
                    while ($row = $target_cols_res->fetch_assoc()) {
                        $target_cols[] = $row['Field'];
                    }
                }

                $columns_for_insert = [];
                if ($user_cols_res) {
                    while ($row = $user_cols_res->fetch_assoc()) {
                        $field = $row['Field'];
                        $columns_for_insert[] = "`$field`";
                        
                        // If column is missing in target table, dynamically add it
                        if (!in_array($field, $target_cols)) {
                            $type = $row['Type'];
                            $conn->query("ALTER TABLE `$target_table` ADD COLUMN `$field` $type");
                        }
                    }
                }

                // C. Ensure 'deleted_at' column exists
                $cols_check = $conn->query("SHOW COLUMNS FROM `$target_table` LIKE 'deleted_at'");
                if ($cols_check->num_rows == 0) {
                    $conn->query("ALTER TABLE `$target_table` ADD COLUMN deleted_at DATETIME DEFAULT CURRENT_TIMESTAMP");
                }
                
                // D. Copy record with ALL columns dynamically
                $col_list = implode(", ", $columns_for_insert);
                
                // Insert into recycle bin
                $copy_sql = "INSERT INTO `$target_table` ($col_list, deleted_at) SELECT $col_list, NOW() FROM users WHERE id = ?";
                $stmt_copy = $conn->prepare($copy_sql);
                if (!$stmt_copy) {
                     throw new Exception("Prepare failed: " . $conn->error);
                }
                $stmt_copy->bind_param("i", $user_id);
                if (!$stmt_copy->execute()) {
                    throw new Exception("Failed to move user to recycle bin: " . $stmt_copy->error);
                }
                $stmt_copy->close();

                // 3. Delete from the main users table
                $delete_stmt = $conn->prepare("DELETE FROM users WHERE id = ?");
                $delete_stmt->bind_param("i", $user_id);
                if (!$delete_stmt->execute()) {
                    throw new Exception("Failed to delete user from main table.");
                }
                $delete_stmt->close();

                // Log the action
                if (function_exists('logAdminAction')) {
                    logAdminAction(
                        $conn,
                        $admin_id,
                        $admin_name,
                        'user_delete',
                        "Moved user to recycle bin: {$user['username']} (ID: {$user_id})",
                        'users',
                        $user_id
                    );
                }
            } else {
                throw new Exception("User not found.");
            }
        }
        
        $conn->commit();
        header("Location: user_accounts.php?success=user_deleted");

    } catch (Exception $e) {
        $conn->rollback();
        // Log the error
        if (function_exists('logAdminAction')) {
            logAdminAction(
                $conn,
                $admin_id,
                $admin_name,
                'user_action_failed',
                "Failed action '{$action}' on user ID {$user_id}. Error: {$e->getMessage()}",
                'users',
                $user_id
            );
        }
        // Redirect with specific error message
        header("Location: user_accounts.php?error=" . urlencode($e->getMessage()));
    }
} else {
    // Redirect if no action
    header("Location: user_accounts.php");
}
exit();
?>