<?php
// --- 1. ENABLE ERROR REPORTING FOR DEBUGGING ---
error_reporting(E_ALL);
ini_set('display_errors', 1);

include 'session_check.php';
require_once 'audit_log.php'; 
include 'db_connect.php';

// --- 2. CHECK CONNECTION & CREATE TABLE IF MISSING ---
if (!isset($conn) || $conn->connect_error) {
    die("❌ Connection failed: " . ($conn->connect_error ?? "Database variable missing"));
}

// Auto-fix table structure
$tableCheck = $conn->query("SHOW TABLES LIKE 'hero_slides'");
if ($tableCheck->num_rows == 0) {
    $sql = "CREATE TABLE hero_slides (
        id INT(11) AUTO_INCREMENT PRIMARY KEY,
        file_path VARCHAR(255) NOT NULL,
        type ENUM('image', 'video') NOT NULL DEFAULT 'image',
        heading VARCHAR(255),
        subtext TEXT,
        button_text VARCHAR(50) DEFAULT 'View Menu',
        button_link VARCHAR(255) DEFAULT 'product.php',
        sort_order INT(11) DEFAULT 0,
        is_active TINYINT(1) DEFAULT 1
    )";
    if(!$conn->query($sql)) {
        die("Error creating table: " . $conn->error);
    }
}

// --- 3. HANDLE ACTIONS ---
$successMessage = "";
$errorMessage = "";

// DELETE
if (isset($_GET['delete'])) {
    $id = (int)$_GET['delete'];
    // Optional: Delete file from server
    $fileQ = $conn->query("SELECT file_path FROM hero_slides WHERE id=$id");
    if($fileQ && $row=$fileQ->fetch_assoc()){
        if(file_exists($row['file_path'])) unlink($row['file_path']);
    }
    
    $stmt = $conn->prepare("DELETE FROM hero_slides WHERE id = ?");
    $stmt->bind_param("i", $id);
    if ($stmt->execute()) {
        $successMessage = "<i class='fas fa-check-circle'></i> Slide deleted successfully.";
        if(function_exists('logAdminAction')) {
            logAdminAction($conn, $_SESSION['user_id'] ?? 0, $_SESSION['fullname'] ?? 'Admin', 'delete_slide', "Deleted ID: $id", 'hero_slides', $id);
        }
    }
    $stmt->close();
}

// ADD / UPDATE
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_slide'])) {
    $slide_id = $_POST['slide_id'] ?? null;
    $heading = $_POST['heading'] ?? '';
    $subtext = $_POST['subtext'] ?? '';
    $btn_text = $_POST['button_text'] ?? '';
    $btn_link = $_POST['button_link'] ?? '';
    $sort_order = (int)($_POST['sort_order'] ?? 0);
    
    $file_path = $_POST['current_file'] ?? '';
    $type = $_POST['current_type'] ?? 'image'; 

    // File Upload Logic
    if (isset($_FILES['slide_file']) && $_FILES['slide_file']['error'] !== UPLOAD_ERR_NO_FILE) {
        // Check for upload errors
        if ($_FILES['slide_file']['error'] !== UPLOAD_ERR_OK) {
            $errCode = $_FILES['slide_file']['error'];
            $errorMessage = "<i class='fas fa-exclamation-circle'></i> Upload Error Code: $errCode. (File might be too large for server settings)";
        } else {
            $target_dir = "uploads/";
            if (!is_dir($target_dir)) { mkdir($target_dir, 0777, true); }
            
            $file_name = time() . "_" . preg_replace("/[^a-zA-Z0-9.]/", "", basename($_FILES["slide_file"]["name"]));
            $target_file = $target_dir . $file_name;
            $ext = strtolower(pathinfo($target_file, PATHINFO_EXTENSION));
            
            $allowed_imgs = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
            $allowed_vids = ['mp4', 'webm', 'ogg'];
            
            if (in_array($ext, $allowed_imgs)) { $type = 'image'; }
            elseif (in_array($ext, $allowed_vids)) { $type = 'video'; }
            else { $errorMessage = "<i class='fas fa-exclamation-circle'></i> Invalid file type. Only JPG, PNG, MP4 allowed."; }

            if (empty($errorMessage)) {
                if (move_uploaded_file($_FILES["slide_file"]["tmp_name"], $target_file)) { 
                    $file_path = $target_file; 
                } else { 
                    $errorMessage = "<i class='fas fa-exclamation-circle'></i> Failed to move file. Check folder permissions."; 
                }
            }
        }
    }

    if (empty($errorMessage)) {
        if ($slide_id) {
            // Update
            $stmt = $conn->prepare("UPDATE hero_slides SET heading=?, subtext=?, button_text=?, button_link=?, sort_order=?, file_path=?, type=? WHERE id=?");
            $stmt->bind_param("ssssissi", $heading, $subtext, $btn_text, $btn_link, $sort_order, $file_path, $type, $slide_id);
            $action = "updated";
        } else {
            // Insert
            if (empty($file_path)) { 
                $errorMessage = "<i class='fas fa-exclamation-circle'></i> Please select a file to upload."; 
            } else {
                $stmt = $conn->prepare("INSERT INTO hero_slides (heading, subtext, button_text, button_link, sort_order, file_path, type) VALUES (?, ?, ?, ?, ?, ?, ?)");
                $stmt->bind_param("ssssiss", $heading, $subtext, $btn_text, $btn_link, $sort_order, $file_path, $type);
                $action = "added";
            }
        }

        if (empty($errorMessage) && isset($stmt)) {
            if ($stmt->execute()) {
                $successMessage = "<i class='fas fa-check-circle'></i> Slide $action successfully!";
            $record_id = $slide_id ?: $conn->insert_id;
            logAdminAction($conn, $_SESSION['user_id'] ?? 0, $_SESSION['fullname'] ?? 'Admin', ($action === 'updated' ? 'update_slide' : 'add_slide'), "Successfully $action hero slide #$record_id ($heading)", 'hero_slides', $record_id);
            } else {
                $errorMessage = "<i class='fas fa-exclamation-circle'></i> Database Error: " . $stmt->error;
            }
            $stmt->close();
        }
    }
}

