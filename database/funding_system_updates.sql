-- Funding System Database Updates
-- LoanFlow Personal Loan Management System

-- Add funding-related columns to loan_applications table
ALTER TABLE loan_applications 
ADD COLUMN IF NOT EXISTS funding_initiated_at TIMESTAMP NULL DEFAULT NULL COMMENT 'When funding process was initiated',
ADD COLUMN IF NOT EXISTS funding_initiated_by INT NULL DEFAULT NULL COMMENT 'Admin who initiated funding',
ADD COLUMN IF NOT EXISTS funding_amount DECIMAL(15,2) NULL DEFAULT NULL COMMENT 'Actual amount funded',
ADD COLUMN IF NOT EXISTS funding_reference VARCHAR(255) NULL DEFAULT NULL COMMENT 'Bank/payment reference for funding',
ADD COLUMN IF NOT EXISTS funding_notes TEXT NULL DEFAULT NULL COMMENT 'Notes about the funding process',
ADD COLUMN IF NOT EXISTS funded_by INT NULL DEFAULT NULL COMMENT 'Admin who completed funding',
ADD COLUMN IF NOT EXISTS funding_cancel_reason TEXT NULL DEFAULT NULL COMMENT 'Reason for funding cancellation',
ADD COLUMN IF NOT EXISTS funding_cancelled_by INT NULL DEFAULT NULL COMMENT 'Admin who cancelled funding',
ADD COLUMN IF NOT EXISTS funding_cancelled_at TIMESTAMP NULL DEFAULT NULL COMMENT 'When funding was cancelled',
ADD COLUMN IF NOT EXISTS estimated_funding_date DATE NULL DEFAULT NULL COMMENT 'Estimated funding completion date',
ADD COLUMN IF NOT EXISTS funding_method VARCHAR(50) DEFAULT 'bank_transfer' COMMENT 'Method of funding (bank_transfer, check, etc)',
ADD COLUMN IF NOT EXISTS funding_status VARCHAR(50) DEFAULT 'pending' COMMENT 'Detailed funding status';

-- Add foreign key constraints for funding-related admin users
ALTER TABLE loan_applications 
ADD CONSTRAINT fk_funding_initiated_by FOREIGN KEY (funding_initiated_by) REFERENCES users(id) ON DELETE SET NULL,
ADD CONSTRAINT fk_funded_by FOREIGN KEY (funded_by) REFERENCES users(id) ON DELETE SET NULL,
ADD CONSTRAINT fk_funding_cancelled_by FOREIGN KEY (funding_cancelled_by) REFERENCES users(id) ON DELETE SET NULL;

