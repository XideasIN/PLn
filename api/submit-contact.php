<?php
require_once '../config/database.php';
require_once '../includes/functions.php';
require_once '../includes/email.php';
require_once '../includes/ai_automation.php';
require_once '../includes/email_template_system.php';

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

try {
    // Get form data
    $name = sanitizeInput($_POST['name'] ?? '');
    $phone = sanitizeInput($_POST['phone'] ?? '');
    $email = filter_var($_POST['email'] ?? '', FILTER_VALIDATE_EMAIL);
    $comment = sanitizeInput($_POST['comment'] ?? '');
    
    // Validate required fields
    if (empty($name) || empty($phone) || !$email) {
        throw new Exception('Please fill in all required fields with valid information.');
    }
    
    // Connect to database
    $pdo = getDBConnection();
    
    // Insert contact form submission
    $stmt = $pdo->prepare("
        INSERT INTO contact_forms (name, phone, email, comment, submitted_at, status) 
        VALUES (?, ?, ?, ?, NOW(), 'new')
    ");
    
    $stmt->execute([$name, $phone, $email, $comment]);
    $contactId = $pdo->lastInsertId();
    
    // Generate unique inquiry ID
    $inquiryId = 'INQ-' . date('Ymd') . '-' . str_pad($contactId, 4, '0', STR_PAD_LEFT);
    
    // Initialize email template system
    $emailSystem = new EmailTemplateSystem();
    
    // Send immediate acknowledgment email
    $acknowledgmentSent = $emailSystem->sendContactAcknowledgment($email, $name, $inquiryId);
    
    // Generate AI response based on comment
    $aiResponse = generateAIResponse($comment, $name);
    
    // Send AI-powered response email
    $responseSent = $emailSystem->sendContactResponse($email, $name, $comment, $aiResponse, $inquiryId);
    
    // Update contact record with AI response and inquiry ID
    $stmt = $pdo->prepare("
        UPDATE contact_forms 
        SET ai_response = ?, email_sent = ?, inquiry_id = ?, processed_at = NOW() 
        WHERE id = ?
    ");
    $stmt->execute([$aiResponse, ($acknowledgmentSent && $responseSent) ? 1 : 0, $inquiryId, $contactId]);
    
    // Log the interaction for admin review
    logActivity('contact_form_submitted', [
        'contact_id' => $contactId,
        'inquiry_id' => $inquiryId,
        'name' => $name,
        'email' => $email,
        'acknowledgment_sent' => $acknowledgmentSent,
        'ai_response_sent' => $responseSent
    ]);
    
    echo json_encode([
        'success' => true,
        'message' => 'Thank you for contacting us! We have received your inquiry and will respond to you shortly.',
        'contact_id' => $contactId,
        'inquiry_id' => $inquiryId,
        'acknowledgment_sent' => $acknowledgmentSent,
        'response_sent' => $responseSent
    ]);
    
} catch (Exception $e) {
    error_log('Contact form error: ' . $e->getMessage());
    
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
?>