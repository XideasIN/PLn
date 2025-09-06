<?php
/**
 * AI Learning API Endpoint
 * Handles AI learning requests, knowledge base management, and search functionality
 */

require_once '../includes/functions.php';
require_once '../includes/auth.php';

// Set JSON response headers
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

// Handle preflight requests
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

// Require admin authentication for all AI learning operations
if (!isLoggedIn() || !isAdmin()) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Admin authentication required']);
    exit();
}

$method = $_SERVER['REQUEST_METHOD'];
$path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$path_parts = explode('/', trim($path, '/'));

try {
    $db = getDB();
    
    // Route handling
    if ($method === 'GET' && strpos($path, '/requests') !== false) {
        // GET /api/ai-learning/requests - Get all learning requests
        handleGetLearningRequests($db);
        
    } elseif ($method === 'POST' && strpos($path, '/requests') !== false && !preg_match('/\/\d+\/retry$/', $path)) {
        // POST /api/ai-learning/requests - Create new learning request
        handleCreateLearningRequest($db);
        
    } elseif ($method === 'GET' && preg_match('/\/requests\/(\d+)$/', $path, $matches)) {
        // GET /api/ai-learning/requests/{id} - Get specific request
        handleGetLearningRequest($db, $matches[1]);
        
    } elseif ($method === 'POST' && preg_match('/\/requests\/(\d+)\/retry$/', $path, $matches)) {
        // POST /api/ai-learning/requests/{id}/retry - Retry failed request
        handleRetryLearningRequest($db, $matches[1]);
        
    } elseif ($method === 'GET' && strpos($path, '/sources') !== false) {
        // GET /api/ai-learning/sources - Get available sources
        handleGetAvailableSources($db);
        
    } elseif ($method === 'POST' && strpos($path, '/knowledge-base/search') !== false) {
        // POST /api/ai-learning/knowledge-base/search - Search knowledge base
        handleSearchKnowledgeBase($db);
        
    } elseif ($method === 'GET' && strpos($path, '/knowledge-base/stats') !== false) {
        // GET /api/ai-learning/knowledge-base/stats - Get statistics
        handleGetKnowledgeBaseStats($db);
        
    } else {
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'Endpoint not found']);
    }
    
} catch (Exception $e) {
    error_log('AI Learning API Error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Internal server error']);
}

/**
 * Get all learning requests
 */
function handleGetLearningRequests($db) {
    try {
        $stmt = $db->prepare("
            SELECT alr.*, u.first_name, u.last_name, u.email as requester_email
            FROM ai_learning_requests alr
            LEFT JOIN users u ON alr.requested_by = u.id
            ORDER BY alr.requested_at DESC
        ");
        $stmt->execute();
        $requests = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Process metadata
        foreach ($requests as &$request) {
            if ($request['learning_metadata']) {
                $request['learning_metadata'] = json_decode($request['learning_metadata'], true);
            }
        }
        
        echo json_encode([
            'success' => true,
            'requests' => $requests,
            'total' => count($requests)
        ]);
        
    } catch (Exception $e) {
        throw new Exception('Failed to get learning requests: ' . $e->getMessage());
    }
}

/**
 * Create new learning request
 */
function handleCreateLearningRequest($db) {
    try {
        $input = json_decode(file_get_contents('php://input'), true);
        
        if (!$input || !isset($input['request_type']) || !isset($input['source_name']) || !isset($input['content'])) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Missing required fields']);
            return;
        }
        
        $request_type = $input['request_type'];
        $source_id = $input['source_id'] ?? null;
        $source_name = $input['source_name'];
        $content = $input['content'];
        $priority = $input['priority'] ?? 1;
        $requested_by = getCurrentUserId();
        
        // Validate request type
        if (!in_array($request_type, ['email_template', 'document_template', 'custom_content'])) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Invalid request type']);
            return;
        }
        
        // Insert learning request
        $stmt = $db->prepare("
            INSERT INTO ai_learning_requests 
            (request_type, source_id, source_name, content, priority, requested_by, status)
            VALUES (?, ?, ?, ?, ?, ?, 'pending')
        ");
        
        $stmt->execute([$request_type, $source_id, $source_name, $content, $priority, $requested_by]);
        $request_id = $db->lastInsertId();
        
        // Process the learning request immediately for high priority
        if ($priority >= 3) {
            processLearningRequest($db, $request_id);
        }
        
        echo json_encode([
            'success' => true,
            'message' => 'Learning request created successfully',
            'request_id' => $request_id
        ]);
        
    } catch (Exception $e) {
        throw new Exception('Failed to create learning request: ' . $e->getMessage());
    }
}

