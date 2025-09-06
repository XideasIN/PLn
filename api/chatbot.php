<?php
/**
 * Chatbot API Endpoint
 * LoanFlow Personal Loan Management System
 */

// Set headers for JSON API
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

// Handle preflight OPTIONS request
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}


require_once '../includes/auth.php';

// Handle different actions
$action = $_GET['action'] ?? $_POST['action'] ?? 'send_message';

try {
    switch ($action) {
        case 'send_message':
            handleSendMessage();
            break;
            
        case 'get_conversation':
            handleGetConversation();
            break;
            
        case 'export_logs':
            handleExportLogs();
            break;
            
        case 'get_stats':
            handleGetStats();
            break;
            
        default:
            handleSendMessage(); // Default to sending message for backward compatibility
            break;
    }
} catch (Exception $e) {
    error_log("Chatbot API error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Internal server error'
    ]);
}

/**
 * Handle sending chatbot messages (original functionality)
 */
function handleSendMessage() {
    // Only allow POST requests for sending messages
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        http_response_code(405);
        echo json_encode(['success' => false, 'error' => 'Method not allowed']);
        exit;
    }

// Include required files
require_once '../config/database.php';
require_once '../includes/functions.php';
require_once '../includes/chatbot.php';

    // Rate limiting check
    $ip_address = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
    $rate_limit_file = sys_get_temp_dir() . '/chatbot_rate_' . md5($ip_address);
    $current_time = time();
    $rate_limit_window = 60; // 1 minute
    $max_requests = 10; // Max 10 requests per minute

    if (file_exists($rate_limit_file)) {
        $requests = json_decode(file_get_contents($rate_limit_file), true) ?: [];
        
        // Remove old requests
        $requests = array_filter($requests, function($timestamp) use ($current_time, $rate_limit_window) {
            return ($current_time - $timestamp) < $rate_limit_window;
        });
        
        if (count($requests) >= $max_requests) {
            http_response_code(429);
            echo json_encode([
                'success' => false,
                'error' => 'Rate limit exceeded. Please wait before sending another message.'
            ]);
            exit;
        }
        
        $requests[] = $current_time;
    } else {
        $requests = [$current_time];
    }

    file_put_contents($rate_limit_file, json_encode($requests));

    // Get JSON input
    $input = json_decode(file_get_contents('php://input'), true);
    
    if (!$input || !isset($input['message'])) {
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'error' => 'Missing message parameter'
        ]);
        exit;
    }
    
    $message = trim($input['message']);
    $conversation_history = $input['conversation_history'] ?? [];
    
    // Validate message
    if (empty($message)) {
        echo json_encode([
            'success' => false,
            'error' => 'Message cannot be empty'
        ]);
        exit;
    }
    
    if (strlen($message) > 1000) {
        echo json_encode([
            'success' => false,
            'error' => 'Message too long. Please keep messages under 1000 characters.'
        ]);
        exit;
    }
    
    // Check for inappropriate content (basic filter)
    $inappropriate_words = ['spam', 'hack', 'exploit', 'virus', 'malware'];
    $message_lower = strtolower($message);
    
    foreach ($inappropriate_words as $word) {
        if (strpos($message_lower, $word) !== false) {
            echo json_encode([
                'success' => false,
                'error' => 'Message contains inappropriate content'
            ]);
            exit;
        }
    }
    
    // Check if chatbot is enabled
    if (!ChatbotManager::isEnabled()) {
        echo json_encode([
            'success' => false,
            'error' => 'Chatbot service is currently unavailable'
        ]);
        exit;
    }
    
    // Detect if this is from client area
    $is_client_area = isset($input['client_area']) && $input['client_area'] === true;
    $user_id = $input['user_id'] ?? null;
    
    // Process message with AI (enhanced for client area)
    if ($is_client_area) {
        $response = ChatbotManager::processClientAreaMessage($message, $conversation_history, $user_id);
    } else {
        $response = ChatbotManager::processMessage($message, $conversation_history);
    }
    
    // Add some delay to make it feel more natural
    usleep(rand(500000, 1500000)); // 0.5-1.5 seconds
    
    echo json_encode($response);
}

/**
 * Get conversation details for admin
 */
function handleGetConversation() {
    // Check if user is admin
    if (!isLoggedIn() || !isAdmin()) {
        http_response_code(403);
        echo json_encode(['success' => false, 'error' => 'Admin access required']);
        exit;
    }
    
    $conversation_id = intval($_GET['id'] ?? 0);
    if (!$conversation_id) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Conversation ID required']);
        exit;
    }
    
    $db = getDB();
    $stmt = $db->prepare("
        SELECT cc.*, u.first_name, u.last_name, u.email
        FROM chatbot_conversations cc
        LEFT JOIN users u ON cc.user_id = u.id
        WHERE cc.id = ?
    ");
    $stmt->execute([$conversation_id]);
    $conversation = $stmt->fetch();
    
    if (!$conversation) {
        http_response_code(404);
        echo json_encode(['success' => false, 'error' => 'Conversation not found']);
        exit;
    }
    
    echo json_encode([
        'success' => true,
        'conversation' => $conversation
    ]);
}

