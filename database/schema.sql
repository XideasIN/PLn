-- LoanFlow Personal Loan Management System Database Schema
-- Simplified version for shared hosting deployment

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
SET AUTOCOMMIT = 0;
START TRANSACTION;
SET time_zone = "+00:00";

-- Database creation (uncomment if needed)
-- CREATE DATABASE IF NOT EXISTS `loanflow` DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
-- USE `loanflow`;

-- --------------------------------------------------------
-- Table structure for `users`
-- --------------------------------------------------------

CREATE TABLE `users` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `reference_number` varchar(6) NOT NULL UNIQUE,
  `email` varchar(120) NOT NULL UNIQUE,
  `password_hash` varchar(255) NOT NULL,
  `first_name` varchar(50) NOT NULL,
  `last_name` varchar(50) NOT NULL,
  `phone` varchar(20) DEFAULT NULL,
  `date_of_birth` date DEFAULT NULL,
  `country` varchar(3) NOT NULL DEFAULT 'USA',
  `state_province` varchar(100) DEFAULT NULL,
  `city` varchar(100) DEFAULT NULL,
  `address` text DEFAULT NULL,
  `postal_zip` varchar(20) DEFAULT NULL,
  `sin_ssn` varchar(20) DEFAULT NULL,
  `role` enum('client','agent','admin','super_admin') NOT NULL DEFAULT 'client',
  `status` enum('active','inactive','locked','cancelled') NOT NULL DEFAULT 'active',
  `email_verified` boolean NOT NULL DEFAULT FALSE,
  `verification_token` varchar(255) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `last_login` timestamp NULL DEFAULT NULL,
  `failed_login_attempts` int(11) NOT NULL DEFAULT 0,
  `locked_until` timestamp NULL DEFAULT NULL,
  `ip_address` varchar(45) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_reference_number` (`reference_number`),
  KEY `idx_email` (`email`),
  KEY `idx_country` (`country`),
  KEY `idx_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- Table structure for `loan_applications`
-- --------------------------------------------------------

