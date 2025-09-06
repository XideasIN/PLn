<?php
/**
 * Mobile Application API
 * Provides REST API endpoints for iOS/Android mobile applications
 */

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With, X-API-Key');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

require_once '../config/database.php';
require_once '../includes/auth.php';
require_once '../includes/functions.php';
require_once '../includes/security.php';

// Mobile API versioning
$api_version = $_GET['v'] ?? '1.0';
$supported_versions = ['1.0', '1.1', '2.0'];

if (!in_array($api_version, $supported_versions)) {
    http_response_code(400);
    echo json_encode(['error' => 'Unsupported API version']);
    exit();
}

// Rate limiting for mobile API
session_start();
$rate_limit_key = 'mobile_api_' . ($_SERVER['REMOTE_ADDR'] ?? 'unknown');
if (!isset($_SESSION[$rate_limit_key])) {
    $_SESSION[$rate_limit_key] = ['count' => 0, 'reset_time' => time() + 3600];
}

if (time() > $_SESSION[$rate_limit_key]['reset_time']) {
    $_SESSION[$rate_limit_key] = ['count' => 0, 'reset_time' => time() + 3600];
}

if ($_SESSION[$rate_limit_key]['count'] >= 5000) { // Higher limit for mobile apps
    http_response_code(429);
    echo json_encode(['error' => 'Rate limit exceeded']);
    exit();
}

$_SESSION[$rate_limit_key]['count']++;

// API Key validation for mobile apps
$api_key = $_SERVER['HTTP_X_API_KEY'] ?? $_GET['api_key'] ?? null;
if (!$api_key || !validateMobileApiKey($api_key)) {
    http_response_code(401);
    echo json_encode(['error' => 'Invalid or missing API key']);
    exit();
}

$method = $_SERVER['REQUEST_METHOD'];
$path = $_GET['path'] ?? '';
$input = json_decode(file_get_contents('php://input'), true);

