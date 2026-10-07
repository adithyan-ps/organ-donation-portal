-- =======================================================
-- Organ Donation – Recipient Matching Portal
-- Database Schema & Comprehensive Demo Dataset
-- Compatible with MySQL 5.7+, MySQL 8.0+, MariaDB 10.3+
-- Designed for XAMPP / WAMP / LAMP Environments
-- =======================================================

CREATE DATABASE IF NOT EXISTS `organ_donation_db` DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE `organ_donation_db`;

-- Drop existing tables in dependency order if re-importing
DROP TABLE IF EXISTS `admin_logs`;
DROP TABLE IF EXISTS `matches`;
DROP TABLE IF EXISTS `donors`;
DROP TABLE IF EXISTS `recipients`;
DROP TABLE IF EXISTS `users`;

-- -------------------------------------------------------
-- Table structure for: users
-- Core authentication for administrators, donors, and recipients
-- -------------------------------------------------------
CREATE TABLE `users` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `name` VARCHAR(120) NOT NULL,
  `email` VARCHAR(150) NOT NULL UNIQUE,
  `password_hash` VARCHAR(255) NOT NULL,
  `role` ENUM('admin', 'donor', 'recipient') NOT NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -------------------------------------------------------
-- Table structure for: donors
-- Registered donor information and organ pledges
-- -------------------------------------------------------
CREATE TABLE `donors` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `user_id` INT NULL,
  `full_name` VARCHAR(120) NOT NULL,
  `age` INT NOT NULL,
  `gender` ENUM('Male', 'Female', 'Other') NOT NULL,
  `email` VARCHAR(150) NOT NULL,
  `mobile` VARCHAR(20) NOT NULL,
  `address_city` VARCHAR(200) NOT NULL,
  `blood_group` ENUM('A+', 'A-', 'B+', 'B-', 'AB+', 'AB-', 'O+', 'O-') NOT NULL,
  `organ_donated` ENUM('Kidney', 'Liver', 'Heart', 'Lung', 'Pancreas', 'Cornea', 'Intestine', 'Tissue') NOT NULL,
  `availability_status` ENUM('Available', 'Temporarily Unavailable', 'Donated') NOT NULL DEFAULT 'Available',
  `medical_notes` TEXT NULL,
  `consent_given` TINYINT(1) NOT NULL DEFAULT 1,
  `verification_status` ENUM('Pending', 'Verified', 'Rejected') NOT NULL DEFAULT 'Pending',
  `admin_notes` TEXT NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  CONSTRAINT `fk_donor_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -------------------------------------------------------
-- Table structure for: recipients
-- Registered organ transplant requirements
-- -------------------------------------------------------
CREATE TABLE `recipients` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `user_id` INT NULL,
  `full_name` VARCHAR(120) NOT NULL,
  `age` INT NOT NULL,
  `gender` ENUM('Male', 'Female', 'Other') NOT NULL,
  `email` VARCHAR(150) NOT NULL,
  `mobile` VARCHAR(20) NOT NULL,
  `hospital_city` VARCHAR(200) NOT NULL,
  `blood_group` ENUM('A+', 'A-', 'B+', 'B-', 'AB+', 'AB-', 'O+', 'O-') NOT NULL,
  `organ_needed` ENUM('Kidney', 'Liver', 'Heart', 'Lung', 'Pancreas', 'Cornea', 'Intestine', 'Tissue') NOT NULL,
  `urgency_level` ENUM('Low', 'Medium', 'High', 'Critical') NOT NULL DEFAULT 'Medium',
  `medical_notes` TEXT NULL,
  `consent_given` TINYINT(1) NOT NULL DEFAULT 1,
  `verification_status` ENUM('Pending', 'Verified', 'Rejected') NOT NULL DEFAULT 'Pending',
  `admin_notes` TEXT NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  CONSTRAINT `fk_recipient_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -------------------------------------------------------
