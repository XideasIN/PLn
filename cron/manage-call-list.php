<?php
/**
 * Call List Management Cron Job
 * LoanFlow Personal Loan Management System
 * 
 * This script should be run every 15 minutes to:
 * - Auto-assign unassigned calls to agents
 * - Update overdue callbacks
 * - Clean up old completed calls
 * - Generate call list reports
 * - Send notifications for urgent calls
 */

require_once dirname(__DIR__) . '/config/database.php';
require_once dirname(__DIR__) . '/includes/functions.php';
require_once dirname(__DIR__) . '/includes/call_list_manager.php';
require_once dirname(__DIR__) . '/includes/email_system.php';

// Ensure this script is only run from command line or cron
if (isset($_SERVER['HTTP_HOST'])) {
    http_response_code(403);
    die('This script can only be run from command line.');
}

echo "[" . date('Y-m-d H:i:s') . "] Starting Call List Management...\n";

try {
    $db = getDB();
    $callManager = new CallListManager();
    
    // 1. Auto-assign unassigned calls
    echo "[" . date('Y-m-d H:i:s') . "] Auto-assigning calls...\n";
    $assigned_count = $callManager->autoAssignCalls();
    if ($assigned_count !== false) {
        echo "[" . date('Y-m-d H:i:s') . "] Assigned {$assigned_count} calls to agents\n";
    } else {
        echo "[" . date('Y-m-d H:i:s') . "] Error during auto-assignment\n";
    }
    
    // 2. Update overdue callbacks
    echo "[" . date('Y-m-d H:i:s') . "] Updating overdue callbacks...\n";
    $stmt = $db->query("
        UPDATE call_lists 
        SET priority = CASE 
            WHEN priority = 'normal' THEN 'high'
            WHEN priority = 'high' THEN 'urgent'
            ELSE priority
        END,
        updated_at = NOW()
        WHERE callback_date IS NOT NULL 
        AND callback_date <= NOW() 
        AND status IN ('pending', 'contacted')
        AND callback_date < DATE_SUB(NOW(), INTERVAL 1 HOUR)
    ");
    $overdue_updated = $stmt->rowCount();
    echo "[" . date('Y-m-d H:i:s') . "] Updated {$overdue_updated} overdue callbacks\n";
    
    // 3. Escalate calls with max attempts reached
    echo "[" . date('Y-m-d H:i:s') . "] Escalating max attempt calls...\n";
    $stmt = $db->query("
        UPDATE call_lists 
        SET priority = 'urgent', 
            notes = CONCAT(COALESCE(notes, ''), ' - ESCALATED: Max attempts reached'),
            updated_at = NOW()
        WHERE call_attempts >= max_attempts 
        AND status = 'pending'
        AND priority != 'urgent'
    ");
    $escalated_count = $stmt->rowCount();
    echo "[" . date('Y-m-d H:i:s') . "] Escalated {$escalated_count} calls due to max attempts\n";
    
    // 4. Clean up old completed calls (older than 30 days)
    echo "[" . date('Y-m-d H:i:s') . "] Cleaning up old calls...\n";
    $cleaned_count = $callManager->cleanupOldCalls(30);
    if ($cleaned_count !== false) {
        echo "[" . date('Y-m-d H:i:s') . "] Cleaned up {$cleaned_count} old call records\n";
    } else {
        echo "[" . date('Y-m-d H:i:s') . "] Error during cleanup\n";
    }
    
    // 5. Generate daily statistics (only run once per day at 9 AM)
    $current_hour = (int)date('H');
    if ($current_hour === 9) {
        echo "[" . date('Y-m-d H:i:s') . "] Generating daily statistics...\n";
        generateDailyCallListReport();
    }
    
    // 6. Send urgent call notifications
    echo "[" . date('Y-m-d H:i:s') . "] Checking for urgent notifications...\n";
    sendUrgentCallNotifications();
    
    // 7. Update call list statistics cache
    echo "[" . date('Y-m-d H:i:s') . "] Updating statistics cache...\n";
    $stats = $callManager->getCallListStats();
    if ($stats) {
        // Store stats in cache table or file for quick access
        $stmt = $db->prepare("
            INSERT INTO system_cache (cache_key, cache_value, expires_at) 
            VALUES ('call_list_stats', ?, DATE_ADD(NOW(), INTERVAL 15 MINUTE))
            ON DUPLICATE KEY UPDATE 
            cache_value = VALUES(cache_value), 
            expires_at = VALUES(expires_at)
        ");
        $stmt->execute([json_encode($stats)]);
        echo "[" . date('Y-m-d H:i:s') . "] Statistics cached: {$stats['total']} total calls\n";
    }
    
    echo "[" . date('Y-m-d H:i:s') . "] Call List Management completed successfully\n";
    
} catch (Exception $e) {
    echo "[" . date('Y-m-d H:i:s') . "] ERROR: " . $e->getMessage() . "\n";
    error_log("Call List Management Cron Error: " . $e->getMessage());
    
    // Send error notification to admin
    try {
        $emailSystem = new EmailSystem();
        $emailSystem->sendSystemAlert(
            'Call List Management Error',
            "An error occurred during call list management:\n\n" . $e->getMessage() . "\n\nTime: " . date('Y-m-d H:i:s')
        );
    } catch (Exception $emailError) {
        error_log("Failed to send error notification: " . $emailError->getMessage());
    }
}

/**
 * Generate daily call list report
 */
function generateDailyCallListReport() {
    try {
        $db = getDB();
        $callManager = new CallListManager();
        
        // Get comprehensive statistics
        $stats = $callManager->getCallListStats();
        
        // Get agent performance
        $stmt = $db->query("
            SELECT u.first_name, u.last_name,
                   COUNT(cl.id) as assigned_calls,
                   SUM(CASE WHEN cl.status = 'completed' THEN 1 ELSE 0 END) as completed_calls,
                   AVG(cl.call_attempts) as avg_attempts
            FROM users u
            LEFT JOIN call_lists cl ON u.id = cl.assigned_to 
                AND cl.created_at >= CURDATE()
            WHERE u.role IN ('admin', 'agent')
            GROUP BY u.id
            ORDER BY completed_calls DESC
        ");
        $agent_performance = $stmt->fetchAll();
        
        // Get overdue calls by type
        $stmt = $db->query("
            SELECT list_type, COUNT(*) as count
            FROM call_lists
            WHERE callback_date IS NOT NULL 
            AND callback_date <= NOW()
            AND status IN ('pending', 'contacted')
            GROUP BY list_type
        ");
        $overdue_by_type = $stmt->fetchAll();
        
        // Generate report
        $report = "Daily Call List Report - " . date('Y-m-d') . "\n";
        $report .= str_repeat('=', 50) . "\n\n";
        
        $report .= "OVERALL STATISTICS:\n";
        $report .= "- Total Pending Calls: {$stats['total']}\n";
        $report .= "- New Applications: {$stats['new_applications']}\n";
        $report .= "- Pre-Approval Calls: {$stats['pre_approval']}\n";
        $report .= "- General Follow-ups: {$stats['general']}\n";
        $report .= "- Paid Client Calls: {$stats['paid_client']}\n";
        $report .= "- Urgent Priority: {$stats['urgent']}\n";
        $report .= "- Overdue Callbacks: {$stats['overdue']}\n";
        $report .= "- Unassigned Calls: {$stats['unassigned']}\n\n";
        
        $report .= "AGENT PERFORMANCE (Today):\n";
        foreach ($agent_performance as $agent) {
            $completion_rate = $agent['assigned_calls'] > 0 ? 
                round(($agent['completed_calls'] / $agent['assigned_calls']) * 100, 1) : 0;
            $report .= "- {$agent['first_name']} {$agent['last_name']}: ";
            $report .= "{$agent['completed_calls']}/{$agent['assigned_calls']} calls ({$completion_rate}%), ";
            $report .= "Avg attempts: " . round($agent['avg_attempts'], 1) . "\n";
        }
        
        if (!empty($overdue_by_type)) {
            $report .= "\nOVERDUE CALLS BY TYPE:\n";
            foreach ($overdue_by_type as $type) {
                $report .= "- " . ucfirst(str_replace('_', ' ', $type['list_type'])) . ": {$type['count']}\n";
            }
        }
        
        // Save report to file
        $report_file = dirname(__DIR__) . '/logs/call_list_reports/daily_' . date('Y-m-d') . '.txt';
        $report_dir = dirname($report_file);
        
        if (!is_dir($report_dir)) {
            mkdir($report_dir, 0755, true);
        }
        
        file_put_contents($report_file, $report);
        
        // Email report to admin (if configured)
        if (function_exists('sendDailyReport')) {
            sendDailyReport('Call List Daily Report', $report);
        }
        
        echo "[" . date('Y-m-d H:i:s') . "] Daily report generated: {$report_file}\n";
        
    } catch (Exception $e) {
        echo "[" . date('Y-m-d H:i:s') . "] Error generating daily report: " . $e->getMessage() . "\n";
        error_log("Call List Daily Report Error: " . $e->getMessage());
    }
}

/**
 * Send notifications for urgent calls
 */
function sendUrgentCallNotifications() {
    try {
        $db = getDB();
        
        // Get urgent calls that haven't been notified in the last 2 hours
        $stmt = $db->query("
            SELECT cl.*, u.first_name, u.last_name, u.reference_number, u.email,
                   agent.first_name as agent_first_name, agent.last_name as agent_last_name,
                   agent.email as agent_email
            FROM call_lists cl
            JOIN users u ON cl.user_id = u.id
            LEFT JOIN users agent ON cl.assigned_to = agent.id
            WHERE cl.priority = 'urgent' 
            AND cl.status IN ('pending', 'contacted')
            AND (cl.last_notification IS NULL OR cl.last_notification < DATE_SUB(NOW(), INTERVAL 2 HOUR))
        ");
        
        $urgent_calls = $stmt->fetchAll();
        
        if (empty($urgent_calls)) {
            echo "[" . date('Y-m-d H:i:s') . "] No urgent calls requiring notification\n";
            return;
        }
        
        $emailSystem = new EmailSystem();
        $notification_count = 0;
        
        foreach ($urgent_calls as $call) {
            $subject = "URGENT: Call Required - {$call['first_name']} {$call['last_name']}";
            
            $message = "An urgent call is required for the following client:\n\n";
            $message .= "Client: {$call['first_name']} {$call['last_name']}\n";
            $message .= "Reference: {$call['reference_number']}\n";
            $message .= "Email: {$call['email']}\n";
            $message .= "Type: " . ucfirst(str_replace('_', ' ', $call['list_type'])) . "\n";
            $message .= "Attempts: {$call['call_attempts']}/{$call['max_attempts']}\n";
            
            if ($call['callback_date']) {
                $message .= "Callback Due: " . date('Y-m-d H:i', strtotime($call['callback_date'])) . "\n";
            }
            
            if ($call['notes']) {
                $message .= "Notes: {$call['notes']}\n";
            }
            
            $message .= "\nPlease contact this client immediately.\n";
            $message .= "\nView Call List: " . getBaseUrl() . "/admin/call-list.php\n";
            
            // Send to assigned agent or all agents if unassigned
            if ($call['agent_email']) {
                $emailSystem->sendNotification($call['agent_email'], $subject, $message);
            } else {
                // Send to all agents
                $stmt_agents = $db->query("
                    SELECT email FROM users 
                    WHERE role IN ('admin', 'agent') AND status = 'active'
                ");
                $agents = $stmt_agents->fetchAll();
                
                foreach ($agents as $agent) {
                    $emailSystem->sendNotification($agent['email'], $subject, $message);
                }
            }
            
            // Update last notification time
            $stmt_update = $db->prepare("
                UPDATE call_lists 
                SET last_notification = NOW() 
                WHERE id = ?
            ");
            $stmt_update->execute([$call['id']]);
            
            $notification_count++;
        }
        
        echo "[" . date('Y-m-d H:i:s') . "] Sent {$notification_count} urgent call notifications\n";
        
    } catch (Exception $e) {
        echo "[" . date('Y-m-d H:i:s') . "] Error sending urgent notifications: " . $e->getMessage() . "\n";
        error_log("Urgent Call Notification Error: " . $e->getMessage());
    }
}

/**
 * Get base URL for links in notifications
 */
function getBaseUrl() {
    $protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http';
    $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
    return $protocol . '://' . $host;
}

echo "[" . date('Y-m-d H:i:s') . "] Call List Management script finished\n";
?>