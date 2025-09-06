<?php
/**
 * Terms of Service Page
 * LoanFlow Personal Loan Management System
 */

require_once 'includes/functions.php';
require_once 'includes/language.php';

// Initialize language
LanguageManager::init();

// Get company settings for dynamic content
$company_settings = getCompanySettings();
$company_name = $company_settings['name'] ?? 'LoanFlow';
$company_email = $company_settings['email'] ?? 'legal@loanflow.com';
$company_address = $company_settings['address'] ?? 'Company Address';
$company_phone = $company_settings['phone'] ?? 'Company Phone';
$company_website = $company_settings['website'] ?? 'https://www.loanflow.com';

?>
<!DOCTYPE html>
<html lang="<?= LanguageManager::getCurrentLanguage() ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= __('terms_of_service') ?> - <?= htmlspecialchars($company_name) ?></title>
    
    <!-- SEO Meta Tags -->
    <meta name="description" content="Terms of Service for <?= htmlspecialchars($company_name) ?> - Complete terms and conditions governing the use of our personal loan services and platform.">
    <meta name="keywords" content="terms of service, loan agreement, terms and conditions, <?= htmlspecialchars($company_name) ?>, legal terms">
    <meta name="robots" content="index, follow">
    <meta name="author" content="<?= htmlspecialchars($company_name) ?>">
    
    <!-- Open Graph / Facebook -->
    <meta property="og:type" content="website">
    <meta property="og:url" content="<?= htmlspecialchars($company_website) ?>/terms.php">
    <meta property="og:title" content="Terms of Service - <?= htmlspecialchars($company_name) ?>">
    <meta property="og:description" content="Complete terms and conditions for <?= htmlspecialchars($company_name) ?> loan services.">
    
    <!-- Twitter -->
    <meta property="twitter:card" content="summary">
    <meta property="twitter:url" content="<?= htmlspecialchars($company_website) ?>/terms.php">
    <meta property="twitter:title" content="Terms of Service - <?= htmlspecialchars($company_name) ?>">
    <meta property="twitter:description" content="Complete terms and conditions for <?= htmlspecialchars($company_name) ?> loan services.">
    
    <!-- Stylesheets -->
    <link href="FrontEnd_Template/css/bootstrap.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="FrontEnd_Template/css/main.css" rel="stylesheet">
    <link href="FrontEnd_Template/css/aos.css" rel="stylesheet">
    
    <!-- Structured Data -->
    <script type="application/ld+json">
    {
        "@context": "https://schema.org",
        "@type": "WebPage",
        "name": "Terms of Service",
        "description": "Terms of Service for <?= htmlspecialchars($company_name) ?>",
        "url": "<?= htmlspecialchars($company_website) ?>/terms.php",
        "publisher": {
            "@type": "Organization",
            "name": "<?= htmlspecialchars($company_name) ?>",
            "url": "<?= htmlspecialchars($company_website) ?>"
        },
        "dateModified": "<?= date('c') ?>"
    }
    </script>
