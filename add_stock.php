<?php
include 'session_check.php';
// --- Database Connection ---
include 'db_connect.php';
require_once 'audit_log.php';

if ($conn->connect_error) {
  die("Connection failed: " . $conn->connect_error);
}

$successMessage = "";
$errorMessage = "";

// --- Handle Stock Update ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_stock'])) {
    $product_id = intval($_POST['product_id']);
    $new_stock = intval($_POST['stock']);

    if ($new_stock >= 0) {
        $stmt = $conn->prepare("UPDATE products SET stock = ? WHERE id = ?");
        $stmt->bind_param("ii", $new_stock, $product_id);
        if ($stmt->execute()) {
            $successMessage = "Stock updated successfully!";
            
            // Get product name for logging
            $product_name = "ID #$product_id";
            $p_stmt = $conn->prepare("SELECT name FROM products WHERE id = ?");
            $p_stmt->bind_param("i", $product_id);
            if ($p_stmt->execute()) {
                $p_res = $p_stmt->get_result();
                if ($p_row = $p_res->fetch_assoc()) {
                    $product_name = $p_row['name'];
                }
            }
            $p_stmt->close();
            
            logAdminAction($conn, $_SESSION['user_id'] ?? 0, $_SESSION['fullname'] ?? 'Admin', 'stock_update', "Updated stock for '$product_name' to $new_stock", 'products', $product_id);
        } else {
            $errorMessage = "Error updating stock.";
        }
        $stmt->close();
    } else {
        $errorMessage = "Stock cannot be a negative number.";
    }
}

// --- Fetch All Products ---
$products_result = $conn->query("SELECT * FROM products ORDER BY name ASC");
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<link rel="icon" type="image/png" href="logo.png">
<title>Manage Stock - Cafe Emmanuel</title>

<link rel="stylesheet" href="CSS/admin.css"/>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" />
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Akaya+Telivigala&family=Archivo+Black&family=Archivo+Narrow:wght@400;700&family=Birthstone+Bounce:wght@500&family=Inknut+Antiqua:wght@600&family=Playfair+Display:wght@400;600;700&family=Lato:wght@300;400;700&display=swap" rel="stylesheet">

