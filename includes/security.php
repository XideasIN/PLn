<?php
/**
 * Advanced Security and Copy Protection
 * LoanFlow Personal Loan Management System
 */

class SecurityManager {
    
    private static $protection_enabled = true;
    private static $allowed_ips = [];
    private static $blocked_user_agents = [
        'wget', 'curl', 'HTTrack', 'WebCopier', 'WebZIP', 'WebReaper',
        'Teleport', 'WebStripper', 'WebSucker', 'WebWhacker', 'Grabber',
        'SiteSnagger', 'ProWebWalker', 'BackStreet', 'toCrawl', 'Offline',
        'PageGrabber', 'SmartDownload', 'FlashGet', 'GetRight'
    ];
    
    /**
     * Initialize security protection
     */
    public static function init() {
        if (!self::$protection_enabled) {
            return;
        }
        
        // Start output buffering to prevent content scraping
        ob_start([self::class, 'protectOutput']);
        
        // Set security headers
        self::setSecurityHeaders();
        
        // Check for suspicious activity
        self::detectSuspiciousActivity();
        
        // Protect against common attacks
        self::preventCommonAttacks();
        
        // Add JavaScript protection
        add_action('wp_footer', [self::class, 'addJavaScriptProtection']);
    }
    
    /**
     * Set comprehensive security headers
     */
    public static function setSecurityHeaders() {
        // Prevent framing (clickjacking protection)
        header('X-Frame-Options: SAMEORIGIN');
        header('Content-Security-Policy: frame-ancestors \'self\'');
        
        // XSS Protection
        header('X-XSS-Protection: 1; mode=block');
        
        // Content Type Options
        header('X-Content-Type-Options: nosniff');
        
        // Referrer Policy
        header('Referrer-Policy: strict-origin-when-cross-origin');
        
        // Prevent MIME type sniffing
        header('X-Download-Options: noopen');
        
        // Strict Transport Security (if HTTPS)
        if (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') {
            header('Strict-Transport-Security: max-age=31536000; includeSubDomains; preload');
        }
        
        // Feature Policy
        header('Permissions-Policy: geolocation=(), microphone=(), camera=()');
        
        // Prevent caching of sensitive pages
        if (self::isSensitivePage()) {
            header('Cache-Control: no-cache, no-store, must-revalidate, private');
            header('Pragma: no-cache');
            header('Expires: 0');
        }
    }
    
    /**
     * Detect suspicious activity
     */
    public static function detectSuspiciousActivity() {
        $user_agent = $_SERVER['HTTP_USER_AGENT'] ?? '';
        $ip_address = self::getRealIpAddress();
        
        // Check for blocked user agents
        foreach (self::$blocked_user_agents as $blocked_agent) {
            if (stripos($user_agent, $blocked_agent) !== false) {
                self::blockRequest('Blocked user agent detected: ' . $blocked_agent);
            }
        }
        
        // Check for empty user agent (common in scrapers)
        if (empty($user_agent)) {
            self::blockRequest('Empty user agent detected');
        }
        
        // Check for suspicious patterns in user agent
        $suspicious_patterns = [
            '/bot/i', '/spider/i', '/crawler/i', '/scraper/i',
            '/harvest/i', '/extract/i', '/parser/i', '/scanner/i'
        ];
        
        foreach ($suspicious_patterns as $pattern) {
            if (preg_match($pattern, $user_agent) && !self::isAllowedBot($user_agent)) {
                self::blockRequest('Suspicious user agent pattern: ' . $user_agent);
            }
        }
        
        // Check request frequency
        self::checkRequestFrequency($ip_address);
        
        // Check for suspicious request parameters
        self::checkSuspiciousParameters();
    }
    
    /**
     * Prevent common attacks
     */
    public static function preventCommonAttacks() {
        // SQL Injection protection
        $suspicious_sql = ['union', 'select', 'insert', 'update', 'delete', 'drop', 'create', 'alter'];
        foreach ($_REQUEST as $key => $value) {
            if (is_string($value)) {
                foreach ($suspicious_sql as $sql_keyword) {
                    if (stripos($value, $sql_keyword) !== false && preg_match('/\b' . $sql_keyword . '\b/i', $value)) {
                        self::blockRequest('SQL injection attempt detected');
                    }
                }
            }
        }
        
        // XSS protection
        foreach ($_REQUEST as $key => $value) {
            if (is_string($value) && (stripos($value, '<script') !== false || stripos($value, 'javascript:') !== false)) {
                self::blockRequest('XSS attempt detected');
            }
        }
        
        // Directory traversal protection
        foreach ($_REQUEST as $key => $value) {
            if (is_string($value) && (strpos($value, '../') !== false || strpos($value, '..\\') !== false)) {
                self::blockRequest('Directory traversal attempt detected');
            }
        }
        
        // File inclusion protection
        if (preg_match('/\.(php|phtml|php3|php4|php5|inc)$/i', $_SERVER['REQUEST_URI'] ?? '')) {
            if (strpos($_SERVER['REQUEST_URI'], '=') !== false) {
                self::blockRequest('File inclusion attempt detected');
            }
        }
    }
    
