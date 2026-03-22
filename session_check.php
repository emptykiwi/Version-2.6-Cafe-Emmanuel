<?php
session_start();

// Prevent browser caching
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Cache-Control: post-check=0, pre-check=0", false);
header("Pragma: no-cache");
header("Expires: 0");

// 1. If there is no login session at all, redirect to the homepage
if (!isset($_SESSION['user_id'])) {
    header("Location: index.php?action=login");
    exit();
}

// 2. Safely fetch the role, converting it to lowercase and removing accidental spaces
$user_role = isset($_SESSION['role']) ? strtolower(trim($_SESSION['role'])) : '';

// 3. Check if user is an admin OR a super_admin
if (!in_array($user_role, ['admin', 'super_admin'])) {
    // If they are just a 'user', send them back to the frontend index page
    header("Location: index.php");
    exit();
}
?>