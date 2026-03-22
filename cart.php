<?php
session_start();
include 'db_connect.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="icon" type="image/png" href="Logo_Brand.png">
    <title>Your Cart - Cafe Emmanuel</title>
    
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" />
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:ital,wght@0,500;0,700;1,500&family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    
    <style>
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
            --shadow: 0 10px 40px rgba(44, 30, 22, 0.05);
        }

        * { box-sizing: border-box; margin: 0; padding: 0; }

        body {
            font-family: var(--font-body);
            background-color: var(--bg-main);
            color: var(--text-dark);
            line-height: 1.6;
        }

        .navbar {
            background: var(--secondary);
            padding: 15px 5%;
            display: flex;
            justify-content: space-between;
            align-items: center;
            position: sticky;
            top: 0;
            z-index: 1000;
            box-shadow: 0 4px 20px rgba(0,0,0,0.1);
        }

        .navbar-brand {
            display: flex;
            align-items: center;
            text-decoration: none;
            gap: 12px;
        }

        .navbar-brand img { height: 50px; width: auto; }
        .navbar-brand span { 
            font-family: var(--font-heading); 
            color: var(--accent); 
            font-size: 22px; 
            font-weight: 700; 
            letter-spacing: 1px;
        }

        .cart-container {
            max-width: 1100px;
            margin: 60px auto;
            padding: 0 20px;
        }

        .page-title {
            font-family: var(--font-heading);
            font-size: 36px;
            color: var(--secondary);
            margin-bottom: 40px;
            text-align: center;
            position: relative;
        }

        .page-title::after {
            content: '';
            display: block;
            width: 60px;
            height: 3px;
            background: var(--primary);
            margin: 15px auto 0;
        }

        .cart-wrapper {
            display: grid;
            grid-template-columns: 1fr 350px;
            gap: 30px;
            align-items: start;
        }

        .cart-card {
            background: var(--bg-card);
            border-radius: 16px;
            padding: 30px;
            box-shadow: var(--shadow);
            border: 1px solid rgba(160, 94, 68, 0.05);
        }

        .cart-table { width: 100%; border-collapse: collapse; }
        .cart-table th {
            text-align: left;
            padding: 15px 10px;
            font-size: 12px;
            text-transform: uppercase;
            letter-spacing: 1px;
            color: var(--text-muted);
            border-bottom: 2px solid var(--bg-main);
        }
        .cart-table td {
            padding: 20px 10px;
            border-bottom: 1px solid var(--bg-main);
            vertical-align: middle;
        }

        .product-info { display: flex; align-items: center; gap: 20px; }
        .product-img {
            width: 80px; height: 80px;
            object-fit: cover; border-radius: 12px;
            border: 1px solid var(--border-color);
        }

        .product-name {
            font-family: var(--font-heading);
            font-size: 18px; font-weight: 700;
            color: var(--secondary); margin-bottom: 4px;
        }

        .product-cat { font-size: 12px; color: var(--text-muted); text-transform: uppercase; }
        .price-text { font-weight: 600; color: var(--primary); font-size: 16px; }

        .qty-control {
            display: flex; align-items: center;
            background: var(--bg-main); border-radius: 8px;
            width: fit-content; overflow: hidden;
            border: 1px solid var(--border-color);
        }

        .qty-btn {
            background: none; border: none; padding: 8px 12px;
            cursor: pointer; color: var(--secondary); transition: 0.3s;
        }
        .qty-btn:hover { background: var(--accent); color: white; }

        .qty-input {
            width: 40px; text-align: center; border: none;
            background: transparent; font-weight: 600; font-family: var(--font-body);
        }

        .remove-btn { background: none; border: none; color: #c62828; cursor: pointer; font-size: 18px; transition: 0.3s; }
        .remove-btn:hover { transform: scale(1.2); }

        .summary-card {
            background: var(--secondary); border-radius: 16px; padding: 30px; color: #fff;
            position: sticky; top: 100px; box-shadow: 0 15px 35px rgba(44, 30, 22, 0.15);
        }

        .summary-card h3 {
            font-family: var(--font-heading); font-size: 24px; margin-bottom: 25px;
            border-bottom: 1px solid rgba(212, 163, 115, 0.2); padding-bottom: 15px; color: var(--accent);
        }

        .summary-row { display: flex; justify-content: space-between; margin-bottom: 15px; font-size: 15px; }
        .summary-total {
            display: flex; justify-content: space-between; margin-top: 25px; padding-top: 20px;
            border-top: 1px solid rgba(255,255,255,0.1); font-size: 20px; font-weight: 700; font-family: var(--font-heading);
        }

        .btn-checkout {
            width: 100%; background: var(--primary); color: white; border: none; padding: 16px; border-radius: 12px;
            font-weight: 700; font-family: var(--font-body); font-size: 16px; margin-top: 30px; cursor: pointer; transition: 0.3s;
            display: flex; align-items: center; justify-content: center; gap: 10px; text-decoration: none;
        }
        .btn-checkout:hover { background: #fff; color: var(--secondary); transform: translateY(-3px); box-shadow: 0 10px 20px rgba(0,0,0,0.2); }

        .empty-cart { text-align: center; padding: 60px 0; }
        .empty-cart i { color: var(--border-color); margin-bottom: 20px; }
        
        @media (max-width: 900px) {
            .cart-wrapper { grid-template-columns: 1fr; }
            .summary-card { position: static; }
        }
    </style>
</head>
<body>

<nav class="navbar">
    <a href="index.php" class="navbar-brand">
        <img src="Logo_Brand.png" alt="Cafe Emmanuel">
        <span>CAFE EMMANUEL</span>
    </a>
    <div style="color: white;">
        <a href="index.php" style="color: var(--text-light); text-decoration: none; font-weight: 500;">Back to Menu</a>
    </div>
</nav>

<main class="cart-container">
    <h1 class="page-title">Your Selections</h1>

    <div id="cart-content-wrapper" style="display: none;">
        <div class="cart-wrapper">
            <div class="cart-card">
                <table class="cart-table">
                    <thead>
                        <tr>
                            <th>Product</th>
                            <th>Price</th>
                            <th>Quantity</th>
                            <th>Subtotal</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody id="cart-items-tbody">
                        </tbody>
                </table>
            </div>

            <aside class="summary-card">
                <h3>Order Summary</h3>
                <div class="summary-row">
                    <span>Subtotal</span>
                    <span id="summary-subtotal">₱0.00</span>
                </div>
                <div class="summary-row">
                    <span>Service Fee</span>
                    <span>₱0.00</span>
                </div>
                <div class="summary-total">
                    <span>TOTAL</span>
                    <span id="summary-total" style="color: var(--accent);">₱0.00</span>
                </div>

                <a href="checkout.php" class="btn-checkout" onclick="return validateCheckout()">
                    Proceed to Checkout <i class="fas fa-arrow-right"></i>
                </a>
                
                <p style="font-size: 11px; color: rgba(255,255,255,0.4); text-align: center; margin-top: 20px;">
                    Secure payment processing. artisanal quality guaranteed.
                </p>
            </aside>
        </div>
    </div>

    <div id="empty-cart-view" class="cart-card empty-cart" style="display: none;">
        <i class="fas fa-shopping-basket fa-5x"></i>
        <h2 style="font-family: var(--font-heading); color: var(--secondary); margin-top: 20px;">Your basket is empty</h2>
        <p style="color: var(--text-muted); margin-bottom: 30px;">Looks like you haven't picked your coffee yet.</p>
        <a href="index.php" class="btn-checkout" style="display: inline-flex; width: auto; padding: 14px 40px;">Browse Menu</a>
    </div>
</main>

<script>
    document.addEventListener('DOMContentLoaded', () => {
        renderCart();
    });

    function getCart() {
        let cart = localStorage.getItem('cart');
        return cart ? JSON.parse(cart) : [];
    }

    function saveCart(cart) {
        localStorage.setItem('cart', JSON.stringify(cart));
    }

    function renderCart() {
        const cart = getCart();
        const contentWrapper = document.getElementById('cart-content-wrapper');
        const emptyView = document.getElementById('empty-cart-view');
        const tbody = document.getElementById('cart-items-tbody');
        const summarySubtotal = document.getElementById('summary-subtotal');
        const summaryTotal = document.getElementById('summary-total');

        if (cart.length === 0) {
            contentWrapper.style.display = 'none';
            emptyView.style.display = 'block';
            return;
        }

        contentWrapper.style.display = 'block';
        emptyView.style.display = 'none';
        tbody.innerHTML = '';
        let totalAmount = 0;

        cart.forEach((item, index) => {
            const price = parseFloat(item.price) || 0;
            const quantity = parseInt(item.quantity) || 1;
            const subtotal = price * quantity;
            totalAmount += subtotal;

            const imageSrc = item.image || 'logo.png';
            const name = item.name || 'Unknown Product';
            const category = item.size ? `Size: ${item.size}` : '';

            const tr = document.createElement('tr');
            tr.innerHTML = `
                <td>
                    <div class="product-info">
                        <img src="${imageSrc}" class="product-img" onerror="this.src='logo.png'">
                        <div>
                            <div class="product-name">${name}</div>
                            <div class="product-cat">${category}</div>
                        </div>
                    </div>
                </td>
                <td class="price-text">₱${price.toFixed(2)}</td>
                <td>
                    <div class="qty-control">
                        <button class="qty-btn" onclick="updateQty(${index}, -1)"><i class="fas fa-minus"></i></button>
                        <input type="text" class="qty-input" value="${quantity}" readonly>
                        <button class="qty-btn" onclick="updateQty(${index}, 1)"><i class="fas fa-plus"></i></button>
                    </div>
                </td>
                <td class="price-text" style="color: var(--secondary);">₱${subtotal.toFixed(2)}</td>
                <td>
                    <button class="remove-btn" onclick="removeItem(${index})" title="Remove Item"><i class="far fa-trash-alt"></i></button>
                </td>
            `;
            tbody.appendChild(tr);
        });

        summarySubtotal.textContent = `₱${totalAmount.toFixed(2)}`;
        summaryTotal.textContent = `₱${totalAmount.toFixed(2)}`;
    }

    function updateQty(index, delta) {
        const cart = getCart();
        if (cart[index]) {
            let newQty = parseInt(cart[index].quantity) + delta;
            if (newQty > 0) {
                cart[index].quantity = newQty;
                saveCart(cart);
                renderCart();
            } else {
                removeItem(index);
            }
        }
    }

    function removeItem(index) {
        if (confirm('Are you sure you want to remove this item from your cart?')) {
            const cart = getCart();
            cart.splice(index, 1);
            saveCart(cart);
            renderCart();
        }
    }

    function validateCheckout() {
        const cart = getCart();
        if (cart.length === 0) {
            alert('Your cart is empty! Please add some items before checking out.');
            return false;
        }
        return true;
    }
</script>

</body>
</html>