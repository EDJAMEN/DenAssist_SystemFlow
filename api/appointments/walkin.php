<?php
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: POST");
header("Access-Control-Allow-Headers: Content-Type");

include_once '../config/database.php';

$data = json_decode(file_get_contents("php://input"));

if (
    empty($data->patient_id) ||
    empty($data->service_id) ||
    empty($data->dentist_id)
) {
    http_response_code(400);
    echo json_encode(["status" => "error", "message" => "Patient, dentist, and service are required."]);
    exit;
}

$patient_id = (int)$data->patient_id;
$service_id = (int)$data->service_id;
$dentist_id = (int)$data->dentist_id;
$appointment_date = !empty($data->appointment_date) ? $data->appointment_date : date('Y-m-d');

// 0. Verify Dentist Availability (Active Status)
$dentist_stmt = $conn->prepare("SELECT is_active FROM users WHERE id = ? AND role IN ('admin', 'dentist')");
$dentist_stmt->bind_param("i", $dentist_id);
$dentist_stmt->execute();
$dentist_res = $dentist_stmt->get_result();
if ($dentist_res->num_rows === 0 || $dentist_res->fetch_assoc()['is_active'] == 0) {
    http_response_code(400);
    echo json_encode(["status" => "error", "message" => "The selected dentist is currently not accepting bookings."]);
    exit;
}

// 1. Get service duration
$stmt_s = $conn->prepare("SELECT duration_minutes FROM services WHERE id = ? LIMIT 1");
$stmt_s->bind_param("i", $service_id);
$stmt_s->execute();
$res_s = $stmt_s->get_result();

if ($res_s->num_rows === 0) {
    http_response_code(400);
    echo json_encode(["status" => "error", "message" => "Invalid service selected."]);
    exit;
}

$service  = $res_s->fetch_assoc();
$duration = (int)$service['duration_minutes'];

// 2. Get clinic hours from schedule_settings
$clinic_open  = '08:00:00';
$clinic_close = '17:00:00';
$break_start  = '12:00:00';
$break_end    = '13:00:00';

$settings_q = $conn->query("SELECT setting_key, setting_value FROM schedule_settings");
while ($s = $settings_q->fetch_assoc()) {
    if ($s['setting_key'] === 'clinic_open')  $clinic_open  = $s['setting_value'];
    if ($s['setting_key'] === 'clinic_close') $clinic_close = $s['setting_value'];
    if ($s['setting_key'] === 'break_start')  $break_start  = $s['setting_value'];
    if ($s['setting_key'] === 'break_end')    $break_end    = $s['setting_value'];
}

// 3. Determine start time — use provided time or find the next available slot
if (!empty($data->start_time)) {
    // Staff specified a time manually
    $start_time = $data->start_time;
} else {
    // AUTO-ASSIGN: Find the next available slot for this dentist today
    $now = date('H:i:s');
    $is_today = ($appointment_date === date('Y-m-d'));

    // Start from current time if today, otherwise clinic open
    $candidate = $is_today ? max($now, $clinic_open) : $clinic_open;

    // Round up to the nearest 15-minute mark
    $ts = strtotime($candidate);
    $min = (int)date('i', $ts);
    $remainder = $min % 15;
    if ($remainder > 0) {
        $ts += (15 - $remainder) * 60;
    }
    $candidate = date('H:i:s', $ts);

    // Get existing appointments for this dentist on this date
    $stmt_appts = $conn->prepare(
        "SELECT start_time, end_time FROM appointments
         WHERE dentist_id = ? AND appointment_date = ? AND status != 'cancelled'
         ORDER BY start_time ASC"
    );
    $stmt_appts->bind_param("is", $dentist_id, $appointment_date);
    $stmt_appts->execute();
    $appts = $stmt_appts->get_result()->fetch_all(MYSQLI_ASSOC);

    $found = false;
    $max_attempts = 50; // safety limit
    $attempts = 0;

    while (!$found && $attempts < $max_attempts) {
        $attempts++;
        $slot_start = strtotime($candidate);
        $slot_end   = $slot_start + ($duration * 60);

        $end_candidate = date('H:i:s', $slot_end);

        // Check: within clinic hours?
        if ($end_candidate > $clinic_close || $candidate >= $clinic_close) {
            break; // no more slots today
        }

        // Check: overlaps with break?
        if ($candidate < $break_end && $end_candidate > $break_start) {
            // Jump past break
            $candidate = $break_end;
            continue;
        }

        // Check: overlaps with any existing appointment?
        $conflict = false;
        foreach ($appts as $apt) {
            if ($candidate < $apt['end_time'] && $end_candidate > $apt['start_time']) {
                $conflict = true;
                // Jump to the end of this conflicting appointment
                $candidate = $apt['end_time'];
                // Round up to 15 min
                $ts2 = strtotime($candidate);
                $m2 = (int)date('i', $ts2);
                $r2 = $m2 % 15;
                if ($r2 > 0) $ts2 += (15 - $r2) * 60;
                $candidate = date('H:i:s', $ts2);
                break;
            }
        }

        if (!$conflict) {
            $start_time = $candidate;
            $found = true;
        }
    }

    if (!$found) {
        http_response_code(409);
        echo json_encode([
            "status"  => "error",
            "message" => "No available time slots remaining for this dentist today. Try another dentist or date."
        ]);
        exit;
    }
}

// 4. Calculate end_time
$end_time = date('H:i:s', strtotime($start_time) + ($duration * 60));

// Final validation: end time within clinic hours
if ($end_time > $clinic_close) {
    http_response_code(409);
    echo json_encode([
        "status"  => "error",
        "message" => "This appointment would extend past clinic closing time ($clinic_close). Choose an earlier slot or shorter service."
    ]);
    exit;
}

// 5. Final overlap check
$stmt_c = $conn->prepare(
    "SELECT id FROM appointments
     WHERE dentist_id = ? AND appointment_date = ? AND status != 'cancelled'
     AND ((start_time < ? AND end_time > ?) OR (start_time BETWEEN ? AND ?) OR (end_time BETWEEN ? AND ?))
     LIMIT 1"
);
$stmt_c->bind_param("isssssss", $dentist_id, $appointment_date, $start_time, $end_time, $start_time, $end_time, $start_time, $end_time);
$stmt_c->execute();

if ($stmt_c->get_result()->num_rows > 0) {
    http_response_code(409);
    echo json_encode(["status" => "error", "message" => "Time conflict detected. The slot overlaps with another appointment."]);
    exit;
}

// 6. Insert walk-in appointment
$stmt = $conn->prepare(
    "INSERT INTO appointments (patient_id, dentist_id, service_id, appointment_date, start_time, end_time, status)
     VALUES (?, ?, ?, ?, ?, ?, 'walk-in')"
);
$stmt->bind_param("iissss", $patient_id, $dentist_id, $service_id, $appointment_date, $start_time, $end_time);

if ($stmt->execute()) {
    http_response_code(201);

    // Format times for the response
    $start_fmt = date('g:i A', strtotime($start_time));
    $end_fmt   = date('g:i A', strtotime($end_time));

    echo json_encode([
        "status"  => "success",
        "message" => "Walk-in registered successfully.",
        "data"    => [
            "appointment_id" => $conn->insert_id,
            "start_time"     => $start_time,
            "end_time"       => $end_time,
            "start_fmt"      => $start_fmt,
            "end_fmt"        => $end_fmt,
            "duration"       => $duration
        ]
    ]);
} else {
    http_response_code(503);
    echo json_encode(["status" => "error", "message" => "Failed to register walk-in. Try again."]);
}

$conn->close();
?>
