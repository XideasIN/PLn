<?php
/**
 * Automated Tasks Cron Job
 * LoanFlow Personal Loan Management System
 * 
 * Run this file via cron every 5 minutes:
 * */5 * * * * /usr/bin/php /path/to/automated_tasks.php
 */

// Prevent web access
if (php_sapi_name() !== 'cli') {
    http_response_code(403);
    die('This script can only be run from command line.');
}

// Set up error reporting
error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('log_errors', 1);

// Include required files
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/ai_automation.php';
require_once __DIR__ . '/../includes/pre_approval_automation.php';
require_once __DIR__ . '/../includes/email.php';

echo "[" . date('Y-m-d H:i:s') . "] Starting automated tasks...\n";

try {
    // 1. Process loan applications with AI
    echo "Processing loan applications...\n";
    $processed_applications = AIAutomationManager::autoProcessApplications();
    echo "Processed {$processed_applications} applications.\n";
    
    // 2. Score new leads
    echo "Scoring leads...\n";
    $scored_leads = AIAutomationManager::scoreLeads();
    echo "Scored {$scored_leads} leads.\n";
    
    // 3. Process email queue
    echo "Processing email queue...\n";
    $emails_sent = processEmailQueue();
    echo "Sent {$emails_sent} emails.\n";
    
    // 4. Process pre-approval workflows
    echo "Processing pre-approval workflows...\n";
    $pre_approval_results = PreApprovalAutomation::processPreApprovalWorkflows();
    if (isset($pre_approval_results['error'])) {
        echo "Pre-approval automation error: {$pre_approval_results['error']}\n";
    } else {
        echo "Pre-approval automation: {$pre_approval_results['pre_approved']} pre-approved, {$pre_approval_results['document_reminders']} document reminders, {$pre_approval_results['agreement_reminders']} agreement reminders, {$pre_approval_results['funding_processed']} funding processed, {$pre_approval_results['expired_applications']} expired.\n";
    }
    
    // 5. Auto-respond to emails (if enabled)
    echo "Processing auto-responses...\n";
    $auto_responses = AIAutomationManager::autoRespondEmails();
    echo "Sent {$auto_responses} auto-responses.\n";
    
    // 6. Clean up old data (run once per day)
    $last_cleanup = getSystemSetting('last_cleanup_run', '1970-01-01');
    if (strtotime($last_cleanup) < strtotime('-1 day')) {
        echo "Running daily cleanup...\n";
        runDailyCleanup();
        updateSystemSetting('last_cleanup_run', date('Y-m-d H:i:s'));
        echo "Daily cleanup completed.\n";
    }
    
    // 7. Generate daily reports (run once per day)
    $last_report = getSystemSetting('last_daily_report', '1970-01-01');
    if (strtotime($last_report) < strtotime('-1 day')) {
        echo "Generating daily reports...\n";
        generateDailyReports();
        updateSystemSetting('last_daily_report', date('Y-m-d H:i:s'));
        echo "Daily reports generated.\n";
    }
    
    // 8. Update system statistics
    echo "Updating system statistics...\n";
    updateSystemStatistics();
    echo "System statistics updated.\n";
    
    echo "[" . date('Y-m-d H:i:s') . "] Automated tasks completed successfully.\n";
    
} catch (Exception $e) {
    $error_message = "Automated tasks error: " . $e->getMessage();
    echo $error_message . "\n";
    error_log($error_message);
    
    // Send alert to admin
    $admin_email = getSystemSetting('admin_email', '');
    if (!empty($admin_email)) {
        sendAlertEmail($admin_email, 'Automated Tasks Error', $error_message);
    }
}

/**
 * Run daily cleanup tasks
 */