/**
 * Get specific learning request
 */
function handleGetLearningRequest($db, $request_id) {
    try {
        $stmt = $db->prepare("
            SELECT alr.*, u.first_name, u.last_name, u.email as requester_email
            FROM ai_learning_requests alr
            LEFT JOIN users u ON alr.requested_by = u.id
            WHERE alr.id = ?
        ");
        $stmt->execute([$request_id]);
        $request = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$request) {
            http_response_code(404);
            echo json_encode(['success' => false, 'message' => 'Request not found']);
            return;
        }
        
        if ($request['learning_metadata']) {
            $request['learning_metadata'] = json_decode($request['learning_metadata'], true);
        }
        
        echo json_encode([
            'success' => true,
            'request' => $request
        ]);
        
    } catch (Exception $e) {
        throw new Exception('Failed to get learning request: ' . $e->getMessage());
    }
}

/**
 * Retry failed learning request
 */
function handleRetryLearningRequest($db, $request_id) {
    try {
        // Check if request exists and is failed
        $stmt = $db->prepare("SELECT * FROM ai_learning_requests WHERE id = ? AND status = 'failed'");
        $stmt->execute([$request_id]);
        $request = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$request) {
            http_response_code(404);
            echo json_encode(['success' => false, 'message' => 'Failed request not found']);
            return;
        }
        
        // Reset status to pending
        $stmt = $db->prepare("
            UPDATE ai_learning_requests 
            SET status = 'pending', error_message = NULL, processed_at = NULL
            WHERE id = ?
        ");
        $stmt->execute([$request_id]);
        
        // Process the request
        processLearningRequest($db, $request_id);
        
        echo json_encode([
            'success' => true,
            'message' => 'Learning request retried successfully'
        ]);
        
    } catch (Exception $e) {
        throw new Exception('Failed to retry learning request: ' . $e->getMessage());
    }
}

/**
 * Get available sources for learning
 */
function handleGetAvailableSources($db) {
    try {
        $sources = [];
        
        // Get email templates
        $stmt = $db->query("SELECT id, name, subject, 'email_template' as type FROM email_templates WHERE status = 'active'");
        $email_templates = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $sources['email_templates'] = $email_templates;
        
        // Get document templates (if table exists)
        try {
            $stmt = $db->query("SELECT id, name, 'document_template' as type FROM document_templates WHERE status = 'active'");
            $document_templates = $stmt->fetchAll(PDO::FETCH_ASSOC);
            $sources['document_templates'] = $document_templates;
        } catch (Exception $e) {
            $sources['document_templates'] = [];
        }
        
        echo json_encode([
            'success' => true,
            'sources' => $sources
        ]);
        
    } catch (Exception $e) {
        throw new Exception('Failed to get available sources: ' . $e->getMessage());
    }
}

/**
 * Search knowledge base
 */
function handleSearchKnowledgeBase($db) {
    try {
        $input = json_decode(file_get_contents('php://input'), true);
        
        if (!$input || !isset($input['query'])) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Search query required']);
            return;
        }
        
        $query = $input['query'];
        $limit = $input['limit'] ?? 10;
        
        // Search in processed learning requests
        $stmt = $db->prepare("
            SELECT id, source_name, request_type, learning_metadata, processed_at
            FROM ai_learning_requests 
            WHERE status = 'completed' 
            AND (source_name LIKE ? OR content LIKE ? OR learning_metadata LIKE ?)
            ORDER BY processed_at DESC
            LIMIT ?
        ");
        
        $search_term = '%' . $query . '%';
        $stmt->execute([$search_term, $search_term, $search_term, $limit]);
        $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Process results
        foreach ($results as &$result) {
            if ($result['learning_metadata']) {
                $metadata = json_decode($result['learning_metadata'], true);
                $result['summary'] = $metadata['summary'] ?? 'No summary available';
                $result['key_phrases'] = $metadata['key_phrases'] ?? [];
                $result['content_type'] = $metadata['content_type'] ?? 'general';
            }
        }
        
        echo json_encode([
            'success' => true,
            'results' => $results,
            'query' => $query,
            'total' => count($results)
        ]);
        
    } catch (Exception $e) {
        throw new Exception('Failed to search knowledge base: ' . $e->getMessage());
    }
}

