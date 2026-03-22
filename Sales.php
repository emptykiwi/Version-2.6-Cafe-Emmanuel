<?php
include 'session_check.php';

// --- Database Connection ---
include 'db_connect.php';
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

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

// --- Fetch Recent Customers ---
$customers_result = $conn->query("SELECT * FROM cart WHERE status = 'Delivered' ORDER BY created_at DESC LIMIT 5");
$customers_data = [];
while ($row = $customers_result->fetch_assoc()) {
    $customers_data[] = $row;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <link rel="icon" type="image/png" href="Logo_Brand.png">
  <title>Sales Reports - Cafe Emmanuel</title>
  
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" />
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:ital,wght@0,500;0,700;1,500&family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
  <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

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
        margin-left: 260px; /* Aligns with fixed sidebar width */
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
    
    .icon-container:hover i {
        color: #fff;
    }

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

    /* --- DASHBOARD GRIDS --- */
    .top-section-grid {
        display: grid;
        grid-template-columns: 2fr 1fr;
        gap: 30px;
        margin-bottom: 30px;
        position: relative;
        z-index: 1;
    }

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

    .card-header h2 {
        font-family: var(--font-heading);
        font-size: 22px;
        font-weight: 700;
        color: var(--secondary);
    }

    .date-dropdown {
        border: 1px solid var(--border-color);
        padding: 8px 18px;
        border-radius: 50px;
        font-size: 13px;
        font-family: var(--font-body);
        background: var(--bg-main);
        color: var(--text-dark);
        cursor: pointer;
        outline: none;
        transition: 0.3s;
    }
    
    .date-dropdown:focus, .date-dropdown:hover {
        border-color: var(--primary);
        box-shadow: 0 0 0 3px rgba(160, 94, 68, 0.1);
    }

    /* --- TABLE STYLES --- */
    .table-wrapper { overflow-x: auto; }
    .customers-table { width: 100%; border-collapse: collapse; }
    .customers-table th {
        text-align: left;
        padding: 15px;
        font-size: 12px;
        text-transform: uppercase;
        color: #3a2b24;
        border-bottom: 2px solid var(--border-color);
        letter-spacing: 0.5px;
    }
    .customers-table td {
        padding: 18px 15px;
        border-bottom: 1px dashed var(--border-color);
        font-size: 14px;
        color: var(--text-dark);
    }
    .customers-table tr:hover td {
        background-color: #FDFBF7;
    }
    .customer-name { font-weight: 600; color: var(--secondary); }
    .price-text { font-weight: 700; color: var(--primary); font-family: var(--font-heading); font-size: 1.1rem; }

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
        .top-section-grid { grid-template-columns: 1fr; }
        .main-content { margin-left: 0; width: 100%; }
    }
  </style>
</head>
<body>
    <?php include 'admin_sidebar.php'; ?>

    <main class="main-content">
        <header class="main-header">
            <h1>Sales Reports</h1>
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
        
        <div class="top-section-grid">
            <div class="card">
                <div class="card-header">
                    <h2>Recent Delivered Orders</h2>
                    <select class="date-dropdown"><option>Last 5 Orders</option></select>
                </div>
                <div class="table-wrapper">
                    <table class="customers-table">
                        <thead>
                            <tr>
                                <th>Name</th>
                                <th>Address</th>
                                <th>Total</th>
                                <th>Date</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (!empty($customers_data)): ?>
                                <?php foreach ($customers_data as $row): ?>
                                    <tr>
                                        <td class="customer-name"><?php echo htmlspecialchars($row['fullname']); ?></td>
                                        <td style="color:#3a2b24"><i class="fas fa-map-marker-alt" style="margin-right:5px; color:var(--accent);"></i> <?php echo htmlspecialchars($row['address']); ?></td>
                                        <td class="price-text">₱<?php echo number_format($row['total'], 2); ?></td>
                                        <td><i class="far fa-clock" style="margin-right:5px; color:#3a2b24;"></i> <?php echo date("M d, Y", strtotime($row['created_at'])); ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr><td colspan="4" style="text-align: center; padding: 40px; color:#3a2b24;">No delivered orders yet.</td></tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <div class="card">
                <div class="card-header"><h2>Top Selling Items</h2></div>
                <div style="height: 300px;"><canvas id="sales-order-chart"></canvas></div>
            </div>
        </div>

        <div class="card">
            <div class="card-header">
                <h2>Revenue Overview</h2>
                <select class="date-dropdown"><option>This Year</option></select>
            </div>
            <div style="height: 350px;"><canvas id="sales-chart"></canvas></div>
        </div>
    </main>

<script>
    document.addEventListener('DOMContentLoaded', function () {
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

        // Setup Brand Colors for Charts
        const brandPrimary = '#A05E44';
        const brandPrimaryLight = 'rgba(160, 94, 68, 0.15)';
        const brandColors = ['#A05E44', '#D4A373', '#2C1E16', '#756358', '#E6DCD3'];

        // --- 1. Revenue Overview Chart ---
        fetch('get_sales_data.php')
          .then(r => r.json())
          .then(data => {
            new Chart(document.getElementById("sales-chart"), { 
              type: "line",
              data: { 
                labels: data.labels, 
                datasets: [{ 
                    label: "Revenue (₱)", 
                    data: data.values, 
                    borderColor: brandPrimary,
                    backgroundColor: brandPrimaryLight,
                    fill: true,
                    tension: 0.4,
                    pointBackgroundColor: '#fff',
                    pointBorderColor: brandPrimary,
                    pointBorderWidth: 2,
                    pointRadius: 4
                }] 
              }, 
              options: { 
                  responsive: true, 
                  maintainAspectRatio: false,
                  scales: {
                      y: { border: { dash: [4, 4] }, grid: { color: '#E6DCD3' } },
                      x: { grid: { display: false } }
                  },
                  plugins: {
                      legend: { labels: { font: { family: "'Poppins', sans-serif" } } }
                  }
              } 
            });
          });

        // --- 2. Top Selling Items Chart ---
        fetch('get_top_selling.php')
          .then(r => r.json())
          .then(result => {
            if (result.success && result.data.length > 0) { 
              new Chart(document.getElementById("sales-order-chart"), { 
                type: "doughnut", // Changed to doughnut for a more modern look
                data: { 
                  labels: result.data.map(i => i.product_name), 
                  datasets: [{ 
                    data: result.data.map(i => i.total_sold), 
                    backgroundColor: brandColors,
                    borderWidth: 2,
                    borderColor: '#ffffff'
                  }] 
                }, 
                options: { 
                    responsive: true, 
                    maintainAspectRatio: false, 
                    cutout: '65%',
                    plugins: { 
                        legend: { 
                            position: 'bottom',
                            labels: { font: { family: "'Poppins', sans-serif" }, usePointStyle: true, padding: 20 }
                        } 
                    } 
                } 
              });
            }
          });
    });
</script>
</body>
</html>