<?php
session_start();

// Enable error reporting temporarily to help debug if issues persist
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $first_name = $_POST['fname'] ?? '';
    $last_name = $_POST['lname'] ?? '';
    $email = $_POST['email'] ?? '';
    $message = $_POST['message'] ?? '';

    // Simple validation
    if (!empty($first_name) && !empty($last_name) && !empty($email) && !empty($message)) {
        
        // --- FIX: Use the centralized connection file ---
        // This ensures we use the correct live server credentials
        require_once 'db_connect.php';

        // Check if connection exists
        if (!isset($conn) || $conn->connect_error) {
            // Log the error to the server error log instead of crashing
            error_log("Database connection failed in submit_inquiry.php: " . ($conn->connect_error ?? 'Variable $conn not set'));
            header("Location: contact.php?status=error");
            exit();
        }

        $stmt = $conn->prepare("INSERT INTO inquiries (first_name, last_name, email, message) VALUES (?, ?, ?, ?)");
        
        if ($stmt) {
            $stmt->bind_param("ssss", $first_name, $last_name, $email, $message);

            if ($stmt->execute()) {
                // Success: redirect to contact.php
                header("Location: contact.php?status=success");
            } else {
                // Database execute error
                error_log("SQL Execute Error: " . $stmt->error);
                header("Location: contact.php?status=error");
            }
            $stmt->close();
        } else {
            // Database prepare error
            error_log("SQL Prepare Error: " . $conn->error);
            header("Location: contact.php?status=error");
        }
        
        $conn->close();
        exit();
    } else {
        // Validation error: redirect to contact.php
        header("Location: contact.php?status=error");
        exit();
    }
} else {
    // Not a POST request: redirect to contact.php
    header("Location: contact.php");
    exit();
}
?>