<?php
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: POST");

include_once '../config/database.php';

$data = json_decode(file_get_contents("php://input"));

if (!empty($data->appointment_id) && !empty($data->status)) {
    $id = (int)$data->appointment_id;
    $status = htmlspecialchars(strip_tags($data->status)); // upcoming, processing, done, cancelled

    $query = "UPDATE appointments SET status = ? WHERE id = ?";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("si", $status, $id);

    if ($stmt->execute()) {
        // --- Reward Points Earning Logic ---
        if ($status === 'completed') {
            // Get patient_id and service name/price
            $get_appt = $conn->prepare("SELECT a.patient_id, s.name as service_name, s.price FROM appointments a JOIN services s ON a.service_id = s.id WHERE a.id = ?");
            $get_appt->bind_param("i", $id);
            $get_appt->execute();
            $appt = $get_appt->get_result()->fetch_assoc();

            if ($appt) {
                $patient_id = $appt['patient_id'];
                // Award 4% of price as completion points (4% + 1% from booking = 5% total)
                $points_to_add = floor($appt['price'] * 0.04); 
                if ($points_to_add < 10) $points_to_add = 10; // Minimum 10 points
                
                $reason = "Visit completed (4% of ₱" . $appt['price'] . ")";

                // Update balance
                $upd_points = $conn->prepare("UPDATE patients_meta SET reward_points = reward_points + ? WHERE user_id = ?");
                $upd_points->bind_param("ii", $points_to_add, $patient_id);
                $upd_points->execute();

                // Log transaction
                $log_points = $conn->prepare("INSERT INTO reward_points_logs (patient_id, points, action, reason) VALUES (?, ?, 'earned', ?)");
                $log_points->bind_param("iis", $patient_id, $points_to_add, $reason);
                $log_points->execute();
            }
        }
        
        echo json_encode(["status" => "success", "message" => "Appointment marked as " . $status]);
    } else {
        echo json_encode(["status" => "error", "message" => "Update failed."]);
    }
} else {
    echo json_encode(["status" => "error", "message" => "Invalid data."]);
}

$conn->close();
?>
