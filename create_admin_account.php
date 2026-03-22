<?php
session_start();
require_once 'config.php';
require_once 'audit_log.php'; 

// --- ACCESS CONTROL ---
// Only super_admin can create admin accounts
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'super_admin') {
    header("Location: user_accounts.php");
    exit;
}

$errors = [];
$success = "";

// --- FORM SUBMISSION HANDLING ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $fullname = trim($_POST['fullname'] ?? '');
    $username = trim($_POST['username'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $contact = trim($_POST['contact'] ?? '');
    $password = $_POST['password'] ?? '';
    $role = $_POST['role'] ?? 'admin';
    
    // 1. Validation
    if (empty($fullname) || !preg_match("/^[a-zA-Z\s]+$/", $fullname)) {
        $errors[] = "Full name must contain only letters and spaces.";
    }
    
    if (empty($username)) {
        $errors[] = "Username is required.";
    }
    
    if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = "Valid email is required.";
    }
    
    if (!empty($contact) && !preg_match("/^[0-9]{10,15}$/", $contact)) {
        $errors[] = "Contact number must be 10-15 digits.";
    }
    
    if (empty($password) || !preg_match("/^(?=.*[A-Z]).{8,}$/", $password)) {
        $errors[] = "Password must be at least 8 characters with 1 capital letter.";
    }
    
    if (!in_array($role, ['admin', 'super_admin'])) {
        $errors[] = "Invalid role selected.";
    }
    
    // 2. Check duplicates
    if (empty($errors)) {
        $stmt = $conn->prepare("SELECT id FROM users WHERE email = ? OR username = ?");
        $stmt->bind_param("ss", $email, $username);
        $stmt->execute();
        $stmt->store_result();
        
        if ($stmt->num_rows > 0) {
            $errors[] = "Email or Username already exists.";
        }
        $stmt->close();
    }
    
    // 3. Insert User
    if (empty($errors)) {
        $hashed_password = password_hash($password, PASSWORD_DEFAULT);
        
        // Ensure contact is handled properly (null if empty)
        $contact_val = empty($contact) ? null : $contact;
        
        $insert_stmt = $conn->prepare("INSERT INTO users (fullname, username, email, contact, password, role) VALUES (?, ?, ?, ?, ?, ?)");
        $insert_stmt->bind_param("ssssss", $fullname, $username, $email, $contact_val, $hashed_password, $role);
        
        if ($insert_stmt->execute()) {
            $new_user_id = $insert_stmt->insert_id;
            
            // Log the action
            if (function_exists('logAdminAction')) {
                logAdminAction(
                    $conn,
                    $_SESSION['user_id'],
                    $_SESSION['fullname'],
                    'admin_created',
                    "Created new {$role} account: {$username}",
                    'users',
                    $new_user_id
                );
            }
            
            // Redirect on success
            header("Location: user_accounts.php?success=admin_created");
            exit;
        } else {
            $errors[] = "Database Error: " . $insert_stmt->error;
        }
        $insert_stmt->close();
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Create Admin Account - Cafe Emmanuel</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@400;500;600;700&family=Poppins:wght@300;400;500;600&display=swap" rel="stylesheet">
    <style>
        :root {
            --primary-red: #E03A3E;
            --secondary-dark: #222222;
            --text-muted: #777777;
            --bg-light: #f8f9fa;
            --card-bg: #FFFFFF;
            --border-color: #eeeeee;
            --font-main: 'Poppins', sans-serif;
            --font-heading: 'Montserrat', sans-serif;
        }

        * { box-sizing: border-box; margin: 0; padding: 0; }

        body {
            font-family: var(--font-main);
            background-color: var(--bg-light);
            color: var(--secondary-dark);
            display: flex;
            min-height: 100vh;
        }

        .main-content { 
            flex-grow: 1;
            margin-left: 260px; /* Width of sidebar */
            padding: 40px;
            width: calc(100% - 260px);
        }

        .header-title {
            margin-bottom: 30px;
        }
        .header-title h1 {
            font-family: var(--font-heading);
            font-weight: 700;
            font-size: 28px;
        }

        .form-card {
            background: var(--card-bg);
            padding: 40px;
            border-radius: 12px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.03);
            border: 1px solid var(--border-color);
            max-width: 800px;
        }

        .form-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
        }

        .form-group {
            margin-bottom: 20px;
        }
        
        .form-group.full-width {
            grid-column: span 2;
        }

        label {
            display: block;
            margin-bottom: 8px;
            font-weight: 500;
            font-size: 14px;
            color: var(--secondary-dark);
        }

        input, select {
            width: 100%;
            padding: 12px 15px;
            border: 1px solid #ddd;
            border-radius: 8px;
            font-family: var(--font-main);
            font-size: 14px;
            transition: 0.3s;
        }

        input:focus, select:focus {
            outline: none;
            border-color: var(--primary-red);
            box-shadow: 0 0 0 3px rgba(224, 58, 62, 0.1);
        }

        .btn-submit {
            background: var(--primary-red);
            color: white;
            padding: 12px 30px;
            border: none;
            border-radius: 8px;
            font-weight: 600;
            cursor: pointer;
            transition: 0.3s;
            font-size: 15px;
        }

        .btn-submit:hover {
            background: #c02d31;
        }

        .btn-cancel {
            background: #f1f1f1;
            color: var(--secondary-dark);
            padding: 12px 30px;
            border: none;
            border-radius: 8px;
            font-weight: 600;
            text-decoration: none;
            margin-right: 10px;
            font-size: 15px;
            display: inline-block;
        }
        .btn-cancel:hover {
            background: #e2e2e2;
        }

        .alert-error {
            background: #fff5f5;
            color: #c53030;
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 25px;
            border: 1px solid #fed7d7;
        }

        @media (max-width: 768px) {
            .main-content { margin-left: 0; width: 100%; }
            .form-grid { grid-template-columns: 1fr; }
            .form-group.full-width { grid-column: span 1; }
        }
    </style>
