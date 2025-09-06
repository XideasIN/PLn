<?php
/**
 * Error Testing and Monitoring System
 * LoanFlow Personal Loan Management System
 */

class ErrorMonitoringManager {
    
    private static $enabled = true;
    private static $log_errors = true;
    private static $email_alerts = true;
    private static $performance_monitoring = true;
    
    /**
     * Initialize error monitoring system
     */
    public static function init() {
        self::$enabled = getSystemSetting('error_monitoring_enabled', '1') === '1';
        self::$log_errors = getSystemSetting('error_logging_enabled', '1') === '1';
        self::$email_alerts = getSystemSetting('error_email_alerts', '1') === '1';
        self::$performance_monitoring = getSystemSetting('performance_monitoring', '1') === '1';
        
        if (self::$enabled) {
            self::setupErrorHandling();
            self::startPerformanceMonitoring();
        }
    }
    
    /**
     * Set up comprehensive error handling
     */
    private static function setupErrorHandling() {
        // Set custom error handler
        set_error_handler([self::class, 'handleError']);
        
        // Set custom exception handler
        set_exception_handler([self::class, 'handleException']);
        
        // Set shutdown function to catch fatal errors
        register_shutdown_function([self::class, 'handleShutdown']);
        
        // Configure error reporting
        error_reporting(E_ALL);
        ini_set('display_errors', 0); // Don't display errors to users
        ini_set('log_errors', 1);
        ini_set('error_log', __DIR__ . '/../logs/php_errors.log');
    }
    
    /**
     * Custom error handler
     */
    public static function handleError($severity, $message, $file, $line) {
        if (!(error_reporting() & $severity)) {
            return false;
        }
        
        $error_data = [
            'type' => 'PHP Error',
            'severity' => self::getSeverityName($severity),
            'message' => $message,
            'file' => $file,
            'line' => $line,
            'trace' => debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS),
            'timestamp' => date('Y-m-d H:i:s'),
            'url' => $_SERVER['REQUEST_URI'] ?? 'CLI',
            'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'Unknown',
            'ip_address' => self::getRealIP(),
            'user_id' => self::getCurrentUserId()
        ];
        
        self::logError($error_data);
        
        // Send alert for critical errors
        if (in_array($severity, [E_ERROR, E_CORE_ERROR, E_COMPILE_ERROR, E_USER_ERROR])) {
            self::sendErrorAlert($error_data);
        }
        
