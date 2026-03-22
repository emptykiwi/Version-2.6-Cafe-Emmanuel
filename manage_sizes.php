<?php
include 'session_check.php'; // Ensure only admins access this
include 'db_connect.php';

$message = "";

// --- HANDLE FORM SUBMISSION (ADD SIZE) ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_size'])) {
    $product_id = intval($_POST['product_id']);
    $size_name = trim($_POST['size_name']);
    $price = floatval($_POST['price']);

    if (!empty($size_name) && $price > 0) {
        // Prepare Statement to prevent SQL Injection
        $stmt = $conn->prepare("INSERT INTO product_sizes (product_id, size_name, price) VALUES (?, ?, ?)");
        $stmt->bind_param("isd", $product_id, $size_name, $price);
        
        if ($stmt->execute()) {
            $message = "<div class='alert success'>✅ Size added successfully!</div>";
        } else {
            // Check if table exists error
            if ($conn->errno == 1146) {
                 $message = "<div class='alert error'>❌ Error: Table 'product_sizes' does not exist. Please run the SQL command below.</div>";
            } else {
                 $message = "<div class='alert error'>❌ Error: " . $conn->error . "</div>";
            }
        }
        $stmt->close();
    } else {
        $message = "<div class='alert error'>❌ Please enter a valid size name and price.</div>";
    }
}

// --- HANDLE DELETION ---
if (isset($_GET['delete'])) {
    $size_id = intval($_GET['delete']);
    $conn->query("DELETE FROM product_sizes WHERE id = $size_id");
    header("Location: manage_sizes.php");
    exit;
}

// --- FETCH PRODUCTS & SIZES ---
$products = $conn->query("SELECT * FROM products ORDER BY name ASC");
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Product Sizes</title>
    <link rel="stylesheet" href="CSS/admin.css"/>
    <style>
        body { font-family: 'Poppins', sans-serif; background-color: #f8f8fb; padding: 20px; }
        .container { max-width: 1000px; margin: 0 auto; background: white; padding: 30px; border-radius: 10px; box-shadow: 0 5px 15px rgba(0,0,0,0.05); }
        h1 { margin-bottom: 20px; color: #333; }
        
        .alert { padding: 15px; margin-bottom: 20px; border-radius: 5px; }
        .success { background: #d4edda; color: #155724; border: 1px solid #c3e6cb; }
        .error { background: #f8d7da; color: #721c24; border: 1px solid #f5c2c7; }

        .form-box { background: #f1f1f1; padding: 20px; border-radius: 8px; margin-bottom: 30px; }
        .form-group { display: flex; gap: 15px; margin-bottom: 10px; align-items: flex-end; }
        .form-group label { font-size: 0.9rem; font-weight: 600; display: block; margin-bottom: 5px; }
        input, select { padding: 10px; border: 1px solid #ddd; border-radius: 5px; width: 100%; }
        button { padding: 10px 20px; background: #556ee6; color: white; border: none; border-radius: 5px; cursor: pointer; font-weight: bold; }
        button:hover { background: #485ec4; }

        table { width: 100%; border-collapse: collapse; margin-top: 20px; }
        th, td { padding: 12px; border-bottom: 1px solid #eee; text-align: left; }
        th { background: #f8f9fa; color: #555; }
        .delete-btn { color: red; text-decoration: none; font-weight: bold; font-size: 0.9rem; }
        .delete-btn:hover { text-decoration: underline; }
        
        .product-section { margin-bottom: 30px; border: 1px solid #eee; border-radius: 8px; overflow: hidden; }
        .product-header { background: #f9f9f9; padding: 15px; font-weight: bold; display: flex; justify-content: space-between; align-items: center; }
        .product-header img { width: 40px; height: 40px; object-fit: cover; border-radius: 4px; margin-right: 10px; vertical-align: middle; }
    </style>
</head>
<body>

<div class="container">
    <a href="Dashboard.php" style="display:inline-block; margin-bottom: 20px; color: #555; text-decoration: none;">&larr; Back to Dashboard</a>
    <h1>Manage Sizes & Prices</h1>
    
    <?php echo $message; ?>

    <!-- ADD SIZE FORM -->
    <div class="form-box">
        <h3>Add New Size Variant</h3>
        <form method="POST">
            <div class="form-group">
                <div style="flex: 2;">
                    <label>Select Product</label>
                    <select name="product_id" required>
                        <option value="">-- Choose Product --</option>
                        <?php 
                        // Reset pointer
                        $products->data_seek(0);
                        while($p = $products->fetch_assoc()): 
                        ?>
                            <option value="<?php echo $p['id']; ?>"><?php echo htmlspecialchars($p['name']); ?></option>
                        <?php endwhile; ?>
                    </select>
                </div>
                <div style="flex: 1;">
                    <label>Size Name (e.g., Large)</label>
                    <input type="text" name="size_name" placeholder="Large" required>
                </div>
                <div style="flex: 1;">
                    <label>Price (₱)</label>
                    <input type="number" name="price" step="0.01" placeholder="0.00" required>
                </div>
                <button type="submit" name="add_size">Add Size</button>
            </div>
        </form>
    </div>

    <!-- LIST OF EXISTING SIZES -->
    <h3>Existing Variants</h3>
    <?php
    $products->data_seek(0); // Reset pointer again to list
    while($p = $products->fetch_assoc()):
        $pid = $p['id'];
        $sizes = $conn->query("SELECT * FROM product_sizes WHERE product_id = $pid ORDER BY price ASC");
        
        if($sizes && $sizes->num_rows > 0):
    ?>
        <div class="product-section">
            <div class="product-header">
                <div>
                    <img src="<?php echo htmlspecialchars($p['image']); ?>" alt="img">
                    <?php echo htmlspecialchars($p['name']); ?>
                </div>
            </div>
            <table>
                <thead>
                    <tr>
                        <th>Size Name</th>
                        <th>Price</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php while($s = $sizes->fetch_assoc()): ?>
                    <tr>
                        <td><?php echo htmlspecialchars($s['size_name']); ?></td>
                        <td>₱<?php echo number_format($s['price'], 2); ?></td>
                        <td><a href="?delete=<?php echo $s['id']; ?>" class="delete-btn" onclick="return confirm('Delete this size?');">Remove</a></td>
                    </tr>
                    <?php endwhile; ?>
                </tbody>
            </table>
        </div>
    <?php 
        endif; 
    endwhile; 
    ?>

</div>

</body>
</html>