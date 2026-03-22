<?php
session_start();
include 'config.php';
require_once __DIR__ . '/mailer.php';

$error = '';
$success = '';
$step = 1;

// If the user already has an active reset session, default to step 2
if (isset($_SESSION['reset_user_id']) && isset($_SESSION['reset_email'])) {
    $step = 2;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    
    // STEP 1: Request Reset Code
    if (isset($_POST['send_code'])) {
        $email = trim($_POST['email'] ?? '');
        
        if (empty($email)) {
            $error = "Please enter your email address.";
        } else {
            $stmt = $conn->prepare("SELECT id, fullname FROM users WHERE email = ?");
            $stmt->bind_param("s", $email);
            $stmt->execute();
            $res = $stmt->get_result();
            
            if ($res && $res->num_rows === 1) {
                $user = $res->fetch_assoc();
                $code = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);
                $expires = date('Y-m-d H:i:s', time() + 900); // 15 mins expiry
                
                $otpStmt = $conn->prepare("INSERT INTO otp_codes (user_id, code, expires_at) VALUES (?, ?, ?)");
                $otpStmt->bind_param("iss", $user['id'], $code, $expires);
                $otpStmt->execute();
                $otpStmt->close();
                
                $subject = 'Password Reset - Cafe Emmanuel';
                $body = "
                <div style='font-family: Arial, sans-serif; color: #333; max-width: 500px; margin: 0 auto; border: 1px solid #eee; border-radius: 10px; overflow: hidden;'>
                    <div style='background-color: #A05E44; color: white; padding: 20px; text-align: center;'>
                        <h2 style='margin: 0;'>Password Reset</h2>
                    </div>
                    <div style='padding: 30px;'>
                        <p>Hi <strong>" . htmlspecialchars($user['fullname']) . "</strong>,</p>
                        <p>We received a request to reset the password for your Cafe Emmanuel account. Here is your 6-digit verification code:</p>
                        <div style='text-align: center; margin: 30px 0;'>
                            <span style='font-size: 32px; font-weight: bold; color: #A05E44; letter-spacing: 5px; background: #f8f4ee; padding: 15px 25px; border-radius: 8px; border: 1px dashed #A05E44;'>$code</span>
                        </div>
                        <p style='color: #666; font-size: 14px;'>This code will expire in 15 minutes. If you didn't request a password reset, you can safely ignore this email.</p>
                    </div>
                </div>";
                
                send_email($email, $subject, $body);
                
                $_SESSION['reset_user_id'] = $user['id'];
                $_SESSION['reset_email'] = $email;
                $success = "A verification code has been sent to your email.";
                $step = 2;
            } else {
                $error = "We couldn't find an account associated with that email.";
            }
            $stmt->close();
        }
    }
    
    // STEP 2: Verify Code and Update Password
    elseif (isset($_POST['reset_password'])) {
        $code_input = trim($_POST['reset_code'] ?? '');
        $new_password = $_POST['new_password'] ?? '';
        $confirm_password = $_POST['confirm_password'] ?? '';
        $userId = (int)($_SESSION['reset_user_id'] ?? 0);

        if (!$userId) {
            $error = "Session expired. Please request a new code.";
            $step = 1;
        } elseif (empty($code_input) || empty($new_password)) {
            $error = "Please fill in all fields.";
            $step = 2;
        } elseif ($new_password !== $confirm_password) {
            $error = "Passwords do not match.";
            $step = 2;
        } else {
            $stmt = $conn->prepare("SELECT id, code, expires_at, attempts FROM otp_codes WHERE user_id = ? AND used_at IS NULL ORDER BY id DESC LIMIT 1");
            $stmt->bind_param('i', $userId);
            $stmt->execute();
            $result = $stmt->get_result();
            $otpRecord = $result->fetch_assoc();
            $stmt->close();
            
            if (!$otpRecord) {
                $error = 'Code expired or not found. Please request a new one.';
                $step = 1;
                unset($_SESSION['reset_user_id'], $_SESSION['reset_email']);
            } elseif (time() > strtotime($otpRecord['expires_at'])) {
                $error = 'Code expired. Please request a new one.';
                $step = 1;
                unset($_SESSION['reset_user_id'], $_SESSION['reset_email']);
            } elseif ((int)$otpRecord['attempts'] >= 5) {
                $error = 'Too many attempts. Please request a new code.';
                $step = 1;
                unset($_SESSION['reset_user_id'], $_SESSION['reset_email']);
            } elseif ($code_input !== $otpRecord['code']) {
                $updateStmt = $conn->prepare("UPDATE otp_codes SET attempts = attempts + 1 WHERE id = ?");
                $updateStmt->bind_param('i', $otpRecord['id']);
                $updateStmt->execute();
                $updateStmt->close();
                $error = 'Invalid code. Try again.';
                $step = 2;
            } else {
                // Success - Update Password
                $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
                $updStmt = $conn->prepare("UPDATE users SET password = ? WHERE id = ?");
                $updStmt->bind_param("si", $hashed_password, $userId);
                $updStmt->execute();
                $updStmt->close();
                
                // Mark OTP as used
                $markStmt = $conn->prepare("UPDATE otp_codes SET used_at = NOW() WHERE id = ?");
                $markStmt->bind_param('i', $otpRecord['id']);
                $markStmt->execute();
                $markStmt->close();
                
                unset($_SESSION['reset_user_id'], $_SESSION['reset_email']);
                $step = 3; 
            }
        }
    }
    
    // CANCEL RESET PROCESS
    elseif (isset($_POST['cancel_reset'])) {
        unset($_SESSION['reset_user_id'], $_SESSION['reset_email']);
        $step = 1;
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="icon" type="image/png" href="Logo_Brand.png">
    <title>Forgot Password - Cafe Emmanuel</title>
    
    <!-- Fonts & Icons -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css" />
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:ital,wght@0,500;0,700;1,500&family=Poppins:wght@300;400;500;600&display=swap" rel="stylesheet">
    
    <style>
        :root {
            --primary: #A05E44;
            --primary-hover: #804832;
            --secondary: #2C1E16;
            --bg-main: #F8F4EE;
            --bg-card: #FFFFFF;
            --text-dark: #3A2B24;
            --text-muted: #756358;
            --radius-md: 16px;
            --shadow-soft: 0 20px 50px rgba(44, 30, 22, 0.08);
        }

        * { margin: 0; padding: 0; box-sizing: border-box; }
        
        body {
            font-family: 'Poppins', sans-serif;
            background-color: var(--bg-main);
            background-image: linear-gradient(135deg, rgba(248, 244, 238, 0.95) 0%, rgba(239, 229, 217, 0.95) 100%), url('Cover-Photo.jpg');
            background-size: cover;
            background-position: center;
            background-attachment: fixed;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
            color: var(--text-dark);
        }

        .auth-container {
            width: 100%;
            max-width: 480px;
            background: var(--bg-card);
            border-radius: var(--radius-md);
            box-shadow: var(--shadow-soft);
            overflow: hidden;
            position: relative;
            border: 1px solid rgba(160, 94, 68, 0.1);
            animation: slideUp 0.5s ease-out;
        }

        @keyframes slideUp {
            from { opacity: 0; transform: translateY(30px); }
            to { opacity: 1; transform: translateY(0); }
        }

        .auth-header {
            text-align: center;
            padding: 40px 30px 20px;
        }

        .auth-logo {
            width: 90px;
            margin-bottom: 20px;
        }

        .auth-title {
            font-family: 'Playfair Display', serif;
            font-size: 2rem;
            color: var(--secondary);
            margin-bottom: 10px;
        }

        .auth-subtitle {
            color: var(--text-muted);
            font-size: 0.95rem;
            line-height: 1.5;
        }

        .auth-body {
            padding: 20px 40px 40px;
        }

        .input-group {
            position: relative;
            margin-bottom: 20px;
        }

        .input-icon {
            position: absolute;
            left: 20px;
            top: 50%;
            transform: translateY(-50%);
            color: #aaa;
            font-size: 1.1rem;
            transition: 0.3s;
        }

        .input-field {
            width: 100%;
            padding: 16px 20px 16px 50px;
            border: 2px solid #E6DCD3;
            border-radius: 8px;
            font-family: 'Poppins', sans-serif;
            font-size: 1rem;
            background: #fff;
            transition: 0.3s;
        }

        .input-field:focus {
            border-color: var(--primary);
            box-shadow: 0 0 0 4px rgba(160, 94, 68, 0.15);
            outline: none;
        }

        .input-field:focus + .input-icon {
            color: var(--primary);
        }

        .btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            padding: 15px;
            border-radius: 50px;
            font-weight: 600;
            font-size: 1rem;
            cursor: pointer;
            transition: all 0.3s ease;
            border: none;
            width: 100%;
            gap: 10px;
        }

        .btn-primary {
            background: linear-gradient(135deg, var(--primary), #D4A373);
            color: #fff;
            box-shadow: 0 8px 25px rgba(160, 94, 68, 0.4);
        }

        .btn-primary:hover {
            transform: translateY(-3px);
            box-shadow: 0 12px 30px rgba(160, 94, 68, 0.5);
        }

        .btn-outline {
            background: transparent;
            color: var(--text-muted);
            border: 2px solid #E6DCD3;
            margin-top: 15px;
        }

        .btn-outline:hover {
            color: var(--secondary);
            border-color: var(--secondary);
        }

        .alert {
            padding: 12px 15px;
            border-radius: 8px;
            margin-bottom: 20px;
            font-size: 0.9rem;
            display: flex;
            align-items: center;
            gap: 10px;
            font-weight: 500;
        }

        .alert-error {
            background-color: #fdf2f2;
            color: #e02424;
            border: 1px solid #fbc4c4;
        }

        .alert-success {
            background-color: #f3faf5;
            color: #12723a;
            border: 1px solid #cce8d5;
        }

        .back-link {
            display: block;
            text-align: center;
            margin-top: 25px;
            color: var(--text-muted);
            text-decoration: none;
            font-weight: 500;
            font-size: 0.95rem;
            transition: color 0.3s;
        }

        .back-link:hover {
            color: var(--primary);
        }

        .otp-input {
            text-align: center;
            letter-spacing: 10px;
            font-weight: 700;
            font-size: 1.5rem;
            padding-left: 20px !important;
        }
        
        /* Success Screen Graphics */
        .success-circle {
            width: 80px;
            height: 80px;
            background: #e8f5e9;
            color: #4caf50;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 2.5rem;
            margin: 0 auto 20px;
            box-shadow: 0 10px 20px rgba(76, 175, 80, 0.2);
            animation: popIn 0.6s cubic-bezier(0.175, 0.885, 0.32, 1.275);
        }

        @keyframes popIn {
            0% { transform: scale(0); }
            100% { transform: scale(1); }
        }
    </style>
</head>
<body>

    <div class="auth-container">
        
        <?php if ($step === 1): ?>
            <!-- STEP 1: Enter Email -->
            <div class="auth-header">
                <img src="Logo_Brand.png" alt="Cafe Emmanuel" class="auth-logo">
                <h1 class="auth-title">Forgot Password</h1>
                <p class="auth-subtitle">Enter your registered email address and we'll send you a 6-digit verification code.</p>
            </div>
            <div class="auth-body">
                <?php if ($error): ?>
                    <div class="alert alert-error"><i class="fas fa-exclamation-circle"></i> <?php echo $error; ?></div>
                <?php endif; ?>
                
                <form method="POST" action="forgot_password.php">
                    <div class="input-group">
                        <input type="email" name="email" class="input-field" placeholder="Enter your email address" required autofocus>
                        <i class="fas fa-envelope input-icon"></i>
                    </div>
                    <button type="submit" name="send_code" class="btn btn-primary">Send Verification Code <i class="fas fa-paper-plane"></i></button>
                </form>
                
                <a href="index.php" class="back-link"><i class="fas fa-arrow-left"></i> Back to Login</a>
            </div>

        <?php elseif ($step === 2): ?>
            <!-- STEP 2: Verify OTP & Enter New Password -->
            <div class="auth-header">
                <img src="Logo_Brand.png" alt="Cafe Emmanuel" class="auth-logo" style="width: 70px;">
                <h1 class="auth-title">Reset Password</h1>
                <p class="auth-subtitle">Code sent to <strong><?php echo htmlspecialchars($_SESSION['reset_email']); ?></strong></p>
            </div>
            <div class="auth-body" style="padding-top: 0;">
                
                <?php if ($success): ?>
                    <div class="alert alert-success"><i class="fas fa-check-circle"></i> <?php echo $success; ?></div>
                <?php endif; ?>
                
                <?php if ($error): ?>
                    <div class="alert alert-error"><i class="fas fa-exclamation-circle"></i> <?php echo $error; ?></div>
                <?php endif; ?>
                
                <form method="POST" action="forgot_password.php">
                    <div class="input-group">
                        <input type="text" name="reset_code" class="input-field otp-input" placeholder="------" maxlength="6" required autofocus>
                        <i class="fas fa-key input-icon" style="display: none;"></i>
                    </div>
                    <div class="input-group">
                        <input type="password" name="new_password" class="input-field" placeholder="New Password" required>
                        <i class="fas fa-lock input-icon"></i>
                    </div>
                    <div class="input-group">
                        <input type="password" name="confirm_password" class="input-field" placeholder="Confirm New Password" required>
                        <i class="fas fa-check-double input-icon"></i>
                    </div>
                    
                    <button type="submit" name="reset_password" class="btn btn-primary">Update Password <i class="fas fa-save"></i></button>
                </form>
                
                <form method="POST" action="forgot_password.php" style="margin-top: 10px;">
                    <button type="submit" name="cancel_reset" class="btn btn-outline" formnovalidate>Cancel</button>
                </form>
            </div>

        <?php elseif ($step === 3): ?>
            <!-- STEP 3: Success -->
            <div class="auth-header" style="padding-top: 60px;">
                <div class="success-circle"><i class="fas fa-check"></i></div>
                <h1 class="auth-title">Password Updated!</h1>
                <p class="auth-subtitle">Your password has been changed successfully. You can now use your new password to log in to your account.</p>
            </div>
            <div class="auth-body" style="text-align: center;">
                <a href="index.php" class="btn btn-primary" style="text-decoration: none;">Continue to Login <i class="fas fa-sign-in-alt"></i></a>
            </div>
        <?php endif; ?>

    </div>

</body>
</html>