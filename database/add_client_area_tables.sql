-- Add Client Area Tables
-- LoanFlow Personal Loan Management System
-- Tables for loan calculations and user preferences

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
SET AUTOCOMMIT = 0;
START TRANSACTION;
SET time_zone = "+00:00";

-- --------------------------------------------------------
-- Table structure for `loan_calculations`
-- --------------------------------------------------------

CREATE TABLE `loan_calculations` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `calculation_type` enum('payment','affordability','comparison') NOT NULL DEFAULT 'payment',
  `loan_amount` decimal(12,2) NOT NULL,
  `interest_rate` decimal(5,2) NOT NULL,
  `loan_term` int(11) NOT NULL,
  `monthly_payment` decimal(12,2) NOT NULL,
  `total_interest` decimal(12,2) NOT NULL,
  `total_amount` decimal(12,2) NOT NULL,
  `processing_fee` decimal(10,2) DEFAULT 0.00,
  `calculation_data` json DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_user_id` (`user_id`),
  KEY `idx_calculation_type` (`calculation_type`),
  KEY `idx_created_at` (`created_at`),
  FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- Table structure for `user_preferences`
-- --------------------------------------------------------

CREATE TABLE `user_preferences` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `email_notifications` boolean NOT NULL DEFAULT TRUE,
  `sms_notifications` boolean NOT NULL DEFAULT TRUE,
  `marketing_emails` boolean NOT NULL DEFAULT FALSE,
  `language` varchar(5) NOT NULL DEFAULT 'en',
  `timezone` varchar(50) NOT NULL DEFAULT 'UTC',
  `currency` varchar(3) NOT NULL DEFAULT 'USD',
  `theme` enum('light','dark','auto') NOT NULL DEFAULT 'light',
  `dashboard_layout` json DEFAULT NULL,
  `notification_settings` json DEFAULT NULL,
  `privacy_settings` json DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_user_preferences` (`user_id`),
  KEY `idx_user_id` (`user_id`),
  FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- Table structure for `client_messages`
-- --------------------------------------------------------

CREATE TABLE `client_messages` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `application_id` int(11) DEFAULT NULL,
  `thread_id` varchar(50) DEFAULT NULL,
  `parent_message_id` int(11) DEFAULT NULL,
  `sender_type` enum('client','admin','agent','system') NOT NULL DEFAULT 'client',
  `sender_id` int(11) DEFAULT NULL,
  `recipient_type` enum('client','admin','agent','system') NOT NULL DEFAULT 'admin',
  `recipient_id` int(11) DEFAULT NULL,
  `category` enum('general','technical','billing','complaint','document','other') NOT NULL DEFAULT 'general',
  `priority` enum('low','normal','high','urgent') NOT NULL DEFAULT 'normal',
  `subject` varchar(255) NOT NULL,
  `message` text NOT NULL,
  `attachments` json DEFAULT NULL,
  `status` enum('unread','read','replied','closed','archived') NOT NULL DEFAULT 'unread',
  `is_internal` boolean NOT NULL DEFAULT FALSE,
  `read_at` timestamp NULL DEFAULT NULL,
  `replied_at` timestamp NULL DEFAULT NULL,
  `closed_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_user_id` (`user_id`),
  KEY `idx_application_id` (`application_id`),
  KEY `idx_thread_id` (`thread_id`),
  KEY `idx_parent_message_id` (`parent_message_id`),
  KEY `idx_sender_type` (`sender_type`),
  KEY `idx_recipient_type` (`recipient_type`),
  KEY `idx_category` (`category`),
  KEY `idx_priority` (`priority`),
  KEY `idx_status` (`status`),
  KEY `idx_created_at` (`created_at`),
  FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  FOREIGN KEY (`application_id`) REFERENCES `loan_applications` (`id`) ON DELETE SET NULL,
  FOREIGN KEY (`parent_message_id`) REFERENCES `client_messages` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- Table structure for `user_bank_accounts`
-- --------------------------------------------------------

CREATE TABLE `user_bank_accounts` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `bank_name` varchar(255) NOT NULL,
  `account_holder_name` varchar(255) NOT NULL,
  `account_number` varchar(100) NOT NULL,
  `routing_number` varchar(50) DEFAULT NULL,
  `swift_code` varchar(20) DEFAULT NULL,
  `iban` varchar(50) DEFAULT NULL,
  `account_type` enum('checking','savings','business','other') NOT NULL DEFAULT 'checking',
  `country` varchar(3) NOT NULL,
  `currency` varchar(3) NOT NULL DEFAULT 'USD',
  `is_primary` boolean NOT NULL DEFAULT FALSE,
  `is_verified` boolean NOT NULL DEFAULT FALSE,
  `verification_method` enum('micro_deposit','document','manual','api') DEFAULT NULL,
  `verification_date` timestamp NULL DEFAULT NULL,
  `verification_notes` text DEFAULT NULL,
  `verified_by` int(11) DEFAULT NULL,
  `status` enum('active','inactive','suspended','closed') NOT NULL DEFAULT 'active',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_user_id` (`user_id`),
  KEY `idx_is_primary` (`is_primary`),
  KEY `idx_is_verified` (`is_verified`),
  KEY `idx_status` (`status`),
  KEY `idx_country` (`country`),
  FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- Table structure for `client_activity_log`
-- --------------------------------------------------------

CREATE TABLE `client_activity_log` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `application_id` int(11) DEFAULT NULL,
  `activity_type` enum('login','logout','document_upload','document_download','signature','payment','message','profile_update','calculation','other') NOT NULL,
  `activity_description` varchar(500) NOT NULL,
  `activity_data` json DEFAULT NULL,
  `ip_address` varchar(45) DEFAULT NULL,
  `user_agent` text DEFAULT NULL,
  `session_id` varchar(255) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_user_id` (`user_id`),
  KEY `idx_application_id` (`application_id`),
  KEY `idx_activity_type` (`activity_type`),
  KEY `idx_created_at` (`created_at`),
  KEY `idx_ip_address` (`ip_address`),
  FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  FOREIGN KEY (`application_id`) REFERENCES `loan_applications` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- Add missing columns to existing tables
