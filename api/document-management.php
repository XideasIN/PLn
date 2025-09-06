<?php
/**
 * Document Management API
 * Handles AJAX requests for document management operations
 */

require_once '../includes/config.php';
require_once '../includes/auth.php';
require_once '../includes/functions.php';

// Enable CORS for admin requests
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');
header('Content-Type: application/json');

// Handle preflight requests
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

// Check authentication and admin privileges
session_start();
if (!isLoggedIn() || !isAdmin()) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit();
}

try {
    $action = $_GET['action'] ?? $_POST['action'] ?? '';
    $method = $_SERVER['REQUEST_METHOD'];
    
    switch ($action) {
        case 'get_documents':
            handleGetDocuments();
            break;
            
        case 'save_document':
            handleSaveDocument();
            break;
            
        case 'delete_document':
            handleDeleteDocument();
            break;
            
        case 'toggle_visibility':
            handleToggleVisibility();
            break;
            
        case 'get_document_content':
            handleGetDocumentContent();
            break;
            
        case 'get_permissions':
            handleGetPermissions();
            break;
            
        case 'save_permissions':
            handleSavePermissions();
            break;
            
        case 'get_analytics':
            handleGetAnalytics();
            break;
            
        case 'get_variables':
            handleGetVariables();
            break;
            
        default:
            throw new Exception('Invalid action specified');
    }
    
} catch (Exception $e) {
    error_log('Document Management API Error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}

/**
 * Get all documents with their metadata
 */
function handleGetDocuments() {
    global $pdo;
    
    try {
        $stmt = $pdo->prepare("
            SELECT d.*, dc.category_name,
                   COUNT(DISTINCT cdp.client_id) as client_count,
                   COUNT(DISTINCT dl.id) as download_count
            FROM editable_documents d
            LEFT JOIN document_categories dc ON d.category_id = dc.id
            LEFT JOIN client_document_permissions cdp ON d.id = cdp.document_id AND cdp.is_active = 1
            LEFT JOIN document_download_log dl ON d.id = dl.document_id
            GROUP BY d.id
            ORDER BY d.created_at DESC
        ");
        
        $stmt->execute();
        $documents = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        echo json_encode([
            'success' => true,
            'documents' => $documents
        ]);
        
    } catch (Exception $e) {
        throw new Exception('Failed to retrieve documents: ' . $e->getMessage());
    }
}

/**
 * Save document (create or update)
 */
function handleSaveDocument() {
    global $pdo;
    
    $documentId = $_POST['document_id'] ?? null;
    $title = $_POST['title'] ?? '';
    $content = $_POST['content'] ?? '';
    $categoryId = $_POST['category_id'] ?? 1;
    $isVisible = isset($_POST['is_visible']) ? 1 : 0;
    $requiresApproval = isset($_POST['requires_approval']) ? 1 : 0;
    
    if (empty($title) || empty($content)) {
        throw new Exception('Title and content are required');
    }
    
    try {
        $pdo->beginTransaction();
        
        if ($documentId) {
            // Update existing document
            $stmt = $pdo->prepare("
                UPDATE editable_documents 
                SET title = ?, content = ?, category_id = ?, is_visible_to_clients = ?, 
                    requires_approval = ?, updated_at = NOW()
                WHERE id = ?
            ");
            $stmt->execute([$title, $content, $categoryId, $isVisible, $requiresApproval, $documentId]);
            
            // Create version history
            $stmt = $pdo->prepare("
                INSERT INTO document_versions (document_id, content, version_number, created_by)
                SELECT ?, ?, COALESCE(MAX(version_number), 0) + 1, ?
                FROM document_versions WHERE document_id = ?
            ");
            $stmt->execute([$documentId, $content, $_SESSION['user_id'], $documentId]);
            
        } else {
            // Create new document
            $stmt = $pdo->prepare("
                INSERT INTO editable_documents 
                (title, content, category_id, is_visible_to_clients, requires_approval, created_by)
                VALUES (?, ?, ?, ?, ?, ?)
            ");
            $stmt->execute([$title, $content, $categoryId, $isVisible, $requiresApproval, $_SESSION['user_id']]);
            $documentId = $pdo->lastInsertId();
            
            // Create initial version
            $stmt = $pdo->prepare("
                INSERT INTO document_versions (document_id, content, version_number, created_by)
                VALUES (?, ?, 1, ?)
            ");
            $stmt->execute([$documentId, $content, $_SESSION['user_id']]);
        }
        
        $pdo->commit();
        
        echo json_encode([
            'success' => true,
            'message' => 'Document saved successfully',
            'document_id' => $documentId
        ]);
        
    } catch (Exception $e) {
        $pdo->rollBack();
        throw new Exception('Failed to save document: ' . $e->getMessage());
    }
}

/**
 * Delete document
 */
function handleDeleteDocument() {
    global $pdo;
    
    $documentId = $_POST['document_id'] ?? null;
    
    if (!$documentId) {
        throw new Exception('Document ID is required');
    }
    
    try {
        $pdo->beginTransaction();
        
        // Delete related records first
        $stmt = $pdo->prepare("DELETE FROM client_document_permissions WHERE document_id = ?");
        $stmt->execute([$documentId]);
        
        $stmt = $pdo->prepare("DELETE FROM document_versions WHERE document_id = ?");
        $stmt->execute([$documentId]);
        
        $stmt = $pdo->prepare("DELETE FROM document_download_log WHERE document_id = ?");
        $stmt->execute([$documentId]);
        
        // Delete the document
        $stmt = $pdo->prepare("DELETE FROM editable_documents WHERE id = ?");
        $stmt->execute([$documentId]);
        
        $pdo->commit();
        
        echo json_encode([
            'success' => true,
            'message' => 'Document deleted successfully'
        ]);
        
    } catch (Exception $e) {
        $pdo->rollBack();
        throw new Exception('Failed to delete document: ' . $e->getMessage());
    }
}

/**
 * Toggle document visibility
 */
function handleToggleVisibility() {
    global $pdo;
    
    $documentId = $_POST['document_id'] ?? null;
    
    if (!$documentId) {
        throw new Exception('Document ID is required');
    }
    
    try {
        $stmt = $pdo->prepare("
            UPDATE editable_documents 
            SET is_visible_to_clients = NOT is_visible_to_clients,
                updated_at = NOW()
            WHERE id = ?
        ");
        $stmt->execute([$documentId]);
        
        // Get new status
        $stmt = $pdo->prepare("SELECT is_visible_to_clients FROM editable_documents WHERE id = ?");
        $stmt->execute([$documentId]);
        $result = $stmt->fetch();
        
        echo json_encode([
            'success' => true,
            'message' => 'Visibility updated successfully',
            'is_visible' => (bool)$result['is_visible_to_clients']
        ]);
        
    } catch (Exception $e) {
        throw new Exception('Failed to toggle visibility: ' . $e->getMessage());
    }
}

/**
 * Get document content for editing
 */
function handleGetDocumentContent() {
    global $pdo;
    
    $documentId = $_GET['document_id'] ?? null;
    
    if (!$documentId) {
        throw new Exception('Document ID is required');
    }
    
    try {
        $stmt = $pdo->prepare("
            SELECT d.*, dc.category_name
            FROM editable_documents d
            LEFT JOIN document_categories dc ON d.category_id = dc.id
            WHERE d.id = ?
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
        
    } catch (Exception $e) {
        throw new Exception('Failed to retrieve document: ' . $e->getMessage());
    }
}

/**
 * Get client permissions for documents
 */
function handleGetPermissions() {
    global $pdo;
    
    try {
        $stmt = $pdo->prepare("
            SELECT u.id as client_id, u.first_name, u.last_name, u.email,
                   d.id as document_id, d.title as document_title,
                   cdp.id as permission_id, cdp.can_download, cdp.expires_at,
                   cdp.is_active, cdp.granted_at
            FROM users u
            CROSS JOIN editable_documents d
            LEFT JOIN client_document_permissions cdp ON u.id = cdp.client_id AND d.id = cdp.document_id
            WHERE u.role = 'client' AND d.is_visible_to_clients = 1
            ORDER BY u.last_name, u.first_name, d.title
        ");
        
        $stmt->execute();
        $permissions = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Group by client
        $groupedPermissions = [];
        foreach ($permissions as $permission) {
            $clientId = $permission['client_id'];
            if (!isset($groupedPermissions[$clientId])) {
                $groupedPermissions[$clientId] = [
                    'client_id' => $clientId,
                    'client_name' => $permission['first_name'] . ' ' . $permission['last_name'],
                    'client_email' => $permission['email'],
                    'documents' => []
                ];
            }
            $groupedPermissions[$clientId]['documents'][] = $permission;
        }
        
        echo json_encode([
            'success' => true,
            'permissions' => array_values($groupedPermissions)
        ]);
        
    } catch (Exception $e) {
        throw new Exception('Failed to retrieve permissions: ' . $e->getMessage());
    }
}

/**
 * Save client permissions
 */
function handleSavePermissions() {
    global $pdo;
    
    $clientId = $_POST['client_id'] ?? null;
    $documentId = $_POST['document_id'] ?? null;
    $canDownload = isset($_POST['can_download']) ? 1 : 0;
    $expiresAt = $_POST['expires_at'] ?? null;
    
    if (!$clientId || !$documentId) {
        throw new Exception('Client ID and Document ID are required');
    }
    
    try {
        // Check if permission already exists
        $stmt = $pdo->prepare("
            SELECT id FROM client_document_permissions 
            WHERE client_id = ? AND document_id = ?
        ");
        $stmt->execute([$clientId, $documentId]);
        $existing = $stmt->fetch();
        
        if ($existing) {
            // Update existing permission
            $stmt = $pdo->prepare("
                UPDATE client_document_permissions 
                SET can_download = ?, expires_at = ?, is_active = 1, updated_at = NOW()
                WHERE client_id = ? AND document_id = ?
            ");
            $stmt->execute([$canDownload, $expiresAt, $clientId, $documentId]);
        } else {
            // Create new permission
            $stmt = $pdo->prepare("
                INSERT INTO client_document_permissions 
                (client_id, document_id, can_download, expires_at, granted_by)
                VALUES (?, ?, ?, ?, ?)
            ");
            $stmt->execute([$clientId, $documentId, $canDownload, $expiresAt, $_SESSION['user_id']]);
        }
        
        echo json_encode([
            'success' => true,
            'message' => 'Permissions updated successfully'
        ]);
        
    } catch (Exception $e) {
        throw new Exception('Failed to save permissions: ' . $e->getMessage());
    }
}

/**
 * Get analytics data
 */
function handleGetAnalytics() {
    global $pdo;
    
    try {
        // Get overall statistics
        $stmt = $pdo->prepare("
            SELECT 
                COUNT(*) as total_documents,
                SUM(CASE WHEN is_visible_to_clients = 1 THEN 1 ELSE 0 END) as visible_documents,
                COUNT(DISTINCT cdp.client_id) as clients_with_access
            FROM editable_documents d
            LEFT JOIN client_document_permissions cdp ON d.id = cdp.document_id AND cdp.is_active = 1
        ");
        $stmt->execute();
        $stats = $stmt->fetch(PDO::FETCH_ASSOC);
        
        // Get download statistics
        $stmt = $pdo->prepare("
            SELECT COUNT(*) as total_downloads
            FROM document_download_log
            WHERE downloaded_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
        ");
        $stmt->execute();
        $downloadStats = $stmt->fetch(PDO::FETCH_ASSOC);
        
        // Get most downloaded documents
        $stmt = $pdo->prepare("
            SELECT d.title, COUNT(dl.id) as download_count
            FROM editable_documents d
            LEFT JOIN document_download_log dl ON d.id = dl.document_id
            WHERE dl.downloaded_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
            GROUP BY d.id, d.title
            ORDER BY download_count DESC
            LIMIT 5
        ");
        $stmt->execute();
        $topDocuments = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Get daily download trends (last 30 days)
        $stmt = $pdo->prepare("
            SELECT DATE(downloaded_at) as download_date, COUNT(*) as downloads
            FROM document_download_log
            WHERE downloaded_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
            GROUP BY DATE(downloaded_at)
            ORDER BY download_date
        ");
        $stmt->execute();
        $dailyTrends = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        echo json_encode([
            'success' => true,
            'analytics' => [
                'stats' => array_merge($stats, $downloadStats),
                'top_documents' => $topDocuments,
                'daily_trends' => $dailyTrends
            ]
        ]);
        
    } catch (Exception $e) {
        throw new Exception('Failed to retrieve analytics: ' . $e->getMessage());
    }
}

/**
 * Get available template variables
 */
function handleGetVariables() {
    try {
        $variables = [
            'client' => [
                '{client_name}' => 'Full name of the client',
                '{first_name}' => 'Client first name',
                '{last_name}' => 'Client last name',
                '{email}' => 'Client email address',
                '{phone}' => 'Client phone number',
                '{address}' => 'Client address',
                '{reference_number}' => 'Unique client reference number'
            ],
            'loan' => [
                '{loan_amount}' => 'Requested loan amount',
                '{loan_term}' => 'Loan term in months',
                '{interest_rate}' => 'Interest rate percentage',
                '{monthly_payment}' => 'Monthly payment amount',
                '{total_amount}' => 'Total amount to be repaid',
                '{application_date}' => 'Date of application submission',
                '{approval_date}' => 'Date of loan approval'
            ],
            'company' => [
                '{company_name}' => 'Company name',
                '{company_address}' => 'Company address',
                '{company_phone}' => 'Company phone number',
                '{company_email}' => 'Company email address',
                '{company_website}' => 'Company website URL',
                '{support_email}' => 'Support email address'
            ],
            'system' => [
                '{current_date}' => 'Current date',
                '{current_time}' => 'Current time',
                '{login_url}' => 'Client portal login URL',
                '{document_url}' => 'Direct document access URL'
            ]
        ];
        
        echo json_encode([
            'success' => true,
            'variables' => $variables
        ]);
        
    } catch (Exception $e) {
        throw new Exception('Failed to retrieve variables: ' . $e->getMessage());
    }
}

?>