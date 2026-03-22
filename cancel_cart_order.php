<?php
// cancel_cart_order.php
session_start();
require_once 'config.php'; // Use config.php for DB connection
require_once __DIR__ . '/audit.php';

if (!isset($_SESSION['user_id']) || !isset($_SESSION['fullname'])) {
    header('Location: index.php?action=login');
    exit;
}

// Use $conn from config.php
if (!isset($conn) || $conn->connect_error) { 
    header('Location: my_orders.php?err=cancel_failed'); 
    exit; 
}

// Alias for existing logic
$db = $conn;

$userId = (int)$_SESSION['user_id'];
$fullname = $_SESSION['fullname'];
$orderId = (int)($_POST['order_id'] ?? 0);
$reason = trim($_POST['reason'] ?? '');
$confirm = trim($_POST['confirm_text'] ?? '');

if ($orderId <= 0 || $reason === '' || strcasecmp($confirm, 'Cancel order') !== 0) {
    header('Location: my_orders.php?err=cancel_failed');
    exit;
}

$db->begin_transaction();
try {
    // Fetch order and verify ownership and status
    $q = $db->prepare("SELECT id, status, cart FROM cart WHERE id=? AND (user_id=? OR fullname=?) LIMIT 1");
    $q->bind_param('iis', $orderId, $userId, $fullname);
    $q->execute();
    $row = $q->get_result()->fetch_assoc();
    
    // Case-insensitive check for 'pending'
    if (!$row || strtolower($row['status']) !== 'pending') { 
        throw new Exception('Not pending'); 
    }

    // Restock items
    $items = json_decode($row['cart'] ?? '[]', true) ?: [];
    foreach ($items as $it) {
        $pid = isset($it['id']) ? (int)$it['id'] : 0;
        $qty = isset($it['quantity']) ? (int)$it['quantity'] : 0;
        if ($pid > 0 && $qty > 0) {
            $st = $db->prepare("UPDATE products SET stock = stock + ? WHERE id=?");
            $st->bind_param('ii', $qty, $pid);
            $st->execute();
        }
    }

    // Mark cancelled with reason
    $upd = $db->prepare("UPDATE cart SET status='Cancelled', cancel_reason=?, cancelled_at=NOW() WHERE id=?");
    $upd->bind_param('si', $reason, $orderId);
    $upd->execute();

    $db->commit();
    
    if (function_exists('audit')) {
        audit($userId, 'order_cancel', 'cart', $orderId, ['reason'=>$reason]);
    }
    
    header('Location: my_orders.php?msg=cancelled');
} catch (Exception $e) {
    $db->rollback();
    if (function_exists('audit')) {
        audit($userId, 'order_cancel_failed', 'cart', $orderId, ['error'=>$e->getMessage()]);
    }
    header('Location: my_orders.php?err=cancel_failed');
}
?>