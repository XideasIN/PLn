<?php
/**
 * Configuration File
 * LoanFlow Personal Loan Management System
 */

// Prevent direct access
if (!defined('SECURE_ACCESS')) {
    define('SECURE_ACCESS', true);
}

// Include core configuration files
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/email.php';
require_once __DIR__ . '/../config/countries.php';

// Load environment variables if .env file exists
if (file_exists(__DIR__ . '/../.env')) {
    $env_lines = file(__DIR__ . '/../.env', FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($env_lines as $line) {
        if (strpos($line, '=') !== false && !str_starts_with($line, '#')) {
            list($key, $value) = explode('=', $line, 2);
            $_ENV[trim($key)] = trim($value);
        }
    }
}

// Application Configuration
define('APP_NAME', 'LoanFlow Personal Loans');
define('APP_VERSION', '2.0.0');
define('APP_ENV', $_ENV['APP_ENV'] ?? 'production');
define('APP_DEBUG', ($_ENV['APP_DEBUG'] ?? 'false') === 'true');
define('APP_URL', $_ENV['APP_URL'] ?? 'https://loanflow.com');
define('APP_TIMEZONE', $_ENV['TIMEZONE'] ?? 'America/New_York');

// Security Configuration
define('JWT_SECRET', $_ENV['JWT_SECRET'] ?? 'LoanFlow2024!JWT!SecretKey!RandomString123');
define('ENCRYPTION_KEY', $_ENV['ENCRYPTION_KEY'] ?? 'LoanFlow2024!EncryptionKey!SecureRandom456');
define('SESSION_LIFETIME', intval($_ENV['SESSION_LIFETIME'] ?? 7200));
define('CSRF_TOKEN_LIFETIME', intval($_ENV['CSRF_TOKEN_LIFETIME'] ?? 3600));
define('MAX_LOGIN_ATTEMPTS', intval($_ENV['MAX_LOGIN_ATTEMPTS'] ?? 5));
define('LOCKOUT_DURATION', intval($_ENV['LOCKOUT_DURATION'] ?? 900));

// File Upload Configuration
define('MAX_UPLOAD_SIZE', intval($_ENV['MAX_UPLOAD_SIZE'] ?? 10485760)); // 10MB
define('ALLOWED_FILE_TYPES', $_ENV['ALLOWED_FILE_TYPES'] ?? 'pdf,doc,docx,jpg,jpeg,png,gif');
define('UPLOAD_PATH', __DIR__ . '/../uploads/');

// API Configuration
define('API_RATE_LIMIT', intval($_ENV['API_RATE_LIMIT'] ?? 1000));
define('API_RATE_WINDOW', intval($_ENV['API_RATE_WINDOW'] ?? 3600));

// Company Information
define('COMPANY_NAME', 'LoanFlow Personal Loans');
define('COMPANY_PHONE', $_ENV['COMPANY_PHONE'] ?? '+1-800-LOANFLOW');
define('COMPANY_EMAIL', 'support@loanflow.com');
define('COMPANY_ADDRESS', '123 Financial Street, Toronto, ON M5V 3A8, Canada');

// Set timezone
date_default_timezone_set(APP_TIMEZONE);

// Error reporting based on environment
if (APP_DEBUG) {
    error_reporting(E_ALL);
    ini_set('display_errors', 1);
} else {
    error_reporting(0);
    ini_set('display_errors', 0);
    ini_set('log_errors', 1);
}

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    // Secure session configuration
    ini_set('session.cookie_httponly', 1);
    ini_set('session.cookie_secure', isset($_SERVER['HTTPS']));
    ini_set('session.use_strict_mode', 1);
    ini_set('session.cookie_samesite', 'Strict');
    ini_set('session.gc_maxlifetime', SESSION_LIFETIME);
    
    session_start();
}

// Helper function to check if user is logged in
function isLoggedIn() {
    return isset($_SESSION['user_id']) && !empty($_SESSION['user_id']);
}

// Helper function to check if user is admin
function isAdmin() {
    return isset($_SESSION['role']) && $_SESSION['role'] === 'admin';
}

// Helper function to get current user
function getCurrentUser() {
    if (!isLoggedIn()) {
        return null;
    }
    
    try {
        $db = getDB();
        $stmt = $db->prepare("SELECT * FROM users WHERE id = ?");
        $stmt->execute([$_SESSION['user_id']]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    } catch (Exception $e) {
        error_log('Error getting current user: ' . $e->getMessage());
        return null;
    }
}

// Helper function to get current user ID
function getCurrentUserId() {
    return $_SESSION['user_id'] ?? null;
}

// Rate limiting helper function
function checkRateLimit($key, $window_seconds, $max_requests) {
    if (!isset($_SESSION['rate_limits'])) {
        $_SESSION['rate_limits'] = [];
    }
    
    $now = time();
    $window_start = $now - $window_seconds;
    
    // Clean old entries
    if (isset($_SESSION['rate_limits'][$key])) {
        $_SESSION['rate_limits'][$key] = array_filter(
            $_SESSION['rate_limits'][$key],
            function($timestamp) use ($window_start) {
                return $timestamp > $window_start;
            }
        );
    } else {
        $_SESSION['rate_limits'][$key] = [];
    }
    
    // Check if limit exceeded
    if (count($_SESSION['rate_limits'][$key]) >= $max_requests) {
        return false;
    }
    
    // Add current request
    $_SESSION['rate_limits'][$key][] = $now;
    return true;
}

?>