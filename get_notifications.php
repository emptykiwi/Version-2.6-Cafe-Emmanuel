<?php
session_start();
header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Not logged in']);
    exit;
}

require_once 'notifications.php';

$user_id = $_SESSION['user_id'];
$action = $_GET['action'] ?? 'list';

// Get unread count
if ($action === 'count') {
    $count = getUnreadCount($conn, $user_id);
    echo json_encode(['success' => true, 'count' => $count]);
    exit;
}

// Get list of notifications
if ($action === 'list') {
    $limit = isset($_GET['limit']) ? intval($_GET['limit']) : 20;
    $notifications = getNotifications($conn, $user_id, $limit);
    echo json_encode(['success' => true, 'notifications' => $notifications]);
    exit;
}

// Mark as read
if ($action === 'mark_read' && isset($_GET['id'])) {
    $notification_id = intval($_GET['id']);
    $result = markAsRead($conn, $notification_id, $user_id);
    echo json_encode(['success' => $result]);
    exit;
}

// Mark all as read
if ($action === 'mark_all_read') {
    $result = markAllAsRead($conn, $user_id);
    echo json_encode(['success' => $result]);
    exit;
}

echo json_encode(['success' => false, 'message' => 'Invalid action']);
?>
