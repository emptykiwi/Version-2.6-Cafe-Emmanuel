<?php
session_start();

// Included your specific database connection file
require_once 'db_connect.php'; 

// include 'config.php'; // Uncomment this if config.php contains other settings besides the DB connection
require_once __DIR__ . '/audit.php';
require_once __DIR__ . '/mailer.php'; 

$login_error = '';
$login_success = '';
$register_error = '';
$register_success = '';
$otp_error = '';
$otp_success = '';
$forgot_error = '';
$forgot_success = '';
$reset_error = '';

// ---------------------------------------------------------
// 1. OTP VERIFICATION LOGIC
// ---------------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['verify_otp'])) {
    $code_input = trim($_POST['otp_code'] ?? '');
    $userId = (int)($_SESSION['otp_user_id'] ?? 0);
    
    if (empty($code_input)) {
        $otp_error = 'Please enter the code.';
    } elseif (!$userId) {
        $otp_error = 'Session expired. Please login again.';
        unset($_SESSION['otp_user_id'], $_SESSION['otp_email'], $_SESSION['show_otp_modal']);
    } else {
        $stmt = $conn->prepare("SELECT id, code, expires_at, attempts FROM otp_codes WHERE user_id = ? AND used_at IS NULL ORDER BY id DESC LIMIT 1");
        $stmt->bind_param('i', $userId);
        $stmt->execute();
        $result = $stmt->get_result();
        $otpRecord = $result->fetch_assoc();
        $stmt->close();
        
        if (!$otpRecord) {
            $otp_error = 'Code expired or not found. Please resend.';
        } elseif (time() > strtotime($otpRecord['expires_at'])) {
            $otp_error = 'Code expired. Please resend.';
        } elseif ((int)$otpRecord['attempts'] >= 5) {
            $otp_error = 'Too many attempts. Please request a new code.';
        } elseif ($code_input !== $otpRecord['code']) {
            $updateStmt = $conn->prepare("UPDATE otp_codes SET attempts = attempts + 1 WHERE id = ?");
            $updateStmt->bind_param('i', $otpRecord['id']);
            $updateStmt->execute();
            $updateStmt->close();
            $otp_error = 'Invalid code. Try again.';
        } else {
            $markStmt = $conn->prepare("UPDATE otp_codes SET used_at = NOW() WHERE id = ?");
            $markStmt->bind_param('i', $otpRecord['id']);
            $markStmt->execute();
            $markStmt->close();
            
            $verifyStmt = $conn->prepare("UPDATE users SET is_verified = 1 WHERE id = ?");
            if ($verifyStmt) {
                $verifyStmt->bind_param('i', $userId);
                $verifyStmt->execute();
                $verifyStmt->close();
            }
            
            $userStmt = $conn->prepare("SELECT fullname, role, profile_picture FROM users WHERE id = ?");
            $userStmt->bind_param('i', $userId);
            $userStmt->execute();
            $userRes = $userStmt->get_result();
            $userData = $userRes->fetch_assoc();
            $userStmt->close();

            $_SESSION['user_id'] = $userId;
            $_SESSION['email'] = $_SESSION['otp_email'];
            $_SESSION['fullname'] = $userData['fullname'] ?? $_SESSION['otp_fullname'];
            $_SESSION['role'] = $userData['role'] ?? $_SESSION['otp_role'];
            $_SESSION['profile_pic'] = $userData['profile_picture'] ?? null; 

            unset($_SESSION['otp_user_id'], $_SESSION['otp_email'], $_SESSION['otp_fullname'], $_SESSION['otp_role'], $_SESSION['show_otp_modal']);
            
            audit($userId, 'login_success_otp_verified', 'users', $userId, []);
            
            if (in_array($_SESSION['role'], ['admin', 'super_admin'])) {
                header("Location: Dashboard.php");
            } else {
                header("Location: index.php");
            }
            exit;
        }
    }
}

// ---------------------------------------------------------
// 2. LOGIN LOGIC
// ---------------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['login'])) {
    $identifier = trim($_POST['identifier'] ?? $_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';

    if (empty($identifier) || empty($password)) {
        $login_error = "Please fill in both fields.";
    } else {
        $stmt = $conn->prepare("SELECT * FROM users WHERE email = ? OR username = ?");
        $stmt->bind_param("ss", $identifier, $identifier);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result && $result->num_rows === 1) {
            $user = $result->fetch_assoc();
            
            if (password_verify($password, $user['password'])) {
                $isVerified = !isset($user['is_verified']) || $user['is_verified'] == 1;

                if ($isVerified) {
                    $_SESSION['user_id'] = $user['id'];
                    $_SESSION['email'] = $user['email']; 
                    $_SESSION['fullname'] = $user['fullname'];
                    $_SESSION['role'] = $user['role'];
                    $_SESSION['profile_pic'] = $user['profile_picture'] ?? null; 
                    
                    audit($user['id'], 'login_success', 'users', $user['id'], []);
                    
                    if (in_array($user['role'], ['admin', 'super_admin'])) {
                        header("Location: Dashboard.php");
                    } else {
                        header("Location: index.php");
                    }
                    exit;
                } else {
                    $code = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);
                    $expires = date('Y-m-d H:i:s', time() + 600);
                    
                    $otpStmt = $conn->prepare("INSERT INTO otp_codes (user_id, code, expires_at) VALUES (?, ?, ?)");
                    $otpStmt->bind_param("iss", $user['id'], $code, $expires);
                    $otpStmt->execute();
                    
                    $subject = 'Verify Your Account - Cafe Emmanuel';
                    $body = "<h3>Account Verification Needed</h3><p>Your code is: <b style='font-size:20px;'>$code</b></p>";
                    send_email($user['email'], $subject, $body);
                    
                    $_SESSION['otp_user_id'] = $user['id'];
                    $_SESSION['otp_email'] = $user['email'];
                    $_SESSION['otp_fullname'] = $user['fullname'];
                    $_SESSION['otp_role'] = $user['role'];
                    $_SESSION['show_otp_modal'] = true;
                    
                    header("Location: index.php?verify=pending");
                    exit;
                }
            } else {
                $login_error = "Wrong password!";
            }
        } else {
            $login_error = "Username or Email not found!";
        }
        $stmt->close();
    }
}

// ---------------------------------------------------------
// 3. REGISTRATION LOGIC
// ---------------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['register'])) {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';
    $fullname = trim($_POST['fullname'] ?? '');
    $email    = trim($_POST['email'] ?? '');
    $contact  = trim($_POST['contact'] ?? '');
    $gender   = trim($_POST['gender'] ?? '');
    $confirm  = $_POST['confirm_password'] ?? '';

    if ($password !== $confirm) {
        $register_error = "Passwords do not match!";
    } else {
        $stmt = $conn->prepare("SELECT id FROM users WHERE email = ? OR username = ?");
        $stmt->bind_param("ss", $email, $username);
        $stmt->execute();
        $stmt->store_result();

        if ($stmt->num_rows > 0) {
            $register_error = "Email or Username already exists.";
        } else {
            $hashed_password = password_hash($password, PASSWORD_DEFAULT);
            $insert_stmt = $conn->prepare("INSERT INTO users (username, password, fullname, email, contact, gender, is_verified) VALUES (?, ?, ?, ?, ?, ?, 0)");
            $insert_stmt->bind_param("ssssss", $username, $hashed_password, $fullname, $email, $contact, $gender);

            if ($insert_stmt->execute()) {
                $new_user_id = $insert_stmt->insert_id;
                $code = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);
                $expires = date('Y-m-d H:i:s', time() + 600);
                
                $otpStmt = $conn->prepare("INSERT INTO otp_codes (user_id, code, expires_at) VALUES (?, ?, ?)");
                $otpStmt->bind_param("iss", $new_user_id, $code, $expires);
                $otpStmt->execute();
                $otpStmt->close();
                
                $subject = "Welcome! Verify your Cafe Emmanuel Account";
                $body = "<div style='color:#333;'><h1>Welcome, $fullname!</h1><p>Verify your account using code: <b style='font-size:24px; color:#A05E44;'>$code</b></p></div>";
                send_email($email, $subject, $body);

                $_SESSION['otp_user_id'] = $new_user_id;
                $_SESSION['otp_email'] = $email;
                $_SESSION['otp_fullname'] = $fullname;
                $_SESSION['otp_role'] = 'user';
                $_SESSION['show_otp_modal'] = true;

                header("Location: index.php?verify=new_account");
                exit;
            } else {
                $register_error = "Error: " . $insert_stmt->error;
            }
            $insert_stmt->close();
        }
        $stmt->close();
    }
}

// ---------------------------------------------------------
// 4. FORGOT PASSWORD LOGIC
// ---------------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['forgot_password'])) {
    $email = trim($_POST['forgot_email'] ?? '');
    if (empty($email)) {
        $forgot_error = "Please enter your email address.";
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
            $body = "<div style='color:#333;'><h3>Password Reset Request</h3><p>Hi " . htmlspecialchars($user['fullname']) . ",</p><p>We received a request to reset your password. Your reset code is: <b style='font-size:24px; color:#A05E44;'>$code</b></p><p>If you didn't request this, you can safely ignore this email.</p></div>";
            send_email($email, $subject, $body);
            
            $_SESSION['reset_user_id'] = $user['id'];
            $_SESSION['reset_email'] = $email;
            $forgot_success = "A reset code has been sent to your email.";
        } else {
            $forgot_error = "Email not found in our system.";
        }
        $stmt->close();
    }
}

