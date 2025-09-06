<?php
/**
 * Workflow Triggers Integration
 * Handles automatic triggering of email workflows based on application events
 * LoanFlow Personal Loan Management System
 */

require_once 'email_workflow_engine.php';
require_once 'database.php';

class WorkflowTriggers {
    private $db;
    private $workflowEngine;
    
    public function __construct() {
        $this->db = Database::getInstance()->getConnection();
        $this->workflowEngine = new EmailWorkflowEngine();
    }
    
    /**
     * Trigger workflows when application step changes
     */
    public function onApplicationStepChange($userId, $applicationId, $oldStep, $newStep) {
        try {
            // Trigger step_after workflow for completed step
            if ($oldStep > 0) {
                $this->workflowEngine->triggerWorkflow($userId, 'step_after', $oldStep, $applicationId);
            }
            
            // Trigger step_before workflow for new step
            if ($newStep > 0) {
                $this->workflowEngine->triggerWorkflow($userId, 'step_before', $newStep, $applicationId);
            }
            
            // Special handling for step 4 completion
            if ($newStep == 4) {
                // Check if application is completed
                $stmt = $this->db->prepare("
                    SELECT status FROM loan_applications WHERE id = ?
                ");
                $stmt->execute([$applicationId]);
                $application = $stmt->fetch(PDO::FETCH_ASSOC);
                
                if ($application && $application['status'] == 'completed') {
                    $this->workflowEngine->triggerWorkflow($userId, 'step4_completion', 4, $applicationId);
                }
            }
            
            return true;
            
        } catch (Exception $e) {
            error_log("Workflow trigger error on step change: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Trigger workflows when application status changes
     */
    public function onApplicationStatusChange($userId, $applicationId, $oldStatus, $newStatus) {
        try {
            // Map status changes to workflow triggers
            $statusTriggers = [
                'submitted' => 'application_submitted',
                'under_review' => 'application_under_review',
                'approved' => 'application_approved',
                'rejected' => 'application_rejected',
                'completed' => 'application_completed'
            ];
            
            if (isset($statusTriggers[$newStatus])) {
                $this->workflowEngine->triggerWorkflow($userId, $statusTriggers[$newStatus], null, $applicationId);
            }
            
            // Special handling for completion
            if ($newStatus == 'completed') {
                $this->workflowEngine->triggerWorkflow($userId, 'step4_completion', 4, $applicationId);
            }
            
            return true;
            
        } catch (Exception $e) {
            error_log("Workflow trigger error on status change: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Trigger workflows when user registers
     */
    public function onUserRegistration($userId) {
        try {
            $this->workflowEngine->triggerWorkflow($userId, 'user_registration');
            return true;
            
        } catch (Exception $e) {
            error_log("Workflow trigger error on user registration: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Trigger workflows when payment is made
     */
    public function onPaymentMade($userId, $applicationId, $paymentAmount) {
        try {
            $this->workflowEngine->triggerWorkflow($userId, 'payment_made', null, $applicationId);
            return true;
            
        } catch (Exception $e) {
            error_log("Workflow trigger error on payment: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Trigger workflows when document is uploaded
     */
    public function onDocumentUpload($userId, $applicationId, $documentType) {
        try {
            $this->workflowEngine->triggerWorkflow($userId, 'document_uploaded', null, $applicationId);
            return true;
            
        } catch (Exception $e) {
            error_log("Workflow trigger error on document upload: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Trigger time-based workflows (called by cron)
     */
    public function processTimeBasedTriggers() {
        try {
            // Find applications that need time-based reminders
            $this->processInactivityReminders();
            $this->processFollowUpReminders();
            $this->processPaymentReminders();
            
            return true;
            
        } catch (Exception $e) {
            error_log("Time-based workflow trigger error: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Process inactivity reminders
     */
    private function processInactivityReminders() {
        // Find applications inactive for 24 hours
        $stmt = $this->db->prepare("
            SELECT la.user_id, la.id as application_id, la.current_step, la.updated_at
            FROM loan_applications la
            WHERE la.status IN ('pending', 'in_progress')
            AND la.updated_at < DATE_SUB(NOW(), INTERVAL 24 HOUR)
            AND NOT EXISTS (
                SELECT 1 FROM email_workflow_triggers ewt 
                WHERE ewt.user_id = la.user_id 
                AND ewt.application_id = la.id 
                AND ewt.trigger_type = 'inactivity_reminder'
                AND ewt.triggered_at > DATE_SUB(NOW(), INTERVAL 24 HOUR)
            )
        ");
        $stmt->execute();
        $applications = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        foreach ($applications as $app) {
            $this->workflowEngine->triggerWorkflow(
                $app['user_id'], 
                'inactivity_reminder', 
                $app['current_step'], 
                $app['application_id']
            );
        }
    }
    
    /**
     * Process follow-up reminders
     */
    private function processFollowUpReminders() {
        // Find applications needing follow-up after 3 days
        $stmt = $this->db->prepare("
            SELECT la.user_id, la.id as application_id, la.current_step
            FROM loan_applications la
            WHERE la.status = 'under_review'
            AND la.updated_at < DATE_SUB(NOW(), INTERVAL 3 DAY)
            AND NOT EXISTS (
                SELECT 1 FROM email_workflow_triggers ewt 
                WHERE ewt.user_id = la.user_id 
                AND ewt.application_id = la.id 
                AND ewt.trigger_type = 'followup_reminder'
                AND ewt.triggered_at > DATE_SUB(NOW(), INTERVAL 3 DAY)
            )
        ");
        $stmt->execute();
        $applications = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        foreach ($applications as $app) {
            $this->workflowEngine->triggerWorkflow(
                $app['user_id'], 
                'followup_reminder', 
                null, 
                $app['application_id']
            );
        }
    }
    
    /**
     * Process payment reminders
     */
    private function processPaymentReminders() {
        // Find approved applications without recent payments
        $stmt = $this->db->prepare("
            SELECT la.user_id, la.id as application_id
            FROM loan_applications la
            WHERE la.status = 'approved'
            AND la.updated_at < DATE_SUB(NOW(), INTERVAL 7 DAY)
            AND NOT EXISTS (
                SELECT 1 FROM payments p 
                WHERE p.application_id = la.id 
                AND p.created_at > DATE_SUB(NOW(), INTERVAL 7 DAY)
            )
            AND NOT EXISTS (
                SELECT 1 FROM email_workflow_triggers ewt 
                WHERE ewt.user_id = la.user_id 
                AND ewt.application_id = la.id 
                AND ewt.trigger_type = 'payment_reminder'
                AND ewt.triggered_at > DATE_SUB(NOW(), INTERVAL 7 DAY)
            )
        ");
        $stmt->execute();
        $applications = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        foreach ($applications as $app) {
            $this->workflowEngine->triggerWorkflow(
                $app['user_id'], 
                'payment_reminder', 
                null, 
                $app['application_id']
            );
        }
    }
}

// Global helper functions for easy integration

/**
 * Trigger workflow when application step changes
 */
function triggerApplicationStepWorkflow($userId, $applicationId, $oldStep, $newStep) {
    $triggers = new WorkflowTriggers();
    return $triggers->onApplicationStepChange($userId, $applicationId, $oldStep, $newStep);
}

/**
 * Trigger workflow when application status changes
 */
function triggerApplicationStatusWorkflow($userId, $applicationId, $oldStatus, $newStatus) {
    $triggers = new WorkflowTriggers();
    return $triggers->onApplicationStatusChange($userId, $applicationId, $oldStatus, $newStatus);
}

/**
 * Trigger workflow when user registers
 */
function triggerUserRegistrationWorkflow($userId) {
    $triggers = new WorkflowTriggers();
    return $triggers->onUserRegistration($userId);
}

/**
 * Trigger workflow when payment is made
 */
function triggerPaymentWorkflow($userId, $applicationId, $paymentAmount) {
    $triggers = new WorkflowTriggers();
    return $triggers->onPaymentMade($userId, $applicationId, $paymentAmount);
}

/**
 * Trigger workflow when document is uploaded
 */
function triggerDocumentWorkflow($userId, $applicationId, $documentType) {
    $triggers = new WorkflowTriggers();
    return $triggers->onDocumentUpload($userId, $applicationId, $documentType);
}

/**
 * Process time-based workflow triggers (for cron jobs)
 */
function processTimeBasedWorkflows() {
    $triggers = new WorkflowTriggers();
    return $triggers->processTimeBasedTriggers();
}

?>