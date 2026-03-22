<?php
include 'session_check.php';
include 'db_connect.php';
require_once 'audit_log.php';

// --- 1. HANDLE FORM SUBMISSIONS (ADD / UPDATE) ---
$message = "";
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $action = $_POST['action'];
    $name = mysqli_real_escape_string($conn, $_POST['name']);
    $category = mysqli_real_escape_string($conn, $_POST['category']);
    $price = $_POST['price']; 
    $stock = (int)$_POST['stock'];
    
    if ($action == 'add') {
        $image = "logo.png"; 
        if (isset($_FILES['image']) && $_FILES['image']['error'] == 0) {
            $image = "uploads/" . time() . "_" . basename($_FILES['image']['name']);
            move_uploaded_file($_FILES['image']['tmp_name'], $image);
        }
        $stmt = $conn->prepare("INSERT INTO products (name, category, price, stock, image, rating) VALUES (?, ?, ?, ?, ?, 5)");
        $stmt->bind_param("ssdis", $name, $category, $price, $stock, $image);
        if ($stmt->execute()) { 
            $message = "<i class='fas fa-check-circle'></i> Product added successfully!";
            $new_id = $conn->insert_id;
            logAdminAction($conn, $_SESSION['user_id'] ?? 0, $_SESSION['fullname'] ?? 'Admin', 'add_product', "Added new product: $name", 'products', $new_id);
        }
    } 
    elseif ($action == 'update') {
        $id = intval($_POST['id']);
        if (isset($_FILES['image']) && $_FILES['image']['error'] == 0) {
            $image = "uploads/" . time() . "_" . basename($_FILES['image']['name']);
            move_uploaded_file($_FILES['image']['tmp_name'], $image);
            $stmt = $conn->prepare("UPDATE products SET name=?, category=?, price=?, stock=?, image=? WHERE id=?");
            $stmt->bind_param("ssdisi", $name, $category, $price, $stock, $image, $id);
        } else {
            $stmt = $conn->prepare("UPDATE products SET name=?, category=?, price=?, stock=? WHERE id=?");
            $stmt->bind_param("ssdii", $name, $category, $price, $stock, $id);
        }
        if ($stmt->execute()) { 
            $message = "<i class='fas fa-check-circle'></i> Product updated successfully!";
            logAdminAction($conn, $_SESSION['user_id'] ?? 0, $_SESSION['fullname'] ?? 'Admin', 'update_product', "Updated product: $name", 'products', $id);
        }
    }
}

// --- 2. DATA FOR HEADER ICONS ---
$inquiry_count_result = $conn->query("SELECT COUNT(*) as count FROM inquiries WHERE status = 'new'");
$unread_inquiries = $inquiry_count_result ? $inquiry_count_result->fetch_assoc()['count'] : 0;

$recent_inquiries_result = $conn->query("SELECT * FROM inquiries WHERE status = 'new' ORDER BY received_at DESC LIMIT 5");
$recent_messages = [];
if ($recent_inquiries_result) {
    while ($row = $recent_inquiries_result->fetch_assoc()) { $recent_messages[] = $row; }
}

// --- 3. FETCH PRODUCTS & CATEGORIES ---
$categories = [];
$cat_result = $conn->query("SELECT DISTINCT category FROM products WHERE category IS NOT NULL ORDER BY category ASC");
while($row = $cat_result->fetch_assoc()) { $categories[] = $row['category']; }

