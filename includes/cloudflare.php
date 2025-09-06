<?php
/**
 * Custom CloudFlare-like Security and Performance System
 * LoanFlow Personal Loan Management System
 */

class CloudFlareManager {
    
    private static $enabled = true;
    private static $cache_enabled = true;
    private static $security_enabled = true;
    private static $ddos_protection = true;
    private static $rate_limiting = true;
    
    /**
     * Initialize CloudFlare-like system
     */
    public static function init() {
        self::$enabled = getSystemSetting('cloudflare_enabled', '1') === '1';
        self::$cache_enabled = getSystemSetting('cache_enabled', '1') === '1';
        self::$security_enabled = getSystemSetting('security_enabled', '1') === '1';
        self::$ddos_protection = getSystemSetting('ddos_protection', '1') === '1';
        self::$rate_limiting = getSystemSetting('rate_limiting', '1') === '1';
        
        if (self::$enabled) {
            self::enableFeatures();
        }
    }
    
    /**
     * Enable all CloudFlare-like features
     */
    private static function enableFeatures() {
        // Start output buffering for compression
        if (self::$cache_enabled) {
            ob_start('ob_gzhandler');
        }
        
        // Set security headers
        if (self::$security_enabled) {
            self::setSecurityHeaders();
        }
        
        // Check for DDoS attacks
        if (self::$ddos_protection) {
            self::checkDDoSProtection();
        }
        
        // Apply rate limiting
        if (self::$rate_limiting) {
            self::applyRateLimiting();
        }
    }
    
    /**
     * Set comprehensive security headers
     */
    public static function setSecurityHeaders() {
        $headers = [
            'X-Content-Type-Options' => 'nosniff',
            'X-Frame-Options' => 'DENY',
            'X-XSS-Protection' => '1; mode=block',
            'Referrer-Policy' => 'strict-origin-when-cross-origin',
            'Permissions-Policy' => 'geolocation=(), microphone=(), camera=()',
            'Strict-Transport-Security' => 'max-age=31536000; includeSubDomains; preload',
            'Content-Security-Policy' => self::getCSPHeader(),
            'Expect-CT' => 'max-age=86400, enforce',
            'Feature-Policy' => 'vibrate \'none\'; geolocation \'none\'; camera \'none\'; microphone \'none\'',
            'Cache-Control' => 'no-cache, no-store, must-revalidate',
            'Pragma' => 'no-cache',
            'Expires' => '0',
            'X-Powered-By' => '', // Hide server info
            'Server' => 'CloudFlare-Like-Security'
        ];
        
        foreach ($headers as $header => $value) {
            if (!headers_sent()) {
                header($header . ': ' . $value);
            }
        }
    }
    
    /**
     * Generate Content Security Policy header
     */
    private static function getCSPHeader() {
        $csp_directives = [
            "default-src 'self'",
            "script-src 'self' 'unsafe-inline' 'unsafe-eval' https://cdn.jsdelivr.net https://cdnjs.cloudflare.com https://www.google.com https://www.gstatic.com https://js.stripe.com https://www.paypal.com",
            "style-src 'self' 'unsafe-inline' https://cdn.jsdelivr.net https://cdnjs.cloudflare.com https://fonts.googleapis.com",
            "font-src 'self' https://fonts.gstatic.com https://cdnjs.cloudflare.com",
            "img-src 'self' data: https: blob:",
            "connect-src 'self' https://api.openai.com https://api.stripe.com https://api.paypal.com",
            "frame-src https://js.stripe.com https://www.paypal.com https://www.google.com",
            "object-src 'none'",
            "media-src 'self'",
            "form-action 'self'",
            "base-uri 'self'",
            "frame-ancestors 'none'"
        ];
        
        return implode('; ', $csp_directives);
    }
    
    /**
     * DDoS Protection System
     */
    public static function checkDDoSProtection() {
        $ip = self::getRealIP();
        $current_time = time();
        $window = 60; // 1 minute window
        $max_requests = 100; // Max requests per minute
        
        // Check if IP is whitelisted
        if (self::isWhitelistedIP($ip)) {
            return true;
        }
        
        // Check if IP is blacklisted
        if (self::isBlacklistedIP($ip)) {
            self::blockRequest('Blacklisted IP', $ip);
            return false;
        }
        
        // Rate limiting check
        $requests = self::getIPRequests($ip, $window);
        
        if ($requests >= $max_requests) {
            // Temporary block for 15 minutes
            self::temporaryBlock($ip, 900);
            self::blockRequest('Rate limit exceeded', $ip);
            return false;
        }
        
        // Log request
        self::logRequest($ip);
        
        // Check for suspicious patterns
        if (self::detectSuspiciousActivity($ip)) {
            self::blockRequest('Suspicious activity detected', $ip);
            return false;
        }
        
        return true;
    }
    
