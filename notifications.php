<?php
// Notification helper functions
require_once 'config.php';

// Create notifications table if it doesn't exist
function createNotificationsTable($conn) {
    $sql = "CREATE TABLE IF NOT EXISTS notifications (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT NOT NULL,
        order_id INT,
        type VARCHAR(50) NOT NULL,
        title VARCHAR(255) NOT NULL,
        message TEXT NOT NULL,
        is_read BOOLEAN DEFAULT FALSE,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_user_id (user_id),
        INDEX idx_user_read (user_id, is_read),
        INDEX idx_created (created_at)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";
    
    return $conn->query($sql);
}

// Initialize table
// Note: Table should be created in login_system database, not via config.php connection
// createNotificationsTable($conn);

// Create a notification
function createNotification($conn, $user_id, $order_id, $type, $title, $message) {
    $stmt = $conn->prepare("INSERT INTO notifications (user_id, order_id, type, title, message) VALUES (?, ?, ?, ?, ?)");
    if (!$stmt) {
        error_log("Notification prepare failed: " . $conn->error);
        return false;
    }
    $stmt->bind_param("iisss", $user_id, $order_id, $type, $title, $message);
    $result = $stmt->execute();
    if (!$result) {
        error_log("Notification execute failed: " . $stmt->error);
    }
    $stmt->close();
    return $result;
}

// Get unread notification count for a user
function getUnreadCount($conn, $user_id) {
    $stmt = $conn->prepare("SELECT COUNT(*) as count FROM notifications WHERE user_id = ? AND is_read = FALSE");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result->fetch_assoc();
    $stmt->close();
    return $row['count'] ?? 0;
}

// Get all notifications for a user (recent first)
function getNotifications($conn, $user_id, $limit = 20) {
    $stmt = $conn->prepare("SELECT * FROM notifications WHERE user_id = ? ORDER BY created_at DESC LIMIT ?");
    $stmt->bind_param("ii", $user_id, $limit);
    $stmt->execute();
    $result = $stmt->get_result();
    $notifications = [];
    while ($row = $result->fetch_assoc()) {
        $notifications[] = $row;
    }
    $stmt->close();
    return $notifications;
}

// Mark notification as read
function markAsRead($conn, $notification_id, $user_id) {
    $stmt = $conn->prepare("UPDATE notifications SET is_read = TRUE WHERE id = ? AND user_id = ?");
    $stmt->bind_param("ii", $notification_id, $user_id);
    $result = $stmt->execute();
    $stmt->close();
    return $result;
}

// Mark all notifications as read for a user
function markAllAsRead($conn, $user_id) {
    $stmt = $conn->prepare("UPDATE notifications SET is_read = TRUE WHERE user_id = ? AND is_read = FALSE");
    $stmt->bind_param("i", $user_id);
    $result = $stmt->execute();
    $stmt->close();
    return $result;
}

// Delete old read notifications (older than 30 days)
function cleanupOldNotifications($conn) {
    $sql = "DELETE FROM notifications WHERE is_read = TRUE AND created_at < DATE_SUB(NOW(), INTERVAL 30 DAY)";
    return $conn->query($sql);
}
?>