// --- 4. FETCH SLIDES (Video First, Then Ordered Images) ---
$slides = [];
$result = $conn->query("SELECT * FROM hero_slides ORDER BY type DESC, sort_order ASC");
if ($result) { $slides = $result->fetch_all(MYSQLI_ASSOC); }

// --- 5. HEADER DATA ---
$inquiry_count_result = $conn->query("SELECT COUNT(*) as count FROM inquiries WHERE status = 'new'");
$unread_inquiries = $inquiry_count_result ? $inquiry_count_result->fetch_assoc()['count'] : 0;

$recent_inquiries_result = $conn->query("SELECT * FROM inquiries WHERE status = 'new' ORDER BY received_at DESC LIMIT 5");
$recent_messages = [];
if ($recent_inquiries_result) {
    while ($row = $recent_inquiries_result->fetch_assoc()) {
        $recent_messages[] = $row;
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<link rel="icon" type="image/png" href="Logo_Brand.png">
<title>Manage Hero - Cafe Emmanuel</title>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
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

    /* Admin Grid Layout */
    .admin-grid { display: grid; grid-template-columns: 380px 1fr; gap: 30px; align-items: start; position: relative; z-index: 1; }
    
    /* Card Styles */
    .card { background: var(--bg-card); border-radius: 16px; padding: 30px; box-shadow: var(--shadow-soft); border: 1px solid rgba(160, 94, 68, 0.05); }
    .form-group { margin-bottom: 20px; }
    .form-group label { display: block; margin-bottom: 8px; font-weight: 600; font-size: 0.85rem; color: #3a2b24; text-transform: uppercase; letter-spacing: 0.5px; }
    .form-control { 
        width: 100%; 
        padding: 12px 15px; 
        border: 1px solid var(--border-color); 
        border-radius: 8px; 
        outline: none; 
        font-family: var(--font-body); 
        background: var(--bg-main); 
        color: var(--text-dark); 
        transition: all 0.3s; 
    }
    .form-control:focus { border-color: var(--primary); box-shadow: 0 0 0 3px rgba(160, 94, 68, 0.1); background: #fff; }
    
    .btn-submit { 
        width: 100%; 
        padding: 14px; 
        background: var(--primary); 
        color: white; 
        border: none; 
        border-radius: 8px; 
        font-weight: 600; 
        font-family: var(--font-body);
        cursor: pointer; 
        transition: 0.3s; 
        font-size: 1rem;
        display: flex;
        justify-content: center;
        align-items: center;
        gap: 8px;
    }
    .btn-submit:hover { background: var(--secondary); transform: translateY(-2px); box-shadow: 0 4px 15px rgba(44, 30, 22, 0.2); }
    
    .btn-cancel {
        width: 100%; 
        margin-top: 15px; 
        background: var(--bg-main); 
        border: 1px solid var(--border-color); 
        padding: 12px; 
        border-radius: 8px; 
        cursor: pointer; 
        color: var(--text-dark);
        font-family: var(--font-body);
        font-weight: 500;
        transition: 0.3s;
    }
    .btn-cancel:hover { background: #e0e0e0; border-color: #ccc; }

    /* Slide List Styles */
    .slide-item { 
        background: var(--bg-card); 
        border: 1px solid var(--border-color); 
        border-radius: 12px; 
        padding: 20px; 
        display: flex; 
        gap: 20px; 
        align-items: center; 
        margin-bottom: 20px; 
        box-shadow: 0 4px 10px rgba(0,0,0,0.02); 
        transition: all 0.3s ease;
    }
    .slide-item:hover {
        border-color: var(--accent);
        transform: translateY(-2px);
        box-shadow: 0 8px 25px rgba(44, 30, 22, 0.05);
    }
    .slide-preview { width: 140px; height: 90px; background: var(--bg-main); border-radius: 8px; overflow: hidden; position: relative; flex-shrink: 0; border: 1px solid var(--border-color); }
    .slide-preview img, .slide-preview video { width: 100%; height: 100%; object-fit: cover; }
    .badge { position: absolute; top: 5px; left: 5px; padding: 4px 8px; font-weight: 600; color: white; font-size: 10px; border-radius: 4px; text-transform: uppercase; letter-spacing: 0.5px; }
    
    .slide-info h4 { font-family: var(--font-heading); font-size: 18px; margin-bottom: 5px; color: var(--secondary); }
    .slide-info p { font-size: 13px; color: #3a2b24; margin-bottom: 8px; line-height: 1.5; }
    
    /* Explicit Action Button Styles */
    .action-icons { display: flex; gap: 8px; flex-direction: column;}
    .action-btn { 
        width: 36px; height: 36px; border-radius: 8px; display: inline-flex; align-items: center; justify-content: center; 
        border: 1px solid var(--border-color); background: var(--bg-card); cursor: pointer; transition: all 0.3s; 
        text-decoration: none; outline: none; padding: 0;
    }
    .action-btn i { color: var(--primary); font-size: 1.1rem; transition: all 0.3s ease; }
    .action-btn:hover { background-color: var(--primary); border-color: var(--primary); transform: translateY(-2px); box-shadow: 0 4px 10px rgba(160, 94, 68, 0.2); }
    .action-btn:hover i { color: #ffffff; }

    /* Alerts */
    .alert { padding: 15px 20px; border-radius: 8px; margin-bottom: 25px; font-size: 14px; font-weight: 500; display: flex; align-items: center; gap: 10px; }
    .alert-success { background: #e8f5e9; color: #2e7d32; border: 1px solid rgba(46,125,50,0.2); }
    .alert-error { background: #ffebee; color: #c62828; border: 1px solid rgba(198,40,40,0.2); }

    @media (max-width: 1024px) { .main-content { margin-left: 0; width: 100%; } .admin-grid { grid-template-columns: 1fr; } .action-icons { flex-direction: row; } }
</style>
</head>
<body>

    <?php include 'admin_sidebar.php'; ?>

    <main class="main-content">
        <header class="main-header">
            <h1>Hero Section Manager</h1>
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

        <?php if($successMessage): ?><div class="alert alert-success"><?php echo $successMessage; ?></div><?php endif; ?>
        <?php if($errorMessage): ?><div class="alert alert-error"><?php echo $errorMessage; ?></div><?php endif; ?>

        <div class="admin-grid">
            <div class="card">
                <h3 id="formTitle" style="margin-bottom:25px; font-family:var(--font-heading); font-size:24px; color:var(--secondary);">Add New Slide</h3>
                
                <form method="POST" enctype="multipart/form-data" id="slideForm">
                    <input type="hidden" name="slide_id" id="slide_id">
                    <input type="hidden" name="current_file" id="current_file">
                    <input type="hidden" name="current_type" id="current_type">

                    <div class="form-group">
                        <label>Media File (Max 30s Video)</label>
                        <input type="file" name="slide_file" id="fileInput" class="form-control" accept="image/*,video/mp4,video/webm" style="padding: 9px;">
                        <small id="fileHelp" style="color:#3a2b24; font-size:11px; margin-top:5px; display:block;">Current File: <span id="fileNameDisplay" style="color:var(--primary); font-weight:600;">None</span></small>
                    </div>

                    <div class="form-group">
                        <label>Heading</label>
                        <input type="text" name="heading" id="heading" class="form-control" placeholder="e.g. Welcome to Cafe Emmanuel">
                    </div>

                    <div class="form-group">
                        <label>Subtext</label>
                        <textarea name="subtext" id="subtext" class="form-control" rows="3" placeholder="Short description..." style="resize: vertical;"></textarea>
                    </div>

                    <div class="form-group">
                        <label>Button Text</label>
                        <input type="text" name="button_text" id="button_text" class="form-control" value="View Menu">
                    </div>

                    <div class="form-group">
                        <label>Sort Order</label>
                        <input type="number" name="sort_order" id="sort_order" class="form-control" value="0">
                    </div>

                    <button type="submit" name="save_slide" class="btn-submit" id="submitBtn"><i class="fas fa-save"></i> Save Slide</button>
                    <button type="button" onclick="resetForm()" class="btn-cancel" id="cancelBtn" style="display:none;">Cancel Edit</button>
                </form>
            </div>

            <div class="slides-list">
                <?php if(empty($slides)): ?>
                    <div style="text-align:center; padding:60px 20px; color:#3a2b24; border:2px dashed var(--border-color); border-radius:16px; background:var(--bg-card);">
                        <i class="fas fa-cloud-upload-alt fa-3x" style="color:var(--border-color); margin-bottom:15px;"></i><br>
                        <span style="font-size:1.1rem;">No slides yet. Upload your first slide!</span>
                    </div>
                <?php else: ?>
                    <?php foreach($slides as $slide): ?>
                    <div class="slide-item">
                        <div class="slide-preview">
                            <?php if($slide['type'] == 'video'): ?>
                                <video src="<?php echo htmlspecialchars($slide['file_path']); ?>" muted></video>
                                <span class="badge" style="background:var(--primary);"><i class="fas fa-video"></i> Video</span>
                            <?php else: ?>
                                <img src="<?php echo htmlspecialchars($slide['file_path']); ?>">
                                <span class="badge" style="background:var(--secondary);"><i class="fas fa-image"></i> Image</span>
                            <?php endif; ?>
                        </div>
                        <div class="slide-info" style="flex:1;">
                            <h4><?php echo htmlspecialchars($slide['heading'] ?: 'No Heading Provided'); ?></h4>
                            <p><?php echo htmlspecialchars(substr($slide['subtext'], 0, 70)) . '...'; ?></p>
                            <span style="background:var(--bg-main); padding:4px 10px; border-radius:20px; font-size:11px; font-weight:600; color:var(--primary); border:1px solid var(--border-color);">
                                <i class="fas fa-sort-numeric-down"></i> Sort Order: <?php echo $slide['sort_order']; ?>
                            </span>
                        </div>
                        <div class="action-icons">
                            <button type="button" class="action-btn" title="Edit Slide" onclick='editSlide(<?php echo json_encode($slide); ?>)'><i class="fas fa-edit"></i></button>
                            <a href="?delete=<?php echo $slide['id']; ?>" class="action-btn" title="Delete Slide" onclick="return confirm('Are you sure you want to permanently delete this slide?')"><i class="fas fa-trash-alt"></i></a>
                        </div>
                    </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
    </main>

    <script>
        // --- Dropdown Logic ---
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

        // --- Populates the form for editing ---
        function editSlide(data) {
            document.getElementById('formTitle').innerText = 'Edit Slide';
            document.getElementById('submitBtn').innerHTML = '<i class="fas fa-sync-alt"></i> Update Slide';
            document.getElementById('cancelBtn').style.display = 'block';
            
            document.getElementById('slide_id').value = data.id;
            document.getElementById('heading').value = data.heading;
            document.getElementById('subtext').value = data.subtext;
            document.getElementById('button_text').value = data.button_text;
            document.getElementById('sort_order').value = data.sort_order;
            
            // Keep track of existing file so we don't lose it if user saves without uploading new one
            document.getElementById('current_file').value = data.file_path;
            document.getElementById('current_type').value = data.type;
            document.getElementById('fileNameDisplay').innerText = data.file_path.split('/').pop();
            
            window.scrollTo({ top: 0, behavior: 'smooth' });
        }

        function resetForm() {
            document.getElementById('formTitle').innerText = 'Add New Slide';
            document.getElementById('submitBtn').innerHTML = '<i class="fas fa-save"></i> Save Slide';
            document.getElementById('cancelBtn').style.display = 'none';
            
            document.getElementById('slideForm').reset();
            document.getElementById('slide_id').value = '';
            document.getElementById('current_file').value = '';
            document.getElementById('current_type').value = '';
            document.getElementById('fileNameDisplay').innerText = 'None';
        }

        // --- Video Duration Check ---
        document.getElementById('fileInput').addEventListener('change', function(e) {
            var file = e.target.files[0];
            if (file && file.type.startsWith('video/')) {
                var vid = document.createElement('video');
                vid.preload = 'metadata';
                vid.onloadedmetadata = function() {
                    window.URL.revokeObjectURL(vid.src);
                    if (vid.duration > 30) {
                        alert("⚠️ Video is too long (" + vid.duration.toFixed(1) + "s). Max allowed is 30s.");
                        e.target.value = ""; // Clear input
                    }
                }
                vid.src = URL.createObjectURL(file);
            }
        });
    </script>
</body>
</html>