<?php
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");

include_once '../config/database.php';

// Only admins should see this (in a real app we'd check session/token)
$query = "SELECT id, full_name, email, phone, role, created_at FROM users WHERE status = 'pending' ORDER BY created_at DESC";
$result = $conn->query($query);

if ($result) {
    $pending = [];
    while ($row = $result->fetch_assoc()) {
        $pending[] = $row;
    }
    echo json_encode(["status" => "success", "data" => $pending]);
} else {
    echo json_encode(["status" => "error", "message" => $conn->error]);
}

$conn->close();
?>
