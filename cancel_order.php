<?php
// cancel_order.php
// Customer-initiated order cancellation. Restores stock and logs audit.
// Expects POST: order_id

session_start();
require_once 'config.php'; // Use config.php for DB connection
require_once __DIR__ . '/audit.php';

if (!isset($_SESSION['user_id']) || !isset($_SESSION['fullname'])) {
    http_response_code(403);
    exit('Not logged in');
}

// Use $conn from config.php
if (!isset($conn) || $conn->connect_error) {
    http_response_code(500);
    exit('DB error');
}

$userId = (int)$_SESSION['user_id'];
$fullName = $_SESSION['fullname'];
$orderId = (int)($_POST['order_id'] ?? 0);

if ($orderId <= 0) {
    http_response_code(400);
    exit('Invalid order');
}

// Alias $conn to $menu for existing logic
$menu = $conn;

$menu->begin_transaction();
try {
    // Verify ownership and status (prefer user_id, fallback to legacy fullname)
    $uid = (int)$userId;
    $q = $menu->prepare("SELECT id, status FROM orders WHERE id=? AND (user_id=? OR fullname=?) LIMIT 1");
    $q->bind_param('iis', $orderId, $uid, $fullName);
    $q->execute();
    $ord = $q->get_result()->fetch_assoc();
    if (!$ord || $ord['status'] !== 'Pending') {
        throw new Exception('Only your pending orders can be cancelled');
    }

    // Restore stock
    $it = $menu->prepare("SELECT product_id, quantity FROM order_items WHERE order_id=?");
    $it->bind_param('i', $orderId);
    $it->execute();
    $res = $it->get_result();
    while ($row = $res->fetch_assoc()) {
        $st = $menu->prepare("UPDATE products SET stock = stock + ? WHERE id=?");
        $st->bind_param('ii', $row['quantity'], $row['product_id']);
        $st->execute();
    }

    // Mark cancelled
    $upd = $menu->prepare("UPDATE orders SET status='Cancelled' WHERE id=?");
    $upd->bind_param('i', $orderId);
    $upd->execute();

    $menu->commit();
    
    if (function_exists('audit')) {
        audit($userId, 'order_cancel', 'orders', $orderId, ['by'=>'customer']);
    }
    
    header('Location: my_orders.php?msg=cancelled');
} catch (Exception $e) {
    $menu->rollback();
    if (function_exists('audit')) {
        audit($userId, 'order_cancel_failed', 'orders', $orderId, ['error'=>$e->getMessage()]);
    }
    header('Location: my_orders.php?err=cancel_failed');
}
?>