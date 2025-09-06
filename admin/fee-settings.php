<?php
/**
 * Fee Structure Settings
 * LoanFlow Personal Loan Management System
 */

require_once '../includes/functions.php';
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
            case 'update_fee_structure':
                $fee_data = [
                    'fee_structure' => sanitizeInput($_POST['fee_structure'] ?? 'subscription'),
                    'subscription_fee' => floatval($_POST['subscription_fee'] ?? 99.00),
                    'subscription_max_months' => intval($_POST['subscription_max_months'] ?? 6),
                    'allow_full_payment' => isset($_POST['allow_full_payment']) ? '1' : '0',
                    'percentage_fee' => floatval($_POST['percentage_fee'] ?? 5.0),
                    'percentage_min_fee' => floatval($_POST['percentage_min_fee'] ?? 500),
                    'percentage_max_fee' => floatval($_POST['percentage_max_fee'] ?? 5000),
                    'refund_enabled' => isset($_POST['refund_enabled']) ? '1' : '0',
                    'refund_percentage' => intval($_POST['refund_percentage'] ?? 80),
                    'refund_conditions' => sanitizeInput($_POST['refund_conditions'] ?? ''),
                    'payment_due_days' => intval($_POST['payment_due_days'] ?? 7)
                ];
                
                if (updateFeeStructureSettings($fee_data)) {
                    $success = __('fee_structure_updated');
                    logAudit('fee_structure_updated', 'system_settings', null, $current_user['id'], $fee_data);
                } else {
                    $error = __('fee_structure_update_failed');
                }
                break;
                
            case 'update_display_settings':
                $display_data = [
                    'show_pricing_page' => isset($_POST['show_pricing_page']) ? '1' : '0',
                    'pricing_page_content' => sanitizeInput($_POST['pricing_page_content'] ?? ''),
                    'hide_fees_until_step' => intval($_POST['hide_fees_until_step'] ?? 4),
                    'fee_disclosure_text' => sanitizeInput($_POST['fee_disclosure_text'] ?? ''),
                    'terms_conditions_text' => sanitizeInput($_POST['terms_conditions_text'] ?? ''),
                    'refund_policy_text' => sanitizeInput($_POST['refund_policy_text'] ?? '')
                ];
                
                if (updateFeeDisplaySettings($display_data)) {
                    $success = __('display_settings_updated');
                    logAudit('fee_display_updated', 'system_settings', null, $current_user['id'], $display_data);
                } else {
                    $error = __('display_settings_update_failed');
                }
                break;
        }
    }
}

// Get current settings
$fee_settings = getFeeStructureSettings();
$display_settings = getFeeDisplaySettings();

