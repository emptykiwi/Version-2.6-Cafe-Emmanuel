<?php
include 'session_check.php';
include 'db_connect.php';
if ($conn->connect_error) {
  die("Connection failed: " . $conn->connect_error);
}
$sql = "SELECT * FROM recently_deleted_products ORDER BY deleted_at DESC";
$result = $conn->query($sql);
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Recently Deleted Products</title>
<link rel="stylesheet" href="CSS/admin.css"/>
<link href="https://fonts.googleapis.com/css?family=Poppins" rel="stylesheet">
<style>
  .card { background:white; padding:30px; border-radius:12px; box-shadow:0 4px 15px rgba(0,0,0,0.08); overflow-x: auto; }
  .card h2 { font-size: 1.8rem; margin-top: 0; margin-bottom: 20px; }
  table { width:100%; border-collapse:collapse; }
  th, td { padding:12px 15px; border-bottom:1px solid #f0f0f0; text-align:left; white-space: nowrap; }
  th { background:#f9fafb; font-weight: 600; }
  td img { width: 50px; height: 50px; object-fit: contain; border-radius: 8px; }
  .action-select { padding: 6px 10px; border-radius: 6px; border: 1px solid #ccc; font-size: 0.9em; cursor: pointer; }
</style>
</head>
<body>
<div class="admin-container">

  <?php include 'admin_sidebar.php'; ?>

  <main class="main-content">
    <div class="card">
      <h2>Recently Deleted Products</h2>
      <table>
        <thead>
          <tr>
            <th>Image</th><th>Name</th><th>Category</th><th>Price</th><th>Stock</th><th>Deleted At</th><th>Action</th>
          </tr>
        </thead>
        <tbody>
          <?php while ($row = $result->fetch_assoc()) : ?>
            <tr>
              <td><img src="<?= htmlspecialchars($row['image']) ?>" alt=""></td>
              <td><?= htmlspecialchars($row['name']) ?></td>
              <td><?= htmlspecialchars($row['category']) ?></td>
              <td>₱<?= number_format($row['price'], 2) ?></td>
              <td><?= $row['stock'] ?></td>
              <td><?= $row['deleted_at'] ?></td>
              <td>
                <form method="POST" action="product_actions.php">
                  <input type="hidden" name="id" value="<?= $row['id'] ?>">
                  <select name="action" class="action-select" onchange="this.form.submit()" required>
                    <option value="">-- Select --</option>
                    <option value="restore">Restore</option>
                    <option value="permanent_delete">Permanent Delete</option>
                  </select>
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
<?php $conn->close(); ?>