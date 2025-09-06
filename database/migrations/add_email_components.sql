-- Migration: Add email components table for standardized headers/footers
-- Created: 2024-01-20

CREATE TABLE `email_components` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `component_type` enum('header','footer','acknowledgment') NOT NULL,
  `component_name` varchar(255) NOT NULL,
  `html_content` text NOT NULL,
  `is_active` boolean NOT NULL DEFAULT TRUE,
  `is_default` boolean NOT NULL DEFAULT FALSE,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `created_by` int(11) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_component_type` (`component_type`),
  KEY `idx_is_active` (`is_active`),
  KEY `idx_is_default` (`is_default`),
  UNIQUE KEY `unique_default_per_type` (`component_type`, `is_default`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Insert default email header
INSERT INTO `email_components` (`component_type`, `component_name`, `html_content`, `is_active`, `is_default`) VALUES
('header', 'Default Email Header', 
'<div style="background-color: #007bff; color: white; padding: 20px; text-align: center; font-family: Arial, sans-serif;">
    <h2 style="margin: 0; font-size: 24px;">{company_name}</h2>
    <p style="margin: 5px 0 0 0; font-size: 14px; opacity: 0.9;">{company_tagline}</p>
</div>', 
TRUE, TRUE),

-- Insert default email footer
('footer', 'Default Email Footer',
'<div style="background-color: #f8f9fa; padding: 20px; text-align: center; font-family: Arial, sans-serif; font-size: 12px; color: #666; border-top: 1px solid #dee2e6;">
    <p style="margin: 0 0 10px 0;"><strong>Contact Information:</strong></p>
    <p style="margin: 0 0 5px 0;">📧 Email: {company_email} | 📞 Phone: {company_phone}</p>
    <p style="margin: 0 0 5px 0;">🌐 Website: {company_website} | 📍 Address: {company_address}</p>
    <hr style="margin: 15px 0; border: none; border-top: 1px solid #dee2e6;">
    <p style="margin: 0 0 5px 0;">This is an automated message. Please do not reply directly to this email.</p>
    <p style="margin: 0;">&copy; {current_year} {company_name}. All rights reserved.</p>
</div>',
TRUE, TRUE),

-- Insert default acknowledgment template
('acknowledgment', 'Contact Form Acknowledgment',
'<div style="background-color: #e7f3ff; padding: 20px; border-left: 4px solid #007bff; margin: 20px 0; font-family: Arial, sans-serif;">
    <h3 style="margin: 0 0 10px 0; color: #007bff;">Thank You for Contacting Us!</h3>
    <p style="margin: 0 0 10px 0;">Dear {customer_name},</p>
    <p style="margin: 0 0 10px 0;"><strong>We have received your inquiry and will respond to you shortly.</strong></p>
    <p style="margin: 0 0 10px 0;">Your message has been forwarded to our team for review. We typically respond within 24 hours during business days.</p>
    <p style="margin: 0;">Reference ID: <strong>{inquiry_id}</strong></p>
</div>',
TRUE, TRUE);

-- Add email template variables table for dynamic content
CREATE TABLE `email_template_variables` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `variable_name` varchar(100) NOT NULL,
  `variable_description` text,
  `default_value` text,
  `is_system_variable` boolean NOT NULL DEFAULT FALSE,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_variable_name` (`variable_name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Insert common email variables
INSERT INTO `email_template_variables` (`variable_name`, `variable_description`, `default_value`, `is_system_variable`) VALUES
('{company_name}', 'Company name from admin settings', 'LoanFlow', TRUE),
('{company_tagline}', 'Company tagline or slogan', 'Your Financial Partner', TRUE),
('{company_email}', 'Company contact email', 'info@loanflow.com', TRUE),
('{company_phone}', 'Company contact phone', '+1 (555) 123-4567', TRUE),
('{company_website}', 'Company website URL', 'www.loanflow.com', TRUE),
('{company_address}', 'Company physical address', '123 Business St, City, State 12345', TRUE),
('{current_year}', 'Current year', '2024', TRUE),
('{customer_name}', 'Customer first name', '', FALSE),
('{inquiry_id}', 'Unique inquiry reference ID', '', FALSE),
('{login_url}', 'Client login URL', '/client/login.php', TRUE);

COMMIT;