-- Fee Sent Forms Table
-- LoanFlow Personal Loan Management System
-- Table for tracking country-specific fee payment submissions

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
SET AUTOCOMMIT = 0;
START TRANSACTION;
SET time_zone = "+00:00";

-- --------------------------------------------------------
-- Table structure for `fee_sent_forms`
-- --------------------------------------------------------

CREATE TABLE `fee_sent_forms` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `application_id` int(11) NOT NULL,
  `payment_method` enum('wire_transfer','crypto','e_transfer','credit_card') NOT NULL,
  `amount_sent` decimal(12,2) NOT NULL,
  `date_sent` date NOT NULL,
  `transaction_reference` varchar(255) DEFAULT NULL,
  `receipt_filename` varchar(255) DEFAULT NULL,
  `additional_notes` text DEFAULT NULL,
  `country` varchar(3) NOT NULL,
  `payment_details` json DEFAULT NULL COMMENT 'Payment method specific details (sending_bank, account_holder, transfer_id, etc.)',
  `status` enum('pending','confirmed','rejected','under_review') NOT NULL DEFAULT 'pending',
  `admin_notes` text DEFAULT NULL,
  `reviewed_by` int(11) DEFAULT NULL,
  `reviewed_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_user_id` (`user_id`),
  KEY `idx_application_id` (`application_id`),
  KEY `idx_payment_method` (`payment_method`),
  KEY `idx_status` (`status`),
  KEY `idx_country` (`country`),
  KEY `idx_date_sent` (`date_sent`),
  KEY `idx_created_at` (`created_at`),
  KEY `idx_reviewed_by` (`reviewed_by`),
  FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  FOREIGN KEY (`application_id`) REFERENCES `loan_applications` (`id`) ON DELETE CASCADE,
  FOREIGN KEY (`reviewed_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- Table structure for `fee_form_templates`
-- --------------------------------------------------------

CREATE TABLE `fee_form_templates` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `country` varchar(3) NOT NULL,
  `payment_method` enum('wire_transfer','crypto','e_transfer','credit_card') NOT NULL,
  `template_name` varchar(255) NOT NULL,
  `instructions` text NOT NULL,
  `email_template` text DEFAULT NULL,
  `required_fields` json DEFAULT NULL,
  `validation_rules` json DEFAULT NULL,
  `is_active` boolean NOT NULL DEFAULT TRUE,
  `created_by` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_country_method` (`country`, `payment_method`),
  KEY `idx_country` (`country`),
  KEY `idx_payment_method` (`payment_method`),
  KEY `idx_is_active` (`is_active`),
  FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- Table structure for `fee_form_notifications`
-- --------------------------------------------------------

CREATE TABLE `fee_form_notifications` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `fee_form_id` int(11) NOT NULL,
  `notification_type` enum('submitted','confirmed','rejected','reminder') NOT NULL,
  `recipient_email` varchar(255) NOT NULL,
  `subject` varchar(255) NOT NULL,
  `message` text NOT NULL,
  `sent_at` timestamp NULL DEFAULT NULL,
  `status` enum('pending','sent','failed') NOT NULL DEFAULT 'pending',
  `error_message` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_fee_form_id` (`fee_form_id`),
  KEY `idx_notification_type` (`notification_type`),
  KEY `idx_status` (`status`),
  KEY `idx_sent_at` (`sent_at`),
  FOREIGN KEY (`fee_form_id`) REFERENCES `fee_sent_forms` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- Insert default fee form templates
-- --------------------------------------------------------

-- USA Templates
INSERT INTO `fee_form_templates` (`country`, `payment_method`, `template_name`, `instructions`, `email_template`, `required_fields`) VALUES
('US', 'wire_transfer', 'USA Wire Transfer Form', 
'Please complete your wire transfer using the bank details provided in your payment instructions email. After sending the wire transfer, return to this form to submit your payment confirmation.', 
'Dear {user_name},\n\nThank you for your loan application. Please complete your wire transfer using the following details:\n\nBank Name: {bank_name}\nAccount Number: {account_number}\nRouting Number: {routing_number}\nAmount: ${amount}\nReference: Application #{application_id}\n\nAfter completing your transfer, please return to your client portal to submit the fee sent form.\n\nBest regards,\nLoanFlow Team', 
'{"amount_sent": true, "date_sent": true, "transaction_reference": false}'),

('US', 'crypto', 'USA Cryptocurrency Form', 
'Please send your cryptocurrency payment to the wallet address provided. After completing the transaction, submit this form with your transaction hash.', 
'Dear {user_name},\n\nPlease send your cryptocurrency payment to:\n\nWallet Address: {wallet_address}\nAmount: ${amount} USD equivalent\nNetwork: {network}\nReference: Application #{application_id}\n\nAfter completing your transaction, please return to submit the fee sent form with your transaction hash.\n\nBest regards,\nLoanFlow Team', 
'{"amount_sent": true, "date_sent": true, "transaction_reference": true}');

-- Canada Templates
INSERT INTO `fee_form_templates` (`country`, `payment_method`, `template_name`, `instructions`, `email_template`, `required_fields`) VALUES
('CA', 'e_transfer', 'Canada e-Transfer Form', 
'Please send your e-Transfer to the email address provided in your payment instructions. After sending, complete this form with your transfer details.', 
'Dear {user_name},\n\nPlease send your e-Transfer payment to:\n\nEmail: {email_address}\nAmount: ${amount} CAD\nSecurity Question: {security_question}\nAnswer: {security_answer}\nReference: Application #{application_id}\n\nAfter sending your e-Transfer, please return to submit the fee sent form.\n\nBest regards,\nLoanFlow Team', 
'{"amount_sent": true, "date_sent": true, "transaction_reference": false}'),

('CA', 'crypto', 'Canada Cryptocurrency Form', 
'Please send your cryptocurrency payment to the wallet address provided. After completing the transaction, submit this form with your transaction hash.', 
'Dear {user_name},\n\nPlease send your cryptocurrency payment to:\n\nWallet Address: {wallet_address}\nAmount: ${amount} CAD equivalent\nNetwork: {network}\nReference: Application #{application_id}\n\nAfter completing your transaction, please return to submit the fee sent form with your transaction hash.\n\nBest regards,\nLoanFlow Team', 
'{"amount_sent": true, "date_sent": true, "transaction_reference": true}');

-- Australia Templates
INSERT INTO `fee_form_templates` (`country`, `payment_method`, `template_name`, `instructions`, `email_template`, `required_fields`) VALUES
('AU', 'wire_transfer', 'Australia Wire Transfer Form', 
'Please complete your wire transfer using the bank details provided. After sending the transfer, return to this form to submit your payment confirmation.', 
'Dear {user_name},\n\nPlease complete your wire transfer using:\n\nBank Name: {bank_name}\nBSB: {bsb_number}\nAccount Number: {account_number}\nAmount: ${amount} AUD\nReference: Application #{application_id}\n\nAfter completing your transfer, please return to submit the fee sent form.\n\nBest regards,\nLoanFlow Team', 
'{"amount_sent": true, "date_sent": true, "transaction_reference": false}'),

('AU', 'crypto', 'Australia Cryptocurrency Form', 
'Please send your cryptocurrency payment to the wallet address provided. After completing the transaction, submit this form with your transaction hash.', 
'Dear {user_name},\n\nPlease send your cryptocurrency payment to:\n\nWallet Address: {wallet_address}\nAmount: ${amount} AUD equivalent\nNetwork: {network}\nReference: Application #{application_id}\n\nAfter completing your transaction, please return to submit the fee sent form.\n\nBest regards,\nLoanFlow Team', 
'{"amount_sent": true, "date_sent": true, "transaction_reference": true}');

-- UK Templates
INSERT INTO `fee_form_templates` (`country`, `payment_method`, `template_name`, `instructions`, `email_template`, `required_fields`) VALUES
('GB', 'wire_transfer', 'UK Wire Transfer Form', 
'Please complete your wire transfer using the bank details provided. After sending the transfer, return to this form to submit your payment confirmation.', 
'Dear {user_name},\n\nPlease complete your wire transfer using:\n\nBank Name: {bank_name}\nSort Code: {sort_code}\nAccount Number: {account_number}\nAmount: £{amount}\nReference: Application #{application_id}\n\nAfter completing your transfer, please return to submit the fee sent form.\n\nBest regards,\nLoanFlow Team', 
'{"amount_sent": true, "date_sent": true, "transaction_reference": false}'),

('GB', 'crypto', 'UK Cryptocurrency Form', 
'Please send your cryptocurrency payment to the wallet address provided. After completing the transaction, submit this form with your transaction hash.', 
'Dear {user_name},\n\nPlease send your cryptocurrency payment to:\n\nWallet Address: {wallet_address}\nAmount: £{amount} equivalent\nNetwork: {network}\nReference: Application #{application_id}\n\nAfter completing your transaction, please return to submit the fee sent form.\n\nBest regards,\nLoanFlow Team', 
'{"amount_sent": true, "date_sent": true, "transaction_reference": true}');

-- --------------------------------------------------------
-- Create indexes for performance optimization
-- --------------------------------------------------------

CREATE INDEX idx_fee_sent_forms_user_status ON fee_sent_forms(user_id, status);
CREATE INDEX idx_fee_sent_forms_application_status ON fee_sent_forms(application_id, status);
CREATE INDEX idx_fee_sent_forms_country_method ON fee_sent_forms(country, payment_method);
CREATE INDEX idx_fee_form_templates_country_active ON fee_form_templates(country, is_active);
CREATE INDEX idx_fee_form_notifications_form_status ON fee_form_notifications(fee_form_id, status);

-- --------------------------------------------------------
-- Create triggers for automatic notifications
-- --------------------------------------------------------

DELIMITER //

CREATE TRIGGER `fee_form_submitted_notification` 
AFTER INSERT ON `fee_sent_forms`
FOR EACH ROW
BEGIN
    DECLARE user_email VARCHAR(255);
    DECLARE user_name VARCHAR(255);
    
    -- Get user details
    SELECT email, CONCAT(first_name, ' ', last_name) 
    INTO user_email, user_name
    FROM users WHERE id = NEW.user_id;
    
    -- Insert notification for user confirmation
    INSERT INTO fee_form_notifications 
    (fee_form_id, notification_type, recipient_email, subject, message)
    VALUES (
        NEW.id, 
        'submitted', 
        user_email, 
        'Fee Form Submitted Successfully',
        CONCAT('Dear ', user_name, ',\n\nYour fee sent form has been submitted successfully. Our admin team will review and confirm receipt of your payment.\n\nApplication ID: ', NEW.application_id, '\nPayment Method: ', NEW.payment_method, '\nAmount: $', NEW.amount_sent, '\n\nYou will be notified once your payment is confirmed.\n\nBest regards,\nLoanFlow Team')
    );
END//

CREATE TRIGGER `fee_form_status_change_notification` 
AFTER UPDATE ON `fee_sent_forms`
FOR EACH ROW
BEGIN
    DECLARE user_email VARCHAR(255);
    DECLARE user_name VARCHAR(255);
    DECLARE notification_subject VARCHAR(255);
    DECLARE notification_message TEXT;
    
    -- Only trigger if status changed
    IF OLD.status != NEW.status THEN
        -- Get user details
        SELECT email, CONCAT(first_name, ' ', last_name) 
        INTO user_email, user_name
        FROM users WHERE id = NEW.user_id;
        
        -- Set notification content based on new status
        CASE NEW.status
            WHEN 'confirmed' THEN
                SET notification_subject = 'Fee Payment Confirmed';
                SET notification_message = CONCAT('Dear ', user_name, ',\n\nGreat news! Your fee payment has been confirmed.\n\nApplication ID: ', NEW.application_id, '\nAmount: $', NEW.amount_sent, '\n\nYour application will now proceed to the next step.\n\nBest regards,\nLoanFlow Team');
            WHEN 'rejected' THEN
                SET notification_subject = 'Fee Payment Requires Attention';
                SET notification_message = CONCAT('Dear ', user_name, ',\n\nWe need to discuss your fee payment submission.\n\nApplication ID: ', NEW.application_id, '\nReason: ', COALESCE(NEW.admin_notes, 'Please contact support for details'), '\n\nPlease contact our support team or resubmit your fee form.\n\nBest regards,\nLoanFlow Team');
            WHEN 'under_review' THEN
                SET notification_subject = 'Fee Payment Under Review';
                SET notification_message = CONCAT('Dear ', user_name, ',\n\nYour fee payment is currently under review.\n\nApplication ID: ', NEW.application_id, '\nAmount: $', NEW.amount_sent, '\n\nWe will notify you once the review is complete.\n\nBest regards,\nLoanFlow Team');
        END CASE;
        
        -- Insert notification
        IF notification_subject IS NOT NULL THEN
            INSERT INTO fee_form_notifications 
            (fee_form_id, notification_type, recipient_email, subject, message)
            VALUES (
                NEW.id, 
                NEW.status, 
                user_email, 
                notification_subject,
                notification_message
            );
        END IF;
    END IF;
END//

DELIMITER ;

-- --------------------------------------------------------
-- Insert sample data for testing (optional)
-- --------------------------------------------------------

-- Sample fee sent form (uncomment to use)
-- INSERT INTO `fee_sent_forms` (`user_id`, `application_id`, `payment_method`, `amount_sent`, `date_sent`, `transaction_reference`, `country`, `status`) 
-- SELECT u.id, la.id, 'wire_transfer', 200.00, CURDATE(), 'TXN123456789', u.country, 'pending'
-- FROM `users` u 
-- JOIN `loan_applications` la ON u.id = la.user_id 
-- WHERE u.role = 'client' 
-- LIMIT 1;

COMMIT;

-- --------------------------------------------------------
-- Verification queries (run these to verify tables were created)
-- --------------------------------------------------------

-- SHOW TABLES LIKE '%fee_%';
-- SELECT COUNT(*) as fee_sent_forms_count FROM fee_sent_forms;
-- SELECT COUNT(*) as fee_form_templates_count FROM fee_form_templates;
-- SELECT COUNT(*) as fee_form_notifications_count FROM fee_form_notifications;
-- SELECT * FROM fee_form_templates WHERE country = 'US';