        return true;
    }
    
    /**
     * Custom exception handler
     */
    public static function handleException($exception) {
        $error_data = [
            'type' => 'Uncaught Exception',
            'severity' => 'Fatal',
            'message' => $exception->getMessage(),
            'file' => $exception->getFile(),
            'line' => $exception->getLine(),
            'trace' => $exception->getTrace(),
            'timestamp' => date('Y-m-d H:i:s'),
            'url' => $_SERVER['REQUEST_URI'] ?? 'CLI',
            'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'Unknown',
            'ip_address' => self::getRealIP(),
            'user_id' => self::getCurrentUserId()
        ];
        
        self::logError($error_data);
        self::sendErrorAlert($error_data);
        
        // Show user-friendly error page
        if (!headers_sent()) {
            self::showErrorPage();
        }
    }
    
    /**
     * Handle shutdown errors (fatal errors)
     */
    public static function handleShutdown() {
        $error = error_get_last();
        
        if ($error && in_array($error['type'], [E_ERROR, E_CORE_ERROR, E_COMPILE_ERROR, E_PARSE])) {
            $error_data = [
                'type' => 'Fatal Error',
                'severity' => 'Fatal',
                'message' => $error['message'],
                'file' => $error['file'],
                'line' => $error['line'],
                'trace' => [],
                'timestamp' => date('Y-m-d H:i:s'),
                'url' => $_SERVER['REQUEST_URI'] ?? 'CLI',
                'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'Unknown',
                'ip_address' => self::getRealIP(),
                'user_id' => self::getCurrentUserId()
            ];
            
            self::logError($error_data);
            self::sendErrorAlert($error_data);
        }
        
        // Log performance metrics
        if (self::$performance_monitoring) {
            self::logPerformanceMetrics();
        }
    }
    
    /**
     * Log error to database and file
     */
    private static function logError($error_data) {
        if (!self::$log_errors) {
            return;
        }
        
        try {
            // Log to database
            $db = getDB();
            $stmt = $db->prepare("
                INSERT INTO error_logs 
                (error_type, severity, message, file, line, trace, url, user_agent, ip_address, user_id, created_at) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())
            ");
            
            $stmt->execute([
                $error_data['type'],
                $error_data['severity'],
                $error_data['message'],
                $error_data['file'],
                $error_data['line'],
                json_encode($error_data['trace']),
                $error_data['url'],
                $error_data['user_agent'],
                $error_data['ip_address'],
                $error_data['user_id']
            ]);
            
        } catch (Exception $e) {
            // Fallback to file logging if database fails
            error_log("Database error logging failed: " . $e->getMessage());
        }
        
        // Log to file
        $log_entry = sprintf(
            "[%s] %s: %s in %s on line %d\n",
            $error_data['timestamp'],
            $error_data['severity'],
            $error_data['message'],
            $error_data['file'],
            $error_data['line']
        );
        
        $log_file = __DIR__ . '/../logs/errors.log';
        file_put_contents($log_file, $log_entry, FILE_APPEND | LOCK_EX);
    }
    
    /**
     * Send error alert email
     */
    private static function sendErrorAlert($error_data) {
        if (!self::$email_alerts) {
            return;
        }
        
        $admin_email = getSystemSetting('admin_email', '');
        if (empty($admin_email)) {
            return;
        }
        
        // Rate limit alerts (max 5 per hour)
        $rate_limit_key = 'error_alerts_' . date('YmdH');
        $alert_count = apcu_fetch($rate_limit_key) ?: 0;
        
        if ($alert_count >= 5) {
            return;
        }
        
        apcu_store($rate_limit_key, $alert_count + 1, 3600);
        
        $subject = 'LoanFlow Error Alert: ' . $error_data['severity'] . ' - ' . $error_data['type'];
        
        $message = "
        <div style='font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto;'>
            <div style='background: #dc3545; color: white; padding: 20px; border-radius: 5px 5px 0 0;'>
                <h2 style='margin: 0;'>Error Alert</h2>
            </div>
            <div style='background: #f8f9fa; padding: 20px; border: 1px solid #dee2e6; border-top: none; border-radius: 0 0 5px 5px;'>
                <table style='width: 100%; border-collapse: collapse;'>
                    <tr>
                        <td style='padding: 8px; border-bottom: 1px solid #dee2e6; font-weight: bold; width: 120px;'>Type:</td>
                        <td style='padding: 8px; border-bottom: 1px solid #dee2e6;'>{$error_data['type']}</td>
                    </tr>
                    <tr>
                        <td style='padding: 8px; border-bottom: 1px solid #dee2e6; font-weight: bold;'>Severity:</td>
                        <td style='padding: 8px; border-bottom: 1px solid #dee2e6;'>{$error_data['severity']}</td>
                    </tr>
                    <tr>
                        <td style='padding: 8px; border-bottom: 1px solid #dee2e6; font-weight: bold;'>Message:</td>
                        <td style='padding: 8px; border-bottom: 1px solid #dee2e6;'>{$error_data['message']}</td>
                    </tr>
                    <tr>
                        <td style='padding: 8px; border-bottom: 1px solid #dee2e6; font-weight: bold;'>File:</td>
                        <td style='padding: 8px; border-bottom: 1px solid #dee2e6;'>{$error_data['file']}</td>
                    </tr>
                    <tr>
                        <td style='padding: 8px; border-bottom: 1px solid #dee2e6; font-weight: bold;'>Line:</td>
                        <td style='padding: 8px; border-bottom: 1px solid #dee2e6;'>{$error_data['line']}</td>
                    </tr>
                    <tr>
                        <td style='padding: 8px; border-bottom: 1px solid #dee2e6; font-weight: bold;'>URL:</td>
                        <td style='padding: 8px; border-bottom: 1px solid #dee2e6;'>{$error_data['url']}</td>
                    </tr>
                    <tr>
                        <td style='padding: 8px; border-bottom: 1px solid #dee2e6; font-weight: bold;'>IP:</td>
                        <td style='padding: 8px; border-bottom: 1px solid #dee2e6;'>{$error_data['ip_address']}</td>
                    </tr>
                    <tr>
                        <td style='padding: 8px; border-bottom: 1px solid #dee2e6; font-weight: bold;'>Time:</td>
                        <td style='padding: 8px; border-bottom: 1px solid #dee2e6;'>{$error_data['timestamp']}</td>
                    </tr>
                </table>
                
                <p style='margin-top: 20px;'><em>This is an automated error alert from the LoanFlow system.</em></p>
            </div>
        </div>";
        
        try {
            sendEmail($admin_email, $subject, $message, true);
        } catch (Exception $e) {
            error_log("Failed to send error alert: " . $e->getMessage());
        }
    }
    
    /**
     * Start performance monitoring
     */
    private static function startPerformanceMonitoring() {
        if (!self::$performance_monitoring) {
            return;
        }
        
        // Store start time and memory usage
        if (!defined('PERFORMANCE_START_TIME')) {
            define('PERFORMANCE_START_TIME', microtime(true));
            define('PERFORMANCE_START_MEMORY', memory_get_usage(true));
        }
    }
    
    /**
     * Log performance metrics
     */
    private static function logPerformanceMetrics() {
        if (!self::$performance_monitoring || !defined('PERFORMANCE_START_TIME')) {
            return;
        }
        
        $execution_time = microtime(true) - PERFORMANCE_START_TIME;
        $memory_usage = memory_get_usage(true);
        $peak_memory = memory_get_peak_usage(true);
        $memory_delta = $memory_usage - PERFORMANCE_START_MEMORY;
        
        $metrics = [
            'url' => $_SERVER['REQUEST_URI'] ?? 'CLI',
            'method' => $_SERVER['REQUEST_METHOD'] ?? 'CLI',
            'execution_time' => round($execution_time * 1000, 2), // milliseconds
            'memory_usage' => $memory_usage,
            'peak_memory' => $peak_memory,
            'memory_delta' => $memory_delta,
            'queries_count' => self::getQueryCount(),
            'user_id' => self::getCurrentUserId(),
            'ip_address' => self::getRealIP()
        ];
        
        try {
            $db = getDB();
            $stmt = $db->prepare("
                INSERT INTO performance_logs 
                (url, method, execution_time, memory_usage, peak_memory, memory_delta, queries_count, user_id, ip_address, created_at) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())
            ");
            
            $stmt->execute([
                $metrics['url'],
                $metrics['method'],
                $metrics['execution_time'],
                $metrics['memory_usage'],
                $metrics['peak_memory'],
                $metrics['memory_delta'],
                $metrics['queries_count'],
                $metrics['user_id'],
                $metrics['ip_address']
            ]);
            
            // Alert for slow requests (over 5 seconds)
            if ($execution_time > 5) {
                self::alertSlowRequest($metrics);
            }
            
            // Alert for high memory usage (over 128MB)
            if ($peak_memory > 134217728) {
                self::alertHighMemoryUsage($metrics);
            }
            
        } catch (Exception $e) {
            error_log("Performance logging error: " . $e->getMessage());
        }
    }
    
    /**
     * Run automated system tests
     */
    public static function runSystemTests() {
        $results = [
            'timestamp' => date('Y-m-d H:i:s'),
            'tests' => [],
            'passed' => 0,
            'failed' => 0,
            'total' => 0
        ];
        
        // Database connectivity test
        $results['tests'][] = self::testDatabaseConnectivity();
        
        // Email system test
        $results['tests'][] = self::testEmailSystem();
        
        // File permissions test
        $results['tests'][] = self::testFilePermissions();
        
        // API endpoints test
        $results['tests'][] = self::testAPIEndpoints();
        
        // Security headers test
        $results['tests'][] = self::testSecurityHeaders();
        
        // Performance test
        $results['tests'][] = self::testPerformance();
        
        // SSL certificate test
        $results['tests'][] = self::testSSLCertificate();
        
        // Disk space test
        $results['tests'][] = self::testDiskSpace();
        
        // Calculate totals
        foreach ($results['tests'] as $test) {
            $results['total']++;
            if ($test['passed']) {
                $results['passed']++;
            } else {
                $results['failed']++;
            }
        }
        
        // Save test results
        self::saveTestResults($results);
        
        // Send alert if any tests failed
        if ($results['failed'] > 0) {
            self::sendTestFailureAlert($results);
        }
        
        return $results;
    }
    
    /**
     * Test database connectivity
     */
    private static function testDatabaseConnectivity() {
        $test = [
            'name' => 'Database Connectivity',
            'passed' => false,
            'message' => '',
            'execution_time' => 0
        ];
        
        $start_time = microtime(true);
        
        try {
            $db = getDB();
            $stmt = $db->query("SELECT 1");
            $result = $stmt->fetchColumn();
            
            if ($result === 1) {
                $test['passed'] = true;
                $test['message'] = 'Database connection successful';
            } else {
                $test['message'] = 'Database query returned unexpected result';
            }
            
        } catch (Exception $e) {
            $test['message'] = 'Database connection failed: ' . $e->getMessage();
        }
        
        $test['execution_time'] = round((microtime(true) - $start_time) * 1000, 2);
        return $test;
    }
    
    /**
     * Test email system
     */
    private static function testEmailSystem() {
        $test = [
            'name' => 'Email System',
            'passed' => false,
            'message' => '',
            'execution_time' => 0
        ];
        
        $start_time = microtime(true);
        
        try {
            $smtp_host = getSystemSetting('smtp_host', '');
            
            if (empty($smtp_host)) {
                $test['message'] = 'SMTP not configured';
            } else {
                // Test SMTP connection
                $connection = @fsockopen($smtp_host, 587, $errno, $errstr, 5);
                if ($connection) {
                    fclose($connection);
                    $test['passed'] = true;
                    $test['message'] = 'SMTP server reachable';
                } else {
                    $test['message'] = "SMTP connection failed: $errstr ($errno)";
                }
            }
            
        } catch (Exception $e) {
            $test['message'] = 'Email system test failed: ' . $e->getMessage();
        }
        
        $test['execution_time'] = round((microtime(true) - $start_time) * 1000, 2);
        return $test;
    }
    
    /**
     * Test file permissions
     */
    private static function testFilePermissions() {
        $test = [
            'name' => 'File Permissions',
            'passed' => true,
            'message' => 'All permissions correct',
            'execution_time' => 0
        ];
        
        $start_time = microtime(true);
        
        $directories_to_check = [
            __DIR__ . '/../uploads' => 0755,
            __DIR__ . '/../logs' => 0755,
            __DIR__ . '/../backups' => 0755,
            __DIR__ . '/../temp' => 0755
        ];
        
        $issues = [];
        
        foreach ($directories_to_check as $dir => $expected_perm) {
            if (!is_dir($dir)) {
                $issues[] = "Directory does not exist: $dir";
                continue;
            }
            
            if (!is_writable($dir)) {
                $issues[] = "Directory not writable: $dir";
            }
        }
        
        if (!empty($issues)) {
            $test['passed'] = false;
            $test['message'] = implode(', ', $issues);
        }
        
        $test['execution_time'] = round((microtime(true) - $start_time) * 1000, 2);
        return $test;
    }
    
    /**
     * Test API endpoints
     */
    private static function testAPIEndpoints() {
        $test = [
            'name' => 'API Endpoints',
            'passed' => true,
            'message' => 'All endpoints responding',
            'execution_time' => 0
        ];
        
        $start_time = microtime(true);
        
        $endpoints = [
            '/api/chatbot.php' => 'POST',
        ];
        
        $issues = [];
        
        foreach ($endpoints as $endpoint => $method) {
            $url = getBaseUrl() . $endpoint;
            
            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, $url);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_TIMEOUT, 5);
            curl_setopt($ch, CURLOPT_NOBODY, true); // HEAD request
            
            $response = curl_exec($ch);
            $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);
            
            if ($http_code === 0 || $http_code >= 500) {
                $issues[] = "Endpoint $endpoint returned HTTP $http_code";
            }
        }
        
        if (!empty($issues)) {
            $test['passed'] = false;
            $test['message'] = implode(', ', $issues);
        }
        
        $test['execution_time'] = round((microtime(true) - $start_time) * 1000, 2);
        return $test;
    }
    
    /**
     * Test security headers
     */
    private static function testSecurityHeaders() {
        $test = [
            'name' => 'Security Headers',
            'passed' => true,
            'message' => 'All security headers present',
            'execution_time' => 0
        ];
        
        $start_time = microtime(true);
        
        $required_headers = [
            'X-Content-Type-Options',
            'X-Frame-Options',
            'X-XSS-Protection',
            'Strict-Transport-Security'
        ];
        
        $url = getBaseUrl();
        
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 5);
        curl_setopt($ch, CURLOPT_HEADER, true);
        curl_setopt($ch, CURLOPT_NOBODY, true);
        
        $response = curl_exec($ch);
        curl_close($ch);
        
        $missing_headers = [];
        
        foreach ($required_headers as $header) {
            if (stripos($response, $header . ':') === false) {
                $missing_headers[] = $header;
            }
        }
        
        if (!empty($missing_headers)) {
            $test['passed'] = false;
            $test['message'] = 'Missing headers: ' . implode(', ', $missing_headers);
        }
        
        $test['execution_time'] = round((microtime(true) - $start_time) * 1000, 2);
        return $test;
    }
    
    /**
     * Test performance
     */
    private static function testPerformance() {
        $test = [
            'name' => 'Performance',
            'passed' => true,
            'message' => 'Performance within acceptable limits',
            'execution_time' => 0
        ];
        
        $start_time = microtime(true);
        
        // Test homepage load time
        $url = getBaseUrl();
        
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 10);
        
        $page_start = microtime(true);
        $response = curl_exec($ch);
        $page_load_time = microtime(true) - $page_start;
        
        curl_close($ch);
        
        if ($page_load_time > 3) { // 3 seconds threshold
            $test['passed'] = false;
            $test['message'] = 'Homepage load time too slow: ' . round($page_load_time, 2) . 's';
        } else {
            $test['message'] = 'Homepage load time: ' . round($page_load_time, 2) . 's';
        }
        
        $test['execution_time'] = round((microtime(true) - $start_time) * 1000, 2);
        return $test;
    }
    
    /**
     * Test SSL certificate
     */
    private static function testSSLCertificate() {
        $test = [
            'name' => 'SSL Certificate',
            'passed' => true,
            'message' => 'SSL certificate valid',
            'execution_time' => 0
        ];
        
        $start_time = microtime(true);
        
        $url = getBaseUrl();
        
        if (strpos($url, 'https://') !== 0) {
            $test['passed'] = false;
            $test['message'] = 'Site not using HTTPS';
        } else {
            $context = stream_context_create([
                "ssl" => [
                    "capture_peer_cert" => true,
                    "verify_peer" => true,
                    "verify_peer_name" => true
                ]
            ]);
            
            $stream = @stream_socket_client(
                parse_url($url, PHP_URL_HOST) . ':443',
                $errno, $errstr, 5,
                STREAM_CLIENT_CONNECT, $context
            );
            
            if (!$stream) {
                $test['passed'] = false;
                $test['message'] = 'SSL connection failed: ' . $errstr;
            } else {
                $cert = stream_context_get_params($stream)['options']['ssl']['peer_certificate'];
                $cert_data = openssl_x509_parse($cert);
                
                if ($cert_data['validTo_time_t'] < time()) {
                    $test['passed'] = false;
                    $test['message'] = 'SSL certificate expired';
                } elseif ($cert_data['validTo_time_t'] < (time() + 2592000)) { // 30 days
                    $test['message'] = 'SSL certificate expires soon: ' . date('Y-m-d', $cert_data['validTo_time_t']);
                }
                
                fclose($stream);
            }
        }
        
        $test['execution_time'] = round((microtime(true) - $start_time) * 1000, 2);
        return $test;
    }
    
    /**
     * Test disk space
     */
    private static function testDiskSpace() {
        $test = [
            'name' => 'Disk Space',
            'passed' => true,
            'message' => 'Sufficient disk space available',
            'execution_time' => 0
        ];
        
        $start_time = microtime(true);
        
        $free_space = disk_free_space(__DIR__);
        $total_space = disk_total_space(__DIR__);
        
        if ($free_space && $total_space) {
            $usage_percent = (($total_space - $free_space) / $total_space) * 100;
            
            if ($usage_percent > 90) {
                $test['passed'] = false;
                $test['message'] = 'Disk usage critical: ' . round($usage_percent, 1) . '%';
            } elseif ($usage_percent > 80) {
                $test['message'] = 'Disk usage high: ' . round($usage_percent, 1) . '%';
            } else {
                $test['message'] = 'Disk usage: ' . round($usage_percent, 1) . '%';
            }
        } else {
            $test['passed'] = false;
            $test['message'] = 'Unable to check disk space';
        }
        
        $test['execution_time'] = round((microtime(true) - $start_time) * 1000, 2);
        return $test;
    }
    
    /**
     * Save test results to database
     */
    private static function saveTestResults($results) {
        try {
            $db = getDB();
            $stmt = $db->prepare("
                INSERT INTO system_tests 
                (test_results, tests_passed, tests_failed, tests_total, created_at) 
                VALUES (?, ?, ?, ?, NOW())
            ");
            
            $stmt->execute([
                json_encode($results),
                $results['passed'],
                $results['failed'],
                $results['total']
            ]);
            
        } catch (Exception $e) {
            error_log("Save test results error: " . $e->getMessage());
        }
    }
    
    /**
     * Send test failure alert
     */
    private static function sendTestFailureAlert($results) {
        $admin_email = getSystemSetting('admin_email', '');
        if (empty($admin_email)) {
            return;
        }
        
        $failed_tests = array_filter($results['tests'], function($test) {
            return !$test['passed'];
        });
        
        $subject = 'LoanFlow System Tests Failed: ' . $results['failed'] . ' out of ' . $results['total'];
        
        $message = "
        <div style='font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto;'>
            <div style='background: #dc3545; color: white; padding: 20px; border-radius: 5px 5px 0 0;'>
                <h2 style='margin: 0;'>System Test Failures</h2>
            </div>
            <div style='background: #f8f9fa; padding: 20px; border: 1px solid #dee2e6; border-top: none; border-radius: 0 0 5px 5px;'>
                <p><strong>Test Summary:</strong></p>
                <ul>
                    <li>Total Tests: {$results['total']}</li>
                    <li>Passed: {$results['passed']}</li>
                    <li>Failed: {$results['failed']}</li>
                    <li>Run Time: {$results['timestamp']}</li>
                </ul>
                
                <h3>Failed Tests:</h3>";
        
        foreach ($failed_tests as $test) {
            $message .= "
                <div style='background: white; padding: 15px; margin: 10px 0; border-left: 4px solid #dc3545;'>
                    <h4 style='margin: 0 0 10px 0; color: #dc3545;'>{$test['name']}</h4>
                    <p><strong>Error:</strong> {$test['message']}</p>
                    <p><strong>Execution Time:</strong> {$test['execution_time']}ms</p>
                </div>";
        }
        
        $message .= "
                <p style='margin-top: 20px;'><em>This is an automated system test alert.</em></p>
            </div>
        </div>";
        
        try {
            sendEmail($admin_email, $subject, $message, true);
        } catch (Exception $e) {
            error_log("Failed to send test failure alert: " . $e->getMessage());
        }
    }
    
    /**
     * Generate error monitoring report
     */
    public static function generateReport($days = 7) {
        try {
            $db = getDB();
            
            // Error statistics
            $stmt = $db->prepare("
                SELECT 
                    error_type,
                    severity,
                    COUNT(*) as count,
                    COUNT(DISTINCT file) as unique_files
                FROM error_logs 
                WHERE created_at >= DATE_SUB(NOW(), INTERVAL ? DAY)
                GROUP BY error_type, severity
                ORDER BY count DESC
            ");
            $stmt->execute([$days]);
            $error_stats = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            // Performance statistics
            $stmt = $db->prepare("
                SELECT 
                    AVG(execution_time) as avg_execution_time,
                    MAX(execution_time) as max_execution_time,
                    AVG(memory_usage) as avg_memory_usage,
                    MAX(peak_memory) as max_memory_usage,
                    COUNT(*) as total_requests,
                    COUNT(CASE WHEN execution_time > 1000 THEN 1 END) as slow_requests
                FROM performance_logs 
                WHERE created_at >= DATE_SUB(NOW(), INTERVAL ? DAY)
            ");
            $stmt->execute([$days]);
            $perf_stats = $stmt->fetch(PDO::FETCH_ASSOC);
            
            // System test results
            $stmt = $db->prepare("
                SELECT * FROM system_tests 
                WHERE created_at >= DATE_SUB(NOW(), INTERVAL ? DAY)
                ORDER BY created_at DESC
                LIMIT 10
            ");
            $stmt->execute([$days]);
            $test_results = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            return [
                'period_days' => $days,
                'error_statistics' => $error_stats,
                'performance_statistics' => $perf_stats,
                'system_tests' => $test_results,
                'generated_at' => date('Y-m-d H:i:s')
            ];
            
        } catch (Exception $e) {
            error_log("Error monitoring report error: " . $e->getMessage());
            return null;
        }
    }
    
    /**
     * Helper methods
     */
    private static function getSeverityName($severity) {
        $severities = [
            E_ERROR => 'Fatal Error',
            E_WARNING => 'Warning',
            E_PARSE => 'Parse Error',
            E_NOTICE => 'Notice',
            E_CORE_ERROR => 'Core Error',
            E_CORE_WARNING => 'Core Warning',
            E_COMPILE_ERROR => 'Compile Error',
            E_COMPILE_WARNING => 'Compile Warning',
            E_USER_ERROR => 'User Error',
            E_USER_WARNING => 'User Warning',
            E_USER_NOTICE => 'User Notice',
            E_STRICT => 'Strict Standards',
            E_RECOVERABLE_ERROR => 'Recoverable Error',
            E_DEPRECATED => 'Deprecated',
            E_USER_DEPRECATED => 'User Deprecated'
        ];
        
        return $severities[$severity] ?? 'Unknown';
    }
    
    private static function getRealIP() {
        $ip_headers = [
            'HTTP_CF_CONNECTING_IP',
            'HTTP_CLIENT_IP',
            'HTTP_X_FORWARDED_FOR',
            'HTTP_X_FORWARDED',
            'HTTP_X_CLUSTER_CLIENT_IP',
            'HTTP_FORWARDED_FOR',
            'HTTP_FORWARDED',
            'REMOTE_ADDR'
        ];
        
        foreach ($ip_headers as $header) {
            if (!empty($_SERVER[$header])) {
                return $_SERVER[$header];
            }
        }
        
        return 'unknown';
    }
    
    private static function getCurrentUserId() {
        if (isset($_SESSION['user_id'])) {
            return $_SESSION['user_id'];
        }
        return null;
    }
    
    private static function getQueryCount() {
        // This would track database queries if implemented
        return 0;
    }
    
    private static function showErrorPage() {
        http_response_code(500);
        echo '<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>System Error - LoanFlow</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); min-height: 100vh; display: flex; align-items: center; }
        .error-card { background: rgba(255,255,255,0.95); border-radius: 15px; box-shadow: 0 15px 35px rgba(0,0,0,0.1); }
        .error-icon { font-size: 4rem; color: #dc3545; }
    </style>
</head>
<body>
    <div class="container">
        <div class="row justify-content-center">
            <div class="col-lg-6">
                <div class="error-card p-5 text-center">
                    <div class="error-icon mb-4">⚠️</div>
                    <h2 class="mb-4">System Error</h2>
                    <p class="lead mb-4">We\'re experiencing technical difficulties. Our team has been notified and is working to resolve the issue.</p>
                    <div class="alert alert-info">
                        Please try again in a few minutes or contact support if the problem persists.
                    </div>
                    <a href="/" class="btn btn-primary me-2">
                        <i class="fas fa-home me-2"></i>Go Home
                    </a>
                    <a href="mailto:support@loanflow.com" class="btn btn-outline-primary">
                        <i class="fas fa-envelope me-2"></i>Contact Support
                    </a>
                </div>
            </div>
        </div>
    </div>
</body>
</html>';
    }
    
    private static function alertSlowRequest($metrics) {
        // Alert for slow requests
        error_log("Slow request detected: {$metrics['url']} took {$metrics['execution_time']}ms");
    }
    
    private static function alertHighMemoryUsage($metrics) {
        // Alert for high memory usage
        error_log("High memory usage detected: {$metrics['url']} used " . round($metrics['peak_memory'] / 1024 / 1024, 2) . "MB");
    }
    
    /**
     * Check if system is enabled
     */
    public static function isEnabled() {
        return self::$enabled;
    }
}

// Initialize error monitoring
ErrorMonitoringManager::init();
