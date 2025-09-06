<?php
/**
 * Email Availability Check API
 * Checks if an email address is already registered in the system
 */

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

// Handle preflight requests
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

// Only allow POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit();
}

require_once '../includes/functions.php';
require_once '../config/database.php';

try {
    // Get JSON input
    $input = json_decode(file_get_contents('php://input'), true);
    
    if (!$input || !isset($input['email'])) {
        throw new Exception('Email address is required');
    }
    
    $email = sanitize_input($input['email']);
    
    // Validate email format
    if (!validate_email($email)) {
        throw new Exception('Invalid email format');
    }
    
    // Get database connection
    $db = Database::getInstance()->getConnection();
    
    // Check if email exists in users table
    $sql_users = "SELECT COUNT(*) as count FROM users WHERE email = :email";
    $stmt_users = $db->prepare($sql_users);
    $stmt_users->execute([':email' => $email]);
    $user_count = $stmt_users->fetch(PDO::FETCH_ASSOC)['count'];
    
    // Email availability is determined only by users table
    $is_available = ($user_count == 0);
    
    // Return response
    echo json_encode([
        'success' => true,
        'available' => $is_available,
        'message' => $is_available ? 'Email is available' : 'Email is already registered'
    ]);
    
} catch (PDOException $e) {
    error_log('Database error in check-email.php: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Database error occurred. Please try again later.'
    ]);
    
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
?>