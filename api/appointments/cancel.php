<?php
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: POST");
header("Access-Control-Allow-Headers: Content-Type");

include_once '../config/database.php';

$data = json_decode(file_get_contents("php://input"));

if (!empty($data->appointment_id)) {
    $appointment_id = (int) $data->appointment_id;

    // Delete the appointment
    $query = "DELETE FROM appointments WHERE id = ?";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("i", $appointment_id);

    if ($stmt->execute()) {
        http_response_code(200);
        echo json_encode(["status" => "success", "message" => "Appointment cancelled successfully."]);
    } else {
        http_response_code(503);
        echo json_encode(["status" => "error", "message" => "Unable to cancel appointment."]);
    }
} else {
    http_response_code(400);
    echo json_encode(["status" => "error", "message" => "Appointment ID is required."]);
}

$conn->close();
?>