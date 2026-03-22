<?php
session_start();
require_once 'config.php'; // Use central config for DB connection
require_once 'audit.php';

// 1. LOGIN LOGIC
$login_error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['login'])) {
    $identifier = trim($_POST['identifier'] ?? $_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';

    if (empty($identifier) || empty($password)) {
        $login_error = "Please fill in both fields.";
    } else {
        $stmt = $conn->prepare("SELECT * FROM users WHERE email = ? OR username = ?");
        $stmt->bind_param("ss", $identifier, $identifier);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result && $result->num_rows === 1) {
            $user = $result->fetch_assoc();
            if (password_verify($password, $user['password'])) {
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['email'] = $user['email'];
                $_SESSION['fullname'] = $user['fullname'];
                $_SESSION['role'] = $user['role'];
                $_SESSION['profile_pic'] = $user['profile_pic'] ?? null; 
                
                audit($user['id'], 'login_success', 'users', $user['id'], ['page' => 'product.php']);
                header("Location: product.php");
                exit;
            } else {
                $login_error = "Wrong password!";
            }
        } else {
            $login_error = "Account not found!";
        }
        $stmt->close();
    }
}

// 2. REGISTRATION LOGIC
$register_error = '';
$register_success = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['register'])) {
    // ... (Existing registration logic kept as is) ...
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';
    $fullname = trim($_POST['fullname'] ?? '');
    $email    = trim($_POST['email'] ?? '');
    $contact  = trim($_POST['contact'] ?? '');
    $gender   = trim($_POST['gender'] ?? '');
    $confirm  = $_POST['confirm_password'] ?? '';

    if (!preg_match("/^[a-zA-Z\s]+$/", $fullname)) {
        $register_error = "Full name must contain only letters and spaces.";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $register_error = "Invalid email format.";
    } elseif ($password !== $confirm) {
        $register_error = "Passwords do not match!";
    } else {
        $stmt = $conn->prepare("SELECT id FROM users WHERE email = ? OR username = ?");
        $stmt->bind_param("ss", $email, $username);
        $stmt->execute();
        if ($stmt->get_result()->num_rows > 0) {
            $register_error = "Email or Username already exists.";
        } else {
            $hashed_password = password_hash($password, PASSWORD_DEFAULT);
            $insert_stmt = $conn->prepare("INSERT INTO users (username, password, fullname, email, contact, gender, is_verified) VALUES (?, ?, ?, ?, ?, ?, 1)");
            $insert_stmt->bind_param("ssssss", $username, $hashed_password, $fullname, $email, $contact, $gender);

            if ($insert_stmt->execute()) {
                $register_success = "Registration successful! You can now login.";
            } else {
                $register_error = "Error: " . $conn->error;
            }
            $insert_stmt->close();
        }
        $stmt->close();
    }
}

