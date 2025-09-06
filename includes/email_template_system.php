<?php
/**
 * Enhanced Email Template System with Standardized Components
 * Handles email headers, footers, and template management
 */

require_once 'config.php';
require_once 'email.php';

class EmailTemplateSystem {
    private $pdo;
    
    public function __construct() {
        global $pdo;
        $this->pdo = $pdo;
    }
    
    /**
     * Get email component by type
     * @param string $type Component type (header, footer, acknowledgment)
     * @param bool $default_only Get only default component
     * @return array|null Component data
     */
    public function getEmailComponent($type, $default_only = true) {
        try {
            $sql = "SELECT * FROM email_components WHERE component_type = ? AND is_active = 1";
            $params = [$type];
            
            if ($default_only) {
                $sql .= " AND is_default = 1";
            }
            
            $sql .= " ORDER BY is_default DESC, created_at DESC LIMIT 1";
            
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute($params);
            return $stmt->fetch(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            error_log('Error getting email component: ' . $e->getMessage());
            return null;
        }
    }
    
    /**
     * Get all template variables with their values
     * @return array Variables array
     */
    public function getTemplateVariables() {
        try {
            // Get system variables from database
            $stmt = $this->pdo->prepare("SELECT variable_name, default_value FROM email_template_variables WHERE is_system_variable = 1");
            $stmt->execute();
            $variables = [];
            
            while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                $variables[$row['variable_name']] = $row['default_value'];
            }
            
            // Override with actual admin settings if available
            $adminSettings = $this->getAdminSettings();
            if ($adminSettings) {
                $variables['{company_name}'] = $adminSettings['company_name'] ?? 'LoanFlow';
                $variables['{company_email}'] = $adminSettings['company_email'] ?? 'info@loanflow.com';
                $variables['{company_phone}'] = $adminSettings['company_phone'] ?? '+1 (555) 123-4567';
                $variables['{company_website}'] = $adminSettings['company_website'] ?? 'www.loanflow.com';
                $variables['{company_address}'] = $adminSettings['company_address'] ?? '123 Business St, City, State 12345';
            }
            
            // Add dynamic variables
            $variables['{current_year}'] = date('Y');
            $variables['{login_url}'] = '/client/login.php';
            
            return $variables;
        } catch (Exception $e) {
            error_log('Error getting template variables: ' . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Get admin settings from database
     * @return array|null Admin settings
     */
    private function getAdminSettings() {
        try {
            $stmt = $this->pdo->prepare("SELECT * FROM admin_settings WHERE id = 1");
            $stmt->execute();
            return $stmt->fetch(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            return null;
        }
    }
    
    /**
     * Replace template variables in content
     * @param string $content Content with variables
     * @param array $customVars Custom variables to replace
     * @return string Processed content
     */
    public function replaceVariables($content, $customVars = []) {
        $variables = array_merge($this->getTemplateVariables(), $customVars);
        
        foreach ($variables as $var => $value) {
            $content = str_replace($var, $value, $content);
        }
        
        return $content;
    }
    
    /**
     * Build complete email with header, content, and footer
     * @param string $content Main email content
     * @param array $customVars Custom variables
     * @return string Complete HTML email
     */
    public function buildCompleteEmail($content, $customVars = []) {
        $header = $this->getEmailComponent('header');
        $footer = $this->getEmailComponent('footer');
        
        $headerHtml = $header ? $header['html_content'] : '';
        $footerHtml = $footer ? $footer['html_content'] : '';
        
        $completeEmail = "
        <!DOCTYPE html>
        <html lang='en'>
        <head>
            <meta charset='UTF-8'>
            <meta name='viewport' content='width=device-width, initial-scale=1.0'>
            <title>Email from {company_name}</title>
            <style>
                body { margin: 0; padding: 0; font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
                .email-container { max-width: 600px; margin: 0 auto; background-color: #ffffff; }
                .email-content { padding: 20px; }
                @media only screen and (max-width: 600px) {
                    .email-container { width: 100% !important; }
                    .email-content { padding: 15px !important; }
                }
            </style>
        </head>
        <body>
            <div class='email-container'>
                {$headerHtml}
                <div class='email-content'>
                    {$content}
                </div>
                {$footerHtml}
            </div>
        </body>
        </html>
        ";
        
        return $this->replaceVariables($completeEmail, $customVars);
    }
    
    /**
     * Send contact form acknowledgment email
     * @param string $email Recipient email
     * @param string $name Recipient name
     * @param string $inquiryId Unique inquiry ID
     * @return bool Success status
     */
    public function sendContactAcknowledgment($email, $name, $inquiryId) {
        try {
            $acknowledgment = $this->getEmailComponent('acknowledgment');
            
            if (!$acknowledgment) {
                throw new Exception('Acknowledgment template not found');
            }
            
            $customVars = [
                '{customer_name}' => $name,
                '{inquiry_id}' => $inquiryId
            ];
            
            $content = $this->replaceVariables($acknowledgment['html_content'], $customVars);
            $completeEmail = $this->buildCompleteEmail($content, $customVars);
            
            $subject = "Thank you for contacting us - Reference #{$inquiryId}";
            
            return sendEmail($email, $subject, $completeEmail, $name);
            
        } catch (Exception $e) {
            error_log('Error sending contact acknowledgment: ' . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Send AI-powered contact response
     * @param string $email Recipient email
     * @param string $name Recipient name
     * @param string $originalMessage Original inquiry
     * @param string $aiResponse AI-generated response
     * @param string $inquiryId Inquiry reference ID
     * @return bool Success status
     */
    public function sendContactResponse($email, $name, $originalMessage, $aiResponse, $inquiryId) {
        try {
            $customVars = [
                '{customer_name}' => $name,
                '{inquiry_id}' => $inquiryId
            ];
            
            $content = "
            <div style='background-color: #f8f9fa; padding: 15px; border-radius: 5px; margin-bottom: 20px;'>
                <h3 style='margin: 0 0 10px 0; color: #495057;'>Your Original Message:</h3>
                <p style='margin: 0; font-style: italic;'>" . htmlspecialchars($originalMessage) . "</p>
            </div>
            
            <div style='margin-bottom: 20px;'>
                <h3 style='color: #007bff; margin: 0 0 15px 0;'>Our Response:</h3>
                <div style='white-space: pre-line; line-height: 1.6;'>" . htmlspecialchars($aiResponse) . "</div>
            </div>
            
            <div style='background-color: #e7f3ff; padding: 15px; border-left: 4px solid #007bff; margin: 20px 0;'>
                <p style='margin: 0 0 10px 0;'><strong>Need further assistance?</strong></p>
                <p style='margin: 0 0 5px 0;'>• Reply to this email with additional questions</p>
                <p style='margin: 0 0 5px 0;'>• Call us directly at {company_phone}</p>
                <p style='margin: 0 0 15px 0;'>• Visit our website at {company_website}</p>
                <p style='margin: 0;'><a href='{login_url}' style='background-color: #007bff; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px; display: inline-block;'>Access Client Portal</a></p>
            </div>
            ";
            
            $completeEmail = $this->buildCompleteEmail($content, $customVars);
            $subject = "Response to your inquiry - Reference #{$inquiryId}";
            
            return sendEmail($email, $subject, $completeEmail, $name);
            
        } catch (Exception $e) {
            error_log('Error sending contact response: ' . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Preview email template with sample data
     * @param string $templateContent Template HTML content
     * @param array $sampleVars Sample variables for preview
     * @return string Preview HTML
     */
    public function previewTemplate($templateContent, $sampleVars = []) {
        $defaultSampleVars = [
            '{customer_name}' => 'John Doe',
            '{inquiry_id}' => 'INQ-' . date('Ymd') . '-001',
            '{company_name}' => 'LoanFlow',
            '{company_email}' => 'info@loanflow.com',
            '{company_phone}' => '+1 (555) 123-4567'
        ];
        
        $sampleVars = array_merge($defaultSampleVars, $sampleVars);
        
        if (strpos($templateContent, '<html') === false) {
            // If it's just a component, wrap it in a complete email
            return $this->buildCompleteEmail($templateContent, $sampleVars);
        } else {
            // If it's already a complete email, just replace variables
            return $this->replaceVariables($templateContent, $sampleVars);
        }
    }
}

// Global functions for backward compatibility
function getEmailTemplateSystem() {
    return new EmailTemplateSystem();
}

function sendContactAcknowledgmentEmail($email, $name, $inquiryId) {
    $system = new EmailTemplateSystem();
    return $system->sendContactAcknowledgment($email, $name, $inquiryId);
}

function sendContactResponseEmail($email, $name, $originalMessage, $aiResponse, $inquiryId) {
    $system = new EmailTemplateSystem();
    return $system->sendContactResponse($email, $name, $originalMessage, $aiResponse, $inquiryId);
}
?>