-- Table structure for: matches
-- Preliminary matches identified between verified donors & recipients
-- -------------------------------------------------------
CREATE TABLE `matches` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `donor_id` INT NOT NULL,
  `recipient_id` INT NOT NULL,
  `organ` VARCHAR(50) NOT NULL,
  `compatibility_score` INT NOT NULL,
  `score_breakdown` TEXT NULL,
  `status` ENUM('Potential', 'Under Review', 'Contacted', 'Approved', 'Closed') NOT NULL DEFAULT 'Potential',
  `notes` TEXT NULL,
  `matched_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY `unique_match_pair` (`donor_id`, `recipient_id`),
  CONSTRAINT `fk_match_donor` FOREIGN KEY (`donor_id`) REFERENCES `donors` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_match_recipient` FOREIGN KEY (`recipient_id`) REFERENCES `recipients` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -------------------------------------------------------
-- Table structure for: admin_logs
-- Audit log of administrative actions, verifications, and status changes
-- -------------------------------------------------------
CREATE TABLE `admin_logs` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `admin_id` INT NULL,
  `action` VARCHAR(100) NOT NULL,
  `description` TEXT NOT NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT `fk_log_admin` FOREIGN KEY (`admin_id`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =======================================================
-- SEED DATA
-- Default Passwords:
--   Admin: admin@organportal.com / admin123
--   Donor: donor@demo.com / donor123
--   Recipient: recipient@demo.com / recipient123
-- =======================================================

INSERT INTO `users` (`id`, `name`, `email`, `password_hash`, `role`, `created_at`) VALUES
(1, 'Portal Administrator', 'admin@organportal.com', '$2y$10$xW0p3fQxSe.JDSZ0LJrziuQJ/T3bRbgEpwcU7EfnzqIqV19VKCZbG', 'admin', NOW()),
(2, 'Adithya Nair', 'donor@demo.com', '$2y$10$cs924MmwlxUgaf6DTxnLyOYljB0brgzFCFPkWA5k7eT1ZPkyPwFwO', 'donor', NOW()),
(3, 'Sarah Thomas', 'sarah.t@example.com', '$2y$10$cs924MmwlxUgaf6DTxnLyOYljB0brgzFCFPkWA5k7eT1ZPkyPwFwO', 'donor', NOW()),
(4, 'Robert Miller', 'robert.m@example.com', '$2y$10$cs924MmwlxUgaf6DTxnLyOYljB0brgzFCFPkWA5k7eT1ZPkyPwFwO', 'donor', NOW()),
(5, 'Elena Vance', 'elena.v@example.com', '$2y$10$cs924MmwlxUgaf6DTxnLyOYljB0brgzFCFPkWA5k7eT1ZPkyPwFwO', 'donor', NOW()),
(6, 'Vikram Sharma', 'vikram.s@example.com', '$2y$10$cs924MmwlxUgaf6DTxnLyOYljB0brgzFCFPkWA5k7eT1ZPkyPwFwO', 'donor', NOW()),
(7, 'David Chen', 'recipient@demo.com', '$2y$10$VKIKvXb6MGRgfGKwDCAXie9HpG4o6QUbOpXjqkmf4XkwUy7lQixWu', 'recipient', NOW()),
(8, 'Ananya Patel', 'ananya.p@example.com', '$2y$10$VKIKvXb6MGRgfGKwDCAXie9HpG4o6QUbOpXjqkmf4XkwUy7lQixWu', 'recipient', NOW()),
(9, 'Michael Scott', 'michael.s@example.com', '$2y$10$VKIKvXb6MGRgfGKwDCAXie9HpG4o6QUbOpXjqkmf4XkwUy7lQixWu', 'recipient', NOW()),
(10, 'Fatima Noor', 'fatima.n@example.com', '$2y$10$VKIKvXb6MGRgfGKwDCAXie9HpG4o6QUbOpXjqkmf4XkwUy7lQixWu', 'recipient', NOW()),
(11, 'George Lucas', 'george.l@example.com', '$2y$10$VKIKvXb6MGRgfGKwDCAXie9HpG4o6QUbOpXjqkmf4XkwUy7lQixWu', 'recipient', NOW());

-- -------------------------------------------------------
-- Seed: donors
-- -------------------------------------------------------
INSERT INTO `donors` (`id`, `user_id`, `full_name`, `age`, `gender`, `email`, `mobile`, `address_city`, `blood_group`, `organ_donated`, `availability_status`, `medical_notes`, `consent_given`, `verification_status`, `admin_notes`, `created_at`) VALUES
(1, 2, 'Adithya Nair', 29, 'Male', 'donor@demo.com', '+91 9876543210', 'Kochi, Kerala', 'O+', 'Kidney', 'Available', 'Healthy non-smoker, normal renal function and blood tests completed.', 1, 'Verified', 'Identity and preliminary health history reviewed and verified.', NOW() - INTERVAL 12 DAY),
(2, 3, 'Sarah Thomas', 34, 'Female', 'sarah.t@example.com', '+91 9845123456', 'Bengaluru, Karnataka', 'A+', 'Liver', 'Available', 'Routine fitness evaluations normal, liver enzymes normal.', 1, 'Verified', 'Medical declaration verified by hospital partner.', NOW() - INTERVAL 10 DAY),
(3, 4, 'Robert Miller', 42, 'Male', 'robert.m@example.com', '+91 9712345678', 'Mumbai, Maharashtra', 'B+', 'Heart', 'Available', 'Pledged through authorized registry, family consent documented.', 1, 'Verified', 'Verified registry pledge.', NOW() - INTERVAL 8 DAY),
(4, 5, 'Elena Vance', 26, 'Female', 'elena.v@example.com', '+91 9901122334', 'Chennai, Tamil Nadu', 'O-', 'Kidney', 'Available', 'Universal donor, complete health checkup attached.', 1, 'Verified', 'Documentation valid and verified.', NOW() - INTERVAL 5 DAY),
(5, 6, 'Vikram Sharma', 38, 'Male', 'vikram.s@example.com', '+91 9823456789', 'New Delhi, Delhi', 'AB+', 'Cornea', 'Available', 'Pledged corneal donation upon deceased status.', 1, 'Verified', 'Consent verified.', NOW() - INTERVAL 3 DAY),
(6, NULL, 'Kavita Rao', 31, 'Female', 'kavita.r@example.com', '+91 9447112233', 'Hyderabad, Telangana', 'A-', 'Kidney', 'Available', 'Pending medical documentation upload.', 1, 'Pending', 'Awaiting hospital lab reports.', NOW() - INTERVAL 1 DAY),
(7, NULL, 'Siddharth Sen', 45, 'Male', 'sid.sen@example.com', '+91 9830112244', 'Kolkata, West Bengal', 'B-', 'Lung', 'Temporarily Unavailable', 'Recent travel, temporary deferral requested.', 1, 'Verified', 'Temporarily unavailable per donor request.', NOW() - INTERVAL 15 DAY);

-- -------------------------------------------------------
-- Seed: recipients
-- -------------------------------------------------------
INSERT INTO `recipients` (`id`, `user_id`, `full_name`, `age`, `gender`, `email`, `mobile`, `hospital_city`, `blood_group`, `organ_needed`, `urgency_level`, `medical_notes`, `consent_given`, `verification_status`, `admin_notes`, `created_at`) VALUES
(1, 7, 'David Chen', 52, 'Male', 'recipient@demo.com', '+91 9811223344', 'Kochi, Kerala', 'O+', 'Kidney', 'Critical', 'End-stage renal disease (ESRD), requiring dialysis 3x/week. Immediate transplant recommended.', 1, 'Verified', 'Confirmed by Aster Medcity Nephrology Dept.', NOW() - INTERVAL 14 DAY),
(2, 8, 'Ananya Patel', 39, 'Female', 'ananya.p@example.com', '+91 9898765432', 'Bengaluru, Karnataka', 'A+', 'Liver', 'High', 'Acute liver failure candidate, MELD score 28. Hospital priority listing.', 1, 'Verified', 'Verified with Manipal Hospital Transplant Unit.', NOW() - INTERVAL 9 DAY),
(3, 9, 'Michael Scott', 47, 'Male', 'michael.s@example.com', '+91 9776655443', 'Mumbai, Maharashtra', 'B+', 'Heart', 'Critical', 'Severe dilated cardiomyopathy, currently in ICU support.', 1, 'Verified', 'Verified with Asian Heart Institute.', NOW() - INTERVAL 7 DAY),
(4, 10, 'Fatima Noor', 24, 'Female', 'fatima.n@example.com', '+91 9654321098', 'Chennai, Tamil Nadu', 'A+', 'Kidney', 'High', 'Chronic glomerulonephritis, undergoing hemodialysis.', 1, 'Verified', 'Verified with Apollo Hospitals Nephrology.', NOW() - INTERVAL 4 DAY),
(5, 11, 'George Lucas', 61, 'Male', 'george.l@example.com', '+91 9543210987', 'New Delhi, Delhi', 'AB+', 'Cornea', 'Medium', 'Bilateral corneal blindness secondary to chemical injury.', 1, 'Verified', 'Verified with Dr. Shroff Charity Eye Hospital.', NOW() - INTERVAL 2 DAY),
(6, NULL, 'Pooja Iyer', 35, 'Female', 'pooja.i@example.com', '+91 9445566778', 'Bengaluru, Karnataka', 'O-', 'Kidney', 'High', 'Dialysis dependent, undergoing antibody screen.', 1, 'Pending', 'Pending physician verification signature.', NOW() - INTERVAL 1 DAY);

-- -------------------------------------------------------
-- Seed: matches (Calculated preliminary demo matches)
-- -------------------------------------------------------
INSERT INTO `matches` (`id`, `donor_id`, `recipient_id`, `organ`, `compatibility_score`, `score_breakdown`, `status`, `notes`, `matched_at`) VALUES
(1, 1, 1, 'Kidney', 100, 'Organ match: 40pts | Exact Blood Group (O+ -> O+): 35pts | Urgency (Critical): 20pts | Location Proximity (Kochi -> Kochi): 5pts', 'Under Review', 'Clinical coordinator initiating HLA and cross-matching assessment.', NOW() - INTERVAL 3 DAY),
(2, 2, 2, 'Liver', 95, 'Organ match: 40pts | Exact Blood Group (A+ -> A+): 35pts | Urgency (High): 15pts | Location Proximity (Bengaluru -> Bengaluru): 5pts', 'Contacted', 'Transplant team contacted recipient coordinator for donor profile review.', NOW() - INTERVAL 2 DAY),
(3, 3, 3, 'Heart', 100, 'Organ match: 40pts | Exact Blood Group (B+ -> B+): 35pts | Urgency (Critical): 20pts | Location Proximity (Mumbai -> Mumbai): 5pts', 'Under Review', 'Urgent case: Donor and recipient cardiothoracic teams notified for formal protocol.', NOW() - INTERVAL 2 DAY),
(4, 4, 4, 'Kidney', 90, 'Organ match: 40pts | Universal Compatible (O- -> A+): 30pts | Urgency (High): 15pts | Location Proximity (Chennai -> Chennai): 5pts', 'Potential', 'Preliminary algorithm match identified. Pending coordinator review.', NOW() - INTERVAL 1 DAY),
(5, 5, 5, 'Cornea', 85, 'Organ match: 40pts | Exact Blood Group (AB+ -> AB+): 35pts | Urgency (Medium): 10pts | Location Proximity (New Delhi -> New Delhi): 5pts', 'Potential', 'Tissue match flagged for eye bank coordinator review.', NOW() - INTERVAL 1 DAY);

-- -------------------------------------------------------
-- Seed: admin_logs
-- -------------------------------------------------------
INSERT INTO `admin_logs` (`id`, `admin_id`, `action`, `description`, `created_at`) VALUES
(1, 1, 'System Initialization', 'Organ Donation Portal database and security settings initialized.', NOW() - INTERVAL 14 DAY),
(2, 1, 'Donor Verified', 'Verified donor profile #1 (Adithya Nair, Organ: Kidney, Blood: O+).', NOW() - INTERVAL 12 DAY),
(3, 1, 'Recipient Verified', 'Verified recipient requirement #1 (David Chen, Organ: Kidney, Urgency: Critical).', NOW() - INTERVAL 12 DAY),
(4, 1, 'Match Generated', 'System identified preliminary match between Donor #1 and Recipient #1 (Score: 100%).', NOW() - INTERVAL 12 DAY),
(5, 1, 'Match Status Update', 'Updated match #1 status to "Under Review". Clinical team notified.', NOW() - INTERVAL 3 DAY),
(6, 1, 'Donor Verified', 'Verified donor profile #4 (Elena Vance, Organ: Kidney, Blood: O-).', NOW() - INTERVAL 2 DAY);
