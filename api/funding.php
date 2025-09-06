<?php
/**
 * Funding API Endpoint
 * LoanFlow Personal Loan Management System
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

require_once '../includes/config.php';
require_once '../includes/functions.php';
require_once '../includes/funding_functions.php';
require_once '../includes/auth.php';

// Check authentication
if (!isLoggedIn()) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Authentication required']);
    exit();
}

// Check admin privileges for most operations
if (!isAdmin() && $_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Admin privileges required']);
    exit();
}

try {
    $method = $_SERVER['REQUEST_METHOD'];
    $action = $_GET['action'] ?? '';
    
    switch ($method) {
        case 'GET':
            handleGetRequest($action);
            break;
            
        case 'POST':
            handlePostRequest($action);
            break;
            
        case 'PUT':
            handlePutRequest($action);
            break;
            
        case 'DELETE':
            handleDeleteRequest($action);
            break;
            
        default:
            http_response_code(405);
            echo json_encode(['success' => false, 'message' => 'Method not allowed']);
            break;
    }
    
} catch (Exception $e) {
    error_log("Funding API error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Internal server error']);
}

/**
 * Handle GET requests
 */
function handleGetRequest($action) {
    switch ($action) {
        case 'status':
            getFundingStatus();
            break;
            
        case 'statistics':
            getFundingStatistics();
            break;
            
        case 'timeline':
            getFundingTimeline();
            break;
            
        case 'applications':
            getFundingApplications();
            break;
            
        case 'documents':
            getFundingDocuments();
            break;
            
        case 'settings':
            getFundingSettings();
            break;
            
        case 'check_eligibility':
            checkFundingEligibility();
            break;
            
        default:
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Invalid action']);
            break;
    }
}

/**
 * Handle POST requests
 */
function handlePostRequest($action) {
    $input = json_decode(file_get_contents('php://input'), true);
    
    switch ($action) {
        case 'initiate':
            initiateFundingProcess($input);
            break;
            
        case 'complete':
            completeFundingProcess($input);
            break;
            
        case 'cancel':
            cancelFundingProcess($input);
            break;
            
        case 'add_timeline_event':
            addTimelineEvent($input);
            break;
            
        case 'send_notification':
            sendFundingNotification($input);
            break;
            
        case 'upload_document':
            uploadFundingDocument($input);
            break;
            
        default:
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Invalid action']);
            break;
    }
}

/**
 * Handle PUT requests
 */
function handlePutRequest($action) {
    $input = json_decode(file_get_contents('php://input'), true);
    
    switch ($action) {
        case 'update_settings':
            updateFundingSettings($input);
            break;
            
        case 'update_status':
            updateFundingStatus($input);
            break;
            
        default:
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Invalid action']);
            break;
    }
}

/**
 * Handle DELETE requests
 */
function handleDeleteRequest($action) {
    switch ($action) {
        case 'document':
            deleteFundingDocument();
            break;
            
        default:
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Invalid action']);
            break;
    }
}

/**
 * Get funding status for application
 */
function getFundingStatus() {
    $application_id = $_GET['application_id'] ?? null;
    
    if (!$application_id) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Application ID required']);
        return;
    }
    
    $status = getFundingStatus($application_id);
    
    if ($status) {
        echo json_encode(['success' => true, 'data' => $status]);
    } else {
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'Application not found']);
    }
}

/**
 * Get funding statistics
 */
function getFundingStatistics() {
    $date_from = $_GET['date_from'] ?? null;
    $date_to = $_GET['date_to'] ?? null;
    
    $stats = getFundingStatistics($date_from, $date_to);
    echo json_encode(['success' => true, 'data' => $stats]);
}

/**
 * Get funding timeline
 */
function getFundingTimeline() {
    $application_id = $_GET['application_id'] ?? null;
    
    if (!$application_id) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Application ID required']);
        return;
    }
    
    $timeline = getFundingTimeline($application_id);
    echo json_encode(['success' => true, 'data' => $timeline]);
}

/**
 * Get funding applications
 */
