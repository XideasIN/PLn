-- Funding Email Templates
-- LoanFlow Personal Loan Management System

-- Insert funding notification email templates
INSERT INTO email_templates (template_type, template_name, subject, content, country, is_active, created_at) VALUES

-- Funding Initiated Template
('funding_initiated', 'Funding Process Started', 
'Your Loan Funding Has Been Initiated - {reference_number}',
'<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Funding Initiated</title>
    <style>
        body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
        .container { max-width: 600px; margin: 0 auto; padding: 20px; }
        .header { background: #28a745; color: white; padding: 20px; text-align: center; }
        .content { padding: 20px; background: #f8f9fa; }
        .footer { padding: 20px; text-align: center; color: #666; }
        .button { display: inline-block; padding: 12px 24px; background: #007bff; color: white; text-decoration: none; border-radius: 5px; }
        .timeline { background: white; padding: 15px; margin: 15px 0; border-left: 4px solid #28a745; }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>🎉 Funding Process Started!</h1>
        </div>
        <div class="content">
            <h2>Dear {first_name},</h2>
            <p>Great news! We are pleased to inform you that the funding process for your loan application has been initiated.</p>
            
            <div class="timeline">
                <h3>📋 Application Details:</h3>
                <ul>
                    <li><strong>Reference Number:</strong> {reference_number}</li>
                    <li><strong>Application ID:</strong> {application_id}</li>
                    <li><strong>Status:</strong> Funding in Progress</li>
                </ul>
            </div>
            
            <h3>🔄 What happens next?</h3>
            <p>Our funding team is now processing your loan disbursement. You can expect to receive your funds within the next 24-48 hours during business days.</p>
            
            <p>We will send you another notification once the funds have been successfully transferred to your account.</p>
            
            <div style="text-align: center; margin: 30px 0;">
                <a href="{website_url}/client/funding.php" class="button">View Funding Status</a>
            </div>
            
            <p>If you have any questions, please don\'t hesitate to contact our support team.</p>
        </div>
        <div class="footer">
            <p>Best regards,<br>
            The {company_name} Team</p>
            <p>📧 {support_email} | 🌐 {website_url}</p>
        </div>
    </div>
</body>
</html>',
'default', 1, NOW()),

-- Funding Completed Template
('funding_completed', 'Loan Funds Disbursed Successfully', 
'🎉 Your Loan Has Been Funded - {reference_number}',
'<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Funding Completed</title>
    <style>
        body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
        .container { max-width: 600px; margin: 0 auto; padding: 20px; }
        .header { background: #28a745; color: white; padding: 20px; text-align: center; }
        .content { padding: 20px; background: #f8f9fa; }
        .footer { padding: 20px; text-align: center; color: #666; }
        .button { display: inline-block; padding: 12px 24px; background: #007bff; color: white; text-decoration: none; border-radius: 5px; }
        .success-box { background: #d4edda; border: 1px solid #c3e6cb; padding: 20px; border-radius: 5px; margin: 20px 0; }
        .amount-box { background: white; padding: 20px; text-align: center; border: 2px solid #28a745; border-radius: 10px; margin: 20px 0; }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>🎉 Congratulations!</h1>
            <h2>Your Loan Has Been Funded</h2>
        </div>
        <div class="content">
            <h2>Dear {first_name},</h2>
            
            <div class="success-box">
                <h3>✅ Funding Completed Successfully!</h3>
                <p>We are delighted to inform you that your loan funds have been successfully disbursed to your account.</p>
            </div>
            
            <div class="amount-box">
                <h3>💰 Funding Details</h3>
                <p><strong>Amount Funded:</strong> {funding_amount}</p>
                <p><strong>Reference Number:</strong> {reference_number}</p>
                <p><strong>Transaction Reference:</strong> {funding_reference}</p>
                <p><strong>Application ID:</strong> {application_id}</p>
            </div>
            
            <h3>📋 Important Information:</h3>
            <ul>
                <li>Please check your bank account for the deposited funds</li>
                <li>Funds may take 1-2 business days to appear in your account</li>
                <li>Keep this email for your records</li>
                <li>Your first payment will be due as per your loan agreement</li>
            </ul>
            
            <div style="text-align: center; margin: 30px 0;">
                <a href="{website_url}/client/funding.php" class="button">View Funding Details</a>
            </div>
            
            <p>Thank you for choosing {company_name}. We appreciate your business and look forward to serving you again in the future.</p>
        </div>
        <div class="footer">
            <p>Best regards,<br>
            The {company_name} Team</p>
            <p>📧 {support_email} | 🌐 {website_url}</p>
        </div>
    </div>
</body>
</html>',
'default', 1, NOW()),

-- Funding Cancelled Template
('funding_cancelled', 'Funding Process Cancelled', 
'Funding Process Update - {reference_number}',
'<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Funding Cancelled</title>
    <style>
        body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
        .container { max-width: 600px; margin: 0 auto; padding: 20px; }
        .header { background: #dc3545; color: white; padding: 20px; text-align: center; }
        .content { padding: 20px; background: #f8f9fa; }
        .footer { padding: 20px; text-align: center; color: #666; }
        .button { display: inline-block; padding: 12px 24px; background: #007bff; color: white; text-decoration: none; border-radius: 5px; }
        .warning-box { background: #f8d7da; border: 1px solid #f5c6cb; padding: 20px; border-radius: 5px; margin: 20px 0; }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>⚠️ Funding Process Update</h1>
        </div>
        <div class="content">
            <h2>Dear {first_name},</h2>
            
            <div class="warning-box">
                <h3>Funding Process Cancelled</h3>
                <p>We regret to inform you that the funding process for your loan application has been cancelled.</p>
            </div>
            
            <h3>📋 Application Details:</h3>
            <ul>
                <li><strong>Reference Number:</strong> {reference_number}</li>
                <li><strong>Application ID:</strong> {application_id}</li>
                <li><strong>Reason:</strong> {cancel_reason}</li>
            </ul>
            
            <h3>🔄 What happens next?</h3>
            <p>Your application status has been reverted to "Approved" and you may be eligible for funding again once any issues are resolved.</p>
            
            <p>Our team will contact you shortly to discuss the next steps and how we can assist you in completing your loan process.</p>
            
            <div style="text-align: center; margin: 30px 0;">
                <a href="{website_url}/client/dashboard.php" class="button">View Application Status</a>
            </div>
            
            <p>If you have any questions or concerns, please contact our support team immediately.</p>
        </div>
        <div class="footer">
            <p>Best regards,<br>
            The {company_name} Team</p>
            <p>📧 {support_email} | 🌐 {website_url}</p>
        </div>
    </div>
</body>
</html>',
'default', 1, NOW()),

-- Funding Delay Notification Template
('funding_delayed', 'Funding Process Delayed', 
'Funding Update - Slight Delay - {reference_number}',
'<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Funding Delayed</title>
    <style>
        body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
        .container { max-width: 600px; margin: 0 auto; padding: 20px; }
        .header { background: #ffc107; color: #212529; padding: 20px; text-align: center; }
        .content { padding: 20px; background: #f8f9fa; }
        .footer { padding: 20px; text-align: center; color: #666; }
        .button { display: inline-block; padding: 12px 24px; background: #007bff; color: white; text-decoration: none; border-radius: 5px; }
        .info-box { background: #fff3cd; border: 1px solid #ffeaa7; padding: 20px; border-radius: 5px; margin: 20px 0; }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>⏰ Funding Update</h1>
        </div>
        <div class="content">
            <h2>Dear {first_name},</h2>
            
            <div class="info-box">
                <h3>Slight Delay in Funding Process</h3>
                <p>We want to keep you informed about a slight delay in processing your loan funding.</p>
            </div>
            
            <h3>📋 Application Details:</h3>
            <ul>
                <li><strong>Reference Number:</strong> {reference_number}</li>
                <li><strong>Application ID:</strong> {application_id}</li>
                <li><strong>Current Status:</strong> Funding in Progress</li>
            </ul>
            
            <h3>🔄 What\'s happening?</h3>
            <p>Due to additional verification requirements or high processing volume, there may be a slight delay in disbursing your funds. We expect to complete the process within the next 1-2 business days.</p>
            
            <p>Rest assured, your application is being processed and you will receive your funds as soon as possible.</p>
            
            <div style="text-align: center; margin: 30px 0;">
                <a href="{website_url}/client/funding.php" class="button">Check Status</a>
            </div>
            
            <p>We apologize for any inconvenience and appreciate your patience. If you have any urgent questions, please contact our support team.</p>
        </div>
        <div class="footer">
            <p>Best regards,<br>
            The {company_name} Team</p>
            <p>📧 {support_email} | 🌐 {website_url}</p>
        </div>
    </div>
</body>
</html>',
'default', 1, NOW());

-- Update existing templates if they exist
INSERT INTO email_templates (template_type, template_name, subject, content, country, is_active, created_at) VALUES
('funding_initiated', 'Funding Process Started', 
'Your Loan Funding Has Been Initiated - {reference_number}',
'Dear {first_name},\n\nGreat news! We are pleased to inform you that the funding process for your loan application has been initiated.\n\nApplication Details:\n- Reference Number: {reference_number}\n- Application ID: {application_id}\n- Status: Funding in Progress\n\nWhat happens next?\nOur funding team is now processing your loan disbursement. You can expect to receive your funds within the next 24-48 hours during business days.\n\nWe will send you another notification once the funds have been successfully transferred to your account.\n\nIf you have any questions, please don\'t hesitate to contact our support team.\n\nBest regards,\nThe {company_name} Team\n{support_email}',
'default', 1, NOW())
ON DUPLICATE KEY UPDATE
subject = VALUES(subject),
content = VALUES(content),
updated_at = NOW();

INSERT INTO email_templates (template_type, template_name, subject, content, country, is_active, created_at) VALUES
('funding_completed', 'Loan Funds Disbursed Successfully', 
'🎉 Your Loan Has Been Funded - {reference_number}',
'Dear {first_name},\n\nCongratulations! We are delighted to inform you that your loan funds have been successfully disbursed to your account.\n\nFunding Details:\n- Amount Funded: {funding_amount}\n- Reference Number: {reference_number}\n- Transaction Reference: {funding_reference}\n- Application ID: {application_id}\n\nImportant Information:\n- Please check your bank account for the deposited funds\n- Funds may take 1-2 business days to appear in your account\n- Keep this email for your records\n- Your first payment will be due as per your loan agreement\n\nThank you for choosing {company_name}. We appreciate your business and look forward to serving you again in the future.\n\nBest regards,\nThe {company_name} Team\n{support_email}',
'default', 1, NOW())
ON DUPLICATE KEY UPDATE
subject = VALUES(subject),
content = VALUES(content),
updated_at = NOW();

INSERT INTO email_templates (template_type, template_name, subject, content, country, is_active, created_at) VALUES
('funding_cancelled', 'Funding Process Cancelled', 
'Funding Process Update - {reference_number}',
'Dear {first_name},\n\nWe regret to inform you that the funding process for your loan application has been cancelled.\n\nApplication Details:\n- Reference Number: {reference_number}\n- Application ID: {application_id}\n- Reason: {cancel_reason}\n\nWhat happens next?\nYour application status has been reverted to "Approved" and you may be eligible for funding again once any issues are resolved.\n\nOur team will contact you shortly to discuss the next steps and how we can assist you in completing your loan process.\n\nIf you have any questions or concerns, please contact our support team immediately.\n\nBest regards,\nThe {company_name} Team\n{support_email}',
'default', 1, NOW())
ON DUPLICATE KEY UPDATE
subject = VALUES(subject),
content = VALUES(content),
updated_at = NOW();

-- Add funding-related system settings
INSERT INTO system_settings (setting_key, setting_value, setting_type, description, category, is_editable, created_at) VALUES
('funding_enabled', '1', 'boolean', 'Enable funding system functionality', 'funding', 1, NOW()),
('auto_funding_enabled', '0', 'boolean', 'Enable automatic funding for approved applications', 'funding', 1, NOW()),
('funding_processing_time_hours', '24', 'integer', 'Expected funding processing time in hours', 'funding', 1, NOW()),
('max_daily_funding_amount', '1000000', 'decimal', 'Maximum daily funding amount limit', 'funding', 1, NOW()),
('require_bank_verification', '1', 'boolean', 'Require bank account verification before funding', 'funding', 1, NOW()),
('funding_business_days_only', '1', 'boolean', 'Only allow funding on business days', 'funding', 1, NOW()),
('funding_notification_enabled', '1', 'boolean', 'Send email notifications for funding events', 'funding', 1, NOW())
ON DUPLICATE KEY UPDATE
setting_value = VALUES(setting_value),
updated_at = NOW();

-- Create funding workflow status options
INSERT INTO system_settings (setting_key, setting_value, setting_type, description, category, is_editable, created_at) VALUES
('funding_workflow_statuses', 'pending,in_progress,completed,cancelled,on_hold', 'text', 'Available funding workflow statuses', 'funding', 1, NOW())
ON DUPLICATE KEY UPDATE
setting_value = VALUES(setting_value),
updated_at = NOW();