    /**
     * Check request frequency for rate limiting
     */
    private static function checkRequestFrequency($ip_address) {
        $cache_file = sys_get_temp_dir() . '/loanflow_requests_' . md5($ip_address);
        $current_time = time();
        $time_window = 60; // 1 minute
        $max_requests = 30; // Maximum requests per minute
        
        $requests = [];
        if (file_exists($cache_file)) {
            $requests = json_decode(file_get_contents($cache_file), true) ?: [];
        }
        
        // Remove old requests
        $requests = array_filter($requests, function($timestamp) use ($current_time, $time_window) {
            return ($current_time - $timestamp) < $time_window;
        });
        
        // Add current request
        $requests[] = $current_time;
        
        // Check if limit exceeded
        if (count($requests) > $max_requests) {
            self::blockRequest('Rate limit exceeded');
        }
        
        // Save updated requests
        file_put_contents($cache_file, json_encode($requests));
    }
    
    /**
     * Check for suspicious request parameters
     */
    private static function checkSuspiciousParameters() {
        $suspicious_params = [
            'debug', 'test', 'admin', 'root', 'config', 'backup',
            'download', 'export', 'dump', 'sql', 'database'
        ];
        
        foreach ($_REQUEST as $key => $value) {
            if (in_array(strtolower($key), $suspicious_params) && !self::isAuthorizedUser()) {
                self::blockRequest('Suspicious parameter detected: ' . $key);
            }
        }
    }
    
    /**
     * Protect output content
     */
    public static function protectOutput($content) {
        if (!self::$protection_enabled) {
            return $content;
        }
        
        // Add anti-copy JavaScript and CSS
        $protection_code = self::getProtectionCode();
        
        // Inject protection code before closing body tag
        $content = str_replace('</body>', $protection_code . '</body>', $content);
        
        // Obfuscate email addresses
        $content = preg_replace_callback('/([a-zA-Z0-9._%+-]+@[a-zA-Z0-9.-]+\.[a-zA-Z]{2,})/', function($matches) {
            return self::obfuscateEmail($matches[1]);
        }, $content);
        
        // Add watermark to images (basic implementation)
        // This would be enhanced with actual image processing
        
        return $content;
    }
    
