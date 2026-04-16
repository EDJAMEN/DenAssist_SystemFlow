<?php
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");

include_once '../config/database.php';

$query = "SELECT id, full_name, email, phone, created_at FROM users WHERE role = 'patient' ORDER BY full_name ASC";
$result = $conn->query($query);

if ($result) {
    $patients = [];
    while ($row = $result->fetch_assoc()) {
        $patients[] = $row;
    }
    echo json_encode(["status" => "success", "data" => $patients]);
} else {
    echo json_encode(["status" => "error", "message" => $conn->error]);
}

$conn->close();
?>
