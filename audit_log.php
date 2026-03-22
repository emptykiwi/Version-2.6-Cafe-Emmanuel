<?php
// Audit Log Helper Functions

// Create audit_logs table if it doesn't exist
function createAuditLogsTable($conn) {
    $sql = "CREATE TABLE IF NOT EXISTS audit_logs (
        id INT AUTO_INCREMENT PRIMARY KEY,
        admin_id INT NOT NULL,
        admin_name VARCHAR(255) NOT NULL,
        action VARCHAR(100) NOT NULL,
        description TEXT NOT NULL,
        table_name VARCHAR(50),
        record_id INT,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_admin_id (admin_id),
        INDEX idx_action (action),
        INDEX idx_created (created_at)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";
    
    return $conn->query($sql);
}

// Log an admin action
function logAdminAction($conn, $admin_id, $admin_name, $action, $description, $table_name = null, $record_id = null) {
    // Check if connection is alive before preparing
    if (!$conn || $conn->connect_errno) {
        error_log("Audit Log Error: Database connection is invalid or closed.");
        return false;
    }

    $stmt = $conn->prepare("INSERT INTO audit_logs (admin_id, admin_name, action, description, table_name, record_id) VALUES (?, ?, ?, ?, ?, ?)");
    if (!$stmt) {
        error_log("Audit log prepare failed: " . $conn->error);
        return false;
    }
    $stmt->bind_param("issssi", $admin_id, $admin_name, $action, $description, $table_name, $record_id);
    $result = $stmt->execute();
    if (!$result) {
        error_log("Audit log execute failed: " . $stmt->error);
    }
    $stmt->close();
    return $result;
}

// Get audit logs with optional filters
function getAuditLogs($conn, $limit = 100, $admin_id = null, $action = null) {
    $sql = "SELECT * FROM audit_logs WHERE 1=1";
    $params = [];
    $types = "";
    
    if ($admin_id !== null) {
        $sql .= " AND admin_id = ?";
        $params[] = $admin_id;
        $types .= "i";
    }
    
    if ($action !== null) {
        $sql .= " AND action = ?";
        $params[] = $action;
        $types .= "s";
    }
    
    $sql .= " ORDER BY created_at DESC LIMIT ?";
    $params[] = $limit;
    $types .= "i";
    
    $stmt = $conn->prepare($sql);
    if (!empty($params)) {
        $stmt->bind_param($types, ...$params);
    }
    $stmt->execute();
    $result = $stmt->get_result();
    $logs = [];
    while ($row = $result->fetch_assoc()) {
        $logs[] = $row;
    }
    $stmt->close();
    return $logs;
}

// Initialize audit logs table
// It's better to let config.php handle table creation, but we keep this for safety
if (file_exists('db_connect.php')) {
    include_once 'db_connect.php';
    if (isset($conn) && !$conn->connect_error) {
        createAuditLogsTable($conn);
    }
}
?>