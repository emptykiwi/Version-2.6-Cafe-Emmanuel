<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

echo "<h1>Cafe Emmanuel Diagnostic Tool</h1>";

// 1. Test Database Connection
echo "<h3>Step 1: Testing Database Connection...</h3>";
if (file_exists('db_connect.php')) {
    include 'db_connect.php';
    if (isset($conn) && !$conn->connect_error) {
        echo "<p style='color:green'>✅ Database Connected Successfully.</p>";
    } else {
        die("<p style='color:red'>❌ Database Connection Failed. Check db_connect.php</p>");
    }
} else {
    die("<p style='color:red'>❌ db_connect.php is missing.</p>");
}

// 2. Test Table Case Sensitivity
echo "<h3>Step 2: Testing Table Names (Case Sensitivity)</h3>";
echo "<p>On live Linux servers, <code>Products</code> is DIFFERENT from <code>products</code>.</p>";

$tables_to_test = ['products', 'Products', 'users', 'Users', 'orders', 'Orders', 'sales', 'Sales'];

echo "<table border='1' cellpadding='5'>";
echo "<tr><th>Query</th><th>Result</th><th>Status</th></tr>";

foreach ($tables_to_test as $table) {
    $sql = "SELECT * FROM $table LIMIT 1";
    echo "<tr><td>SELECT * FROM <strong>$table</strong></td>";
    
    try {
        $result = $conn->query($sql);
        if ($result) {
            echo "<td>Found</td><td style='color:green'>✅ Working</td>";
        } else {
            echo "<td>Not Found</td><td style='color:red'>❌ Error: Table doesn't exist</td>";
        }
    } catch (Exception $e) {
        echo "<td>Crash</td><td style='color:red'>❌ Critical Error</td>";
    }
    echo "</tr>";
}
echo "</table>";

echo "<h3>Step 3: Recommendations</h3>";
echo "<ul>";
echo "<li>If 'products' works but 'Products' fails (Red X), you MUST edit <strong>Dashboard.php</strong>.</li>";
echo "<li>Search for <code>Products</code> and change it to <code>products</code> (lowercase).</li>";
echo "<li>Do the same for <code>Users</code>, <code>Orders</code>, etc.</li>";
echo "</ul>";
?>