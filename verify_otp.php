<?php
// Enable error reporting just in case, but we will handle them gracefully
error_reporting(E_ALL);
ini_set('display_errors', 1);

session_start();
ob_start(); // Buffer output to prevent header errors

// Connect to Database
try {
    if (!file_exists('db_connect.php')) {
        throw new Exception("db_connect.php file not found!");
    }
    include 'db_connect.php';
    
    if (!$conn) {
        throw new Exception("Database connection failed.");
    }
} catch (Exception $e) {
    die("<h3 style='color:red'>Database Error: " . $e->getMessage() . "</h3>");
}

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $entered_otp = $_POST['otp'] ?? '';
    
    // Check if session exists
    if (!isset($_SESSION['otp']) || !isset($_SESSION['temp_user'])) {
        die("<div style='color:red; padding:20px; border:1px solid red;'>Error: Session expired. Please go back and register again.</div>");
    }

    // Verify OTP Match
    if (trim($entered_otp) == trim($_SESSION['otp'])) {
        $user = $_SESSION['temp_user'];
        
        try {
            // 1. Check if email ALREADY exists (The Fix for your error)
            $check_stmt = $conn->prepare("SELECT user_id FROM users WHERE email = ?");
            $check_stmt->bind_param("s", $user['email']);
            $check_stmt->execute();
            $check_result = $check_stmt->get_result();
            
            // IF ACCOUNT EXISTS -> We treat this as SUCCESS now (Double Submission)
            if ($check_result->num_rows > 0) {
                $check_stmt->close();
                // Clear session
                unset($_SESSION['otp']);
                unset($_SESSION['temp_user']);
                
                // Show Success Page immediately
                showSuccessMessage("Account Verified!", "Your account was already active. You can now log in.");
                exit();
            }
            $check_stmt->close();

            // 2. Account doesn't exist? Create it.
            $sql = "INSERT INTO users (full_name, email, password, address, contact_number, role) VALUES (?, ?, ?, ?, ?, 'user')";
            $stmt = $conn->prepare($sql);
            
            if (!$stmt) {
                throw new Exception("Database Prepare Error: " . $conn->error);
            }

            $stmt->bind_param("sssss", 
                $user['full_name'], 
                $user['email'], 
                $user['password'], 
                $user['address'], 
                $user['contact_number']
            );

            if ($stmt->execute()) {
                // Success - Clear Session
                unset($_SESSION['otp']);
                unset($_SESSION['temp_user']);
                
                showSuccessMessage("Verification Successful!", "Your account has been created successfully.");
                exit(); 
            } else {
                throw new Exception("Registration Failed: " . $stmt->error);
            }

        } catch (Exception $e) {
            $error = "System Error: " . $e->getMessage();
        }

    } else {
        $error = "Invalid OTP. Please try again.";
    }
}

// Helper function to show a nice success page
function showSuccessMessage($title, $message) {
    ?>
    <!DOCTYPE html>
    <html lang="en">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Success</title>
        <link rel="stylesheet" href="CSS/register1.css">
        <style>
            body { font-family: 'Poppins', sans-serif; display: flex; justify-content: center; align-items: center; height: 100vh; background-color: #f4f4f4; margin: 0; }
            .success-container { background: white; padding: 40px; border-radius: 10px; box-shadow: 0 4px 10px rgba(0,0,0,0.1); text-align: center; max-width: 400px; width: 100%; }
            .success-icon { font-size: 50px; color: #28a745; margin-bottom: 20px; }
            .btn-login { display: inline-block; margin-top: 20px; padding: 12px 24px; background: #8B4513; color: white; text-decoration: none; border-radius: 5px; font-weight: bold; }
            .btn-login:hover { background: #6F3709; }
        </style>
    </head>
    <body>
        <div class="success-container">
            <div class="success-icon">✔</div>
            <h2><?php echo $title; ?></h2>
            <p><?php echo $message; ?></p>
            <a href="index.php?status=success" class="btn-login">Go to Login</a>
        </div>
    </body>
    </html>
    <?php
}
ob_end_flush();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Verify OTP - Cafe Emmanuel</title>
    <link rel="stylesheet" href="CSS/register1.css">
</head>
<body>
    <div class="verify-container">
        <h2>Enter Verification Code</h2>
        <p>We sent a code to your email.</p>
        
        <?php if(isset($error)): ?>
            <div style="background-color: #ffebee; color: #c62828; padding: 10px; border-radius: 5px; margin-bottom: 15px; border: 1px solid #ef9a9a;">
                <?php echo $error; ?>
            </div>
        <?php endif; ?>

        <form method="POST" action="verify_otp.php">
            <input type="text" name="otp" placeholder="6-digit code" required maxlength="6" pattern="\d{6}" autocomplete="off">
            <button type="submit" class="btn-verify">Verify Account</button>
        </form>
        
        <div class="resend" style="margin-top: 20px;">
            <a href="resend_otp.php">Didn't receive code? Resend</a>
        </div>
    </div>
</body>
</html>