CREATE TABLE `loan_applications` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `reference_number` varchar(6) NOT NULL,
  `loan_amount` decimal(12,2) NOT NULL,
  `loan_type` enum('personal','debt_consolidation','home_repair','automotive','business','medical') NOT NULL DEFAULT 'personal',
  `loan_purpose` text DEFAULT NULL,
  `monthly_income` decimal(12,2) DEFAULT NULL,
  `employment_status` enum('employed','self_employed','unemployed','retired','student') DEFAULT NULL,
  `employer_name` varchar(255) DEFAULT NULL,
  `employment_duration` varchar(50) DEFAULT NULL,
  `credit_score` int(11) DEFAULT NULL,
  `existing_debts` decimal(12,2) DEFAULT 0,
  `interest_rate` decimal(5,2) DEFAULT NULL,
  `loan_term_months` int(11) DEFAULT NULL,
  `monthly_payment` decimal(12,2) DEFAULT NULL,
  `application_status` enum('pending','pre_approved','document_review','approved','funded','cancelled','rejected') NOT NULL DEFAULT 'pending',
  `current_step` int(11) NOT NULL DEFAULT 1,
  `steps_completed` json DEFAULT NULL,
  `approval_date` timestamp NULL DEFAULT NULL,
  `funding_date` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `processed_by` int(11) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_user_id` (`user_id`),
  KEY `idx_reference_number` (`reference_number`),
  KEY `idx_status` (`application_status`),
  KEY `idx_loan_type` (`loan_type`),
  FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- Table structure for `documents`
-- --------------------------------------------------------

CREATE TABLE `documents` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `application_id` int(11) DEFAULT NULL,
  `document_type` enum('photo_id','proof_income','proof_address','bank_statement','other') NOT NULL,
  `original_filename` varchar(255) NOT NULL,
  `stored_filename` varchar(255) NOT NULL,
  `file_path` varchar(500) NOT NULL,
  `file_size` int(11) NOT NULL,
  `mime_type` varchar(100) NOT NULL,
  `upload_status` enum('uploaded','verified','rejected','pending') NOT NULL DEFAULT 'uploaded',
  `verification_notes` text DEFAULT NULL,
  `verified_by` int(11) DEFAULT NULL,
  `verified_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_user_id` (`user_id`),
  KEY `idx_application_id` (`application_id`),
  KEY `idx_document_type` (`document_type`),
  KEY `idx_upload_status` (`upload_status`),
  FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  FOREIGN KEY (`application_id`) REFERENCES `loan_applications` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- Table structure for `digital_signatures`
-- --------------------------------------------------------

CREATE TABLE `digital_signatures` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `application_id` int(11) NOT NULL,
  `document_type` enum('loan_agreement','pad_agreement','asset_liability','guarantee_agreement') NOT NULL,
  `signature_data` text NOT NULL,
  `ip_address` varchar(45) DEFAULT NULL,
  `user_agent` text DEFAULT NULL,
  `signed_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `document_hash` varchar(255) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_user_id` (`user_id`),
  KEY `idx_application_id` (`application_id`),
  KEY `idx_document_type` (`document_type`),
  FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  FOREIGN KEY (`application_id`) REFERENCES `loan_applications` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- Table structure for `bank_details`
-- --------------------------------------------------------

CREATE TABLE `bank_details` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `application_id` int(11) NOT NULL,
  `bank_name` varchar(255) NOT NULL,
  `account_holder_name` varchar(255) NOT NULL,
  `account_number` varchar(50) NOT NULL,
  `routing_number` varchar(20) DEFAULT NULL,
  `swift_code` varchar(20) DEFAULT NULL,
  `account_type` enum('checking','savings','business') NOT NULL DEFAULT 'checking',
  `country` varchar(3) NOT NULL,
  `verified` boolean NOT NULL DEFAULT FALSE,
  `verified_by` int(11) DEFAULT NULL,
  `verified_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_user_id` (`user_id`),
  KEY `idx_application_id` (`application_id`),
  FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  FOREIGN KEY (`application_id`) REFERENCES `loan_applications` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- Table structure for `payments`
-- --------------------------------------------------------

CREATE TABLE `payments` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `application_id` int(11) NOT NULL,
  `payment_type` enum('subscription','percentage','additional') NOT NULL,
  `amount` decimal(12,2) NOT NULL,
  `currency` varchar(3) NOT NULL DEFAULT 'USD',
  `payment_method` enum('wire_transfer','crypto','e_transfer','credit_card') DEFAULT NULL,
  `payment_status` enum('pending','processing','completed','failed','refunded') NOT NULL DEFAULT 'pending',
  `transaction_id` varchar(255) DEFAULT NULL,
  `payment_date` timestamp NULL DEFAULT NULL,
  `due_date` timestamp NULL DEFAULT NULL,
  `confirmation_image` varchar(255) DEFAULT NULL,
  `confirmation_details` text DEFAULT NULL,
  `requires_2fa` boolean NOT NULL DEFAULT FALSE,
  `2fa_verified` boolean NOT NULL DEFAULT FALSE,
  `notes` text DEFAULT NULL,
  `processed_by` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_user_id` (`user_id`),
  KEY `idx_application_id` (`application_id`),
  KEY `idx_payment_status` (`payment_status`),
  KEY `idx_payment_type` (`payment_type`),
  FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  FOREIGN KEY (`application_id`) REFERENCES `loan_applications` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- Table structure for `payment_schemes`
-- --------------------------------------------------------

