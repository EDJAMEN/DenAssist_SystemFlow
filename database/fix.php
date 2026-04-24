<?php
// DentAssist — One-Click Database Fix
// Run this by visiting: http://localhost/DentAssist_System1/database/fix.php
// DELETE this file after running it for security.

include_once '../api/config/database.php';

$results = [];

$patches = [
    "Add deleted_at to users" => "ALTER TABLE `users` ADD COLUMN IF NOT EXISTS `deleted_at` timestamp NULL DEFAULT NULL",
    "Add cancellation_reason to appointments" => "ALTER TABLE `appointments` ADD COLUMN IF NOT EXISTS `cancellation_reason` text DEFAULT NULL",
    "Add reward_id to appointments" => "ALTER TABLE `appointments` ADD COLUMN IF NOT EXISTS `reward_id` int(11) DEFAULT NULL",
    "Create invoices table" => "CREATE TABLE IF NOT EXISTS `invoices` (
        `id` int(11) NOT NULL AUTO_INCREMENT,
        `patient_id` int(11) NOT NULL,
        `appointment_id` int(11) DEFAULT NULL,
        `total_amount` decimal(10,2) NOT NULL,
        `discount_amount` decimal(10,2) DEFAULT 0.00,
        `status` enum('unpaid','partially_paid','paid','cancelled') DEFAULT 'unpaid',
        `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (`id`),
        FOREIGN KEY (`patient_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
        FOREIGN KEY (`appointment_id`) REFERENCES `appointments` (`id`) ON DELETE SET NULL
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
    "Create reward_points_logs table" => "CREATE TABLE IF NOT EXISTS `reward_points_logs` (
        `id` int(11) NOT NULL AUTO_INCREMENT,
        `patient_id` int(11) NOT NULL,
        `points` int(11) NOT NULL,
        `action` enum('earned','redeemed') NOT NULL,
        `reason` varchar(255) NOT NULL,
        `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (`id`),
        FOREIGN KEY (`patient_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
    "Insert default clinic hours" => "INSERT IGNORE INTO `schedule_settings` (`setting_key`, `setting_value`) VALUES
        ('clinic_open','08:00:00'),('clinic_close','17:00:00'),
        ('break_start','12:00:00'),('break_end','13:00:00')",
];

foreach ($patches as $label => $sql) {
    if ($conn->query($sql)) {
        $results[] = ["status" => "✅ OK", "label" => $label];
    } else {
        $results[] = ["status" => "❌ Error: " . $conn->error, "label" => $label];
    }
}

$conn->close();
?>
<!DOCTYPE html>
<html>

<head>
    <title>DentAssist — Database Fix</title>
    <style>
        body {
            font-family: sans-serif;
            max-width: 700px;
            margin: 40px auto;
            padding: 20px;
            background: #f5f5f5;
        }

        h2 {
            color: #2d6a4f;
        }

        .item {
            background: white;
            padding: 12px 16px;
            margin: 8px 0;
            border-radius: 8px;
            display: flex;
            gap: 16px;
            align-items: center;
            box-shadow: 0 1px 4px rgba(0, 0, 0, 0.08);
        }

        .ok {
            border-left: 4px solid #40c057;
        }

        .err {
            border-left: 4px solid #fa5252;
        }

        .btn {
            display: inline-block;
            margin-top: 24px;
            padding: 12px 28px;
            background: #2d6a4f;
            color: white;
            text-decoration: none;
            border-radius: 8px;
            font-weight: bold;
        }

        .warn {
            background: #fff3cd;
            padding: 12px 16px;
            border-radius: 8px;
            border-left: 4px solid #ffc107;
            margin-top: 20px;
            font-size: 13px;
        }
    </style>
</head>

<body>
    <h2>🦷 DentAssist — Database Migration Results</h2>
    <?php foreach ($results as $r): ?>
        <div class="item <?= str_starts_with($r['status'], '✅') ? 'ok' : 'err' ?>">
            <span><?= $r['status'] ?></span>
            <span><?= htmlspecialchars($r['label']) ?></span>
        </div>
    <?php endforeach; ?>

    <div class="warn">
        ⚠️ <strong>Security Notice:</strong> Please delete <code>database/fix.php</code> after this runs successfully.
    </div>

    <a class="btn" href="/../DentAssist_System1/">▶ Go to DentAssist Login</a>
</body>

</html>