/**
 * Get knowledge base statistics
 */
function handleGetKnowledgeBaseStats($db) {
    try {
        // Get total entries
        $stmt = $db->query("SELECT COUNT(*) as total FROM ai_learning_requests WHERE status = 'completed'");
        $total_entries = $stmt->fetch(PDO::FETCH_ASSOC)['total'];
        
        // Get content type distribution
        $stmt = $db->query("
            SELECT 
                JSON_EXTRACT(learning_metadata, '$.content_type') as content_type,
                COUNT(*) as count
            FROM ai_learning_requests 
            WHERE status = 'completed' AND learning_metadata IS NOT NULL
            GROUP BY JSON_EXTRACT(learning_metadata, '$.content_type')
        ");
        $content_types = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Get recent activity
        $stmt = $db->query("
            SELECT DATE(processed_at) as date, COUNT(*) as count
            FROM ai_learning_requests 
            WHERE status = 'completed' AND processed_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
            GROUP BY DATE(processed_at)
            ORDER BY date DESC
        ");
        $recent_activity = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Get last updated
        $stmt = $db->query("SELECT MAX(processed_at) as last_updated FROM ai_learning_requests WHERE status = 'completed'");
        $last_updated = $stmt->fetch(PDO::FETCH_ASSOC)['last_updated'];
        
        echo json_encode([
            'success' => true,
            'stats' => [
                'total_entries' => $total_entries,
                'content_types' => $content_types,
                'recent_activity' => $recent_activity,
                'last_updated' => $last_updated
            ]
        ]);
        
    } catch (Exception $e) {
        throw new Exception('Failed to get knowledge base stats: ' . $e->getMessage());
    }
}

/**
 * Process learning request (extract information and update knowledge base)
 */
function processLearningRequest($db, $request_id) {
    try {
        // Update status to processing
        $stmt = $db->prepare("UPDATE ai_learning_requests SET status = 'processing' WHERE id = ?");
        $stmt->execute([$request_id]);
        
        // Get request details
        $stmt = $db->prepare("SELECT * FROM ai_learning_requests WHERE id = ?");
        $stmt->execute([$request_id]);
        $request = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$request) {
            throw new Exception('Request not found');
        }
        
        // Extract key information from content
        $metadata = extractContentMetadata($request['content'], $request['request_type']);
        
        // Update request with processed metadata
        $stmt = $db->prepare("
            UPDATE ai_learning_requests 
            SET status = 'completed', processed_at = NOW(), learning_metadata = ?
            WHERE id = ?
        ");
        $stmt->execute([json_encode($metadata), $request_id]);
        
        return true;
        
    } catch (Exception $e) {
        // Update status to failed
        $stmt = $db->prepare("
            UPDATE ai_learning_requests 
            SET status = 'failed', error_message = ?, processed_at = NOW()
            WHERE id = ?
        ");
        $stmt->execute([$e->getMessage(), $request_id]);
        
        throw $e;
    }
}

/**
 * Extract metadata from content
 */
function extractContentMetadata($content, $request_type) {
    // Remove HTML tags and clean content
    $clean_content = strip_tags($content);
    $clean_content = preg_replace('/\s+/', ' ', $clean_content);
    $clean_content = trim($clean_content);
    
    // Extract key phrases (simple implementation)
    $key_phrases = extractKeyPhrases($clean_content);
    
    // Classify content type
    $content_type = classifyContent($clean_content);
    
    // Generate summary
    $summary = generateSummary($clean_content);
    
    // Extract structured data
    $structured_data = extractStructuredData($clean_content);
    
    return [
        'summary' => $summary,
        'key_phrases' => $key_phrases,
        'content_type' => $content_type,
        'structured_data' => $structured_data,
        'word_count' => str_word_count($clean_content),
        'processed_at' => date('Y-m-d H:i:s')
    ];
}

/**
 * Extract key phrases from content
 */
function extractKeyPhrases($content) {
    // Simple keyword extraction (can be enhanced with NLP)
    $words = str_word_count(strtolower($content), 1);
    $word_freq = array_count_values($words);
    
    // Filter out common words
    $stop_words = ['the', 'and', 'or', 'but', 'in', 'on', 'at', 'to', 'for', 'of', 'with', 'by', 'is', 'are', 'was', 'were', 'be', 'been', 'have', 'has', 'had', 'do', 'does', 'did', 'will', 'would', 'could', 'should', 'may', 'might', 'must', 'can', 'this', 'that', 'these', 'those', 'a', 'an'];
    
    foreach ($stop_words as $stop_word) {
        unset($word_freq[$stop_word]);
    }
    
    // Get top keywords
    arsort($word_freq);
    return array_keys(array_slice($word_freq, 0, 10));
}

/**
 * Classify content type
 */
function classifyContent($content) {
    $content_lower = strtolower($content);
    
    // Financial terms
    $financial_terms = ['loan', 'credit', 'payment', 'interest', 'rate', 'amount', 'balance', 'finance', 'money', 'dollar', 'bank', 'account'];
    $financial_count = 0;
    foreach ($financial_terms as $term) {
        $financial_count += substr_count($content_lower, $term);
    }
    
    // Communication terms
    $communication_terms = ['email', 'message', 'contact', 'support', 'help', 'phone', 'call', 'response', 'reply', 'notification'];
    $communication_count = 0;
    foreach ($communication_terms as $term) {
        $communication_count += substr_count($content_lower, $term);
    }
    
    // Legal terms
    $legal_terms = ['agreement', 'contract', 'terms', 'conditions', 'legal', 'document', 'signature', 'consent', 'privacy', 'policy'];
    $legal_count = 0;
    foreach ($legal_terms as $term) {
        $legal_count += substr_count($content_lower, $term);
    }
    
    // Determine category
    if ($financial_count >= $communication_count && $financial_count >= $legal_count) {
        return 'financial';
    } elseif ($communication_count >= $legal_count) {
        return 'communication';
    } elseif ($legal_count > 0) {
        return 'legal';
    } else {
        return 'general';
    }
}

/**
 * Generate content summary
 */
function generateSummary($content) {
    // Simple summary generation (first 200 characters)
    $summary = substr($content, 0, 200);
    if (strlen($content) > 200) {
        $summary .= '...';
    }
    return $summary;
}

/**
 * Extract structured data (emails, phones, URLs, etc.)
 */
function extractStructuredData($content) {
    $data = [];
    
    // Extract emails
    preg_match_all('/[a-zA-Z0-9._%+-]+@[a-zA-Z0-9.-]+\.[a-zA-Z]{2,}/', $content, $emails);
    if (!empty($emails[0])) {
        $data['emails'] = array_unique($emails[0]);
    }
    
    // Extract phone numbers
    preg_match_all('/\b\d{3}[-.]?\d{3}[-.]?\d{4}\b/', $content, $phones);
    if (!empty($phones[0])) {
        $data['phones'] = array_unique($phones[0]);
    }
    
    // Extract URLs
    preg_match_all('/https?:\/\/[^\s]+/', $content, $urls);
    if (!empty($urls[0])) {
        $data['urls'] = array_unique($urls[0]);
    }
    
    // Extract dates
    preg_match_all('/\b\d{1,2}[\/\-]\d{1,2}[\/\-]\d{2,4}\b/', $content, $dates);
    if (!empty($dates[0])) {
        $data['dates'] = array_unique($dates[0]);
    }
    
    return $data;
}

?>