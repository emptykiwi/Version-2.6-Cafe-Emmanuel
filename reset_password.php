<?php
session_start();
require_once 'config.php';
require_once 'audit.php';

// 1. INCLUDE MAILER
require_once __DIR__ . '/mailer.php';

if (!isset($_SESSION['reset_user_id'])) {
    header("Location: forgot_password.php");
    exit;
}

$error_message = '';
$success_message = '';
$userId = $_SESSION['reset_user_id'];
$resetMethod = $_SESSION['reset_method'] ?? 'email';

// Handle Password Reset Submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['reset_password'])) {
    $reset_code = trim($_POST['reset_code'] ?? '');
    $new_password = $_POST['new_password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';
    
    if (empty($reset_code)) {
        $error_message = "Please enter the reset code.";
    } elseif (empty($new_password) || empty($confirm_password)) {
        $error_message = "Please enter and confirm your new password.";
    } elseif ($new_password !== $confirm_password) {
        $error_message = "Passwords do not match.";
    } elseif (!preg_match("/^(?=.*[A-Z]).{8,}$/", $new_password)) {
        $error_message = "Password must be at least 8 characters with one capital letter.";
    } else {
        // Verify reset code
        $stmt = $conn->prepare("SELECT id, expires_at, attempts, used_at FROM password_resets WHERE user_id = ? AND reset_code = ? AND reset_method = ? ORDER BY created_at DESC LIMIT 1");
        $stmt->bind_param("iss", $userId, $reset_code, $resetMethod);
        $stmt->execute();
        $result = $stmt->get_result();
        $resetRecord = $result->fetch_assoc();
        $stmt->close();
        
        if (!$resetRecord) {
            $error_message = "Invalid reset code.";
        } elseif ($resetRecord['used_at']) {
            $error_message = "This reset code has already been used.";
        } elseif (time() > strtotime($resetRecord['expires_at'])) {
            $error_message = "Reset code has expired. Please request a new one.";
        } elseif ((int)$resetRecord['attempts'] >= 5) {
            $error_message = "Too many attempts. Please request a new reset code.";
        } else {
            // Update password
            $hashedPassword = password_hash($new_password, PASSWORD_DEFAULT);
            $updateStmt = $conn->prepare("UPDATE users SET password = ? WHERE id = ?");
            $updateStmt->bind_param("si", $hashedPassword, $userId);
            
            if ($updateStmt->execute()) {
                // Mark code as used
                $markStmt = $conn->prepare("UPDATE password_resets SET used_at = NOW() WHERE id = ?");
                $markStmt->bind_param("i", $resetRecord['id']);
                $markStmt->execute();
                $markStmt->close();
                
                audit($userId, 'password_reset_completed', 'users', $userId, ['method' => $resetMethod]);
                unset($_SESSION['reset_user_id'], $_SESSION['reset_method']);
                
                $success_message = "Password reset successfully! Redirecting to login...";
                header("refresh:3;url=index.php");
            } else {
                $error_message = "Failed to reset password. Please try again.";
            }
            $updateStmt->close();
        }
        
        if ($resetRecord && !$success_message) {
            $incStmt = $conn->prepare("UPDATE password_resets SET attempts = attempts + 1 WHERE id = ?");
            $incStmt->bind_param("i", $resetRecord['id']);
            $incStmt->execute();
            $incStmt->close();
        }
    }
}

// Handle Resend Code
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['resend_code'])) {
    $userStmt = $conn->prepare("SELECT email, contact, fullname FROM users WHERE id = ?");
    $userStmt->bind_param("i", $userId);
    $userStmt->execute();
    $userResult = $userStmt->get_result();
    $user = $userResult->fetch_assoc();
    $userStmt->close();
    
    if ($user) {
        $reset_code = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);
        $expires = date('Y-m-d H:i:s', time() + 600);
        
        $insertStmt = $conn->prepare("INSERT INTO password_resets (user_id, reset_code, reset_method, expires_at) VALUES (?, ?, ?, ?)");
        $insertStmt->bind_param("isss", $userId, $reset_code, $resetMethod, $expires);
        $insertStmt->execute();
        $insertStmt->close();
        
        if ($resetMethod === 'email') {
            // 2. USE MAILER TO RESEND CODE
            $subject = 'New Password Reset Code - Cafe Emmanuel';
            $body = "
                <h3>Password Reset Request</h3>
                <p>Hello " . htmlspecialchars($user['fullname']) . ",</p>
                <p>Your new password reset code is: <b>$reset_code</b></p>
                <p>This code will expire in 10 minutes.</p>
            ";
            
            send_email($user['email'], $subject, $body);
            $success_message = "New reset code sent to your email!";
        } else {
            $success_message = "New reset code sent to your phone!";
        }
        
        audit($userId, 'password_reset_code_resent', 'users', $userId, ['method' => $resetMethod]);
    }
}
?>
<!-- Paste the rest of your HTML code (form, styling) here exactly as it was -->
<!DOCTYPE html>
<html lang="en">
<!-- ... -->