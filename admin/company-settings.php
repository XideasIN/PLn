<?php
/**
 * Company Settings Management
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
            case 'update_company_info':
                $company_data = [
                    'company_name' => sanitizeInput($_POST['company_name'] ?? ''),
                    'company_address' => sanitizeInput($_POST['company_address'] ?? ''),
                    'company_phone' => sanitizeInput($_POST['company_phone'] ?? ''),
                    'company_email' => sanitizeInput($_POST['company_email'] ?? ''),
                    'company_website' => sanitizeInput($_POST['company_website'] ?? ''),
                    'company_registration' => sanitizeInput($_POST['company_registration'] ?? ''),
                    'company_tax_id' => sanitizeInput($_POST['company_tax_id'] ?? ''),
                    'company_description' => sanitizeInput($_POST['company_description'] ?? '')
                ];
                
                if (updateCompanySettings($company_data)) {
                    $success = __('company_info_updated');
                    logAudit('company_settings_updated', 'system_settings', null, $current_user['id'], $company_data);
                } else {
                    $error = __('company_info_update_failed');
                }
                break;
                
            case 'upload_logo':
                $upload_result = handleLogoUpload($_FILES['company_logo'] ?? null);
                if ($upload_result['success']) {
                    updateSystemSetting('company_logo', $upload_result['filename']);
                    $success = __('logo_uploaded_successfully');
                    logAudit('company_logo_updated', 'system_settings', null, $current_user['id'], ['filename' => $upload_result['filename']]);
                } else {
                    $error = $upload_result['error'];
                }
                break;
                
            case 'update_branding':
                $branding_data = [
                    'brand_primary_color' => sanitizeInput($_POST['brand_primary_color'] ?? '#007bff'),
                    'brand_secondary_color' => sanitizeInput($_POST['brand_secondary_color'] ?? '#6c757d'),
                    'brand_accent_color' => sanitizeInput($_POST['brand_accent_color'] ?? '#28a745'),
                    'brand_font_family' => sanitizeInput($_POST['brand_font_family'] ?? 'Arial, sans-serif'),
                    'letterhead_template' => sanitizeInput($_POST['letterhead_template'] ?? 'default'),
                    'email_template_style' => sanitizeInput($_POST['email_template_style'] ?? 'modern')
                ];
                
                if (updateBrandingSettings($branding_data)) {
                    $success = __('branding_updated');
                    logAudit('branding_settings_updated', 'system_settings', null, $current_user['id'], $branding_data);
                } else {
                    $error = __('branding_update_failed');
                }
                break;
        }
    }
}

// Get current company settings
$company_settings = getCompanySettings();
$branding_settings = getBrandingSettings();

?>
<!DOCTYPE html>
<html lang="<?= LanguageManager::getCurrentLanguage() ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= __('company_settings') ?> - LoanFlow Admin</title>
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
                            <i class="fas fa-building me-2"></i><?= __('company_settings') ?>
                        </h4>
                    </div>
                    <div class="card-body">
                        
                        <!-- Navigation Tabs -->
                        <ul class="nav nav-tabs" id="companyTabs" role="tablist">
                            <li class="nav-item" role="presentation">
                                <button class="nav-link active" id="info-tab" data-bs-toggle="tab" data-bs-target="#info" type="button" role="tab">
                                    <i class="fas fa-info-circle me-2"></i><?= __('company_info') ?>
                                </button>
                            </li>
                            <li class="nav-item" role="presentation">
                                <button class="nav-link" id="logo-tab" data-bs-toggle="tab" data-bs-target="#logo" type="button" role="tab">
                                    <i class="fas fa-image me-2"></i><?= __('logo_branding') ?>
                                </button>
                            </li>
                            <li class="nav-item" role="presentation">
                                <button class="nav-link" id="branding-tab" data-bs-toggle="tab" data-bs-target="#branding" type="button" role="tab">
                                    <i class="fas fa-palette me-2"></i><?= __('brand_colors') ?>
                                </button>
                            </li>
                            <li class="nav-item" role="presentation">
                                <button class="nav-link" id="templates-tab" data-bs-toggle="tab" data-bs-target="#templates" type="button" role="tab">
                                    <i class="fas fa-file-alt me-2"></i><?= __('templates') ?>
                                </button>
                            </li>
                        </ul>
                        
                        <!-- Tab Content -->
                        <div class="tab-content" id="companyTabsContent">
                            
                            <!-- Company Information Tab -->
                            <div class="tab-pane fade show active" id="info" role="tabpanel">
                                <div class="row mt-4">
                                    <div class="col-lg-8">
                                        <form method="POST">
                                            <input type="hidden" name="csrf_token" value="<?= generateCSRFToken() ?>">
                                            <input type="hidden" name="action" value="update_company_info">
                                            
                                            <div class="row">
                                                <div class="col-md-6 mb-3">
                                                    <label for="company_name" class="form-label">
                                                        <?= __('company_name') ?> <span class="text-danger">*</span>
                                                    </label>
                                                    <input type="text" class="form-control" id="company_name" name="company_name" 
                                                           value="<?= htmlspecialchars($company_settings['name'] ?? '') ?>" required>
                                                </div>
                                                
                                                <div class="col-md-6 mb-3">
                                                    <label for="company_email" class="form-label">
                                                        <?= __('company_email') ?> <span class="text-danger">*</span>
                                                    </label>
                                                    <input type="email" class="form-control" id="company_email" name="company_email" 
                                                           value="<?= htmlspecialchars($company_settings['email'] ?? '') ?>" required>
                                                </div>
                                            </div>
                                            
                                            <div class="row">
                                                <div class="col-md-6 mb-3">
                                                    <label for="company_phone" class="form-label">
                                                        <?= __('company_phone') ?> <span class="text-danger">*</span>
                                                    </label>
                                                    <input type="tel" class="form-control" id="company_phone" name="company_phone" 
                                                           value="<?= htmlspecialchars($company_settings['phone'] ?? '') ?>" required>
                                                </div>
                                                
                                                <div class="col-md-6 mb-3">
                                                    <label for="company_website" class="form-label">
                                                        <?= __('company_website') ?>
                                                    </label>
                                                    <input type="url" class="form-control" id="company_website" name="company_website" 
                                                           value="<?= htmlspecialchars($company_settings['website'] ?? '') ?>" 
                                                           placeholder="https://www.example.com">
                                                </div>
                                            </div>
                                            
                                            <div class="mb-3">
                                                <label for="company_address" class="form-label">
                                                    <?= __('company_address') ?> <span class="text-danger">*</span>
                                                </label>
                                                <textarea class="form-control" id="company_address" name="company_address" 
                                                          rows="3" required><?= htmlspecialchars($company_settings['address'] ?? '') ?></textarea>
                                            </div>
                                            
                                            <div class="row">
                                                <div class="col-md-6 mb-3">
                                                    <label for="company_registration" class="form-label">
                                                        <?= __('company_registration') ?>
                                                    </label>
                                                    <input type="text" class="form-control" id="company_registration" name="company_registration" 
                                                           value="<?= htmlspecialchars($company_settings['registration'] ?? '') ?>" 
                                                           placeholder="Company Registration Number">
                                                </div>
                                                
                                                <div class="col-md-6 mb-3">
                                                    <label for="company_tax_id" class="form-label">
                                                        <?= __('company_tax_id') ?>
                                                    </label>
                                                    <input type="text" class="form-control" id="company_tax_id" name="company_tax_id" 
                                                           value="<?= htmlspecialchars($company_settings['tax_id'] ?? '') ?>" 
                                                           placeholder="Tax ID / EIN">
                                                </div>
                                            </div>
                                            
                                            <div class="mb-4">
                                                <label for="company_description" class="form-label">
                                                    <?= __('company_description') ?>
                                                </label>
                                                <textarea class="form-control" id="company_description" name="company_description" 
                                                          rows="4" placeholder="Brief description of your company..."><?= htmlspecialchars($company_settings['description'] ?? '') ?></textarea>
                                            </div>
                                            
                                            <button type="submit" class="btn btn-primary">
                                                <i class="fas fa-save me-2"></i><?= __('save_changes') ?>
                                            </button>
                                        </form>
                                    </div>
                                    
                                    <div class="col-lg-4">
                                        <div class="card bg-light">
                                            <div class="card-body">
                                                <h6 class="card-title">
                                                    <i class="fas fa-info-circle me-2"></i><?= __('important_note') ?>
                                                </h6>
                                                <p class="card-text small">
                                                    <?= __('company_settings_note') ?>
                                                </p>
                                                <ul class="small">
                                                    <li><?= __('updates_reflected_everywhere') ?></li>
                                                    <li><?= __('affects_email_templates') ?></li>
                                                    <li><?= __('affects_documents') ?></li>
                                                    <li><?= __('affects_website_display') ?></li>
                                                </ul>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            
                            <!-- Logo & Branding Tab -->
                            <div class="tab-pane fade" id="logo" role="tabpanel">
                                <div class="row mt-4">
                                    <div class="col-lg-6">
                                        <h5><?= __('company_logo') ?></h5>
                                        
                                        <div class="text-center mb-4">
                                            <div class="logo-preview border rounded p-4" style="min-height: 200px; display: flex; align-items: center; justify-content: center;">
                                                <?php if (!empty($company_settings['logo'])): ?>
                                                    <img src="../assets/images/<?= htmlspecialchars($company_settings['logo']) ?>" 
                                                         alt="Company Logo" class="img-fluid" style="max-height: 150px;">
                                                <?php else: ?>
                                                    <div class="text-muted">
                                                        <i class="fas fa-image fa-3x mb-2"></i>
                                                        <p><?= __('no_logo_uploaded') ?></p>
                                                    </div>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                        
                                        <form method="POST" enctype="multipart/form-data">
                                            <input type="hidden" name="csrf_token" value="<?= generateCSRFToken() ?>">
                                            <input type="hidden" name="action" value="upload_logo">
                                            
                                            <div class="mb-3">
                                                <label for="company_logo" class="form-label">
                                                    <?= __('upload_logo') ?>
                                                </label>
                                                <input type="file" class="form-control" id="company_logo" name="company_logo" 
                                                       accept="image/*" required>
                                                <div class="form-text">
                                                    <?= __('logo_requirements') ?>
                                                </div>
                                            </div>
                                            
                                            <button type="submit" class="btn btn-primary">
                                                <i class="fas fa-upload me-2"></i><?= __('upload_logo') ?>
                                            </button>
                                        </form>
                                    </div>
                                    
                                    <div class="col-lg-6">
                                        <h5><?= __('logo_usage') ?></h5>
                                        <div class="alert alert-info">
                                            <h6><?= __('logo_will_appear_in') ?>:</h6>
                                            <ul class="mb-0">
                                                <li><?= __('website_header') ?></li>
                                                <li><?= __('email_templates') ?></li>
                                                <li><?= __('pdf_documents') ?></li>
                                                <li><?= __('letterhead') ?></li>
                                                <li><?= __('admin_panel') ?></li>
                                                <li><?= __('client_portal') ?></li>
                                            </ul>
                                        </div>
                                        
                                        <div class="card">
                                            <div class="card-header">
                                                <h6 class="mb-0"><?= __('logo_specifications') ?></h6>
                                            </div>
                                            <div class="card-body">
                                                <table class="table table-sm">
                                                    <tr>
                                                        <td><?= __('format') ?>:</td>
                                                        <td>PNG, JPG, SVG</td>
                                                    </tr>
                                                    <tr>
                                                        <td><?= __('max_size') ?>:</td>
                                                        <td>2MB</td>
                                                    </tr>
                                                    <tr>
                                                        <td><?= __('recommended_size') ?>:</td>
                                                        <td>300x100px</td>
                                                    </tr>
                                                    <tr>
                                                        <td><?= __('background') ?>:</td>
                                                        <td>Transparent (PNG)</td>
                                                    </tr>
                                                </table>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            
                            <!-- Brand Colors Tab -->
                            <div class="tab-pane fade" id="branding" role="tabpanel">
                                <div class="row mt-4">
                                    <div class="col-lg-8">
                                        <form method="POST">
                                            <input type="hidden" name="csrf_token" value="<?= generateCSRFToken() ?>">
                                            <input type="hidden" name="action" value="update_branding">
                                            
                                            <h5><?= __('brand_colors') ?></h5>
                                            
                                            <div class="row">
                                                <div class="col-md-4 mb-3">
                                                    <label for="brand_primary_color" class="form-label">
                                                        <?= __('primary_color') ?>
                                                    </label>
                                                    <div class="input-group">
                                                        <input type="color" class="form-control form-control-color" id="brand_primary_color" 
                                                               name="brand_primary_color" value="<?= htmlspecialchars($branding_settings['primary_color'] ?? '#007bff') ?>">
                                                        <input type="text" class="form-control" value="<?= htmlspecialchars($branding_settings['primary_color'] ?? '#007bff') ?>" 
                                                               id="primary_color_text" readonly>
                                                    </div>
                                                </div>
                                                
                                                <div class="col-md-4 mb-3">
                                                    <label for="brand_secondary_color" class="form-label">
                                                        <?= __('secondary_color') ?>
                                                    </label>
                                                    <div class="input-group">
                                                        <input type="color" class="form-control form-control-color" id="brand_secondary_color" 
                                                               name="brand_secondary_color" value="<?= htmlspecialchars($branding_settings['secondary_color'] ?? '#6c757d') ?>">
                                                        <input type="text" class="form-control" value="<?= htmlspecialchars($branding_settings['secondary_color'] ?? '#6c757d') ?>" 
                                                               id="secondary_color_text" readonly>
                                                    </div>
                                                </div>
                                                
                                                <div class="col-md-4 mb-3">
                                                    <label for="brand_accent_color" class="form-label">
                                                        <?= __('accent_color') ?>
                                                    </label>
                                                    <div class="input-group">
                                                        <input type="color" class="form-control form-control-color" id="brand_accent_color" 
                                                               name="brand_accent_color" value="<?= htmlspecialchars($branding_settings['accent_color'] ?? '#28a745') ?>">
                                                        <input type="text" class="form-control" value="<?= htmlspecialchars($branding_settings['accent_color'] ?? '#28a745') ?>" 
                                                               id="accent_color_text" readonly>
                                                    </div>
                                                </div>
                                            </div>
                                            
                                            <h5><?= __('typography') ?></h5>
                                            
                                            <div class="mb-3">
                                                <label for="brand_font_family" class="form-label">
                                                    <?= __('font_family') ?>
                                                </label>
                                                <select class="form-select" id="brand_font_family" name="brand_font_family">
                                                    <option value="Arial, sans-serif" <?= ($branding_settings['font_family'] ?? '') === 'Arial, sans-serif' ? 'selected' : '' ?>>Arial</option>
                                                    <option value="Helvetica, sans-serif" <?= ($branding_settings['font_family'] ?? '') === 'Helvetica, sans-serif' ? 'selected' : '' ?>>Helvetica</option>
                                                    <option value="'Times New Roman', serif" <?= ($branding_settings['font_family'] ?? '') === "'Times New Roman', serif" ? 'selected' : '' ?>>Times New Roman</option>
                                                    <option value="Georgia, serif" <?= ($branding_settings['font_family'] ?? '') === 'Georgia, serif' ? 'selected' : '' ?>>Georgia</option>
                                                    <option value="'Roboto', sans-serif" <?= ($branding_settings['font_family'] ?? '') === "'Roboto', sans-serif" ? 'selected' : '' ?>>Roboto</option>
                                                    <option value="'Open Sans', sans-serif" <?= ($branding_settings['font_family'] ?? '') === "'Open Sans', sans-serif" ? 'selected' : '' ?>>Open Sans</option>
                                                </select>
                                            </div>
                                            
                                            <button type="submit" class="btn btn-primary">
                                                <i class="fas fa-save me-2"></i><?= __('save_branding') ?>
                                            </button>
                                        </form>
                                    </div>
                                    
                                    <div class="col-lg-4">
                                        <div class="card">
                                            <div class="card-header">
                                                <h6 class="mb-0"><?= __('color_preview') ?></h6>
                                            </div>
                                            <div class="card-body">
                                                <div class="color-preview-item mb-2">
                                                    <div class="color-box" id="primary_preview" style="background-color: <?= htmlspecialchars($branding_settings['primary_color'] ?? '#007bff') ?>"></div>
                                                    <span><?= __('primary_buttons_links') ?></span>
                                                </div>
                                                <div class="color-preview-item mb-2">
                                                    <div class="color-box" id="secondary_preview" style="background-color: <?= htmlspecialchars($branding_settings['secondary_color'] ?? '#6c757d') ?>"></div>
                                                    <span><?= __('secondary_elements') ?></span>
                                                </div>
                                                <div class="color-preview-item mb-2">
                                                    <div class="color-box" id="accent_preview" style="background-color: <?= htmlspecialchars($branding_settings['accent_color'] ?? '#28a745') ?>"></div>
                                                    <span><?= __('success_highlights') ?></span>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            
                            <!-- Templates Tab -->
                            <div class="tab-pane fade" id="templates" role="tabpanel">
                                <div class="row mt-4">
                                    <div class="col-lg-6">
                                        <h5><?= __('letterhead_template') ?></h5>
                                        <div class="letterhead-preview border rounded p-4 mb-3" style="min-height: 300px; background: white;">
                                            <div class="letterhead-header d-flex align-items-center mb-4">
                                                <?php if (!empty($company_settings['logo'])): ?>
                                                    <img src="../assets/images/<?= htmlspecialchars($company_settings['logo']) ?>" 
                                                         alt="Logo" style="max-height: 60px; margin-right: 20px;">
                                                <?php endif; ?>
                                                <div>
                                                    <h4 class="mb-1"><?= htmlspecialchars($company_settings['name'] ?? 'Company Name') ?></h4>
                                                    <p class="mb-0 text-muted small"><?= htmlspecialchars($company_settings['address'] ?? 'Company Address') ?></p>
                                                </div>
                                            </div>
                                            <hr>
                                            <div class="letterhead-content">
                                                <p><strong>Date:</strong> <?= date('F j, Y') ?></p>
                                                <p><strong>To:</strong> [Recipient Name]</p>
                                                <br>
                                                <p>Dear [Recipient Name],</p>
                                                <p>This is a sample letterhead preview showing how your company branding will appear on official documents.</p>
                                                <p>Best regards,</p>
                                                <p><strong><?= htmlspecialchars($company_settings['name'] ?? 'Company Name') ?></strong></p>
                                            </div>
                                            <hr>
                                            <div class="letterhead-footer text-center small text-muted">
                                                <p class="mb-0">
                                                    <?= htmlspecialchars($company_settings['phone'] ?? 'Phone') ?> | 
                                                    <?= htmlspecialchars($company_settings['email'] ?? 'Email') ?> | 
                                                    <?= htmlspecialchars($company_settings['website'] ?? 'Website') ?>
                                                </p>
                                            </div>
                                        </div>
                                    </div>
                                    
                                    <div class="col-lg-6">
                                        <h5><?= __('email_template_preview') ?></h5>
                                        <div class="email-preview border rounded p-4" style="min-height: 300px; background: #f8f9fa;">
                                            <div class="email-header text-center p-3 mb-3" style="background: <?= htmlspecialchars($branding_settings['primary_color'] ?? '#007bff') ?>; color: white; border-radius: 8px;">
                                                <?php if (!empty($company_settings['logo'])): ?>
                                                    <img src="../assets/images/<?= htmlspecialchars($company_settings['logo']) ?>" 
                                                         alt="Logo" style="max-height: 40px; margin-bottom: 10px;">
                                                <?php endif; ?>
                                                <h5 class="mb-0"><?= htmlspecialchars($company_settings['name'] ?? 'Company Name') ?></h5>
                                            </div>
                                            <div class="email-content p-3 bg-white rounded">
                                                <h6>Dear [Client Name],</h6>
                                                <p>This is a preview of how your branded emails will look to clients.</p>
                                                <div class="text-center my-3">
                                                    <a href="#" class="btn text-white" style="background-color: <?= htmlspecialchars($branding_settings['primary_color'] ?? '#007bff') ?>">
                                                        <?= __('sample_button') ?>
                                                    </a>
                                                </div>
                                                <p>Best regards,<br>The <?= htmlspecialchars($company_settings['name'] ?? 'Company') ?> Team</p>
                                            </div>
                                            <div class="email-footer text-center p-2 small text-muted">
                                                <p class="mb-0">
                                                    <?= htmlspecialchars($company_settings['address'] ?? 'Company Address') ?><br>
                                                    <?= htmlspecialchars($company_settings['phone'] ?? 'Phone') ?> | <?= htmlspecialchars($company_settings['email'] ?? 'Email') ?>
                                                </p>
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
        // Color picker synchronization
        document.querySelectorAll('input[type="color"]').forEach(colorPicker => {
            colorPicker.addEventListener('input', function() {
                const textInput = document.getElementById(this.id + '_text');
                if (textInput) {
                    textInput.value = this.value;
                }
                
                // Update preview
                const previewId = this.id.replace('brand_', '') + '_preview';
                const preview = document.getElementById(previewId);
                if (preview) {
                    preview.style.backgroundColor = this.value;
                }
            });
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
        .color-box {
            width: 30px;
            height: 30px;
            border-radius: 4px;
            display: inline-block;
            margin-right: 10px;
            border: 1px solid #ddd;
        }
        .color-preview-item {
            display: flex;
            align-items: center;
        }
    </style>
</body>
</html>
