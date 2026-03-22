<?php
include 'db_connect.php';
$data = ["labels"=>[], "values"=>[]];

$res = $conn->query("
  SELECT DATE(date_created) as date, SUM(amount) as daily_total
  FROM revenue
  GROUP BY DATE(date_created)
  ORDER BY date ASC
");
while($row = $res->fetch_assoc()){
  $data["labels"][] = $row["date"];
  $data["values"][] = $row["daily_total"];
}
header('Content-Type: application/json');
echo json_encode($data);
$conn->close();
?>
