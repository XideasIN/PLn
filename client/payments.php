<?php
/**
 * Payment Processing Interface
 * LoanFlow Personal Loan Management System
 */

require_once '../config/database.php';
require_once '../includes/functions.php';
require_once '../includes/auth.php';
require_once '../includes/enhanced_payment.php';
require_once '../includes/language.php';

// Require client login
requireLogin();

// Initialize language
LanguageManager::init();

$current_user = getCurrentUser();
$error = '';
$success = '';
$payment_created = false;
$payment_instructions = null;

// Get user's application
try {
    $db = getDB();
    $stmt = $db->prepare("
        SELECT * FROM loan_applications 
        WHERE user_id = ? 
        ORDER BY created_at DESC 
        LIMIT 1
    ");
    $stmt->execute([$current_user['id']]);
    $application = $stmt->fetch();
    
    if (!$application) {
        header('Location: index.php');
        exit();
    }
    
    // Get user's payment scheme
    $payment_scheme = EnhancedPaymentManager::getUserPaymentScheme($current_user['id']);
    
    // Get available payment methods for user's country
    $available_methods = EnhancedPaymentManager::getAvailablePaymentMethods($current_user['country']);
    
    // Get existing payments
    $stmt = $db->prepare("
        SELECT * FROM payments 
        WHERE user_id = ? AND application_id = ? 
        ORDER BY created_at DESC
    ");
    $stmt->execute([$current_user['id'], $application['id']]);
    $existing_payments = $stmt->fetchAll();
    
} catch (Exception $e) {
    error_log("Payment page data fetch failed: " . $e->getMessage());
    $error = __('data_fetch_failed');
}

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCSRFToken($_POST['csrf_token'] ?? '')) {
        $error = __('invalid_csrf_token');
    } else {
        $action = $_POST['action'] ?? '';
        
        switch ($action) {
            case 'create_payment':
                $payment_method = sanitizeInput($_POST['payment_method'] ?? '');
                
                if (empty($payment_method)) {
                    $error = __('payment_method_required');
                } elseif (!array_key_exists($payment_method, $available_methods)) {
                    $error = __('invalid_payment_method');
                } else {
                    $result = EnhancedPaymentManager::createEnhancedPayment(
                        $current_user['id'], 
                        $application['id'], 
                        $payment_method, 
                        $payment_scheme
                    );
                    
                    if ($result['success']) {
                        $payment_created = true;
                        $payment_instructions = EnhancedPaymentManager::getPaymentMethodConfig($payment_method);
                        $success = __('payment_created_successfully');
                        
                        // Refresh existing payments
                        $stmt = $db->prepare("
                            SELECT * FROM payments 
                            WHERE user_id = ? AND application_id = ? 
                            ORDER BY created_at DESC
                        ");
                        $stmt->execute([$current_user['id'], $application['id']]);
                        $existing_payments = $stmt->fetchAll();
                    } else {
                        $error = $result['error'];
                    }
                }
                break;
                
            case 'submit_confirmation':
                $payment_id = intval($_POST['payment_id'] ?? 0);
                $confirmation_details = sanitizeInput($_POST['confirmation_details'] ?? '');
                $reference_number = sanitizeInput($_POST['reference_number'] ?? '');
                $transaction_date = sanitizeInput($_POST['transaction_date'] ?? '');
                
                $confirmation_data = [
                    'reference_number' => $reference_number,
                    'transaction_date' => $transaction_date,
                    'details' => $confirmation_details,
                    'submitted_at' => date('Y-m-d H:i:s')
                ];
                
                $uploaded_file = $_FILES['confirmation_image'] ?? null;
                
                $result = EnhancedPaymentManager::submitPaymentConfirmation(
                    $payment_id, 
                    $current_user['id'], 
                    $confirmation_data, 
                    $uploaded_file
                );
                
                if ($result['success']) {
                    $success = $result['message'];
                    
                    // Refresh payments
                    $stmt = $db->prepare("
                        SELECT * FROM payments 
                        WHERE user_id = ? AND application_id = ? 
                        ORDER BY created_at DESC
                    ");
                    $stmt->execute([$current_user['id'], $application['id']]);
                    $existing_payments = $stmt->fetchAll();
                } else {
                    $error = $result['error'];
                }
                break;
                
            case 'verify_subscription':
                $verification_code = sanitizeInput($_POST['verification_code'] ?? '');
                
                if (empty($verification_code)) {
                    $error = __('2fa_code_required');
                } else {
                    $result = EnhancedPaymentManager::verifySubscriptionAssignment($current_user['id'], $verification_code);
                    
                    if ($result['success']) {
                        $success = $result['message'];
                        
                        // Refresh payment scheme
                        $payment_scheme = EnhancedPaymentManager::getUserPaymentScheme($current_user['id']);
                    } else {
                        $error = $result['error'];
                    }
                }
                break;
        }
    }
}

// Calculate payment amount
$payment_amount = 0;
if ($payment_scheme) {
    if ($payment_scheme['scheme_type'] === 'subscription') {
        $payment_amount = $payment_scheme['subscription_fee'];
    } else {
        $payment_amount = ($application['loan_amount'] * $payment_scheme['percentage_fee']) / 100;
        
        // Apply min/max limits
        if (isset($payment_scheme['percentage_min_fee']) && $payment_amount < $payment_scheme['percentage_min_fee']) {
            $payment_amount = $payment_scheme['percentage_min_fee'];
        }
        if (isset($payment_scheme['percentage_max_fee']) && $payment_amount > $payment_scheme['percentage_max_fee']) {
            $payment_amount = $payment_scheme['percentage_max_fee'];
        }
    }
}

?>
<!DOCTYPE html>
<html lang="<?= LanguageManager::getCurrentLanguage() ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= __('payment_processing') ?> - LoanFlow</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="../assets/css/style.css" rel="stylesheet">
</head>
<body>
    <!-- Navigation -->
    <nav class="navbar navbar-expand-lg navbar-dark bg-primary">
        <div class="container">
            <a class="navbar-brand" href="index.php">
                <img src="../logo.png" alt="LoanFlow" height="40" class="me-2">
                LoanFlow
            </a>
            <div class="navbar-nav ms-auto">
                <?= LanguageManager::getLanguageSelector() ?>
                <a class="nav-link" href="dashboard.php">
                    <i class="fas fa-arrow-left me-1"></i><?= __('back_to_dashboard') ?>
                </a>
            </div>
        </div>
    </nav>

    <div class="container py-4">
        <!-- Flash Messages -->
        <?php if ($error): ?>
            <div class="alert alert-danger alert-dismissible fade show">
                <i class="fas fa-exclamation-circle me-2"></i><?= htmlspecialchars($error) ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>
        
        <?php if ($success): ?>
            <div class="alert alert-success alert-dismissible fade show">
                <i class="fas fa-check-circle me-2"></i><?= htmlspecialchars($success) ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <div class="row">
            <div class="col-lg-8">
                <!-- Payment Scheme Information -->
                <div class="card mb-4">
                    <div class="card-header">
                        <h5 class="mb-0">
                            <i class="fas fa-info-circle me-2"></i><?= __('payment_scheme_info') ?>
                        </h5>
                    </div>
                    <div class="card-body">
                        <?php if ($payment_scheme): ?>
                            <?php if ($payment_scheme['scheme_type'] === 'subscription'): ?>
                                <div class="alert alert-info">
                                    <h6><?= __('subscription_plan') ?></h6>
                                    <p class="mb-1"><?= __('monthly_fee') ?>: <strong><?= formatCurrency($payment_scheme['subscription_fee'], $current_user['country']) ?></strong></p>
                                    <p class="mb-1"><?= __('max_duration') ?>: <strong><?= $payment_scheme['max_subscription_months'] ?> <?= __('months') ?></strong></p>
                                    <p class="mb-0"><?= __('refund_policy') ?>: <?= $payment_scheme['refund_policy_subscription'] ?>%</p>
                                    
                                    <?php if (isset($payment_scheme['requires_2fa']) && $payment_scheme['requires_2fa'] && !$payment_scheme['2fa_verified']): ?>
                                        <hr>
                                        <div class="alert alert-warning">
                                            <h6><?= __('verification_required') ?></h6>
                                            <p><?= __('subscription_2fa_required') ?></p>
                                            
                                            <form method="POST" class="mt-3">
                                                <input type="hidden" name="csrf_token" value="<?= generateCSRFToken() ?>">
                                                <input type="hidden" name="action" value="verify_subscription">
                                                
                                                <div class="row align-items-end">
                                                    <div class="col-md-6">
                                                        <label for="verification_code" class="form-label"><?= __('2fa_verification_code') ?></label>
                                                        <input type="text" class="form-control" id="verification_code" name="verification_code" 
                                                               placeholder="000000" maxlength="6" required pattern="[0-9]{6}">
                                                    </div>
                                                    <div class="col-md-6">
                                                        <button type="submit" class="btn btn-primary">
                                                            <i class="fas fa-check me-2"></i><?= __('verify_subscription') ?>
                                                        </button>
                                                    </div>
                                                </div>
                                            </form>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            <?php else: ?>
                                <div class="alert alert-success">
                                    <h6><?= __('percentage_plan') ?></h6>
                                    <p class="mb-1"><?= __('fee_rate') ?>: <strong><?= $payment_scheme['percentage_fee'] ?>%</strong></p>
                                    <p class="mb-1"><?= __('calculated_fee') ?>: <strong><?= formatCurrency($payment_amount, $current_user['country']) ?></strong></p>
                                    <p class="mb-0"><?= __('refund_policy') ?>: <?= $payment_scheme['refund_policy_percentage'] ?>%</p>
                                </div>
                            <?php endif; ?>
                        <?php else: ?>
                            <div class="alert alert-warning">
                                <p class="mb-0"><?= __('no_payment_scheme_assigned') ?></p>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Payment Methods -->
                <?php if (!empty($available_methods) && $payment_scheme && (!isset($payment_scheme['requires_2fa']) || $payment_scheme['2fa_verified'])): ?>
                <div class="card mb-4">
                    <div class="card-header">
                        <h5 class="mb-0">
                            <i class="fas fa-credit-card me-2"></i><?= __('select_payment_method') ?>
                        </h5>
                    </div>
                    <div class="card-body">
                        <form method="POST">
                            <input type="hidden" name="csrf_token" value="<?= generateCSRFToken() ?>">
                            <input type="hidden" name="action" value="create_payment">
                            
                            <div class="row">
                                <?php foreach ($available_methods as $method => $config): ?>
                                <div class="col-md-6 mb-3">
                                    <div class="card payment-method-card" data-method="<?= $method ?>">
                                        <div class="card-body text-center">
                                            <input type="radio" name="payment_method" value="<?= $method ?>" id="method_<?= $method ?>" class="form-check-input payment-method-radio">
                                            <label for="method_<?= $method ?>" class="payment-method-label">
                                                <?php
                                                $method_icons = [
                                                    'wire_transfer' => 'fas fa-university',
                                                    'crypto' => 'fab fa-bitcoin',
                                                    'e_transfer' => 'fas fa-envelope',
                                                    'credit_card' => 'fas fa-credit-card'
                                                ];
                                                ?>
                                                <i class="<?= $method_icons[$method] ?? 'fas fa-payment' ?> fa-2x mb-2 d-block"></i>
                                                <h6><?= __(str_replace('_', '_', $method)) ?></h6>
                                                
                                                <?php if ($method === 'wire_transfer'): ?>
                                                    <small class="text-muted"><?= __('bank_transfer_description') ?></small>
                                                <?php elseif ($method === 'crypto'): ?>
                                                    <small class="text-muted"><?= __('cryptocurrency_description') ?></small>
                                                <?php elseif ($method === 'e_transfer'): ?>
                                                    <small class="text-muted"><?= __('email_transfer_description') ?></small>
                                                <?php elseif ($method === 'credit_card'): ?>
                                                    <small class="text-muted"><?= __('credit_card_description') ?></small>
                                                <?php endif; ?>
                                            </label>
                                        </div>
                                    </div>
                                </div>
                                <?php endforeach; ?>
                            </div>
                            
                            <div class="payment-summary mb-3" id="payment-summary" style="display: none;">
                                <div class="alert alert-light">
                                    <h6><?= __('payment_summary') ?></h6>
                                    <div class="d-flex justify-content-between">
                                        <span><?= __('amount_due') ?>:</span>
                                        <strong><?= formatCurrency($payment_amount, $current_user['country']) ?></strong>
                                    </div>
                                    <div class="d-flex justify-content-between">
                                        <span><?= __('payment_method') ?>:</span>
                                        <span id="selected-method-name"></span>
                                    </div>
                                </div>
                            </div>
                            
                            <button type="submit" class="btn btn-primary btn-lg w-100" id="create-payment-btn" disabled>
                                <i class="fas fa-arrow-right me-2"></i><?= __('proceed_with_payment') ?>
                            </button>
                        </form>
                    </div>
                </div>
                <?php endif; ?>

                <!-- Payment Instructions (if payment was just created) -->
                <?php if ($payment_created && $payment_instructions): ?>
                <div class="card mb-4">
                    <div class="card-header bg-success text-white">
                        <h5 class="mb-0">
                            <i class="fas fa-info-circle me-2"></i><?= __('payment_instructions') ?>
                        </h5>
                    </div>
                    <div class="card-body">
                        <div class="instructions-content">
                            <?= nl2br(htmlspecialchars($payment_instructions['instructions'])) ?>
                        </div>
                        
                        <?php if (!empty($payment_instructions['config'])): ?>
                        <hr>
                        <h6><?= __('payment_details') ?></h6>
                        <div class="payment-details">
                            <?php foreach ($payment_instructions['config'] as $key => $value): ?>
                                <?php if (!empty($value)): ?>
                                <div class="detail-item">
                                    <strong><?= __(str_replace('_', '_', $key)) ?>:</strong> 
                                    <span class="copyable" onclick="copyToClipboard('<?= htmlspecialchars($value) ?>')"><?= htmlspecialchars($value) ?></span>
                                    <i class="fas fa-copy ms-1 text-muted copy-icon" style="cursor: pointer;" onclick="copyToClipboard('<?= htmlspecialchars($value) ?>')" title="<?= __('click_to_copy') ?>"></i>
                                </div>
                                <?php endif; ?>
                            <?php endforeach; ?>
                        </div>
                        <?php endif; ?>
                        
                        <div class="alert alert-info mt-3">
                            <i class="fas fa-exclamation-triangle me-2"></i>
                            <?= __('payment_confirmation_required') ?>
                        </div>
                    </div>
                </div>
                <?php endif; ?>

                <!-- Existing Payments -->
                <?php if (!empty($existing_payments)): ?>
                <div class="card">
                    <div class="card-header">
                        <h5 class="mb-0">
                            <i class="fas fa-history me-2"></i><?= __('payment_history') ?>
                        </h5>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-hover">
                                <thead>
                                    <tr>
                                        <th><?= __('payment_id') ?></th>
                                        <th><?= __('amount') ?></th>
                                        <th><?= __('method') ?></th>
                                        <th><?= __('status') ?></th>
                                        <th><?= __('created') ?></th>
                                        <th><?= __('actions') ?></th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($existing_payments as $payment): ?>
                                    <tr>
                                        <td>#<?= $payment['id'] ?></td>
                                        <td><?= formatCurrency($payment['amount'], $current_user['country']) ?></td>
                                        <td>
                                            <i class="<?= $method_icons[$payment['payment_method']] ?? 'fas fa-payment' ?> me-1"></i>
                                            <?= __(str_replace('_', '_', $payment['payment_method'])) ?>
                                        </td>
                                        <td>
                                            <?php
                                            $status_classes = [
                                                'pending' => 'warning',
                                                'processing' => 'info',
                                                'completed' => 'success',
                                                'failed' => 'danger',
                                                'refunded' => 'secondary'
                                            ];
                                            ?>
                                            <span class="badge bg-<?= $status_classes[$payment['payment_status']] ?? 'secondary' ?>">
                                                <?= __(str_replace('_', '_', $payment['payment_status'])) ?>
                                            </span>
                                        </td>
                                        <td><?= date('M j, Y', strtotime($payment['created_at'])) ?></td>
                                        <td>
                                            <?php if ($payment['payment_status'] === 'pending' && in_array($payment['payment_method'], ['wire_transfer', 'e_transfer'])): ?>
                                                <button class="btn btn-sm btn-outline-primary" onclick="showConfirmationForm(<?= $payment['id'] ?>)">
                                                    <i class="fas fa-upload me-1"></i><?= __('submit_confirmation') ?>
                                                </button>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
                <?php endif; ?>
            </div>

            <!-- Sidebar -->
            <div class="col-lg-4">
                <div class="card">
                    <div class="card-header">
                        <h6 class="mb-0">
                            <i class="fas fa-question-circle me-2"></i><?= __('payment_help') ?>
                        </h6>
                    </div>
                    <div class="card-body">
                        <div class="help-section">
                            <h6><?= __('wire_transfer') ?></h6>
                            <p class="small text-muted"><?= __('wire_transfer_help') ?></p>
                        </div>
                        
                        <div class="help-section">
                            <h6><?= __('cryptocurrency') ?></h6>
                            <p class="small text-muted"><?= __('crypto_help') ?></p>
                        </div>
                        
                        <div class="help-section">
                            <h6><?= __('e_transfer') ?></h6>
                            <p class="small text-muted"><?= __('e_transfer_help') ?></p>
                        </div>
                        
                        <hr>
                        
                        <div class="contact-support">
                            <h6><?= __('need_help') ?></h6>
                            <p class="small text-muted"><?= __('contact_support_help') ?></p>
                            <a href="mailto:<?= getSystemSetting('admin_email', 'support@loanflow.com') ?>" class="btn btn-outline-primary btn-sm">
                                <i class="fas fa-envelope me-1"></i><?= __('contact_support') ?>
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Payment Confirmation Modal -->
    <div class="modal fade" id="confirmationModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">
                        <i class="fas fa-upload me-2"></i><?= __('submit_payment_confirmation') ?>
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST" enctype="multipart/form-data" id="confirmationForm">
                    <div class="modal-body">
                        <input type="hidden" name="csrf_token" value="<?= generateCSRFToken() ?>">
                        <input type="hidden" name="action" value="submit_confirmation">
                        <input type="hidden" name="payment_id" id="confirmation_payment_id">
                        
                        <div class="alert alert-info">
                            <i class="fas fa-info-circle me-2"></i>
                            <?= __('confirmation_upload_help') ?>
                        </div>
                        
                        <div class="form-section">
                            <h6><i class="fas fa-receipt me-2"></i><?= __('transaction_details') ?></h6>
                            
                            <div class="form-floating mb-3">
                                <input type="text" class="form-control" id="reference_number" name="reference_number" 
                                       placeholder="<?= __('transaction_reference') ?>" required minlength="3">
                                <label for="reference_number">
                                    <?= __('transaction_reference') ?>
                                    <span class="text-danger">*</span>
                                </label>
                                <div class="form-text"><?= __('reference_number_help') ?></div>
                            </div>
                            
                            <div class="form-floating mb-3">
                                <input type="date" class="form-control" id="transaction_date" name="transaction_date" 
                                       placeholder="<?= __('transaction_date') ?>" required max="<?= date('Y-m-d') ?>">
                                <label for="transaction_date">
                                    <?= __('transaction_date') ?>
                                    <span class="text-danger">*</span>
                                </label>
                            </div>
                        </div>
                        
                        <div class="form-section">
                            <h6><i class="fas fa-upload me-2"></i><?= __('upload_confirmation') ?></h6>
                            
                            <div class="mb-3">
                                <label for="confirmation_image" class="form-label">
                                    <?= __('confirmation_image') ?>
                                    <span class="text-danger">*</span>
                                </label>
                                <input type="file" class="form-control" id="confirmation_image" name="confirmation_image" 
                                       accept="image/*,.pdf" required>
                                <div class="form-text">
                                    <i class="fas fa-info-circle me-1"></i><?= __('upload_receipt_help') ?>
                                </div>
                            </div>
                        </div>
                        
                        <div class="form-section">
                            <h6><i class="fas fa-comment me-2"></i><?= __('additional_information') ?></h6>
                            
                            <div class="form-floating">
                                <textarea class="form-control" id="confirmation_details" name="confirmation_details" 
                                          placeholder="<?= __('additional_details_placeholder') ?>" style="height: 100px" maxlength="500"></textarea>
                                <label for="confirmation_details"><?= __('additional_details') ?></label>
                                <div class="form-text">
                                    <small class="text-muted">
                                        <span id="char-count">0</span>/500 <?= __('characters') ?>
                                    </small>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal"><?= __('cancel') ?></button>
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-upload me-2"></i><?= __('submit_confirmation') ?>
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Enhanced Payment method selection with validation
        document.querySelectorAll('.payment-method-radio').forEach(radio => {
            radio.addEventListener('change', function() {
                const paymentSummary = document.getElementById('payment-summary');
                const createPaymentBtn = document.getElementById('create-payment-btn');
                const selectedMethodName = document.getElementById('selected-method-name');
                
                if (this.checked) {
                    // Show payment summary with animation
                    paymentSummary.classList.add('fade-in');
                    paymentSummary.style.display = 'block';
                    createPaymentBtn.disabled = false;
                    
                    const methodNames = {
                        'wire_transfer': '<?= __('wire_transfer') ?>',
                        'crypto': '<?= __('cryptocurrency') ?>',
                        'e_transfer': '<?= __('e_transfer') ?>',
                        'credit_card': '<?= __('credit_card') ?>'
                    };
                    
                    selectedMethodName.textContent = methodNames[this.value] || this.value;
                    
                    // Enhanced card highlighting
                    document.querySelectorAll('.payment-method-card').forEach(card => {
                        card.classList.remove('selected', 'border-primary');
                    });
                    this.closest('.payment-method-card').classList.add('selected', 'border-primary');
                    
                    // Scroll to summary on mobile
                    if (window.innerWidth <= 768) {
                        setTimeout(() => {
                            paymentSummary.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
                        }, 300);
                    }
                }
            });
        });
        
        // Show confirmation form
        function showConfirmationForm(paymentId) {
            document.getElementById('confirmation_payment_id').value = paymentId;
            const modal = new bootstrap.Modal(document.getElementById('confirmationModal'));
            modal.show();
        }
        
        // Copy to clipboard
        function copyToClipboard(text) {
            navigator.clipboard.writeText(text).then(function() {
                // Show temporary success message
                const toast = document.createElement('div');
                toast.className = 'toast-notification';
                toast.textContent = '<?= __('copied_to_clipboard') ?>';
                toast.style.cssText = 'position: fixed; top: 20px; right: 20px; background: #28a745; color: white; padding: 10px 15px; border-radius: 5px; z-index: 9999;';
                document.body.appendChild(toast);
                
                setTimeout(() => {
                    toast.remove();
                }, 2000);
            });
        }
        
        // Enhanced form validation
        function setupFormValidation() {
            const confirmationForm = document.getElementById('confirmationForm');
            if (confirmationForm) {
                confirmationForm.addEventListener('submit', function(e) {
                    if (!validateConfirmationForm(this)) {
                        e.preventDefault();
                        return false;
                    }
                    showLoadingState(this);
                });
                
                // Real-time validation
                const inputs = confirmationForm.querySelectorAll('input, textarea');
                inputs.forEach(input => {
                    input.addEventListener('blur', () => validateField(input));
                    input.addEventListener('input', () => clearFieldError(input));
                });
                
                // File input special handling
                const fileInput = document.getElementById('confirmation_image');
                if (fileInput) {
                    fileInput.addEventListener('change', handleFileUpload);
                }
            }
        }
        
        function validateConfirmationForm(form) {
            let isValid = true;
            const errors = [];
            
            // Reference number validation
            const refInput = form.querySelector('#reference_number');
            if (refInput) {
                const value = refInput.value.trim();
                if (!value) {
                    showFieldError(refInput, 'Reference number is required');
                    errors.push('Reference number is required');
                    isValid = false;
                } else if (value.length < 3) {
                    showFieldError(refInput, 'Reference number must be at least 3 characters');
                    errors.push('Reference number too short');
                    isValid = false;
                } else {
                    showFieldSuccess(refInput);
                }
            }
            
            // Transaction date validation
            const dateInput = form.querySelector('#transaction_date');
            if (dateInput) {
                const value = dateInput.value;
                if (!value) {
                    showFieldError(dateInput, 'Transaction date is required');
                    errors.push('Transaction date is required');
                    isValid = false;
                } else if (new Date(value) > new Date()) {
                    showFieldError(dateInput, 'Transaction date cannot be in the future');
                    errors.push('Invalid transaction date');
                    isValid = false;
                } else {
                    showFieldSuccess(dateInput);
                }
            }
            
            // File validation
            const fileInput = form.querySelector('#confirmation_image');
            if (fileInput && fileInput.hasAttribute('required')) {
                if (fileInput.files.length === 0) {
                    showFieldError(fileInput, 'Please upload a confirmation image');
                    errors.push('Confirmation image is required');
                    isValid = false;
                } else {
                    const file = fileInput.files[0];
                    const maxSize = 10 * 1024 * 1024; // 10MB
                    const allowedTypes = ['image/jpeg', 'image/png', 'image/gif', 'application/pdf'];
                    
                    if (file.size > maxSize) {
                        showFieldError(fileInput, 'File size must be less than 10MB');
                        errors.push('File too large');
                        isValid = false;
                    } else if (!allowedTypes.includes(file.type)) {
                        showFieldError(fileInput, 'Please upload a valid image or PDF file');
                        errors.push('Invalid file type');
                        isValid = false;
                    } else {
                        showFieldSuccess(fileInput);
                    }
                }
            }
            
            if (!isValid) {
                showNotification('Please correct the errors below and try again.', 'error');
                focusFirstError();
            }
            
            return isValid;
        }
        
        function validateField(field) {
            const value = field.value.trim();
            
            clearFieldError(field);
            
            if (field.hasAttribute('required') && !value) {
                showFieldError(field, 'This field is required');
                return false;
            }
            
            if (field.type === 'email' && value && !isValidEmail(value)) {
                showFieldError(field, 'Please enter a valid email address');
                return false;
            }
            
            if (field.type === 'date' && value && new Date(value) > new Date()) {
                showFieldError(field, 'Date cannot be in the future');
                return false;
            }
            
            if (value) {
                showFieldSuccess(field);
            }
            
            return true;
        }
        
        function showFieldError(field, message) {
            field.classList.remove('is-valid');
            field.classList.add('is-invalid');
            
            let feedback = field.parentNode.querySelector('.invalid-feedback');
            if (!feedback) {
                feedback = document.createElement('div');
                feedback.className = 'invalid-feedback';
                field.parentNode.appendChild(feedback);
            }
            feedback.textContent = message;
        }
        
        function showFieldSuccess(field) {
            field.classList.remove('is-invalid');
            field.classList.add('is-valid');
            
            const feedback = field.parentNode.querySelector('.invalid-feedback');
            if (feedback) {
                feedback.remove();
            }
        }
        
        function clearFieldError(field) {
            field.classList.remove('is-invalid', 'is-valid');
            const feedback = field.parentNode.querySelector('.invalid-feedback');
            if (feedback) {
                feedback.remove();
            }
        }
        
        function handleFileUpload(event) {
            const input = event.target;
            const file = input.files[0];
            
            if (!file) return;
            
            // Show file info
            displayFileInfo(input, file);
            
            // Validate file
            validateField(input);
        }
        
        function displayFileInfo(input, file) {
            let fileInfo = input.parentNode.querySelector('.file-info');
            if (!fileInfo) {
                fileInfo = document.createElement('div');
                fileInfo.className = 'file-info mt-2 p-2 bg-light border rounded fade-in';
                input.parentNode.appendChild(fileInfo);
            }
            
            const fileSize = formatFileSize(file.size);
            fileInfo.innerHTML = `
                <small>
                    <i class="fas fa-file me-1 text-primary"></i>
                    <strong>${file.name}</strong><br>
                    <span class="text-muted">Size: ${fileSize} | Type: ${file.type}</span>
                </small>
            `;
        }
        
        function formatFileSize(bytes) {
            if (bytes === 0) return '0 Bytes';
            const k = 1024;
            const sizes = ['Bytes', 'KB', 'MB', 'GB'];
            const i = Math.floor(Math.log(bytes) / Math.log(k));
            return parseFloat((bytes / Math.pow(k, i)).toFixed(2)) + ' ' + sizes[i];
        }
        
        function showLoadingState(form) {
            const submitButton = form.querySelector('button[type="submit"]');
            if (submitButton) {
                const originalText = submitButton.innerHTML;
                submitButton.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>Processing...';
                submitButton.disabled = true;
                submitButton.dataset.originalText = originalText;
            }
        }
        
        function showNotification(message, type = 'info') {
            const notification = document.createElement('div');
            notification.className = `alert alert-${type === 'error' ? 'danger' : type} alert-dismissible fade show notification-toast`;
            notification.style.cssText = 'position: fixed; top: 20px; right: 20px; z-index: 9999; min-width: 300px; max-width: 400px;';
            notification.innerHTML = `
                <i class="fas fa-${type === 'error' ? 'exclamation-circle' : 'info-circle'} me-2"></i>
                ${message}
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            `;
            
            document.body.appendChild(notification);
            
            setTimeout(() => {
                if (notification.parentNode) {
                    notification.remove();
                }
            }, 5000);
        }
        
        function focusFirstError() {
            const firstError = document.querySelector('.is-invalid');
            if (firstError) {
                firstError.focus();
                firstError.scrollIntoView({ behavior: 'smooth', block: 'center' });
            }
        }
        
        function isValidEmail(email) {
            return /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email);
        }
        
        // Character counter for textarea
        function setupCharacterCounter() {
            const textarea = document.getElementById('confirmation_details');
            const counter = document.getElementById('char-count');
            
            if (textarea && counter) {
                textarea.addEventListener('input', function() {
                    const count = this.value.length;
                    counter.textContent = count;
                    
                    if (count > 450) {
                        counter.style.color = '#dc3545';
                    } else if (count > 400) {
                        counter.style.color = '#fd7e14';
                    } else {
                        counter.style.color = '#6c757d';
                    }
                });
            }
        }
        
        // Initialize validation when page loads
        document.addEventListener('DOMContentLoaded', function() {
            setupFormValidation();
            setupCharacterCounter();
        });
        
        // Language change function
        function changeLanguage(lang) {
            const form = document.createElement('form');
            form.method = 'POST';
            form.action = '../change-language.php';
            
            const langInput = document.createElement('input');
            langInput.type = 'hidden';
            langInput.name = 'language';
            langInput.value = lang;
            
            const redirectInput = document.createElement('input');
            redirectInput.type = 'hidden';
            redirectInput.name = 'redirect';
            redirectInput.value = window.location.href;
            
            form.appendChild(langInput);
            form.appendChild(redirectInput);
            document.body.appendChild(form);
            form.submit();
        }
    </script>
    
    <style>
        /* Payment Method Cards */
        .payment-method-card {
            cursor: pointer;
            transition: all 0.3s ease;
            border: 2px solid #dee2e6;
            min-height: 140px;
            display: flex;
            align-items: center;
        }
        
        .payment-method-card:hover {
            border-color: #0d6efd;
            box-shadow: 0 0.125rem 0.25rem rgba(0, 0, 0, 0.075);
            transform: translateY(-2px);
        }
        
        .payment-method-card.selected {
            border-color: #0d6efd;
            background-color: #f8f9ff;
            box-shadow: 0 0.25rem 0.5rem rgba(13, 110, 253, 0.15);
        }
        
        .payment-method-label {
            cursor: pointer;
            width: 100%;
            margin: 0;
            padding: 1rem;
        }
        
        .payment-method-radio {
            position: absolute;
            opacity: 0;
        }
        
        /* Payment Instructions */
        .copyable {
            font-family: 'Monaco', 'Menlo', 'Ubuntu Mono', monospace;
            background: #f8f9fa;
            padding: 8px 12px;
            border-radius: 6px;
            cursor: pointer;
            display: inline-block;
            margin: 2px 0;
            transition: all 0.2s ease;
            border: 1px solid #e9ecef;
        }
        
        .copyable:hover {
            background: #e9ecef;
            border-color: #0d6efd;
        }
        
        .copy-icon:hover {
            color: #0d6efd !important;
            transform: scale(1.1);
        }
        
        /* Form Styling */
        .form-section {
            background: #f8f9fa;
            border-radius: 10px;
            padding: 1.5rem;
            margin-bottom: 1.5rem;
            border: 1px solid #e9ecef;
        }
        
        .form-section h6 {
            color: #495057;
            margin-bottom: 1rem;
            font-weight: 600;
        }
        
        .form-floating {
            margin-bottom: 1rem;
        }
        
        .form-control:focus {
            border-color: #0d6efd;
            box-shadow: 0 0 0 0.2rem rgba(13, 110, 253, 0.25);
        }
        
        /* Validation Styling */
        .form-control.is-invalid {
            border-color: #dc3545;
            box-shadow: 0 0 0 0.2rem rgba(220, 53, 69, 0.25);
        }
        
        .form-control.is-valid {
            border-color: #198754;
            box-shadow: 0 0 0 0.2rem rgba(25, 135, 84, 0.25);
        }
        
        .invalid-feedback {
            display: block;
            width: 100%;
            margin-top: 0.25rem;
            font-size: 0.875rem;
            color: #dc3545;
        }
        
        .valid-feedback {
            display: block;
            width: 100%;
            margin-top: 0.25rem;
            font-size: 0.875rem;
            color: #198754;
        }
        
        /* Progress Indicators */
        .step-indicator {
            display: flex;
            justify-content: space-between;
            margin-bottom: 2rem;
            padding: 0 1rem;
        }
        
        .step {
            flex: 1;
            text-align: center;
            position: relative;
        }
        
        .step:not(:last-child)::after {
            content: '';
            position: absolute;
            top: 15px;
            right: -50%;
            width: 100%;
            height: 2px;
            background: #dee2e6;
            z-index: 0;
        }
        
        .step.active::after {
            background: #0d6efd;
        }
        
        .step-circle {
            width: 30px;
            height: 30px;
            border-radius: 50%;
            background: #dee2e6;
            color: #6c757d;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 0.5rem;
            font-weight: 600;
            font-size: 0.875rem;
            position: relative;
            z-index: 1;
        }
        
        .step.active .step-circle {
            background: #0d6efd;
            color: white;
        }
        
        .step.completed .step-circle {
            background: #198754;
            color: white;
        }
        
        .step-label {
            font-size: 0.75rem;
            color: #6c757d;
            font-weight: 500;
        }
        
        .step.active .step-label {
            color: #0d6efd;
            font-weight: 600;
        }
        
        /* Help & Information */
        .help-section {
            margin-bottom: 1.5rem;
            padding: 1rem;
            background: #f8f9fa;
            border-radius: 8px;
            border-left: 4px solid #0d6efd;
        }
        
        .help-section:last-child {
            margin-bottom: 0;
        }
        
        .help-section h6 {
            color: #0d6efd;
            margin-bottom: 0.5rem;
        }
        
        .detail-item {
            margin-bottom: 0.75rem;
            padding: 0.75rem;
            background: #ffffff;
            border-radius: 8px;
            border: 1px solid #e9ecef;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
        }
        
        .detail-item:last-child {
            margin-bottom: 0;
        }
        
        /* Mobile Responsiveness */
        @media (max-width: 768px) {
            .payment-method-card {
                min-height: 120px;
                margin-bottom: 1rem;
            }
            
            .payment-method-label {
                padding: 0.75rem;
            }
            
            .payment-method-card .fa-2x {
                font-size: 1.5rem !important;
            }
            
            .form-section {
                padding: 1rem;
            }
            
            .step-indicator {
                padding: 0 0.5rem;
            }
            
            .step-label {
                font-size: 0.7rem;
            }
            
            .copyable {
                font-size: 0.875rem;
                padding: 6px 10px;
                word-break: break-all;
            }
            
            .btn-lg {
                padding: 0.75rem 1.5rem;
                font-size: 1rem;
            }
        }
        
        @media (max-width: 576px) {
            .container {
                padding-left: 15px;
                padding-right: 15px;
            }
            
            .payment-method-card {
                min-height: 100px;
            }
            
            .step-circle {
                width: 25px;
                height: 25px;
                font-size: 0.75rem;
            }
            
            .step:not(:last-child)::after {
                top: 12px;
            }
        }
        
        /* Loading States */
        .loading {
            opacity: 0.6;
            pointer-events: none;
        }
        
        .spinner-border-sm {
            width: 1rem;
            height: 1rem;
        }
        
        /* Success/Error States */
        .alert {
            border-radius: 10px;
            border: none;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
        }
        
        .alert-success {
            background-color: #d1edda;
            color: #155724;
            border-left: 4px solid #28a745;
        }
        
        .alert-danger {
            background-color: #f8d7da;
            color: #721c24;
            border-left: 4px solid #dc3545;
        }
        
        .alert-info {
            background-color: #d1ecf1;
            color: #0c5460;
            border-left: 4px solid #17a2b8;
        }
        
        /* Animation Classes */
        .fade-in {
            animation: fadeIn 0.3s ease-in;
        }
        
        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(10px); }
            to { opacity: 1; transform: translateY(0); }
        }
        
        .slide-up {
            animation: slideUp 0.3s ease-out;
        }
        
        @keyframes slideUp {
            from { transform: translateY(20px); opacity: 0; }
            to { transform: translateY(0); opacity: 1; }
        }
    </style>
</body>
</html>
