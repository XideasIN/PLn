<?php
/**
 * Document Management API
 * LoanFlow Personal Loan Management System
 * 
 * Handles document management requests including:
 * - Document CRUD operations
 * - Permission management
 * - Analytics and reporting
 * - PDF generation
 */

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

// Handle preflight requests
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

// Only allow POST requests for this API
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit();
}

require_once '../includes/config.php';
require_once '../includes/functions.php';
require_once '../includes/auth.php';

// Rate limiting
session_start();
$rate_limit_key = 'document_api_' . ($_SESSION['user_id'] ?? $_SERVER['REMOTE_ADDR']);
if (!checkRateLimit($rate_limit_key, 60, 100)) { // 100 requests per minute
    http_response_code(429);
    echo json_encode(['success' => false, 'message' => 'Rate limit exceeded']);
    exit();
}

// Authentication check
if (!isLoggedIn()) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Authentication required']);
    exit();
}

// Get action from request
$action = $_POST['action'] ?? '';

try {
    switch ($action) {
        case 'get_document':
            handleGetDocument();
            break;
            
        case 'preview_document':
            handlePreviewDocument();
            break;
            
        case 'get_user_permissions':
            handleGetUserPermissions();
            break;
            
        case 'save_permissions':
            handleSavePermissions();
            break;
            
        case 'get_analytics':
            handleGetAnalytics();
            break;
            
        case 'generate_pdf':
            handleGeneratePDF();
            break;
            
        case 'log_download':
            handleLogDownload();
            break;
            
        case 'get_client_documents':
            handleGetClientDocuments();
            break;
            
        case 'personalize_document':
            handlePersonalizeDocument();
            break;
            
        default:
            throw new Exception('Invalid action specified');
    }
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage(),
        'error_code' => $e->getCode()
    ]);
}

/**
 * Get document details for editing
 */
function handleGetDocument() {
    global $pdo;
    
    // Admin authorization check
    if (!isAdmin()) {
        throw new Exception('Admin access required');
    }
    
    $documentId = intval($_POST['document_id'] ?? 0);
    if (!$documentId) {
        throw new Exception('Document ID is required');
    }
    
    $stmt = $pdo->prepare("
        SELECT ed.*, dc.name as category_name,
               JSON_EXTRACT(ed.template_variables, '$') as variables
        FROM editable_documents ed
        LEFT JOIN document_categories dc ON ed.category_id = dc.id
        WHERE ed.id = ?
    ");
    $stmt->execute([$documentId]);
    $document = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$document) {
        throw new Exception('Document not found');
    }
    
    echo json_encode([
        'success' => true,
        'document' => $document
    ]);
}

/**
 * Preview document with sample data
 */
function handlePreviewDocument() {
    global $pdo;
    
    $documentId = intval($_POST['document_id'] ?? 0);
    if (!$documentId) {
        throw new Exception('Document ID is required');
    }
    
    // Check if user has access to this document
    if (!isAdmin()) {
        $hasAccess = checkDocumentAccess($_SESSION['user_id'], $documentId, 'view');
        if (!$hasAccess) {
            throw new Exception('Access denied');
        }
    }
    
    $stmt = $pdo->prepare("SELECT content, template_variables FROM editable_documents WHERE id = ?");
    $stmt->execute([$documentId]);
    $document = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$document) {
        throw new Exception('Document not found');
    }
    
    // Get sample data for preview
    $sampleData = getSampleDocumentData();
    $content = personalizeDocumentContent($document['content'], $sampleData);
    
    echo json_encode([
        'success' => true,
        'content' => $content
    ]);
}

/**
 * Get user permissions for all documents
 */
