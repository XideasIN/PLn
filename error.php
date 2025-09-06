<?php
/**
 * Custom Error Page
 * LoanFlow Personal Loan Management System
 */

// Prevent direct access
if (!isset($_GET['code'])) {
    header('Location: index.php');
    exit;
}

$error_code = intval($_GET['code']);
$error_messages = [
    400 => ['title' => 'Bad Request', 'message' => 'The request could not be understood by the server.'],
    401 => ['title' => 'Unauthorized', 'message' => 'Authentication is required to access this resource.'],
    403 => ['title' => 'Access Forbidden', 'message' => 'You do not have permission to access this resource.'],
    404 => ['title' => 'Page Not Found', 'message' => 'The requested page could not be found on this server.'],
    500 => ['title' => 'Internal Server Error', 'message' => 'The server encountered an unexpected condition.']
];

$error = $error_messages[$error_code] ?? $error_messages[404];

// Set appropriate HTTP response code
http_response_code($error_code);

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $error['title'] ?> - QuickFunds</title>
    <meta name="robots" content="noindex, nofollow">
    <link href="FrontEnd_Template/css/bootstrap.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="FrontEnd_Template/css/main.css" rel="stylesheet">
    <link href="FrontEnd_Template/css/aos.css" rel="stylesheet">
    <style>
        body {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            font-family: 'Arial', sans-serif;
        }
        .error-container {
            background: rgba(255, 255, 255, 0.95);
            border-radius: 20px;
            box-shadow: 0 20px 40px rgba(0, 0, 0, 0.1);
            padding: 3rem;
            text-align: center;
            backdrop-filter: blur(10px);
        }
        .error-code {
            font-size: 6rem;
            font-weight: bold;
            color: #dc3545;
            text-shadow: 2px 2px 4px rgba(0, 0, 0, 0.1);
            margin-bottom: 0;
        }
        .error-title {
            font-size: 2rem;
            color: #495057;
            margin-bottom: 1rem;
        }
        .error-message {
            font-size: 1.1rem;
            color: #6c757d;
            margin-bottom: 2rem;
        }
        .btn-home {
            background: linear-gradient(45deg, #007bff, #0056b3);
            border: none;
            padding: 12px 30px;
            font-size: 1.1rem;
            border-radius: 50px;
            transition: all 0.3s ease;
        }
        .btn-home:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 20px rgba(0, 123, 255, 0.3);
        }
        .security-notice {
            background: #fff3cd;
            border: 1px solid #ffeaa7;
            border-radius: 10px;
            padding: 1rem;
            margin-top: 2rem;
            font-size: 0.9rem;
            color: #856404;
        }
        .contact-info {
            margin-top: 2rem;
            padding-top: 2rem;
            border-top: 1px solid #dee2e6;
            color: #6c757d;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="row justify-content-center">
            <div class="col-lg-6 col-md-8">
                <div class="error-container">
                    <!-- Error Icon and Code -->
                    <div class="mb-4">
                        <?php if ($error_code == 403): ?>
                            <i class="fas fa-shield-alt fa-4x text-danger mb-3"></i>
                        <?php elseif ($error_code == 404): ?>
                            <i class="fas fa-search fa-4x text-warning mb-3"></i>
                        <?php elseif ($error_code == 500): ?>
                            <i class="fas fa-exclamation-triangle fa-4x text-danger mb-3"></i>
                        <?php else: ?>
                            <i class="fas fa-exclamation-circle fa-4x text-danger mb-3"></i>
                        <?php endif; ?>
                        <div class="error-code"><?= $error_code ?></div>
                    </div>
                    
                    <!-- Error Details -->
                    <h1 class="error-title"><?= htmlspecialchars($error['title']) ?></h1>
                    <p class="error-message"><?= htmlspecialchars($error['message']) ?></p>
                    
                    <!-- Action Buttons -->
                    <div class="mb-4">
                        <a href="index.php" class="btn btn-primary btn-home me-3">
                            <i class="fas fa-home me-2"></i>Go Home
                        </a>
                        <button onclick="history.back()" class="btn btn-outline-secondary">
                            <i class="fas fa-arrow-left me-2"></i>Go Back
                        </button>
                    </div>
                    
                    <!-- Security Notice for 403 errors -->
                    <?php if ($error_code == 403): ?>
                        <div class="security-notice">
                            <i class="fas fa-info-circle me-2"></i>
                            <strong>Security Notice:</strong> Your request has been blocked for security reasons. 
                            This may be due to suspicious activity or unauthorized access attempts. 
                            If you believe this is an error, please contact our support team.
                        </div>
                    <?php endif; ?>
                    
                    <!-- Additional Help -->
                    <?php if ($error_code == 404): ?>
                        <div class="mt-4">
                            <h5>What you can do:</h5>
                            <ul class="list-unstyled text-start">
                                <li><i class="fas fa-check text-success me-2"></i>Check the URL for typos</li>
                                <li><i class="fas fa-check text-success me-2"></i>Use the navigation menu</li>
                                <li><i class="fas fa-check text-success me-2"></i>Visit our homepage</li>
                                <li><i class="fas fa-check text-success me-2"></i>Contact support if needed</li>
                            </ul>
                        </div>
                    <?php endif; ?>
                    
                    <!-- Contact Information -->
                    <div class="contact-info">
                        <p class="mb-1"><strong>Need Help?</strong></p>
                        <p class="mb-0">
                            <i class="fas fa-envelope me-2"></i>support@loanflow.com
                            <span class="mx-2">|</span>
                            <i class="fas fa-phone me-2"></i>+1 (555) 123-4567
                        </p>
                        <p class="mt-2 small">
                            Reference ID: <?= strtoupper(substr(md5(time() . $_SERVER['REMOTE_ADDR'] ?? ''), 0, 8)) ?>
                        </p>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="FrontEnd_Template/js/bootstrap.bundle.js"></script>
    <script src="FrontEnd_Template/js/bootstrap.min.js"></script>
    <script src="FrontEnd_Template/js/aos.js"></script>
    <script>
        AOS.init();
    </script>
    <script>
        // Add some animation
        document.addEventListener('DOMContentLoaded', function() {
            const container = document.querySelector('.error-container');
            container.style.opacity = '0';
            container.style.transform = 'translateY(30px)';
            
            setTimeout(function() {
                container.style.transition = 'all 0.6s ease';
                container.style.opacity = '1';
                container.style.transform = 'translateY(0)';
            }, 100);
        });
        
        // Disable right-click and common shortcuts
        document.addEventListener('contextmenu', function(e) {
            e.preventDefault();
        });
        
        document.addEventListener('keydown', function(e) {
            if (e.keyCode === 123 || // F12
                (e.ctrlKey && e.shiftKey && e.keyCode === 73) || // Ctrl+Shift+I
                (e.ctrlKey && e.keyCode === 85)) { // Ctrl+U
                e.preventDefault();
            }
        });
    </script>
</body>
</html>