/**
 * Export conversation logs for admin
 */
function handleExportLogs() {
    // Check if user is admin
    if (!isLoggedIn() || !isAdmin()) {
        http_response_code(403);
        echo json_encode(['success' => false, 'error' => 'Admin access required']);
        exit;
    }
    
    $format = $_GET['format'] ?? 'csv';
    $days = intval($_GET['days'] ?? 30);
    
    $db = getDB();
    $stmt = $db->prepare("
        SELECT cc.*, u.first_name, u.last_name, u.email
        FROM chatbot_conversations cc
        LEFT JOIN users u ON cc.user_id = u.id
        WHERE cc.created_at >= DATE_SUB(NOW(), INTERVAL ? DAY)
        ORDER BY cc.created_at DESC
    ");
    $stmt->execute([$days]);
    $conversations = $stmt->fetchAll();
    
    if ($format === 'csv') {
        header('Content-Type: text/csv');
        header('Content-Disposition: attachment; filename="chatbot_logs_' . date('Y-m-d') . '.csv"');
        
        $output = fopen('php://output', 'w');
        
        // CSV headers
        fputcsv($output, [
            'Date',
            'User Name',
            'User Email',
            'User Message',
            'Bot Response',
            'IP Address',
            'User Agent'
        ]);
        
        // CSV data
        foreach ($conversations as $conv) {
            fputcsv($output, [
                $conv['created_at'],
                ($conv['first_name'] ?? '') . ' ' . ($conv['last_name'] ?? ''),
                $conv['email'] ?? 'Anonymous',
                $conv['user_message'],
                $conv['bot_response'],
                $conv['ip_address'],
                $conv['user_agent']
            ]);
        }
        
        fclose($output);
    } else {
        // JSON format
        header('Content-Type: application/json');
        header('Content-Disposition: attachment; filename="chatbot_logs_' . date('Y-m-d') . '.json"');
        
        echo json_encode([
            'export_date' => date('Y-m-d H:i:s'),
            'total_conversations' => count($conversations),
            'conversations' => $conversations
        ], JSON_PRETTY_PRINT);
    }
}

/**
 * Get chatbot statistics for admin dashboard
 */
function handleGetStats() {
    // Check if user is admin
    if (!isLoggedIn() || !isAdmin()) {
        http_response_code(403);
        echo json_encode(['success' => false, 'error' => 'Admin access required']);
        exit;
    }
    
    $db = getDB();
    
    // Overall stats
    $stats_query = $db->query("
        SELECT 
            COUNT(*) as total_conversations,
            COUNT(DISTINCT user_id) as unique_users,
            COUNT(DISTINCT ip_address) as unique_ips,
            AVG(CHAR_LENGTH(user_message)) as avg_message_length,
            AVG(CHAR_LENGTH(bot_response)) as avg_response_length,
            COUNT(CASE WHEN created_at >= DATE_SUB(NOW(), INTERVAL 1 HOUR) THEN 1 END) as conversations_1h,
            COUNT(CASE WHEN created_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR) THEN 1 END) as conversations_24h,
            COUNT(CASE WHEN created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY) THEN 1 END) as conversations_7d,
            COUNT(CASE WHEN created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY) THEN 1 END) as conversations_30d
        FROM chatbot_conversations
    ");
    $stats = $stats_query->fetch();
    
    // Daily conversation counts for the last 7 days
    $daily_query = $db->query("
        SELECT 
            DATE(created_at) as date,
            COUNT(*) as count
        FROM chatbot_conversations
        WHERE created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)
        GROUP BY DATE(created_at)
        ORDER BY date DESC
    ");
    $daily_stats = $daily_query->fetchAll();
    
    // Top user messages (most common questions)
    $top_messages_query = $db->query("
        SELECT 
            user_message,
            COUNT(*) as frequency
        FROM chatbot_conversations
        WHERE created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
        GROUP BY user_message
        HAVING frequency > 1
        ORDER BY frequency DESC
        LIMIT 10
    ");
    $top_messages = $top_messages_query->fetchAll();
    
    echo json_encode([
        'success' => true,
        'stats' => $stats,
        'daily_stats' => $daily_stats,
        'top_messages' => $top_messages,
        'chatbot_enabled' => ChatbotManager::isEnabled()
    ]);
}
?>
