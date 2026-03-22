<?php
include 'session_check.php';
include 'db_connect.php';

if ($conn->connect_error) {
  die("Connection failed: " . $conn->connect_error);
}

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

$sql = "SELECT * FROM cart ORDER BY id DESC";
$result = $conn->query($sql);

function getInitials($name) {
    $words = explode(" ", $name);
    $initials = "";
    foreach ($words as $w) { if (!empty($w)) { $initials .= strtoupper($w[0]); } }
    return substr($initials, 0, 2);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0"/>
<link rel="icon" type="image/png" href="Logo_Brand.png">
<title>Orders - Cafe Emmanuel</title>

<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" />
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Playfair+Display:ital,wght@0,500;0,700;1,500&family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
<script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js"></script>

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

    .orders-table {
        width: 100%;
        border-collapse: collapse;
    }

    .orders-table th {
        text-align: left;
        padding: 15px;
        font-size: 12px;
        text-transform: uppercase;
        color: #3a2b24;
        border-bottom: 2px solid var(--border-color);
        letter-spacing: 0.5px;
    }

    .orders-table td {
        padding: 18px 15px;
        border-bottom: 1px dashed var(--border-color);
        font-size: 14px;
        vertical-align: middle;
        color: var(--text-dark);
    }

    .orders-table tr:hover td { background-color: #FDFBF7; }

    .customer-cell { display: flex; align-items: center; gap: 15px; }
    .avatar { 
        width: 40px; height: 40px; border-radius: 50%; 
        background-color: rgba(160, 94, 68, 0.1); 
        color: var(--primary);
        display: flex; align-items: center; justify-content: center; 
        font-weight: 700; font-size: 13px;
        border: 1px solid rgba(160, 94, 68, 0.2);
    }

    /* --- STATUS PILLS --- */
    .status-pill { 
        padding: 5px 12px; border-radius: 20px; font-size: 11px; font-weight: 700; 
        text-transform: uppercase; display: inline-block; letter-spacing: 0.5px;
        border: 1px solid transparent;
    }
    .status-pending { background: rgba(212, 163, 115, 0.15); color: #B37D4D; border-color: rgba(212, 163, 115, 0.3); }
    .status-confirmed, .status-processing { background: rgba(44, 30, 22, 0.1); color: var(--secondary); border-color: rgba(44, 30, 22, 0.2); }
    .status-delivered, .status-completed { background: #e8f5e9; color: #2e7d32; border-color: rgba(46,125,50,0.2); }
    .status-cancelled { background: #ffebee; color: #c62828; border-color: rgba(198,40,40,0.2); }

    /* --- ACTION BUTTONS --- */
    .action-icons { display: flex; gap: 8px; }
    .action-btn { 
        width: 36px; height: 36px; border-radius: 8px; border: 1px solid var(--border-color);
        display: flex; align-items: center; justify-content: center;
        cursor: pointer; transition: all 0.3s; background: var(--bg-card); color: #3a2b24;
    }
    .action-btn:hover { background: var(--primary); color: white; border-color: var(--primary); transform: translateY(-2px); box-shadow: 0 4px 10px rgba(160, 94, 68, 0.2); }
    
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
    
    @keyframes slideDown {
        from { opacity: 0; transform: translateY(-10px); }
        to { opacity: 1; transform: translateY(0); }
    }

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
        <h1>Orders Management</h1>
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
            <table class="orders-table">
              <thead>
                <tr>
                  <th>Order ID</th> 
                  <th>Customer</th> 
                  <th>Items</th> 
                  <th>Total</th> 
                  <th>Status</th> 
                  <th>Actions</th>
                </tr>
              </thead>
              <tbody>
                <?php if ($result && $result->num_rows > 0): ?>
                    <?php while ($row = $result->fetch_assoc()) : ?>
                      <?php
                        $cart_items = json_decode($row['cart'], true);
                        $status_raw = $row['status'] ?? 'Pending';
                        $status_class = strtolower(str_replace(' ', '_', $status_raw));
                        $row_data = htmlspecialchars(json_encode($row), ENT_QUOTES, 'UTF-8');
                      ?>
                      <tr>
                        <td>
                            <span style="font-weight:700; color:var(--primary); font-size:1.1rem;">#<?php echo str_pad($row['id'], 4, '0', STR_PAD_LEFT); ?></span><br>
                            <small style="color:#3a2b24; font-size:11px;"><i class="far fa-clock" style="margin-right:3px;"></i><?php echo date("M d, Y H:i", strtotime($row['created_at'])); ?></small>
                        </td>
                        <td>
                            <div class="customer-cell">
                                <div class="avatar"><?php echo getInitials($row['fullname']); ?></div>
                                <div>
                                    <div style="font-weight:600; color:var(--secondary);"><?php echo htmlspecialchars($row['fullname']); ?></div>
                                    <div style="font-size:12px; color:#3a2b24;"><i class="fas fa-phone-alt" style="margin-right:3px; font-size:10px;"></i><?php echo htmlspecialchars($row['contact']); ?></div>
                                </div>
                            </div>
                        </td>
                        <td>
                            <div style="font-size:13px; color:var(--text-dark); font-weight:500;">
                            <?php
                                if ($cart_items && is_array($cart_items)) {
                                    $count = 0;
                                    foreach($cart_items as $item) $count += ($item['quantity'] ?? 1);
                                    echo $count . " Items";
                                } else {
                                    echo "0 Items";
                                }
                            ?>
                            </div>
                        </td>
                        <td><strong style="color:var(--secondary); font-family:var(--font-heading); font-size:1.1rem;">₱<?php echo number_format($row['total'], 2); ?></strong></td>
                        <td><div class="status-pill status-<?php echo $status_class; ?>"><?php echo htmlspecialchars($status_raw); ?></div></td>
                        <td>
                          <div class="action-icons">
                            <button class="action-btn" onclick="printReceipt(this)" data-order='<?php echo $row_data; ?>' title="Print Receipt"><i class="fas fa-print"></i></button>
                            
                            <?php if ($status_class === 'pending') : ?>
                                <form method="POST" action="update_order.php" style="display:inline;">
                                    <input type="hidden" name="id" value="<?php echo $row['id']; ?>"><input type="hidden" name="action" value="accept">
                                    <button type="submit" class="action-btn" title="Confirm Order"><i class="fas fa-check"></i></button>
                                </form>
                            <?php elseif ($status_class === 'confirmed') : ?>
                                <form method="POST" action="update_order.php" style="display:inline;">
                                    <input type="hidden" name="id" value="<?php echo $row['id']; ?>"><input type="hidden" name="action" value="processing">
                                    <button type="submit" class="action-btn" title="Prepare Order"><i class="fas fa-utensils"></i></button>
                                </form>
                            <?php elseif ($status_class === 'processing') : ?>
                                <form method="POST" action="update_order.php" style="display:inline;">
                                    <input type="hidden" name="id" value="<?php echo $row['id']; ?>"><input type="hidden" name="action" value="out_for_delivery">
                                    <button type="submit" class="action-btn" title="Ship Order"><i class="fas fa-motorcycle"></i></button>
                                </form>
                            <?php elseif ($status_class === 'out_for_delivery') : ?>
                                <form method="POST" action="update_order.php" style="display:inline;">
                                    <input type="hidden" name="id" value="<?php echo $row['id']; ?>"><input type="hidden" name="action" value="completed">
                                    <button type="submit" class="action-btn" title="Mark as Delivered"><i class="fas fa-check-double"></i></button>
                                </form>
                            <?php endif; ?>

                            <form method="POST" action="update_order.php" onsubmit="return confirm('Are you sure you want to move this order to the recycle bin?');" style="display:inline;">
                                <input type="hidden" name="id" value="<?php echo $row['id']; ?>"><input type="hidden" name="action" value="delete">
                                <button type="submit" class="action-btn" title="Move to Trash"><i class="fas fa-trash-alt"></i></button>
                            </form>
                          </div>
                        </td>
                      </tr>
                    <?php endwhile; ?>
                <?php else: ?>
                    <tr><td colspan="6" style="text-align: center; padding: 40px; color:#3a2b24;">No orders found.</td></tr>
                <?php endif; ?>
              </tbody>
            </table>
        </div>
    </div>
  </main>
</div>

<script>
document.addEventListener("DOMContentLoaded", function() {
    // Dropdown Logic
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

function printReceipt(btn) {
    const orderData = JSON.parse(btn.getAttribute('data-order'));
    const items = JSON.parse(orderData.cart || '[]');
    let html = `<html><head><title>Receipt #${orderData.id}</title><style>
        body{font-family:'Courier New',monospace; padding:20px; text-align:center;}
        .brand{font-size:24px; font-weight:bold; margin-bottom:5px;}
        .sub-brand{font-size:12px; margin-bottom:15px;}
        .divider{border-top:1px dashed #000; margin:10px 0;}
        .item-row{display:flex; justify-content:space-between; font-size:13px; margin-bottom:5px;}
        .text-left{text-align:left;}
    </style></head><body>
        <div class="brand">CAFE EMMANUEL</div>
        <div class="sub-brand">Artisanal Coffee & Dining</div>
        <div class="divider"></div>
        <div class="text-left" style="font-size:13px;">
            <div><strong>Order #:</strong> ${String(orderData.id).padStart(4,'0')}</div>
            <div><strong>Customer:</strong> ${orderData.fullname}</div>
            <div><strong>Date:</strong> ${new Date(orderData.created_at).toLocaleString()}</div>
        </div>
        <div class="divider"></div>`;
    
    items.forEach(it => {
        html += `<div class="item-row"><span>${it.quantity}x ${it.name}</span><span>₱${(it.quantity * it.price).toFixed(2)}</span></div>`;
    });
    
    html += `<div class="divider"></div><div style="font-weight:bold; font-size:16px; display:flex; justify-content:space-between;"><span>TOTAL</span><span>₱${Number(orderData.total).toFixed(2)}</span></div>
        <div class="divider"></div>
        <div style="font-size:12px; margin-top:20px;">Thank you for your order!</div>
    </body></html>`;
    
    const win = window.open('','','width=400,height=600');
    win.document.write(html); win.document.close(); win.print();
}
</script>
</body>
</html>
<?php $conn->close(); ?>