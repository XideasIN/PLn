<?php
/**
 * Template Management System
 * LoanFlow Personal Loan Management System
 */

require_once '../config/database.php';
require_once '../includes/functions.php';
require_once '../includes/auth.php';
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
            case 'switch_template':
                $template_type = sanitizeInput($_POST['template_type'] ?? '');
                $template_key = sanitizeInput($_POST['template_key'] ?? '');
                
                if (empty($template_type) || empty($template_key)) {
                    $error = __('template_switch_invalid_parameters');
                } else {
                    $result = switchActiveTemplate($template_type, $template_key);
                    if ($result) {
                        $success = __('template_switched_successfully');
                        logAudit('template_switched', 'templates', null, $current_user['id'], [
                            'type' => $template_type,
                            'template_key' => $template_key
                        ]);
                    } else {
                        $error = __('template_switch_failed');
                    }
                }
                break;
                
            case 'upload_template':
                $upload_result = handleTemplateUpload($_FILES['template_file'] ?? null, $_POST);
                if ($upload_result['success']) {
                    $success = __('template_uploaded_successfully');
                    logAudit('template_uploaded', 'templates', null, $current_user['id'], $upload_result);
                } else {
                    $error = $upload_result['error'];
                }
                break;
                
            case 'delete_template':
                $template_id = intval($_POST['template_id'] ?? 0);
                if ($template_id > 0) {
                    $result = deleteTemplate($template_id);
                    if ($result) {
                        $success = __('template_deleted_successfully');
                        logAudit('template_deleted', 'templates', $template_id, $current_user['id']);
                    } else {
                        $error = __('template_delete_failed');
                    }
                }
                break;
        }
    }
}

// Get available templates
$templates = getAvailableTemplates();
$active_templates = getActiveTemplates();

