<?php
// Upload this file to your server in the CafeEmmanuel folder
// Access it via: yourdomain.com/CafeEmmanuel/debug_server.php

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

echo "<h1>Cafe Emmanuel Server Debugger</h1>";

// 1. Check PHP Version
echo "<h3>1. PHP Version</h3>";
echo "Current PHP Version: " . phpversion() . "<br>";

// 2. Test Database Connection
echo "<h3>2. Database Connection Test</h3>";

// TRY TO INCLUDE YOUR EXISTING CONNECTION FILE FIRST
if (file_exists('db_connect.php')) {
    echo "Found db_connect.php... trying to include it.<br>";
    include 'db_connect.php';
    
    // Check if $conn variable exists from the include
    if (isset($conn)) {
        if ($conn->connect_error) {
            echo "<span style='color:red'>Connection Failed: " . $conn->connect_error . "</span><br>";
            echo "<strong>Hint:</strong> Check your username and password in db_connect.php.";
        } else {
            echo "<span style='color:green'><strong>Database Connected Successfully!</strong></span><br>";
            
            // 3. List Tables to check Case Sensitivity
            echo "<h3>3. Table Name Check (Case Sensitivity)</h3>";
            echo "<p>Linux servers are case-sensitive. 'Products' is not the same as 'products'. Compare these names with your PHP code.</p>";
            
            $result = $conn->query("SHOW TABLES");
            if ($result) {
                echo "<ul>";
                while ($row = $result->fetch_array()) {
                    echo "<li>" . $row[0] . "</li>";
                }
                echo "</ul>";
            } else {
                echo "Could not list tables.";
            }
        }
    } else {
        echo "<span style='color:red'>Error: \$conn variable not found after including db_connect.php. Check variable names.</span>";
    }
} else {
    echo "<span style='color:red'>Error: db_connect.php not found in this directory.</span>";
}

// 4. Check Session Support
echo "<h3>4. Session Test</h3>";
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}
$_SESSION['test_key'] = "Session Working";
if (isset($_SESSION['test_key'])) {
    echo "<span style='color:green'>Sessions are working.</span>";
} else {
    echo "<span style='color:red'>Sessions are NOT saving. Check folder permissions.</span>";
}
?>