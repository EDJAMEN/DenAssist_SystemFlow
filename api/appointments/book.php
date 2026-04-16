<?php
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: POST");
header("Access-Control-Allow-Headers: Content-Type");

include_once '../config/database.php';

$data = json_decode(file_get_contents("php://input"));

if (!empty($data->patient_id) && !empty($data->service_id) && !empty($data->appointment_date) && !empty($data->start_time) && !empty($data->dentist_id)) {
    $patient_id = (int)$data->patient_id;
    $service_id = (int)$data->service_id;
    $dentist_id = (int)$data->dentist_id;
    $appointment_date = htmlspecialchars(strip_tags($data->appointment_date));
    $start_time = htmlspecialchars(strip_tags($data->start_time));
    
    // 1. Get service duration to calculate end_time
    $service_query = "SELECT duration_minutes FROM services WHERE id = ? LIMIT 1";
    $stmt_service = $conn->prepare($service_query);
    $stmt_service->bind_param("i", $service_id);
    $stmt_service->execute();
    $result_service = $stmt_service->get_result();
    
    if ($result_service->num_rows > 0) {
        $service = $result_service->fetch_assoc();
        $duration = $service['duration_minutes'];
        
        // Calculate end_time
        $start_timestamp = strtotime($start_time);
        $end_timestamp = $start_timestamp + ($duration * 60);
        $end_time = date("H:i:s", $end_timestamp);

        // 2. Validate Date (Cannot book in the past)
        $today = date("Y-m-d");
        if ($appointment_date < $today) {
            http_response_code(400);
            echo json_encode(["status" => "error", "message" => "Cannot book appointments in the past."]);
            exit;
        }

        // 3. Validate Clinic Hours (08:00 to 17:00)
        $hours = (int)date("H", $start_timestamp);
        if ($hours < 8 || $hours >= 17) {
            http_response_code(400);
            echo json_encode(["status" => "error", "message" => "Clinic is only open from 08:00 AM to 05:00 PM."]);
            exit;
        }

        // 4. Check for Overlaps/Conflicts (Specific to the selected Dentist)
        $conflict_query = "SELECT id FROM appointments 
                          WHERE appointment_date = ? 
                          AND dentist_id = ?
                          AND status != 'cancelled'
                          AND (
                              (start_time < ? AND end_time > ?) OR
                              (start_time BETWEEN ? AND ?) OR
                              (end_time BETWEEN ? AND ?)
                          ) LIMIT 1";
        
        $stmt_conflict = $conn->prepare($conflict_query);
        $stmt_conflict->bind_param("sissssss", $appointment_date, $dentist_id, $start_time, $end_time, $start_time, $end_time, $start_time, $end_time);
        $stmt_conflict->execute();
        $result_conflict = $stmt_conflict->get_result();

        if ($result_conflict->num_rows > 0) {
            http_response_code(409);
            echo json_encode(["status" => "error", "message" => "This time slot is already booked or overlaps with another appointment."]);
            exit;
        }
        
        // 5. Handle Reward Points Redemption (if applied)
        $reward_id = !empty($data->reward_id) ? (int)$data->reward_id : null;
        if ($reward_id) {
            $reward_stmt = $conn->prepare("SELECT name, points_required FROM rewards WHERE id = ? AND is_active = 1");
            $reward_stmt->bind_param("i", $reward_id);
            $reward_stmt->execute();
            $reward = $reward_stmt->get_result()->fetch_assoc();

            if ($reward) {
                $balance_stmt = $conn->prepare("SELECT reward_points FROM patients_meta WHERE user_id = ?");
                $balance_stmt->bind_param("i", $patient_id);
                $balance_stmt->execute();
                $pat_meta = $balance_stmt->get_result()->fetch_assoc();

                if ($pat_meta && $pat_meta['reward_points'] >= $reward['points_required']) {
                    // Deduct points
                    $new_points = $pat_meta['reward_points'] - $reward['points_required'];
                    $upd_bal = $conn->prepare("UPDATE patients_meta SET reward_points = ? WHERE user_id = ?");
                    $upd_bal->bind_param("ii", $new_points, $patient_id);
                    $upd_bal->execute();

                    // Log redemption
                    $log_red = $conn->prepare("INSERT INTO reward_points_logs (patient_id, points, action, reason) VALUES (?, ?, 'redeemed', ?)");
                    $redeem_reason = "Redeemed for appointment: " . $reward['name'];
                    $log_red->bind_param("iis", $patient_id, $reward['points_required'], $redeem_reason);
                    $log_red->execute();
                }
            }
        }

        // 6. Insert appointment as 'pending'
        $query = "INSERT INTO appointments (patient_id, dentist_id, service_id, appointment_date, start_time, end_time, status) VALUES (?, ?, ?, ?, ?, ?, 'pending')";
        $stmt = $conn->prepare($query);
        $stmt->bind_param("iissss", $patient_id, $dentist_id, $service_id, $appointment_date, $start_time, $end_time);
        
        if ($stmt->execute()) {
            $appointment_id = $stmt->insert_id;
            
            // --- Reward Points Earning Logic (2% of service price for booking) ---
            $get_service = $conn->prepare("SELECT price, name FROM services WHERE id = ?");
            $get_service->bind_param("i", $service_id);
            $get_service->execute();
            $svc = $get_service->get_result()->fetch_assoc();
            
            $points_to_add = 0;
            if ($svc) {
                // Award 1% of price as booking points
                $points_to_add = floor($svc['price'] * 0.01);
                if ($points_to_add < 5) $points_to_add = 5; 
                
                $update_points = "UPDATE patients_meta SET reward_points = reward_points + ? WHERE user_id = ?";
                $stmt_points = $conn->prepare($update_points);
                $stmt_points->bind_param("ii", $points_to_add, $patient_id);
                $stmt_points->execute();
    
                // Log the points earned
                $reason = "Booking bonus (1% of ₱" . $svc['price'] . ")";
                $log_points = $conn->prepare("INSERT INTO reward_points_logs (patient_id, points, action, reason) VALUES (?, ?, 'earned', ?)");
                $log_points->bind_param("iis", $patient_id, $points_to_add, $reason);
                $log_points->execute();
            }

            http_response_code(201);
            echo json_encode(["status" => "success", "message" => "Appointment booked successfully.", "points_earned" => $points_to_add]);
        } else {
            http_response_code(503);
            echo json_encode(["status" => "error", "message" => "Unable to book appointment."]);
        }
    } else {
        http_response_code(400);
        echo json_encode(["status" => "error", "message" => "Invalid service selected."]);
    }
} else {
    http_response_code(400);
    echo json_encode(["status" => "error", "message" => "Provide patient ID, service ID, date, and time."]);
}

$conn->close();
?>
