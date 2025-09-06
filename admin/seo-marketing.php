<?php
/**
 * SEO and Marketing Management
 * LoanFlow Personal Loan Management System
 */

require_once '../includes/functions.php';
require_once '../includes/language.php';
require_once '../includes/seo.php';

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
            case 'update_seo_settings':
                $seo_data = [
                    'seo_enabled' => isset($_POST['seo_enabled']) ? '1' : '0',
                    'google_analytics_id' => sanitizeInput($_POST['google_analytics_id'] ?? ''),
                    'google_api_key' => sanitizeInput($_POST['google_api_key'] ?? ''),
                    'search_console_property' => sanitizeInput($_POST['search_console_property'] ?? ''),
                    'meta_title_template' => sanitizeInput($_POST['meta_title_template'] ?? ''),
                    'meta_description_template' => sanitizeInput($_POST['meta_description_template'] ?? ''),
                    'default_keywords' => sanitizeInput($_POST['default_keywords'] ?? ''),
                    'auto_optimize_content' => isset($_POST['auto_optimize_content']) ? '1' : '0'
                ];
                
                if (updateSEOSettings($seo_data)) {
                    $success = __('seo_settings_updated');
                    logAudit('seo_settings_updated', 'system_settings', null, $current_user['id'], $seo_data);
                } else {
                    $error = __('seo_settings_update_failed');
                }
                break;
                
            case 'generate_sitemap':
                try {
                    $sitemap_content = SEOManager::generateSitemap();
                    file_put_contents('../sitemap.xml', $sitemap_content);
                    $success = __('sitemap_generated');
                    logAudit('sitemap_generated', 'seo', null, $current_user['id']);
                } catch (Exception $e) {
                    $error = __('sitemap_generation_failed');
                }
                break;
                
            case 'generate_robots':
                try {
                    $robots_content = SEOManager::generateRobotsTxt();
                    file_put_contents('../robots.txt', $robots_content);
                    $success = __('robots_txt_generated');
                    logAudit('robots_txt_generated', 'seo', null, $current_user['id']);
                } catch (Exception $e) {
                    $error = __('robots_txt_generation_failed');
                }
                break;
                
            case 'perform_keyword_research':
                $keywords = explode(',', sanitizeInput($_POST['seed_keywords'] ?? ''));
                $keyword_results = SEOManager::performKeywordResearch($keywords);
                $_SESSION['keyword_results'] = $keyword_results;
                $success = __('keyword_research_completed');
                break;
                
            case 'analyze_competitors':
                $competitors = explode(',', sanitizeInput($_POST['competitor_domains'] ?? ''));
                $competitor_analysis = SEOManager::analyzeCompetitors($competitors);
                $_SESSION['competitor_analysis'] = $competitor_analysis;
                $success = __('competitor_analysis_completed');
                break;
        }
    }
}

// Get current settings
$seo_settings = getSEOSettings();
$seo_report = SEOManager::generateSEOReport();
$content_suggestions = SEOManager::generateContentSuggestions();

