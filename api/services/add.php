<?php
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: POST");
header("Access-Control-Allow-Headers: Content-Type");

include_once '../config/database.php';

$data = json_decode(file_get_contents("php://input"));

if (!empty($data->name) && !empty($data->price) && !empty($data->duration)) {
    $name = htmlspecialchars(strip_tags($data->name));
    $price = (float)$data->price;
    $duration = (int)$data->duration;
    $description = !empty($data->description) ? htmlspecialchars(strip_tags($data->description)) : "";

    $query = "INSERT INTO services (name, price, duration_minutes, description) VALUES (?, ?, ?, ?)";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("sdis", $name, $price, $duration, $description);

    if ($stmt->execute()) {
        http_response_code(201);
        echo json_encode(["status" => "success", "message" => "Service added successfully."]);
    } else {
        http_response_code(503);
        echo json_encode(["status" => "error", "message" => "Unable to add service."]);
    }
} else {
    http_response_code(400);
    echo json_encode(["status" => "error", "message" => "Incomplete data."]);
}

$conn->close();
?>
