<?php
require_once 'config.php'; // Use the central config file for DB connection
require_once 'notifications.php';
require_once 'audit_log.php';
session_start();

// Check if the main connection ($conn) from config.php is working
if (!isset($conn) || $conn->connect_error) {
    die("Connection failed: " . ($conn->connect_error ?? 'Database configuration error'));
}

// Since on Hostinger all tables are in ONE database, we can use $conn for everything.
// We alias $user_conn to $conn so your existing code below works without changes.
$user_conn = $conn;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id = intval($_POST['id']);
    $action = $_POST['action'];
    $action_l = strtolower($action);

    $conn->begin_transaction();
    $transaction_started = true;

    try {
        if ($action_l === 'delete') {
            // 1) Get the order
            $sel = $conn->prepare("SELECT * FROM cart WHERE id = ?");
            if (!$sel) throw new Exception("Prepare SELECT failed: " . $conn->error);
            $sel->bind_param("i", $id);
            $sel->execute();
            $res = $sel->get_result();
            if ($res->num_rows === 0) {
                $sel->close();
                throw new Exception("Order not found (id=$id).");
            }
            $row = $res->fetch_assoc();
            $sel->close();

            // 2) --- ROBUST RECYCLE BIN LOGIC ---
            $target_table = 'recently_deleted';
            
            // A. Ensure table exists (Clone structure)
            $conn->query("CREATE TABLE IF NOT EXISTS `$target_table` LIKE cart");
            
            // B. Ensure 'deleted_at' column exists
            $cols = $conn->query("SHOW COLUMNS FROM `$target_table` LIKE 'deleted_at'");
            if ($cols->num_rows == 0) {
                $conn->query("ALTER TABLE `$target_table` ADD COLUMN deleted_at DATETIME DEFAULT CURRENT_TIMESTAMP");
            }
            
            // C. Copy record with ALL columns dynamically
            // Get columns from 'cart' table
            $columns = [];
            $res_cols = $conn->query("SHOW COLUMNS FROM cart");
            while ($c = $res_cols->fetch_assoc()) {
                $columns[] = "`" . $c['Field'] . "`";
            }
            $col_list = implode(", ", $columns);
            
            // Insert into recycle bin
            $copy_sql = "INSERT INTO `$target_table` ($col_list, deleted_at) SELECT $col_list, NOW() FROM cart WHERE id = ?";
            $ins = $conn->prepare($copy_sql);
            if (!$ins) throw new Exception("Prepare INSERT recently_deleted failed: " . $conn->error);

            $ins->bind_param("i", $id);
            if (!$ins->execute()) {
                $ins->close();
                throw new Exception("Execute INSERT failed: " . $ins->error);
            }
            $ins->close();

            // 3) Delete from cart
            $del = $conn->prepare("DELETE FROM cart WHERE id = ?");
            if (!$del) throw new Exception("Prepare DELETE failed: " . $conn->error);
            $del->bind_param("i", $id);
            if (!$del->execute()) {
                $del->close();
                throw new Exception("Execute DELETE failed: " . $del->error);
            }
            $del->close();

            $conn->commit();

        } elseif ($action_l === 'cancel') {
            // Get order details first
            $sel = $conn->prepare("SELECT * FROM cart WHERE id = ?");
            if (!$sel) throw new Exception("Prepare SELECT failed: " . $conn->error);
            $sel->bind_param("i", $id);
            $sel->execute();
            $order = $sel->get_result()->fetch_assoc();
            $sel->close();
            
            $cart_json = $order['cart'] ?? null;

            if ($cart_json) {
                $cart_items = json_decode($cart_json, true);
                if ($cart_items && is_array($cart_items)) {
                    foreach ($cart_items as $item) {
                        $product_id = isset($item['id']) ? intval($item['id']) : 0;
                        $qty = isset($item['quantity']) ? intval($item['quantity']) : 1;

                        if ($product_id > 0) {
                            $upd = $conn->prepare("UPDATE products SET stock = stock + ? WHERE id = ?");
                            if (!$upd) throw new Exception("Prepare UPDATE failed: " . $conn->error);
                            $upd->bind_param("ii", $qty, $product_id);
                            if (!$upd->execute()) {
                                $upd->close();
                                throw new Exception("Execute UPDATE failed: " . $upd->error);
                            }
                            $upd->close();
                        }
                    }
                }
            }

            $up = $conn->prepare("UPDATE cart SET status = 'Cancelled' WHERE id = ?");
            if (!$up) throw new Exception("Prepare UPDATE status failed: " . $conn->error);
            $up->bind_param("i", $id);
            if (!$up->execute()) {
                $up->close();
                throw new Exception("Execute UPDATE status failed: " . $up->error);
            }
            $up->close();

            $conn->commit();
            
            // Log audit
            logAdminAction(
                $user_conn,
                $_SESSION['user_id'] ?? 0,
                $_SESSION['fullname'] ?? 'Admin',
                'order_cancel', 
                "Cancelled order #$id (Customer: {$order['fullname']})",
                'cart',
                $id
            );
            
            // Create notification for cancelled order
            if (isset($order['user_id']) && $order['user_id']) {
                createNotification(
                    $user_conn,
                    $order['user_id'],
                    $id,
                    'order_cancelled',
                    'Order Cancelled',
                    'Your order #' . $id . ' has been cancelled.'
                );
            }

        } elseif ($action_l === 'accept' || $action_l === 'approve') {
            // Get order details
            $sel = $conn->prepare("SELECT * FROM cart WHERE id = ?");
            if (!$sel) throw new Exception("Prepare SELECT failed: " . $conn->error);
            $sel->bind_param("i", $id);
            $sel->execute();
            $order = $sel->get_result()->fetch_assoc();
            $sel->close();
            
            // Update status to Confirmed
            $up = $conn->prepare("UPDATE cart SET status = 'Confirmed' WHERE id = ?");
            if (!$up) throw new Exception("Prepare UPDATE accept failed: " . $conn->error);
            $up->bind_param("i", $id);
            if (!$up->execute()) {
                $up->close();
                throw new Exception("Execute UPDATE accept failed: " . $up->error);
            }
            $up->close();
            
            $conn->commit();
            
            // Log audit
            logAdminAction(
                $user_conn,
                $_SESSION['user_id'] ?? 0,
                $_SESSION['fullname'] ?? 'Admin',
                'order_confirm',
                "Confirmed order #$id for customer: {$order['fullname']}",
                'cart',
                $id
            );
            
            // Create notification for accepted/confirmed order
            if (isset($order['user_id']) && $order['user_id']) {
                createNotification(
                    $user_conn,
                    $order['user_id'],
                    $id,
                    'order_confirmed',
                    'Order Confirmed',
                    'Your order #' . $id . ' has been confirmed and will be prepared soon.'
                );
            }

        } elseif ($action_l === 'processing' || $action_l === 'preparing') {
            // Move order to Processing / Food Preparing
            $sel = $conn->prepare("SELECT * FROM cart WHERE id = ?");
            if (!$sel) throw new Exception("Prepare SELECT failed: " . $conn->error);
            $sel->bind_param("i", $id);
            $sel->execute();
            $order = $sel->get_result()->fetch_assoc();
            $sel->close();

            $up = $conn->prepare("UPDATE cart SET status = 'Processing' WHERE id = ?");
            if (!$up) throw new Exception("Prepare UPDATE processing failed: " . $conn->error);
            $up->bind_param("i", $id);
            if (!$up->execute()) {
                $up->close();
                throw new Exception("Execute UPDATE processing failed: " . $up->error);
            }
            $up->close();

            $conn->commit();

            logAdminAction(
                $user_conn,
                $_SESSION['user_id'] ?? 0,
                $_SESSION['fullname'] ?? 'Admin',
                'order_processing',
                "Order #$id is now being prepared for customer: {$order['fullname']}",
                'cart',
                $id
            );

            if (isset($order['user_id']) && $order['user_id']) {
                createNotification(
                    $user_conn,
                    $order['user_id'],
                    $id,
                    'order_processing',
                    'Order is being prepared',
                    'Your order #' . $id . ' is now being prepared.'
                );
            }

        } elseif ($action_l === 'out_for_delivery') {
            // Move order to Out for Delivery
            $sel = $conn->prepare("SELECT * FROM cart WHERE id = ?");
            if (!$sel) throw new Exception("Prepare SELECT failed: " . $conn->error);
            $sel->bind_param("i", $id);
            $sel->execute();
            $order = $sel->get_result()->fetch_assoc();
            $sel->close();

            $up = $conn->prepare("UPDATE cart SET status = 'Out for Delivery' WHERE id = ?");
            if (!$up) throw new Exception("Prepare UPDATE out_for_delivery failed: " . $conn->error);
            $up->bind_param("i", $id);
            if (!$up->execute()) {
                $up->close();
                throw new Exception("Execute UPDATE out_for_delivery failed: " . $up->error);
            }
            $up->close();

            $conn->commit();

            logAdminAction(
                $user_conn,
                $_SESSION['user_id'] ?? 0,
                $_SESSION['fullname'] ?? 'Admin',
                'order_out_for_delivery',
                "Order #$id is out for delivery for customer: {$order['fullname']}",
                'cart',
                $id
            );

            if (isset($order['user_id']) && $order['user_id']) {
                createNotification(
                    $user_conn,
                    $order['user_id'],
                    $id,
                    'order_out_for_delivery',
                    'Out for delivery',
                    'Your order #' . $id . ' is now out for delivery.'
                );
            }

        } elseif ($action_l === 'completed') {
            // Get order details para maipasok sa revenue
            $sel = $conn->prepare("SELECT * FROM cart WHERE id = ?");
            if (!$sel) throw new Exception("Prepare SELECT failed: " . $conn->error);
            $sel->bind_param("i", $id);
            $sel->execute();
            $order = $sel->get_result()->fetch_assoc();
            $sel->close();

            // Update status to Delivered (final state)
            $up = $conn->prepare("UPDATE cart SET status = 'Delivered' WHERE id = ?");
            if (!$up) throw new Exception("Prepare UPDATE delivered failed: " . $conn->error);
            $up->bind_param("i", $id);
            if (!$up->execute()) {
                $up->close();
                throw new Exception("Execute UPDATE completed failed: " . $up->error);
            }
            $up->close();

            // Insert revenue record
            $ins_rev = $conn->prepare("INSERT INTO revenue (order_id, amount, date_created) VALUES (?, ?, NOW())");
            if (!$ins_rev) throw new Exception("Prepare INSERT revenue failed: " . $conn->error);
            $ins_rev->bind_param("id", $order['id'], $order['total']);
            if (!$ins_rev->execute()) {
                $ins_rev->close();
                throw new Exception("Execute INSERT revenue failed: " . $ins_rev->error);
            }
            $ins_rev->close();

            $conn->commit();
            
            // Log audit
            logAdminAction(
                $user_conn,
                $_SESSION['user_id'] ?? 0,
                $_SESSION['fullname'] ?? 'Admin',
                'order_complete',
                "Marked order #$id as completed for customer: {$order['fullname']} - Amount: ₱" . number_format($order['total'], 2),
                'cart',
                $id
            );
            
            // Create notification for completed order
            if (isset($order['user_id']) && $order['user_id']) {
                createNotification(
                    $user_conn,
                    $order['user_id'],
                    $id,
                    'order_completed',
                    'Order Completed!',
                    'Your order #' . $id . ' has been completed. Thank you for your purchase!'
                );
            }
        } else {
            $conn->rollback();
            throw new Exception("Unknown action: " . htmlspecialchars($action));
        }

        header("Location: Orders.php");
        exit();

    } catch (Exception $e) {
        if ($transaction_started) $conn->rollback();
        error_log("update_order.php error: " . $e->getMessage());
        die("Operation failed: " . htmlspecialchars($e->getMessage()));
    }
}

// Close connection (only need to close once since we aliased)
$conn->close();
?>