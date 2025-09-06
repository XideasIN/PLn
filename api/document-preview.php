<?php
/**
 * Document Preview API
 * LoanFlow Personal Loan Management System
 * 
 * Handles document preview requests for client document viewer
 */

session_start();
require_once '../includes/config.php';
require_once '../includes/functions.php';

// Set JSON response header
header('Content-Type: application/json');

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    echo json_encode([
        'success' => false,
        'message' => 'Authentication required'
    ]);
    exit();
}

$user_id = $_SESSION['user_id'];
$action = $_POST['action'] ?? '';

try {
    switch ($action) {
        case 'preview':
            handleDocumentPreview();
            break;
            
        case 'personalize':
            handleDocumentPersonalization();
            break;
            
        default:
            echo json_encode([
                'success' => false,
                'message' => 'Invalid action'
            ]);
    }
} catch (Exception $e) {
    error_log("Document Preview API Error: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => 'An error occurred while processing your request'
    ]);
}

function handleDocumentPreview() {
    global $pdo, $user_id;
    
    $doc_id = (int)($_POST['doc_id'] ?? 0);
    
    if (!$doc_id) {
        echo json_encode([
            'success' => false,
            'message' => 'Document ID is required'
        ]);
        return;
    }
    
    // Check if user has permission to view this document
    $stmt = $pdo->prepare("
        SELECT ed.*, edc.category_name, cdp.can_download
        FROM editable_documents ed
        JOIN editable_document_categories edc ON ed.category_id = edc.id
        LEFT JOIN client_document_permissions cdp ON ed.id = cdp.document_id AND cdp.client_id = ?
        WHERE ed.id = ? AND ed.is_visible = 1 
        AND (cdp.client_id IS NOT NULL OR ed.public_access = 1)
    ");
    $stmt->execute([$user_id, $doc_id]);
    $document = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$document) {
        echo json_encode([
            'success' => false,
            'message' => 'Document not found or access denied'
        ]);
        return;
    }
    
    // Get user information for personalization
    $user_info = getUserInfo($user_id);
    
    // Personalize the document content
    $personalized_content = personalizeDocumentContent($document['content'], $user_info);
    
    // Log the preview access
    try {
        $stmt = $pdo->prepare("
            INSERT INTO document_download_logs (document_id, client_id, download_type, downloaded_at, ip_address)
            VALUES (?, ?, 'preview', NOW(), ?)
        ");
        $stmt->execute([$doc_id, $user_id, $_SERVER['REMOTE_ADDR']]);
    } catch (PDOException $e) {
        error_log("Error logging document preview: " . $e->getMessage());
    }
    
    echo json_encode([
        'success' => true,
        'content' => formatContentForPreview($personalized_content),
        'document' => [
            'id' => $document['id'],
            'title' => $document['title'],
            'category' => $document['category_name'],
            'can_download' => (bool)$document['can_download']
        ]
    ]);
}

function handleDocumentPersonalization() {
    global $pdo, $user_id;
    
    $doc_id = (int)($_POST['doc_id'] ?? 0);
    
    if (!$doc_id) {
        echo json_encode([
            'success' => false,
            'message' => 'Document ID is required'
        ]);
        return;
    }
    
    // Check permissions and get document
    $stmt = $pdo->prepare("
        SELECT ed.*, cdp.can_download
        FROM editable_documents ed
        LEFT JOIN client_document_permissions cdp ON ed.id = cdp.document_id AND cdp.client_id = ?
        WHERE ed.id = ? AND ed.is_visible = 1 
        AND (cdp.client_id IS NOT NULL OR ed.public_access = 1)
    ");
    $stmt->execute([$user_id, $doc_id]);
    $document = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$document) {
        echo json_encode([
            'success' => false,
            'message' => 'Document not found or access denied'
        ]);
        return;
    }
    
    // Get user information
    $user_info = getUserInfo($user_id);
    
    // Personalize content
    $personalized_content = personalizeDocumentContent($document['content'], $user_info);
    
    echo json_encode([
        'success' => true,
        'content' => $personalized_content,
        'variables_used' => getUsedVariables($document['content'])
    ]);
}

function personalizeDocumentContent($content, $user_info) {
    // Get loan application data if available
    $loan_data = getLoanApplicationData($user_info['id']);
    
    // Define replacement variables
    $variables = [
        // Personal Information
        '{{client_name}}' => $user_info['full_name'] ?? ($user_info['first_name'] . ' ' . $user_info['last_name']),
        '{{first_name}}' => $user_info['first_name'] ?? '',
        '{{last_name}}' => $user_info['last_name'] ?? '',
        '{{email}}' => $user_info['email'] ?? '',
        '{{phone}}' => $user_info['phone'] ?? '',
        '{{address}}' => formatAddress($user_info),
        
        // Loan Information
        '{{loan_amount}}' => formatCurrency($loan_data['loan_amount'] ?? 0),
        '{{loan_term}}' => ($loan_data['loan_term'] ?? 0) . ' months',
        '{{interest_rate}}' => ($loan_data['interest_rate'] ?? 0) . '%',
        '{{monthly_payment}}' => formatCurrency($loan_data['monthly_payment'] ?? 0),
        '{{application_id}}' => $loan_data['application_id'] ?? 'N/A',
        '{{reference_number}}' => $loan_data['reference_number'] ?? generateReferenceNumber($user_info['id']),
        
        // Company Information
        '{{company_name}}' => 'LoanFlow Financial Services',
        '{{company_address}}' => '123 Financial District, Suite 456\nNew York, NY 10001',
        '{{company_phone}}' => '(555) 123-4567',
        '{{company_email}}' => 'info@loanflow.com',
        '{{company_website}}' => 'www.loanflow.com',
        
        // System Information
        '{{current_date}}' => date('F j, Y'),
        '{{current_time}}' => date('g:i A'),
        '{{current_year}}' => date('Y'),
        '{{login_url}}' => getBaseUrl() . '/login.php',
        '{{client_portal_url}}' => getBaseUrl() . '/client/',
        
        // Document Information
        '{{document_date}}' => date('F j, Y'),
        '{{expiry_date}}' => date('F j, Y', strtotime('+1 year')),
    ];
    
    // Apply replacements
    $personalized_content = $content;
    foreach ($variables as $variable => $value) {
        $personalized_content = str_replace($variable, $value, $personalized_content);
    }
    
    return $personalized_content;
}

function formatContentForPreview($content) {
    // Convert markdown-like syntax to HTML
    $html_content = $content;
    
    // Headers
    $html_content = preg_replace('/^### (.+)$/m', '<h3>$1</h3>', $html_content);
    $html_content = preg_replace('/^## (.+)$/m', '<h2>$1</h2>', $html_content);
    $html_content = preg_replace('/^# (.+)$/m', '<h1>$1</h1>', $html_content);
    
    // Bold and italic
    $html_content = preg_replace('/\*\*(.+?)\*\*/', '<strong>$1</strong>', $html_content);
    $html_content = preg_replace('/\*(.+?)\*/', '<em>$1</em>', $html_content);
    
    // Lists
    $html_content = preg_replace('/^\* (.+)$/m', '<li>$1</li>', $html_content);
    $html_content = preg_replace('/^\d+\. (.+)$/m', '<li>$1</li>', $html_content);
    
    // Wrap consecutive list items
    $html_content = preg_replace('/(<li>.*<\/li>\s*)+/s', '<ul>$0</ul>', $html_content);
    
    // Paragraphs
    $html_content = preg_replace('/\n\n/', '</p><p>', $html_content);
    $html_content = '<p>' . $html_content . '</p>';
    
    // Clean up empty paragraphs
    $html_content = preg_replace('/<p><\/p>/', '', $html_content);
    $html_content = preg_replace('/<p>\s*<(h[1-6]|ul|ol)/', '<$1', $html_content);
    $html_content = preg_replace('/<\/(h[1-6]|ul|ol)>\s*<\/p>/', '</$1>', $html_content);
    
    // Line breaks
    $html_content = nl2br($html_content);
    
    return $html_content;
}

function getLoanApplicationData($user_id) {
    global $pdo;
    
    try {
        $stmt = $pdo->prepare("
            SELECT la.*, 
                   CONCAT('LF-', YEAR(la.created_at), '-', LPAD(la.id, 4, '0')) as reference_number
            FROM loan_applications la
            WHERE la.user_id = ?
            ORDER BY la.created_at DESC
            LIMIT 1
        ");
        $stmt->execute([$user_id]);
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: [];
    } catch (PDOException $e) {
        error_log("Error fetching loan application data: " . $e->getMessage());
        return [];
    }
}

function formatAddress($user_info) {
    $address_parts = [];
    
    if (!empty($user_info['address'])) {
        $address_parts[] = $user_info['address'];
    }
    if (!empty($user_info['city'])) {
        $address_parts[] = $user_info['city'];
    }
    if (!empty($user_info['state'])) {
        $address_parts[] = $user_info['state'];
    }
    if (!empty($user_info['zip_code'])) {
        $address_parts[] = $user_info['zip_code'];
    }
    
    return implode(', ', $address_parts);
}

function formatCurrency($amount) {
    return '$' . number_format((float)$amount, 2);
}

function generateReferenceNumber($user_id) {
    return 'LF-' . date('Y') . '-' . str_pad($user_id, 4, '0', STR_PAD_LEFT);
}

function getUsedVariables($content) {
    preg_match_all('/\{\{([^}]+)\}\}/', $content, $matches);
    return array_unique($matches[1]);
}

function getBaseUrl() {
    $protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http';
    $host = $_SERVER['HTTP_HOST'];
    $path = dirname($_SERVER['SCRIPT_NAME']);
    return $protocol . '://' . $host . rtrim($path, '/');
}

function getUserInfo($user_id) {
    global $pdo;
    
    try {
        $stmt = $pdo->prepare("
            SELECT u.*, up.*
            FROM users u
            LEFT JOIN user_profiles up ON u.id = up.user_id
            WHERE u.id = ?
        ");
        $stmt->execute([$user_id]);
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: [];
    } catch (PDOException $e) {
        error_log("Error fetching user info: " . $e->getMessage());
        return [];
    }
}
?>