function runDailyCleanup() {
    try {
        $db = getDB();
        
        // Clean old audit logs (keep 90 days)
        $stmt = $db->prepare("DELETE FROM audit_logs WHERE created_at < DATE_SUB(NOW(), INTERVAL 90 DAY)");
        $stmt->execute();
        $cleaned_audits = $stmt->rowCount();
        echo "Cleaned {$cleaned_audits} old audit log entries.\n";
        
        // Clean old security logs (keep 30 days)
        $stmt = $db->prepare("DELETE FROM security_logs WHERE created_at < DATE_SUB(NOW(), INTERVAL 30 DAY)");
        $stmt->execute();
        $cleaned_security = $stmt->rowCount();
        echo "Cleaned {$cleaned_security} old security log entries.\n";
        
        // Remove expired IP blacklist entries
        $stmt = $db->prepare("DELETE FROM ip_blacklist WHERE expires_at IS NOT NULL AND expires_at < NOW()");
        $stmt->execute();
        $cleaned_ips = $stmt->rowCount();
        echo "Removed {$cleaned_ips} expired IP blacklist entries.\n";
        
        // Clean old chatbot conversations (keep 30 days)
        $stmt = $db->prepare("DELETE FROM chatbot_conversations WHERE created_at < DATE_SUB(NOW(), INTERVAL 30 DAY)");
        $stmt->execute();
        $cleaned_chats = $stmt->rowCount();
        echo "Cleaned {$cleaned_chats} old chatbot conversations.\n";
        
        // Clean temporary files
        $temp_dir = sys_get_temp_dir();
        $files = glob($temp_dir . '/loanflow_*');
        $cleaned_files = 0;
        
        foreach ($files as $file) {
            if (is_file($file) && (time() - filemtime($file)) > 86400) { // 24 hours
                unlink($file);
                $cleaned_files++;
            }
        }
        echo "Cleaned {$cleaned_files} temporary files.\n";
        
        // Optimize database tables
        $tables = ['users', 'loan_applications', 'documents', 'payments', 'audit_logs', 'security_logs'];
        foreach ($tables as $table) {
            $stmt = $db->prepare("OPTIMIZE TABLE {$table}");
            $stmt->execute();
        }
        echo "Optimized database tables.\n";
        
    } catch (Exception $e) {
        throw new Exception("Daily cleanup error: " . $e->getMessage());
    }
}

/**
 * Generate daily reports
 */
