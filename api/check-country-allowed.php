<?php
/**
 * API Endpoint: Check Country Allowed
 * Checks if a specific country is allowed for loan applications
 */

require_once '../config/database.php';
require_once '../includes/geolocation.php';
require_once '../includes/security.php';
require_once '../includes/audit_logger.php';

// Set JSON response headers
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, X-Requested-With');

// Handle preflight requests
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

try {
    // Initialize security and audit logging
    $security = new Security();
    $audit_logger = new AuditLogger();
    
    // Rate limiting check
    $client_ip = $security->getClientIP();
    if (!$security->checkRateLimit($client_ip, 'country_check_api', 60, 100)) { // 100 requests per minute
        throw new Exception('Rate limit exceeded. Please try again later.');
    }
    
    // Validate request method
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        throw new Exception('Invalid request method. Only POST requests are allowed.');
    }
    
    // Get and validate input
    $input = json_decode(file_get_contents('php://input'), true);
    
    if (!$input || !isset($input['country_code'])) {
        throw new Exception('Missing required parameter: country_code');
    }
    
    $country_code = strtoupper(trim($input['country_code']));
    
    // Validate country code format
    if (!preg_match('/^[A-Z]{2,3}$/', $country_code)) {
        throw new Exception('Invalid country code format.');
    }
    
    // Initialize geolocation service
    $geolocation = new Geolocation();
    
    // Check if country is allowed
    $is_allowed = $geolocation->isCountryAllowed($country_code);
    
    // Get additional country information
    $country_info = $geolocation->getCountryInfo($country_code);
    
    // Log the country check request
    $audit_logger->logActivity([
        'user_id' => null,
        'action' => 'country_check_request',
        'details' => [
            'country_code' => $country_code,
            'is_allowed' => $is_allowed,
            'ip_address' => $client_ip,
            'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'Unknown'
        ],
        'ip_address' => $client_ip
    ]);
    
    // Prepare response data
    $response_data = [
        'success' => true,
        'allowed' => $is_allowed,
        'country_code' => $country_code,
        'country_info' => $country_info,
        'meta' => [
            'timestamp' => date('c'),
            'cache_duration' => 1800 // 30 minutes
        ]
    ];
    
    // Add restriction reason if not allowed
    if (!$is_allowed) {
        $response_data['restriction_reason'] = $geolocation->getRestrictionReason($country_code);
        $response_data['alternative_options'] = $geolocation->getAlternativeOptions($country_code);
    }
    
    // Set cache headers
    header('Cache-Control: public, max-age=1800'); // Cache for 30 minutes
    
    // Return successful response
    http_response_code(200);
    echo json_encode($response_data, JSON_PRETTY_PRINT);
    
} catch (Exception $e) {
    // Log the error
    if (isset($audit_logger)) {
        $audit_logger->logActivity([
            'user_id' => null,
            'action' => 'country_check_error',
            'details' => [
                'error' => $e->getMessage(),
                'country_code' => $country_code ?? 'Unknown',
                'ip_address' => $client_ip ?? 'Unknown',
                'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'Unknown'
            ],
            'ip_address' => $client_ip ?? 'Unknown'
        ]);
    }
    
    // Return error response
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage(),
        'error_code' => 'COUNTRY_CHECK_ERROR',
        'timestamp' => date('c')
    ], JSON_PRETTY_PRINT);
}

// Clean up
if (isset($pdo)) {
    $pdo = null;
}
?>