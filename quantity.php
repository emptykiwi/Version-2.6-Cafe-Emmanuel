<?php 
session_start(); 
// Prevent browser caching to ensure fresh session state
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Cache-Control: post-check=0, pre-check=0", false);
header("Pragma: no-cache");
header("Expires: 0");
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Product Details - Cafe Emmanuel</title>
    
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Akaya+Telivigala&family=Archivo+Black&family=Archivo+Narrow:wght@400;700&family=Birthstone+Bounce:wght@500&family=Inknut+Antiqua:wght@600&family=Playfair+Display:wght@400;600;700&family=Lato:wght@300;400;700&display=swap" rel="stylesheet">

    <style>
        /* --- Global Variables & Theme --- */
        :root {
            --primary-color: #B95A4B;
            --primary-dark: #9C4538;
            --secondary-color: #3C2A21;
            --text-color: #333;
            --heading-color: #1F1F1F;
            --white: #FFFFFF;
            --bg-light: #fcfbf8;
            --border-color: #EAEAEA;
            --footer-bg-color: #1a120b;
            --footer-text-color: #ccc;
            --footer-link-hover: #FFC94A;
            
            --font-logo-cafe: 'Archivo Black', sans-serif;
            --font-logo-emmanuel: 'Birthstone Bounce', cursive;
            --font-nav: 'Inknut Antiqua', serif;
            --font-section-heading: 'Playfair Display', serif;
            --font-body: 'Lato', sans-serif;
            
            --nav-height: 90px;
        }

        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: var(--font-body); color: var(--text-color); background-color: var(--bg-light); line-height: 1.7; padding-top: var(--nav-height); min-height: 100vh; display: flex; flex-direction: column; }
        .container { max-width: 1200px; margin: 0 auto; padding: 0 20px; }
        a { text-decoration: none; transition: 0.3s; color: inherit; }

        /* --- Header & Navigation --- */
        .header { position: fixed; width: 100%; top: 0; z-index: 1000; height: var(--nav-height); background: rgba(26, 18, 11, 0.95); backdrop-filter: blur(10px); border-bottom: 1px solid rgba(255, 255, 255, 0.1); }
        .navbar { display: flex; justify-content: space-between; align-items: center; height: 100%; }
        .nav-logo { display: flex; align-items: center; color: var(--white); }
        .logo-cafe { font-family: var(--font-logo-cafe); font-size: 32px; letter-spacing: -1px; }
        .logo-emmanuel { font-family: var(--font-logo-emmanuel); font-size: 38px; margin-left: 8px; color: var(--primary-color); font-weight: 500; }
        
        .nav-menu { display: flex; list-style: none; gap: 3rem; }
        .nav-link { font-family: var(--font-nav); font-size: 16px; font-weight: 600; color: #E0E0E0; text-decoration: none; transition: color 0.3s ease; position: relative; }
        .nav-link:hover, .nav-link.active { color: var(--footer-link-hover); }
        
        .nav-right-cluster { display: flex; align-items: center; gap: 1.5rem; }
        
        .nav-icon-btn { position: relative; width: 45px; height: 45px; display: flex; align-items: center; justify-content: center; border-radius: 50%; background: rgba(255, 255, 255, 0.1); color: var(--white); font-size: 1.1rem; transition: all 0.3s ease; text-decoration: none; }
        .nav-icon-btn:hover { background: var(--footer-link-hover); color: var(--secondary-color); transform: translateY(-2px); }

        .user-avatar { width: 45px; height: 45px; border-radius: 50%; object-fit: cover; border: 2px solid var(--primary-color); transition: transform 0.3s ease; background: #fff; }
        .profile-dropdown:hover .user-avatar { transform: scale(1.05); box-shadow: 0 0 10px rgba(255, 255, 255, 0.3); }

        .login-trigger { background: var(--primary-color); color: var(--white); padding: 8px 24px; border-radius: 30px; font-weight: 600; font-size: 0.9rem; border: none; cursor: pointer; transition: background 0.3s; }
        .login-trigger:hover { background: var(--primary-dark); }

        .profile-dropdown { position: relative; display: inline-block; cursor: pointer; }
        .profile-dropdown::after { content: ''; position: absolute; top: 100%; left: 0; width: 100%; height: 50px; background: transparent; }

        .profile-menu { display: none; position: absolute; right: 0; top: 140%; background: var(--white); min-width: 200px; border-radius: 10px; box-shadow: 0 10px 30px rgba(0,0,0,0.15); overflow: hidden; z-index: 1001; }
        .profile-dropdown:hover .profile-menu { display: block; }
        .profile-menu a { display: block; padding: 12px 20px; color: var(--text-color); font-size: 0.95rem; border-bottom: 1px solid var(--border-color); text-decoration: none; }
        .profile-menu a:hover { background: #f8f9fa; color: var(--primary-color); }
        
        .hamburger { display: none; cursor: pointer; }
        .bar { display: block; width: 25px; height: 3px; margin: 5px auto; background-color: var(--white); transition: 0.3s; }

        /* --- Main Product Section --- */
        main { flex: 1; padding: 4rem 0 6rem; }
        
        .back-link { display: inline-flex; align-items: center; gap: 8px; color: #666; font-weight: 600; margin-bottom: 2rem; font-size: 0.95rem; }
        .back-link:hover { color: var(--primary-color); }

        .product-card-large { background: var(--white); border-radius: 20px; box-shadow: 0 20px 40px rgba(0,0,0,0.05); border: 1px solid var(--border-color); padding: 3rem; display: grid; grid-template-columns: 1fr 1.2fr; gap: 4rem; align-items: center; }
        
        .product-img-wrapper { width: 100%; background-color: #f8f8f8; border-radius: 16px; overflow: hidden; display: flex; align-items: center; justify-content: center; padding: 2rem; }
        .product-img-wrapper img { width: 100%; height: auto; object-fit: contain; max-height: 400px; mix-blend-mode: multiply; transition: transform 0.3s; }
        .product-img-wrapper img:hover { transform: scale(1.05); }

        .product-info-col { display: flex; flex-direction: column; justify-content: center; }
        
        .product-title { font-family: var(--font-section-heading); font-size: 2.5rem; color: var(--heading-color); margin-bottom: 0.5rem; line-height: 1.2; }
        
        .rating { color: #FFC107; font-size: 1.1rem; margin-bottom: 1.5rem; display: flex; align-items: center; gap: 5px; }
        .rating span { color: #999; font-size: 0.9rem; font-weight: 400; margin-left: 5px; }
        
        .price { font-family: var(--font-section-heading); font-size: 2rem; font-weight: 700; color: var(--primary-color); margin-bottom: 2rem; }
        
        .description { color: #666; font-size: 1.05rem; line-height: 1.8; margin-bottom: 2.5rem; }
        
        /* Controls */
        .control-group { margin-bottom: 1.5rem; }
        .control-label { display: block; font-weight: 700; margin-bottom: 10px; font-size: 0.9rem; text-transform: uppercase; letter-spacing: 1px; color: var(--heading-color); }
        
        .qty-wrapper { display: inline-flex; align-items: center; border: 2px solid var(--border-color); border-radius: 50px; padding: 5px; }
        .qty-btn { width: 40px; height: 40px; border-radius: 50%; border: none; background: transparent; cursor: pointer; font-size: 1.2rem; color: var(--text-color); transition: background 0.2s; display: flex; align-items: center; justify-content: center; }
        .qty-btn:hover { background: #f0f0f0; color: var(--primary-color); }
        .qty-input { width: 60px; border: none; text-align: center; font-size: 1.2rem; font-weight: 700; font-family: var(--font-body); background: transparent; outline: none; color: var(--heading-color); }
        
        /* Options Buttons (Size/Temp) */
        .option-buttons { display: flex; gap: 10px; flex-wrap: wrap; }
        .opt-btn {
            padding: 10px 20px;
            border: 1px solid var(--border-color);
            background: var(--white);
            border-radius: 8px;
            cursor: pointer;
            font-size: 0.9rem;
            font-weight: 600;
            transition: all 0.2s;
            color: var(--text-color);
        }
        .opt-btn:hover { border-color: var(--primary-color); color: var(--primary-color); }
        .opt-btn.active {
            background: var(--primary-color);
            color: var(--white);
            border-color: var(--primary-color);
        }

        .action-buttons { display: flex; gap: 1.5rem; margin-top: 2rem; }
        .btn-large { flex: 1; padding: 16px; border-radius: 50px; font-size: 1rem; font-weight: 700; text-align: center; cursor: pointer; transition: all 0.3s; border: 2px solid transparent; text-transform: uppercase; letter-spacing: 1px; }
        .btn-add-cart { background: var(--secondary-color); color: var(--white); }
        .btn-add-cart:hover { background: #2a1e17; transform: translateY(-3px); box-shadow: 0 10px 20px rgba(0,0,0,0.1); }
        .btn-buy-now { background: var(--primary-color); color: var(--white); }
        .btn-buy-now:hover { background: var(--primary-dark); transform: translateY(-3px); box-shadow: 0 10px 20px rgba(185, 90, 75, 0.2); }

        /* --- Footer --- */
        .footer { background-color: var(--footer-bg-color); color: var(--footer-text-color); padding-top: 4rem; font-size: 0.95rem; margin-top: auto; }
        .footer-content { display: grid; grid-template-columns: 1.5fr 1fr 1fr; gap: 3rem; padding-bottom: 3rem; border-bottom: 1px solid rgba(255,255,255,0.1); }
        .footer-brand h3 { color: var(--white); font-family: var(--font-logo-cafe); font-size: 1.8rem; margin-bottom: 1rem; }
        .footer-brand p { opacity: 0.7; margin-bottom: 1.5rem; line-height: 1.7; }
        .socials { display: flex; gap: 15px; }
        .social-link { width: 40px; height: 40px; border-radius: 50%; background: rgba(255,255,255,0.1); display: flex; align-items: center; justify-content: center; transition: 0.3s; color: var(--white); }
        .social-link:hover { background: var(--footer-link-hover); color: var(--secondary-color); }
        .footer-col h4 { color: var(--white); font-size: 1.1rem; margin-bottom: 1.5rem; font-family: var(--font-body-default); font-weight: bold; }
        .footer-links li { margin-bottom: 0.8rem; }
        .footer-links a { color: rgba(255,255,255,0.6); transition: 0.3s; }
        .footer-links a:hover { color: var(--footer-link-hover); padding-left: 5px; }
        .copyright { text-align: center; padding: 1.5rem 0; opacity: 0.5; font-size: 0.85rem; }

        /* --- Modals --- */
        .modal-overlay { display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.6); z-index: 2000; align-items: center; justify-content: center; backdrop-filter: blur(5px); animation: fadeIn 0.3s; }
        .modal-box { background: #ffffff; padding: 40px; border-radius: 24px; width: 90%; max-width: 450px; position: relative; box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.25); animation: slideUp 0.4s; }
        .modal-close { position: absolute; top: 20px; right: 25px; font-size: 1.5rem; color: #ccc; cursor: pointer; transition: 0.2s; }
        .modal-close:hover { color: var(--primary-color); }
        .input-group { position: relative; margin-bottom: 20px; }
        .input-icon { position: absolute; left: 18px; top: 50%; transform: translateY(-50%); color: #aaa; font-size: 1rem; transition: 0.3s; }
        .input-field { width: 100%; padding: 14px 15px 14px 48px; border: 1px solid #e2e8f0; border-radius: 12px; font-family: var(--font-body); font-size: 1rem; background: #f8f9fa; transition: 0.3s; color: var(--text-color); }
        .input-field:focus { border-color: var(--primary-color); background: #fff; box-shadow: 0 0 0 4px rgba(185, 90, 75, 0.1); outline: none; }
        .input-field:focus + .input-icon { color: var(--primary-color); }
        
        .modal-btn { width: 100%; padding: 14px; border-radius: 12px; font-size: 1rem; margin-top: 10px; letter-spacing: 0.5px; background: var(--primary-color); color: white; border: none; cursor: pointer; font-weight: bold; transition: background 0.3s; }
        .modal-btn:hover { background: var(--primary-dark); }

        /* Friendly Message Modal */
        .msg-content { text-align: center; }
        .msg-icon { font-size: 3rem; margin-bottom: 1rem; display: block; color: #28a745; }
        .btn-shop { display: inline-block; padding: 10px 30px; background: var(--primary-color); color: var(--white); border-radius: 50px; font-weight: 600; margin-top: 20px; border: none; cursor: pointer; }

        @keyframes fadeIn { from { opacity: 0; } to { opacity: 1; } }
        @keyframes slideUp { from { opacity: 0; transform: translateY(30px); scale: 0.95; } to { opacity: 1; transform: translateY(0); scale: 1; } }

        @media (max-width: 992px) {
            .product-card-large { grid-template-columns: 1fr; gap: 2rem; padding: 2rem; }
            .product-info-col { text-align: center; }
            .rating { justify-content: center; }
            .action-buttons { flex-direction: column; }
            .option-buttons { justify-content: center; }
        }
        @media (max-width: 768px) {
            .nav-menu, .nav-right-cluster { display: none; }
            .hamburger { display: block; }
        }
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
            </ul>
            
            <div class="nav-right-cluster">
                <a href="cart.php" class="nav-icon-btn" title="View Cart">
                    <i class="fas fa-shopping-cart"></i>
                </a>

                <div class="nav-icon-btn">
                    <?php if (isset($_SESSION['user_id'])) { include 'notification_bell.php'; } else { echo '<i class="fas fa-bell"></i>'; } ?>
                </div>

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
        <div class="container">
            <a href="product.php" class="back-link"><i class="fas fa-arrow-left"></i> Back to Menu</a>
            
            <section class="product-card-large">
                <div class="product-img-wrapper">
                    <img src="assets/placeholder.jpg" alt="Product Image" id="main-product-image">
                </div>

                <div class="product-info-col">
                    <h1 id="product-name" class="product-title">Product Name</h1>
                    <div class="rating" id="product-rating">
                        ★★★★☆ <span>(4.5/5)</span>
                    </div>
                    <p class="price" id="product-price">₱0.00</p>
                    <p class="description" id="product-description">Loading product details...</p>
                    
                    <div id="drink-options" style="display:none; margin-bottom: 25px;">
                        <div class="control-group">
                            <label class="control-label">Size</label>
                            <div class="option-buttons" id="size-options">
                                <button class="opt-btn active" onclick="selectOption(this, 'size', 0)">Regular</button>
                                <button class="opt-btn" onclick="selectOption(this, 'size', 10)">Large (+₱10.00)</button>
                            </div>
                        </div>
                        
                        <div class="control-group">
                            <label class="control-label">Temperature</label>
                            <div class="option-buttons" id="temp-options">
                                <button class="opt-btn active" onclick="selectOption(this, 'temp', 0)">Hot</button>
                                <button class="opt-btn" onclick="selectOption(this, 'temp', 0)">Iced</button>
                            </div>
                        </div>
                    </div>
                    <div class="control-group">
                        <label class="control-label">Quantity</label>
                        <div class="qty-wrapper">
                            <button id="qty-minus" class="qty-btn"><i class="fas fa-minus"></i></button>
                            <input type="number" id="quantity-input" value="1" min="1" max="10" class="qty-input" readonly />
                            <button id="qty-plus" class="qty-btn"><i class="fas fa-plus"></i></button>
                        </div>
                    </div>

                    <div class="action-buttons">
                        <button class="btn-large btn-add-cart">Add to Cart</button>
                        <button class="btn-large btn-buy-now" onclick="buyNow()">Buy Now</button>
                    </div>
                </div>
            </section>
        </div>
    </main>

    <footer class="footer">
        <div class="container">
            <div class="footer-content">
                <div class="footer-brand">
                    <h3><span style="color:var(--primary-color);">C</span>afe Emmanuel</h3>
                    <p>Your neighborhood destination for exceptional coffee, delicious food, and warm hospitality.</p>
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
                        <li><i class="fas fa-map-marker-alt" style="margin-right:8px; color:var(--primary-color);"></i> San Antonio, Guagua</li>
                        <li><i class="fas fa-phone" style="margin-right:8px; color:var(--primary-color);"></i> (555) 123-CAFE</li>
                    </ul>
                </div>
            </div>
            <div class="copyright">
                <p>© 2025 Cafe Emmanuel. All rights reserved.</p>
            </div>
        </div>
    </footer>

    <div id="loginModal" class="modal-overlay">
        <div class="modal-box">
            <span class="modal-close" onclick="closeModal('loginModal')">×</span>
            <h2 style="text-align:center; font-family:var(--font-section-heading); margin-bottom:20px;">Welcome Back</h2>
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
        </div>
    </div>

    <div id="msgModal" class="modal-overlay">
        <div class="modal-box" style="max-width: 400px;">
            <span class="modal-close" onclick="closeModal('msgModal')">&times;</span>
            <div class="msg-content">
                <i class="fas fa-check-circle msg-icon"></i>
                <h3 id="msgTitle" style="margin-bottom:10px; font-family: var(--font-section-heading);">Success</h3>
                <p id="msgText" style="color:#666;">Item added to cart!</p>
                <button onclick="closeModal('msgModal')" class="btn-shop">Continue Shopping</button>
            </div>
        </div>
    </div>

    <script>
        // GLOBAL STATE
        let basePrice = 0;
        let currentAddonPrice = 0;
        let selectedSize = "Regular";
        let selectedTemp = "Hot";
        let isDrink = false;

        document.addEventListener("DOMContentLoaded", () => {
            const product = JSON.parse(localStorage.getItem('selectedProduct'));

            if (!product) {
                window.location.href = 'product.php';
                return;
            }

            // Populate Basic Data
            document.getElementById('main-product-image').src = product.image;
            document.getElementById('product-name').textContent = product.name;
            
            // Set Base Price
            basePrice = parseFloat(product.price);
            updateTotalPrice();
            
            // Generate Stars
            const rating = product.rating || 5;
            const stars = '★'.repeat(Math.floor(rating)) + '☆'.repeat(5 - Math.floor(rating));
            document.getElementById('product-rating').innerHTML = `${stars} <span>(${rating}.0/5)</span>`;
            document.getElementById('product-description').textContent = product.description || "A delicious choice prepared fresh for you.";

            // --- SMART CATEGORY DETECTION ---
            const cat = (product.category || "").toLowerCase();
            const drinkKeywords = ['coffee', 'tea', 'latte', 'drink', 'beverage', 'iced', 'hot', 'frappe', 'smoothie', 'classico', 'freddo', 'cafe artistry'];
            const foodKeywords = ['pizza', 'pasta', 'food', 'meal', 'rice', 'sandwich', 'burger', 'katsu', 'chicken', 'waffle', 'dessert'];

            // Logic: Is Drink AND Not explicitly Food
            const matchesDrink = drinkKeywords.some(k => cat.includes(k));
            const matchesFood = foodKeywords.some(k => cat.includes(k));
            
            if (matchesDrink && !matchesFood) {
                isDrink = true;
                document.getElementById('drink-options').style.display = 'block';
            }

            // Quantity Logic
            const quantityInput = document.getElementById('quantity-input');
            document.getElementById('qty-plus').addEventListener('click', () => {
                let currentQty = parseInt(quantityInput.value);
                if (currentQty < 20) quantityInput.value = currentQty + 1;
            });
            document.getElementById('qty-minus').addEventListener('click', () => {
                let currentQty = parseInt(quantityInput.value);
                if (currentQty > 1) quantityInput.value = currentQty - 1;
            });

            // Add to Cart
            document.querySelector('.btn-add-cart').addEventListener('click', () => {
                const item = {
                    id: product.id,
                    name: product.name,
                    price: basePrice + currentAddonPrice, // Final Calculated Price
                    image: product.image || 'assets/placeholder.jpg',
                    quantity: parseInt(quantityInput.value) || 1,
                    size: isDrink ? selectedSize : 'Standard',
                    temperature: isDrink ? selectedTemp : 'N/A' // Save temperature
                };

                let cart = JSON.parse(localStorage.getItem('cart')) || [];
                
                // Check if existing item matches (ID + Size + Temp)
                const existingItemIndex = cart.findIndex(cartItem => 
                    cartItem.id === item.id && 
                    cartItem.size === item.size && 
                    cartItem.temperature === item.temperature
                );

                if (existingItemIndex > -1) {
                    cart[existingItemIndex].quantity += item.quantity;
                } else {
                    cart.push(item);
                }

                localStorage.setItem('cart', JSON.stringify(cart));
                showMessage('Added to Cart', `${item.name} (${item.size}) has been added!`);
            });
            
            // Profile Dropdown Toggle
            const profileDropdown = document.querySelector('.profile-dropdown');
            if (profileDropdown) {
                profileDropdown.addEventListener('click', function(event) {
                    event.stopPropagation();
                    this.classList.toggle('active');
                });
                window.addEventListener('click', function() {
                    profileDropdown.classList.remove('active');
                });
            }
        });

        // OPTION SELECTION LOGIC
        window.selectOption = function(btn, type, cost) {
            // 1. Toggle Active Class
            const parent = btn.parentElement;
            const buttons = parent.getElementsByClassName('opt-btn');
            for(let b of buttons) b.classList.remove('active');
            btn.classList.add('active');

            // 2. Update Logic
            if(type === 'size') {
                selectedSize = btn.textContent.split(' ')[0]; // "Regular" or "Large"
                currentAddonPrice = cost;
            } else if (type === 'temp') {
                selectedTemp = btn.textContent;
            }
            
            updateTotalPrice();
        };

        function updateTotalPrice() {
            const total = basePrice + currentAddonPrice;
            document.getElementById('product-price').textContent = `₱${total.toLocaleString(undefined, {minimumFractionDigits: 2, maximumFractionDigits: 2})}`;
        }

        function buyNow() {
            document.querySelector('.btn-add-cart').click(); 
            setTimeout(() => {
                window.location.href = 'cart.php';
            }, 500);
        }

        // Modal Logic
        function openModal(modalId) { document.getElementById(modalId).style.display = 'flex'; }
        function closeModal(modalId) { document.getElementById(modalId).style.display = 'none'; }
        
        function showMessage(title, text) {
            document.getElementById('msgTitle').textContent = title;
            document.getElementById('msgText').textContent = text;
            openModal('msgModal');
        }

        window.onclick = function(event) {
            if (event.target.classList.contains('modal-overlay')) {
                event.target.style.display = 'none';
            }
        }

        // Mobile Menu
        const hamburger = document.querySelector('.hamburger');
        const navMenu = document.querySelector('.nav-menu');
        if (hamburger) {
            hamburger.addEventListener('click', () => {
                navMenu.classList.toggle('active');
            });
        }
    </script>
</body>
</html>