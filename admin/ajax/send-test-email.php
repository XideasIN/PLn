<?php
session_start();

// Check if user is logged in as admin
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit;
}

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
    exit;
}

if (!isset($_POST['email']) || !isset($_POST['content'])) {
    echo json_encode(['success' => false, 'message' => 'Missing required parameters']);
    exit;
}

$email = filter_var($_POST['email'], FILTER_VALIDATE_EMAIL);
if (!$email) {
    echo json_encode(['success' => false, 'message' => 'Invalid email address']);
    exit;
}

$content = $_POST['content'];

try {
    // Include the email template system
    require_once '../../includes/email_template_system.php';
    
    $emailSystem = new EmailTemplateSystem();
    
    // Replace variables with sample data for testing
    $sampleVariables = [
        'customer_name' => 'Test Customer',
        'company_name' => 'Your Company',
        'company_email' => 'info@yourcompany.com',
        'company_phone' => '+1 (555) 123-4567',
        'company_address' => '123 Business St, City, State 12345',
        'inquiry_id' => 'TEST-' . date('Ymd') . '-001',
        'current_year' => date('Y'),
        'message_content' => 'This is a test email to preview your email template design.',
        'customer_email' => $email,
        'inquiry_date' => date('F j, Y'),
        'inquiry_time' => date('g:i A')
    ];
    
    // Replace variables in content
    $processedContent = $emailSystem->replaceVariables($content, $sampleVariables);
    
    // Create complete email with responsive styling
    $completeEmail = $emailSystem->createCompleteEmail($processedContent, 'Test Email Template');
    
    // Email headers
    $headers = [
        'MIME-Version: 1.0',
        'Content-type: text/html; charset=UTF-8',
        'From: Test Email System <noreply@yourcompany.com>',
        'Reply-To: noreply@yourcompany.com',
        'X-Mailer: PHP/' . phpversion()
    ];
    
    $subject = 'Test Email Template - ' . date('Y-m-d H:i:s');
    
    // Send the email
    $success = mail($email, $subject, $completeEmail, implode("\r\n", $headers));
    
    if ($success) {
        echo json_encode([
            'success' => true, 
            'message' => 'Test email sent successfully to ' . $email
        ]);
    } else {
        echo json_encode([
            'success' => false, 
            'message' => 'Failed to send email. Please check your server mail configuration.'
        ]);
    }
    
} catch (Exception $e) {
    error_log('Test email error: ' . $e->getMessage());
    echo json_encode([
        'success' => false, 
        'message' => 'An error occurred while sending the test email: ' . $e->getMessage()
    ]);
}
?>