<?php
/**
 * System Tests Runner
 * LoanFlow Personal Loan Management System
 * 
 * Run this file via cron every hour:
 * 0 * * * * /usr/bin/php /path/to/run_system_tests.php
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
require_once __DIR__ . '/../includes/error_monitoring.php';

echo "[" . date('Y-m-d H:i:s') . "] Starting system tests...\n";

try {
    // Run comprehensive system tests
    $results = ErrorMonitoringManager::runSystemTests();
    
    echo "System Tests Completed:\n";
    echo "- Total Tests: {$results['total']}\n";
    echo "- Passed: {$results['passed']}\n";
    echo "- Failed: {$results['failed']}\n";
    
    if ($results['failed'] > 0) {
        echo "\nFailed Tests:\n";
        foreach ($results['tests'] as $test) {
            if (!$test['passed']) {
                echo "- {$test['name']}: {$test['message']}\n";
            }
        }
    }
    
    echo "\n[" . date('Y-m-d H:i:s') . "] System tests completed.\n";
    
    // Update system health status
    $health_status = $results['failed'] == 0 ? 'healthy' : 'warning';
    if ($results['failed'] > 3) {
        $health_status = 'critical';
    }
    
    updateSystemSetting('system_health', $health_status);
    updateSystemSetting('last_system_test', date('Y-m-d H:i:s'));
    
} catch (Exception $e) {
    $error_message = "System tests error: " . $e->getMessage();
    echo $error_message . "\n";
    error_log($error_message);
    
    // Update system health to critical
    updateSystemSetting('system_health', 'critical');
    
    // Send alert to admin
    $admin_email = getSystemSetting('admin_email', '');
    if (!empty($admin_email)) {
        sendAlertEmail($admin_email, 'System Tests Critical Error', $error_message);
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
                <p><em>This is an automated message from the LoanFlow system testing framework.</em></p>
            </div>
        </div>";
        
        sendEmail($to, $subject, $html_message, true);
        
    } catch (Exception $e) {
        error_log("Alert email error: " . $e->getMessage());
    }
}

echo "[" . date('Y-m-d H:i:s') . "] System tests script finished.\n";
?>
