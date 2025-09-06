<?php
/**
 * Email Workflow Processor - Cron Job
 * Processes automated email workflows, triggers, and campaigns
 * Run this script every 5-15 minutes via cron job
 */

// Prevent direct web access
if (isset($_SERVER['HTTP_HOST'])) {
    die('This script can only be run from command line.');
}

// Set execution time limit
set_time_limit(300); // 5 minutes
ini_set('memory_limit', '256M');

// Include required files
require_once dirname(__DIR__) . '/config/database.php';
require_once dirname(__DIR__) . '/includes/email_workflow_engine.php';
require_once dirname(__DIR__) . '/includes/functions.php';

// Log start time
$startTime = microtime(true);
$logFile = dirname(__DIR__) . '/logs/email_workflow_' . date('Y-m-d') . '.log';

// Ensure logs directory exists
$logsDir = dirname($logFile);
if (!is_dir($logsDir)) {
    mkdir($logsDir, 0755, true);
}

function logMessage($message, $level = 'INFO') {
    global $logFile;
    $timestamp = date('Y-m-d H:i:s');
    $logEntry = "[{$timestamp}] [{$level}] {$message}" . PHP_EOL;
    file_put_contents($logFile, $logEntry, FILE_APPEND | LOCK_EX);
    
    // Also output to console if running from CLI
    if (php_sapi_name() === 'cli') {
        echo $logEntry;
    }
}

try {
    logMessage("Starting email workflow processing...");
    
    // Initialize workflow engine
    $workflowEngine = new EmailWorkflowEngine();
    
    // Process workflows
    $result = $workflowEngine->processWorkflows();
    
    if ($result['success']) {
        logMessage("Workflow processing completed successfully");
        
        // Get processing statistics
        $analytics = $workflowEngine->getAnalytics(1); // Last 24 hours
        logMessage("Statistics - Sent: {$analytics['sent_emails']}, Failed: {$analytics['failed_emails']}, Pending: {$analytics['pending_emails']}");
        
    } else {
        logMessage("Workflow processing failed: " . $result['error'], 'ERROR');
    }
    
} catch (Exception $e) {
    logMessage("Fatal error in workflow processing: " . $e->getMessage(), 'ERROR');
    logMessage("Stack trace: " . $e->getTraceAsString(), 'ERROR');
    
    // Send alert email to admin if critical error
    try {
        $adminEmail = getSystemSetting('admin_email', '');
        if ($adminEmail) {
            $subject = 'Email Workflow Processing Error - ' . date('Y-m-d H:i:s');
            $message = "An error occurred during email workflow processing:\n\n";
            $message .= "Error: " . $e->getMessage() . "\n";
            $message .= "File: " . $e->getFile() . "\n";
            $message .= "Line: " . $e->getLine() . "\n";
            $message .= "Time: " . date('Y-m-d H:i:s') . "\n";
            
            mail($adminEmail, $subject, $message);
        }
    } catch (Exception $mailError) {
        logMessage("Failed to send error notification email: " . $mailError->getMessage(), 'ERROR');
    }
}

// Log completion time
$endTime = microtime(true);
$executionTime = round($endTime - $startTime, 2);
logMessage("Email workflow processing completed in {$executionTime} seconds");

// Clean up old log files (keep last 30 days)
try {
    $logDir = dirname($logFile);
    $files = glob($logDir . '/email_workflow_*.log');
    $cutoffTime = time() - (30 * 24 * 60 * 60); // 30 days ago
    
    foreach ($files as $file) {
        if (filemtime($file) < $cutoffTime) {
            unlink($file);
            logMessage("Cleaned up old log file: " . basename($file));
        }
    }
} catch (Exception $e) {
    logMessage("Error cleaning up log files: " . $e->getMessage(), 'WARNING');
}

?>