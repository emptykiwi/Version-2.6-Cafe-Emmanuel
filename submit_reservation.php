<?php
session_start();
// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: index.php?res_status=login_required");
    exit;
}

include 'db_connect.php'; // Ensure this matches your database connection file name
require_once __DIR__ . '/audit_log.php'; // Updated to match your audit log file
require_once __DIR__ . '/mailer.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['reserve_table'])) {
    
    // 1. Fetch and sanitize form data
    $user_id    = $_SESSION['user_id'];
    $res_name   = mysqli_real_escape_string($conn, trim($_POST['res_name'] ?? ''));
    $res_email  = mysqli_real_escape_string($conn, trim($_POST['res_email'] ?? ''));
    $res_phone  = mysqli_real_escape_string($conn, trim($_POST['res_phone'] ?? ''));
    $res_date   = trim($_POST['res_date'] ?? '');
    $res_time   = trim($_POST['res_time'] ?? '');
    $res_guests = (int)($_POST['res_guests'] ?? 1);
    $res_notes  = mysqli_real_escape_string($conn, trim($_POST['res_notes'] ?? ''));

    // 2. Comprehensive Validation
    if (empty($res_name) || empty($res_email) || empty($res_phone) || empty($res_date) || empty($res_time)) {
        header("Location: index.php?res_status=empty#reservation");
        exit;
    }

    // Prevent past date reservations
    $today = date('Y-m-d');
    if ($res_date < $today) {
        header("Location: index.php?res_status=past_date#reservation");
        exit;
    }

    // 3. Insert into Database
    // Note: Ensure your 'reservations' table has these columns
    $stmt = $conn->prepare("INSERT INTO reservations (user_id, res_name, res_email, res_phone, res_date, res_time, res_guests, res_notes, status) VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'pending')");
    $stmt->bind_param("isssssis", $user_id, $res_name, $res_email, $res_phone, $res_date, $res_time, $res_guests, $res_notes);

    if ($stmt->execute()) {
        $reservation_id = $stmt->insert_id;

        // 4. Log the action for the Admin Panel Audit Logs
        if (function_exists('logAdminAction')) {
            $log_desc = "New reservation #$reservation_id created for $res_name ($res_guests guests)";
            logAdminAction($conn, $user_id, $_SESSION['fullname'] ?? 'Customer', 'create_reservation', $log_desc, 'reservations', $reservation_id);
        }

        // 5. Send Themed Email Notification
        if (function_exists('send_email')) {
            $formatted_date = date("F j, Y", strtotime($res_date));
            $formatted_time = date("g:i A", strtotime($res_time));
            
            $subject = "Reservation Request Received - Cafe Emmanuel";
            $body = "
                <div style='background-color: #F8F4EE; padding: 40px; font-family: \"Poppins\", Arial, sans-serif;'>
                    <div style='max-width: 600px; margin: 0 auto; background: #ffffff; border-radius: 16px; overflow: hidden; border: 1px solid #E6DCD3; box-shadow: 0 10px 30px rgba(44, 30, 22, 0.05);'>
                        <div style='background-color: #2C1E16; padding: 30px; text-align: center;'>
                            <h1 style='color: #D4A373; margin: 0; font-size: 24px; text-transform: uppercase; letter-spacing: 2px;'>Cafe Emmanuel</h1>
                        </div>
                        <div style='padding: 40px; color: #3A2B24; line-height: 1.6;'>
                            <h2 style='color: #A05E44; margin-top: 0;'>Hello, $res_name!</h2>
                            <p>We've received your table reservation request. Our team is currently reviewing the availability for your preferred time.</p>
                            
                            <div style='background: #FDFBF7; padding: 25px; border-radius: 12px; border: 1px dashed #D4A373; margin: 25px 0;'>
                                <p style='margin: 5px 0;'><strong>Date:</strong> $formatted_date</p>
                                <p style='margin: 5px 0;'><strong>Time:</strong> $formatted_time</p>
                                <p style='margin: 5px 0;'><strong>Guests:</strong> $res_guests Persons</p>
                                <p style='margin: 5px 0;'><strong>Status:</strong> <span style='color: #A05E44; font-weight: bold;'>Pending Confirmation</span></p>
                            </div>

                            <p>You will receive another email once your reservation is confirmed. We look forward to serving you!</p>
                            <hr style='border: none; border-top: 1px solid #E6DCD3; margin: 30px 0;'>
                            <p style='font-size: 13px; color: #756358; text-align: center;'>Guagua, Pampanga, Philippines</p>
                        </div>
                    </div>
                </div>
            ";
            send_email($res_email, $subject, $body);
        }

        // 6. Success Redirect
        header("Location: index.php?res_status=success#reservation");
        exit;

    } else {
        // Database Error
        header("Location: index.php?res_status=error#reservation");
        exit;
    }

    $stmt->close();
} else {
    header("Location: index.php");
    exit;
}
?>