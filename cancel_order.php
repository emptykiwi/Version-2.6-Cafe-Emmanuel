<?php
// cancel_order.php
// Customer-initiated order cancellation. Restores stock and logs audit.

session_start();
require_once 'config.php'; // Use config.php for DB connection
require_once __DIR__ . '/audit_log.php';

if (!isset($_SESSION['user_id']) || !isset($_SESSION['fullname'])) {
    http_response_code(403);
    exit('Not logged in');
}

if (!isset($conn) || $conn->connect_error) {
    http_response_code(500);
    exit('DB error');
}

$userId = (int)$_SESSION['user_id'];
$fullName = $_SESSION['fullname'];
$orderId = (int)($_POST['order_id'] ?? $_GET['id'] ?? 0);

if ($orderId <= 0) {
    http_response_code(400);
    exit('Invalid order ID');
}

$conn->begin_transaction();
try {
    // Verify ownership and status in orders table
    $q = $conn->prepare("SELECT id, status FROM orders WHERE id=? AND (user_id=? OR fullname=?) LIMIT 1");
    $q->bind_param('iis', $orderId, $userId, $fullName);
    $q->execute();
    $ord = $q->get_result()->fetch_assoc();
    $q->close();

    if (!$ord || $ord['status'] !== 'Pending') {
        throw new Exception('Only your pending orders can be cancelled');
    }

    // Restore stock
    $it = $conn->prepare("SELECT product_id, quantity FROM order_items WHERE order_id=?");
    $it->bind_param('i', $orderId);
    $it->execute();
    $res = $it->get_result();
    while ($row = $res->fetch_assoc()) {
        $st = $conn->prepare("UPDATE products SET stock = stock + ? WHERE id=?");
        $st->bind_param('ii', $row['quantity'], $row['product_id']);
        $st->execute();
        $st->close();
    }
    $it->close();

    // Mark cancelled in orders table
    $upd = $conn->prepare("UPDATE orders SET status='Cancelled' WHERE id=?");
    $upd->bind_param('i', $orderId);
    $upd->execute();
    $upd->close();

    // Link precisely with cart table and mark cancelled for Admin Dashboard
    $upd_cart = $conn->prepare("UPDATE cart SET status='Cancelled' WHERE order_id=? OR (user_id=? AND status='Pending' AND total=(SELECT total FROM orders WHERE id=?))");
    $upd_cart->bind_param('iii', $orderId, $userId, $orderId);
    $upd_cart->execute();
    $upd_cart->close();

    $conn->commit();
    
    // Log audit using standard logging
    logAdminAction($conn, $userId, $fullName, 'order_cancel_by_user', "Customer cancelled order #$orderId", 'orders', $orderId);
    
    header('Location: my_orders.php?msg=cancelled');
} catch (Exception $e) {
    $conn->rollback();
    error_log("cancel_order.php error: " . $e->getMessage());
    header('Location: my_orders.php?err=cancel_failed');
}
$conn->close();
?>