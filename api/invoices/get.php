<?php
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: GET");

include_once '../config/database.php';

$patient_id = isset($_GET['patient_id']) ? (int)$_GET['patient_id'] : 0;

$whereClause = "";
if ($patient_id > 0) {
    $whereClause = " WHERE i.patient_id = $patient_id ";
}

$query = "SELECT i.*, 
                 IFNULL(s.name, 'General Consultation') as service_name,
                 a.appointment_date
          FROM invoices i
          LEFT JOIN appointments a ON i.appointment_id = a.id
          LEFT JOIN services s ON a.service_id = s.id
          $whereClause
          ORDER BY i.created_at DESC";

$result = $conn->query($query);

if ($result) {
    $invoices = [];
    while ($row = $result->fetch_assoc()) {
        $invoices[] = $row;
    }
    http_response_code(200);
    echo json_encode(["status" => "success", "data" => $invoices]);
} else {
    http_response_code(500);
    echo json_encode(["status" => "error", "message" => "SQL Error: " . $conn->error]);
}

$conn->close();
?>