$products_result = $conn->query("SELECT * FROM products ORDER BY id DESC");
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="icon" type="image/png" href="Logo_Brand.png">
    <title>Product Management - Cafe Emmanuel</title>
    
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" />
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:ital,wght@0,500;0,700;1,500&family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    
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
            margin-left: 260px; 
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
        
        .icon-container:hover i { color: #fff; }

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

        /* --- CARDS & FORMS --- */
        .card { 
            background: var(--bg-card); 
            padding: 30px; 
            border-radius: 16px; 
            box-shadow: var(--shadow-soft); 
            border: 1px solid rgba(160, 94, 68, 0.05); 
            margin-bottom: 30px; 
            position: relative;
            z-index: 1;
        }
        
        .card-header { 
            margin-bottom: 25px; 
            border-bottom: 1px dashed var(--border-color); 
            padding-bottom: 15px; 
            display: flex; 
            justify-content: space-between; 
            align-items: center; 
            flex-wrap: wrap; 
            gap: 10px;
        }
        
        .card-header h2 {
            font-family: var(--font-heading);
            font-size: 22px;
            color: var(--secondary);
        }

        .alert { 
            background: #e8f5e9; 
            color: #2e7d32; 
            padding: 15px 20px; 
            border-radius: 8px; 
            margin-bottom: 25px; 
            border: 1px solid rgba(46,125,50,0.2); 
            display: flex;
            align-items: center;
            gap: 10px;
            font-weight: 500;
        }
        
        .form-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 20px; }
        .form-group { display: flex; flex-direction: column; }
        .form-group label { font-size: 12px; font-weight: 600; color: #3a2b24; margin-bottom: 8px; text-transform: uppercase; letter-spacing: 0.5px; }
        
        .form-group input, .form-group select { 
            padding: 12px 15px; 
            border: 1px solid var(--border-color); 
            border-radius: 8px; 
            outline: none; 
            font-family: var(--font-body); 
            background: var(--bg-main);
            color: var(--text-dark);
            transition: all 0.3s;
        }
        
        .form-group input:focus, .form-group select:focus {
            border-color: var(--primary);
            box-shadow: 0 0 0 3px rgba(160, 94, 68, 0.1);
            background: #fff;
        }

        /* Highlight fields that are conditionally hidden */
        .attr-field { 
            display: none; 
            background-color: rgba(212, 163, 115, 0.05); 
            border: 1px dashed var(--accent); 
            padding: 15px; 
            border-radius: 8px; 
        }

        /* Buttons */
        .btn-main { 
            background: var(--primary); 
            color: white; 
            border: none; 
            padding: 14px 28px; 
            border-radius: 8px; 
            font-weight: 600; 
            font-family: var(--font-body);
            cursor: pointer; 
            transition: 0.3s; 
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
        }
        .btn-main:hover { 
            background: var(--secondary); 
            transform: translateY(-2px);
            box-shadow: 0 4px 15px rgba(44, 30, 22, 0.2);
        }
        
        .btn-cancel {
            background: var(--bg-main);
            color: var(--text-dark);
            border: 1px solid var(--border-color);
            padding: 14px 28px;
            border-radius: 8px;
            font-weight: 500;
            font-family: var(--font-body);
            cursor: pointer;
            transition: 0.3s;
        }
        .btn-cancel:hover {
            background: #e0e0e0;
            border-color: #ccc;
        }

        .search-input, .filter-select { 
            padding: 10px 20px; 
            border-radius: 50px; 
            border: 1px solid var(--border-color); 
            background: var(--bg-main); 
            font-size: 13px; 
            font-family: var(--font-body);
            outline: none; 
            transition: 0.3s;
        }
        
        .search-input:focus, .filter-select:focus {
            border-color: var(--primary);
            box-shadow: 0 0 0 3px rgba(160, 94, 68, 0.1);
            background: #fff;
        }

        /* --- TABLE --- */
        table { width: 100%; border-collapse: collapse; }
        th { 
            text-align: left; 
            padding: 15px; 
            font-size: 12px; 
            text-transform: uppercase; 
            color: #3a2b24; 
            border-bottom: 2px solid var(--border-color); 
            letter-spacing: 0.5px;
        }
        td { 
            padding: 15px; 
            border-bottom: 1px dashed var(--border-color); 
            font-size: 14px; 
            vertical-align: middle;
        }
        tr:hover td { background-color: #FDFBF7; }
        
        .product-img { 
            width: 50px; 
            height: 50px; 
            object-fit: cover; 
            border-radius: 8px; 
            border: 1px solid var(--border-color);
            box-shadow: 0 2px 5px rgba(0,0,0,0.05);
        }
        
        /* Action Buttons explicitly styled for visibility */
        .action-btn { 
            width: 36px; height: 36px; border-radius: 8px; display: inline-flex; align-items: center; justify-content: center; 
            border: 1px solid var(--border-color); background: var(--bg-card); cursor: pointer; transition: all 0.3s; 
            text-decoration: none; outline: none; padding: 0; margin-right: 5px;
        }
        .action-btn i { color: var(--primary); font-size: 1rem; transition: all 0.3s ease; }
        .action-btn:hover { background-color: var(--primary); border-color: var(--primary); transform: translateY(-2px); box-shadow: 0 4px 10px rgba(160, 94, 68, 0.2); }
        .action-btn:hover i { color: #ffffff; }

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
        @keyframes slideDown { from { opacity: 0; transform: translateY(-10px); } to { opacity: 1; transform: translateY(0); } }
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

        @media (max-width: 1024px) { .main-content { margin-left: 0; width: 100%; } }
    </style>
</head>
<body>
    <?php include 'admin_sidebar.php'; ?>

    <main class="main-content">
        <header class="main-header">
            <h1>Product Management</h1>
            <div class="header-icons">
                <div class="icon-container" id="msgBtn">
                    <i class="fas fa-envelope"></i>
                    <?php if($unread_inquiries > 0): ?><span class="header-badge"><?php echo $unread_inquiries; ?></span><?php endif; ?>
                    <div class="dropdown-menu" id="msgDrop">
                        <?php if(empty($recent_messages)): ?>
                            <div class="dropdown-item" style="text-align:center; color:#3a2b24;">No new messages</div>
                        <?php else: ?>
                            <?php foreach ($recent_messages as $m): ?>
                                <a href="admin_inquiries.php" class="dropdown-item">
                                    <strong style="color:var(--secondary);"><?php echo htmlspecialchars($m['first_name']); ?></strong><br>
                                    <span style="color:#3a2b24;"><?php echo htmlspecialchars(substr($m['message'], 0, 30)); ?>...</span>
                                </a>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>
                <div class="icon-container" style="border:none; box-shadow:none; background:transparent;">
                    <img src="Logo_Brand.png" alt="Admin" style="background:var(--secondary); width: 45px; height: 45px; border-radius: 50%; object-fit: cover; border: 2px solid var(--primary); padding: 2px;">
                </div>
            </div>
        </header>

        <?php if($message): ?><div class="alert"><?php echo $message; ?></div><?php endif; ?>

        <div class="card">
            <div class="card-header"><h2 id="form-title">Add New Product</h2></div>
            <form id="productForm" method="POST" enctype="multipart/form-data">
                <input type="hidden" name="action" id="form-action" value="add">
                <input type="hidden" name="id" id="prod-id">
                <input type="hidden" id="base-regular-price" value="0"> 
                
                <div class="form-grid">
                    <div class="form-group">
                        <label>Product Name</label>
                        <input type="text" name="name" id="prod-name" placeholder="e.g. Caramel Macchiato" required>
                    </div>
                    
                    <div class="form-group">
                        <label>Category</label>
                        <select name="category" id="prod-cat" required onchange="toggleAttributes()">
                            <option value="">Select Category</option>
                            <?php foreach($categories as $c): ?>
                                <option value="<?php echo htmlspecialchars($c); ?>"><?php echo htmlspecialchars($c); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="form-group attr-field" id="size-field">
                        <label>Cup Size (Calc Only)</label>
                        <select id="prod-size" onchange="calculateAutoPrice()">
                            <option value="regular">Regular</option>
                            <option value="large">Large (+₱10)</option>
                        </select>
                    </div>

                    <div class="form-group attr-field" id="temp-field">
                        <label>Temperature</label>
                        <select id="prod-temp">
                            <option value="hot">Hot</option>
                            <option value="iced">Iced</option>
                        </select>
                    </div>

                    <div class="form-group">
                        <label>Price (₱)</label>
                        <input type="number" step="0.01" name="price" id="prod-price" placeholder="0.00" required>
                    </div>
                    
                    <div class="form-group">
                        <label>Stock Level</label>
                        <input type="number" name="stock" id="prod-stock" placeholder="0" required>
                    </div>
                    
                    <div class="form-group">
                        <label>Product Image</label>
                        <input type="file" name="image" id="prod-img" style="background:#fff; padding:9px;">
                    </div>
                </div>
                
                <div style="margin-top:30px; display:flex; gap:15px;">
                    <button type="submit" class="btn-main" id="submit-btn"><i class="fas fa-plus"></i> Add Product</button>
                    <button type="button" onclick="resetForm()" class="btn-cancel" style="display:none;" id="cancel-btn">Cancel</button>
                </div>
            </form>
        </div>

        <div class="card">
            <div class="card-header">
                <h2>Inventory List</h2>
                <div style="display:flex; gap:10px;">
                    <input type="text" id="searchInput" class="search-input" placeholder="Search by name..." onkeyup="applyFilters()">
                    <select class="filter-select" id="categoryFilter" onchange="applyFilters()">
                        <option value="all">All Categories</option>
                        <?php foreach($categories as $c): ?>
                            <option value="<?php echo strtolower($c); ?>"><?php echo htmlspecialchars($c); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
            <div style="overflow-x: auto;">
                <table id="inventoryTable">
                    <thead>
                        <tr>
                            <th>Image</th>
                            <th>Name</th>
                            <th>Category</th>
                            <th>Base Price</th>
                            <th>Stock</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if($products_result->num_rows > 0): ?>
                            <?php while($p = $products_result->fetch_assoc()): ?>
                            <tr data-category="<?php echo strtolower($p['category']); ?>" data-name="<?php echo strtolower($p['name']); ?>">
                                <td><img src="<?php echo htmlspecialchars($p['image']); ?>" class="product-img" onerror="this.src='logo.png'"></td>
                                <td><strong style="color:var(--secondary); font-size:15px;"><?php echo htmlspecialchars($p['name']); ?></strong></td>
                                <td><span style="background:rgba(212, 163, 115, 0.15); color:#B37D4D; padding:4px 10px; border-radius:4px; font-size:11px; font-weight:600; text-transform:uppercase; letter-spacing:0.5px;"><?php echo htmlspecialchars($p['category']); ?></span></td>
                                <td><span style="font-weight:700; color:var(--primary); font-family:var(--font-heading); font-size:1.1rem;">₱<?php echo number_format($p['price'], 2); ?></span></td>
                                <td>
                                    <?php if($p['stock'] <= 5): ?>
                                        <span style="color:#d32f2f; font-weight:600;"><i class="fas fa-exclamation-triangle"></i> <?php echo $p['stock']; ?> Left</span>
                                    <?php else: ?>
                                        <span style="color:#2e7d32; font-weight:500;"><?php echo $p['stock']; ?> In Stock</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <button class="action-btn" onclick='editProduct(<?php echo json_encode($p); ?>)' title="Edit Product"><i class="fas fa-edit"></i></button>
                                    <a href="delete_product.php?id=<?php echo $p['id']; ?>" class="action-btn" onclick="return confirm('Are you sure you want to move this product to the Recycle Bin?')" title="Move to Trash"><i class="fas fa-trash-alt"></i></a>
                                </td>
                            </tr>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <tr><td colspan="6" style="text-align:center; padding:40px; color:#3a2b24;">No products found in inventory.</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </main>

    <script>
        // --- UPDATED LOGIC: Only show Size/Temp for Beverage Types ---
        function toggleAttributes() {
            const cat = document.getElementById('prod-cat').value.toLowerCase();
            const attrFields = document.querySelectorAll('.attr-field');
            
            // List of keywords that imply a "Drink"
            const beverageKeywords = [
                'coffee', 'tea', 'iced', 'hot', 'frappe', 'smoothie', 'milkshake', 
                'classico', 'freddo', 'espresso', 'latte', 'drink', 'beverage', 'cafe'
            ];
            
            // List of keywords that imply "Food" (Explicit Exclusion)
            const foodKeywords = [
                'pizza', 'pasta', 'food', 'rice', 'meal', 'sandwich', 'burger', 
                'katsu', 'chicken', 'waffle', 'dessert', 'pastry', 'snack'
            ];
            
            // Check matches
            const isBeverage = beverageKeywords.some(keyword => cat.includes(keyword));
            const isFood = foodKeywords.some(keyword => cat.includes(keyword));

            // Show attributes ONLY if it's a beverage AND NOT food
            const showAttrs = isBeverage && !isFood;

            attrFields.forEach(f => {
                f.style.display = showAttrs ? 'block' : 'none'; // Changed flex to block for standard form inputs
            });
        }

        // AUTO-PRICE CALCULATION LOGIC
        function calculateAutoPrice() {
            const size = document.getElementById('prod-size').value;
            const basePrice = parseFloat(document.getElementById('base-regular-price').value) || 0;
            const priceInput = document.getElementById('prod-price');

            if (size === 'large' && basePrice > 0) {
                priceInput.value = (basePrice + 10).toFixed(2);
            } else if (basePrice > 0) {
                priceInput.value = basePrice.toFixed(2);
            }
        }

        function editProduct(data) {
            document.getElementById('form-title').innerText = 'Edit Product Details';
            document.getElementById('form-action').value = 'update';
            document.getElementById('submit-btn').innerHTML = '<i class="fas fa-save"></i> Save Changes';
            document.getElementById('cancel-btn').style.display = 'inline-flex';
            
            document.getElementById('prod-id').value = data.id;
            document.getElementById('prod-name').value = data.name;
            document.getElementById('prod-cat').value = data.category;
            document.getElementById('prod-price').value = data.price;
            document.getElementById('base-regular-price').value = data.price; 
            document.getElementById('prod-stock').value = data.stock;
            
            toggleAttributes(); // Run check immediately when editing
            window.scrollTo({ top: 0, behavior: 'smooth' });
        }

        function resetForm() {
            document.getElementById('productForm').reset();
            document.getElementById('form-title').innerText = 'Add New Product';
            document.getElementById('form-action').value = 'add';
            document.getElementById('submit-btn').innerHTML = '<i class="fas fa-plus"></i> Add Product';
            document.getElementById('cancel-btn').style.display = 'none';
            document.getElementById('base-regular-price').value = 0;
            toggleAttributes(); // Reset view
        }

        function applyFilters() {
            const search = document.getElementById('searchInput').value.toLowerCase();
            const cat = document.getElementById('categoryFilter').value;
            const rows = document.querySelectorAll('#inventoryTable tbody tr');
            
            rows.forEach(row => {
                const nameMatch = row.getAttribute('data-name').includes(search);
                const catMatch = (cat === 'all' || row.getAttribute('data-category') === cat);
                row.style.display = (nameMatch && catMatch) ? '' : 'none';
            });
        }

        document.getElementById('msgBtn').onclick = function(e) {
            e.stopPropagation();
            document.getElementById('msgDrop').classList.toggle('show');
        }
        window.onclick = function() { 
            const drop = document.getElementById('msgDrop');
            if(drop) drop.classList.remove('show'); 
        }
    </script>
</body>
</html>