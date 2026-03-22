<?php
include 'session_check.php';
include 'db_connect.php';
if ($conn->connect_error) { die("Connection failed: " . $conn->connect_error); }

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

// Handle status update action (from View Modal or Direct Links)
if (isset($_GET['action']) && isset($_GET['id'])) {
    $action = $_GET['action'];
    $id = intval($_GET['id']);
    
    if ($action == 'status' && isset($_GET['new_status'])) {
        $new_status = $_GET['new_status'];
        $stmt = $conn->prepare("UPDATE inquiries SET status = ? WHERE id = ?");
        $stmt->bind_param("si", $new_status, $id);
        $stmt->execute();
    } elseif ($action == 'delete') {
        $stmt = $conn->prepare("DELETE FROM inquiries WHERE id = ?");
        $stmt->bind_param("i", $id);
        $stmt->execute();
    }
    header("Location: admin_inquiries.php");
    exit();
}

// Query Inquiries - Sorted by Priority
$inquiries_result = $conn->query("SELECT * FROM inquiries ORDER BY 
    CASE status 
        WHEN 'new' THEN 1 
        WHEN 'in_progress' THEN 2 
        WHEN 'responded' THEN 3 
        WHEN 'closed' THEN 4 
    END, 
    received_at DESC");
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0"/>
<link rel="icon" type="image/png" href="Logo_Brand.png">
<title>Inquiries - Cafe Emmanuel</title>

<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" />
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Playfair+Display:ital,wght@0,500;0,700;1,500&family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">

<style>
    /* --- CAFE EMMANUEL COLOR THEME --- */
    :root {
        --primary: #A05E44;       
        --primary-hover: #804832;
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
        --shadow-hover: 0 15px 35px rgba(160, 94, 68, 0.1);
    }

    * { box-sizing: border-box; margin: 0; padding: 0; }

    body {
        font-family: var(--font-body);
        background-color: var(--bg-main);
        color: var(--text-dark);
        display: flex;
        height: 100vh;
        overflow: hidden;
    }

    /* --- LAYOUT --- */
    .main-content { 
        flex-grow: 1;
        margin-left: 260px; /* Space for fixed sidebar */
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
        font-family: var(--font-heading);
        font-size: 32px; 
        font-weight: 700; 
        color: var(--secondary);
    }

    /* --- HEADER ICONS --- */
    .header-icons { display: flex; align-items: center; gap: 20px; }
    .icon-container {
        position: relative;
        cursor: pointer;
        color: #3a2b24;
        width: 45px;
        height: 45px;
        background: var(--bg-card);
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        border: 1px solid var(--border-color);
        transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        box-shadow: 0 4px 10px rgba(44, 30, 22, 0.05);
    }
    
    .icon-container i {
        font-size: 1.25rem;
        color: var(--secondary);
        transition: all 0.3s ease;
    }

    .icon-container:hover { 
        background: var(--primary); 
        border-color: var(--primary); 
        transform: translateY(-3px);
        box-shadow: 0 8px 20px rgba(160, 94, 68, 0.2);
    }
    
    .icon-container:hover i { color: #fff; }

    .header-badge {
        position: absolute;
        top: -5px; right: -5px;
        background-color: var(--accent);
        color: white;
        border-radius: 50%;
        width: 20px; height: 20px;
        font-size: 11px;
        display: flex; align-items: center; justify-content: center;
        font-weight: bold; border: 2px solid var(--bg-card);
    }
    .header-icons img { 
        width: 45px; height: 45px; 
        border-radius: 50%; 
        object-fit: cover;
        border: 2px solid var(--primary);
        padding: 2px;
        background: #fff;
    }

    /* --- TABLE CARD --- */
    .table-card {
        background: var(--bg-card);
        padding: 30px;
        border-radius: 16px;
        box-shadow: var(--shadow-soft);
        border: 1px solid rgba(160, 94, 68, 0.05);
        position: relative;
        z-index: 1;
    }

    table { width: 100%; border-collapse: collapse; }
    th {
        text-align: left;
        padding: 15px;
        font-size: 12px;
        text-transform: uppercase;
        color: #3a2b24;
        border-bottom: 2px solid var(--border-color);
        letter-spacing: 0.5px;
    }
    td {
        padding: 18px 15px;
        border-bottom: 1px dashed var(--border-color);
        font-size: 14px;
        vertical-align: middle;
        color: var(--text-dark);
    }
    tr:hover td { background-color: #FDFBF7; }

    /* Status Badges */
    .status-badge { 
        padding: 5px 12px; border-radius: 20px; font-weight: 700; font-size: 11px; 
        display: inline-block; text-transform: uppercase; letter-spacing: 0.5px;
        border: 1px solid transparent;
    }
    .badge-new { background-color: rgba(160, 94, 68, 0.1); color: var(--primary); border-color: rgba(160, 94, 68, 0.3); }
    .badge-in_progress { background-color: rgba(212, 163, 115, 0.15); color: #B37D4D; border-color: rgba(212, 163, 115, 0.3); }
    .badge-responded { background-color: #e8f5e9; color: #2e7d32; border-color: rgba(46,125,50,0.2); }
    .badge-closed { background-color: #f5f5f5; color: #3a2b24; border-color: #ddd; }

    /* --- FIXED ACTION BUTTONS (Icons explicitly styled to be visible) --- */
    .action-icons { display: flex; gap: 8px; }
    .btn-icon { 
        width: 38px; height: 38px; border-radius: 8px; display: inline-flex; align-items: center; justify-content: center; 
        border: 1px solid var(--border-color); background: var(--bg-card); cursor: pointer; transition: all 0.3s; 
        text-decoration: none; outline: none; padding: 0;
    }
    
    /* Explicitly target the i tag to ensure visibility */
    .btn-icon i {
        color: var(--primary); /* Give icons a strong caramel color by default */
        font-size: 1.15rem;
        transition: all 0.3s ease;
    }

    .btn-icon:hover { 
        background-color: var(--primary); 
        border-color: var(--primary); 
        transform: translateY(-2px); 
        box-shadow: 0 4px 10px rgba(160, 94, 68, 0.2); 
    }
    
    .btn-icon:hover i { 
        color: #ffffff; /* Turn white on hover */
    }

    /* Modal */
    .modal { display: none; position: fixed; z-index: 9999; left: 0; top: 0; width: 100%; height: 100%; background: rgba(44, 30, 22, 0.6); align-items: center; justify-content: center; backdrop-filter: blur(4px); opacity: 0; transition: opacity 0.3s ease; }
    .modal.active { display: flex; opacity: 1; }
    .modal-container { background: var(--bg-card); border-radius: 16px; width: 90%; max-width: 600px; box-shadow: 0 25px 60px rgba(0,0,0,0.2); transform: translateY(20px); transition: transform 0.3s ease; border: 1px solid var(--accent); }
    .modal.active .modal-container { transform: translateY(0); }
    .modal-header { padding: 25px 30px; border-bottom: 1px solid var(--border-color); display: flex; justify-content: space-between; align-items: center; }
    .modal-body { padding: 30px; }

    /* Forms in Modal */
    .form-control { width: 100%; padding: 12px 15px; border: 1px solid var(--border-color); border-radius: 8px; font-family: var(--font-body); font-size: 14px; background: var(--bg-main); color: var(--text-dark); transition: 0.3s; outline: none; }
    .form-control:focus { border-color: var(--primary); box-shadow: 0 0 0 3px rgba(160, 94, 68, 0.1); background: #fff; }

    /* Dropdowns */
    .dropdown-menu {
        display: none;
        position: absolute;
        right: 0; top: 60px;
        background: var(--bg-card);
        width: 320px;
        box-shadow: 0 15px 35px rgba(44, 30, 22, 0.15);
        border-radius: 12px;
        z-index: 1001;
        border: 1px solid var(--border-color);
        overflow: hidden;
    }
    .dropdown-menu.show { display: block; animation: slideDown 0.3s ease; }
    @keyframes slideDown { from { opacity: 0; transform: translateY(-10px); } to { opacity: 1; transform: translateY(0); } }
    .dropdown-item {
        padding: 15px 20px;
        display: block;
        text-decoration: none;
        color: var(--text-dark);
        font-size: 13px;
        border-bottom: 1px solid var(--bg-main);
        transition: 0.3s;
    }
    .dropdown-item:hover { background: var(--bg-main); color: var(--primary); padding-left: 25px; }
    .dropdown-item:last-child { border-bottom: none; }

    @media (max-width: 1024px) {
        .main-content { margin-left: 0; width: 100%; }
    }
</style>
</head>
<body>
<div class="dashboard-wrapper" style="display:flex; width:100%;">
  <?php include 'admin_sidebar.php'; ?>
  <main class="main-content">
    <header class="main-header">
      <h1>Customer Inquiries</h1>
      <div class="header-icons">
          <div class="icon-container" id="messageIcon">
              <i class="fas fa-envelope"></i>
              <?php if($unread_inquiries > 0): ?><span class="header-badge"><?php echo $unread_inquiries; ?></span><?php endif; ?>
              <div class="dropdown-menu" id="messageDropdown">
                    <?php if(empty($recent_messages)): ?>
                        <div class="dropdown-item" style="text-align:center; color:#3a2b24;">No new messages</div>
                    <?php else: ?>
                        <?php foreach ($recent_messages as $msg): ?>
                            <a href="admin_inquiries.php" class="dropdown-item">
                                <strong style="color:var(--secondary);"><?php echo htmlspecialchars($msg['first_name']); ?></strong><br>
                                <span style="color:#3a2b24;"><?php echo htmlspecialchars(substr($msg['message'], 0, 40)); ?>...</span>
                            </a>
                        <?php endforeach; ?>
                    <?php endif; ?>
              </div>
          </div>
          <div class="icon-container" id="notificationBell">
              <i class="fas fa-bell"></i>
              <div class="dropdown-menu" id="notificationDropdown">
                  <div class="dropdown-item" style="text-align:center; color:#3a2b24;">No new notifications</div>
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

    <div class="table-card">
        <div style="overflow-x: auto;">
            <table>
                <thead>
                    <tr>
                        <th>Sender</th>
                        <th>Message Preview</th>
                        <th>Received Date</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                <?php if ($inquiries_result->num_rows > 0): ?>
                    <?php while ($row = $inquiries_result->fetch_assoc()): 
                        $jsonData = htmlspecialchars(json_encode($row), ENT_QUOTES, 'UTF-8');
                        $status = $row['status'] ?? 'new';
                    ?>
                    <tr>
                        <td>
                            <div style="font-weight:600; color:var(--secondary);"><?php echo htmlspecialchars($row['first_name'] . ' ' . $row['last_name']); ?></div>
                            <div style="font-size:12px; color:#3a2b24;"><i class="fas fa-envelope" style="margin-right:5px; font-size:10px;"></i><?php echo htmlspecialchars($row['email']); ?></div>
                        </td>
                        <td style="max-width:300px; white-space:nowrap; overflow:hidden; text-overflow:ellipsis; font-size:13px;">
                            <?php echo htmlspecialchars($row['message']); ?>
                        </td>
                        <td style="font-size:13px; color:#3a2b24;">
                            <i class="far fa-clock" style="margin-right:5px;"></i><?php echo date("M d, Y - g:i A", strtotime($row['received_at'])); ?>
                        </td>
                        <td>
                            <span class="status-badge badge-<?php echo $status; ?>"><?php echo ucwords(str_replace('_', ' ', $status)); ?></span>
                        </td>
                        <td>
                            <div class="action-icons">
                                <button onclick="openViewModal(<?php echo $jsonData; ?>)" class="btn-icon" title="View Details"><i class="fas fa-eye"></i></button>
                                <button onclick="openReplyModal(<?php echo $jsonData; ?>)" class="btn-icon" title="Reply to Customer"><i class="fas fa-reply"></i></button>
                                <a href="?action=delete&id=<?php echo $row['id']; ?>" class="btn-icon" title="Delete Inquiry" onclick="return confirm('Are you sure you want to permanently delete this inquiry?')"><i class="fas fa-trash-alt"></i></a>
                            </div>
                        </td>
                    </tr>
                    <?php endwhile; ?>
                <?php else: ?>
                    <tr><td colspan="5" style="text-align:center; padding:40px; color:#3a2b24;">No inquiries found.</td></tr>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
  </main>
</div>

<div id="viewModal" class="modal">
    <div class="modal-container">
        <div class="modal-header">
            <h2 style="font-family:var(--font-heading); font-size:22px; color:var(--secondary);">Inquiry Details</h2>
            <button onclick="closeModal('viewModal')" style="background:none; border:none; font-size:28px; cursor:pointer; color:#3a2b24; line-height:1;">&times;</button>
        </div>
        <div class="modal-body">
            <div id="viewMeta" style="margin-bottom:20px; font-size:14px; color:var(--text-dark); line-height:1.6;"></div>
            <div style="background:var(--bg-main); padding:20px; border-radius:8px; margin-bottom:25px; border:1px solid var(--border-color); font-size:14px; line-height:1.6;" id="viewMessage"></div>
            
            <form action="admin_inquiries.php" method="GET" style="display:flex; gap:10px; align-items:center;">
                <input type="hidden" name="action" value="status">
                <input type="hidden" name="id" id="viewId">
                <label style="font-size:13px; font-weight:600; color:#3a2b24;">Status:</label>
                <select name="new_status" id="viewStatusSelect" class="form-control" style="flex-grow:1;">
                    <option value="new">New</option>
                    <option value="in_progress">In Progress</option>
                    <option value="responded">Responded</option>
                    <option value="closed">Closed</option>
                </select>
                <button type="submit" style="background:var(--primary); color:white; border:none; padding:12px 20px; border-radius:8px; cursor:pointer; font-weight:600; transition:0.3s; font-family:var(--font-body);">Update</button>
            </form>
        </div>
    </div>
</div>

<div id="replyModal" class="modal">
    <div class="modal-container">
        <div class="modal-header">
            <h2 style="font-family:var(--font-heading); font-size:22px; color:var(--secondary);">Reply to Customer</h2>
            <button onclick="closeModal('replyModal')" style="background:none; border:none; font-size:28px; cursor:pointer; color:#3a2b24; line-height:1;">&times;</button>
        </div>
        <div class="modal-body">
            <form method="POST" action="send_inquiry_response.php">
                <input type="hidden" name="inquiry_id" id="replyId">
                <div style="margin-bottom:20px;">
                    <label style="font-size:13px; font-weight:600; color:var(--text-dark); margin-bottom:8px; display:block;">Your Message:</label>
                    <textarea name="response_message" id="responseMessage" class="form-control" required style="height:150px; resize:vertical;" placeholder="Type your response here..."></textarea>
                </div>
                <div style="display:flex; gap:15px;">
                    <button type="submit" style="flex-grow:1; background:var(--primary); color:white; border:none; padding:14px; border-radius:8px; cursor:pointer; font-weight:600; font-family:var(--font-body); font-size:15px; transition:0.3s;">Send Reply <i class="fas fa-paper-plane" style="margin-left:5px;"></i></button>
                    <button type="button" onclick="closeModal('replyModal')" style="padding:14px 20px; background:var(--bg-main); border:1px solid var(--border-color); color:var(--text-dark); border-radius:8px; cursor:pointer; font-family:var(--font-body); font-weight:500; transition:0.3s;">Cancel</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
function closeModal(id) { 
    const modal = document.getElementById(id);
    modal.style.opacity = '0';
    setTimeout(() => { modal.classList.remove('active'); }, 300);
}

function openViewModal(data) {
    document.getElementById('viewId').value = data.id;
    document.getElementById('viewMeta').innerHTML = `<strong style="color:var(--secondary);">From:</strong> ${data.first_name} ${data.last_name} <br> <strong style="color:var(--secondary);">Email:</strong> ${data.email}`;
    document.getElementById('viewMessage').textContent = data.message;
    document.getElementById('viewStatusSelect').value = data.status;
    
    const modal = document.getElementById('viewModal');
    modal.classList.add('active');
    setTimeout(() => { modal.style.opacity = '1'; }, 10);
}

function openReplyModal(data) {
    document.getElementById('replyId').value = data.id;
    
    const modal = document.getElementById('replyModal');
    modal.classList.add('active');
    setTimeout(() => { modal.style.opacity = '1'; }, 10);
}

// Close modals when clicking outside the container
window.onclick = function(event) {
    if (event.target.classList.contains('modal')) {
        closeModal(event.target.id);
    }
}

document.addEventListener('DOMContentLoaded', function() {
    const toggles = [
        { id: 'messageIcon', menu: 'messageDropdown' },
        { id: 'notificationBell', menu: 'notificationDropdown' },
        { id: 'profileIcon', menu: 'profileDropdown' }
    ];
    toggles.forEach(t => {
        const btn = document.getElementById(t.id);
        if(btn) {
            btn.addEventListener('click', (e) => {
                e.stopPropagation();
                document.querySelectorAll('.dropdown-menu').forEach(m => {
                    if(m.id !== t.menu) m.classList.remove('show');
                });
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
<?php $conn->close(); ?>