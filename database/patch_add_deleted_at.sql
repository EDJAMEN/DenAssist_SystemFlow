-- ============================================================
-- DentAssist — Safe Migration Patch
-- Run this in phpMyAdmin > dental_clinic_db > SQL tab
-- This ONLY adds missing columns. It will NOT delete any data.
-- ============================================================

-- 1. Add deleted_at to users (for soft-delete / Archive feature)
ALTER TABLE `users`
    ADD COLUMN IF NOT EXISTS `deleted_at` timestamp NULL DEFAULT NULL AFTER `created_at`;

-- 2. Add cancellation_reason to appointments (for Decline/Cancel with reason)
ALTER TABLE `appointments`
    ADD COLUMN IF NOT EXISTS `cancellation_reason` text DEFAULT NULL AFTER `status`;

-- 3. Add reward_id to appointments (for Rewards redemption on booking)
ALTER TABLE `appointments`
    ADD COLUMN IF NOT EXISTS `reward_id` int(11) DEFAULT NULL AFTER `service_id`,
    ADD CONSTRAINT IF NOT EXISTS `fk_appt_reward`
        FOREIGN KEY (`reward_id`) REFERENCES `rewards` (`id`) ON DELETE SET NULL;

-- 4. Ensure invoices table exists (for Billing & Invoices feature)
CREATE TABLE IF NOT EXISTS `invoices` (
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 5. Ensure reward_points_logs table exists
CREATE TABLE IF NOT EXISTS `reward_points_logs` (
    `id` int(11) NOT NULL AUTO_INCREMENT,
    `patient_id` int(11) NOT NULL,
    `points` int(11) NOT NULL,
    `action` enum('earned','redeemed') NOT NULL,
    `reason` varchar(255) NOT NULL,
    `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    FOREIGN KEY (`patient_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 6. Ensure patients_meta exists (for patient medical records)
CREATE TABLE IF NOT EXISTS `patients_meta` (
    `user_id` int(11) NOT NULL,
    `dob` date DEFAULT NULL,
    `emergency_contact_name` varchar(100) DEFAULT NULL,
    `emergency_contact_phone` varchar(20) DEFAULT NULL,
    `allergies` text DEFAULT NULL,
    `medications` text DEFAULT NULL,
    `reward_points` int(11) DEFAULT 0,
    PRIMARY KEY (`user_id`),
    FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 7. Ensure schedule_settings has default clinic hours
INSERT IGNORE INTO `schedule_settings` (`setting_key`, `setting_value`) VALUES
('clinic_open',  '08:00:00'),
('clinic_close', '17:00:00'),
('break_start',  '12:00:00'),
('break_end',    '13:00:00');

-- Done! All tables and columns are now in sync with the DentAssist system.
SELECT 'Migration complete. DentAssist is now fully connected.' AS result;
