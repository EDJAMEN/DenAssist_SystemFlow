<?php
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");

include_once '../config/database.php';

$dentist_id = isset($_GET['dentist_id']) ? (int)$_GET['dentist_id'] : 0;
$date = isset($_GET['date']) ? $_GET['date'] : '';

if ($dentist_id > 0 && !empty($date)) {
    // 1. Get Clinic Hours & Breaks
    $settings = [];
    $res = $conn->query("SELECT setting_key, setting_value FROM schedule_settings");
    while($row = $res->fetch_assoc()) {
        $settings[$row['setting_key']] = $row['setting_value'];
    }

    $openTime = $settings['clinic_open'] ?? '08:00:00';
    $closeTime = $settings['clinic_close'] ?? '17:00:00';
    $breakStart = $settings['break_start'] ?? '12:00:00';
    $breakEnd = $settings['break_end'] ?? '13:00:00';

    // 2. Get existing appointments for this specific dentist on this date
    $booked = [];
    $stmt = $conn->prepare("SELECT start_time, end_time FROM appointments WHERE dentist_id = ? AND appointment_date = ? AND status != 'cancelled'");
    $stmt->bind_param("is", $dentist_id, $date);
    $stmt->execute();
    $result = $stmt->get_result();
    while($row = $result->fetch_assoc()) {
        $booked[] = [
            'start' => strtotime($row['start_time']),
            'end' => strtotime($row['end_time'])
        ];
    }

    // 3. Generate slots (30 min increments)
    $slots = [];
    $current = strtotime($openTime);
    $end = strtotime($closeTime);
    $breakS = strtotime($breakStart);
    $breakE = strtotime($breakEnd);
    
    // Safety: Don't book in the past if it's today
    $now = time();
    $isToday = ($date == date('Y-m-d'));

    while ($current < $end) {
        $slotEnd = $current + (30 * 60); // 30 min slots
        $isAvailable = true;

        // Check if in break time
        if ($current >= $breakS && $current < $breakE) {
            $isAvailable = false;
        }

        // Check if in the past
        if ($isToday && $current <= $now) {
            $isAvailable = false;
        }

        // Check against booked appointments
        foreach ($booked as $apt) {
            if (($current >= $apt['start'] && $current < $apt['end']) || 
                ($slotEnd > $apt['start'] && $slotEnd <= $apt['end'])) {
                $isAvailable = false;
                break;
            }
        }

        if ($isAvailable) {
            $slots[] = date("h:i A", $current);
        }

        $current += (30 * 60);
    }

    echo json_encode(["status" => "success", "slots" => $slots]);
} else {
    echo json_encode(["status" => "error", "message" => "Invalid parameters."]);
}

$conn->close();
?>
