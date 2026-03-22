<?php
try {
    include 'session_check.php';
    include 'db_connect.php';

    // --- ACCESS CONTROL FIX ---
    if (!isset($_SESSION['role']) || !in_array(strtolower(trim($_SESSION['role'])), ['admin', 'super_admin'])) {
        header("Location: index.php");
        exit();
    }

    $local_conn = $conn;

    // --- 1. AUTO-CREATE MISSING TABLES ---
    $local_conn->query("CREATE TABLE IF NOT EXISTS `recently_deleted` (
      `id` int(11) NOT NULL AUTO_INCREMENT,
      `order_id` int(11) NOT NULL,
      `fullname` varchar(255) NOT NULL,
      `contact` varchar(20) NOT NULL,
      `address` text NOT NULL,
      `cart` longtext NOT NULL,
      `total` decimal(10,2) NOT NULL,
      `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
      `status` varchar(50) NOT NULL,
      `deleted_at` timestamp NOT NULL DEFAULT current_timestamp(),
      PRIMARY KEY (`id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");

    $local_conn->query("CREATE TABLE IF NOT EXISTS `recently_deleted_products` (
      `id` int(11) NOT NULL AUTO_INCREMENT,
      `original_id` int(11) NOT NULL,
      `name` varchar(255) NOT NULL,
      `price` decimal(10,2) NOT NULL,
      `stock` int(11) NOT NULL,
      `image` varchar(255) NOT NULL,
      `category` varchar(100) NOT NULL,
      `rating` int(11) DEFAULT 5,
      `deleted_at` timestamp NOT NULL DEFAULT current_timestamp(),
      PRIMARY KEY (`id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");

    $local_conn->query("CREATE TABLE IF NOT EXISTS `recently_deleted_users` (
      `id` int(11) NOT NULL AUTO_INCREMENT,
      `original_id` int(11) NOT NULL,
      `fullname` varchar(255) DEFAULT NULL,
      `username` varchar(255) NOT NULL,
      `email` varchar(255) NOT NULL,
      `role` varchar(50) NOT NULL DEFAULT 'user',
      `deleted_at` timestamp NOT NULL DEFAULT current_timestamp(),
      PRIMARY KEY (`id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");

    // --- 2. DATA FOR HEADER ICONS ---
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

    // --- 3. HELPER FUNCTION FOR DATA FETCHING ---
    function fetch_deleted_data($conn, $table) {
        $check = $conn->query("SHOW TABLES LIKE '$table'");
        if (!$check || $check->num_rows == 0) return null;

        $cols = $conn->query("SHOW COLUMNS FROM `$table` LIKE 'deleted_at'");
        $order_col = ($cols && $cols->num_rows > 0) ? 'deleted_at' : 'id';

        if ($order_col === 'id') {
            $cols_id = $conn->query("SHOW COLUMNS FROM `$table` LIKE 'id'");
            if (!$cols_id || $cols_id->num_rows == 0) {
                return $conn->query("SELECT * FROM `$table` LIMIT 50");
            }
        }

        $sql = "SELECT * FROM `$table` ORDER BY `$order_col` DESC LIMIT 50";
        return $conn->query($sql);
    }

    // --- 4. FETCH ALL BINS ---
    $orders_error = $products_error = $users_error = "";

    $deleted_orders = fetch_deleted_data($local_conn, 'recently_deleted');
    if (!$deleted_orders) $orders_error = "Table missing or error";

    $deleted_products = fetch_deleted_data($local_conn, 'recently_deleted_products');
    if (!$deleted_products) $products_error = "Table missing or error";

    $deleted_users = fetch_deleted_data($local_conn, 'recently_deleted_users');
    if (!$deleted_users) $users_error = "Table missing or error";

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
    <title>Recycle Bin - Cafe Emmanuel</title>
    
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
            color: var(--secondary) !important; /* Forced Dark Brown */
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

        /* --- BULLETPROOF TABS --- */
        .tabs { 
            display: flex; gap: 15px; margin-bottom: 25px; 
            border-bottom: 2px solid var(--border-color); position: relative; z-index: 1;
        }
        .tab-btn {
            padding: 12px 25px !important; 
            cursor: pointer !important; 
            border: none !important; 
            background: transparent !important;
            font-family: var(--font-body) !important; 
            font-weight: 700 !important; 
            font-size: 14px !important;
            color: #756358 !important; /* FORCED VISIBLE COLOR (Muted Brown) */
            transition: all 0.3s ease !important; 
            position: relative !important;
            letter-spacing: 0.5px !important; 
            text-transform: uppercase !important; 
            outline: none !important;
        }
        .tab-btn::after {
            content: ''; position: absolute; bottom: -2px; left: 0; width: 100%;
            height: 2px; background-color: var(--primary); transform: scaleX(0); transition: transform 0.3s ease;
        }
        .tab-btn:hover { color: #2C1E16 !important; } /* Dark Brown */
        .tab-btn.active { color: #A05E44 !important; } /* Caramel */
        .tab-btn.active::after { transform: scaleX(1); }

        /* --- CONTENT CARD --- */
        .card {
            background: var(--bg-card); padding: 30px; border-radius: 16px;
            box-shadow: var(--shadow-soft); border: 1px solid rgba(160, 94, 68, 0.05);
            position: relative; z-index: 1;
        }

        /* JS TAB HANDLING */
        .tab-content { display: none; }
        .tab-content.active { display: block; animation: fadeIn 0.4s ease; }
        @keyframes fadeIn { from { opacity: 0; transform: translateY(10px); } to { opacity: 1; transform: translateY(0); } }

        /* --- BULLETPROOF TABLE STYLING --- */
        table { width: 100%; border-collapse: collapse; }
        th {
            text-align: left !important; 
            padding: 15px !important; 
            font-size: 12px !important; 
            text-transform: uppercase !important;
            color: #756358 !important; /* FORCED VISIBLE COLOR */
            font-weight: 700 !important;
            border-bottom: 2px solid var(--border-color) !important; 
            letter-spacing: 0.5px !important;
        }
        td { 
            padding: 18px 15px !important; 
            border-bottom: 1px dashed var(--border-color) !important; 
            font-size: 14px !important; 
            vertical-align: middle !important; 
            color: #3A2B24 !important; /* FORCED VISIBLE COLOR */
        }
        tr:hover td { background-color: #FDFBF7 !important; }
        
        td strong { color: #2C1E16 !important; /* Forced Espresso */ }
        td span { color: #756358 !important; /* Forced Muted */ }
        
        .empty-state { 
            padding: 60px 20px !important; 
            text-align: center !important; 
            color: #756358 !important; /* FORCED VISIBLE COLOR */
            font-weight: 600 !important;
            font-size: 15px !important;
            border: 2px dashed var(--border-color) !important; 
            border-radius: 12px !important; 
            background: var(--bg-main) !important;
        }
        
        .error-state { 
            padding: 20px !important; color: #c62828 !important; background: #ffebee !important; 
            border-radius: 8px !important; margin-bottom: 15px !important; 
            border: 1px solid rgba(198,40,40,0.2) !important; font-weight: 600 !important;
        }

        /* --- BULLETPROOF SOLID BUTTON STYLES --- */
        .action-icons { display: flex; gap: 10px; }
        .action-icons form { margin: 0 !important; padding: 0 !important; }
        
        .btn-solid {
            padding: 8px 16px !important;
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
            <h1>Recycle Bin</h1>
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

        <div class="tabs">
            <button class="tab-btn active" onclick="openTab(event, 'orders')"><i class="fas fa-box" style="margin-right:5px;"></i> Deleted Orders</button>
            <button class="tab-btn" onclick="openTab(event, 'products')"><i class="fas fa-coffee" style="margin-right:5px;"></i> Deleted Products</button>
            <button class="tab-btn" onclick="openTab(event, 'users')"><i class="fas fa-users" style="margin-right:5px;"></i> Deleted Users</button>
        </div>

        <div class="card">
            <div id="orders" class="tab-content active">
                <?php if (!empty($orders_error)): ?>
                    <div class="error-state"><i class="fas fa-exclamation-triangle"></i> Error: <?php echo htmlspecialchars($orders_error); ?></div>
                <?php else: ?>
                    <div style="overflow-x: auto;">
                        <table>
                            <thead>
                                <tr>
                                    <th>Order ID</th>
                                    <th>Customer</th>
                                    <th>Total</th>
                                    <th>Deleted At</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if ($deleted_orders && $deleted_orders->num_rows > 0): ?>
                                    <?php while($row = $deleted_orders->fetch_assoc()): ?>
                                    <tr>
                                        <td><strong>#<?php echo str_pad($row['order_id'] ?? 0, 4, '0', STR_PAD_LEFT); ?></strong></td>
                                        <td><strong><?php echo htmlspecialchars($row['fullname'] ?? 'Unknown'); ?></strong></td>
                                        <td><strong style="color:#A05E44 !important; font-family:var(--font-heading) !important; font-size:1.1rem !important;">₱<?php echo number_format($row['total'] ?? 0, 2); ?></strong></td>
                                        <td><span><i class="far fa-clock" style="margin-right:5px;"></i><?php echo isset($row['deleted_at']) ? date("M d, Y", strtotime($row['deleted_at'])) : 'N/A'; ?></span></td>
                                        <td>
                                            <div class="action-icons">
                                                <form action="restore_delete.php" method="POST">
                                                    <input type="hidden" name="id" value="<?php echo $row['id']; ?>">
                                                    <input type="hidden" name="action" value="restore">
                                                    <button type="submit" class="btn-solid btn-solid-green" title="Restore"><i class="fas fa-undo"></i> Restore</button>
                                                </form>
                                                <form action="recently_deleted_action.php" method="POST" onsubmit="return confirm('Permanent delete cannot be undone. Proceed?');">
                                                    <input type="hidden" name="id" value="<?php echo $row['id']; ?>">
                                                    <input type="hidden" name="action" value="permanent_delete">
                                                    <button type="submit" class="btn-solid btn-solid-red" title="Delete Permanently"><i class="fas fa-trash-alt"></i> Delete</button>
                                                </form>
                                            </div>
                                        </td>
                                    </tr>
                                    <?php endwhile; ?>
                                <?php else: ?>
                                    <tr><td colspan="5"><div class="empty-state"><i class="fas fa-box-open fa-3x" style="color:var(--border-color); margin-bottom:15px; display:block;"></i>No deleted orders found in the bin.</div></td></tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>

            <div id="products" class="tab-content">
                <?php if (!empty($products_error)): ?>
                    <div class="error-state"><i class="fas fa-exclamation-triangle"></i> Error: <?php echo htmlspecialchars($products_error); ?></div>
                <?php else: ?>
                    <div style="overflow-x: auto;">
                        <table>
                            <thead>
                                <tr>
                                    <th>Product Name</th>
                                    <th>Category</th>
                                    <th>Price</th>
                                    <th>Deleted At</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if ($deleted_products && $deleted_products->num_rows > 0): ?>
                                    <?php while($row = $deleted_products->fetch_assoc()): ?>
                                    <tr>
                                        <td><strong><?php echo htmlspecialchars($row['name'] ?? 'Unknown'); ?></strong></td>
                                        <td><span style="background:rgba(212, 163, 115, 0.15) !important; color:#B37D4D !important; padding:4px 10px; border-radius:4px; font-size:11px; font-weight:600; text-transform:uppercase; letter-spacing:0.5px;"><?php echo htmlspecialchars($row['category'] ?? 'Uncategorized'); ?></span></td>
                                        <td><strong style="color:#A05E44 !important; font-family:var(--font-heading) !important; font-size:1.1rem !important;">₱<?php echo number_format($row['price'] ?? 0, 2); ?></strong></td>
                                        <td><span><i class="far fa-clock" style="margin-right:5px;"></i><?php echo isset($row['deleted_at']) ? date("M d, Y", strtotime($row['deleted_at'])) : 'N/A'; ?></span></td>
                                        <td>
                                            <div class="action-icons">
                                                <form action="restore_delete_product.php" method="POST">
                                                    <input type="hidden" name="id" value="<?php echo $row['id']; ?>">
                                                    <input type="hidden" name="action" value="restore">
                                                    <button type="submit" class="btn-solid btn-solid-green" title="Restore Product"><i class="fas fa-undo"></i> Restore</button>
                                                </form>
                                                <form action="product_actions.php" method="POST" onsubmit="return confirm('Delete product permanently?');">
                                                    <input type="hidden" name="id" value="<?php echo $row['id']; ?>">
                                                    <input type="hidden" name="action" value="permanent_delete">
                                                    <button type="submit" class="btn-solid btn-solid-red" title="Delete Permanently"><i class="fas fa-trash-alt"></i> Delete</button>
                                                </form>
                                            </div>
                                        </td>
                                    </tr>
                                    <?php endwhile; ?>
                                <?php else: ?>
                                    <tr><td colspan="5"><div class="empty-state"><i class="fas fa-coffee fa-3x" style="color:var(--border-color); margin-bottom:15px; display:block;"></i>No deleted products found in the bin.</div></td></tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>

            <div id="users" class="tab-content">
                <?php if (!empty($users_error)): ?>
                    <div class="error-state"><i class="fas fa-exclamation-triangle"></i> Error: <?php echo htmlspecialchars($users_error); ?></div>
                <?php else: ?>
                    <div style="overflow-x: auto;">
                        <table>
                            <thead>
                                <tr>
                                    <th>Name / Username</th>
                                    <th>Email</th>
                                    <th>Role</th>
                                    <th>Deleted At</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if ($deleted_users && $deleted_users->num_rows > 0): ?>
                                    <?php while($row = $deleted_users->fetch_assoc()): 
                                        $displayName = !empty($row['fullname']) ? $row['fullname'] : (!empty($row['username']) ? $row['username'] : $row['email']);
                                    ?>
                                    <tr>
                                        <td><strong style="font-size: 15px;"><?php echo htmlspecialchars($displayName); ?></strong></td>
                                        <td><span><i class="fas fa-envelope" style="margin-right:5px; font-size:11px;"></i><?php echo htmlspecialchars($row['email'] ?? 'No Email'); ?></span></td>
                                        <td>
                                            <?php 
                                            $roleClass = (isset($row['role']) && $row['role'] === 'super_admin') ? 'background:rgba(44, 30, 22, 0.1) !important; color:#2C1E16 !important;' : 'background:rgba(160, 94, 68, 0.1) !important; color:#A05E44 !important;';
                                            if (isset($row['role']) && strtolower($row['role']) === 'user') { $roleClass = 'background:#e8f5e9 !important; color:#2e7d32 !important;'; }
                                            ?>
                                            <span style="<?php echo $roleClass; ?> padding:4px 10px; border-radius:4px; font-size:11px; font-weight:600; text-transform:uppercase; letter-spacing:0.5px;"><?php echo htmlspecialchars(str_replace('_', ' ', $row['role'] ?? 'User')); ?></span>
                                        </td>
                                        <td><span><i class="far fa-clock" style="margin-right:5px;"></i><?php echo isset($row['deleted_at']) ? date("M d, Y", strtotime($row['deleted_at'])) : 'N/A'; ?></span></td>
                                        <td>
                                            <div class="action-icons">
                                                <form action="user_restore_actions.php" method="POST">
                                                    <input type="hidden" name="id" value="<?php echo $row['id']; ?>">
                                                    <input type="hidden" name="action" value="restore">
                                                    <button type="submit" class="btn-solid btn-solid-green" title="Restore User"><i class="fas fa-undo"></i> Restore</button>
                                                </form>
                                                <form action="user_restore_actions.php" method="POST" onsubmit="return confirm('Delete user account permanently?');">
                                                    <input type="hidden" name="id" value="<?php echo $row['id']; ?>">
                                                    <input type="hidden" name="action" value="permanent_delete">
                                                    <button type="submit" class="btn-solid btn-solid-red" title="Delete Permanently"><i class="fas fa-trash-alt"></i> Delete</button>
                                                </form>
                                            </div>
                                        </td>
                                    </tr>
                                    <?php endwhile; ?>
                                <?php else: ?>
                                    <tr><td colspan="5"><div class="empty-state"><i class="fas fa-users fa-3x" style="color:var(--border-color); margin-bottom:15px; display:block;"></i>No deleted user accounts found in the bin.</div></td></tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </main>

    <script>
        function openTab(evt, tabName) {
            // 1. Hide all contents
            var i, tabcontent, tablinks;
            tabcontent = document.getElementsByClassName("tab-content");
            for (i = 0; i < tabcontent.length; i++) { 
                tabcontent[i].style.display = "none"; 
                tabcontent[i].classList.remove("active");
            }
            
            // 2. Remove active state from buttons
            tablinks = document.getElementsByClassName("tab-btn");
            for (i = 0; i < tablinks.length; i++) { 
                tablinks[i].classList.remove("active"); 
            }
            
            // 3. Show current content and activate button
            document.getElementById(tabName).style.display = "block";
            document.getElementById(tabName).classList.add("active");
            evt.currentTarget.classList.add("active");
        }

        // Initialize first tab
        document.addEventListener('DOMContentLoaded', function() {
            document.getElementById("orders").style.display = "block";

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
                        document.querySelectorAll('.dropdown-menu').forEach(m => m.classList.remove('show'));
                        const menu = document.getElementById(t.menu);
                        if(menu) menu.classList.add('show');
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