-- --------------------------------------------------------

-- Add missing columns to users table if they don't exist
ALTER TABLE `users` 
ADD COLUMN IF NOT EXISTS `phone_verified` boolean NOT NULL DEFAULT FALSE AFTER `email_verified`,
ADD COLUMN IF NOT EXISTS `address` text DEFAULT NULL AFTER `city`,
ADD COLUMN IF NOT EXISTS `state` varchar(100) DEFAULT NULL AFTER `address`,
ADD COLUMN IF NOT EXISTS `zip_code` varchar(20) DEFAULT NULL AFTER `state`,
ADD COLUMN IF NOT EXISTS `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP AFTER `created_at`;

-- Add missing columns to bank_details table if they don't exist
ALTER TABLE `bank_details` 
ADD COLUMN IF NOT EXISTS `is_primary` boolean NOT NULL DEFAULT FALSE AFTER `verified_at`;

-- --------------------------------------------------------
-- Insert default user preferences for existing users
-- --------------------------------------------------------

INSERT IGNORE INTO `user_preferences` (`user_id`, `email_notifications`, `sms_notifications`, `marketing_emails`)
SELECT `id`, TRUE, TRUE, FALSE FROM `users` WHERE `role` = 'client';

-- --------------------------------------------------------
-- Create triggers for automatic thread_id generation
-- --------------------------------------------------------

DELIMITER //

CREATE TRIGGER `generate_message_thread_id` 
BEFORE INSERT ON `client_messages`
FOR EACH ROW
BEGIN
    IF NEW.thread_id IS NULL AND NEW.parent_message_id IS NULL THEN
        SET NEW.thread_id = CONCAT('MSG_', DATE_FORMAT(NOW(), '%Y%m%d'), '_', LPAD(NEW.user_id, 6, '0'), '_', UNIX_TIMESTAMP());
    ELSEIF NEW.parent_message_id IS NOT NULL THEN
        SET NEW.thread_id = (SELECT thread_id FROM client_messages WHERE id = NEW.parent_message_id);
    END IF;
END//

DELIMITER ;

-- --------------------------------------------------------
-- Create indexes for performance optimization
-- --------------------------------------------------------

CREATE INDEX idx_loan_calculations_user_created ON loan_calculations(user_id, created_at DESC);
CREATE INDEX idx_client_messages_thread_status ON client_messages(thread_id, status);
CREATE INDEX idx_client_messages_user_created ON client_messages(user_id, created_at DESC);
CREATE INDEX idx_user_bank_accounts_user_primary ON user_bank_accounts(user_id, is_primary);
CREATE INDEX idx_client_activity_log_user_created ON client_activity_log(user_id, created_at DESC);

-- --------------------------------------------------------
-- Insert sample data for testing (optional)
-- --------------------------------------------------------

-- Sample loan calculation
INSERT INTO `loan_calculations` (`user_id`, `calculation_type`, `loan_amount`, `interest_rate`, `loan_term`, `monthly_payment`, `total_interest`, `total_amount`) 
SELECT `id`, 'payment', 10000.00, 12.50, 24, 470.73, 1297.52, 11297.52 
FROM `users` WHERE `role` = 'client' LIMIT 1;

-- Sample client message
INSERT INTO `client_messages` (`user_id`, `sender_type`, `recipient_type`, `category`, `priority`, `subject`, `message`) 
SELECT `id`, 'client', 'admin', 'general', 'normal', 'Welcome Message', 'Thank you for using our loan management system. Please let us know if you have any questions.' 
FROM `users` WHERE `role` = 'client' LIMIT 1;

COMMIT;

-- --------------------------------------------------------
-- Verification queries (run these to verify tables were created)
-- --------------------------------------------------------

-- SHOW TABLES LIKE '%loan_calculations%';
-- SHOW TABLES LIKE '%user_preferences%';
-- SHOW TABLES LIKE '%client_messages%';
-- SHOW TABLES LIKE '%user_bank_accounts%';
-- SHOW TABLES LIKE '%client_activity_log%';

-- SELECT COUNT(*) as loan_calculations_count FROM loan_calculations;
-- SELECT COUNT(*) as user_preferences_count FROM user_preferences;
-- SELECT COUNT(*) as client_messages_count FROM client_messages;
-- SELECT COUNT(*) as user_bank_accounts_count FROM user_bank_accounts;
-- SELECT COUNT(*) as client_activity_log_count FROM client_activity_log;