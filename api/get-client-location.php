<?php
/**
 * API Endpoint: Get Client Location
 * Returns client's location data based on IP geolocation
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
    if (!$security->checkRateLimit($client_ip, 'geolocation_api', 60, 30)) { // 30 requests per minute
        throw new Exception('Rate limit exceeded. Please try again later.');
    }
    
    // Validate request method
    if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
        throw new Exception('Invalid request method. Only GET requests are allowed.');
    }
    
    // Initialize geolocation service
    $geolocation = new Geolocation();
    
    // Get client's country data
    $country_data = $geolocation->getClientCountryData();
    
    if (!$country_data) {
        throw new Exception('Unable to determine client location.');
    }
    
    // Log the geolocation request
    $audit_logger->logActivity([
        'user_id' => null,
        'action' => 'geolocation_request',
        'details' => [
            'ip_address' => $client_ip,
            'country_detected' => $country_data['country_code'],
            'method' => $geolocation->getDetectionMethod(),
            'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'Unknown'
        ],
        'ip_address' => $client_ip
    ]);
    
    // Prepare response data
    $response_data = [
        'success' => true,
        'data' => $country_data,
        'meta' => [
            'detection_method' => $geolocation->getDetectionMethod(),
            'timestamp' => date('c'),
            'cache_duration' => 3600 // 1 hour
        ]
    ];
    
    // Set cache headers
    header('Cache-Control: public, max-age=3600'); // Cache for 1 hour
    header('ETag: "' . md5(json_encode($country_data)) . '"');
    
    // Check if client has cached version
    $client_etag = $_SERVER['HTTP_IF_NONE_MATCH'] ?? '';
    $current_etag = '"' . md5(json_encode($country_data)) . '"';
    
    if ($client_etag === $current_etag) {
        http_response_code(304); // Not Modified
        exit();
    }
    
    // Return successful response
    http_response_code(200);
    echo json_encode($response_data, JSON_PRETTY_PRINT);
    
} catch (Exception $e) {
    // Log the error
    if (isset($audit_logger)) {
        $audit_logger->logActivity([
            'user_id' => null,
            'action' => 'geolocation_error',
            'details' => [
                'error' => $e->getMessage(),
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
        'error_code' => 'GEOLOCATION_ERROR',
        'timestamp' => date('c')
    ], JSON_PRETTY_PRINT);
}

// Clean up
if (isset($pdo)) {
    $pdo = null;
}
?>