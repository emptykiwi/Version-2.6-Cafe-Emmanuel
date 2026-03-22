<?php
// admin_audit.php
include 'session_check.php'; // should ensure admin session
require_once __DIR__ . '/audit.php';

include 'db_connect.php';
if ($conn->connect_error) { die('DB error'); }

$action = $_GET['action'] ?? '';
$from = $_GET['from'] ?? '';
$to   = $_GET['to'] ?? '';

$where = [];
$params = [];
$types = '';

if ($action !== '') { $where[] = 'action = ?'; $params[] = $action; $types .= 's'; }
if ($from !== '') { $where[] = 'created_at >= ?'; $params[] = $from.' 00:00:00'; $types .= 's'; }
if ($to !== '')   { $where[] = 'created_at <= ?'; $params[] = $to.' 23:59:59'; $types .= 's'; }

$sql = "SELECT * FROM audit_logs" .
       (count($where) ? (' WHERE '.implode(' AND ', $where)) : '') .
       " ORDER BY id DESC LIMIT 500";

$stmt = $conn->prepare($sql);
if ($types) { $stmt->bind_param($types, ...$params); }
$stmt->execute();
$res = $stmt->get_result();
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Audit Logs</title>
  <link rel="stylesheet" href="CSS/admin.css"/>
  <link href="https://fonts.googleapis.com/css?family=Poppins" rel="stylesheet">
  <style>
    :root { --main-bg:#f8f8fb; --card:#fff; --border:#eff2f7; --text:#495057; }
    .main-content{ background:var(--main-bg); }
    .card{ background:var(--card); padding:20px; border-radius:8px; box-shadow:0 1px 2px rgba(0,0,0,.05); }
    table{ width:100%; border-collapse: collapse; }
    th,td{ padding:10px; border-bottom:1px solid var(--border); text-align:left; font-size:.92rem; }
    th{ color:#74788d; text-transform:uppercase; font-size:.8rem; }
    .filter{ display:flex; gap:10px; margin-bottom:15px; }
    input,select{ padding:8px 10px; }
  </style>
</head>
<body>
<div class="admin-container">
  <?php include 'admin_sidebar.php'; ?>
  <main class="main-content">
    <div class="card">
      <h2 style="margin:0 0 10px 0">Audit Logs</h2>
      <form method="GET" class="filter">
        <input type="text" name="action" placeholder="action (e.g. user_delete)" value="<?php echo htmlspecialchars($action); ?>">
        <input type="date" name="from" value="<?php echo htmlspecialchars($from); ?>">
        <input type="date" name="to" value="<?php echo htmlspecialchars($to); ?>">
        <button type="submit">Filter</button>
      </form>
      <table>
        <thead><tr><th>ID</th><th>When</th><th>User</th><th>Action</th><th>Table</th><th>Record ID</th><th>Description</th></tr></thead>
        <tbody>
          <?php while($row = $res->fetch_assoc()): ?>
            <tr>
              <td><?php echo (int)$row['id']; ?></td>
              <td><?php echo htmlspecialchars($row['created_at']); ?></td>
              <td><?php echo htmlspecialchars($row['admin_name']); ?></td>
              <td><?php echo htmlspecialchars($row['action']); ?></td>
              <td><?php echo htmlspecialchars($row['table_name'] ?? ''); ?></td>
              <td><?php echo htmlspecialchars($row['record_id'] ?? ''); ?></td>
              <td style="max-width:420px; white-space:nowrap; overflow:hidden; text-overflow:ellipsis;">
                <?php echo htmlspecialchars($row['description']); ?>
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