// ---------------------------------------------------------
// 5. RESET PASSWORD LOGIC
// ---------------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['reset_password'])) {
    $code_input = trim($_POST['reset_code'] ?? '');
    $new_password = $_POST['new_password'] ?? '';
    $confirm_password = $_POST['confirm_new_password'] ?? '';
    $userId = (int)($_SESSION['reset_user_id'] ?? 0);

    if (!$userId) {
        $reset_error = "Session expired. Please request a new code.";
    } elseif (empty($code_input) || empty($new_password)) {
        $reset_error = "Please fill in all fields.";
    } elseif ($new_password !== $confirm_password) {
        $reset_error = "Passwords do not match.";
    } else {
        $stmt = $conn->prepare("SELECT id, code, expires_at, attempts FROM otp_codes WHERE user_id = ? AND used_at IS NULL ORDER BY id DESC LIMIT 1");
        $stmt->bind_param('i', $userId);
        $stmt->execute();
        $result = $stmt->get_result();
        $otpRecord = $result->fetch_assoc();
        $stmt->close();
        
        if (!$otpRecord) {
            $reset_error = 'Code expired or not found. Please request a new one.';
        } elseif (time() > strtotime($otpRecord['expires_at'])) {
            $reset_error = 'Code expired. Please request a new one.';
        } elseif ((int)$otpRecord['attempts'] >= 5) {
            $reset_error = 'Too many attempts. Please request a new code.';
        } elseif ($code_input !== $otpRecord['code']) {
            $updateStmt = $conn->prepare("UPDATE otp_codes SET attempts = attempts + 1 WHERE id = ?");
            $updateStmt->bind_param('i', $otpRecord['id']);
            $updateStmt->execute();
            $updateStmt->close();
            $reset_error = 'Invalid code. Try again.';
        } else {
            $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
            $updStmt = $conn->prepare("UPDATE users SET password = ? WHERE id = ?");
            $updStmt->bind_param("si", $hashed_password, $userId);
            $updStmt->execute();
            $updStmt->close();
            
            $markStmt = $conn->prepare("UPDATE otp_codes SET used_at = NOW() WHERE id = ?");
            $markStmt->bind_param('i', $otpRecord['id']);
            $markStmt->execute();
            $markStmt->close();
            
            unset($_SESSION['reset_user_id'], $_SESSION['reset_email']);
            $login_success = "Password updated successfully! You can now log in.";
        }
    }
}

// ---------------------------------------------------------
// FETCH DATA (HERO SLIDES & FULL MENU)
// ---------------------------------------------------------
$video_slide = null;
$image_slides = [];

$vid_sql = "SELECT * FROM hero_slides WHERE type = 'video' ORDER BY sort_order ASC LIMIT 1";
$vid_res = $conn->query($vid_sql);
if ($vid_res && $vid_res->num_rows > 0) { $video_slide = $vid_res->fetch_assoc(); }

$img_sql = "SELECT * FROM hero_slides WHERE type = 'image' ORDER BY sort_order ASC";
$img_res = $conn->query($img_sql);
if ($img_res) { while ($row = $img_res->fetch_assoc()) { $image_slides[] = $row; } }

$hero_slides = [];
if ($video_slide) $hero_slides[] = $video_slide;
foreach ($image_slides as $img) $hero_slides[] = $img;

if (empty($hero_slides)) {
    $hero_slides[] = [
        'type' => 'image',
        'file_path' => 'Cover-Photo.jpg',
        'heading' => 'Welcome to Cafe Emmanuel',
        'subtext' => 'Roasting with Art. Taste the difference.',
        'button_text' => 'Explore Menu',
        'button_link' => '#menu'
    ];
}

