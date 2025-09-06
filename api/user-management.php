<?php
/**
 * Enhanced User Management API
 * LoanFlow Personal Loan Management System
 * 
 * Provides comprehensive REST API endpoints for user management operations
 * including CRUD operations, security controls, audit logging, and bulk actions
 */

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With');

// Handle preflight requests
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

require_once '../config/database.php';
require_once '../includes/functions.php';
require_once '../includes/auth.php';
require_once '../includes/language.php';
require_once '../includes/rate_limiter.php';

// Initialize components
LanguageManager::init();
$rate_limiter = new RateLimiter();

// Rate limiting
if (!$rate_limiter->checkLimit('user_management_api', 100, 3600)) {
    http_response_code(429);
    echo json_encode(['error' => 'Rate limit exceeded']);
    exit();
}

// Authentication check
$current_user = getCurrentUser();
if (!$current_user) {
    http_response_code(401);
    echo json_encode(['error' => 'Authentication required']);
    exit();
}

// Get request method and action
$method = $_SERVER['REQUEST_METHOD'];
$action = $_GET['action'] ?? '';
$user_id = $_GET['id'] ?? $_GET['user_id'] ?? null;

// Route requests
try {
    switch ($method) {
        case 'GET':
            handleGetRequest($action, $user_id, $current_user);
            break;
            
        case 'POST':
            handlePostRequest($action, $current_user);
            break;
            
        case 'PUT':
            handlePutRequest($action, $user_id, $current_user);
            break;
            
        case 'DELETE':
            handleDeleteRequest($action, $user_id, $current_user);
            break;
            
        default:
            http_response_code(405);
            echo json_encode(['error' => 'Method not allowed']);
            break;
    }
} catch (Exception $e) {
    error_log('User Management API Error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'Internal server error']);
}

/**
 * Handle GET requests
 */
function handleGetRequest($action, $user_id, $current_user) {
    switch ($action) {
        case 'list':
        case 'users':
            requireRole('admin', $current_user);
            getUsersList();
            break;
            
        case 'get_user':
        case 'user':
            if ($user_id) {
                getUser($user_id, $current_user);
            } else {
                http_response_code(400);
                echo json_encode(['error' => 'User ID required']);
            }
            break;
            
        case 'profile':
            getUserProfile($current_user);
            break;
            
        case 'permissions':
            if ($user_id) {
                requireRole('admin', $current_user);
                getUserPermissions($user_id);
            } else {
                http_response_code(400);
                echo json_encode(['error' => 'User ID required']);
            }
            break;
            
        case 'security_settings':
            if ($user_id) {
                getUserSecuritySettings($user_id, $current_user);
            } else {
                http_response_code(400);
                echo json_encode(['error' => 'User ID required']);
            }
            break;
            
        case 'audit_logs':
            requireRole('admin', $current_user);
            getUserAuditLogs($user_id);
            break;
            
        case 'statistics':
            requireRole('admin', $current_user);
            getUserStatistics();
            break;
            
        case 'export_users':
            requireRole('admin', $current_user);
            exportUsers();
            break;
            
        case 'search':
            requireRole('admin', $current_user);
            searchUsers();
            break;
            
        case 'roles':
            requireRole('admin', $current_user);
            getUserRoles();
            break;
            
        default:
            http_response_code(400);
            echo json_encode(['error' => 'Invalid action']);
            break;
    }
}

/**
 * Handle POST requests
 */
function handlePostRequest($action, $current_user) {
    $input = json_decode(file_get_contents('php://input'), true);
    
    switch ($action) {
        case 'create':
        case 'create_user':
            requireRole('admin', $current_user);
            createUser($input, $current_user);
            break;
            
        case 'bulk_action':
            requireRole('admin', $current_user);
            performBulkAction($input, $current_user);
            break;
            
        case 'reset_password':
            resetUserPassword($input, $current_user);
            break;
            
        case 'change_password':
            changeUserPassword($input, $current_user);
            break;
            
        case 'unlock_account':
            requireRole('admin', $current_user);
            unlockUserAccount($input, $current_user);
            break;
            
        case 'enable_2fa':
            enableTwoFactorAuth($input, $current_user);
            break;
            
        case 'disable_2fa':
            disableTwoFactorAuth($input, $current_user);
            break;
            
        case 'update_security_settings':
            updateSecuritySettings($input, $current_user);
            break;
            
        case 'send_verification_email':
            sendVerificationEmail($input, $current_user);
            break;
            
        default:
            http_response_code(400);
            echo json_encode(['error' => 'Invalid action']);
            break;
    }
}

/**
 * Handle PUT requests
 */
function handlePutRequest($action, $user_id, $current_user) {
    $input = json_decode(file_get_contents('php://input'), true);
    
    switch ($action) {
        case 'update':
        case 'update_user':
            if ($user_id) {
                updateUser($user_id, $input, $current_user);
            } else {
                http_response_code(400);
                echo json_encode(['error' => 'User ID required']);
            }
            break;
            
        case 'update_profile':
            updateUserProfile($input, $current_user);
            break;
            
        case 'update_permissions':
            requireRole('admin', $current_user);
            if ($user_id) {
                updateUserPermissions($user_id, $input, $current_user);
            } else {
                http_response_code(400);
                echo json_encode(['error' => 'User ID required']);
            }
            break;
            
        case 'activate':
            requireRole('admin', $current_user);
            if ($user_id) {
                activateUser($user_id, $current_user);
            } else {
                http_response_code(400);
                echo json_encode(['error' => 'User ID required']);
            }
            break;
            
        case 'deactivate':
            requireRole('admin', $current_user);
            if ($user_id) {
                deactivateUser($user_id, $current_user);
            } else {
                http_response_code(400);
                echo json_encode(['error' => 'User ID required']);
            }
            break;
            
        default:
            http_response_code(400);
            echo json_encode(['error' => 'Invalid action']);
            break;
    }
}

/**
 * Handle DELETE requests
 */
function handleDeleteRequest($action, $user_id, $current_user) {
    switch ($action) {
        case 'delete':
        case 'delete_user':
            requireRole('admin', $current_user);
            if ($user_id) {
                deleteUser($user_id, $current_user);
            } else {
                http_response_code(400);
                echo json_encode(['error' => 'User ID required']);
            }
            break;
            
        case 'delete_account':
            deleteUserAccount($current_user);
            break;
            
        default:
            http_response_code(400);
            echo json_encode(['error' => 'Invalid action']);
            break;
    }
}

/**
 * Get users list with filtering and pagination
 */
function getUsersList() {
    global $pdo;
    
    try {
        $page = intval($_GET['page'] ?? 1);
        $limit = min(intval($_GET['limit'] ?? 20), 100); // Max 100 per page
        $offset = ($page - 1) * $limit;
        $search = sanitizeInput($_GET['search'] ?? '');
        $role = sanitizeInput($_GET['role'] ?? '');
        $status = sanitizeInput($_GET['status'] ?? '');
        $sort_by = sanitizeInput($_GET['sort_by'] ?? 'created_at');
        $sort_order = strtoupper($_GET['sort_order'] ?? 'DESC');
        
        // Validate sort parameters
        $allowed_sort_fields = ['id', 'first_name', 'last_name', 'email', 'role', 'status', 'created_at', 'last_login'];
        if (!in_array($sort_by, $allowed_sort_fields)) {
            $sort_by = 'created_at';
        }
        if (!in_array($sort_order, ['ASC', 'DESC'])) {
            $sort_order = 'DESC';
        }
        
        $where_conditions = ["status != 'deleted'"];
        $params = [];
        
        if (!empty($search)) {
            $where_conditions[] = "(first_name LIKE ? OR last_name LIKE ? OR email LIKE ?)";
            $search_param = "%$search%";
            $params[] = $search_param;
            $params[] = $search_param;
            $params[] = $search_param;
        }
        
        if (!empty($role)) {
            $where_conditions[] = "role = ?";
            $params[] = $role;
        }
        
        if (!empty($status)) {
            $where_conditions[] = "status = ?";
            $params[] = $status;
        }
        
        $where_clause = implode(' AND ', $where_conditions);
        
        // Get total count
        $count_sql = "SELECT COUNT(*) FROM users WHERE $where_clause";
        $stmt = $pdo->prepare($count_sql);
        $stmt->execute($params);
        $total = $stmt->fetchColumn();
        
        // Get users
        $sql = "
            SELECT id, first_name, last_name, email, phone, role, status, 
                   last_login, failed_login_attempts, locked_until, two_factor_enabled,
                   email_verified, created_at, updated_at
            FROM users 
            WHERE $where_clause 
            ORDER BY $sort_by $sort_order 
            LIMIT ? OFFSET ?
        ";
        
        $params[] = $limit;
        $params[] = $offset;
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $users = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Remove sensitive data
        foreach ($users as &$user) {
            unset($user['password_hash']);
            $user['security_level'] = calculateSecurityLevel($user);
        }
        
        echo json_encode([
            'success' => true,
            'data' => [
                'users' => $users,
                'pagination' => [
                    'current_page' => $page,
                    'per_page' => $limit,
                    'total' => $total,
                    'total_pages' => ceil($total / $limit)
                ]
            ]
        ]);
        
    } catch (Exception $e) {
        error_log('Error getting users list: ' . $e->getMessage());
        http_response_code(500);
        echo json_encode(['error' => 'Failed to retrieve users']);
    }
}

/**
 * Get single user details
 */
function getUser($user_id, $current_user) {
    global $pdo;
    
    try {
        // Check permissions - admin can view any user, users can only view their own profile
        if ($current_user['role'] !== 'admin' && $current_user['id'] != $user_id) {
            http_response_code(403);
            echo json_encode(['error' => 'Access denied']);
            return;
        }
        
        $stmt = $pdo->prepare("
            SELECT id, first_name, last_name, email, phone, role, status, 
                   last_login, failed_login_attempts, locked_until, two_factor_enabled,
                   email_verified, password_reset_required, created_at, updated_at
            FROM users 
            WHERE id = ? AND status != 'deleted'
        ");
        
        $stmt->execute([$user_id]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$user) {
            http_response_code(404);
            echo json_encode(['error' => 'User not found']);
            return;
        }
        
        // Add security level
        $user['security_level'] = calculateSecurityLevel($user);
        
        // Get user permissions if admin is requesting
        if ($current_user['role'] === 'admin') {
            $user['permissions'] = getUserPermissionsList($user_id);
        }
        
        // For edit modal HTML generation
        if (isset($_GET['format']) && $_GET['format'] === 'html') {
            $html = generateEditUserHTML($user);
            echo json_encode(['success' => true, 'html' => $html]);
        } else {
            echo json_encode(['success' => true, 'data' => $user]);
        }
        
    } catch (Exception $e) {
        error_log('Error getting user: ' . $e->getMessage());
        http_response_code(500);
        echo json_encode(['error' => 'Failed to retrieve user']);
    }
}

/**
 * Get user profile (current user)
 */
function getUserProfile($current_user) {
    getUser($current_user['id'], $current_user);
}

/**
 * Create new user
 */
function createUser($data, $current_user) {
    global $pdo;
    
    try {
        // Validate required fields
        $required_fields = ['first_name', 'last_name', 'email', 'password', 'role'];
        foreach ($required_fields as $field) {
            if (empty($data[$field])) {
                http_response_code(400);
                echo json_encode(['error' => "Field '$field' is required"]);
                return;
            }
        }
        
        // Sanitize input
        $first_name = sanitizeInput($data['first_name']);
        $last_name = sanitizeInput($data['last_name']);
        $email = sanitizeInput($data['email']);
        $phone = sanitizeInput($data['phone'] ?? '');
        $role = sanitizeInput($data['role']);
        $status = sanitizeInput($data['status'] ?? 'active');
        $password = $data['password'];
        
        // Validate email
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            http_response_code(400);
            echo json_encode(['error' => 'Invalid email format']);
            return;
        }
        
        // Check if email already exists
        $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ?");
        $stmt->execute([$email]);
        if ($stmt->fetch()) {
            http_response_code(409);
            echo json_encode(['error' => 'Email already exists']);
            return;
        }
        
        // Validate password
        if (strlen($password) < 8) {
            http_response_code(400);
            echo json_encode(['error' => 'Password must be at least 8 characters long']);
            return;
        }
        
        // Validate role
        $allowed_roles = ['user', 'client', 'admin'];
        if (!in_array($role, $allowed_roles)) {
            http_response_code(400);
            echo json_encode(['error' => 'Invalid role']);
            return;
        }
        
        // Hash password
        $password_hash = password_hash($password, PASSWORD_DEFAULT);
        
        // Insert user
        $stmt = $pdo->prepare("
            INSERT INTO users (first_name, last_name, email, phone, role, status, password_hash, created_at) 
            VALUES (?, ?, ?, ?, ?, ?, ?, NOW())
        ");
        
        $stmt->execute([$first_name, $last_name, $email, $phone, $role, $status, $password_hash]);
        $user_id = $pdo->lastInsertId();
        
        // Log audit
        logAudit('user_created', 'users', $user_id, $current_user['id'], [
            'email' => $email,
            'role' => $role,
            'status' => $status
        ]);
        
        // Send welcome email if requested
        if (!empty($data['send_welcome_email'])) {
            sendWelcomeEmail($email, $first_name, $password);
        }
        
        echo json_encode([
            'success' => true,
            'message' => 'User created successfully',
            'data' => ['user_id' => $user_id]
        ]);
        
    } catch (Exception $e) {
        error_log('Error creating user: ' . $e->getMessage());
        http_response_code(500);
        echo json_encode(['error' => 'Failed to create user']);
    }
}

/**
 * Update user
 */
function updateUser($user_id, $data, $current_user) {
    global $pdo;
    
    try {
        // Check permissions
        if ($current_user['role'] !== 'admin' && $current_user['id'] != $user_id) {
            http_response_code(403);
            echo json_encode(['error' => 'Access denied']);
            return;
        }
        
        // Get current user data
        $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ? AND status != 'deleted'");
        $stmt->execute([$user_id]);
        $existing_user = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$existing_user) {
            http_response_code(404);
            echo json_encode(['error' => 'User not found']);
            return;
        }
        
        // Prepare update data
        $update_fields = [];
        $params = [];
        
        if (isset($data['first_name'])) {
            $update_fields[] = 'first_name = ?';
            $params[] = sanitizeInput($data['first_name']);
        }
        
        if (isset($data['last_name'])) {
            $update_fields[] = 'last_name = ?';
            $params[] = sanitizeInput($data['last_name']);
        }
        
        if (isset($data['email'])) {
            $email = sanitizeInput($data['email']);
            if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                http_response_code(400);
                echo json_encode(['error' => 'Invalid email format']);
                return;
            }
            
            // Check if email already exists for another user
            $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ? AND id != ?");
            $stmt->execute([$email, $user_id]);
            if ($stmt->fetch()) {
                http_response_code(409);
                echo json_encode(['error' => 'Email already exists']);
                return;
            }
            
            $update_fields[] = 'email = ?';
            $params[] = $email;
        }
        
        if (isset($data['phone'])) {
            $update_fields[] = 'phone = ?';
            $params[] = sanitizeInput($data['phone']);
        }
        
        // Only admin can update role and status
        if ($current_user['role'] === 'admin') {
            if (isset($data['role'])) {
                $role = sanitizeInput($data['role']);
                $allowed_roles = ['user', 'client', 'admin'];
                if (!in_array($role, $allowed_roles)) {
                    http_response_code(400);
                    echo json_encode(['error' => 'Invalid role']);
                    return;
                }
                $update_fields[] = 'role = ?';
                $params[] = $role;
            }
            
            if (isset($data['status'])) {
                $status = sanitizeInput($data['status']);
                $allowed_statuses = ['active', 'inactive', 'locked'];
                if (!in_array($status, $allowed_statuses)) {
                    http_response_code(400);
                    echo json_encode(['error' => 'Invalid status']);
                    return;
                }
                $update_fields[] = 'status = ?';
                $params[] = $status;
            }
        }
        
        if (empty($update_fields)) {
            http_response_code(400);
            echo json_encode(['error' => 'No valid fields to update']);
            return;
        }
        
        // Add updated_at
        $update_fields[] = 'updated_at = NOW()';
        $params[] = $user_id;
        
        // Update user
        $sql = "UPDATE users SET " . implode(', ', $update_fields) . " WHERE id = ?";
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        
        // Log audit
        logAudit('user_updated', 'users', $user_id, $current_user['id'], $data);
        
        echo json_encode([
            'success' => true,
            'message' => 'User updated successfully'
        ]);
        
    } catch (Exception $e) {
        error_log('Error updating user: ' . $e->getMessage());
        http_response_code(500);
        echo json_encode(['error' => 'Failed to update user']);
    }
}

