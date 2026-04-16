<?php
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: POST");
header("Access-Control-Allow-Headers: Content-Type");

include_once '../config/database.php';

$data = json_decode(file_get_contents("php://input"));

if (!empty($data->email) && !empty($data->phone)) {
    $email = htmlspecialchars(strip_tags($data->email));
    $phone = htmlspecialchars(strip_tags($data->phone));

    $query = "SELECT id FROM users WHERE email = ? AND phone = ? LIMIT 1";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("ss", $email, $phone);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows > 0) {
        $user = $result->fetch_assoc();
        http_response_code(200);
        echo json_encode([
            "status" => "success",
            "message" => "Account verified.",
            "user_id" => $user['id']
        ]);
    } else {
        http_response_code(404);
        echo json_encode(["status" => "error", "message" => "Account details do not match our records."]);
    }
} else {
    http_response_code(400);
    echo json_encode(["status" => "error", "message" => "Provide email and phone number."]);
}

$conn->close();
?>
