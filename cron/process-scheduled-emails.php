<?php
/**
 * Process Scheduled Emails Cron Job
 * Runs daily to process emails scheduled for today, respecting holidays and weekends
 */

// Set execution time limit for cron job
set_time_limit(300); // 5 minutes

// Include required files
require_once dirname(__DIR__) . '/config/database.php';
require_once dirname(__DIR__) . '/includes/holiday_email_scheduler.php';
require_once dirname(__DIR__) . '/includes/audit_logger.php';

// Initialize components
$scheduler = new HolidayEmailScheduler();
$audit_logger = new AuditLogger();

// Log cron job start
echo "[" . date('Y-m-d H:i:s') . "] Starting scheduled email processing...\n";
$audit_logger->logActivity([
    'user_id' => 'system',
    'action' => 'cron_scheduled_emails_start',
    'details' => ['date' => date('Y-m-d')]
]);

try {
    // Process scheduled emails
    $result = $scheduler->processScheduledEmails();
    
    if ($result['success']) {
        echo "[" . date('Y-m-d H:i:s') . "] Email processing completed successfully\n";
        echo "  - Total emails: {$result['total']}\n";
        echo "  - Processed: {$result['processed']}\n";
        echo "  - Errors: {$result['errors']}\n";
        
        // Log success
        $audit_logger->logActivity([
            'user_id' => 'system',
            'action' => 'cron_scheduled_emails_success',
            'details' => [
                'total' => $result['total'],
                'processed' => $result['processed'],
                'errors' => $result['errors']
            ]
        ]);
        
        // Additional processing: Check for emails that need rescheduling
        $rescheduled = checkAndRescheduleEmails($scheduler);
        if ($rescheduled > 0) {
            echo "  - Rescheduled: {$rescheduled} emails due to holidays/weekends\n";
        }
        
    } else {
        echo "[" . date('Y-m-d H:i:s') . "] Email processing failed: {$result['message']}\n";
        
        // Log failure
        $audit_logger->logActivity([
            'user_id' => 'system',
            'action' => 'cron_scheduled_emails_error',
            'details' => ['error' => $result['message']]
        ]);
    }
    
    // Generate daily email report
    generateDailyEmailReport($scheduler);
    
} catch (Exception $e) {
    echo "[" . date('Y-m-d H:i:s') . "] Critical error: " . $e->getMessage() . "\n";
    
    // Log critical error
    $audit_logger->logActivity([
        'user_id' => 'system',
        'action' => 'cron_scheduled_emails_critical_error',
        'details' => ['error' => $e->getMessage()]
    ]);
    
    // Send alert to administrators
    sendCriticalErrorAlert($e->getMessage());
}

echo "[" . date('Y-m-d H:i:s') . "] Scheduled email processing completed\n";

/**
 * Check and reschedule emails that fall on holidays or weekends
 * @param HolidayEmailScheduler $scheduler
 * @return int Number of rescheduled emails
 */
