<?php
session_start();

// Check if user is logged in as admin
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    http_response_code(401);
    echo '<div class="alert alert-danger">Unauthorized access</div>';
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo '<div class="alert alert-danger">Invalid request method</div>';
    exit;
}

if (!isset($_POST['content'])) {
    echo '<div class="alert alert-warning">No content provided</div>';
    exit;
}

$content = $_POST['content'];
$device = isset($_POST['device']) ? $_POST['device'] : 'desktop';

try {
    // Include the email template system
    require_once '../../includes/email_template_system.php';
    
    $emailSystem = new EmailTemplateSystem();
    
    // Get sample variables for preview
    $sampleVariables = [
        'customer_name' => 'John Doe',
        'company_name' => 'Your Company Name',
        'company_email' => 'info@yourcompany.com',
        'company_phone' => '+1 (555) 123-4567',
        'company_address' => '123 Business Street, City, State 12345',
        'inquiry_id' => 'INQ-' . date('Ymd') . '-001',
        'current_year' => date('Y'),
        'message_content' => 'This is a sample message content for preview purposes. It demonstrates how your email template will look with actual content.',
        'customer_email' => 'customer@example.com',
        'inquiry_date' => date('F j, Y'),
        'inquiry_time' => date('g:i A')
    ];
    
    // Replace variables in content
    $processedContent = $emailSystem->replaceVariables($content, $sampleVariables);
    
    // Device-specific viewport and styling
    $viewportMeta = '';
    $deviceStyles = '';
    
    switch ($device) {
        case 'mobile':
            $viewportMeta = '<meta name="viewport" content="width=320, initial-scale=1.0">';
            $deviceStyles = '
                body { width: 320px !important; }
                .container { max-width: 300px !important; padding: 10px !important; }
                h1 { font-size: 20px !important; }
                h2 { font-size: 18px !important; }
                h3 { font-size: 16px !important; }
                p { font-size: 14px !important; }
                .two-column, .three-column { flex-direction: column !important; }
                .column { margin-bottom: 15px !important; }
            ';
            break;
        case 'tablet':
            $viewportMeta = '<meta name="viewport" content="width=768, initial-scale=1.0">';
            $deviceStyles = '
                body { width: 768px !important; }
                .container { max-width: 720px !important; padding: 15px !important; }
                h1 { font-size: 24px !important; }
                h2 { font-size: 20px !important; }
                h3 { font-size: 18px !important; }
                .three-column { flex-direction: column !important; }
            ';
            break;
        default: // desktop
            $viewportMeta = '<meta name="viewport" content="width=1200, initial-scale=1.0">';
            $deviceStyles = '
                body { width: 100%; max-width: 1200px; }
                .container { max-width: 800px; }
            ';
    }
    
    // Create complete HTML document for iframe
    $htmlDocument = '<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    ' . $viewportMeta . '
    <title>Email Preview</title>
    <style>
        body {
            margin: 0;
            padding: 20px;
            font-family: Arial, sans-serif;
            line-height: 1.6;
            color: #333;
            background-color: #f8f9fa;
        }
        .email-container {
            background-color: #ffffff;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            overflow: hidden;
            margin: 0 auto;
        }
        .container {
            padding: 20px;
        }
        img {
            max-width: 100%;
            height: auto;
        }
        a {
            color: #007bff;
            text-decoration: none;
        }
        a:hover {
            text-decoration: underline;
        }
        .btn {
            display: inline-block;
            padding: 12px 24px;
            background-color: #007bff;
            color: white;
            text-decoration: none;
            border-radius: 4px;
            font-weight: bold;
        }
        .btn:hover {
            background-color: #0056b3;
            color: white;
            text-decoration: none;
        }
        .two-column {
            display: flex;
            gap: 20px;
        }
        .three-column {
            display: flex;
            gap: 15px;
        }
        .column {
            flex: 1;
        }
        hr {
            border: none;
            border-top: 1px solid #dee2e6;
            margin: 20px 0;
        }
        
        /* Responsive styles */
        @media screen and (max-width: 768px) {
            body {
                padding: 10px;
            }
            .container {
                padding: 15px;
            }
            .two-column,
            .three-column {
                flex-direction: column;
            }
            .column {
                margin-bottom: 15px;
            }
            h1 {
                font-size: 24px;
            }
            h2 {
                font-size: 20px;
            }
            h3 {
                font-size: 18px;
            }
        }
        
        @media screen and (max-width: 480px) {
            .container {
                padding: 10px;
            }
            h1 {
                font-size: 20px;
            }
            h2 {
                font-size: 18px;
            }
            h3 {
                font-size: 16px;
            }
            p {
                font-size: 14px;
            }
        }
        
        /* Device-specific overrides */
        ' . $deviceStyles . '
    </style>
</head>
<body>
    <div class="email-container">
        <div class="container">
            ' . $processedContent . '
        </div>
    </div>
</body>
</html>';
    
    echo $htmlDocument;
    
} catch (Exception $e) {
    error_log('Preview content error: ' . $e->getMessage());
    echo '<!DOCTYPE html><html><body><div style="padding: 20px; color: red; font-family: Arial, sans-serif;">Error generating preview: ' . htmlspecialchars($e->getMessage()) . '</div></body></html>';
}
?>