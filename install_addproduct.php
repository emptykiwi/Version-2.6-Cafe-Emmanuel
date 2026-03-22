<?php
// install_addproduct.php
// One-time installer to import DATABASE/addproduct.sql into the `addproduct` database.
// After successful import, DELETE this file.

// Show errors during setup
error_reporting(E_ALL);
ini_set('display_errors', '1');

// Use existing DB credentials from config.php
require_once __DIR__ . '/config.php'; // provides $host, $user, $pass (and opens $conn for login_system)

// Connect to MySQL server (no DB selected)
$server = mysqli_connect($host, $user, $pass);
if (!$server) {
    die('MySQL connection failed: ' . mysqli_connect_error());
}

$db = 'addproduct';

// Ensure database exists
if (!mysqli_query($server, "CREATE DATABASE IF NOT EXISTS `$db` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci")) {
    die('Failed to create database `addproduct`: ' . mysqli_error($server));
}

// Select database
if (!mysqli_select_db($server, $db)) {
    die('Failed to select database `addproduct`: ' . mysqli_error($server));
}

// If already installed, bail out to avoid duplicate imports
$installed = mysqli_query($server, "SHOW TABLES LIKE 'products'");
if ($installed && mysqli_num_rows($installed) > 0) {
    echo '<p>`addproduct` already seems installed (table `products` exists). If you want to re-import, drop the database first via phpMyAdmin.</p>';
    exit;
}

$sqlFile = __DIR__ . '/DATABASE/addproduct.sql';
if (!file_exists($sqlFile)) {
    die('SQL file not found: ' . htmlspecialchars($sqlFile));
}

$sql = file_get_contents($sqlFile);
if ($sql === false) {
    die('Failed to read SQL file.');
}

// Run the dump via multi_query and drain all results
if (!mysqli_multi_query($server, $sql)) {
    die('Import error: ' . mysqli_error($server));
}

// Drain remaining results to complete multi_query cycle
while (mysqli_more_results($server)) {
    mysqli_next_result($server);
    if ($res = mysqli_store_result($server)) {
        mysqli_free_result($res);
    }
}

echo '<h3>Success</h3><p>The `addproduct` database has been created and populated from DATABASE/addproduct.sql.</p><p>You can now refresh your site. Please delete install_addproduct.php for security.</p>';
