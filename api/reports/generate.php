<?php
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: GET");
header("Access-Control-Allow-Headers: Content-Type");

include_once '../config/database.php';

// Allow GET requests with start_date and end_date
$start_date = isset($_GET['start_date']) ? htmlspecialchars(strip_tags($_GET['start_date'])) : date('Y-m-01');
$end_date = isset($_GET['end_date']) ? htmlspecialchars(strip_tags($_GET['end_date'])) : date('Y-m-t');

// Validate dates
if (!$start_date || !$end_date) {
    http_response_code(400);
    echo json_encode(["status" => "error", "message" => "Valid start_date and end_date are required."]);
    exit;
}

$report_data = [
    "date_range" => ["start" => $start_date, "end" => $end_date],
    "appointments" => [],
    "services" => [],
    "patients" => [],
    "revenue" => [],
    "dentists" => []
];

try {
    // A. Appointment Summary
    $stmt = $conn->prepare("
        SELECT 
            COUNT(id) AS total_appointments,
            SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) AS completed,
            SUM(CASE WHEN status = 'cancelled' THEN 1 ELSE 0 END) AS cancelled,
            SUM(CASE WHEN status = 'walk-in' THEN 1 ELSE 0 END) AS walk_in,
            SUM(CASE WHEN status IN ('upcoming', 'pending') THEN 1 ELSE 0 END) AS upcoming
        FROM appointments
        WHERE appointment_date BETWEEN ? AND ?
    ");
    $stmt->bind_param("ss", $start_date, $end_date);
    $stmt->execute();
    $report_data['appointments'] = $stmt->get_result()->fetch_assoc();

    // B. Service Report
    $stmt = $conn->prepare("
        SELECT 
            s.name AS service_name,
            COUNT(a.id) AS usage_count,
            COALESCE(SUM(i.total_amount), 0) AS total_revenue
        FROM appointments a
        JOIN services s ON a.service_id = s.id
        LEFT JOIN invoices i ON a.id = i.appointment_id
        WHERE a.appointment_date BETWEEN ? AND ?
        GROUP BY s.id, s.name
        ORDER BY usage_count DESC
    ");
    $stmt->bind_param("ss", $start_date, $end_date);
    $stmt->execute();
    $res = $stmt->get_result();
    while ($row = $res->fetch_assoc()) {
        $report_data['services'][] = $row;
    }

    // C. Patient Report
    $stmt = $conn->prepare("
        SELECT 
            COUNT(DISTINCT patient_id) as total_patients_seen
        FROM appointments
        WHERE appointment_date BETWEEN ? AND ? AND status != 'cancelled'
    ");
    $stmt->bind_param("ss", $start_date, $end_date);
    $stmt->execute();
    $patient_data = $stmt->get_result()->fetch_assoc();
    
    // Calculate new patients in this period
    $stmt = $conn->prepare("
        SELECT COUNT(id) as new_patients
        FROM users
        WHERE role = 'patient' AND DATE(created_at) BETWEEN ? AND ?
    ");
    $stmt->bind_param("ss", $start_date, $end_date);
    $stmt->execute();
    $new_patients = $stmt->get_result()->fetch_assoc()['new_patients'];
    
    $report_data['patients'] = [
        "total_seen" => $patient_data['total_patients_seen'],
        "new_patients" => $new_patients
    ];

    // D. Revenue Report
    $stmt = $conn->prepare("
        SELECT 
            COALESCE(SUM(i.total_amount), 0) AS total_billed,
            COALESCE(SUM(CASE WHEN i.status = 'paid' THEN i.total_amount ELSE 0 END), 0) AS total_paid,
            COALESCE(SUM(CASE WHEN i.status != 'paid' THEN i.total_amount ELSE 0 END), 0) AS total_unpaid
        FROM invoices i
        LEFT JOIN appointments a ON i.appointment_id = a.id
        WHERE a.appointment_date BETWEEN ? AND ?
    ");
    $stmt->bind_param("ss", $start_date, $end_date);
    $stmt->execute();
    $report_data['revenue'] = $stmt->get_result()->fetch_assoc();

    // E. Dentist Activity Report
    $stmt = $conn->prepare("
        SELECT 
            u.full_name AS dentist_name,
            COUNT(a.id) AS total_appointments,
            COUNT(DISTINCT a.patient_id) AS unique_patients
        FROM appointments a
        JOIN users u ON a.dentist_id = u.id
        WHERE a.appointment_date BETWEEN ? AND ?
        GROUP BY u.id, u.full_name
        ORDER BY total_appointments DESC
    ");
    $stmt->bind_param("ss", $start_date, $end_date);
    $stmt->execute();
    $res = $stmt->get_result();
    while ($row = $res->fetch_assoc()) {
        $report_data['dentists'][] = $row;
    }

    echo json_encode(["status" => "success", "data" => $report_data]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(["status" => "error", "message" => "Failed to generate report."]);
}

$conn->close();
?>
