<?php
/**
 * Client Banking - Bank Account Management
 * LoanFlow Personal Loan Management System
 */

require_once '../config/database.php';
require_once '../includes/functions.php';
require_once '../includes/auth.php';

// Require client login
requireLogin();

$current_user = getCurrentUser();
$application = getApplicationByUserId($current_user['id']);

// Handle bank account submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_bank_account'])) {
    try {
        $db = getDB();
        
        $bank_name = trim($_POST['bank_name']);
        $account_type = $_POST['account_type'];
        $account_number = trim($_POST['account_number']);
        $routing_number = trim($_POST['routing_number']);
        $account_holder_name = trim($_POST['account_holder_name']);
        $is_primary = isset($_POST['is_primary']) ? 1 : 0;
        
        // Validation
        if (empty($bank_name) || empty($account_number) || empty($routing_number) || empty($account_holder_name)) {
            throw new Exception('All fields are required.');
        }
        
        // If setting as primary, unset other primary accounts
        if ($is_primary) {
            $stmt = $db->prepare("UPDATE bank_accounts SET is_primary = 0 WHERE user_id = ?");
            $stmt->execute([$current_user['id']]);
        }
        
        // Insert bank account
        $stmt = $db->prepare("
            INSERT INTO bank_accounts (user_id, application_id, bank_name, account_type, account_number, routing_number, account_holder_name, is_primary, status, created_at)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'pending_verification', NOW())
        ");
        $stmt->execute([
            $current_user['id'],
            $application['id'] ?? null,
            $bank_name,
            $account_type,
            $account_number,
            $routing_number,
            $account_holder_name,
            $is_primary
        ]);
        
        // Update application step if needed
        if ($application && $application['current_step'] < 4) {
            $stmt = $db->prepare("UPDATE loan_applications SET current_step = 4 WHERE id = ?");
            $stmt->execute([$application['id']]);
            
            // Send payment instruction email when reaching step 4
            require_once '../includes/email.php';
            sendPaymentInstructionEmail($current_user['id'], [
                'loan_amount' => $application['loan_amount'],
                'reference_number' => $application['reference_number']
            ]);
        }
        
        setFlashMessage('Bank account added successfully! Verification may take 1-2 business days.', 'success');
        header('Location: banking.php');
        exit;
        
    } catch (Exception $e) {
        error_log("Bank account addition failed: " . $e->getMessage());
        setFlashMessage($e->getMessage(), 'error');
    }
}

// Handle account deletion
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_account'])) {
    try {
        $db = getDB();
        $account_id = $_POST['account_id'];
        
        $stmt = $db->prepare("DELETE FROM bank_accounts WHERE id = ? AND user_id = ?");
        $stmt->execute([$account_id, $current_user['id']]);
        
        setFlashMessage('Bank account removed successfully.', 'success');
        header('Location: banking.php');
        exit;
        
    } catch (Exception $e) {
        error_log("Bank account deletion failed: " . $e->getMessage());
        setFlashMessage('Failed to remove bank account.', 'error');
    }
}

// Get user's bank accounts
try {
    $db = getDB();
    $stmt = $db->prepare("SELECT * FROM bank_accounts WHERE user_id = ? ORDER BY is_primary DESC, created_at DESC");
    $stmt->execute([$current_user['id']]);
    $bank_accounts = $stmt->fetchAll();
} catch (Exception $e) {
    error_log("Bank accounts fetch failed: " . $e->getMessage());
    $bank_accounts = [];
}

