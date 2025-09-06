<?php
/**
 * Authentication System
 * Handles user login, logout, session management, and access control
 */

if (!defined('SECURE_ACCESS')) {
    die('Direct access not permitted');
}

class Auth {
    private static $instance = null;
    private $db;
    
    private function __construct() {
        $this->db = Database::getInstance()->getConnection();
        $this->startSession();
    }
    
    public static function getInstance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    /**
     * Start secure session
     */
    private function startSession() {
        if (session_status() === PHP_SESSION_NONE) {
            // Secure session configuration
            ini_set('session.cookie_httponly', 1);
            ini_set('session.cookie_secure', isset($_SERVER['HTTPS']));
            ini_set('session.use_strict_mode', 1);
            ini_set('session.cookie_samesite', 'Strict');
            
            session_start();
            
            // Regenerate session ID periodically
            if (!isset($_SESSION['last_regeneration'])) {
                $this->regenerateSession();
            } elseif (time() - $_SESSION['last_regeneration'] > 300) { // 5 minutes
                $this->regenerateSession();
            }
        }
    }
    
    /**
     * Regenerate session ID for security
     */
    private function regenerateSession() {
        session_regenerate_id(true);
        $_SESSION['last_regeneration'] = time();
    }
    
    /**
     * Authenticate user login
     */
    public function login($email, $password, $remember_me = false) {
        try {
            // Validate input
            if (empty($email) || empty($password)) {
                throw new Exception('Email and password are required');
            }
            
            if (!validate_email($email)) {
                throw new Exception('Invalid email format');
            }
            
            // Check for rate limiting
            if ($this->isRateLimited($email)) {
                throw new Exception('Too many login attempts. Please try again later.');
            }
            
            // Find user in database
            $sql = "SELECT id, email, password_hash as password, first_name, last_name, role as user_type, status, 
                           failed_login_attempts, last_login, password_changed 
                    FROM users WHERE email = :email";
            $stmt = $this->db->prepare($sql);
            $stmt->execute([':email' => $email]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$user) {
                $this->recordFailedAttempt($email);
                throw new Exception('Invalid email or password');
            }
            
            // Check if account is active
            if ($user['status'] !== 'active') {
                throw new Exception('Account is not active. Please contact support.');
            }
            
            // Verify password
            if (!password_verify($password, $user['password'])) {
                $this->recordFailedAttempt($email, $user['id']);
                throw new Exception('Invalid email or password');
            }
            
            // Check if admin needs to change password
            if (in_array($user['user_type'], ['admin', 'super_admin']) && !$user['password_changed']) {
                $_SESSION['admin_needs_password_change'] = true;
                $_SESSION['admin_id'] = $user['id'];
                return [
                    'success' => true,
                    'redirect' => '/admin/first-login-setup.php',
                    'message' => 'Password change required for security'
                ];
            }
            
            // Reset failed attempts on successful login
            $this->resetFailedAttempts($user['id']);
            
            // Create session
            $this->createUserSession($user);
            
            // Handle remember me
            if ($remember_me) {
                $this->createRememberToken($user['id']);
            }
            
            // Update last login
            $this->updateLastLogin($user['id']);
            
            return [
                'success' => true,
                'user' => [
                    'id' => $user['id'],
                    'email' => $user['email'],
                    'name' => $user['first_name'] . ' ' . $user['last_name'],
                    'user_type' => $user['user_type']
                ]
            ];
            
        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => $e->getMessage()
            ];
        }
    }
    
    /**
     * Create user session
     */
    private function createUserSession($user) {
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['user_email'] = $user['email'];
        $_SESSION['user_name'] = $user['first_name'] . ' ' . $user['last_name'];
        $_SESSION['user_type'] = $user['user_type'];
        $_SESSION['logged_in'] = true;
        $_SESSION['login_time'] = time();
    }
    
    /**
     * Check if user is logged in
     */
    public function isLoggedIn() {
        return isset($_SESSION['logged_in']) && $_SESSION['logged_in'] === true;
    }
    
    /**
     * Get current user data
     */
    public function getCurrentUser() {
        if (!$this->isLoggedIn()) {
            return null;
        }
        
        return [
            'id' => $_SESSION['user_id'],
            'email' => $_SESSION['user_email'],
            'name' => $_SESSION['user_name'],
            'user_type' => $_SESSION['user_type']
        ];
    }
    
    /**
     * Check if user has specific role
     */
    public function hasRole($role) {
        if (!$this->isLoggedIn()) {
            return false;
        }
        
        return $_SESSION['user_type'] === $role;
    }
    
    /**
     * Logout user
     */
    public function logout() {
        // Remove remember token if exists
        if (isset($_COOKIE['remember_token'])) {
            $this->removeRememberToken($_COOKIE['remember_token']);
            setcookie('remember_token', '', time() - 3600, '/', '', true, true);
        }
        
        // Clear session
        $_SESSION = [];
        
        // Destroy session cookie
        if (ini_get('session.use_cookies')) {
            $params = session_get_cookie_params();
            setcookie(session_name(), '', time() - 42000,
                $params['path'], $params['domain'],
                $params['secure'], $params['httponly']
            );
        }
        
        session_destroy();
    }
    
    /**
     * Check rate limiting for login attempts
     */
    private function isRateLimited($email) {
        $sql = "SELECT failed_login_attempts, last_login_attempt 
                FROM users WHERE email = :email";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([':email' => $email]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$user) {
            return false;
        }
        
        // Allow 5 attempts per 15 minutes
        if ($user['failed_login_attempts'] >= 5) {
            $last_attempt = strtotime($user['last_login_attempt']);
            if (time() - $last_attempt < 900) { // 15 minutes
                return true;
            }
        }
        
        return false;
    }
    
    /**
     * Record failed login attempt
     */
    private function recordFailedAttempt($email, $user_id = null) {
        if ($user_id) {
            $sql = "UPDATE users SET 
                    failed_login_attempts = failed_login_attempts + 1,
                    last_login_attempt = NOW()
                    WHERE id = :user_id";
            $stmt = $this->db->prepare($sql);
            $stmt->execute([':user_id' => $user_id]);
        }
        
        // Log attempt for security monitoring
        error_log("Failed login attempt for email: {$email} from IP: {$_SERVER['REMOTE_ADDR']}");
    }
    
    /**
     * Reset failed login attempts
     */
    private function resetFailedAttempts($user_id) {
        $sql = "UPDATE users SET 
                failed_login_attempts = 0,
                last_login_attempt = NULL
                WHERE id = :user_id";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([':user_id' => $user_id]);
    }
    
    /**
     * Update last login timestamp
     */
    private function updateLastLogin($user_id) {
        $sql = "UPDATE users SET last_login = NOW() WHERE id = :user_id";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([':user_id' => $user_id]);
    }
    
    /**
     * Create remember me token
     */
    private function createRememberToken($user_id) {
        $token = bin2hex(random_bytes(32));
        $expires = date('Y-m-d H:i:s', time() + (30 * 24 * 60 * 60)); // 30 days
        
        $sql = "INSERT INTO remember_tokens (user_id, token, expires_at) 
                VALUES (:user_id, :token, :expires_at)
                ON DUPLICATE KEY UPDATE token = :token, expires_at = :expires_at";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            ':user_id' => $user_id,
            ':token' => hash('sha256', $token),
            ':expires_at' => $expires
        ]);
        
        setcookie('remember_token', $token, time() + (30 * 24 * 60 * 60), '/', '', true, true);
    }
    
    /**
     * Remove remember token
     */
    private function removeRememberToken($token) {
        $sql = "DELETE FROM remember_tokens WHERE token = :token";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([':token' => hash('sha256', $token)]);
    }
    
    /**
     * Require login - redirect if not authenticated
     */
    public function requireLogin($redirect_url = '/login.php') {
        if (!$this->isLoggedIn()) {
            header('Location: ' . $redirect_url);
            exit();
        }
    }
    
    /**
     * Require specific role
     */
    public function requireRole($role, $redirect_url = '/unauthorized.php') {
        if (!$this->hasRole($role)) {
            header('Location: ' . $redirect_url);
            exit();
        }
    }
}

// Helper functions for backward compatibility
function auth() {
    return Auth::getInstance();
}

function is_logged_in() {
    return Auth::getInstance()->isLoggedIn();
}

function get_current_user() {
    return Auth::getInstance()->getCurrentUser();
}

function require_login($redirect_url = '/login.php') {
    Auth::getInstance()->requireLogin($redirect_url);
}

function require_role($role, $redirect_url = '/unauthorized.php') {
    Auth::getInstance()->requireRole($role, $redirect_url);
}
?>