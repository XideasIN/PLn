<?php
/**
 * Daily Tasks Cron Job
 * LoanFlow Personal Loan Management System
 * 
 * This script should be run once daily at 6 AM:
 * 0 6 * * * /usr/bin/php /path/to/loanflow/cron/daily-tasks.php
 */

// Prevent web access
if (isset($_SERVER['HTTP_HOST'])) {
    die('This script can only be run from command line');
}

require_once dirname(__DIR__) . '/includes/functions.php';
require_once dirname(__DIR__) . '/includes/email.php';
require_once dirname(__DIR__) . '/includes/backup_manager.php';

echo "[" . date('Y-m-d H:i:s') . "] Starting daily tasks...\n";

try {
    $db = getDB();
    $tasks_completed = 0;
    
    // 1. Clean up expired account locks
    $unlock_stmt = $db->prepare("
        UPDATE users 
        SET locked_until = NULL, failed_login_attempts = 0 
        WHERE locked_until IS NOT NULL 
        AND locked_until < NOW()
    ");
    $unlock_stmt->execute();
    $unlocked = $unlock_stmt->rowCount();
    echo "Unlocked $unlocked expired account locks\n";
    $tasks_completed++;
    
    // 2. Update application statuses based on business rules
    // Move document_review to approved after 48 hours if all docs verified
    $auto_approve_stmt = $db->query("
        SELECT la.*, COUNT(d.id) as doc_count, COUNT(CASE WHEN d.upload_status = 'verified' THEN 1 END) as verified_count
        FROM loan_applications la
        LEFT JOIN documents d ON la.user_id = d.user_id
        WHERE la.application_status = 'document_review'
        AND la.updated_at <= DATE_SUB(NOW(), INTERVAL 48 HOUR)
        GROUP BY la.id
        HAVING doc_count >= 3 AND verified_count = doc_count
    ");
    $auto_approvals = $auto_approve_stmt->fetchAll();
    
    foreach ($auto_approvals as $app) {
        $approve_stmt = $db->prepare("
            UPDATE loan_applications 
            SET application_status = 'approved', current_step = 4 
            WHERE id = ?
        ");
        $approve_stmt->execute([$app['id']]);
        
        // Send approval email
        $user_data = getUserById($app['user_id']);
        sendApprovalEmail($app['user_id'], $user_data, $app);
        
        echo "Auto-approved application {$app['reference_number']}\n";
    }
    $tasks_completed++;
    
    // 3. Clean up old call list entries
    $cleanup_calls_stmt = $db->prepare("
        DELETE FROM call_lists 
        WHERE status = 'completed' 
        AND updated_at < DATE_SUB(NOW(), INTERVAL 30 DAY)
    ");
    $cleanup_calls_stmt->execute();
    $cleaned_calls = $cleanup_calls_stmt->rowCount();
    echo "Cleaned up $cleaned_calls old call list entries\n";
    $tasks_completed++;
    
    // 4. Generate daily statistics
    $stats = [
        'date' => date('Y-m-d'),
        'new_applications' => 0,
        'approved_applications' => 0,
        'documents_uploaded' => 0,
        'payments_received' => 0,
        'emails_sent' => 0
    ];
    
    // New applications today
    $new_apps_stmt = $db->query("
        SELECT COUNT(*) as count 
        FROM loan_applications 
        WHERE DATE(created_at) = CURDATE()
    ");
    $stats['new_applications'] = $new_apps_stmt->fetch()['count'];
    
    // Approved applications today
    $approved_stmt = $db->query("
        SELECT COUNT(*) as count 
        FROM loan_applications 
        WHERE application_status = 'approved' 
        AND DATE(updated_at) = CURDATE()
    ");
    $stats['approved_applications'] = $approved_stmt->fetch()['count'];
    
    // Documents uploaded today
    $docs_stmt = $db->query("
        SELECT COUNT(*) as count 
        FROM documents 
        WHERE DATE(created_at) = CURDATE()
    ");
    $stats['documents_uploaded'] = $docs_stmt->fetch()['count'];
    
    // Payments received today
    $payments_stmt = $db->query("
        SELECT COUNT(*) as count 
        FROM payments 
        WHERE payment_status = 'completed' 
        AND DATE(created_at) = CURDATE()
    ");
    $stats['payments_received'] = $payments_stmt->fetch()['count'];
    
    // Emails sent today
    $emails_stmt = $db->query("
        SELECT COUNT(*) as count 
        FROM email_queue 
        WHERE status = 'sent' 
        AND DATE(sent_at) = CURDATE()
    ");
    $stats['emails_sent'] = $emails_stmt->fetch()['count'];
    
    echo "Daily stats: " . json_encode($stats) . "\n";
    $tasks_completed++;
    
    // 5. Update holiday calendar for next year (run in December)
    if (date('m') == 12) {
        $next_year = date('Y') + 1;
        updateHolidayCalendar($next_year);
        echo "Updated holiday calendar for $next_year\n";
    }
    $tasks_completed++;
    
    // 6. Send daily summary email to admins
    sendDailySummaryToAdmins($stats);
    echo "Sent daily summary to administrators\n";
    $tasks_completed++;
    
    // 7. Backup database (if enabled)
    if (getSystemSetting('auto_backup_enabled', false)) {
        $backup_result = createDatabaseBackup();
        if ($backup_result) {
            echo "Database backup created successfully\n";
        } else {
            echo "Database backup failed\n";
        }
    }
    $tasks_completed++;
    
    echo "[" . date('Y-m-d H:i:s') . "] Daily tasks completed successfully ($tasks_completed tasks)\n\n";
    
} catch (Exception $e) {
    echo "[" . date('Y-m-d H:i:s') . "] ERROR: " . $e->getMessage() . "\n";
    error_log("Daily tasks cron job failed: " . $e->getMessage());
    exit(1);
}

// Helper functions
function updateHolidayCalendar($year) {
    try {
        $db = getDB();
        
        // US holidays
        $us_holidays = [
            "$year-01-01" => "New Year's Day",
            "$year-07-04" => "Independence Day",
            "$year-12-25" => "Christmas Day"
        ];
        
        // Canadian holidays
        $ca_holidays = [
            "$year-01-01" => "New Year's Day",
            "$year-07-01" => "Canada Day",
            "$year-12-25" => "Christmas Day"
        ];
        
        // UK holidays
        $uk_holidays = [
            "$year-01-01" => "New Year's Day",
            "$year-12-25" => "Christmas Day",
            "$year-12-26" => "Boxing Day"
        ];
        
        // Australian holidays
        $au_holidays = [
            "$year-01-01" => "New Year's Day",
            "$year-01-26" => "Australia Day",
            "$year-12-25" => "Christmas Day",
            "$year-12-26" => "Boxing Day"
        ];
        
        $all_holidays = [
            'USA' => $us_holidays,
            'CAN' => $ca_holidays,
            'GBR' => $uk_holidays,
            'AUS' => $au_holidays
        ];
        
        foreach ($all_holidays as $country => $holidays) {
            foreach ($holidays as $date => $name) {
                $stmt = $db->prepare("
                    INSERT IGNORE INTO holidays (country_code, holiday_name, holiday_date, year) 
                    VALUES (?, ?, ?, ?)
                ");
                $stmt->execute([$country, $name, $date, $year]);
            }
        }
        
        return true;
    } catch (Exception $e) {
        error_log("Update holiday calendar failed: " . $e->getMessage());
        return false;
    }
}

function sendDailySummaryToAdmins($stats) {
    try {
        $db = getDB();
        
        // Get admin users
        $stmt = $db->query("SELECT * FROM users WHERE role IN ('admin', 'super_admin') AND status = 'active'");
        $admins = $stmt->fetchAll();
        
        $subject = "LoanFlow Daily Summary - " . date('F j, Y');
        $body = "
<h2>Daily Summary Report</h2>
<p>Here's your daily summary for " . date('F j, Y') . ":</p>

<table style='border-collapse: collapse; width: 100%;'>
    <tr style='background: #f8f9fa;'>
        <td style='padding: 10px; border: 1px solid #dee2e6;'><strong>Metric</strong></td>
        <td style='padding: 10px; border: 1px solid #dee2e6;'><strong>Count</strong></td>
    </tr>
    <tr>
        <td style='padding: 10px; border: 1px solid #dee2e6;'>New Applications</td>
        <td style='padding: 10px; border: 1px solid #dee2e6;'>{$stats['new_applications']}</td>
    </tr>
    <tr>
        <td style='padding: 10px; border: 1px solid #dee2e6;'>Approved Applications</td>
        <td style='padding: 10px; border: 1px solid #dee2e6;'>{$stats['approved_applications']}</td>
    </tr>
    <tr>
        <td style='padding: 10px; border: 1px solid #dee2e6;'>Documents Uploaded</td>
        <td style='padding: 10px; border: 1px solid #dee2e6;'>{$stats['documents_uploaded']}</td>
    </tr>
    <tr>
        <td style='padding: 10px; border: 1px solid #dee2e6;'>Payments Received</td>
        <td style='padding: 10px; border: 1px solid #dee2e6;'>{$stats['payments_received']}</td>
    </tr>
    <tr>
        <td style='padding: 10px; border: 1px solid #dee2e6;'>Emails Sent</td>
        <td style='padding: 10px; border: 1px solid #dee2e6;'>{$stats['emails_sent']}</td>
    </tr>
</table>

<p><a href='" . getBaseUrl() . "/admin/' style='background: #0d6efd; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px;'>View Admin Dashboard</a></p>

<p>Best regards,<br>LoanFlow System</p>
        ";
        
        $email_manager = new EmailManager();
        
        foreach ($admins as $admin) {
            $email_manager->sendEmail($admin['email'], $subject, $body);
        }
        
        return true;
    } catch (Exception $e) {
        error_log("Send daily summary failed: " . $e->getMessage());
        return false;
    }
}

function createDatabaseBackup() {
    try {
        $backup_dir = dirname(__DIR__) . '/backups';
        if (!is_dir($backup_dir)) {
            mkdir($backup_dir, 0755, true);
        }
        
        $filename = 'loanflow_backup_' . date('Y-m-d_H-i-s') . '.sql';
        $filepath = $backup_dir . '/' . $filename;
        
        $command = sprintf(
            'mysqldump -h%s -u%s -p%s %s > %s',
            DB_HOST,
            DB_USER,
            DB_PASS,
            DB_NAME,
            escapeshellarg($filepath)
        );
        
        exec($command, $output, $return_code);
        
        if ($return_code === 0 && file_exists($filepath)) {
            // Keep only last 7 days of backups
            $files = glob($backup_dir . '/loanflow_backup_*.sql');
            if (count($files) > 7) {
                array_multisort(array_map('filemtime', $files), SORT_ASC, $files);
                foreach (array_slice($files, 0, -7) as $old_file) {
                    unlink($old_file);
                }
            }
            
            return true;
        }
        
        return false;
    } catch (Exception $e) {
        error_log("Database backup failed: " . $e->getMessage());
        return false;
    }
}

// Add backup management to daily tasks
try {
    // Check for scheduled weekly backups
    $backup_result = BackupManager::weeklyBackupCheck();
    if ($backup_result !== false) {
        if ($backup_result['success']) {
            echo "✓ Weekly backup created: " . $backup_result['backup_info']['name'] . "\n";
        } else {
            echo "✗ Weekly backup failed: " . $backup_result['error'] . "\n";
        }
    }
    
    // Clean old backups (retention policy)
    BackupManager::cleanOldBackups();
    echo "✓ Checked backup retention policy\n";
    
} catch (Exception $e) {
    echo "✗ Backup management error: " . $e->getMessage() . "\n";
    error_log("Backup management error: " . $e->getMessage());
}
?>
