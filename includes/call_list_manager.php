<?php
/**
 * Call List Manager
 * LoanFlow Personal Loan Management System
 * 
 * Manages automatic population and maintenance of the call list
 * based on application status changes and business rules.
 */

class CallListManager {
    private $db;
    
    public function __construct() {
        $this->db = getDB();
    }
    
    /**
     * Add a client to the call list
     */
    public function addToCallList($user_id, $list_type, $priority = 'normal', $max_attempts = 5, $callback_date = null, $notes = null, $assigned_to = null) {
        try {
            // Check if client is already in the call list
            $stmt = $this->db->prepare("
                SELECT id FROM call_lists 
                WHERE user_id = ? AND status IN ('pending', 'contacted')
            ");
            $stmt->execute([$user_id]);
            
            if ($stmt->fetch()) {
                // Update existing entry instead of creating duplicate
                return $this->updateCallListEntry($user_id, $list_type, $priority, $max_attempts, $callback_date, $notes, $assigned_to);
            }
            
            // Insert new call list entry
            $stmt = $this->db->prepare("
                INSERT INTO call_lists (
                    user_id, list_type, priority, call_attempts, max_attempts, 
                    callback_date, status, notes, assigned_to, created_at, updated_at
                ) VALUES (?, ?, ?, 0, ?, ?, 'pending', ?, ?, NOW(), NOW())
            ");
            
            $stmt->execute([
                $user_id, $list_type, $priority, $max_attempts, 
                $callback_date, $notes, $assigned_to
            ]);
            
            // Log the addition
            $this->logCallListAction($user_id, 'added', "Added to call list as {$list_type} with {$priority} priority");
            
            return true;
            
        } catch (Exception $e) {
            error_log("Call List Manager Error (addToCallList): " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Update existing call list entry
     */
    private function updateCallListEntry($user_id, $list_type, $priority, $max_attempts, $callback_date, $notes, $assigned_to) {
        try {
            $stmt = $this->db->prepare("
                UPDATE call_lists SET 
                    list_type = ?, priority = ?, max_attempts = ?, 
                    callback_date = ?, notes = ?, assigned_to = ?, updated_at = NOW()
                WHERE user_id = ? AND status IN ('pending', 'contacted')
            ");
            
            $stmt->execute([
                $list_type, $priority, $max_attempts, 
                $callback_date, $notes, $assigned_to, $user_id
            ]);
            
            $this->logCallListAction($user_id, 'updated', "Updated call list entry to {$list_type} with {$priority} priority");
            
            return true;
            
        } catch (Exception $e) {
            error_log("Call List Manager Error (updateCallListEntry): " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Remove client from call list
     */
    public function removeFromCallList($user_id, $reason = 'Manual removal') {
        try {
            $stmt = $this->db->prepare("
                UPDATE call_lists 
                SET status = 'completed', notes = ?, updated_at = NOW() 
                WHERE user_id = ? AND status IN ('pending', 'contacted')
            ");
            
            $stmt->execute([$reason, $user_id]);
            
            $this->logCallListAction($user_id, 'removed', $reason);
            
            return true;
            
        } catch (Exception $e) {
            error_log("Call List Manager Error (removeFromCallList): " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Handle new application submission
     */
    public function handleNewApplication($user_id, $loan_amount = null) {
        // Determine priority based on loan amount
        $priority = 'high'; // Default for new applications
        
        if ($loan_amount) {
            if ($loan_amount >= 50000) {
                $priority = 'urgent';
            } elseif ($loan_amount >= 25000) {
                $priority = 'high';
            } else {
                $priority = 'normal';
            }
        }
        
        // Add to call list with high priority and 3 max attempts
        return $this->addToCallList(
            $user_id, 
            'new_application', 
            $priority, 
            3, // Max attempts for new applications
            null, // Immediate callback
            'New loan application submitted - requires immediate contact',
            null // Auto-assign later based on workload
        );
    }
    
    /**
     * Handle pre-approval status
     */
    public function handlePreApproval($user_id, $callback_hours = 24) {
        $callback_date = date('Y-m-d H:i:s', strtotime("+{$callback_hours} hours"));
        
        return $this->addToCallList(
            $user_id,
            'pre_approval',
            'high',
            5, // More attempts for pre-approved clients
            $callback_date,
            'Client pre-approved - schedule callback to discuss next steps',
            null
        );
    }
    
    /**
     * Handle general follow-up
     */
    public function handleGeneralFollowUp($user_id, $reason, $priority = 'normal', $callback_date = null) {
        return $this->addToCallList(
            $user_id,
            'general',
            $priority,
            3,
            $callback_date,
            "General follow-up: {$reason}",
            null
        );
    }
    
    /**
     * Handle paid client follow-up
     */
    public function handlePaidClient($user_id, $reason = 'Post-payment follow-up') {
        return $this->addToCallList(
            $user_id,
            'paid_client',
            'normal',
            2, // Fewer attempts for paid clients
            date('Y-m-d H:i:s', strtotime('+3 days')), // 3 days later
            $reason,
            null
        );
    }
    
    /**
     * Auto-assign calls based on agent workload
     */
    public function autoAssignCalls() {
        try {
            // Get agents and their current workload
            $stmt = $this->db->query("
                SELECT u.id, u.first_name, u.last_name,
                       COUNT(cl.id) as current_calls
                FROM users u
                LEFT JOIN call_lists cl ON u.id = cl.assigned_to AND cl.status IN ('pending', 'contacted')
                WHERE u.role IN ('admin', 'agent') AND u.status = 'active'
                GROUP BY u.id
                ORDER BY current_calls ASC, u.id ASC
            ");
            
            $agents = $stmt->fetchAll();
            
            if (empty($agents)) {
                return false;
            }
            
            // Get unassigned calls
            $stmt = $this->db->query("
                SELECT id, user_id, list_type, priority 
                FROM call_lists 
                WHERE assigned_to IS NULL AND status IN ('pending', 'contacted')
                ORDER BY 
                    CASE list_type 
                        WHEN 'new_application' THEN 1
                        WHEN 'pre_approval' THEN 2
                        WHEN 'general' THEN 3
                        WHEN 'paid_client' THEN 4
                    END,
                    CASE priority 
                        WHEN 'urgent' THEN 1
                        WHEN 'high' THEN 2
                        WHEN 'normal' THEN 3
                        WHEN 'low' THEN 4
                    END,
                    created_at ASC
            ");
            
            $unassigned_calls = $stmt->fetchAll();
            
            if (empty($unassigned_calls)) {
                return true; // No unassigned calls
            }
            
            // Round-robin assignment
            $agent_index = 0;
            $assigned_count = 0;
            
            foreach ($unassigned_calls as $call) {
                $agent = $agents[$agent_index % count($agents)];
                
                // Assign the call
                $stmt = $this->db->prepare("
                    UPDATE call_lists 
                    SET assigned_to = ?, updated_at = NOW() 
                    WHERE id = ?
                ");
                
                $stmt->execute([$agent['id'], $call['id']]);
                
                $this->logCallListAction(
                    $call['user_id'], 
                    'assigned', 
                    "Auto-assigned to {$agent['first_name']} {$agent['last_name']}"
                );
                
                $assigned_count++;
                $agent_index++;
            }
            
            return $assigned_count;
            
        } catch (Exception $e) {
            error_log("Call List Manager Error (autoAssignCalls): " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Clean up old completed calls
     */
    public function cleanupOldCalls($days = 30) {
        try {
            $stmt = $this->db->prepare("
                DELETE FROM call_lists 
                WHERE status IN ('completed', 'removed') 
                AND updated_at < DATE_SUB(NOW(), INTERVAL ? DAY)
            ");
            
            $stmt->execute([$days]);
            
            return $stmt->rowCount();
            
        } catch (Exception $e) {
            error_log("Call List Manager Error (cleanupOldCalls): " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Get call list statistics
     */
    public function getCallListStats() {
        try {
            $stmt = $this->db->query("
                SELECT 
                    COUNT(*) as total,
                    SUM(CASE WHEN list_type = 'new_application' THEN 1 ELSE 0 END) as new_applications,
                    SUM(CASE WHEN list_type = 'pre_approval' THEN 1 ELSE 0 END) as pre_approval,
                    SUM(CASE WHEN list_type = 'general' THEN 1 ELSE 0 END) as general,
                    SUM(CASE WHEN list_type = 'paid_client' THEN 1 ELSE 0 END) as paid_client,
                    SUM(CASE WHEN priority = 'urgent' THEN 1 ELSE 0 END) as urgent,
                    SUM(CASE WHEN priority = 'high' THEN 1 ELSE 0 END) as high,
                    SUM(CASE WHEN callback_date IS NOT NULL AND callback_date <= NOW() THEN 1 ELSE 0 END) as overdue,
                    SUM(CASE WHEN assigned_to IS NULL THEN 1 ELSE 0 END) as unassigned
                FROM call_lists 
                WHERE status IN ('pending', 'contacted')
            ");
            
            return $stmt->fetch();
            
        } catch (Exception $e) {
            error_log("Call List Manager Error (getCallListStats): " . $e->getMessage());
            return null;
        }
    }
    
    /**
     * Log call list actions for audit trail
     */
    private function logCallListAction($user_id, $action, $details) {
        try {
            $stmt = $this->db->prepare("
                INSERT INTO audit_logs (user_id, action, details, ip_address, user_agent, created_at)
                VALUES (?, ?, ?, ?, ?, NOW())
            ");
            
            $stmt->execute([
                $user_id,
                "call_list_{$action}",
                $details,
                $_SERVER['REMOTE_ADDR'] ?? 'system',
                $_SERVER['HTTP_USER_AGENT'] ?? 'system'
            ]);
            
        } catch (Exception $e) {
            // Don't fail the main operation if logging fails
            error_log("Call List Logging Error: " . $e->getMessage());
        }
    }
    
    /**
     * Process application status changes
     */
    public function handleApplicationStatusChange($user_id, $old_status, $new_status, $loan_amount = null) {
        switch ($new_status) {
            case 'submitted':
            case 'under_review':
                return $this->handleNewApplication($user_id, $loan_amount);
                
            case 'pre_approved':
                return $this->handlePreApproval($user_id);
                
            case 'approved':
                // Remove from call list when approved
                return $this->removeFromCallList($user_id, 'Application approved');
                
            case 'rejected':
                // Remove from call list when rejected
                return $this->removeFromCallList($user_id, 'Application rejected');
                
            case 'funded':
                // Add to paid client follow-up
                return $this->handlePaidClient($user_id, 'Loan funded - follow-up required');
                
            default:
                return true; // No action needed
        }
    }
}

// Helper functions for easy access
function addToCallList($user_id, $list_type, $priority = 'normal', $max_attempts = 5, $callback_date = null, $notes = null, $assigned_to = null) {
    $manager = new CallListManager();
    return $manager->addToCallList($user_id, $list_type, $priority, $max_attempts, $callback_date, $notes, $assigned_to);
}

function removeFromCallList($user_id, $reason = 'Manual removal') {
    $manager = new CallListManager();
    return $manager->removeFromCallList($user_id, $reason);
}

function handleApplicationStatusChange($user_id, $old_status, $new_status, $loan_amount = null) {
    $manager = new CallListManager();
    return $manager->handleApplicationStatusChange($user_id, $old_status, $new_status, $loan_amount);
}

function autoAssignCalls() {
    $manager = new CallListManager();
    return $manager->autoAssignCalls();
}

function getCallListStats() {
    $manager = new CallListManager();
    return $manager->getCallListStats();
}

?>