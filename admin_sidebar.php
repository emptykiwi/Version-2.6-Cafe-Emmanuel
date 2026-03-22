<?php
$current_page = basename($_SERVER['SCRIPT_NAME']);
require_once __DIR__ . '/config.php';
?>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Playfair+Display:ital,wght@0,500;0,700;1,500&family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">

<style>
/* --- CAFE EMMANUEL THEME --- */
:root {
    --primary: #A05E44;       
    --secondary: #2C1E16;     
    --accent: #D4A373;        
    --sidebar-bg: #2C1E16;
    --text-light: #F8F4EE;
    --text-muted: rgba(248, 244, 238, 0.6);
    --nav-hover-bg: rgba(160, 94, 68, 0.15);
    --transition: all 0.3s ease;
}

.sidebar {
    width: 260px;
    height: 100vh;
    background: var(--sidebar-bg);
    position: fixed;
    left: 0;
    top: 0;
    display: flex;
    flex-direction: column;
    box-shadow: 4px 0 20px rgba(44, 30, 22, 0.4);
    z-index: 1000;
    font-family: 'Poppins', sans-serif;
    border-right: 1px solid rgba(212, 163, 115, 0.1);
}

.sidebar-logo-link {
    text-decoration: none;
    padding: 25px 15px;
    border-bottom: 1px solid rgba(255, 255, 255, 0.05);
    margin-bottom: 10px;
    display: flex;
    justify-content: center;
    align-items: center;
    width: 100%;
}

.sidebar-logo-img {
    height: 90px;
    width: auto;
    object-fit: contain;
    transition: transform 0.4s cubic-bezier(0.4, 0, 0.2, 1);
    filter: drop-shadow(0px 4px 8px rgba(0,0,0,0.3));
}

.sidebar-logo-link:hover .sidebar-logo-img {
    transform: scale(1.08) rotate(-2deg);
}

.sidebar-nav {
    display: flex;
    flex-direction: column;
    padding: 10px 15px;
    flex-grow: 1;
    overflow-y: auto;
}

/* Scrollbar for sidebar */
.sidebar-nav::-webkit-scrollbar { width: 4px; }
.sidebar-nav::-webkit-scrollbar-track { background: transparent; }
.sidebar-nav::-webkit-scrollbar-thumb { background: rgba(212, 163, 115, 0.3); border-radius: 10px; }

.nav-item {
    display: flex;
    align-items: center;
    padding: 12px 15px;
    text-decoration: none;
    color: var(--text-muted);
    font-size: 14px;
    font-weight: 500;
    border-radius: 8px;
    margin-bottom: 5px;
    transition: var(--transition);
}

.nav-item i {
    width: 20px;
    margin-right: 12px;
    font-size: 18px;
    color: var(--text-muted);
    transition: var(--transition);
}

.nav-item:hover {
    background-color: var(--nav-hover-bg);
    color: var(--text-light);
    transform: translateX(4px);
}

.nav-item:hover i {
    color: var(--accent);
}

.nav-item.active {
    background-color: var(--primary);
    color: #ffffff;
    box-shadow: 0 4px 15px rgba(160, 94, 68, 0.3);
}

.nav-item.active i {
    color: #ffffff;
}

.nav-item-logout {
    margin-top: auto;
    border-top: 1px solid rgba(255, 255, 255, 0.05);
    padding-top: 20px;
    margin-bottom: 20px;
}

.nav-label {
    font-size: 11px;
    text-transform: uppercase;
    letter-spacing: 1px;
    color: rgba(255, 255, 255, 0.3);
    margin: 15px 0 10px 15px;
    font-weight: 600;
}
</style>

<aside class="sidebar">
   <a href="Dashboard.php" class="sidebar-logo-link">
       <img src="Logo_Brand.png" alt="Cafe Emmanuel Logo" class="sidebar-logo-img">
   </a>

    <nav class="sidebar-nav">
        <div class="nav-label">Main Menu</div>
        <a href="Dashboard.php" class="nav-item <?php if ($current_page == 'Dashboard.php') echo 'active'; ?>">
            <i class="fas fa-home fa-fw"></i><span>Dashboard</span>
        </a>
        <a href="Sales.php" class="nav-item <?php if ($current_page == 'Sales.php') echo 'active'; ?>">
            <i class="fas fa-chart-bar fa-fw"></i><span>Reports</span>
        </a>
        <a href="Orders.php" class="nav-item <?php if ($current_page == 'Orders.php') echo 'active'; ?>">
            <i class="fas fa-box-open fa-fw"></i><span>Orders</span>
        </a>
        
        <a href="admin_reservations.php" class="nav-item <?php if ($current_page == 'admin_reservations.php' || $current_page == 'reservations.php') echo 'active'; ?>">
            <i class="fas fa-calendar-check fa-fw"></i><span>Reservations</span>
        </a>
        
        <a href="admin_inquiries.php" class="nav-item <?php if ($current_page == 'admin_inquiries.php') echo 'active'; ?>">
            <i class="fas fa-envelope fa-fw"></i><span>Inquiries</span>
        </a>

        <div class="nav-label">Management</div>
        
        <a href="practiceaddproduct.php" class="nav-item <?php if ($current_page == 'practiceaddproduct.php') echo 'active'; ?>">
            <i class="fas fa-tasks fa-fw"></i><span>Manage Products</span>
        </a>
        <a href="manage_hero.php" class="nav-item <?php if ($current_page == 'manage_hero.php') echo 'active'; ?>">
            <i class="fas fa-image fa-fw"></i><span>Hero Section</span>
        </a>
        
        <div class="nav-label">System Admin</div>
        <a href="recently_deleted.php" class="nav-item <?php if ($current_page == 'recently_deleted.php') echo 'active'; ?>">
            <i class="fas fa-trash-alt fa-fw"></i><span>Recycle Bin</span>
        </a>

        <?php if (isset($_SESSION['role']) && $_SESSION['role'] === 'super_admin'): ?>
        <a href="user_accounts.php" class="nav-item <?php if ($current_page == 'user_accounts.php') echo 'active'; ?>">
            <i class="fas fa-users fa-fw"></i><span>User Accounts</span>
        </a>
        <a href="audit_logs_page.php" class="nav-item <?php if ($current_page == 'audit_logs_page.php') echo 'active'; ?>">
            <i class="fas fa-clipboard-list fa-fw"></i><span>Audit Logs</span>
        </a>
        <?php endif; ?>
        
        <div class="nav-item-logout">
            <a href="logout.php" class="nav-item">
                <i class="fas fa-sign-out-alt fa-fw"></i><span>Log Out</span>
            </a>
        </div>
    </nav>
</aside>