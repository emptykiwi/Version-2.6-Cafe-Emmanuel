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

// --- FETCH ALL USERS ---
// Removed the WHERE clause so it pulls admins, super admins, AND regular users
$sql = "SELECT id, fullname, email, role, created_at FROM users ORDER BY id DESC";
$result = $conn->query($sql);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="icon" type="image/png" href="Logo_Brand.png">
    <title>User Accounts - Cafe Emmanuel</title>
    
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

        /* --- DROPDOWNS --- */
        .dropdown-menu {
            display: none; position: absolute; right: 0; top: 60px;
            background: var(--bg-card); width: 320px;
            box-shadow: 0 15px 35px rgba(44, 30, 22, 0.15);
            border-radius: 12px; z-index: 1001;
            border: 1px solid var(--border-color);
            overflow: hidden;
        }
        .dropdown-menu.show { display: block; animation: slideDown 0.3s ease; }
        @keyframes slideDown { from { opacity: 0; transform: translateY(-10px); } to { opacity: 1; transform: translateY(0); } }
        
        .dropdown-item {
            padding: 15px 20px; display: block; text-decoration: none;
            color: var(--text-dark); font-size: 13px; border-bottom: 1px solid var(--bg-main);
            transition: 0.3s;
        }
        .dropdown-item:hover { background: var(--bg-main); color: var(--primary); padding-left: 25px; }
        .dropdown-item:last-child { border-bottom: none; }

        /* --- CARD & TABLE --- */
        .card {
            background: var(--bg-card);
            padding: 30px;
            border-radius: 16px;
            box-shadow: var(--shadow-soft);
            border: 1px solid rgba(160, 94, 68, 0.05);
            position: relative;
            z-index: 1;
        }
        .card-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 25px;
        }
        .card-header h2 { font-family: var(--font-heading); font-size: 22px; font-weight: 700; color: var(--secondary); }

        .btn-add {
            background: var(--primary);
            color: white;
            padding: 12px 24px;
            border-radius: 8px;
            text-decoration: none;
            font-size: 14px;
            font-weight: 600;
            transition: all 0.3s;
            display: inline-flex;
            align-items: center;
            gap: 8px;
        }
        .btn-add:hover { background: var(--secondary); transform: translateY(-2px); box-shadow: 0 4px 15px rgba(44, 30, 22, 0.2); }

        .user-table { width: 100%; border-collapse: collapse; }
        .user-table th {
            text-align: left;
            padding: 15px;
            font-size: 12px;
            text-transform: uppercase;
            color: #3a2b24;
            border-bottom: 2px solid var(--border-color);
            letter-spacing: 0.5px;
        }
        .user-table td {
            padding: 18px 15px;
            border-bottom: 1px dashed var(--border-color);
            font-size: 14px;
            vertical-align: middle;
            color: var(--text-dark);
        }
        .user-table tr:hover td { background-color: #FDFBF7; }

        /* --- BADGES --- */
        .role-badge { padding: 5px 12px; border-radius: 20px; font-size: 11px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.5px; display: inline-block; text-align: center;}
        .role-super_admin { background: rgba(44, 30, 22, 0.1); color: var(--secondary); border: 1px solid rgba(44, 30, 22, 0.2); }
        .role-admin { background: rgba(160, 94, 68, 0.1); color: var(--primary); border: 1px solid rgba(160, 94, 68, 0.3); }
        .role-user { background: #e8f5e9; color: #2e7d32; border: 1px solid rgba(46,125,50,0.2); } /* Standard User Badge */

        /* --- ACTION BUTTONS --- */
        .action-icons { display: flex; gap: 8px; }
        .btn-icon { 
            width: 36px; height: 36px; border-radius: 8px; display: inline-flex; align-items: center; justify-content: center; 
            border: 1px solid var(--border-color); background: var(--bg-card); cursor: pointer; transition: all 0.3s; 
            text-decoration: none; outline: none; padding: 0;
        }
        .btn-icon i { color: var(--primary); font-size: 1.1rem; transition: all 0.3s ease; }
        .btn-icon:hover { background-color: var(--primary); border-color: var(--primary); transform: translateY(-2px); box-shadow: 0 4px 10px rgba(160, 94, 68, 0.2); }
        .btn-icon:hover i { color: #ffffff; }

        /* --- MODAL STYLES --- */
        .modal {
            display: none; position: fixed; z-index: 2000; left: 0; top: 0; 
            width: 100%; height: 100%; background-color: rgba(44, 30, 22, 0.6); 
            align-items: center; justify-content: center; backdrop-filter: blur(4px);
            opacity: 0; transition: opacity 0.3s ease;
        }
        .modal.active { display: flex; opacity: 1; }
        .modal-content {
            background-color: var(--bg-card); padding: 40px; border-radius: 16px; width: 90%; max-width: 450px;
            box-shadow: 0 25px 60px rgba(0,0,0,0.2); position: relative; border: 1px solid var(--accent);
            transform: translateY(20px); transition: transform 0.3s ease;
        }
        .modal.active .modal-content { transform: translateY(0); }
        .close-modal {
            position: absolute; top: 20px; right: 25px;
            font-size: 28px; cursor: pointer; color: #3a2b24; line-height: 1; transition: 0.3s;
        }
        .close-modal:hover { color: var(--primary); transform: rotate(90deg); }
        
        .form-group { margin-bottom: 20px; }
        .form-group label { display: block; margin-bottom: 8px; font-weight: 600; font-size: 13px; color: #3a2b24; text-transform: uppercase; }
        .form-group select { 
            width: 100%; padding: 12px 15px; border: 1px solid var(--border-color); border-radius: 8px; 
            font-size: 14px; font-family: var(--font-body); background: var(--bg-main); color: var(--text-dark);
            outline: none; transition: 0.3s;
        }
        .form-group select:focus { border-color: var(--primary); box-shadow: 0 0 0 3px rgba(160, 94, 68, 0.1); background: #fff; }
        
        .btn-save {
            width: 100%; background: var(--primary); color: white; border: none; padding: 14px;
            border-radius: 8px; cursor: pointer; font-weight: 600; font-size: 15px; font-family: var(--font-body);
            transition: 0.3s; margin-top: 10px;
        }
        .btn-save:hover { background: var(--secondary); box-shadow: 0 4px 15px rgba(44, 30, 22, 0.2); transform: translateY(-2px); }

        /* Alerts */
        .alert { padding: 15px 20px; border-radius: 8px; margin-bottom: 25px; font-size: 14px; font-weight: 500; display: flex; align-items: center; gap: 10px; }
        .alert-success { background: #e8f5e9; color: #2e7d32; border: 1px solid rgba(46,125,50,0.2); }
        .alert-error { background: #ffebee; color: #c62828; border: 1px solid rgba(198,40,40,0.2); }

        @media (max-width: 1024px) { .main-content { margin-left: 0; width: 100%; } }
    </style>
</head>
<body>

    <?php include 'admin_sidebar.php'; ?>

    <main class="main-content">
        <header class="main-header">
            <h1>User Accounts</h1>
            <div class="header-icons">
                <div class="icon-container" id="messageIcon">
                    <i class="fas fa-envelope"></i>
                    <?php if($unread_inquiries > 0): ?>
                        <span class="header-badge"><?php echo $unread_inquiries; ?></span>
                    <?php endif; ?>
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

        <?php if(isset($_GET['success'])): ?>
            <div class="alert alert-success">
                <i class="fas fa-check-circle"></i> Action completed successfully!
            </div>
        <?php endif; ?>
        <?php if(isset($_GET['error'])): ?>
            <div class="alert alert-error">
                <i class="fas fa-exclamation-triangle"></i> Error: <?php echo htmlspecialchars($_GET['error']); ?>
            </div>
        <?php endif; ?>

        <div class="card">
            <div class="card-header">
                <h2>All Registered Users</h2>
                <a href="create_admin_account.php" class="btn-add"><i class="fas fa-user-plus"></i> Create New User</a>
            </div>
            <div style="overflow-x: auto;">
                <table class="user-table">
                    <thead>
                        <tr>
                            <th>Full Name</th>
                            <th>Email Address</th>
                            <th>Role</th>
                            <th>Created Date</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ($result && $result->num_rows > 0): ?>
                            <?php while ($row = $result->fetch_assoc()): ?>
                                <tr>
                                    <td><strong style="color: var(--secondary); font-size:15px;"><?php echo htmlspecialchars($row['fullname']); ?></strong></td>
                                    <td><span style="color: #3a2b24;"><i class="fas fa-envelope" style="font-size:11px; margin-right:5px;"></i><?php echo htmlspecialchars($row['email']); ?></span></td>
                                    <td>
                                        <span class="role-badge role-<?php echo strtolower($row['role']); ?>">
                                            <?php echo str_replace('_', ' ', $row['role']); ?>
                                        </span>
                                    </td>
                                    <td><span style="font-size: 13px; color: #3a2b24;"><i class="far fa-clock" style="margin-right:5px;"></i><?php echo date("M d, Y", strtotime($row['created_at'])); ?></span></td>
                                    <td>
                                        <div class="action-icons">
                                            <button type="button" class="btn-icon" title="Edit Role" onclick="openEditModal('<?php echo $row['id']; ?>', '<?php echo $row['role']; ?>', '<?php echo htmlspecialchars($row['fullname'], ENT_QUOTES); ?>')" <?php if($row['id'] == $_SESSION['user_id']) echo 'disabled style="opacity:0.5; cursor:not-allowed;"'; ?>>
                                                <i class="fas fa-user-tag"></i>
                                            </button>

                                            <!-- Replaced basic confirm with custom modal trigger -->
                                            <form id="deleteForm_<?php echo $row['id']; ?>" action="user_actions.php?action=delete&id=<?php echo $row['id']; ?>" method="POST" style="display:inline;">
                                                <button type="button" class="btn-icon" title="Move to Recycle Bin" onclick="openDeleteModal('<?php echo $row['id']; ?>', '<?php echo htmlspecialchars($row['fullname'], ENT_QUOTES); ?>')" <?php if($row['id'] == $_SESSION['user_id']) echo 'disabled style="opacity:0.5; cursor:not-allowed;"'; ?>>
                                                    <i class="fas fa-trash-alt"></i>
                                                </button>
                                            </form>
                                        </div>
                                    </td>
                                </tr>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <tr><td colspan="5" style="text-align:center; padding: 40px; color: #3a2b24;">No user accounts found.</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </main>

    <!-- Edit Role Modal -->
    <div id="editRoleModal" class="modal">
        <div class="modal-content">
            <span class="close-modal" onclick="closeEditModal()">&times;</span>
            <h2 style="font-family:var(--font-heading); color:var(--secondary); font-size:24px; margin-bottom:5px;">Edit User Role</h2>
            <p id="editUserName" style="color:var(--primary); font-weight:600; font-size:14px; margin-bottom:25px;"></p>
            
            <form action="update_role.php" method="POST">
                <input type="hidden" name="user_id" id="modalUserId">
                
                <div class="form-group">
                    <label for="new_role">Select New Role</label>
                    <select name="new_role" id="modalRoleSelect" required>
                        <option value="user">User (Customer)</option>
                        <option value="admin">Admin</option>
                        <option value="super_admin">Super Admin</option>
                    </select>
                </div>

                <button type="submit" class="btn-save">Update Role</button>
            </form>
        </div>
    </div>

    <!-- Delete Confirmation Modal -->
    <div id="deleteModal" class="modal">
        <div class="modal-content" style="text-align: center; max-width: 400px; padding: 30px;">
            <span class="close-modal" onclick="closeDeleteModal()">&times;</span>
            <div style="font-size: 48px; color: #c62828; margin-bottom: 15px;">
                <i class="fas fa-exclamation-circle"></i>
            </div>
            <h2 style="font-family:var(--font-heading); color:var(--secondary); font-size:24px; margin-bottom:10px;">Confirm Deletion</h2>
            <p style="color:#3a2b24; margin-bottom: 25px; font-size: 14px;">Are you sure you want to move <strong id="deleteUserNameText" style="color:var(--primary);"></strong> to the recycle bin?</p>
            
            <div style="display: flex; gap: 10px;">
                <button type="button" class="btn-save" style="background: #e0e0e0; color: #333;" onclick="closeDeleteModal()">Cancel</button>
                <button type="button" class="btn-save" style="background: #c62828; color: white;" onclick="confirmDelete()">Yes, Delete</button>
            </div>
        </div>
    </div>

    <script>
        // Modal Functions - Edit Role
        function openEditModal(id, role, name) {
            document.getElementById('modalUserId').value = id;
            document.getElementById('modalRoleSelect').value = role;
            document.getElementById('editUserName').innerHTML = `<i class="fas fa-user-circle" style="margin-right:5px;"></i> ${name}`;
            
            const modal = document.getElementById('editRoleModal');
            modal.style.display = 'flex';
            setTimeout(() => { modal.classList.add('active'); }, 10);
        }

        function closeEditModal() {
            const modal = document.getElementById('editRoleModal');
            modal.style.opacity = '0';
            setTimeout(() => { 
                modal.classList.remove('active'); 
                modal.style.display = 'none'; 
            }, 300);
        }

        // Modal Functions - Delete User
        let currentDeleteId = null;

        function openDeleteModal(id, name) {
            currentDeleteId = id;
            document.getElementById('deleteUserNameText').innerText = name;
            
            const modal = document.getElementById('deleteModal');
            modal.style.display = 'flex';
            setTimeout(() => { modal.classList.add('active'); }, 10);
        }

        function closeDeleteModal() {
            const modal = document.getElementById('deleteModal');
            modal.style.opacity = '0';
            setTimeout(() => { 
                modal.classList.remove('active'); 
                modal.style.display = 'none'; 
                currentDeleteId = null;
            }, 300);
        }

        function confirmDelete() {
            if (currentDeleteId) {
                document.getElementById('deleteForm_' + currentDeleteId).submit();
            }
        }

        // Close modal if clicked outside content
        window.onclick = function(event) {
            var editModal = document.getElementById('editRoleModal');
            var deleteModal = document.getElementById('deleteModal');
            if (event.target == editModal) {
                closeEditModal();
            }
            if (event.target == deleteModal) {
                closeDeleteModal();
            }
        }

        // Dropdown functionality
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
                            if (m.id !== t.menu) m.classList.remove('show');
                        });
                        document.getElementById(t.menu).classList.toggle('show');
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