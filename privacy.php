<?php
/**
 * Privacy Policy Page
 * LoanFlow Personal Loan Management System
 */

require_once 'includes/functions.php';
require_once 'includes/language.php';

// Initialize language
LanguageManager::init();

// Get company settings for dynamic content
$company_settings = getCompanySettings();
$company_name = $company_settings['name'] ?? 'LoanFlow';
$company_email = $company_settings['email'] ?? 'privacy@loanflow.com';
$company_address = $company_settings['address'] ?? 'Company Address';
$company_phone = $company_settings['phone'] ?? 'Company Phone';
$company_website = $company_settings['website'] ?? 'https://www.loanflow.com';

?>
<!DOCTYPE html>
<html lang="<?= LanguageManager::getCurrentLanguage() ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= __('privacy_policy') ?> - <?= htmlspecialchars($company_name) ?></title>
    
    <!-- SEO Meta Tags -->
    <meta name="description" content="Privacy Policy for <?= htmlspecialchars($company_name) ?> - Learn how we collect, use, and protect your personal information in accordance with applicable privacy laws.">
    <meta name="keywords" content="privacy policy, data protection, personal information, <?= htmlspecialchars($company_name) ?>, loan services">
    <meta name="robots" content="index, follow">
    <meta name="author" content="<?= htmlspecialchars($company_name) ?>">
    
    <!-- Open Graph / Facebook -->
    <meta property="og:type" content="website">
    <meta property="og:url" content="<?= htmlspecialchars($company_website) ?>/privacy.php">
    <meta property="og:title" content="Privacy Policy - <?= htmlspecialchars($company_name) ?>">
    <meta property="og:description" content="Learn how <?= htmlspecialchars($company_name) ?> protects your privacy and personal information.">
    
    <!-- Twitter -->
    <meta property="twitter:card" content="summary">
    <meta property="twitter:url" content="<?= htmlspecialchars($company_website) ?>/privacy.php">
    <meta property="twitter:title" content="Privacy Policy - <?= htmlspecialchars($company_name) ?>">
    <meta property="twitter:description" content="Learn how <?= htmlspecialchars($company_name) ?> protects your privacy and personal information.">
    
    <!-- Stylesheets -->
    <link href="FrontEnd_Template/css/bootstrap.min.css" rel="stylesheet">
    <link href="FrontEnd_Template/css/style.css" rel="stylesheet">
    <link href="FrontEnd_Template/css/aos.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    
    <!-- Structured Data -->
    <script type="application/ld+json">
    {
        "@context": "https://schema.org",
        "@type": "WebPage",
        "name": "Privacy Policy",
        "description": "Privacy Policy for <?= htmlspecialchars($company_name) ?>",
        "url": "<?= htmlspecialchars($company_website) ?>/privacy.php",
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
                        <a class="nav-link" href="terms.php">
                            <i class="fas fa-file-contract me-1"></i><?= __('terms_of_service') ?>
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
                        <i class="fas fa-shield-alt me-3"></i><?= __('privacy_policy') ?>
                    </h1>
                    <p class="lead mb-0">
                        <?= sprintf(__('privacy_policy_subtitle'), htmlspecialchars($company_name)) ?>
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
                                    <li><a href="#section-1" class="text-decoration-none">1. <?= __('information_we_collect') ?></a></li>
                                    <li><a href="#section-2" class="text-decoration-none">2. <?= __('how_we_use_information') ?></a></li>
                                    <li><a href="#section-3" class="text-decoration-none">3. <?= __('information_sharing') ?></a></li>
                                    <li><a href="#section-4" class="text-decoration-none">4. <?= __('data_security') ?></a></li>
                                    <li><a href="#section-5" class="text-decoration-none">5. <?= __('cookies_tracking') ?></a></li>
                                </ul>
                            </div>
                            <div class="col-md-6">
                                <ul class="list-unstyled">
                                    <li><a href="#section-6" class="text-decoration-none">6. <?= __('your_privacy_rights') ?></a></li>
                                    <li><a href="#section-7" class="text-decoration-none">7. <?= __('data_retention') ?></a></li>
                                    <li><a href="#section-8" class="text-decoration-none">8. <?= __('third_party_services') ?></a></li>
                                    <li><a href="#section-9" class="text-decoration-none">9. <?= __('policy_updates') ?></a></li>
                                    <li><a href="#section-10" class="text-decoration-none">10. <?= __('contact_information') ?></a></li>
                                </ul>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Introduction -->
                <div class="mb-5">
                    <div class="alert alert-info">
                        <h5><i class="fas fa-info-circle me-2"></i><?= __('introduction') ?></h5>
                        <p class="mb-0">
                            <?= sprintf(__('privacy_introduction'), htmlspecialchars($company_name)) ?>
                        </p>
                    </div>
                </div>

                <!-- Section 1: Information We Collect -->
                <section id="section-1" class="mb-5">
                    <h2 class="border-bottom pb-2 mb-4">
                        <i class="fas fa-database me-2 text-primary"></i>1. <?= __('information_we_collect') ?>
                    </h2>
                    
                    <div class="row">
                        <div class="col-lg-8">
                            <h4><?= __('personal_information') ?></h4>
                            <p><?= __('personal_info_description') ?></p>
                            <ul>
                                <li><?= __('name_contact_details') ?></li>
                                <li><?= __('identification_documents') ?></li>
                                <li><?= __('financial_information') ?></li>
                                <li><?= __('employment_details') ?></li>
                                <li><?= __('bank_account_information') ?></li>
                                <li><?= __('credit_history_reports') ?></li>
                            </ul>
                            
                            <h4 class="mt-4"><?= __('technical_information') ?></h4>
                            <p><?= __('technical_info_description') ?></p>
                            <ul>
                                <li><?= __('ip_address_location') ?></li>
                                <li><?= __('browser_device_info') ?></li>
                                <li><?= __('usage_patterns') ?></li>
                                <li><?= __('cookies_session_data') ?></li>
                            </ul>
                        </div>
                        <div class="col-lg-4">
                            <div class="card bg-light">
                                <div class="card-body">
                                    <h6 class="card-title">
                                        <i class="fas fa-lightbulb me-2"></i><?= __('why_we_collect') ?>
                                    </h6>
                                    <p class="card-text small">
                                        <?= __('data_collection_purpose') ?>
                                    </p>
                                </div>
                            </div>
                        </div>
                    </div>
                </section>

                <!-- Section 2: How We Use Information -->
                <section id="section-2" class="mb-5">
                    <h2 class="border-bottom pb-2 mb-4">
                        <i class="fas fa-cogs me-2 text-primary"></i>2. <?= __('how_we_use_information') ?>
                    </h2>
                    
                    <div class="row">
                        <div class="col-md-6">
                            <h5><?= __('primary_uses') ?></h5>
                            <ul>
                                <li><?= __('process_loan_applications') ?></li>
                                <li><?= __('verify_identity_income') ?></li>
                                <li><?= __('assess_creditworthiness') ?></li>
                                <li><?= __('provide_customer_service') ?></li>
                                <li><?= __('comply_legal_requirements') ?></li>
                            </ul>
                        </div>
                        <div class="col-md-6">
                            <h5><?= __('secondary_uses') ?></h5>
                            <ul>
                                <li><?= __('improve_services') ?></li>
                                <li><?= __('marketing_communications') ?></li>
                                <li><?= __('fraud_prevention') ?></li>
                                <li><?= __('analytics_research') ?></li>
                                <li><?= __('system_maintenance') ?></li>
                            </ul>
                        </div>
                    </div>
                </section>

                <!-- Section 3: Information Sharing -->
                <section id="section-3" class="mb-5">
                    <h2 class="border-bottom pb-2 mb-4">
                        <i class="fas fa-share-alt me-2 text-primary"></i>3. <?= __('information_sharing') ?>
                    </h2>
                    
                    <div class="alert alert-warning">
                        <h5><i class="fas fa-exclamation-triangle me-2"></i><?= __('important_note') ?></h5>
                        <p class="mb-0"><?= sprintf(__('no_selling_policy'), htmlspecialchars($company_name)) ?></p>
                    </div>
                    
                    <h4><?= __('limited_sharing_circumstances') ?></h4>
                    <ul>
                        <li><strong><?= __('service_providers') ?>:</strong> <?= __('service_providers_description') ?></li>
                        <li><strong><?= __('legal_compliance') ?>:</strong> <?= __('legal_compliance_description') ?></li>
                        <li><strong><?= __('business_transfers') ?>:</strong> <?= __('business_transfers_description') ?></li>
                        <li><strong><?= __('credit_bureaus') ?>:</strong> <?= __('credit_bureaus_description') ?></li>
                        <li><strong><?= __('financial_institutions') ?>:</strong> <?= __('financial_institutions_description') ?></li>
                    </ul>
                </section>

                <!-- Section 4: Data Security -->
                <section id="section-4" class="mb-5">
                    <h2 class="border-bottom pb-2 mb-4">
                        <i class="fas fa-lock me-2 text-primary"></i>4. <?= __('data_security') ?>
                    </h2>
                    
                    <div class="row">
                        <div class="col-lg-8">
                            <p><?= __('security_commitment') ?></p>
                            
                            <h4><?= __('security_measures') ?></h4>
                            <ul>
                                <li><strong><?= __('encryption') ?>:</strong> <?= __('encryption_description') ?></li>
                                <li><strong><?= __('access_controls') ?>:</strong> <?= __('access_controls_description') ?></li>
                                <li><strong><?= __('secure_transmission') ?>:</strong> <?= __('secure_transmission_description') ?></li>
                                <li><strong><?= __('regular_audits') ?>:</strong> <?= __('regular_audits_description') ?></li>
                                <li><strong><?= __('employee_training') ?>:</strong> <?= __('employee_training_description') ?></li>
                            </ul>
                        </div>
                        <div class="col-lg-4">
                            <div class="card border-success">
                                <div class="card-header bg-success text-white">
                                    <h6 class="mb-0">
                                        <i class="fas fa-shield-alt me-2"></i><?= __('security_standards') ?>
                                    </h6>
                                </div>
                                <div class="card-body">
                                    <ul class="list-unstyled small mb-0">
                                        <li><i class="fas fa-check text-success me-2"></i>SSL/TLS Encryption</li>
                                        <li><i class="fas fa-check text-success me-2"></i>PCI DSS Compliance</li>
                                        <li><i class="fas fa-check text-success me-2"></i>Two-Factor Authentication</li>
                                        <li><i class="fas fa-check text-success me-2"></i>Regular Security Audits</li>
                                        <li><i class="fas fa-check text-success me-2"></i>Data Encryption at Rest</li>
                                    </ul>
                                </div>
                            </div>
                        </div>
                    </div>
                </section>

                <!-- Section 5: Cookies and Tracking -->
                <section id="section-5" class="mb-5">
                    <h2 class="border-bottom pb-2 mb-4">
                        <i class="fas fa-cookie-bite me-2 text-primary"></i>5. <?= __('cookies_tracking') ?>
                    </h2>
                    
                    <p><?= __('cookies_description') ?></p>
                    
                    <div class="table-responsive">
                        <table class="table table-striped">
                            <thead class="table-dark">
                                <tr>
                                    <th><?= __('cookie_type') ?></th>
                                    <th><?= __('purpose') ?></th>
                                    <th><?= __('duration') ?></th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr>
                                    <td><strong><?= __('essential_cookies') ?></strong></td>
                                    <td><?= __('essential_cookies_purpose') ?></td>
                                    <td><?= __('session_or_persistent') ?></td>
                                </tr>
                                <tr>
                                    <td><strong><?= __('analytics_cookies') ?></strong></td>
                                    <td><?= __('analytics_cookies_purpose') ?></td>
                                    <td><?= __('up_to_2_years') ?></td>
                                </tr>
                                <tr>
                                    <td><strong><?= __('marketing_cookies') ?></strong></td>
                                    <td><?= __('marketing_cookies_purpose') ?></td>
                                    <td><?= __('up_to_1_year') ?></td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </section>

                <!-- Section 6: Your Privacy Rights -->
                <section id="section-6" class="mb-5">
                    <h2 class="border-bottom pb-2 mb-4">
                        <i class="fas fa-user-shield me-2 text-primary"></i>6. <?= __('your_privacy_rights') ?>
                    </h2>
                    
                    <div class="row">
                        <div class="col-md-6">
                            <h5><?= __('general_rights') ?></h5>
                            <ul>
                                <li><?= __('access_your_data') ?></li>
                                <li><?= __('correct_inaccurate_data') ?></li>
                                <li><?= __('delete_your_data') ?></li>
                                <li><?= __('restrict_processing') ?></li>
                                <li><?= __('data_portability') ?></li>
                                <li><?= __('object_to_processing') ?></li>
                            </ul>
                        </div>
                        <div class="col-md-6">
                            <h5><?= __('regional_rights') ?></h5>
                            <p><strong>GDPR (EU):</strong> <?= __('gdpr_rights') ?></p>
                            <p><strong>CCPA (California):</strong> <?= __('ccpa_rights') ?></p>
                            <p><strong>PIPEDA (Canada):</strong> <?= __('pipeda_rights') ?></p>
                        </div>
                    </div>
                    
                    <div class="alert alert-info">
                        <h6><i class="fas fa-envelope me-2"></i><?= __('exercise_your_rights') ?></h6>
                        <p class="mb-0">
                            <?= sprintf(__('rights_contact_info'), htmlspecialchars($company_email)) ?>
                        </p>
                    </div>
                </section>

                <!-- Section 7: Data Retention -->
                <section id="section-7" class="mb-5">
                    <h2 class="border-bottom pb-2 mb-4">
                        <i class="fas fa-archive me-2 text-primary"></i>7. <?= __('data_retention') ?>
                    </h2>
                    
                    <p><?= __('retention_policy') ?></p>
                    
                    <div class="table-responsive">
                        <table class="table table-bordered">
                            <thead class="table-dark">
                                <tr>
                                    <th><?= __('data_type') ?></th>
                                    <th><?= __('retention_period') ?></th>
                                    <th><?= __('reason') ?></th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr>
                                    <td><?= __('loan_application_data') ?></td>
                                    <td><?= __('7_years') ?></td>
                                    <td><?= __('regulatory_requirements') ?></td>
                                </tr>
                                <tr>
                                    <td><?= __('marketing_data') ?></td>
                                    <td><?= __('3_years') ?></td>
                                    <td><?= __('business_purposes') ?></td>
                                </tr>
                                <tr>
                                    <td><?= __('website_analytics') ?></td>
                                    <td><?= __('2_years') ?></td>
                                    <td><?= __('service_improvement') ?></td>
                                </tr>
                                <tr>
                                    <td><?= __('customer_service_records') ?></td>
                                    <td><?= __('5_years') ?></td>
                                    <td><?= __('quality_assurance') ?></td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </section>

                <!-- Section 8: Third-Party Services -->
                <section id="section-8" class="mb-5">
                    <h2 class="border-bottom pb-2 mb-4">
                        <i class="fas fa-link me-2 text-primary"></i>8. <?= __('third_party_services') ?>
                    </h2>
                    
                    <p><?= __('third_party_description') ?></p>
                    
                    <div class="row">
                        <div class="col-md-4">
                            <div class="card h-100">
                                <div class="card-body">
                                    <h6 class="card-title">
                                        <i class="fas fa-chart-line me-2"></i><?= __('analytics_providers') ?>
                                    </h6>
                                    <p class="card-text small">Google Analytics, Adobe Analytics</p>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="card h-100">
                                <div class="card-body">
                                    <h6 class="card-title">
                                        <i class="fas fa-credit-card me-2"></i><?= __('payment_processors') ?>
                                    </h6>
                                    <p class="card-text small">Stripe, PayPal, Bank Partners</p>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="card h-100">
                                <div class="card-body">
                                    <h6 class="card-title">
                                        <i class="fas fa-shield-alt me-2"></i><?= __('security_services') ?>
                                    </h6>
                                    <p class="card-text small">CloudFlare, Security Auditors</p>
                                </div>
                            </div>
                        </div>
                    </div>
                </section>

                <!-- Section 9: Policy Updates -->
                <section id="section-9" class="mb-5">
                    <h2 class="border-bottom pb-2 mb-4">
                        <i class="fas fa-sync-alt me-2 text-primary"></i>9. <?= __('policy_updates') ?>
                    </h2>
                    
                    <p><?= __('policy_updates_description') ?></p>
                    
                    <div class="alert alert-warning">
                        <h6><i class="fas fa-bell me-2"></i><?= __('notification_of_changes') ?></h6>
                        <p class="mb-0"><?= __('change_notification_method') ?></p>
                    </div>
                </section>

                <!-- Section 10: Contact Information -->
                <section id="section-10" class="mb-5">
                    <h2 class="border-bottom pb-2 mb-4">
                        <i class="fas fa-envelope me-2 text-primary"></i>10. <?= __('contact_information') ?>
                    </h2>
                    
                    <div class="row">
                        <div class="col-lg-6">
                            <div class="card">
                                <div class="card-header">
                                    <h6 class="mb-0"><?= __('privacy_officer') ?></h6>
                                </div>
                                <div class="card-body">
                                    <p><strong><?= htmlspecialchars($company_name) ?></strong></p>
                                    <p>
                                        <i class="fas fa-envelope me-2"></i>
                                        <a href="mailto:<?= htmlspecialchars($company_email) ?>"><?= htmlspecialchars($company_email) ?></a>
                                    </p>
                                    <p>
                                        <i class="fas fa-phone me-2"></i>
                                        <a href="tel:<?= htmlspecialchars($company_phone) ?>"><?= htmlspecialchars($company_phone) ?></a>
                                    </p>
                                    <p>
                                        <i class="fas fa-map-marker-alt me-2"></i>
                                        <?= nl2br(htmlspecialchars($company_address)) ?>
                                    </p>
                                </div>
                            </div>
                        </div>
                        <div class="col-lg-6">
                            <div class="card">
                                <div class="card-header">
                                    <h6 class="mb-0"><?= __('response_timeframe') ?></h6>
                                </div>
                                <div class="card-body">
                                    <ul class="list-unstyled">
                                        <li><i class="fas fa-clock me-2 text-success"></i><?= __('general_inquiries_24_48_hours') ?></li>
                                        <li><i class="fas fa-clock me-2 text-warning"></i><?= __('privacy_requests_30_days') ?></li>
                                        <li><i class="fas fa-clock me-2 text-danger"></i><?= __('urgent_matters_immediate') ?></li>
                                    </ul>
                                </div>
                            </div>
                        </div>
                    </div>
                </section>

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