try {
    switch ($method) {
        case 'GET':
            handleGetRequest($path, $api_version);
            break;
        case 'POST':
            handlePostRequest($path, $input, $api_version);
            break;
        case 'PUT':
            handlePutRequest($path, $input, $api_version);
            break;
        case 'DELETE':
            handleDeleteRequest($path, $api_version);
            break;
        default:
            http_response_code(405);
            echo json_encode(['error' => 'Method not allowed']);
    }
} catch (Exception $e) {
    error_log('Mobile API Error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'Internal server error', 'code' => 'MOBILE_API_ERROR']);
}

function handleGetRequest($path, $version) {
    switch ($path) {
        // Authentication endpoints
        case 'auth/user':
            echo json_encode(getCurrentUser());
            break;
        case 'auth/refresh':
            echo json_encode(refreshAuthToken());
            break;
            
        // Loan application endpoints
        case 'loans':
            echo json_encode(getUserLoans());
            break;
        case 'loans/status':
            $loanId = $_GET['id'] ?? null;
            echo json_encode(getLoanStatus($loanId));
            break;
        case 'loans/calculator':
            $amount = $_GET['amount'] ?? 0;
            $term = $_GET['term'] ?? 12;
            echo json_encode(calculateLoan($amount, $term));
            break;
            
        // Document endpoints
        case 'documents':
            echo json_encode(getUserDocuments());
            break;
        case 'documents/download':
            $docId = $_GET['id'] ?? null;
            downloadDocument($docId);
            break;
            
        // Profile endpoints
        case 'profile':
            echo json_encode(getUserProfile());
            break;
        case 'profile/settings':
            echo json_encode(getUserSettings());
            break;
            
        // Notification endpoints
        case 'notifications':
            echo json_encode(getUserNotifications());
            break;
        case 'notifications/unread-count':
            echo json_encode(getUnreadNotificationCount());
            break;
            
        // Payment endpoints
        case 'payments':
            echo json_encode(getUserPayments());
            break;
        case 'payments/methods':
            echo json_encode(getPaymentMethods());
            break;
            
        // Support endpoints
        case 'support/faq':
            echo json_encode(getFAQs());
            break;
        case 'support/contact-info':
            echo json_encode(getContactInfo());
            break;
            
        // App configuration
        case 'config/app':
            echo json_encode(getAppConfig($version));
            break;
        case 'config/features':
            echo json_encode(getFeatureFlags());
            break;
            
        default:
            http_response_code(404);
            echo json_encode(['error' => 'Endpoint not found']);
    }
}

function handlePostRequest($path, $input, $version) {
    switch ($path) {
        // Authentication
        case 'auth/login':
            echo json_encode(mobileLogin($input));
            break;
        case 'auth/register':
            echo json_encode(mobileRegister($input));
            break;
        case 'auth/forgot-password':
            echo json_encode(forgotPassword($input));
            break;
        case 'auth/reset-password':
            echo json_encode(resetPassword($input));
            break;
        case 'auth/logout':
            echo json_encode(mobileLogout($input));
            break;
            
        // Loan applications
        case 'loans/apply':
            echo json_encode(submitLoanApplication($input));
            break;
        case 'loans/upload-document':
            echo json_encode(uploadLoanDocument($input));
            break;
            
        // Profile management
        case 'profile/update':
            echo json_encode(updateUserProfile($input));
            break;
        case 'profile/change-password':
            echo json_encode(changePassword($input));
            break;
        case 'profile/upload-avatar':
            echo json_encode(uploadAvatar($input));
            break;
            
        // Notifications
        case 'notifications/mark-read':
            echo json_encode(markNotificationRead($input));
            break;
        case 'notifications/register-device':
            echo json_encode(registerPushDevice($input));
            break;
            
        // Payments
        case 'payments/make-payment':
            echo json_encode(makePayment($input));
            break;
        case 'payments/add-method':
            echo json_encode(addPaymentMethod($input));
            break;
            
        // Support
        case 'support/contact':
            echo json_encode(submitSupportRequest($input));
            break;
        case 'support/feedback':
            echo json_encode(submitFeedback($input));
            break;
            
        // Analytics
        case 'analytics/event':
            echo json_encode(trackAnalyticsEvent($input));
            break;
            
        default:
            http_response_code(404);
            echo json_encode(['error' => 'Endpoint not found']);
    }
}

function handlePutRequest($path, $input, $version) {
    switch ($path) {
        case 'profile':
            echo json_encode(updateUserProfile($input));
            break;
        case 'notifications/settings':
            echo json_encode(updateNotificationSettings($input));
            break;
        case 'payments/method':
            $methodId = $_GET['id'] ?? null;
            echo json_encode(updatePaymentMethod($methodId, $input));
            break;
        default:
            http_response_code(404);
            echo json_encode(['error' => 'Endpoint not found']);
    }
}

function handleDeleteRequest($path, $version) {
    switch ($path) {
        case 'notifications':
            $notificationId = $_GET['id'] ?? null;
            echo json_encode(deleteNotification($notificationId));
            break;
        case 'payments/method':
            $methodId = $_GET['id'] ?? null;
            echo json_encode(deletePaymentMethod($methodId));
            break;
        case 'auth/device':
            $deviceId = $_GET['device_id'] ?? null;
            echo json_encode(unregisterDevice($deviceId));
            break;
        default:
            http_response_code(404);
            echo json_encode(['error' => 'Endpoint not found']);
    }
}

// Mobile-specific functions
function validateMobileApiKey($api_key) {
    // In production, validate against database
    $valid_keys = [
        'mobile_ios_v1_key_2024',
        'mobile_android_v1_key_2024',
        'mobile_dev_key_2024'
    ];
    return in_array($api_key, $valid_keys);
}

function mobileLogin($credentials) {
    global $pdo;
    
    try {
        $email = $credentials['email'] ?? '';
        $password = $credentials['password'] ?? '';
        $device_info = $credentials['device_info'] ?? [];
        
        if (empty($email) || empty($password)) {
            return ['success' => false, 'message' => 'Email and password required'];
        }
        
        // Verify credentials
        $stmt = $pdo->prepare("SELECT id, email, password, first_name, last_name, status FROM users WHERE email = ?");
        $stmt->execute([$email]);
        $user = $stmt->fetch();
        
        if (!$user || !password_verify($password, $user['password'])) {
            return ['success' => false, 'message' => 'Invalid credentials'];
        }
        
        if ($user['status'] !== 'active') {
            return ['success' => false, 'message' => 'Account is not active'];
        }
        
        // Generate mobile session token
        $token = generateMobileToken($user['id'], $device_info);
        
        // Log mobile login
        logMobileActivity($user['id'], 'login', $device_info);
        
        return [
            'success' => true,
            'message' => 'Login successful',
            'user' => [
                'id' => $user['id'],
                'email' => $user['email'],
                'first_name' => $user['first_name'],
                'last_name' => $user['last_name']
            ],
            'token' => $token,
            'expires_in' => 86400 // 24 hours
        ];
    } catch (Exception $e) {
        error_log('Mobile Login Error: ' . $e->getMessage());
        return ['success' => false, 'message' => 'Login failed'];
    }
}

function mobileRegister($data) {
    global $pdo;
    
    try {
        $required_fields = ['email', 'password', 'first_name', 'last_name'];
        foreach ($required_fields as $field) {
            if (empty($data[$field])) {
                return ['success' => false, 'message' => "Field {$field} is required"];
            }
        }
        
        // Check if email exists
        $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ?");
        $stmt->execute([$data['email']]);
        if ($stmt->fetch()) {
            return ['success' => false, 'message' => 'Email already registered'];
        }
        
        // Create user account
        $hashedPassword = password_hash($data['password'], PASSWORD_DEFAULT);
        $stmt = $pdo->prepare("
            INSERT INTO users (email, password, first_name, last_name, status, created_at, registration_source)
            VALUES (?, ?, ?, ?, 'active', NOW(), 'mobile_app')
        ");
        $stmt->execute([
            $data['email'],
            $hashedPassword,
            $data['first_name'],
            $data['last_name']
        ]);
        
        $userId = $pdo->lastInsertId();
        
        // Generate welcome token
        $token = generateMobileToken($userId, $data['device_info'] ?? []);
        
        // Log registration
        logMobileActivity($userId, 'register', $data['device_info'] ?? []);
        
        return [
            'success' => true,
            'message' => 'Registration successful',
            'user' => [
                'id' => $userId,
                'email' => $data['email'],
                'first_name' => $data['first_name'],
                'last_name' => $data['last_name']
            ],
            'token' => $token
        ];
    } catch (Exception $e) {
        error_log('Mobile Registration Error: ' . $e->getMessage());
        return ['success' => false, 'message' => 'Registration failed'];
    }
}

function getUserLoans() {
    global $pdo;
    
    try {
        $userId = getCurrentUserId();
        if (!$userId) {
            return ['error' => 'Authentication required'];
        }
        
        $stmt = $pdo->prepare("
            SELECT id, amount, term, status, interest_rate, monthly_payment, 
                   created_at, updated_at, next_payment_date
            FROM loan_applications 
            WHERE user_id = ? 
            ORDER BY created_at DESC
        ");
        $stmt->execute([$userId]);
        $loans = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        return [
            'success' => true,
            'loans' => $loans,
            'total_count' => count($loans)
        ];
    } catch (Exception $e) {
        error_log('Get User Loans Error: ' . $e->getMessage());
        return ['error' => 'Failed to fetch loans'];
    }
}

function calculateLoan($amount, $term) {
    try {
        $amount = floatval($amount);
        $term = intval($term);
        
        if ($amount <= 0 || $term <= 0) {
            return ['error' => 'Invalid amount or term'];
        }
        
        // Base interest rate (would be dynamic in production)
        $annual_rate = 0.12; // 12%
        $monthly_rate = $annual_rate / 12;
        
        // Calculate monthly payment using loan formula
        $monthly_payment = $amount * ($monthly_rate * pow(1 + $monthly_rate, $term)) / (pow(1 + $monthly_rate, $term) - 1);
        $total_payment = $monthly_payment * $term;
        $total_interest = $total_payment - $amount;
        
        return [
            'success' => true,
            'calculation' => [
                'loan_amount' => $amount,
                'term_months' => $term,
                'interest_rate' => $annual_rate * 100,
                'monthly_payment' => round($monthly_payment, 2),
                'total_payment' => round($total_payment, 2),
                'total_interest' => round($total_interest, 2)
            ]
        ];
    } catch (Exception $e) {
        return ['error' => 'Calculation failed'];
    }
}

function getUserNotifications() {
    global $pdo;
    
    try {
        $userId = getCurrentUserId();
        if (!$userId) {
            return ['error' => 'Authentication required'];
        }
        
        $stmt = $pdo->prepare("
            SELECT id, title, message, type, is_read, created_at
            FROM notifications 
            WHERE user_id = ? 
            ORDER BY created_at DESC 
            LIMIT 50
        ");
        $stmt->execute([$userId]);
        $notifications = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        return [
            'success' => true,
            'notifications' => $notifications,
            'unread_count' => array_sum(array_column($notifications, 'is_read')) === 0 ? count($notifications) : 0
        ];
    } catch (Exception $e) {
        error_log('Get Notifications Error: ' . $e->getMessage());
        return ['error' => 'Failed to fetch notifications'];
    }
}

function getAppConfig($version) {
    return [
        'success' => true,
        'config' => [
            'api_version' => $version,
            'min_supported_version' => '1.0',
            'features' => [
                'biometric_auth' => true,
                'push_notifications' => true,
                'document_upload' => true,
                'live_chat' => true,
                'payment_integration' => true
            ],
            'limits' => [
                'max_file_size' => 10485760, // 10MB
                'max_files_per_upload' => 5,
                'session_timeout' => 1800 // 30 minutes
            ],
            'endpoints' => [
                'base_url' => 'https://yourdomain.com/api/',
                'websocket_url' => 'wss://yourdomain.com/ws/',
                'support_email' => 'support@yourdomain.com',
                'support_phone' => '+1-800-123-4567'
            ]
        ]
    ];
}

function generateMobileToken($userId, $deviceInfo) {
    $payload = [
        'user_id' => $userId,
        'device_id' => $deviceInfo['device_id'] ?? uniqid(),
        'platform' => $deviceInfo['platform'] ?? 'unknown',
        'app_version' => $deviceInfo['app_version'] ?? '1.0',
        'issued_at' => time(),
        'expires_at' => time() + 86400 // 24 hours
    ];
    
    // In production, use proper JWT library
    return base64_encode(json_encode($payload));
}

function logMobileActivity($userId, $action, $deviceInfo) {
    global $pdo;
    
    try {
        $stmt = $pdo->prepare("
            INSERT INTO mobile_activity_log (user_id, action, device_info, ip_address, created_at)
            VALUES (?, ?, ?, ?, NOW())
        ");
        $stmt->execute([
            $userId,
            $action,
            json_encode($deviceInfo),
            $_SERVER['REMOTE_ADDR'] ?? 'unknown'
        ]);
    } catch (Exception $e) {
        error_log('Mobile Activity Log Error: ' . $e->getMessage());
    }
}

function getCurrentUserId() {
    // Extract user ID from mobile token
    $token = $_SERVER['HTTP_AUTHORIZATION'] ?? $_GET['token'] ?? null;
    if (!$token) {
        return null;
    }
    
    try {
        $token = str_replace('Bearer ', '', $token);
        $payload = json_decode(base64_decode($token), true);
        
        if ($payload && isset($payload['user_id']) && $payload['expires_at'] > time()) {
            return $payload['user_id'];
        }
    } catch (Exception $e) {
        error_log('Token Validation Error: ' . $e->getMessage());
    }
    
    return null;
}

function getCurrentUser() {
    global $pdo;
    
    try {
        $userId = getCurrentUserId();
        if (!$userId) {
            return ['error' => 'Authentication required'];
        }
        
        $stmt = $pdo->prepare("
            SELECT id, email, first_name, last_name, phone, status, created_at, last_login
            FROM users WHERE id = ?
        ");
        $stmt->execute([$userId]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$user) {
            return ['error' => 'User not found'];
        }
        
        return [
            'success' => true,
            'user' => $user
        ];
    } catch (Exception $e) {
        error_log('Get Current User Error: ' . $e->getMessage());
        return ['error' => 'Failed to fetch user data'];
    }
}

function submitLoanApplication($data) {
    global $pdo;
    
    try {
        $userId = getCurrentUserId();
        if (!$userId) {
            return ['error' => 'Authentication required'];
        }
        
        $required_fields = ['amount', 'term', 'purpose'];
        foreach ($required_fields as $field) {
            if (empty($data[$field])) {
                return ['success' => false, 'message' => "Field {$field} is required"];
            }
        }
        
        $stmt = $pdo->prepare("
            INSERT INTO loan_applications (user_id, amount, term, purpose, status, created_at, source)
            VALUES (?, ?, ?, ?, 'pending', NOW(), 'mobile_app')
        ");
        $stmt->execute([
            $userId,
            $data['amount'],
            $data['term'],
            $data['purpose']
        ]);
        
        $applicationId = $pdo->lastInsertId();
        
        return [
            'success' => true,
            'message' => 'Loan application submitted successfully',
            'application_id' => $applicationId,
            'status' => 'pending'
        ];
    } catch (Exception $e) {
        error_log('Submit Loan Application Error: ' . $e->getMessage());
        return ['success' => false, 'message' => 'Failed to submit application'];
    }
}

// Additional helper functions would be implemented here...
// Including: getUserDocuments, makePayment, registerPushDevice, etc.

?>