-- Automated Email System Database Schema
-- LoanFlow Personal Loan Management System
-- This schema supports automated email workflows, step-based triggers, and time-based reminders

-- Email workflow templates table
CREATE TABLE IF NOT EXISTS email_workflow_templates (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    description TEXT,
    trigger_type ENUM('step_before', 'step_after', 'time_based', 'bulk', 'agent_manual', 'step4_completion') NOT NULL,
    trigger_step INT NULL, -- Which step triggers this email (1-4)
    trigger_delay_hours INT DEFAULT 0, -- Hours after trigger event
    subject VARCHAR(500) NOT NULL,
    body_html LONGTEXT NOT NULL,
    body_text LONGTEXT,
    is_active BOOLEAN DEFAULT TRUE,
    send_to_all BOOLEAN DEFAULT FALSE, -- For bulk emails
    created_by INT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL,
    INDEX idx_trigger_type (trigger_type),
    INDEX idx_trigger_step (trigger_step),
    INDEX idx_active (is_active)
);

-- Email workflow queue for scheduled emails
CREATE TABLE IF NOT EXISTS email_workflow_queue (
    id INT AUTO_INCREMENT PRIMARY KEY,
    template_id INT NOT NULL,
    user_id INT NOT NULL,
    application_id INT,
    scheduled_at TIMESTAMP NOT NULL,
    sent_at TIMESTAMP NULL,
    status ENUM('pending', 'sent', 'failed', 'cancelled') DEFAULT 'pending',
    error_message TEXT NULL,
    personalized_subject VARCHAR(500),
    personalized_body LONGTEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (template_id) REFERENCES email_workflow_templates(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (application_id) REFERENCES loan_applications(id) ON DELETE SET NULL,
    INDEX idx_scheduled (scheduled_at),
    INDEX idx_status (status),
    INDEX idx_user (user_id),
    INDEX idx_template (template_id)
);

-- Email workflow triggers tracking
CREATE TABLE IF NOT EXISTS email_workflow_triggers (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    application_id INT,
    trigger_type VARCHAR(50) NOT NULL,
    trigger_step INT NULL,
    triggered_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    processed BOOLEAN DEFAULT FALSE,
    processed_at TIMESTAMP NULL,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (application_id) REFERENCES loan_applications(id) ON DELETE SET NULL,
    INDEX idx_user_trigger (user_id, trigger_type),
    INDEX idx_processed (processed),
    INDEX idx_triggered_at (triggered_at)
);

-- Email personalization variables
CREATE TABLE IF NOT EXISTS email_personalization_vars (
    id INT AUTO_INCREMENT PRIMARY KEY,
    var_name VARCHAR(100) NOT NULL UNIQUE,
    var_description TEXT,
    var_source ENUM('user', 'application', 'system', 'custom') NOT NULL,
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Bulk email campaigns
CREATE TABLE IF NOT EXISTS bulk_email_campaigns (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    template_id INT NOT NULL,
    target_criteria JSON, -- Criteria for selecting recipients
    scheduled_at TIMESTAMP NULL,
    started_at TIMESTAMP NULL,
    completed_at TIMESTAMP NULL,
    status ENUM('draft', 'scheduled', 'sending', 'completed', 'cancelled') DEFAULT 'draft',
    total_recipients INT DEFAULT 0,
    sent_count INT DEFAULT 0,
    failed_count INT DEFAULT 0,
    created_by INT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (template_id) REFERENCES email_workflow_templates(id) ON DELETE CASCADE,
    FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL,
    INDEX idx_status (status),
    INDEX idx_scheduled (scheduled_at)
);

-- Email delivery log
CREATE TABLE IF NOT EXISTS email_delivery_log (
    id INT AUTO_INCREMENT PRIMARY KEY,
    queue_id INT NULL,
    campaign_id INT NULL,
    user_id INT NOT NULL,
    template_id INT,
    email_address VARCHAR(255) NOT NULL,
    subject VARCHAR(500),
    delivery_status ENUM('sent', 'delivered', 'bounced', 'failed', 'opened', 'clicked') NOT NULL,
    delivery_time TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    error_message TEXT NULL,
    tracking_data JSON NULL,
    FOREIGN KEY (queue_id) REFERENCES email_workflow_queue(id) ON DELETE SET NULL,
    FOREIGN KEY (campaign_id) REFERENCES bulk_email_campaigns(id) ON DELETE SET NULL,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (template_id) REFERENCES email_workflow_templates(id) ON DELETE SET NULL,
    INDEX idx_user_email (user_id, email_address),
    INDEX idx_delivery_status (delivery_status),
    INDEX idx_delivery_time (delivery_time)
);

-- Agent email interactions
CREATE TABLE IF NOT EXISTS agent_email_interactions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    agent_id INT NOT NULL,
    user_id INT NOT NULL,
    template_id INT NULL,
    interaction_type ENUM('call_followup', 'manual_send', 'custom_message') NOT NULL,
    subject VARCHAR(500),
    message LONGTEXT,
    sent_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    notes TEXT,
    FOREIGN KEY (agent_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (template_id) REFERENCES email_workflow_templates(id) ON DELETE SET NULL,
    INDEX idx_agent (agent_id),
    INDEX idx_user (user_id),
    INDEX idx_sent_at (sent_at)
);

-- Insert default personalization variables
INSERT INTO email_personalization_vars (var_name, var_description, var_source) VALUES
('{{first_name}}', 'User first name', 'user'),
('{{last_name}}', 'User last name', 'user'),
('{{full_name}}', 'User full name', 'user'),
('{{email}}', 'User email address', 'user'),
('{{phone}}', 'User phone number', 'user'),
('{{application_id}}', 'Loan application ID', 'application'),
('{{loan_amount}}', 'Requested loan amount', 'application'),
('{{current_step}}', 'Current application step', 'application'),
('{{next_step}}', 'Next application step', 'application'),
('{{company_name}}', 'Company name', 'system'),
('{{company_phone}}', 'Company phone number', 'system'),
('{{company_email}}', 'Company email address', 'system'),
('{{current_date}}', 'Current date', 'system'),
('{{login_url}}', 'Client area login URL', 'system'),
('{{support_url}}', 'Support page URL', 'system')
ON DUPLICATE KEY UPDATE var_description = VALUES(var_description);

-- Insert default email workflow templates
INSERT INTO email_workflow_templates (name, description, trigger_type, trigger_step, trigger_delay_hours, subject, body_html, body_text, is_active) VALUES

-- Step 1 Before
('Step 1 Reminder - Application Start', 'Reminder email sent before step 1 to encourage application completion', 'step_before', 1, 0, 
'Complete Your Loan Application - {{company_name}}',
'<html><body><h2>Hello {{first_name}},</h2><p>We noticed you started your loan application but haven\'t completed it yet.</p><p>Complete your application today to get pre-approved for up to ${{loan_amount}}.</p><p><a href="{{login_url}}" style="background-color: #007bff; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px;">Continue Application</a></p><p>Need help? Contact us at {{company_phone}} or {{company_email}}.</p><p>Best regards,<br>{{company_name}} Team</p></body></html>',
'Hello {{first_name}}, We noticed you started your loan application but haven\'t completed it yet. Complete your application today to get pre-approved for up to ${{loan_amount}}. Visit {{login_url}} to continue. Need help? Contact us at {{company_phone}} or {{company_email}}. Best regards, {{company_name}} Team',
TRUE),

-- Step 1 After
('Step 1 Completion Confirmation', 'Confirmation email sent after completing step 1', 'step_after', 1, 0,
'Application Received - Next Steps | {{company_name}}',
'<html><body><h2>Thank you {{first_name}}!</h2><p>We\'ve successfully received your loan application (#{{application_id}}).</p><p><strong>What happens next:</strong></p><ul><li>Our team will review your application within 24 hours</li><li>You\'ll receive an email with your pre-approval decision</li><li>If approved, we\'ll guide you through the next steps</li></ul><p><a href="{{login_url}}" style="background-color: #28a745; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px;">Check Application Status</a></p><p>Questions? We\'re here to help at {{company_phone}}.</p><p>Best regards,<br>{{company_name}} Team</p></body></html>',
'Thank you {{first_name}}! We\'ve successfully received your loan application (#{{application_id}}). Our team will review your application within 24 hours and you\'ll receive an email with your pre-approval decision. Visit {{login_url}} to check your status. Questions? Contact us at {{company_phone}}. Best regards, {{company_name}} Team',
TRUE),

-- Step 2 Before
('Step 2 Reminder - Document Upload', 'Reminder to complete document upload', 'step_before', 2, 24,
'Action Required: Upload Your Documents | {{company_name}}',
'<html><body><h2>Hi {{first_name}},</h2><p>Great news! You\'ve been pre-approved for your loan.</p><p><strong>Next step:</strong> Upload your required documents to move forward with your application.</p><p>Required documents:</p><ul><li>Government-issued ID</li><li>Proof of income</li><li>Bank statements</li></ul><p><a href="{{login_url}}" style="background-color: #007bff; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px;">Upload Documents Now</a></p><p>Need assistance? Call us at {{company_phone}}.</p><p>Best regards,<br>{{company_name}} Team</p></body></html>',
'Hi {{first_name}}, Great news! You\'ve been pre-approved for your loan. Next step: Upload your required documents (Government-issued ID, Proof of income, Bank statements). Visit {{login_url}} to upload now. Need assistance? Call us at {{company_phone}}. Best regards, {{company_name}} Team',
TRUE),

-- Step 3 Before
('Step 3 Reminder - Banking Information', 'Reminder to provide banking information', 'step_before', 3, 12,
'Almost Done: Add Your Banking Details | {{company_name}}',
'<html><body><h2>Hello {{first_name}},</h2><p>You\'re almost there! We just need your banking information to complete your loan setup.</p><p>This step takes less than 2 minutes and ensures we can deposit your funds quickly once approved.</p><p><a href="{{login_url}}" style="background-color: #28a745; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px;">Add Banking Info</a></p><p>Your information is secure and encrypted.</p><p>Questions? Contact us at {{company_phone}}.</p><p>Best regards,<br>{{company_name}} Team</p></body></html>',
'Hello {{first_name}}, You\'re almost there! We just need your banking information to complete your loan setup. This takes less than 2 minutes. Visit {{login_url}} to add your banking info. Your information is secure and encrypted. Questions? Contact us at {{company_phone}}. Best regards, {{company_name}} Team',
TRUE),

-- Step 4 After (Time-based)
('Step 4 Completion - Final Review', 'Email sent after step 4 completion with time delay', 'step4_completion', 4, 2,
'Final Review in Progress - {{company_name}}',
'<html><body><h2>Congratulations {{first_name}}!</h2><p>You\'ve successfully completed all application steps. Your loan application is now in final review.</p><p><strong>Application Summary:</strong></p><ul><li>Application ID: #{{application_id}}</li><li>Loan Amount: ${{loan_amount}}</li><li>Status: Final Review</li></ul><p>Our underwriting team is conducting the final review of your application. You\'ll receive a decision within 24-48 hours.</p><p><a href="{{login_url}}" style="background-color: #17a2b8; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px;">View Application</a></p><p>Thank you for choosing {{company_name}}!</p><p>Best regards,<br>The {{company_name}} Team</p></body></html>',
'Congratulations {{first_name}}! You\'ve completed all application steps. Your loan application (#{{application_id}}) for ${{loan_amount}} is now in final review. You\'ll receive a decision within 24-48 hours. Visit {{login_url}} to view your application. Thank you for choosing {{company_name}}!',
TRUE),

-- Agent Follow-up Template
('Agent Follow-up Template', 'Template for agents to send follow-up emails after calls', 'agent_manual', NULL, 0,
'Follow-up from Our Conversation - {{company_name}}',
'<html><body><h2>Hi {{first_name}},</h2><p>Thank you for taking the time to speak with me today about your loan application.</p><p>As discussed, here are the next steps:</p><p>[AGENT: Add specific next steps discussed during the call]</p><p>If you have any questions or need assistance, please don\'t hesitate to reach out to me directly.</p><p><a href="{{login_url}}" style="background-color: #007bff; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px;">Access Your Account</a></p><p>Best regards,<br>[AGENT NAME]<br>{{company_name}}</p></body></html>',
'Hi {{first_name}}, Thank you for speaking with me today about your loan application. As discussed, here are the next steps: [AGENT: Add specific next steps]. If you have questions, please reach out. Visit {{login_url}} to access your account. Best regards, [AGENT NAME], {{company_name}}',
TRUE),

-- Bulk Email Template
('Monthly Newsletter Template', 'Template for monthly bulk emails to all users', 'bulk', NULL, 0,
'{{company_name}} Monthly Update - {{current_date}}',
'<html><body><h2>Hello {{first_name}},</h2><p>Here\'s your monthly update from {{company_name}}:</p><h3>This Month\'s Highlights:</h3><ul><li>New loan products available</li><li>Improved application process</li><li>Customer success stories</li></ul><p>We\'re committed to providing you with the best lending experience.</p><p><a href="{{login_url}}" style="background-color: #007bff; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px;">Visit Your Account</a></p><p>Thank you for being a valued customer!</p><p>Best regards,<br>{{company_name}} Team</p></body></html>',
'Hello {{first_name}}, Here\'s your monthly update from {{company_name}}: New loan products available, improved application process, and customer success stories. We\'re committed to providing you with the best lending experience. Visit {{login_url}} for more. Thank you for being a valued customer! Best regards, {{company_name}} Team',
TRUE);

-- Create indexes for better performance
CREATE INDEX idx_email_queue_scheduled_status ON email_workflow_queue(scheduled_at, status);
CREATE INDEX idx_email_triggers_user_processed ON email_workflow_triggers(user_id, processed, triggered_at);
CREATE INDEX idx_delivery_log_user_status ON email_delivery_log(user_id, delivery_status, delivery_time);

-- Add system settings for email automation
INSERT INTO system_settings (setting_key, setting_value, description, category) VALUES
('email_automation_enabled', '1', 'Enable automated email workflows', 'email'),
('email_queue_batch_size', '50', 'Number of emails to process in each batch', 'email'),
('email_retry_attempts', '3', 'Number of retry attempts for failed emails', 'email'),
('email_retry_delay_minutes', '30', 'Delay between retry attempts in minutes', 'email'),
('bulk_email_rate_limit', '100', 'Maximum bulk emails per hour', 'email'),
('step4_completion_delay_hours', '2', 'Hours to wait after step 4 completion before sending email', 'email')
ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value);

-- Create stored procedure for processing email queue
DELIMITER //
CREATE PROCEDURE ProcessEmailQueue()
BEGIN
    DECLARE done INT DEFAULT FALSE;
    DECLARE queue_id INT;
    DECLARE template_id INT;
    DECLARE user_id INT;
    DECLARE scheduled_time TIMESTAMP;
    
    DECLARE email_cursor CURSOR FOR 
        SELECT eq.id, eq.template_id, eq.user_id, eq.scheduled_at
        FROM email_workflow_queue eq
        WHERE eq.status = 'pending' 
        AND eq.scheduled_at <= NOW()
        ORDER BY eq.scheduled_at ASC
        LIMIT 50;
    
    DECLARE CONTINUE HANDLER FOR NOT FOUND SET done = TRUE;
    
    OPEN email_cursor;
    
    email_loop: LOOP
        FETCH email_cursor INTO queue_id, template_id, user_id, scheduled_time;
        IF done THEN
            LEAVE email_loop;
        END IF;
        
        -- Update status to processing
        UPDATE email_workflow_queue 
        SET status = 'sent', sent_at = NOW() 
        WHERE id = queue_id;
        
    END LOOP;
    
    CLOSE email_cursor;
END//
DELIMITER ;

-- Create trigger to automatically schedule emails based on application steps
DELIMITER //
CREATE TRIGGER after_application_step_update
AFTER UPDATE ON loan_applications
FOR EACH ROW
BEGIN
    -- Check if step has changed
    IF OLD.current_step != NEW.current_step THEN
        -- Insert trigger record
        INSERT INTO email_workflow_triggers (user_id, application_id, trigger_type, trigger_step)
        VALUES (NEW.user_id, NEW.id, 'step_after', OLD.current_step);
        
        -- Schedule step_before emails for next step
        INSERT INTO email_workflow_triggers (user_id, application_id, trigger_type, trigger_step)
        VALUES (NEW.user_id, NEW.id, 'step_before', NEW.current_step);
        
        -- Special handling for step 4 completion
        IF NEW.current_step = 4 AND NEW.status = 'completed' THEN
            INSERT INTO email_workflow_triggers (user_id, application_id, trigger_type, trigger_step)
            VALUES (NEW.user_id, NEW.id, 'step4_completion', 4);
        END IF;
    END IF;
END//
DELIMITER ;

-- Create view for email analytics
CREATE VIEW email_analytics AS
SELECT 
    DATE(edl.delivery_time) as date,
    ewt.name as template_name,
    ewt.trigger_type,
    COUNT(*) as total_sent,
    SUM(CASE WHEN edl.delivery_status = 'delivered' THEN 1 ELSE 0 END) as delivered,
    SUM(CASE WHEN edl.delivery_status = 'opened' THEN 1 ELSE 0 END) as opened,
    SUM(CASE WHEN edl.delivery_status = 'clicked' THEN 1 ELSE 0 END) as clicked,
    SUM(CASE WHEN edl.delivery_status = 'bounced' THEN 1 ELSE 0 END) as bounced,
    SUM(CASE WHEN edl.delivery_status = 'failed' THEN 1 ELSE 0 END) as failed
FROM email_delivery_log edl
JOIN email_workflow_templates ewt ON edl.template_id = ewt.id
GROUP BY DATE(edl.delivery_time), ewt.id
ORDER BY date DESC, template_name;

COMMIT;