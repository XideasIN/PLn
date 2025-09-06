<?php
/**
 * Rate Limiter
 * Handles API rate limiting and request throttling
 */

if (!defined('SECURE_ACCESS')) {
    die('Direct access not permitted');
}

class RateLimiter {
    private static $instance = null;
    private $db;
    private $redis = null;
    
    private function __construct() {
        $this->db = Database::getInstance()->getConnection();
        
        // Try to connect to Redis if available
        if (class_exists('Redis')) {
            try {
                $this->redis = new Redis();
                $this->redis->connect('127.0.0.1', 6379);
            } catch (Exception $e) {
                $this->redis = null;
                error_log('Redis connection failed: ' . $e->getMessage());
            }
        }
    }
    
    public static function getInstance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    /**
     * Check if request is within rate limit
     * @param string $identifier - IP address or user ID
     * @param int $limit - Maximum requests allowed
     * @param int $window - Time window in seconds
     * @param string $type - Type of rate limit (api, login, etc.)
     * @return bool
     */
    public function checkLimit($identifier, $limit = 100, $window = 3600, $type = 'api') {
        $key = $this->generateKey($identifier, $type);
        
        if ($this->redis) {
            return $this->checkLimitRedis($key, $limit, $window);
        } else {
            return $this->checkLimitDatabase($key, $limit, $window);
        }
    }
    
    /**
     * Check rate limit using Redis
     */
    private function checkLimitRedis($key, $limit, $window) {
        try {
            $current = $this->redis->incr($key);
            
            if ($current === 1) {
                $this->redis->expire($key, $window);
            }
            
            return $current <= $limit;
        } catch (Exception $e) {
            error_log('Redis rate limit check failed: ' . $e->getMessage());
            return true; // Allow request if Redis fails
        }
    }
    
