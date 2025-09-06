<?php
/**
 * Call List Integration Hooks
 * Automatically manages call list entries based on application events
 */

require_once __DIR__ . '/call_list_manager.php';

class CallListHooks {
    private $callManager;
    private $db;
    
    public function __construct($database) {
        $this->db = $database;
        $this->callManager = new CallListManager($database);
    }
    
    /**
     * Hook for new application submission
     * Called after application is successfully submitted
     */
    public function onApplicationSubmitted($userId, $applicationData) {
        try {
            // Add to call list as new application
            $this->callManager->addToCallList($userId, 'new_application', [
                'loan_amount' => $applicationData['loan_amount'] ?? 0,
                'notes' => 'New application submitted - requires initial contact'
            ]);
            
            $this->logAction('application_submitted', $userId, 'Added to call list as new application');
            
        } catch (Exception $e) {
            error_log("CallListHooks::onApplicationSubmitted Error: " . $e->getMessage());
        }
    }
    
    /**
     * Hook for application status change
     * Called when application status is updated
     */
    public function onApplicationStatusChanged($userId, $oldStatus, $newStatus, $applicationData = []) {
        try {
            switch ($newStatus) {
                case 'pre_approved':
                    // Move to pre-approval follow-up
                    $this->callManager->updateCallListType($userId, 'pre_approval');
                    $this->callManager->updateCallNotes($userId, 'Application pre-approved - follow up required');
                    break;
                    
                case 'approved':
                    // Move to general follow-up
                    $this->callManager->updateCallListType($userId, 'general');
                    $this->callManager->updateCallNotes($userId, 'Application approved - general follow-up');
                    break;
                    
                case 'funded':
                    // Move to paid client list
                    $this->callManager->updateCallListType($userId, 'paid_client');
                    $this->callManager->updateCallNotes($userId, 'Client funded - ongoing relationship management');
                    break;
                    
                case 'rejected':
                case 'cancelled':
                    // Remove from call list
                    $this->callManager->removeFromCallList($userId, 'Application ' . $newStatus);
                    break;
                    
                case 'documents_requested':
                    // Update priority and notes
                    $this->callManager->updateCallPriority($userId, 'high');
                    $this->callManager->updateCallNotes($userId, 'Documents requested - follow up on submission');
                    break;
            }
            
            $this->logAction('status_changed', $userId, "Status changed from {$oldStatus} to {$newStatus}");
            
        } catch (Exception $e) {
            error_log("CallListHooks::onApplicationStatusChanged Error: " . $e->getMessage());
        }
    }
    
    /**
     * Hook for document upload
     * Called when client uploads documents
     */
    public function onDocumentUploaded($userId, $documentType) {
        try {
            // Update call list with document upload info
            $this->callManager->updateCallNotes($userId, "Document uploaded: {$documentType} - review and follow up");
            $this->callManager->updateCallPriority($userId, 'high');
            
            $this->logAction('document_uploaded', $userId, "Document uploaded: {$documentType}");
            
        } catch (Exception $e) {
            error_log("CallListHooks::onDocumentUploaded Error: " . $e->getMessage());
        }
    }
    
    /**
     * Hook for payment received
     * Called when client makes a payment
     */
    public function onPaymentReceived($userId, $amount, $paymentType) {
        try {
            // Update call list for payment follow-up
            $this->callManager->updateCallNotes($userId, "Payment received: $" . number_format($amount, 2) . " ({$paymentType}) - confirm and thank client");
            $this->callManager->updateCallPriority($userId, 'normal');
            
            $this->logAction('payment_received', $userId, "Payment received: $" . number_format($amount, 2));
            
        } catch (Exception $e) {
            error_log("CallListHooks::onPaymentReceived Error: " . $e->getMessage());
        }
    }
    
    /**
     * Hook for missed callback
     * Called when scheduled callback time passes without contact
     */
    public function onMissedCallback($userId) {
        try {
            // Escalate priority and reschedule
            $this->callManager->updateCallPriority($userId, 'urgent');
            $this->callManager->scheduleCallback($userId, date('Y-m-d H:i:s', strtotime('+1 hour')));
            $this->callManager->updateCallNotes($userId, 'MISSED CALLBACK - Escalated to urgent, rescheduled');
            
            $this->logAction('missed_callback', $userId, 'Callback missed - escalated to urgent');
            
        } catch (Exception $e) {
            error_log("CallListHooks::onMissedCallback Error: " . $e->getMessage());
        }
    }
    
    /**
     * Hook for client communication
     * Called when client initiates contact (email, chat, etc.)
     */
    public function onClientCommunication($userId, $communicationType, $message = '') {
        try {
            // Update call list with communication info
            $notes = "Client initiated {$communicationType}";
            if ($message) {
                $notes .= ": " . substr($message, 0, 100) . (strlen($message) > 100 ? '...' : '');
            }
            $notes .= " - Follow up required";
            
            $this->callManager->updateCallNotes($userId, $notes);
            $this->callManager->updateCallPriority($userId, 'high');
            
            $this->logAction('client_communication', $userId, "Client initiated {$communicationType}");
            
        } catch (Exception $e) {
            error_log("CallListHooks::onClientCommunication Error: " . $e->getMessage());
        }
    }
    