// 3. FETCH PRODUCTS
$products = [];
$result = $conn->query("SELECT * FROM products WHERE stock > 0 ORDER BY category, name");
if ($result) {
    while ($row = $result->fetch_assoc()) {
        $products[] = $row;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="icon" type="image/png" href="logo.png">
    <title>Menu - Cafe Emmanuel</title>
    
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Akaya+Telivigala&family=Archivo+Black&family=Archivo+Narrow:wght@400;700&family=Birthstone+Bounce:wght@500&family=Inknut+Antiqua:wght@600&family=Playfair+Display:wght@400;600;700&family=Lato:wght@300;400;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">

    <style>
        /* (Your existing CSS here - omitted for brevity, keeping file structure intact) */
        :root { --primary-color: #B95A4B; --primary-dark: #9C4538; --secondary-color: #3C2A21; --text-color: #333; --text-light: #666; --heading-color: #1F1F1F; --bg-body: #FFFFFF; --bg-light: #FCFBF8; --bg-gray: #F9F9F9; --white: #FFFFFF; --border-color: #EAEAEA; --footer-bg-color: #1a120b; --footer-text-color: #ccc; --footer-link-hover: #FFC94A; --font-logo-cafe: 'Archivo Black', sans-serif; --font-logo-emmanuel: 'Birthstone Bounce', cursive; --font-nav: 'Inknut Antiqua', serif; --font-hero: 'Akaya Telivigala', cursive; --font-heading: 'Playfair Display', serif; --font-body: 'Lato', sans-serif; --nav-height: 90px; }
        * { margin: 0; padding: 0; box-sizing: border-box; }
        html { scroll-behavior: smooth; scroll-padding-top: var(--nav-height); }
        body { font-family: var(--font-body); color: var(--text-color); background-color: var(--bg-light); line-height: 1.7; overflow-x: hidden; padding-top: var(--nav-height); }
        .container { max-width: 1200px; margin: 0 auto; padding: 0 20px; }
        a { text-decoration: none; color: inherit; transition: all 0.3s ease; }
        ul { list-style: none; }
        .header { position: fixed; width: 100%; top: 0; z-index: 1000; height: var(--nav-height); background: rgba(26, 18, 11, 0.85); backdrop-filter: blur(10px); border-bottom: 1px solid rgba(255, 255, 255, 0.1); transition: background 0.4s ease; }
        .navbar { display: flex; justify-content: space-between; align-items: center; height: 100%; }
        .nav-logo { display: flex; align-items: center; color: var(--white); }
        .logo-cafe { font-family: var(--font-logo-cafe); font-size: 32px; letter-spacing: -1px; }
        .logo-emmanuel { font-family: var(--font-logo-emmanuel); font-size: 38px; margin-left: 8px; color: var(--primary-color); font-weight: 500; }
        .nav-menu { display: flex; gap: 2.5rem; }
        .nav-link { font-family: var(--font-nav); font-size: 15px; font-weight: 500; color: rgba(255,255,255,0.9); position: relative; letter-spacing: 0.5px; }
        .nav-link::after { content: ''; position: absolute; width: 0; height: 2px; bottom: -4px; left: 0; background-color: var(--footer-link-hover); transition: width 0.3s; }
        .nav-link:hover::after, .nav-link.active::after { width: 100%; }
        .nav-link:hover, .nav-link.active { color: var(--footer-link-hover); }
        .nav-right-cluster { display: flex; align-items: center; gap: 1rem; }
        .nav-icon-btn { position: relative; width: 45px; height: 45px; display: flex; align-items: center; justify-content: center; border-radius: 50%; background: rgba(255, 255, 255, 0.1); color: var(--white); font-size: 1.1rem; transition: all 0.3s ease; }
        .nav-icon-btn:hover { background: var(--footer-link-hover); color: var(--secondary-color); transform: translateY(-2px); }
        .user-avatar { width: 45px; height: 45px; border-radius: 50%; object-fit: cover; border: 2px solid var(--primary-color); transition: transform 0.3s ease; background: #fff; }
        .profile-dropdown:hover .user-avatar { transform: scale(1.05); box-shadow: 0 0 10px rgba(255, 255, 255, 0.3); }
        .login-trigger { background: var(--primary-color); color: var(--white); padding: 8px 24px; border-radius: 30px; font-weight: 600; font-size: 0.9rem; border: none; cursor: pointer; transition: background 0.3s; }
        .login-trigger:hover { background: var(--primary-dark); }
        .profile-dropdown { position: relative; cursor: pointer; display: flex; align-items: center;}
        .profile-dropdown::after { content: ''; position: absolute; top: 100%; left: 0; width: 100%; height: 50px; background: transparent; }
        .profile-menu { display: none; position: absolute; right: 0; top: 140%; background: var(--white); min-width: 200px; border-radius: 10px; box-shadow: 0 10px 30px rgba(0,0,0,0.15); overflow: hidden; z-index: 1001; }
        .profile-dropdown:hover .profile-menu { display: block; }
        .profile-menu a { display: block; padding: 12px 20px; color: var(--text-color); font-size: 0.95rem; border-bottom: 1px solid var(--border-color); }
        .profile-menu a:hover { background: var(--bg-light); color: var(--primary-color); }
        .hamburger { display: none; cursor: pointer; z-index: 1002; }
        .bar { display: block; width: 25px; height: 3px; margin: 5px auto; background-color: var(--white); transition: 0.3s; }
        .menu-hero { position: relative; height: 50vh; min-height: 350px; background: url('Cover-Photo.jpg') no-repeat center center/cover; display: flex; align-items: center; justify-content: flex-start; margin-top: -90px; padding-top: 90px; overflow: hidden; }
        .menu-hero::before { content: ''; position: absolute; top: 0; left: 0; width: 100%; height: 100%; background: linear-gradient(to right, rgba(0,0,0,0.9) 0%, rgba(0,0,0,0.6) 40%, rgba(0,0,0,0) 100%); z-index: 1; }
        .menu-hero-content { position: relative; z-index: 2; color: var(--white); animation: fadeInUp 1s ease-out; padding-left: 5%; max-width: 800px; }
        .menu-hero h1 { font-family: var(--font-hero); font-size: 5rem; color: var(--white); margin-bottom: 1rem; line-height: 1.1; text-shadow: 2px 2px 20px rgba(0,0,0,0.8); }
        .menu-hero h1 span { color: var(--primary-color); }
        .menu-hero p { font-size: 1.3rem; color: rgba(255,255,255,0.95); font-weight: 300; text-shadow: 1px 1px 10px rgba(0,0,0,0.8); }
        .filters-container { padding: 3rem 0 2rem; text-align: center; }
        .menu-filters { display: flex; flex-wrap: wrap; justify-content: center; gap: 1rem; }
        .filter-btn { background: var(--white); border: 1px solid var(--border-color); padding: 10px 25px; border-radius: 50px; font-family: var(--font-body); font-weight: 600; color: var(--text-light); cursor: pointer; transition: all 0.3s; font-size: 0.95rem; box-shadow: 0 2px 5px rgba(0,0,0,0.05); }
        .filter-btn:hover, .filter-btn.active { background: var(--primary-color); border-color: var(--primary-color); color: var(--white); box-shadow: 0 4px 12px rgba(185, 90, 75, 0.3); transform: translateY(-2px); }
        .menu-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(280px, 1fr)); gap: 2.5rem; padding-bottom: 6rem; }
        .menu-card { background: var(--white); border-radius: 16px; overflow: visible; box-shadow: 0 10px 25px rgba(0,0,0,0.05); transition: transform 0.3s ease; border: 1px solid var(--border-color); display: flex; flex-direction: column; cursor: pointer; }
        .menu-card:hover { transform: translateY(-8px); box-shadow: 0 20px 40px rgba(0,0,0,0.1); }
        .card-img-wrapper { height: 220px; overflow: hidden; background: #f4f4f4; position: relative; border-radius: 16px 16px 0 0;}
        .card-img-wrapper img { width: 100%; height: 100%; object-fit: cover; transition: transform 0.5s ease; }
        .menu-card:hover .card-img-wrapper img { transform: scale(1.1); }
        .card-content { padding: 1.5rem; flex-grow: 1; display: flex; flex-direction: column; justify-content: space-between; }
        .card-header { margin-bottom: 0.5rem; }
        .card-title { font-family: var(--font-heading); font-size: 1.3rem; font-weight: 700; color: var(--heading-color); margin: 0; }
        .card-cat { font-size: 0.8rem; text-transform: uppercase; color: var(--primary-color); letter-spacing: 1px; font-weight: 600; margin-bottom: 0.5rem; display: block; }
        .card-footer { display: flex; justify-content: space-between; align-items: center; margin-top: 1.5rem; border-top: 1px solid #f0f0f0; padding-top: 1rem; }
        .card-price { font-size: 1.2rem; font-weight: 700; color: var(--text-color); }
        .add-cart-btn { background: var(--bg-light); color: var(--text-color); width: 40px; height: 40px; border-radius: 50%; display: flex; align-items: center; justify-content: center; transition: all 0.3s; border: 1px solid var(--border-color); }
        .menu-card:hover .add-cart-btn { background: var(--primary-color); color: var(--white); border-color: var(--primary-color); }
        
        /* New Size Selector Styles */
        .size-selector {
            width: 100%;
            padding: 8px;
            margin-bottom: 10px;
            border: 1px solid #ddd;
            border-radius: 8px;
            font-size: 0.9rem;
            outline: none;
            background-color: #fcfbf8;
            cursor: pointer;
        }
        .size-selector:focus {
            border-color: var(--primary-color);
        }

        .footer { background-color: var(--footer-bg-color); color: var(--footer-text-color); padding-top: 4rem; font-size: 0.95rem; }
        .footer-content { display: grid; grid-template-columns: 1.5fr 1fr 1fr; gap: 3rem; padding-bottom: 3rem; border-bottom: 1px solid rgba(255,255,255,0.1); }
        .footer-brand h3 { color: var(--white); font-family: var(--font-logo-cafe); font-size: 1.8rem; }
        .footer-brand p { opacity: 0.7; margin-bottom: 1.5rem; line-height: 1.7; }
        .socials { display: flex; gap: 15px; }
        .social-link { width: 40px; height: 40px; border-radius: 50%; background: rgba(255,255,255,0.1); display: flex; align-items: center; justify-content: center; transition: 0.3s; color: var(--white); }
        .social-link:hover { background: var(--footer-link-hover); color: var(--secondary-color); }
        .footer-col h4 { color: var(--white); font-size: 1.1rem; margin-bottom: 1.5rem; font-family: var(--font-body); }
        .footer-links li { margin-bottom: 0.8rem; }
        .footer-links a { color: rgba(255,255,255,0.6); transition: 0.3s; }
        .footer-links a:hover { color: var(--footer-link-hover); padding-left: 5px; }
        .copyright { text-align: center; padding: 1.5rem 0; opacity: 0.5; font-size: 0.85rem; }
        .modal-overlay { display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.6); z-index: 2000; align-items: center; justify-content: center; backdrop-filter: blur(8px); animation: fadeIn 0.3s; }
        .modal-box { background: #ffffff; padding: 40px; border-radius: 24px; width: 90%; max-width: 420px; position: relative; box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.25); animation: slideUp 0.4s cubic-bezier(0.16, 1, 0.3, 1); }
        .modal-close { position: absolute; top: 20px; right: 25px; font-size: 1.5rem; color: #ccc; cursor: pointer; transition: 0.2s; }
        .modal-close:hover { color: var(--primary-color); }
        .modal-title { text-align: center; font-family: var(--font-heading); font-size: 2rem; margin-bottom: 2rem; color: var(--heading-color); letter-spacing: -0.5px; }
        .input-group { position: relative; margin-bottom: 20px; }
        .input-icon { position: absolute; left: 18px; top: 50%; transform: translateY(-50%); color: #aaa; font-size: 1rem; transition: 0.3s; }
        .input-field { width: 100%; padding: 14px 15px 14px 48px; border: 1px solid #e2e8f0; border-radius: 12px; font-family: var(--font-body); font-size: 1rem; background: #f8f9fa; transition: 0.3s; color: var(--text-color); }
        .input-field:focus { border-color: var(--primary-color); background: #fff; box-shadow: 0 0 0 4px rgba(185, 90, 75, 0.1); outline: none; }
        .input-field:focus + .input-icon { color: var(--primary-color); }
        .modal-btn { width: 100%; padding: 14px; border-radius: 12px; font-size: 1rem; margin-top: 10px; letter-spacing: 0.5px; background: var(--primary-color); color: white; border: none; cursor: pointer; font-weight: bold; transition: background 0.3s; }
        .modal-btn:hover { background: var(--primary-dark); }
        .modal-footer { text-align: center; margin-top: 25px; font-size: 0.95rem; color: #666; }
        .link-highlight { color: var(--primary-color); font-weight: 700; cursor: pointer; transition: 0.2s; }
        .link-highlight:hover { text-decoration: underline; color: var(--primary-dark); }
        select.input-field { appearance: none; cursor: pointer; }
        @keyframes fadeIn { from { opacity: 0; } to { opacity: 1; } }
        @keyframes fadeInUp { from { opacity: 0; transform: translateY(40px); } to { opacity: 1; transform: translateY(0); } }
        @keyframes slideUp { from { opacity: 0; transform: translateY(30px); scale: 0.95; } to { opacity: 1; transform: translateY(0); scale: 1; } }
        @media (max-width: 992px) { .menu-hero h1 { font-size: 3.5rem; } .footer-content { grid-template-columns: 1fr; } }
        @media (max-width: 768px) { .nav-menu, .nav-right-cluster { display: none; } .hamburger { display: block; } .nav-menu.active { display: flex; flex-direction: column; position: absolute; top: var(--nav-height); left: 0; width: 100%; background: var(--secondary-color); padding: 2rem; text-align: center; } .mobile-link { display: block; margin-bottom: 1rem; } .menu-hero-content { padding-left: 20px; } }
    </style>
</head>
<body>

    <header class="header">
        <nav class="navbar container">
            <a href="index.php" class="nav-logo">
                <span class="logo-cafe"><span class="first-letter">C</span>afe</span>
                <span class="logo-emmanuel"><span class="first-letter">E</span>mmanuel</span>
            </a>
            <ul class="nav-menu">
                <li><a href="index.php" class="nav-link">Home</a></li>
                <li><a href="product.php" class="nav-link active">Menu</a></li>
                <li><a href="about.php" class="nav-link">About</a></li>
                <li><a href="contact.php" class="nav-link">Contact</a></li>
                <?php if (isset($_SESSION['user_id'])): ?>
                <li><a href="my_orders.php" class="nav-link">My Orders</a></li>
                <?php endif; ?>
                
                <li class="mobile-link" style="display:none;"><a href="cart.php" class="nav-link">Cart</a></li>
                <?php if (!isset($_SESSION['user_id'])): ?>
                <li class="mobile-link" style="display:none;"><a href="#" onclick="openModal('loginModal')" class="nav-link">Login</a></li>
                <?php else: ?>
                <li class="mobile-link" style="display:none;"><a href="logout.php" class="nav-link">Logout</a></li>
                <?php endif; ?>
            </ul>
            
            <div class="nav-right-cluster">
                <a href="cart.php" class="nav-icon-btn" title="View Cart">
                    <i class="fas fa-shopping-cart"></i>
                </a>

                <?php if (isset($_SESSION['user_id'])): ?>
                    <div class="nav-icon-btn" title="Notifications">
                        <?php include 'notification_bell.php'; ?>
                    </div>
                <?php endif; ?>

                <?php if (isset($_SESSION['user_id']) && isset($_SESSION['fullname'])): ?>
                    <div class="profile-dropdown">
                        <?php 
                            $profilePic = !empty($_SESSION['profile_pic']) ? $_SESSION['profile_pic'] : 'https://ui-avatars.com/api/?name='.urlencode($_SESSION['fullname']).'&background=B95A4B&color=fff';
                        ?>
                        <img src="<?php echo htmlspecialchars($profilePic); ?>" alt="Profile" class="user-avatar">
                        
                        <div class="profile-menu">
                            <a href="profile.php"><i class="fas fa-user"></i> My Profile</a>
                            <?php if (isset($_SESSION['role']) && in_array($_SESSION['role'], ['admin', 'super_admin'])): ?>
                                <a href="Dashboard.php"><i class="fas fa-tachometer-alt"></i> Dashboard</a>
                            <?php endif; ?>
                            <a href="logout.php"><i class="fas fa-sign-out-alt"></i> Log Out</a>
                        </div>
                    </div>
                <?php else: ?>
                    <button id="loginTrigger" class="login-trigger" onclick="openModal('loginModal')">Login</button>
                <?php endif; ?>
            </div>

            <div class="hamburger">
                <span class="bar"></span>
                <span class="bar"></span>
                <span class="bar"></span>
            </div>
        </nav>
    </header>

    <main>
        <section class="menu-hero">
            <div class="menu-hero-content">
                <h1>Our <span>Menu</span></h1>
                <p>Discover the taste of passion in every bite and sip. Freshly prepared for you.</p>
            </div>
        </section>

        <div class="container">
            <div class="filters-container">
                <div class="menu-filters">
                    <button class="filter-btn active" data-filter="all">All</button>
                    <button class="filter-btn" data-filter="coffee">Coffee</button>
                    <button class="filter-btn" data-filter="non-coffee">Non-Coffee</button>
                    <button class="filter-btn" data-filter="food">Food</button>
                    <button class="filter-btn" data-filter="breakfast">All Day Breakfast</button>
                    <button class="filter-btn" data-filter="pizza">Pizza</button>
                </div>
            </div>

            <div class="menu-grid">
                <?php foreach ($products as $product): 
                    // Determine categories for filtering
                    $cat = strtolower($product['category']);
                    $filterClass = $cat;
                    if(in_array($cat, ['pizza','katsu','ali di pollo','antipasto','mexican food','waffle','pasta','sandwich'])) { $filterClass .= ' food'; }
                    if(in_array($cat, ['classico','over iced','cafe artistry','freddo'])) { $filterClass .= ' coffee'; }
                    if(in_array($cat, ['hot tea','refresher','iced tea','hot choco','smoothies'])) { $filterClass .= ' non-coffee'; }

                    $product_data = htmlspecialchars(json_encode($product), ENT_QUOTES, 'UTF-8');
                    $onclick_action = isset($_SESSION['user_id'])
                        ? "viewProduct(" . $product_data . ")"
                        : "openModal('loginModal')";
                ?>
                <div class="menu-card product-box" data-category="<?php echo htmlspecialchars($filterClass); ?>" onclick="<?php echo $onclick_action; ?>">
                    <div class="card-img-wrapper">
                        <img src="<?php echo htmlspecialchars($product['image']); ?>" alt="<?php echo htmlspecialchars($product['name']); ?>">
                    </div>
                    <div class="card-content">
                        <div class="card-header">
                            <span class="card-cat"><?php echo htmlspecialchars($product['category']); ?></span>
                            <h3 class="card-title"><?php echo htmlspecialchars($product['name']); ?></h3>
                        </div>
                        
                        <!-- STEP 2: DYNAMIC SIZE & PRICE LOGIC -->
                        <?php
                            $pid = $product['id'];
                            // Query to get sizes for this product
                            $size_sql = "SELECT * FROM product_sizes WHERE product_id = $pid ORDER BY price ASC";
                            $size_result = $conn->query($size_sql);

                            if ($size_result && $size_result->num_rows > 0) {
                                // SCENARIO A: Product HAS sizes (e.g. Drinks)
                                echo '<div class="card-footer" style="flex-direction: column; align-items: flex-start; gap: 10px;">';
                                
                                // Stop propagation so clicking the dropdown doesn't trigger the card's onclick (which goes to quantity.php)
                                echo '<select class="size-selector" onclick="event.stopPropagation();" onchange="updatePrice(this)">';
                                
                                $first = true;
                                $default_price = 0;
                                while($size = $size_result->fetch_assoc()) {
                                    if($first) { $default_price = $size['price']; $first = false; }
                                    echo '<option value="' . $size['size_name'] . '" data-price="' . $size['price'] . '">' . $size['size_name'] . '</option>';
                                }
                                echo '</select>';
                                
                                echo '<div style="display:flex; justify-content:space-between; width:100%; align-items:center;">';
                                echo '<span class="card-price price">₱' . number_format($default_price, 2) . '</span>';
                                // Note: We use a special class or attribute so JS knows the initial price
                                echo '<button class="add-cart-btn add-cart" data-price="'.$default_price.'" data-size="Regular"><i class="fas fa-shopping-cart"></i></button>';
                                echo '</div></div>';

                            } else {
                                // SCENARIO B: No sizes (Standard Items)
                                echo '<div class="card-footer">';
                                echo '<span class="card-price price">₱' . number_format($product['price'], 2) . '</span>';
                                echo '<button class="add-cart-btn add-cart" data-price="'.$product['price'].'" data-size="Standard"><i class="fas fa-shopping-cart"></i></button>';
                                echo '</div>';
                            }
                        ?>
                        <!-- END STEP 2 -->

                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
    </main>

    <footer class="footer">
        <div class="container">
            <div class="footer-content">
                <div class="footer-brand">
                    <h3><span style="color:var(--primary-color);">C</span>afe Emmanuel</h3>
                    <p>Your neighborhood destination for exceptional coffee, delicious food, and warm hospitality.</p>
                    <div class="socials">
                        <a href="https://www.facebook.com/profile.php?id=61574968445731" class="social-link"><i class="fab fa-facebook-f"></i></a>
                        <a href="https://www.instagram.com/cafeemmanuelph/" class="social-link"><i class="fab fa-instagram"></i></a>
                    </div>
                </div>
                <div class="footer-col">
                    <h4>Quick Links</h4>
                    <ul class="footer-links">
                        <li><a href="index.php">Home</a></li>
                        <li><a href="product.php">Menu</a></li>
                        <li><a href="about.php">About Us</a></li>
                        <li><a href="contact.php">Contact</a></li>
                    </ul>
                </div>
                <div class="footer-col">
                    <h4>Contact Info</h4>
                    <ul class="footer-links">
                        <li><i class="fas fa-map-marker-alt" style="margin-right:8px; color:var(--primary-color);"></i> San Antonio Road, Purok Dayat, San Antonio, Guagua, Pampanga, Philippines</li>
                        <li><i class="fas fa-phone" style="margin-right:8px; color:var(--primary-color);"></i> 0995 100 9209</li>
                        <li><i class="fas fa-envelope" style="margin-right:8px; color:var(--primary-color);"></i> emmanuel.cafegallery@gmail.com</li>
                    </ul>
                </div>
            </div>
            <div class="copyright">
                <p>© 2025 Cafe Emmanuel. All rights reserved.</p>
            </div>
        </div>
    </footer>

    <!-- (Modals excluded for brevity, they remain unchanged) -->
    <div id="loginModal" class="modal-overlay" <?php if ($login_error) echo 'style="display:flex;"'; ?>>
        <div class="modal-box">
            <span class="modal-close" onclick="closeModal('loginModal')">×</span>
            <h2 class="modal-title">Welcome Back</h2>
            <?php if ($login_error): ?><p style="color: #dc3545; text-align: center; margin-bottom: 15px; font-size: 0.9rem;"><?php echo $login_error; ?></p><?php endif; ?>
            <form method="POST" action="product.php">
                <div class="input-group">
                    <i class="fas fa-envelope input-icon"></i>
                    <input type="text" name="identifier" placeholder="Email or Username" class="input-field" required>
                </div>
                <div class="input-group">
                    <i class="fas fa-lock input-icon"></i>
                    <input type="password" name="password" placeholder="Password" class="input-field" required>
                </div>
                <button type="submit" name="login" class="modal-btn">Login</button>
            </form>
            <div class="modal-footer">
                Don't have an account? <a href="#" onclick="switchModal('loginModal', 'registerModal')" class="link-highlight">Register</a>
            </div>
        </div>
    </div>

    <div id="registerModal" class="modal-overlay" <?php if ($register_error) echo 'style="display:flex;"'; ?>>
        <div class="modal-box" style="max-width: 450px;">
            <span class="modal-close" onclick="closeModal('registerModal')">×</span>
            <h2 class="modal-title">Create Account</h2>
            <?php if ($register_error): ?><p style="color: #dc3545; text-align: center; margin-bottom: 10px; font-size: 0.9rem;"><?php echo $register_error; ?></p><?php endif; ?>
            <?php if ($register_success): ?><p style="color: #28a745; text-align: center; margin-bottom: 10px; font-size: 0.9rem;"><?php echo $register_success; ?></p><?php endif; ?>
            <form method="POST" action="product.php">
                <div class="input-group">
                    <i class="fas fa-user input-icon"></i>
                    <input type="text" name="fullname" placeholder="Full Name" class="input-field" required minlength="8">
                </div>
                <div class="input-group">
                    <i class="fas fa-at input-icon"></i>
                    <input type="text" name="username" placeholder="Username" class="input-field" required minlength="4">
                </div>
                <div class="input-group">
                    <i class="fas fa-envelope input-icon"></i>
                    <input type="email" name="email" placeholder="Email Address" class="input-field" required>
                </div>
                <div class="input-group">
                    <i class="fas fa-phone input-icon"></i>
                    <input type="tel" name="contact" placeholder="Contact Number" class="input-field" pattern="[0-9]{10,15}">
                </div>
                <div class="input-group">
                    <i class="fas fa-venus-mars input-icon"></i>
                    <select name="gender" class="input-field" required>
                        <option value="" disabled selected>Select Gender</option>
                        <option value="Male">Male</option>
                        <option value="Female">Female</option>
                        <option value="Non-Binary">Non-Binary</option>
                        <option value="Prefer not to say">Prefer not to say</option>
                    </select>
                </div>
                <div class="input-group">
                    <i class="fas fa-lock input-icon"></i>
                    <input type="password" name="password" placeholder="Password (Min 8 chars, 1 Uppercase)" class="input-field" required minlength="8" pattern="^(?=.*[A-Z]).{8,}$">
                </div>
                <div class="input-group">
                    <i class="fas fa-check-circle input-icon"></i>
                    <input type="password" name="confirm_password" placeholder="Confirm Password" class="input-field" required>
                </div>
                <button type="submit" name="register" class="modal-btn">Register</button>
            </form>
            <div class="modal-footer">
                Already have an account? <a href="#" onclick="switchModal('registerModal', 'loginModal')" class="link-highlight">Login</a>
            </div>
        </div>
    </div>

    <!-- LINK TO EXTERNAL JS -->
    <script src="JS/product.js"></script> 

    <script>
        // --- View Product Logic ---
        function viewProduct(productData) {
            // NOTE: If you select a size in the dropdown, this redirect currently 
            // ignores it and sends base product data. 
            // If you want the size to carry over, you'd need more complex JS here.
            localStorage.setItem('selectedProduct', JSON.stringify(productData));
            window.location.href = 'quantity.php';
        }

        // --- Filter Logic ---
        const filterBtns = document.querySelectorAll('.filter-btn');
        const menuCards = document.querySelectorAll('.menu-card');

        filterBtns.forEach(btn => {
            btn.addEventListener('click', () => {
                filterBtns.forEach(b => b.classList.remove('active'));
                btn.classList.add('active');
                const filterValue = btn.getAttribute('data-filter');
                
                menuCards.forEach(card => {
                    const categories = card.getAttribute('data-category');
                    if (filterValue === 'all' || categories.includes(filterValue)) {
                        card.style.display = 'flex';
                    } else {
                        card.style.display = 'none';
                    }
                });
            });
        });

        // --- Modal Logic ---
        function openModal(modalId) { document.getElementById(modalId).style.display = 'flex'; }
        function closeModal(modalId) { document.getElementById(modalId).style.display = 'none'; }
        function switchModal(from, to) { closeModal(from); openModal(to); }
        
        window.onclick = function(event) {
            if (event.target.classList.contains('modal-overlay')) {
                event.target.style.display = 'none';
            }
        }

        // --- Mobile Menu ---
        const hamburger = document.querySelector('.hamburger');
        const navMenu = document.querySelector('.nav-menu');
        const mobileLinks = document.querySelectorAll('.mobile-link');

        hamburger.addEventListener('click', () => {
            navMenu.classList.toggle('active');
            mobileLinks.forEach(link => {
                link.style.display = navMenu.classList.contains('active') ? 'block' : 'none';
            });
        });
    </script>
</body>
</html>