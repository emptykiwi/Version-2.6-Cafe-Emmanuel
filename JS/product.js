const cartBtn = document.getElementById('cartBtn');
const cartSidebar = document.getElementById('cartSidebar');
const closeCart = document.getElementById('closeCart');
const cartItemsContainer = document.getElementById('cartItems');
const cartTotal = document.getElementById('cartTotal');
const addButtons = document.querySelectorAll('.add');
const addToCartBtn = document.querySelector('.add-to-cart');
const clearCartBtn = document.getElementById('clearCart');

let cart = JSON.parse(localStorage.getItem('cart')) || [];
updateCartDisplay();

// Open Cart Sidebar
if (cartBtn) {
    cartBtn.addEventListener('click', () => {
      cartSidebar.classList.add('open');
      updateCartDisplay();
    });
}

// Close Cart Sidebar
if (closeCart) {
    closeCart.addEventListener('click', () => {
      cartSidebar.classList.remove('open');
    });
}


if (addToCartBtn) {
    addToCartBtn.addEventListener('click', () => {
      const productName = document.querySelector('.product-details h1').textContent;
      const price = parseFloat(document.querySelector('.price').textContent.replace('₱', '').replace(',', ''));
      const quantity = parseInt(document.querySelector('.quantity-selector input').value);
      const selectedSize = document.querySelector('.sizes button.active');
      const selectedColor = document.querySelector('.color.active');
      const imageSrc = document.querySelector('.product-image img').src;

      if (!selectedSize || !selectedColor) {
        alert('Please select size and color');
        return;
      }

      const item = {
        name: productName,
        price: price,
        quantity: quantity,
        size: selectedSize.textContent,
        color: selectedColor.classList.contains('blue') ? 'Blue' : 'Black',
        image: imageSrc
      };

      const existingIndex = cart.findIndex(c => c.name === item.name && c.size === item.size && c.color === item.color);
      if (existingIndex > -1) {
        cart[existingIndex].quantity += item.quantity;
      } else {
        cart.push(item);
      }

      localStorage.setItem('cart', JSON.stringify(cart));
      updateCartDisplay();
    });
}

function updateCartDisplay() {
  if (!cartItemsContainer) return;
  cartItemsContainer.innerHTML = '';
  let total = 0;
  cart.forEach((item, index) => {
    total += item.price * item.quantity;

    const cartItem = document.createElement('div');
    cartItem.classList.add('cart-item');
    cartItem.innerHTML = `
      <img src="${item.image}" alt="${item.name}">
      <div class="text">
        <p><strong>${item.name}</strong></p>
        <p>₱${item.price.toFixed(2)} x ${item.quantity}</p>
        <p>Size: ${item.size} | Color: ${item.color}</p>
        <div>
          <button onclick="changeQuantity(${index}, -1)">-</button>
          <span>${item.quantity}</span>
          <button onclick="changeQuantity(${index}, 1)">+</button>
        </div>
      </div>
    `;
    cartItemsContainer.appendChild(cartItem);
  });
  if (cartTotal) cartTotal.textContent = total.toFixed(2);
}

function changeQuantity(index, change) {
  if (cart[index].quantity + change <= 0) {
    cart.splice(index, 1);
  } else {
    cart[index].quantity += change;
  }
  localStorage.setItem('cart', JSON.stringify(cart));
  updateCartDisplay();
}

function removeItem(index) {
  cart.splice(index, 1);
  localStorage.setItem('cart', JSON.stringify(cart));
  updateCartDisplay();
}

if (clearCartBtn) {
    clearCartBtn.addEventListener('click', () => {
      cart = [];
      localStorage.removeItem('cart');
      updateCartDisplay();
    });
}

// Size & Color Selection (Make them selectable)
document.querySelectorAll('.sizes button').forEach(button => {
  button.addEventListener('click', () => {
    document.querySelectorAll('.sizes button').forEach(btn => btn.classList.remove('active'));
    button.classList.add('active');
  });
});

document.querySelectorAll('.color').forEach(color => {
  color.addEventListener('click', () => {
    document.querySelectorAll('.color').forEach(clr => clr.classList.remove('active'));
    color.classList.add('active');
  });
});


// Logic for loading product details on quantity.php
const product = JSON.parse(localStorage.getItem('selectedProduct'));

// Only run this if we are on the product details page (checking for element existence)
if (product && document.querySelector('.product-details h1')) {
  document.querySelector('.product-details h1').textContent = product.name;
  document.querySelector('.price').textContent = `₱${product.price.toLocaleString()}`;
  document.querySelector('.product-image img').src = product.image;

  // Display rating stars dynamically
  const stars = '★'.repeat(product.rating) + '☆'.repeat(5 - product.rating);
  document.querySelector('.rating').textContent = stars;
} 
// If no product in storage and we are on quantity.php, redirect back
else if (!product && window.location.pathname.includes('quantity.php')) {
  window.location.href = 'product.php';
}



function goTocart() {
  const productName = document.querySelector('.product-details h1').textContent;
  const price = parseFloat(document.querySelector('.price').textContent.replace('₱', '').replace(',', ''));
  const quantity = parseInt(document.querySelector('.quantity-selector input').value);
  const selectedSize = document.querySelector('.sizes button.active');
  const selectedColor = document.querySelector('.color.active');
  const imageSrc = document.querySelector('.product-image img').src;

  if (!selectedSize || !selectedColor) {
    alert('Please select size and color before buying');
    return;
  }

  const item = {
    name: productName,
    price: price,
    quantity: quantity,
    size: selectedSize.textContent,
    color: selectedColor.classList.contains('blue') ? 'Blue' : 'Black',
    image: imageSrc
  };

  let cart = JSON.parse(localStorage.getItem('cart')) || [];
  const existingIndex = cart.findIndex(c => c.name === item.name && c.size === item.size && c.color === item.color);
  if (existingIndex > -1) {
    cart[existingIndex].quantity += item.quantity;
  } else {
    cart.push(item);
  }

  localStorage.setItem('cart', JSON.stringify(cart));
  window.location.href = "cart.php";
}

function changeMainImage(element) {
  document.querySelector('.product-image img').src = element.src;
}


// --- STEP 3: NEW FUNCTION FOR PRICE UPDATE ON MENU GRID ---
function updatePrice(selectElement) {
    // 1. Get the selected option (e.g., "Large")
    const selectedOption = selectElement.options[selectElement.selectedIndex];
    const newPrice = selectedOption.getAttribute('data-price');
    const newSize = selectedOption.value;

    // 2. Find the product card that contains this dropdown
    // Note: We used class 'product-box' in product.php now to help find it
    const productBox = selectElement.closest('.product-box');

    if (productBox) {
        // 3. Update the visible price text
        const priceSpan = productBox.querySelector('.price');
        if(priceSpan) {
            priceSpan.innerText = '₱' + parseFloat(newPrice).toFixed(2);
        }

        // 4. Update the "Add to Cart" button's hidden data
        const cartBtn = productBox.querySelector('.add-cart');
        if(cartBtn) {
            cartBtn.setAttribute('data-price', newPrice);
            cartBtn.setAttribute('data-size', newSize);
        }
    }
}