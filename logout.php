<?php
session_start();

// Destroy all session data
session_unset();
session_destroy();

// Optional: Clear session cookie
setcookie(session_name(), '', time() - 3600, '/');

// Redirect back to the homepage
header("Location: index.php");
exit();
?>