<?php
session_start();

// Pre-fill user data if logged in
$user_fullname = $_SESSION['fullname'] ?? '';
$user_contact = $_SESSION['contact'] ?? '';
$user_address = $_SESSION['address'] ?? '';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="icon" type="image/png" href="Logo_Brand.png">
    <title>Checkout - Cafe Emmanuel</title>
    
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
            --shadow: 0 10px 40px rgba(44, 30, 22, 0.05);
        }

        * { box-sizing: border-box; margin: 0; padding: 0; }
        body { font-family: var(--font-body); background-color: var(--bg-main); color: var(--text-dark); line-height: 1.6; }

        /* NAVBAR */
        .navbar { background: var(--secondary); padding: 15px 5%; display: flex; justify-content: space-between; align-items: center; position: sticky; top: 0; z-index: 1000; box-shadow: 0 4px 20px rgba(0,0,0,0.1); }
        .navbar-brand { display: flex; align-items: center; text-decoration: none; gap: 12px; }
        .navbar-brand img { height: 50px; width: auto; }
        .navbar-brand span { font-family: var(--font-heading); color: var(--accent); font-size: 22px; font-weight: 700; letter-spacing: 1px; }
        .back-link { color: white; text-decoration: none; font-weight: 500; transition: 0.3s; }
        .back-link:hover { color: var(--accent); }

        /* LAYOUT */
        .checkout-container { max-width: 1100px; margin: 60px auto; padding: 0 20px; }
        .page-title { font-family: var(--font-heading); font-size: 36px; color: var(--secondary); margin-bottom: 40px; text-align: center; position: relative; }
        .page-title::after { content: ''; display: block; width: 60px; height: 3px; background: var(--primary); margin: 15px auto 0; }

        .checkout-wrapper { display: grid; grid-template-columns: 1.5fr 1fr; gap: 30px; align-items: start; }
        .card { background: var(--bg-card); border-radius: 16px; padding: 30px; box-shadow: var(--shadow); border: 1px solid rgba(160, 94, 68, 0.05); }
        .card h3 { font-family: var(--font-heading); font-size: 22px; color: var(--secondary); margin-bottom: 20px; border-bottom: 1px solid var(--border-color); padding-bottom: 10px; }

        /* FORM STYLES */
        .form-group { margin-bottom: 20px; }
        .form-group label { display: block; font-weight: 600; margin-bottom: 8px; color: var(--secondary); font-size: 14px; }
        .form-control { width: 100%; padding: 12px 15px; border: 1px solid var(--border-color); border-radius: 8px; font-family: var(--font-body); font-size: 15px; transition: 0.3s; background: var(--bg-main); }
        .form-control:focus { outline: none; border-color: var(--primary); box-shadow: 0 0 0 3px rgba(160, 94, 68, 0.1); background: #fff; }
        textarea.form-control { resize: vertical; min-height: 100px; }

        /* PAYMENT OPTIONS */
        .payment-options { display: flex; flex-direction: column; gap: 15px; }
        .payment-option { display: flex; align-items: center; padding: 15px; border: 1px solid var(--border-color); border-radius: 8px; cursor: pointer; transition: 0.3s; }
        .payment-option:hover { border-color: var(--primary); background: rgba(160, 94, 68, 0.02); }
        .payment-option input[type="radio"] { margin-right: 15px; accent-color: var(--primary); transform: scale(1.2); }
        .payment-option label { cursor: pointer; font-weight: 600; width: 100%; display: flex; justify-content: space-between; align-items: center; margin: 0;}
        .payment-icon { font-size: 20px; color: var(--primary); }

        /* SUMMARY STYLES */
        .summary-item { display: flex; justify-content: space-between; align-items: center; padding: 10px 0; border-bottom: 1px dashed var(--border-color); font-size: 14px; }
        .summary-item:last-child { border-bottom: none; }
        .summary-item span:last-child { font-weight: 600; color: var(--secondary); }
        .summary-total { display: flex; justify-content: space-between; margin-top: 20px; padding-top: 20px; border-top: 2px solid var(--border-color); font-size: 20px; font-weight: 700; font-family: var(--font-heading); color: var(--primary); }

        /* BUTTONS */
        .btn-submit { width: 100%; background: var(--primary); color: white; border: none; padding: 16px; border-radius: 12px; font-weight: 700; font-family: var(--font-body); font-size: 16px; margin-top: 30px; cursor: pointer; transition: 0.3s; display: flex; align-items: center; justify-content: center; gap: 10px; }
        .btn-submit:hover { background: var(--secondary); transform: translateY(-2px); box-shadow: 0 10px 20px rgba(0,0,0,0.1); }
        .btn-submit:disabled { background: #ccc; cursor: not-allowed; transform: none; box-shadow: none; }

        @media (max-width: 900px) { .checkout-wrapper { grid-template-columns: 1fr; } }
    </style>
</head>
<body>

<nav class="navbar">
    <a href="index.php" class="navbar-brand">
        <img src="Logo_Brand.png" alt="Cafe Emmanuel" onerror="this.src='logo.png'">
        <span>CAFE EMMANUEL</span>
    </a>
    <a href="cart.php" class="back-link"><i class="fas fa-arrow-left"></i> Back to Cart</a>
</nav>

<main class="checkout-container">
    <h1 class="page-title">Checkout</h1>

    <div class="checkout-wrapper">
        <div class="card">
            <h3>Delivery Details</h3>
            <form id="checkoutForm">
                <div class="form-group">
                    <label for="fullname">Full Name</label>
                    <input type="text" id="fullname" name="fullname" class="form-control" value="<?php echo htmlspecialchars($user_fullname); ?>" required placeholder="e.g. John Doe">
                </div>
                
                <div class="form-group">
                    <label for="contact">Contact Number</label>
                    <input type="text" id="contact" name="contact" class="form-control" value="<?php echo htmlspecialchars($user_contact); ?>" required placeholder="e.g. 09123456789">
                </div>

                <div class="form-group">
                    <label for="address">Delivery Address</label>
                    <textarea id="address" name="address" class="form-control" required placeholder="Complete address for delivery..."><?php echo htmlspecialchars($user_address); ?></textarea>
                </div>

                <h3 style="margin-top: 40px;">Payment Method</h3>
                <div class="payment-options">
                    <label class="payment-option">
                        <input type="radio" name="payment" value="COD" checked>
                        <span>Cash on Delivery (COD)</span>
                        <i class="fas fa-money-bill-wave payment-icon"></i>
                    </label>
                    <label class="payment-option">
                        <input type="radio" name="payment" value="GCash">
                        <span>GCash (PayMongo)</span>
                        <img src="https://getlogovector.com/wp-content/uploads/2020/11/gcash-logo-vector.png" alt="GCash" style="height:20px;">
                    </label>
                    <label class="payment-option">
                        <input type="radio" name="payment" value="GrabPay">
                        <span>GrabPay (PayMongo)</span>
                        <i class="fas fa-wallet payment-icon"></i>
                    </label>
                </div>

                <button type="submit" class="btn-submit" id="submitBtn">
                    Place Order <i class="fas fa-check-circle"></i>
                </button>
            </form>
        </div>

        <div class="card" style="position: sticky; top: 100px;">
            <h3>Order Summary</h3>
            <div id="summaryItemsContainer">
                </div>
            
            <div class="summary-total">
                <span>Total Amount</span>
                <span id="summaryTotalAmount">₱0.00</span>
            </div>
        </div>
    </div>
</main>

<script>
    document.addEventListener('DOMContentLoaded', () => {
        // 1. Load cart from LocalStorage
        let cart = JSON.parse(localStorage.getItem('cart')) || [];
        
        if (cart.length === 0) {
            alert("Your cart is empty. Redirecting to menu...");
            window.location.href = "index.php";
            return;
        }

        // 2. Render Order Summary
        const container = document.getElementById('summaryItemsContainer');
        const totalDisplay = document.getElementById('summaryTotalAmount');
        let grandTotal = 0;

        cart.forEach(item => {
            const price = parseFloat(item.price) || 0;
            const quantity = parseInt(item.quantity) || 1;
            const subtotal = price * quantity;
            grandTotal += subtotal;

            const div = document.createElement('div');
            div.className = 'summary-item';
            div.innerHTML = `
                <span>${quantity}x ${item.name} ${item.size ? `(${item.size})` : ''}</span>
                <span>₱${subtotal.toFixed(2)}</span>
            `;
            container.appendChild(div);
        });

        totalDisplay.textContent = `₱${grandTotal.toFixed(2)}`;

        // 3. Handle Form Submission
        const form = document.getElementById('checkoutForm');
        const submitBtn = document.getElementById('submitBtn');

        form.addEventListener('submit', function(e) {
            e.preventDefault();
            
            // Disable button to prevent double clicks
            submitBtn.disabled = true;
            submitBtn.innerHTML = 'Processing... <i class="fas fa-spinner fa-spin"></i>';

            // Get Form Data
            const formData = new FormData(form);
            const paymentMethod = formData.get('payment');

            // Build Payload specifically for place_order.php
            const payload = {
                fullname: formData.get('fullname'),
                contact: formData.get('contact'),
                address: formData.get('address'),
                payment: paymentMethod,
                cart: cart,     // your place_order.php expects 'cart' or 'items'
                total: grandTotal
            };

            // Send via AJAX to your existing working place_order script
            fetch('place_order.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(payload)
            })
            .then(response => response.json())
            .then(data => {
                if (data.status === 'success') {
                    // Clear LocalStorage Cart since order is placed
                    localStorage.removeItem('cart');

                    // If PayMongo generated a checkout URL (GCash/GrabPay)
                    if (data.checkout_url) {
                        window.location.href = data.checkout_url;
                    } else {
                        // Standard COD success
                        alert(data.message || 'Order placed successfully!');
                        window.location.href = 'my_orders.php';
                    }
                } else {
                    alert('Error: ' + (data.message || 'Something went wrong.'));
                    submitBtn.disabled = false;
                    submitBtn.innerHTML = 'Place Order <i class="fas fa-check-circle"></i>';
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('A network error occurred. Please try again.');
                submitBtn.disabled = false;
                submitBtn.innerHTML = 'Place Order <i class="fas fa-check-circle"></i>';
            });
        });
    });
</script>

</body>
</html>