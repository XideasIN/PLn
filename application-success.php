<?php
require_once 'config/database.php';
require_once 'includes/functions.php';
require_once 'includes/security.php';

// Get reference number from URL
$reference_number = isset($_GET['ref']) ? sanitizeInput($_GET['ref']) : '';

// Validate reference number format
if (!$reference_number || !preg_match('/^[0-9]{6}$/', $reference_number)) {
    header('Location: index.php');
    exit;
}

// Optional: Verify reference number exists in database
try {
    $db = getDatabase();
    $stmt = $db->prepare("SELECT id FROM loan_applications WHERE reference_number = ?");
    $stmt->execute([$reference_number]);
    
    if (!$stmt->fetch()) {
        header('Location: index.php');
        exit;
    }
} catch (Exception $e) {
    // If database check fails, continue anyway
    error_log("Database error in application-success.php: " . $e->getMessage());
}

$pageTitle = 'Application Submitted Successfully';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($pageTitle) ?> - QuickFunds</title>
    
    <!-- Template CSS -->
    <link href="FrontEnd_Template/css/bootstrap.min.css" rel="stylesheet">
    <link href="FrontEnd_Template/css/style.css" rel="stylesheet">
    <link href="FrontEnd_Template/css/aos.css" rel="stylesheet">
    <link href="FrontEnd_Template/bootstrap-icons/bootstrap-icons.min.css" rel="stylesheet">
    <!-- Font Awesome -->
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    
    <style>
        .success-container {
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            background: linear-gradient(135deg, #1B424C 0%, #0f2a31 100%);
        }
        
        .success-card {
            background: white;
            border-radius: 20px;
            box-shadow: 0 20px 40px rgba(0,0,0,0.1);
            padding: 3rem;
            text-align: center;
            max-width: 600px;
            margin: 2rem;
        }
        
        .success-icon {
            font-size: 4rem;
            color: #1B424C;
            margin-bottom: 1.5rem;
            animation: checkmark 0.6s ease-in-out;
        }
        
        @keyframes checkmark {
            0% {
                transform: scale(0);
                opacity: 0;
            }
            50% {
                transform: scale(1.2);
            }
            100% {
                transform: scale(1);
                opacity: 1;
            }
        }
        
        .reference-number {
            background: #f8f9fa;
            border: 2px dashed #dee2e6;
            border-radius: 10px;
            padding: 1rem;
            margin: 1.5rem 0;
            font-family: 'Courier New', monospace;
            font-size: 1.5rem;
            font-weight: bold;
            color: #495057;
        }
        
        .next-steps {
            background: #f8f9fa;
            border-left: 4px solid #1B424C;
            padding: 1.5rem;
            margin: 2rem 0;
            border-radius: 0 10px 10px 0;
            text-align: left;
        }
        
        .step-item {
            display: flex;
            align-items: flex-start;
            margin-bottom: 1rem;
        }
        
        .step-number {
            background: #1B424C;
            color: white;
            width: 24px;
            height: 24px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 0.8rem;
            font-weight: bold;
            margin-right: 1rem;
            flex-shrink: 0;
        }
        
        .btn-home {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            border: none;
            padding: 12px 30px;
            border-radius: 25px;
            color: white;
            font-weight: 600;
            text-decoration: none;
            display: inline-block;
            margin-top: 1rem;
            transition: transform 0.3s ease;
        }
        
        .btn-home:hover {
            transform: translateY(-2px);
            color: white;
        }
    </style>
</head>
<body>
    <div class="success-container">
        <div class="success-card">
            <div class="success-icon">
                <i class="fas fa-check-circle"></i>
            </div>
            
            <h1 class="h2 mb-3">Application Submitted Successfully!</h1>
            
            <p class="lead text-muted mb-4">
                Thank you for choosing LoanFlow. Your loan application has been received and is being processed.
            </p>
            
            <div class="reference-number">
                Reference Number: <?= htmlspecialchars($reference_number) ?>
            </div>
            
            <div class="alert alert-info">
                <i class="fas fa-info-circle me-2"></i>
                <strong>Important:</strong> Please save your reference number for future correspondence.
            </div>
            
            <div class="next-steps">
                <h5 class="mb-3"><i class="fas fa-list-check me-2"></i>What Happens Next?</h5>
                
                <div class="step-item">
                    <div class="step-number">1</div>
                    <div>
                        <strong>Application Review</strong><br>
                        <small class="text-muted">Our team will review your application within 24 hours.</small>
                    </div>
                </div>
                
                <div class="step-item">
                    <div class="step-number">2</div>
                    <div>
                        <strong>Email Confirmation</strong><br>
                        <small class="text-muted">You'll receive a confirmation email with further instructions.</small>
                    </div>
                </div>
                
                <div class="step-item">
                    <div class="step-number">3</div>
                    <div>
                        <strong>Document Verification</strong><br>
                        <small class="text-muted">We may request additional documents if needed.</small>
                    </div>
                </div>
                
                <div class="step-item">
                    <div class="step-number">4</div>
                    <div>
                        <strong>Loan Decision</strong><br>
                        <small class="text-muted">You'll be notified of our decision within 2-3 business days.</small>
                    </div>
                </div>
            </div>
            
            <div class="contact-info mt-4">
                <p class="mb-2"><strong>Need Help?</strong></p>
                <p class="text-muted mb-0">
                    <i class="fas fa-envelope me-2"></i>support@quickfunds.com<br>
                    <i class="fas fa-phone me-2"></i>+1 (555) 123-4567
                </p>
            </div>
            
            <a href="index.php" class="btn-home">
                <i class="fas fa-home me-2"></i>Return to Home
            </a>
        </div>
    </div>
    
    <!-- Template JS -->
    <script src="FrontEnd_Template/js/bootstrap.bundle.js"></script>
    <script src="FrontEnd_Template/js/bootstrap.min.js"></script>
    <script src="FrontEnd_Template/js/aos.js"></script>
    <script>
        AOS.init();
    </script>
    
    <script>
        // Auto-redirect after 5 minutes
        setTimeout(() => {
            window.location.href = 'index.php';
        }, 300000);
        
        // Copy reference number to clipboard
        document.querySelector('.reference-number').addEventListener('click', function() {
            const referenceNumber = '<?= htmlspecialchars($reference_number) ?>';
            navigator.clipboard.writeText(referenceNumber).then(() => {
                // Show temporary tooltip
                const tooltip = document.createElement('div');
                tooltip.textContent = 'Copied to clipboard!';
                tooltip.style.cssText = `
                    position: absolute;
                    background: #333;
                    color: white;
                    padding: 5px 10px;
                    border-radius: 5px;
                    font-size: 12px;
                    top: -30px;
                    left: 50%;
                    transform: translateX(-50%);
                    z-index: 1000;
                `;
                this.style.position = 'relative';
                this.appendChild(tooltip);
                
                setTimeout(() => {
                    tooltip.remove();
                }, 2000);
            }).catch(() => {
                // Fallback for older browsers
                const textArea = document.createElement('textarea');
                textArea.value = referenceNumber;
                document.body.appendChild(textArea);
                textArea.select();
                document.execCommand('copy');
                document.body.removeChild(textArea);
            });
        });
    </script>
    
    <!-- Footer -->
    <div id="footer-placeholder"></div>
    
    <script>
        function loadFooter() {
            let isFileProtocol = window.location.protocol === 'file:';
            
            if (isFileProtocol) {
                // For file:// protocol, show a message about CORS limitations
                document.getElementById('footer-placeholder').innerHTML = `
                    <div class="container text-center py-5">
                        <div class="alert alert-warning">
                            <h5>Footer Loading Limited</h5>
                            <p>When accessing via file:// protocol, the footer cannot load due to browser security restrictions (CORS).</p>
                            <p><strong>To see the complete website with footer:</strong></p>
                            <p>Please use: <code>http://localhost:8000/application-success.php</code></p>
                        </div>
                    </div>
                `;
                return;
            }
            
            fetch('footer.html')
                .then(response => response.text())
                .then(data => {
                    document.getElementById('footer-placeholder').innerHTML = data;
                })
                .catch(error => console.error('Error loading footer:', error));
        }
        
        document.addEventListener('DOMContentLoaded', loadFooter);
    </script>
</body>
</html>