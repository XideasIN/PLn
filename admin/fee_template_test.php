<?php
/**
 * Fee Form Template Test Email Endpoint
 * LoanFlow Personal Loan Management System
 * Sends test emails using fee form templates
 */

require_once '../includes/config.php';
require_once '../includes/auth.php';
require_once '../includes/functions.php';

// Check if user is admin
if (!isAdmin()) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Access denied']);
    exit();
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit();
}

$template_id = (int)($_POST['template_id'] ?? 0);

if (!$template_id) {
    echo json_encode(['success' => false, 'message' => 'Invalid template ID']);
    exit();
}

// Get template details
$stmt = $pdo->prepare("
    SELECT fft.*, u.email as admin_email, u.first_name, u.last_name
    FROM fee_form_templates fft
    JOIN users u ON u.id = ?
    WHERE fft.id = ?
");
$stmt->execute([$_SESSION['user_id'], $template_id]);
$result = $stmt->fetch();

if (!$result) {
    echo json_encode(['success' => false, 'message' => 'Template not found']);
    exit();
}

$template = $result;

// Available countries and payment methods for display
$countries = [
    'US' => 'United States',
    'CA' => 'Canada',
    'AU' => 'Australia',
    'GB' => 'United Kingdom',
    'DE' => 'Germany',
    'FR' => 'France',
    'IT' => 'Italy',
    'ES' => 'Spain',
    'NL' => 'Netherlands',
    'BE' => 'Belgium',
    'CH' => 'Switzerland',
    'AT' => 'Austria',
    'SE' => 'Sweden',
    'NO' => 'Norway',
    'DK' => 'Denmark',
    'FI' => 'Finland'
];

$payment_methods = [
    'wire_transfer' => 'Wire Transfer',
    'crypto' => 'Cryptocurrency',
    'e_transfer' => 'e-Transfer',
    'credit_card' => 'Credit Card'
];

// Sample data for test email
$sample_data = [
    'user_name' => $template['first_name'] . ' ' . $template['last_name'],
    'application_id' => 'TEST-' . date('Y-m-d') . '-001',
    'amount' => '$2,500.00',
    'bank_name' => 'Test Bank of America',
    'account_number' => '1234567890',
    'routing_number' => '021000021',
    'wallet_address' => '1A1zP1eP5QGefi2DMPTfTL5SLmv7DivfNa',
    'network' => 'Bitcoin (BTC)'
];

// Process email template with sample data
$email_content = $template['email_template'];
if (empty($email_content)) {
    // Default email template if none provided
    $email_content = "Dear {user_name},\n\nThis is a test email for the fee payment template.\n\nTemplate: {template_name}\nCountry: {country}\nPayment Method: {payment_method}\nApplication ID: {application_id}\n\nPlease complete your payment of {amount} using the instructions provided in your client portal.\n\nBest regards,\nLoanFlow Team";
}

// Add template-specific data
$sample_data['template_name'] = $template['template_name'];
$sample_data['country'] = $countries[$template['country']] ?? $template['country'];
$sample_data['payment_method'] = $payment_methods[$template['payment_method']] ?? $template['payment_method'];

// Replace placeholders
foreach ($sample_data as $key => $value) {
    $email_content = str_replace('{' . $key . '}', $value, $email_content);
}

// Prepare email subject
$subject = 'TEST: Fee Payment Instructions - ' . $template['template_name'];

// Prepare email headers
$headers = [
    'From: LoanFlow System <noreply@loanflow.com>',
    'Reply-To: support@loanflow.com',
    'X-Mailer: LoanFlow Fee Template Test',
    'Content-Type: text/plain; charset=UTF-8'
];

// Add test notice to email content
$test_notice = "=== THIS IS A TEST EMAIL ===\n";
$test_notice .= "Template: " . $template['template_name'] . "\n";
$test_notice .= "Country: " . ($countries[$template['country']] ?? $template['country']) . "\n";
$test_notice .= "Payment Method: " . ($payment_methods[$template['payment_method']] ?? $template['payment_method']) . "\n";
$test_notice .= "Sent by: " . $template['first_name'] . ' ' . $template['last_name'] . "\n";
$test_notice .= "Date: " . date('Y-m-d H:i:s') . "\n";
$test_notice .= "=== END TEST NOTICE ===\n\n";

$final_email_content = $test_notice . $email_content;

// Add footer with template info
$footer = "\n\n--- Template Information ---\n";
$footer .= "Template ID: " . $template['id'] . "\n";
$footer .= "Created: " . date('M j, Y', strtotime($template['created_at'])) . "\n";
$footer .= "Status: " . ($template['is_active'] ? 'Active' : 'Inactive') . "\n";
$footer .= "Required Fields: " . implode(', ', array_keys(array_filter(json_decode($template['required_fields'], true) ?? []))) . "\n";

$final_email_content .= $footer;

try {
    // Send email using PHP's mail function
    $mail_sent = mail(
        $template['admin_email'],
        $subject,
        $final_email_content,
        implode("\r\n", $headers)
    );
    
    if ($mail_sent) {
        // Log the test email
        $log_stmt = $pdo->prepare("
            INSERT INTO fee_form_notifications 
            (template_id, user_id, notification_type, subject, content, status, created_at) 
            VALUES (?, ?, 'test_email', ?, ?, 'sent', NOW())
        ");
        $log_stmt->execute([
            $template_id,
            $_SESSION['user_id'],
            $subject,
            $final_email_content
        ]);
        
        echo json_encode([
            'success' => true, 
            'message' => 'Test email sent successfully to ' . $template['admin_email']
        ]);
    } else {
        echo json_encode([
            'success' => false, 
            'message' => 'Failed to send test email. Please check your mail server configuration.'
        ]);
    }
    
} catch (Exception $e) {
    // Log the error
    error_log('Fee template test email error: ' . $e->getMessage());
    
    // Try to log to database
    try {
        $error_stmt = $pdo->prepare("
            INSERT INTO fee_form_notifications 
            (template_id, user_id, notification_type, subject, content, status, error_message, created_at) 
            VALUES (?, ?, 'test_email', ?, ?, 'failed', ?, NOW())
        ");
        $error_stmt->execute([
            $template_id,
            $_SESSION['user_id'],
            $subject,
            $final_email_content,
            $e->getMessage()
        ]);
    } catch (PDOException $db_error) {
        error_log('Failed to log email error to database: ' . $db_error->getMessage());
    }
    
    echo json_encode([
        'success' => false, 
        'message' => 'Error sending test email: ' . $e->getMessage()
    ]);
}
?>