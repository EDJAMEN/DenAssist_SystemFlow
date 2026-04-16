<?php
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: POST");
header("Access-Control-Allow-Headers: Content-Type");

include_once '../config/database.php';

$data = json_decode(file_get_contents("php://input"));

if (!empty($data->id) && !empty($data->name)) {
    $id = (int)$data->id;
    $name = htmlspecialchars(strip_tags($data->name));
    $price = (float)$data->price;
    $duration = (int)$data->duration;
    $description = !empty($data->description) ? htmlspecialchars(strip_tags($data->description)) : "";

    $query = "UPDATE services SET name = ?, price = ?, duration_minutes = ?, description = ? WHERE id = ?";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("sdisi", $name, $price, $duration, $description, $id);

    if ($stmt->execute()) {
        echo json_encode(["status" => "success", "message" => "Service updated successfully."]);
    } else {
        echo json_encode(["status" => "error", "message" => "Unable to update service."]);
    }
} else {
    echo json_encode(["status" => "error", "message" => "Incomplete data."]);
}

$conn->close();
?>
