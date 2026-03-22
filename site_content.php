<?php
// Run this file once to set up the database table for content
include 'db_connect.php';

$sql = "CREATE TABLE IF NOT EXISTS site_content (
    id INT(11) UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    file_path VARCHAR(255) NOT NULL,
    media_type ENUM('image', 'video') NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
)";

if ($conn->query($sql) === TRUE) {
    echo "Table 'site_content' created successfully.";
} else {
    echo "Error creating table: " . $conn->error;
}

$conn->close();
?>