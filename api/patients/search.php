<?php
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");

include_once '../config/database.php';

$q = isset($_GET['q']) ? trim($_GET['q']) : '';

if (strlen($q) < 1) {
    echo json_encode(["status" => "success", "data" => []]);
    exit;
}

$like = "%" . $conn->real_escape_string($q) . "%";
$query = "SELECT id, full_name, email, phone FROM users
          WHERE role = 'patient'
            AND (full_name LIKE ? OR phone LIKE ? OR email LIKE ?)
          ORDER BY full_name ASC
          LIMIT 10";

$stmt = $conn->prepare($query);
$stmt->bind_param("sss", $like, $like, $like);
$stmt->execute();
$result = $stmt->get_result();

$patients = [];
while ($row = $result->fetch_assoc()) {
    $patients[] = $row;
}

echo json_encode(["status" => "success", "data" => $patients]);
$conn->close();
?>
