<?php
/**
 * Test Payment Options - Demonstrates Gateway Availability
 * LoanFlow Personal Loan Management System
 */

require_once 'includes/functions.php';
require_once 'includes/enhanced_payment.php';
require_once 'includes/language.php';

// Initialize language
LanguageManager::init();

// Simulate a user from USA
$user_country = 'USA';

// Get available payment methods (this will exclude disabled gateways)
$available_methods = EnhancedPaymentManager::getAvailablePaymentMethods($user_country);
$available_gateways = EnhancedPaymentManager::getAvailablePaymentGateways();
$gateway_status = EnhancedPaymentManager::getGatewayStatus();

?>
<!DOCTYPE html>
<html lang="<?= LanguageManager::getCurrentLanguage() ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Payment Options Test - LoanFlow</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="assets/css/style.css" rel="stylesheet">
</head>
<body>
    <!-- Header -->
    <nav class="navbar navbar-expand-lg navbar-dark bg-primary">
        <div class="container">
            <a class="navbar-brand fw-bold" href="index.php">
                <i class="fas fa-hand-holding-usd me-2"></i>LoanFlow
            </a>
            <div class="navbar-nav ms-auto">
                <a class="nav-link" href="admin/payment-gateway-status.php">
                    <i class="fas fa-cog me-1"></i>Admin Gateway Status
                </a>
            </div>
        </div>
    </nav>

    <div class="container py-5">
        <!-- Header -->
        <div class="text-center mb-5">
            <h1 class="display-5 fw-bold mb-3">
                <i class="fas fa-credit-card me-3 text-primary"></i>Payment Options Test
            </h1>
            <p class="lead text-muted">
                This page demonstrates how payment options are filtered based on admin configuration
            </p>
        </div>

        <!-- Gateway Configuration Status -->
        <div class="row mb-5">
            <div class="col-lg-8 mx-auto">
                <div class="card">
                    <div class="card-header bg-primary text-white">
                        <h5 class="mb-0">
                            <i class="fas fa-cogs me-2"></i>Current Gateway Configuration
                        </h5>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-6">
                                <h6><i class="fab fa-stripe me-2"></i>Stripe</h6>
                                <ul class="list-unstyled">
                                    <li>
                                        <span class="badge bg-<?= $gateway_status['stripe']['enabled'] ? 'success' : 'secondary' ?> me-2">
                                            <?= $gateway_status['stripe']['enabled'] ? 'Enabled' : 'Disabled' ?>
                                        </span>
                                    </li>
                                    <li>
                                        <span class="badge bg-<?= $gateway_status['stripe']['configured'] ? 'success' : 'warning' ?> me-2">
                                            <?= $gateway_status['stripe']['configured'] ? 'Configured' : 'Not Configured' ?>
                                        </span>
                                    </li>
                                    <li>
                                        <span class="badge bg-<?= $gateway_status['stripe']['available'] ? 'success' : 'danger' ?> me-2">
                                            <?= $gateway_status['stripe']['available'] ? 'Available' : 'Unavailable' ?>
                                        </span>
                                    </li>
                                </ul>
                                <?php if (!empty($gateway_status['stripe']['missing_fields'])): ?>
                                    <small class="text-danger">
                                        Missing: <?= implode(', ', $gateway_status['stripe']['missing_fields']) ?>
                                    </small>
                                <?php endif; ?>
                            </div>
                            <div class="col-md-6">
                                <h6><i class="fab fa-paypal me-2"></i>PayPal</h6>
                                <ul class="list-unstyled">
                                    <li>
                                        <span class="badge bg-<?= $gateway_status['paypal']['enabled'] ? 'success' : 'secondary' ?> me-2">
                                            <?= $gateway_status['paypal']['enabled'] ? 'Enabled' : 'Disabled' ?>
                                        </span>
                                    </li>
                                    <li>
                                        <span class="badge bg-<?= $gateway_status['paypal']['configured'] ? 'success' : 'warning' ?> me-2">
                                            <?= $gateway_status['paypal']['configured'] ? 'Configured' : 'Not Configured' ?>
                                        </span>
                                    </li>
                                    <li>
                                        <span class="badge bg-<?= $gateway_status['paypal']['available'] ? 'success' : 'danger' ?> me-2">
                                            <?= $gateway_status['paypal']['available'] ? 'Available' : 'Unavailable' ?>
                                        </span>
                                    </li>
                                </ul>
                                <?php if (!empty($gateway_status['paypal']['missing_fields'])): ?>
                                    <small class="text-danger">
                                        Missing: <?= implode(', ', $gateway_status['paypal']['missing_fields']) ?>
                                    </small>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Available Payment Methods -->
        <div class="row mb-5">
            <div class="col-lg-8 mx-auto">
                <div class="card">
                    <div class="card-header">
                        <h5 class="mb-0">
                            <i class="fas fa-list me-2"></i>Available Payment Methods for Customers
                            <span class="badge bg-primary ms-2"><?= count($available_methods) ?></span>
                        </h5>
                    </div>
                    <div class="card-body">
                        <?php if (!empty($available_methods)): ?>
                            <div class="row">
                                <?php foreach ($available_methods as $method_name => $method_config): ?>
                                    <div class="col-md-6 mb-3">
                                        <div class="card bg-light">
                                            <div class="card-body">
                                                <h6>
                                                    <i class="fas fa-<?= $method_name === 'credit_card' ? 'credit-card' : ($method_name === 'wire_transfer' ? 'university' : ($method_name === 'crypto' ? 'bitcoin' : 'envelope')) ?> me-2"></i>
                                                    <?= ucwords(str_replace('_', ' ', $method_name)) ?>
                                                </h6>
                                                <p class="small text-muted">
                                                    <?= $method_name === 'credit_card' ? 'Credit card processing via configured gateways' : 
                                                        ($method_name === 'wire_transfer' ? 'Bank wire transfer' : 
                                                        ($method_name === 'crypto' ? 'Cryptocurrency payment' : 'E-Transfer (Canada only)')) ?>
                                                </p>
                                                <span class="badge bg-success">
                                                    <i class="fas fa-check me-1"></i>Available
                                                </span>
                                            </div>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php else: ?>
                            <div class="text-center py-4">
                                <i class="fas fa-exclamation-triangle fa-3x text-warning mb-3"></i>
                                <h6 class="text-warning">No Payment Methods Available</h6>
                                <p class="text-muted">
                                    No payment methods are currently configured and enabled. 
                                    Please contact an administrator to configure payment gateways.
                                </p>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>

        <!-- Credit Card Gateway Details -->
        <div class="row mb-5">
            <div class="col-lg-8 mx-auto">
                <div class="card">
                    <div class="card-header">
                        <h5 class="mb-0">
                            <i class="fas fa-credit-card me-2"></i>Credit Card Gateway Availability
                        </h5>
                    </div>
                    <div class="card-body">
                        <?php if (!empty($available_gateways)): ?>
                            <div class="alert alert-success">
                                <h6><i class="fas fa-check-circle me-2"></i>Credit Card Processing Available</h6>
                                <p class="mb-0">
                                    The following payment gateways are configured and available for credit card processing:
                                </p>
                            </div>
                            
                            <div class="row">
                                <?php foreach ($available_gateways as $gateway_key => $gateway): ?>
                                    <div class="col-md-6">
                                        <div class="card border-success">
                                            <div class="card-body">
                                                <h6>
                                                    <i class="fab fa-<?= $gateway_key ?> me-2"></i>
                                                    <?= htmlspecialchars($gateway['name']) ?>
                                                </h6>
                                                <ul class="list-unstyled small">
                                                    <li>
                                                        <i class="fas fa-check text-success me-2"></i>
                                                        Enabled and Configured
                                                    </li>
                                                    <?php if ($gateway_key === 'paypal' && $gateway['sandbox']): ?>
                                                        <li>
                                                            <i class="fas fa-flask text-info me-2"></i>
                                                            Sandbox Mode
                                                        </li>
                                                    <?php endif; ?>
                                                    <li>
                                                        <i class="fas fa-shield-alt text-primary me-2"></i>
                                                        Secure Processing
                                                    </li>
                                                </ul>
                                            </div>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php else: ?>
                            <div class="alert alert-danger">
                                <h6><i class="fas fa-times-circle me-2"></i>Credit Card Processing Unavailable</h6>
                                <p class="mb-0">
                                    No payment gateways (Stripe or PayPal) are currently configured and enabled. 
                                    Credit card payments are not available to customers.
                                </p>
                            </div>
                            
                            <div class="text-center py-3">
                                <i class="fas fa-credit-card fa-3x text-muted mb-3"></i>
                                <h6 class="text-muted">Credit Card Option Hidden from Customers</h6>
                                <p class="text-muted small">
                                    This demonstrates how the system automatically hides payment options 
                                    when administrators haven't provided the necessary account details.
                                </p>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>

        <!-- Test Results Summary -->
        <div class="row">
            <div class="col-lg-8 mx-auto">
                <div class="card border-info">
                    <div class="card-header bg-info text-white">
                        <h5 class="mb-0">
                            <i class="fas fa-clipboard-check me-2"></i>Test Results Summary
                        </h5>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-6">
                                <h6>Gateway Status</h6>
                                <ul class="list-unstyled">
                                    <li>
                                        <i class="fas fa-circle text-<?= $gateway_status['stripe']['available'] ? 'success' : 'danger' ?> me-2"></i>
                                        Stripe: <?= $gateway_status['stripe']['available'] ? 'Available' : 'Disabled' ?>
                                    </li>
                                    <li>
                                        <i class="fas fa-circle text-<?= $gateway_status['paypal']['available'] ? 'success' : 'danger' ?> me-2"></i>
                                        PayPal: <?= $gateway_status['paypal']['available'] ? 'Available' : 'Disabled' ?>
                                    </li>
                                </ul>
                            </div>
                            <div class="col-md-6">
                                <h6>Customer Impact</h6>
                                <ul class="list-unstyled">
                                    <li>
                                        <i class="fas fa-users me-2"></i>
                                        Payment Options: <strong><?= count($available_methods) ?></strong>
                                    </li>
                                    <li>
                                        <i class="fas fa-credit-card me-2"></i>
                                        Credit Cards: 
                                        <strong class="text-<?= count($available_gateways) > 0 ? 'success' : 'danger' ?>">
                                            <?= count($available_gateways) > 0 ? 'Available' : 'Unavailable' ?>
                                        </strong>
                                    </li>
                                </ul>
                            </div>
                        </div>
                        
                        <div class="mt-3">
                            <div class="alert alert-info mb-0">
                                <small>
                                    <strong>How it works:</strong> 
                                    The system automatically checks if payment gateways are both enabled in admin settings 
                                    AND have the required API credentials configured. Only properly configured gateways 
                                    appear as payment options for customers. This ensures customers never see broken payment methods.
                                </small>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Footer -->
    <footer class="bg-dark text-white py-4 mt-5">
        <div class="container text-center">
            <p class="mb-0">
                <i class="fas fa-info-circle me-2"></i>
                This is a demonstration page showing how payment gateway availability is controlled by admin configuration.
            </p>
        </div>
    </footer>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
