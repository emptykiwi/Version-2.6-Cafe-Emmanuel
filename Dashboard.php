<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
include 'session_check.php';

// --- Database Connection ---
include 'db_connect.php';
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// --- Dynamic Metrics ---
$total_orders_result = $conn->query("SELECT COUNT(*) as count FROM cart");
$total_orders = $total_orders_result ? $total_orders_result->fetch_assoc()['count'] : 0;

$total_delivered_result = $conn->query("SELECT COUNT(*) as count FROM cart WHERE status = 'Delivered'");
$total_delivered = $total_delivered_result ? $total_delivered_result->fetch_assoc()['count'] : 0;

$total_revenue_result = $conn->query("SELECT SUM(total) as sum FROM cart WHERE status = 'Delivered'");
$total_revenue = $total_revenue_result ? $total_revenue_result->fetch_assoc()['sum'] : 0;

if ($total_revenue === null) {
    $total_revenue = 0;
}

$total_canceled_result = $conn->query("SELECT COUNT(*) as count FROM cart WHERE status = 'Cancelled'");
$total_canceled = $total_canceled_result ? $total_canceled_result->fetch_assoc()['count'] : 0;

// --- DATA FOR MESSAGE ICON ---
$inquiry_count_result = $conn->query("SELECT COUNT(*) as count FROM inquiries WHERE status = 'new'");
$unread_inquiries = $inquiry_count_result ? $inquiry_count_result->fetch_assoc()['count'] : 0;

$recent_inquiries_result = $conn->query("SELECT * FROM inquiries WHERE status = 'new' ORDER BY received_at DESC LIMIT 5");
$recent_messages = [];
if ($recent_inquiries_result) {
    while ($row = $recent_inquiries_result->fetch_assoc()) {
        $recent_messages[] = $row;
    }
}

// --- Fetch All Orders ---
$query = "
    SELECT 
        c.*, 
        u.fullname AS customer_name,
        u.email AS customer_email
    FROM cart c
    LEFT JOIN users u ON c.user_id = u.id
    ORDER BY c.id DESC
