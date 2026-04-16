<?php
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");

include_once '../config/database.php';

$query = "SELECT * FROM rewards WHERE is_active = 1 ORDER BY points_required ASC";
$result = $conn->query($query);

if ($result) {
    $rewards = [];
    while ($row = $result->fetch_assoc()) {
        $rewards[] = $row;
    }
    echo json_encode(["status" => "success", "data" => $rewards]);
} else {
    echo json_encode(["status" => "error", "message" => "Failed to load rewards."]);
}

$conn->close();
?>
