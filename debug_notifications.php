<?php
// Debug script to check notifications
include 'db_connect.php';

if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

echo "<h2>Notifications Table Check</h2>";

// Check if table exists
$result = $conn->query("SHOW TABLES LIKE 'notifications'");
if ($result->num_rows > 0) {
    echo "✅ notifications table exists<br><br>";
    
    // Show all notifications
    echo "<h3>All Notifications:</h3>";
    $result = $conn->query("SELECT * FROM notifications ORDER BY created_at DESC LIMIT 10");
    
    if ($result->num_rows > 0) {
        echo "<table border='1' cellpadding='10'>";
        echo "<tr><th>ID</th><th>User ID</th><th>Order ID</th><th>Type</th><th>Title</th><th>Message</th><th>Read</th><th>Created</th></tr>";
        while ($row = $result->fetch_assoc()) {
            echo "<tr>";
            echo "<td>" . $row['id'] . "</td>";
            echo "<td>" . $row['user_id'] . "</td>";
            echo "<td>" . $row['order_id'] . "</td>";
            echo "<td>" . $row['type'] . "</td>";
            echo "<td>" . $row['title'] . "</td>";
            echo "<td>" . $row['message'] . "</td>";
            echo "<td>" . ($row['is_read'] ? 'Yes' : 'No') . "</td>";
            echo "<td>" . $row['created_at'] . "</td>";
            echo "</tr>";
        }
        echo "</table>";
    } else {
        echo "❌ No notifications found in database<br>";
    }
    
    // Check users table
    echo "<br><h3>Users in database:</h3>";
    $result = $conn->query("SELECT id, fullname, email FROM users LIMIT 5");
    echo "<table border='1' cellpadding='10'>";
    echo "<tr><th>ID</th><th>Full Name</th><th>Email</th></tr>";
    while ($row = $result->fetch_assoc()) {
        echo "<tr><td>" . $row['id'] . "</td><td>" . $row['fullname'] . "</td><td>" . $row['email'] . "</td></tr>";
    }
    echo "</table>";
    
} else {
    echo "❌ notifications table does NOT exist<br>";
}

// Check cart table for user_id
echo "<br><h3>Recent Orders with user_id:</h3>";
$order_conn = new mysqli('localhost', 'root', '', 'addproduct');
$result = $order_conn->query("SELECT id, fullname, user_id, status FROM cart ORDER BY id DESC LIMIT 5");
echo "<table border='1' cellpadding='10'>";
echo "<tr><th>Order ID</th><th>Customer Name</th><th>User ID</th><th>Status</th></tr>";
while ($row = $result->fetch_assoc()) {
    echo "<tr>";
    echo "<td>#" . $row['id'] . "</td>";
    echo "<td>" . $row['fullname'] . "</td>";
    echo "<td>" . ($row['user_id'] ?? 'NULL') . "</td>";
    echo "<td>" . $row['status'] . "</td>";
    echo "</tr>";
}
echo "</table>";

$conn->close();
$order_conn->close();
?>