    /**
     * Get protection JavaScript and CSS code
     */
    private static function getProtectionCode() {
        return '
        <style>
            /* Disable text selection */
            body {
                -webkit-user-select: none;
                -moz-user-select: none;
                -ms-user-select: none;
                user-select: none;
                -webkit-touch-callout: none;
                -webkit-tap-highlight-color: transparent;
            }
            
            /* Hide content during potential scraping */
            .protected-content {
                visibility: hidden;
            }
            
            /* Anti-print styles */
            @media print {
                body * {
                    visibility: hidden !important;
                }
                body::before {
                    content: "This document is protected and cannot be printed.";
                    visibility: visible !important;
                    position: absolute;
                    top: 50%;
                    left: 50%;
                    transform: translate(-50%, -50%);
                    font-size: 24px;
                    color: red;
                }
            }
        </style>
        
        <script>
            (function() {
                "use strict";
                
                // Disable right-click context menu
                document.addEventListener("contextmenu", function(e) {
                    e.preventDefault();
                    return false;
                });
                
                // Disable common keyboard shortcuts
                document.addEventListener("keydown", function(e) {
                    // Disable F12 (Developer Tools)
                    if (e.keyCode === 123) {
                        e.preventDefault();
                        return false;
                    }
                    
                    // Disable Ctrl+Shift+I (Developer Tools)
                    if (e.ctrlKey && e.shiftKey && e.keyCode === 73) {
                        e.preventDefault();
                        return false;
                    }
                    
                    // Disable Ctrl+Shift+J (Console)
                    if (e.ctrlKey && e.shiftKey && e.keyCode === 74) {
                        e.preventDefault();
                        return false;
                    }
                    
                    // Disable Ctrl+U (View Source)
                    if (e.ctrlKey && e.keyCode === 85) {
                        e.preventDefault();
                        return false;
                    }
                    
                    // Disable Ctrl+S (Save Page)
                    if (e.ctrlKey && e.keyCode === 83) {
                        e.preventDefault();
                        return false;
                    }
                    
                    // Disable Ctrl+A (Select All)
                    if (e.ctrlKey && e.keyCode === 65) {
                        e.preventDefault();
                        return false;
                    }
                    
                    // Disable Ctrl+C (Copy)
                    if (e.ctrlKey && e.keyCode === 67) {
                        e.preventDefault();
                        return false;
                    }
                    
                    // Disable Ctrl+V (Paste)
                    if (e.ctrlKey && e.keyCode === 86) {
                        e.preventDefault();
                        return false;
                    }
                    
                    // Disable Ctrl+P (Print)
                    if (e.ctrlKey && e.keyCode === 80) {
                        e.preventDefault();
                        return false;
                    }
                });
                
                // Disable drag and drop
                document.addEventListener("dragstart", function(e) {
                    e.preventDefault();
                    return false;
                });
                
                // Disable image saving
                document.addEventListener("DOMContentLoaded", function() {
                    var images = document.getElementsByTagName("img");
                    for (var i = 0; i < images.length; i++) {
                        images[i].addEventListener("dragstart", function(e) {
                            e.preventDefault();
                            return false;
                        });
                        
                        images[i].addEventListener("contextmenu", function(e) {
                            e.preventDefault();
                            return false;
                        });
                        
                        // Add transparent overlay to prevent easy downloading
                        var overlay = document.createElement("div");
                        overlay.style.position = "absolute";
                        overlay.style.top = "0";
                        overlay.style.left = "0";
                        overlay.style.width = "100%";
                        overlay.style.height = "100%";
                        overlay.style.background = "transparent";
                        overlay.style.zIndex = "1000";
                        
                        if (images[i].parentNode.style.position !== "relative") {
                            images[i].parentNode.style.position = "relative";
                        }
                        images[i].parentNode.appendChild(overlay);
                    }
                });
                
                // Detect developer tools
                var devtools = {
                    open: false,
                    orientation: null
                };
                
                setInterval(function() {
                    if (window.outerHeight - window.innerHeight > 200 || window.outerWidth - window.innerWidth > 200) {
                        if (!devtools.open) {
                            devtools.open = true;
                            // Redirect or show warning
                            window.location.href = "about:blank";
                        }
                    } else {
                        devtools.open = false;
                    }
                }, 500);
                
                // Disable console
                if (typeof console !== "undefined") {
                    console.log = function() {};
                    console.warn = function() {};
                    console.error = function() {};
                    console.info = function() {};
                    console.debug = function() {};
                    console.trace = function() {};
                }
                
                // Show protected content after JavaScript loads
                document.addEventListener("DOMContentLoaded", function() {
                    var protectedElements = document.querySelectorAll(".protected-content");
                    for (var i = 0; i < protectedElements.length; i++) {
                        protectedElements[i].style.visibility = "visible";
                    }
                });
                
                // Add random delays to make automated scraping difficult
                setTimeout(function() {
                    // Additional protection code can be added here
                }, Math.random() * 1000);
                
            })();
        </script>';
    }
    
    /**
     * Obfuscate email addresses
     */
    private static function obfuscateEmail($email) {
        $encoded = '';
        for ($i = 0; $i < strlen($email); $i++) {
            $encoded .= '&#' . ord($email[$i]) . ';';
        }
        return $encoded;
    }
    
    /**
     * Block request and log attempt
     */
    private static function blockRequest($reason) {
        $ip_address = self::getRealIpAddress();
        $user_agent = $_SERVER['HTTP_USER_AGENT'] ?? '';
        $request_uri = $_SERVER['REQUEST_URI'] ?? '';
        
        // Log the blocked request
        error_log("SECURITY BLOCK: $reason | IP: $ip_address | UA: $user_agent | URI: $request_uri");
        
        // Log to database if available
        try {
            if (function_exists('logAudit')) {
                logAudit('security_block', 'security', null, null, [
                    'reason' => $reason,
                    'ip_address' => $ip_address,
                    'user_agent' => $user_agent,
                    'request_uri' => $request_uri
                ]);
            }
        } catch (Exception $e) {
            // Ignore database errors during security blocking
        }
        
        // Return 403 Forbidden
        http_response_code(403);
        header('Content-Type: text/html; charset=UTF-8');
        
        echo '<!DOCTYPE html>
        <html>
        <head>
            <title>Access Denied</title>
            <meta name="robots" content="noindex, nofollow">
        </head>
        <body style="font-family: Arial, sans-serif; text-align: center; padding: 50px;">
            <h1>Access Denied</h1>
            <p>Your request has been blocked for security reasons.</p>
            <p>If you believe this is an error, please contact support.</p>
            <hr>
            <p><small>Reference ID: ' . md5($ip_address . time()) . '</small></p>
        </body>
        </html>';
        
        exit;
    }
    
