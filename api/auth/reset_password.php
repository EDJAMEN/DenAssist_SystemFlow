<?php
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: POST");
header("Access-Control-Allow-Headers: Content-Type");

include_once '../config/database.php';

$data = json_decode(file_get_contents("php://input"));

if (!empty($data->user_id) && !empty($data->new_password)) {
    $user_id = intval($data->user_id);
    $new_password = htmlspecialchars(strip_tags($data->new_password));

    // Update password_hash (Note: system currently uses plain text check as seen in login.php)
    $query = "UPDATE users SET password_hash = ? WHERE id = ?";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("si", $new_password, $user_id);

    if ($stmt->execute()) {
        http_response_code(200);
        echo json_encode(["status" => "success", "message" => "Password updated successfully. You can now login."]);
    } else {
        http_response_code(500);
        echo json_encode(["status" => "error", "message" => "Failed to update password."]);
    }
} else {
    http_response_code(400);
    echo json_encode(["status" => "error", "message" => "Invalid request details."]);
}

$conn->close();
?>