function handleGetUserPermissions() {
    global $pdo;
    
    // Admin authorization check
    if (!isAdmin()) {
        throw new Exception('Admin access required');
    }
    
    $userId = intval($_POST['user_id'] ?? 0);
    if (!$userId) {
        throw new Exception('User ID is required');
    }
    
    // Get user details
    $stmt = $pdo->prepare("SELECT id, first_name, last_name, email FROM users WHERE id = ? AND role = 'client'");
    $stmt->execute([$userId]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$user) {
        throw new Exception('User not found');
    }
    
    // Get all documents
    $stmt = $pdo->query("
        SELECT id, title, document_key, is_client_visible, requires_download_permission
        FROM editable_documents
        WHERE is_client_visible = 1
        ORDER BY title
    ");
    $documents = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Get current permissions
    $stmt = $pdo->prepare("
        SELECT document_id, can_view, can_download, expires_at, notes
        FROM client_document_permissions
        WHERE user_id = ?
    ");
    $stmt->execute([$userId]);
    $permissions = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo json_encode([
        'success' => true,
        'user' => $user,
        'documents' => $documents,
        'permissions' => $permissions
    ]);
}

/**
 * Save user document permissions
 */
function handleSavePermissions() {
    global $pdo;
    
    // Admin authorization check
    if (!isAdmin()) {
        throw new Exception('Admin access required');
    }
    
    $userId = intval($_POST['user_id'] ?? 0);
    if (!$userId) {
        throw new Exception('User ID is required');
    }
    
    $documents = $_POST['documents'] ?? [];
    
    $pdo->beginTransaction();
    
    try {
        // Clear existing permissions
        $stmt = $pdo->prepare("DELETE FROM client_document_permissions WHERE user_id = ?");
        $stmt->execute([$userId]);
        
        // Insert new permissions
        foreach ($documents as $documentId => $permissions) {
            $canView = isset($permissions['can_view']) ? 1 : 0;
            $canDownload = isset($permissions['can_download']) ? 1 : 0;
            $expiresAt = !empty($permissions['expires_at']) ? $permissions['expires_at'] : null;
            
            if ($canView || $canDownload) {
                $stmt = $pdo->prepare("
                    INSERT INTO client_document_permissions 
                    (user_id, document_id, can_view, can_download, granted_by, expires_at)
                    VALUES (?, ?, ?, ?, ?, ?)
                ");
                $stmt->execute([$userId, $documentId, $canView, $canDownload, $_SESSION['user_id'], $expiresAt]);
            }
        }
        
        $pdo->commit();
        
        echo json_encode([
            'success' => true,
            'message' => 'Permissions saved successfully'
        ]);
        
    } catch (Exception $e) {
        $pdo->rollBack();
        throw $e;
    }
}

/**
 * Get analytics data for dashboard
 */
function handleGetAnalytics() {
    global $pdo;
    
    // Admin authorization check
    if (!isAdmin()) {
        throw new Exception('Admin access required');
    }
    
    // Downloads by document
    $stmt = $pdo->query("
        SELECT ed.title, COUNT(ddl.id) as download_count
        FROM editable_documents ed
        LEFT JOIN document_download_log ddl ON ed.id = ddl.document_id
        GROUP BY ed.id, ed.title
        ORDER BY download_count DESC
        LIMIT 10
    ");
    $downloadsByDocument = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Access distribution
    $stmt = $pdo->query("
        SELECT ed.title, COUNT(cdp.user_id) as access_count
        FROM editable_documents ed
        LEFT JOIN client_document_permissions cdp ON ed.id = cdp.document_id AND cdp.can_view = 1
        WHERE ed.is_client_visible = 1
        GROUP BY ed.id, ed.title
        ORDER BY access_count DESC
    ");
    $accessDistribution = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Recent downloads
    $stmt = $pdo->query("
        SELECT ed.title, u.first_name, u.last_name, ddl.download_time, ddl.download_type
        FROM document_download_log ddl
        JOIN editable_documents ed ON ddl.document_id = ed.id
        JOIN users u ON ddl.user_id = u.id
        ORDER BY ddl.download_time DESC
        LIMIT 20
    ");
    $recentDownloads = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Monthly download trends
    $stmt = $pdo->query("
        SELECT 
            DATE_FORMAT(download_time, '%Y-%m') as month,
            COUNT(*) as download_count
        FROM document_download_log
        WHERE download_time >= DATE_SUB(NOW(), INTERVAL 12 MONTH)
        GROUP BY DATE_FORMAT(download_time, '%Y-%m')
        ORDER BY month
    ");
    $monthlyTrends = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo json_encode([
        'success' => true,
        'analytics' => [
            'downloads_by_document' => $downloadsByDocument,
            'access_distribution' => $accessDistribution,
            'recent_downloads' => $recentDownloads,
            'monthly_trends' => $monthlyTrends
        ]
    ]);
}

/**
 * Generate PDF for document
 */
function handleGeneratePDF() {
    global $pdo;
    
    $documentId = intval($_POST['document_id'] ?? 0);
    $userId = intval($_POST['user_id'] ?? $_SESSION['user_id']);
    
    if (!$documentId) {
        throw new Exception('Document ID is required');
    }
    
    // Check access permissions
    if (!isAdmin()) {
        $hasAccess = checkDocumentAccess($userId, $documentId, 'download');
        if (!$hasAccess) {
            throw new Exception('Download permission required');
        }
    }
    
    // Get document
    $stmt = $pdo->prepare("SELECT * FROM editable_documents WHERE id = ?");
    $stmt->execute([$documentId]);
    $document = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$document) {
        throw new Exception('Document not found');
    }
    
    // Get user data for personalization
    $userData = getUserDocumentData($userId);
    $personalizedContent = personalizeDocumentContent($document['content'], $userData);
    
    // Generate PDF (using a simple HTML to PDF approach)
    $pdfContent = generatePDFFromMarkdown($personalizedContent, $document['title']);
    
    // Log the download
    logDocumentDownload($userId, $documentId, 'pdf_download', strlen($pdfContent));
    
    // Return PDF data
    echo json_encode([
        'success' => true,
        'pdf_data' => base64_encode($pdfContent),
        'filename' => sanitizeFilename($document['title']) . '.pdf'
    ]);
}

/**
 * Log document download/view
 */
function handleLogDownload() {
    global $pdo;
    
    $documentId = intval($_POST['document_id'] ?? 0);
    $downloadType = $_POST['download_type'] ?? 'view';
    $userId = $_SESSION['user_id'];
    
    if (!$documentId) {
        throw new Exception('Document ID is required');
    }
    
    logDocumentDownload($userId, $documentId, $downloadType);
    
    echo json_encode([
        'success' => true,
        'message' => 'Download logged successfully'
    ]);
}

/**
 * Get documents available to client
 */
function handleGetClientDocuments() {
    global $pdo;
    
    $userId = $_SESSION['user_id'];
    
    $stmt = $pdo->prepare("
        SELECT 
            ed.id,
            ed.title,
            ed.description,
            ed.document_key,
            dc.name as category_name,
            COALESCE(cdp.can_view, 0) as can_view,
            COALESCE(cdp.can_download, 0) as can_download,
            cdp.expires_at,
            (SELECT COUNT(*) FROM document_download_log WHERE user_id = ? AND document_id = ed.id) as download_count
        FROM editable_documents ed
        LEFT JOIN document_categories dc ON ed.category_id = dc.id
        LEFT JOIN client_document_permissions cdp ON ed.id = cdp.document_id AND cdp.user_id = ?
        WHERE ed.is_client_visible = 1
        AND (cdp.expires_at IS NULL OR cdp.expires_at > NOW())
        ORDER BY dc.sort_order, ed.title
    ");
    $stmt->execute([$userId, $userId]);
    $documents = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo json_encode([
        'success' => true,
        'documents' => $documents
    ]);
}

/**
 * Personalize document content for user
 */
function handlePersonalizeDocument() {
    global $pdo;
    
    $documentId = intval($_POST['document_id'] ?? 0);
    $userId = $_SESSION['user_id'];
    
    if (!$documentId) {
        throw new Exception('Document ID is required');
    }
    
    // Check access
    if (!isAdmin()) {
        $hasAccess = checkDocumentAccess($userId, $documentId, 'view');
        if (!$hasAccess) {
            throw new Exception('Access denied');
        }
    }
    
    // Get document
    $stmt = $pdo->prepare("SELECT content FROM editable_documents WHERE id = ?");
    $stmt->execute([$documentId]);
    $document = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$document) {
        throw new Exception('Document not found');
    }
    
    // Get user data and personalize
    $userData = getUserDocumentData($userId);
    $personalizedContent = personalizeDocumentContent($document['content'], $userData);
    
    // Save personalized version
    $stmt = $pdo->prepare("
        INSERT INTO document_personalizations (user_id, document_id, personalized_content, is_current)
        VALUES (?, ?, ?, 1)
        ON DUPLICATE KEY UPDATE
        personalized_content = VALUES(personalized_content),
        generated_at = NOW(),
        is_current = 1
    ");
    $stmt->execute([$userId, $documentId, $personalizedContent]);
    
    echo json_encode([
        'success' => true,
        'content' => $personalizedContent
    ]);
}

/**
 * Helper Functions
 */

/**
 * Check if user has access to document
 */
function checkDocumentAccess($userId, $documentId, $accessType) {
    global $pdo;
    
    $stmt = $pdo->prepare("SELECT CheckDocumentAccess(?, ?, ?) as has_access");
    $stmt->execute([$userId, $documentId, $accessType]);
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    
    return $result['has_access'] == 1;
}

/**
 * Get user data for document personalization
 */
function getUserDocumentData($userId) {
    global $pdo;
    
    $stmt = $pdo->prepare("
        SELECT u.*, la.loan_amount, la.interest_rate, la.loan_term,
               ROUND(la.loan_amount * (la.interest_rate/100/12) * POWER(1 + la.interest_rate/100/12, la.loan_term) / 
                     (POWER(1 + la.interest_rate/100/12, la.loan_term) - 1), 2) as monthly_payment
        FROM users u
        LEFT JOIN loan_applications la ON u.id = la.user_id AND la.status = 'approved'
        WHERE u.id = ?
    ");
    $stmt->execute([$userId]);
    $userData = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$userData) {
        throw new Exception('User not found');
    }
    
    // Add system data
    $userData['current_date'] = date('F j, Y');
    $userData['company_name'] = getSystemSetting('company_name', 'LoanFlow Financial');
    $userData['company_phone'] = getSystemSetting('company_phone', '(555) 123-4567');
    $userData['company_email'] = getSystemSetting('company_email', 'info@loanflow.com');
    $userData['client_full_name'] = $userData['first_name'] . ' ' . $userData['last_name'];
    
    return $userData;
}

/**
 * Get sample data for document preview
 */
function getSampleDocumentData() {
    return [
        'client_full_name' => 'John Smith',
        'client_email' => 'john.smith@example.com',
        'client_phone' => '(555) 123-4567',
        'client_address' => '123 Main St, Anytown, ST 12345',
        'current_date' => date('F j, Y'),
        'company_name' => 'LoanFlow Financial',
        'company_phone' => '(555) 987-6543',
        'company_email' => 'info@loanflow.com',
        'loan_amount' => '25000',
        'interest_rate' => '8.5',
        'loan_term' => '60',
        'monthly_payment' => '512.45',
        'application_id' => 'LF-2024-001',
        'agreement_number' => 'AG-2024-001'
    ];
}

/**
 * Personalize document content with user data
 */
function personalizeDocumentContent($content, $userData) {
    foreach ($userData as $key => $value) {
        $content = str_replace('{{' . $key . '}}', $value, $content);
    }
    return $content;
}

/**
 * Log document download
 */
function logDocumentDownload($userId, $documentId, $downloadType, $fileSize = null) {
    global $pdo;
    
    $stmt = $pdo->prepare("
        INSERT INTO document_download_log 
        (user_id, document_id, download_type, ip_address, user_agent, file_size)
        VALUES (?, ?, ?, ?, ?, ?)
    ");
    $stmt->execute([
        $userId,
        $documentId,
        $downloadType,
        $_SERVER['REMOTE_ADDR'],
        $_SERVER['HTTP_USER_AGENT'] ?? '',
        $fileSize
    ]);
}

/**
 * Generate PDF from markdown content
 */
function generatePDFFromMarkdown($markdownContent, $title) {
    // Convert markdown to HTML
    $htmlContent = convertMarkdownToHTML($markdownContent);
    
    // Create simple PDF-like HTML
    $pdfHtml = "
    <!DOCTYPE html>
    <html>
    <head>
        <meta charset='UTF-8'>
        <title>{$title}</title>
        <style>
            body { font-family: Arial, sans-serif; margin: 40px; line-height: 1.6; }
            h1, h2, h3 { color: #333; page-break-after: avoid; }
            h1 { border-bottom: 2px solid #333; padding-bottom: 10px; }
            p { margin-bottom: 15px; }
            ul, ol { margin-bottom: 15px; }
            .header { text-align: center; margin-bottom: 30px; }
            .footer { position: fixed; bottom: 20px; right: 20px; font-size: 10px; color: #666; }
            @media print { .no-print { display: none; } }
        </style>
    </head>
    <body>
        <div class='header'>
            <h1>{$title}</h1>
            <p>Generated on " . date('F j, Y g:i A') . "</p>
        </div>
        {$htmlContent}
        <div class='footer'>LoanFlow Financial Services</div>
    </body>
    </html>
    ";
    
    // For now, return HTML content (in production, use a proper PDF library)
    return $pdfHtml;
}

/**
 * Convert markdown to HTML (basic implementation)
 */
function convertMarkdownToHTML($markdown) {
    // Basic markdown conversion
    $html = $markdown;
    
    // Headers
    $html = preg_replace('/^### (.*$)/m', '<h3>$1</h3>', $html);
    $html = preg_replace('/^## (.*$)/m', '<h2>$1</h2>', $html);
    $html = preg_replace('/^# (.*$)/m', '<h1>$1</h1>', $html);
    
    // Bold and italic
    $html = preg_replace('/\*\*(.*?)\*\*/', '<strong>$1</strong>', $html);
    $html = preg_replace('/\*(.*?)\*/', '<em>$1</em>', $html);
    
    // Lists
    $html = preg_replace('/^- (.*$)/m', '<li>$1</li>', $html);
    $html = preg_replace('/(<li>.*<\/li>)/s', '<ul>$1</ul>', $html);
    
    // Paragraphs
    $html = preg_replace('/\n\n/', '</p><p>', $html);
    $html = '<p>' . $html . '</p>';
    
    // Clean up
    $html = str_replace('<p></p>', '', $html);
    $html = nl2br($html);
    
    return $html;
}

/**
 * Sanitize filename for download
 */
function sanitizeFilename($filename) {
    return preg_replace('/[^a-zA-Z0-9_-]/', '_', $filename);
}

/**
 * Rate limiting function
 */
function checkRateLimit($key, $windowSeconds, $maxRequests) {
    $cacheFile = sys_get_temp_dir() . '/rate_limit_' . md5($key);
    $now = time();
    
    if (file_exists($cacheFile)) {
        $data = json_decode(file_get_contents($cacheFile), true);
        if ($data && $now - $data['window_start'] < $windowSeconds) {
            if ($data['requests'] >= $maxRequests) {
                return false;
            }
            $data['requests']++;
        } else {
            $data = ['window_start' => $now, 'requests' => 1];
        }
    } else {
        $data = ['window_start' => $now, 'requests' => 1];
    }
    
    file_put_contents($cacheFile, json_encode($data));
    return true;
}

/**
 * Get system setting value
 */
function getSystemSetting($key, $default = null) {
    global $pdo;
    
    try {
        $stmt = $pdo->prepare("SELECT setting_value FROM system_settings WHERE setting_key = ?");
        $stmt->execute([$key]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        
        return $result ? $result['setting_value'] : $default;
    } catch (Exception $e) {
        return $default;
    }
}

/**
 * Check if user is admin
 */
function isAdmin() {
    return isset($_SESSION['role']) && $_SESSION['role'] === 'admin';
}

/**
 * Check if user is logged in
 */
function isLoggedIn() {
    return isset($_SESSION['user_id']) && !empty($_SESSION['user_id']);
}
?>