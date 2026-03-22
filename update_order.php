<?php
require_once 'config.php'; // Use the central config file for DB connection
require_once 'notifications.php';
require_once 'audit_log.php';
session_start();

// Check if the main connection ($conn) from config.php is working
if (!isset($conn) || $conn->connect_error) {
    die("Connection failed: " . ($conn->connect_error ?? 'Database configuration error'));
}

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
            $columns = [];
            $res_cols = $conn->query("SHOW COLUMNS FROM cart");
            while ($c = $res_cols->fetch_assoc()) { $columns[] = "`" . $c['Field'] . "`"; }
            $col_list = implode(", ", $columns);
            
            // D. Check for additional columns in target table (e.g., 'order_id')
            $target_columns = [];
            $res_target_cols = $conn->query("SHOW COLUMNS FROM `$target_table` ");
            while ($tc = $res_target_cols->fetch_assoc()) { $target_columns[] = $tc['Field']; }

            $extra_cols = ""; $extra_vals = "";
            if (in_array('order_id', $target_columns) && !in_array('order_id', array_map(function($c) { return trim($c, "`"); }, $columns))) {
                $extra_cols = ", order_id"; $extra_vals = ", id";
            }

            // Insert into recycle bin
            $copy_sql = "INSERT INTO `$target_table` ($col_list $extra_cols, deleted_at) SELECT $col_list $extra_vals, NOW() FROM cart WHERE id = ?";
            $ins = $conn->prepare($copy_sql);
            if (!$ins) throw new Exception("Prepare INSERT failed: " . $conn->error);
            $ins->bind_param("i", $id);
            $ins->execute();
            $ins->close();

            // 3) Delete from cart
            $del = $conn->prepare("DELETE FROM cart WHERE id = ?");
            $del->bind_param("i", $id);
            $del->execute();
            $del->close();

            $conn->commit();

            logAdminAction($conn, $_SESSION['user_id'] ?? 0, $_SESSION['fullname'] ?? 'Admin', 'order_delete', "Moved order #$id to recycle bin", 'cart', $id);

        } else {
            // General Status Update Logic
            $status_map = [
                'cancel' => 'Cancelled',
                'accept' => 'Confirmed',
                'approve' => 'Confirmed',
                'processing' => 'Processing',
                'preparing' => 'Processing',
                'out_for_delivery' => 'Out for Delivery',
                'completed' => 'Delivered'
            ];

            if (!isset($status_map[$action_l])) {
                throw new Exception("Unknown action: " . htmlspecialchars($action));
            }

            $new_status = $status_map[$action_l];

            // 1) Get order details
            $sel = $conn->prepare("SELECT * FROM cart WHERE id = ?");
            $sel->bind_param("i", $id);
            $sel->execute();
            $order = $sel->get_result()->fetch_assoc();
            $sel->close();

            if (!$order) throw new Exception("Order #$id not found.");

            // 2) Restore stock if cancelling
            if ($new_status === 'Cancelled') {
                $cart_items = json_decode($order['cart'] ?? '[]', true);
                foreach ($cart_items as $item) {
                    $pid = intval($item['id'] ?? 0);
                    $qty = intval($item['quantity'] ?? 1);
                    if ($pid > 0) {
                        $upd_s = $conn->prepare("UPDATE products SET stock = stock + ? WHERE id = ?");
                        $upd_s->bind_param("ii", $qty, $pid);
                        $upd_s->execute();
                        $upd_s->close();
                    }
                }
            }

            // 3) Update Cart Table
            $up_cart = $conn->prepare("UPDATE cart SET status = ? WHERE id = ?");
            $up_cart->bind_param("si", $new_status, $id);
            $up_cart->execute();
            $up_cart->close();

            // 4) Update Orders Table (Sync)
            // Use order_id if linked, otherwise fallback to heuristic
            if ($order['order_id']) {
                $up_orders = $conn->prepare("UPDATE orders SET status = ? WHERE id = ?");
                $up_orders->bind_param("si", $new_status, $order['order_id']);
            } else {
                $up_orders = $conn->prepare("UPDATE orders SET status = ? WHERE user_id = ? AND total = ? AND status = 'Pending' ORDER BY created_at DESC LIMIT 1");
                $up_orders->bind_param("sid", $new_status, $order['user_id'], $order['total']);
            }
            $up_orders->execute();
            $up_orders->close();

            // 5) Special case for revenue on completion
            if ($new_status === 'Delivered') {
                $ins_rev = $conn->prepare("INSERT INTO revenue (order_id, amount, date_created) VALUES (?, ?, NOW())");
                $ins_rev->bind_param("id", $id, $order['total']);
                $ins_rev->execute();
                $ins_rev->close();
            }

            $conn->commit();

            // Audit & Notification
            logAdminAction($conn, $_SESSION['user_id'] ?? 0, $_SESSION['fullname'] ?? 'Admin', "order_" . strtolower($new_status), "Updated order #$id to $new_status", 'cart', $id);
            
            if ($order['user_id']) {
                $notif_titles = [
                    'Cancelled' => 'Order Cancelled',
                    'Confirmed' => 'Order Confirmed',
                    'Processing' => 'Order is being prepared',
                    'Out for Delivery' => 'Out for delivery',
                    'Delivered' => 'Order Completed!'
                ];
                $notif_msgs = [
                    'Cancelled' => 'Your order #'.$id.' has been cancelled.',
                    'Confirmed' => 'Your order #'.$id.' has been confirmed.',
                    'Processing' => 'Your order #'.$id.' is now being prepared.',
                    'Out for Delivery' => 'Your order #'.$id.' is now out for delivery.',
                    'Delivered' => 'Your order #'.$id.' has been completed. Thank you!'
                ];
                createNotification($conn, $order['user_id'], $id, "order_" . strtolower($new_status), $notif_titles[$new_status], $notif_msgs[$new_status]);
            }
        }

        header("Location: Orders.php");
        exit();

    } catch (Exception $e) {
        if ($transaction_started) $conn->rollback();
        error_log("update_order.php error: " . $e->getMessage());
        die("Operation failed: " . htmlspecialchars($e->getMessage()));
    }
}
$conn->close();
?>