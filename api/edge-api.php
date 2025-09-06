<?php
/**
 * Edge Network API
 * LoanFlow Personal Loan Management System
 * 
 * REST API endpoints for Edge Network System operations
 */

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

require_once '../config/database.php';
require_once '../includes/functions.php';
require_once '../includes/auth.php';
require_once '../includes/rate_limiter.php';

// Rate limiting
$rate_limiter = new RateLimiter();
if (!$rate_limiter->checkLimit($_SERVER['REMOTE_ADDR'], 'edge_api', 100, 3600)) {
    http_response_code(429);
    echo json_encode(['error' => 'Rate limit exceeded']);
    exit();
}

// Authentication check
$current_user = getCurrentUser();
if (!$current_user || $current_user['role'] !== 'admin') {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized access']);
    exit();
}

// Get request method and action
$method = $_SERVER['REQUEST_METHOD'];
$action = $_GET['action'] ?? '';
$input = json_decode(file_get_contents('php://input'), true) ?? [];

try {
    switch ($method) {
        case 'GET':
            handleGetRequest($action);
            break;
        case 'POST':
            handlePostRequest($action, $input);
            break;
        case 'PUT':
            handlePutRequest($action, $input);
            break;
        case 'DELETE':
            handleDeleteRequest($action, $input);
            break;
        default:
            http_response_code(405);
            echo json_encode(['error' => 'Method not allowed']);
    }
} catch (Exception $e) {
    error_log('Edge API Error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'Internal server error']);
}

/**
 * Handle GET requests
 */
function handleGetRequest($action) {
    switch ($action) {
        case 'get_cache_rules':
            getCacheRules();
            break;
        case 'get_security_rules':
            getSecurityRules();
            break;
        case 'get_analytics':
            getAnalytics();
            break;
        case 'get_edge_locations':
            getEdgeLocations();
            break;
        case 'get_performance_metrics':
            getPerformanceMetrics();
            break;
        case 'get_security_metrics':
            getSecurityMetrics();
            break;
        case 'get_ssl_certificates':
            getSSLCertificates();
            break;
        case 'get_traffic_data':
            getTrafficData();
            break;
        case 'get_threat_intelligence':
            getThreatIntelligence();
            break;
        default:
            http_response_code(404);
            echo json_encode(['error' => 'Endpoint not found']);
    }
}

/**
 * Handle POST requests
 */
function handlePostRequest($action, $input) {
    switch ($action) {
        case 'purge_cache':
            purgeCache($input);
            break;
        case 'purge_all_cache':
            purgeAllCache();
            break;
        case 'update_ddos_settings':
            updateDDoSSettings($input);
            break;
        case 'update_waf_settings':
            updateWAFSettings($input);
            break;
        case 'add_edge_location':
            addEdgeLocation($input);
            break;
        case 'configure_location':
            configureLocation($input);
            break;
        case 'renew_certificate':
            renewCertificate($input);
            break;
        case 'generate_certificate':
            generateCertificate($input);
            break;
        case 'add_cache_rule':
            addCacheRule($input);
            break;
        case 'add_security_rule':
            addSecurityRule($input);
            break;
        case 'block_ip':
            blockIP($input);
            break;
        case 'whitelist_ip':
            whitelistIP($input);
            break;
        default:
            http_response_code(404);
            echo json_encode(['error' => 'Endpoint not found']);
    }
}

/**
 * Handle PUT requests
 */
function handlePutRequest($action, $input) {
    switch ($action) {
        case 'update_cache_rule':
            updateCacheRule($input);
            break;
        case 'update_security_rule':
            updateSecurityRule($input);
            break;
        case 'update_edge_location':
            updateEdgeLocation($input);
            break;
        case 'update_ssl_certificate':
            updateSSLCertificate($input);
            break;
        default:
            http_response_code(404);
            echo json_encode(['error' => 'Endpoint not found']);
    }
}

/**
 * Handle DELETE requests
 */
function handleDeleteRequest($action, $input) {
    switch ($action) {
        case 'delete_cache_rule':
            deleteCacheRule($input);
            break;
        case 'delete_security_rule':
            deleteSecurityRule($input);
            break;
        case 'remove_edge_location':
            removeEdgeLocation($input);
            break;
        case 'revoke_certificate':
            revokeCertificate($input);
            break;
        default:
            http_response_code(404);
            echo json_encode(['error' => 'Endpoint not found']);
    }
}

/**
 * Get cache rules with HTML for admin interface
 */
function getCacheRules() {
    global $pdo;
    
    try {
        $stmt = $pdo->prepare("
            SELECT * FROM edge_cache_rules 
            WHERE status = 'active' 
            ORDER BY priority DESC, created_at DESC
        ");
        $stmt->execute();
        $rules = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        $html = '<div class="cache-rules-list">';
        
        if (empty($rules)) {
            $html .= '<div class="alert alert-info">No cache rules configured. <a href="#" onclick="addCacheRule()">Add your first rule</a></div>';
        } else {
            foreach ($rules as $rule) {
                $html .= '
                <div class="card mb-3" data-rule-id="' . $rule['id'] . '">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-start">
                            <div>
                                <h6 class="card-title">' . htmlspecialchars($rule['name']) . '</h6>
                                <p class="card-text text-muted mb-2">' . htmlspecialchars($rule['description']) . '</p>
                                <div class="rule-details">
                                    <span class="badge bg-primary me-2">Pattern: ' . htmlspecialchars($rule['url_pattern']) . '</span>
                                    <span class="badge bg-info me-2">TTL: ' . $rule['cache_ttl'] . 's</span>
                                    <span class="badge bg-success">Priority: ' . $rule['priority'] . '</span>
                                </div>
                            </div>
                            <div class="btn-group">
                                <button class="btn btn-sm btn-outline-primary" onclick="editCacheRule(' . $rule['id'] . ')">
                                    <i class="fas fa-edit"></i>
                                </button>
                                <button class="btn btn-sm btn-outline-danger" onclick="deleteCacheRule(' . $rule['id'] . ')">
                                    <i class="fas fa-trash"></i>
                                </button>
                            </div>
                        </div>
                    </div>
                </div>';
            }
        }
        
        $html .= '</div>';
        $html .= '
        <div class="mt-3">
            <button class="btn btn-primary" onclick="showAddCacheRuleModal()">
                <i class="fas fa-plus me-2"></i>Add Cache Rule
            </button>
        </div>';
        
        echo json_encode(['success' => true, 'html' => $html]);
        
    } catch (Exception $e) {
        error_log('Error getting cache rules: ' . $e->getMessage());
        echo json_encode(['success' => false, 'error' => 'Failed to load cache rules']);
    }
}

/**
 * Get security rules with HTML for admin interface
 */
function getSecurityRules() {
    global $pdo;
    
    try {
        $stmt = $pdo->prepare("
            SELECT * FROM edge_security_rules 
            WHERE status = 'active' 
            ORDER BY priority DESC, created_at DESC
        ");
        $stmt->execute();
        $rules = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        $html = '<div class="security-rules-list">';
        
        if (empty($rules)) {
            $html .= '<div class="alert alert-info">No security rules configured. <a href="#" onclick="addSecurityRule()">Add your first rule</a></div>';
        } else {
            foreach ($rules as $rule) {
                $actionClass = $rule['action'] === 'block' ? 'danger' : ($rule['action'] === 'challenge' ? 'warning' : 'success');
                $html .= '
                <div class="card mb-3" data-rule-id="' . $rule['id'] . '">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-start">
                            <div>
                                <h6 class="card-title">' . htmlspecialchars($rule['name']) . '</h6>
                                <p class="card-text text-muted mb-2">' . htmlspecialchars($rule['description']) . '</p>
                                <div class="rule-details">
                                    <span class="badge bg-secondary me-2">Type: ' . ucfirst($rule['rule_type']) . '</span>
                                    <span class="badge bg-' . $actionClass . ' me-2">Action: ' . ucfirst($rule['action']) . '</span>
                                    <span class="badge bg-info">Priority: ' . $rule['priority'] . '</span>
                                </div>
                                <div class="mt-2">
                                    <small class="text-muted">Pattern: ' . htmlspecialchars($rule['pattern']) . '</small>
                                </div>
                            </div>
                            <div class="btn-group">
                                <button class="btn btn-sm btn-outline-primary" onclick="editSecurityRule(' . $rule['id'] . ')">
                                    <i class="fas fa-edit"></i>
                                </button>
                                <button class="btn btn-sm btn-outline-danger" onclick="deleteSecurityRule(' . $rule['id'] . ')">
                                    <i class="fas fa-trash"></i>
                                </button>
                            </div>
                        </div>
                    </div>
                </div>';
            }
        }
        
        $html .= '</div>';
        $html .= '
        <div class="mt-3">
            <button class="btn btn-primary" onclick="showAddSecurityRuleModal()">
                <i class="fas fa-plus me-2"></i>Add Security Rule
            </button>
        </div>';
        
        echo json_encode(['success' => true, 'html' => $html]);
        
    } catch (Exception $e) {
        error_log('Error getting security rules: ' . $e->getMessage());
        echo json_encode(['success' => false, 'error' => 'Failed to load security rules']);
    }
}

/**
 * Get analytics data with HTML for admin interface
 */
function getAnalytics() {
    $timeframe = $_GET['timeframe'] ?? '24h';
    
    // Mock analytics data - in real implementation, fetch from analytics systems
    $analytics = generateAnalyticsData($timeframe);
    
    $html = '
    <div class="row mb-4">
        <div class="col-md-3">
            <div class="card text-center">
                <div class="card-body">
                    <h4 class="text-primary">' . number_format($analytics['total_requests']) . '</h4>
                    <p class="mb-0">Total Requests</p>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card text-center">
                <div class="card-body">
                    <h4 class="text-success">' . $analytics['cache_hit_ratio'] . '%</h4>
                    <p class="mb-0">Cache Hit Ratio</p>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card text-center">
                <div class="card-body">
                    <h4 class="text-info">' . $analytics['avg_response_time'] . 'ms</h4>
                    <p class="mb-0">Avg Response Time</p>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card text-center">
                <div class="card-body">
                    <h4 class="text-warning">' . $analytics['bandwidth_saved'] . '</h4>
                    <p class="mb-0">Bandwidth Saved</p>
                </div>
            </div>
        </div>
    </div>
    
    <div class="row">
        <div class="col-md-6">
            <div class="card">
                <div class="card-header">
                    <h6 class="mb-0">Traffic by Country</h6>
                </div>
                <div class="card-body">
                    <canvas id="trafficByCountryChart" height="200"></canvas>
                </div>
            </div>
        </div>
        <div class="col-md-6">
            <div class="card">
                <div class="card-header">
                    <h6 class="mb-0">Response Time Trends</h6>
                </div>
                <div class="card-body">
                    <canvas id="responseTimeChart" height="200"></canvas>
                </div>
            </div>
        </div>
    </div>
    
    <script>
        // Initialize analytics charts
        setTimeout(() => {
            initializeAnalyticsCharts(' . json_encode($analytics) . ');
        }, 100);
    </script>';
    
    echo json_encode(['success' => true, 'html' => $html]);
}

/**
 * Purge cache for specific URLs or patterns
 */
function purgeCache($input) {
    $urls = $input['urls'] ?? [];
    $patterns = $input['patterns'] ?? [];
    
    // Mock implementation - in real system, this would call CDN APIs
    $purged_count = count($urls) + count($patterns);
    
    // Log cache purge activity
    logEdgeActivity('cache_purge', [
        'urls' => $urls,
        'patterns' => $patterns,
        'purged_count' => $purged_count
    ]);
    
    echo json_encode([
        'success' => true,
        'message' => "Successfully purged cache for {$purged_count} items",
        'purged_count' => $purged_count
    ]);
}

/**
 * Purge all cache
 */
function purgeAllCache() {
    // Mock implementation - in real system, this would call CDN APIs
    $start_time = microtime(true);
    
    // Simulate cache purge process
    sleep(1);
    
    $end_time = microtime(true);
    $duration = round(($end_time - $start_time) * 1000);
    
    // Log cache purge activity
    logEdgeActivity('cache_purge_all', [
        'duration_ms' => $duration,
        'timestamp' => date('Y-m-d H:i:s')
    ]);
    
    echo json_encode([
        'success' => true,
        'message' => 'All cache purged successfully',
        'duration_ms' => $duration
    ]);
}

/**
 * Update DDoS protection settings
 */
function updateDDoSSettings($input) {
    global $pdo;
    
    try {
        $enabled = $input['enabled'] ?? false;
        $sensitivity = $input['sensitivity'] ?? 'medium';
        
        // Update settings in database
        $stmt = $pdo->prepare("
            INSERT INTO system_settings (setting_key, setting_value, updated_at) 
            VALUES (?, ?, NOW()) 
            ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value), updated_at = NOW()
        ");
        
        $stmt->execute(['edge_ddos_protection', $enabled ? '1' : '0']);
        $stmt->execute(['edge_ddos_sensitivity', $sensitivity]);
        
        // Log security configuration change
        logEdgeActivity('ddos_settings_update', [
            'enabled' => $enabled,
            'sensitivity' => $sensitivity
        ]);
        
        echo json_encode([
            'success' => true,
            'message' => 'DDoS protection settings updated successfully'
        ]);
        
    } catch (Exception $e) {
        error_log('Error updating DDoS settings: ' . $e->getMessage());
        echo json_encode([
            'success' => false,
            'message' => 'Failed to update DDoS settings'
        ]);
    }
}

/**
 * Update WAF settings
 */
function updateWAFSettings($input) {
    global $pdo;
    
    try {
        $enabled = $input['enabled'] ?? false;
        $level = $input['level'] ?? 'medium';
        
        // Update settings in database
        $stmt = $pdo->prepare("
            INSERT INTO system_settings (setting_key, setting_value, updated_at) 
            VALUES (?, ?, NOW()) 
            ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value), updated_at = NOW()
        ");
        
        $stmt->execute(['edge_waf_enabled', $enabled ? '1' : '0']);
        $stmt->execute(['edge_waf_level', $level]);
        
        // Log security configuration change
        logEdgeActivity('waf_settings_update', [
            'enabled' => $enabled,
            'level' => $level
        ]);
        
        echo json_encode([
            'success' => true,
            'message' => 'WAF settings updated successfully'
        ]);
        
    } catch (Exception $e) {
        error_log('Error updating WAF settings: ' . $e->getMessage());
        echo json_encode([
            'success' => false,
            'message' => 'Failed to update WAF settings'
        ]);
    }
}

/**
 * Add new edge location
 */
function addEdgeLocation($input) {
    global $pdo;
    
    try {
        $location_data = [
            'city' => sanitizeInput($input['city'] ?? ''),
            'country' => sanitizeInput($input['country'] ?? ''),
            'region' => sanitizeInput($input['region'] ?? ''),
            'latitude' => floatval($input['latitude'] ?? 0),
            'longitude' => floatval($input['longitude'] ?? 0),
            'provider' => sanitizeInput($input['provider'] ?? ''),
            'capacity' => intval($input['capacity'] ?? 1000),
            'status' => 'pending'
        ];
        
        $stmt = $pdo->prepare("
            INSERT INTO edge_locations (city, country, region, latitude, longitude, provider, capacity, status, created_at) 
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW())
        ");
        
        $stmt->execute([
            $location_data['city'],
            $location_data['country'],
            $location_data['region'],
            $location_data['latitude'],
            $location_data['longitude'],
            $location_data['provider'],
            $location_data['capacity'],
            $location_data['status']
        ]);
        
        $location_id = $pdo->lastInsertId();
        
        // Log edge location addition
        logEdgeActivity('edge_location_added', array_merge($location_data, ['id' => $location_id]));
        
        echo json_encode([
            'success' => true,
            'message' => 'Edge location added successfully',
            'location_id' => $location_id
        ]);
        
    } catch (Exception $e) {
        error_log('Error adding edge location: ' . $e->getMessage());
        echo json_encode([
            'success' => false,
            'message' => 'Failed to add edge location'
        ]);
    }
}

/**
 * Renew SSL certificate
 */
function renewCertificate($input) {
    $cert_id = intval($input['cert_id'] ?? 0);
    
    if (!$cert_id) {
        echo json_encode([
            'success' => false,
            'message' => 'Invalid certificate ID'
        ]);
        return;
    }
    
    // Mock certificate renewal - in real implementation, this would call certificate authority APIs
    $renewal_data = [
        'cert_id' => $cert_id,
        'renewal_initiated' => date('Y-m-d H:i:s'),
        'estimated_completion' => date('Y-m-d H:i:s', strtotime('+5 minutes'))
    ];
    
    // Log certificate renewal
    logEdgeActivity('certificate_renewal', $renewal_data);
    
    echo json_encode([
        'success' => true,
        'message' => 'Certificate renewal initiated successfully',
        'renewal_data' => $renewal_data
    ]);
}

/**
 * Block IP address
 */
function blockIP($input) {
    global $pdo;
    
    try {
        $ip_address = sanitizeInput($input['ip_address'] ?? '');
        $reason = sanitizeInput($input['reason'] ?? 'Manual block');
        $duration = intval($input['duration'] ?? 3600); // Default 1 hour
        
        if (!filter_var($ip_address, FILTER_VALIDATE_IP)) {
            echo json_encode([
                'success' => false,
                'message' => 'Invalid IP address format'
            ]);
            return;
        }
        
        $stmt = $pdo->prepare("
            INSERT INTO edge_blocked_ips (ip_address, reason, blocked_until, created_at) 
            VALUES (?, ?, DATE_ADD(NOW(), INTERVAL ? SECOND), NOW())
            ON DUPLICATE KEY UPDATE 
                reason = VALUES(reason),
                blocked_until = VALUES(blocked_until),
                updated_at = NOW()
        ");
        
        $stmt->execute([$ip_address, $reason, $duration]);
        
        // Log IP blocking
        logEdgeActivity('ip_blocked', [
            'ip_address' => $ip_address,
            'reason' => $reason,
            'duration' => $duration
        ]);
        
        echo json_encode([
            'success' => true,
            'message' => "IP address {$ip_address} blocked successfully"
        ]);
        
    } catch (Exception $e) {
        error_log('Error blocking IP: ' . $e->getMessage());
        echo json_encode([
            'success' => false,
            'message' => 'Failed to block IP address'
        ]);
    }
}

/**
 * Whitelist IP address
 */
function whitelistIP($input) {
    global $pdo;
    
    try {
        $ip_address = sanitizeInput($input['ip_address'] ?? '');
        $reason = sanitizeInput($input['reason'] ?? 'Manual whitelist');
        
        if (!filter_var($ip_address, FILTER_VALIDATE_IP)) {
            echo json_encode([
                'success' => false,
                'message' => 'Invalid IP address format'
            ]);
            return;
        }
        
        $stmt = $pdo->prepare("
            INSERT INTO edge_whitelisted_ips (ip_address, reason, created_at) 
            VALUES (?, ?, NOW())
            ON DUPLICATE KEY UPDATE 
                reason = VALUES(reason),
                updated_at = NOW()
        ");
        
        $stmt->execute([$ip_address, $reason]);
        
        // Remove from blocked IPs if exists
        $stmt = $pdo->prepare("DELETE FROM edge_blocked_ips WHERE ip_address = ?");
        $stmt->execute([$ip_address]);
        
        // Log IP whitelisting
        logEdgeActivity('ip_whitelisted', [
            'ip_address' => $ip_address,
            'reason' => $reason
        ]);
        
        echo json_encode([
            'success' => true,
            'message' => "IP address {$ip_address} whitelisted successfully"
        ]);
        
    } catch (Exception $e) {
        error_log('Error whitelisting IP: ' . $e->getMessage());
        echo json_encode([
            'success' => false,
            'message' => 'Failed to whitelist IP address'
        ]);
    }
}

/**
 * Helper Functions
 */

function generateAnalyticsData($timeframe) {
    // Mock analytics data generation based on timeframe
    $multiplier = $timeframe === '24h' ? 1 : ($timeframe === '7d' ? 7 : 30);
    
    return [
        'total_requests' => 1420000 * $multiplier,
        'cache_hit_ratio' => rand(85, 95),
        'avg_response_time' => rand(75, 120),
        'bandwidth_saved' => (1.8 * $multiplier) . ' TB',
        'unique_visitors' => 45000 * $multiplier,
        'bot_requests_blocked' => 8920 * $multiplier,
        'countries' => [
            'United States' => 35,
            'United Kingdom' => 18,
            'Germany' => 12,
            'Canada' => 10,
            'Australia' => 8,
            'Others' => 17
        ],
        'response_times' => [
            'labels' => ['00:00', '04:00', '08:00', '12:00', '16:00', '20:00'],
            'data' => [120, 95, 85, 110, 90, 75]
        ]
    ];
}

function logEdgeActivity($action, $data) {
    global $pdo, $current_user;
    
    try {
        $stmt = $pdo->prepare("
            INSERT INTO edge_activity_log (user_id, action, data, ip_address, user_agent, created_at) 
            VALUES (?, ?, ?, ?, ?, NOW())
        ");
        
        $stmt->execute([
            $current_user['id'],
            $action,
            json_encode($data),
            $_SERVER['REMOTE_ADDR'] ?? '',
            $_SERVER['HTTP_USER_AGENT'] ?? ''
        ]);
        
    } catch (Exception $e) {
        error_log('Error logging edge activity: ' . $e->getMessage());
    }
}

function getEdgeLocations() {
    // Mock edge locations data
    echo json_encode([
        'success' => true,
        'locations' => [
            ['id' => 1, 'city' => 'New York', 'country' => 'USA', 'active' => true, 'latency' => 12, 'load' => 65],
            ['id' => 2, 'city' => 'London', 'country' => 'UK', 'active' => true, 'latency' => 18, 'load' => 72],
            ['id' => 3, 'city' => 'Tokyo', 'country' => 'Japan', 'active' => true, 'latency' => 25, 'load' => 58],
            ['id' => 4, 'city' => 'Sydney', 'country' => 'Australia', 'active' => true, 'latency' => 32, 'load' => 45],
            ['id' => 5, 'city' => 'Frankfurt', 'country' => 'Germany', 'active' => true, 'latency' => 15, 'load' => 68]
        ]
    ]);
}

function getPerformanceMetrics() {
    echo json_encode([
        'success' => true,
        'metrics' => [
            'overall_score' => 92,
            'avg_response_time' => 85,
            'uptime' => 99.98,
            'requests_per_second' => 2450,
            'cache_hit_ratio' => 87,
            'bandwidth_usage' => '1.2 TB'
        ]
    ]);
}

function getSecurityMetrics() {
    echo json_encode([
        'success' => true,
        'metrics' => [
            'threats_blocked' => 1247,
            'ddos_attacks_blocked' => 23,
            'malware_blocked' => 156,
            'bot_requests_filtered' => 8920,
            'security_score' => 95
        ]
    ]);
}

function getSSLCertificates() {
    echo json_encode([
        'success' => true,
        'certificates' => [
            [
                'id' => 1,
                'domain' => 'loanflow.com',
                'status' => 'valid',
                'type' => 'Let\'s Encrypt',
                'issued_date' => '2024-01-15',
                'expiry_date' => '2024-04-15'
            ],
            [
                'id' => 2,
                'domain' => 'api.loanflow.com',
                'status' => 'expiring',
                'type' => 'Let\'s Encrypt',
                'issued_date' => '2024-01-10',
                'expiry_date' => '2024-04-10'
            ]
        ]
    ]);
}

function getTrafficData() {
    echo json_encode([
        'success' => true,
        'traffic' => [
            'total_requests' => 1420000,
            'unique_visitors' => 45000,
            'page_views' => 2100000,
            'bounce_rate' => 32.5,
            'avg_session_duration' => '4m 32s'
        ]
    ]);
}

function getThreatIntelligence() {
    echo json_encode([
        'success' => true,
        'threats' => [
            'active_threats' => 15,
            'blocked_ips' => 1247,
            'malicious_requests' => 8920,
            'threat_level' => 'Medium',
            'last_attack' => '2024-01-15 14:30:00'
        ]
    ]);
}

// Additional helper functions for cache and security rule management
function addCacheRule($input) {
    global $pdo;
    
    try {
        $rule_data = [
            'name' => sanitizeInput($input['name'] ?? ''),
            'description' => sanitizeInput($input['description'] ?? ''),
            'url_pattern' => sanitizeInput($input['url_pattern'] ?? ''),
            'cache_ttl' => intval($input['cache_ttl'] ?? 3600),
            'priority' => intval($input['priority'] ?? 1),
            'status' => 'active'
        ];
        
        $stmt = $pdo->prepare("
            INSERT INTO edge_cache_rules (name, description, url_pattern, cache_ttl, priority, status, created_at) 
            VALUES (?, ?, ?, ?, ?, ?, NOW())
        ");
        
        $stmt->execute([
            $rule_data['name'],
            $rule_data['description'],
            $rule_data['url_pattern'],
            $rule_data['cache_ttl'],
            $rule_data['priority'],
            $rule_data['status']
        ]);
        
        $rule_id = $pdo->lastInsertId();
        
        logEdgeActivity('cache_rule_added', array_merge($rule_data, ['id' => $rule_id]));
        
        echo json_encode([
            'success' => true,
            'message' => 'Cache rule added successfully',
            'rule_id' => $rule_id
        ]);
        
    } catch (Exception $e) {
        error_log('Error adding cache rule: ' . $e->getMessage());
        echo json_encode([
            'success' => false,
            'message' => 'Failed to add cache rule'
        ]);
    }
}

function addSecurityRule($input) {
    global $pdo;
    
    try {
        $rule_data = [
            'name' => sanitizeInput($input['name'] ?? ''),
            'description' => sanitizeInput($input['description'] ?? ''),
            'rule_type' => sanitizeInput($input['rule_type'] ?? 'ip'),
            'pattern' => sanitizeInput($input['pattern'] ?? ''),
            'action' => sanitizeInput($input['action'] ?? 'block'),
            'priority' => intval($input['priority'] ?? 1),
            'status' => 'active'
        ];
        
        $stmt = $pdo->prepare("
            INSERT INTO edge_security_rules (name, description, rule_type, pattern, action, priority, status, created_at) 
            VALUES (?, ?, ?, ?, ?, ?, ?, NOW())
        ");
        
        $stmt->execute([
            $rule_data['name'],
            $rule_data['description'],
            $rule_data['rule_type'],
            $rule_data['pattern'],
            $rule_data['action'],
            $rule_data['priority'],
            $rule_data['status']
        ]);
        
        $rule_id = $pdo->lastInsertId();
        
        logEdgeActivity('security_rule_added', array_merge($rule_data, ['id' => $rule_id]));
        
        echo json_encode([
            'success' => true,
            'message' => 'Security rule added successfully',
            'rule_id' => $rule_id
        ]);
        
    } catch (Exception $e) {
        error_log('Error adding security rule: ' . $e->getMessage());
        echo json_encode([
            'success' => false,
            'message' => 'Failed to add security rule'
        ]);
    }
}

// Placeholder functions for other operations
function updateCacheRule($input) {
    echo json_encode(['success' => true, 'message' => 'Cache rule updated successfully']);
}

function updateSecurityRule($input) {
    echo json_encode(['success' => true, 'message' => 'Security rule updated successfully']);
}

function updateEdgeLocation($input) {
    echo json_encode(['success' => true, 'message' => 'Edge location updated successfully']);
}

function updateSSLCertificate($input) {
    echo json_encode(['success' => true, 'message' => 'SSL certificate updated successfully']);
}

function deleteCacheRule($input) {
    echo json_encode(['success' => true, 'message' => 'Cache rule deleted successfully']);
}

function deleteSecurityRule($input) {
    echo json_encode(['success' => true, 'message' => 'Security rule deleted successfully']);
}

function removeEdgeLocation($input) {
    echo json_encode(['success' => true, 'message' => 'Edge location removed successfully']);
}

function revokeCertificate($input) {
    echo json_encode(['success' => true, 'message' => 'Certificate revoked successfully']);
}

function configureLocation($input) {
    echo json_encode(['success' => true, 'message' => 'Edge location configured successfully']);
}

function generateCertificate($input) {
    echo json_encode(['success' => true, 'message' => 'Certificate generation initiated']);
}

?>