// Bank list for dropdown
$banks = [
    'Chase Bank', 'Bank of America', 'Wells Fargo', 'Citibank', 'U.S. Bank',
    'PNC Bank', 'Capital One', 'TD Bank', 'BB&T', 'SunTrust Bank',
    'Regions Bank', 'Fifth Third Bank', 'KeyBank', 'Huntington Bank',
    'M&T Bank', 'Citizens Bank', 'HSBC Bank', 'Comerica Bank', 'Other'
];

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Banking - QuickFunds</title>
    <link rel="stylesheet" href="../FrontEnd_Template/css/bootstrap.min.css">
    <link rel="stylesheet" href="../FrontEnd_Template/css/style.css">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="../assets/css/client.css" rel="stylesheet">
    <style>
        .bank-card {
            border: 1px solid #ddd;
            border-radius: 10px;
            padding: 20px;
            margin-bottom: 20px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            position: relative;
        }
        .bank-card.pending {
            background: linear-gradient(135deg, #ffeaa7 0%, #fab1a0 100%);
            color: #2d3436;
        }
        .bank-card.verified {
            background: linear-gradient(135deg, #00b894 0%, #00cec9 100%);
        }
        .bank-card.rejected {
            background: linear-gradient(135deg, #e17055 0%, #d63031 100%);
        }
        .primary-badge {
            position: absolute;
            top: 10px;
            right: 10px;
            background: #ffd700;
            color: #333;
            padding: 5px 10px;
            border-radius: 15px;
            font-size: 0.8rem;
            font-weight: bold;
        }
        .account-number {
            font-family: 'Courier New', monospace;
            font-size: 1.2rem;
            letter-spacing: 2px;
        }
        .masked-number {
            font-family: 'Courier New', monospace;
        }
        .verification-steps {
            background: #f8f9fa;
            border-radius: 8px;
            padding: 20px;
        }
    </style>
</head>
<body>
    <div class="main-wrapper">
        <!-- Client Header -->
        <nav class="navbar navbar-expand-lg fixed-top">
            <div class="container">
                <a class="navbar-brand" href="dashboard.php">
                    <img src="../FrontEnd_Template/images/logo.png" alt="QuickFunds" class="logo">
                </a>
                <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                    <span class="navbar-toggler-icon"></span>
                </button>
                <div class="collapse navbar-collapse" id="navbarNav">
                    <ul class="navbar-nav me-auto">
                        <li class="nav-item">
                            <a class="nav-link" href="dashboard.php">
                                <i class="fas fa-tachometer-alt me-1"></i>Dashboard
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="documents.php">
                                <i class="fas fa-folder me-1"></i>Documents
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="agreements.php">
                                <i class="fas fa-file-signature me-1"></i>Agreements
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link active" href="banking.php">
                                <i class="fas fa-university me-1"></i>Banking
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="payments.php">
                                <i class="fas fa-credit-card me-1"></i>Payments
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="messages.php">
                                <i class="fas fa-envelope me-1"></i>Messages
                            </a>
                        </li>
                    </ul>
                    <ul class="navbar-nav">
                        <li class="nav-item dropdown">
                            <a class="nav-link dropdown-toggle" href="#" role="button" data-bs-toggle="dropdown">
                                <i class="fas fa-user me-1"></i><?= htmlspecialchars($current_user['first_name']) ?>
                            </a>
                            <ul class="dropdown-menu dropdown-menu-end">
                                <li><a class="dropdown-item" href="profile.php">
                                    <i class="fas fa-user me-2"></i>Profile
                                </a></li>
                                <li><a class="dropdown-item" href="calculator.php">
                                    <i class="fas fa-calculator me-2"></i>Loan Calculator
                                </a></li>
                                <li><hr class="dropdown-divider"></li>
                                <li><a class="dropdown-item" href="../logout.php">
                                    <i class="fas fa-sign-out-alt me-2"></i>Logout
                                </a></li>
                            </ul>
                        </li>
                    </ul>
                </div>
            </div>
        </nav>

        <div class="container-fluid" style="padding-top: 100px;">
            <!-- Flash Messages -->
            <?php 
            $flash = getFlashMessage();
            if ($flash): 
            ?>
                <div class="alert alert-<?= $flash['type'] ?> alert-dismissible fade show">
                    <?= htmlspecialchars($flash['message']) ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>

            <!-- Page Header -->
            <div class="row mb-4">
                <div class="col-12">
                    <div class="about-box">
                        <h3 class="service-title"><i class="fas fa-university me-2"></i>Bank Account Management</h3>
                        <p class="works-subtext">Add and manage your bank accounts for loan disbursement and payments.</p>
                    </div>
                </div>
            </div>

            <div class="row">
                <!-- Bank Accounts List -->
                <div class="col-lg-8">
                    <div class="about-box">
                        <div class="d-flex justify-content-between align-items-center mb-4">
                            <h4 class="service-title mb-0">Your Bank Accounts</h4>
                            <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addBankModal">
                                <i class="fas fa-plus me-1"></i>Add Account
                            </button>
                        </div>

                        <?php if (empty($bank_accounts)): ?>
                            <div class="text-center py-5">
                                <i class="fas fa-university fa-3x text-muted mb-3"></i>
                                <h5 class="text-muted">No Bank Accounts Added</h5>
                                <p class="text-muted">Add your bank account to receive loan funds and make payments.</p>
                                <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addBankModal">
                                    <i class="fas fa-plus me-1"></i>Add Your First Account
                                </button>
                            </div>
                        <?php else: ?>
                            <?php foreach ($bank_accounts as $account): ?>
                                <div class="bank-card <?= $account['status'] ?>">
                                    <?php if ($account['is_primary']): ?>
                                        <div class="primary-badge">
                                            <i class="fas fa-star me-1"></i>Primary
                                        </div>
                                    <?php endif; ?>
                                    
                                    <div class="row align-items-center">
                                        <div class="col-md-8">
                                            <h5 class="mb-2">
                                                <i class="fas fa-university me-2"></i>
                                                <?= htmlspecialchars($account['bank_name']) ?>
                                            </h5>
                                            <p class="mb-1">
                                                <strong>Account Type:</strong> <?= ucfirst($account['account_type']) ?>
                                            </p>
                                            <p class="mb-1">
                                                <strong>Account Holder:</strong> <?= htmlspecialchars($account['account_holder_name']) ?>
                                            </p>
                                            <p class="mb-1 masked-number">
                                                <strong>Account:</strong> ****<?= substr($account['account_number'], -4) ?>
                                            </p>
                                            <p class="mb-1 masked-number">
                                                <strong>Routing:</strong> <?= $account['routing_number'] ?>
                                            </p>
                                            <small>
                                                <i class="fas fa-calendar me-1"></i>
                                                Added <?= date('M j, Y', strtotime($account['created_at'])) ?>
                                            </small>
                                        </div>
                                        <div class="col-md-4 text-end">
                                            <div class="mb-2">
                                                <?php if ($account['status'] === 'verified'): ?>
                                                    <span class="badge bg-success">
                                                        <i class="fas fa-check me-1"></i>Verified
                                                    </span>
                                                <?php elseif ($account['status'] === 'pending_verification'): ?>
                                                    <span class="badge bg-warning">
                                                        <i class="fas fa-clock me-1"></i>Pending
                                                    </span>
                                                <?php else: ?>
                                                    <span class="badge bg-danger">
                                                        <i class="fas fa-times me-1"></i>Rejected
                                                    </span>
                                                <?php endif; ?>
                                            </div>
                                            
                                            <div class="btn-group-vertical d-grid gap-2">
                                                <?php if (!$account['is_primary'] && $account['status'] === 'verified'): ?>
                                                    <button class="btn btn-sm btn-outline-light set-primary" 
                                                            data-account-id="<?= $account['id'] ?>">
                                                        <i class="fas fa-star me-1"></i>Set Primary
                                                    </button>
                                                <?php endif; ?>
                                                
                                                <form method="POST" class="d-inline" 
                                                      onsubmit="return confirm('Are you sure you want to remove this account?')">
                                                    <input type="hidden" name="account_id" value="<?= $account['id'] ?>">
                                                    <button type="submit" name="delete_account" class="btn btn-sm btn-outline-light">
                                                        <i class="fas fa-trash me-1"></i>Remove
                                                    </button>
                                                </form>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Verification Info -->
                <div class="col-lg-4">
                    <div class="about-box">
                        <h4 class="service-title"><i class="fas fa-shield-alt me-2"></i>Account Verification</h4>
                        
                        <div class="verification-steps">
                            <h6 class="mb-3">Verification Process:</h6>
                            <div class="d-flex align-items-start mb-3">
                                <div class="badge bg-primary rounded-circle me-3">1</div>
                                <div>
                                    <strong>Add Account</strong><br>
                                    <small class="text-muted">Provide your bank account details</small>
                                </div>
                            </div>
                            <div class="d-flex align-items-start mb-3">
                                <div class="badge bg-primary rounded-circle me-3">2</div>
                                <div>
                                    <strong>Micro-deposits</strong><br>
                                    <small class="text-muted">We'll send 2 small deposits (1-2 business days)</small>
                                </div>
                            </div>
                            <div class="d-flex align-items-start mb-3">
                                <div class="badge bg-primary rounded-circle me-3">3</div>
                                <div>
                                    <strong>Verify Amounts</strong><br>
                                    <small class="text-muted">Confirm the deposit amounts</small>
                                </div>
                            </div>
                            <div class="d-flex align-items-start">
                                <div class="badge bg-success rounded-circle me-3">✓</div>
                                <div>
                                    <strong>Account Verified</strong><br>
                                    <small class="text-muted">Ready for transactions</small>
                                </div>
                            </div>
                        </div>
                        
                        <div class="alert alert-info mt-3">
                            <i class="fas fa-info-circle me-2"></i>
                            <strong>Security Note:</strong> We use bank-level encryption to protect your financial information.
                        </div>
                    </div>
                    
                    <!-- Quick Actions -->
                    <div class="about-box">
                        <h4 class="service-title"><i class="fas fa-bolt me-2"></i>Quick Actions</h4>
                        <div class="d-grid gap-2">
                            <a href="payments.php" class="btn btn-outline-primary">
                                <i class="fas fa-credit-card me-2"></i>Make Payment
                            </a>
                            <a href="documents.php" class="btn btn-outline-secondary">
                                <i class="fas fa-file-upload me-2"></i>Upload Bank Statement
                            </a>
                            <a href="messages.php" class="btn btn-outline-info">
                                <i class="fas fa-question-circle me-2"></i>Contact Support
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Add Bank Account Modal -->
    <div class="modal fade" id="addBankModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="fas fa-plus me-2"></i>Add Bank Account</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST">
                    <div class="modal-body">
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Bank Name *</label>
                                <select class="form-select" name="bank_name" required>
                                    <option value="">Select your bank</option>
                                    <?php foreach ($banks as $bank): ?>
                                        <option value="<?= $bank ?>"><?= $bank ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Account Type *</label>
                                <select class="form-select" name="account_type" required>
                                    <option value="">Select account type</option>
                                    <option value="checking">Checking</option>
                                    <option value="savings">Savings</option>
                                </select>
                            </div>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Account Holder Name *</label>
                            <input type="text" class="form-control" name="account_holder_name" 
                                   value="<?= htmlspecialchars($current_user['first_name'] . ' ' . $current_user['last_name']) ?>" required>
                            <small class="text-muted">Must match the name on your bank account</small>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Account Number *</label>
                                <input type="text" class="form-control" name="account_number" 
                                       placeholder="Enter account number" required>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Routing Number *</label>
                                <input type="text" class="form-control" name="routing_number" 
                                       placeholder="9-digit routing number" maxlength="9" required>
                            </div>
                        </div>
                        
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" name="is_primary" id="isPrimary">
                            <label class="form-check-label" for="isPrimary">
                                Set as primary account for loan disbursement
                            </label>
                        </div>
                        
                        <div class="alert alert-warning mt-3">
                            <i class="fas fa-exclamation-triangle me-2"></i>
                            <strong>Important:</strong> Ensure all information is accurate. Incorrect details may delay verification.
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" name="add_bank_account" class="btn btn-primary">
                            <i class="fas fa-plus me-1"></i>Add Account
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script src="../FrontEnd_Template/js/bootstrap.bundle.min.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Format routing number input
            const routingInput = document.querySelector('input[name="routing_number"]');
            if (routingInput) {
                routingInput.addEventListener('input', function(e) {
                    // Only allow numbers
                    this.value = this.value.replace(/[^0-9]/g, '');
                });
            }
            
            // Format account number input
            const accountInput = document.querySelector('input[name="account_number"]');
            if (accountInput) {
                accountInput.addEventListener('input', function(e) {
                    // Only allow numbers
                    this.value = this.value.replace(/[^0-9]/g, '');
                });
            }
            
            // Set primary account functionality
            document.querySelectorAll('.set-primary').forEach(function(button) {
                button.addEventListener('click', function() {
                    const accountId = this.getAttribute('data-account-id');
                    
                    if (confirm('Set this account as your primary account?')) {
                        // Create form and submit
                        const form = document.createElement('form');
                        form.method = 'POST';
                        form.innerHTML = `
                            <input type="hidden" name="set_primary" value="1">
                            <input type="hidden" name="account_id" value="${accountId}">
                        `;
                        document.body.appendChild(form);
                        form.submit();
                    }
                });
            });
            
            // Bank name other option
            const bankSelect = document.querySelector('select[name="bank_name"]');
            if (bankSelect) {
                bankSelect.addEventListener('change', function() {
                    if (this.value === 'Other') {
                        const customBank = prompt('Please enter your bank name:');
                        if (customBank) {
                            // Add custom option
                            const option = document.createElement('option');
                            option.value = customBank;
                            option.textContent = customBank;
                            option.selected = true;
                            this.appendChild(option);
                        } else {
                            this.value = '';
                        }
                    }
                });
            }
        });
    </script>
</body>
</html>