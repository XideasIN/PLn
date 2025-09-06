<?php
/**
 * Automated Email System API
 * LoanFlow Personal Loan Management System
 * 
 * Handles email operations including:
 * - Template management
 * - Email queue processing
 * - Automated triggers
 * - Bulk email campaigns
 * - Email analytics
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
$rate_limit_key = 'email_api_' . ($_SESSION['user_id'] ?? $_SERVER['REMOTE_ADDR']);
if (!checkRateLimit($rate_limit_key, 60, 50)) { // 50 requests per minute
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
        case 'get_templates':
            handleGetTemplates();
            break;
            
        case 'save_template':
            handleSaveTemplate();
            break;
            
        case 'delete_template':
            handleDeleteTemplate();
            break;
            
        case 'send_email':
            handleSendEmail();
            break;
            
        case 'send_bulk_email':
            handleSendBulkEmail();
            break;
            
        case 'get_email_queue':
            handleGetEmailQueue();
            break;
            
        case 'process_queue':
            handleProcessQueue();
            break;
            
        case 'get_email_analytics':
            handleGetEmailAnalytics();
            break;
            
        case 'get_delivery_log':
            handleGetDeliveryLog();
            break;
            
        case 'schedule_email':
            handleScheduleEmail();
            break;
            
        case 'cancel_scheduled_email':
            handleCancelScheduledEmail();
            break;
            
        case 'test_email_settings':
            handleTestEmailSettings();
            break;
            
        case 'get_personalization_variables':
            handleGetPersonalizationVariables();
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
 * Get email templates
 */
