<?php
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: POST");
header("Access-Control-Allow-Headers: Content-Type");

include_once '../config/database.php';

$data = json_decode(file_get_contents("php://input"));

if (!empty($data->user_id)) {
    $user_id = (int)$data->user_id;
    $action = $data->action ?? 'delete'; // delete or suspend

    // Security Check: Prevent deleting the main admin with ID 1 (Dr. Reyes)
    if ($user_id === 1) {
        http_response_code(403);
        echo json_encode(["status" => "error", "message" => "Master Admin account cannot be modified or removed."]);
        exit;
    }

    if ($action === 'delete') {
        $query = "DELETE FROM users WHERE id = ?";
    } else {
        $status = ($action === 'suspend') ? 'suspended' : 'active';
        $query = "UPDATE users SET status = ? WHERE id = ?";
    }

    $stmt = $conn->prepare($query);
    
    if ($action === 'delete') {
        $stmt->bind_param("i", $user_id);
    } else {
        $stmt->bind_param("si", $status, $user_id);
    }

    if ($stmt->execute()) {
        $msg = ($action === 'delete') ? "Professional removed from system." : "User status updated to $status.";
        echo json_encode(["status" => "success", "message" => $msg]);
    } else {
        http_response_code(500);
        echo json_encode(["status" => "error", "message" => "Database error: " . $conn->error]);
    }
} else {
    http_response_code(400);
    echo json_encode(["status" => "error", "message" => "Invalid user ID."]);
}

$conn->close();
?>
