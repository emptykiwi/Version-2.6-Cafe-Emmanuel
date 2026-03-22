<?php session_start(); ?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Cafe Emmanuel</title>
    
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Akaya+Telivigala&family=Archivo+Black&family=Archivo+Narrow:wght@400;700&family=Birthstone+Bounce:wght@500&family=Inknut+Antiqua:wght@600&family=Playfair+Display:wght@700&family=Lato:wght@400;700&display=swap" rel="stylesheet">
    
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">

    <style>
        :root {
            --primary-color: #B95A4B;
            --secondary-color: #3C2A21;
            --text-color: #333;
            --heading-color: #1F1F1F;
            --white: #FFFFFF;
            --border-color: #EAEAEA;
            --footer-bg-color: #1a120b;
            --footer-text-color: #ccc;
            --footer-link-hover: #FFC94A;
            --font-logo-cafe: 'Archivo Black', sans-serif;
            --font-logo-emmanuel: 'Birthstone Bounce', cursive;
            --font-nav: 'Inknut Antiqua', serif;
            --font-body-default: 'Lato', sans-serif;
            --nav-height: 90px;
        }
        body {
             font-family: var(--font-body-default);
             padding-top: var(--nav-height); /* Prevent content from hiding behind fixed header */
        }
        
        /* --- Header & Navigation --- */
        .header { position: fixed; width: 100%; top: 0; z-index: 1000; height: var(--nav-height); transition: background-color 0.4s ease, backdrop-filter 0.4s ease; background: rgba(26, 18, 11, 0.85); backdrop-filter: blur(10px); border-bottom: 1px solid rgba(255, 255, 255, 0.1); }
        .navbar { display: flex; justify-content: space-between; align-items: center; height: 100%; max-width: 1200px; margin: 0 auto; padding: 0 20px; }
        .nav-logo { text-decoration: none; color: var(--white); display: flex; align-items: center; }
        .logo-cafe { font-family: var(--font-logo-cafe); font-size: 40px; }
        .logo-emmanuel { font-family: var(--font-logo-emmanuel); font-size: 40px; font-weight: 500; margin-left: 10px; }
        .first-letter { color: #932432; }
        .nav-menu { display: flex; list-style: none; gap: 3rem; }
        .nav-link { font-family: var(--font-nav); font-size: 16px; font-weight: 600; color: #E0E0E0; text-decoration: none; transition: color 0.3s ease; }
        .nav-link:hover, .nav-link.active { color: var(--footer-link-hover); }
        .nav-right-cluster { display: flex; align-items: center; gap: 1.5rem; }
        .nav-cart-link { color: var(--white); font-size: 1.2rem; text-decoration: none; transition: color 0.3s ease; }
        .nav-cart-link:hover { color: var(--footer-link-hover); }
        .nav-button { background-color: var(--footer-link-hover); color: var(--secondary-color); padding: 10px 22px; border-radius: 8px; text-decoration: none; font-weight: bold; transition: background-color 0.3s ease; border: none; cursor: pointer; }
        .nav-button:hover { background-color: #e6b33a; }
        .hamburger { display: none; margin-left: 1rem; cursor: pointer; }
        .bar { display: block; width: 25px; height: 3px; margin: 5px auto; transition: all 0.3s ease-in-out; background-color: var(--white); }
        .profile-dropdown { position: relative; display: inline-block; cursor: pointer; }
        .profile-info { display: flex; align-items: center; gap: 8px; }
        .profile-info i, .profile-info span { color: white !important; font-size: 1.1rem; }
        .dropdown-content { display: none; position: absolute; right: 0; top: 120%; background-color: #fff; min-width: 180px; box-shadow: 0px 8px 16px 0px rgba(0,0,0,0.1); z-index: 1001; border-radius: 8px; overflow: hidden; border: 1px solid #eee; }
        .dropdown-content a { color: black; padding: 12px 16px; text-decoration: none; display: flex; align-items: center; gap: 10px; font-size: 14px; font-weight: 500; text-align: left; transition: background-color 0.2s; }
        .dropdown-content a:hover { background-color: #f1f1f1; color: #E03A3E; }
        .profile-dropdown.active .dropdown-content { display: block; }
        
        /* Modal Styles */
        .modal { display: none; position: fixed; z-index: 2000; left: 0; top: 0; width: 100%; height: 100%; overflow: auto; background-color: rgba(0,0,0,0.6); align-items: center; justify-content: center; }
        .modal-content { background-color: #fefefe; margin: auto; padding: 30px; border-radius: 12px; width: 90%; max-width: 380px; box-shadow: 0 5px 15px rgba(0,0,0,0.3); text-align: center; position: relative; animation: fadeIn 0.5s; }
        .close-btn { color: #aaa; position: absolute; top: 10px; right: 20px; font-size: 28px; font-weight: bold; cursor: pointer; }
        .modal-content h2{ margin-bottom: 15px; }
        .modal-content input { width: 100%; padding: 10px; margin: 8px 0; border: 1px solid #ccc; border-radius: 6px; }
        .modal-content button { background-color: #111; color: white; padding: 12px 20px; margin: 15px 0; border: none; border-radius: 6px; cursor: pointer; width: 100%; font-size: 16px; }
        .modal-content .register a, .modal-content .login a { color: #E03A3E; font-weight: bold; }

        @media (max-width: 850px) {
            .nav-menu { position: fixed; left: -100%; top: var(--nav-height); flex-direction: column; background-color: var(--secondary-color); width: 100%; text-align: center; transition: 0.3s; padding: 2rem 0; }
            .nav-menu.active { left: 0; }
            .nav-item { margin: 1rem 0; }
            .hamburger { display: block; }
            .nav-button, .profile-dropdown, .nav-cart-link { display: none; }
        }
    </style>
</head>
<body>

<header class="header header-page"> 
    <nav class="navbar container">
        <a href="index.php" class="nav-logo">
            <span class="logo-cafe"><span class="first-letter">C</span>afe</span>
            <span class="logo-emmanuel"><span class="first-letter">E</span>mmanuel</span>
        </a>
        <ul class="nav-menu">
            <li class="nav-item"><a href="index.php" class="nav-link">Home</a></li>
            <li class="nav-item"><a href="product.php" class="nav-link">Menu</a></li>
            <li class="nav-item"><a href="about.php" class="nav-link">About</a></li>
            <li class="nav-item"><a href="contact.php" class="nav-link">Contact</a></li>
            <li class="nav-item" style="display: none;"><a href="cart.php" class="nav-link">My Cart</a></li>
            <?php if (isset($_SESSION['user_id'])): ?>
                <li class="nav-item" style="display: none;"><a href="logout.php" class="nav-link">Log Out</a></li>
            <?php else: ?>
                <li class="nav-item" style="display: none;"><a href="#" id="loginModalBtnMobile" class="nav-link">Login/Register</a></li>
            <?php endif; ?>
        </ul>
        <div class="nav-right-cluster">
            <a href="cart.php" class="nav-cart-link"><i class="fas fa-shopping-cart"></i></a>
            <?php if (isset($_SESSION['user_id']) && isset($_SESSION['fullname'])): ?>
                <div class="profile-dropdown">
                    <div class="profile-info">
                        <i class="fa fa-user-circle"></i>
                        <span><?php echo htmlspecialchars($_SESSION['fullname']); ?></span>
                        <i class="fa fa-caret-down"></i>
                    </div>
                    <div class="dropdown-content">
                        <a href="logout.php"><i class="fa fa-sign-out-alt"></i> Log Out</a>
                    </div>
                </div>
            <?php else: ?>
                <button id="loginModalBtn" class="nav-button">Sign In / Sign Up</button>
            <?php endif; ?>
            <div class="hamburger">
                <span class="bar"></span>
                <span class="bar"></span>
                <span class="bar"></span>
            </div>
        </div>
    </nav>
</header>

<div id="loginModal" class="modal">
    <div class="modal-content">
      <span class="close-btn" id="closeLoginModal">&times;</span>
      <h2>Login to CafeEmmanuel</h2>
      <?php if (isset($login_error) && $login_error): ?><p style="color:red;"><?php echo $login_error; ?></p><?php endif; ?>
      <form method="POST" action="index.php">
        <input type="email" name="email" placeholder="Email" required>
        <input type="password" name="password" placeholder="Password" required>
        <button type="submit" name="login">Login</button>
        <p class="register">Don't you have an account? <a href="#" id="showRegisterModal">Register</a></p>
      </form>
    </div>
</div>

<div id="registerModal" class="modal">
    <div class="modal-content">
      <span class="close-btn" id="closeRegisterModal">&times;</span>
      <h2>Register to Cafe</h2>
       <?php if (isset($register_error) && $register_error): ?><p style="color:red;"><?php echo $register_error; ?></p><?php endif; ?>
       <?php if (isset($register_success) && $register_success): ?><p style="color:green;"><?php echo $register_success; ?></p><?php endif; ?>
      <form method="POST" action="index.php">
          <input type="text" name="fullname" placeholder="Full Name" required>
          <input type="text" name="username" placeholder="Username" required>
          <input type="email" name="email" placeholder="Email" required>
          <input type="password" name="password" placeholder="Password" required>
          <input type="password" name="confirm_password" placeholder="Confirm Password" required>
          <button type="submit" name="register">Register</button>
          <p class="login">Have an account? <a href="#" id="showLoginModal">Login</a></p>
      </form>
    </div>
</div>

<script>
    document.addEventListener("DOMContentLoaded", function() {
        // --- PROFILE DROPDOWN ---
        const profileDropdown = document.querySelector('.profile-dropdown');
        if (profileDropdown) {
            profileDropdown.addEventListener('click', function(event) {
                event.stopPropagation();
                this.classList.toggle('active');
            });
            window.addEventListener('click', function() {
                if(profileDropdown.classList.contains('active')) {
                    profileDropdown.classList.remove('active');
                }
            });
        }
        
        // --- MODAL CONTROLS ---
        const loginModal = document.getElementById("loginModal");
        const registerModal = document.getElementById("registerModal");
        const loginBtn = document.getElementById("loginModalBtn");
        const loginBtnMobile = document.getElementById("loginModalBtnMobile");
        const closeLoginModal = document.getElementById("closeLoginModal");
        const closeRegisterModal = document.getElementById("closeRegisterModal");
        const showRegisterModal = document.getElementById("showRegisterModal");
        const showLoginModal = document.getElementById("showLoginModal");

        if(loginBtn) loginBtn.onclick = () => { loginModal.style.display = "flex"; }
        if(loginBtnMobile) loginBtnMobile.onclick = (e) => { e.preventDefault(); loginModal.style.display = "flex"; }
        if(closeLoginModal) closeLoginModal.onclick = () => { loginModal.style.display = "none"; }
        if(closeRegisterModal) closeRegisterModal.onclick = () => { registerModal.style.display = "none"; }
        
        window.onclick = (event) => {
            if (event.target == loginModal) loginModal.style.display = "none";
            if (event.target == registerModal) registerModal.style.display = "none";
        }

        if(showRegisterModal) showRegisterModal.onclick = (e) => { e.preventDefault(); loginModal.style.display = "none"; registerModal.style.display = "flex"; }
        if(showLoginModal) showLoginModal.onclick = (e) => { e.preventDefault(); registerModal.style.display = "none"; loginModal.style.display = "flex"; }
        
        // --- NAVIGATION SCRIPT FOR NEW UI ---
        const hamburger = document.querySelector('.hamburger');
        const navMenu = document.querySelector('.nav-menu');

        if (hamburger) {
            hamburger.addEventListener('click', () => {
                hamburger.classList.toggle('active');
                navMenu.classList.toggle('active');
                
                // Show mobile-only nav items when menu is active
                const mobileItems = navMenu.querySelectorAll('.nav-item[style*="display: none"]');
                mobileItems.forEach(item => {
                    item.style.display = navMenu.classList.contains('active') ? 'block' : 'none';
                });
            });
        }
    });
</script>