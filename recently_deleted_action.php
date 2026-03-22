<?php
include 'db_connect.php';
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id = intval($_POST['id']);
    $action = $_POST['action'];

    // kunin muna yung record
    $res = $conn->query("SELECT * FROM recently_deleted WHERE id = $id");
    if ($res && $res->num_rows > 0) {
        $row = $res->fetch_assoc();

        if ($action === 'restore') {
            // ibalik sa orders
            $stmt = $conn->prepare("
                INSERT INTO orders (fullname, contact, address, payment_method, total, created_at, status)
                VALUES (?, ?, ?, ?, ?, ?, ?)
            ");
            $stmt->bind_param(
                "sssssss",
                $row['fullname'],
                $row['contact'],
                $row['address'],
                $row['payment_method'],
                $row['total'],
                $row['created_at'],
                $row['status']
            );
            $stmt->execute();
            $stmt->close();

            // burahin sa recently_deleted
            $conn->query("DELETE FROM recently_deleted WHERE id = $id");
        } elseif ($action === 'permanent_delete') {
            // tuluyang burahin
            $conn->query("DELETE FROM recently_deleted WHERE id = $id");
        }
    }
}

header("Location: recently_deleted.php");
exit;