?>
<!DOCTYPE html>
<html lang="<?= LanguageManager::getCurrentLanguage() ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= __('template_management') ?> - LoanFlow Admin</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="../assets/css/style.css" rel="stylesheet">
    <link href="../assets/css/admin.css" rel="stylesheet">
    
    <style>
        .template-card {
            transition: transform 0.2s, box-shadow 0.2s;
            border: 2px solid transparent;
        }
        .template-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 8px 25px rgba(0,0,0,0.15);
        }
        .template-card.active {
            border-color: #28a745;
            background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);
        }
        .template-preview {
            height: 200px;
            overflow: hidden;
            background: #f8f9fa;
            border-radius: 8px;
            position: relative;
        }
        .template-preview img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }
        .template-preview .no-preview {
            display: flex;
            align-items: center;
            justify-content: center;
            height: 100%;
            color: #6c757d;
            font-size: 2rem;
        }
        .template-type-badge {
            position: absolute;
            top: 10px;
            right: 10px;
            z-index: 10;
        }
        .active-badge {
            position: absolute;
            top: 10px;
            left: 10px;
            z-index: 10;
        }
        .upload-area {
            border: 2px dashed #dee2e6;
            border-radius: 8px;
            padding: 40px;
            text-align: center;
            transition: all 0.3s ease;
        }
        .upload-area:hover {
            border-color: #007bff;
            background-color: #f8f9fa;
        }
        .upload-area.dragover {
            border-color: #28a745;
            background-color: #d4edda;
        }
    </style>
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

        <!-- Header -->
        <div class="d-flex justify-content-between align-items-center mb-4">
            <div>
                <h1 class="h3 mb-0">
                    <i class="fas fa-palette me-2 text-primary"></i><?= __('template_management') ?>
                </h1>
                <p class="text-muted mt-2"><?= __('template_management_description') ?></p>
            </div>
            <div>
                <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#uploadModal">
                    <i class="fas fa-upload me-2"></i><?= __('upload_template') ?>
                </button>
                <button type="button" class="btn btn-outline-primary" data-bs-toggle="modal" data-bs-target="#onboardingModal">
                    <i class="fas fa-question-circle me-2"></i><?= __('help_guide') ?>
                </button>
            </div>
        </div>

        <!-- Active Templates Overview -->
        <div class="row mb-4">
            <div class="col-12">
                <div class="card">
                    <div class="card-header">
                        <h5 class="mb-0">
                            <i class="fas fa-star me-2"></i><?= __('active_templates') ?>
                        </h5>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-4">
                                <div class="d-flex align-items-center">
                                    <div class="flex-shrink-0">
                                        <i class="fas fa-globe fa-2x text-primary"></i>
                                    </div>
                                    <div class="flex-grow-1 ms-3">
                                        <h6 class="mb-0"><?= __('frontend_template') ?></h6>
                                        <small class="text-muted"><?= htmlspecialchars($active_templates['frontend']['name'] ?? __('no_template_active')) ?></small>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="d-flex align-items-center">
                                    <div class="flex-shrink-0">
                                        <i class="fas fa-user fa-2x text-success"></i>
                                    </div>
                                    <div class="flex-grow-1 ms-3">
                                        <h6 class="mb-0"><?= __('client_template') ?></h6>
                                        <small class="text-muted"><?= htmlspecialchars($active_templates['client']['name'] ?? __('no_template_active')) ?></small>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="d-flex align-items-center">
                                    <div class="flex-shrink-0">
                                        <i class="fas fa-cogs fa-2x text-warning"></i>
                                    </div>
                                    <div class="flex-grow-1 ms-3">
                                        <h6 class="mb-0"><?= __('admin_template') ?></h6>
                                        <small class="text-muted"><?= htmlspecialchars($active_templates['admin']['name'] ?? __('no_template_active')) ?></small>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Template Categories -->
        <div class="row">
            <div class="col-12">
                <ul class="nav nav-tabs" id="templateTabs" role="tablist">
                    <li class="nav-item" role="presentation">
                        <button class="nav-link active" id="frontend-tab" data-bs-toggle="tab" data-bs-target="#frontend" type="button" role="tab">
                            <i class="fas fa-globe me-2"></i><?= __('frontend_templates') ?>
                            <span class="badge bg-primary ms-2"><?= count($templates['frontend'] ?? []) ?></span>
                        </button>
                    </li>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link" id="client-tab" data-bs-toggle="tab" data-bs-target="#client" type="button" role="tab">
                            <i class="fas fa-user me-2"></i><?= __('client_templates') ?>
                            <span class="badge bg-success ms-2"><?= count($templates['client'] ?? []) ?></span>
                        </button>
                    </li>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link" id="admin-tab" data-bs-toggle="tab" data-bs-target="#admin" type="button" role="tab">
                            <i class="fas fa-cogs me-2"></i><?= __('admin_templates') ?>
                            <span class="badge bg-warning ms-2"><?= count($templates['admin'] ?? []) ?></span>
                        </button>
                    </li>
                </ul>
            </div>
        </div>

        <!-- Template Content -->
        <div class="tab-content" id="templateTabsContent">
            
            <!-- Frontend Templates -->
            <div class="tab-pane fade show active" id="frontend" role="tabpanel">
                <div class="py-4">
                    <div class="row">
                        <?php if (!empty($templates['frontend'])): ?>
                            <?php foreach ($templates['frontend'] as $template): ?>
                                <div class="col-lg-4 col-md-6 mb-4">
                                    <div class="card template-card h-100 <?= $template['is_active'] ? 'active' : '' ?>">
                                        <div class="template-preview">
                                            <?php if ($template['is_active']): ?>
                                                <span class="badge bg-success active-badge">
                                                    <i class="fas fa-check me-1"></i><?= __('active') ?>
                                                </span>
                                            <?php endif; ?>
                                            <span class="badge bg-primary template-type-badge"><?= __('frontend') ?></span>
                                            <?php if ($template['preview_image']): ?>
                                                <img src="<?= htmlspecialchars($template['preview_image']) ?>" alt="Preview">
                                            <?php else: ?>
                                                <div class="no-preview">
                                                    <i class="fas fa-image"></i>
                                                </div>
                                            <?php endif; ?>
                                        </div>
                                        <div class="card-body">
                                            <h6 class="card-title"><?= htmlspecialchars($template['name']) ?></h6>
                                            <p class="card-text small text-muted"><?= htmlspecialchars($template['description']) ?></p>
                                            <div class="d-flex justify-content-between align-items-center">
                                                <small class="text-muted">
                                                    <i class="fas fa-code me-1"></i>v<?= htmlspecialchars($template['version']) ?>
                                                </small>
                                                <div class="btn-group btn-group-sm" role="group">
                                                    <?php if (!$template['is_active']): ?>
                                                        <button type="button" class="btn btn-outline-success" onclick="switchTemplate('frontend', '<?= htmlspecialchars($template['template_key']) ?>')">
                                                            <i class="fas fa-check"></i>
                                                        </button>
                                                    <?php endif; ?>
                                                    <button type="button" class="btn btn-outline-primary" onclick="previewTemplate('<?= htmlspecialchars($template['template_key']) ?>')">
                                                        <i class="fas fa-eye"></i>
                                                    </button>
                                                    <button type="button" class="btn btn-outline-danger" onclick="deleteTemplate(<?= $template['id'] ?>)">
                                                        <i class="fas fa-trash"></i>
                                                    </button>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <div class="col-12">
                                <div class="text-center py-5">
                                    <i class="fas fa-palette fa-3x text-muted mb-3"></i>
                                    <h5 class="text-muted"><?= __('no_frontend_templates') ?></h5>
                                    <p class="text-muted"><?= __('upload_your_first_frontend_template') ?></p>
                                    <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#uploadModal">
                                        <i class="fas fa-upload me-2"></i><?= __('upload_template') ?>
                                    </button>
                                </div>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <!-- Client Templates -->
            <div class="tab-pane fade" id="client" role="tabpanel">
                <div class="py-4">
                    <div class="row">
                        <?php if (!empty($templates['client'])): ?>
                            <?php foreach ($templates['client'] as $template): ?>
                                <div class="col-lg-4 col-md-6 mb-4">
                                    <div class="card template-card h-100 <?= $template['is_active'] ? 'active' : '' ?>">
                                        <div class="template-preview">
                                            <?php if ($template['is_active']): ?>
                                                <span class="badge bg-success active-badge">
                                                    <i class="fas fa-check me-1"></i><?= __('active') ?>
                                                </span>
                                            <?php endif; ?>
                                            <span class="badge bg-success template-type-badge"><?= __('client') ?></span>
                                            <?php if ($template['preview_image']): ?>
                                                <img src="<?= htmlspecialchars($template['preview_image']) ?>" alt="Preview">
                                            <?php else: ?>
                                                <div class="no-preview">
                                                    <i class="fas fa-image"></i>
                                                </div>
                                            <?php endif; ?>
                                        </div>
                                        <div class="card-body">
                                            <h6 class="card-title"><?= htmlspecialchars($template['name']) ?></h6>
                                            <p class="card-text small text-muted"><?= htmlspecialchars($template['description']) ?></p>
                                            <div class="d-flex justify-content-between align-items-center">
                                                <small class="text-muted">
                                                    <i class="fas fa-code me-1"></i>v<?= htmlspecialchars($template['version']) ?>
                                                </small>
                                                <div class="btn-group btn-group-sm" role="group">
                                                    <?php if (!$template['is_active']): ?>
                                                        <button type="button" class="btn btn-outline-success" onclick="switchTemplate('client', '<?= htmlspecialchars($template['template_key']) ?>')">
                                                            <i class="fas fa-check"></i>
                                                        </button>
                                                    <?php endif; ?>
                                                    <button type="button" class="btn btn-outline-primary" onclick="previewTemplate('<?= htmlspecialchars($template['template_key']) ?>')">
                                                        <i class="fas fa-eye"></i>
                                                    </button>
                                                    <button type="button" class="btn btn-outline-danger" onclick="deleteTemplate(<?= $template['id'] ?>)">
                                                        <i class="fas fa-trash"></i>
                                                    </button>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <div class="col-12">
                                <div class="text-center py-5">
                                    <i class="fas fa-user fa-3x text-muted mb-3"></i>
                                    <h5 class="text-muted"><?= __('no_client_templates') ?></h5>
                                    <p class="text-muted"><?= __('upload_your_first_client_template') ?></p>
                                    <button type="button" class="btn btn-success" data-bs-toggle="modal" data-bs-target="#uploadModal">
                                        <i class="fas fa-upload me-2"></i><?= __('upload_template') ?>
                                    </button>
                                </div>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <!-- Admin Templates -->
            <div class="tab-pane fade" id="admin" role="tabpanel">
                <div class="py-4">
                    <div class="row">
                        <?php if (!empty($templates['admin'])): ?>
                            <?php foreach ($templates['admin'] as $template): ?>
                                <div class="col-lg-4 col-md-6 mb-4">
                                    <div class="card template-card h-100 <?= $template['is_active'] ? 'active' : '' ?>">
                                        <div class="template-preview">
                                            <?php if ($template['is_active']): ?>
                                                <span class="badge bg-success active-badge">
                                                    <i class="fas fa-check me-1"></i><?= __('active') ?>
                                                </span>
                                            <?php endif; ?>
                                            <span class="badge bg-warning template-type-badge"><?= __('admin') ?></span>
                                            <?php if ($template['preview_image']): ?>
                                                <img src="<?= htmlspecialchars($template['preview_image']) ?>" alt="Preview">
                                            <?php else: ?>
                                                <div class="no-preview">
                                                    <i class="fas fa-image"></i>
                                                </div>
                                            <?php endif; ?>
                                        </div>
                                        <div class="card-body">
                                            <h6 class="card-title"><?= htmlspecialchars($template['name']) ?></h6>
                                            <p class="card-text small text-muted"><?= htmlspecialchars($template['description']) ?></p>
                                            <div class="d-flex justify-content-between align-items-center">
                                                <small class="text-muted">
                                                    <i class="fas fa-code me-1"></i>v<?= htmlspecialchars($template['version']) ?>
                                                </small>
                                                <div class="btn-group btn-group-sm" role="group">
                                                    <?php if (!$template['is_active']): ?>
                                                        <button type="button" class="btn btn-outline-success" onclick="switchTemplate('admin', '<?= htmlspecialchars($template['template_key']) ?>')">
                                                            <i class="fas fa-check"></i>
                                                        </button>
                                                    <?php endif; ?>
                                                    <button type="button" class="btn btn-outline-primary" onclick="previewTemplate('<?= htmlspecialchars($template['template_key']) ?>')">
                                                        <i class="fas fa-eye"></i>
                                                    </button>
                                                    <button type="button" class="btn btn-outline-danger" onclick="deleteTemplate(<?= $template['id'] ?>)">
                                                        <i class="fas fa-trash"></i>
                                                    </button>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <div class="col-12">
                                <div class="text-center py-5">
                                    <i class="fas fa-cogs fa-3x text-muted mb-3"></i>
                                    <h5 class="text-muted"><?= __('no_admin_templates') ?></h5>
                                    <p class="text-muted"><?= __('upload_your_first_admin_template') ?></p>
                                    <button type="button" class="btn btn-warning" data-bs-toggle="modal" data-bs-target="#uploadModal">
                                        <i class="fas fa-upload me-2"></i><?= __('upload_template') ?>
                                    </button>
                                </div>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Upload Template Modal -->
    <div class="modal fade" id="uploadModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">
                        <i class="fas fa-upload me-2"></i><?= __('upload_new_template') ?>
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST" enctype="multipart/form-data">
                    <input type="hidden" name="csrf_token" value="<?= generateCSRFToken() ?>">
                    <input type="hidden" name="action" value="upload_template">
                    
                    <div class="modal-body">
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="template_name" class="form-label">
                                        <?= __('template_name') ?> <span class="text-danger">*</span>
                                    </label>
                                    <input type="text" class="form-control" id="template_name" name="template_name" required>
                                </div>
                                
                                <div class="mb-3">
                                    <label for="template_type" class="form-label">
                                        <?= __('template_type') ?> <span class="text-danger">*</span>
                                    </label>
                                    <select class="form-select" id="template_type" name="template_type" required>
                                        <option value=""><?= __('select_template_type') ?></option>
                                        <option value="frontend"><?= __('frontend_template') ?></option>
                                        <option value="client"><?= __('client_template') ?></option>
                                        <option value="admin"><?= __('admin_template') ?></option>
                                    </select>
                                </div>
                                
                                <div class="mb-3">
                                    <label for="template_version" class="form-label">
                                        <?= __('version') ?>
                                    </label>
                                    <input type="text" class="form-control" id="template_version" name="template_version" value="1.0.0">
                                </div>
                            </div>
                            
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="template_description" class="form-label">
                                        <?= __('description') ?>
                                    </label>
                                    <textarea class="form-control" id="template_description" name="template_description" rows="4" placeholder="<?= __('template_description_placeholder') ?>"></textarea>
                                </div>
                            </div>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">
                                <?= __('template_files') ?> <span class="text-danger">*</span>
                            </label>
                            <div class="upload-area" onclick="document.getElementById('template_file').click()">
                                <i class="fas fa-cloud-upload-alt fa-3x text-muted mb-3"></i>
                                <h6><?= __('click_to_upload_or_drag_drop') ?></h6>
                                <p class="text-muted small mb-0"><?= __('supported_formats_zip_html_php') ?></p>
                                <input type="file" id="template_file" name="template_file" class="d-none" accept=".zip,.html,.php" required>
                            </div>
                        </div>
                    </div>
                    
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                            <?= __('cancel') ?>
                        </button>
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-upload me-2"></i><?= __('upload_template') ?>
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Onboarding/Help Modal -->
    <div class="modal fade" id="onboardingModal" tabindex="-1">
        <div class="modal-dialog modal-xl">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">
                        <i class="fas fa-graduation-cap me-2"></i><?= __('template_management_guide') ?>
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="row">
                        <div class="col-lg-8">
                            <h6 class="border-bottom pb-2"><?= __('getting_started') ?></h6>
                            <div class="row">
                                <div class="col-md-4 mb-3">
                                    <div class="text-center">
                                        <div class="bg-primary text-white rounded-circle d-inline-flex align-items-center justify-content-center" style="width: 50px; height: 50px;">
                                            <span class="fw-bold">1</span>
                                        </div>
                                        <h6 class="mt-2"><?= __('upload_templates') ?></h6>
                                        <p class="small text-muted"><?= __('upload_step_description') ?></p>
                                    </div>
                                </div>
                                <div class="col-md-4 mb-3">
                                    <div class="text-center">
                                        <div class="bg-success text-white rounded-circle d-inline-flex align-items-center justify-content-center" style="width: 50px; height: 50px;">
                                            <span class="fw-bold">2</span>
                                        </div>
                                        <h6 class="mt-2"><?= __('preview_switch') ?></h6>
                                        <p class="small text-muted"><?= __('preview_step_description') ?></p>
                                    </div>
                                </div>
                                <div class="col-md-4 mb-3">
                                    <div class="text-center">
                                        <div class="bg-warning text-white rounded-circle d-inline-flex align-items-center justify-content-center" style="width: 50px; height: 50px;">
                                            <span class="fw-bold">3</span>
                                        </div>
                                        <h6 class="mt-2"><?= __('activate_enjoy') ?></h6>
                                        <p class="small text-muted"><?= __('activate_step_description') ?></p>
                                    </div>
                                </div>
                            </div>
                            
                            <h6 class="border-bottom pb-2 mt-4"><?= __('template_types') ?></h6>
                            <div class="accordion" id="templateTypesAccordion">
                                <div class="accordion-item">
                                    <h2 class="accordion-header">
                                        <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#frontendAccordion">
                                            <i class="fas fa-globe me-2 text-primary"></i><?= __('frontend_templates') ?>
                                        </button>
                                    </h2>
                                    <div id="frontendAccordion" class="accordion-collapse collapse" data-bs-parent="#templateTypesAccordion">
                                        <div class="accordion-body">
                                            <p><?= __('frontend_templates_description') ?></p>
                                        </div>
                                    </div>
                                </div>
                                <div class="accordion-item">
                                    <h2 class="accordion-header">
                                        <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#clientAccordion">
                                            <i class="fas fa-user me-2 text-success"></i><?= __('client_templates') ?>
                                        </button>
                                    </h2>
                                    <div id="clientAccordion" class="accordion-collapse collapse" data-bs-parent="#templateTypesAccordion">
                                        <div class="accordion-body">
                                            <p><?= __('client_templates_description') ?></p>
                                        </div>
                                    </div>
                                </div>
                                <div class="accordion-item">
                                    <h2 class="accordion-header">
                                        <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#adminAccordion">
                                            <i class="fas fa-cogs me-2 text-warning"></i><?= __('admin_templates') ?>
                                        </button>
                                    </h2>
                                    <div id="adminAccordion" class="accordion-collapse collapse" data-bs-parent="#templateTypesAccordion">
                                        <div class="accordion-body">
                                            <p><?= __('admin_templates_description') ?></p>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <div class="col-lg-4">
                            <div class="card bg-light">
                                <div class="card-header">
                                    <h6 class="mb-0">
                                        <i class="fas fa-lightbulb me-2"></i><?= __('tips_best_practices') ?>
                                    </h6>
                                </div>
                                <div class="card-body">
                                    <ul class="list-unstyled small">
                                        <li class="mb-2">
                                            <i class="fas fa-check text-success me-2"></i>
                                            <?= __('tip_responsive_design') ?>
                                        </li>
                                        <li class="mb-2">
                                            <i class="fas fa-check text-success me-2"></i>
                                            <?= __('tip_test_thoroughly') ?>
                                        </li>
                                        <li class="mb-2">
                                            <i class="fas fa-check text-success me-2"></i>
                                            <?= __('tip_backup_before_switch') ?>
                                        </li>
                                        <li class="mb-2">
                                            <i class="fas fa-check text-success me-2"></i>
                                            <?= __('tip_use_version_control') ?>
                                        </li>
                                        <li class="mb-2">
                                            <i class="fas fa-check text-success me-2"></i>
                                            <?= __('tip_optimize_performance') ?>
                                        </li>
                                    </ul>
                                </div>
                            </div>
                            
                            <div class="card bg-warning bg-opacity-10 mt-3">
                                <div class="card-body">
                                    <h6 class="text-warning">
                                        <i class="fas fa-exclamation-triangle me-2"></i><?= __('important_notes') ?>
                                    </h6>
                                    <ul class="list-unstyled small">
                                        <li><?= __('note_backup_current') ?></li>
                                        <li><?= __('note_test_staging') ?></li>
                                        <li><?= __('note_security_scan') ?></li>
                                    </ul>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-primary" data-bs-dismiss="modal">
                        <?= __('got_it') ?>
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Scripts -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Template management functions
        function switchTemplate(type, templateKey) {
            if (confirm('<?= __('confirm_switch_template') ?>')) {
                const form = document.createElement('form');
                form.method = 'POST';
                form.innerHTML = `
                    <input type="hidden" name="csrf_token" value="<?= generateCSRFToken() ?>">
                    <input type="hidden" name="action" value="switch_template">
                    <input type="hidden" name="template_type" value="${type}">
                    <input type="hidden" name="template_key" value="${templateKey}">
                `;
                document.body.appendChild(form);
                form.submit();
            }
        }
        
        function deleteTemplate(templateId) {
            if (confirm('<?= __('confirm_delete_template') ?>')) {
                const form = document.createElement('form');
                form.method = 'POST';
                form.innerHTML = `
                    <input type="hidden" name="csrf_token" value="<?= generateCSRFToken() ?>">
                    <input type="hidden" name="action" value="delete_template">
                    <input type="hidden" name="template_id" value="${templateId}">
                `;
                document.body.appendChild(form);
                form.submit();
            }
        }
        
        function previewTemplate(templateKey) {
            // Open template preview in new window
            window.open(`template-preview.php?template=${templateKey}`, '_blank', 'width=1200,height=800,scrollbars=yes');
        }
        
        // Drag and drop functionality
        const uploadArea = document.querySelector('.upload-area');
        const fileInput = document.getElementById('template_file');
        
        uploadArea.addEventListener('dragover', (e) => {
            e.preventDefault();
            uploadArea.classList.add('dragover');
        });
        
        uploadArea.addEventListener('dragleave', (e) => {
            e.preventDefault();
            uploadArea.classList.remove('dragover');
        });
        
        uploadArea.addEventListener('drop', (e) => {
            e.preventDefault();
            uploadArea.classList.remove('dragover');
            
            const files = e.dataTransfer.files;
            if (files.length > 0) {
                fileInput.files = files;
                updateFileDisplay(files[0]);
            }
        });
        
        fileInput.addEventListener('change', (e) => {
            if (e.target.files.length > 0) {
                updateFileDisplay(e.target.files[0]);
            }
        });
        
        function updateFileDisplay(file) {
            uploadArea.innerHTML = `
                <i class="fas fa-file-alt fa-3x text-success mb-3"></i>
                <h6>${file.name}</h6>
                <p class="text-muted small mb-0">${(file.size / 1024 / 1024).toFixed(2)} MB</p>
            `;
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
