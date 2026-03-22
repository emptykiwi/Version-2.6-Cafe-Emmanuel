<?php
// Create notifications table in login_system database
$conn = new mysqli('localhost', 'root', '', 'login_system');

if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

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

if ($conn->query($sql) === TRUE) {
    echo "✅ Notifications table created successfully (or already exists).<br>";
    
    // Check if table exists now
    $result = $conn->query("SHOW TABLES LIKE 'notifications'");
    if ($result->num_rows > 0) {
        echo "✅ Verified: notifications table exists in login_system database.<br>";
        
        // Show table structure
        $result = $conn->query("DESCRIBE notifications");
        echo "<br><strong>Table structure:</strong><br>";
        while ($row = $result->fetch_assoc()) {
            echo "- " . $row['Field'] . " (" . $row['Type'] . ")<br>";
        }
    }
} else {
    echo "❌ Error creating table: " . $conn->error;
}

$conn->close();
?>
