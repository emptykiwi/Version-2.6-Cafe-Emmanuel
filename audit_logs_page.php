<?php
include 'session_check.php';
include 'db_connect.php';

// --- DATA FOR HEADER ICONS ---
$inquiry_count_result = $conn->query("SELECT COUNT(*) as count FROM inquiries WHERE status = 'new'");
$unread_inquiries = $inquiry_count_result ? $inquiry_count_result->fetch_assoc()['count'] : 0;

$recent_inquiries_result = $conn->query("SELECT * FROM inquiries WHERE status = 'new' ORDER BY received_at DESC LIMIT 5");
$recent_messages = [];
if ($recent_inquiries_result) {
    while ($row = $recent_inquiries_result->fetch_assoc()) {
        $recent_messages[] = $row;
    }
}

// --- FETCH AUDIT LOGS ---
$sql = "SELECT admin_name, action, description, created_at FROM audit_logs ORDER BY created_at DESC";
$result = $conn->query($sql);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="icon" type="image/png" href="Logo_Brand.png">
    <title>Audit Logs - Cafe Emmanuel</title>
    
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
            margin-bottom: 40px;
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
        .icon-container:hover { background: var(--primary); border-color: var(--primary); color: #fff; }

        .card {
            background: var(--bg-card); padding: 30px; border-radius: 16px;
            box-shadow: var(--shadow-soft); border: 1px solid rgba(160, 94, 68, 0.05);
        }

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

        /* --- LOG STYLES --- */
        .admin-name { font-weight: 600; color: var(--secondary); }
        .action-tag {
            padding: 4px 10px; border-radius: 4px; font-size: 11px; font-weight: 700;
            text-transform: uppercase; background: rgba(160, 94, 68, 0.1); color: var(--primary);
        }
        .date-time { font-size: 13px; color: #3a2b24; line-height: 1.4; }
        .date-time i { margin-right: 5px; color: var(--accent); }

        /* Dropdowns */
        .dropdown-menu {
            display: none; position: absolute; right: 0; top: 60px;
            background: var(--bg-card); width: 320px; box-shadow: 0 15px 35px rgba(44, 30, 22, 0.15);
            border-radius: 12px; z-index: 1001; border: 1px solid var(--border-color); overflow: hidden;
        }
        .dropdown-menu.show { display: block; }
        .dropdown-item { padding: 15px 20px; display: block; text-decoration: none; color: var(--text-dark); font-size: 13px; border-bottom: 1px solid var(--bg-main); transition: 0.3s; }
        .dropdown-item:hover { background: var(--bg-main); color: var(--primary); }

        @media (max-width: 1024px) { .main-content { margin-left: 0; width: 100%; } }
    </style>
</head>
<body>

    <?php include 'admin_sidebar.php'; ?>

    <main class="main-content">
        <header class="main-header">
            <h1>System Audit Logs</h1>
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
                    <img src="Logo_Brand.png" alt="Admin" style="width:45px; height:45px; border-radius:50%; border:2px solid var(--primary); padding:2px; background:#fff;">
                </div>
            </div>
        </header>

        <div class="card">
            <div style="overflow-x: auto;">
                <table>
                    <thead>
                        <tr>
                            <th>Admin Name</th>
                            <th>Action</th>
                            <th>Description</th>
                            <th>Date & Time</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ($result && $result->num_rows > 0): ?>
                            <?php while ($row = $result->fetch_assoc()): ?>
                                <tr>
                                    <td class="admin-name"><?php echo htmlspecialchars($row['admin_name']); ?></td>
                                    <td><span class="action-tag"><?php echo htmlspecialchars(str_replace('_', ' ', $row['action'])); ?></span></td>
                                    <td style="max-width: 300px;"><?php echo htmlspecialchars($row['description']); ?></td>
                                    <td class="date-time">
                                        <div><i class="far fa-calendar-alt"></i><?php echo date("M d, Y", strtotime($row['created_at'])); ?></div>
                                        <div><i class="far fa-clock"></i><?php echo date("g:i A", strtotime($row['created_at'])); ?></div>
                                    </td>
                                </tr>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <tr><td colspan="4" style="text-align:center; padding:40px; color:#3a2b24; font-weight:600;">No activity logs found.</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </main>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const toggles = [
                { id: 'messageIcon', menu: 'messageDropdown' }
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
    </script>
</body>
</html>