</head>
<body>

    <?php include 'admin_sidebar.php'; ?>

    <main class="main-content">
        <div class="header-title">
            <h1>Create New Admin</h1>
        </div>

        <div class="form-card">
            <?php if (!empty($errors)): ?>
                <div class="alert-error">
                    <strong>Please fix the following errors:</strong>
                    <ul style="margin-top: 10px; margin-left: 20px;">
                        <?php foreach($errors as $err): ?>
                            <li><?php echo htmlspecialchars($err); ?></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            <?php endif; ?>

            <form action="create_admin_account.php" method="POST">
                <div class="form-grid">
                    <div class="form-group">
                        <label for="fullname">Full Name *</label>
                        <input type="text" id="fullname" name="fullname" value="<?php echo htmlspecialchars($fullname ?? ''); ?>" placeholder="e.g. Juan Dela Cruz" required>
                    </div>

                    <div class="form-group">
                        <label for="role">Role *</label>
                        <select name="role" id="role" required>
                            <option value="admin" <?php if(($role ?? '') === 'admin') echo 'selected'; ?>>Admin</option>
                            <option value="super_admin" <?php if(($role ?? '') === 'super_admin') echo 'selected'; ?>>Super Admin</option>
                        </select>
                    </div>

                    <div class="form-group">
                        <label for="username">Username *</label>
                        <input type="text" id="username" name="username" value="<?php echo htmlspecialchars($username ?? ''); ?>" required>
                    </div>

                    <div class="form-group">
                        <label for="email">Email Address *</label>
                        <input type="email" id="email" name="email" value="<?php echo htmlspecialchars($email ?? ''); ?>" required>
                    </div>

                    <div class="form-group">
                        <label for="contact">Contact Number</label>
                        <input type="text" id="contact" name="contact" value="<?php echo htmlspecialchars($contact ?? ''); ?>" placeholder="e.g. 09123456789">
                    </div>

                    <div class="form-group">
                        <label for="password">Password *</label>
                        <input type="password" id="password" name="password" required placeholder="Min 8 chars, 1 Capital letter">
                    </div>
                </div>

                <div style="margin-top: 30px;">
                    <a href="user_accounts.php" class="btn-cancel">Cancel</a>
                    <button type="submit" class="btn-submit">Create Account</button>
                </div>
            </form>
        </div>
    </main>

</body>
</html>