</head>
<body>
    <!-- Navigation -->
    <nav class="navbar navbar-expand-lg navbar-dark bg-primary sticky-top">
        <div class="container">
            <a class="navbar-brand fw-bold" href="index.php">
                <?php if (!empty($company_settings['logo'])): ?>
                    <img src="assets/images/<?= htmlspecialchars($company_settings['logo']) ?>" alt="<?= htmlspecialchars($company_name) ?>" height="40" class="me-2">
                <?php endif; ?>
                <?= htmlspecialchars($company_name) ?>
            </a>
            
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                <span class="navbar-toggler-icon"></span>
            </button>
            
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav ms-auto">
                    <li class="nav-item">
                        <a class="nav-link" href="index.php">
                            <i class="fas fa-home me-1"></i><?= __('home') ?>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="privacy.php">
                            <i class="fas fa-shield-alt me-1"></i><?= __('privacy_policy') ?>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="login.php">
                            <i class="fas fa-sign-in-alt me-1"></i><?= __('login') ?>
                        </a>
                    </li>
                    <li class="nav-item">
                        <?= LanguageManager::getLanguageSelector() ?>
                    </li>
                </ul>
            </div>
        </div>
    </nav>

    <!-- Header Section -->
    <div class="bg-gradient-primary text-white py-5">
        <div class="container">
            <div class="row justify-content-center">
                <div class="col-lg-8 text-center">
                    <h1 class="display-4 fw-bold mb-3">
                        <i class="fas fa-file-contract me-3"></i><?= __('terms_of_service') ?>
                    </h1>
                    <p class="lead mb-0">
                        <?= sprintf(__('terms_subtitle'), htmlspecialchars($company_name)) ?>
                    </p>
                    <p class="mt-3">
                        <small><i class="fas fa-calendar-alt me-2"></i><?= sprintf(__('last_updated'), date('F j, Y')) ?></small>
                    </p>
                </div>
            </div>
        </div>
    </div>

    <!-- Main Content -->
    <div class="container py-5">
        <div class="row justify-content-center">
            <div class="col-lg-10">
                
                <!-- Table of Contents -->
                <div class="card mb-5">
                    <div class="card-header bg-light">
                        <h4 class="mb-0">
                            <i class="fas fa-list me-2"></i><?= __('table_of_contents') ?>
                        </h4>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-6">
                                <ul class="list-unstyled">
                                    <li><a href="#section-1" class="text-decoration-none">1. <?= __('acceptance_of_terms') ?></a></li>
                                    <li><a href="#section-2" class="text-decoration-none">2. <?= __('description_of_services') ?></a></li>
                                    <li><a href="#section-3" class="text-decoration-none">3. <?= __('eligibility_requirements') ?></a></li>
                                    <li><a href="#section-4" class="text-decoration-none">4. <?= __('loan_application_process') ?></a></li>
                                    <li><a href="#section-5" class="text-decoration-none">5. <?= __('fees_and_charges') ?></a></li>
                                    <li><a href="#section-6" class="text-decoration-none">6. <?= __('loan_terms_conditions') ?></a></li>
                                </ul>
                            </div>
                            <div class="col-md-6">
                                <ul class="list-unstyled">
                                    <li><a href="#section-7" class="text-decoration-none">7. <?= __('repayment_obligations') ?></a></li>
                                    <li><a href="#section-8" class="text-decoration-none">8. <?= __('default_consequences') ?></a></li>
                                    <li><a href="#section-9" class="text-decoration-none">9. <?= __('user_responsibilities') ?></a></li>
                                    <li><a href="#section-10" class="text-decoration-none">10. <?= __('privacy_data_protection') ?></a></li>
                                    <li><a href="#section-11" class="text-decoration-none">11. <?= __('limitation_of_liability') ?></a></li>
                                    <li><a href="#section-12" class="text-decoration-none">12. <?= __('governing_law_disputes') ?></a></li>
                                </ul>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Important Notice -->
                <div class="alert alert-danger mb-5">
                    <h5><i class="fas fa-exclamation-triangle me-2"></i><?= __('important_notice') ?></h5>
                    <p class="mb-0">
                        <?= sprintf(__('terms_importance_notice'), htmlspecialchars($company_name)) ?>
                    </p>
                </div>

                <!-- Section 1: Acceptance of Terms -->
                <section id="section-1" class="mb-5">
                    <h2 class="border-bottom pb-2 mb-4">
                        <i class="fas fa-handshake me-2 text-primary"></i>1. <?= __('acceptance_of_terms') ?>
                    </h2>
                    
                    <p><?= sprintf(__('terms_acceptance_text'), htmlspecialchars($company_name)) ?></p>
                    
                    <div class="alert alert-info">
                        <h6><i class="fas fa-info-circle me-2"></i><?= __('key_point') ?></h6>
                        <p class="mb-0"><?= __('acceptance_key_point') ?></p>
                    </div>
                    
                    <h4><?= __('binding_agreement') ?></h4>
                    <ul>
                        <li><?= __('electronic_signature_validity') ?></li>
                        <li><?= __('terms_modification_rights') ?></li>
                        <li><?= __('continued_use_acceptance') ?></li>
                        <li><?= __('legal_capacity_requirement') ?></li>
                    </ul>
                </section>

                <!-- Section 2: Description of Services -->
                <section id="section-2" class="mb-5">
                    <h2 class="border-bottom pb-2 mb-4">
                        <i class="fas fa-cogs me-2 text-primary"></i>2. <?= __('description_of_services') ?>
                    </h2>
                    
                    <p><?= sprintf(__('services_description'), htmlspecialchars($company_name)) ?></p>
                    
                    <div class="row">
                        <div class="col-md-6">
                            <h5><?= __('primary_services') ?></h5>
                            <ul>
                                <li><?= __('personal_loan_origination') ?></li>
                                <li><?= __('online_application_platform') ?></li>
                                <li><?= __('credit_assessment_services') ?></li>
                                <li><?= __('loan_management_tools') ?></li>
                                <li><?= __('customer_support_services') ?></li>
                            </ul>
                        </div>
                        <div class="col-md-6">
                            <h5><?= __('additional_features') ?></h5>
                            <ul>
                                <li><?= __('mobile_app_access') ?></li>
                                <li><?= __('automated_payment_processing') ?></li>
                                <li><?= __('financial_education_resources') ?></li>
                                <li><?= __('account_management_portal') ?></li>
                                <li><?= __('real_time_notifications') ?></li>
                            </ul>
                        </div>
                    </div>
                    
                    <div class="alert alert-warning">
                        <h6><i class="fas fa-exclamation-triangle me-2"></i><?= __('service_limitations') ?></h6>
                        <p class="mb-0"><?= __('service_limitations_text') ?></p>
                    </div>
                </section>

                <!-- Section 3: Eligibility Requirements -->
                <section id="section-3" class="mb-5">
                    <h2 class="border-bottom pb-2 mb-4">
                        <i class="fas fa-user-check me-2 text-primary"></i>3. <?= __('eligibility_requirements') ?>
                    </h2>
                    
                    <p><?= __('eligibility_intro') ?></p>
                    
                    <div class="row">
                        <div class="col-lg-8">
                            <h4><?= __('minimum_requirements') ?></h4>
                            <ul>
                                <li><strong><?= __('age_requirement') ?>:</strong> <?= __('minimum_18_years') ?></li>
                                <li><strong><?= __('residency') ?>:</strong> <?= __('legal_resident_requirement') ?></li>
                                <li><strong><?= __('income') ?>:</strong> <?= __('verifiable_income_requirement') ?></li>
                                <li><strong><?= __('credit_history') ?>:</strong> <?= __('credit_check_requirement') ?></li>
                                <li><strong><?= __('bank_account') ?>:</strong> <?= __('active_bank_account_requirement') ?></li>
                                <li><strong><?= __('identification') ?>:</strong> <?= __('government_id_requirement') ?></li>
                            </ul>
                            
                            <h4><?= __('additional_criteria') ?></h4>
                            <ul>
                                <li><?= __('debt_to_income_ratio') ?></li>
                                <li><?= __('employment_stability') ?></li>
                                <li><?= __('no_recent_bankruptcy') ?></li>
                                <li><?= __('compliance_with_laws') ?></li>
                            </ul>
                        </div>
                        <div class="col-lg-4">
                            <div class="card bg-light">
                                <div class="card-body">
                                    <h6 class="card-title">
                                        <i class="fas fa-lightbulb me-2"></i><?= __('eligibility_note') ?>
                                    </h6>
                                    <p class="card-text small">
                                        <?= __('eligibility_disclaimer') ?>
                                    </p>
                                </div>
                            </div>
                        </div>
                    </div>
                </section>

                <!-- Section 4: Loan Application Process -->
                <section id="section-4" class="mb-5">
                    <h2 class="border-bottom pb-2 mb-4">
                        <i class="fas fa-clipboard-list me-2 text-primary"></i>4. <?= __('loan_application_process') ?>
                    </h2>
                    
                    <p><?= __('application_process_intro') ?></p>
                    
                    <div class="row">
                        <div class="col-md-6">
                            <h5><?= __('application_steps') ?></h5>
                            <ol>
                                <li><strong><?= __('initial_application') ?>:</strong> <?= __('complete_online_form') ?></li>
                                <li><strong><?= __('document_submission') ?>:</strong> <?= __('upload_required_documents') ?></li>
                                <li><strong><?= __('verification') ?>:</strong> <?= __('identity_income_verification') ?></li>
                                <li><strong><?= __('credit_check') ?>:</strong> <?= __('credit_assessment_process') ?></li>
                                <li><strong><?= __('underwriting') ?>:</strong> <?= __('loan_approval_review') ?></li>
                                <li><strong><?= __('approval_funding') ?>:</strong> <?= __('final_approval_funding') ?></li>
                            </ol>
                        </div>
                        <div class="col-md-6">
                            <h5><?= __('required_documents') ?></h5>
                            <ul>
                                <li><?= __('government_issued_id') ?></li>
                                <li><?= __('proof_of_income') ?></li>
                                <li><?= __('bank_statements') ?></li>
                                <li><?= __('proof_of_address') ?></li>
                                <li><?= __('employment_verification') ?></li>
                                <li><?= __('additional_documents_as_required') ?></li>
                            </ul>
                        </div>
                    </div>
                    
                    <div class="alert alert-info">
                        <h6><i class="fas fa-clock me-2"></i><?= __('processing_timeline') ?></h6>
                        <p class="mb-0"><?= __('processing_timeline_text') ?></p>
                    </div>
                </section>

                <!-- Section 5: Fees and Charges -->
                <section id="section-5" class="mb-5">
                    <h2 class="border-bottom pb-2 mb-4">
                        <i class="fas fa-dollar-sign me-2 text-primary"></i>5. <?= __('fees_and_charges') ?>
                    </h2>
                    
                    <p><?= __('fees_transparency_statement') ?></p>
                    
                    <div class="table-responsive">
                        <table class="table table-striped table-bordered">
                            <thead class="table-dark">
                                <tr>
                                    <th><?= __('fee_type') ?></th>
                                    <th><?= __('amount_rate') ?></th>
                                    <th><?= __('when_charged') ?></th>
                                    <th><?= __('description') ?></th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr>
                                    <td><strong><?= __('application_fee') ?></strong></td>
                                    <td><?= __('application_fee_amount') ?></td>
                                    <td><?= __('upon_application_submission') ?></td>
                                    <td><?= __('application_fee_description') ?></td>
                                </tr>
                                <tr>
                                    <td><strong><?= __('origination_fee') ?></strong></td>
                                    <td><?= __('origination_fee_rate') ?></td>
                                    <td><?= __('loan_funding') ?></td>
                                    <td><?= __('origination_fee_description') ?></td>
                                </tr>
                                <tr>
                                    <td><strong><?= __('late_payment_fee') ?></strong></td>
                                    <td><?= __('late_payment_fee_amount') ?></td>
                                    <td><?= __('payment_past_due') ?></td>
                                    <td><?= __('late_payment_fee_description') ?></td>
                                </tr>
                                <tr>
                                    <td><strong><?= __('returned_payment_fee') ?></strong></td>
                                    <td><?= __('returned_payment_fee_amount') ?></td>
                                    <td><?= __('insufficient_funds') ?></td>
                                    <td><?= __('returned_payment_fee_description') ?></td>
                                </tr>
                                <tr>
                                    <td><strong><?= __('prepayment_penalty') ?></strong></td>
                                    <td><?= __('prepayment_penalty_rate') ?></td>
                                    <td><?= __('early_loan_payoff') ?></td>
                                    <td><?= __('prepayment_penalty_description') ?></td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                    
                    <div class="alert alert-warning">
                        <h6><i class="fas fa-exclamation-triangle me-2"></i><?= __('fee_disclosure') ?></h6>
                        <p class="mb-0"><?= __('fee_disclosure_text') ?></p>
                    </div>
                </section>

                <!-- Section 6: Loan Terms and Conditions -->
                <section id="section-6" class="mb-5">
                    <h2 class="border-bottom pb-2 mb-4">
                        <i class="fas fa-file-alt me-2 text-primary"></i>6. <?= __('loan_terms_conditions') ?>
                    </h2>
                    
                    <p><?= __('loan_terms_intro') ?></p>
                    
                    <div class="row">
                        <div class="col-md-6">
                            <h5><?= __('interest_rates') ?></h5>
                            <ul>
                                <li><?= __('apr_disclosure') ?></li>
                                <li><?= __('rate_determination_factors') ?></li>
                                <li><?= __('fixed_vs_variable_rates') ?></li>
                                <li><?= __('rate_change_notification') ?></li>
                            </ul>
                            
                            <h5><?= __('loan_amounts') ?></h5>
                            <ul>
                                <li><?= __('minimum_loan_amount') ?></li>
                                <li><?= __('maximum_loan_amount') ?></li>
                                <li><?= __('loan_amount_determination') ?></li>
                            </ul>
                        </div>
                        <div class="col-md-6">
                            <h5><?= __('repayment_terms') ?></h5>
                            <ul>
                                <li><?= __('loan_term_options') ?></li>
                                <li><?= __('payment_frequency') ?></li>
                                <li><?= __('payment_due_dates') ?></li>
                                <li><?= __('payment_methods') ?></li>
                            </ul>
                            
                            <h5><?= __('loan_use_restrictions') ?></h5>
                            <ul>
                                <li><?= __('permitted_uses') ?></li>
                                <li><?= __('prohibited_uses') ?></li>
                                <li><?= __('use_verification') ?></li>
                            </ul>
                        </div>
                    </div>
                </section>

                <!-- Section 7: Repayment Obligations -->
                <section id="section-7" class="mb-5">
                    <h2 class="border-bottom pb-2 mb-4">
                        <i class="fas fa-calendar-check me-2 text-primary"></i>7. <?= __('repayment_obligations') ?>
                    </h2>
                    
                    <p><?= __('repayment_obligations_intro') ?></p>
                    
                    <div class="row">
                        <div class="col-lg-8">
                            <h4><?= __('payment_requirements') ?></h4>
                            <ul>
                                <li><strong><?= __('payment_amount') ?>:</strong> <?= __('payment_amount_details') ?></li>
                                <li><strong><?= __('payment_schedule') ?>:</strong> <?= __('payment_schedule_details') ?></li>
                                <li><strong><?= __('payment_method') ?>:</strong> <?= __('payment_method_details') ?></li>
                                <li><strong><?= __('grace_period') ?>:</strong> <?= __('grace_period_details') ?></li>
                            </ul>
                            
                            <h4><?= __('automatic_payments') ?></h4>
                            <p><?= __('automatic_payments_description') ?></p>
                            <ul>
                                <li><?= __('autopay_enrollment') ?></li>
                                <li><?= __('autopay_cancellation') ?></li>
                                <li><?= __('autopay_failure_handling') ?></li>
                                <li><?= __('autopay_discount_eligibility') ?></li>
                            </ul>
                        </div>
                        <div class="col-lg-4">
                            <div class="card border-success">
                                <div class="card-header bg-success text-white">
                                    <h6 class="mb-0">
                                        <i class="fas fa-piggy-bank me-2"></i><?= __('payment_tips') ?>
                                    </h6>
                                </div>
                                <div class="card-body">
                                    <ul class="list-unstyled small mb-0">
                                        <li><i class="fas fa-check text-success me-2"></i><?= __('set_up_automatic_payments') ?></li>
                                        <li><i class="fas fa-check text-success me-2"></i><?= __('make_payments_early') ?></li>
                                        <li><i class="fas fa-check text-success me-2"></i><?= __('consider_biweekly_payments') ?></li>
                                        <li><i class="fas fa-check text-success me-2"></i><?= __('contact_us_if_struggling') ?></li>
                                    </ul>
                                </div>
                            </div>
                        </div>
                    </div>
                </section>

                <!-- Section 8: Default and Consequences -->
                <section id="section-8" class="mb-5">
                    <h2 class="border-bottom pb-2 mb-4">
                        <i class="fas fa-exclamation-triangle me-2 text-danger"></i>8. <?= __('default_consequences') ?>
                    </h2>
                    
                    <div class="alert alert-danger">
                        <h6><i class="fas fa-warning me-2"></i><?= __('important_warning') ?></h6>
                        <p class="mb-0"><?= __('default_warning_text') ?></p>
                    </div>
                    
                    <h4><?= __('default_conditions') ?></h4>
                    <p><?= __('default_definition') ?></p>
                    <ul>
                        <li><?= __('missed_payment_threshold') ?></li>
                        <li><?= __('breach_of_agreement') ?></li>
                        <li><?= __('bankruptcy_filing') ?></li>
                        <li><?= __('material_adverse_change') ?></li>
                    </ul>
                    
                    <h4><?= __('consequences_of_default') ?></h4>
                    <div class="row">
                        <div class="col-md-6">
                            <h6><?= __('immediate_consequences') ?></h6>
                            <ul>
                                <li><?= __('acceleration_of_debt') ?></li>
                                <li><?= __('late_fees_penalties') ?></li>
                                <li><?= __('credit_reporting') ?></li>
                                <li><?= __('collection_activities') ?></li>
                            </ul>
                        </div>
                        <div class="col-md-6">
                            <h6><?= __('long_term_consequences') ?></h6>
                            <ul>
                                <li><?= __('credit_score_impact') ?></li>
                                <li><?= __('legal_action') ?></li>
                                <li><?= __('wage_garnishment') ?></li>
                                <li><?= __('asset_seizure') ?></li>
                            </ul>
                        </div>
                    </div>
                </section>

                <!-- Section 9: User Responsibilities -->
                <section id="section-9" class="mb-5">
                    <h2 class="border-bottom pb-2 mb-4">
                        <i class="fas fa-user-cog me-2 text-primary"></i>9. <?= __('user_responsibilities') ?>
                    </h2>
                    
                    <p><?= __('user_responsibilities_intro') ?></p>
                    
                    <div class="row">
                        <div class="col-md-6">
                            <h5><?= __('information_accuracy') ?></h5>
                            <ul>
                                <li><?= __('provide_accurate_information') ?></li>
                                <li><?= __('update_changes_promptly') ?></li>
                                <li><?= __('verify_contact_information') ?></li>
                                <li><?= __('report_suspected_fraud') ?></li>
                            </ul>
                            
                            <h5><?= __('account_security') ?></h5>
                            <ul>
                                <li><?= __('protect_login_credentials') ?></li>
                                <li><?= __('report_unauthorized_access') ?></li>
                                <li><?= __('use_secure_devices') ?></li>
                                <li><?= __('log_out_after_sessions') ?></li>
                            </ul>
                        </div>
                        <div class="col-md-6">
                            <h5><?= __('prohibited_activities') ?></h5>
                            <ul>
                                <li><?= __('no_fraudulent_applications') ?></li>
                                <li><?= __('no_identity_misrepresentation') ?></li>
                                <li><?= __('no_system_manipulation') ?></li>
                                <li><?= __('no_reverse_engineering') ?></li>
                            </ul>
                            
                            <h5><?= __('communication_requirements') ?></h5>
                            <ul>
                                <li><?= __('respond_to_requests_promptly') ?></li>
                                <li><?= __('maintain_current_contact_info') ?></li>
                                <li><?= __('notify_of_financial_changes') ?></li>
                                <li><?= __('cooperate_with_collections') ?></li>
                            </ul>
                        </div>
                    </div>
                </section>

                <!-- Section 10: Privacy and Data Protection -->
                <section id="section-10" class="mb-5">
                    <h2 class="border-bottom pb-2 mb-4">
                        <i class="fas fa-shield-alt me-2 text-primary"></i>10. <?= __('privacy_data_protection') ?>
                    </h2>
                    
                    <p><?= sprintf(__('privacy_reference'), htmlspecialchars($company_name)) ?></p>
                    
                    <div class="row">
                        <div class="col-md-6">
                            <h5><?= __('data_collection_use') ?></h5>
                            <ul>
                                <li><?= __('personal_financial_information') ?></li>
                                <li><?= __('credit_information') ?></li>
                                <li><?= __('device_usage_information') ?></li>
                                <li><?= __('communication_records') ?></li>
                            </ul>
                        </div>
                        <div class="col-md-6">
                            <h5><?= __('data_sharing') ?></h5>
                            <ul>
                                <li><?= __('credit_bureaus') ?></li>
                                <li><?= __('service_providers') ?></li>
                                <li><?= __('regulatory_authorities') ?></li>
                                <li><?= __('legal_requirements') ?></li>
                            </ul>
                        </div>
                    </div>
                    
                    <div class="alert alert-info">
                        <p class="mb-0">
                            <strong><?= __('full_privacy_policy') ?>:</strong> 
                            <a href="privacy.php" class="alert-link"><?= __('view_complete_privacy_policy') ?></a>
                        </p>
                    </div>
                </section>

                <!-- Section 11: Limitation of Liability -->
                <section id="section-11" class="mb-5">
                    <h2 class="border-bottom pb-2 mb-4">
                        <i class="fas fa-balance-scale me-2 text-primary"></i>11. <?= __('limitation_of_liability') ?>
                    </h2>
                    
                    <div class="alert alert-warning">
                        <h6><i class="fas fa-info-circle me-2"></i><?= __('legal_disclaimer') ?></h6>
                        <p class="mb-0"><?= __('liability_limitation_notice') ?></p>
                    </div>
                    
                    <h4><?= __('service_disclaimers') ?></h4>
                    <p><?= sprintf(__('service_disclaimer_text'), htmlspecialchars($company_name)) ?></p>
                    
                    <h4><?= __('limitation_scope') ?></h4>
                    <ul>
                        <li><?= __('no_indirect_damages') ?></li>
                        <li><?= __('no_consequential_damages') ?></li>
                        <li><?= __('no_lost_profits') ?></li>
                        <li><?= __('no_business_interruption') ?></li>
                        <li><?= __('maximum_liability_limit') ?></li>
                    </ul>
                    
                    <h4><?= __('exceptions_to_limitations') ?></h4>
                    <ul>
                        <li><?= __('gross_negligence') ?></li>
                        <li><?= __('willful_misconduct') ?></li>
                        <li><?= __('fraud') ?></li>
                        <li><?= __('statutory_rights') ?></li>
                    </ul>
                </section>

                <!-- Section 12: Governing Law and Disputes -->
                <section id="section-12" class="mb-5">
                    <h2 class="border-bottom pb-2 mb-4">
                        <i class="fas fa-gavel me-2 text-primary"></i>12. <?= __('governing_law_disputes') ?>
                    </h2>
                    
                    <h4><?= __('governing_law') ?></h4>
                    <p><?= __('governing_law_text') ?></p>
                    
                    <h4><?= __('dispute_resolution') ?></h4>
                    <p><?= __('dispute_resolution_intro') ?></p>
                    
                    <div class="row">
                        <div class="col-md-6">
                            <h6><?= __('mandatory_arbitration') ?></h6>
                            <ul>
                                <li><?= __('binding_arbitration') ?></li>
                                <li><?= __('arbitration_rules') ?></li>
                                <li><?= __('arbitrator_selection') ?></li>
                                <li><?= __('arbitration_location') ?></li>
                            </ul>
                        </div>
                        <div class="col-md-6">
                            <h6><?= __('exceptions_to_arbitration') ?></h6>
                            <ul>
                                <li><?= __('small_claims_court') ?></li>
                                <li><?= __('injunctive_relief') ?></li>
                                <li><?= __('intellectual_property') ?></li>
                                <li><?= __('collection_actions') ?></li>
                            </ul>
                        </div>
                    </div>
                    
                    <h4><?= __('class_action_waiver') ?></h4>
                    <p><?= __('class_action_waiver_text') ?></p>
                </section>

                <!-- Contact Information -->
                <div class="card mb-5">
                    <div class="card-header">
                        <h4 class="mb-0">
                            <i class="fas fa-envelope me-2"></i><?= __('legal_contact_information') ?>
                        </h4>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-6">
                                <h6><?= __('general_inquiries') ?></h6>
                                <p>
                                    <strong><?= htmlspecialchars($company_name) ?></strong><br>
                                    <i class="fas fa-envelope me-2"></i><a href="mailto:<?= htmlspecialchars($company_email) ?>"><?= htmlspecialchars($company_email) ?></a><br>
                                    <i class="fas fa-phone me-2"></i><a href="tel:<?= htmlspecialchars($company_phone) ?>"><?= htmlspecialchars($company_phone) ?></a><br>
                                    <i class="fas fa-map-marker-alt me-2"></i><?= nl2br(htmlspecialchars($company_address)) ?>
                                </p>
                            </div>
                            <div class="col-md-6">
                                <h6><?= __('legal_notices') ?></h6>
                                <p>
                                    <?= __('legal_notices_text') ?><br>
                                    <strong><?= __('email') ?>:</strong> <a href="mailto:legal@<?= parse_url($company_website, PHP_URL_HOST) ?>">legal@<?= parse_url($company_website, PHP_URL_HOST) ?></a><br>
                                    <strong><?= __('mailing_address') ?>:</strong> <?= nl2br(htmlspecialchars($company_address)) ?>
                                </p>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Effective Date and Version -->
                <div class="text-center mt-5 pt-5 border-top">
                    <p class="text-muted">
                        <strong><?= __('effective_date') ?>:</strong> <?= date('F j, Y') ?><br>
                        <strong><?= __('version') ?>:</strong> 2.0<br>
                        <small><?= sprintf(__('copyright_notice'), date('Y'), htmlspecialchars($company_name)) ?></small>
                    </p>
                </div>
            </div>
        </div>
    </div>

    <!-- Footer -->
    <footer class="bg-dark text-white py-4">
        <div class="container">
            <div class="row align-items-center">
                <div class="col-md-6">
                    <p class="mb-0">
                        &copy; <?= date('Y') ?> <?= htmlspecialchars($company_name) ?>. <?= __('all_rights_reserved') ?>
                    </p>
                </div>
                <div class="col-md-6 text-md-end">
                    <a href="terms.php" class="text-light text-decoration-none me-3"><?= __('terms_of_service') ?></a>
                    <a href="privacy.php" class="text-light text-decoration-none me-3"><?= __('privacy_policy') ?></a>
                    <a href="index.php" class="text-light text-decoration-none"><?= __('home') ?></a>
                </div>
            </div>
        </div>
    </footer>

    <!-- Scripts -->
    <script src="FrontEnd_Template/js/bootstrap.bundle.js"></script>
    <script src="FrontEnd_Template/js/bootstrap.min.js"></script>
    <script src="FrontEnd_Template/js/aos.js"></script>
    <script>
        AOS.init();
    </script>
    <script>
        // Smooth scrolling for anchor links
        document.querySelectorAll('a[href^="#"]').forEach(anchor => {
            anchor.addEventListener('click', function (e) {
                e.preventDefault();
                const target = document.querySelector(this.getAttribute('href'));
                if (target) {
                    target.scrollIntoView({
                        behavior: 'smooth',
                        block: 'start'
                    });
                }
            });
        });
        
        // Language change function
        function changeLanguage(lang) {
            const form = document.createElement('form');
            form.method = 'POST';
            form.action = 'change-language.php';
            
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