function getFundingApplications() {
    try {
        $db = getDB();
        
        $status_filter = $_GET['status'] ?? '';
        $search = $_GET['search'] ?? '';
        $page = max(1, intval($_GET['page'] ?? 1));
        $limit = min(100, max(10, intval($_GET['limit'] ?? 20)));
        $offset = ($page - 1) * $limit;
        
        $where_conditions = [];
        $params = [];
        
        // Status filter
        if ($status_filter) {
            $where_conditions[] = "la.application_status = ?";
            $params[] = $status_filter;
        } else {
            $where_conditions[] = "la.application_status IN ('approved', 'funding', 'funded')";
        }
        
        // Search filter
        if ($search) {
            $where_conditions[] = "(u.first_name LIKE ? OR u.last_name LIKE ? OR u.email LIKE ? OR u.reference_number LIKE ?)";
            $search_param = "%$search%";
            $params = array_merge($params, [$search_param, $search_param, $search_param, $search_param]);
        }
        
        $where_clause = implode(' AND ', $where_conditions);
        
        // Get total count
        $count_stmt = $db->prepare("
            SELECT COUNT(*) as total
            FROM loan_applications la
            JOIN users u ON la.user_id = u.id
            WHERE $where_clause
        ");
        $count_stmt->execute($params);
        $total = $count_stmt->fetch()['total'];
        
        // Get applications
        $stmt = $db->prepare("
            SELECT la.*, 
                   u.first_name, u.last_name, u.email, u.reference_number,
                   bd.account_number, bd.bank_name, bd.verified as bank_verified
            FROM loan_applications la
            JOIN users u ON la.user_id = u.id
            LEFT JOIN bank_details bd ON u.id = bd.user_id AND bd.verified = 1
            WHERE $where_clause
            ORDER BY 
                CASE 
                    WHEN la.application_status = 'funding' THEN 1
                    WHEN la.application_status = 'approved' THEN 2
                    WHEN la.application_status = 'funded' THEN 3
                    ELSE 4
                END,
                la.updated_at DESC
            LIMIT ? OFFSET ?
        ");
        
        $params[] = $limit;
        $params[] = $offset;
        $stmt->execute($params);
        $applications = $stmt->fetchAll();
        
        echo json_encode([
            'success' => true,
            'data' => [
                'applications' => $applications,
                'pagination' => [
                    'page' => $page,
                    'limit' => $limit,
                    'total' => $total,
                    'pages' => ceil($total / $limit)
                ]
            ]
        ]);
        
    } catch (Exception $e) {
        error_log("Get funding applications failed: " . $e->getMessage());
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Failed to fetch applications']);
    }
}

/**
 * Get funding documents
 */
function getFundingDocuments() {
    $application_id = $_GET['application_id'] ?? null;
    
    if (!$application_id) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Application ID required']);
        return;
    }
    
    $documents = getFundingDocuments($application_id);
    echo json_encode(['success' => true, 'data' => $documents]);
}

/**
 * Get funding settings
 */
function getFundingSettings() {
    try {
        $db = getDB();
        $stmt = $db->prepare("
            SELECT setting_key, setting_value, setting_type, description
            FROM funding_settings
            WHERE is_active = 1
            ORDER BY setting_key
        ");
        $stmt->execute();
        $settings = $stmt->fetchAll();
        
        echo json_encode(['success' => true, 'data' => $settings]);
        
    } catch (Exception $e) {
        error_log("Get funding settings failed: " . $e->getMessage());
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Failed to fetch settings']);
    }
}

/**
 * Check funding eligibility
 */
function checkFundingEligibility() {
    $application_id = $_GET['application_id'] ?? null;
    
    if (!$application_id) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Application ID required']);
        return;
    }
    
    $eligibility = isFundingAllowed($application_id);
    echo json_encode(['success' => true, 'data' => $eligibility]);
}

/**
 * Initiate funding process
 */
function initiateFundingProcess($input) {
    $application_id = $input['application_id'] ?? null;
    $initiated_by = $_SESSION['user_id'] ?? null;
    
    if (!$application_id) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Application ID required']);
        return;
    }
    
    // Check eligibility first
    $eligibility = isFundingAllowed($application_id);
    if (!$eligibility['allowed']) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => $eligibility['reason']]);
        return;
    }
    
    $result = initiateFunding($application_id, $initiated_by);
    
    if ($result) {
        // Log the action
        logFundingAction($application_id, 'funding_initiated', null, ['initiated_by' => $initiated_by], $initiated_by);
        
        echo json_encode(['success' => true, 'message' => 'Funding process initiated successfully']);
    } else {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Failed to initiate funding process']);
    }
}

/**
 * Complete funding process
 */
function completeFundingProcess($input) {
    $application_id = $input['application_id'] ?? null;
    $funding_amount = $input['funding_amount'] ?? null;
    $funding_reference = $input['funding_reference'] ?? null;
    $funding_notes = $input['funding_notes'] ?? null;
    $funded_by = $_SESSION['user_id'] ?? null;
    
    if (!$application_id || !$funding_amount || !$funding_reference) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Application ID, funding amount, and reference are required']);
        return;
    }
    
    $result = completeFunding($application_id, $funding_amount, $funding_reference, $funding_notes, $funded_by);
    
    if ($result) {
        // Log the action
        logFundingAction($application_id, 'funding_completed', null, [
            'funding_amount' => $funding_amount,
            'funding_reference' => $funding_reference,
            'funded_by' => $funded_by
        ], $funded_by);
        
        echo json_encode(['success' => true, 'message' => 'Funding completed successfully']);
    } else {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Failed to complete funding process']);
    }
}

/**
 * Cancel funding process
 */
