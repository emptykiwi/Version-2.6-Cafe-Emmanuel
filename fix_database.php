<?php
require_once 'config.php';

// 1. Add 'is_verified' column if it's missing
$sql = "ALTER TABLE users ADD COLUMN is_verified TINYINT(1) DEFAULT 0 AFTER role";
if ($conn->query($sql) === TRUE) {
    echo "<p style='color:green;'>✅ Successfully added 'is_verified' column.</p>";
    // Mark existing users as verified
    $conn->query("UPDATE users SET is_verified = 1 WHERE is_verified = 0");
} else {
    if (strpos($conn->error, 'Duplicate column') !== false) {
        echo "<p style='color:blue;'>ℹ️ 'is_verified' column already exists.</p>";
    } else {
        echo "<p style='color:red;'>❌ Error adding column: " . $conn->error . "</p>";
    }
}

// 2. Ensure 'contact' column exists (just in case)
$sql2 = "ALTER TABLE users ADD COLUMN contact VARCHAR(20) AFTER email";
if ($conn->query($sql2)) {
    echo "<p style='color:green;'>✅ Successfully added 'contact' column.</p>";
}

echo "<h3>Database Fix Complete. Please delete this file and try registering again.</h3>";
echo "<a href='index.php'>Go back to Home</a>";
$conn->close();
?>