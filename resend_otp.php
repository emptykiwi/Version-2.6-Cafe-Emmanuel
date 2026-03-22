<?php
session_start();
require_once 'config.php';
require_once 'audit.php';
require_once 'mailer.php'; // Loads your fixed mailer

if (!isset($_SESSION['otp_user_id'])) {
    header('Location: index.php?action=login');
    exit;
}

$userId = (int)$_SESSION['otp_user_id'];
$email = $_SESSION['otp_email'];

// 1. Generate new 6-digit code
$new_code = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);
$expires_at = date('Y-m-d H:i:s', time() + 600); // 10 minutes

// 2. Save to Database
$stmt = $conn->prepare("INSERT INTO otp_codes (user_id, code, expires_at) VALUES (?, ?, ?)");
$stmt->bind_param("iss", $userId, $new_code, $expires_at);

if ($stmt->execute()) {
    // 3. Send Email
    $subject = "New Verification Code - Cafe Emmanuel";
    $body = "
        <div style='font-family: Arial, sans-serif; padding: 20px; color: #333;'>
            <h2 style='color: #B95A4B;'>Cafe Emmanuel Verification</h2>
            <p>You requested a new code. Use the following OTP to complete your login:</p>
            <h1 style='font-size: 32px; letter-spacing: 5px; color: #333;'>$new_code</h1>
            <p>This code expires in 10 minutes.</p>
        </div>
    ";

    if (send_email($email, $subject, $body)) {
        $_SESSION['otp_resent'] = true; // Flag to show success message
        audit($userId, 'otp_resent_success', 'otp_codes', $stmt->insert_id, ['email' => $email]);
    } else {
        $_SESSION['otp_error'] = "Failed to send email. Please try again later.";
        audit($userId, 'otp_resent_failed_email', 'otp_codes', $stmt->insert_id, ['email' => $email]);
    }
} else {
    $_SESSION['otp_error'] = "System error generating code.";
}

$stmt->close();

// 4. Redirect back to OTP modal
$_SESSION['show_otp_modal'] = true;
header("Location: index.php");
exit;
?>