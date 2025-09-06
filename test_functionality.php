<?php
/**
 * Functional Testing Script
 * Tests core functionality of the loan application system
 */

require_once 'config/database.php';
require_once 'includes/functions.php';

echo "<h1>LoanFlow System Functional Test</h1>";
echo "<style>body{font-family:Arial,sans-serif;margin:20px;} .success{color:green;} .error{color:red;} .info{color:blue;}</style>";

// Test 1: Database Connection
echo "<h2>1. Database Connection Test</h2>";
try {
    $db = Database::getInstance()->getConnection();
    echo "<p class='success'>✓ Database connection successful</p>";
    
    // Test basic query
    $stmt = $db->query("SELECT COUNT(*) as count FROM users");
    $user_count = $stmt->fetch()['count'];
    echo "<p class='info'>Total users in database: {$user_count}</p>";
    
} catch (Exception $e) {
    echo "<p class='error'>✗ Database connection failed: " . $e->getMessage() . "</p>";
}

// Test 2: Functions Loading
echo "<h2>2. Core Functions Test</h2>";
if (function_exists('sanitizeInput')) {
    echo "<p class='success'>✓ sanitizeInput function available</p>";
} else {
    echo "<p class='error'>✗ sanitizeInput function missing</p>";
}

if (function_exists('generateCSRFToken')) {
    echo "<p class='success'>✓ CSRF token generation available</p>";
} else {
    echo "<p class='error'>✗ CSRF token generation missing</p>";
}

if (function_exists('logAudit')) {
    echo "<p class='success'>✓ Audit logging function available</p>";
} else {
    echo "<p class='error'>✗ Audit logging function missing</p>";
}

// Test 3: Email Validation
echo "<h2>3. Email Validation Test</h2>";
$test_emails = [
    'valid@example.com' => true,
    'invalid.email' => false,
    'test@domain.co.uk' => true,
    '@invalid.com' => false
];

foreach ($test_emails as $email => $expected) {
    $result = filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
    if ($result === $expected) {
        echo "<p class='success'>✓ Email '{$email}' validation correct</p>";
    } else {
        echo "<p class='error'>✗ Email '{$email}' validation failed</p>";
    }
}

// Test 4: Session Management
echo "<h2>4. Session Management Test</h2>";
if (session_status() === PHP_SESSION_ACTIVE) {
    echo "<p class='success'>✓ Session is active</p>";
} else {
    session_start();
    if (session_status() === PHP_SESSION_ACTIVE) {
        echo "<p class='success'>✓ Session started successfully</p>";
    } else {
        echo "<p class='error'>✗ Session management failed</p>";
    }
}

// Test 5: File Structure
echo "<h2>5. File Structure Test</h2>";
$required_files = [
    'api/submit-application.php',
    'api/check-email.php',
    'includes/auth.php',
    'includes/functions.php',
    'config/database.php',
    'assets/js/application-form.js'
];

foreach ($required_files as $file) {
    if (file_exists($file)) {
        echo "<p class='success'>✓ {$file} exists</p>";
    } else {
        echo "<p class='error'>✗ {$file} missing</p>";
    }
}

// Test 6: Database Tables
echo "<h2>6. Database Tables Test</h2>";
try {
    $required_tables = [
        'users', 'loan_applications', 'documents', 'payments', 
        'system_settings', 'audit_logs', 'client_messages'
    ];
    
    foreach ($required_tables as $table) {
        $stmt = $db->query("SHOW TABLES LIKE '{$table}'");
        if ($stmt->rowCount() > 0) {
            echo "<p class='success'>✓ Table '{$table}' exists</p>";
        } else {
            echo "<p class='error'>✗ Table '{$table}' missing</p>";
        }
    }
} catch (Exception $e) {
    echo "<p class='error'>✗ Database table check failed: " . $e->getMessage() . "</p>";
}

echo "<h2>Test Complete</h2>";
echo "<p class='info'>Functional testing completed. Review results above.</p>";
?>