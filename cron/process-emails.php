<?php
/**
 * Email Processing Cron Job
 * LoanFlow Personal Loan Management System
 * 
 * This script should be run every 5 minutes via cron:
 * */5 * * * * /usr/bin/php /path/to/loanflow/cron/process-emails.php
 */

// Prevent web access
if (isset($_SERVER['HTTP_HOST'])) {
    die('This script can only be run from command line');
}

require_once dirname(__DIR__) . '/includes/functions.php';
require_once dirname(__DIR__) . '/includes/email.php';

echo "[" . date('Y-m-d H:i:s') . "] Starting email processing...\n";

try {
    // Process automated emails (pre-approvals, reminders, etc.)
    $automated_count = processAutomatedEmails();
    echo "Processed $automated_count automated emails\n";
    
    // Process email queue
    $queue_count = processEmailQueue();
    echo "Sent $queue_count queued emails\n";
    
    // Clean up old sent emails (older than 30 days)
    $db = getDB();
    $cleanup_stmt = $db->prepare("
        DELETE FROM email_queue 
        WHERE status = 'sent' 
        AND sent_at < DATE_SUB(NOW(), INTERVAL 30 DAY)
    ");
    $cleanup_stmt->execute();
    $cleaned = $cleanup_stmt->rowCount();
    echo "Cleaned up $cleaned old email records\n";
    
    // Log statistics
    $email_manager = new EmailManager();
    $stats = $email_manager->getQueueStats();
    echo "Queue stats - Total: {$stats['total']}, Pending: {$stats['pending']}, Sent: {$stats['sent']}, Failed: {$stats['failed']}\n";
    
    echo "[" . date('Y-m-d H:i:s') . "] Email processing completed successfully\n\n";
    
} catch (Exception $e) {
    echo "[" . date('Y-m-d H:i:s') . "] ERROR: " . $e->getMessage() . "\n";
    error_log("Email cron job failed: " . $e->getMessage());
    exit(1);
}
?>