    /**
     * Hook for agent assignment
     * Called when an agent is assigned to a client
     */
    public function onAgentAssigned($userId, $agentId, $reason = '') {
        try {
            // Update call list assignment
            $this->callManager->assignCall($userId, $agentId);
            
            $agentName = $this->getAgentName($agentId);
            $notes = "Assigned to agent: {$agentName}";
            if ($reason) {
                $notes .= " - Reason: {$reason}";
            }
            
            $this->callManager->updateCallNotes($userId, $notes);
            
            $this->logAction('agent_assigned', $userId, "Assigned to agent ID: {$agentId}");
            
        } catch (Exception $e) {
            error_log("CallListHooks::onAgentAssigned Error: " . $e->getMessage());
        }
    }
    
    /**
     * Hook for holiday/weekend detection
     * Called to check if calls should be made today
     */
    public function shouldMakeCallsToday($countryCode = 'US') {
        try {
            // Check if today is a holiday or weekend
            $today = date('Y-m-d');
            $dayOfWeek = date('w'); // 0 = Sunday, 6 = Saturday
            
            // Don't make calls on weekends
            if ($dayOfWeek == 0 || $dayOfWeek == 6) {
                return false;
            }
            
            // Check for holidays
            $stmt = $this->db->prepare("
                SELECT COUNT(*) as holiday_count 
                FROM holidays 
                WHERE country_code = ? AND holiday_date = ?
            ");
            $stmt->execute([$countryCode, $today]);
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            
            return $result['holiday_count'] == 0;
            
        } catch (Exception $e) {
            error_log("CallListHooks::shouldMakeCallsToday Error: " . $e->getMessage());
            return true; // Default to allowing calls if check fails
        }
    }
    
    /**
     * Get agent name by ID
     */
    private function getAgentName($agentId) {
        try {
            $stmt = $this->db->prepare("SELECT CONCAT(first_name, ' ', last_name) as full_name FROM users WHERE id = ?");
            $stmt->execute([$agentId]);
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            return $result ? $result['full_name'] : "Agent #{$agentId}";
        } catch (Exception $e) {
            return "Agent #{$agentId}";
        }
    }
    
    /**
     * Log action for audit trail
     */
    private function logAction($action, $userId, $details) {
        try {
            $stmt = $this->db->prepare("
                INSERT INTO system_logs (log_type, user_id, action, details, created_at) 
                VALUES ('call_list_hook', ?, ?, ?, NOW())
            ");
            $stmt->execute([$userId, $action, $details]);
        } catch (Exception $e) {
            error_log("CallListHooks::logAction Error: " . $e->getMessage());
        }
    }
    
    /**
     * Bulk update call list priorities based on business rules
     */
    public function updateCallPriorities() {
        try {
            // High priority: New applications over $50k
            $stmt = $this->db->prepare("
                UPDATE call_lists cl
                JOIN loan_applications la ON cl.user_id = la.user_id
                SET cl.priority = 'high'
                WHERE cl.list_type = 'new_application' 
                AND la.loan_amount >= 50000
                AND cl.priority != 'urgent'
            ");
            $stmt->execute();
            
            // Urgent: Overdue callbacks (more than 24 hours past callback_date)
            $stmt = $this->db->prepare("
                UPDATE call_lists 
                SET priority = 'urgent'
                WHERE callback_date < DATE_SUB(NOW(), INTERVAL 24 HOUR)
                AND status = 'pending'
            ");
            $stmt->execute();
            
            // Normal priority: Paid clients (unless urgent)
            $stmt = $this->db->prepare("
                UPDATE call_lists 
                SET priority = 'normal'
                WHERE list_type = 'paid_client'
                AND priority NOT IN ('urgent', 'high')
            ");
            $stmt->execute();
            
            $this->logAction('bulk_priority_update', 0, 'Bulk priority update completed');
            
        } catch (Exception $e) {
            error_log("CallListHooks::updateCallPriorities Error: " . $e->getMessage());
        }
    }
}

// Global function to get CallListHooks instance
function getCallListHooks($database = null) {
    static $instance = null;
    
    if ($instance === null && $database !== null) {
        $instance = new CallListHooks($database);
    }
    
    return $instance;
}

// Example usage in application files:
/*
// In application submission handler:
$hooks = getCallListHooks($pdo);
$hooks->onApplicationSubmitted($userId, $applicationData);

// In status update handler:
$hooks = getCallListHooks($pdo);
$hooks->onApplicationStatusChanged($userId, $oldStatus, $newStatus);

// In document upload handler:
$hooks = getCallListHooks($pdo);
$hooks->onDocumentUploaded($userId, $documentType);
*/
?>