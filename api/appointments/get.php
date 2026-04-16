<?php
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");

include_once '../config/database.php';

// 1. Check for filtering
$dentist_id = isset($_GET['dentist_id']) ? (int)$_GET['dentist_id'] : 0;

$whereClause = "";
if ($dentist_id > 0) {
    $whereClause = " WHERE a.dentist_id = $dentist_id ";
}

// 2. Fetch results
$query = "SELECT a.id, a.patient_id, a.appointment_date, a.start_time, a.status, 
          IFNULL(s.name, 'Unknown Service') as service_name, 
          IFNULL(s.price, 0) as price,
          IFNULL(u.full_name, 'Unknown Patient') as patient_name,
          IFNULL(doc.full_name, 'Unassigned') as dentist_name
          FROM appointments a
          LEFT JOIN services s ON a.service_id = s.id
          LEFT JOIN users u ON a.patient_id = u.id
          LEFT JOIN users doc ON a.dentist_id = doc.id
          $whereClause
          ORDER BY a.appointment_date DESC, a.start_time DESC";

$result = $conn->query($query);

if ($result) {
    $appointments = [];
    while ($row = $result->fetch_assoc()) {
        $appointments[] = $row;
    }

    http_response_code(200);
    echo json_encode(["status" => "success", "data" => $appointments]);
} else {
    http_response_code(500);
    echo json_encode(["status" => "error", "message" => "SQL Error: " . $conn->error]);
}

$conn->close();
?>
