<?php
/**
 * Payment Gateway Status Check
 * LoanFlow Personal Loan Management System
 */

require_once '../includes/functions.php';
require_once '../includes/enhanced_payment.php';
require_once '../includes/language.php';

// Require admin access
requireRole('admin');

// Initialize language
LanguageManager::init();

$current_user = getCurrentUser();

// Get payment gateway status
$gateway_status = EnhancedPaymentManager::getGatewayStatus();
$available_gateways = EnhancedPaymentManager::getAvailablePaymentGateways();

?>
<!DOCTYPE html>
<html lang="<?= LanguageManager::getCurrentLanguage() ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= __('payment_gateway_status') ?> - LoanFlow Admin</title>
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
        <!-- Header -->
        <div class="d-flex justify-content-between align-items-center mb-4">
            <div>
                <h1 class="h3 mb-0">
                    <i class="fas fa-credit-card me-2 text-primary"></i><?= __('payment_gateway_status') ?>
                </h1>
                <p class="text-muted mt-2"><?= __('verify_payment_gateway_configuration') ?></p>
            </div>
            <div>
                <a href="system-settings.php" class="btn btn-primary">
                    <i class="fas fa-cog me-2"></i><?= __('configure_gateways') ?>
                </a>
            </div>
        </div>

        <!-- Gateway Status Overview -->
        <div class="row mb-4">
            <div class="col-md-6 col-lg-4">
                <div class="card">
                    <div class="card-body text-center">
                        <div class="mb-3">
                            <?php if (count($available_gateways) > 0): ?>
                                <i class="fas fa-check-circle fa-3x text-success"></i>
                            <?php else: ?>
                                <i class="fas fa-exclamation-triangle fa-3x text-warning"></i>
                            <?php endif; ?>
                        </div>
                        <h5 class="card-title"><?= __('available_gateways') ?></h5>
                        <h2 class="text-primary"><?= count($available_gateways) ?></h2>
                        <p class="card-text text-muted"><?= __('configured_and_enabled') ?></p>
                    </div>
                </div>
            </div>
            
            <div class="col-md-6 col-lg-4">
                <div class="card">
                    <div class="card-body text-center">
                        <div class="mb-3">
                            <?php if (EnhancedPaymentManager::hasAvailableGateways()): ?>
                                <i class="fas fa-shield-alt fa-3x text-success"></i>
                            <?php else: ?>
                                <i class="fas fa-times-circle fa-3x text-danger"></i>
                            <?php endif; ?>
                        </div>
                        <h5 class="card-title"><?= __('payment_processing') ?></h5>
                        <?php if (EnhancedPaymentManager::hasAvailableGateways()): ?>
                            <span class="badge bg-success fs-6"><?= __('active') ?></span>
                        <?php else: ?>
                            <span class="badge bg-danger fs-6"><?= __('disabled') ?></span>
                        <?php endif; ?>
                        <p class="card-text text-muted mt-2"><?= __('credit_card_processing') ?></p>
                    </div>
                </div>
            </div>
            
            <div class="col-md-6 col-lg-4">
                <div class="card">
                    <div class="card-body text-center">
                        <div class="mb-3">
                            <i class="fas fa-users fa-3x text-info"></i>
                        </div>
                        <h5 class="card-title"><?= __('customer_impact') ?></h5>
                        <?php if (EnhancedPaymentManager::hasAvailableGateways()): ?>
                            <span class="badge bg-success fs-6"><?= __('full_access') ?></span>
                        <?php else: ?>
                            <span class="badge bg-warning fs-6"><?= __('limited_options') ?></span>
                        <?php endif; ?>
                        <p class="card-text text-muted mt-2"><?= __('payment_options_available') ?></p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Detailed Gateway Status -->
        <div class="row">
            <div class="col-lg-6">
                <!-- Stripe Status -->
                <div class="card mb-4">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <h5 class="mb-0">
                            <i class="fab fa-stripe me-2"></i>Stripe Payment Gateway
                        </h5>
                        <?php if ($gateway_status['stripe']['available']): ?>
                            <span class="badge bg-success">
                                <i class="fas fa-check me-1"></i><?= __('available') ?>
                            </span>
                        <?php else: ?>
                            <span class="badge bg-danger">
                                <i class="fas fa-times me-1"></i><?= __('unavailable') ?>
                            </span>
                        <?php endif; ?>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-6">
                                <h6><?= __('configuration_status') ?></h6>
                                <ul class="list-unstyled">
                                    <li>
                                        <i class="fas fa-<?= $gateway_status['stripe']['enabled'] ? 'check text-success' : 'times text-danger' ?> me-2"></i>
                                        <?= __('gateway_enabled') ?>: 
                                        <strong><?= $gateway_status['stripe']['enabled'] ? __('yes') : __('no') ?></strong>
                                    </li>
                                    <li>
                                        <i class="fas fa-<?= $gateway_status['stripe']['configured'] ? 'check text-success' : 'times text-danger' ?> me-2"></i>
                                        <?= __('api_keys_configured') ?>: 
                                        <strong><?= $gateway_status['stripe']['configured'] ? __('yes') : __('no') ?></strong>
                                    </li>
                                </ul>
                            </div>
                            <div class="col-md-6">
                                <?php if (!empty($gateway_status['stripe']['missing_fields'])): ?>
                                    <h6 class="text-danger"><?= __('missing_configuration') ?></h6>
                                    <ul class="list-unstyled">
                                        <?php foreach ($gateway_status['stripe']['missing_fields'] as $field): ?>
                                            <li>
                                                <i class="fas fa-exclamation-triangle text-warning me-2"></i>
                                                <?= htmlspecialchars($field) ?>
                                            </li>
                                        <?php endforeach; ?>
                                    </ul>
                                <?php else: ?>
                                    <h6 class="text-success"><?= __('fully_configured') ?></h6>
                                    <p class="text-muted small"><?= __('stripe_ready_for_payments') ?></p>
                                <?php endif; ?>
                            </div>
                        </div>
                        
                        <?php if (!$gateway_status['stripe']['available']): ?>
                            <div class="alert alert-warning mt-3">
                                <i class="fas fa-info-circle me-2"></i>
                                <strong><?= __('stripe_disabled') ?>:</strong> 
                                <?= __('stripe_disabled_message') ?>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            
            <div class="col-lg-6">
                <!-- PayPal Status -->
                <div class="card mb-4">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <h5 class="mb-0">
                            <i class="fab fa-paypal me-2"></i>PayPal Payment Gateway
                        </h5>
                        <?php if ($gateway_status['paypal']['available']): ?>
                            <span class="badge bg-success">
                                <i class="fas fa-check me-1"></i><?= __('available') ?>
                            </span>
                        <?php else: ?>
                            <span class="badge bg-danger">
                                <i class="fas fa-times me-1"></i><?= __('unavailable') ?>
                            </span>
                        <?php endif; ?>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-6">
                                <h6><?= __('configuration_status') ?></h6>
                                <ul class="list-unstyled">
                                    <li>
                                        <i class="fas fa-<?= $gateway_status['paypal']['enabled'] ? 'check text-success' : 'times text-danger' ?> me-2"></i>
                                        <?= __('gateway_enabled') ?>: 
                                        <strong><?= $gateway_status['paypal']['enabled'] ? __('yes') : __('no') ?></strong>
                                    </li>
                                    <li>
                                        <i class="fas fa-<?= $gateway_status['paypal']['configured'] ? 'check text-success' : 'times text-danger' ?> me-2"></i>
                                        <?= __('api_credentials_configured') ?>: 
                                        <strong><?= $gateway_status['paypal']['configured'] ? __('yes') : __('no') ?></strong>
                                    </li>
                                </ul>
                            </div>
                            <div class="col-md-6">
                                <?php if (!empty($gateway_status['paypal']['missing_fields'])): ?>
                                    <h6 class="text-danger"><?= __('missing_configuration') ?></h6>
                                    <ul class="list-unstyled">
                                        <?php foreach ($gateway_status['paypal']['missing_fields'] as $field): ?>
                                            <li>
                                                <i class="fas fa-exclamation-triangle text-warning me-2"></i>
                                                <?= htmlspecialchars($field) ?>
                                            </li>
                                        <?php endforeach; ?>
                                    </ul>
                                <?php else: ?>
                                    <h6 class="text-success"><?= __('fully_configured') ?></h6>
                                    <p class="text-muted small"><?= __('paypal_ready_for_payments') ?></p>
                                <?php endif; ?>
                            </div>
                        </div>
                        
                        <?php if (!$gateway_status['paypal']['available']): ?>
                            <div class="alert alert-warning mt-3">
                                <i class="fas fa-info-circle me-2"></i>
                                <strong><?= __('paypal_disabled') ?>:</strong> 
                                <?= __('paypal_disabled_message') ?>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>

        <!-- Action Required Section -->
        <?php if (!EnhancedPaymentManager::hasAvailableGateways()): ?>
            <div class="row">
                <div class="col-12">
                    <div class="alert alert-danger">
                        <h5>
                            <i class="fas fa-exclamation-triangle me-2"></i><?= __('action_required') ?>
                        </h5>
                        <p><?= __('no_payment_gateways_available') ?></p>
                        <div class="mt-3">
                            <a href="system-settings.php" class="btn btn-danger">
                                <i class="fas fa-cog me-2"></i><?= __('configure_payment_gateways') ?>
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        <?php endif; ?>

        <!-- Testing Section -->
        <div class="row">
            <div class="col-12">
                <div class="card">
                    <div class="card-header">
                        <h5 class="mb-0">
                            <i class="fas fa-flask me-2"></i><?= __('payment_gateway_testing') ?>
                        </h5>
                    </div>
                    <div class="card-body">
                        <p><?= __('testing_description') ?></p>
                        
                        <div class="row">
                            <?php foreach ($available_gateways as $gateway_key => $gateway): ?>
                                <div class="col-md-6">
                                    <div class="card bg-light">
                                        <div class="card-body">
                                            <h6>
                                                <i class="fab fa-<?= $gateway_key ?> me-2"></i><?= htmlspecialchars($gateway['name']) ?>
                                            </h6>
                                            <p class="small text-muted">
                                                <?= $gateway_key === 'stripe' ? __('stripe_test_description') : __('paypal_test_description') ?>
                                            </p>
                                            <button class="btn btn-outline-primary btn-sm" onclick="testGateway('<?= $gateway_key ?>')">
                                                <i class="fas fa-play me-2"></i><?= __('test_connection') ?>
                                            </button>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                            
                            <?php if (empty($available_gateways)): ?>
                                <div class="col-12">
                                    <div class="text-center py-4">
                                        <i class="fas fa-exclamation-circle fa-3x text-muted mb-3"></i>
                                        <h6 class="text-muted"><?= __('no_gateways_to_test') ?></h6>
                                        <p class="text-muted small"><?= __('configure_gateways_first') ?></p>
                                    </div>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Scripts -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function testGateway(gateway) {
            alert(`Testing ${gateway} gateway connection... (This would perform an actual API test in production)`);
        }
        
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