CREATE TABLE `payment_schemes` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `scheme_type` enum('subscription','percentage') NOT NULL DEFAULT 'subscription',
  `is_active` boolean NOT NULL DEFAULT TRUE,
  `subscription_fee` decimal(10,2) DEFAULT NULL,
  `max_subscription_months` int(11) DEFAULT 6,
  `percentage_fee` decimal(5,2) DEFAULT NULL,
  `refund_policy_subscription` decimal(5,2) DEFAULT 100.00,
  `refund_policy_percentage` decimal(5,2) DEFAULT 80.00,
  `loan_amount_min` decimal(12,2) DEFAULT NULL,
  `loan_amount_max` decimal(12,2) DEFAULT NULL,
  `monthly_fee_amount` decimal(10,2) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `created_by` int(11) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_scheme_type` (`scheme_type`),
  KEY `idx_is_active` (`is_active`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- Table structure for `user_payment_schemes`
-- --------------------------------------------------------

CREATE TABLE `user_payment_schemes` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `scheme_type` enum('subscription','percentage') NOT NULL DEFAULT 'percentage',
  `assigned_by` int(11) NOT NULL,
  `requires_2fa` boolean NOT NULL DEFAULT FALSE,
  `2fa_verified` boolean NOT NULL DEFAULT FALSE,
  `verified_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_user_scheme` (`user_id`),
  KEY `idx_scheme_type` (`scheme_type`),
  FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  FOREIGN KEY (`assigned_by`) REFERENCES `users` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- Table structure for `payment_method_config`
-- --------------------------------------------------------

CREATE TABLE `payment_method_config` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `method_name` enum('wire_transfer','crypto','e_transfer','credit_card') NOT NULL,
  `is_enabled` boolean NOT NULL DEFAULT FALSE,
  `allowed_countries` json DEFAULT NULL,
  `config_data` json DEFAULT NULL,
  `instructions` text DEFAULT NULL,
  `email_template` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_method` (`method_name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- Table structure for `email_templates`
-- --------------------------------------------------------

CREATE TABLE `email_templates` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `template_name` varchar(255) NOT NULL,
  `template_type` enum('confirmation','pre_approval','approval','document_request','payment_request','cancellation','reminder','step_completion') NOT NULL,
  `subject` varchar(255) NOT NULL,
  `body` text NOT NULL,
  `is_active` boolean NOT NULL DEFAULT TRUE,
  `country_specific` varchar(3) DEFAULT NULL,
  `auto_send` boolean NOT NULL DEFAULT FALSE,
  `send_delay_hours` int(11) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `created_by` int(11) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_template_type` (`template_type`),
  KEY `idx_is_active` (`is_active`),
  KEY `idx_country_specific` (`country_specific`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- Table structure for `email_queue`
-- --------------------------------------------------------

CREATE TABLE `email_queue` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) DEFAULT NULL,
  `template_id` int(11) DEFAULT NULL,
  `recipient_email` varchar(255) NOT NULL,
  `recipient_name` varchar(255) DEFAULT NULL,
  `subject` varchar(255) NOT NULL,
  `body` text NOT NULL,
  `template_name` varchar(100) DEFAULT NULL,
  `country_code` varchar(3) DEFAULT NULL,
  `status` enum('pending','sent','failed','cancelled','scheduled') NOT NULL DEFAULT 'pending',
  `priority` enum('low','normal','high','urgent') NOT NULL DEFAULT 'normal',
  `send_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `scheduled_date` date DEFAULT NULL,
  `sent_at` timestamp NULL DEFAULT NULL,
  `error_message` text DEFAULT NULL,
  `attempts` int(11) NOT NULL DEFAULT 0,
  `max_attempts` int(11) NOT NULL DEFAULT 3,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_user_id` (`user_id`),
  KEY `idx_template_id` (`template_id`),
  KEY `idx_status` (`status`),
  KEY `idx_send_at` (`send_at`),
  KEY `idx_scheduled_date` (`scheduled_date`),
  KEY `idx_country_code` (`country_code`),
  KEY `idx_priority` (`priority`),
  FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  FOREIGN KEY (`template_id`) REFERENCES `email_templates` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- Table structure for `client_messages`
-- --------------------------------------------------------

CREATE TABLE `client_messages` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `sender_id` int(11) NOT NULL,
  `recipient_id` int(11) NOT NULL,
  `subject` varchar(255) NOT NULL,
  `message` text NOT NULL,
  `is_read` boolean NOT NULL DEFAULT FALSE,
  `message_type` enum('internal','client_to_admin','admin_to_client') NOT NULL DEFAULT 'internal',
  `priority` enum('low','normal','high') NOT NULL DEFAULT 'normal',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `read_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_user_id` (`user_id`),
  KEY `idx_sender_id` (`sender_id`),
  KEY `idx_recipient_id` (`recipient_id`),
  KEY `idx_is_read` (`is_read`),
  KEY `idx_message_type` (`message_type`),
  FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  FOREIGN KEY (`sender_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  FOREIGN KEY (`recipient_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- Table structure for `client_memos`
-- --------------------------------------------------------

CREATE TABLE `client_memos` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `memo_text` text NOT NULL,
  `memo_type` enum('manual','system','email_sent','status_change','document_upload') NOT NULL DEFAULT 'manual',
  `is_internal` boolean NOT NULL DEFAULT TRUE,
  `created_by` int(11) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_user_id` (`user_id`),
  KEY `idx_created_by` (`created_by`),
  KEY `idx_memo_type` (`memo_type`),
  KEY `idx_created_at` (`created_at`),
  FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- Table structure for `call_lists`
-- --------------------------------------------------------

CREATE TABLE `call_lists` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `list_type` enum('new_application','pre_approval','general','paid_client') NOT NULL,
  `priority` enum('low','normal','high','urgent') NOT NULL DEFAULT 'normal',
  `call_attempts` int(11) NOT NULL DEFAULT 0,
  `max_attempts` int(11) NOT NULL DEFAULT 3,
  `callback_date` timestamp NULL DEFAULT NULL,
  `status` enum('pending','contacted','completed','removed') NOT NULL DEFAULT 'pending',
  `notes` text DEFAULT NULL,
  `assigned_to` int(11) DEFAULT NULL,
  `last_notification` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_user_id` (`user_id`),
  KEY `idx_list_type` (`list_type`),
  KEY `idx_priority` (`priority`),
  KEY `idx_status` (`status`),
  KEY `idx_assigned_to` (`assigned_to`),
  KEY `idx_last_notification` (`last_notification`),
  FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  FOREIGN KEY (`assigned_to`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- Table structure for `system_settings`
-- --------------------------------------------------------

CREATE TABLE `system_settings` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `setting_key` varchar(255) NOT NULL UNIQUE,
  `setting_value` text DEFAULT NULL,
  `setting_type` enum('string','integer','boolean','json','text') NOT NULL DEFAULT 'string',
  `description` text DEFAULT NULL,
  `is_public` boolean NOT NULL DEFAULT FALSE,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `updated_by` int(11) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_setting_key` (`setting_key`),
  KEY `idx_is_public` (`is_public`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- Table structure for `system_cache`
-- --------------------------------------------------------

CREATE TABLE `system_cache` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `cache_key` varchar(255) NOT NULL UNIQUE,
  `cache_value` longtext DEFAULT NULL,
  `expires_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_cache_key` (`cache_key`),
  KEY `idx_expires_at` (`expires_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- Table structure for `system_logs`
-- --------------------------------------------------------

CREATE TABLE `system_logs` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `log_type` varchar(50) NOT NULL,
  `user_id` int(11) DEFAULT NULL,
  `action` varchar(100) NOT NULL,
  `details` text DEFAULT NULL,
  `ip_address` varchar(45) DEFAULT NULL,
  `user_agent` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_log_type` (`log_type`),
  KEY `idx_user_id` (`user_id`),
  KEY `idx_action` (`action`),
  KEY `idx_created_at` (`created_at`),
  FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- Table structure for `countries`
-- --------------------------------------------------------

CREATE TABLE `countries` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `country_code` varchar(3) NOT NULL UNIQUE,
  `country_name` varchar(100) NOT NULL,
  `currency_code` varchar(3) NOT NULL,
  `currency_symbol` varchar(10) NOT NULL,
  `phone_format` varchar(50) DEFAULT NULL,
  `postal_format` varchar(50) DEFAULT NULL,
  `tax_id_format` varchar(50) DEFAULT NULL,
  `tax_id_label` varchar(50) DEFAULT NULL,
  `is_active` boolean NOT NULL DEFAULT TRUE,
  `timezone` varchar(50) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_country_code` (`country_code`),
  KEY `idx_is_active` (`is_active`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- Table structure for `holidays`
-- --------------------------------------------------------

CREATE TABLE `holidays` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `country_code` varchar(3) NOT NULL,
  `holiday_name` varchar(255) NOT NULL,
  `holiday_date` date NOT NULL,
  `is_recurring` boolean NOT NULL DEFAULT TRUE,
  `year` int(11) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_country_code` (`country_code`),
  KEY `idx_holiday_date` (`holiday_date`),
  KEY `idx_year` (`year`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- Table structure for `contact_forms`
-- --------------------------------------------------------

CREATE TABLE `contact_forms` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(255) NOT NULL,
  `phone` varchar(20) NOT NULL,
  `email` varchar(255) NOT NULL,
  `comment` text DEFAULT NULL,
  `ai_response` text DEFAULT NULL,
  `email_sent` boolean NOT NULL DEFAULT FALSE,
  `status` enum('new','processed','responded') NOT NULL DEFAULT 'new',
  `submitted_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `processed_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_email` (`email`),
  KEY `idx_status` (`status`),
  KEY `idx_submitted_at` (`submitted_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- Table structure for `audit_logs`
-- --------------------------------------------------------

CREATE TABLE `audit_logs` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) DEFAULT NULL,
  `action` varchar(255) NOT NULL,
  `table_name` varchar(100) DEFAULT NULL,
  `record_id` int(11) DEFAULT NULL,
  `old_values` json DEFAULT NULL,
  `new_values` json DEFAULT NULL,
  `ip_address` varchar(45) DEFAULT NULL,
  `user_agent` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_user_id` (`user_id`),
  KEY `idx_action` (`action`),
  KEY `idx_table_name` (`table_name`),
  KEY `idx_created_at` (`created_at`),
  FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- Insert default data
-- --------------------------------------------------------

-- Insert default payment schemes
INSERT INTO `payment_schemes` (`scheme_type`, `is_active`, `subscription_fee`, `max_subscription_months`, `percentage_fee`, `refund_policy_subscription`, `refund_policy_percentage`) VALUES
('subscription', TRUE, 99.99, 6, NULL, 100.00, NULL),
('percentage', FALSE, NULL, NULL, 15.00, NULL, 80.00);

-- Insert supported countries
INSERT INTO `countries` (`country_code`, `country_name`, `currency_code`, `currency_symbol`, `phone_format`, `postal_format`, `tax_id_format`, `tax_id_label`, `timezone`) VALUES
('USA', 'United States', 'USD', '$', '(###) ###-####', '#####-####', '###-##-####', 'SSN', 'America/New_York'),
('CAN', 'Canada', 'CAD', 'C$', '(###) ###-####', 'A#A #A#', '###-###-###', 'SIN', 'America/Toronto'),
('GBR', 'United Kingdom', 'GBP', '£', '+44 #### ######', 'AA## #AA', 'AA ##### A', 'NINO', 'Europe/London'),
('AUS', 'Australia', 'AUD', 'A$', '+61 # #### ####', '####', '###-###-###', 'TFN', 'Australia/Sydney');

-- Insert default system settings
INSERT INTO `system_settings` (`setting_key`, `setting_value`, `setting_type`, `description`, `is_public`) VALUES
('site_name', 'LoanFlow', 'string', 'Website name', TRUE),
('site_email', 'support@loanflow.com', 'string', 'Default system email', FALSE),
('reference_start', '100000', 'integer', 'Starting reference number', FALSE),
('pre_approval_delay_hours', '6', 'integer', 'Hours to wait before pre-approval', FALSE),
('max_login_attempts', '5', 'integer', 'Maximum login attempts before lockout', FALSE),
('session_timeout_minutes', '30', 'integer', 'Session timeout in minutes', FALSE),
('allowed_countries', '["USA","CAN","GBR","AUS"]', 'json', 'Allowed countries for applications', FALSE),
('email_sending_hours', '{"start": "09:00", "end": "17:00"}', 'json', 'Hours when emails can be sent', FALSE);

-- Insert default payment method configurations
INSERT INTO `payment_method_config` (`method_name`, `is_enabled`, `allowed_countries`, `config_data`, `instructions`, `email_template`) VALUES
('wire_transfer', FALSE, '["USA","CAN","GBR","AUS"]', '{}', '', ''),
('crypto', FALSE, '["USA","CAN","GBR","AUS"]', '{}', '', ''),
('e_transfer', FALSE, '["CAN"]', '{}', '', ''),
('credit_card', FALSE, '["USA","CAN","GBR","AUS"]', '{}', '', '');

-- Insert default admin user
INSERT INTO `users` (`reference_number`, `email`, `password_hash`, `first_name`, `last_name`, `role`, `status`, `email_verified`, `country`) VALUES
('100001', 'admin@loanflow.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Admin', 'User', 'admin', 'active', TRUE, 'USA');

-- Insert default email templates
INSERT INTO `email_templates` (`template_name`, `template_type`, `subject`, `body`, `is_active`, `auto_send`, `send_delay_hours`) VALUES
('Application Confirmation', 'confirmation', 'Application Received - Reference #{ref#}', 
'Dear {first_name},\n\nThank you for submitting your loan application. Your reference number is {ref#}.\n\nWe will review your application and contact you within 24 hours.\n\nBest regards,\nLoanFlow Team', 
TRUE, TRUE, 0),

('Pre-Approval Notice', 'pre_approval', 'Pre-Approval - Reference #{ref#}', 
'Dear {first_name},\n\nCongratulations! You have been pre-approved for your loan application {ref#}.\n\nPlease log into your account to complete the next steps:\n1. Upload required documents\n2. Review and sign agreements\n3. Provide bank details\n\nLogin: {login_url}\n\nBest regards,\nLoanFlow Team', 
TRUE, TRUE, 6),

('Document Request', 'document_request', 'Documents Required - Reference #{ref#}', 
'Dear {first_name},\n\nWe need additional documents to process your application {ref#}.\n\nRequired documents:\n- Photo ID\n- Proof of Income\n- Proof of Address\n\nPlease upload these documents in your client area.\n\nLogin: {login_url}\n\nBest regards,\nLoanFlow Team', 
TRUE, FALSE, 0),

('Payment Instructions - Wire Transfer', 'payment_instructions_wire_transfer', 'Wire Transfer Instructions - Payment #{payment_id}', 
'Dear {first_name},\n\nYour payment has been created. Please complete your wire transfer using the following details:\n\nPayment Amount: {amount}\nBank Name: {bank_name}\nAccount Name: {account_name}\nAccount Number: {account_number}\nRouting Number: {routing_number}\nSWIFT Code: {swift_code}\n\nIMPORTANT: After completing your transfer, please return to your account to submit confirmation.\n\nLogin: {login_url}\n\nBest regards,\nLoanFlow Team', 
TRUE, TRUE, 0),

('Payment Instructions - e-Transfer', 'payment_instructions_e_transfer', 'e-Transfer Instructions - Payment #{payment_id}', 
'Dear {first_name},\n\nYour payment has been created. Please complete your e-Transfer using the following details:\n\nPayment Amount: {amount}\nEmail Address: {email_address}\nRecipient Name: {recipient_name}\nSecurity Question: {security_question}\nSecurity Answer: {security_answer}\n\nIMPORTANT: After completing your e-Transfer, please return to your account to submit confirmation.\n\nLogin: {login_url}\n\nBest regards,\nLoanFlow Team', 
TRUE, TRUE, 0),

('Payment Instructions - Cryptocurrency', 'payment_instructions_crypto', 'Cryptocurrency Payment Instructions - Payment #{payment_id}', 
'Dear {first_name},\n\nYour cryptocurrency payment has been created. Please send the exact amount to the following wallet:\n\nPayment Amount: {amount}\nCurrency: {currency_type}\nWallet Address: {wallet_address}\nNetwork: {network}\n\nYour payment will be automatically confirmed once the transaction is verified on the blockchain.\n\nLogin: {login_url}\n\nBest regards,\nLoanFlow Team', 
TRUE, TRUE, 0);

-- --------------------------------------------------------
-- Table structure for `rate_limits`
-- --------------------------------------------------------

CREATE TABLE `rate_limits` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `rate_key` varchar(255) NOT NULL,
  `created_at` int(11) NOT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_rate_key` (`rate_key`),
  KEY `idx_created_at` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- Table structure for `security_blocks`
-- --------------------------------------------------------

CREATE TABLE `security_blocks` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `identifier` varchar(255) NOT NULL,
  `reason` text NOT NULL,
  `blocked_at` int(11) NOT NULL,
  `expires_at` int(11) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_identifier` (`identifier`),
  KEY `idx_expires_at` (`expires_at`),
  KEY `idx_blocked_at` (`blocked_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Create indexes for performance
CREATE INDEX idx_users_created_at ON users(created_at);
CREATE INDEX idx_loan_applications_created_at ON loan_applications(created_at);
CREATE INDEX idx_documents_created_at ON documents(created_at);
CREATE INDEX idx_payments_created_at ON payments(created_at);
CREATE INDEX idx_email_queue_send_at ON email_queue(send_at);

COMMIT;
