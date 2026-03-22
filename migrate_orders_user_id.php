<?php
// migrate_orders_users.php
// One-time utility: backfill addproduct.orders.user_id by matching login_system.users.fullname.
// PREVIEW FIRST. Click Run to apply.

session_start();
// Ensure admin role check matches your actual session variable
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') { 
    // Temporary: If you can't login because of this, comment out the line below to run the script, then uncomment it.
    // die('Admins only'); 
}

// --- 1. ENTER YOUR DATABASE CREDENTIALS HERE ---
$host = 'localhost';
$username = 'u763865560_Mancave'; // Your Hostinger Username
$password = 'ManCave2025'; // Check your config.php for the real password
$dbname   = 'u763865560_EmmanuelCafeDB'; // ENTER YOUR ACTUAL DATABASE NAME HERE

// Connect (Assuming all tables are in the SAME database on the live server)
$login = new mysqli($host, $username, $password, $dbname);
$ap    = new mysqli($host, $username, $password, $dbname);

if ($login->connect_error || $ap->connect_error) { 
    die('DB Connection Error: ' . $login->connect_error); 
}

// Find names that map uniquely to exactly one user
$names = [];
$res = $login->query("SELECT fullname, COUNT(*) c FROM users GROUP BY fullname HAVING c=1");
if (!$res) { die("Error reading users table: " . $login->error); }

while ($row = $res->fetch_assoc()) { $names[$row['fullname']] = true; }

// Preview pending rows
$pending = $ap->query("SELECT id, fullname FROM orders WHERE user_id IS NULL");
if (!$pending) { die("Error reading orders table: " . $ap->error); }

$preview = [];
while ($row = $pending->fetch_assoc()) {
  if (isset($names[$row['fullname']])) { $preview[] = $row; }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['run'])) {
  $ap->begin_transaction();
  try {
    // Note: We removed "login_system." prefix since we assume tables are in the same DB now.
    // If tables really are in different databases, change `JOIN users` to `JOIN database_name.users`
    $stmt = $ap->prepare("UPDATE orders o JOIN users u ON u.fullname=o.fullname SET o.user_id=u.id WHERE o.user_id IS NULL");
    $stmt->execute();
    $aff = $stmt->affected_rows;
    $ap->commit();
    echo '<p style="color:green; font-weight:bold;">Migration complete. Rows updated: ' . (int)$aff . '</p>';
    echo '<p><a href="migrate_orders_users.php">Back</a></p>';
    exit;
  } catch (Throwable $e) {
    $ap->rollback();
    echo '<p style="color:red">Migration failed: '.htmlspecialchars($e->getMessage()).'</p>';
  }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Migrate Orders user_id (Preview)</title>
  <style>body{font-family:system-ui,Arial;padding:20px} table{border-collapse:collapse;width:100%;max-width:600px;} th,td{border:1px solid #ddd;padding:8px;text-align:left} th{background:#f7f7f7}</style>
</head>
<body>
  <h2>Migrate Orders.user_id (Preview)</h2>
  <p>This will set <code>orders.user_id</code> by matching <code>users.fullname</code>.</p>
  
  <div style="background:#fff3cd; padding:10px; border:1px solid #ffeeba; margin-bottom:20px;">
    <strong>Debug Info:</strong><br>
    Connected as: <?php echo htmlspecialchars($username); ?><br>
    Database: <?php echo htmlspecialchars($dbname); ?><br>
    Eligible unique names found: <?php echo count($names); ?><br>
    Pending updates: <?php echo count($preview); ?>
  </div>

  <table>
    <thead><tr><th>Order ID</th><th>Fullname</th></tr></thead>
    <tbody>
      <?php foreach (array_slice($preview, 0, 200) as $r): ?>
        <tr><td><?php echo (int)$r['id']; ?></td><td><?php echo htmlspecialchars($r['fullname']); ?></td></tr>
      <?php endforeach; ?>
      <?php if (count($preview) === 0): ?><tr><td colspan="2">No eligible rows found to update.</td></tr><?php endif; ?>
    </tbody>
  </table>
  
  <?php if (count($preview) > 0): ?>
  <form method="POST" onsubmit="return confirm('Run migration now? This will update orders.user_id where names uniquely match.');" style="margin-top:12px">
    <button name="run" value="1" type="submit" style="background:blue; color:white; padding:10px 20px; border:none; cursor:pointer;">Run Migration</button>
  </form>
  <?php endif; ?>
  <p style="margin-top:8px;color:#777">You can delete this file after running (migrate_orders_users.php).</p>
</body>
</html>