";
$all_orders_result = $conn->query($query);
$all_orders = [];
while ($row = $all_orders_result->fetch_assoc()) {
    $all_orders[] = $row;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <link rel="icon" type="image/png" href="Logo_Brand.png">
  <title>Dashboard - Cafe Emmanuel</title>
  
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
        margin-left: 260px; /* Space for the fixed sidebar */
        width: calc(100% - 260px); 
        height: 100vh;
        overflow-y: auto;
        padding: 40px;
        transition: all 0.3s ease;
    }

    /* FIX: Added relative positioning and high z-index to prevent modals from slipping behind dashboard metrics */
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
    
    .header-icons { 
        display: flex; 
        align-items: center; 
        gap: 20px;
    }
    
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

    /* FIX: Explicitly style the <i> tags so they are visible */
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

    /* FIX: Change icon color to white on hover so it pops against the primary background */
    .icon-container:hover i {
        color: #fff;
    }

    .header-badge {
        position: absolute;
        top: -5px;
        right: -5px;
        background-color: var(--primary);
        color: white;
        border-radius: 50%;
        width: 20px;
        height: 20px;
        font-size: 11px;
        display: flex;
        align-items: center;
        justify-content: center;
        font-weight: bold;
        border: 2px solid var(--bg-card);
    }

    .header-icons img { 
        width: 45px; 
        height: 45px; 
        border-radius: 50%; 
        object-fit: cover;
        border: 2px solid var(--primary);
        padding: 2px;
        background: #fff;
    }

    /* --- Metrics --- */
    .dashboard-metrics { 
        display: grid; 
        grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); 
        gap: 25px; 
        margin-bottom: 40px; 
        position: relative;
        z-index: 1; /* Keep this lower than the header */
    }
    
    .metric { 
        background: var(--bg-card); 
        padding: 25px; 
        border-radius: 16px; 
        box-shadow: var(--shadow-soft);
        border: 1px solid rgba(160, 94, 68, 0.05);
        border-bottom: 4px solid transparent;
        transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        position: relative;
        overflow: hidden;
    }
    
    .metric::after {
        content: '';
        position: absolute;
        top: -20px;
        right: -20px;
        width: 100px;
        height: 100px;
        background: radial-gradient(circle, rgba(212, 163, 115, 0.1) 0%, rgba(255,255,255,0) 70%);
        border-radius: 50%;
    }

    .metric:hover { 
        transform: translateY(-5px);
        border-bottom-color: var(--primary);
        box-shadow: var(--shadow-hover);
    }

    .metric-header { 
        display: flex; 
        justify-content: space-between; 
        align-items: center;
        margin-bottom: 15px; 
        position: relative;
        z-index: 2;
    }
    .metric-header p { 
        color: #3a2b24; 
        font-weight: 600; 
        font-size: 13px; 
        text-transform: uppercase;
        letter-spacing: 1px;
    }
    
    .metric-icon-box {
        width: 40px;
        height: 40px;
        border-radius: 10px;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 1.2rem;
    }
    
    .icon-orders { background: rgba(160, 94, 68, 0.1); color: var(--primary); }
    .icon-revenue { background: rgba(212, 163, 115, 0.15); color: #B37D4D; }
    .icon-delivered { background: rgba(46, 125, 50, 0.1); color: #2e7d32; }
    .icon-canceled { background: rgba(198, 40, 40, 0.1); color: #c62828; }

    .metric-body h3 { 
        font-family: var(--font-heading);
        font-size: 32px; 
        color: var(--secondary); 
        position: relative;
        z-index: 2;
    }

    /* --- Main Grid --- */
    .dashboard-main-content { 
        display: grid; 
        grid-template-columns: 1.5fr 1fr; 
        gap: 30px; 
        position: relative;
        z-index: 1; /* Keep this lower than the header */
    }
    
    .card {
        background: var(--bg-card);
        border-radius: 16px;
        padding: 30px;
        border: 1px solid rgba(160, 94, 68, 0.05);
        box-shadow: var(--shadow-soft);
    }

    .card-header h2 { 
        font-family: var(--font-heading);
        font-size: 22px; 
        color: var(--secondary);
        margin-bottom: 25px;
    }

    .search-bar input { 
        width: 100%; 
        padding: 14px 20px; 
        border-radius: 50px; 
        border: 1px solid var(--border-color); 
        background: var(--bg-main);
        color: var(--text-dark);
        font-family: var(--font-body);
        font-size: 0.95rem;
        margin-bottom: 25px;
        transition: all 0.3s ease;
    }
    
    .search-bar input:focus {
        outline: none;
        border-color: var(--primary);
        box-shadow: 0 0 0 4px rgba(160, 94, 68, 0.1);
        background: #fff;
    }

    .orders-list { 
        max-height: 500px; 
        overflow-y: auto; 
        padding-right: 10px;
    }

    .orders-list::-webkit-scrollbar { width: 6px; }
    .orders-list::-webkit-scrollbar-track { background: var(--bg-main); border-radius: 10px; }
    .orders-list::-webkit-scrollbar-thumb { background: var(--border-color); border-radius: 10px; }
    .orders-list::-webkit-scrollbar-thumb:hover { background: var(--accent); }

    .order-card { 
        padding: 20px;
        border: 1px solid var(--border-color);
        border-radius: 12px;
        margin-bottom: 15px;
        cursor: pointer;
        transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        background: #fff;
    }
    .order-card:hover { 
        border-color: var(--accent); 
        transform: translateY(-2px);
        box-shadow: 0 5px 15px rgba(44, 30, 22, 0.05);
    }
    .order-card.active { 
        background: #FDFBF7; 
        border-color: var(--primary); 
        box-shadow: 0 0 0 1px var(--primary);
    }

    .status-tag { 
        padding: 5px 12px; 
        border-radius: 20px; 
        font-size: 11px; 
        font-weight: 700; 
        text-transform: uppercase;
        letter-spacing: 0.5px;
    }
    .status-delivered { background: #e8f5e9; color: #2e7d32; border: 1px solid rgba(46,125,50,0.2); }
    .status-pending { background: rgba(212, 163, 115, 0.15); color: #B37D4D; border: 1px solid rgba(212, 163, 115, 0.3); }
    .status-cancelled { background: #ffebee; color: #c62828; border: 1px solid rgba(198,40,40,0.2); }

    /* Dropdowns */
    .dropdown-menu {
        display: none;
        position: absolute;
        right: 0;
        top: 60px; /* Pushed slightly further down so it clears the icon cleanly */
        background: var(--bg-card);
        width: 320px;
        box-shadow: 0 15px 35px rgba(44, 30, 22, 0.15);
        border-radius: 12px;
        z-index: 1001; /* Renders above everything else */
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

    /* Responsive */
    @media (max-width: 1200px) {
        .dashboard-main-content { grid-template-columns: 1fr; }
        .main-content { margin-left: 0; width: 100%; }
    }

    /* Billing details */
    .bill-details { 
        background: var(--bg-main); 
        padding: 20px; 
        border-radius: 12px; 
        margin-top: 25px; 
        border: 1px solid var(--border-color);
    }
    .bill-row { 
        display: flex; 
        justify-content: space-between; 
        padding: 8px 0; 
        font-size: 14px; 
        color: var(--text-dark);
    }
    .total-bill { 
        border-top: 1px dashed var(--border-color); 
        padding-top: 15px; 
        margin-top: 10px;
        font-weight: 700; 
        color: var(--primary); 
        font-size: 20px; 
        font-family: var(--font-heading);
    }
  </style>
</head>
<body>

    <?php include 'admin_sidebar.php'; ?>

    <main class="main-content">
      <header class="main-header">
        <h1>Dashboard Overview</h1>
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

      <section class="dashboard-metrics">
        <div class="metric">
            <div class="metric-header">
                <p>Total Orders</p>
                <div class="metric-icon-box icon-orders"><i class="fas fa-box"></i></div>
            </div>
            <div class="metric-body"><h3><?php echo number_format($total_orders); ?></h3></div>
        </div>
        <div class="metric">
            <div class="metric-header">
                <p>Total Revenue</p>
                <div class="metric-icon-box icon-revenue"><i class="fas fa-peso-sign"></i></div>
            </div>
            <div class="metric-body"><h3>₱<?php echo number_format($total_revenue, 2); ?></h3></div>
        </div>
        <div class="metric">
            <div class="metric-header">
                <p>Delivered</p>
                <div class="metric-icon-box icon-delivered"><i class="fas fa-check-circle"></i></div>
            </div>
            <div class="metric-body"><h3><?php echo number_format($total_delivered); ?></h3></div>
        </div>
        <div class="metric">
            <div class="metric-header">
                <p>Canceled</p>
                <div class="metric-icon-box icon-canceled"><i class="fas fa-times-circle"></i></div>
            </div>
            <div class="metric-body"><h3><?php echo number_format($total_canceled); ?></h3></div>
        </div>
      </section>  

      <div class="dashboard-main-content">
        <div class="card">
            <div class="card-header"><h2>Recent Orders</h2></div>
            <div class="search-bar">
                <input type="text" id="orderSearch" placeholder="Search by Order ID or Name...">
            </div>
            <div class="orders-list">
                <?php foreach ($all_orders as $order): 
                    $status_class = strtolower(str_replace(' ', '_', $order['status']));
                ?>
                <div class="order-card" data-order='<?php echo json_encode($order); ?>'>
                    <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:8px;">
                        <strong style="color:var(--primary); font-size:1.1rem;">#<?php echo str_pad($order['id'], 4, '0', STR_PAD_LEFT); ?></strong>
                        <span class="status-tag status-<?php echo $status_class; ?>"><?php echo $order['status']; ?></span>
                    </div>
                    <div style="font-size:14px; font-weight:500; color:var(--secondary);"><?php echo htmlspecialchars($order['fullname']); ?></div>
                    <div style="font-size:12px; margin-top:8px; color:#3a2b24;">
                        <i class="far fa-clock" style="margin-right:5px;"></i> <?php echo date("F j, Y - g:i A", strtotime($order['created_at'])); ?>
                    </div>
                </div>
                <?php endforeach; ?>
                <?php if(empty($all_orders)): ?>
                    <p style="text-align:center; color:#3a2b24; padding:20px;">No orders found.</p>
                <?php endif; ?>
            </div>
        </div>

        <div class="card" id="details-view">
            <div class="card-header"><h2>Order Details</h2></div>
            <div id="details-content" style="display:none;">
                <h3 id="det-name" style="font-family:var(--font-heading); color:var(--secondary); font-size:24px; margin-bottom:5px;">Customer Name</h3>
                <p id="det-address" style="font-size:14px; color:#3a2b24; margin-bottom:20px; line-height:1.6;"></p>
                
                <h4 style="font-size:14px; color:var(--secondary); text-transform:uppercase; letter-spacing:1px; margin-bottom:10px;">Order Summary</h4>
                <div class="bill-details">
                    <div id="det-items"></div>
                    <div class="bill-row total-bill">
                        <span>Total Amount</span>
                        <span id="det-total">₱0.00</span>
                    </div>
                </div>
            </div>
            <div id="details-placeholder" style="text-align:center; padding:80px 20px; color:var(--border-color);">
                <i class="fas fa-receipt fa-4x" style="color:var(--border-color); margin-bottom:20px;"></i>
                <p style="color:#3a2b24; font-size:1.1rem;">Select an order from the list<br>to view the complete details.</p>
            </div>
        </div>
      </div>
    </main>

  <script>
    document.addEventListener('DOMContentLoaded', function() {
        // Dropdown Logic
        const toggles = [
            { id: 'messageIcon', menu: 'messageDropdown' },
            { id: 'notificationBell', menu: 'notificationDropdown' },
            { id: 'profileIcon', menu: 'profileDropdown' }
        ];

        toggles.forEach(t => {
            document.getElementById(t.id).addEventListener('click', (e) => {
                e.stopPropagation();
                document.querySelectorAll('.dropdown-menu').forEach(m => {
                    if(m.id !== t.menu) m.classList.remove('show');
                });
                document.getElementById(t.menu).classList.toggle('show');
            });
        });

        document.addEventListener('click', () => {
            document.querySelectorAll('.dropdown-menu').forEach(m => m.classList.remove('show'));
        });

        // Order Selection Logic
        const orderCards = document.querySelectorAll('.order-card');
        const detContent = document.getElementById('details-content');
        const detPlaceholder = document.getElementById('details-placeholder');

        orderCards.forEach(card => {
            card.addEventListener('click', () => {
                orderCards.forEach(c => c.classList.remove('active'));
                card.classList.add('active');
                
                const data = JSON.parse(card.dataset.order);
                const items = JSON.parse(data.cart || '[]');

                detPlaceholder.style.display = 'none';
                detContent.style.display = 'block';

                document.getElementById('det-name').textContent = data.fullname;
                document.getElementById('det-address').innerHTML = `<i class="fas fa-map-marker-alt" style="margin-right:8px; color:var(--primary);"></i>` + data.address;
                document.getElementById('det-total').textContent = '₱' + parseFloat(data.total).toFixed(2);

                let itemsHtml = '';
                items.forEach(item => {
                    itemsHtml += `<div class="bill-row">
                        <span style="font-weight:500;">
                            <span style="color:var(--primary); margin-right:8px;">${item.quantity}x</span> 
                            ${item.name}
                        </span>
                        <span>₱${(item.quantity * item.price).toFixed(2)}</span>
                    </div>`;
                });
                document.getElementById('det-items').innerHTML = itemsHtml;
            });
        });

        // Search Logic
        document.getElementById('orderSearch').addEventListener('keyup', function() {
            const val = this.value.toLowerCase();
            orderCards.forEach(c => {
                c.style.display = c.innerText.toLowerCase().includes(val) ? 'block' : 'none';
            });
        });
    });
  </script>
</body>
</html>