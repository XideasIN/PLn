<?php
/**
 * Payment Methods Configuration
 * LoanFlow Personal Loan Management System
 */

require_once '../config/database.php';
require_once '../includes/functions.php';
require_once '../includes/auth.php';
require_once '../includes/enhanced_payment.php';
require_once '../includes/language.php';

// Require admin access
requireRole('admin');

// Initialize language
LanguageManager::init();

$current_user = getCurrentUser();

$error = '';
$success = '';

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCSRFToken($_POST['csrf_token'] ?? '')) {
        $error = __('invalid_csrf_token');
    } else {
        $action = $_POST['action'] ?? '';
        
        switch ($action) {
            case 'update_payment_method':
                $method_name = sanitizeInput($_POST['method_name'] ?? '');
                $is_enabled = isset($_POST['is_enabled']) ? 1 : 0;
                $allowed_countries = $_POST['allowed_countries'] ?? [];
                $config_data = [];
                $instructions = sanitizeInput($_POST['instructions'] ?? '');
                $email_template = sanitizeInput($_POST['email_template'] ?? '');
                
                // Build config data based on method
                switch ($method_name) {
                    case 'wire_transfer':
                        $config_data = [
                            'bank_name' => sanitizeInput($_POST['bank_name'] ?? ''),
                            'account_name' => sanitizeInput($_POST['account_name'] ?? ''),
                            'account_number' => sanitizeInput($_POST['account_number'] ?? ''),
                            'routing_number' => sanitizeInput($_POST['routing_number'] ?? ''),
                            'swift_code' => sanitizeInput($_POST['swift_code'] ?? ''),
                            'bank_address' => sanitizeInput($_POST['bank_address'] ?? ''),
                            // Workflow settings
                            'auto_email_instructions' => isset($_POST['auto_email_instructions']),
                            'require_confirmation' => isset($_POST['require_confirmation']),
                            'require_image_upload' => isset($_POST['require_image_upload']),
                            'max_file_size' => intval($_POST['max_file_size'] ?? 10),
                            'allowed_file_types' => sanitizeInput($_POST['allowed_file_types'] ?? 'jpg,jpeg,png,pdf'),
                            'confirmation_timeout' => intval($_POST['confirmation_timeout'] ?? 72)
                        ];
                        break;
                    case 'crypto':
                        $config_data = [
                            'wallet_address' => sanitizeInput($_POST['wallet_address'] ?? ''),
                            'currency_type' => sanitizeInput($_POST['currency_type'] ?? 'BTC'),
                            'network' => sanitizeInput($_POST['network'] ?? ''),
                            'qr_code_url' => sanitizeInput($_POST['qr_code_url'] ?? ''),
                            // Workflow settings
                            'auto_complete_enabled' => isset($_POST['auto_complete_enabled']),
                            'auto_email_instructions' => isset($_POST['auto_email_instructions']),
                            'show_qr_code' => isset($_POST['show_qr_code']),
                            'required_confirmations' => intval($_POST['required_confirmations'] ?? 3),
                            'payment_timeout' => intval($_POST['payment_timeout'] ?? 30),
                            'manual_verification' => isset($_POST['manual_verification'])
                        ];
                        break;
                    case 'e_transfer':
                        $config_data = [
                            'email_address' => sanitizeInput($_POST['email_address'] ?? ''),
                            'security_question' => sanitizeInput($_POST['security_question'] ?? ''),
                            'security_answer' => sanitizeInput($_POST['security_answer'] ?? ''),
                            'recipient_name' => sanitizeInput($_POST['recipient_name'] ?? ''),
                            // Workflow settings
                            'auto_email_instructions' => isset($_POST['auto_email_instructions']),
                            'require_confirmation' => isset($_POST['require_confirmation']),
                            'allow_manual_details' => isset($_POST['allow_manual_details']),
                            'require_image_upload' => isset($_POST['require_image_upload']),
                            'max_file_size' => intval($_POST['max_file_size'] ?? 10),
                            'allowed_file_types' => sanitizeInput($_POST['allowed_file_types'] ?? 'jpg,jpeg,png,pdf'),
                            'confirmation_timeout' => intval($_POST['confirmation_timeout'] ?? 24),
                            'auto_accept_known' => isset($_POST['auto_accept_known'])
                        ];
                        break;
                    case 'credit_card':
                        $config_data = [
                            'stripe_publishable_key' => sanitizeInput($_POST['stripe_publishable_key'] ?? ''),
                            'stripe_secret_key' => sanitizeInput($_POST['stripe_secret_key'] ?? ''),
                            'paypal_client_id' => sanitizeInput($_POST['paypal_client_id'] ?? ''),
                            'paypal_secret' => sanitizeInput($_POST['paypal_secret'] ?? ''),
                            // Workflow settings
                            'auto_email_instructions' => isset($_POST['auto_email_instructions']),
                            'require_3d_secure' => isset($_POST['require_3d_secure']),
                            'save_card_option' => isset($_POST['save_card_option']),
                            'payment_timeout' => intval($_POST['payment_timeout'] ?? 15)
                        ];
                        break;
                }
                
                try {
                    $db = getDB();
                    $stmt = $db->prepare("
                        INSERT INTO payment_method_config (method_name, is_enabled, allowed_countries, config_data, instructions, email_template)
                        VALUES (?, ?, ?, ?, ?, ?)
                        ON DUPLICATE KEY UPDATE
                        is_enabled = VALUES(is_enabled),
                        allowed_countries = VALUES(allowed_countries),
                        config_data = VALUES(config_data),
                        instructions = VALUES(instructions),
                        email_template = VALUES(email_template),
                        updated_at = NOW()
                    ");
                    
                    $result = $stmt->execute([
                        $method_name,
                        $is_enabled,
                        json_encode($allowed_countries),
                        json_encode($config_data),
                        $instructions,
                        $email_template
                    ]);
                    
                    if ($result) {
                        $success = __('payment_method_updated');
                        logAudit('payment_method_updated', 'payment_method_config', null, $current_user['id'], [
                            'method' => $method_name,
                            'enabled' => $is_enabled
                        ]);
                    } else {
                        $error = __('payment_method_update_failed');
                    }
                } catch (Exception $e) {
                    error_log("Payment method update failed: " . $e->getMessage());
                    $error = __('payment_method_update_failed');
                }
                break;
                
            case 'assign_subscription':
                $user_id = intval($_POST['user_id'] ?? 0);
                $admin_2fa_code = sanitizeInput($_POST['admin_2fa_code'] ?? '');
                
                if (empty($user_id)) {
                    $error = __('user_id_required');
                } elseif (empty($admin_2fa_code)) {
                    $error = __('admin_2fa_required');
                } else {
                    $result = EnhancedPaymentManager::assignSubscriptionScheme($user_id, $current_user['id'], $admin_2fa_code);
                    
                    if ($result['success']) {
                        $success = $result['message'];
                    } else {
                        $error = $result['error'];
                    }
                }
                break;
        }
    }
}