-- Create funding_timeline table for detailed tracking
CREATE TABLE IF NOT EXISTS funding_timeline (
    id INT AUTO_INCREMENT PRIMARY KEY,
    application_id INT NOT NULL,
    event_type VARCHAR(50) NOT NULL COMMENT 'Type of funding event',
    event_title VARCHAR(255) NOT NULL COMMENT 'Human-readable event title',
    event_description TEXT NULL COMMENT 'Detailed description of the event',
    event_data JSON NULL COMMENT 'Additional event data in JSON format',
    created_by INT NULL COMMENT 'User who triggered this event',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    
    FOREIGN KEY (application_id) REFERENCES loan_applications(id) ON DELETE CASCADE,
    FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL,
    
    INDEX idx_application_timeline (application_id, created_at),
    INDEX idx_event_type (event_type),
    INDEX idx_created_at (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Create funding_documents table for funding-specific documents
CREATE TABLE IF NOT EXISTS funding_documents (
    id INT AUTO_INCREMENT PRIMARY KEY,
    application_id INT NOT NULL,
    document_type VARCHAR(100) NOT NULL COMMENT 'Type of funding document',
    document_name VARCHAR(255) NOT NULL COMMENT 'Name/title of the document',
    file_path VARCHAR(500) NOT NULL COMMENT 'Path to the document file',
    file_size INT NOT NULL DEFAULT 0 COMMENT 'File size in bytes',
    mime_type VARCHAR(100) NULL COMMENT 'MIME type of the file',
    upload_status VARCHAR(50) DEFAULT 'uploaded' COMMENT 'Status of the document',
    verification_status VARCHAR(50) DEFAULT 'pending' COMMENT 'Verification status',
    verification_notes TEXT NULL COMMENT 'Notes from document verification',
    verified_by INT NULL COMMENT 'Admin who verified the document',
    verified_at TIMESTAMP NULL DEFAULT NULL,
    uploaded_by INT NOT NULL COMMENT 'User who uploaded the document',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    FOREIGN KEY (application_id) REFERENCES loan_applications(id) ON DELETE CASCADE,
    FOREIGN KEY (uploaded_by) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (verified_by) REFERENCES users(id) ON DELETE SET NULL,
    
    INDEX idx_application_docs (application_id),
    INDEX idx_document_type (document_type),
    INDEX idx_upload_status (upload_status),
    INDEX idx_verification_status (verification_status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Create funding_notifications table for funding-related notifications
CREATE TABLE IF NOT EXISTS funding_notifications (
    id INT AUTO_INCREMENT PRIMARY KEY,
    application_id INT NOT NULL,
    user_id INT NOT NULL COMMENT 'Recipient of the notification',
    notification_type VARCHAR(100) NOT NULL COMMENT 'Type of notification',
    title VARCHAR(255) NOT NULL COMMENT 'Notification title',
    message TEXT NOT NULL COMMENT 'Notification message',
    notification_data JSON NULL COMMENT 'Additional notification data',
    delivery_method VARCHAR(50) DEFAULT 'email' COMMENT 'How notification was/will be delivered',
    delivery_status VARCHAR(50) DEFAULT 'pending' COMMENT 'Delivery status',
    delivered_at TIMESTAMP NULL DEFAULT NULL,
    read_at TIMESTAMP NULL DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    
    FOREIGN KEY (application_id) REFERENCES loan_applications(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    
    INDEX idx_user_notifications (user_id, created_at),
    INDEX idx_application_notifications (application_id),
    INDEX idx_notification_type (notification_type),
    INDEX idx_delivery_status (delivery_status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Create funding_settings table for system configuration
CREATE TABLE IF NOT EXISTS funding_settings (
    id INT AUTO_INCREMENT PRIMARY KEY,
    setting_key VARCHAR(100) NOT NULL UNIQUE COMMENT 'Setting identifier',
    setting_value TEXT NOT NULL COMMENT 'Setting value',
    setting_type VARCHAR(50) DEFAULT 'string' COMMENT 'Data type of the setting',
    description TEXT NULL COMMENT 'Description of the setting',
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    INDEX idx_setting_key (setting_key),
    INDEX idx_is_active (is_active)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Insert default funding settings
INSERT INTO funding_settings (setting_key, setting_value, setting_type, description) VALUES
('auto_funding_enabled', 'false', 'boolean', 'Enable automatic funding for approved applications'),
('funding_business_days_only', 'true', 'boolean', 'Only process funding on business days'),
('max_daily_funding_amount', '1000000', 'decimal', 'Maximum total amount that can be funded per day'),
('funding_approval_required', 'true', 'boolean', 'Require additional approval for funding'),
('funding_notification_enabled', 'true', 'boolean', 'Send notifications for funding events'),
('funding_sms_enabled', 'false', 'boolean', 'Send SMS notifications for funding events'),
('funding_email_enabled', 'true', 'boolean', 'Send email notifications for funding events'),
('default_funding_method', 'bank_transfer', 'string', 'Default method for funding'),
('funding_processing_time_hours', '24', 'integer', 'Expected processing time for funding in hours'),
('require_bank_verification', 'true', 'boolean', 'Require bank account verification before funding')
ON DUPLICATE KEY UPDATE 
    setting_value = VALUES(setting_value),
    updated_at = CURRENT_TIMESTAMP;

-- Create funding_audit_log table for audit trail
CREATE TABLE IF NOT EXISTS funding_audit_log (
    id INT AUTO_INCREMENT PRIMARY KEY,
    application_id INT NOT NULL,
    action VARCHAR(100) NOT NULL COMMENT 'Action performed',
    old_values JSON NULL COMMENT 'Previous values before change',
    new_values JSON NULL COMMENT 'New values after change',
    performed_by INT NOT NULL COMMENT 'User who performed the action',
    ip_address VARCHAR(45) NULL COMMENT 'IP address of the user',
    user_agent TEXT NULL COMMENT 'User agent string',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    
    FOREIGN KEY (application_id) REFERENCES loan_applications(id) ON DELETE CASCADE,
    FOREIGN KEY (performed_by) REFERENCES users(id) ON DELETE CASCADE,
    
    INDEX idx_application_audit (application_id, created_at),
    INDEX idx_action (action),
    INDEX idx_performed_by (performed_by),
    INDEX idx_created_at (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Add indexes for better performance on loan_applications
CREATE INDEX IF NOT EXISTS idx_funding_status ON loan_applications(application_status, funding_initiated_at);
CREATE INDEX IF NOT EXISTS idx_funding_date ON loan_applications(funding_date);
CREATE INDEX IF NOT EXISTS idx_funding_initiated ON loan_applications(funding_initiated_at);

-- Update existing application statuses to include funding-related statuses
-- This is safe to run multiple times
UPDATE loan_applications 
SET funding_status = CASE 
    WHEN application_status = 'approved' THEN 'ready_for_funding'
    WHEN application_status = 'funding' THEN 'in_progress'
    WHEN application_status = 'funded' THEN 'completed'
    ELSE 'not_applicable'
END
WHERE funding_status = 'pending';

-- Create view for funding dashboard statistics
CREATE OR REPLACE VIEW funding_dashboard_stats AS
SELECT 
    COUNT(CASE WHEN application_status = 'approved' THEN 1 END) as ready_for_funding,
    COUNT(CASE WHEN application_status = 'funding' THEN 1 END) as funding_in_progress,
    COUNT(CASE WHEN application_status = 'funded' THEN 1 END) as funding_completed,
    SUM(CASE WHEN application_status = 'funded' THEN loan_amount ELSE 0 END) as total_funded_amount,
    SUM(CASE WHEN application_status = 'funded' AND funding_date >= CURDATE() THEN loan_amount ELSE 0 END) as today_funded_amount,
    AVG(CASE WHEN application_status = 'funded' AND funding_date IS NOT NULL AND approval_date IS NOT NULL 
             THEN TIMESTAMPDIFF(HOUR, approval_date, funding_date) END) as avg_funding_time_hours,
    COUNT(CASE WHEN funding_initiated_at >= CURDATE() THEN 1 END) as today_initiated_count,
    COUNT(CASE WHEN funding_date >= CURDATE() THEN 1 END) as today_completed_count
FROM loan_applications
WHERE application_status IN ('approved', 'funding', 'funded');

-- Create view for funding timeline with user details
CREATE OR REPLACE VIEW funding_timeline_view AS
SELECT 
    ft.*,
    la.user_id,
    la.loan_amount,
    u.first_name as client_first_name,
    u.last_name as client_last_name,
    u.reference_number,
    admin.first_name as admin_first_name,
    admin.last_name as admin_last_name
FROM funding_timeline ft
JOIN loan_applications la ON ft.application_id = la.id
JOIN users u ON la.user_id = u.id
LEFT JOIN users admin ON ft.created_by = admin.id;

-- Insert sample funding timeline events for existing applications
-- This helps populate the timeline for applications that were processed before this system
INSERT IGNORE INTO funding_timeline (application_id, event_type, event_title, event_description, created_at)
SELECT 
    id,
    'application_submitted',
    'Application Submitted',
    'Loan application was submitted and is under review',
    created_at
FROM loan_applications
WHERE created_at IS NOT NULL;

INSERT IGNORE INTO funding_timeline (application_id, event_type, event_title, event_description, created_at)
SELECT 
    id,
    'application_approved',
    'Application Approved',
    'Loan application has been approved and is ready for funding',
    approval_date
FROM loan_applications
WHERE approval_date IS NOT NULL;

INSERT IGNORE INTO funding_timeline (application_id, event_type, event_title, event_description, created_at)
SELECT 
    id,
    'funding_completed',
    'Funding Completed',
    'Loan funds have been successfully disbursed',
    funding_date
FROM loan_applications
WHERE funding_date IS NOT NULL;

-- Create stored procedure for funding workflow automation
DELIMITER //

CREATE PROCEDURE IF NOT EXISTS ProcessFundingWorkflow()
BEGIN
    DECLARE done INT DEFAULT FALSE;
    DECLARE app_id INT;
    DECLARE funding_cursor CURSOR FOR 
        SELECT id FROM loan_applications 
        WHERE application_status = 'approved' 
        AND funding_initiated_at IS NULL
        AND approval_date <= DATE_SUB(NOW(), INTERVAL 1 DAY)
        LIMIT 10;
    
    DECLARE CONTINUE HANDLER FOR NOT FOUND SET done = TRUE;
    
    -- Auto-initiate funding for approved applications older than 1 day
    OPEN funding_cursor;
    
    funding_loop: LOOP
        FETCH funding_cursor INTO app_id;
        IF done THEN
            LEAVE funding_loop;
        END IF;
        
        -- Check if auto-funding is enabled
        IF (SELECT setting_value FROM funding_settings WHERE setting_key = 'auto_funding_enabled') = 'true' THEN
            UPDATE loan_applications 
            SET application_status = 'funding',
                funding_initiated_at = NOW(),
                funding_status = 'in_progress'
            WHERE id = app_id;
            
            -- Log the timeline event
            INSERT INTO funding_timeline (application_id, event_type, event_title, event_description)
            VALUES (app_id, 'funding_auto_initiated', 'Funding Auto-Initiated', 'Funding process was automatically initiated by the system');
        END IF;
    END LOOP;
    
    CLOSE funding_cursor;
END//

DELIMITER ;

-- Grant necessary permissions (adjust as needed for your setup)
-- GRANT SELECT, INSERT, UPDATE, DELETE ON funding_timeline TO 'loan_app_user'@'localhost';
-- GRANT SELECT, INSERT, UPDATE, DELETE ON funding_documents TO 'loan_app_user'@'localhost';
-- GRANT SELECT, INSERT, UPDATE, DELETE ON funding_notifications TO 'loan_app_user'@'localhost';
-- GRANT SELECT, INSERT, UPDATE ON funding_settings TO 'loan_app_user'@'localhost';
-- GRANT SELECT, INSERT ON funding_audit_log TO 'loan_app_user'@'localhost';

-- Create triggers for automatic audit logging
DELIMITER //

CREATE TRIGGER IF NOT EXISTS funding_audit_insert
AFTER UPDATE ON loan_applications
FOR EACH ROW
BEGIN
    IF OLD.application_status != NEW.application_status 
       OR OLD.funding_initiated_at != NEW.funding_initiated_at 
       OR OLD.funding_date != NEW.funding_date THEN
        
        INSERT INTO funding_audit_log (application_id, action, old_values, new_values, performed_by)
        VALUES (
            NEW.id,
            'funding_status_change',
            JSON_OBJECT(
                'application_status', OLD.application_status,
                'funding_initiated_at', OLD.funding_initiated_at,
                'funding_date', OLD.funding_date,
                'funding_amount', OLD.funding_amount
            ),
            JSON_OBJECT(
                'application_status', NEW.application_status,
                'funding_initiated_at', NEW.funding_initiated_at,
                'funding_date', NEW.funding_date,
                'funding_amount', NEW.funding_amount
            ),
            COALESCE(NEW.funded_by, NEW.funding_initiated_by, 1)
        );
    END IF;
END//

DELIMITER ;

-- Add comments to tables for documentation
ALTER TABLE funding_timeline COMMENT = 'Tracks detailed timeline of funding events for each application';
ALTER TABLE funding_documents COMMENT = 'Stores funding-specific documents like disbursement receipts';
ALTER TABLE funding_notifications COMMENT = 'Manages notifications sent during funding process';
ALTER TABLE funding_settings COMMENT = 'System-wide settings for funding operations';
ALTER TABLE funding_audit_log COMMENT = 'Audit trail for all funding-related changes';

-- Final verification query to check if all tables were created successfully
SELECT 
    TABLE_NAME,
    TABLE_COMMENT,
    CREATE_TIME
FROM information_schema.TABLES 
WHERE TABLE_SCHEMA = DATABASE() 
AND TABLE_NAME LIKE 'funding_%'
ORDER BY TABLE_NAME;