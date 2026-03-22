<?php
session_start();
require_once 'config.php';
require_once 'audit_log.php';

// Only super_admin can change roles
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'super_admin') {
    header("Location: user_accounts.php");
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $user_id = intval($_POST['user_id'] ?? 0);
    $new_role = $_POST['new_role'] ?? '';
    
    if (!$user_id || !in_array($new_role, ['user', 'admin', 'super_admin'])) {
        header("Location: user_accounts.php");
        exit;
    }
    
    // Prevent super admin from changing their own role
    if ($user_id == $_SESSION['user_id']) {
        header("Location: user_accounts.php");
        exit;
    }
    
    // Get current user info
    $stmt = $conn->prepare("SELECT username, role FROM users WHERE id = ?");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $user = $result->fetch_assoc();
    $stmt->close();
    
    if (!$user) {
        header("Location: user_accounts.php");
        exit;
    }
    
    // Update role
    $update_stmt = $conn->prepare("UPDATE users SET role = ? WHERE id = ?");
    $update_stmt->bind_param("si", $new_role, $user_id);
    
    if ($update_stmt->execute()) {
        // Log audit
        logAdminAction(
            $conn,
            $_SESSION['user_id'],
            $_SESSION['fullname'],
            'user_role_change',
            "Changed role of user '{$user['username']}' from {$user['role']} to {$new_role}",
            'users',
            $user_id
        );
        header("Location: user_accounts.php?success=role_changed");
    } else {
        header("Location: user_accounts.php");
    }
    
    $update_stmt->close();
    $conn->close();
    exit;
}

header("Location: user_accounts.php");
exit;
?>
