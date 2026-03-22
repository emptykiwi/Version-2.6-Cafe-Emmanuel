<?php
include 'session_check.php';
require_once 'audit_log.php'; // Include the audit log helper

// --- Database Connection ---
include 'db_connect.php';
if ($conn->connect_error) {
  die("Connection failed: " . $conn->connect_error);
}

// --- Audit Log Connection ---
$audit_conn = new mysqli("localhost", "root", "", "login_system");
if ($audit_conn->connect_error) {
    die("Audit connection failed: " . $audit_conn->connect_error);
}

$successMessage = "";
$errorMessage = "";

// --- Handle Stock Update ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_stock'])) {
    $product_id = intval($_POST['product_id']);
    $new_stock = intval($_POST['stock']);

    if ($new_stock >= 0) {
        // Get product name for logging (optional, but good for description)
        $product_name = "ID #{$product_id}";
        $p_stmt = $conn->prepare("SELECT name FROM products WHERE id = ?");
        $p_stmt->bind_param("i", $product_id);
        if ($p_stmt->execute()) {
            $p_res = $p_stmt->get_result();
            if ($p_row = $p_res->fetch_assoc()) {
                $product_name = $p_row['name'];
            }
        }
        $p_stmt->close();

        // Update stock
        $stmt = $conn->prepare("UPDATE products SET stock = ? WHERE id = ?");
        $stmt->bind_param("ii", $new_stock, $product_id);
        
        if ($stmt->execute()) {
            $successMessage = "✅ Stock updated successfully!";
            
            // --- ADDED AUDIT LOG ---
            logAdminAction(
                $audit_conn,
                $_SESSION['user_id'] ?? 0,
                $_SESSION['fullname'] ?? 'Admin',
                'stock_update',
                "Updated stock for product '{$product_name}' to {$new_stock}",
                'products',
                $product_id
            );
            // --- END OF LOG ---

        } else {
            $errorMessage = "❌ Error updating stock.";
        }
        $stmt->close();
    } else {
        $errorMessage = "❌ Stock cannot be a negative number.";
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
<title>Manage Stock</title>
<link rel="stylesheet" href="CSS/admin.css"/>
<link href="https://fonts.googleapis.com/css?family=Poppins" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" />
<style>
    :root {
        --primary-color: #556ee6;
        --main-bg: #f8f8fb;
        --card-bg: #ffffff;
        --text-color: #495057;
        --subtle-text: #74788d;
        --border-color: #eff2f7;
        --green-accent: #34c38f;
        --red-accent: #f46a6a;
        --yellow-accent: #f1b44c;
    }
    .main-content { background-color: var(--main-bg); }
    .page-header { margin-bottom: 25px; }
    .page-header h1 { font-size: 1.5rem; color: var(--text-color); margin: 0; }

    .message { text-align: center; font-weight: bold; margin-bottom: 20px; font-size: 1rem; padding: 12px; border-radius: 6px; }
    .message.success { color: #0f5132; background-color: #d1e7dd; border: 1px solid #badbcc; }
    .message.error { color: #842029; background-color: #f8d7da; border: 1px solid #f5c2c7; }

    .table-card { background: var(--card-bg); padding: 25px; border-radius: 8px; box-shadow: 0 1px 2px rgba(0,0,0,0.05); }
    .stock-table { width: 100%; border-collapse: collapse; }
    .stock-table th, .stock-table td { padding: 12px 15px; text-align: left; border-bottom: 1px solid var(--border-color); vertical-align: middle; }
    .stock-table th { font-weight: 600; font-size: 0.8rem; color: var(--subtle-text); text-transform: uppercase; }
    .stock-table td img { width: 40px; height: 40px; object-fit: cover; border-radius: 6px; }
    
    .status-badge { padding: 5px 10px; border-radius: 20px; font-weight: 500; font-size: 0.8rem; }
    .status-instock { background-color: #eaf7f3; color: var(--green-accent); }
    .status-lowstock { background-color: #fef8ec; color: var(--yellow-accent); }
    .status-nostock { background-color: #fdeeee; color: var(--red-accent); }

    .stock-update-form { display: flex; align-items: center; gap: 10px; }
    .stock-input { width: 80px; padding: 8px; border: 1px solid var(--border-color); border-radius: 6px; text-align: center; }
    .update-btn { padding: 8px 15px; border-radius: 6px; border: none; font-weight: 500; cursor: pointer; background: var(--primary-color); color: #fff; }
    .update-btn:hover { background: #485ec4; }
</style>
</head>
<body>
<div class="admin-container">
  
  <?php include 'admin_sidebar.php'; ?>

  <main class="main-content">
    <header class="page-header">
        <h1>Manage Stock</h1>
    </header>

    <?php if($successMessage): ?><div class="message success"><?php echo $successMessage; ?></div><?php endif; ?>
    <?php if($errorMessage): ?><div class="message error"><?php echo $errorMessage; ?></div><?php endif; ?>

    <div class="table-card">
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
                            <div style="display: flex; align-items: center; gap: 10px;">
                                <img src="<?php echo htmlspecialchars($row['image']); ?>" alt="<?php echo htmlspecialchars($row['name']); ?>">
                                <span><?php echo htmlspecialchars($row['name']); ?></span>
                            </div>
                        </td>
                        <td>₱<?php echo number_format($row['price'], 2); ?></td>
                        <td><strong><?php echo $row['stock']; ?></strong></td>
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
  </main>
</div>
</body>
</html>
<?php 
$conn->close();
$audit_conn->close(); // Close audit connection
?>