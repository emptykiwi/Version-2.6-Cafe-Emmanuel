document.addEventListener('DOMContentLoaded', function() {
    // --- DOM Elements ---
    const cartItemsContainer = document.getElementById('cartItemsContainer');
    const summaryInfo = document.getElementById('summaryInfo');
    const totalSection = document.getElementById('totalSection');
    const checkoutBtn = document.getElementById('checkoutBtn');
    const emptyCartView = document.getElementById('emptyCartView');
    const cartContent = document.getElementById('cartContent');
    
    // --- Modal Elements ---
    const deliveryModal = document.getElementById('deliveryModal');
    const deliveryForm = document.getElementById('deliveryForm');
    const locationSelect = document.getElementById('location');
    const modalSubtotal = document.getElementById('modalSubtotal');
    const modalShipping = document.getElementById('modalShipping');
    const modalFinalTotal = document.getElementById('modalFinalTotal');

    // --- Application State ---
    let cart = [];
    try {
        cart = JSON.parse(localStorage.getItem('cart')) || [];
        
        // Filter out nulls or invalid items immediately
        cart = cart.filter(item => item && item.id && item.name);
        
        // Save cleaned version back
        localStorage.setItem('cart', JSON.stringify(cart));
    } catch (e) {
        console.error("Cart data corrupted, resetting.", e);
        localStorage.removeItem('cart');
        cart = [];
    }

    let shippingCost = 0;

    // --- INITIALIZATION ---
    initCart();
    injectConfirmModal();

    // --- CORE FUNCTIONS ---

    function initCart() {
        if (cart.length === 0) {
            if (emptyCartView) emptyCartView.style.display = 'block';
            if (cartContent) cartContent.style.display = 'none';
        } else {
            if (emptyCartView) emptyCartView.style.display = 'none';
            if (cartContent) cartContent.style.display = 'grid'; // Ensure grid layout is applied
            renderCartItems();
            renderSummary();
        }
    }

    function renderCartItems() {
        if (!cartItemsContainer) return;
        cartItemsContainer.innerHTML = '';

        cart.forEach((item, index) => {
            // Ensure numeric values
            const price = parseFloat(item.price) || 0;
            const quantity = parseInt(item.quantity) || 1;
            const total = price * quantity;
            // Default image if missing
            const img = item.image || 'assets/placeholder.jpg'; 

            const itemDiv = document.createElement('div');
            itemDiv.className = 'cart-item';
            
            itemDiv.innerHTML = `
                <img src="${img}" alt="${item.name}" class="cart-item-img" onerror="this.src='assets/placeholder.jpg'">
                <div class="cart-item-details">
                    <div class="item-name">${item.name}</div>
                    <div class="item-meta">Price: ₱${price.toLocaleString(undefined, {minimumFractionDigits: 2})}</div>
                    <div class="quantity-control">
                        <button class="qty-btn" type="button" onclick="window.updateQuantity(${index}, -1)">-</button>
                        <span class="qty-display">${quantity}</span>
                        <button class="qty-btn" type="button" onclick="window.updateQuantity(${index}, 1)">+</button>
                    </div>
                </div>
                <div class="item-actions">
                    <div class="item-price">₱${total.toLocaleString(undefined, {minimumFractionDigits: 2})}</div>
                    <button class="remove-btn" type="button" onclick="window.removeItem(${index})">
                        <i class="fas fa-trash"></i>
                    </button>
                </div>
            `;
            cartItemsContainer.appendChild(itemDiv);
        });
    }

    function renderSummary() {
        if (!summaryInfo) return;
        
        const subtotal = cart.reduce((sum, item) => {
            return sum + ((parseFloat(item.price) || 0) * (parseInt(item.quantity) || 1));
        }, 0);
        
        summaryInfo.innerHTML = `
            <div class="summary-row">
                <span>Subtotal (${cart.length} items)</span>
                <span>₱${subtotal.toLocaleString(undefined, {minimumFractionDigits: 2})}</span>
            </div>
        `;

        if (totalSection) {
            const totalSpan = totalSection.querySelector('span:last-child');
            if (totalSpan) totalSpan.textContent = `₱${subtotal.toLocaleString(undefined, {minimumFractionDigits: 2})}`;
        }
    }

    function updateModalTotals() {
        const subtotal = cart.reduce((sum, item) => sum + ((parseFloat(item.price) || 0) * (parseInt(item.quantity) || 1)), 0);
        const total = Math.max(0, subtotal + shippingCost);

        if (modalSubtotal) modalSubtotal.textContent = `₱${subtotal.toLocaleString(undefined, {minimumFractionDigits: 2})}`;
        if (modalShipping) modalShipping.textContent = `₱${shippingCost.toLocaleString(undefined, {minimumFractionDigits: 2})}`;
        if (modalFinalTotal) modalFinalTotal.textContent = `₱${total.toLocaleString(undefined, {minimumFractionDigits: 2})}`;
    }

    function saveAndRefresh() {
        localStorage.setItem('cart', JSON.stringify(cart));
        // No need to re-parse, just re-render current state
        initCart();
    }

    // --- CUSTOM CONFIRM MODAL ---
    function injectConfirmModal() {
        if (!document.getElementById('customConfirmModal')) {
            const html = `
            <div id="customConfirmModal" class="modal-overlay" style="z-index: 3000;">
                <div class="modal-box" style="max-width: 350px; text-align: center;">
                    <h3 class="modal-title" style="margin-bottom: 15px;">Confirm Action</h3>
                    <p id="confirmText" style="margin-bottom: 25px; color: #666;">Are you sure?</p>
                    <div style="display: flex; gap: 10px; justify-content: center;">
                        <button id="confirmBtnNo" class="modal-btn" style="background:#ccc; color:#333;">Cancel</button>
                        <button id="confirmBtnYes" class="modal-btn" style="background:#dc3545;">Yes, Remove</button>
                    </div>
                </div>
            </div>`;
            document.body.insertAdjacentHTML('beforeend', html);
        }
    }

    function showConfirm(message, onConfirm) {
        const modal = document.getElementById('customConfirmModal');
        if (!modal) { if(confirm(message)) onConfirm(); return; } // Fallback

        document.getElementById('confirmText').textContent = message;
        modal.style.display = 'flex';

        const yesBtn = document.getElementById('confirmBtnYes');
        const noBtn = document.getElementById('confirmBtnNo');
        
        // Clone to remove old listeners
        const newYes = yesBtn.cloneNode(true);
        const newNo = noBtn.cloneNode(true);
        yesBtn.parentNode.replaceChild(newYes, yesBtn);
        noBtn.parentNode.replaceChild(newNo, noBtn);

        newYes.addEventListener('click', () => {
            modal.style.display = 'none';
            onConfirm();
        });

        newNo.addEventListener('click', () => {
            modal.style.display = 'none';
        });
    }

    // --- GLOBAL ACTIONS ---
    window.updateQuantity = function(index, change) {
        const newQty = parseInt(cart[index].quantity) + change;
        if (newQty > 0) {
            cart[index].quantity = newQty;
            saveAndRefresh();
        } else {
            showConfirm('Remove this item from your cart?', () => {
                cart.splice(index, 1);
                saveAndRefresh();
            });
        }
    };

    window.removeItem = function(index) {
        showConfirm('Are you sure you want to remove this item?', () => {
            cart.splice(index, 1);
            saveAndRefresh();
        });
    };

    window.closeModal = function(id) {
        const modal = document.getElementById(id);
        if (modal) modal.style.display = 'none';
    };

    // --- EVENT LISTENERS ---
    if (checkoutBtn) {
        checkoutBtn.addEventListener('click', () => {
            if (cart.length === 0) {
                if(window.showMessage) window.showMessage('Cart Empty', 'Your cart is empty!', true);
                else alert('Your cart is empty!');
                return;
            }
            if (deliveryModal) {
                deliveryModal.style.display = 'flex';
                updateModalTotals();
            }
        });
    }

    if (locationSelect) {
        locationSelect.addEventListener('change', function() {
            shippingCost = parseFloat(this.value) || 0;
            updateModalTotals();
        });
    }

    if (deliveryForm) {
        deliveryForm.addEventListener('submit', function(e) {
            e.preventDefault();
            const submitBtn = deliveryForm.querySelector('.submit-btn');
            const originalText = submitBtn.innerText;
            submitBtn.innerText = "Processing...";
            submitBtn.disabled = true;

            const formData = new FormData(deliveryForm);
            const subtotal = cart.reduce((sum, item) => sum + ((parseFloat(item.price)||0) * (parseInt(item.quantity)||1)), 0);
            const total = Math.max(0, subtotal + shippingCost); 
            
            let paymentMethod = 'COD';
            const paymentElement = document.querySelector('input[name="payment"]:checked');
            if (paymentElement) paymentMethod = paymentElement.value;

            let locationText = '';
            if (locationSelect.selectedIndex >= 0) {
                locationText = locationSelect.options[locationSelect.selectedIndex].text;
            }
            const fullAddress = `${formData.get('address')}, ${locationText}`;

            const payload = {
                fullname: formData.get('fullname'),
                contact: formData.get('contact'),
                address: fullAddress,
                payment: paymentMethod,
                items: cart,
                cart: cart, 
                total: total
            };

            fetch('place_order.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(payload)
            })
            .then(response => response.json())
            .then(data => {
                submitBtn.innerText = originalText;
                submitBtn.disabled = false;

                if (data.status === 'success' || data.success) {
                    if (data.checkout_url) {
                        localStorage.removeItem('cart'); 
                        window.location.href = data.checkout_url; 
                    } else {
                        if(window.showMessage) window.showMessage('Success!', 'Your order has been placed successfully.');
                        else alert('Order placed successfully!');
                        
                        localStorage.removeItem('cart');
                        setTimeout(() => { window.location.href = 'my_orders.php'; }, 2000);
                    }
                } else {
                    const errMsg = (data.message || data.error || 'Unknown error occurred');
                    if(window.showMessage) window.showMessage('Error', errMsg, true);
                    else alert(errMsg);
                }
            })
            .catch(error => {
                console.error('Error:', error);
                submitBtn.innerText = originalText;
                submitBtn.disabled = false;
                if(window.showMessage) window.showMessage('Error', 'Connection failed. Please try again.', true);
                else alert('Connection failed.');
            });
        });
    }

    // Close modals on outside click
    window.onclick = function(event) {
        if (event.target.classList.contains('modal-overlay')) {
            event.target.style.display = 'none';
        }
    };
});