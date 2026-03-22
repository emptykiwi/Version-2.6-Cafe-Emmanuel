<?php
session_start();

// Enable Error Reporting for Debugging
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require_once 'audit_log.php';
require_once 'mailer.php'; // Use the PHPMailer wrapper
require_once 'db_connect.php'; // Use standard connection

// Security check
if (!isset($_SESSION['user_id']) || !in_array($_SESSION['role'], ['admin', 'super_admin'])) {
    header("Location: admin_inquiries.php");
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $inquiry_id = intval($_POST['inquiry_id']);
    $response_message = trim($_POST['response_message']);
    $internal_notes = trim($_POST['internal_notes'] ?? '');
    $new_status = $_POST['status'] ?? 'responded';
    
    if (!$inquiry_id || empty($response_message)) {
        header("Location: admin_inquiries.php?error=missing_data");
        exit();
    }
    
    // Get inquiry details to send email
    $stmt = $conn->prepare("SELECT * FROM inquiries WHERE id = ?");
    if (!$stmt) {
        die("Database Error (Select): " . $conn->error);
    }
    $stmt->bind_param("i", $inquiry_id);
    $stmt->execute();
    $inquiry = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    
    if (!$inquiry) {
        header("Location: admin_inquiries.php?error=not_found");
        exit();
    }
    
    // Update database with response
    $update_sql = "UPDATE inquiries SET status = ?, admin_response = ?, responded_by = ?, responded_at = NOW(), internal_notes = ? WHERE id = ?";
    
    try {
        $stmt = $conn->prepare($update_sql);

        // Check if prepare failed (often due to missing columns if exceptions aren't thrown)
        if (!$stmt) {
            throw new Exception($conn->error);
        }

        $admin_id = $_SESSION['user_id'];
        $stmt->bind_param("ssisi", $new_status, $response_message, $admin_id, $internal_notes, $inquiry_id);
        
        if ($stmt->execute()) {
            $stmt->close();
            
            // --- SEND EMAIL NOTIFICATION VIA PHPMAILER ---
            $to = $inquiry['email'];
            $customerName = $inquiry['first_name'];
            $subject = "Response to your inquiry - Cafe Emmanuel";
            
            // Clean up message for email body
            $clean_response = nl2br(htmlspecialchars($response_message));
            $clean_original = nl2br(htmlspecialchars($inquiry['message']));
            
            $body = "
            <div style='font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto; border: 1px solid #eee; border-radius: 8px; overflow: hidden;'>
                <div style='background-color: #B95A4B; color: white; padding: 20px; text-align: center;'>
                    <h2 style='margin:0;'>Cafe Emmanuel</h2>
                </div>
                <div style='padding: 30px; background-color: #ffffff;'>
                    <p>Dear <strong>$customerName</strong>,</p>
                    <p>Thank you for contacting us. Here is our response to your inquiry:</p>
                    
                    <div style='background-color: #f8f9fa; padding: 15px; border-left: 4px solid #B95A4B; margin: 20px 0;'>
                        $clean_response
                    </div>
                    
                    <p style='color: #666; font-size: 14px;'>
                        <em>Regarding your message:<br>
                        \"$clean_original\"</em>
                    </p>
                    
                    <p>If you have any further questions, please reply to this email.</p>
                    <p>Best regards,<br>The Cafe Emmanuel Team</p>
                </div>
                <div style='background-color: #f1f1f1; padding: 15px; text-align: center; font-size: 12px; color: #888;'>
                    &copy; " . date('Y') . " Cafe Emmanuel. All rights reserved.
                </div>
            </div>";
            
            // Use the send_email function from mailer.php
            $email_sent = false;
            if (function_exists('send_email')) {
                $email_sent = send_email($to, $subject, $body);
            }
            
            // Log the action
            if (function_exists('logAdminAction')) {
                logAdminAction(
                    $conn,
                    $_SESSION['user_id'],
                    $_SESSION['fullname'],
                    'inquiry_response',
                    "Replied to inquiry #$inquiry_id from {$inquiry['first_name']} {$inquiry['last_name']}. Status: $new_status",
                    'inquiries',
                    $inquiry_id
                );
            }
            
            if ($email_sent) {
                header("Location: admin_inquiries.php?success=response_sent");
            } else {
                // Database updated but email failed
                header("Location: admin_inquiries.php?success=response_saved&warning=email_failed");
            }
        } else {
            throw new Exception($stmt->error);
        }
        
    } catch (Exception $e) {
        // Catch the "Unknown column" error specifically
        if (strpos($e->getMessage(), "Unknown column") !== false) {
            die("<div style='font-family:sans-serif; max-width:600px; margin:50px auto; padding:20px; border:2px solid #B95A4B; border-radius:10px; background:#fff;'>
                    <h2 style='color:#B95A4B; margin-top:0;'>Database Update Required</h2>
                    <p>The system attempted to save your response, but the database table 'inquiries' is missing the required columns (e.g., <code>admin_response</code>).</p>
                    <p>Please run the database update script to fix this automatically:</p>
                    <p style='text-align:center; margin:30px 0;'>
                        <a href='update_inquiries_table.php' target='_blank' style='background:#B95A4B; color:white; padding:15px 30px; text-decoration:none; border-radius:5px; font-weight:bold;'>Click Here to Fix Database</a>
                    </p>
                    <p>After running the fix, go back and try replying again.</p>
                    <hr style='border:0; border-top:1px solid #eee; margin:20px 0;'>
                    <small style='color:#666;'>Technical Error: " . htmlspecialchars($e->getMessage()) . "</small>
                 </div>");
        } else {
            // Other errors
            header("Location: admin_inquiries.php?error=update_failed&msg=" . urlencode($e->getMessage()));
        }
        exit();
    }
    
    $conn->close();
    exit();
}

// If accessed directly
header("Location: admin_inquiries.php");
exit();
?>