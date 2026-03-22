<?php
// Update inquiries table for response system
include 'db_connect.php';

if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

echo "<h2>Updating Inquiries Table</h2>";

// Update status column to use ENUM
$sql = "ALTER TABLE inquiries MODIFY status ENUM('new', 'in_progress', 'responded', 'closed') DEFAULT 'new'";
if ($conn->query($sql) === TRUE) {
    echo "✅ Status column updated to ENUM<br>";
} else {
    echo "⚠️ Status column: " . $conn->error . "<br>";
}

// Add admin_response column
$sql = "ALTER TABLE inquiries ADD COLUMN IF NOT EXISTS admin_response TEXT NULL";
if ($conn->query($sql) === TRUE) {
    echo "✅ admin_response column added<br>";
} else {
    // Try without IF NOT EXISTS for older MySQL versions
    $result = $conn->query("SHOW COLUMNS FROM inquiries LIKE 'admin_response'");
    if ($result->num_rows == 0) {
        $sql = "ALTER TABLE inquiries ADD COLUMN admin_response TEXT NULL";
        $conn->query($sql);
        echo "✅ admin_response column added<br>";
    } else {
        echo "✅ admin_response column already exists<br>";
    }
}

// Add responded_by column
$result = $conn->query("SHOW COLUMNS FROM inquiries LIKE 'responded_by'");
if ($result->num_rows == 0) {
    $sql = "ALTER TABLE inquiries ADD COLUMN responded_by INT NULL";
    $conn->query($sql);
    echo "✅ responded_by column added<br>";
} else {
    echo "✅ responded_by column already exists<br>";
}

// Add responded_at column
$result = $conn->query("SHOW COLUMNS FROM inquiries LIKE 'responded_at'");
if ($result->num_rows == 0) {
    $sql = "ALTER TABLE inquiries ADD COLUMN responded_at TIMESTAMP NULL";
    $conn->query($sql);
    echo "✅ responded_at column added<br>";
} else {
    echo "✅ responded_at column already exists<br>";
}

// Add internal_notes column
$result = $conn->query("SHOW COLUMNS FROM inquiries LIKE 'internal_notes'");
if ($result->num_rows == 0) {
    $sql = "ALTER TABLE inquiries ADD COLUMN internal_notes TEXT NULL";
    $conn->query($sql);
    echo "✅ internal_notes column added<br>";
} else {
    echo "✅ internal_notes column already exists<br>";
}

echo "<br><h3>Table Structure:</h3>";
$result = $conn->query("DESCRIBE inquiries");
echo "<table border='1' cellpadding='10'>";
echo "<tr><th>Field</th><th>Type</th><th>Null</th><th>Default</th></tr>";
while ($row = $result->fetch_assoc()) {
    echo "<tr>";
    echo "<td>" . $row['Field'] . "</td>";
    echo "<td>" . $row['Type'] . "</td>";
    echo "<td>" . $row['Null'] . "</td>";
    echo "<td>" . ($row['Default'] ?? 'NULL') . "</td>";
    echo "</tr>";
}
echo "</table>";

$conn->close();
echo "<br><br><strong>✅ Database update complete! You can now close this page.</strong>";
?>
