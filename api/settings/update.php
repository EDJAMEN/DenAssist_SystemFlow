<?php
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: POST");

include_once '../config/database.php';

$data = json_decode(file_get_contents("php://input"));

if (!empty($data)) {
    $success = true;
    
    foreach ($data as $key => $value) {
        $clean_key = htmlspecialchars(strip_tags($key));
        $clean_value = htmlspecialchars(strip_tags($value));
        
        $query = "UPDATE schedule_settings SET setting_value = ? WHERE setting_key = ?";
        $stmt = $conn->prepare($query);
        $stmt->bind_param("ss", $clean_value, $clean_key);
        if (!$stmt->execute()) {
            $success = false;
        }
    }

    if ($success) {
        echo json_encode(["status" => "success", "message" => "Settings updated successfully."]);
    } else {
        echo json_encode(["status" => "error", "message" => "Some settings failed to update."]);
    }
} else {
    echo json_encode(["status" => "error", "message" => "No data provided."]);
}

$conn->close();
?>
