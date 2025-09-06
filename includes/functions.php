<?php
/**
 * Core Functions
 * LoanFlow Personal Loan Management System
 */

// Include required files
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/email.php';
require_once __DIR__ . '/../config/countries.php';

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Error reporting (disable in production)
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Security headers
function setSecurityHeaders() {
    header('X-Content-Type-Options: nosniff');
    header('X-Frame-Options: DENY');
    header('X-XSS-Protection: 1; mode=block');
    header('Referrer-Policy: strict-origin-when-cross-origin');
    
    // HTTPS redirect in production
    if (!isset($_SERVER['HTTPS']) && $_SERVER['SERVER_PORT'] != 443) {
        // Uncomment in production
        // header('Location: https://' . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI']);
        // exit();
    }
}

// Generate CSRF token
function generateCSRFToken() {
    if (!isset($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

// Verify CSRF token
function verifyCSRFToken($token) {
    return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
}

// Check if admin needs password change and redirect
function checkAdminPasswordChange() {
    if (isset($_SESSION['admin_needs_password_change']) && $_SESSION['admin_needs_password_change']) {
        // Allow access to first-login-setup.php and logout.php
        $current_page = basename($_SERVER['PHP_SELF']);
        if (!in_array($current_page, ['first-login-setup.php', 'logout.php'])) {
            header('Location: first-login-setup.php');
            exit;
        }
    }
}

// Require admin role with password change check
function requireRole($required_role) {
    if (!isLoggedIn()) {
        header('Location: ../login.php');
        exit;
    }
    
    $user = getCurrentUser();
    if (!$user || !hasRole($user['role'], $required_role)) {
        header('Location: ../unauthorized.php');
        exit;
    }
    
    // Check if admin needs password change
    if (in_array($user['role'], ['admin', 'super_admin'])) {
        checkAdminPasswordChange();
    }
}

// Check if user is logged in
function isLoggedIn() {
    return isset($_SESSION['user_id']) && !empty($_SESSION['user_id']);
}

// Get current user
function getCurrentUser() {
    if (!isLoggedIn()) {
        return null;
    }
    
    try {
        $db = getDB();
        $stmt = $db->prepare("SELECT id, email, first_name, last_name, role, status, password_changed FROM users WHERE id = ?");
        $stmt->execute([$_SESSION['user_id']]);
        return $stmt->fetch();
    } catch (Exception $e) {
        error_log("Get current user failed: " . $e->getMessage());
        return null;
    }
}

// Check if user has required role
function hasRole($user_role, $required_role) {
    $role_hierarchy = [
        'client' => 1,
        'agent' => 2,
        'admin' => 3,
        'super_admin' => 4
    ];
    
    return isset($role_hierarchy[$user_role]) && 
           isset($role_hierarchy[$required_role]) && 
           $role_hierarchy[$user_role] >= $role_hierarchy[$required_role];
}

// Generate reference number
function generateReferenceNumber() {
    try {
        $db = getDB();
        
        // Get the starting reference number from settings
        $stmt = $db->prepare("SELECT setting_value FROM system_settings WHERE setting_key = 'reference_start'");
        $stmt->execute();
        $result = $stmt->fetch();
        $start = (int)($result['setting_value'] ?? 100000);
        
        // Get the highest current reference number
        $stmt = $db->prepare("SELECT MAX(CAST(reference_number AS UNSIGNED)) as max_ref FROM users WHERE reference_number REGEXP '^[0-9]+$'");
        $stmt->execute();
        $result = $stmt->fetch();
        $max_ref = (int)($result['max_ref'] ?? $start - 1);
        
        return str_pad($max_ref + 1, 6, '0', STR_PAD_LEFT);
    } catch (Exception $e) {
        error_log("Reference number generation failed: " . $e->getMessage());
        return str_pad(mt_rand(100000, 999999), 6, '0', STR_PAD_LEFT);
    }
}

// Hash password
function hashPassword($password) {
    return password_hash($password, PASSWORD_DEFAULT);
}

// Verify password
function verifyPassword($password, $hash) {
    return password_verify($password, $hash);
}

// Generate secure random token
function generateToken($length = 32) {
    return bin2hex(random_bytes($length));
}

// Sanitize input
function sanitizeInput($input) {
    if (is_array($input)) {
        return array_map('sanitizeInput', $input);
    }
    return htmlspecialchars(trim($input), ENT_QUOTES, 'UTF-8');
}

// Validate email
function validateEmail($email) {
    return filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
}

// Check if user exists
function userExists($email) {
    try {
        $db = getDB();
        $stmt = $db->prepare("SELECT COUNT(*) as count FROM users WHERE email = ?");
        $stmt->execute([$email]);
        $result = $stmt->fetch();
        return $result['count'] > 0;
    } catch (Exception $e) {
        error_log("User exists check failed: " . $e->getMessage());
        return false;
    }
}

// Get user by email
function getUserByEmail($email) {
    try {
        $db = getDB();
        $stmt = $db->prepare("SELECT * FROM users WHERE email = ? LIMIT 1");
        $stmt->execute([$email]);
        return $stmt->fetch();
    } catch (Exception $e) {
        error_log("Get user by email failed: " . $e->getMessage());
        return false;
    }
}

// Get user by reference number
function getUserByReference($reference) {
    try {
        $db = getDB();
        $stmt = $db->prepare("SELECT * FROM users WHERE reference_number = ? LIMIT 1");
        $stmt->execute([$reference]);
        return $stmt->fetch();
    } catch (Exception $e) {
        error_log("Get user by reference failed: " . $e->getMessage());
        return false;
    }
}

// Get user by ID
function getUserById($id) {
    try {
        $db = getDB();
        $stmt = $db->prepare("SELECT * FROM users WHERE id = ? LIMIT 1");
        $stmt->execute([$id]);
        return $stmt->fetch();
    } catch (Exception $e) {
        error_log("Get user by ID failed: " . $e->getMessage());
        return false;
    }
}

// Create new user
function createUser($data) {
    try {
        $db = getDB();
        
        $stmt = $db->prepare("
            INSERT INTO users (
                reference_number, email, password_hash, first_name, last_name, 
                phone, date_of_birth, country, state_province, city, address, 
                postal_zip, sin_ssn, role, status, verification_token, ip_address
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");
        
        $result = $stmt->execute([
            $data['reference_number'],
            $data['email'],
            $data['password_hash'],
            $data['first_name'],
            $data['last_name'],
            $data['phone'] ?? null,
            $data['date_of_birth'] ?? null,
            $data['country'] ?? 'USA',
            $data['state_province'] ?? null,
            $data['city'] ?? null,
            $data['address'] ?? null,
            $data['postal_zip'] ?? null,
            $data['sin_ssn'] ?? null,
            $data['role'] ?? 'client',
            $data['status'] ?? 'active',
            $data['verification_token'] ?? null,
            $_SERVER['REMOTE_ADDR'] ?? null
        ]);
        
        if ($result) {
            return $db->lastInsertId();
        }
        
        return false;
    } catch (Exception $e) {
        error_log("Create user failed: " . $e->getMessage());
        return false;
    }
}

// Update user last login
function updateLastLogin($user_id) {
    try {
        $db = getDB();
        $stmt = $db->prepare("UPDATE users SET last_login = NOW(), failed_login_attempts = 0, ip_address = ? WHERE id = ?");
        return $stmt->execute([$_SERVER['REMOTE_ADDR'] ?? null, $user_id]);
    } catch (Exception $e) {
        error_log("Update last login failed: " . $e->getMessage());
        return false;
    }
}

// Handle failed login attempt
function handleFailedLogin($email) {
    try {
        $db = getDB();
        
        // Increment failed attempts
        $stmt = $db->prepare("UPDATE users SET failed_login_attempts = failed_login_attempts + 1 WHERE email = ?");
        $stmt->execute([$email]);
        
        // Check if account should be locked
        $user = getUserByEmail($email);
        if ($user && $user['failed_login_attempts'] >= 5) {
            $lock_until = date('Y-m-d H:i:s', strtotime('+30 minutes'));
            $stmt = $db->prepare("UPDATE users SET locked_until = ? WHERE email = ?");
            $stmt->execute([$lock_until, $email]);
            return 'locked';
        }
        
        return 'failed';
    } catch (Exception $e) {
        error_log("Handle failed login failed: " . $e->getMessage());
        return 'error';
    }
}

// Check if account is locked
function isAccountLocked($user) {
    if (!$user || !$user['locked_until']) {
        return false;
    }
    
    return strtotime($user['locked_until']) > time();
}

// Get system setting
function getSystemSetting($key, $default = null) {
    try {
        $db = getDB();
        $stmt = $db->prepare("SELECT setting_value, setting_type FROM system_settings WHERE setting_key = ?");
        $stmt->execute([$key]);
        $result = $stmt->fetch();
        
        if (!$result) {
            return $default;
        }
        
        $value = $result['setting_value'];
        
        switch ($result['setting_type']) {
            case 'integer':
                return (int)$value;
            case 'boolean':
                return (bool)$value;
            case 'json':
                return json_decode($value, true);
            default:
                return $value;
        }
    } catch (Exception $e) {
        error_log("Get system setting failed: " . $e->getMessage());
        return $default;
    }
}

// Set system setting
function setSystemSetting($key, $value, $type = 'string') {
    try {
        $db = getDB();
        
        if ($type === 'json') {
            $value = json_encode($value);
        }
        
        $stmt = $db->prepare("
            INSERT INTO system_settings (setting_key, setting_value, setting_type, updated_by) 
            VALUES (?, ?, ?, ?) 
            ON DUPLICATE KEY UPDATE 
            setting_value = VALUES(setting_value), 
            setting_type = VALUES(setting_type),
            updated_by = VALUES(updated_by),
            updated_at = NOW()
        ");
        
        return $stmt->execute([$key, $value, $type, getCurrentUserId()]);
    } catch (Exception $e) {
        error_log("Set system setting failed: " . $e->getMessage());
        return false;
    }
}

// Get current user ID from session
function getCurrentUserId() {
    return $_SESSION['user_id'] ?? null;
}

// Get current user
function getCurrentUser() {
    $user_id = getCurrentUserId();
    return $user_id ? getUserById($user_id) : null;
}

// Check if user is logged in
function isLoggedIn() {
    return isset($_SESSION['user_id']) && !empty($_SESSION['user_id']);
}

// Check user role
function hasRole($required_role) {
    $user = getCurrentUser();
    if (!$user) return false;
    
    $role_hierarchy = ['client' => 1, 'agent' => 2, 'admin' => 3, 'super_admin' => 4];
    $user_level = $role_hierarchy[$user['role']] ?? 0;
    $required_level = $role_hierarchy[$required_role] ?? 0;
    
    return $user_level >= $required_level;
}

// Require login
function requireLogin() {
    if (!isLoggedIn()) {
        header('Location: /login.php');
        exit();
    }
}

// Require role
function requireRole($role) {
    requireLogin();
    if (!hasRole($role)) {
        http_response_code(403);
        die('Access denied');
    }
}

// Log audit event
function logAudit($action, $table_name = null, $record_id = null, $old_values = null, $new_values = null) {
    try {
        $db = getDB();
        $stmt = $db->prepare("
            INSERT INTO audit_logs (user_id, action, table_name, record_id, old_values, new_values, ip_address, user_agent) 
            VALUES (?, ?, ?, ?, ?, ?, ?, ?)
        ");
        
        return $stmt->execute([
            getCurrentUserId(),
            $action,
            $table_name,
            $record_id,
            $old_values ? json_encode($old_values) : null,
            $new_values ? json_encode($new_values) : null,
            $_SERVER['REMOTE_ADDR'] ?? null,
            $_SERVER['HTTP_USER_AGENT'] ?? null
        ]);
    } catch (Exception $e) {
        error_log("Audit log failed: " . $e->getMessage());
        return false;
    }
}

// Add memo to client
function addMemo($user_id, $memo_text, $memo_type = 'manual', $is_internal = true) {
    try {
        $db = getDB();
        $stmt = $db->prepare("
            INSERT INTO client_memos (user_id, memo_text, memo_type, is_internal, created_by) 
            VALUES (?, ?, ?, ?, ?)
        ");
        
        $result = $stmt->execute([$user_id, $memo_text, $memo_type, $is_internal, getCurrentUserId()]);
        
        if ($result) {
            logAudit('memo_added', 'client_memos', $db->lastInsertId(), null, [
                'user_id' => $user_id,
                'memo_type' => $memo_type,
                'is_internal' => $is_internal
            ]);
        }
        
        return $result;
    } catch (Exception $e) {
        error_log("Add memo failed: " . $e->getMessage());
        return false;
    }
}

// Format date for display
function formatDate($date, $format = 'M j, Y') {
    if (!$date) return '';
    return date($format, strtotime($date));
}

// Format datetime for display
function formatDateTime($datetime, $format = 'M j, Y g:i A') {
    if (!$datetime) return '';
    return date($format, strtotime($datetime));
}

// Get file extension
function getFileExtension($filename) {
    return strtolower(pathinfo($filename, PATHINFO_EXTENSION));
}

// Check if file type is allowed
function isAllowedFileType($filename, $allowed_types = ['pdf', 'jpg', 'jpeg', 'png', 'doc', 'docx']) {
    $extension = getFileExtension($filename);
    return in_array($extension, $allowed_types);
}

// Generate secure filename
function generateSecureFilename($original_filename) {
    $extension = getFileExtension($original_filename);
    return uniqid() . '_' . time() . '.' . $extension;
}

// Format file size
function formatFileSize($bytes) {
    $units = ['B', 'KB', 'MB', 'GB'];
    $bytes = max($bytes, 0);
    $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
    $pow = min($pow, count($units) - 1);
    $bytes /= pow(1024, $pow);
    return round($bytes, 2) . ' ' . $units[$pow];
}

// Redirect with message
function redirectWithMessage($url, $message, $type = 'success') {
    $_SESSION['flash_message'] = $message;
    $_SESSION['flash_type'] = $type;
    header("Location: $url");
    exit();
}

// Get flash message
function getFlashMessage() {
    if (isset($_SESSION['flash_message'])) {
        $message = $_SESSION['flash_message'];
        $type = $_SESSION['flash_type'] ?? 'info';
        unset($_SESSION['flash_message'], $_SESSION['flash_type']);
        return ['message' => $message, 'type' => $type];
    }
    return null;
}

// Initialize security headers
setSecurityHeaders();

/**
 * Template Management Functions
 */

/**
 * Get available templates by type
 */
function getAvailableTemplates() {
    try {
        $db = getDB();
        
        // Initialize empty arrays
        $templates = [
            'frontend' => [],
            'client' => [],
            'admin' => []
        ];
        
        // Check if templates table exists
        $stmt = $db->query("SHOW TABLES LIKE 'templates'");
        if ($stmt->rowCount() == 0) {
            // Table doesn't exist, create it
            createTemplatesTable();
        }
        
        $stmt = $db->prepare("
            SELECT id, template_key, name, description, type, version, 
                   file_path, css_path, js_path, preview_image, is_active, 
                   is_ai_generated, created_at
            FROM templates 
            WHERE status = 'active'
            ORDER BY type, name
        ");
        $stmt->execute();
        $results = $stmt->fetchAll();
        
        foreach ($results as $template) {
            $templates[$template['type']][] = $template;
        }
        
        return $templates;
    } catch (Exception $e) {
        error_log("Get available templates failed: " . $e->getMessage());
        return [
            'frontend' => [],
            'client' => [],
            'admin' => []
        ];
    }
}

/**
 * Get active templates for each type
 */
function getActiveTemplates() {
    try {
        $db = getDB();
        
        $active_templates = [
            'frontend' => null,
            'client' => null,
            'admin' => null
        ];
        
        // Check if templates table exists
        $stmt = $db->query("SHOW TABLES LIKE 'templates'");
        if ($stmt->rowCount() == 0) {
            return $active_templates;
        }
        
        $stmt = $db->prepare("
            SELECT template_key, name, type
            FROM templates 
            WHERE is_active = 1 AND status = 'active'
        ");
        $stmt->execute();
        $results = $stmt->fetchAll();
        
        foreach ($results as $template) {
            $active_templates[$template['type']] = [
                'template_key' => $template['template_key'],
                'name' => $template['name']
            ];
        }
        
        return $active_templates;
    } catch (Exception $e) {
        error_log("Get active templates failed: " . $e->getMessage());
        return [
            'frontend' => null,
            'client' => null,
            'admin' => null
        ];
    }
}

/**
 * Switch active template
 */
function switchActiveTemplate($type, $template_key) {
    try {
        $db = getDB();
        
        // Validate template type
        $valid_types = ['frontend', 'client', 'admin'];
        if (!in_array($type, $valid_types)) {
            return false;
        }
        
        // Start transaction
        $db->beginTransaction();
        
        // Deactivate all templates of this type
        $stmt = $db->prepare("
            UPDATE templates 
            SET is_active = 0 
            WHERE type = ? AND status = 'active'
        ");
        $stmt->execute([$type]);
        
        // Activate the selected template
        $stmt = $db->prepare("
            UPDATE templates 
            SET is_active = 1 
            WHERE template_key = ? AND type = ? AND status = 'active'
        ");
        $result = $stmt->execute([$template_key, $type]);
        
        if ($result && $stmt->rowCount() > 0) {
            $db->commit();
            return true;
        } else {
            $db->rollBack();
            return false;
        }
    } catch (Exception $e) {
        if (isset($db)) {
            $db->rollBack();
        }
        error_log("Switch active template failed: " . $e->getMessage());
        return false;
    }
}

/**
 * Handle template upload
 */
function handleTemplateUpload($file, $form_data) {
    try {
        if (!$file || $file['error'] !== UPLOAD_ERR_OK) {
            return ['success' => false, 'error' => 'File upload failed'];
        }
        
        $template_name = sanitizeInput($form_data['template_name'] ?? '');
        $template_type = sanitizeInput($form_data['template_type'] ?? '');
        $template_version = sanitizeInput($form_data['template_version'] ?? '1.0.0');
        $template_description = sanitizeInput($form_data['template_description'] ?? '');
        
        // Validate required fields
        if (empty($template_name) || empty($template_type)) {
            return ['success' => false, 'error' => 'Template name and type are required'];
        }
        
        // Validate file type
        $file_extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        
        if (!in_array($file_extension, ['zip', 'html', 'php'])) {
            return ['success' => false, 'error' => 'Invalid file type. Only ZIP, HTML, and PHP files are allowed'];
        }
        
        // Create upload directory if it doesn't exist
        $upload_dir = '../uploads/templates/';
        if (!is_dir($upload_dir)) {
            mkdir($upload_dir, 0755, true);
        }
        
        // Generate unique template key
        $template_key = generateTemplateKey($template_name, $template_type);
        
        // Create template directory
        $template_dir = $upload_dir . $template_key . '/';
        if (!is_dir($template_dir)) {
            mkdir($template_dir, 0755, true);
        }
        
        // Move uploaded file
        $file_path = $template_dir . $file['name'];
        if (!move_uploaded_file($file['tmp_name'], $file_path)) {
            return ['success' => false, 'error' => 'Failed to save uploaded file'];
        }
        
        // Process file based on type
        if ($file_extension === 'zip') {
            // Extract ZIP file
            $zip = new ZipArchive();
            if ($zip->open($file_path) === TRUE) {
                $zip->extractTo($template_dir);
                $zip->close();
                unlink($file_path); // Remove ZIP file after extraction
            } else {
                return ['success' => false, 'error' => 'Failed to extract ZIP file'];
            }
        }
        
        // Save template to database
        $db = getDB();
        
        // Check if templates table exists
        $stmt = $db->query("SHOW TABLES LIKE 'templates'");
        if ($stmt->rowCount() == 0) {
            createTemplatesTable();
        }
        
        $stmt = $db->prepare("
            INSERT INTO templates (
                template_key, name, description, type, version, 
                file_path, is_active, is_ai_generated, created_by, status
            ) VALUES (?, ?, ?, ?, ?, ?, 0, 0, ?, 'active')
        ");
        
        $result = $stmt->execute([
            $template_key,
            $template_name,
            $template_description,
            $template_type,
            $template_version,
            $file_path,
            getCurrentUserId()
        ]);
        
        if ($result) {
            return [
                'success' => true,
                'template_key' => $template_key,
                'message' => 'Template uploaded successfully'
            ];
        } else {
            return ['success' => false, 'error' => 'Failed to save template to database'];
        }
        
    } catch (Exception $e) {
        error_log("Template upload failed: " . $e->getMessage());
        return ['success' => false, 'error' => 'Upload failed: ' . $e->getMessage()];
    }
}

/**
 * Generate unique template key
 */
function generateTemplateKey($name, $type) {
    $key = strtolower(trim($name));
    $key = preg_replace('/[^a-z0-9\-]/', '-', $key);
    $key = preg_replace('/-+/', '-', $key);
    $key = trim($key, '-');
    $key = $type . '-' . $key . '-' . uniqid();
    return $key;
}

/**
 * Delete template
 */
function deleteTemplate($template_id) {
    try {
        $db = getDB();
        
        // Get template info first
        $stmt = $db->prepare("SELECT * FROM templates WHERE id = ?");
        $stmt->execute([$template_id]);
        $template = $stmt->fetch();
        
        if (!$template) {
            return false;
        }
        
        // Don't allow deleting active templates
        if ($template['is_active']) {
            return false;
        }
        
        // Delete template record
        $stmt = $db->prepare("UPDATE templates SET status = 'deleted' WHERE id = ?");
        $result = $stmt->execute([$template_id]);
        
        return $result;
    } catch (Exception $e) {
        error_log("Delete template failed: " . $e->getMessage());
        return false;
    }
}

/**
 * Company Settings Functions
 */

/**
 * Get company settings
 */
function getCompanySettings() {
    try {
        $db = getDB();
        
        // Initialize default settings
        $default_settings = [
            'name' => 'LoanFlow',
            'email' => 'info@loanflow.com',
            'phone' => '+1 (555) 123-4567',
            'address' => '123 Business Street, Suite 100\nBusinessville, BV 12345',
            'website' => 'https://www.loanflow.com',
            'logo' => '',
            'registration' => '',
            'tax_id' => '',
            'description' => 'Professional personal loan services with fast approval and competitive rates.'
        ];
        
        // Check if system_settings table exists
        $stmt = $db->query("SHOW TABLES LIKE 'system_settings'");
        if ($stmt->rowCount() == 0) {
            createSystemSettingsTable();
            return $default_settings;
        }
        
        // Get company settings from database
        $stmt = $db->prepare("
            SELECT setting_key, setting_value 
            FROM system_settings 
            WHERE setting_key LIKE 'company_%'
        ");
        $stmt->execute();
        $results = $stmt->fetchAll();
        
        $settings = $default_settings;
        foreach ($results as $row) {
            $key = str_replace('company_', '', $row['setting_key']);
            $settings[$key] = $row['setting_value'];
        }
        
        return $settings;
    } catch (Exception $e) {
        error_log("Get company settings failed: " . $e->getMessage());
        return [
            'name' => 'LoanFlow',
            'email' => 'info@loanflow.com',
            'phone' => '+1 (555) 123-4567',
            'address' => '123 Business Street, Suite 100\nBusinessville, BV 12345',
            'website' => 'https://www.loanflow.com',
            'logo' => '',
            'registration' => '',
            'tax_id' => '',
            'description' => 'Professional personal loan services with fast approval and competitive rates.'
        ];
    }
}

/**
 * Update company settings
 */
function updateCompanySettings($data) {
    try {
        $db = getDB();
        
        // Check if system_settings table exists
        $stmt = $db->query("SHOW TABLES LIKE 'system_settings'");
        if ($stmt->rowCount() == 0) {
            createSystemSettingsTable();
        }
        
        $current_user_id = getCurrentUserId();
        
        foreach ($data as $key => $value) {
            $setting_key = 'company_' . $key;
            
            $stmt = $db->prepare("
                INSERT INTO system_settings (setting_key, setting_value, setting_type, updated_by) 
                VALUES (?, ?, 'string', ?) 
                ON DUPLICATE KEY UPDATE 
                setting_value = VALUES(setting_value),
                updated_by = VALUES(updated_by),
                updated_at = NOW()
            ");
            
            $stmt->execute([$setting_key, $value, $current_user_id]);
        }
        
        return true;
    } catch (Exception $e) {
        error_log("Update company settings failed: " . $e->getMessage());
        return false;
    }
}

/**
 * Get branding settings
 */
function getBrandingSettings() {
    try {
        $db = getDB();
        
        // Initialize default settings
        $default_settings = [
            'primary_color' => '#007bff',
            'secondary_color' => '#6c757d',
            'accent_color' => '#28a745',
            'font_family' => 'Arial, sans-serif',
            'letterhead_template' => 'default',
            'email_template_style' => 'modern'
        ];
        
        // Check if system_settings table exists
        $stmt = $db->query("SHOW TABLES LIKE 'system_settings'");
        if ($stmt->rowCount() == 0) {
            createSystemSettingsTable();
            return $default_settings;
        }
        
        // Get branding settings from database
        $stmt = $db->prepare("
            SELECT setting_key, setting_value 
            FROM system_settings 
            WHERE setting_key LIKE 'brand_%'
        ");
        $stmt->execute();
        $results = $stmt->fetchAll();
        
        $settings = $default_settings;
        foreach ($results as $row) {
            $key = str_replace('brand_', '', $row['setting_key']);
            $settings[$key] = $row['setting_value'];
        }
        
        return $settings;
    } catch (Exception $e) {
        error_log("Get branding settings failed: " . $e->getMessage());
        return [
            'primary_color' => '#007bff',
            'secondary_color' => '#6c757d',
            'accent_color' => '#28a745',
            'font_family' => 'Arial, sans-serif',
            'letterhead_template' => 'default',
            'email_template_style' => 'modern'
        ];
    }
}

/**
 * Update branding settings
 */
function updateBrandingSettings($data) {
    try {
        $db = getDB();
        
        // Check if system_settings table exists
        $stmt = $db->query("SHOW TABLES LIKE 'system_settings'");
        if ($stmt->rowCount() == 0) {
            createSystemSettingsTable();
        }
        
        $current_user_id = getCurrentUserId();
        
        foreach ($data as $key => $value) {
            $setting_key = 'brand_' . $key;
            
            $stmt = $db->prepare("
                INSERT INTO system_settings (setting_key, setting_value, setting_type, updated_by) 
                VALUES (?, ?, 'string', ?) 
                ON DUPLICATE KEY UPDATE 
                setting_value = VALUES(setting_value),
                updated_by = VALUES(updated_by),
                updated_at = NOW()
            ");
            
            $stmt->execute([$setting_key, $value, $current_user_id]);
        }
        
        return true;
    } catch (Exception $e) {
        error_log("Update branding settings failed: " . $e->getMessage());
        return false;
    }
}

/**
 * Handle logo upload
 */
function handleLogoUpload($file) {
    try {
        if (!$file || $file['error'] !== UPLOAD_ERR_OK) {
            return ['success' => false, 'error' => 'File upload failed'];
        }
        
        // Validate file type
        $allowed_types = ['image/jpeg', 'image/png', 'image/gif', 'image/svg+xml'];
        if (!in_array($file['type'], $allowed_types)) {
            return ['success' => false, 'error' => 'Invalid file type. Only JPEG, PNG, GIF, and SVG files are allowed'];
        }
        
        // Validate file size (2MB max)
        if ($file['size'] > 2097152) {
            return ['success' => false, 'error' => 'File too large. Maximum size is 2MB'];
        }
        
        // Create upload directory if it doesn't exist
        $upload_dir = '../assets/images/';
        if (!is_dir($upload_dir)) {
            mkdir($upload_dir, 0755, true);
        }
        
        // Generate unique filename
        $file_extension = pathinfo($file['name'], PATHINFO_EXTENSION);
        $filename = 'logo-' . uniqid() . '.' . $file_extension;
        $file_path = $upload_dir . $filename;
        
        // Move uploaded file
        if (move_uploaded_file($file['tmp_name'], $file_path)) {
            return [
                'success' => true,
                'filename' => $filename,
                'path' => $file_path
            ];
        } else {
            return ['success' => false, 'error' => 'Failed to save uploaded file'];
        }
        
    } catch (Exception $e) {
        error_log("Logo upload failed: " . $e->getMessage());
        return ['success' => false, 'error' => 'Upload failed: ' . $e->getMessage()];
    }
}

/**
 * Update system setting (helper function)
 */
function updateSystemSetting($key, $value, $type = 'string') {
    return setSystemSetting($key, $value, $type);
}

/**
 * Get general settings
 */
function getGeneralSettings() {
    try {
        $db = getDB();
        
        // Default settings
        $default_settings = [
            'site_name' => 'LoanFlow',
            'site_email' => 'admin@loanflow.com',
            'admin_email' => 'admin@loanflow.com',
            'timezone' => 'America/New_York',
            'date_format' => 'Y-m-d',
            'currency' => 'USD',
            'maintenance_mode' => '0'
        ];
        
        // Check if system_settings table exists
        $stmt = $db->query("SHOW TABLES LIKE 'system_settings'");
        if ($stmt->rowCount() == 0) {
            createSystemSettingsTable();
            return $default_settings;
        }
        
        // Get general settings from database
        $stmt = $db->prepare("
            SELECT setting_key, setting_value 
            FROM system_settings 
            WHERE setting_key IN ('site_name', 'site_email', 'admin_email', 'timezone', 'date_format', 'currency', 'maintenance_mode')
        ");
        $stmt->execute();
        $results = $stmt->fetchAll();
        
        $settings = $default_settings;
        foreach ($results as $row) {
            $settings[$row['setting_key']] = $row['setting_value'];
        }
        
        return $settings;
    } catch (Exception $e) {
        error_log("Get general settings failed: " . $e->getMessage());
        return [
            'site_name' => 'LoanFlow',
            'site_email' => 'admin@loanflow.com',
            'admin_email' => 'admin@loanflow.com',
            'timezone' => 'America/New_York',
            'date_format' => 'Y-m-d',
            'currency' => 'USD',
            'maintenance_mode' => '0'
        ];
    }
}

/**
 * Update general settings
 */
function updateGeneralSettings($data) {
    try {
        $db = getDB();
        
        // Check if system_settings table exists
        $stmt = $db->query("SHOW TABLES LIKE 'system_settings'");
        if ($stmt->rowCount() == 0) {
            createSystemSettingsTable();
        }
        
        $current_user_id = getCurrentUserId();
        
        foreach ($data as $key => $value) {
            $stmt = $db->prepare("
                INSERT INTO system_settings (setting_key, setting_value, setting_type, updated_by) 
                VALUES (?, ?, 'string', ?) 
                ON DUPLICATE KEY UPDATE 
                setting_value = VALUES(setting_value),
                updated_by = VALUES(updated_by),
                updated_at = NOW()
            ");
            
            $stmt->execute([$key, $value, $current_user_id]);
        }
        
        return true;
    } catch (Exception $e) {
        error_log("Update general settings failed: " . $e->getMessage());
        return false;
    }
}

/**
 * Get payment settings
 */
function getPaymentSettings() {
    try {
        $db = getDB();
        
        // Default settings
        $default_settings = [
            'paypal_enabled' => '0',
            'paypal_client_id' => '',
            'paypal_client_secret' => '',
            'paypal_sandbox' => '1',
            'stripe_enabled' => '0',
            'stripe_publishable_key' => '',
            'stripe_secret_key' => '',
            'stripe_webhook_secret' => ''
        ];
        
        // Check if system_settings table exists
        $stmt = $db->query("SHOW TABLES LIKE 'system_settings'");
        if ($stmt->rowCount() == 0) {
            createSystemSettingsTable();
            return $default_settings;
        }
        
        // Get payment settings from database
        $stmt = $db->prepare("
            SELECT setting_key, setting_value 
            FROM system_settings 
            WHERE setting_key LIKE 'paypal_%' OR setting_key LIKE 'stripe_%'
        ");
        $stmt->execute();
        $results = $stmt->fetchAll();
        
        $settings = $default_settings;
        foreach ($results as $row) {
            $settings[$row['setting_key']] = $row['setting_value'];
        }
        
        return $settings;
    } catch (Exception $e) {
        error_log("Get payment settings failed: " . $e->getMessage());
        return [
            'paypal_enabled' => '0',
            'paypal_client_id' => '',
            'paypal_client_secret' => '',
            'paypal_sandbox' => '1',
            'stripe_enabled' => '0',
            'stripe_publishable_key' => '',
            'stripe_secret_key' => '',
            'stripe_webhook_secret' => ''
        ];
    }
}

/**
 * Update payment settings
 */
function updatePaymentSettings($data) {
    try {
        $db = getDB();
        
        // Check if system_settings table exists
        $stmt = $db->query("SHOW TABLES LIKE 'system_settings'");
        if ($stmt->rowCount() == 0) {
            createSystemSettingsTable();
        }
        
        $current_user_id = getCurrentUserId();
        
        foreach ($data as $key => $value) {
            $stmt = $db->prepare("
                INSERT INTO system_settings (setting_key, setting_value, setting_type, updated_by) 
                VALUES (?, ?, 'string', ?) 
                ON DUPLICATE KEY UPDATE 
                setting_value = VALUES(setting_value),
                updated_by = VALUES(updated_by),
                updated_at = NOW()
            ");
            
            $stmt->execute([$key, $value, $current_user_id]);
        }
        
        return true;
    } catch (Exception $e) {
        error_log("Update payment settings failed: " . $e->getMessage());
        return false;
    }
}

/**
 * Get database settings
 */
function getDatabaseSettingsForAdmin() {
    try {
        $db = getDB();
        
        // Default settings
        $default_settings = [
            'db_host' => 'localhost',
            'db_name' => 'loanflow',
            'db_user' => 'loanflow_user',
            'db_password' => '',
            'db_charset' => 'utf8mb4',
            'db_port' => 3306,
            'db_socket' => '',
            'db_ssl_key' => '',
            'db_ssl_cert' => '',
            'db_ssl_ca' => '',
            'db_sql_mode' => 'STRICT_TRANS_TABLES,NO_ZERO_DATE,NO_ZERO_IN_DATE,ERROR_FOR_DIVISION_BY_ZERO',
            'db_timezone' => '+00:00',
            'db_init_command' => 'SET NAMES utf8mb4'
        ];
        
        // Check if system_settings table exists
        $stmt = $db->query("SHOW TABLES LIKE 'system_settings'");
        if ($stmt->rowCount() == 0) {
            createSystemSettingsTable();
            return $default_settings;
        }
        
        // Get database settings from database
        $stmt = $db->prepare("
            SELECT setting_key, setting_value 
            FROM system_settings 
            WHERE setting_key LIKE 'db_%'
        ");
        $stmt->execute();
        $results = $stmt->fetchAll();
        
        $settings = $default_settings;
        foreach ($results as $row) {
            $settings[$row['setting_key']] = $row['setting_value'];
        }
        
        return $settings;
    } catch (Exception $e) {
        error_log("Get database settings failed: " . $e->getMessage());
        return [
            'db_host' => 'localhost',
            'db_name' => 'loanflow',
            'db_user' => 'loanflow_user',
            'db_password' => '',
            'db_charset' => 'utf8mb4',
            'db_port' => 3306,
            'db_socket' => '',
            'db_ssl_key' => '',
            'db_ssl_cert' => '',
            'db_ssl_ca' => '',
            'db_sql_mode' => 'STRICT_TRANS_TABLES,NO_ZERO_DATE,NO_ZERO_IN_DATE,ERROR_FOR_DIVISION_BY_ZERO',
            'db_timezone' => '+00:00',
            'db_init_command' => 'SET NAMES utf8mb4'
        ];
    }
}

/**
 * Update database settings
 */
function updateDatabaseSettings($data) {
    try {
        $db = getDB();
        
        // Check if system_settings table exists
        $stmt = $db->query("SHOW TABLES LIKE 'system_settings'");
        if ($stmt->rowCount() == 0) {
            createSystemSettingsTable();
        }
        
        $current_user_id = getCurrentUserId();
        
        foreach ($data as $key => $value) {
            $stmt = $db->prepare("
                INSERT INTO system_settings (setting_key, setting_value, setting_type, updated_by) 
                VALUES (?, ?, 'string', ?) 
                ON DUPLICATE KEY UPDATE 
                setting_value = VALUES(setting_value),
                updated_by = VALUES(updated_by),
                updated_at = NOW()
            ");
            
            $stmt->execute([$key, $value, $current_user_id]);
        }
        
        return true;
    } catch (Exception $e) {
        error_log("Update database settings failed: " . $e->getMessage());
        return false;
    }
}

/**
 * Test database connection with specific settings
 */
function testDatabaseConnectionWithSettings($settings) {
    try {
        $dsn = "mysql:host={$settings['host']};dbname={$settings['name']};charset={$settings['charset']}";
        
        if (!empty($settings['port']) && $settings['port'] != 3306) {
            $dsn .= ";port={$settings['port']}";
        }
        
        $options = [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
            PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES {$settings['charset']}"
        ];
        
        $test_pdo = new PDO($dsn, $settings['user'], $settings['password'], $options);
        
        // Test a simple query
        $stmt = $test_pdo->query("SELECT VERSION() as version, NOW() as current_time");
        $result = $stmt->fetch();
        
        return [
            'success' => true,
            'message' => "MySQL {$result['version']} - Connected at {$result['current_time']}"
        ];
    } catch (Exception $e) {
        return [
            'success' => false,
            'error' => $e->getMessage()
        ];
    }
}

/**
 * Create system_settings table if it doesn't exist
 */
function createSystemSettingsTable() {
    try {
        $db = getDB();
        
        $sql = "
        CREATE TABLE IF NOT EXISTS system_settings (
            id INT AUTO_INCREMENT PRIMARY KEY,
            setting_key VARCHAR(100) NOT NULL UNIQUE,
            setting_value TEXT,
            setting_type ENUM('boolean', 'string', 'integer', 'json') DEFAULT 'string',
            description TEXT,
            is_public BOOLEAN DEFAULT FALSE,
            updated_by INT,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_setting_key (setting_key),
            INDEX idx_is_public (is_public),
            INDEX idx_updated_by (updated_by)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ";
        
        $db->exec($sql);
        return true;
    } catch (Exception $e) {
        error_log("Create system_settings table failed: " . $e->getMessage());
        return false;
    }
}

/**
 * Create templates table if it doesn't exist
 */
function createTemplatesTable() {
    try {
        $db = getDB();
        
        $sql = "
        CREATE TABLE IF NOT EXISTS templates (
            id INT AUTO_INCREMENT PRIMARY KEY,
            template_key VARCHAR(100) NOT NULL UNIQUE,
            name VARCHAR(255) NOT NULL,
            description TEXT,
            type ENUM('frontend', 'client', 'admin') NOT NULL,
            version VARCHAR(50) DEFAULT '1.0.0',
            file_path VARCHAR(255) NOT NULL,
            css_path VARCHAR(255),
            js_path VARCHAR(255),
            preview_image VARCHAR(255),
            is_active BOOLEAN DEFAULT FALSE,
            is_ai_generated BOOLEAN DEFAULT FALSE,
            supports_ai_integration BOOLEAN DEFAULT TRUE,
            status ENUM('active', 'inactive', 'deleted') DEFAULT 'active',
            created_by INT,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            INDEX idx_type (type),
            INDEX idx_active (is_active),
            INDEX idx_status (status),
            INDEX idx_ai_generated (is_ai_generated)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ";
        
        $db->exec($sql);
        return true;
    } catch (Exception $e) {
        error_log("Create templates table failed: " . $e->getMessage());
        return false;
    }
}

/**
 * Get list of supported countries for the loan system
 * @return array Array of country codes
 */
function getSupportedCountries() {
    return ['USA', 'CAN', 'GBR', 'AUS', 'NZL', 'IRL', 'ZAF'];
}

/**
 * Get country name from country code
 * @param string $countryCode Country code (e.g., 'USA', 'CAN')
 * @return string Country name
 */
function getCountryName($countryCode) {
    $countries = [
        'USA' => 'United States',
        'CAN' => 'Canada', 
        'GBR' => 'United Kingdom',
        'AUS' => 'Australia',
        'NZL' => 'New Zealand',
        'IRL' => 'Ireland',
        'ZAF' => 'South Africa'
    ];
    
    return $countries[$countryCode] ?? $countryCode;
}

// Update loan application status
function updateApplicationStatus($application_id, $status, $notes = '') {
    try {
        $db = getDB();
        
        $sql = "UPDATE loan_applications SET 
                application_status = :status,
                updated_at = NOW()
                WHERE id = :application_id";
        
        $stmt = $db->prepare($sql);
        $result = $stmt->execute([
            ':status' => $status,
            ':application_id' => $application_id
        ]);
        
        // If notes provided, log them
        if ($notes && $result) {
            logAudit('status_update', 'loan_applications', $application_id, null, [
                'status' => $status,
                'notes' => $notes
            ]);
        }
        
        return $result;
    } catch (Exception $e) {
        error_log("Update application status failed: " . $e->getMessage());
        return false;
    }
}

?>
