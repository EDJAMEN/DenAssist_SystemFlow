-- 1. Obliterate the old database and everything in it
DROP DATABASE IF EXISTS `dental_clinic_db`;

-- 2. Create a fresh, clean database
CREATE DATABASE `dental_clinic_db`;

-- 3. Switch into our new clean slate
USE `dental_clinic_db`;

-- ==========================================
-- A. AUTHENTICATION & USERS (Login / Register)
-- ==========================================
CREATE TABLE `users` (
    `id` int(11) NOT NULL AUTO_INCREMENT,
    `full_name` varchar(100) NOT NULL,
    `email` varchar(100) NOT NULL UNIQUE,
    `phone` varchar(20) NOT NULL,
    `professional_id` varchar(50) DEFAULT NULL,
    `position` varchar(100) DEFAULT NULL,
    `password_hash` varchar(255) NOT NULL,
    `role` enum(
        'admin',
        'patient',
        'staff',
        'dentist'
    ) NOT NULL DEFAULT 'patient',
    `status` enum(
        'pending',
        'active',
        'suspended'
    ) NOT NULL DEFAULT 'active',
    `is_master` boolean NOT NULL DEFAULT FALSE,
    `google_id` varchar(255) DEFAULT NULL,
    `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`)
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4;

-- B. PATIENT RECORDS (Intake forms, Points, Medical History)
CREATE TABLE `patients_meta` (
    `user_id` int(11) NOT NULL,
    `dob` date DEFAULT NULL,
    `emergency_contact_name` varchar(100) DEFAULT NULL,
    `emergency_contact_phone` varchar(20) DEFAULT NULL,
    `allergies` text DEFAULT NULL,
    `medications` text DEFAULT NULL,
    `reward_points` int(11) DEFAULT 0,
    PRIMARY KEY (`user_id`),
    FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4;

-- ==========================================
-- C. CLINIC MANAGEMENT (Services & Scheduling)
-- ==========================================
CREATE TABLE `services` (
    `id` int(11) NOT NULL AUTO_INCREMENT,
    `name` varchar(100) NOT NULL,
    `description` text DEFAULT NULL,
    `price` decimal(10, 2) NOT NULL,
    `duration_minutes` int(11) NOT NULL DEFAULT 30,
    `is_active` boolean NOT NULL DEFAULT TRUE,
    PRIMARY KEY (`id`)
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4;

-- Admin Settings (Clinic hours, Breaks, Holidays for AI Booking)
CREATE TABLE `schedule_settings` (
    `id` int(11) NOT NULL AUTO_INCREMENT,
    `setting_key` varchar(50) NOT NULL UNIQUE,
    `setting_value` varchar(255) NOT NULL,
    PRIMARY KEY (`id`)
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4;

-- ==========================================
-- D. APPOINTMENTS & AI LOGIC
-- ==========================================
CREATE TABLE `appointments` (
    `id` int(11) NOT NULL AUTO_INCREMENT,
    `patient_id` int(11) NOT NULL,
    `dentist_id` int(11) DEFAULT NULL,
    `service_id` int(11) NOT NULL,
    `appointment_date` date NOT NULL,
    `start_time` time NOT NULL,
    `end_time` time NOT NULL,
    `status` enum(
        'pending',
        'upcoming',
        'completed',
        'cancelled',
        'walk-in'
    ) NOT NULL DEFAULT 'pending',
    `is_offline_sync` boolean DEFAULT FALSE,
    `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    FOREIGN KEY (`patient_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
    FOREIGN KEY (`dentist_id`) REFERENCES `users` (`id`) ON DELETE SET NULL,
    FOREIGN KEY (`service_id`) REFERENCES `services` (`id`) ON DELETE CASCADE
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4;

-- ==========================================
-- E. CLINICAL RECORDS (Treatment History)
-- ==========================================
CREATE TABLE `treatments` (
    `id` int(11) NOT NULL AUTO_INCREMENT,
    `appointment_id` int(11) NOT NULL,
    `tooth_number` varchar(10) DEFAULT NULL,
    `diagnosis` text DEFAULT NULL,
    `clinical_notes` text DEFAULT NULL,
    `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    FOREIGN KEY (`appointment_id`) REFERENCES `appointments` (`id`) ON DELETE CASCADE
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4;

-- ==========================================
-- F. BILLING & INVOICING
-- ==========================================
CREATE TABLE `invoices` (
    `id` int(11) NOT NULL AUTO_INCREMENT,
    `patient_id` int(11) NOT NULL,
    `appointment_id` int(11) DEFAULT NULL,
    `total_amount` decimal(10, 2) NOT NULL,
    `discount_amount` decimal(10, 2) DEFAULT 0.00,
    `status` enum(
        'unpaid',
        'partially_paid',
        'paid',
        'cancelled'
    ) DEFAULT 'unpaid',
    `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    FOREIGN KEY (`patient_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
    FOREIGN KEY (`appointment_id`) REFERENCES `appointments` (`id`) ON DELETE SET NULL
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4;

-- ==========================================
-- G. CHATBOT PERSISTENCE
-- ==========================================
CREATE TABLE `chat_history` (
    `id` int(11) NOT NULL AUTO_INCREMENT,
    `patient_id` int(11) NOT NULL,
    `message` text NOT NULL,
    `sender` enum('user', 'ai') NOT NULL,
    `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    FOREIGN KEY (`patient_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4;

-- ==========================================
-- H. OFFLINE SYNC SYSTEM
-- ==========================================
CREATE TABLE `sync_logs` (
    `id` int(11) NOT NULL AUTO_INCREMENT,
    `action` varchar(50) NOT NULL,
    `table_name` varchar(50) NOT NULL,
    `record_id` int(11) DEFAULT NULL,
    `payload` text DEFAULT NULL,
    `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`)
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4;

-- ==========================================
-- I. REWARD POINTS SYSTEM
-- ==========================================
CREATE TABLE `rewards` (
    `id` int(11) NOT NULL AUTO_INCREMENT,
    `name` varchar(100) NOT NULL,
    `description` text DEFAULT NULL,
    `points_required` int(11) NOT NULL,
    `reward_type` enum('discount', 'service') NOT NULL, -- 'discount' is cash value, 'service' is specific item
    `value` decimal(10, 2) DEFAULT NULL,               -- e.g., 50.00 for ₱50 off
    `service_id` int(11) DEFAULT NULL,                 -- linked service for free items
    `is_active` boolean DEFAULT TRUE,
    PRIMARY KEY (`id`),
    FOREIGN KEY (`service_id`) REFERENCES `services` (`id`) ON DELETE SET NULL
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4;

CREATE TABLE `reward_points_logs` (
    `id` int(11) NOT NULL AUTO_INCREMENT,
    `patient_id` int(11) NOT NULL,
    `points` int(11) NOT NULL,
    `action` enum('earned', 'redeemed') NOT NULL,
    `reason` varchar(255) NOT NULL,
    `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    FOREIGN KEY (`patient_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4;

-- ========================================================
-- INJECT CLINIC BASE CONFIGURATION
-- ========================================================
INSERT INTO
    `schedule_settings` (
        `setting_key`,
        `setting_value`
    )
VALUES ('clinic_open', '08:00:00'),
    ('clinic_close', '17:00:00'),
    ('break_start', '12:00:00'),
    ('break_end', '13:00:00');

INSERT INTO
    `users` (
        `id`,
        `full_name`,
        `email`,
        `phone`,
        `password_hash`,
        `role`,
        `status`,
        `is_master`
    )
VALUES (
        1,
        'Dr. L. Reyes',
        'admin@dentassist.com',
        '09170000000',
        '123456',
        'admin',
        'active',
        1
    ),
    (
        2,
        'Maria Santos',
        'maria@gmail.com',
        '09171112222',
        '123456',
        'patient',
        'active',
        0
    );

INSERT INTO
    `patients_meta` (`user_id`, `reward_points`)
VALUES (2, 120);

INSERT INTO
    `services` (
        `id`,
        `name`,
        `description`,
        `price`,
        `duration_minutes`
    )
VALUES (
        1,
        'Teeth Cleaning (Prophylaxis)',
        'Basic teeth cleaning and polishing.',
        800.00,
        30
    ),
    (
        2,
        'Tooth Extraction',
        'Safe removal of a damaged tooth.',
        1200.00,
        45
    ),
    (
        3,
        'Laser Teeth Whitening',
        'Advanced whitening session.',
        5000.00,
        60
    ),
    (
        4,
        'Braces / Orthodontics',
        'Alignment and braces consultation.',
        15000.00,
        60
    ),
    (
        5,
        'Dental Consultation',
        'Initial checkup and assessment.',
        500.00,
        15
    );

INSERT INTO
    `appointments` (
        `id`,
        `patient_id`,
        `dentist_id`,
        `service_id`,
        `appointment_date`,
        `start_time`,
        `end_time`,
        `status`
    )
VALUES (
        1,
        2,
        1,
        1,
        '2026-10-25',
        '10:00:00',
        '10:30:00',
        'upcoming'
    );

INSERT INTO `rewards` (`name`, `description`, `points_required`, `reward_type`, `value`, `service_id`) VALUES
('₱50 Discount', 'Get ₱50 off your next treatment.', 100, 'discount', 50.00, NULL),
('₱100 Discount', 'Get ₱100 off your next treatment.', 200, 'discount', 100.00, NULL),
('Free Teeth Cleaning', 'One free prophylaxis session.', 500, 'service', 800.00, 1),
('Free Consultation', 'One free dental checkup.', 300, 'service', 500.00, 5);

INSERT INTO `reward_points_logs` (`patient_id`, `points`, `action`, `reason`) VALUES
(2, 50, 'earned', 'Welcome bonus'),
(2, 50, 'earned', 'First visit completed'),
(2, 20, 'earned', 'Booking bonus');