// Get current payment method configurations
try {
    $db = getDB();
    $stmt = $db->query("SELECT * FROM payment_method_config ORDER BY method_name");
    $payment_methods = $stmt->fetchAll();
    
    // Convert to associative array for easier access
    $methods_config = [];
    foreach ($payment_methods as $method) {
        $methods_config[$method['method_name']] = $method;
    }
    
    // Get countries list
    $stmt = $db->query("SELECT country_code, country_name FROM countries ORDER BY country_name");
    $countries = $stmt->fetchAll();
    
    // Get users for subscription assignment
    $stmt = $db->query("
        SELECT u.id, u.first_name, u.last_name, u.email, u.reference_number,
               ups.scheme_type as current_scheme
        FROM users u
        LEFT JOIN user_payment_schemes ups ON u.id = ups.user_id
        WHERE u.role = 'client' AND u.status = 'active'
        ORDER BY u.first_name, u.last_name
    ");
    $users = $stmt->fetchAll();
    
} catch (Exception $e) {
    error_log("Payment methods data fetch failed: " . $e->getMessage());
    $error = __('data_fetch_failed');
}

// Get payment statistics
$payment_stats = EnhancedPaymentManager::getPaymentStatistics();

?>
<!DOCTYPE html>
<html lang="<?= LanguageManager::getCurrentLanguage() ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= __('payment_methods_config') ?> - LoanFlow Admin</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="../assets/css/style.css" rel="stylesheet">
    <link href="../assets/css/admin.css" rel="stylesheet">
</head>
<body>
    <!-- Admin Header -->
    <nav class="navbar navbar-expand-lg navbar-dark bg-dark">
        <div class="container-fluid">
            <a class="navbar-brand" href="index.php">
                <i class="fas fa-tachometer-alt me-2"></i>LoanFlow Admin
            </a>
            <div class="navbar-nav ms-auto">
                <?= LanguageManager::getLanguageSelector() ?>
                <a class="nav-link" href="index.php">
                    <i class="fas fa-arrow-left me-1"></i><?= __('back_to_dashboard') ?>
                </a>
            </div>
        </div>
    </nav>

    <div class="container-fluid py-4">
        <!-- Flash Messages -->
        <?php if ($error): ?>
            <div class="alert alert-danger alert-dismissible fade show">
                <?= htmlspecialchars($error) ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>
        
        <?php if ($success): ?>
            <div class="alert alert-success alert-dismissible fade show">
                <?= htmlspecialchars($success) ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <!-- Payment Statistics -->
        <div class="row mb-4">
            <div class="col-12">
                <div class="card">
                    <div class="card-header">
                        <h5 class="mb-0">
                            <i class="fas fa-chart-bar me-2"></i><?= __('payment_statistics') ?>
                        </h5>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <?php if (isset($payment_stats['method_breakdown'])): ?>
                                <?php foreach ($payment_stats['method_breakdown'] as $method_stat): ?>
                                <div class="col-md-3 mb-3">
                                    <div class="card bg-light">
                                        <div class="card-body text-center">
                                            <h6><?= __(str_replace('_', '_', $method_stat['payment_method'])) ?></h6>
                                            <h4 class="text-primary"><?= $method_stat['count'] ?></h4>
                                            <small class="text-muted"><?= formatCurrency($method_stat['total_amount']) ?></small>
                                        </div>
                                    </div>
                                </div>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="row">
            <div class="col-12">
                <div class="card">
                    <div class="card-header">
                        <h4 class="mb-0">
                            <i class="fas fa-credit-card me-2"></i><?= __('payment_methods_configuration') ?>
                        </h4>
                    </div>
                    <div class="card-body">
                        
                        <!-- Navigation Tabs -->
                        <ul class="nav nav-tabs" id="methodTabs" role="tablist">
                            <li class="nav-item" role="presentation">
                                <button class="nav-link active" id="wire-tab" data-bs-toggle="tab" data-bs-target="#wire-transfer" type="button" role="tab">
                                    <i class="fas fa-university me-2"></i><?= __('wire_transfer') ?>
                                </button>
                            </li>
                            <li class="nav-item" role="presentation">
                                <button class="nav-link" id="crypto-tab" data-bs-toggle="tab" data-bs-target="#crypto" type="button" role="tab">
                                    <i class="fab fa-bitcoin me-2"></i><?= __('cryptocurrency') ?>
                                </button>
                            </li>
                            <li class="nav-item" role="presentation">
                                <button class="nav-link" id="etransfer-tab" data-bs-toggle="tab" data-bs-target="#e-transfer" type="button" role="tab">
                                    <i class="fas fa-envelope me-2"></i><?= __('e_transfer') ?>
                                </button>
                            </li>
                            <li class="nav-item" role="presentation">
                                <button class="nav-link" id="creditcard-tab" data-bs-toggle="tab" data-bs-target="#credit-card" type="button" role="tab">
                                    <i class="fas fa-credit-card me-2"></i><?= __('credit_card') ?>
                                </button>
                            </li>
                            <li class="nav-item" role="presentation">
                                <button class="nav-link" id="subscription-tab" data-bs-toggle="tab" data-bs-target="#subscription-assignment" type="button" role="tab">
                                    <i class="fas fa-users me-2"></i><?= __('subscription_assignment') ?>
                                </button>
                            </li>
                        </ul>
                        
                        <!-- Tab Content -->
                        <div class="tab-content" id="methodTabsContent" class="mt-4">
                            
                            <!-- Wire Transfer Tab -->
                            <div class="tab-pane fade show active" id="wire-transfer" role="tabpanel">
                                <?php 
                                $wire_config = $methods_config['wire_transfer'] ?? null;
                                $wire_data = $wire_config ? json_decode($wire_config['config_data'], true) : [];
                                ?>
                                <form method="POST">
                                    <input type="hidden" name="csrf_token" value="<?= generateCSRFToken() ?>">
                                    <input type="hidden" name="action" value="update_payment_method">
                                    <input type="hidden" name="method_name" value="wire_transfer">
                                    
                                    <div class="row">
                                        <div class="col-lg-8">
                                            <div class="form-check mb-3">
                                                <input class="form-check-input" type="checkbox" id="wire_enabled" name="is_enabled" 
                                                       <?= ($wire_config && $wire_config['is_enabled']) ? 'checked' : '' ?>>
                                                <label class="form-check-label" for="wire_enabled">
                                                    <strong><?= __('enable_wire_transfer') ?></strong>
                                                </label>
                                            </div>
                                            
                                                                        <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label for="bank_name" class="form-label"><?= __('bank_name') ?> <span class="text-danger">*</span></label>
                                    <input type="text" class="form-control" id="bank_name" name="bank_name" 
                                           value="<?= htmlspecialchars($wire_data['bank_name'] ?? '') ?>" required>
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label for="account_name" class="form-label"><?= __('account_name') ?> <span class="text-danger">*</span></label>
                                    <input type="text" class="form-control" id="account_name" name="account_name" 
                                           value="<?= htmlspecialchars($wire_data['account_name'] ?? '') ?>" required>
                                </div>
                            </div>
                            
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label for="account_number" class="form-label"><?= __('account_number') ?> <span class="text-danger">*</span></label>
                                    <input type="text" class="form-control" id="account_number" name="account_number" 
                                           value="<?= htmlspecialchars($wire_data['account_number'] ?? '') ?>" required>
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label for="routing_number" class="form-label"><?= __('routing_number') ?></label>
                                    <input type="text" class="form-control" id="routing_number" name="routing_number" 
                                           value="<?= htmlspecialchars($wire_data['routing_number'] ?? '') ?>">
                                </div>
                            </div>
                            
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label for="swift_code" class="form-label"><?= __('swift_code') ?></label>
                                    <input type="text" class="form-control" id="swift_code" name="swift_code" 
                                           value="<?= htmlspecialchars($wire_data['swift_code'] ?? '') ?>">
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label for="bank_address" class="form-label"><?= __('bank_address') ?></label>
                                    <input type="text" class="form-control" id="bank_address" name="bank_address" 
                                           value="<?= htmlspecialchars($wire_data['bank_address'] ?? '') ?>">
                                </div>
                            </div>
                            
                            <!-- Wire Transfer Workflow Settings -->
                            <div class="card mb-3 bg-light">
                                <div class="card-header">
                                    <h6 class="mb-0"><?= __('wire_transfer_workflow_settings') ?></h6>
                                </div>
                                <div class="card-body">
                                    <div class="row">
                                        <div class="col-md-6">
                                            <div class="form-check mb-3">
                                                <input class="form-check-input" type="checkbox" id="wire_auto_email" name="auto_email_instructions" 
                                                       <?= ($wire_data['auto_email_instructions'] ?? true) ? 'checked' : '' ?>>
                                                <label class="form-check-label" for="wire_auto_email">
                                                    <?= __('auto_email_instructions') ?>
                                                </label>
                                                <div class="form-text"><?= __('auto_email_instructions_help') ?></div>
                                            </div>
                                            
                                            <div class="form-check mb-3">
                                                <input class="form-check-input" type="checkbox" id="wire_require_confirmation" name="require_confirmation" 
                                                       <?= ($wire_data['require_confirmation'] ?? true) ? 'checked' : '' ?>>
                                                <label class="form-check-label" for="wire_require_confirmation">
                                                    <?= __('require_payment_confirmation') ?>
                                                </label>
                                                <div class="form-text"><?= __('require_confirmation_help') ?></div>
                                            </div>
                                            
                                            <div class="form-check mb-3">
                                                <input class="form-check-input" type="checkbox" id="wire_require_image" name="require_image_upload" 
                                                       <?= ($wire_data['require_image_upload'] ?? true) ? 'checked' : '' ?>>
                                                <label class="form-check-label" for="wire_require_image">
                                                    <?= __('require_image_upload') ?>
                                                </label>
                                                <div class="form-text"><?= __('require_image_upload_help') ?></div>
                                            </div>
                                        </div>
                                        
                                        <div class="col-md-6">
                                            <div class="mb-3">
                                                <label for="wire_max_file_size" class="form-label"><?= __('max_file_size_mb') ?></label>
                                                <input type="number" class="form-control" id="wire_max_file_size" name="max_file_size" 
                                                       value="<?= htmlspecialchars($wire_data['max_file_size'] ?? '10') ?>" min="1" max="50">
                                                <div class="form-text"><?= __('max_file_size_help') ?></div>
                                            </div>
                                            
                                            <div class="mb-3">
                                                <label for="wire_allowed_file_types" class="form-label"><?= __('allowed_file_types') ?></label>
                                                <input type="text" class="form-control" id="wire_allowed_file_types" name="allowed_file_types" 
                                                       value="<?= htmlspecialchars($wire_data['allowed_file_types'] ?? 'jpg,jpeg,png,pdf') ?>"
                                                       placeholder="jpg,jpeg,png,pdf">
                                                <div class="form-text"><?= __('allowed_file_types_help') ?></div>
                                            </div>
                                            
                                            <div class="mb-3">
                                                <label for="wire_confirmation_timeout" class="form-label"><?= __('confirmation_timeout_hours') ?></label>
                                                <input type="number" class="form-control" id="wire_confirmation_timeout" name="confirmation_timeout" 
                                                       value="<?= htmlspecialchars($wire_data['confirmation_timeout'] ?? '72') ?>" min="1" max="168">
                                                <div class="form-text"><?= __('confirmation_timeout_help') ?></div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="mb-3">
                                <label for="wire_instructions" class="form-label"><?= __('payment_instructions') ?></label>
                                <textarea class="form-control" id="wire_instructions" name="instructions" rows="5" 
                                          placeholder="<?= __('wire_instructions_placeholder') ?>"><?= htmlspecialchars($wire_config['instructions'] ?? '') ?></textarea>
                                <div class="form-text"><?= __('wire_instructions_help') ?></div>
                            </div>
                            
                            <div class="mb-3">
                                <label for="wire_email_template" class="form-label"><?= __('email_template_custom') ?></label>
                                <textarea class="form-control" id="wire_email_template" name="email_template" rows="8" 
                                          placeholder="<?= __('wire_email_template_placeholder') ?>"><?= htmlspecialchars($wire_config['email_template'] ?? '') ?></textarea>
                                <div class="form-text"><?= __('email_template_help') ?></div>
                            </div>
                                        </div>
                                        
                                        <div class="col-lg-4">
                                            <div class="card bg-light">
                                                <div class="card-body">
                                                    <h6><?= __('allowed_countries') ?></h6>
                                                    <?php 
                                                    $wire_countries = $wire_config ? json_decode($wire_config['allowed_countries'], true) : [];
                                                    ?>
                                                    <?php foreach ($countries as $country): ?>
                                                    <div class="form-check">
                                                        <input class="form-check-input" type="checkbox" name="allowed_countries[]" 
                                                               value="<?= $country['country_code'] ?>" id="wire_<?= $country['country_code'] ?>"
                                                               <?= in_array($country['country_code'], $wire_countries) ? 'checked' : '' ?>>
                                                        <label class="form-check-label" for="wire_<?= $country['country_code'] ?>">
                                                            <?= htmlspecialchars($country['country_name']) ?>
                                                        </label>
                                                    </div>
                                                    <?php endforeach; ?>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                    
                                    <button type="submit" class="btn btn-primary">
                                        <i class="fas fa-save me-2"></i><?= __('save_wire_transfer_config') ?>
                                    </button>
                                </form>
                            </div>
                            
                            <!-- Cryptocurrency Tab -->
                            <div class="tab-pane fade" id="crypto" role="tabpanel">
                                <?php 
                                $crypto_config = $methods_config['crypto'] ?? null;
                                $crypto_data = $crypto_config ? json_decode($crypto_config['config_data'], true) : [];
                                ?>
                                <form method="POST">
                                    <input type="hidden" name="csrf_token" value="<?= generateCSRFToken() ?>">
                                    <input type="hidden" name="action" value="update_payment_method">
                                    <input type="hidden" name="method_name" value="crypto">
                                    
                                    <div class="row">
                                        <div class="col-lg-8">
                                            <div class="form-check mb-3">
                                                <input class="form-check-input" type="checkbox" id="crypto_enabled" name="is_enabled" 
                                                       <?= ($crypto_config && $crypto_config['is_enabled']) ? 'checked' : '' ?>>
                                                <label class="form-check-label" for="crypto_enabled">
                                                    <strong><?= __('enable_cryptocurrency') ?></strong>
                                                </label>
                                            </div>
                                            
                                                                        <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label for="wallet_address" class="form-label"><?= __('wallet_address') ?> <span class="text-danger">*</span></label>
                                    <input type="text" class="form-control" id="wallet_address" name="wallet_address" 
                                           value="<?= htmlspecialchars($crypto_data['wallet_address'] ?? '') ?>" required>
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label for="currency_type" class="form-label"><?= __('currency_type') ?></label>
                                    <select class="form-select" id="currency_type" name="currency_type">
                                        <option value="BTC" <?= ($crypto_data['currency_type'] ?? '') === 'BTC' ? 'selected' : '' ?>>Bitcoin (BTC)</option>
                                        <option value="ETH" <?= ($crypto_data['currency_type'] ?? '') === 'ETH' ? 'selected' : '' ?>>Ethereum (ETH)</option>
                                        <option value="USDT" <?= ($crypto_data['currency_type'] ?? '') === 'USDT' ? 'selected' : '' ?>>Tether (USDT)</option>
                                        <option value="LTC" <?= ($crypto_data['currency_type'] ?? '') === 'LTC' ? 'selected' : '' ?>>Litecoin (LTC)</option>
                                    </select>
                                </div>
                            </div>
                            
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label for="network" class="form-label"><?= __('network') ?></label>
                                    <input type="text" class="form-control" id="network" name="network" 
                                           value="<?= htmlspecialchars($crypto_data['network'] ?? '') ?>" 
                                           placeholder="e.g., ERC-20, TRC-20">
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label for="qr_code_url" class="form-label"><?= __('qr_code_url') ?></label>
                                    <input type="url" class="form-control" id="qr_code_url" name="qr_code_url" 
                                           value="<?= htmlspecialchars($crypto_data['qr_code_url'] ?? '') ?>">
                                </div>
                            </div>
                            
                            <!-- Cryptocurrency Workflow Settings -->
                            <div class="card mb-3 bg-light">
                                <div class="card-header">
                                    <h6 class="mb-0"><?= __('crypto_workflow_settings') ?></h6>
                                </div>
                                <div class="card-body">
                                    <div class="row">
                                        <div class="col-md-6">
                                            <div class="form-check mb-3">
                                                <input class="form-check-input" type="checkbox" id="crypto_auto_complete" name="auto_complete_enabled" 
                                                       <?= ($crypto_data['auto_complete_enabled'] ?? true) ? 'checked' : '' ?>>
                                                <label class="form-check-label" for="crypto_auto_complete">
                                                    <?= __('enable_auto_completion') ?>
                                                </label>
                                                <div class="form-text"><?= __('auto_completion_help') ?></div>
                                            </div>
                                            
                                            <div class="form-check mb-3">
                                                <input class="form-check-input" type="checkbox" id="crypto_auto_email" name="auto_email_instructions" 
                                                       <?= ($crypto_data['auto_email_instructions'] ?? true) ? 'checked' : '' ?>>
                                                <label class="form-check-label" for="crypto_auto_email">
                                                    <?= __('auto_email_instructions') ?>
                                                </label>
                                                <div class="form-text"><?= __('crypto_email_help') ?></div>
                                            </div>
                                            
                                            <div class="form-check mb-3">
                                                <input class="form-check-input" type="checkbox" id="crypto_show_qr" name="show_qr_code" 
                                                       <?= ($crypto_data['show_qr_code'] ?? true) ? 'checked' : '' ?>>
                                                <label class="form-check-label" for="crypto_show_qr">
                                                    <?= __('show_qr_code') ?>
                                                </label>
                                                <div class="form-text"><?= __('qr_code_help') ?></div>
                                            </div>
                                        </div>
                                        
                                        <div class="col-md-6">
                                            <div class="mb-3">
                                                <label for="crypto_confirmation_blocks" class="form-label"><?= __('required_confirmations') ?></label>
                                                <input type="number" class="form-control" id="crypto_confirmation_blocks" name="required_confirmations" 
                                                       value="<?= htmlspecialchars($crypto_data['required_confirmations'] ?? '3') ?>" min="1" max="12">
                                                <div class="form-text"><?= __('confirmation_blocks_help') ?></div>
                                            </div>
                                            
                                            <div class="mb-3">
                                                <label for="crypto_timeout_minutes" class="form-label"><?= __('payment_timeout_minutes') ?></label>
                                                <input type="number" class="form-control" id="crypto_timeout_minutes" name="payment_timeout" 
                                                       value="<?= htmlspecialchars($crypto_data['payment_timeout'] ?? '30') ?>" min="5" max="120">
                                                <div class="form-text"><?= __('payment_timeout_help') ?></div>
                                            </div>
                                            
                                            <div class="form-check mb-3">
                                                <input class="form-check-input" type="checkbox" id="crypto_manual_verification" name="manual_verification" 
                                                       <?= ($crypto_data['manual_verification'] ?? false) ? 'checked' : '' ?>>
                                                <label class="form-check-label" for="crypto_manual_verification">
                                                    <?= __('require_manual_verification') ?>
                                                </label>
                                                <div class="form-text"><?= __('manual_verification_help') ?></div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="mb-3">
                                <label for="crypto_instructions" class="form-label"><?= __('payment_instructions') ?></label>
                                <textarea class="form-control" id="crypto_instructions" name="instructions" rows="5" 
                                          placeholder="<?= __('crypto_instructions_placeholder') ?>"><?= htmlspecialchars($crypto_config['instructions'] ?? '') ?></textarea>
                                <div class="form-text"><?= __('crypto_instructions_help') ?></div>
                            </div>
                            
                            <div class="mb-3">
                                <label for="crypto_email_template" class="form-label"><?= __('email_template_custom') ?></label>
                                <textarea class="form-control" id="crypto_email_template" name="email_template" rows="8" 
                                          placeholder="<?= __('crypto_email_template_placeholder') ?>"><?= htmlspecialchars($crypto_config['email_template'] ?? '') ?></textarea>
                                <div class="form-text"><?= __('email_template_help') ?></div>
                            </div>
                                        </div>
                                        
                                        <div class="col-lg-4">
                                            <div class="card bg-light">
                                                <div class="card-body">
                                                    <h6><?= __('allowed_countries') ?></h6>
                                                    <?php 
                                                    $crypto_countries = $crypto_config ? json_decode($crypto_config['allowed_countries'], true) : [];
                                                    ?>
                                                    <?php foreach ($countries as $country): ?>
                                                    <div class="form-check">
                                                        <input class="form-check-input" type="checkbox" name="allowed_countries[]" 
                                                               value="<?= $country['country_code'] ?>" id="crypto_<?= $country['country_code'] ?>"
                                                               <?= in_array($country['country_code'], $crypto_countries) ? 'checked' : '' ?>>
                                                        <label class="form-check-label" for="crypto_<?= $country['country_code'] ?>">
                                                            <?= htmlspecialchars($country['country_name']) ?>
                                                        </label>
                                                    </div>
                                                    <?php endforeach; ?>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                    
                                    <button type="submit" class="btn btn-primary">
                                        <i class="fas fa-save me-2"></i><?= __('save_crypto_config') ?>
                                    </button>
                                </form>
                            </div>
                            
                            <!-- E-Transfer Tab -->
                            <div class="tab-pane fade" id="e-transfer" role="tabpanel">
                                <?php 
                                $etransfer_config = $methods_config['e_transfer'] ?? null;
                                $etransfer_data = $etransfer_config ? json_decode($etransfer_config['config_data'], true) : [];
                                ?>
                                <form method="POST">
                                    <input type="hidden" name="csrf_token" value="<?= generateCSRFToken() ?>">
                                    <input type="hidden" name="action" value="update_payment_method">
                                    <input type="hidden" name="method_name" value="e_transfer">
                                    
                                    <div class="row">
                                        <div class="col-lg-8">
                                            <div class="form-check mb-3">
                                                <input class="form-check-input" type="checkbox" id="etransfer_enabled" name="is_enabled" 
                                                       <?= ($etransfer_config && $etransfer_config['is_enabled']) ? 'checked' : '' ?>>
                                                <label class="form-check-label" for="etransfer_enabled">
                                                    <strong><?= __('enable_e_transfer') ?></strong>
                                                </label>
                                            </div>
                                            
                                            <div class="alert alert-info">
                                                <i class="fas fa-info-circle me-2"></i>
                                                <?= __('e_transfer_canada_only') ?>
                                            </div>
                                            
                                                                        <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label for="email_address" class="form-label"><?= __('email_address') ?> <span class="text-danger">*</span></label>
                                    <input type="email" class="form-control" id="email_address" name="email_address" 
                                           value="<?= htmlspecialchars($etransfer_data['email_address'] ?? '') ?>" required>
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label for="recipient_name" class="form-label"><?= __('recipient_name') ?> <span class="text-danger">*</span></label>
                                    <input type="text" class="form-control" id="recipient_name" name="recipient_name" 
                                           value="<?= htmlspecialchars($etransfer_data['recipient_name'] ?? '') ?>" required>
                                </div>
                            </div>
                            
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label for="security_question" class="form-label"><?= __('security_question') ?></label>
                                    <input type="text" class="form-control" id="security_question" name="security_question" 
                                           value="<?= htmlspecialchars($etransfer_data['security_question'] ?? '') ?>">
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label for="security_answer" class="form-label"><?= __('security_answer') ?></label>
                                    <input type="text" class="form-control" id="security_answer" name="security_answer" 
                                           value="<?= htmlspecialchars($etransfer_data['security_answer'] ?? '') ?>">
                                </div>
                            </div>
                            
                            <!-- e-Transfer Workflow Settings -->
                            <div class="card mb-3 bg-light">
                                <div class="card-header">
                                    <h6 class="mb-0"><?= __('etransfer_workflow_settings') ?></h6>
                                </div>
                                <div class="card-body">
                                    <div class="row">
                                        <div class="col-md-6">
                                            <div class="form-check mb-3">
                                                <input class="form-check-input" type="checkbox" id="etransfer_auto_email" name="auto_email_instructions" 
                                                       <?= ($etransfer_data['auto_email_instructions'] ?? true) ? 'checked' : '' ?>>
                                                <label class="form-check-label" for="etransfer_auto_email">
                                                    <?= __('auto_email_instructions') ?>
                                                </label>
                                                <div class="form-text"><?= __('etransfer_email_help') ?></div>
                                            </div>
                                            
                                            <div class="form-check mb-3">
                                                <input class="form-check-input" type="checkbox" id="etransfer_require_confirmation" name="require_confirmation" 
                                                       <?= ($etransfer_data['require_confirmation'] ?? true) ? 'checked' : '' ?>>
                                                <label class="form-check-label" for="etransfer_require_confirmation">
                                                    <?= __('require_payment_confirmation') ?>
                                                </label>
                                                <div class="form-text"><?= __('etransfer_confirmation_help') ?></div>
                                            </div>
                                            
                                            <div class="form-check mb-3">
                                                <input class="form-check-input" type="checkbox" id="etransfer_allow_manual" name="allow_manual_details" 
                                                       <?= ($etransfer_data['allow_manual_details'] ?? true) ? 'checked' : '' ?>>
                                                <label class="form-check-label" for="etransfer_allow_manual">
                                                    <?= __('allow_manual_details') ?>
                                                </label>
                                                <div class="form-text"><?= __('manual_details_help') ?></div>
                                            </div>
                                            
                                            <div class="form-check mb-3">
                                                <input class="form-check-input" type="checkbox" id="etransfer_require_image" name="require_image_upload" 
                                                       <?= ($etransfer_data['require_image_upload'] ?? false) ? 'checked' : '' ?>>
                                                <label class="form-check-label" for="etransfer_require_image">
                                                    <?= __('require_image_upload') ?>
                                                </label>
                                                <div class="form-text"><?= __('etransfer_image_help') ?></div>
                                            </div>
                                        </div>
                                        
                                        <div class="col-md-6">
                                            <div class="mb-3">
                                                <label for="etransfer_max_file_size" class="form-label"><?= __('max_file_size_mb') ?></label>
                                                <input type="number" class="form-control" id="etransfer_max_file_size" name="max_file_size" 
                                                       value="<?= htmlspecialchars($etransfer_data['max_file_size'] ?? '10') ?>" min="1" max="50">
                                                <div class="form-text"><?= __('max_file_size_help') ?></div>
                                            </div>
                                            
                                            <div class="mb-3">
                                                <label for="etransfer_allowed_file_types" class="form-label"><?= __('allowed_file_types') ?></label>
                                                <input type="text" class="form-control" id="etransfer_allowed_file_types" name="allowed_file_types" 
                                                       value="<?= htmlspecialchars($etransfer_data['allowed_file_types'] ?? 'jpg,jpeg,png,pdf') ?>"
                                                       placeholder="jpg,jpeg,png,pdf">
                                                <div class="form-text"><?= __('allowed_file_types_help') ?></div>
                                            </div>
                                            
                                            <div class="mb-3">
                                                <label for="etransfer_confirmation_timeout" class="form-label"><?= __('confirmation_timeout_hours') ?></label>
                                                <input type="number" class="form-control" id="etransfer_confirmation_timeout" name="confirmation_timeout" 
                                                       value="<?= htmlspecialchars($etransfer_data['confirmation_timeout'] ?? '24') ?>" min="1" max="168">
                                                <div class="form-text"><?= __('etransfer_timeout_help') ?></div>
                                            </div>
                                            
                                            <div class="form-check mb-3">
                                                <input class="form-check-input" type="checkbox" id="etransfer_auto_accept" name="auto_accept_known" 
                                                       <?= ($etransfer_data['auto_accept_known'] ?? false) ? 'checked' : '' ?>>
                                                <label class="form-check-label" for="etransfer_auto_accept">
                                                    <?= __('auto_accept_from_known_users') ?>
                                                </label>
                                                <div class="form-text"><?= __('auto_accept_help') ?></div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="mb-3">
                                <label for="etransfer_instructions" class="form-label"><?= __('payment_instructions') ?></label>
                                <textarea class="form-control" id="etransfer_instructions" name="instructions" rows="5" 
                                          placeholder="<?= __('etransfer_instructions_placeholder') ?>"><?= htmlspecialchars($etransfer_config['instructions'] ?? '') ?></textarea>
                                <div class="form-text"><?= __('etransfer_instructions_help') ?></div>
                            </div>
                            
                            <div class="mb-3">
                                <label for="etransfer_email_template" class="form-label"><?= __('email_template_custom') ?></label>
                                <textarea class="form-control" id="etransfer_email_template" name="email_template" rows="8" 
                                          placeholder="<?= __('etransfer_email_template_placeholder') ?>"><?= htmlspecialchars($etransfer_config['email_template'] ?? '') ?></textarea>
                                <div class="form-text"><?= __('email_template_help') ?></div>
                            </div>
                                        </div>
                                        
                                        <div class="col-lg-4">
                                            <div class="card bg-light">
                                                <div class="card-body">
                                                    <h6><?= __('allowed_countries') ?></h6>
                                                    <div class="alert alert-warning small">
                                                        <?= __('etransfer_canada_restriction') ?>
                                                    </div>
                                                    <!-- Pre-select Canada only -->
                                                    <input type="hidden" name="allowed_countries[]" value="CAN">
                                                    <div class="form-check">
                                                        <input class="form-check-input" type="checkbox" checked disabled>
                                                        <label class="form-check-label">
                                                            Canada (<?= __('required') ?>)
                                                        </label>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                    
                                    <button type="submit" class="btn btn-primary">
                                        <i class="fas fa-save me-2"></i><?= __('save_etransfer_config') ?>
                                    </button>
                                </form>
                            </div>
                            
                            <!-- Credit Card Tab -->
                            <div class="tab-pane fade" id="credit-card" role="tabpanel">
                                <?php 
                                $cc_config = $methods_config['credit_card'] ?? null;
                                $cc_data = $cc_config ? json_decode($cc_config['config_data'], true) : [];
                                ?>
                                <form method="POST">
                                    <input type="hidden" name="csrf_token" value="<?= generateCSRFToken() ?>">
                                    <input type="hidden" name="action" value="update_payment_method">
                                    <input type="hidden" name="method_name" value="credit_card">
                                    
                                    <div class="row">
                                        <div class="col-lg-8">
                                            <div class="form-check mb-3">
                                                <input class="form-check-input" type="checkbox" id="cc_enabled" name="is_enabled" 
                                                       <?= ($cc_config && $cc_config['is_enabled']) ? 'checked' : '' ?>>
                                                <label class="form-check-label" for="cc_enabled">
                                                    <strong><?= __('enable_credit_card') ?></strong>
                                                </label>
                                            </div>
                                            
                                            <h6><?= __('stripe_configuration') ?></h6>
                                            <div class="row">
                                                <div class="col-md-6 mb-3">
                                                    <label for="stripe_publishable_key" class="form-label"><?= __('stripe_publishable_key') ?></label>
                                                    <input type="text" class="form-control" id="stripe_publishable_key" name="stripe_publishable_key" 
                                                           value="<?= htmlspecialchars($cc_data['stripe_publishable_key'] ?? '') ?>">
                                                </div>
                                                <div class="col-md-6 mb-3">
                                                    <label for="stripe_secret_key" class="form-label"><?= __('stripe_secret_key') ?></label>
                                                    <input type="password" class="form-control" id="stripe_secret_key" name="stripe_secret_key" 
                                                           value="<?= htmlspecialchars($cc_data['stripe_secret_key'] ?? '') ?>">
                                                </div>
                                            </div>
                                            
                                            <h6><?= __('paypal_configuration') ?></h6>
                                            <div class="row">
                                                <div class="col-md-6 mb-3">
                                                    <label for="paypal_client_id" class="form-label"><?= __('paypal_client_id') ?></label>
                                                    <input type="text" class="form-control" id="paypal_client_id" name="paypal_client_id" 
                                                           value="<?= htmlspecialchars($cc_data['paypal_client_id'] ?? '') ?>">
                                                </div>
                                                <div class="col-md-6 mb-3">
                                                    <label for="paypal_secret" class="form-label"><?= __('paypal_secret') ?></label>
                                                    <input type="password" class="form-control" id="paypal_secret" name="paypal_secret" 
                                                           value="<?= htmlspecialchars($cc_data['paypal_secret'] ?? '') ?>">
                                                </div>
                                            </div>
                                        </div>
                                        
                                        <div class="col-lg-4">
                                            <div class="card bg-light">
                                                <div class="card-body">
                                                    <h6><?= __('allowed_countries') ?></h6>
                                                    <?php 
                                                    $cc_countries = $cc_config ? json_decode($cc_config['allowed_countries'], true) : [];
                                                    ?>
                                                    <?php foreach ($countries as $country): ?>
                                                    <div class="form-check">
                                                        <input class="form-check-input" type="checkbox" name="allowed_countries[]" 
                                                               value="<?= $country['country_code'] ?>" id="cc_<?= $country['country_code'] ?>"
                                                               <?= in_array($country['country_code'], $cc_countries) ? 'checked' : '' ?>>
                                                        <label class="form-check-label" for="cc_<?= $country['country_code'] ?>">
                                                            <?= htmlspecialchars($country['country_name']) ?>
                                                        </label>
                                                    </div>
                                                    <?php endforeach; ?>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                    
                                    <button type="submit" class="btn btn-primary">
                                        <i class="fas fa-save me-2"></i><?= __('save_credit_card_config') ?>
                                    </button>
                                </form>
                            </div>
                            
                            <!-- Subscription Assignment Tab -->
                            <div class="tab-pane fade" id="subscription-assignment" role="tabpanel">
                                <div class="alert alert-warning">
                                    <i class="fas fa-shield-alt me-2"></i>
                                    <?= __('subscription_assignment_warning') ?>
                                </div>
                                
                                <form method="POST" id="subscriptionForm">
                                    <input type="hidden" name="csrf_token" value="<?= generateCSRFToken() ?>">
                                    <input type="hidden" name="action" value="assign_subscription">
                                    
                                    <div class="row">
                                        <div class="col-md-6 mb-3">
                                            <label for="user_id" class="form-label">
                                                <?= __('select_user') ?> <span class="text-danger">*</span>
                                            </label>
                                            <select class="form-select" id="user_id" name="user_id" required>
                                                <option value=""><?= __('select_user') ?></option>
                                                <?php foreach ($users as $user): ?>
                                                <option value="<?= $user['id'] ?>" 
                                                        <?= ($user['current_scheme'] === 'subscription') ? 'disabled' : '' ?>>
                                                    <?= htmlspecialchars($user['first_name'] . ' ' . $user['last_name']) ?> 
                                                    (<?= htmlspecialchars($user['email']) ?>)
                                                    <?= ($user['current_scheme'] === 'subscription') ? ' - Already on subscription' : '' ?>
                                                </option>
                                                <?php endforeach; ?>
                                            </select>
                                        </div>
                                        
                                        <div class="col-md-6 mb-3">
                                            <label for="admin_2fa_code" class="form-label">
                                                <?= __('admin_2fa_code') ?> <span class="text-danger">*</span>
                                            </label>
                                            <input type="text" class="form-control" id="admin_2fa_code" name="admin_2fa_code" 
                                                   placeholder="000000" maxlength="6" required pattern="[0-9]{6}">
                                            <div class="form-text"><?= __('admin_2fa_help') ?></div>
                                        </div>
                                    </div>
                                    
                                    <button type="submit" class="btn btn-warning">
                                        <i class="fas fa-user-plus me-2"></i><?= __('assign_subscription_scheme') ?>
                                    </button>
                                </form>
                                
                                <!-- Current Subscriptions -->
                                <hr class="my-4">
                                <h6><?= __('current_subscription_users') ?></h6>
                                <div class="table-responsive">
                                    <table class="table table-sm">
                                        <thead>
                                            <tr>
                                                <th><?= __('user') ?></th>
                                                <th><?= __('email') ?></th>
                                                <th><?= __('assigned_date') ?></th>
                                                <th><?= __('status') ?></th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($users as $user): ?>
                                                <?php if ($user['current_scheme'] === 'subscription'): ?>
                                                <tr>
                                                    <td><?= htmlspecialchars($user['first_name'] . ' ' . $user['last_name']) ?></td>
                                                    <td><?= htmlspecialchars($user['email']) ?></td>
                                                    <td>Recent</td>
                                                    <td><span class="badge bg-success"><?= __('active') ?></span></td>
                                                </tr>
                                                <?php endif; ?>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Form validation
        document.querySelectorAll('form').forEach(form => {
            form.addEventListener('submit', function(e) {
                const enabledCheckbox = this.querySelector('input[name="is_enabled"]');
                const requiredFields = this.querySelectorAll('input[required]');
                
                if (enabledCheckbox && enabledCheckbox.checked) {
                    let hasErrors = false;
                    
                    requiredFields.forEach(field => {
                        if (!field.value.trim()) {
                            field.classList.add('is-invalid');
                            hasErrors = true;
                        } else {
                            field.classList.remove('is-invalid');
                        }
                    });
                    
                    if (hasErrors) {
                        e.preventDefault();
                        alert('<?= __('please_fill_required_fields') ?>');
                        return false;
                    }
                }
            });
        });
        
        // Subscription assignment confirmation
        document.getElementById('subscriptionForm').addEventListener('submit', function(e) {
            const userSelect = document.getElementById('user_id');
            const userName = userSelect.options[userSelect.selectedIndex].text;
            
            if (!confirm(`<?= __('confirm_subscription_assignment') ?>\n\n${userName}`)) {
                e.preventDefault();
                return false;
            }
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
</body>
</html>
