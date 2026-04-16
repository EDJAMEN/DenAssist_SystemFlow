<?php
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");

include_once '../config/database.php';

// Get today's date (or allow a date param)
$date = isset($_GET['date']) ? $_GET['date'] : date('Y-m-d');

// Get all active dentists/admins with their appointment count for the given date
$query = "SELECT u.id, u.full_name,
                 (SELECT COUNT(*) FROM appointments a
                  WHERE a.dentist_id = u.id
                    AND a.appointment_date = ?
                    AND a.status NOT IN ('cancelled')
                 ) AS appointment_count,
                 (SELECT MAX(a2.end_time) FROM appointments a2
                  WHERE a2.dentist_id = u.id
                    AND a2.appointment_date = ?
                    AND a2.status NOT IN ('cancelled')
                 ) AS last_end_time
          FROM users u
          WHERE u.role IN ('admin', 'dentist')
            AND u.status = 'active'
          ORDER BY appointment_count ASC, u.full_name ASC";

$stmt = $conn->prepare($query);
$stmt->bind_param("ss", $date, $date);
$stmt->execute();
$result = $stmt->get_result();

$dentists = [];
while ($row = $result->fetch_assoc()) {
    $row['appointment_count'] = (int)$row['appointment_count'];
    $dentists[] = $row;
}

echo json_encode(["status" => "success", "data" => $dentists]);
$conn->close();
?>
