<?php
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: POST");
header("Access-Control-Allow-Headers: Content-Type");

include_once '../config/database.php';

$data = json_decode(file_get_contents("php://input"));

if (!empty($data->user_id) && !empty($data->full_name) && !empty($data->email)) {
    $user_id = (int)$data->user_id;
    $full_name = htmlspecialchars(strip_tags($data->full_name));
    $email = htmlspecialchars(strip_tags($data->email));
    $phone = htmlspecialchars(strip_tags($data->phone));
    
    // Optional Professional Fields (for Dentists/Admins)
    $professional_id = !empty($data->professional_id) ? htmlspecialchars(strip_tags($data->professional_id)) : null;
    $position = !empty($data->position) ? htmlspecialchars(strip_tags($data->position)) : null;

    // Check if new email is already taken by someone else
    $check_query = "SELECT id FROM users WHERE email = ? AND id != ? LIMIT 1";
    $stmt_check = $conn->prepare($check_query);
    $stmt_check->bind_param("si", $email, $user_id);
    $stmt_check->execute();
    $stmt_check->store_result();

    if ($stmt_check->num_rows > 0) {
        echo json_encode(["status" => "error", "message" => "Email is already in use by another account."]);
    } else {
        $query = "UPDATE users SET full_name = ?, email = ?, phone = ?, professional_id = ?, position = ? WHERE id = ?";
        $stmt = $conn->prepare($query);
        $stmt->bind_param("sssssi", $full_name, $email, $phone, $professional_id, $position, $user_id);

        if ($stmt->execute()) {
            // Update patient meta if the fields are present in the request
            if (isset($data->dob) || isset($data->emergency_contact_name) || isset($data->allergies)) {
                $dob = isset($data->dob) ? htmlspecialchars(strip_tags($data->dob)) : null;
                $e_name = isset($data->emergency_contact_name) ? htmlspecialchars(strip_tags($data->emergency_contact_name)) : null;
                $e_phone = isset($data->emergency_contact_phone) ? htmlspecialchars(strip_tags($data->emergency_contact_phone)) : null;
                $allergies = isset($data->allergies) ? htmlspecialchars(strip_tags($data->allergies)) : null;
                $medications = isset($data->medications) ? htmlspecialchars(strip_tags($data->medications)) : null;

                // Check if row exists in patients_meta
                $check_meta = $conn->prepare("SELECT user_id FROM patients_meta WHERE user_id = ?");
                $check_meta->bind_param("i", $user_id);
                $check_meta->execute();
                if ($check_meta->get_result()->num_rows > 0) {
                    $upd_meta = $conn->prepare("UPDATE patients_meta SET dob = ?, emergency_contact_name = ?, emergency_contact_phone = ?, allergies = ?, medications = ? WHERE user_id = ?");
                    $upd_meta->bind_param("sssssi", $dob, $e_name, $e_phone, $allergies, $medications, $user_id);
                    $upd_meta->execute();
                } else {
                    // Only insert if it's actually a patient (we can verify by checking if it's NOT a staff member or just assume based on caller)
                    $ins_meta = $conn->prepare("INSERT INTO patients_meta (user_id, dob, emergency_contact_name, emergency_contact_phone, allergies, medications) VALUES (?, ?, ?, ?, ?, ?)");
                    $ins_meta->bind_param("isssss", $user_id, $dob, $e_name, $e_phone, $allergies, $medications);
                    $ins_meta->execute();
                }
            }

            // Fetch updated user data to return
            $get_query = "SELECT u.id, u.full_name as name, u.email, u.phone, u.role as role, u.status, u.professional_id, u.position,
                               pm.dob, pm.emergency_contact_name, pm.emergency_contact_phone, pm.allergies, pm.medications, pm.reward_points
                        FROM users u
                        LEFT JOIN patients_meta pm ON u.id = pm.user_id
                        WHERE u.id = ?";
            $get_stmt = $conn->prepare($get_query);
            $get_stmt->bind_param("i", $user_id);
            $get_stmt->execute();
            $user_data = $get_stmt->get_result()->fetch_assoc();

            echo json_encode([
                "status" => "success", 
                "message" => "Profile updated successfully.",
                "user" => $user_data
            ]);
        } else {
            echo json_encode(["status" => "error", "message" => "Database error: " . $conn->error]);
        }
    }
} else {
    echo json_encode(["status" => "error", "message" => "Required fields missing."]);
}

$conn->close();
?>
