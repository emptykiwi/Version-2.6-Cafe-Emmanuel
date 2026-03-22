<?php
// create_admin.php
// One-time helper to create an admin account in the `login_system`.`users` table.
// After creating your admin, DELETE this file.

error_reporting(E_ALL);
ini_set('display_errors', '1');

require_once __DIR__ . '/config.php'; // provides $conn

function respond($msg, $ok = false) {
    echo '<div style="max-width:520px;margin:30px auto;font-family:system-ui,Segoe UI,Arial;line-height:1.5">';
    echo '<h2>Create Admin</h2>';
    echo $ok ? '<p style="color:green">' . htmlspecialchars($msg) . '</p>' : '<p style="color:#b00020">' . htmlspecialchars($msg) . '</p>';
}

// Check if an admin already exists (you can still create another)
$adminExists = false;
if ($conn) {
    if ($res = $conn->query("SELECT id FROM users WHERE role='admin' LIMIT 1")) {
        $adminExists = $res->num_rows > 0;
        $res->free();
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $fullname = trim($_POST['fullname'] ?? '');
    $username = trim($_POST['username'] ?? '');
    $email    = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $confirm  = $_POST['confirm_password'] ?? '';

    // Validations mirroring your index.php rules
    if (!preg_match('/^[a-zA-Z\s]+$/', $fullname)) {
        respond('Full name must contain only letters and spaces.');
        exit; 
    }
    if (!filter_var($email, FILTER_VALIDATE_EMAIL) || !preg_match('/@(gmail\.com|email\.com)$/', $email)) {
        respond('Email must be either @gmail.com or @email.com.');
        exit;
    }
    if (!preg_match('/^(?=.*[A-Z]).{8,}$/', $password)) {
        respond('Password must be at least 8 characters and contain at least one capital letter.');
        exit;
    }
    if ($password !== $confirm) {
        respond('Passwords do not match.');
        exit;
    }
    if (!preg_match('/^[A-Za-z0-9_\.\-]{4,}$/', $username)) {
        respond('Username must be at least 4 characters and contain only letters, numbers, underscore, dot, or hyphen.');
        exit;
    }

    // Check duplicates
    $check = $conn->prepare('SELECT id FROM users WHERE email = ? OR username = ? LIMIT 1');
    $check->bind_param('ss', $email, $username);
    $check->execute();
    $check->store_result();
    if ($check->num_rows > 0) {
        $check->close();
        respond('Email or Username already exists.');
        exit;
    }
    $check->close();

    $hash = password_hash($password, PASSWORD_DEFAULT);

    $stmt = $conn->prepare('INSERT INTO users (username, password, fullname, email, role) VALUES (?, ?, ?, ?, "admin")');
    $stmt->bind_param('ssss', $username, $hash, $fullname, $email);
    if ($stmt->execute()) {
        respond('Admin account created. You can now log in.', true);
        echo '<p><a href="index.php?action=login">Go to login</a></p>';
    } else {
        respond('Insert error: ' . $stmt->error);
    }
    $stmt->close();
    echo '</div>';
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Create Admin</title>
</head>
<body style="font-family:system-ui,Segoe UI,Arial; background:#fafafa;">
  <div style="max-width:520px;margin:40px auto;background:#fff;padding:24px;border-radius:10px;box-shadow:0 2px 10px rgba(0,0,0,.06)">
    <h2 style="margin-top:0">Create Admin</h2>
    <?php if ($adminExists): ?>
      <p style="color:#555">An admin already exists. You can still create another admin user below.</p>
    <?php else: ?>
      <p style="color:#0a7">No admin found yet. Create the first admin account.</p>
    <?php endif; ?>
    <form method="POST" autocomplete="off">
      <div style="margin:10px 0">
        <label>Full Name</label><br />
        <input name="fullname" type="text" required minlength="8" style="width:100%;padding:10px" />
      </div>
      <div style="margin:10px 0">
        <label>Username</label><br />
        <input name="username" type="text" required minlength="4" style="width:100%;padding:10px" />
      </div>
      <div style="margin:10px 0">
        <label>Email (gmail.com or email.com)</label><br />
        <input name="email" type="email" required style="width:100%;padding:10px" />
      </div>
      <div style="margin:10px 0">
        <label>Password (min 8, with 1 capital letter)</label><br />
        <input name="password" type="password" required minlength="8" style="width:100%;padding:10px" />
      </div>
      <div style="margin:10px 0">
        <label>Confirm Password</label><br />
        <input name="confirm_password" type="password" required minlength="8" style="width:100%;padding:10px" />
      </div>
      <button type="submit" style="padding:10px 16px;background:#111;border:none;color:#fff;border-radius:6px;cursor:pointer">Create Admin</button>
      <p style="margin-top:8px;color:#888">After creating your admin, delete this file (create_admin.php) for security.</p>
    </form>
  </div>
</body>
</html>