function checkAndRescheduleEmails($scheduler) {
    global $pdo;
    
    $rescheduled = 0;
    $today = date('Y-m-d');
    
    try {
        // Get emails scheduled for today that might need rescheduling
        $stmt = $pdo->prepare("
            SELECT * FROM email_queue 
            WHERE scheduled_date = ? AND status = 'scheduled'
        ");
        
        $stmt->execute([$today]);
        $emails = $stmt->fetchAll();
        
        foreach ($emails as $email) {
            $country_code = $email['country_code'] ?? 'USA';
            
            // Check if today is suitable for sending
            if (!$scheduler->isEmailSendingAllowed($today, $country_code)) {
                // Find next available date
                $next_date = $scheduler->getNextAvailableEmailDate($country_code, $today);
                
                // Update the scheduled date
                $update_stmt = $pdo->prepare("
                    UPDATE email_queue 
                    SET scheduled_date = ?, updated_at = NOW() 
                    WHERE id = ?
                ");
                
                if ($update_stmt->execute([$next_date, $email['id']])) {
                    $rescheduled++;
                    echo "  - Rescheduled email ID {$email['id']} from {$today} to {$next_date}\n";
                }
            }
        }
        
    } catch (Exception $e) {
        echo "Error rescheduling emails: " . $e->getMessage() . "\n";
    }
    
    return $rescheduled;
}

/**
 * Generate daily email report
 * @param HolidayEmailScheduler $scheduler
 */
function generateDailyEmailReport($scheduler) {
    global $pdo;
    
    try {
        $today = date('Y-m-d');
        $report = [];
        
        // Get email statistics for today
        $stmt = $pdo->query("
            SELECT 
                status,
                COUNT(*) as count,
                country_code
            FROM email_queue 
            WHERE DATE(created_at) = '{$today}' OR scheduled_date = '{$today}'
            GROUP BY status, country_code
            ORDER BY country_code, status
        ");
        
        $stats = $stmt->fetchAll();
        
        if (!empty($stats)) {
            echo "\n=== Daily Email Report for {$today} ===\n";
            
            $total_by_status = [];
            foreach ($stats as $stat) {
                $country = $stat['country_code'] ?? 'Unknown';
                $status = $stat['status'];
                $count = $stat['count'];
                
                echo "  {$country}: {$status} = {$count}\n";
                
                if (!isset($total_by_status[$status])) {
                    $total_by_status[$status] = 0;
                }
                $total_by_status[$status] += $count;
            }
            
            echo "\nTotals by Status:\n";
            foreach ($total_by_status as $status => $count) {
                echo "  {$status}: {$count}\n";
            }
        }
        
        // Get upcoming holidays that might affect email scheduling
        $countries = ['USA', 'CAN', 'GBR', 'AUS'];
        foreach ($countries as $country) {
            $holidays = $scheduler->getUpcomingHolidays($country, 7); // Next 7 days
            if (!empty($holidays)) {
                echo "\nUpcoming holidays for {$country}:\n";
                foreach ($holidays as $holiday) {
                    echo "  - {$holiday['holiday_name']}: {$holiday['holiday_date']}\n";
                }
            }
        }
        
        echo "\n=== End of Report ===\n\n";
        
    } catch (Exception $e) {
        echo "Error generating daily report: " . $e->getMessage() . "\n";
    }
}

/**
 * Send critical error alert to administrators
 * @param string $error_message
 */
function sendCriticalErrorAlert($error_message) {
    try {
        require_once dirname(__DIR__) . '/includes/email_system.php';
        $email_system = new EmailTemplateSystem();
        
        $subject = "Critical Error in Scheduled Email Processing";
        $body = "
        <h2>Critical Error Alert</h2>
        <p><strong>Date:</strong> " . date('Y-m-d H:i:s') . "</p>
        <p><strong>Error:</strong> " . htmlspecialchars($error_message) . "</p>
        <p><strong>System:</strong> Scheduled Email Processing Cron Job</p>
        <p>Please investigate this issue immediately.</p>
        ";
        
        // Get admin emails
        global $pdo;
        $stmt = $pdo->query("
            SELECT email, CONCAT(first_name, ' ', last_name) as name 
            FROM users 
            WHERE role IN ('admin', 'super_admin') AND status = 'active'
        ");
        
        $admins = $stmt->fetchAll();
        
        foreach ($admins as $admin) {
            $email_system->sendEmail(
                $admin['email'],
                $subject,
                $body,
                $admin['name']
            );
        }
        
    } catch (Exception $e) {
        echo "Failed to send critical error alert: " . $e->getMessage() . "\n";
    }
}

/**
 * Clean up old email queue entries (older than 30 days)
 */
function cleanupOldEmails() {
    global $pdo;
    
    try {
        $stmt = $pdo->prepare("
            DELETE FROM email_queue 
            WHERE status IN ('sent', 'failed', 'cancelled') 
            AND created_at < DATE_SUB(NOW(), INTERVAL 30 DAY)
        ");
        
        $stmt->execute();
        $deleted = $stmt->rowCount();
        
        if ($deleted > 0) {
            echo "[" . date('Y-m-d H:i:s') . "] Cleaned up {$deleted} old email records\n";
        }
        
    } catch (Exception $e) {
        echo "Error cleaning up old emails: " . $e->getMessage() . "\n";
    }
}

// Run cleanup
cleanupOldEmails();

?>