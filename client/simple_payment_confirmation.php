<?php
/**
 * Simple Payment Confirmation Form
 * Simplified version for basic payment confirmation
 * LoanFlow Personal Loan Management System
 */

require_once '../config/database.php';
require_once '../includes/functions.php';
require_once '../includes/auth.php';

// Require client login
requireLogin();

$current_user = getCurrentUser();
$application = getApplicationByUserId($current_user['id']);

if (!$application) {
    header('Location: dashboard.php');
    exit();
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['confirm_payment'])) {
    try {
        $db = getDB();
        
        $payment_method = $_POST['payment_method'];
        $confirmation_notes = $_POST['confirmation_notes'] ?? '';
        
        // Insert simple payment confirmation
        $stmt = $db->prepare("
            INSERT INTO payment_confirmations 
            (user_id, application_id, payment_method, confirmation_notes, 
             status, created_at)
            VALUES (?, ?, ?, ?, 'pending', NOW())
        ");
        $stmt->execute([
            $current_user['id'], 
            $application['id'], 
            $payment_method, 
            $confirmation_notes
        ]);
        
        // Update application status
        $stmt = $db->prepare("UPDATE loan_applications SET status = 'payment_confirmed' WHERE id = ?");
        $stmt->execute([$application['id']]);
        
        // Send simple notification
        $admin_email = getSystemSetting('admin_email', 'admin@loanflow.com');
        $subject = "Payment Confirmation - Application #{$application['id']}";
        $message = "Payment confirmation received from {$current_user['first_name']} {$current_user['last_name']}\n\n";
        $message .= "Application ID: {$application['id']}\n";
        $message .= "Payment Method: {$payment_method}\n";
        
        mail($admin_email, $subject, $message);
        
        setFlashMessage('Payment confirmation submitted successfully!', 'success');
        header('Location: dashboard.php');
        exit();
        
    } catch (Exception $e) {
        error_log("Payment confirmation failed: " . $e->getMessage());
        setFlashMessage('Failed to submit confirmation. Please try again.', 'error');
    }
}

// Check if user can submit confirmation
$can_submit = $application['current_step'] >= 4;

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Payment Confirmation - QuickFunds</title>
    <link rel="stylesheet" href="../FrontEnd_Template/css/bootstrap.min.css">
    <link rel="stylesheet" href="../FrontEnd_Template/css/style.css">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="../assets/css/client.css" rel="stylesheet">
    <style>
        .confirmation-card {
            background: white;
            border-radius: 15px;
            padding: 30px;
            box-shadow: 0 5px 20px rgba(0,0,0,0.1);
            margin-bottom: 30px;
            max-width: 600px;
            margin: 0 auto;
        }
        .payment-method-option {
            border: 2px solid #e9ecef;
            border-radius: 10px;
            padding: 15px;
            margin-bottom: 10px;
            cursor: pointer;
            transition: all 0.3s ease;
        }
        .payment-method-option:hover {
            border-color: #28a745;
            background-color: #f8fff9;
        }
        .payment-method-option.selected {
            border-color: #28a745;
            background-color: #f8fff9;
        }
        .simple-form {
            background: #f8f9fa;
            border-radius: 10px;
            padding: 20px;
        }
    </style>
</head>
<body>
    <div class="container mt-5">
        <div class="row justify-content-center">
            <div class="col-lg-8">
                <!-- Header -->
                <div class="text-center mb-4">
                    <h2 class="text-primary">
                        <i class="fas fa-check-circle me-2"></i>Payment Confirmation
                    </h2>
                    <p class="text-muted">Confirm that you have sent your payment</p>
                </div>

                <?php if (getFlashMessage()): ?>
                    <div class="alert alert-<?php echo getFlashMessage()['type']; ?> alert-dismissible fade show" role="alert">
                        <?php echo htmlspecialchars(getFlashMessage()['message']); ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>

                <?php if ($can_submit): ?>
                    <!-- Simple Confirmation Form -->
                    <div class="confirmation-card">
                        <div class="text-center mb-4">
                            <div class="alert alert-info">
                                <h5><i class="fas fa-info-circle me-2"></i>Processing Fee</h5>
                                <h3 class="text-primary mb-0">$<?php echo number_format($application['loan_amount'] * 0.02, 2); ?></h3>
                                <small>2% of loan amount</small>
                            </div>
                        </div>

                        <form method="POST" id="confirmationForm">
                            <div class="simple-form">
                                <h6 class="mb-3">How did you send the payment?</h6>
                                
                                <div class="payment-method-option" onclick="selectMethod('wire_transfer')">
                                    <input type="radio" name="payment_method" value="wire_transfer" id="wire" style="display: none;">
                                    <div class="d-flex align-items-center">
                                        <i class="fas fa-university fa-2x text-primary me-3"></i>
                                        <div>
                                            <h6 class="mb-0">Wire Transfer</h6>
                                            <small class="text-muted">Bank wire transfer</small>
                                        </div>
                                    </div>
                                </div>

                                <div class="payment-method-option" onclick="selectMethod('crypto')">
                                    <input type="radio" name="payment_method" value="crypto" id="crypto" style="display: none;">
                                    <div class="d-flex align-items-center">
                                        <i class="fab fa-bitcoin fa-2x text-warning me-3"></i>
                                        <div>
                                            <h6 class="mb-0">Cryptocurrency</h6>
                                            <small class="text-muted">Bitcoin or other crypto</small>
                                        </div>
                                    </div>
                                </div>

                                <div class="payment-method-option" onclick="selectMethod('e_transfer')">
                                    <input type="radio" name="payment_method" value="e_transfer" id="etransfer" style="display: none;">
                                    <div class="d-flex align-items-center">
                                        <i class="fas fa-envelope fa-2x text-info me-3"></i>
                                        <div>
                                            <h6 class="mb-0">e-Transfer</h6>
                                            <small class="text-muted">Email money transfer</small>
                                        </div>
                                    </div>
                                </div>

                                <div class="mt-3">
                                    <label for="confirmation_notes" class="form-label">Additional Notes (Optional)</label>
                                    <textarea class="form-control" id="confirmation_notes" name="confirmation_notes" rows="3" 
                                              placeholder="Any additional information about your payment..."></textarea>
                                </div>
                            </div>

                            <div class="text-center mt-4">
                                <button type="submit" name="confirm_payment" class="btn btn-success btn-lg px-5">
                                    <i class="fas fa-check me-2"></i>Confirm Payment Sent
                                </button>
                            </div>
                        </form>
                    </div>
                <?php else: ?>
                    <!-- Cannot Submit Notice -->
                    <div class="confirmation-card">
                        <div class="alert alert-warning text-center">
                            <h5><i class="fas fa-exclamation-triangle me-2"></i>Not Available Yet</h5>
                            <p class="mb-0">Payment confirmation is available after completing the previous steps.</p>
                            <a href="dashboard.php" class="btn btn-primary mt-3">
                                <i class="fas fa-arrow-left me-2"></i>Back to Dashboard
                            </a>
                        </div>
                    </div>
                <?php endif; ?>

                <!-- Back to Dashboard -->
                <div class="text-center mt-4">
                    <a href="dashboard.php" class="btn btn-outline-secondary">
                        <i class="fas fa-arrow-left me-2"></i>Back to Dashboard
                    </a>
                </div>
            </div>
        </div>
    </div>

    <script src="../FrontEnd_Template/js/bootstrap.bundle.min.js"></script>
    <script>
        function selectMethod(method) {
            // Remove selected class from all options
            document.querySelectorAll('.payment-method-option').forEach(option => {
                option.classList.remove('selected');
            });
            
            // Add selected class to clicked option
            event.currentTarget.classList.add('selected');
            
            // Check the radio button
            document.getElementById(method === 'wire_transfer' ? 'wire' : 
                                  method === 'crypto' ? 'crypto' : 'etransfer').checked = true;
        }

        // Form validation
        document.getElementById('confirmationForm').addEventListener('submit', function(e) {
            const paymentMethod = document.querySelector('input[name="payment_method"]:checked');
            if (!paymentMethod) {
                e.preventDefault();
                alert('Please select how you sent the payment.');
                return false;
            }
            
            // Simple confirmation
            if (!confirm('Are you sure you have sent the payment? This will notify our admin team.')) {
                e.preventDefault();
                return false;
            }
        });
    </script>
</body>
</html>