<style>
    :root {
        --primary-color: #B95A4B;
        --primary-dark: #9C4538;
        --secondary-color: #3C2A21;
        --text-color: #333;
        --heading-color: #1F1F1F;
        --bg-light: #fcfbf8;
        --card-bg: #FFFFFF;
        --border-color: #EAEAEA;
        --green-accent: #2e7d32;
        --red-accent: #c62828;
        --yellow-accent: #f9a825;
        
        /* Fonts */
        --font-heading: 'Playfair Display', serif;
        --font-body: 'Lato', sans-serif;
    }

    body {
        font-family: var(--font-body);
        background-color: var(--bg-light);
        color: var(--text-color);
        margin: 0;
        overflow: hidden;
    }

    .admin-container {
        display: flex;
        height: 100vh;
    }

    .main-content {
        flex: 1;
        background-color: var(--bg-light);
        padding: 30px;
        overflow-y: auto;
        height: 100vh;
    }

    .main-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 30px;
    }

    .main-header h1 {
        font-family: var(--font-heading);
        font-size: 2rem;
        font-weight: 700;
        color: var(--secondary-color);
        margin: 0;
    }

    .header-icons {
        display: flex;
        align-items: center;
        gap: 1.5rem;
    }
    .header-icons i {
        font-size: 1.2rem;
        color: var(--primary-color);
        cursor: pointer;
        transition: color 0.3s;
    }
    .header-icons i:hover { color: var(--primary-dark); }
    .header-icons img {
        width: 45px;
        height: 45px;
        border-radius: 50%;
        border: 2px solid var(--primary-color);
        object-fit: cover;
    }

    /* --- Alerts --- */
    .alert {
        padding: 15px;
        border-radius: 12px;
        margin-bottom: 25px;
        font-weight: 500;
        display: flex;
        align-items: center;
        gap: 10px;
    }
    .alert-success { background-color: #e8f5e9; color: var(--green-accent); border: 1px solid #c8e6c9; }
    .alert-error { background-color: #ffebee; color: var(--red-accent); border: 1px solid #ffcdd2; }

    /* --- Table Card --- */
    .table-card {
        background: var(--card-bg);
        padding: 25px;
        border-radius: 16px;
        box-shadow: 0 10px 30px rgba(0,0,0,0.05);
        border: 1px solid var(--border-color);
    }

    .stock-table {
        width: 100%;
        border-collapse: collapse;
    }

    .stock-table th {
        text-align: left;
        padding: 15px;
        font-weight: 700;
        font-size: 0.85rem;
        color: #666;
        text-transform: uppercase;
        letter-spacing: 0.5px;
        border-bottom: 2px solid var(--border-color);
        background-color: #fafafa;
    }

    .stock-table td {
        padding: 15px;
        border-bottom: 1px solid var(--border-color);
        font-size: 0.95rem;
        color: var(--text-color);
        vertical-align: middle;
    }

    .stock-table tr:hover {
        background-color: #fcfcfc;
    }

    /* --- Product Info --- */
    .product-cell {
        display: flex;
        align-items: center;
        gap: 15px;
    }
    .product-img {
        width: 50px;
        height: 50px;
        object-fit: cover;
        border-radius: 8px;
        background-color: #f0f0f0;
        border: 1px solid #eee;
    }
    .product-name {
        font-weight: 600;
        color: var(--secondary-color);
        font-size: 1rem;
    }

    /* --- Status Badges --- */
    .status-badge { 
        padding: 6px 14px; 
        border-radius: 30px; 
        font-size: 0.75rem; 
        font-weight: 700; 
        text-transform: uppercase; 
        letter-spacing: 0.5px; 
        display: inline-block;
    }
    .status-instock { background-color: #e8f5e9; color: var(--green-accent); border: 1px solid #c8e6c9; }
    .status-lowstock { background-color: #fff3e0; color: var(--yellow-accent); border: 1px solid #ffe0b2; }
    .status-nostock { background-color: #ffebee; color: var(--red-accent); border: 1px solid #ffcdd2; }

    /* --- Update Form --- */
    .stock-update-form {
        display: flex;
        align-items: center;
        gap: 10px;
    }
    
    .stock-input {
        width: 80px;
        padding: 8px 12px;
        border: 1px solid var(--border-color);
        border-radius: 8px;
        text-align: center;
        font-family: var(--font-body);
        font-weight: 600;
        outline: none;
        transition: border-color 0.3s;
    }
    .stock-input:focus {
        border-color: var(--primary-color);
    }

    .update-btn {
        padding: 8px 16px;
        border-radius: 8px;
        border: none;
        font-weight: 600;
        cursor: pointer;
        background-color: var(--primary-color);
        color: #fff;
        font-size: 0.85rem;
        transition: all 0.2s;
    }
    .update-btn:hover {
        background-color: var(--primary-dark);
    }

</style>
</head>
<body>
<div class="admin-container">
  
  <?php include 'admin_sidebar.php'; ?>

  <main class="main-content">
    <header class="main-header">
        <h1>Manage Stock</h1>
        <div class="header-icons">
            <i class="fas fa-envelope"></i>
            <i class="fas fa-bell"></i>
            <img src="logo.png" alt="Admin Profile">
        </div>
    </header>

    <?php if($successMessage): ?>
        <div class="alert alert-success">
            <i class="fas fa-check-circle"></i> <?php echo $successMessage; ?>
        </div>
    <?php endif; ?>
    
    <?php if($errorMessage): ?>
        <div class="alert alert-error">
            <i class="fas fa-exclamation-circle"></i> <?php echo $errorMessage; ?>
        </div>
    <?php endif; ?>

    <div class="table-card">
        <div style="overflow-x: auto;">
            <table class="stock-table">
                <thead>
                    <tr>
                        <th>Product</th>
                        <th>Price</th>
                        <th>Current Stock</th>
                        <th>Status</th>
                        <th>Update Stock</th>
                    </tr>
                </thead>
                <tbody>
                    <?php while ($row = $products_result->fetch_assoc()): ?>
                        <tr>
                            <td>
                                <div class="product-cell">
                                    <img src="<?php echo htmlspecialchars($row['image']); ?>" alt="<?php echo htmlspecialchars($row['name']); ?>" class="product-img">
                                    <span class="product-name"><?php echo htmlspecialchars($row['name']); ?></span>
                                </div>
                            </td>
                            <td style="font-weight: 600; color: #555;">₱<?php echo number_format($row['price'], 2); ?></td>
                            <td><strong style="font-size: 1.1rem;"><?php echo $row['stock']; ?></strong></td>
                            <td>
                                <?php
                                    $stock = $row['stock'];
                                    if ($stock <= 0) {
                                        echo '<span class="status-badge status-nostock">No Stock</span>';
                                    } elseif ($stock < 10) {
                                        echo '<span class="status-badge status-lowstock">Low Stock</span>';
                                    } else {
                                        echo '<span class="status-badge status-instock">In Stock</span>';
                                    }
                                ?>
                            </td>
                            <td>
                                <form method="POST" action="add_stock.php" class="stock-update-form">
                                    <input type="hidden" name="product_id" value="<?php echo $row['id']; ?>">
                                    <input type="number" name="stock" class="stock-input" value="<?php echo $row['stock']; ?>" min="0">
                                    <button type="submit" name="update_stock" class="update-btn">Update</button>
                                </form>
                            </td>
                        </tr>
                    <?php endwhile; ?>
                </tbody>
            </table>
        </div>
    </div>
  </main>
</div>
</body>
</html>
<?php $conn->close(); ?>