    /**
     * Get real IP address
     */
    private static function getRealIpAddress() {
        $ip_keys = ['HTTP_CF_CONNECTING_IP', 'HTTP_X_FORWARDED_FOR', 'HTTP_X_FORWARDED', 'HTTP_X_CLUSTER_CLIENT_IP', 'HTTP_FORWARDED_FOR', 'HTTP_FORWARDED', 'REMOTE_ADDR'];
        
        foreach ($ip_keys as $key) {
            if (array_key_exists($key, $_SERVER) === true) {
                $ip = trim($_SERVER[$key]);
                if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) !== false) {
                    return $ip;
                }
                
                // Handle comma-separated IPs
                if (strpos($ip, ',') !== false) {
                    $ips = explode(',', $ip);
                    $ip = trim($ips[0]);
                    if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) !== false) {
                        return $ip;
                    }
                }
            }
        }
        
        return $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
    }
    
    /**
     * Check if current page is sensitive
     */
    private static function isSensitivePage() {
        $sensitive_paths = ['/admin/', '/client/', '/login.php', '/register.php', '/forgot-password.php'];
        $current_path = $_SERVER['REQUEST_URI'] ?? '';
        
        foreach ($sensitive_paths as $path) {
            if (strpos($current_path, $path) !== false) {
                return true;
            }
        }
        
        return false;
    }
    
    /**
     * Check if bot is allowed (legitimate search engines)
     */
    private static function isAllowedBot($user_agent) {
        $allowed_bots = ['Googlebot', 'Bingbot', 'Slurp', 'DuckDuckBot', 'Baiduspider', 'YandexBot', 'facebookexternalhit'];
        
        foreach ($allowed_bots as $bot) {
            if (stripos($user_agent, $bot) !== false) {
                return true;
            }
        }
        
        return false;
    }
    
    /**
     * Check if current user is authorized
     */
    private static function isAuthorizedUser() {
        return isset($_SESSION['user_id']) && isset($_SESSION['role']) && 
               in_array($_SESSION['role'], ['admin', 'super_admin']);
    }
    
    /**
     * Add .htaccess protection rules
     */
    public static function generateHtaccessRules() {
        return '
# LoanFlow Security Protection Rules
# Generated automatically - do not edit manually

# Disable directory browsing
Options -Indexes

# Protect sensitive files
<FilesMatch "\.(htaccess|htpasswd|ini|log|sh|sql|bak|backup)$">
    Order Allow,Deny
    Deny from all
</FilesMatch>

# Block suspicious user agents
<IfModule mod_rewrite.c>
    RewriteEngine On
    RewriteCond %{HTTP_USER_AGENT} (wget|curl|HTTrack|WebCopier|WebZIP|WebReaper|Teleport|WebStripper|WebSucker|WebWhacker|Grabber|SiteSnagger|ProWebWalker|BackStreet|toCrawl|Offline|PageGrabber|SmartDownload|FlashGet|GetRight) [NC]
    RewriteRule .* - [F,L]
</IfModule>

# Prevent access to backup and temporary files
<FilesMatch "\.(bak|backup|old|orig|original|tmp|temp|~)$">
    Order Allow,Deny
    Deny from all
</FilesMatch>

# Block access to version control directories
<IfModule mod_rewrite.c>
    RewriteEngine On
    RewriteRule ^\.git - [F,L]
    RewriteRule ^\.svn - [F,L]
    RewriteRule ^\.hg - [F,L]
</IfModule>

# Prevent hotlinking
<IfModule mod_rewrite.c>
    RewriteEngine On
    RewriteCond %{HTTP_REFERER} !^$
    RewriteCond %{HTTP_REFERER} !^http(s)?://(www\.)?yourdomain.com [NC]
    RewriteRule \.(jpg|jpeg|png|gif|svg|pdf)$ - [F]
</IfModule>

# Security headers
<IfModule mod_headers.c>
    Header always set X-Frame-Options SAMEORIGIN
    Header always set X-Content-Type-Options nosniff
    Header always set X-XSS-Protection "1; mode=block"
    Header always set Referrer-Policy "strict-origin-when-cross-origin"
    Header always set Permissions-Policy "geolocation=(), microphone=(), camera=()"
</IfModule>

# Rate limiting (basic)
<IfModule mod_rewrite.c>
    RewriteEngine On
    # Block IPs that make more than 30 requests per minute
    # This would require additional server-level configuration
</IfModule>
';
    }
    
    /**
     * Enable or disable protection
     */
    public static function setProtectionEnabled($enabled) {
        self::$protection_enabled = $enabled;
    }
    
    /**
     * Add allowed IP address
     */
    public static function addAllowedIp($ip) {
        if (!in_array($ip, self::$allowed_ips)) {
            self::$allowed_ips[] = $ip;
        }
    }
    
    /**
     * Check if IP is allowed
     */
    public static function isIpAllowed($ip) {
        return empty(self::$allowed_ips) || in_array($ip, self::$allowed_ips);
    }
}

// Initialize security protection
if (!defined('DISABLE_SECURITY_PROTECTION')) {
    SecurityManager::init();
}