    /**
     * Apply rate limiting
     */
    public static function applyRateLimiting() {
        $ip = self::getRealIP();
        $endpoint = $_SERVER['REQUEST_URI'] ?? '/';
        
        // Different limits for different endpoints
        $limits = [
            '/api/' => ['requests' => 30, 'window' => 60], // 30 per minute for API
            '/login.php' => ['requests' => 5, 'window' => 300], // 5 per 5 minutes for login
            '/register.php' => ['requests' => 3, 'window' => 300], // 3 per 5 minutes for registration
            'default' => ['requests' => 60, 'window' => 60] // 60 per minute for general
        ];
        
        $limit_config = $limits['default'];
        
        foreach ($limits as $pattern => $config) {
            if ($pattern !== 'default' && strpos($endpoint, $pattern) !== false) {
                $limit_config = $config;
                break;
            }
        }
        
        $requests = self::getEndpointRequests($ip, $endpoint, $limit_config['window']);
        
        if ($requests >= $limit_config['requests']) {
            http_response_code(429);
            header('Retry-After: ' . $limit_config['window']);
            
            echo json_encode([
                'error' => 'Rate limit exceeded',
                'retry_after' => $limit_config['window']
            ]);
            exit;
        }
        
        // Log endpoint request
        self::logEndpointRequest($ip, $endpoint);
    }
    
    /**
     * Get real IP address
     */
    public static function getRealIP() {
        $ip_headers = [
            'HTTP_CF_CONNECTING_IP',     // CloudFlare
            'HTTP_CLIENT_IP',            // Proxy
            'HTTP_X_FORWARDED_FOR',      // Load balancer/proxy
            'HTTP_X_FORWARDED',          // Proxy
            'HTTP_X_CLUSTER_CLIENT_IP',  // Cluster
            'HTTP_FORWARDED_FOR',        // Proxy
            'HTTP_FORWARDED',            // Proxy
            'REMOTE_ADDR'                // Standard
        ];
        
        foreach ($ip_headers as $header) {
            if (!empty($_SERVER[$header])) {
                $ips = explode(',', $_SERVER[$header]);
                $ip = trim($ips[0]);
                
                if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
                    return $ip;
                }
            }
        }
        