    /**
     * Check rate limit using database
     */
    private function checkLimitDatabase($key, $limit, $window) {
        try {
            $now = time();
            $window_start = $now - $window;
            
            // Clean old entries
            $stmt = $this->db->prepare("
                DELETE FROM rate_limits 
                WHERE created_at < ? AND rate_key = ?
            ");
            $stmt->execute([$window_start, $key]);
            
            // Count current requests
            $stmt = $this->db->prepare("
                SELECT COUNT(*) as count 
                FROM rate_limits 
                WHERE rate_key = ? AND created_at >= ?
            ");
            $stmt->execute([$key, $window_start]);
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            
            $current_count = $result['count'] ?? 0;
            
            if ($current_count >= $limit) {
                return false;
            }
            
            // Add current request
            $stmt = $this->db->prepare("
                INSERT INTO rate_limits (rate_key, created_at) 
                VALUES (?, ?)
            ");
            $stmt->execute([$key, $now]);
            
            return true;
            
        } catch (Exception $e) {
            error_log('Database rate limit check failed: ' . $e->getMessage());
            return true; // Allow request if database fails
        }
    }
    
    /**
     * Get remaining requests for identifier
     */
    public function getRemainingRequests($identifier, $limit = 100, $window = 3600, $type = 'api') {
        $key = $this->generateKey($identifier, $type);
        
        if ($this->redis) {
            try {
                $current = $this->redis->get($key) ?: 0;
                return max(0, $limit - $current);
            } catch (Exception $e) {
                return $limit;
            }
        } else {
            try {
                $now = time();
                $window_start = $now - $window;
                
                $stmt = $this->db->prepare("
                    SELECT COUNT(*) as count 
                    FROM rate_limits 
                    WHERE rate_key = ? AND created_at >= ?
                ");
                $stmt->execute([$key, $window_start]);
                $result = $stmt->fetch(PDO::FETCH_ASSOC);
                
                $current_count = $result['count'] ?? 0;
                return max(0, $limit - $current_count);
                
            } catch (Exception $e) {
                return $limit;
            }
        }
    }
    
    /**
     * Reset rate limit for identifier
     */
    public function resetLimit($identifier, $type = 'api') {
        $key = $this->generateKey($identifier, $type);
        
        if ($this->redis) {
            try {
                $this->redis->del($key);
            } catch (Exception $e) {
                error_log('Redis reset failed: ' . $e->getMessage());
            }
        } else {
            try {
                $stmt = $this->db->prepare("DELETE FROM rate_limits WHERE rate_key = ?");
                $stmt->execute([$key]);
            } catch (Exception $e) {
                error_log('Database reset failed: ' . $e->getMessage());
            }
        }
    }
    
    /**
     * Generate rate limit key
     */
    private function generateKey($identifier, $type) {
        return "rate_limit:{$type}:{$identifier}";
    }
    
    /**
     * Get client identifier (IP or user ID)
     */
    public static function getClientIdentifier() {
        // Use user ID if logged in
        if (isset($_SESSION['user_id'])) {
            return 'user_' . $_SESSION['user_id'];
        }
        
        // Otherwise use IP address
        return self::getClientIP();
    }
    
    /**
     * Get client IP address
     */
    public static function getClientIP() {
        $ip_keys = ['HTTP_CF_CONNECTING_IP', 'HTTP_X_FORWARDED_FOR', 'HTTP_X_REAL_IP', 'REMOTE_ADDR'];
        
        foreach ($ip_keys as $key) {
            if (!empty($_SERVER[$key])) {
                $ip = $_SERVER[$key];
                
                // Handle comma-separated IPs (X-Forwarded-For)
                if (strpos($ip, ',') !== false) {
                    $ip = trim(explode(',', $ip)[0]);
                }
                
                // Validate IP
                if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
                    return $ip;
                }
            }
        }
        
        return $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
    }
    
    /**
     * Check if IP is whitelisted
     */
    public function isWhitelisted($ip) {
        $whitelist = [
            '127.0.0.1',
            '::1',
            // Add more IPs as needed
        ];
        
        return in_array($ip, $whitelist);
    }
    
    /**
     * Block suspicious activity
     */
    public function blockSuspiciousActivity($identifier, $reason = 'Suspicious activity detected') {
        try {
            $stmt = $this->db->prepare("
                INSERT INTO security_blocks (identifier, reason, blocked_at, expires_at) 
                VALUES (?, ?, ?, ?)
            ");
            
            $blocked_at = time();
            $expires_at = $blocked_at + (24 * 3600); // Block for 24 hours
            
            $stmt->execute([$identifier, $reason, $blocked_at, $expires_at]);
            
            // Log security event
            error_log("Security block applied: {$identifier} - {$reason}");
            
        } catch (Exception $e) {
            error_log('Failed to block suspicious activity: ' . $e->getMessage());
        }
    }
    
    /**
     * Check if identifier is blocked
     */
    public function isBlocked($identifier) {
        try {
            $stmt = $this->db->prepare("
                SELECT COUNT(*) as count 
                FROM security_blocks 
                WHERE identifier = ? AND expires_at > ?
            ");
            $stmt->execute([$identifier, time()]);
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            
            return ($result['count'] ?? 0) > 0;
            
        } catch (Exception $e) {
            error_log('Failed to check block status: ' . $e->getMessage());
            return false;
        }
    }
}

// Helper functions for easy access
function checkRateLimit($limit = 100, $window = 3600, $type = 'api') {
    $rateLimiter = RateLimiter::getInstance();
    $identifier = RateLimiter::getClientIdentifier();
    
    // Check if blocked
    if ($rateLimiter->isBlocked($identifier)) {
        http_response_code(429);
        header('Content-Type: application/json');
        echo json_encode([
            'error' => 'Access blocked due to suspicious activity',
            'code' => 'BLOCKED'
        ]);
        exit;
    }
    
    // Check if whitelisted
    $ip = RateLimiter::getClientIP();
    if ($rateLimiter->isWhitelisted($ip)) {
        return true;
    }
    
    // Check rate limit
    if (!$rateLimiter->checkLimit($identifier, $limit, $window, $type)) {
        http_response_code(429);
        header('Content-Type: application/json');
        header('Retry-After: ' . $window);
        echo json_encode([
            'error' => 'Rate limit exceeded',
            'code' => 'RATE_LIMIT_EXCEEDED',
            'retry_after' => $window
        ]);
        exit;
    }
    
    return true;
}

function getRemainingRequests($limit = 100, $window = 3600, $type = 'api') {
    $rateLimiter = RateLimiter::getInstance();
    $identifier = RateLimiter::getClientIdentifier();
    return $rateLimiter->getRemainingRequests($identifier, $limit, $window, $type);
}

?>