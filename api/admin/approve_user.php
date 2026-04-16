<?php
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: POST");

include_once '../config/database.php';

$data = json_decode(file_get_contents("php://input"));

if (!empty($data->user_id)) {
    $user_id = (int)$data->user_id;
    $action = $data->action ?? 'approve'; // approve or reject

    if ($action === 'approve') {
        $query = "UPDATE users SET status = 'active' WHERE id = ?";
    } else {
        $query = "DELETE FROM users WHERE id = ? AND status = 'pending'";
    }

    $stmt = $conn->prepare($query);
    $stmt->bind_param("i", $user_id);

    if ($stmt->execute()) {
        $msg = ($action === 'approve') ? "User approved successfully." : "User registration rejected.";
        echo json_encode(["status" => "success", "message" => $msg]);
    } else {
        echo json_encode(["status" => "error", "message" => "Database error."]);
    }
} else {
    echo json_encode(["status" => "error", "message" => "Invalid user ID."]);
}

$conn->close();
?>
