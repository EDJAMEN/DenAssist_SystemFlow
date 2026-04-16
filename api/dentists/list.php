<?php
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");

include_once '../config/database.php';

// Fetch all users with role 'dentist' or 'admin' who are also 'active'
$query = "SELECT id, full_name, email, role, professional_id, position FROM users WHERE role IN ('dentist', 'admin') AND status = 'active' ORDER BY full_name ASC";
$result = $conn->query($query);

if ($result) {
    $dentists = [];
    while ($row = $result->fetch_assoc()) {
        $dentists[] = $row;
    }
    echo json_encode(["status" => "success", "data" => $dentists]);
} else {
    echo json_encode(["status" => "error", "message" => $conn->error]);
}

$conn->close();
?>