?>
<!DOCTYPE html>
<html lang="<?= LanguageManager::getCurrentLanguage() ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= __('seo_marketing') ?> - LoanFlow Admin</title>
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
                            <i class="fas fa-search me-2"></i><?= __('seo_marketing') ?>
                        </h4>
                    </div>
                    <div class="card-body">
                        
                        <!-- Navigation Tabs -->
                        <ul class="nav nav-tabs" id="seoTabs" role="tablist">
                            <li class="nav-item" role="presentation">
                                <button class="nav-link active" id="overview-tab" data-bs-toggle="tab" data-bs-target="#overview" type="button" role="tab">
                                    <i class="fas fa-chart-line me-2"></i><?= __('overview') ?>
                                </button>
                            </li>
                            <li class="nav-item" role="presentation">
                                <button class="nav-link" id="settings-tab" data-bs-toggle="tab" data-bs-target="#settings" type="button" role="tab">
                                    <i class="fas fa-cogs me-2"></i><?= __('seo_settings') ?>
                                </button>
                            </li>
                            <li class="nav-item" role="presentation">
                                <button class="nav-link" id="keywords-tab" data-bs-toggle="tab" data-bs-target="#keywords" type="button" role="tab">
                                    <i class="fas fa-key me-2"></i><?= __('keyword_research') ?>
                                </button>
                            </li>
                            <li class="nav-item" role="presentation">
                                <button class="nav-link" id="competitors-tab" data-bs-toggle="tab" data-bs-target="#competitors" type="button" role="tab">
                                    <i class="fas fa-users me-2"></i><?= __('competitors') ?>
                                </button>
                            </li>
                            <li class="nav-item" role="presentation">
                                <button class="nav-link" id="content-tab" data-bs-toggle="tab" data-bs-target="#content" type="button" role="tab">
                                    <i class="fas fa-edit me-2"></i><?= __('content_ideas') ?>
                                </button>
                            </li>
                            <li class="nav-item" role="presentation">
                                <button class="nav-link" id="tools-tab" data-bs-toggle="tab" data-bs-target="#tools" type="button" role="tab">
                                    <i class="fas fa-tools me-2"></i><?= __('seo_tools') ?>
                                </button>
                            </li>
                        </ul>
                        
                        <!-- Tab Content -->
                        <div class="tab-content" id="seoTabsContent">
                            
                            <!-- Overview Tab -->
                            <div class="tab-pane fade show active" id="overview" role="tabpanel">
                                <div class="row mt-4">
                                    <div class="col-lg-8">
                                        <h5><?= __('seo_performance_overview') ?></h5>
                                        
                                        <div class="row">
                                            <div class="col-md-3 mb-3">
                                                <div class="card bg-primary text-white">
                                                    <div class="card-body text-center">
                                                        <i class="fas fa-search fa-2x mb-2"></i>
                                                        <h4><?= number_format($seo_report['performance']['organic_traffic']) ?></h4>
                                                        <p class="mb-0"><?= __('organic_traffic') ?></p>
                                                    </div>
                                                </div>
                                            </div>
                                            
                                            <div class="col-md-3 mb-3">
                                                <div class="card bg-success text-white">
                                                    <div class="card-body text-center">
                                                        <i class="fas fa-link fa-2x mb-2"></i>
                                                        <h4><?= $seo_report['performance']['backlinks'] ?></h4>
                                                        <p class="mb-0"><?= __('backlinks') ?></p>
                                                    </div>
                                                </div>
                                            </div>
                                            
                                            <div class="col-md-3 mb-3">
                                                <div class="card bg-info text-white">
                                                    <div class="card-body text-center">
                                                        <i class="fas fa-tachometer-alt fa-2x mb-2"></i>
                                                        <h4><?= $seo_report['performance']['page_speed_score'] ?></h4>
                                                        <p class="mb-0"><?= __('page_speed') ?></p>
                                                    </div>
                                                </div>
                                            </div>
                                            
                                            <div class="col-md-3 mb-3">
                                                <div class="card bg-warning text-white">
                                                    <div class="card-body text-center">
                                                        <i class="fas fa-star fa-2x mb-2"></i>
                                                        <h4><?= $seo_report['performance']['domain_authority'] ?></h4>
                                                        <p class="mb-0"><?= __('domain_authority') ?></p>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                        
                                        <div class="card">
                                            <div class="card-header">
                                                <h6 class="mb-0"><?= __('keyword_rankings') ?></h6>
                                            </div>
                                            <div class="card-body">
                                                <div class="table-responsive">
                                                    <table class="table table-sm">
                                                        <thead>
                                                            <tr>
                                                                <th><?= __('keyword') ?></th>
                                                                <th><?= __('position') ?></th>
                                                                <th><?= __('change') ?></th>
                                                            </tr>
                                                        </thead>
                                                        <tbody>
                                                            <?php foreach ($seo_report['performance']['keyword_rankings'] as $keyword => $position): ?>
                                                                <tr>
                                                                    <td><?= htmlspecialchars($keyword) ?></td>
                                                                    <td>
                                                                        <span class="badge <?= $position <= 10 ? 'bg-success' : ($position <= 20 ? 'bg-warning' : 'bg-danger') ?>">
                                                                            #<?= $position ?>
                                                                        </span>
                                                                    </td>
                                                                    <td>
                                                                        <i class="fas fa-arrow-up text-success"></i> +<?= rand(1, 5) ?>
                                                                    </td>
                                                                </tr>
                                                            <?php endforeach; ?>
                                                        </tbody>
                                                    </table>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                    
                                    <div class="col-lg-4">
                                        <div class="card">
                                            <div class="card-header">
                                                <h6 class="mb-0"><?= __('seo_recommendations') ?></h6>
                                            </div>
                                            <div class="card-body">
                                                <ul class="list-unstyled">
                                                    <?php foreach ($seo_report['recommendations'] as $recommendation): ?>
                                                        <li class="mb-2">
                                                            <i class="fas fa-lightbulb text-warning me-2"></i>
                                                            <?= htmlspecialchars($recommendation) ?>
                                                        </li>
                                                    <?php endforeach; ?>
                                                </ul>
                                            </div>
                                        </div>
                                        
                                        <div class="card mt-3">
                                            <div class="card-header">
                                                <h6 class="mb-0"><?= __('technical_issues') ?></h6>
                                            </div>
                                            <div class="card-body">
                                                <?php foreach ($seo_report['technical_issues'] as $issue => $count): ?>
                                                    <div class="d-flex justify-content-between align-items-center mb-2">
                                                        <span><?= __(str_replace('_', ' ', $issue)) ?></span>
                                                        <span class="badge <?= $count > 0 ? 'bg-danger' : 'bg-success' ?>">
                                                            <?= $count ?>
                                                        </span>
                                                    </div>
                                                <?php endforeach; ?>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            
                            <!-- SEO Settings Tab -->
                            <div class="tab-pane fade" id="settings" role="tabpanel">
                                <form method="POST" class="mt-4">
                                    <input type="hidden" name="csrf_token" value="<?= generateCSRFToken() ?>">
                                    <input type="hidden" name="action" value="update_seo_settings">
                                    
                                    <div class="row">
                                        <div class="col-lg-6">
                                            <h5><?= __('general_seo_settings') ?></h5>
                                            
                                            <div class="form-check mb-3">
                                                <input class="form-check-input" type="checkbox" id="seo_enabled" name="seo_enabled" 
                                                       <?= ($seo_settings['seo_enabled'] ?? '1') === '1' ? 'checked' : '' ?>>
                                                <label class="form-check-label" for="seo_enabled">
                                                    <?= __('enable_seo_features') ?>
                                                </label>
                                            </div>
                                            
                                            <div class="mb-3">
                                                <label for="google_analytics_id" class="form-label">
                                                    <?= __('google_analytics_id') ?>
                                                </label>
                                                <input type="text" class="form-control" id="google_analytics_id" name="google_analytics_id" 
                                                       value="<?= htmlspecialchars($seo_settings['google_analytics_id'] ?? '') ?>" 
                                                       placeholder="G-XXXXXXXXXX">
                                            </div>
                                            
                                            <div class="mb-3">
                                                <label for="google_api_key" class="form-label">
                                                    <?= __('google_api_key') ?>
                                                </label>
                                                <input type="password" class="form-control" id="google_api_key" name="google_api_key" 
                                                       value="<?= htmlspecialchars($seo_settings['google_api_key'] ?? '') ?>" 
                                                       placeholder="AIza...">
                                            </div>
                                            
                                            <div class="mb-3">
                                                <label for="search_console_property" class="form-label">
                                                    <?= __('search_console_property') ?>
                                                </label>
                                                <input type="url" class="form-control" id="search_console_property" name="search_console_property" 
                                                       value="<?= htmlspecialchars($seo_settings['search_console_property'] ?? '') ?>" 
                                                       placeholder="https://www.example.com/">
                                            </div>
                                        </div>
                                        
                                        <div class="col-lg-6">
                                            <h5><?= __('meta_templates') ?></h5>
                                            
                                            <div class="mb-3">
                                                <label for="meta_title_template" class="form-label">
                                                    <?= __('meta_title_template') ?>
                                                </label>
                                                <input type="text" class="form-control" id="meta_title_template" name="meta_title_template" 
                                                       value="<?= htmlspecialchars($seo_settings['meta_title_template'] ?? '{page_title} - {company_name}') ?>">
                                                <div class="form-text"><?= __('available_variables') ?>: {page_title}, {company_name}</div>
                                            </div>
                                            
                                            <div class="mb-3">
                                                <label for="meta_description_template" class="form-label">
                                                    <?= __('meta_description_template') ?>
                                                </label>
                                                <textarea class="form-control" id="meta_description_template" name="meta_description_template" rows="3"><?= htmlspecialchars($seo_settings['meta_description_template'] ?? '') ?></textarea>
                                            </div>
                                            
                                            <div class="mb-3">
                                                <label for="default_keywords" class="form-label">
                                                    <?= __('default_keywords') ?>
                                                </label>
                                                <input type="text" class="form-control" id="default_keywords" name="default_keywords" 
                                                       value="<?= htmlspecialchars($seo_settings['default_keywords'] ?? '') ?>" 
                                                       placeholder="personal loans, fast approval, competitive rates">
                                                <div class="form-text"><?= __('comma_separated_keywords') ?></div>
                                            </div>
                                            
                                            <div class="form-check mb-3">
                                                <input class="form-check-input" type="checkbox" id="auto_optimize_content" name="auto_optimize_content" 
                                                       <?= ($seo_settings['auto_optimize_content'] ?? '0') === '1' ? 'checked' : '' ?>>
                                                <label class="form-check-label" for="auto_optimize_content">
                                                    <?= __('auto_optimize_content') ?>
                                                </label>
                                            </div>
                                        </div>
                                    </div>
                                    
                                    <button type="submit" class="btn btn-primary">
                                        <i class="fas fa-save me-2"></i><?= __('save_seo_settings') ?>
                                    </button>
                                </form>
                            </div>
                            
                            <!-- Keyword Research Tab -->
                            <div class="tab-pane fade" id="keywords" role="tabpanel">
                                <div class="mt-4">
                                    <div class="row">
                                        <div class="col-lg-4">
                                            <h5><?= __('keyword_research') ?></h5>
                                            
                                            <form method="POST">
                                                <input type="hidden" name="csrf_token" value="<?= generateCSRFToken() ?>">
                                                <input type="hidden" name="action" value="perform_keyword_research">
                                                
                                                <div class="mb-3">
                                                    <label for="seed_keywords" class="form-label">
                                                        <?= __('seed_keywords') ?>
                                                    </label>
                                                    <textarea class="form-control" id="seed_keywords" name="seed_keywords" rows="4" 
                                                              placeholder="personal loans&#10;fast approval&#10;competitive rates"><?= htmlspecialchars($_POST['seed_keywords'] ?? '') ?></textarea>
                                                    <div class="form-text"><?= __('one_keyword_per_line') ?></div>
                                                </div>
                                                
                                                <button type="submit" class="btn btn-primary">
                                                    <i class="fas fa-search me-2"></i><?= __('research_keywords') ?>
                                                </button>
                                            </form>
                                        </div>
                                        
                                        <div class="col-lg-8">
                                            <?php if (isset($_SESSION['keyword_results'])): ?>
                                                <h5><?= __('keyword_research_results') ?></h5>
                                                
                                                <div class="table-responsive">
                                                    <table class="table">
                                                        <thead>
                                                            <tr>
                                                                <th><?= __('keyword') ?></th>
                                                                <th><?= __('search_volume') ?></th>
                                                                <th><?= __('competition') ?></th>
                                                                <th><?= __('suggested_bid') ?></th>
                                                            </tr>
                                                        </thead>
                                                        <tbody>
                                                            <?php foreach ($_SESSION['keyword_results'] as $result): ?>
                                                                <tr>
                                                                    <td><?= htmlspecialchars($result['keyword']) ?></td>
                                                                    <td><?= number_format($result['search_volume']) ?></td>
                                                                    <td>
                                                                        <div class="progress" style="height: 20px;">
                                                                            <div class="progress-bar <?= $result['competition'] > 0.7 ? 'bg-danger' : ($result['competition'] > 0.4 ? 'bg-warning' : 'bg-success') ?>" 
                                                                                 style="width: <?= $result['competition'] * 100 ?>%">
                                                                                <?= round($result['competition'] * 100) ?>%
                                                                            </div>
                                                                        </div>
                                                                    </td>
                                                                    <td>$<?= number_format($result['suggested_bid'], 2) ?></td>
                                                                </tr>
                                                            <?php endforeach; ?>
                                                        </tbody>
                                                    </table>
                                                </div>
                                                
                                                <?php unset($_SESSION['keyword_results']); ?>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            
                            <!-- Competitors Tab -->
                            <div class="tab-pane fade" id="competitors" role="tabpanel">
                                <div class="mt-4">
                                    <div class="row">
                                        <div class="col-lg-4">
                                            <h5><?= __('competitor_analysis') ?></h5>
                                            
                                            <form method="POST">
                                                <input type="hidden" name="csrf_token" value="<?= generateCSRFToken() ?>">
                                                <input type="hidden" name="action" value="analyze_competitors">
                                                
                                                <div class="mb-3">
                                                    <label for="competitor_domains" class="form-label">
                                                        <?= __('competitor_domains') ?>
                                                    </label>
                                                    <textarea class="form-control" id="competitor_domains" name="competitor_domains" rows="4" 
                                                              placeholder="competitor1.com&#10;competitor2.com"><?= htmlspecialchars($_POST['competitor_domains'] ?? '') ?></textarea>
                                                    <div class="form-text"><?= __('one_domain_per_line') ?></div>
                                                </div>
                                                
                                                <button type="submit" class="btn btn-primary">
                                                    <i class="fas fa-chart-bar me-2"></i><?= __('analyze_competitors') ?>
                                                </button>
                                            </form>
                                        </div>
                                        
                                        <div class="col-lg-8">
                                            <?php if (isset($_SESSION['competitor_analysis'])): ?>
                                                <h5><?= __('competitor_analysis_results') ?></h5>
                                                
                                                <div class="table-responsive">
                                                    <table class="table">
                                                        <thead>
                                                            <tr>
                                                                <th><?= __('domain') ?></th>
                                                                <th><?= __('estimated_traffic') ?></th>
                                                                <th><?= __('backlinks') ?></th>
                                                                <th><?= __('domain_authority') ?></th>
                                                                <th><?= __('top_keywords') ?></th>
                                                            </tr>
                                                        </thead>
                                                        <tbody>
                                                            <?php foreach ($_SESSION['competitor_analysis'] as $competitor): ?>
                                                                <tr>
                                                                    <td><?= htmlspecialchars($competitor['domain']) ?></td>
                                                                    <td><?= number_format($competitor['estimated_traffic']) ?></td>
                                                                    <td><?= number_format($competitor['backlinks']) ?></td>
                                                                    <td>
                                                                        <span class="badge <?= $competitor['domain_authority'] > 60 ? 'bg-success' : ($competitor['domain_authority'] > 40 ? 'bg-warning' : 'bg-danger') ?>">
                                                                            <?= $competitor['domain_authority'] ?>
                                                                        </span>
                                                                    </td>
                                                                    <td><?= implode(', ', $competitor['top_keywords']) ?></td>
                                                                </tr>
                                                            <?php endforeach; ?>
                                                        </tbody>
                                                    </table>
                                                </div>
                                                
                                                <?php unset($_SESSION['competitor_analysis']); ?>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            
                            <!-- Content Ideas Tab -->
                            <div class="tab-pane fade" id="content" role="tabpanel">
                                <div class="mt-4">
                                    <h5><?= __('content_suggestions') ?></h5>
                                    
                                    <div class="row">
                                        <?php foreach ($content_suggestions as $suggestion): ?>
                                            <div class="col-lg-6 mb-4">
                                                <div class="card">
                                                    <div class="card-body">
                                                        <h6 class="card-title"><?= htmlspecialchars($suggestion['title']) ?></h6>
                                                        <p class="card-text">
                                                            <span class="badge bg-primary"><?= htmlspecialchars($suggestion['type']) ?></span>
                                                            <span class="text-muted ms-2">Est. Traffic: <?= number_format($suggestion['estimated_traffic']) ?></span>
                                                        </p>
                                                        <p class="card-text">
                                                            <strong><?= __('target_keywords') ?>:</strong> 
                                                            <?= implode(', ', array_map('htmlspecialchars', $suggestion['keywords'])) ?>
                                                        </p>
                                                        <button class="btn btn-outline-primary btn-sm">
                                                            <i class="fas fa-plus me-1"></i><?= __('create_content') ?>
                                                        </button>
                                                    </div>
                                                </div>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                </div>
                            </div>
                            
                            <!-- SEO Tools Tab -->
                            <div class="tab-pane fade" id="tools" role="tabpanel">
                                <div class="mt-4">
                                    <h5><?= __('seo_tools') ?></h5>
                                    
                                    <div class="row">
                                        <div class="col-lg-6">
                                            <div class="card">
                                                <div class="card-header">
                                                    <h6 class="mb-0"><?= __('sitemap_management') ?></h6>
                                                </div>
                                                <div class="card-body">
                                                    <p><?= __('sitemap_description') ?></p>
                                                    <form method="POST" class="d-inline">
                                                        <input type="hidden" name="csrf_token" value="<?= generateCSRFToken() ?>">
                                                        <input type="hidden" name="action" value="generate_sitemap">
                                                        <button type="submit" class="btn btn-primary">
                                                            <i class="fas fa-sitemap me-2"></i><?= __('generate_sitemap') ?>
                                                        </button>
                                                    </form>
                                                    <a href="../sitemap.php" target="_blank" class="btn btn-outline-secondary ms-2">
                                                        <i class="fas fa-eye me-2"></i><?= __('view_sitemap') ?>
                                                    </a>
                                                </div>
                                            </div>
                                        </div>
                                        
                                        <div class="col-lg-6">
                                            <div class="card">
                                                <div class="card-header">
                                                    <h6 class="mb-0"><?= __('robots_txt_management') ?></h6>
                                                </div>
                                                <div class="card-body">
                                                    <p><?= __('robots_txt_description') ?></p>
                                                    <form method="POST" class="d-inline">
                                                        <input type="hidden" name="csrf_token" value="<?= generateCSRFToken() ?>">
                                                        <input type="hidden" name="action" value="generate_robots">
                                                        <button type="submit" class="btn btn-primary">
                                                            <i class="fas fa-robot me-2"></i><?= __('generate_robots_txt') ?>
                                                        </button>
                                                    </form>
                                                    <a href="../robots.php" target="_blank" class="btn btn-outline-secondary ms-2">
                                                        <i class="fas fa-eye me-2"></i><?= __('view_robots_txt') ?>
                                                    </a>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                    
                                    <div class="row mt-4">
                                        <div class="col-12">
                                            <div class="card">
                                                <div class="card-header">
                                                    <h6 class="mb-0"><?= __('backlink_opportunities') ?></h6>
                                                </div>
                                                <div class="card-body">
                                                    <p><?= __('backlink_opportunities_description') ?></p>
                                                    <button class="btn btn-primary">
                                                        <i class="fas fa-link me-2"></i><?= __('find_opportunities') ?>
                                                    </button>
                                                    <button class="btn btn-outline-primary ms-2">
                                                        <i class="fas fa-envelope me-2"></i><?= __('start_outreach') ?>
                                                    </button>
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

<?php

// Helper functions for SEO settings
function getSEOSettings() {
    return [
        'seo_enabled' => getSystemSetting('seo_enabled', '1'),
        'google_analytics_id' => getSystemSetting('google_analytics_id', ''),
        'google_api_key' => getSystemSetting('google_api_key', ''),
        'search_console_property' => getSystemSetting('search_console_property', ''),
        'meta_title_template' => getSystemSetting('meta_title_template', '{page_title} - {company_name}'),
        'meta_description_template' => getSystemSetting('meta_description_template', ''),
        'default_keywords' => getSystemSetting('default_keywords', ''),
        'auto_optimize_content' => getSystemSetting('auto_optimize_content', '0')
    ];
}

function updateSEOSettings($data) {
    try {
        foreach ($data as $key => $value) {
            updateSystemSetting($key, $value);
        }
        return true;
    } catch (Exception $e) {
        error_log("Update SEO settings error: " . $e->getMessage());
        return false;
    }
}
?>
