<?php
session_start();
include 'config.php'; 

// Redirect to login if user is not authenticated
if (!isset($_SESSION['user_id'])) {
    header("Location: index.php");
    exit;
}

$user_id = $_SESSION['user_id'];

// Fetch the user's orders (Adjust column names if your DB schema is different)
$orders_query = "SELECT * FROM orders WHERE user_id = ? ORDER BY created_at DESC";
$stmt = $conn->prepare($orders_query);
if ($stmt) {
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <link rel="icon" type="image/png" href="Logo_Brand.png">
    <title>My Orders - Cafe Emmanuel</title>
    
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css" />
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:ital,wght@0,500;0,700;1,500&family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    
    <style>
        /* ================== ROOT VARIABLES ================== */
        :root {
            --primary: #A05E44;
            --primary-hover: #804832;
            --primary-glow: rgba(160, 94, 68, 0.4);
            --secondary: #2C1E16;
            --accent: #D4A373;
            --bg-main: #F8F4EE;
            --bg-card: #FFFFFF;
            --text-dark: #3A2B24;
            --text-muted: #756358;
            --font-heading: 'Playfair Display', serif;
            --font-body: 'Poppins', sans-serif;
            --nav-height-scrolled: 80px; 
            --radius-lg: 24px;
            --radius-md: 16px;
            --radius-sm: 8px;
            --shadow-soft: 0 10px 40px rgba(44, 30, 22, 0.06);
        }

        /* ================== GLOBAL STYLES ================== */
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { 
            font-family: var(--font-body); 
            color: var(--text-dark); 
            background-color: var(--bg-main); 
            line-height: 1.7; 
            overflow-x: hidden; 
            /* Add padding so content isn't hidden behind the fixed navbar */
            padding-top: var(--nav-height-scrolled); 
        }
        .container { max-width: 1280px; margin: 0 auto; padding: 0 24px; position: relative; z-index: 2; }
        a { text-decoration: none; color: inherit; transition: all 0.3s ease; }
        ul { list-style: none; }

        /* ================== NAVBAR (Solid Style for Internal Pages) ================== */
        .header { 
            position: fixed; 
            width: 100%; 
            top: 0; 
            z-index: 1000; 
            height: var(--nav-height-scrolled); 
            background: rgba(255, 255, 255, 0.95); 
            backdrop-filter: blur(20px); 
            box-shadow: 0 4px 30px rgba(44,30,22,0.1); 
            border-bottom: 1px solid rgba(160,94,68,0.1);
            display: flex; 
            align-items: center; 
        }
        .navbar { display: flex; justify-content: space-between; align-items: center; height: 100%; width: 100%; }
        
        .nav-logo { display: flex; align-items: center; }
        .nav-logo img { height: 60px; object-fit: contain; filter: drop-shadow(0px 2px 4px rgba(0,0,0,0.1)); transition: all 0.3s; }
        .nav-logo:hover img { transform: scale(1.05) rotate(-2deg); }
        
        .nav-menu { display: flex; gap: 3rem; }
        .nav-link { font-size: 15px; font-weight: 500; color: var(--secondary); position: relative; letter-spacing: 0.5px; transition: color 0.3s; text-transform: uppercase; }
        .nav-link::after { content: ''; position: absolute; width: 6px; height: 6px; bottom: -10px; left: 50%; transform: translateX(-50%) scale(0); background-color: var(--primary); border-radius: 50%; transition: transform 0.3s ease; }
        .nav-link:hover::after, .nav-link.active::after { transform: translateX(-50%) scale(1); }
        .nav-link:hover, .nav-link.active { color: var(--primary); }

        .nav-right-cluster { display: flex; align-items: center; gap: 1.2rem; }
        .nav-icon-btn { 
            width: 45px; height: 45px; display: flex; align-items: center; justify-content: center; 
            border-radius: 50%; background: var(--bg-main); color: var(--secondary); 
            font-size: 1.1rem; transition: all 0.3s ease; border: 1px solid #E6DCD3; 
        }
        .nav-icon-btn:hover { background: var(--primary); color: #fff; transform: translateY(-3px); box-shadow: 0 5px 15px var(--primary-glow); border-color: var(--primary); }
        
        .user-avatar { width: 45px; height: 45px; border-radius: 50%; object-fit: cover; border: 2px solid var(--primary); transition: transform 0.3s; background: #fff; cursor: pointer; }
        .profile-dropdown { position: relative; display: flex; align-items: center;}
        .profile-dropdown::after { content: ''; position: absolute; top: 100%; left: 0; width: 100%; height: 30px; }
        .profile-menu { opacity: 0; visibility: hidden; transform: translateY(15px); position: absolute; right: 0; top: 130%; background: rgba(255,255,255,0.98); backdrop-filter: blur(10px); min-width: 220px; border-radius: var(--radius-md); box-shadow: 0 15px 35px rgba(0,0,0,0.15); overflow: hidden; z-index: 1001; transition: all 0.3s ease; border: 1px solid var(--accent); }
        .profile-dropdown:hover .profile-menu { opacity: 1; visibility: visible; transform: translateY(0); }
        .profile-menu a { display: flex; align-items: center; gap: 12px; padding: 14px 20px; color: var(--text-dark); font-size: 0.95rem; border-bottom: 1px solid var(--bg-main); transition: 0.3s; }
        .profile-menu a:hover { background: var(--primary); color: #fff; padding-left: 25px; }

        .hamburger { display: none; cursor: pointer; z-index: 1002; margin-left: 10px; }
        .bar { display: block; width: 28px; height: 3px; margin: 6px auto; background-color: var(--secondary); transition: 0.4s ease; border-radius: 3px; }

        /* ================== ORDERS LAYOUT ================== */
        .orders-section { padding: 4rem 0 6rem; min-height: calc(100vh - 300px); }
        .page-header { margin-bottom: 3rem; text-align: left; border-bottom: 2px solid #E6DCD3; padding-bottom: 1.5rem; }
        .page-title { font-family: var(--font-heading); font-size: 2.5rem; color: var(--secondary); margin-bottom: 0.5rem; }
        .page-subtitle { color: var(--text-muted); font-size: 1rem; }

        .orders-card { background: var(--bg-card); border-radius: var(--radius-lg); box-shadow: var(--shadow-soft); padding: 2rem; border: 1px solid rgba(160, 94, 68, 0.1); overflow-x: auto; }
        
        .orders-table { width: 100%; border-collapse: collapse; min-width: 700px; }
        .orders-table th { background: var(--bg-main); color: var(--text-muted); font-weight: 600; text-transform: uppercase; font-size: 0.85rem; padding: 15px 20px; text-align: left; letter-spacing: 1px; }
        .orders-table th:first-child { border-top-left-radius: 12px; border-bottom-left-radius: 12px; }
        .orders-table th:last-child { border-top-right-radius: 12px; border-bottom-right-radius: 12px; }
        
        .orders-table td { padding: 20px; border-bottom: 1px solid #E6DCD3; color: var(--secondary); vertical-align: middle; }
        .orders-table tr.main-order-row:hover td { background: rgba(248, 244, 238, 0.5); }
        
        .order-id { font-weight: 600; color: var(--primary); }
        .order-date { font-size: 0.9rem; color: var(--text-muted); }
        .order-total { font-weight: 700; font-family: var(--font-heading); font-size: 1.1rem; }
        
        .status-badge { display: inline-block; padding: 6px 14px; border-radius: 30px; font-size: 0.8rem; font-weight: 600; text-transform: uppercase; letter-spacing: 0.5px; }
        .status-pending { background: #fff3cd; color: #856404; }
        .status-processing { background: #cce5ff; color: #004085; }
        .status-completed { background: #d4edda; color: #155724; }
        .status-cancelled { background: #f8d7da; color: #721c24; }

        .action-btn { display: inline-flex; align-items: center; gap: 6px; padding: 8px 16px; border-radius: 8px; background: var(--bg-main); color: var(--primary); font-size: 0.85rem; font-weight: 500; border: 1px solid #E6DCD3; transition: 0.3s; cursor: pointer; }
        .action-btn:hover { background: var(--primary); color: #fff; border-color: var(--primary); }

        /* Order Details Styling */
        .order-details-container { background-color: #fdfbf9; padding: 20px; border-radius: 8px; border: 1px dashed var(--accent); }
        .order-details-container h4 { margin-bottom: 10px; color: var(--secondary); font-family: var(--font-heading); font-size: 1.1rem; }
        .item-list { list-style: none; padding-left: 0; }
        .item-list li { display: flex; justify-content: space-between; padding: 8px 0; border-bottom: 1px solid #E6DCD3; font-size: 0.9rem; }
        .item-list li:last-child { border-bottom: none; }

        /* Empty State */
        .empty-orders { text-align: center; padding: 4rem 2rem; }
        .empty-icon { font-size: 4rem; color: #E6DCD3; margin-bottom: 1.5rem; }
        .empty-title { font-family: var(--font-heading); font-size: 1.8rem; color: var(--secondary); margin-bottom: 1rem; }
        .empty-btn { display: inline-flex; background: var(--primary); color: #fff; padding: 12px 28px; border-radius: 30px; font-weight: 500; margin-top: 1rem; transition: 0.3s; }
        .empty-btn:hover { background: var(--secondary); transform: translateY(-3px); }

        /* ================== FOOTER ================== */
        .footer { background-color: #1A120D; color: rgba(255,255,255,0.7); padding-top: 4rem; position: relative; }
        .footer::before { content: ''; position: absolute; top: 0; left: 0; width: 100%; height: 5px; background: linear-gradient(90deg, var(--primary), var(--accent)); }
        .footer-content { display: grid; grid-template-columns: 2fr 1fr 1fr; gap: 4rem; padding-bottom: 3rem; border-bottom: 1px solid rgba(255,255,255,0.05); }
        .footer-brand img { height: 80px; margin-bottom: 1.5rem; filter: brightness(0) invert(1) opacity(0.8); }
        .footer-col h4 { color: #fff; font-size: 1.2rem; margin-bottom: 1.5rem; font-family: var(--font-heading); }
        .footer-links li { margin-bottom: 1rem; }
        .footer-links a:hover { color: var(--accent); }
        .copyright { text-align: center; padding: 1.5rem 0; font-size: 0.9rem; }

        /* ================== RESPONSIVE DESIGN ================== */
        @media (max-width: 992px) { 
            .footer-content { grid-template-columns: 1fr; } 
        }

        @media (max-width: 768px) { 
            .nav-menu { display: none; } 
            .nav-right-cluster { display: flex !important; gap: 10px; }
            .hamburger { display: block; }
            .nav-menu.active { 
                display: flex; flex-direction: column; position: absolute; 
                top: var(--nav-height-scrolled); left: 0; width: 100%; 
                background: var(--bg-card); padding: 2rem; text-align: center; 
                box-shadow: 0 10px 30px rgba(0,0,0,0.1); 
            }
            .orders-card { padding: 1rem; border-radius: var(--radius-md); }
        }
        
        @media (max-width: 480px) {
            .nav-icon-btn { width: 38px; height: 38px; font-size: 0.95rem; }
            .user-avatar { width: 38px; height: 38px; }
        }
    </style>
</head>
<body>

    <header class="header" id="navbar">
        <nav class="navbar container">
            <a href="index.php#home" class="nav-logo">
                <img src="Logo_Brand.png" alt="Cafe Emmanuel Logo" onerror="this.src='logo.png'">
            </a>
            <ul class="nav-menu">
                <li><a href="index.php#home" class="nav-link">Home</a></li>
                <li><a href="index.php#menu" class="nav-link">Menu</a></li>
                <li><a href="index.php#about" class="nav-link">About</a></li>
                <li><a href="index.php#contact" class="nav-link">Contact</a></li>
                <?php if (isset($_SESSION['user_id'])): ?>
                    <li><a href="my_orders.php" class="nav-link active">My Orders</a></li>
                <?php endif; ?>
            </ul>
            <div class="nav-right-cluster">
                <a href="cart.php" class="nav-icon-btn" title="View Cart"><i class="fas fa-shopping-cart"></i></a>
                <?php if (isset($_SESSION['user_id'])): ?>
                    <div class="nav-icon-btn" title="Notifications">
                        <?php include 'notification_bell.php'; ?>
                    </div>
                    <div class="profile-dropdown">
                        <?php $profilePic = !empty($_SESSION['profile_pic']) ? $_SESSION['profile_pic'] : 'https://ui-avatars.com/api/?name='.urlencode($_SESSION['fullname']).'&background=A05E44&color=fff'; ?>
                        <img src="<?php echo htmlspecialchars($profilePic); ?>" alt="Profile" class="user-avatar">
                        <div class="profile-menu">
                            <a href="profile.php"><i class="fas fa-user-circle"></i> My Profile</a>
                            <?php if (in_array($_SESSION['role'], ['admin', 'super_admin'])): ?>
                                <a href="Dashboard.php"><i class="fas fa-chart-line"></i> Dashboard</a>
                            <?php endif; ?>
                            <a href="logout.php"><i class="fas fa-sign-out-alt"></i> Log Out</a>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
            <div class="hamburger" id="hamburger">
                <span class="bar"></span>
                <span class="bar"></span>
                <span class="bar"></span>
            </div>
        </nav>
    </header>

    <main class="orders-section">
        <div class="container">
            <div class="page-header">
                <h1 class="page-title">Order History</h1>
                <p class="page-subtitle">Track your recent purchases and past orders.</p>
            </div>

            <div class="orders-card">
                <?php if (isset($result) && $result->num_rows > 0): ?>
                    <table class="orders-table">
                        <thead>
                            <tr>
                                <th>Order ID</th>
                                <th>Date & Time</th>
                                <th>Total Amount</th>
                                <th>Status</th>
                                <th>Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php while ($order = $result->fetch_assoc()): ?>
                                <tr class="main-order-row">
                                    <td class="order-id">#<?php echo str_pad($order['id'], 6, "0", STR_PAD_LEFT); ?></td>
                                    <td>
                                        <div class="order-date">
                                            <?php echo date('M d, Y', strtotime($order['created_at'])); ?><br>
                                            <small><?php echo date('h:i A', strtotime($order['created_at'])); ?></small>
                                        </div>
                                    </td>
                                    <td class="order-total">₱<?php echo number_format($order['total'] ?? 0, 2); ?></td>
                                    <td>
                                        <?php 
                                            // Handle different statuses
                                            $status = strtolower($order['status'] ?? 'pending');
                                            $status_class = 'status-pending'; // default
                                            if ($status === 'completed' || $status === 'delivered') $status_class = 'status-completed';
                                            if ($status === 'processing' || $status === 'preparing') $status_class = 'status-processing';
                                            if ($status === 'cancelled') $status_class = 'status-cancelled';
                                        ?>
                                        <span class="status-badge <?php echo $status_class; ?>">
                                            <?php echo htmlspecialchars(ucfirst($status)); ?>
                                        </span>
                                    </td>
                                    <td>
                                        <button class="action-btn toggle-view-btn" data-target="details-<?php echo $order['id']; ?>">
                                            <i class="fas fa-eye"></i> View
                                        </button>
                                        
                                        <?php if ($status === 'pending'): ?>
                                            <a href="cancel_order.php?id=<?php echo $order['id']; ?>" class="action-btn" style="color: #e02424; border-color: #fbc4c4;" onclick="return confirm('Are you sure you want to cancel this order?');">
                                                <i class="fas fa-times"></i> Cancel
                                            </a>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                                
                                <tr id="details-<?php echo $order['id']; ?>" style="display: none;">
                                    <td colspan="5" style="padding: 10px 20px 30px;">
                                        <div class="order-details-container">
                                            <h4>Ordered Items</h4>
                                            <ul class="item-list">
                                                <?php
                                                    $order_id = $order['id'];
                                                    $items_query = "SELECT * FROM order_items WHERE order_id = ?";
                                                    $item_stmt = $conn->prepare($items_query);
                                                    if ($item_stmt) {
                                                        $item_stmt->bind_param("i", $order_id);
                                                        $item_stmt->execute();
                                                        $items_res = $item_stmt->get_result();
                                                        
                                                        if ($items_res->num_rows > 0) {
                                                            while ($item = $items_res->fetch_assoc()) {
                                                                $prod_name = !empty($item['product_name']) ? htmlspecialchars($item['product_name']) : "Product ID: " . $item['product_id'];
                                                                $subtotal = $item['quantity'] * $item['price'];
                                                                echo "<li>
                                                                        <span><b>{$item['quantity']}x</b> {$prod_name}</span>
                                                                        <span>₱" . number_format($subtotal, 2) . "</span>
                                                                      </li>";
                                                            }
                                                        } else {
                                                            echo "<li>No items found for this order.</li>";
                                                        }
                                                    }
                                                ?>
                                            </ul>
                                        </div>
                                    </td>
                                </tr>
                            <?php endwhile; ?>
                        </tbody>
                    </table>
                <?php else: ?>
                    <div class="empty-orders">
                        <i class="fas fa-box-open empty-icon"></i>
                        <h3 class="empty-title">No Orders Yet</h3>
                        <p style="color: var(--text-muted); margin-bottom: 20px;">You haven't placed any orders with us. Treat yourself to something delicious!</p>
                        <a href="index.php#menu" class="empty-btn">Explore Menu</a>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </main>

    <footer class="footer">
        <div class="container">
            <div class="footer-content">
                <div class="footer-brand">
                    <img src="Logo_Brand.png" alt="Cafe Emmanuel" onerror="this.src='logo.png'">
                    <p>Where every cup tells a story, and every bite is a masterpiece. Join us in celebrating the art of coffee and community.</p>
                </div>
                <div class="footer-col">
                    <h4>Quick Links</h4>
                    <ul class="footer-links">
                        <li><a href="index.php#home">Home</a></li>
                        <li><a href="index.php#menu">Menu</a></li>
                        <li><a href="index.php#about">About Us</a></li>
                        <li><a href="index.php#contact">Contact</a></li>
                    </ul>
                </div>
                <div class="footer-col">
                    <h4>Find Us</h4>
                    <ul class="footer-links">
                        <li style="display:flex; gap:10px;"><i class="fas fa-map-marker-alt" style="color:var(--primary); margin-top:5px;"></i> San Antonio Road, Purok Dayat, San Antonio, Guagua</li>
                        <li style="display:flex; gap:10px;"><i class="fas fa-phone" style="color:var(--primary); margin-top:5px;"></i> 0995 100 9209</li>
                        <li style="display:flex; gap:10px;"><i class="fas fa-envelope" style="color:var(--primary); margin-top:5px;"></i> emmanuel.cafegallery@gmail.com</li>
                    </ul>
                </div>
            </div>
            <div class="copyright"><p>© 2026 Cafe Emmanuel. Designed with passion. All rights reserved.</p></div>
        </div>
    </footer>

    <script>
        document.addEventListener("DOMContentLoaded", () => {
            // Hamburger Menu Logic
            const hamburger = document.getElementById('hamburger');
            const navMenu = document.querySelector('.nav-menu');
            if(hamburger) {
                hamburger.addEventListener('click', () => {
                    navMenu.classList.toggle('active');
                    hamburger.classList.toggle('active');
                });
            }

            // View Order Details Logic
            const viewButtons = document.querySelectorAll('.toggle-view-btn');
            viewButtons.forEach(button => {
                button.addEventListener('click', function(e) {
                    e.preventDefault();
                    const targetId = this.getAttribute('data-target');
                    const targetRow = document.getElementById(targetId);
                    
                    if (targetRow.style.display === 'none') {
                        targetRow.style.display = 'table-row';
                        this.innerHTML = '<i class="fas fa-eye-slash"></i> Hide';
                    } else {
                        targetRow.style.display = 'none';
                        this.innerHTML = '<i class="fas fa-eye"></i> View';
                    }
                });
            });
        });
    </script>
</body>
</html>