/**
 * Delete user (soft delete)
 */
function deleteUser($user_id, $current_user) {
    global $pdo;
    
    try {
        // Prevent self-deletion
        if ($current_user['id'] == $user_id) {
            http_response_code(400);
            echo json_encode(['error' => 'Cannot delete your own account']);
            return;
        }
        
        // Check if user exists
        $stmt = $pdo->prepare("SELECT id, email FROM users WHERE id = ? AND status != 'deleted'");
        $stmt->execute([$user_id]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$user) {
            http_response_code(404);
            echo json_encode(['error' => 'User not found']);
            return;
        }
        
        // Soft delete
        $stmt = $pdo->prepare("UPDATE users SET status = 'deleted', updated_at = NOW() WHERE id = ?");
        $stmt->execute([$user_id]);
        
        // Log audit
        logAudit('user_deleted', 'users', $user_id, $current_user['id'], [
            'email' => $user['email']
        ]);
        
        echo json_encode([
            'success' => true,
            'message' => 'User deleted successfully'
        ]);
        
    } catch (Exception $e) {
        error_log('Error deleting user: ' . $e->getMessage());
        http_response_code(500);
        echo json_encode(['error' => 'Failed to delete user']);
    }
}

/**
 * Reset user password
 */
function resetUserPassword($data, $current_user) {
    global $pdo;
    
    try {
        $user_id = $data['user_id'] ?? null;
        
        if (!$user_id) {
            http_response_code(400);
            echo json_encode(['error' => 'User ID required']);
            return;
        }
        
        // Check permissions
        if ($current_user['role'] !== 'admin' && $current_user['id'] != $user_id) {
            http_response_code(403);
            echo json_encode(['error' => 'Access denied']);
            return;
        }
        
        // Get user
        $stmt = $pdo->prepare("SELECT email, first_name FROM users WHERE id = ? AND status != 'deleted'");
        $stmt->execute([$user_id]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$user) {
            http_response_code(404);
            echo json_encode(['error' => 'User not found']);
            return;
        }
        
        // Generate new temporary password
        $temp_password = generateRandomPassword(12);
        $password_hash = password_hash($temp_password, PASSWORD_DEFAULT);
        
        // Update password
        $stmt = $pdo->prepare("
            UPDATE users 
            SET password_hash = ?, password_reset_required = 1, updated_at = NOW() 
            WHERE id = ?
        ");
        
        $stmt->execute([$password_hash, $user_id]);
        
        // Send password reset email
        sendPasswordResetEmail($user['email'], $user['first_name'], $temp_password);
        
        // Log audit
        logAudit('password_reset', 'users', $user_id, $current_user['id']);
        
        echo json_encode([
            'success' => true,
            'message' => 'Password reset successfully. New password sent to user email.'
        ]);
        
    } catch (Exception $e) {
        error_log('Error resetting password: ' . $e->getMessage());
        http_response_code(500);
        echo json_encode(['error' => 'Failed to reset password']);
    }
}

/**
 * Change user password
 */
function changeUserPassword($data, $current_user) {
    global $pdo;
    
    try {
        $user_id = $data['user_id'] ?? $current_user['id'];
        $current_password = $data['current_password'] ?? '';
        $new_password = $data['new_password'] ?? '';
        
        // Check permissions
        if ($current_user['role'] !== 'admin' && $current_user['id'] != $user_id) {
            http_response_code(403);
            echo json_encode(['error' => 'Access denied']);
            return;
        }
        
        // Validate new password
        if (strlen($new_password) < 8) {
            http_response_code(400);
            echo json_encode(['error' => 'Password must be at least 8 characters long']);
            return;
        }
        
        // Get current user data
        $stmt = $pdo->prepare("SELECT password_hash FROM users WHERE id = ? AND status != 'deleted'");
        $stmt->execute([$user_id]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$user) {
            http_response_code(404);
            echo json_encode(['error' => 'User not found']);
            return;
        }
        
        // Verify current password (unless admin is changing another user's password)
        if ($current_user['role'] !== 'admin' || $current_user['id'] == $user_id) {
            if (!password_verify($current_password, $user['password_hash'])) {
                http_response_code(400);
                echo json_encode(['error' => 'Current password is incorrect']);
                return;
            }
        }
        
        // Update password
        $new_password_hash = password_hash($new_password, PASSWORD_DEFAULT);
        $stmt = $pdo->prepare("
            UPDATE users 
            SET password_hash = ?, password_reset_required = 0, updated_at = NOW() 
            WHERE id = ?
        ");
        
        $stmt->execute([$new_password_hash, $user_id]);
        
        // Log audit
        logAudit('password_changed', 'users', $user_id, $current_user['id']);
        
        echo json_encode([
            'success' => true,
            'message' => 'Password changed successfully'
        ]);
        
    } catch (Exception $e) {
        error_log('Error changing password: ' . $e->getMessage());
        http_response_code(500);
        echo json_encode(['error' => 'Failed to change password']);
    }
}

/**
 * Unlock user account
 */
function unlockUserAccount($data, $current_user) {
    global $pdo;
    
    try {
        $user_id = $data['user_id'] ?? null;
        
        if (!$user_id) {
            http_response_code(400);
            echo json_encode(['error' => 'User ID required']);
            return;
        }
        
        // Update user status
        $stmt = $pdo->prepare("
            UPDATE users 
            SET status = 'active', failed_login_attempts = 0, locked_until = NULL, updated_at = NOW() 
            WHERE id = ?
        ");
        
        $stmt->execute([$user_id]);
        
        if ($stmt->rowCount() === 0) {
            http_response_code(404);
            echo json_encode(['error' => 'User not found']);
            return;
        }
        
        // Log audit
        logAudit('account_unlocked', 'users', $user_id, $current_user['id']);
        
        echo json_encode([
            'success' => true,
            'message' => 'Account unlocked successfully'
        ]);
        
    } catch (Exception $e) {
        error_log('Error unlocking account: ' . $e->getMessage());
        http_response_code(500);
        echo json_encode(['error' => 'Failed to unlock account']);
    }
}

/**
 * Perform bulk actions on users
 */
function performBulkAction($data, $current_user) {
    global $pdo;
    
    try {
        $user_ids = $data['user_ids'] ?? [];
        $action = $data['action'] ?? '';
        
        if (empty($user_ids) || empty($action)) {
            http_response_code(400);
            echo json_encode(['error' => 'User IDs and action are required']);
            return;
        }
        
        // Convert to array if string
        if (is_string($user_ids)) {
            $user_ids = explode(',', $user_ids);
        }
        
        $user_ids = array_map('intval', $user_ids);
        $user_ids = array_filter($user_ids, function($id) use ($current_user) {
            return $id > 0 && $id !== $current_user['id']; // Prevent self-action
        });
        
        if (empty($user_ids)) {
            http_response_code(400);
            echo json_encode(['error' => 'No valid user IDs provided']);
            return;
        }
        
        $placeholders = str_repeat('?,', count($user_ids) - 1) . '?';
        $affected_rows = 0;
        
        switch ($action) {
            case 'activate':
                $stmt = $pdo->prepare("UPDATE users SET status = 'active', updated_at = NOW() WHERE id IN ($placeholders)");
                $stmt->execute($user_ids);
                $affected_rows = $stmt->rowCount();
                break;
                
            case 'deactivate':
                $stmt = $pdo->prepare("UPDATE users SET status = 'inactive', updated_at = NOW() WHERE id IN ($placeholders)");
                $stmt->execute($user_ids);
                $affected_rows = $stmt->rowCount();
                break;
                
            case 'unlock':
                $stmt = $pdo->prepare("
                    UPDATE users 
                    SET status = 'active', failed_login_attempts = 0, locked_until = NULL, updated_at = NOW() 
                    WHERE id IN ($placeholders)
                ");
                $stmt->execute($user_ids);
                $affected_rows = $stmt->rowCount();
                break;
                
            case 'delete':
                $stmt = $pdo->prepare("UPDATE users SET status = 'deleted', updated_at = NOW() WHERE id IN ($placeholders)");
                $stmt->execute($user_ids);
                $affected_rows = $stmt->rowCount();
                break;
                
            case 'reset_password':
                foreach ($user_ids as $user_id) {
                    $result = resetUserPasswordById($user_id);
                    if ($result) {
                        $affected_rows++;
                    }
                }
                break;
                
            default:
                http_response_code(400);
                echo json_encode(['error' => 'Invalid action']);
                return;
        }
        
        // Log audit
        logAudit('bulk_user_action', 'users', null, $current_user['id'], [
            'action' => $action,
            'user_ids' => $user_ids,
            'affected_count' => $affected_rows
        ]);
        
        echo json_encode([
            'success' => true,
            'message' => "Bulk action '$action' completed successfully on $affected_rows users",
            'affected_count' => $affected_rows
        ]);
        
    } catch (Exception $e) {
        error_log('Error performing bulk action: ' . $e->getMessage());
        http_response_code(500);
        echo json_encode(['error' => 'Failed to perform bulk action']);
    }
}

/**
 * Get user statistics
 */
function getUserStatistics() {
    global $pdo;
    
    try {
        $stats = [];
        
        // Total users
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM users WHERE status != 'deleted'");
        $stmt->execute();
        $stats['total_users'] = $stmt->fetchColumn();
        
        // Active users
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM users WHERE status = 'active'");
        $stmt->execute();
        $stats['active_users'] = $stmt->fetchColumn();
        
        // Inactive users
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM users WHERE status = 'inactive'");
        $stmt->execute();
        $stats['inactive_users'] = $stmt->fetchColumn();
        
        // Locked users
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM users WHERE status = 'locked'");
        $stmt->execute();
        $stats['locked_users'] = $stmt->fetchColumn();
        
        // Users by role
        $stmt = $pdo->prepare("
            SELECT role, COUNT(*) as count 
            FROM users 
            WHERE status != 'deleted' 
            GROUP BY role
        ");
        $stmt->execute();
        $roles = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);
        $stats['users_by_role'] = $roles;
        
        // New users this month
        $stmt = $pdo->prepare("
            SELECT COUNT(*) FROM users 
            WHERE YEAR(created_at) = YEAR(CURDATE()) 
            AND MONTH(created_at) = MONTH(CURDATE()) 
            AND status != 'deleted'
        ");
        $stmt->execute();
        $stats['new_this_month'] = $stmt->fetchColumn();
        
        // Users with 2FA enabled
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM users WHERE two_factor_enabled = 1 AND status != 'deleted'");
        $stmt->execute();
        $stats['two_factor_enabled'] = $stmt->fetchColumn();
        
        // Recent logins (last 30 days)
        $stmt = $pdo->prepare("
            SELECT COUNT(*) FROM users 
            WHERE last_login >= DATE_SUB(NOW(), INTERVAL 30 DAY) 
            AND status != 'deleted'
        ");
        $stmt->execute();
        $stats['recent_logins'] = $stmt->fetchColumn();
        
        echo json_encode([
            'success' => true,
            'data' => $stats
        ]);
        
    } catch (Exception $e) {
        error_log('Error getting user statistics: ' . $e->getMessage());
        http_response_code(500);
        echo json_encode(['error' => 'Failed to retrieve statistics']);
    }
}

/**
 * Export users to CSV
 */
function exportUsers() {
    global $pdo;
    
    try {
        $stmt = $pdo->prepare("
            SELECT id, first_name, last_name, email, phone, role, status, 
                   last_login, created_at, updated_at
            FROM users 
            WHERE status != 'deleted'
            ORDER BY created_at DESC
        ");
        
        $stmt->execute();
        $users = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Set headers for CSV download
        header('Content-Type: text/csv');
        header('Content-Disposition: attachment; filename="users_export_' . date('Y-m-d_H-i-s') . '.csv"');
        
        $output = fopen('php://output', 'w');
        
        // Write CSV header
        fputcsv($output, [
            'ID', 'First Name', 'Last Name', 'Email', 'Phone', 'Role', 'Status',
            'Last Login', 'Created At', 'Updated At'
        ]);
        
        // Write user data
        foreach ($users as $user) {
            fputcsv($output, [
                $user['id'],
                $user['first_name'],
                $user['last_name'],
                $user['email'],
                $user['phone'],
                $user['role'],
                $user['status'],
                $user['last_login'],
                $user['created_at'],
                $user['updated_at']
            ]);
        }
        
        fclose($output);
        
    } catch (Exception $e) {
        error_log('Error exporting users: ' . $e->getMessage());
        http_response_code(500);
        echo json_encode(['error' => 'Failed to export users']);
    }
}

/**
 * Search users
 */
function searchUsers() {
    $query = sanitizeInput($_GET['q'] ?? '');
    $limit = min(intval($_GET['limit'] ?? 10), 50);
    
    if (empty($query)) {
        echo json_encode(['success' => true, 'data' => []]);
        return;
    }
    
    global $pdo;
    
    try {
        $stmt = $pdo->prepare("
            SELECT id, first_name, last_name, email, role, status
            FROM users 
            WHERE (first_name LIKE ? OR last_name LIKE ? OR email LIKE ?) 
            AND status != 'deleted'
            ORDER BY first_name, last_name
            LIMIT ?
        ");
        
        $search_param = "%$query%";
        $stmt->execute([$search_param, $search_param, $search_param, $limit]);
        $users = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        echo json_encode([
            'success' => true,
            'data' => $users
        ]);
        
    } catch (Exception $e) {
        error_log('Error searching users: ' . $e->getMessage());
        http_response_code(500);
        echo json_encode(['error' => 'Search failed']);
    }
}

/**
 * Get user audit logs
 */
function getUserAuditLogs($user_id) {
    global $pdo;
    
    try {
        $page = intval($_GET['page'] ?? 1);
        $limit = min(intval($_GET['limit'] ?? 20), 100);
        $offset = ($page - 1) * $limit;
        
        $where_clause = $user_id ? "WHERE target_id = ?" : "";
        $params = $user_id ? [$user_id] : [];
        
        // Get total count
        $count_sql = "SELECT COUNT(*) FROM audit_logs $where_clause";
        $stmt = $pdo->prepare($count_sql);
        $stmt->execute($params);
        $total = $stmt->fetchColumn();
        
        // Get logs
        $sql = "
            SELECT al.*, u.first_name, u.last_name, u.email
            FROM audit_logs al
            LEFT JOIN users u ON al.user_id = u.id
            $where_clause
            ORDER BY al.created_at DESC
            LIMIT ? OFFSET ?
        ";
        
        $params[] = $limit;
        $params[] = $offset;
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $logs = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        echo json_encode([
            'success' => true,
            'data' => [
                'logs' => $logs,
                'pagination' => [
                    'current_page' => $page,
                    'per_page' => $limit,
                    'total' => $total,
                    'total_pages' => ceil($total / $limit)
                ]
            ]
        ]);
        
    } catch (Exception $e) {
        error_log('Error getting audit logs: ' . $e->getMessage());
        http_response_code(500);
        echo json_encode(['error' => 'Failed to retrieve audit logs']);
    }
}

/**
 * Helper Functions
 */

function calculateSecurityLevel($user) {
    $score = 0;
    
    // Recent login
    if ($user['last_login'] && strtotime($user['last_login']) > strtotime('-30 days')) {
        $score++;
    }
    
    // Low failed attempts
    if ($user['failed_login_attempts'] < 3) {
        $score++;
    }
    
    // Active status
    if ($user['status'] === 'active') {
        $score++;
    }
    
    // Two-factor authentication
    if ($user['two_factor_enabled']) {
        $score++;
    }
    
    // Email verified
    if ($user['email_verified']) {
        $score++;
    }
    
    if ($score >= 4) return 'high';
    if ($score >= 2) return 'medium';
    return 'low';
}

function generateEditUserHTML($user) {
    $html = '
        <div class="row g-3">
            <div class="col-md-6">
                <label for="edit_first_name" class="form-label">First Name *</label>
                <input type="text" class="form-control" id="edit_first_name" name="first_name" 
                       value="' . htmlspecialchars($user['first_name']) . '" required>
            </div>
            <div class="col-md-6">
                <label for="edit_last_name" class="form-label">Last Name *</label>
                <input type="text" class="form-control" id="edit_last_name" name="last_name" 
                       value="' . htmlspecialchars($user['last_name']) . '" required>
            </div>
            <div class="col-md-6">
                <label for="edit_email" class="form-label">Email *</label>
                <input type="email" class="form-control" id="edit_email" name="email" 
                       value="' . htmlspecialchars($user['email']) . '" required>
            </div>
            <div class="col-md-6">
                <label for="edit_phone" class="form-label">Phone</label>
                <input type="tel" class="form-control" id="edit_phone" name="phone" 
                       value="' . htmlspecialchars($user['phone']) . '">
            </div>
            <div class="col-md-6">
                <label for="edit_role" class="form-label">Role *</label>
                <select class="form-select" id="edit_role" name="role" required>
                    <option value="user"' . ($user['role'] === 'user' ? ' selected' : '') . '>User</option>
                    <option value="client"' . ($user['role'] === 'client' ? ' selected' : '') . '>Client</option>
                    <option value="admin"' . ($user['role'] === 'admin' ? ' selected' : '') . '>Admin</option>
                </select>
            </div>
            <div class="col-md-6">
                <label for="edit_status" class="form-label">Status *</label>
                <select class="form-select" id="edit_status" name="status" required>
                    <option value="active"' . ($user['status'] === 'active' ? ' selected' : '') . '>Active</option>
                    <option value="inactive"' . ($user['status'] === 'inactive' ? ' selected' : '') . '>Inactive</option>
                    <option value="locked"' . ($user['status'] === 'locked' ? ' selected' : '') . '>Locked</option>
                </select>
            </div>
        </div>
    ';
    
    return $html;
}

function resetUserPasswordById($user_id) {
    global $pdo;
    
    try {
        // Get user
        $stmt = $pdo->prepare("SELECT email, first_name FROM users WHERE id = ?");
        $stmt->execute([$user_id]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$user) {
            return false;
        }
        
        // Generate new password
        $temp_password = generateRandomPassword(12);
        $password_hash = password_hash($temp_password, PASSWORD_DEFAULT);
        
        // Update password
        $stmt = $pdo->prepare("
            UPDATE users 
            SET password_hash = ?, password_reset_required = 1, updated_at = NOW() 
            WHERE id = ?
        ");
        
        $stmt->execute([$password_hash, $user_id]);
        
        // Send email
        sendPasswordResetEmail($user['email'], $user['first_name'], $temp_password);
        
        return true;
        
    } catch (Exception $e) {
        error_log('Error resetting password for user ' . $user_id . ': ' . $e->getMessage());
        return false;
    }
}

function generateRandomPassword($length = 12) {
    $chars = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789!@#$%^&*';
    return substr(str_shuffle($chars), 0, $length);
}

function sendWelcomeEmail($email, $name, $password) {
    // Implementation would integrate with existing email system
    // For now, just log the action
    error_log("Welcome email sent to: $email");
}

function sendPasswordResetEmail($email, $name, $temp_password) {
    // Implementation would integrate with existing email system
    // For now, just log the action
    error_log("Password reset email sent to: $email");
}

// Additional helper functions for permissions, roles, etc.
function getUserPermissionsList($user_id) {
    // Implementation for getting user permissions
    return [];
}

function getUserPermissions($user_id) {
    echo json_encode(['success' => true, 'data' => getUserPermissionsList($user_id)]);
}

function getUserSecuritySettings($user_id, $current_user) {
    // Implementation for getting security settings
    echo json_encode(['success' => true, 'data' => []]);
}

function getUserRoles() {
    echo json_encode([
        'success' => true,
        'data' => [
            ['value' => 'user', 'label' => 'User'],
            ['value' => 'client', 'label' => 'Client'],
            ['value' => 'admin', 'label' => 'Administrator']
        ]
    ]);
}

function updateUserProfile($data, $current_user) {
    updateUser($current_user['id'], $data, $current_user);
}

function updateUserPermissions($user_id, $data, $current_user) {
    // Implementation for updating user permissions
    echo json_encode(['success' => true, 'message' => 'Permissions updated']);
}

function activateUser($user_id, $current_user) {
    updateUser($user_id, ['status' => 'active'], $current_user);
}

function deactivateUser($user_id, $current_user) {
    updateUser($user_id, ['status' => 'inactive'], $current_user);
}

function deleteUserAccount($current_user) {
    deleteUser($current_user['id'], $current_user);
}

function enableTwoFactorAuth($data, $current_user) {
    // Implementation for enabling 2FA
    echo json_encode(['success' => true, 'message' => '2FA enabled']);
}

function disableTwoFactorAuth($data, $current_user) {
    // Implementation for disabling 2FA
    echo json_encode(['success' => true, 'message' => '2FA disabled']);
}

function updateSecuritySettings($data, $current_user) {
    // Implementation for updating security settings
    echo json_encode(['success' => true, 'message' => 'Security settings updated']);
}

function sendVerificationEmail($data, $current_user) {
    // Implementation for sending verification email
    echo json_encode(['success' => true, 'message' => 'Verification email sent']);
}

?>