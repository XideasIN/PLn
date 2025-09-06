<?php
/**
 * Email Workflow Engine
 * Core engine for processing automated email triggers and workflows
 * LoanFlow Personal Loan Management System
 */

require_once 'database.php';
require_once 'email.php';
require_once 'functions.php';

class EmailWorkflowEngine {
    private $db;
    private $emailManager;
    private $batchSize;
    private $retryAttempts;
    private $retryDelay;
    
    public function __construct() {
        $this->db = Database::getInstance()->getConnection();
        $this->emailManager = new EmailManager();
        
        // Load system settings
        $this->batchSize = (int)getSystemSetting('email_queue_batch_size', 50);
        $this->retryAttempts = (int)getSystemSetting('email_retry_attempts', 3);
        $this->retryDelay = (int)getSystemSetting('email_retry_delay_minutes', 30);
    }
    
    /**
     * Main workflow processing method - called by cron job
     */
    public function processWorkflows() {
        try {
            $this->processTriggers();
            $this->processEmailQueue();
            $this->processBulkCampaigns();
            $this->cleanupOldRecords();
            
            return [
                'success' => true,
                'message' => 'Workflow processing completed successfully'
            ];
        } catch (Exception $e) {
            error_log("Email workflow processing error: " . $e->getMessage());
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }
    
    /**
     * Process workflow triggers and schedule emails
     */
    private function processTriggers() {
        // Get unprocessed triggers
        $stmt = $this->db->prepare("
            SELECT * FROM email_workflow_triggers 
            WHERE processed = FALSE 
            ORDER BY triggered_at ASC
            LIMIT ?
        ");
        $stmt->execute([$this->batchSize]);
        $triggers = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        foreach ($triggers as $trigger) {
            $this->processTrigger($trigger);
        }
    }
    
    /**
     * Process individual trigger
     */
    private function processTrigger($trigger) {
        try {
            // Find matching workflow templates
            $stmt = $this->db->prepare("
                SELECT * FROM email_workflow_templates 
                WHERE trigger_type = ? 
                AND (trigger_step IS NULL OR trigger_step = ?) 
                AND is_active = TRUE
                ORDER BY id ASC
            ");
            $stmt->execute([$trigger['trigger_type'], $trigger['trigger_step']]);
            $templates = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            foreach ($templates as $template) {
                $this->scheduleEmailFromTemplate($template, $trigger);
            }
            
            // Mark trigger as processed
            $stmt = $this->db->prepare("
                UPDATE email_workflow_triggers 
                SET processed = TRUE, processed_at = NOW() 
                WHERE id = ?
            ");
            $stmt->execute([$trigger['id']]);
            
        } catch (Exception $e) {
            error_log("Trigger processing error: " . $e->getMessage());
        }
    }
    
    /**
     * Schedule email from template and trigger
     */
    private function scheduleEmailFromTemplate($template, $trigger) {
        try {
            // Calculate scheduled time
            $scheduledAt = new DateTime();
            if ($template['trigger_delay_hours'] > 0) {
                $scheduledAt->add(new DateInterval('PT' . $template['trigger_delay_hours'] . 'H'));
            }
            
            // Get user and application data for personalization
            $userData = $this->getUserData($trigger['user_id']);
            $applicationData = null;
            if ($trigger['application_id']) {
                $applicationData = $this->getApplicationData($trigger['application_id']);
            }
            
            // Personalize email content
            $personalizedSubject = $this->personalizeContent($template['subject'], $userData, $applicationData);
            $personalizedBody = $this->personalizeContent($template['body_html'], $userData, $applicationData);
            
            // Insert into email queue
            $stmt = $this->db->prepare("
                INSERT INTO email_workflow_queue 
                (template_id, user_id, application_id, scheduled_at, status, personalized_subject, personalized_body, created_at)
                VALUES (?, ?, ?, ?, 'pending', ?, ?, NOW())
            ");
            
            $stmt->execute([
                $template['id'],
                $trigger['user_id'],
                $trigger['application_id'],
                $scheduledAt->format('Y-m-d H:i:s'),
                $personalizedSubject,
                $personalizedBody
            ]);
            
        } catch (Exception $e) {
            error_log("Email scheduling error: " . $e->getMessage());
        }
    }
    
    /**
     * Process email queue and send emails
     */
    private function processEmailQueue() {
        // Get pending emails ready to send
        $stmt = $this->db->prepare("
            SELECT eq.*, ewt.name as template_name 
            FROM email_workflow_queue eq
            JOIN email_workflow_templates ewt ON eq.template_id = ewt.id
            WHERE eq.status = 'pending' 
            AND eq.scheduled_at <= NOW()
            ORDER BY eq.scheduled_at ASC
            LIMIT ?
        ");
        $stmt->execute([$this->batchSize]);
        $emails = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        foreach ($emails as $email) {
            $this->sendQueuedEmail($email);
        }
    }
    
    /**
     * Send individual queued email
     */
    private function sendQueuedEmail($email) {
        try {
            // Update status to sending
            $this->updateEmailStatus($email['id'], 'sending');
            
            // Get recipient data
            $userData = $this->getUserData($email['user_id']);
            if (!$userData || !$userData['email']) {
                throw new Exception('Invalid recipient email');
            }
            
            // Send email
            $result = $this->emailManager->sendEmail(
                $userData['email'],
                $email['personalized_subject'],
                $email['personalized_body'],
                true // HTML format
            );
            
            if ($result) {
                // Mark as sent
                $this->updateEmailStatus($email['id'], 'sent', null, date('Y-m-d H:i:s'));
                
                // Log delivery
                $this->logEmailDelivery($email, 'sent');
            } else {
                throw new Exception('Email sending failed');
            }
            
        } catch (Exception $e) {
            // Handle failure
            $this->handleEmailFailure($email, $e->getMessage());
        }
    }
    
    /**
     * Handle email sending failure
     */
    private function handleEmailFailure($email, $errorMessage) {
        $attempts = $email['retry_attempts'] ?? 0;
        
        if ($attempts < $this->retryAttempts) {
            // Schedule retry
            $retryTime = new DateTime();
            $retryTime->add(new DateInterval('PT' . $this->retryDelay . 'M'));
            
            $stmt = $this->db->prepare("
                UPDATE email_workflow_queue 
                SET status = 'pending', 
                    scheduled_at = ?, 
                    retry_attempts = retry_attempts + 1,
                    error_message = ?
                WHERE id = ?
            ");
            $stmt->execute([
                $retryTime->format('Y-m-d H:i:s'),
                $errorMessage,
                $email['id']
            ]);
        } else {
            // Mark as failed
            $this->updateEmailStatus($email['id'], 'failed', $errorMessage);
            $this->logEmailDelivery($email, 'failed', $errorMessage);
        }
    }
    
    /**
     * Process bulk email campaigns
     */
    private function processBulkCampaigns() {
        // Get campaigns ready to start
        $stmt = $this->db->prepare("
            SELECT * FROM bulk_email_campaigns 
            WHERE status = 'scheduled' 
            AND scheduled_at <= NOW()
            ORDER BY scheduled_at ASC
        ");
        $stmt->execute();
        $campaigns = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        foreach ($campaigns as $campaign) {
            $this->processBulkCampaign($campaign);
        }
    }
    
    /**
     * Process individual bulk campaign
     */
    private function processBulkCampaign($campaign) {
        try {
            // Update campaign status
            $stmt = $this->db->prepare("
                UPDATE bulk_email_campaigns 
                SET status = 'sending', started_at = NOW() 
                WHERE id = ?
            ");
            $stmt->execute([$campaign['id']]);
            
            // Get recipients based on criteria
            $recipients = $this->getCampaignRecipients($campaign['target_criteria']);
            
            // Update total recipients count
            $stmt = $this->db->prepare("
                UPDATE bulk_email_campaigns 
                SET total_recipients = ? 
                WHERE id = ?
            ");
            $stmt->execute([count($recipients), $campaign['id']]);
            
            // Schedule emails for recipients
            foreach ($recipients as $recipient) {
                $this->scheduleBulkEmail($campaign, $recipient);
            }
            
            // Mark campaign as completed if no recipients
            if (empty($recipients)) {
                $stmt = $this->db->prepare("
                    UPDATE bulk_email_campaigns 
                    SET status = 'completed', completed_at = NOW() 
                    WHERE id = ?
                ");
                $stmt->execute([$campaign['id']]);
            }
            
        } catch (Exception $e) {
            error_log("Bulk campaign processing error: " . $e->getMessage());
            
            // Mark campaign as failed
            $stmt = $this->db->prepare("
                UPDATE bulk_email_campaigns 
                SET status = 'cancelled' 
                WHERE id = ?
            ");
            $stmt->execute([$campaign['id']]);
        }
    }
    
    /**
     * Get campaign recipients based on criteria
     */
    private function getCampaignRecipients($criteria) {
        $criteriaData = json_decode($criteria, true);
        $targetType = $criteriaData['target_type'] ?? 'all_clients';
        
        switch ($targetType) {
            case 'all_clients':
                $stmt = $this->db->prepare("
                    SELECT id, email, first_name, last_name 
                    FROM users 
                    WHERE role = 'client' AND email IS NOT NULL AND email != ''
                ");
                $stmt->execute();
                break;
                
            case 'active_loans':
                $stmt = $this->db->prepare("
                    SELECT DISTINCT u.id, u.email, u.first_name, u.last_name 
                    FROM users u
                    JOIN loan_applications la ON u.id = la.user_id
                    WHERE u.role = 'client' AND u.email IS NOT NULL 
                    AND la.status = 'approved'
                ");
                $stmt->execute();
                break;
                
            case 'pending_applications':
                $stmt = $this->db->prepare("
                    SELECT DISTINCT u.id, u.email, u.first_name, u.last_name 
                    FROM users u
                    JOIN loan_applications la ON u.id = la.user_id
                    WHERE u.role = 'client' AND u.email IS NOT NULL 
                    AND la.status IN ('pending', 'under_review')
                ");
                $stmt->execute();
                break;
                
            default:
                return [];
        }
        
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    /**
     * Schedule bulk email for recipient
     */
    private function scheduleBulkEmail($campaign, $recipient) {
        try {
            // Get template data
            $stmt = $this->db->prepare("
                SELECT * FROM email_workflow_templates WHERE id = ?
            ");
            $stmt->execute([$campaign['template_id']]);
            $template = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$template) {
                throw new Exception('Template not found');
            }
            
            // Personalize content
            $personalizedSubject = $this->personalizeContent($template['subject'], $recipient);
            $personalizedBody = $this->personalizeContent($template['body_html'], $recipient);
            
            // Insert into queue
            $stmt = $this->db->prepare("
                INSERT INTO email_workflow_queue 
                (template_id, user_id, campaign_id, scheduled_at, status, personalized_subject, personalized_body, created_at)
                VALUES (?, ?, ?, NOW(), 'pending', ?, ?, NOW())
            ");
            
            $stmt->execute([
                $template['id'],
                $recipient['id'],
                $campaign['id'],
                $personalizedSubject,
                $personalizedBody
            ]);
            
        } catch (Exception $e) {
            error_log("Bulk email scheduling error: " . $e->getMessage());
        }
    }
    
    /**
     * Personalize email content with variables
     */
    private function personalizeContent($content, $userData, $applicationData = null) {
        $variables = [
            '{{first_name}}' => $userData['first_name'] ?? '',
            '{{last_name}}' => $userData['last_name'] ?? '',
            '{{full_name}}' => trim(($userData['first_name'] ?? '') . ' ' . ($userData['last_name'] ?? '')),
            '{{email}}' => $userData['email'] ?? '',
            '{{phone}}' => $userData['phone'] ?? '',
            '{{company_name}}' => getSystemSetting('company_name', 'LoanFlow'),
            '{{company_phone}}' => getSystemSetting('company_phone', ''),
            '{{company_email}}' => getSystemSetting('company_email', ''),
            '{{current_date}}' => date('F j, Y'),
            '{{login_url}}' => getSystemSetting('site_url', '') . '/client/',
            '{{support_url}}' => getSystemSetting('site_url', '') . '/contact.php'
        ];
        
        // Add application-specific variables
        if ($applicationData) {
            $variables['{{application_id}}'] = $applicationData['id'] ?? '';
            $variables['{{loan_amount}}'] = '$' . number_format($applicationData['loan_amount'] ?? 0);
            $variables['{{current_step}}'] = $applicationData['current_step'] ?? '';
            $variables['{{next_step}}'] = ($applicationData['current_step'] ?? 0) + 1;
            $variables['{{application_status}}'] = ucfirst($applicationData['status'] ?? '');
        }
        
        // Replace variables in content
        foreach ($variables as $placeholder => $value) {
            $content = str_replace($placeholder, $value, $content);
        }
        
        return $content;
    }
    
    /**
     * Get user data for personalization
     */
    private function getUserData($userId) {
        $stmt = $this->db->prepare("
            SELECT id, email, first_name, last_name, phone 
            FROM users WHERE id = ?
        ");
        $stmt->execute([$userId]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }
    
    /**
     * Get application data for personalization
     */
    private function getApplicationData($applicationId) {
        $stmt = $this->db->prepare("
            SELECT id, loan_amount, current_step, status, created_at 
            FROM loan_applications WHERE id = ?
        ");
        $stmt->execute([$applicationId]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }
    
    /**
     * Update email status
     */
    private function updateEmailStatus($emailId, $status, $errorMessage = null, $sentAt = null) {
        $sql = "UPDATE email_workflow_queue SET status = ?";
        $params = [$status];
        
        if ($errorMessage !== null) {
            $sql .= ", error_message = ?";
            $params[] = $errorMessage;
        }
        
        if ($sentAt !== null) {
            $sql .= ", sent_at = ?";
            $params[] = $sentAt;
        }
        
        $sql .= " WHERE id = ?";
        $params[] = $emailId;
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
    }
    
    /**
     * Log email delivery
     */
    private function logEmailDelivery($email, $status, $errorMessage = null) {
        try {
            $userData = $this->getUserData($email['user_id']);
            
            $stmt = $this->db->prepare("
                INSERT INTO email_delivery_log 
                (queue_id, campaign_id, user_id, template_id, email_address, subject, delivery_status, error_message, delivery_time)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW())
            ");
            
            $stmt->execute([
                $email['id'],
                $email['campaign_id'] ?? null,
                $email['user_id'],
                $email['template_id'],
                $userData['email'] ?? '',
                $email['personalized_subject'],
                $status,
                $errorMessage
            ]);
            
        } catch (Exception $e) {
            error_log("Email delivery logging error: " . $e->getMessage());
        }
    }
    
    /**
     * Clean up old records
     */
    private function cleanupOldRecords() {
        try {
            // Clean up old processed triggers (older than 30 days)
            $stmt = $this->db->prepare("
                DELETE FROM email_workflow_triggers 
                WHERE processed = TRUE AND processed_at < DATE_SUB(NOW(), INTERVAL 30 DAY)
            ");
            $stmt->execute();
            
            // Clean up old sent emails (older than 90 days)
            $stmt = $this->db->prepare("
                DELETE FROM email_workflow_queue 
                WHERE status IN ('sent', 'failed') AND created_at < DATE_SUB(NOW(), INTERVAL 90 DAY)
            ");
            $stmt->execute();
            
            // Clean up old delivery logs (older than 180 days)
            $stmt = $this->db->prepare("
                DELETE FROM email_delivery_log 
                WHERE delivery_time < DATE_SUB(NOW(), INTERVAL 180 DAY)
            ");
            $stmt->execute();
            
        } catch (Exception $e) {
            error_log("Cleanup error: " . $e->getMessage());
        }
    }
    
    /**
     * Trigger workflow manually
     */
    public function triggerWorkflow($userId, $triggerType, $triggerStep = null, $applicationId = null) {
        try {
            $stmt = $this->db->prepare("
                INSERT INTO email_workflow_triggers 
                (user_id, application_id, trigger_type, trigger_step, triggered_at)
                VALUES (?, ?, ?, ?, NOW())
            ");
            
            $stmt->execute([$userId, $applicationId, $triggerType, $triggerStep]);
            
            return [
                'success' => true,
                'message' => 'Workflow triggered successfully'
            ];
            
        } catch (Exception $e) {
            error_log("Manual workflow trigger error: " . $e->getMessage());
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }
    
    /**
     * Get workflow analytics
     */
    public function getAnalytics($days = 30) {
        try {
            $stmt = $this->db->prepare("
                SELECT 
                    COUNT(*) as total_emails,
                    SUM(CASE WHEN delivery_status = 'sent' THEN 1 ELSE 0 END) as sent_emails,
                    SUM(CASE WHEN delivery_status = 'delivered' THEN 1 ELSE 0 END) as delivered_emails,
                    SUM(CASE WHEN delivery_status = 'failed' THEN 1 ELSE 0 END) as failed_emails,
                    SUM(CASE WHEN delivery_status = 'opened' THEN 1 ELSE 0 END) as opened_emails
                FROM email_delivery_log 
                WHERE delivery_time >= DATE_SUB(NOW(), INTERVAL ? DAY)
            ");
            $stmt->execute([$days]);
            $stats = $stmt->fetch(PDO::FETCH_ASSOC);
            
            // Get pending emails count
            $stmt = $this->db->prepare("
                SELECT COUNT(*) as pending_emails 
                FROM email_workflow_queue 
                WHERE status = 'pending'
            ");
            $stmt->execute();
            $pending = $stmt->fetch(PDO::FETCH_ASSOC);
            
            return array_merge($stats, $pending);
            
        } catch (Exception $e) {
            error_log("Analytics error: " . $e->getMessage());
            return [];
        }
    }
}

// Helper functions for workflow triggers
function triggerStepWorkflow($userId, $step, $applicationId = null) {
    $engine = new EmailWorkflowEngine();
    
    // Trigger step_after for previous step
    if ($step > 1) {
        $engine->triggerWorkflow($userId, 'step_after', $step - 1, $applicationId);
    }
    
    // Trigger step_before for current step
    $engine->triggerWorkflow($userId, 'step_before', $step, $applicationId);
    
    // Special handling for step 4 completion
    if ($step == 4) {
        $engine->triggerWorkflow($userId, 'step4_completion', 4, $applicationId);
    }
}

function triggerTimeBasedWorkflow($userId, $applicationId = null) {
    $engine = new EmailWorkflowEngine();
    return $engine->triggerWorkflow($userId, 'time_based', null, $applicationId);
}

function triggerBulkWorkflow($campaignId) {
    $engine = new EmailWorkflowEngine();
    // This would be handled by the bulk campaign processing
    return ['success' => true];
}

?>