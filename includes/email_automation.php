<?php
/**
 * Email Automation System
 * Handles automated email workflows, triggers, and bulk email campaigns
 */

require_once 'database.php';
require_once 'email.php';
require_once 'functions.php';

class EmailAutomationManager {
    private $db;
    private $emailManager;
    
    public function __construct() {
        $this->db = Database::getInstance()->getConnection();
        $this->emailManager = new EmailManager();
    }
    
    /**
     * Process email queue - called by cron job
     */
    public function processEmailQueue() {
        try {
            $stmt = $this->db->prepare("
                SELECT eq.*, ewt.template_name, ewt.subject_template, ewt.body_template, ewt.from_email, ewt.from_name
                FROM email_queue eq
                JOIN email_workflow_templates ewt ON eq.template_id = ewt.id
                WHERE eq.status = 'pending' AND eq.scheduled_at <= NOW()
                ORDER BY eq.priority DESC, eq.scheduled_at ASC
                LIMIT 50
            ");
            $stmt->execute();
            $emails = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            foreach ($emails as $email) {
                $this->sendQueuedEmail($email);
            }
            
            return count($emails);
        } catch (Exception $e) {
            error_log("Email queue processing error: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Send a queued email
     */
    private function sendQueuedEmail($emailData) {
        try {
            // Update status to processing
            $this->updateEmailStatus($emailData['id'], 'processing');
            
            // Get personalization variables
            $variables = $this->getPersonalizationVariables($emailData['client_id']);
            
            // Process template
            $subject = $this->processTemplate($emailData['subject_template'], $variables);
            $body = $this->processTemplate($emailData['body_template'], $variables);
            
            // Send email
            $result = $this->emailManager->sendEmail(
                $emailData['recipient_email'],
                $subject,
                $body,
                $emailData['from_email'],
                $emailData['from_name']
            );
            
            if ($result) {
                $this->updateEmailStatus($emailData['id'], 'sent');
                $this->logEmailDelivery($emailData['id'], 'sent', 'Email sent successfully');
            } else {
                $this->updateEmailStatus($emailData['id'], 'failed');
                $this->logEmailDelivery($emailData['id'], 'failed', 'Failed to send email');
            }
            
        } catch (Exception $e) {
            $this->updateEmailStatus($emailData['id'], 'failed');
            $this->logEmailDelivery($emailData['id'], 'failed', $e->getMessage());
            error_log("Email sending error: " . $e->getMessage());
        }
    }
    
    /**
     * Update email status in queue
     */
    private function updateEmailStatus($emailId, $status) {
        $stmt = $this->db->prepare("UPDATE email_queue SET status = ?, updated_at = NOW() WHERE id = ?");
        $stmt->execute([$status, $emailId]);
    }
    
    /**
     * Log email delivery
     */
    private function logEmailDelivery($emailId, $status, $message) {
        $stmt = $this->db->prepare("
            INSERT INTO email_delivery_log (email_queue_id, status, message, created_at)
            VALUES (?, ?, ?, NOW())
        ");
        $stmt->execute([$emailId, $status, $message]);
    }
    
    /**
     * Get personalization variables for a client
     */
    private function getPersonalizationVariables($clientId) {
        $variables = [];
        
        // Get client data
        $stmt = $this->db->prepare("
            SELECT u.*, a.loan_amount, a.loan_purpose, a.monthly_income, a.employment_status,
                   a.application_step, a.created_at as application_date
            FROM users u
            LEFT JOIN applications a ON u.id = a.user_id
            WHERE u.id = ?
        ");
        $stmt->execute([$clientId]);
        $client = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($client) {
            $variables = [
                'client_name' => $client['first_name'] . ' ' . $client['last_name'],
                'first_name' => $client['first_name'],
                'last_name' => $client['last_name'],
                'email' => $client['email'],
                'phone' => $client['phone'] ?? '',
                'loan_amount' => number_format($client['loan_amount'] ?? 0, 2),
                'loan_purpose' => $client['loan_purpose'] ?? '',
                'monthly_income' => number_format($client['monthly_income'] ?? 0, 2),
                'employment_status' => $client['employment_status'] ?? '',
                'application_step' => $client['application_step'] ?? '',
                'application_date' => date('F j, Y', strtotime($client['application_date'] ?? 'now')),
                'current_date' => date('F j, Y'),
                'company_name' => $this->getSystemSetting('company_name', 'QuickFunds'),
                'company_phone' => $this->getSystemSetting('company_phone', ''),
                'company_email' => $this->getSystemSetting('company_email', ''),
                'support_url' => $this->getSystemSetting('support_url', ''),
            ];
        }
        
        // Get custom personalization variables
        $stmt = $this->db->prepare("SELECT variable_name, variable_value FROM email_personalization_variables WHERE client_id = ?");
        $stmt->execute([$clientId]);
        $customVars = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        foreach ($customVars as $var) {
            $variables[$var['variable_name']] = $var['variable_value'];
        }
        
        return $variables;
    }
    
    /**
     * Process email template with variables
     */
    private function processTemplate($template, $variables) {
        foreach ($variables as $key => $value) {
            $template = str_replace('{{' . $key . '}}', $value, $template);
        }
        return $template;
    }
    
    /**
     * Schedule an email based on workflow trigger
     */
    public function scheduleWorkflowEmail($clientId, $triggerType, $triggerValue = null) {
        try {
            // Find matching workflow templates
            $stmt = $this->db->prepare("
                SELECT * FROM email_workflow_templates 
                WHERE trigger_type = ? AND is_active = 1
                ORDER BY priority DESC
            ");
            $stmt->execute([$triggerType]);
            $templates = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            foreach ($templates as $template) {
                // Check if email should be scheduled based on conditions
                if ($this->shouldScheduleEmail($template, $clientId, $triggerValue)) {
                    $this->scheduleEmail($template, $clientId);
                }
            }
            
        } catch (Exception $e) {
            error_log("Workflow email scheduling error: " . $e->getMessage());
        }
    }
    
    /**
     * Check if email should be scheduled based on template conditions
     */
    private function shouldScheduleEmail($template, $clientId, $triggerValue) {
        // Check if email was already sent for this trigger
        $stmt = $this->db->prepare("
            SELECT COUNT(*) FROM email_queue 
            WHERE client_id = ? AND template_id = ? AND status IN ('sent', 'processing')
        ");
        $stmt->execute([$clientId, $template['id']]);
        if ($stmt->fetchColumn() > 0) {
            return false; // Already sent
        }
        
        // Additional condition checks can be added here
        // For example, checking client status, loan amount, etc.
        
        return true;
    }
    
    /**
     * Schedule an email
     */
    private function scheduleEmail($template, $clientId) {
        // Get client email
        $stmt = $this->db->prepare("SELECT email FROM users WHERE id = ?");
        $stmt->execute([$clientId]);
        $clientEmail = $stmt->fetchColumn();
        
        if (!$clientEmail) {
            return false;
        }
        
        // Calculate scheduled time
        $scheduledAt = new DateTime();
        if ($template['delay_minutes'] > 0) {
            $scheduledAt->add(new DateInterval('PT' . $template['delay_minutes'] . 'M'));
        }
        
        // Insert into queue
        $stmt = $this->db->prepare("
            INSERT INTO email_queue (client_id, template_id, recipient_email, priority, scheduled_at, status, created_at)
            VALUES (?, ?, ?, ?, ?, 'pending', NOW())
        ");
        
        return $stmt->execute([
            $clientId,
            $template['id'],
            $clientEmail,
            $template['priority'],
            $scheduledAt->format('Y-m-d H:i:s')
        ]);
    }
    
    /**
     * Create bulk email campaign
     */
    public function createBulkCampaign($campaignData) {
        try {
            $this->db->beginTransaction();
            
            // Insert campaign
            $stmt = $this->db->prepare("
                INSERT INTO bulk_email_campaigns (name, subject_template, body_template, from_email, from_name, 
                                                 target_criteria, scheduled_at, created_by, status, created_at)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'draft', NOW())
            ");
            
            $stmt->execute([
                $campaignData['name'],
                $campaignData['subject'],
                $campaignData['body'],
                $campaignData['from_email'],
                $campaignData['from_name'],
                json_encode($campaignData['criteria']),
                $campaignData['scheduled_at'],
                $campaignData['created_by']
            ]);
            
            $campaignId = $this->db->lastInsertId();
            
            // Get target recipients
            $recipients = $this->getTargetRecipients($campaignData['criteria']);
            
            // Schedule emails for each recipient
            foreach ($recipients as $recipient) {
                $stmt = $this->db->prepare("
                    INSERT INTO email_queue (client_id, bulk_campaign_id, recipient_email, priority, scheduled_at, status, created_at)
                    VALUES (?, ?, ?, 3, ?, 'pending', NOW())
                ");
                
                $stmt->execute([
                    $recipient['id'],
                    $campaignId,
                    $recipient['email'],
                    $campaignData['scheduled_at']
                ]);
            }
            
            // Update campaign status
            $stmt = $this->db->prepare("UPDATE bulk_email_campaigns SET status = 'scheduled', recipient_count = ? WHERE id = ?");
            $stmt->execute([count($recipients), $campaignId]);
            
            $this->db->commit();
            return $campaignId;
            
        } catch (Exception $e) {
            $this->db->rollBack();
            error_log("Bulk campaign creation error: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Get target recipients based on criteria
     */
    private function getTargetRecipients($criteria) {
        $sql = "SELECT DISTINCT u.id, u.email, u.first_name, u.last_name FROM users u";
        $joins = [];
        $conditions = [];
        $params = [];
        
        // Build query based on criteria
        if (!empty($criteria['application_step'])) {
            $joins[] = "LEFT JOIN applications a ON u.id = a.user_id";
            $conditions[] = "a.application_step = ?";
            $params[] = $criteria['application_step'];
        }
        
        if (!empty($criteria['country'])) {
            $conditions[] = "u.country = ?";
            $params[] = $criteria['country'];
        }
        
        if (!empty($criteria['date_range'])) {
            $conditions[] = "u.created_at >= ? AND u.created_at <= ?";
            $params[] = $criteria['date_range']['start'];
            $params[] = $criteria['date_range']['end'];
        }
        
        // Add joins
        if (!empty($joins)) {
            $sql .= " " . implode(" ", array_unique($joins));
        }
        
        // Add conditions
        if (!empty($conditions)) {
            $sql .= " WHERE " . implode(" AND ", $conditions);
        }
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    /**
     * Get system setting
     */
    private function getSystemSetting($key, $default = '') {
        $stmt = $this->db->prepare("SELECT setting_value FROM system_settings WHERE setting_key = ?");
        $stmt->execute([$key]);
        $result = $stmt->fetchColumn();
        return $result !== false ? $result : $default;
    }
    
    /**
     * Get email analytics
     */
    public function getEmailAnalytics($dateRange = null) {
        $sql = "SELECT * FROM email_analytics_view";
        $params = [];
        
        if ($dateRange) {
            $sql .= " WHERE date >= ? AND date <= ?";
            $params = [$dateRange['start'], $dateRange['end']];
        }
        
        $sql .= " ORDER BY date DESC";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    /**
     * Get campaign statistics
     */
    public function getCampaignStats($campaignId) {
        $stmt = $this->db->prepare("
            SELECT 
                COUNT(*) as total_emails,
                SUM(CASE WHEN status = 'sent' THEN 1 ELSE 0 END) as sent_count,
                SUM(CASE WHEN status = 'failed' THEN 1 ELSE 0 END) as failed_count,
                SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending_count
            FROM email_queue 
            WHERE bulk_campaign_id = ?
        ");
        
        $stmt->execute([$campaignId]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }
}

/**
 * Email Template Manager
 */
class EmailTemplateManager {
    private $db;
    
    public function __construct() {
        $this->db = Database::getInstance()->getConnection();
    }
    
    /**
     * Create or update email template
     */
    public function saveTemplate($templateData) {
        try {
            if (isset($templateData['id']) && $templateData['id']) {
                // Update existing template
                $stmt = $this->db->prepare("
                    UPDATE email_workflow_templates 
                    SET template_name = ?, subject_template = ?, body_template = ?, 
                        trigger_type = ?, delay_minutes = ?, priority = ?, is_active = ?,
                        from_email = ?, from_name = ?, updated_at = NOW()
                    WHERE id = ?
                ");
                
                return $stmt->execute([
                    $templateData['name'],
                    $templateData['subject'],
                    $templateData['body'],
                    $templateData['trigger_type'],
                    $templateData['delay_minutes'],
                    $templateData['priority'],
                    $templateData['is_active'] ? 1 : 0,
                    $templateData['from_email'],
                    $templateData['from_name'],
                    $templateData['id']
                ]);
            } else {
                // Create new template
                $stmt = $this->db->prepare("
                    INSERT INTO email_workflow_templates 
                    (template_name, subject_template, body_template, trigger_type, delay_minutes, 
                     priority, is_active, from_email, from_name, created_at)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())
                ");
                
                return $stmt->execute([
                    $templateData['name'],
                    $templateData['subject'],
                    $templateData['body'],
                    $templateData['trigger_type'],
                    $templateData['delay_minutes'],
                    $templateData['priority'],
                    $templateData['is_active'] ? 1 : 0,
                    $templateData['from_email'],
                    $templateData['from_name']
                ]);
            }
        } catch (Exception $e) {
            error_log("Template save error: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Get all templates
     */
    public function getTemplates() {
        $stmt = $this->db->prepare("SELECT * FROM email_workflow_templates ORDER BY priority DESC, template_name");
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    /**
     * Get template by ID
     */
    public function getTemplate($id) {
        $stmt = $this->db->prepare("SELECT * FROM email_workflow_templates WHERE id = ?");
        $stmt->execute([$id]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }
    
    /**
     * Delete template
     */
    public function deleteTemplate($id) {
        $stmt = $this->db->prepare("DELETE FROM email_workflow_templates WHERE id = ?");
        return $stmt->execute([$id]);
    }
}
?>