function cancelFundingProcess($input) {
    $application_id = $input['application_id'] ?? null;
    $cancel_reason = $input['cancel_reason'] ?? null;
    $cancelled_by = $_SESSION['user_id'] ?? null;
    
    if (!$application_id || !$cancel_reason) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Application ID and cancel reason are required']);
        return;
    }
    
    $result = cancelFunding($application_id, $cancel_reason, $cancelled_by);
    
    if ($result) {
        // Log the action
        logFundingAction($application_id, 'funding_cancelled', null, [
            'cancel_reason' => $cancel_reason,
            'cancelled_by' => $cancelled_by
        ], $cancelled_by);
        
        echo json_encode(['success' => true, 'message' => 'Funding process cancelled successfully']);
    } else {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Failed to cancel funding process']);
    }
}

/**
 * Add timeline event
 */
function addTimelineEvent($input) {
    $application_id = $input['application_id'] ?? null;
    $event_type = $input['event_type'] ?? null;
    $event_title = $input['event_title'] ?? null;
    $event_description = $input['event_description'] ?? null;
    $event_data = $input['event_data'] ?? null;
    $created_by = $_SESSION['user_id'] ?? null;
    
    if (!$application_id || !$event_type || !$event_title) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Application ID, event type, and title are required']);
        return;
    }
    
    $result = addFundingTimelineEvent($application_id, $event_type, $event_title, $event_description, $event_data, $created_by);
    
    if ($result) {
        echo json_encode(['success' => true, 'message' => 'Timeline event added successfully']);
    } else {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Failed to add timeline event']);
    }
}

/**
 * Send funding notification
 */
function sendFundingNotification($input) {
    $user_id = $input['user_id'] ?? null;
    $notification_type = $input['notification_type'] ?? null;
    $data = $input['data'] ?? [];
    
    if (!$user_id || !$notification_type) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'User ID and notification type are required']);
        return;
    }
    
    $result = sendFundingNotification($user_id, $notification_type, $data);
    
    if ($result) {
        echo json_encode(['success' => true, 'message' => 'Notification sent successfully']);
    } else {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Failed to send notification']);
    }
}

/**
 * Update funding settings
 */
function updateFundingSettings($input) {
    $settings = $input['settings'] ?? [];
    
    if (empty($settings)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Settings data required']);
        return;
    }
    
    $updated = 0;
    $failed = 0;
    
    foreach ($settings as $key => $value) {
        if (updateFundingSetting($key, $value)) {
            $updated++;
        } else {
            $failed++;
        }
    }
    
    if ($failed === 0) {
        echo json_encode(['success' => true, 'message' => "Updated $updated settings successfully"]);
    } else {
        echo json_encode(['success' => false, 'message' => "Updated $updated settings, $failed failed"]);
    }
}

/**
 * Update funding status
 */
function updateFundingStatus($input) {
    $application_id = $input['application_id'] ?? null;
    $status = $input['status'] ?? null;
    $notes = $input['notes'] ?? null;
    $updated_by = $_SESSION['user_id'] ?? null;
    
    if (!$application_id || !$status) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Application ID and status are required']);
        return;
    }
    
    try {
        $db = getDB();
        $stmt = $db->prepare("
            UPDATE loan_applications 
            SET funding_status = ?, funding_notes = ?, updated_at = NOW()
            WHERE id = ?
        ");
        
        $result = $stmt->execute([$status, $notes, $application_id]);
        
        if ($result) {
            // Add timeline event
            addFundingTimelineEvent(
                $application_id,
                'status_updated',
                'Funding Status Updated',
                "Status changed to: $status" . ($notes ? " - $notes" : ''),
                ['new_status' => $status, 'notes' => $notes],
                $updated_by
            );
            
            echo json_encode(['success' => true, 'message' => 'Status updated successfully']);
        } else {
            http_response_code(500);
            echo json_encode(['success' => false, 'message' => 'Failed to update status']);
        }
        
    } catch (Exception $e) {
        error_log("Update funding status failed: " . $e->getMessage());
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Failed to update status']);
    }
}

/**
 * Upload funding document
 */
function uploadFundingDocument($input) {
    // This would typically handle file uploads
    // For now, just return a placeholder response
    echo json_encode(['success' => false, 'message' => 'Document upload not implemented in this endpoint']);
}

/**
 * Delete funding document
 */
function deleteFundingDocument() {
    $document_id = $_GET['document_id'] ?? null;
    
    if (!$document_id) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Document ID required']);
        return;
    }
    
    try {
        $db = getDB();
        $stmt = $db->prepare("DELETE FROM funding_documents WHERE id = ?");
        $result = $stmt->execute([$document_id]);
        
        if ($result && $stmt->rowCount() > 0) {
            echo json_encode(['success' => true, 'message' => 'Document deleted successfully']);
        } else {
            http_response_code(404);
            echo json_encode(['success' => false, 'message' => 'Document not found']);
        }
        
    } catch (Exception $e) {
        error_log("Delete funding document failed: " . $e->getMessage());
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Failed to delete document']);
    }
}

?>