function generateDailyReports() {
    try {
        $db = getDB();
        
        // Application statistics
        $stmt = $db->prepare("
            SELECT 
                COUNT(*) as total_applications,
                COUNT(CASE WHEN application_status = 'approved' THEN 1 END) as approved,
                COUNT(CASE WHEN application_status = 'rejected' THEN 1 END) as rejected,
                COUNT(CASE WHEN application_status = 'pending' THEN 1 END) as pending,
                AVG(loan_amount) as avg_loan_amount,
                SUM(CASE WHEN application_status = 'approved' THEN loan_amount ELSE 0 END) as approved_amount
            FROM loan_applications 
            WHERE DATE(created_at) = CURDATE() - INTERVAL 1 DAY
        ");
        $stmt->execute();
        $app_stats = $stmt->fetch(PDO::FETCH_ASSOC);
        
        // User statistics
        $stmt = $db->prepare("
            SELECT 
                COUNT(*) as new_users,
                COUNT(CASE WHEN email_verified = 1 THEN 1 END) as verified_users
            FROM users 
            WHERE DATE(created_at) = CURDATE() - INTERVAL 1 DAY
        ");
        $stmt->execute();
        $user_stats = $stmt->fetch(PDO::FETCH_ASSOC);
        
        // Security statistics
        $stmt = $db->prepare("
            SELECT 
                COUNT(*) as security_events,
                COUNT(DISTINCT ip_address) as unique_threats
            FROM security_logs 
            WHERE DATE(created_at) = CURDATE() - INTERVAL 1 DAY
        ");
        $stmt->execute();
        $security_stats = $stmt->fetch(PDO::FETCH_ASSOC);
        
        // Generate report content
        $report_date = date('Y-m-d', strtotime('-1 day'));
        $report_content = generateDailyReportContent($report_date, $app_stats, $user_stats, $security_stats);
        
        // Save report to file
        $report_dir = __DIR__ . '/../reports/daily';
        if (!is_dir($report_dir)) {
            mkdir($report_dir, 0755, true);
        }
        
        $report_file = $report_dir . '/report_' . $report_date . '.html';
        file_put_contents($report_file, $report_content);
        
        // Email report to admin
        $admin_email = getSystemSetting('admin_email', '');
        if (!empty($admin_email)) {
            sendTemplatedEmail($admin_email, 'daily_report', [
                'report_date' => $report_date,
                'total_applications' => $app_stats['total_applications'],
                'approved_applications' => $app_stats['approved'],
                'new_users' => $user_stats['new_users'],
                'security_events' => $security_stats['security_events']
            ]);
        }
        
        echo "Daily report generated for {$report_date}.\n";
        
    } catch (Exception $e) {
        throw new Exception("Daily report generation error: " . $e->getMessage());
    }
}

/**
 * Generate daily report content
 */
function generateDailyReportContent($date, $app_stats, $user_stats, $security_stats) {
    $html = "<!DOCTYPE html>
<html>
<head>
    <title>Daily Report - {$date}</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 20px; }
        .header { background: #007bff; color: white; padding: 20px; border-radius: 5px; }
        .stats { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 20px; margin: 20px 0; }
        .stat-card { background: #f8f9fa; padding: 15px; border-radius: 5px; border-left: 4px solid #007bff; }
        .stat-number { font-size: 2em; font-weight: bold; color: #007bff; }
        .section { margin: 30px 0; }
        .section h3 { color: #333; border-bottom: 2px solid #007bff; padding-bottom: 5px; }
    </style>
</head>
<body>
    <div class='header'>
        <h1>LoanFlow Daily Report</h1>
        <p>Report Date: {$date}</p>
    </div>
    
    <div class='section'>
        <h3>Application Statistics</h3>
        <div class='stats'>
            <div class='stat-card'>
                <div class='stat-number'>{$app_stats['total_applications']}</div>
                <div>Total Applications</div>
            </div>
            <div class='stat-card'>
                <div class='stat-number'>{$app_stats['approved']}</div>
                <div>Approved</div>
            </div>
            <div class='stat-card'>
                <div class='stat-number'>{$app_stats['rejected']}</div>
                <div>Rejected</div>
            </div>
            <div class='stat-card'>
                <div class='stat-number'>{$app_stats['pending']}</div>
                <div>Pending</div>
            </div>
        </div>
    </div>
    
    <div class='section'>
        <h3>User Statistics</h3>
        <div class='stats'>
            <div class='stat-card'>
                <div class='stat-number'>{$user_stats['new_users']}</div>
                <div>New Users</div>
            </div>
            <div class='stat-card'>
                <div class='stat-number'>{$user_stats['verified_users']}</div>
                <div>Verified Users</div>
            </div>
        </div>
    </div>
    
    <div class='section'>
        <h3>Security Statistics</h3>
        <div class='stats'>
            <div class='stat-card'>
                <div class='stat-number'>{$security_stats['security_events']}</div>
                <div>Security Events</div>
            </div>
            <div class='stat-card'>
                <div class='stat-number'>{$security_stats['unique_threats']}</div>
                <div>Unique Threats</div>
            </div>
        </div>
    </div>
    
    <div class='section'>
        <h3>Financial Summary</h3>
        <div class='stats'>
            <div class='stat-card'>
                <div class='stat-number'>$" . number_format($app_stats['avg_loan_amount'] ?? 0) . "</div>
                <div>Average Loan Amount</div>
            </div>
            <div class='stat-card'>
                <div class='stat-number'>$" . number_format($app_stats['approved_amount'] ?? 0) . "</div>
                <div>Total Approved Amount</div>
            </div>
        </div>
    </div>
    
    <div class='section'>
        <p><em>Report generated automatically by LoanFlow AI Automation System</em></p>
    </div>
</body>
</html>";

    return $html;
}

/**
 * Update system statistics
 */
function updateSystemStatistics() {
    try {
        $db = getDB();
        
        // Update application statistics
        $stmt = $db->prepare("
            SELECT 
                COUNT(*) as total,
                COUNT(CASE WHEN application_status = 'approved' THEN 1 END) as approved,
                COUNT(CASE WHEN application_status = 'rejected' THEN 1 END) as rejected,
                COUNT(CASE WHEN application_status = 'pending' THEN 1 END) as pending
            FROM loan_applications
        ");
        $stmt->execute();
        $stats = $stmt->fetch(PDO::FETCH_ASSOC);
        
        updateSystemSetting('total_applications', $stats['total']);
        updateSystemSetting('approved_applications', $stats['approved']);
        updateSystemSetting('rejected_applications', $stats['rejected']);
        updateSystemSetting('pending_applications', $stats['pending']);
        
        // Update user statistics
        $stmt = $db->prepare("SELECT COUNT(*) FROM users");
        $stmt->execute();
        $total_users = $stmt->fetchColumn();
        updateSystemSetting('total_users', $total_users);
        
        // Update system health metrics
        updateSystemSetting('last_cron_run', date('Y-m-d H:i:s'));
        updateSystemSetting('system_status', 'operational');
        
    } catch (Exception $e) {
        throw new Exception("System statistics update error: " . $e->getMessage());
    }
}

/**
 * Send alert email
 */
function sendAlertEmail($to, $subject, $message) {
    try {
        $html_message = "
        <div style='font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto;'>
            <div style='background: #dc3545; color: white; padding: 20px; border-radius: 5px 5px 0 0;'>
                <h2 style='margin: 0;'>System Alert</h2>
            </div>
            <div style='background: #f8f9fa; padding: 20px; border: 1px solid #dee2e6; border-top: none; border-radius: 0 0 5px 5px;'>
                <h3>Alert Details:</h3>
                <p><strong>Time:</strong> " . date('Y-m-d H:i:s T') . "</p>
                <p><strong>Message:</strong></p>
                <div style='background: white; padding: 15px; border-left: 4px solid #dc3545; margin: 10px 0;'>
                    " . nl2br(htmlspecialchars($message)) . "
                </div>
                <p><em>This is an automated message from the LoanFlow system.</em></p>
            </div>
        </div>";
        
        sendEmail($to, $subject, $html_message, true);
        
    } catch (Exception $e) {
        error_log("Alert email error: " . $e->getMessage());
    }
}

echo "[" . date('Y-m-d H:i:s') . "] Automated tasks script finished.\n";
?>
