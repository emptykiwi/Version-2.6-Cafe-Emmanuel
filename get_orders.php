<?php
include 'db_connect.php';

if ($conn->connect_error) {
    http_response_code(500);
    echo json_encode(["error" => "Connection failed"]);
    exit;
}

$sql = "
    SELECT 
        c.*, 
        u.fullname AS customer_name,
        u.email AS customer_email
    FROM cart c
    LEFT JOIN users u ON c.user_id = u.id
    ORDER BY c.created_at DESC
";
$result = $conn->query($sql);

$orders = [];

while ($row = $result->fetch_assoc()) {
    $orders[] = $row;
}

echo json_encode($orders);
$conn->close();
?>