?>
<!DOCTYPE html>
<html lang="<?= LanguageManager::getCurrentLanguage() ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= __('fee_structure_settings') ?> - LoanFlow Admin</title>
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

        <div class="row">
            <div class="col-12">
                <div class="card">
                    <div class="card-header">
                        <h4 class="mb-0">
                            <i class="fas fa-dollar-sign me-2"></i><?= __('fee_structure_settings') ?>
                        </h4>
                    </div>
                    <div class="card-body">
                        
                        <!-- Navigation Tabs -->
                        <ul class="nav nav-tabs" id="feeTabs" role="tablist">
                            <li class="nav-item" role="presentation">
                                <button class="nav-link active" id="structure-tab" data-bs-toggle="tab" data-bs-target="#structure" type="button" role="tab">
                                    <i class="fas fa-cogs me-2"></i><?= __('fee_structure') ?>
                                </button>
                            </li>
                            <li class="nav-item" role="presentation">
                                <button class="nav-link" id="display-tab" data-bs-toggle="tab" data-bs-target="#display" type="button" role="tab">
                                    <i class="fas fa-eye me-2"></i><?= __('display_settings') ?>
                                </button>
                            </li>
                            <li class="nav-item" role="presentation">
                                <button class="nav-link" id="preview-tab" data-bs-toggle="tab" data-bs-target="#preview" type="button" role="tab">
                                    <i class="fas fa-search me-2"></i><?= __('preview') ?>
                                </button>
                            </li>
                        </ul>
                        
                        <!-- Tab Content -->
                        <div class="tab-content" id="feeTabsContent">
                            
                            <!-- Fee Structure Tab -->
                            <div class="tab-pane fade show active" id="structure" role="tabpanel">
                                <form method="POST" class="mt-4">
                                    <input type="hidden" name="csrf_token" value="<?= generateCSRFToken() ?>">
                                    <input type="hidden" name="action" value="update_fee_structure">
                                    
                                    <div class="row">
                                        <div class="col-lg-6">
                                            <h5><?= __('primary_fee_structure') ?></h5>
                                            
                                            <div class="mb-3">
                                                <label class="form-label">
                                                    <?= __('fee_structure_type') ?>
                                                    <i class="fas fa-question-circle ms-1" data-bs-toggle="tooltip" title="<?= __('help_fee_structure_type') ?>"></i>
                                                </label>
                                                <div class="form-check">
                                                    <input class="form-check-input" type="radio" name="fee_structure" id="subscription" value="subscription" 
                                                           <?= ($fee_settings['fee_structure'] ?? 'subscription') === 'subscription' ? 'checked' : '' ?>>
                                                    <label class="form-check-label" for="subscription">
                                                        <strong><?= __('subscription_model') ?></strong><br>
                                                        <small class="text-muted"><?= __('subscription_model_description') ?></small>
                                                    </label>
                                                </div>
                                                <div class="form-check">
                                                    <input class="form-check-input" type="radio" name="fee_structure" id="percentage" value="percentage" 
                                                           <?= ($fee_settings['fee_structure'] ?? '') === 'percentage' ? 'checked' : '' ?>>
                                                    <label class="form-check-label" for="percentage">
                                                        <strong><?= __('percentage_model') ?></strong><br>
                                                        <small class="text-muted"><?= __('percentage_model_description') ?></small>
                                                    </label>
                                                </div>
                                            </div>
                                            
                                            <!-- Subscription Settings -->
                                            <div class="fee-settings" id="subscription-settings">
                                                <h6><?= __('subscription_settings') ?></h6>
                                                
                                                <div class="mb-3">
                                                    <label for="subscription_fee" class="form-label">
                                                        <?= __('monthly_subscription_fee') ?>
                                                        <i class="fas fa-question-circle ms-1" data-bs-toggle="tooltip" title="<?= __('help_subscription_fee') ?>"></i>
                                                    </label>
                                                    <div class="input-group">
                                                        <span class="input-group-text">$</span>
                                                        <input type="number" class="form-control" id="subscription_fee" name="subscription_fee" 
                                                               value="<?= htmlspecialchars($fee_settings['subscription_fee'] ?? '99.00') ?>" 
                                                               step="0.01" min="1" max="999">
                                                    </div>
                                                </div>
                                                
                                                <div class="mb-3">
                                                    <label for="subscription_max_months" class="form-label">
                                                        <?= __('maximum_subscription_months') ?>
                                                        <i class="fas fa-question-circle ms-1" data-bs-toggle="tooltip" title="<?= __('help_max_months') ?>"></i>
                                                    </label>
                                                    <input type="number" class="form-control" id="subscription_max_months" name="subscription_max_months" 
                                                           value="<?= htmlspecialchars($fee_settings['subscription_max_months'] ?? '6') ?>" 
                                                           min="1" max="12">
                                                </div>
                                                
                                                <div class="form-check mb-3">
                                                    <input class="form-check-input" type="checkbox" id="allow_full_payment" name="allow_full_payment" 
                                                           <?= ($fee_settings['allow_full_payment'] ?? '1') === '1' ? 'checked' : '' ?>>
                                                    <label class="form-check-label" for="allow_full_payment">
                                                        <?= __('allow_full_payment_option') ?>
                                                        <i class="fas fa-question-circle ms-1" data-bs-toggle="tooltip" title="<?= __('help_full_payment') ?>"></i>
                                                    </label>
                                                </div>
                                            </div>
                                            
                                            <!-- Percentage Settings -->
                                            <div class="fee-settings" id="percentage-settings" style="display: none;">
                                                <h6><?= __('percentage_settings') ?></h6>
                                                
                                                <div class="mb-3">
                                                    <label for="percentage_fee" class="form-label">
                                                        <?= __('percentage_of_loan_amount') ?>
                                                        <i class="fas fa-question-circle ms-1" data-bs-toggle="tooltip" title="<?= __('help_percentage_fee') ?>"></i>
                                                    </label>
                                                    <div class="input-group">
                                                        <input type="number" class="form-control" id="percentage_fee" name="percentage_fee" 
                                                               value="<?= htmlspecialchars($fee_settings['percentage_fee'] ?? '5.0') ?>" 
                                                               step="0.1" min="0.1" max="25">
                                                        <span class="input-group-text">%</span>
                                                    </div>
                                                </div>
                                                
                                                <div class="row">
                                                    <div class="col-md-6 mb-3">
                                                        <label for="percentage_min_fee" class="form-label">
                                                            <?= __('minimum_fee') ?>
                                                        </label>
                                                        <div class="input-group">
                                                            <span class="input-group-text">$</span>
                                                            <input type="number" class="form-control" id="percentage_min_fee" name="percentage_min_fee" 
                                                                   value="<?= htmlspecialchars($fee_settings['percentage_min_fee'] ?? '500') ?>" 
                                                                   step="1" min="100">
                                                        </div>
                                                    </div>
                                                    
                                                    <div class="col-md-6 mb-3">
                                                        <label for="percentage_max_fee" class="form-label">
                                                            <?= __('maximum_fee') ?>
                                                        </label>
                                                        <div class="input-group">
                                                            <span class="input-group-text">$</span>
                                                            <input type="number" class="form-control" id="percentage_max_fee" name="percentage_max_fee" 
                                                                   value="<?= htmlspecialchars($fee_settings['percentage_max_fee'] ?? '5000') ?>" 
                                                                   step="1" min="1000">
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                        
                                        <div class="col-lg-6">
                                            <h5><?= __('refund_policy') ?></h5>
                                            
                                            <div class="form-check mb-3">
                                                <input class="form-check-input" type="checkbox" id="refund_enabled" name="refund_enabled" 
                                                       <?= ($fee_settings['refund_enabled'] ?? '1') === '1' ? 'checked' : '' ?>>
                                                <label class="form-check-label" for="refund_enabled">
                                                    <?= __('enable_refund_policy') ?>
                                                    <i class="fas fa-question-circle ms-1" data-bs-toggle="tooltip" title="<?= __('help_refund_policy') ?>"></i>
                                                </label>
                                            </div>
                                            
                                            <div class="refund-settings" id="refund-settings">
                                                <div class="mb-3">
                                                    <label for="refund_percentage" class="form-label">
                                                        <?= __('refund_percentage') ?>
                                                        <i class="fas fa-question-circle ms-1" data-bs-toggle="tooltip" title="<?= __('help_refund_percentage') ?>"></i>
                                                    </label>
                                                    <div class="input-group">
                                                        <input type="number" class="form-control" id="refund_percentage" name="refund_percentage" 
                                                               value="<?= htmlspecialchars($fee_settings['refund_percentage'] ?? '80') ?>" 
                                                               min="50" max="100">
                                                        <span class="input-group-text">%</span>
                                                    </div>
                                                    <div class="form-text"><?= __('refund_percentage_help') ?></div>
                                                </div>
                                                
                                                <div class="mb-3">
                                                    <label for="refund_conditions" class="form-label">
                                                        <?= __('refund_conditions') ?>
                                                    </label>
                                                    <textarea class="form-control" id="refund_conditions" name="refund_conditions" rows="4" 
                                                              placeholder="<?= __('refund_conditions_placeholder') ?>"><?= htmlspecialchars($fee_settings['refund_conditions'] ?? '') ?></textarea>
                                                </div>
                                            </div>
                                            
                                            <h5 class="mt-4"><?= __('payment_terms') ?></h5>
                                            
                                            <div class="mb-3">
                                                <label for="payment_due_days" class="form-label">
                                                    <?= __('payment_due_days') ?>
                                                    <i class="fas fa-question-circle ms-1" data-bs-toggle="tooltip" title="<?= __('help_payment_due_days') ?>"></i>
                                                </label>
                                                <div class="input-group">
                                                    <input type="number" class="form-control" id="payment_due_days" name="payment_due_days" 
                                                           value="<?= htmlspecialchars($fee_settings['payment_due_days'] ?? '7') ?>" 
                                                           min="1" max="30">
                                                    <span class="input-group-text"><?= __('days') ?></span>
                                                </div>
                                                <div class="form-text"><?= __('payment_due_days_help') ?></div>
                                            </div>
                                        </div>
                                    </div>
                                    
                                    <button type="submit" class="btn btn-primary">
                                        <i class="fas fa-save me-2"></i><?= __('save_fee_structure') ?>
                                    </button>
                                </form>
                            </div>
                            
                            <!-- Display Settings Tab -->
                            <div class="tab-pane fade" id="display" role="tabpanel">
                                <form method="POST" class="mt-4">
                                    <input type="hidden" name="csrf_token" value="<?= generateCSRFToken() ?>">
                                    <input type="hidden" name="action" value="update_display_settings">
                                    
                                    <div class="row">
                                        <div class="col-lg-8">
                                            <h5><?= __('pricing_display') ?></h5>
                                            
                                            <div class="form-check mb-3">
                                                <input class="form-check-input" type="checkbox" id="show_pricing_page" name="show_pricing_page" 
                                                       <?= ($display_settings['show_pricing_page'] ?? '1') === '1' ? 'checked' : '' ?>>
                                                <label class="form-check-label" for="show_pricing_page">
                                                    <?= __('show_pricing_page') ?>
                                                    <i class="fas fa-question-circle ms-1" data-bs-toggle="tooltip" title="<?= __('help_pricing_page') ?>"></i>
                                                </label>
                                            </div>
                                            
                                            <div class="mb-3" id="pricing-content-section">
                                                <label for="pricing_page_content" class="form-label">
                                                    <?= __('pricing_page_content') ?>
                                                </label>
                                                <textarea class="form-control" id="pricing_page_content" name="pricing_page_content" rows="6" 
                                                          placeholder="<?= __('pricing_page_content_placeholder') ?>"><?= htmlspecialchars($display_settings['pricing_page_content'] ?? '') ?></textarea>
                                            </div>
                                            
                                            <div class="mb-3">
                                                <label for="hide_fees_until_step" class="form-label">
                                                    <?= __('hide_fees_until_step') ?>
                                                    <i class="fas fa-question-circle ms-1" data-bs-toggle="tooltip" title="<?= __('help_hide_fees_step') ?>"></i>
                                                </label>
                                                <select class="form-select" id="hide_fees_until_step" name="hide_fees_until_step">
                                                    <option value="1" <?= ($display_settings['hide_fees_until_step'] ?? '4') === '1' ? 'selected' : '' ?>><?= __('step_1_application') ?></option>
                                                    <option value="2" <?= ($display_settings['hide_fees_until_step'] ?? '4') === '2' ? 'selected' : '' ?>><?= __('step_2_documents') ?></option>
                                                    <option value="3" <?= ($display_settings['hide_fees_until_step'] ?? '4') === '3' ? 'selected' : '' ?>><?= __('step_3_review') ?></option>
                                                    <option value="4" <?= ($display_settings['hide_fees_until_step'] ?? '4') === '4' ? 'selected' : '' ?>><?= __('step_4_payment') ?></option>
                                                </select>
                                                <div class="form-text"><?= __('hide_fees_step_help') ?></div>
                                            </div>
                                            
                                            <h5 class="mt-4"><?= __('legal_texts') ?></h5>
                                            
                                            <div class="mb-3">
                                                <label for="fee_disclosure_text" class="form-label">
                                                    <?= __('fee_disclosure_text') ?>
                                                    <i class="fas fa-question-circle ms-1" data-bs-toggle="tooltip" title="<?= __('help_fee_disclosure') ?>"></i>
                                                </label>
                                                <textarea class="form-control" id="fee_disclosure_text" name="fee_disclosure_text" rows="4" 
                                                          placeholder="<?= __('fee_disclosure_placeholder') ?>"><?= htmlspecialchars($display_settings['fee_disclosure_text'] ?? '') ?></textarea>
                                            </div>
                                            
                                            <div class="mb-3">
                                                <label for="terms_conditions_text" class="form-label">
                                                    <?= __('terms_conditions_text') ?>
                                                </label>
                                                <textarea class="form-control" id="terms_conditions_text" name="terms_conditions_text" rows="4" 
                                                          placeholder="<?= __('terms_conditions_placeholder') ?>"><?= htmlspecialchars($display_settings['terms_conditions_text'] ?? '') ?></textarea>
                                            </div>
                                            
                                            <div class="mb-3">
                                                <label for="refund_policy_text" class="form-label">
                                                    <?= __('refund_policy_text') ?>
                                                </label>
                                                <textarea class="form-control" id="refund_policy_text" name="refund_policy_text" rows="4" 
                                                          placeholder="<?= __('refund_policy_placeholder') ?>"><?= htmlspecialchars($display_settings['refund_policy_text'] ?? '') ?></textarea>
                                            </div>
                                        </div>
                                        
                                        <div class="col-lg-4">
                                            <div class="card bg-light">
                                                <div class="card-body">
                                                    <h6><?= __('display_tips') ?></h6>
                                                    <ul class="small mb-0">
                                                        <li><?= __('tip_subscription_transparent') ?></li>
                                                        <li><?= __('tip_percentage_hidden') ?></li>
                                                        <li><?= __('tip_legal_compliance') ?></li>
                                                        <li><?= __('tip_clear_refund_policy') ?></li>
                                                    </ul>
                                                </div>
                                            </div>
                                            
                                            <div class="card mt-3">
                                                <div class="card-body">
                                                    <h6><?= __('fee_calculator') ?></h6>
                                                    <div class="mb-2">
                                                        <label class="form-label small"><?= __('loan_amount') ?>:</label>
                                                        <input type="number" class="form-control form-control-sm" id="calc_loan_amount" value="10000" min="1000" max="100000">
                                                    </div>
                                                    <div class="mb-2">
                                                        <button type="button" class="btn btn-sm btn-outline-primary w-100" onclick="calculateFees()">
                                                            <?= __('calculate_fee') ?>
                                                        </button>
                                                    </div>
                                                    <div id="fee-calculation-result" class="small"></div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                    
                                    <button type="submit" class="btn btn-primary">
                                        <i class="fas fa-save me-2"></i><?= __('save_display_settings') ?>
                                    </button>
                                </form>
                            </div>
                            
                            <!-- Preview Tab -->
                            <div class="tab-pane fade" id="preview" role="tabpanel">
                                <div class="mt-4">
                                    <h5><?= __('fee_structure_preview') ?></h5>
                                    
                                    <div class="row">
                                        <div class="col-lg-6">
                                            <div class="card">
                                                <div class="card-header">
                                                    <h6 class="mb-0"><?= __('subscription_preview') ?></h6>
                                                </div>
                                                <div class="card-body" id="subscription-preview">
                                                    <!-- Subscription preview content will be populated by JavaScript -->
                                                </div>
                                            </div>
                                        </div>
                                        
                                        <div class="col-lg-6">
                                            <div class="card">
                                                <div class="card-header">
                                                    <h6 class="mb-0"><?= __('percentage_preview') ?></h6>
                                                </div>
                                                <div class="card-body" id="percentage-preview">
                                                    <!-- Percentage preview content will be populated by JavaScript -->
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                    
                                    <div class="row mt-4">
                                        <div class="col-12">
                                            <div class="card">
                                                <div class="card-header">
                                                    <h6 class="mb-0"><?= __('user_journey_preview') ?></h6>
                                                </div>
                                                <div class="card-body">
                                                    <div class="timeline">
                                                        <div class="timeline-item">
                                                            <div class="timeline-marker bg-primary"></div>
                                                            <div class="timeline-content">
                                                                <h6><?= __('step_1_application') ?></h6>
                                                                <p class="text-muted small" id="step1-preview"><?= __('step1_preview_text') ?></p>
                                                            </div>
                                                        </div>
                                                        <div class="timeline-item">
                                                            <div class="timeline-marker bg-info"></div>
                                                            <div class="timeline-content">
                                                                <h6><?= __('step_2_documents') ?></h6>
                                                                <p class="text-muted small"><?= __('step2_preview_text') ?></p>
                                                            </div>
                                                        </div>
                                                        <div class="timeline-item">
                                                            <div class="timeline-marker bg-warning"></div>
                                                            <div class="timeline-content">
                                                                <h6><?= __('step_3_review') ?></h6>
                                                                <p class="text-muted small"><?= __('step3_preview_text') ?></p>
                                                            </div>
                                                        </div>
                                                        <div class="timeline-item">
                                                            <div class="timeline-marker bg-success"></div>
                                                            <div class="timeline-content">
                                                                <h6><?= __('step_4_payment') ?></h6>
                                                                <p class="text-muted small" id="step4-preview"><?= __('step4_preview_text') ?></p>
                                                            </div>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
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
        // Initialize tooltips
        var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
        var tooltipList = tooltipTriggerList.map(function (tooltipTriggerEl) {
            return new bootstrap.Tooltip(tooltipTriggerEl);
        });
        
        // Fee structure switching
        document.querySelectorAll('input[name="fee_structure"]').forEach(radio => {
            radio.addEventListener('change', function() {
                document.querySelectorAll('.fee-settings').forEach(el => el.style.display = 'none');
                const selected = this.value;
                const settingsDiv = document.getElementById(selected + '-settings');
                if (settingsDiv) {
                    settingsDiv.style.display = 'block';
                }
                updatePreview();
            });
        });
        
        // Refund policy toggle
        document.getElementById('refund_enabled').addEventListener('change', function() {
            const refundSettings = document.getElementById('refund-settings');
            refundSettings.style.display = this.checked ? 'block' : 'none';
        });
        
        // Pricing page toggle
        document.getElementById('show_pricing_page').addEventListener('change', function() {
            const pricingContent = document.getElementById('pricing-content-section');
            pricingContent.style.display = this.checked ? 'block' : 'none';
        });
        
        // Initialize visibility
        document.querySelector('input[name="fee_structure"]:checked').dispatchEvent(new Event('change'));
        document.getElementById('refund_enabled').dispatchEvent(new Event('change'));
        document.getElementById('show_pricing_page').dispatchEvent(new Event('change'));
        
        // Fee calculator
        function calculateFees() {
            const loanAmount = parseFloat(document.getElementById('calc_loan_amount').value);
            const subscriptionFee = parseFloat(document.getElementById('subscription_fee').value);
            const subscriptionMonths = parseInt(document.getElementById('subscription_max_months').value);
            const percentageFee = parseFloat(document.getElementById('percentage_fee').value);
            const minFee = parseFloat(document.getElementById('percentage_min_fee').value);
            const maxFee = parseFloat(document.getElementById('percentage_max_fee').value);
            
            let result = '<strong>Fee Calculation:</strong><br>';
            
            // Subscription calculation
            const totalSubscription = subscriptionFee * subscriptionMonths;
            result += `Subscription: $${subscriptionFee}/month × ${subscriptionMonths} months = $${totalSubscription}<br>`;
            
            // Percentage calculation
            let percentageTotal = (loanAmount * percentageFee) / 100;
            percentageTotal = Math.max(minFee, Math.min(maxFee, percentageTotal));
            result += `Percentage: ${percentageFee}% of $${loanAmount} = $${percentageTotal.toFixed(2)}<br>`;
            
            document.getElementById('fee-calculation-result').innerHTML = result;
        }
        
        // Update preview
        function updatePreview() {
            const feeStructure = document.querySelector('input[name="fee_structure"]:checked').value;
            const subscriptionFee = document.getElementById('subscription_fee').value;
            const subscriptionMonths = document.getElementById('subscription_max_months').value;
            const percentageFee = document.getElementById('percentage_fee').value;
            const hideFeeStep = document.getElementById('hide_fees_until_step').value;
            
            // Update subscription preview
            const subscriptionPreview = document.getElementById('subscription-preview');
            subscriptionPreview.innerHTML = `
                <h6 class="text-primary">Monthly Subscription Plan</h6>
                <div class="pricing-display">
                    <span class="h4">$${subscriptionFee}</span><small class="text-muted">/month</small>
                </div>
                <ul class="list-unstyled mt-3">
                    <li><i class="fas fa-check text-success me-2"></i>Up to ${subscriptionMonths} months</li>
                    <li><i class="fas fa-check text-success me-2"></i>Full payment option available</li>
                    <li><i class="fas fa-check text-success me-2"></i>Cancel anytime</li>
                </ul>
                <button class="btn btn-primary">Choose Plan</button>
            `;
            
            // Update percentage preview
            const percentagePreview = document.getElementById('percentage-preview');
            percentagePreview.innerHTML = `
                <h6 class="text-success">Success-Based Fee</h6>
                <div class="pricing-display">
                    <span class="h4">${percentageFee}%</span><small class="text-muted"> of loan amount</small>
                </div>
                <ul class="list-unstyled mt-3">
                    <li><i class="fas fa-check text-success me-2"></i>Pay only when funded</li>
                    <li><i class="fas fa-check text-success me-2"></i>80% refund guarantee</li>
                    <li><i class="fas fa-check text-success me-2"></i>No upfront costs</li>
                </ul>
                <div class="alert alert-info small">Fee disclosed at step ${hideFeeStep}</div>
            `;
            
            // Update step previews
            if (feeStructure === 'subscription') {
                document.getElementById('step1-preview').textContent = 'User sees pricing information and selects subscription plan.';
                document.getElementById('step4-preview').textContent = 'User pays subscription fee to proceed with loan application.';
            } else {
                document.getElementById('step1-preview').textContent = 'User applies without seeing fees upfront.';
                document.getElementById('step4-preview').textContent = `Fee (${percentageFee}% of loan amount) is disclosed and charged.`;
            }
        }
        
        // Initialize preview
        updatePreview();
        
        // Update preview when values change
        document.addEventListener('input', updatePreview);
        document.addEventListener('change', updatePreview);
        
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
        .timeline {
            position: relative;
            padding-left: 30px;
        }
        
        .timeline::before {
            content: '';
            position: absolute;
            left: 15px;
            top: 0;
            bottom: 0;
            width: 2px;
            background: #dee2e6;
        }
        
        .timeline-item {
            position: relative;
            margin-bottom: 30px;
        }
        
        .timeline-marker {
            position: absolute;
            left: -23px;
            width: 16px;
            height: 16px;
            border-radius: 50%;
            border: 3px solid #fff;
            box-shadow: 0 0 0 3px #dee2e6;
        }
        
        .pricing-display {
            text-align: center;
            padding: 20px;
            border: 2px solid #e9ecef;
            border-radius: 8px;
            margin: 15px 0;
        }
    </style>
</body>
</html>