        return $_SERVER['REMOTE_ADDR'] ?? 'unknown';
    }
    
    /**
     * Check if IP is whitelisted
     */
    private static function isWhitelistedIP($ip) {
        $whitelist = getSystemSetting('ip_whitelist', '');
        if (empty($whitelist)) {
            return false;
        }
        
        $whitelisted_ips = array_map('trim', explode(',', $whitelist));
        return in_array($ip, $whitelisted_ips);
    }
    
    /**
     * Check if IP is blacklisted
     */
    private static function isBlacklistedIP($ip) {
        try {
            $db = getDB();
            $stmt = $db->prepare("
                SELECT COUNT(*) FROM ip_blacklist 
                WHERE ip_address = ? AND (expires_at IS NULL OR expires_at > NOW())
            ");
            $stmt->execute([$ip]);
            return $stmt->fetchColumn() > 0;
        } catch (Exception $e) {
            error_log("Blacklist check error: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Get IP request count in time window
     */
    private static function getIPRequests($ip, $window) {
        $cache_key = "ip_requests_{$ip}_{$window}";
        $requests = apcu_fetch($cache_key);
        
        if ($requests === false) {
            $requests = 0;
        }
        
        return $requests;
    }
    
    /**
     * Log IP request
     */
    private static function logRequest($ip) {
        $cache_key = "ip_requests_{$ip}_60";
        $requests = apcu_fetch($cache_key);
        
        if ($requests === false) {
            $requests = 1;
        } else {
            $requests++;
        }
        
        apcu_store($cache_key, $requests, 60);
    }
    
    /**
     * Get endpoint-specific request count
     */
    private static function getEndpointRequests($ip, $endpoint, $window) {
        $cache_key = "endpoint_requests_" . md5($ip . $endpoint) . "_{$window}";
        $requests = apcu_fetch($cache_key);
        
        if ($requests === false) {
            $requests = 0;
        }
        
        return $requests;
    }
    
    /**
     * Log endpoint request
     */
    private static function logEndpointRequest($ip, $endpoint) {
        $cache_key = "endpoint_requests_" . md5($ip . $endpoint) . "_60";
        $requests = apcu_fetch($cache_key);
        
        if ($requests === false) {
            $requests = 1;
        } else {
            $requests++;
        }
        
        apcu_store($cache_key, $requests, 60);
    }
    
    /**
     * Detect suspicious activity
     */
    private static function detectSuspiciousActivity($ip) {
        $suspicious_patterns = [
            // Check for common attack patterns in user agent
            'user_agent_patterns' => [
                'sqlmap', 'nikto', 'nmap', 'masscan', 'zap', 'burp',
                'python-requests', 'curl', 'wget', 'bot', 'crawler'
            ],
            
            // Check for suspicious request patterns
            'request_patterns' => [
                'admin', 'wp-admin', 'phpmyadmin', '.env', 'config',
                'backup', 'sql', 'database', 'shell', 'cmd'
            ]
        ];
        
        $user_agent = strtolower($_SERVER['HTTP_USER_AGENT'] ?? '');
        $request_uri = strtolower($_SERVER['REQUEST_URI'] ?? '');
        
        // Check user agent
        foreach ($suspicious_patterns['user_agent_patterns'] as $pattern) {
            if (strpos($user_agent, $pattern) !== false) {
                self::logSuspiciousActivity($ip, 'Suspicious user agent: ' . $user_agent);
                return true;
            }
        }
        
        // Check request URI
        foreach ($suspicious_patterns['request_patterns'] as $pattern) {
            if (strpos($request_uri, $pattern) !== false) {
                self::logSuspiciousActivity($ip, 'Suspicious request: ' . $request_uri);
                return true;
            }
        }
        
        // Check for rapid requests from same IP
        $recent_requests = self::getIPRequests($ip, 10); // Last 10 seconds
        if ($recent_requests > 20) {
            self::logSuspiciousActivity($ip, 'Rapid fire requests: ' . $recent_requests . ' in 10 seconds');
            return true;
        }
        
        return false;
    }
    
    /**
     * Log suspicious activity
     */
    private static function logSuspiciousActivity($ip, $reason) {
        try {
            $db = getDB();
            $stmt = $db->prepare("
                INSERT INTO security_logs 
                (ip_address, event_type, description, user_agent, request_uri, created_at) 
                VALUES (?, 'suspicious_activity', ?, ?, ?, NOW())
            ");
            
            $stmt->execute([
                $ip,
                $reason,
                $_SERVER['HTTP_USER_AGENT'] ?? '',
                $_SERVER['REQUEST_URI'] ?? ''
            ]);
        } catch (Exception $e) {
            error_log("Security log error: " . $e->getMessage());
        }
    }
    
    /**
     * Temporary block IP
     */
    private static function temporaryBlock($ip, $duration = 900) {
        try {
            $db = getDB();
            $stmt = $db->prepare("
                INSERT INTO ip_blacklist 
                (ip_address, reason, expires_at, created_at) 
                VALUES (?, 'Automatic temporary block', DATE_ADD(NOW(), INTERVAL ? SECOND), NOW())
                ON DUPLICATE KEY UPDATE 
                expires_at = DATE_ADD(NOW(), INTERVAL ? SECOND)
            ");
            
            $stmt->execute([$ip, $duration, $duration]);
        } catch (Exception $e) {
            error_log("Temporary block error: " . $e->getMessage());
        }
    }
    
    /**
     * Block request and return error page
     */
    private static function blockRequest($reason, $ip) {
        http_response_code(403);
        
        // Log the block
        self::logSecurityEvent($ip, 'request_blocked', $reason);
        
        // Show security block page
        self::showSecurityBlockPage($reason);
        exit;
    }
    
    /**
     * Log security event
     */
    private static function logSecurityEvent($ip, $event_type, $description) {
        try {
            $db = getDB();
            $stmt = $db->prepare("
                INSERT INTO security_logs 
                (ip_address, event_type, description, user_agent, request_uri, created_at) 
                VALUES (?, ?, ?, ?, ?, NOW())
            ");
            
            $stmt->execute([
                $ip,
                $event_type,
                $description,
                $_SERVER['HTTP_USER_AGENT'] ?? '',
                $_SERVER['REQUEST_URI'] ?? ''
            ]);
        } catch (Exception $e) {
            error_log("Security event log error: " . $e->getMessage());
        }
    }
    
    /**
     * Show security block page
     */
    private static function showSecurityBlockPage($reason) {
        echo '<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Access Blocked - Security Protection</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); min-height: 100vh; display: flex; align-items: center; }
        .security-card { background: rgba(255,255,255,0.95); border-radius: 15px; box-shadow: 0 15px 35px rgba(0,0,0,0.1); }
        .security-icon { font-size: 4rem; color: #dc3545; }
    </style>
</head>
<body>
    <div class="container">
        <div class="row justify-content-center">
            <div class="col-lg-6">
                <div class="security-card p-5 text-center">
                    <div class="security-icon mb-4">
                        <i class="fas fa-shield-alt"></i>
                    </div>
                    <h2 class="mb-4">Access Blocked</h2>
                    <p class="lead mb-4">Your request has been blocked by our security system.</p>
                    <div class="alert alert-danger">
                        <strong>Reason:</strong> ' . htmlspecialchars($reason) . '
                    </div>
                    <p class="text-muted mb-4">
                        If you believe this is an error, please contact our support team with the reference ID below.
                    </p>
                    <p class="small">
                        <strong>Reference ID:</strong> ' . uniqid('SEC-') . '<br>
                        <strong>Timestamp:</strong> ' . date('Y-m-d H:i:s T') . '<br>
                        <strong>IP Address:</strong> ' . htmlspecialchars(self::getRealIP()) . '
                    </p>
                    <hr>
                    <a href="mailto:support@loanflow.com" class="btn btn-primary">
                        <i class="fas fa-envelope me-2"></i>Contact Support
                    </a>
                </div>
            </div>
        </div>
    </div>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/js/all.min.js"></script>
</body>
</html>';
    }
    
    /**
     * Page caching system
     */
    public static function enablePageCache($cache_time = 3600) {
        if (!self::$cache_enabled) {
            return false;
        }
        
        $cache_key = 'page_' . md5($_SERVER['REQUEST_URI'] . serialize($_GET));
        $cached_content = apcu_fetch($cache_key);
        
        if ($cached_content !== false) {
            // Serve cached content
            header('X-Cache: HIT');
            header('X-Cache-Time: ' . date('Y-m-d H:i:s', $cached_content['timestamp']));
            echo $cached_content['content'];
            return true;
        }
        
        // Start output buffering to capture page content
        ob_start(function($content) use ($cache_key, $cache_time) {
            // Cache the content
            apcu_store($cache_key, [
                'content' => $content,
                'timestamp' => time()
            ], $cache_time);
            
            header('X-Cache: MISS');
            return $content;
        });
        
        return false;
    }
    
    /**
     * Clear page cache
     */
    public static function clearPageCache($pattern = null) {
        if ($pattern) {
            $iterator = new APCUIterator('/^page_/');
            foreach ($iterator as $item) {
                if (strpos($item['key'], $pattern) !== false) {
                    apcu_delete($item['key']);
                }
            }
        } else {
            $iterator = new APCUIterator('/^page_/');
            foreach ($iterator as $item) {
                apcu_delete($item['key']);
            }
        }
    }
    
    /**
     * Minify HTML output
     */
    public static function minifyHTML($html) {
        if (!self::$cache_enabled) {
            return $html;
        }
        
        // Remove HTML comments (except IE conditionals)
        $html = preg_replace('/<!--(?!\s*(?:\[if [^\]]+]|<!|>))(?:(?!-->).)*-->/s', '', $html);
        
        // Remove unnecessary whitespace
        $html = preg_replace('/>\s+</', '><', $html);
        $html = preg_replace('/\s+/', ' ', $html);
        
        // Remove whitespace around block elements
        $html = preg_replace('/\s*(<\/?(div|p|h[1-6]|ul|ol|li|table|tr|td|th|thead|tbody|section|article|header|footer|nav|main)[^>]*>)\s*/', '$1', $html);
        
        return trim($html);
    }
    
    /**
     * Generate security report
     */
    public static function generateSecurityReport($days = 7) {
        try {
            $db = getDB();
            
            // Get blocked requests
            $stmt = $db->prepare("
                SELECT event_type, COUNT(*) as count 
                FROM security_logs 
                WHERE created_at >= DATE_SUB(NOW(), INTERVAL ? DAY)
                GROUP BY event_type
                ORDER BY count DESC
            ");
            $stmt->execute([$days]);
            $security_events = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            // Get top attacking IPs
            $stmt = $db->prepare("
                SELECT ip_address, COUNT(*) as count 
                FROM security_logs 
                WHERE created_at >= DATE_SUB(NOW(), INTERVAL ? DAY)
                GROUP BY ip_address
                ORDER BY count DESC
                LIMIT 10
            ");
            $stmt->execute([$days]);
            $top_attackers = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            // Get current blacklisted IPs
            $stmt = $db->prepare("
                SELECT ip_address, reason, created_at, expires_at 
                FROM ip_blacklist 
                WHERE expires_at IS NULL OR expires_at > NOW()
                ORDER BY created_at DESC
            ");
            $stmt->execute();
            $blacklisted_ips = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            return [
                'security_events' => $security_events,
                'top_attackers' => $top_attackers,
                'blacklisted_ips' => $blacklisted_ips,
                'total_blocked' => array_sum(array_column($security_events, 'count')),
                'report_period' => $days
            ];
            
        } catch (Exception $e) {
            error_log("Security report error: " . $e->getMessage());
            return null;
        }
    }
    
    /**
     * Check if system is enabled
     */
    public static function isEnabled() {
        return self::$enabled;
    }
    
    /**
     * Enable/disable system
     */
    public static function setEnabled($enabled) {
        self::$enabled = $enabled;
        updateSystemSetting('cloudflare_enabled', $enabled ? '1' : '0');
    }
}

// Initialize CloudFlare-like system
CloudFlareManager::init();
