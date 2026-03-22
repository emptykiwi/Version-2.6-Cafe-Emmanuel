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

    // 2. ID Upload Handling
    $valid_id_path = '';
    if (isset($_FILES['valid_id_file']) && $_FILES['valid_id_file']['error'] === 0) {
        $allowed = ['jpg', 'jpeg', 'png', 'webp'];
        $filename = $_FILES['valid_id_file']['name'];
        $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
        
        if (in_array($ext, $allowed)) {
            $new_filename = "ID_" . time() . "_" . $user_id . "." . $ext;
            $upload_dir = 'uploads/reservations/';
            if (!is_dir($upload_dir)) mkdir($upload_dir, 0777, true);
            $target = $upload_dir . $new_filename;
            
            if (move_uploaded_file($_FILES['valid_id_file']['tmp_name'], $target)) {
                $valid_id_path = $target;
            }
        }
    }

    // 3. Comprehensive Validation
    if (empty($res_name) || empty($res_email) || empty($res_phone) || empty($res_date) || empty($res_time) || empty($valid_id_path)) {
        header("Location: index.php?res_status=empty#reservation");
        exit;
    }

    // Prevent past date reservations
    $today = date('Y-m-d');
    if ($res_date < $today) {
        header("Location: index.php?res_status=past_date#reservation");
        exit;
    }

    // 4. Insert into Database
    // Note: Ensure your 'reservations' table has these columns (run migrate_reservations.php)
    $sql = "INSERT INTO reservations (user_id, res_name, res_email, res_phone, res_date, res_time, res_guests, res_notes, status, valid_id) VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'pending', ?)";
    $stmt = $conn->prepare($sql);
    
    if (!$stmt) {
        // This usually happens if the database schema is not updated.
        if (strpos($conn->error, "Unknown column 'valid_id'") !== false) {
             die("<div style='font-family:sans-serif; max-width:600px; margin:50px auto; padding:20px; border:2px solid #A05E44; border-radius:10px; background:#fff;'>
                    <h2 style='color:#A05E44; margin-top:0;'>Database Update Required</h2>
                    <p>The reservation system has been upgraded, but your database needs a quick update to support Valid ID uploads.</p>
                    <p style='text-align:center; margin:30px 0;'>
                        <a href='migrate_reservations.php' style='background:#A05E44; color:white; padding:15px 30px; text-decoration:none; border-radius:5px; font-weight:bold;'>Click Here to Fix Database Automatically</a>
                    </p>
                    <p>After clicking, you can come back and complete your reservation.</p>
                 </div>");
        }
        die("Database Error: " . $conn->error);
    }
    
    $stmt->bind_param("isssssiss", $user_id, $res_name, $res_email, $res_phone, $res_date, $res_time, $res_guests, $res_notes, $valid_id_path);

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