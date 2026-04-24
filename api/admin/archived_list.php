<?php
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");

include_once '../config/database.php';

$query = "SELECT id, full_name, role, deleted_at FROM users WHERE deleted_at IS NOT NULL ORDER BY deleted_at DESC";
$result = $conn->query($query);

if ($result) {
    $archived = [];
    while ($row = $result->fetch_assoc()) {
        $archived[] = $row;
    }
    echo json_encode(["status" => "success", "data" => $archived]);
} else {
    echo json_encode(["status" => "error", "message" => $conn->error]);
}

$conn->close();
?>