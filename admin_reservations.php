<?php
try {
    include 'session_check.php';
    include 'db_connect.php';

    // --- ACCESS CONTROL ---
    if (!isset($_SESSION['role']) || !in_array(strtolower(trim($_SESSION['role'])), ['admin', 'super_admin'])) {
        header("Location: index.php");
        exit();
    }

    $local_conn = $conn;

    // --- HANDLE STATUS UPDATES ---
    if (isset($_GET['action']) && isset($_GET['id'])) {
        $id = (int)$_GET['id'];
        $action = $_GET['action'];
        $target_status = ($action === 'confirm') ? 'confirmed' : (($action === 'cancel') ? 'cancelled' : 'pending');

        // Fetch reservation details first for email
        $res_stmt = $local_conn->prepare("SELECT * FROM reservations WHERE id = ?");
        $res_stmt->bind_param("i", $id);
        $res_stmt->execute();
        $res_data = $res_stmt->get_result()->fetch_assoc();

        if ($res_data) {
            require_once 'mailer.php';
            $to = $res_data['res_email'];
            $customerName = $res_data['res_name'];
            $formatted_date = date("F j, Y", strtotime($res_data['res_date']));
            $formatted_time = date("g:i A", strtotime($res_data['res_time']));
            $guests = $res_data['res_guests'];
            
            $email_sent = false;
            if ($target_status === 'confirmed') {
                $subject = "Reservation Confirmed - Cafe Emmanuel";
                $body = "
                <div style='background-color: #F8F4EE; padding: 40px; font-family: \"Poppins\", Arial, sans-serif;'>
                    <div style='max-width: 600px; margin: 0 auto; background: #ffffff; border-radius: 16px; overflow: hidden; border: 1px solid #E6DCD3; box-shadow: 0 10px 30px rgba(44, 30, 22, 0.05);'>
                        <div style='background-color: #2C1E16; padding: 30px; text-align: center;'>
                            <h1 style='color: #D4A373; margin: 0; font-size: 24px; text-transform: uppercase; letter-spacing: 2px;'>Cafe Emmanuel</h1>
                        </div>
                        <div style='padding: 40px; color: #3A2B24; line-height: 1.6;'>
                            <h2 style='color: #2e7d32; margin-top: 0;'>Reservation Confirmed!</h2>
                            <p>Dear <strong>$customerName</strong>,</p>
                            <p>Great news! Your reservation at Cafe Emmanuel has been confirmed. We have a table waiting for you.</p>
                            
                            <div style='background: #FDFBF7; padding: 25px; border-radius: 12px; border: 1px dashed #D4A373; margin: 25px 0;'>
                                <p style='margin: 5px 0;'><strong>Date:</strong> $formatted_date</p>
                                <p style='margin: 5px 0;'><strong>Time:</strong> $formatted_time</p>
                                <p style='margin: 5px 0;'><strong>Guests:</strong> $guests Persons</p>
                                <p style='margin: 5px 0;'><strong>Status:</strong> <span style='color: #2e7d32; font-weight: bold;'>Confirmed</span></p>
                            </div>

                            <p>We look forward to providing you with an exceptional dining experience. If you need to make any changes, please contact us.</p>
                            <hr style='border: none; border-top: 1px solid #E6DCD3; margin: 30px 0;'>
                            <p style='font-size: 13px; color: #756358; text-align: center;'>Guagua, Pampanga, Philippines</p>
                        </div>
                    </div>
                </div>";
                $email_sent = send_email($to, $subject, $body);
            } elseif ($target_status === 'cancelled') {
                $subject = "Update on your Reservation - Cafe Emmanuel";
                $body = "
                <div style='background-color: #F8F4EE; padding: 40px; font-family: \"Poppins\", Arial, sans-serif;'>
                    <div style='max-width: 600px; margin: 0 auto; background: #ffffff; border-radius: 16px; overflow: hidden; border: 1px solid #E6DCD3; box-shadow: 0 10px 30px rgba(44, 30, 22, 0.05);'>
                        <div style='background-color: #2C1E16; padding: 30px; text-align: center;'>
                            <h1 style='color: #D4A373; margin: 0; font-size: 24px; text-transform: uppercase; letter-spacing: 2px;'>Cafe Emmanuel</h1>
                        </div>
                        <div style='padding: 40px; color: #3A2B24; line-height: 1.6;'>
                            <h2 style='color: #c62828; margin-top: 0;'>Reservation Cancelled</h2>
                            <p>Dear <strong>$customerName</strong>,</p>
                            <p>We're sorry, but we are unable to accommodate your reservation request at this time.</p>
                            
                            <div style='background: #FDFBF7; padding: 25px; border-radius: 12px; border: 1px solid #ffebee; margin: 25px 0;'>
                                <p style='margin: 5px 0;'><strong>Date:</strong> $formatted_date</p>
                                <p style='margin: 5px 0;'><strong>Time:</strong> $formatted_time</p>
                                <p style='margin: 5px 0;'><strong>Status:</strong> <span style='color: #c62828; font-weight: bold;'>Cancelled</span></p>
                            </div>

                            <p>This may be due to high demand or private events. We hope you'll try visiting us another time soon!</p>
                            <hr style='border: none; border-top: 1px solid #E6DCD3; margin: 30px 0;'>
                            <p style='font-size: 13px; color: #756358; text-align: center;'>Guagua, Pampanga, Philippines</p>
                        </div>
                    </div>
                </div>";
                $email_sent = send_email($to, $subject, $body);
            } else {
                // For other status changes (like back to pending), we don't send emails here
                $email_sent = true; 
            }

            if ($email_sent) {
                $stmt = $local_conn->prepare("UPDATE reservations SET status = ? WHERE id = ?");
                $stmt->bind_param("si", $target_status, $id);
                if ($stmt->execute()) {
                    // Log audit
                    require_once 'audit_log.php';
                    logAdminAction(
                        $local_conn,
                        $_SESSION['user_id'] ?? 0,
                        $_SESSION['fullname'] ?? 'Admin',
                        'reservation_status_update',
                        "Updated reservation #$id to $target_status",
                        'reservations',
                        $id
                    );

                    // --- CREATE INTERNAL NOTIFICATION ---
                    require_once 'notifications.php';
                    $notif_title = ($target_status === 'confirmed') ? "Reservation Confirmed" : "Reservation Update";
                    $notif_msg = ($target_status === 'confirmed') 
                        ? "Your reservation for " . date("M d, Y", strtotime($res_data['res_date'])) . " at " . date("g:i A", strtotime($res_data['res_time'])) . " has been confirmed!"
                        : "We regret to inform you that your reservation request has been cancelled.";
                    
                    if (function_exists('createNotification')) {
                        createNotification($local_conn, $res_data['user_id'], $id, 'reservation', $notif_title, $notif_msg);
                    }
                }
                header("Location: admin_reservations.php" . (($target_status === 'confirmed') ? "?success=confirmed" : "?success=cancelled"));
                exit();
            } else {
                // Email failed, do not update status and show error
                header("Location: admin_reservations.php?error=email_failed&id=$id");
                exit();
            }
        }
    }

    // --- DATA FOR HEADER ICONS ---
    $unread_inquiries = 0;
    $inquiry_count_result = $local_conn->query("SELECT COUNT(*) as count FROM inquiries WHERE status = 'new'");
    if ($inquiry_count_result) {
        $unread_inquiries = $inquiry_count_result->fetch_assoc()['count'];
    }

    $recent_messages = [];
    $recent_inquiries_result = $local_conn->query("SELECT * FROM inquiries WHERE status = 'new' ORDER BY received_at DESC LIMIT 5");
    if ($recent_inquiries_result) {
        while ($row = $recent_inquiries_result->fetch_assoc()) {
            $recent_messages[] = $row;
        }
    }

    // Fetch Reservations
    $sql = "SELECT * FROM reservations ORDER BY res_date ASC, res_time ASC";
    $result = $local_conn->query($sql);

} catch (Throwable $e) {
    die("Error initializing page: " . htmlspecialchars($e->getMessage()));
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="icon" type="image/png" href="Logo_Brand.png">
    <title>Reservations - Cafe Emmanuel</title>
    
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" />
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:ital,wght@0,500;0,700;1,500&family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    
    <style>
        :root {
            --primary: #A05E44;       
            --secondary: #2C1E16;     
            --accent: #D4A373;        
            --bg-main: #F8F4EE;       
            --bg-card: #FFFFFF;
            --text-dark: #3A2B24;
            --text-muted: #756358;
            --border-color: #E6DCD3;
            --font-heading: 'Playfair Display', serif;
            --font-body: 'Poppins', sans-serif;
            --shadow-soft: 0 10px 40px rgba(44, 30, 22, 0.05);
        }

        * { box-sizing: border-box; margin: 0; padding: 0; }

        body {
            font-family: var(--font-body) !important;
            background-color: var(--bg-main) !important;
            color: var(--text-dark) !important;
            display: flex;
            height: 100vh;
            overflow: hidden;
        }

        .main-content { 
            flex-grow: 1;
            margin-left: 260px;
            width: calc(100% - 260px); 
            height: 100vh;
            overflow-y: auto;
            padding: 40px;
        }

        .main-header { 
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 30px;
            position: relative;
            z-index: 1000;
        }
        
        .main-header h1 { 
            font-family: var(--font-heading) !important; 
            font-size: 32px !important; 
            font-weight: 700 !important; 
            color: var(--secondary) !important;
        }

        .header-icons { display: flex; align-items: center; gap: 20px; }
        
        .icon-container {
            position: relative; cursor: pointer; color: #3a2b24;
            width: 45px; height: 45px; background: var(--bg-card);
            border-radius: 50%; display: flex; align-items: center; justify-content: center;
            border: 1px solid var(--border-color); transition: 0.3s;
            box-shadow: 0 4px 10px rgba(44, 30, 22, 0.05);
        }
        .icon-container i { font-size: 1.25rem; color: var(--secondary); transition: 0.3s; }
        .icon-container:hover { background: var(--primary); border-color: var(--primary); transform: translateY(-3px); }
        .icon-container:hover i { color: #fff; }

        .header-badge {
            position: absolute; top: -5px; right: -5px;
            background-color: var(--accent); color: white; border-radius: 50%;
            width: 20px; height: 20px; font-size: 11px; display: flex; 
            align-items: center; justify-content: center; font-weight: bold; 
            border: 2px solid var(--bg-card);
        }

        .header-icons img { 
            width: 45px; height: 45px; border-radius: 50%; 
            object-fit: cover; border: 2px solid var(--primary); padding: 2px; background: #fff;
        }

        .card {
            background: var(--bg-card); padding: 30px; border-radius: 16px;
            box-shadow: var(--shadow-soft); border: 1px solid rgba(160, 94, 68, 0.05);
            position: relative; z-index: 1;
        }

        /* --- BULLETPROOF TABLE STYLING --- */
        table { width: 100%; border-collapse: collapse; }
        th {
            text-align: left !important; 
            padding: 15px !important; 
            font-size: 12px !important; 
            text-transform: uppercase !important;
            color: #756358 !important; 
            font-weight: 700 !important;
            border-bottom: 2px solid var(--border-color) !important; 
            letter-spacing: 0.5px !important;
        }
        td { 
            padding: 18px 15px !important; 
            border-bottom: 1px dashed var(--border-color) !important; 
            font-size: 14px !important; 
            vertical-align: middle !important; 
            color: #3A2B24 !important;
        }
        tr:hover td { background-color: #FDFBF7 !important; }

        /* --- STATUS BADGES --- */
        .status-badge { 
            padding: 5px 12px; border-radius: 20px; font-weight: 700; font-size: 11px; 
            display: inline-block; text-transform: uppercase; letter-spacing: 0.5px;
        }
        .status-pending { background: rgba(212, 163, 115, 0.15); color: #B37D4D; }
        .status-confirmed { background: #e8f5e9; color: #2e7d32; }
        .status-cancelled { background: #ffebee; color: #c62828; }

        /* --- SOLID ACTION BUTTONS --- */
        .action-icons { display: flex; gap: 8px; }
        
        .btn-solid {
            padding: 8px 14px !important;
            border-radius: 6px !important;
            display: inline-flex !important;
            align-items: center !important;
            justify-content: center !important;
            gap: 6px !important;
            font-weight: bold !important;
            font-size: 12px !important;
            font-family: var(--font-body) !important;
            text-decoration: none !important;
            transition: 0.3s !important;
            cursor: pointer !important;
            text-transform: uppercase !important;
            letter-spacing: 0.5px !important;
            border: none !important;
        }

        .btn-solid-green { background-color: #2e7d32 !important; color: #ffffff !important; border: 1px solid #1b5e20 !important; }
        .btn-solid-green:hover { background-color: #1b5e20 !important; box-shadow: 0 4px 10px rgba(46, 125, 50, 0.3) !important; transform: translateY(-2px) !important; }
        .btn-solid-green i { color: #ffffff !important; }

        .btn-solid-red { background-color: #c62828 !important; color: #ffffff !important; border: 1px solid #b71c1c !important; }
        .btn-solid-red:hover { background-color: #b71c1c !important; box-shadow: 0 4px 10px rgba(198, 40, 40, 0.3) !important; transform: translateY(-2px) !important; }
        .btn-solid-red i { color: #ffffff !important; }

        .btn-solid-blue { background-color: #1976d2 !important; color: #ffffff !important; border: 1px solid #1565c0 !important; }
        .btn-solid-blue:hover { background-color: #1565c0 !important; box-shadow: 0 4px 10px rgba(25, 118, 210, 0.3) !important; transform: translateY(-2px) !important; }
        .btn-solid-blue i { color: #ffffff !important; }

        /* ID Preview Thumbnail */
        .id-thumbnail {
            width: 40px;
            height: 40px;
            border-radius: 4px;
            object-fit: cover;
            cursor: pointer;
            border: 1px solid var(--border-color);
            transition: 0.3s;
        }
        .id-thumbnail:hover { transform: scale(1.1); border-color: var(--primary); }

        /* Modal Styling */
        .admin-modal {
            display: none;
            position: fixed;
            z-index: 2000;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            overflow: auto;
            background-color: rgba(0,0,0,0.8);
            backdrop-filter: blur(5px);
        }
        .admin-modal-content {
            background-color: var(--bg-card);
            margin: 5% auto;
            padding: 30px;
            border: 1px solid var(--accent);
            width: 80%;
            max-width: 800px;
            border-radius: 16px;
            box-shadow: 0 25px 50px rgba(0,0,0,0.2);
            position: relative;
        }
        .close-admin-modal {
            position: absolute;
            right: 25px;
            top: 20px;
            color: var(--text-muted);
            font-size: 28px;
            font-weight: bold;
            cursor: pointer;
            transition: 0.3s;
        }
        .close-admin-modal:hover { color: var(--primary); }

        /* Dropdowns */
        .dropdown-menu {
            display: none; position: absolute; right: 0; top: 60px;
            background: var(--bg-card); width: 320px; box-shadow: 0 15px 35px rgba(44, 30, 22, 0.15);
            border-radius: 12px; z-index: 1001; border: 1px solid var(--border-color); overflow: hidden;
        }
        .dropdown-menu.show { display: block; animation: slideDown 0.3s ease; }
        @keyframes slideDown { from { opacity: 0; transform: translateY(-10px); } to { opacity: 1; transform: translateY(0); } }
        
        .dropdown-item { padding: 15px 20px; display: block; text-decoration: none; color: var(--text-dark); font-size: 13px; border-bottom: 1px solid var(--bg-main); transition: 0.3s; }
        .dropdown-item:hover { background: var(--bg-main); color: var(--primary); padding-left: 25px; }

        @media (max-width: 1024px) { .main-content { margin-left: 0; width: 100%; } }
    </style>
</head>
<body>

    <?php include 'admin_sidebar.php'; ?>

    <main class="main-content">
        <header class="main-header">
            <h1>Reservations Management</h1>
            <div class="header-icons">
                <div class="icon-container" id="messageIcon">
                    <i class="fas fa-envelope"></i>
                    <?php if($unread_inquiries > 0): ?><span class="header-badge"><?php echo $unread_inquiries; ?></span><?php endif; ?>
                    <div class="dropdown-menu" id="messageDropdown">
                        <?php if(count($recent_messages) > 0): ?>
                            <?php foreach ($recent_messages as $msg): ?>
                                <a href="admin_inquiries.php" class="dropdown-item">
                                    <strong style="color:var(--secondary);"><?php echo htmlspecialchars($msg['first_name']); ?></strong><br>
                                    <span style="color:#3a2b24;"><?php echo htmlspecialchars(substr($msg['message'], 0, 30)); ?>...</span>
                                </a>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <div class="dropdown-item" style="text-align:center; color:#3a2b24;">No new messages</div>
                        <?php endif; ?>
                    </div>
                </div>
                
                <div class="icon-container" id="profileIcon" style="border:none; box-shadow:none; background:transparent;">
                    <img src="Logo_Brand.png" alt="Admin" style="background:var(--secondary);">
                    <div class="dropdown-menu" id="profileDropdown" style="width:180px;">
                        <a href="profile.php" class="dropdown-item"><i class="fas fa-user-circle"></i> My Profile</a>
                        <a href="logout.php" class="dropdown-item"><i class="fas fa-sign-out-alt"></i> Logout</a>
                    </div>
                </div>
            </div>
        </header>

        <?php if(isset($_GET['success'])): ?>
            <div style="padding:15px; background:#e8f5e9; color:#2e7d32; border-radius:12px; margin-bottom:20px; font-weight:600; border:1px solid #c8e6c9;">
                <i class="fas fa-check-circle"></i> Reservation successfully <?php echo htmlspecialchars($_GET['success']); ?>!
            </div>
        <?php endif; ?>

        <?php if(isset($_GET['error'])): ?>
            <div style="padding:15px; background:#ffebee; color:#c62828; border-radius:12px; margin-bottom:20px; font-weight:600; border:1px solid #ffcdd2;">
                <i class="fas fa-exclamation-triangle"></i> 
                <?php 
                    if($_GET['error'] === 'email_failed') echo "Failed to send notification email. Reservation remains pending for security.";
                    else echo "An error occurred. Please try again.";
                ?>
            </div>
        <?php endif; ?>

        <div class="card">
            <div style="overflow-x: auto;">
                <table>
                    <thead>
                        <tr>
                            <th>Guest Name</th>
                            <th>Valid ID</th>
                            <th>Date & Time</th>
                            <th>Guests</th>
                            <th>Contact</th>
                            <th>Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ($result && $result->num_rows > 0): ?>
                            <?php while($row = $result->fetch_assoc()): ?>
                                <tr>
                                    <td>
                                        <strong style="color:var(--secondary) !important;"><?php echo htmlspecialchars($row['res_name']); ?></strong><br>
                                        <small style="color:#3a2b24; font-size:11px;"><?php echo htmlspecialchars($row['res_notes']); ?></small>
                                    </td>
                                    <td>
                                        <?php if (!empty($row['valid_id'])): ?>
                                            <img src="<?php echo htmlspecialchars($row['valid_id']); ?>" alt="ID" class="id-thumbnail" onclick="viewReservationDetails(<?php echo htmlspecialchars(json_encode($row)); ?>)">
                                        <?php else: ?>
                                            <span style="font-size: 11px; color: #756358;">No ID</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <div style="font-weight:600; color:var(--primary) !important;">
                                            <i class="far fa-calendar-alt" style="margin-right:5px;"></i><?php echo date("M d, Y", strtotime($row['res_date'])); ?>
                                        </div>
                                        <div style="font-size:12px; color:#3a2b24;">
                                            <i class="far fa-clock" style="margin-right:5px;"></i><?php echo date("g:i A", strtotime($row['res_time'])); ?>
                                        </div>
                                    </td>
                                    <td><strong style="color:var(--secondary) !important;"><?php echo $row['res_guests']; ?> Persons</strong></td>
                                    <td>
                                        <div style="font-size:13px; color:var(--text-dark) !important;"><i class="fas fa-phone-alt" style="margin-right:5px; font-size:11px;"></i><?php echo htmlspecialchars($row['res_phone']); ?></div>
                                        <div style="font-size:12px; color:#3a2b24;"><i class="fas fa-envelope" style="margin-right:5px; font-size:11px;"></i><?php echo htmlspecialchars($row['res_email']); ?></div>
                                    </td>
                                    <td><span class="status-badge status-<?php echo $row['status']; ?>"><?php echo ucfirst($row['status']); ?></span></td>
                                    <td>
                                        <div class="action-icons">
                                            <button class="btn-solid btn-solid-blue" title="View" onclick="viewReservationDetails(<?php echo htmlspecialchars(json_encode($row)); ?>)"><i class="fas fa-eye"></i> View</button>
                                            <a href="?action=confirm&id=<?php echo $row['id']; ?>" class="btn-solid btn-solid-green" title="Confirm"><i class="fas fa-check"></i> Confirm</a>
                                            <a href="?action=cancel&id=<?php echo $row['id']; ?>" class="btn-solid btn-solid-red" title="Cancel" onclick="return confirm('Cancel this reservation?')"><i class="fas fa-times"></i> Cancel</a>
                                        </div>
                                    </td>
                                </tr>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <tr><td colspan="6" style="text-align:center; padding:40px; color:#3a2b24 !important; font-weight:600;">No reservations found.</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </main>

    <!-- Reservation Detail Modal -->
    <div id="resDetailModal" class="admin-modal">
        <div class="admin-modal-content">
            <span class="close-admin-modal" onclick="closeAdminModal()">&times;</span>
            <h2 style="font-family: var(--font-heading); color: var(--secondary); margin-bottom: 25px; border-bottom: 2px solid var(--border-color); padding-bottom: 10px;">Reservation Details</h2>
            
            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 30px;">
                <div id="resIdContainer">
                    <h4 style="margin-bottom: 10px; color: var(--primary);">Valid ID Provided</h4>
                    <img id="resValidIdImg" src="" alt="Valid ID" style="width: 100%; border-radius: 8px; border: 1px solid var(--border-color); box-shadow: 0 4px 15px rgba(0,0,0,0.1);">
                </div>
                <div>
                    <h4 style="margin-bottom: 15px; color: var(--primary);">Information</h4>
                    <div style="display: flex; flex-direction: column; gap: 12px;">
                        <div><strong>Guest Name:</strong> <span id="resNameDetail"></span></div>
                        <div><strong>Email:</strong> <span id="resEmailDetail"></span></div>
                        <div><strong>Phone:</strong> <span id="resPhoneDetail"></span></div>
                        <div><strong>Date:</strong> <span id="resDateDetail"></span></div>
                        <div><strong>Time:</strong> <span id="resTimeDetail"></span></div>
                        <div><strong>Guests:</strong> <span id="resGuestsDetail"></span> Persons</div>
                        <div><strong>Status:</strong> <span id="resStatusDetail" class="status-badge"></span></div>
                        <div style="margin-top: 10px;">
                            <strong>Special Notes:</strong>
                            <p id="resNotesDetail" style="margin-top: 5px; padding: 10px; background: #FDFBF7; border-radius: 6px; border-left: 3px solid var(--accent); font-size: 13px;"></p>
                        </div>
                    </div>
                    
                    <div style="margin-top: 30px; display: flex; gap: 10px;">
                        <a id="confirmBtnDetail" href="" class="btn-solid btn-solid-green"><i class="fas fa-check"></i> Confirm Reservation</a>
                        <a id="cancelBtnDetail" href="" class="btn-solid btn-solid-red" onclick="return confirm('Cancel this reservation?')"><i class="fas fa-times"></i> Cancel Reservation</a>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const toggles = [
                { id: 'messageIcon', menu: 'messageDropdown' },
                { id: 'profileIcon', menu: 'profileDropdown' }
            ];
            toggles.forEach(t => {
                const btn = document.getElementById(t.id);
                if(btn) {
                    btn.addEventListener('click', (e) => {
                        e.stopPropagation();
                        document.querySelectorAll('.dropdown-menu').forEach(m => m.classList.remove('show'));
                        const menu = document.getElementById(t.menu);
                        if(menu) menu.classList.toggle('show');
                    });
                }
            });
            document.addEventListener('click', () => {
                document.querySelectorAll('.dropdown-menu').forEach(m => m.classList.remove('show'));
            });
        });

        function viewReservationDetails(data) {
            document.getElementById('resNameDetail').textContent = data.res_name;
            document.getElementById('resEmailDetail').textContent = data.res_email;
            document.getElementById('resPhoneDetail').textContent = data.res_phone;
            document.getElementById('resDateDetail').textContent = new Date(data.res_date).toLocaleDateString('en-US', { month: 'long', day: 'numeric', year: 'numeric' });
            document.getElementById('resTimeDetail').textContent = data.res_time; // Better formatting could be done here
            document.getElementById('resGuestsDetail').textContent = data.res_guests;
            document.getElementById('resNotesDetail').textContent = data.res_notes || 'No special notes provided.';
            
            const statusBadge = document.getElementById('resStatusDetail');
            statusBadge.textContent = data.status.charAt(0).toUpperCase() + data.status.slice(1);
            statusBadge.className = 'status-badge status-' + data.status;

            if (data.valid_id) {
                document.getElementById('resValidIdImg').src = data.valid_id;
                document.getElementById('resIdContainer').style.display = 'block';
            } else {
                document.getElementById('resIdContainer').style.display = 'none';
            }

            document.getElementById('confirmBtnDetail').href = '?action=confirm&id=' + data.id;
            document.getElementById('cancelBtnDetail').href = '?action=cancel&id=' + data.id;
            
            // Show/Hide buttons based on status
            if (data.status === 'pending') {
                document.getElementById('confirmBtnDetail').style.display = 'inline-flex';
                document.getElementById('cancelBtnDetail').style.display = 'inline-flex';
            } else {
                document.getElementById('confirmBtnDetail').style.display = 'none';
                document.getElementById('cancelBtnDetail').style.display = 'none';
            }

            const modal = document.getElementById('resDetailModal');
            modal.style.display = "block";
        }

        function closeAdminModal() {
            document.getElementById('resDetailModal').style.display = "none";
        }

        window.onclick = function(event) {
            const modal = document.getElementById('resDetailModal');
            if (event.target == modal) {
                closeAdminModal();
            }
        }
    </script>
</body>
</html>