$all_products = [];
$product_res = $conn->query("SELECT * FROM products WHERE stock > 0 ORDER BY category, name");
if ($product_res) {
    while ($row = $product_res->fetch_assoc()) {
        $all_products[] = $row;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <link rel="icon" type="image/png" href="Logo_Brand.png">
    <title>Cafe Emmanuel - A Symphony of Taste</title>
    
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css" />
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:ital,wght@0,500;0,700;1,500&family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    
    <style>
        /* ================== ROOT VARIABLES ================== */
        :root {
            /* Rich Coffee Brown Theme */
            --primary: #A05E44;       /* Warm Caramel / Cinnamon */
            --primary-hover: #804832;
            --primary-glow: rgba(160, 94, 68, 0.4);
            
            --secondary: #2C1E16;     /* Deep Espresso */
            --accent: #D4A373;        /* Warm Latte */
            
            --bg-main: #F8F4EE;       /* Creamy Latte Foam */
            --bg-card: #FFFFFF;
            
            --text-dark: #3A2B24;
            --text-muted: #756358;
            
            --font-heading: 'Playfair Display', serif;
            --font-body: 'Poppins', sans-serif;
            
            --nav-height-top: 140px;      /* Very large top nav */
            --nav-height-scrolled: 80px;  /* Compact scrolled nav */
            
            --radius-lg: 24px;
            --radius-md: 16px;
            --radius-sm: 8px;
            
            --shadow-soft: 0 10px 40px rgba(44, 30, 22, 0.06);
            --shadow-hover: 0 20px 40px rgba(160, 94, 68, 0.15);
        }

        /* ================== GLOBAL STYLES ================== */
        * { margin: 0; padding: 0; box-sizing: border-box; }
        html { scroll-behavior: smooth; scroll-padding-top: var(--nav-height-scrolled); }
        body { font-family: var(--font-body); color: var(--text-dark); background-color: var(--bg-main); line-height: 1.7; overflow-x: hidden; }
        .container { max-width: 1280px; margin: 0 auto; padding: 0 24px; position: relative; z-index: 2; }
        a { text-decoration: none; color: inherit; transition: all 0.3s ease; }
        ul { list-style: none; }
        
        /* Smooth Reveal Animation */
        .reveal { opacity: 0; transform: translateY(40px); transition: all 0.8s cubic-bezier(0.5, 0, 0, 1); }
        .reveal.active { opacity: 1; transform: translateY(0); }

        /* Buttons */
        .btn { display: inline-flex; align-items: center; justify-content: center; padding: 14px 32px; border-radius: 50px; font-weight: 600; font-size: 1rem; cursor: pointer; transition: all 0.4s cubic-bezier(0.4, 0, 0.2, 1); border: none; gap: 8px; position: relative; overflow: hidden; z-index: 1; }
        .btn::before { content: ''; position: absolute; top: 0; left: -100%; width: 50%; height: 100%; background: linear-gradient(to right, rgba(255,255,255,0) 0%, rgba(255,255,255,0.2) 50%, rgba(255,255,255,0) 100%); transform: skewX(-25deg); transition: all 0.5s; z-index: -1; }
        .btn:hover::before { left: 200%; }
        .btn-primary { background: linear-gradient(135deg, var(--primary), #D4A373); color: #fff; box-shadow: 0 8px 25px var(--primary-glow); }
        .btn-primary:hover { transform: translateY(-3px); box-shadow: 0 12px 30px var(--primary-glow); }
        .btn-outline { background: transparent; color: #fff; border: 2px solid rgba(255,255,255,0.8); }
        .btn-outline:hover { background: #fff; color: var(--secondary); border-color: #fff; }

        /* ================== NAVBAR (Glassmorphism & Auto Resize) ================== */
        .header { position: fixed; width: 100%; top: 0; z-index: 1000; height: var(--nav-height-top); transition: all 0.5s cubic-bezier(0.4, 0, 0.2, 1); background: transparent; display: flex; align-items: center; }
        .header.scrolled { height: var(--nav-height-scrolled); background: rgba(255, 255, 255, 0.95); backdrop-filter: blur(20px); box-shadow: 0 4px 30px rgba(44,30,22,0.1); border-bottom: 1px solid rgba(160,94,68,0.1); }
        .header.scrolled .nav-link, .header.scrolled .nav-icon-btn { color: var(--secondary); }
        .header.scrolled .bar { background-color: var(--secondary); }
        
        .navbar { display: flex; justify-content: space-between; align-items: center; height: 100%; width: 100%; }
        
        /* BIG Auto Sizing Logo */
        .nav-logo { display: flex; align-items: center; }
        .nav-logo img { height: 130px; object-fit: contain; filter: drop-shadow(0px 6px 12px rgba(0,0,0,0.5)); transition: all 0.5s cubic-bezier(0.4, 0, 0.2, 1); transform-origin: left center; }
        .header.scrolled .nav-logo img { height: 60px; filter: drop-shadow(0px 2px 4px rgba(0,0,0,0.1)); }
        .nav-logo:hover img { transform: scale(1.05) rotate(-2deg); }
        
        .nav-menu { display: flex; gap: 3rem; }
        .nav-link { font-size: 15px; font-weight: 500; color: #fff; position: relative; letter-spacing: 0.5px; transition: color 0.3s; text-transform: uppercase; }
        .nav-link::after { content: ''; position: absolute; width: 6px; height: 6px; bottom: -10px; left: 50%; transform: translateX(-50%) scale(0); background-color: var(--primary); border-radius: 50%; transition: transform 0.3s cubic-bezier(0.4, 0, 0.2, 1); }
        .nav-link:hover::after, .nav-link.active::after { transform: translateX(-50%) scale(1); }
        .nav-link:hover, .nav-link.active { color: var(--primary) !important; }

        .nav-right-cluster { display: flex; align-items: center; gap: 1.2rem; }
        .nav-icon-btn { width: 45px; height: 45px; display: flex; align-items: center; justify-content: center; border-radius: 50%; background: rgba(255,255,255,0.15); color: #fff; font-size: 1.1rem; transition: all 0.3s ease; border: 1px solid rgba(255,255,255,0.1); backdrop-filter: blur(5px); }
        .header.scrolled .nav-icon-btn { background: var(--bg-main); border-color: #E6DCD3; }
        .nav-icon-btn:hover { background: var(--primary); color: #fff !important; transform: translateY(-3px); box-shadow: 0 5px 15px var(--primary-glow); }
        
        .user-avatar { width: 45px; height: 45px; border-radius: 50%; object-fit: cover; border: 2px solid var(--primary); transition: transform 0.3s; background: #fff; cursor: pointer; }
        .profile-dropdown { position: relative; display: flex; align-items: center;}
        .profile-dropdown::after { content: ''; position: absolute; top: 100%; left: 0; width: 100%; height: 30px; }
        .profile-menu { opacity: 0; visibility: hidden; transform: translateY(15px); position: absolute; right: 0; top: 130%; background: rgba(255,255,255,0.98); backdrop-filter: blur(10px); min-width: 220px; border-radius: var(--radius-md); box-shadow: 0 15px 35px rgba(0,0,0,0.15); overflow: hidden; z-index: 1001; transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1); border: 1px solid var(--accent); }
        .profile-dropdown:hover .profile-menu { opacity: 1; visibility: visible; transform: translateY(0); }
        .profile-menu a { display: flex; align-items: center; gap: 12px; padding: 14px 20px; color: var(--text-dark); font-size: 0.95rem; border-bottom: 1px solid var(--bg-main); transition: 0.3s; }
        .profile-menu a:hover { background: var(--primary); color: #fff; padding-left: 25px; }

        .hamburger { display: none; cursor: pointer; z-index: 1002; }
        .bar { display: block; width: 28px; height: 3px; margin: 6px auto; background-color: #fff; transition: 0.4s cubic-bezier(0.68, -0.55, 0.265, 1.55); border-radius: 3px; }

        /* ================== HERO SECTION ================== */
        .hero-section { position: relative; height: 100vh; width: 100%; overflow: hidden; display: flex; align-items: center; background: var(--secondary); }
        .hero-bg-layer { position: absolute; top: 0; left: 0; width: 100%; height: 120%; z-index: 0; }
        
        /* Slides */
        .hero-slide { position: absolute; top: 0; left: 0; width: 100%; height: 100%; background-size: cover; background-position: center; opacity: 0; transition: opacity 1.5s ease-in-out; }
        .hero-slide.active { opacity: 1; z-index: 1; }
        video.hero-slide { object-fit: cover; }
        
        .hero-overlay { position: absolute; top: 0; left: 0; width: 100%; height: 100%; background: linear-gradient(90deg, rgba(44,30,22,0.95) 0%, rgba(44,30,22,0.5) 50%, rgba(44,30,22,0.1) 100%); z-index: 2; }
        
        .hero-content-wrapper { position: relative; z-index: 4; display: flex; align-items: center; width: 100%; height: 100%; }
        .hero-text-item { display: none; max-width: 750px; text-align: left; }
        .hero-text-item.active { display: block; }
        
        /* Removed text-transform: uppercase here so it won't be in all capital letters */
        .hero-badge { display: inline-block; padding: 6px 16px; background: rgba(212, 163, 115, 0.15); border: 1px solid var(--accent); color: var(--accent); border-radius: 30px; font-size: 0.85rem; font-weight: 600; margin-bottom: 20px; letter-spacing: 1px; text-transform: none; }
        
        .typing-container h1 { font-family: var(--font-heading); font-size: 5.5rem; line-height: 1.1; margin-bottom: 1.5rem; color: #fff; }
        .typing-container h1 span.highlight { color: var(--accent); position: relative; }
        
        .hero-text-item p { font-size: 1.25rem; margin-bottom: 2.5rem; color: #EAE0D5; font-weight: 300; line-height: 1.8; max-width: 600px; }
        .hero-actions { display: flex; gap: 1rem; }

        /* ================== FLOATING CUPS ANIMATIONS ================== */
        @keyframes floatCup { 
            0% { transform: translateY(0) rotate(0deg) scale(1); } 
            50% { transform: translateY(-50px) rotate(15deg) scale(1.1); } 
            100% { transform: translateY(0) rotate(0deg) scale(1); } 
        }

        .anim-cup { position: absolute; opacity: 0.08; z-index: 1; pointer-events: none; animation: floatCup 15s infinite ease-in-out; filter: blur(2px); color: var(--primary); }

        /* Other Section Cups */
        .anim-cup.c1 { top: 10%; left: -2%; font-size: 200px; animation-duration: 20s; }
        .anim-cup.c2 { bottom: 20%; right: -3%; font-size: 250px; animation-delay: -5s; animation-direction: reverse; color: var(--accent); }
        .anim-cup.c3 { top: 40%; left: 85%; font-size: 150px; animation-duration: 18s; animation-delay: -2s; }
        .anim-cup.c4 { bottom: 5%; left: 5%; font-size: 220px; animation-duration: 25s; }
        .anim-cup.c5 { top: 50%; right: 40%; font-size: 140px; animation-duration: 28s; animation-delay: -8s; color: var(--secondary); opacity: 0.05; }
        .anim-cup.c6 { top: 5%; right: 15%; font-size: 100px; animation-duration: 16s; }

        /* ================== SECTION GLOBALS ================== */
        .section-padding { padding: 8rem 0; position: relative; overflow: hidden; }
        .section-header { text-align: center; margin-bottom: 4rem; position: relative; z-index: 2; }
        .section-tag { display: inline-block; color: var(--primary); font-weight: 600; font-size: 0.9rem; text-transform: uppercase; letter-spacing: 2px; margin-bottom: 10px; }
        .section-title { font-family: var(--font-heading); font-size: 3.2rem; color: var(--secondary); margin-bottom: 1rem; }
        .section-subtitle { color: var(--text-muted); max-width: 600px; margin: 0 auto; font-size: 1.1rem; }

        /* ================== MENU SECTION ================== */
        .menu-section { 
            background-image: linear-gradient(rgba(248, 244, 238, 0.96), rgba(248, 244, 238, 0.96)), url('Cover-Photo.jpg');
            background-attachment: fixed;
            background-size: cover;
            background-position: center;
        }
        .menu-filters { display: flex; flex-wrap: wrap; justify-content: center; gap: 1rem; margin-bottom: 4rem; position: relative; z-index: 2;}
        .filter-btn { background: var(--bg-card); border: 1px solid #E6DCD3; padding: 10px 24px; border-radius: 50px; font-weight: 500; color: var(--text-muted); cursor: pointer; transition: all 0.3s; box-shadow: var(--shadow-soft); font-size: 0.95rem; }
        .filter-btn:hover { color: var(--primary); border-color: var(--primary); transform: translateY(-2px); }
        .filter-btn.active { background: var(--primary); color: #fff; border-color: var(--primary); box-shadow: 0 8px 20px var(--primary-glow); }
        
        .menu-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(290px, 1fr)); gap: 2.5rem; position: relative; min-height: 400px; z-index: 2;}
        
        .menu-card { background: var(--bg-card); border-radius: var(--radius-lg); padding: 1rem; box-shadow: var(--shadow-soft); transition: all 0.4s cubic-bezier(0.175, 0.885, 0.32, 1.275); display: flex; flex-direction: column; cursor: pointer; border: 1px solid rgba(160, 94, 68, 0.05); }
        
        .menu-card.hide { opacity: 0; transform: scale(0.8); pointer-events: none; position: absolute; }
        .menu-card.show { opacity: 1; transform: scale(1); position: relative; }
        
        .menu-card:hover { transform: translateY(-12px); box-shadow: var(--shadow-hover); border-color: rgba(160, 94, 68, 0.3); }
        .card-img-wrapper { height: 220px; border-radius: var(--radius-md); overflow: hidden; position: relative; margin-bottom: 1.5rem; }
        .card-img-wrapper img { width: 100%; height: 100%; object-fit: cover; transition: transform 0.6s cubic-bezier(0.4, 0, 0.2, 1); }
        .menu-card:hover .card-img-wrapper img { transform: scale(1.1) rotate(2deg); }
        
        .card-cat-badge { position: absolute; top: 12px; left: 12px; background: rgba(255,255,255,0.95); backdrop-filter: blur(5px); color: var(--primary); padding: 5px 14px; border-radius: 20px; font-size: 0.75rem; font-weight: 700; text-transform: uppercase; letter-spacing: 1px; z-index: 2; box-shadow: 0 4px 10px rgba(0,0,0,0.1); border: 1px solid rgba(160,94,68,0.2); }
        
        .card-content { flex-grow: 1; display: flex; flex-direction: column; padding: 0 0.5rem; }
        .card-title { font-family: var(--font-heading); font-size: 1.35rem; font-weight: 700; color: var(--secondary); margin-bottom: 1rem; line-height: 1.3; }
        
        .size-selector { width: 100%; padding: 12px 16px; margin-bottom: 15px; border: 1px solid #E6DCD3; border-radius: var(--radius-sm); font-size: 0.9rem; background: var(--bg-main); color: var(--text-dark); cursor: pointer; transition: 0.3s; appearance: none; background-image: url("data:image/svg+xml;charset=US-ASCII,%3Csvg%20xmlns%3D%22http%3A%2F%2Fwww.w3.org%2F2000%2Fsvg%22%20width%3D%22292.4%22%20height%3D%22292.4%22%3E%3Cpath%20fill%3D%22%23A05E44%22%20d%3D%22M287%2069.4a17.6%2017.6%200%200%200-13-5.4H18.4c-5%200-9.3%201.8-12.9%205.4A17.6%2017.6%200%200%200%200%2082.2c0%205%201.8%209.3%205.4%2012.9l128%20127.9c3.6%203.6%207.8%205.4%2012.8%205.4s9.2-1.8%2012.8-5.4L287%2095c3.5-3.5%205.4-7.8%205.4-12.8%200-5-1.9-9.2-5.5-12.8z%22%2F%3E%3C%2Fsvg%3E"); background-repeat: no-repeat; background-position: right 1rem top 50%; background-size: 0.65rem auto; }
        .size-selector:focus { border-color: var(--primary); outline: none; box-shadow: 0 0 0 4px var(--primary-glow); background-color: #fff; }
        
        .card-footer { display: flex; justify-content: space-between; align-items: center; margin-top: auto; padding-top: 1rem; border-top: 1px dashed #E6DCD3; }
        .card-price { font-size: 1.4rem; font-weight: 700; color: var(--primary); font-family: var(--font-heading); }
        .add-cart-btn { background: var(--primary); color: #fff; width: 40px; height: 40px; border-radius: 50%; display: flex; align-items: center; justify-content: center; transition: all 0.3s; border: none; font-size: 1.1rem; cursor: pointer; box-shadow: 0 4px 10px var(--primary-glow); }
        .menu-card:hover .add-cart-btn { transform: scale(1.1) rotate(90deg); background: var(--secondary); }

        @keyframes pulseBtn { 0% { box-shadow: 0 0 0 0 rgba(160, 94, 68, 0.4); } 70% { box-shadow: 0 0 0 15px rgba(160, 94, 68, 0); } 100% { box-shadow: 0 0 0 0 rgba(160, 94, 68, 0); } }
        #showMoreBtn { animation: pulseBtn 2s infinite; }

        /* ================== ABOUT SECTION ================== */
        .about-section { 
            background-image: linear-gradient(135deg, rgba(248, 244, 238, 0.94) 0%, rgba(239, 229, 217, 0.94) 100%), url('Cover-Photo.jpg');
            background-attachment: fixed;
            background-size: cover;
            background-position: center;
        }
        .shape { position: absolute; border-radius: 50%; filter: blur(60px); z-index: 0; animation: floatShape 15s infinite alternate; pointer-events: none; }
        .shape-1 { width: 350px; height: 350px; background: rgba(160, 94, 68, 0.15); top: -50px; left: -100px; }
        .shape-2 { width: 450px; height: 450px; background: rgba(212, 163, 115, 0.2); bottom: -100px; right: -100px; animation-delay: -5s; }
        @keyframes floatShape { 0% { transform: translate(0, 0) scale(1); } 100% { transform: translate(50px, 50px) scale(1.1); } }
        
        .about-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 4rem; align-items: center; position: relative; z-index: 1; }
        .about-img-group { position: relative; }
        .about-img-main { width: 90%; border-radius: var(--radius-lg); box-shadow: 0 20px 50px rgba(44,30,22,0.15); border: 4px solid #fff; }
        .about-experience-badge { position: absolute; bottom: 10%; right: 0; background: var(--bg-card); padding: 1.5rem; border-radius: var(--radius-md); box-shadow: 0 15px 35px rgba(44,30,22,0.15); display: flex; align-items: center; gap: 15px; animation: float 6s infinite ease-in-out; border: 1px solid rgba(160,94,68,0.2); }
        .exp-num { font-family: var(--font-heading); font-size: 3rem; color: var(--primary); font-weight: 700; line-height: 1; }
        .exp-text { font-size: 0.9rem; color: var(--text-muted); font-weight: 500; line-height: 1.2; text-transform: uppercase; letter-spacing: 1px; }
        
        .about-text h2 { font-family: var(--font-heading); font-size: 3rem; color: var(--secondary); margin-bottom: 1.5rem; line-height: 1.2; }
        .about-text p { color: var(--text-muted); font-size: 1.05rem; margin-bottom: 1.5rem; }
        
        .stats-row { display: flex; gap: 3rem; margin-top: 2rem; padding-top: 2rem; border-top: 1px solid rgba(160, 94, 68, 0.2); }
        .stat-item h3 { font-family: var(--font-heading); font-size: 2.5rem; color: var(--secondary); margin-bottom: 5px; }
        .stat-item p { color: var(--primary); font-weight: 600; font-size: 0.9rem; text-transform: uppercase; letter-spacing: 1px; margin: 0; }

        /* ================== CONTACT SECTION ================== */
        .contact-section { 
            background-image: linear-gradient(135deg, rgba(239, 229, 217, 0.94) 0%, rgba(230, 220, 211, 0.94) 100%), url('Cover-Photo.jpg');
            background-attachment: fixed;
            background-size: cover;
            background-position: center;
        }
        .shape-3 { width: 300px; height: 300px; background: rgba(44, 30, 22, 0.08); top: 20%; right: 5%; animation-delay: -2s; }
        .shape-4 { width: 400px; height: 400px; background: rgba(160, 94, 68, 0.1); bottom: 10%; left: -5%; animation-delay: -7s; }

        .contact-wrapper { display: grid; grid-template-columns: 1fr 1.5fr; background: var(--bg-card); border-radius: var(--radius-lg); box-shadow: var(--shadow-soft); overflow: hidden; margin-bottom: 2rem; position: relative; z-index: 2; border: 1px solid rgba(255,255,255,0.5); }
        
        /* 3D Tilt Card Panel */
        .contact-info-panel { background: linear-gradient(135deg, var(--secondary), #1A120D); color: #fff; padding: 4rem 3rem; position: relative; overflow: hidden; transform-style: preserve-3d; perspective: 1000px; }
        .contact-info-panel::after { content: ''; position: absolute; width: 300px; height: 300px; background: rgba(160, 94, 68, 0.3); filter: blur(60px); top: -50px; right: -50px; border-radius: 50%; pointer-events: none;}
        
        .info-content { transform: translateZ(30px); position: relative; z-index: 1; }
        .info-content h3 { font-family: var(--font-heading); font-size: 2.2rem; margin-bottom: 2.5rem; color: var(--accent); }
        
        .info-item { display: flex; gap: 1.5rem; margin-bottom: 2rem; align-items: center; }
        .info-icon { width: 50px; height: 50px; background: rgba(255,255,255,0.05); border: 1px solid rgba(255,255,255,0.1); border-radius: 50%; display: flex; align-items: center; justify-content: center; color: var(--accent); font-size: 1.2rem; transition: 0.4s; backdrop-filter: blur(4px); }
        .info-item:hover .info-icon { background: var(--primary); color: #fff; transform: scale(1.1) rotate(10deg); box-shadow: 0 0 20px var(--primary-glow); border-color:var(--primary); }
        .info-text h4 { font-size: 1.1rem; margin-bottom: 0.2rem; font-weight: 500; }
        .info-text p { color: rgba(255,255,255,0.7); font-size: 0.95rem; margin: 0; }

        /* Dynamic Form */
        .contact-form-panel { padding: 4rem 3rem; background: var(--bg-card); }
        .form-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 2rem 1.5rem; margin-top: 1rem; }
        .form-group { position: relative; }
        .form-group.full { grid-column: 1 / -1; }
        
        .form-control { width: 100%; padding: 16px 20px; border: 2px solid #E6DCD3; border-radius: var(--radius-sm); font-family: var(--font-body); font-size: 1rem; background: transparent; transition: all 0.3s; color: var(--text-dark); }
        .form-label { position: absolute; left: 20px; top: 18px; color: var(--text-muted); transition: all 0.3s ease; pointer-events: none; background: var(--bg-card); padding: 0 5px; }
        .form-control:focus, .form-control:not(:placeholder-shown) { border-color: var(--primary); outline: none; }
        .form-control:focus ~ .form-label, .form-control:not(:placeholder-shown) ~ .form-label { top: -10px; left: 15px; font-size: 0.85rem; color: var(--primary); font-weight: 500; }
        textarea.form-control { min-height: 150px; resize: vertical; }

        /* Map Embed */
        .map-wrapper { 
            border-radius: var(--radius-lg); 
            overflow: hidden; 
            box-shadow: var(--shadow-soft); 
            height: 400px; 
            position: relative; 
            z-index: 2; 
            border: 1px solid rgba(255,255,255,0.5); 
            margin-bottom: 2rem;
            background: var(--bg-card);
        }

        /* ================== FOOTER ================== */
        .footer { background-color: #1A120D; color: rgba(255,255,255,0.7); padding-top: 5rem; position: relative; overflow: hidden; }
        .footer::before { content: ''; position: absolute; top: 0; left: 0; width: 100%; height: 5px; background: linear-gradient(90deg, var(--primary), var(--accent)); }
        .footer-content { display: grid; grid-template-columns: 2fr 1fr 1fr; gap: 4rem; padding-bottom: 3rem; border-bottom: 1px solid rgba(255,255,255,0.05); }
        .footer-brand img { height: 100px; margin-bottom: 1.5rem; object-fit: contain; } /* Removed the filter that caused it to vanish */
        .socials { display: flex; gap: 15px; margin-top: 2rem; }
        .social-link { width: 40px; height: 40px; border-radius: 50%; background: rgba(255,255,255,0.05); display: flex; align-items: center; justify-content: center; color: #fff; transition: 0.3s; }
        .social-link:hover { background: var(--primary); transform: translateY(-3px); }
        .footer-col h4 { color: #fff; font-size: 1.2rem; margin-bottom: 1.5rem; font-family: var(--font-heading); letter-spacing: 0.5px; }
        .footer-links li { margin-bottom: 1rem; }
        .footer-links a:hover { color: var(--accent); padding-left: 5px; }

        /* ================== MODALS ================== */
        .modal-overlay { display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.6); z-index: 2000; align-items: center; justify-content: center; backdrop-filter: blur(10px); opacity: 0; transition: opacity 0.3s ease; }
        .modal-overlay.show { opacity: 1; }
        .modal-box { background: rgba(255, 255, 255, 0.98); padding: 40px; border-radius: var(--radius-lg); width: 90%; max-width: 450px; position: relative; box-shadow: 0 30px 60px rgba(0,0,0,0.3); transform: translateY(30px) scale(0.95); transition: all 0.4s cubic-bezier(0.16, 1, 0.3, 1); border: 1px solid var(--accent); }
        .modal-overlay.show .modal-box { transform: translateY(0) scale(1); }
        .modal-close { position: absolute; top: 20px; right: 25px; font-size: 1.8rem; color: var(--text-muted); cursor: pointer; transition: 0.2s; line-height: 1; }
        .modal-close:hover { color: var(--primary); transform: rotate(90deg); }
        .modal-title { text-align: center; font-family: var(--font-heading); font-size: 2.2rem; margin-bottom: 2rem; color: var(--secondary); }
        
        .input-group { position: relative; margin-bottom: 20px; }
        .input-icon { position: absolute; left: 20px; top: 50%; transform: translateY(-50%); color: #aaa; font-size: 1.1rem; z-index: 2; transition: 0.3s; }
        .input-field { width: 100%; padding: 16px 20px 16px 50px; border: 2px solid #E6DCD3; border-radius: var(--radius-sm); font-family: var(--font-body); font-size: 1rem; background: #fff; transition: 0.3s; }
        .input-field:focus { border-color: var(--primary); box-shadow: 0 0 0 4px var(--primary-glow); outline: none; }
        .input-field:focus + .input-icon { color: var(--primary); }

        /* ================== RESPONSIVE DESIGN ================== */
        @media (max-width: 992px) { 
            .about-grid, .contact-wrapper, .footer-content { grid-template-columns: 1fr; } 
            .about-img-group { order: -1; margin-bottom: 2rem; }
            .typing-container h1 { font-size: 4rem; } 
            .nav-logo img { height: 90px; }
            .header { --nav-height-top: 110px; }
        }

        @media (max-width: 768px) { 
            /* 1. Hide ONLY the text links in the center */
            .nav-menu { display: none; } 
            
            /* 2. FORCE the right cluster (Cart, Profile, Login) to stay visible */
            .nav-right-cluster { 
                display: flex !important; 
                gap: 12px; 
                align-items: center; 
            }
            
            /* 3. Scale down the icons and buttons slightly so they fit next to the logo and hamburger */
            .nav-icon-btn { width: 38px; height: 38px; font-size: 0.95rem; }
            .user-avatar { width: 38px; height: 38px; }
            .nav-right-cluster .btn-primary { padding: 8px 16px; font-size: 0.85rem; }

            /* 4. Show the hamburger menu with a little spacing */
            .hamburger { display: block; margin-left: 10px; } 

            /* 5. Dropdown mobile menu styling */
            .nav-menu.active { 
                display: flex; 
                flex-direction: column; 
                position: absolute; 
                top: var(--nav-height-scrolled); 
                left: 0; 
                width: 100%; 
                background: var(--bg-card); 
                padding: 2rem; 
                text-align: center; 
                box-shadow: 0 10px 30px rgba(0,0,0,0.1); 
            } 
            .nav-menu.active .nav-link { color: var(--secondary) !important; font-size: 1.2rem; }
            
            /* Hide the duplicate mobile links inside the hamburger since the real icons are now visible */
            .mobile-link { display: none !important; } 

            /* Other Section Adjustments */
            .form-grid { grid-template-columns: 1fr; }
            .stats-row { flex-direction: column; gap: 1.5rem; text-align: center; }
            .about-experience-badge { position: relative; width: max-content; margin: -30px auto 0; }
            .anim-cup { display: none; } 
            .typing-container h1 { font-size: 3rem; }
            .map-wrapper { height: 300px; }
        }

        /* Extra small devices (Phones) */
        @media (max-width: 480px) {
            .nav-logo img { height: 75px; }
            .header.scrolled .nav-logo img { height: 50px; }
            
            /* Make cluster items even smaller to prevent wrapping on tiny screens */
            .nav-right-cluster { gap: 8px; }
            .nav-icon-btn { width: 34px; height: 34px; font-size: 0.85rem; }
            .user-avatar { width: 34px; height: 34px; }
            .nav-right-cluster .btn-primary { padding: 6px 12px; font-size: 0.8rem; }
            
            .typing-container h1 { font-size: 2.3rem; }
            .section-title { font-size: 2.5rem; }
            .hero-text-item p { font-size: 1rem; }
        }
    </style>
</head>
<body>

    <header class="header" id="navbar">
        <nav class="navbar container">
            <a href="#home" class="nav-logo">
                <img src="Logo_Brand.png" alt="Cafe Emmanuel Logo">
            </a>
            <ul class="nav-menu">
                <li><a href="#home" class="nav-link active">Home</a></li>
                <li><a href="#menu" class="nav-link">Menu</a></li>
                <li><a href="#about" class="nav-link">About</a></li>
                <li><a href="#contact" class="nav-link">Contact</a></li>
                <?php if (isset($_SESSION['user_id'])): ?>
                    <li><a href="my_orders.php" class="nav-link">My Orders</a></li>
                    <li class="mobile-link" style="display:none;"><a href="logout.php" class="nav-link" style="color:var(--primary)!important;">Logout</a></li>
                <?php else: ?>
                    <li class="mobile-link" style="display:none;"><a href="#" onclick="openModal('loginModal')" class="nav-link" style="color:var(--primary)!important;">Login</a></li>
                <?php endif; ?>
            </ul>
            <div class="nav-right-cluster">
                <a href="cart.php" class="nav-icon-btn" title="View Cart"><i class="fas fa-shopping-cart"></i></a>
                <?php if (isset($_SESSION['user_id'])): ?>
                    <div class="nav-icon-btn" title="Notifications">
                        <?php include 'notification_bell.php'; ?>
                    </div>
                    <div class="profile-dropdown">
                        <?php $profilePic = !empty($_SESSION['profile_pic']) ? $_SESSION['profile_pic'] : 'https://ui-avatars.com/api/?name='.urlencode($_SESSION['fullname']).'&background=A05E44&color=fff'; ?>
                        <img src="<?php echo htmlspecialchars($profilePic); ?>" alt="Profile" class="user-avatar">
                        <div class="profile-menu">
                            <a href="profile.php"><i class="fas fa-user-circle"></i> My Profile</a>
                            <?php if (in_array($_SESSION['role'], ['admin', 'super_admin'])): ?>
                                <a href="Dashboard.php"><i class="fas fa-chart-line"></i> Dashboard</a>
                            <?php endif; ?>
                            <a href="logout.php"><i class="fas fa-sign-out-alt"></i> Log Out</a>
                        </div>
                    </div>
                <?php else: ?>
                    <button class="btn btn-primary" style="padding: 10px 24px; font-size: 0.95rem; box-shadow: none;" onclick="openModal('loginModal')">Sign In</button>
                <?php endif; ?>
            </div>
            <div class="hamburger" id="hamburger">
                <span class="bar"></span>
                <span class="bar"></span>
                <span class="bar"></span>
            </div>
        </nav>
    </header>

    <section id="home" class="hero-section">
        <div class="hero-bg-layer" id="heroBgLayer">
            <?php foreach ($hero_slides as $index => $slide): 
                $isActive = ($index === 0) ? 'active' : ''; 
                $isVideo = ($slide['type'] === 'video');
            ?>
                <?php if ($isVideo): ?>
                    <video class="hero-slide <?php echo $isActive; ?> video-slide" muted playsinline>
                        <source src="<?php echo htmlspecialchars($slide['file_path']); ?>" type="video/mp4">
                    </video>
                <?php else: ?>
                    <div class="hero-slide <?php echo $isActive; ?> image-slide" style="background-image: url('<?php echo htmlspecialchars($slide['file_path']); ?>');"></div>
                <?php endif; ?>
            <?php endforeach; ?>
        </div>

        <div class="hero-overlay"></div>
        
        <div class="hero-content-wrapper container">
            <?php foreach ($hero_slides as $index => $slide): ?>
                <div class="hero-text-item <?php echo $index === 0 ? 'active' : ''; ?>">
                    <span class="hero-badge reveal">Roasting with Art</span>
                    <div class="typing-container reveal" style="transition-delay: 0.1s;">
                        <h1 class="typewriter-text-target">
                            Welcome to <span class="highlight">Cafe Emmanuel</span>
                        </h1>
                    </div>
                    <p class="reveal" style="transition-delay: 0.2s;"><?php echo nl2br(htmlspecialchars($slide['subtext'])); ?></p>
                    <div class="hero-actions reveal" style="transition-delay: 0.3s;">
                        <?php 
                            // Only allow opening the reservation modal if the user is logged in
                            $reserve_action = isset($_SESSION['user_id']) ? "openModal('reservationModal')" : "openModal('loginModal')"; 
                        ?>
                        <button onclick="<?php echo $reserve_action; ?>" class="btn btn-primary">Reserve Now <i class="fas fa-calendar-check"></i></button>
                        <a href="#menu" class="btn btn-outline">View Menu</a>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </section>

    <section id="menu" class="section-padding menu-section">
        <i class="fas fa-coffee anim-cup c1"></i>
        <i class="fas fa-mug-hot anim-cup c2"></i>
        
        <div class="container">
            <div class="section-header reveal">
                <span class="section-tag">Our Menu</span>
                <h2 class="section-title">A Symphony of Taste</h2>
                <p class="section-subtitle">Handpicked favorites from our kitchen to your table, crafted with passion and precision using only the finest ingredients.</p>
            </div>
            
            <div class="menu-filters reveal">
                <button class="filter-btn active" data-filter="all">All</button>
                <button class="filter-btn" data-filter="coffee">Coffee</button>
                <button class="filter-btn" data-filter="non-coffee">Non-Coffee</button>
                <button class="filter-btn" data-filter="food">Food</button>
                <button class="filter-btn" data-filter="breakfast">Breakfast</button>
                <button class="filter-btn" data-filter="pizza">Pizza</button>
            </div>

            <div class="menu-grid" id="menuGrid">
                <?php 
                foreach ($all_products as $product): 
                    $cat = strtolower($product['category']);
                    $filterClass = $cat;
                    if(in_array($cat, ['pizza','katsu','ali di pollo','antipasto','mexican food','waffle','pasta','sandwich','all day breakfast'])) { $filterClass .= ' food'; }
                    if(in_array($cat, ['all day breakfast'])) { $filterClass .= ' breakfast'; }
                    if(in_array($cat, ['classico','over iced','cafe artistry','freddo'])) { $filterClass .= ' coffee'; }
                    if(in_array($cat, ['hot tea','refresher','iced tea','hot choco','smoothies'])) { $filterClass .= ' non-coffee'; }

                    $product_data = htmlspecialchars(json_encode($product), ENT_QUOTES, 'UTF-8');
                    $onclick_action = isset($_SESSION['user_id']) ? "viewProduct(" . $product_data . ")" : "openModal('loginModal')";
                ?>
                <div class="menu-card reveal" data-category="<?php echo htmlspecialchars($filterClass); ?>" onclick="<?php echo $onclick_action; ?>">
                    <div class="card-img-wrapper">
                        <div class="card-cat-badge"><?php echo htmlspecialchars($product['category']); ?></div>
                        <img src="<?php echo htmlspecialchars($product['image']); ?>" alt="<?php echo htmlspecialchars($product['name']); ?>" loading="lazy">
                    </div>
                    <div class="card-content">
                        <h3 class="card-title"><?php echo htmlspecialchars($product['name']); ?></h3>
                        
                        <?php
                            $pid = $product['id'];
                            $size_sql = "SELECT * FROM product_sizes WHERE product_id = $pid ORDER BY price ASC";
                            $size_result = $conn->query($size_sql);

                            if ($size_result && $size_result->num_rows > 0) {
                                echo '<select class="size-selector" onclick="event.stopPropagation();" onchange="updatePrice(this)">';
                                $first = true; $default_price = 0;
                                while($size = $size_result->fetch_assoc()) {
                                    if($first) { $default_price = $size['price']; $first = false; }
                                    echo '<option value="' . $size['size_name'] . '" data-price="' . $size['price'] . '">' . $size['size_name'] . '</option>';
                                }
                                echo '</select>';
                                echo '<div class="card-footer">';
                                echo '<span class="card-price price">₱' . number_format($default_price, 2) . '</span>';
                                echo '<button class="add-cart-btn add-cart" data-price="'.$default_price.'" data-size="Regular"><i class="fas fa-plus"></i></button>';
                                echo '</div>';
                            } else {
                                echo '<div style="flex-grow:1;"></div>'; 
                                echo '<div class="card-footer">';
                                echo '<span class="card-price price">₱' . number_format($product['price'], 2) . '</span>';
                                echo '<button class="add-cart-btn add-cart" data-price="'.$product['price'].'" data-size="Standard"><i class="fas fa-plus"></i></button>';
                                echo '</div>';
                            }
                        ?>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
            
            <div id="showMoreContainer" style="text-align: center; margin-top: 3rem; display: none;">
                <button id="showMoreBtn" class="btn btn-outline" style="color:var(--primary); border-color:var(--primary); font-size: 1.1rem; padding: 12px 40px; background:var(--bg-card);">Show More Menu <i class="fas fa-chevron-down" style="margin-left: 5px;"></i></button>
            </div>
        </div>
    </section>

    <section id="about" class="section-padding about-section">
        <div class="shape shape-1"></div>
        <div class="shape shape-2"></div>
        <i class="fas fa-glass-whiskey anim-cup c3"></i>
        <i class="fas fa-coffee anim-cup c6"></i>
        
        <div class="container">
            <div class="about-grid">
                <div class="about-img-group reveal">
                    <img src="Cover-Photo.jpg" alt="Cafe Barista" class="about-img-main">
                    <div class="about-experience-badge" id="tiltCard">
                        <div class="exp-num counter" data-target="2">0</div>
                        <div class="exp-text">Years of<br>Excellence</div>
                    </div>
                </div>
                
                <div class="about-text reveal">
                    <span class="section-tag">Our Heritage</span>
                    <h2>Brewing Magic Since 2024</h2>
                    <p>Cafe Emmanuel started as a simple dream: to create a space where exceptional coffee meets genuine hospitality. Located in the heart of Pampanga, we wanted to build more than just a coffee shop—we wanted to build a community hub.</p>
                    <p>We blend local artistry with contemporary design to create an atmosphere that feels both modern and timeless. We meticulously source our beans and craft our menu to ensure every visit is an experience worth remembering.</p>
                    
                    <div class="stats-row">
                        <div class="stat-item">
                            <h3 class="counter" data-target="500">0</h3>
                            <p>Daily Cups</p>
                        </div>
                        <div class="stat-item">
                            <h3 class="counter" data-target="30">0</h3>
                            <p>Menu Items</p>
                        </div>
                        <div class="stat-item">
                            <h3 class="counter" data-target="100">0</h3>
                            <p>% Passion</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <section id="contact" class="section-padding contact-section">
        <div class="shape shape-3"></div>
        <div class="shape shape-4"></div>
        <i class="fas fa-coffee anim-cup c4"></i>
        <i class="fas fa-mug-hot anim-cup c5"></i>
        
        <div class="container">
            <div class="section-header reveal">
                <span class="section-tag">Reach Out</span>
                <h2 class="section-title">Let's Connect</h2>
                <p class="section-subtitle">Have a question or want to reserve a table? Drop us a message or visit us in person. We're always happy to welcome you.</p>
            </div>
            
            <div class="contact-wrapper reveal">
                <div class="contact-info-panel" id="contactInfoCard">
                    <div class="info-content">
                        <h3>Contact Information</h3>
                        <div class="info-item">
                            <div class="info-icon"><i class="fas fa-map-marker-alt"></i></div>
                            <div class="info-text">
                                <h4>Location</h4>
                                <p>San Antonio Road, Purok Dayat, San Antonio, Guagua</p>
                            </div>
                        </div>
                        <div class="info-item">
                            <div class="info-icon"><i class="fas fa-phone"></i></div>
                            <div class="info-text">
                                <h4>Phone</h4>
                                <p>0995 100 9209</p>
                            </div>
                        </div>
                        <div class="info-item">
                            <div class="info-icon"><i class="fas fa-envelope"></i></div>
                            <div class="info-text">
                                <h4>Email</h4>
                                <p>emmanuel.cafegallery@gmail.com</p>
                            </div>
                        </div>
                        
                        <div style="background: rgba(255,255,255,0.05); padding: 1.5rem; border-radius: var(--radius-md); margin-top: 3rem; border: 1px solid rgba(255,255,255,0.1); backdrop-filter: blur(5px);">
                            <h4 style="margin-bottom:1rem; color:var(--accent); font-family:var(--font-heading); font-size:1.2rem;"><i class="fas fa-clock"></i> Operating Hours</h4>
                            <div style="display:flex; justify-content:space-between; margin-bottom:0.5rem; border-bottom:1px solid rgba(255,255,255,0.1); padding-bottom:5px;"><span>Mon - Thu</span> <span>10:00 AM - 11:00 PM</span></div>
                            <div style="display:flex; justify-content:space-between; padding-top:5px;"><span>Fri - Sun</span> <span>10:00 AM - 12:00 MN</span></div>
                        </div>
                    </div>
                </div>

                <div class="contact-form-panel">
                    <h3 style="font-family:var(--font-heading); font-size:2rem; margin-bottom: 2rem; color:var(--secondary);">Send a Message</h3>
                    <?php if(isset($_GET['status']) && $_GET['status'] == 'success'): ?>
                        <div style="padding:15px; background:#d4edda; color:#155724; border-radius:var(--radius-sm); margin-bottom:20px; font-weight:500;"><i class="fas fa-check-circle"></i> Message sent successfully!</div>
                    <?php endif; ?>
                    <form action="submit_inquiry.php" method="POST">
                        <div class="form-grid">
                            <div class="form-group">
                                <input type="text" name="fname" id="fname" class="form-control" placeholder=" " required>
                                <label for="fname" class="form-label">First Name</label>
                            </div>
                            <div class="form-group">
                                <input type="text" name="lname" id="lname" class="form-control" placeholder=" " required>
                                <label for="lname" class="form-label">Last Name</label>
                            </div>
                            <div class="form-group full">
                                <input type="email" name="email" id="email" class="form-control" placeholder=" " required>
                                <label for="email" class="form-label">Email Address</label>
                            </div>
                            <div class="form-group full">
                                <textarea name="message" id="message" class="form-control" placeholder=" " required></textarea>
                                <label for="message" class="form-label">How can we help you?</label>
                            </div>
                            <div class="form-group full">
                                <button type="submit" class="btn btn-primary" style="width:100%;">Send Message <i class="fas fa-paper-plane"></i></button>
                            </div>
                        </div>
                    </form>
                </div>
            </div>

            <div class="section-header reveal" style="margin-top: 6rem; margin-bottom: 3rem;">
                <span class="section-tag">Location</span>
                <h2 class="section-title">Find us at</h2>
                <p class="section-subtitle">Get directions to Cafe Emmanuel. We look forward to welcoming you in person!</p>
            </div>
            <div class="map-wrapper reveal">
                <iframe src="https://www.google.com/maps/embed?pb=!1m18!1m12!1m3!1d3854.42442430791!2d120.60583477408767!3d14.96912686794994!2m3!1f0!2f0!3f0!3m2!1i1024!2i768!4f13.1!3m3!1m2!1s0x33965f4dc8345fd9%3A0x1712653ecc8817cf!2sCafe%20Emmanuel!5e0!3m2!1sen!2sph!4v1773121464111!5m2!1sen!2sph" width="100%" height="100%" style="border:0;" allowfullscreen="" loading="lazy" referrerpolicy="no-referrer-when-downgrade"></iframe> 
            </div>

        </div>
    </section>

    <footer class="footer">
        <div class="container">
            <div class="footer-content reveal">
                <div class="footer-brand">
                    <img src="Logo_Brand.png" alt="Cafe Emmanuel">
                    <p>Where every cup tells a story, and every bite is a masterpiece. Join us in celebrating the art of coffee and community.</p>
                    <div class="socials">
                        <a href="https://www.facebook.com/profile.php?id=61574968445731" class="social-link"><i class="fab fa-facebook-f"></i></a>
                        <a href="https://www.instagram.com/cafeemmanuelph/" class="social-link"><i class="fab fa-instagram"></i></a>
                    </div>
                </div>
                <div class="footer-col">
                    <h4>Quick Links</h4>
                    <ul class="footer-links">
                        <li><a href="#home">Home</a></li>
                        <li><a href="#menu">Menu</a></li>
                        <li><a href="#about">About Us</a></li>
                        <li><a href="#contact">Contact</a></li>
                    </ul>
                </div>
                <div class="footer-col">
                    <h4>Find Us</h4>
                    <ul class="footer-links">
                        <li style="display:flex; gap:10px;"><i class="fas fa-map-marker-alt" style="color:var(--primary); margin-top:5px;"></i> San Antonio Road, Purok Dayat, San Antonio, Guagua</li>
                        <li style="display:flex; gap:10px;"><i class="fas fa-phone" style="color:var(--primary); margin-top:5px;"></i> 0995 100 9209</li>
                        <li style="display:flex; gap:10px;"><i class="fas fa-envelope" style="color:var(--primary); margin-top:5px;"></i> emmanuel.cafegallery@gmail.com</li>
                    </ul>
                </div>
            </div>
            <div class="copyright"><p>© 2026 Cafe Emmanuel. Designed with passion. All rights reserved.</p></div>
        </div>
    </footer>

    <div id="loginModal" class="modal-overlay" <?php if ($login_error || $login_success) echo 'style="display:flex; opacity:1;"'; ?>>
        <div class="modal-box">
            <span class="modal-close" onclick="closeModal('loginModal')">×</span>
            <h2 class="modal-title">Welcome Back</h2>
            <?php if ($login_error): ?><p style="color: var(--primary); text-align: center; margin-bottom: 15px; font-weight:500;"><i class="fas fa-exclamation-circle"></i> <?php echo $login_error; ?></p><?php endif; ?>
            <?php if ($login_success): ?><div style="padding:12px; background:#d4edda; color:#155724; border-radius:8px; margin-bottom:15px; font-size:0.9rem; text-align:center; font-weight:500;"><i class="fas fa-check-circle"></i> <?php echo $login_success; ?></div><?php endif; ?>
            
            <form method="POST" action="index.php">
                <div class="input-group">
                    <i class="fas fa-user input-icon"></i>
                    <input type="text" name="identifier" placeholder="Email or Username" class="input-field" required>
                </div>
                <div class="input-group">
                    <i class="fas fa-lock input-icon"></i>
                    <input type="password" name="password" placeholder="Password" class="input-field" required>
                </div>
                <div style="display:flex; justify-content:space-between; margin-bottom:20px; font-size:0.9rem;">
                    <label style="color:var(--text-muted); cursor:pointer;"><input type="checkbox" name="remember" style="margin-right:5px; accent-color:var(--primary);"> Remember me</label>
                    <a href="forgot_password.php" class="link-highlight" style="color:var(--primary); font-weight:600;">Forgot Password?</a>
                </div>
                <button type="submit" name="login" class="btn btn-primary" style="width:100%;">Sign In</button>
            </form>
            <div style="text-align:center; margin-top:20px; font-size:0.95rem; color:var(--text-muted);">
                Don't have an account? <a href="#" onclick="switchModal('loginModal', 'registerModal')" style="color:var(--primary); font-weight:600;">Register Here</a>
            </div>
        </div>
    </div>

    <div id="registerModal" class="modal-overlay" <?php if ($register_error) echo 'style="display:flex; opacity:1;"'; ?>>
        <div class="modal-box" style="max-height: 90vh; overflow-y: auto;">
            <span class="modal-close" onclick="closeModal('registerModal')">×</span>
            <h2 class="modal-title">Join the Family</h2>
            <?php if ($register_error): ?><p style="color: var(--primary); text-align: center; margin-bottom: 15px; font-weight:500;"><i class="fas fa-exclamation-circle"></i> <?php echo $register_error; ?></p><?php endif; ?>
            <form method="POST" action="index.php">
                <div class="input-group"><i class="fas fa-id-card input-icon"></i><input type="text" name="fullname" placeholder="Full Name" class="input-field" required></div>
                <div class="input-group"><i class="fas fa-at input-icon"></i><input type="text" name="username" placeholder="Username" class="input-field" required></div>
                <div class="input-group"><i class="fas fa-envelope input-icon"></i><input type="email" name="email" placeholder="Email Address" class="input-field" required></div>
                <div class="input-group"><i class="fas fa-phone input-icon"></i><input type="tel" name="contact" placeholder="Contact Number" class="input-field"></div>
                <div class="input-group">
                    <i class="fas fa-venus-mars input-icon"></i>
                    <select name="gender" class="input-field" required style="-webkit-appearance: none; cursor:pointer;">
                        <option value="" disabled selected>Select Gender</option>
                        <option value="Male">Male</option>
                        <option value="Female">Female</option>
                    </select>
                </div>
                <div class="input-group"><i class="fas fa-lock input-icon"></i><input type="password" name="password" placeholder="Password" class="input-field" required></div>
                <div class="input-group"><i class="fas fa-check-double input-icon"></i><input type="password" name="confirm_password" placeholder="Confirm Password" class="input-field" required></div>
                <button type="submit" name="register" class="btn btn-primary" style="width:100%;">Create Account</button>
            </form>
            <div style="text-align:center; margin-top:20px; font-size:0.95rem; color:var(--text-muted);">
                Already have an account? <a href="#" onclick="switchModal('registerModal', 'loginModal')" style="color:var(--primary); font-weight:600;">Sign In</a>
            </div>
        </div>
    </div>

    <div id="otpModal" class="modal-overlay" <?php if(isset($_SESSION['show_otp_modal']) && $_SESSION['show_otp_modal']): ?>style="display:flex; opacity:1;"<?php endif; ?>>
        <div class="modal-box">
            <span class="modal-close" onclick="window.location.href='logout.php'">×</span>
            <h2 class="modal-title">Verify Email</h2>
            <p style="text-align:center; color:var(--text-muted); margin-bottom:1.5rem;">Enter the 6-digit code sent to<br><strong style="color:var(--secondary);"><?php echo htmlspecialchars($_SESSION['otp_email'] ?? ''); ?></strong></p>
            <?php if ($otp_error): ?><p style="color: var(--primary); text-align: center; margin-bottom: 10px; font-weight:500;"><?php echo $otp_error; ?></p><?php endif; ?>
            <form method="POST" action="index.php">
                <div class="input-group">
                    <i class="fas fa-key input-icon"></i>
                    <input type="text" name="otp_code" placeholder="------" class="input-field" required maxlength="6" style="text-align:center; letter-spacing:12px; font-size:1.8rem; font-weight:700; font-family:monospace;">
                </div>
                <button type="submit" name="verify_otp" class="btn btn-primary" style="width:100%;">Verify & Continue</button>
            </form>
        </div>
    </div>

    <?php if (isset($_SESSION['user_id'])): ?>
    <div id="reservationModal" class="modal-overlay">
        <div class="modal-box" style="max-height: 90vh; overflow-y: auto;">
            <span class="modal-close" onclick="closeModal('reservationModal')">×</span>
            <h2 class="modal-title">Table Reservation</h2>
            <p style="text-align:center; color:var(--text-muted); margin-bottom:20px;">Book your table with us.</p>
            <form method="POST" action="submit_reservation.php">
                <div class="input-group">
                    <i class="fas fa-user input-icon"></i>
                    <input type="text" name="res_name" placeholder="Full Name" class="input-field" value="<?php echo htmlspecialchars($_SESSION['fullname'] ?? 'Vincent Paul'); ?>" required>
                </div>
                <div class="input-group">
                    <i class="fas fa-envelope input-icon"></i>
                    <input type="email" name="res_email" placeholder="Email Address" class="input-field" value="<?php echo htmlspecialchars($_SESSION['email'] ?? ''); ?>" required>
                </div>
                <div class="input-group">
                    <i class="fas fa-phone input-icon"></i>
                    <input type="tel" name="res_phone" placeholder="Contact Number" class="input-field" required>
                </div>
                <div class="input-group">
                    <i class="fas fa-calendar-alt input-icon"></i>
                    <input type="date" name="res_date" class="input-field" required>
                </div>
                <div class="input-group">
                    <i class="fas fa-clock input-icon"></i>
                    <input type="time" name="res_time" class="input-field" required>
                </div>
                <div class="input-group">
                    <i class="fas fa-users input-icon"></i>
                    <input type="number" name="res_guests" placeholder="Number of Guests" min="1" max="20" class="input-field" required>
                </div>
                <div class="input-group" style="margin-bottom: 0;">
                    <i class="fas fa-comment-dots input-icon" style="top: 25px;"></i>
                    <textarea name="res_notes" placeholder="Special Requests / Notes" class="input-field" style="min-height: 100px; resize: vertical; padding-top: 16px;"></textarea>
                </div>
                <button type="submit" name="reserve_table" class="btn btn-primary" style="width:100%; margin-top: 20px;">Confirm Reservation</button>
            </form>
        </div>
    </div>
    <?php endif; ?>

    <script src="JS/product.js"></script> 
    <script>
        document.addEventListener("DOMContentLoaded", () => {
            
            // --- 1. Navbar Scroll Effect, Logo Auto-Resize & Background Parallax ---
            const header = document.getElementById('navbar');
            const sections = document.querySelectorAll('section');
            const navLinks = document.querySelectorAll('.nav-menu a[href^="#"]');
            const animCups = document.querySelectorAll('.anim-cup');

            window.addEventListener('scroll', () => {
                if (window.scrollY > 80) header.classList.add('scrolled');
                else header.classList.remove('scrolled');

                let current = '';
                sections.forEach(section => {
                    const sectionTop = section.offsetTop;
                    if (pageYOffset >= sectionTop - 200) current = section.getAttribute('id');
                });

                navLinks.forEach(a => {
                    a.classList.remove('active');
                    if (a.getAttribute('href').includes(current)) a.classList.add('active');
                });
                
                // Parallax effect for floating cups
                animCups.forEach((cup, index) => {
                    const speed = (index % 3 + 1) * 0.1;
                    cup.style.transform = `translateY(${window.scrollY * speed}px)`;
                });
            });

            // --- 2. Custom Intersection Observer (Reveal Animation) ---
            const observerOptions = { threshold: 0.15, rootMargin: "0px 0px -50px 0px" };
            const observer = new IntersectionObserver((entries) => {
                entries.forEach(entry => {
                    if (entry.isIntersecting) {
                        entry.target.classList.add('active');
                        if(entry.target.classList.contains('about-text') || entry.target.classList.contains('about-img-group')) {
                            startCounters();
                        }
                        observer.unobserve(entry.target);
                    }
                });
            }, observerOptions);

            document.querySelectorAll('.reveal').forEach(el => observer.observe(el));

            // --- 3. Hero Parallax, Typewriter Effect & Background Slider ---
            const heroBg = document.getElementById('heroBgLayer');
            window.addEventListener('scroll', () => {
                let scrollVal = window.scrollY;
                if (scrollVal < window.innerHeight && heroBg) {
                    heroBg.style.transform = `translateY(${scrollVal * 0.4}px)`;
                }
            });

            const text = "Welcome to Cafe Emmanuel";
            const typeTargets = document.querySelectorAll('.typewriter-text-target');
            
            typeTargets.forEach(target => {
                target.innerHTML = '';
                let i = 0;
                function typeWriter() {
                    if (i < text.length) {
                        if(text.substring(i, i+13) === "Cafe Emmanuel") {
                            target.innerHTML += `<span class="highlight">Cafe Emmanuel</span>`;
                            i += 13;
                        } else {
                            target.innerHTML += text.charAt(i);
                            i++;
                        }
                        setTimeout(typeWriter, 80);
                    }
                }
                setTimeout(typeWriter, 500); 
            });

            // HERO SLIDER LOGIC
            const slides = document.querySelectorAll(".hero-slide");
            const textItems = document.querySelectorAll(".hero-text-item");
            if (slides.length > 1) {
                let currentIndex = 0;
                let slideInterval;
                
                function showSlide(index) {
                    slides.forEach(s => s.classList.remove("active"));
                    textItems.forEach(t => t.classList.remove("active"));
                    
                    slides[index].classList.add("active");
                    if (textItems[index]) textItems[index].classList.add("active");

                    const currentElement = slides[index];
                    clearTimeout(slideInterval);

                    if (currentElement.tagName === 'VIDEO') {
                        currentElement.currentTime = 0;
                        currentElement.muted = true;
                        let playPromise = currentElement.play();
                        
                        if (playPromise !== undefined) {
                            playPromise.then(_ => {
                                currentElement.onended = nextSlide;
                            }).catch(error => {
                                slideInterval = setTimeout(nextSlide, 5000);
                            });
                        }
                    } else {
                        slideInterval = setTimeout(nextSlide, 5000);
                    }
                }
                
                function nextSlide() {
                    currentIndex = (currentIndex + 1) % slides.length;
                    showSlide(currentIndex);
                }
                
                showSlide(0);
            } else if (slides.length === 1 && slides[0].tagName === 'VIDEO') {
                slides[0].loop = true;
                slides[0].play().catch(e => console.log('Autoplay prevented.'));
            }

            // --- 4. Number Counter Animation ---
            let countersStarted = false;
            function startCounters() {
                if(countersStarted) return;
                countersStarted = true;
                const counters = document.querySelectorAll('.counter');
                const speed = 200; 
                counters.forEach(counter => {
                    const updateCount = () => {
                        const target = +counter.getAttribute('data-target');
                        const count = +counter.innerText;
                        const inc = target / speed;
                        if (count < target) {
                            counter.innerText = Math.ceil(count + inc);
                            setTimeout(updateCount, 20);
                        } else {
                            counter.innerText = target + (target > 50 ? '+' : '');
                        }
                    };
                    updateCount();
                });
            }

            // --- 5. Menu Filter & "Show More" Logic ---
            const filterBtns = document.querySelectorAll('.filter-btn');
            const menuCards = document.querySelectorAll('.menu-card');
            const showMoreContainer = document.getElementById('showMoreContainer');
            const showMoreBtn = document.getElementById('showMoreBtn');
            
            let visibleLimit = 6;
            let currentFilter = 'all';

            function updateMenuGrid() {
                let matchedCount = 0;
                let visibleCount = 0;

                menuCards.forEach(card => {
                    const categories = card.getAttribute('data-category');
                    const matches = (currentFilter === 'all' || categories.includes(currentFilter));
                    
                    if (matches) {
                        matchedCount++;
                        if (visibleCount < visibleLimit) {
                            card.style.display = 'flex';
                            setTimeout(() => { card.classList.remove('hide'); card.classList.add('show'); }, 50);
                            visibleCount++;
                        } else {
                            card.classList.remove('show');
                            card.classList.add('hide');
                            setTimeout(() => { if(card.classList.contains('hide')) card.style.display = 'none'; }, 400); 
                        }
                    } else {
                        card.classList.remove('show');
                        card.classList.add('hide');
                        setTimeout(() => { if(card.classList.contains('hide')) card.style.display = 'none'; }, 400); 
                    }
                });

                if (matchedCount > visibleLimit) {
                    showMoreContainer.style.display = 'block';
                } else {
                    showMoreContainer.style.display = 'none';
                }
            }

            updateMenuGrid();

            filterBtns.forEach(btn => {
                btn.addEventListener('click', () => {
                    filterBtns.forEach(b => b.classList.remove('active'));
                    btn.classList.add('active');
                    currentFilter = btn.getAttribute('data-filter');
                    visibleLimit = 6;
                    updateMenuGrid();
                });
            });

            showMoreBtn.addEventListener('click', () => {
                visibleLimit += 6;
                updateMenuGrid();
            });

            // --- 6. 3D Tilt Effect for Contact Info Card ---
            const tiltCard = document.getElementById('contactInfoCard');
            if(tiltCard && window.innerWidth > 992) {
                tiltCard.addEventListener('mousemove', (e) => {
                    const rect = tiltCard.getBoundingClientRect();
                    const x = e.clientX - rect.left;
                    const y = e.clientY - rect.top;
                    const centerX = rect.width / 2;
                    const centerY = rect.height / 2;
                    
                    const rotateX = ((y - centerY) / centerY) * -10;
                    const rotateY = ((x - centerX) / centerX) * 10;
                    
                    tiltCard.style.transform = `perspective(1000px) rotateX(${rotateX}deg) rotateY(${rotateY}deg)`;
                    tiltCard.style.transition = 'none';
                });
                tiltCard.addEventListener('mouseleave', () => {
                    tiltCard.style.transform = `perspective(1000px) rotateX(0deg) rotateY(0deg)`;
                    tiltCard.style.transition = 'transform 0.5s ease';
                });
            }

            // --- 7. Modals & Hamburger Logic ---
            const hamburger = document.getElementById('hamburger');
            const navMenu = document.querySelector('.nav-menu');
            if(hamburger) {
                hamburger.addEventListener('click', () => {
                    navMenu.classList.toggle('active');
                    hamburger.classList.toggle('active');
                });
            }
        });

        // Global Modal Functions
        function openModal(id) { 
            const modal = document.getElementById(id);
            if (modal) {
                modal.style.display = 'flex';
                setTimeout(() => modal.classList.add('show'), 10);
            }
        }
        function closeModal(id) { 
            const modal = document.getElementById(id);
            if (modal) {
                modal.classList.remove('show');
                setTimeout(() => modal.style.display = 'none', 300);
            }
        }
        function switchModal(from, to) { closeModal(from); setTimeout(() => openModal(to), 300); }
        window.onclick = e => { if (e.target.classList.contains('modal-overlay')) closeModal(e.target.id); }

        // Helper for View Product
        function viewProduct(productData) {
            localStorage.setItem('selectedProduct', JSON.stringify(productData));
            window.location.href = 'quantity.php';
        }

        // Helper for Price update in Menu Card
        function updatePrice(selectElem) {
            const selectedOpt = selectElem.options[selectElem.selectedIndex];
            const price = selectedOpt.getAttribute('data-price');
            const cardFooter = selectElem.closest('.card-footer');
            cardFooter.querySelector('.price').innerHTML = '₱' + parseFloat(price).toFixed(2);
            const btn = cardFooter.querySelector('.add-cart-btn');
            if(btn) { btn.setAttribute('data-price', price); btn.setAttribute('data-size', selectedOpt.value); }
        }
    </script>
</body>
</html>