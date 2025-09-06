<?php
/**
 * Fee Sent Form - Country-Specific Payment Submission Tracking
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

// Get user's country to determine available payment methods
$user_country = $current_user['country'] ?? 'US';

// Define country-specific payment methods
$country_payment_methods = [
    'US' => ['wire_transfer', 'crypto'],
    'CA' => ['e_transfer', 'crypto'],
    'AU' => ['wire_transfer', 'crypto'],
    'GB' => ['wire_transfer', 'crypto'],
    'UK' => ['wire_transfer', 'crypto']
];

$available_methods = $country_payment_methods[$user_country] ?? ['wire_transfer', 'crypto'];

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_fee_form'])) {
    try {
        $db = getDB();
        
        $payment_method = $_POST['payment_method'];
        $amount_sent = floatval($_POST['amount_sent']);
        $date_sent = $_POST['date_sent'];
        $transaction_reference = $_POST['transaction_reference'] ?? '';
        $additional_notes = $_POST['additional_notes'] ?? '';
        
        // Handle file upload if provided
        $receipt_filename = null;
        if (isset($_FILES['receipt_file']) && $_FILES['receipt_file']['error'] === UPLOAD_ERR_OK) {
            $upload_dir = '../uploads/fee_receipts/';
            if (!is_dir($upload_dir)) {
                mkdir($upload_dir, 0755, true);
            }
            
            $file_extension = pathinfo($_FILES['receipt_file']['name'], PATHINFO_EXTENSION);
            $receipt_filename = 'fee_receipt_' . $current_user['id'] . '_' . time() . '.' . $file_extension;
            
            if (move_uploaded_file($_FILES['receipt_file']['tmp_name'], $upload_dir . $receipt_filename)) {
                // File uploaded successfully
            } else {
                throw new Exception('Failed to upload receipt file');
            }
        }
        
        // Process payment-specific fields
        $payment_details = [];
        if ($payment_method === 'wire_transfer') {
            $payment_details = [
                'sending_bank' => $_POST['sending_bank'] ?? '',
                'account_holder' => $_POST['account_holder'] ?? '',
                'wire_reference' => $_POST['wire_reference'] ?? ''
            ];
        } elseif ($payment_method === 'e_transfer') {
            $payment_details = [
                'sending_email' => $_POST['sending_email'] ?? '',
                'etransfer_reference' => $_POST['etransfer_reference'] ?? ''
            ];
        } elseif ($payment_method === 'crypto') {
            $payment_details = [
                'transfer_id' => $_POST['transfer_id'] ?? '',
                'sender_name' => $_POST['sender_name'] ?? ''
            ];
        }
        
        // Insert fee sent record
        $stmt = $db->prepare("
            INSERT INTO fee_sent_forms 
            (user_id, application_id, payment_method, amount_sent, date_sent, 
             transaction_reference, receipt_filename, additional_notes, country, 
             payment_details, status, created_at)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'pending', NOW())
        ");
        $stmt->execute([
            $current_user['id'], $application['id'], $payment_method, $amount_sent, 
            $date_sent, $transaction_reference, $receipt_filename, $additional_notes, 
            $user_country, json_encode($payment_details)
        ]);
        
        // Update application status if needed
        $stmt = $db->prepare("UPDATE loan_applications SET status = 'fee_submitted' WHERE id = ? AND status = 'approved'");
        $stmt->execute([$application['id']]);
        
        // Send notification email to admin
        $admin_email = getSystemSetting('admin_email', 'admin@loanflow.com');
        $subject = "Fee Sent Form Submitted - Application #{$application['id']}";
        $message = "A fee sent form has been submitted by {$current_user['first_name']} {$current_user['last_name']}\n\n";
        $message .= "Application ID: {$application['id']}\n";
        $message .= "Payment Method: {$payment_method}\n";
        $message .= "Amount: $" . number_format($amount_sent, 2) . "\n";
        $message .= "Date Sent: {$date_sent}\n";
        $message .= "Reference: {$transaction_reference}\n";
        
        mail($admin_email, $subject, $message);
        
        setFlashMessage('Fee sent form submitted successfully! Admin will review and confirm receipt.', 'success');
        header('Location: fee_sent_form.php');
        exit();
        
    } catch (Exception $e) {
        error_log("Fee sent form submission failed: " . $e->getMessage());
        setFlashMessage('Failed to submit fee sent form. Please try again.', 'error');
    }
}

// Get existing fee sent forms for this user
try {
    $db = getDB();
    $stmt = $db->prepare("
        SELECT * FROM fee_sent_forms 
        WHERE user_id = ? 
        ORDER BY created_at DESC
    ");
    $stmt->execute([$current_user['id']]);
    $fee_forms = $stmt->fetchAll();
} catch (Exception $e) {
    error_log("Fee forms fetch failed: " . $e->getMessage());
    $fee_forms = [];
}

// Check if user can submit a new form
$can_submit = $application['status'] === 'approved' || $application['status'] === 'fee_submitted';
$latest_form = !empty($fee_forms) ? $fee_forms[0] : null;
$pending_form = $latest_form && $latest_form['status'] === 'pending';

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Fee Sent Form - QuickFunds</title>
    <link rel="stylesheet" href="../FrontEnd_Template/css/bootstrap.min.css">
    <link rel="stylesheet" href="../FrontEnd_Template/css/style.css">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="../assets/css/client.css" rel="stylesheet">
    <style>
        .fee-form-card {
            background: white;
            border-radius: 15px;
            padding: 30px;
            box-shadow: 0 5px 20px rgba(0,0,0,0.1);
            margin-bottom: 30px;
        }
        .country-badge {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 8px 16px;
            border-radius: 20px;
            font-size: 0.875rem;
            font-weight: 500;
        }
        .payment-method-card {
            border: 2px solid #e9ecef;
            border-radius: 10px;
            padding: 20px;
            margin-bottom: 15px;
            cursor: pointer;
            transition: all 0.3s ease;
        }
        .payment-method-card:hover {
            border-color: #667eea;
            background-color: #f8f9ff;
        }
        .payment-method-card.selected {
            border-color: #667eea;
            background-color: #f8f9ff;
        }
        .status-badge {
            padding: 6px 12px;
            border-radius: 15px;
            font-size: 0.75rem;
            font-weight: 500;
        }
        .status-pending { background-color: #fff3cd; color: #856404; }
        .status-confirmed { background-color: #d1edff; color: #0c5460; }
        .status-rejected { background-color: #f8d7da; color: #721c24; }
        .form-section {
            background: #f8f9fa;
            border-radius: 10px;
            padding: 20px;
            margin-bottom: 20px;
        }
    </style>
</head>
<body>
    <?php include '../includes/client_header.php'; ?>
    
    <div class="container-fluid">
        <div class="row">
            <!-- Sidebar -->
            <?php include '../includes/client_sidebar.php'; ?>

            <!-- Main content -->
            <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4">
                <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                    <h1 class="h2">
                        <i class="fas fa-receipt me-2"></i>Fee Sent Form
                        <span class="country-badge ms-3"><?php echo strtoupper($user_country); ?></span>
                    </h1>
                    <div class="btn-toolbar mb-2 mb-md-0">
                        <div class="btn-group me-2">
                            <a href="payments.php" class="btn btn-sm btn-outline-secondary">
                                <i class="fas fa-arrow-left me-1"></i>Back to Payments
                            </a>
                        </div>
                    </div>
                </div>

                <?php if (getFlashMessage()): ?>
                    <div class="alert alert-<?php echo getFlashMessage()['type']; ?> alert-dismissible fade show" role="alert">
                        <?php echo htmlspecialchars(getFlashMessage()['message']); ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>

                <!-- Application Status -->
                <div class="fee-form-card">
                    <div class="row align-items-center">
                        <div class="col-md-8">
                            <h5 class="mb-1">Application #<?php echo $application['id']; ?></h5>
                            <p class="text-muted mb-0">Status: <span class="badge bg-<?php echo $application['status'] === 'approved' ? 'success' : 'info'; ?>"><?php echo ucfirst($application['status']); ?></span></p>
                        </div>
                        <div class="col-md-4 text-end">
                            <h4 class="text-primary mb-0">$<?php echo number_format($application['loan_amount'] * 0.02, 2); ?></h4>
                            <small class="text-muted">Processing Fee (2%)</small>
                        </div>
                    </div>
                </div>

                <?php if ($can_submit && !$pending_form): ?>
                    <!-- Fee Sent Form -->
                    <div class="fee-form-card">
                        <h5 class="mb-4">
                            <i class="fas fa-paper-plane me-2"></i>Submit Fee Payment Details
                        </h5>
                        
                        <form method="POST" enctype="multipart/form-data" id="feeSentForm">
                            <!-- Payment Method Selection -->
                            <div class="form-section">
                                <h6 class="mb-3">Payment Method</h6>
                                <div class="row">
                                    <?php foreach ($available_methods as $method): ?>
                                        <div class="col-md-6">
                                            <div class="payment-method-card" onclick="selectPaymentMethod('<?php echo $method; ?>')">
                                                <input type="radio" name="payment_method" value="<?php echo $method; ?>" id="method_<?php echo $method; ?>" required style="display: none;">
                                                <div class="d-flex align-items-center">
                                                    <?php
                                                    $method_icons = [
                                                        'wire_transfer' => 'fas fa-university',
                                                        'crypto' => 'fab fa-bitcoin',
                                                        'e_transfer' => 'fas fa-envelope'
                                                    ];
                                                    $method_names = [
                                                        'wire_transfer' => 'Wire Transfer',
                                                        'crypto' => 'Cryptocurrency',
                                                        'e_transfer' => 'e-Transfer'
                                                    ];
                                                    ?>
                                                    <i class="<?php echo $method_icons[$method]; ?> fa-2x text-primary me-3"></i>
                                                    <div>
                                                        <h6 class="mb-1"><?php echo $method_names[$method]; ?></h6>
                                                        <small class="text-muted">
                                                            <?php if ($method === 'e_transfer'): ?>
                                                                Canada Only
                                                            <?php else: ?>
                                                                All Countries
                                                            <?php endif; ?>
                                                        </small>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            </div>

                            <!-- Payment Details -->
                            <div class="form-section">
                                <h6 class="mb-3">Payment Details</h6>
                                
                                <!-- Common Fields -->
                                <div class="row">
                                    <div class="col-md-6">
                                        <div class="mb-3">
                                            <label for="amount_sent" class="form-label">Amount Sent <span class="text-danger">*</span></label>
                                            <div class="input-group">
                                                <span class="input-group-text">$</span>
                                                <input type="number" class="form-control" id="amount_sent" name="amount_sent" 
                                                       value="<?php echo number_format($application['loan_amount'] * 0.02, 2, '.', ''); ?>" 
                                                       step="0.01" min="0" required>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="mb-3">
                                            <label for="date_sent" class="form-label">Date Sent <span class="text-danger">*</span></label>
                                            <input type="date" class="form-control" id="date_sent" name="date_sent" 
                                                   max="<?php echo date('Y-m-d'); ?>" required>
                                        </div>
                                    </div>
                                </div>
                                
                                <!-- Wire Transfer Specific Fields -->
                                <div id="wire_transfer_fields" class="payment-specific-fields" style="display: none;">
                                    <div class="row">
                                        <div class="col-md-6">
                                            <div class="mb-3">
                                                <label for="sending_bank" class="form-label">Sending Bank <span class="text-danger">*</span></label>
                                                <input type="text" class="form-control" id="sending_bank" name="sending_bank" 
                                                       placeholder="Name of your bank">
                                            </div>
                                        </div>
                                        <div class="col-md-6">
                                            <div class="mb-3">
                                                <label for="account_holder" class="form-label">Account Holder Name <span class="text-danger">*</span></label>
                                                <input type="text" class="form-control" id="account_holder" name="account_holder" 
                                                       placeholder="Name on the sending account">
                                            </div>
                                        </div>
                                    </div>
                                    <div class="mb-3">
                                        <label for="wire_reference" class="form-label">Wire Reference Number</label>
                                        <input type="text" class="form-control" id="wire_reference" name="wire_reference" 
                                               placeholder="Wire transfer reference or confirmation number">
                                    </div>
                                </div>
                                
                                <!-- E-Transfer Specific Fields -->
                                <div id="e_transfer_fields" class="payment-specific-fields" style="display: none;">
                                    <div class="mb-3">
                                        <label for="sending_email" class="form-label">Sending Email Address <span class="text-danger">*</span></label>
                                        <input type="email" class="form-control" id="sending_email" name="sending_email" 
                                               placeholder="Email address used to send e-Transfer">
                                    </div>
                                    <div class="mb-3">
                                        <label for="etransfer_reference" class="form-label">e-Transfer Reference</label>
                                        <input type="text" class="form-control" id="etransfer_reference" name="etransfer_reference" 
                                               placeholder="e-Transfer confirmation number or reference">
                                    </div>
                                </div>
                                
                                <!-- Cryptocurrency Specific Fields -->
                                <div id="crypto_fields" class="payment-specific-fields" style="display: none;">
                                    <div class="row">
                                        <div class="col-md-8">
                                            <div class="mb-3">
                                                <label for="transfer_id" class="form-label">Transfer ID/Transaction Hash <span class="text-danger">*</span></label>
                                                <input type="text" class="form-control" id="transfer_id" name="transfer_id" 
                                                       placeholder="Enter the blockchain transaction hash">
                                                <div class="form-text">This will be verified before submission</div>
                                            </div>
                                        </div>
                                        <div class="col-md-4">
                                            <div class="mb-3">
                                                <label class="form-label">&nbsp;</label>
                                                <button type="button" class="btn btn-outline-primary d-block" id="verify_transfer_btn" disabled>
                                                    <i class="fas fa-search me-1"></i>Verify Transfer
                                                </button>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="mb-3">
                                        <label for="sender_name" class="form-label">Sender's Name <span class="text-danger">*</span></label>
                                        <input type="text" class="form-control" id="sender_name" name="sender_name" 
                                               placeholder="Name of the person who sent the cryptocurrency">
                                    </div>
                                    <div id="verification_status" class="alert" style="display: none;"></div>
                                </div>
                            </div>

                            <!-- Receipt Upload -->
                            <div class="form-section">
                                <h6 class="mb-3">Receipt Upload (Optional)</h6>
                                <div class="mb-3">
                                    <label for="receipt_file" class="form-label">Upload Receipt or Proof of Payment</label>
                                    <input type="file" class="form-control" id="receipt_file" name="receipt_file" 
                                           accept=".jpg,.jpeg,.png,.pdf,.gif">
                                    <div class="form-text">Accepted formats: JPG, PNG, PDF, GIF (Max 5MB)</div>
                                </div>
                            </div>

                            <!-- Additional Notes -->
                            <div class="form-section">
                                <h6 class="mb-3">Additional Information</h6>
                                <div class="mb-3">
                                    <label for="additional_notes" class="form-label">Additional Notes</label>
                                    <textarea class="form-control" id="additional_notes" name="additional_notes" rows="3" 
                                              placeholder="Any additional information about your payment..."></textarea>
                                </div>
                            </div>

                            <div class="d-grid gap-2 d-md-flex justify-content-md-end">
                                <button type="submit" name="submit_fee_form" class="btn btn-primary btn-lg">
                                    <i class="fas fa-paper-plane me-2"></i>Submit Fee Form
                                </button>
                            </div>
                        </form>
                    </div>
                <?php elseif ($pending_form): ?>
                    <!-- Pending Form Notice -->
                    <div class="fee-form-card">
                        <div class="alert alert-info">
                            <h5><i class="fas fa-clock me-2"></i>Form Submitted</h5>
                            <p class="mb-0">Your fee sent form is currently being reviewed by our admin team. You will be notified once it's processed.</p>
                        </div>
                    </div>
                <?php else: ?>
                    <!-- Cannot Submit Notice -->
                    <div class="fee-form-card">
                        <div class="alert alert-warning">
                            <h5><i class="fas fa-exclamation-triangle me-2"></i>Form Not Available</h5>
                            <p class="mb-0">The fee sent form is only available after your application has been approved. Please complete the previous steps first.</p>
                        </div>
                    </div>
                <?php endif; ?>

                <!-- Previous Submissions -->
                <?php if (!empty($fee_forms)): ?>
                    <div class="fee-form-card">
                        <h5 class="mb-4">
                            <i class="fas fa-history me-2"></i>Previous Submissions
                        </h5>
                        <div class="table-responsive">
                            <table class="table table-hover">
                                <thead>
                                    <tr>
                                        <th>Date Submitted</th>
                                        <th>Payment Method</th>
                                        <th>Amount</th>
                                        <th>Date Sent</th>
                                        <th>Status</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($fee_forms as $form): ?>
                                        <tr>
                                            <td><?php echo date('M j, Y g:i A', strtotime($form['created_at'])); ?></td>
                                            <td>
                                                <i class="<?php echo $method_icons[$form['payment_method']] ?? 'fas fa-payment'; ?> me-1"></i>
                                                <?php echo $method_names[$form['payment_method']] ?? ucfirst(str_replace('_', ' ', $form['payment_method'])); ?>
                                            </td>
                                            <td>$<?php echo number_format($form['amount_sent'], 2); ?></td>
                                            <td><?php echo date('M j, Y', strtotime($form['date_sent'])); ?></td>
                                            <td>
                                                <span class="status-badge status-<?php echo $form['status']; ?>">
                                                    <?php echo ucfirst($form['status']); ?>
                                                </span>
                                            </td>
                                            <td>
                                                <?php if ($form['receipt_filename']): ?>
                                                    <a href="../uploads/fee_receipts/<?php echo $form['receipt_filename']; ?>" 
                                                       class="btn btn-sm btn-outline-primary" target="_blank">
                                                        <i class="fas fa-download"></i>
                                                    </a>
                                                <?php endif; ?>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                <?php endif; ?>
            </main>
        </div>
    </div>

    <script src="../FrontEnd_Template/js/bootstrap.bundle.min.js"></script>
    <script>
        let cryptoVerified = false;
        
        function selectPaymentMethod(method) {
            // Remove selected class from all cards
            document.querySelectorAll('.payment-method-card').forEach(card => {
                card.classList.remove('selected');
            });
            
            // Add selected class to clicked card
            event.currentTarget.classList.add('selected');
            
            // Check the radio button
            document.getElementById('method_' + method).checked = true;
            
            // Show/hide payment-specific fields
            document.querySelectorAll('.payment-specific-fields').forEach(field => {
                field.style.display = 'none';
            });
            
            if (method === 'wire_transfer') {
                document.getElementById('wire_transfer_fields').style.display = 'block';
            } else if (method === 'e_transfer') {
                document.getElementById('e_transfer_fields').style.display = 'block';
            } else if (method === 'crypto') {
                document.getElementById('crypto_fields').style.display = 'block';
                cryptoVerified = false;
                document.getElementById('verification_status').style.display = 'none';
            }
        }
        
        // Enable/disable verify button based on transfer ID input
        document.getElementById('transfer_id').addEventListener('input', function() {
            const transferId = this.value.trim();
            const verifyBtn = document.getElementById('verify_transfer_btn');
            verifyBtn.disabled = transferId.length < 10; // Minimum length for crypto hash
            cryptoVerified = false;
            document.getElementById('verification_status').style.display = 'none';
        });
        
        // Crypto transfer verification
        document.getElementById('verify_transfer_btn').addEventListener('click', function() {
            const transferId = document.getElementById('transfer_id').value.trim();
            const statusDiv = document.getElementById('verification_status');
            const btn = this;
            
            if (!transferId) {
                alert('Please enter a transfer ID first.');
                return;
            }
            
            // Show loading state
            btn.innerHTML = '<i class="fas fa-spinner fa-spin me-1"></i>Verifying...';
            btn.disabled = true;
            
            // Simulate API call for crypto verification
            // In a real implementation, this would call a blockchain API
            setTimeout(() => {
                // Mock verification logic - in reality this would verify against blockchain
                const isValid = transferId.length >= 40 && /^[a-fA-F0-9]+$/.test(transferId);
                
                if (isValid) {
                    cryptoVerified = true;
                    statusDiv.className = 'alert alert-success';
                    statusDiv.innerHTML = '<i class="fas fa-check-circle me-1"></i>Transfer ID verified successfully!';
                    btn.innerHTML = '<i class="fas fa-check me-1"></i>Verified';
                    btn.className = 'btn btn-success d-block';
                } else {
                    cryptoVerified = false;
                    statusDiv.className = 'alert alert-danger';
                    statusDiv.innerHTML = '<i class="fas fa-times-circle me-1"></i>Invalid transfer ID. Please check and try again.';
                    btn.innerHTML = '<i class="fas fa-search me-1"></i>Verify Transfer';
                    btn.className = 'btn btn-outline-primary d-block';
                    btn.disabled = false;
                }
                
                statusDiv.style.display = 'block';
            }, 2000); // 2 second delay to simulate API call
        });

        // Form validation
        document.getElementById('feeSentForm').addEventListener('submit', function(e) {
            const paymentMethod = document.querySelector('input[name="payment_method"]:checked');
            if (!paymentMethod) {
                e.preventDefault();
                alert('Please select a payment method.');
                return false;
            }
            
            const amountSent = document.getElementById('amount_sent').value;
            const dateSent = document.getElementById('date_sent').value;
            
            if (!amountSent || !dateSent) {
                e.preventDefault();
                alert('Please fill in all required fields.');
                return false;
            }
            
            // Validate payment-specific required fields
            const method = paymentMethod.value;
            if (method === 'wire_transfer') {
                const sendingBank = document.getElementById('sending_bank').value;
                const accountHolder = document.getElementById('account_holder').value;
                if (!sendingBank || !accountHolder) {
                    e.preventDefault();
                    alert('Please fill in all required Wire Transfer fields.');
                    return false;
                }
            } else if (method === 'e_transfer') {
                const sendingEmail = document.getElementById('sending_email').value;
                if (!sendingEmail) {
                    e.preventDefault();
                    alert('Please fill in the sending email address for e-Transfer.');
                    return false;
                }
            } else if (method === 'crypto') {
                const transferId = document.getElementById('transfer_id').value;
                const senderName = document.getElementById('sender_name').value;
                if (!transferId || !senderName) {
                    e.preventDefault();
                    alert('Please fill in all required Cryptocurrency fields.');
                    return false;
                }
                if (!cryptoVerified) {
                    e.preventDefault();
                    alert('Please verify the transfer ID before submitting.');
                    return false;
                }
            }
            
            // Confirm submission
            if (!confirm('Are you sure you want to submit this fee form? Please ensure all information is correct.')) {
                e.preventDefault();
                return false;
            }
        });

        // File upload validation
        document.getElementById('receipt_file').addEventListener('change', function() {
            const file = this.files[0];
            if (file) {
                const maxSize = 5 * 1024 * 1024; // 5MB
                if (file.size > maxSize) {
                    alert('File size must be less than 5MB.');
                    this.value = '';
                    return;
                }
                
                const allowedTypes = ['image/jpeg', 'image/jpg', 'image/png', 'application/pdf', 'image/gif'];
                if (!allowedTypes.includes(file.type)) {
                    alert('Please upload a valid file type (JPG, PNG, PDF, GIF).');
                    this.value = '';
                    return;
                }
            }
        });

        // Set default date to today
        document.getElementById('date_sent').value = new Date().toISOString().split('T')[0];
    </script>
</body>
</html>