function handleGetTemplates() {
    global $pdo;
    
    // Admin authorization check for management operations
    if (!isAdmin()) {
        throw new Exception('Admin access required');
    }
    
    $stmt = $pdo->query("
        SELECT 
            id, name, subject, body_template, trigger_type, trigger_condition,
            delay_hours, is_active, created_at, updated_at,
            (SELECT COUNT(*) FROM email_queue WHERE template_id = ewt.id) as queue_count,
            (SELECT COUNT(*) FROM email_delivery_log WHERE template_id = ewt.id AND status = 'sent') as sent_count
        FROM email_workflow_templates ewt
        ORDER BY name
    ");
    $templates = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo json_encode([
        'success' => true,
        'templates' => $templates
    ]);
}

/**
 * Save email template
 */
function handleSaveTemplate() {
    global $pdo;
    
    // Admin authorization check
    if (!isAdmin()) {
        throw new Exception('Admin access required');
    }
    
    $templateId = intval($_POST['template_id'] ?? 0);
    $name = trim($_POST['name'] ?? '');
    $subject = trim($_POST['subject'] ?? '');
    $bodyTemplate = $_POST['body_template'] ?? '';
    $triggerType = $_POST['trigger_type'] ?? '';
    $triggerCondition = $_POST['trigger_condition'] ?? '';
    $delayHours = intval($_POST['delay_hours'] ?? 0);
    $isActive = isset($_POST['is_active']) ? 1 : 0;
    
    // Validation
    if (empty($name) || empty($subject) || empty($bodyTemplate)) {
        throw new Exception('Name, subject, and body template are required');
    }
    
    if (!in_array($triggerType, ['step_before', 'step_after', 'time_based', 'manual', 'bulk'])) {
        throw new Exception('Invalid trigger type');
    }
    
    $pdo->beginTransaction();
    
    try {
        if ($templateId > 0) {
            // Update existing template
            $stmt = $pdo->prepare("
                UPDATE email_workflow_templates 
                SET name = ?, subject = ?, body_template = ?, trigger_type = ?, 
                    trigger_condition = ?, delay_hours = ?, is_active = ?, updated_at = NOW()
                WHERE id = ?
            ");
            $stmt->execute([$name, $subject, $bodyTemplate, $triggerType, $triggerCondition, $delayHours, $isActive, $templateId]);
        } else {
            // Create new template
            $stmt = $pdo->prepare("
                INSERT INTO email_workflow_templates 
                (name, subject, body_template, trigger_type, trigger_condition, delay_hours, is_active)
                VALUES (?, ?, ?, ?, ?, ?, ?)
            ");
            $stmt->execute([$name, $subject, $bodyTemplate, $triggerType, $triggerCondition, $delayHours, $isActive]);
            $templateId = $pdo->lastInsertId();
        }
        
        $pdo->commit();
        
        echo json_encode([
            'success' => true,
            'message' => 'Template saved successfully',
            'template_id' => $templateId
        ]);
        
    } catch (Exception $e) {
        $pdo->rollBack();
        throw $e;
    }
}

/**
 * Delete email template
 */
function handleDeleteTemplate() {
    global $pdo;
    
    // Admin authorization check
    if (!isAdmin()) {
        throw new Exception('Admin access required');
    }
    
    $templateId = intval($_POST['template_id'] ?? 0);
    if (!$templateId) {
        throw new Exception('Template ID is required');
    }
    
    // Check if template is in use
    $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM email_queue WHERE template_id = ? AND status = 'pending'");
    $stmt->execute([$templateId]);
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($result['count'] > 0) {
        throw new Exception('Cannot delete template with pending emails in queue');
    }
    
    $stmt = $pdo->prepare("DELETE FROM email_workflow_templates WHERE id = ?");
    $stmt->execute([$templateId]);
    
    echo json_encode([
        'success' => true,
        'message' => 'Template deleted successfully'
    ]);
}

/**
 * Send individual email
 */
function handleSendEmail() {
    global $pdo;
    
    $templateId = intval($_POST['template_id'] ?? 0);
    $userId = intval($_POST['user_id'] ?? 0);
    $customSubject = $_POST['custom_subject'] ?? '';
    $customBody = $_POST['custom_body'] ?? '';
    $sendImmediately = isset($_POST['send_immediately']);
    
    if (!$templateId && (!$customSubject || !$customBody)) {
        throw new Exception('Template ID or custom subject/body is required');
    }
    
    if (!$userId) {
        throw new Exception('User ID is required');
    }
    
    // Check if user exists
    $stmt = $pdo->prepare("SELECT email, first_name, last_name FROM users WHERE id = ?");
    $stmt->execute([$userId]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$user) {
        throw new Exception('User not found');
    }
    
    // Queue the email
    $emailId = queueEmail($templateId, $userId, $customSubject, $customBody, $sendImmediately ? 0 : null);
    
    if ($sendImmediately) {
        // Process immediately
        $result = processEmailQueue(1, $emailId);
        
        echo json_encode([
            'success' => true,
            'message' => 'Email sent successfully',
            'email_id' => $emailId,
            'processed' => $result['processed']
        ]);
    } else {
        echo json_encode([
            'success' => true,
            'message' => 'Email queued successfully',
            'email_id' => $emailId
        ]);
    }
}

/**
 * Send bulk email
 */
function handleSendBulkEmail() {
    global $pdo;
    
    // Admin authorization check
    if (!isAdmin()) {
        throw new Exception('Admin access required');
    }
    
    $templateId = intval($_POST['template_id'] ?? 0);
    $userIds = $_POST['user_ids'] ?? [];
    $subject = $_POST['subject'] ?? '';
    $body = $_POST['body'] ?? '';
    $sendImmediately = isset($_POST['send_immediately']);
    
    if (!$templateId && (!$subject || !$body)) {
        throw new Exception('Template ID or custom subject/body is required');
    }
    
    if (empty($userIds)) {
        throw new Exception('At least one user must be selected');
    }
    
    // Create bulk campaign
    $stmt = $pdo->prepare("
        INSERT INTO bulk_email_campaigns (name, template_id, subject, body, total_recipients, created_by)
        VALUES (?, ?, ?, ?, ?, ?)
    ");
    $campaignName = 'Bulk Email - ' . date('Y-m-d H:i:s');
    $stmt->execute([$campaignName, $templateId, $subject, $body, count($userIds), $_SESSION['user_id']]);
    $campaignId = $pdo->lastInsertId();
    
    // Queue emails for each user
    $queuedCount = 0;
    foreach ($userIds as $userId) {
        $userId = intval($userId);
        if ($userId > 0) {
            $emailId = queueEmail($templateId, $userId, $subject, $body, $sendImmediately ? 0 : null, $campaignId);
            if ($emailId) {
                $queuedCount++;
            }
        }
    }
    
    // Update campaign stats
    $stmt = $pdo->prepare("UPDATE bulk_email_campaigns SET queued_count = ? WHERE id = ?");
    $stmt->execute([$queuedCount, $campaignId]);
    
    if ($sendImmediately) {
        // Process immediately
        $result = processEmailQueue($queuedCount);
        
        echo json_encode([
            'success' => true,
            'message' => "Bulk email sent to {$result['processed']} recipients",
            'campaign_id' => $campaignId,
            'queued' => $queuedCount,
            'processed' => $result['processed']
        ]);
    } else {
        echo json_encode([
            'success' => true,
            'message' => "Bulk email queued for {$queuedCount} recipients",
            'campaign_id' => $campaignId,
            'queued' => $queuedCount
        ]);
    }
}

/**
 * Get email queue status
 */
function handleGetEmailQueue() {
    global $pdo;
    
    // Admin authorization check
    if (!isAdmin()) {
        throw new Exception('Admin access required');
    }
    
    $limit = intval($_POST['limit'] ?? 50);
    $offset = intval($_POST['offset'] ?? 0);
    $status = $_POST['status'] ?? 'all';
    
    $whereClause = '';
    $params = [];
    
    if ($status !== 'all') {
        $whereClause = 'WHERE eq.status = ?';
        $params[] = $status;
    }
    
    $stmt = $pdo->prepare("
        SELECT 
            eq.id, eq.template_id, eq.user_id, eq.subject, eq.status, 
            eq.scheduled_at, eq.created_at, eq.attempts,
            u.first_name, u.last_name, u.email,
            ewt.name as template_name,
            bec.name as campaign_name
        FROM email_queue eq
        LEFT JOIN users u ON eq.user_id = u.id
        LEFT JOIN email_workflow_templates ewt ON eq.template_id = ewt.id
        LEFT JOIN bulk_email_campaigns bec ON eq.campaign_id = bec.id
        {$whereClause}
        ORDER BY eq.created_at DESC
        LIMIT ? OFFSET ?
    ");
    $params[] = $limit;
    $params[] = $offset;
    $stmt->execute($params);
    $queue = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Get total count
    $countStmt = $pdo->prepare("SELECT COUNT(*) as total FROM email_queue eq {$whereClause}");
    $countParams = array_slice($params, 0, -2); // Remove limit and offset
    $countStmt->execute($countParams);
    $total = $countStmt->fetch(PDO::FETCH_ASSOC)['total'];
    
    echo json_encode([
        'success' => true,
        'queue' => $queue,
        'total' => $total,
        'limit' => $limit,
        'offset' => $offset
    ]);
}

/**
 * Process email queue
 */
function handleProcessQueue() {
    global $pdo;
    
    // Admin authorization check
    if (!isAdmin()) {
        throw new Exception('Admin access required');
    }
    
    $limit = intval($_POST['limit'] ?? 10);
    
    $result = processEmailQueue($limit);
    
    echo json_encode([
        'success' => true,
        'message' => "Processed {$result['processed']} emails",
        'processed' => $result['processed'],
        'failed' => $result['failed'],
        'details' => $result['details']
    ]);
}

/**
 * Get email analytics
 */
function handleGetEmailAnalytics() {
    global $pdo;
    
    // Admin authorization check
    if (!isAdmin()) {
        throw new Exception('Admin access required');
    }
    
    // Email stats by status
    $stmt = $pdo->query("
        SELECT status, COUNT(*) as count
        FROM email_delivery_log
        WHERE sent_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
        GROUP BY status
    ");
    $statusStats = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Daily email volume (last 30 days)
    $stmt = $pdo->query("
        SELECT 
            DATE(sent_at) as date,
            COUNT(*) as total_sent,
            SUM(CASE WHEN status = 'sent' THEN 1 ELSE 0 END) as successful,
            SUM(CASE WHEN status = 'failed' THEN 1 ELSE 0 END) as failed
        FROM email_delivery_log
        WHERE sent_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
        GROUP BY DATE(sent_at)
        ORDER BY date
    ");
    $dailyVolume = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Template performance
    $stmt = $pdo->query("
        SELECT 
            ewt.name,
            COUNT(edl.id) as total_sent,
            SUM(CASE WHEN edl.status = 'sent' THEN 1 ELSE 0 END) as successful,
            ROUND(SUM(CASE WHEN edl.status = 'sent' THEN 1 ELSE 0 END) * 100.0 / COUNT(edl.id), 2) as success_rate
        FROM email_workflow_templates ewt
        LEFT JOIN email_delivery_log edl ON ewt.id = edl.template_id
        WHERE edl.sent_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
        GROUP BY ewt.id, ewt.name
        HAVING total_sent > 0
        ORDER BY total_sent DESC
        LIMIT 10
    ");
    $templatePerformance = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Queue statistics
    $stmt = $pdo->query("
        SELECT 
            status,
            COUNT(*) as count,
            MIN(created_at) as oldest_email
        FROM email_queue
        GROUP BY status
    ");
    $queueStats = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo json_encode([
        'success' => true,
        'analytics' => [
            'status_stats' => $statusStats,
            'daily_volume' => $dailyVolume,
            'template_performance' => $templatePerformance,
            'queue_stats' => $queueStats
        ]
    ]);
}

/**
 * Get delivery log
 */
function handleGetDeliveryLog() {
    global $pdo;
    
    // Admin authorization check
    if (!isAdmin()) {
        throw new Exception('Admin access required');
    }
    
    $limit = intval($_POST['limit'] ?? 50);
    $offset = intval($_POST['offset'] ?? 0);
    $status = $_POST['status'] ?? 'all';
    $templateId = intval($_POST['template_id'] ?? 0);
    
    $whereConditions = [];
    $params = [];
    
    if ($status !== 'all') {
        $whereConditions[] = 'edl.status = ?';
        $params[] = $status;
    }
    
    if ($templateId > 0) {
        $whereConditions[] = 'edl.template_id = ?';
        $params[] = $templateId;
    }
    
    $whereClause = !empty($whereConditions) ? 'WHERE ' . implode(' AND ', $whereConditions) : '';
    
    $stmt = $pdo->prepare("
        SELECT 
            edl.id, edl.template_id, edl.user_id, edl.subject, edl.status,
            edl.sent_at, edl.error_message, edl.attempts,
            u.first_name, u.last_name, u.email,
            ewt.name as template_name
        FROM email_delivery_log edl
        LEFT JOIN users u ON edl.user_id = u.id
        LEFT JOIN email_workflow_templates ewt ON edl.template_id = ewt.id
        {$whereClause}
        ORDER BY edl.sent_at DESC
        LIMIT ? OFFSET ?
    ");
    $params[] = $limit;
    $params[] = $offset;
    $stmt->execute($params);
    $log = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Get total count
    $countStmt = $pdo->prepare("SELECT COUNT(*) as total FROM email_delivery_log edl {$whereClause}");
    $countParams = array_slice($params, 0, -2); // Remove limit and offset
    $countStmt->execute($countParams);
    $total = $countStmt->fetch(PDO::FETCH_ASSOC)['total'];
    
    echo json_encode([
        'success' => true,
        'log' => $log,
        'total' => $total,
        'limit' => $limit,
        'offset' => $offset
    ]);
}

/**
 * Schedule email for later delivery
 */
function handleScheduleEmail() {
    global $pdo;
    
    $templateId = intval($_POST['template_id'] ?? 0);
    $userId = intval($_POST['user_id'] ?? 0);
    $scheduledAt = $_POST['scheduled_at'] ?? '';
    $customSubject = $_POST['custom_subject'] ?? '';
    $customBody = $_POST['custom_body'] ?? '';
    
    if (!$templateId && (!$customSubject || !$customBody)) {
        throw new Exception('Template ID or custom subject/body is required');
    }
    
    if (!$userId) {
        throw new Exception('User ID is required');
    }
    
    if (!$scheduledAt) {
        throw new Exception('Scheduled time is required');
    }
    
    // Validate scheduled time is in the future
    $scheduledTimestamp = strtotime($scheduledAt);
    if ($scheduledTimestamp <= time()) {
        throw new Exception('Scheduled time must be in the future');
    }
    
    // Queue the email with scheduled time
    $emailId = queueEmail($templateId, $userId, $customSubject, $customBody, $scheduledAt);
    
    echo json_encode([
        'success' => true,
        'message' => 'Email scheduled successfully',
        'email_id' => $emailId,
        'scheduled_at' => $scheduledAt
    ]);
}

/**
 * Cancel scheduled email
 */
function handleCancelScheduledEmail() {
    global $pdo;
    
    $emailId = intval($_POST['email_id'] ?? 0);
    if (!$emailId) {
        throw new Exception('Email ID is required');
    }
    
    $stmt = $pdo->prepare("UPDATE email_queue SET status = 'cancelled' WHERE id = ? AND status = 'pending'");
    $stmt->execute([$emailId]);
    
    if ($stmt->rowCount() > 0) {
        echo json_encode([
            'success' => true,
            'message' => 'Scheduled email cancelled successfully'
        ]);
    } else {
        throw new Exception('Email not found or cannot be cancelled');
    }
}

/**
 * Test email settings
 */
function handleTestEmailSettings() {
    global $pdo;
    
    // Admin authorization check
    if (!isAdmin()) {
        throw new Exception('Admin access required');
    }
    
    $testEmail = $_POST['test_email'] ?? $_SESSION['email'] ?? '';
    if (!$testEmail) {
        throw new Exception('Test email address is required');
    }
    
    // Send test email
    $subject = 'LoanFlow Email System Test';
    $body = 'This is a test email from the LoanFlow automated email system. If you receive this, your email configuration is working correctly.';
    
    $result = sendEmail($testEmail, $subject, $body);
    
    if ($result['success']) {
        echo json_encode([
            'success' => true,
            'message' => 'Test email sent successfully'
        ]);
    } else {
        throw new Exception('Failed to send test email: ' . $result['error']);
    }
}

/**
 * Get personalization variables
 */
function handleGetPersonalizationVariables() {
    global $pdo;
    
    $stmt = $pdo->query("
        SELECT variable_name, description, example_value, category
        FROM email_personalization_variables
        ORDER BY category, variable_name
    ");
    $variables = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Group by category
    $groupedVariables = [];
    foreach ($variables as $variable) {
        $category = $variable['category'] ?: 'General';
        if (!isset($groupedVariables[$category])) {
            $groupedVariables[$category] = [];
        }
        $groupedVariables[$category][] = $variable;
    }
    
    echo json_encode([
        'success' => true,
        'variables' => $groupedVariables
    ]);
}

/**
 * Helper Functions
 */

/**
 * Queue an email for delivery
 */
function queueEmail($templateId, $userId, $customSubject = '', $customBody = '', $scheduledAt = null, $campaignId = null) {
    global $pdo;
    
    // Get template if provided
    $template = null;
    if ($templateId > 0) {
        $stmt = $pdo->prepare("SELECT * FROM email_workflow_templates WHERE id = ? AND is_active = 1");
        $stmt->execute([$templateId]);
        $template = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$template) {
            throw new Exception('Template not found or inactive');
        }
    }
    
    // Get user data
    $userData = getUserEmailData($userId);
    if (!$userData) {
        throw new Exception('User not found');
    }
    
    // Prepare email content
    $subject = $customSubject ?: ($template ? $template['subject'] : '');
    $body = $customBody ?: ($template ? $template['body_template'] : '');
    
    // Personalize content
    $personalizedSubject = personalizeEmailContent($subject, $userData);
    $personalizedBody = personalizeEmailContent($body, $userData);
    
    // Set scheduled time
    if ($scheduledAt === null) {
        $scheduledAt = date('Y-m-d H:i:s');
    } elseif (is_numeric($scheduledAt)) {
        // Delay in hours
        $scheduledAt = date('Y-m-d H:i:s', time() + ($scheduledAt * 3600));
    }
    
    // Insert into queue
    $stmt = $pdo->prepare("
        INSERT INTO email_queue 
        (template_id, user_id, campaign_id, recipient_email, subject, body, scheduled_at, status)
        VALUES (?, ?, ?, ?, ?, ?, ?, 'pending')
    ");
    $stmt->execute([
        $templateId ?: null,
        $userId,
        $campaignId,
        $userData['email'],
        $personalizedSubject,
        $personalizedBody,
        $scheduledAt
    ]);
    
    return $pdo->lastInsertId();
}

/**
 * Process email queue
 */
function processEmailQueue($limit = 10, $specificEmailId = null) {
    global $pdo;
    
    $whereClause = "WHERE status = 'pending' AND scheduled_at <= NOW()";
    $params = [];
    
    if ($specificEmailId) {
        $whereClause .= " AND id = ?";
        $params[] = $specificEmailId;
        $limit = 1;
    }
    
    // Get emails to process
    $stmt = $pdo->prepare("
        SELECT * FROM email_queue 
        {$whereClause}
        ORDER BY scheduled_at ASC
        LIMIT ?
    ");
    $params[] = $limit;
    $stmt->execute($params);
    $emails = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    $processed = 0;
    $failed = 0;
    $details = [];
    
    foreach ($emails as $email) {
        try {
            // Mark as processing
            $updateStmt = $pdo->prepare("UPDATE email_queue SET status = 'processing' WHERE id = ?");
            $updateStmt->execute([$email['id']]);
            
            // Send email
            $result = sendEmail($email['recipient_email'], $email['subject'], $email['body']);
            
            if ($result['success']) {
                // Mark as sent and log delivery
                $updateStmt = $pdo->prepare("UPDATE email_queue SET status = 'sent', sent_at = NOW() WHERE id = ?");
                $updateStmt->execute([$email['id']]);
                
                logEmailDelivery($email, 'sent');
                $processed++;
                $details[] = "Email {$email['id']} sent successfully to {$email['recipient_email']}";
            } else {
                // Handle failure
                $attempts = $email['attempts'] + 1;
                $maxAttempts = 3;
                
                if ($attempts >= $maxAttempts) {
                    $updateStmt = $pdo->prepare("UPDATE email_queue SET status = 'failed', attempts = ? WHERE id = ?");
                    $updateStmt->execute([$attempts, $email['id']]);
                    
                    logEmailDelivery($email, 'failed', $result['error']);
                    $failed++;
                    $details[] = "Email {$email['id']} failed permanently: {$result['error']}";
                } else {
                    // Retry later
                    $retryAt = date('Y-m-d H:i:s', time() + (300 * $attempts)); // Exponential backoff
                    $updateStmt = $pdo->prepare("
                        UPDATE email_queue 
                        SET status = 'pending', attempts = ?, scheduled_at = ?
                        WHERE id = ?
                    ");
                    $updateStmt->execute([$attempts, $retryAt, $email['id']]);
                    
                    $details[] = "Email {$email['id']} failed, will retry at {$retryAt}: {$result['error']}";
                }
            }
        } catch (Exception $e) {
            // Handle processing error
            $updateStmt = $pdo->prepare("UPDATE email_queue SET status = 'failed' WHERE id = ?");
            $updateStmt->execute([$email['id']]);
            
            logEmailDelivery($email, 'failed', $e->getMessage());
            $failed++;
            $details[] = "Email {$email['id']} processing failed: {$e->getMessage()}";
        }
    }
    
    return [
        'processed' => $processed,
        'failed' => $failed,
        'details' => $details
    ];
}

/**
 * Get user data for email personalization
 */
function getUserEmailData($userId) {
    global $pdo;
    
    $stmt = $pdo->prepare("
        SELECT u.*, la.loan_amount, la.interest_rate, la.loan_term, la.status as loan_status,
               la.current_step, la.created_at as application_date
        FROM users u
        LEFT JOIN loan_applications la ON u.id = la.user_id
        WHERE u.id = ?
    ");
    $stmt->execute([$userId]);
    $userData = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$userData) {
        return null;
    }
    
    // Add computed fields
    $userData['full_name'] = $userData['first_name'] . ' ' . $userData['last_name'];
    $userData['current_date'] = date('F j, Y');
    $userData['current_time'] = date('g:i A');
    $userData['company_name'] = getSystemSetting('company_name', 'LoanFlow Financial');
    $userData['company_phone'] = getSystemSetting('company_phone', '(555) 123-4567');
    $userData['company_email'] = getSystemSetting('company_email', 'info@loanflow.com');
    
    // Calculate monthly payment if loan data exists
    if ($userData['loan_amount'] && $userData['interest_rate'] && $userData['loan_term']) {
        $principal = $userData['loan_amount'];
        $rate = $userData['interest_rate'] / 100 / 12;
        $term = $userData['loan_term'];
        
        if ($rate > 0) {
            $monthlyPayment = $principal * ($rate * pow(1 + $rate, $term)) / (pow(1 + $rate, $term) - 1);
            $userData['monthly_payment'] = number_format($monthlyPayment, 2);
        } else {
            $userData['monthly_payment'] = number_format($principal / $term, 2);
        }
    }
    
    return $userData;
}

/**
 * Personalize email content with user data
 */
function personalizeEmailContent($content, $userData) {
    foreach ($userData as $key => $value) {
        if (is_string($value) || is_numeric($value)) {
            $content = str_replace('{{' . $key . '}}', $value, $content);
        }
    }
    return $content;
}

/**
 * Log email delivery
 */
function logEmailDelivery($emailData, $status, $errorMessage = null) {
    global $pdo;
    
    $stmt = $pdo->prepare("
        INSERT INTO email_delivery_log 
        (template_id, user_id, campaign_id, recipient_email, subject, status, error_message, attempts)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?)
    ");
    $stmt->execute([
        $emailData['template_id'],
        $emailData['user_id'],
        $emailData['campaign_id'],
        $emailData['recipient_email'],
        $emailData['subject'],
        $status,
        $errorMessage,
        $emailData['attempts'] + 1
    ]);
}

/**
 * Send email using configured method
 */
function sendEmail($to, $subject, $body, $isHtml = true) {
    // Get email settings
    $smtpHost = getSystemSetting('smtp_host', 'localhost');
    $smtpPort = getSystemSetting('smtp_port', '587');
    $smtpUsername = getSystemSetting('smtp_username', '');
    $smtpPassword = getSystemSetting('smtp_password', '');
    $smtpEncryption = getSystemSetting('smtp_encryption', 'tls');
    $fromEmail = getSystemSetting('from_email', 'noreply@loanflow.com');
    $fromName = getSystemSetting('from_name', 'LoanFlow Financial');
    
    try {
        // For now, use PHP's mail() function
        // In production, use PHPMailer or similar library
        $headers = [];
        $headers[] = 'MIME-Version: 1.0';
        $headers[] = $isHtml ? 'Content-type: text/html; charset=UTF-8' : 'Content-type: text/plain; charset=UTF-8';
        $headers[] = "From: {$fromName} <{$fromEmail}>";
        $headers[] = "Reply-To: {$fromEmail}";
        $headers[] = 'X-Mailer: LoanFlow Email System';
        
        $success = mail($to, $subject, $body, implode("\r\n", $headers));
        
        if ($success) {
            return ['success' => true];
        } else {
            return ['success' => false, 'error' => 'Failed to send email'];
        }
    } catch (Exception $e) {
        return ['success' => false, 'error' => $e->getMessage()];
    }
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