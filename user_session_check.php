<?php
session_start();

// Prevent browser caching
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Cache-Control: post-check=0, pre-check=0", false);
header("Pragma: no-cache");
header("Expires: 0");

// If there is no login session, redirect to the homepage and add an action to trigger the login modal.
if (!isset($_SESSION['user_id'])) {
    header("Location: index.php?action=login");
    exit();
}
?>
