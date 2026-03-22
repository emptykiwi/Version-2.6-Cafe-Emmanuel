<?php
include 'db_connect.php';
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id = intval($_POST['id']);
    $action = $_POST['action'];

    $stmt = $conn->prepare("SELECT * FROM recently_deleted_users WHERE id = ?");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $res = $stmt->get_result();
    $user = $res->fetch_assoc();
    $stmt->close();

    if ($user) {
        if ($action === "restore") {
            // Restore back to 'users' table using dynamic column mapping
            
            // Get columns from 'users' table
            $columns = [];
            $res = $conn->query("SHOW COLUMNS FROM users");
            while ($c = $res->fetch_assoc()) {
                if ($c['Field'] !== 'deleted_at') { // Skip deleted_at
                    $columns[] = $c['Field'];
                }
            }
            
            // Build query dynamically
            $col_names = [];
            $placeholders = [];
            $values = [];
            $types = "";

            foreach ($columns as $col) {
                if (array_key_exists($col, $user)) {
                    $col_names[] = "`" . $col . "`";
                    $placeholders[] = "?";
                    $values[] = $user[$col];
                    $types .= "s"; 
                }
            }
            
            $sql_insert = "INSERT INTO users (" . implode(", ", $col_names) . ") VALUES (" . implode(", ", $placeholders) . ")";
            $stmt = $conn->prepare($sql_insert);
            
            if ($stmt) {
                // Bind parameters dynamically
                $stmt->bind_param($types, ...$values);
                $stmt->execute();
                $stmt->close();

                // Delete from recently_deleted_users
                $stmt = $conn->prepare("DELETE FROM recently_deleted_users WHERE id = ?");
                $stmt->bind_param("i", $id);
                $stmt->execute();
                $stmt->close();
            } else {
                 die("Error preparing restore: " . $conn->error);
            }

        } elseif ($action === "permanent_delete") { 
            $stmt = $conn->prepare("DELETE FROM recently_deleted_users WHERE id = ?");
            $stmt->bind_param("i", $id);
            $stmt->execute();
            $stmt->close();
        }
    }
}

$conn->close();
header("Location: recently_deleted.php");
exit;
?>