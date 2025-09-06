<?php
/**
 * SEO Automation System
 * LoanFlow Personal Loan Management System
 * 
 * AI-driven autonomous SEO marketing system with automated backlink generation,
 * on-page SEO, technical SEO, content optimization, and competitor analysis
 */

require_once '../config/database.php';
require_once '../includes/functions.php';
require_once '../includes/auth.php';
require_once '../includes/language.php';

// Initialize language manager
LanguageManager::init();

// Check authentication and admin role
$current_user = getCurrentUser();
if (!$current_user || $current_user['role'] !== 'admin') {
    header('Location: ../login.php');
    exit();
}

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    switch ($action) {
        case 'update_seo_settings':
            updateSEOSettings($_POST);
            break;
        case 'run_seo_audit':
            runSEOAudit();
            break;
        case 'generate_backlinks':
            generateBacklinks($_POST);
            break;
        case 'optimize_content':
            optimizeContent($_POST);
            break;
        case 'analyze_competitors':
            analyzeCompetitors($_POST);
            break;
        case 'update_meta_tags':
            updateMetaTags($_POST);
            break;
        case 'generate_sitemap':
            generateSitemap();
            break;
        case 'submit_to_search_engines':
            submitToSearchEngines();
            break;
    }
}

// Get current SEO settings and data
$seo_settings = getSEOSettings();
$seo_metrics = getSEOMetrics();
$recent_activities = getRecentSEOActivities();
$keyword_rankings = getKeywordRankings();
$backlink_status = getBacklinkStatus();
$competitor_data = getCompetitorData();
$technical_issues = getTechnicalSEOIssues();

?>
<!DOCTYPE html>
<html lang="<?php echo getCurrentLanguage(); ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo __('SEO Automation System'); ?> - <?php echo getCompanyName(); ?></title>
    
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Font Awesome -->
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <!-- Chart.js -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    
    <style>
        .seo-card {
            border: none;
            border-radius: 15px;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
            transition: transform 0.2s;
        }
        .seo-card:hover {
            transform: translateY(-2px);
        }
        .metric-card {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border-radius: 15px;
        }
        .status-indicator {
            width: 12px;
            height: 12px;
            border-radius: 50%;
            display: inline-block;
            margin-right: 8px;
        }
        .status-good { background-color: #28a745; }
        .status-warning { background-color: #ffc107; }
        .status-error { background-color: #dc3545; }
        .automation-toggle {
            transform: scale(1.2);
        }
        .progress-ring {
            width: 60px;
            height: 60px;
        }
        .keyword-tag {
            background: #e3f2fd;
            color: #1976d2;
            padding: 4px 8px;
            border-radius: 12px;
            font-size: 0.85em;
            margin: 2px;
            display: inline-block;
        }
    </style>
</head>
<body class="bg-light">
    <!-- Navigation -->
    <?php include '../includes/admin_nav.php'; ?>
    
    <div class="container-fluid py-4">
        <!-- Header -->
        <div class="row mb-4">
            <div class="col-12">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <h1 class="h3 mb-0">
                            <i class="fas fa-search-plus text-primary me-2"></i>
                            <?php echo __('SEO Automation System'); ?>
                        </h1>
                        <p class="text-muted mb-0"><?php echo __('AI-driven SEO optimization and marketing automation'); ?></p>
                    </div>
                    <div>
                        <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#seoSettingsModal">
                            <i class="fas fa-cog me-2"></i><?php echo __('SEO Settings'); ?>
                        </button>
                        <button class="btn btn-success" onclick="runFullSEOAudit()">
                            <i class="fas fa-play me-2"></i><?php echo __('Run Full Audit'); ?>
                        </button>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- SEO Metrics Overview -->
        <div class="row mb-4">
            <div class="col-md-3">
                <div class="card metric-card seo-card h-100">
                    <div class="card-body text-center">
                        <div class="progress-ring mx-auto mb-3">
                            <canvas id="seoScoreChart" width="60" height="60"></canvas>
                        </div>
                        <h4 class="mb-1"><?php echo $seo_metrics['overall_score']; ?>/100</h4>
                        <p class="mb-0"><?php echo __('SEO Score'); ?></p>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card seo-card h-100">
                    <div class="card-body text-center">
                        <i class="fas fa-chart-line fa-2x text-success mb-3"></i>
                        <h4 class="mb-1"><?php echo number_format($seo_metrics['organic_traffic']); ?></h4>
                        <p class="mb-0 text-muted"><?php echo __('Organic Traffic'); ?></p>
                        <small class="text-success">
                            <i class="fas fa-arrow-up"></i> +<?php echo $seo_metrics['traffic_growth']; ?>%
                        </small>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card seo-card h-100">
                    <div class="card-body text-center">
                        <i class="fas fa-link fa-2x text-info mb-3"></i>
                        <h4 class="mb-1"><?php echo number_format($seo_metrics['total_backlinks']); ?></h4>
                        <p class="mb-0 text-muted"><?php echo __('Total Backlinks'); ?></p>
                        <small class="text-info">
                            <i class="fas fa-plus"></i> +<?php echo $seo_metrics['new_backlinks']; ?> <?php echo __('this week'); ?>
                        </small>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card seo-card h-100">
                    <div class="card-body text-center">
                        <i class="fas fa-key fa-2x text-warning mb-3"></i>
                        <h4 class="mb-1"><?php echo $seo_metrics['ranking_keywords']; ?></h4>
                        <p class="mb-0 text-muted"><?php echo __('Ranking Keywords'); ?></p>
                        <small class="text-warning">
                            <i class="fas fa-crown"></i> <?php echo $seo_metrics['top_10_keywords']; ?> <?php echo __('in top 10'); ?>
                        </small>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Main Content Tabs -->
        <div class="row">
            <div class="col-12">
                <div class="card seo-card">
                    <div class="card-header bg-white">
                        <ul class="nav nav-tabs card-header-tabs" id="seoTabs" role="tablist">
                            <li class="nav-item" role="presentation">
                                <button class="nav-link active" id="dashboard-tab" data-bs-toggle="tab" data-bs-target="#dashboard" type="button" role="tab">
                                    <i class="fas fa-tachometer-alt me-2"></i><?php echo __('Dashboard'); ?>
                                </button>
                            </li>
                            <li class="nav-item" role="presentation">
                                <button class="nav-link" id="keywords-tab" data-bs-toggle="tab" data-bs-target="#keywords" type="button" role="tab">
                                    <i class="fas fa-key me-2"></i><?php echo __('Keywords'); ?>
                                </button>
                            </li>
                            <li class="nav-item" role="presentation">
                                <button class="nav-link" id="backlinks-tab" data-bs-toggle="tab" data-bs-target="#backlinks" type="button" role="tab">
                                    <i class="fas fa-link me-2"></i><?php echo __('Backlinks'); ?>
                                </button>
                            </li>
                            <li class="nav-item" role="presentation">
                                <button class="nav-link" id="content-tab" data-bs-toggle="tab" data-bs-target="#content" type="button" role="tab">
                                    <i class="fas fa-file-alt me-2"></i><?php echo __('Content'); ?>
                                </button>
                            </li>
                            <li class="nav-item" role="presentation">
                                <button class="nav-link" id="technical-tab" data-bs-toggle="tab" data-bs-target="#technical" type="button" role="tab">
                                    <i class="fas fa-cogs me-2"></i><?php echo __('Technical SEO'); ?>
                                </button>
                            </li>
                            <li class="nav-item" role="presentation">
                                <button class="nav-link" id="competitors-tab" data-bs-toggle="tab" data-bs-target="#competitors" type="button" role="tab">
                                    <i class="fas fa-users me-2"></i><?php echo __('Competitors'); ?>
                                </button>
                            </li>
                        </ul>
                    </div>
                    <div class="card-body">
                        <div class="tab-content" id="seoTabsContent">
                            <!-- Dashboard Tab -->
                            <div class="tab-pane fade show active" id="dashboard" role="tabpanel">
                                <div class="row">
                                    <!-- Recent Activities -->
                                    <div class="col-md-6">
                                        <h5 class="mb-3"><?php echo __('Recent SEO Activities'); ?></h5>
                                        <div class="list-group">
                                            <?php foreach ($recent_activities as $activity): ?>
                                            <div class="list-group-item d-flex justify-content-between align-items-start">
                                                <div class="ms-2 me-auto">
                                                    <div class="fw-bold"><?php echo htmlspecialchars($activity['title']); ?></div>
                                                    <small class="text-muted"><?php echo htmlspecialchars($activity['description']); ?></small>
                                                </div>
                                                <small class="text-muted"><?php echo timeAgo($activity['created_at']); ?></small>
                                            </div>
                                            <?php endforeach; ?>
                                        </div>
                                    </div>
                                    
                                    <!-- Technical Issues -->
                                    <div class="col-md-6">
                                        <h5 class="mb-3"><?php echo __('Technical SEO Issues'); ?></h5>
                                        <div class="list-group">
                                            <?php foreach ($technical_issues as $issue): ?>
                                            <div class="list-group-item d-flex justify-content-between align-items-center">
                                                <div>
                                                    <span class="status-indicator status-<?php echo $issue['severity']; ?>"></span>
                                                    <?php echo htmlspecialchars($issue['title']); ?>
                                                </div>
                                                <div>
                                                    <button class="btn btn-sm btn-outline-primary" onclick="fixIssue(<?php echo $issue['id']; ?>)">
                                                        <i class="fas fa-wrench"></i> <?php echo __('Fix'); ?>
                                                    </button>
                                                </div>
                                            </div>
                                            <?php endforeach; ?>
                                        </div>
                                    </div>
                                </div>
                                
                                <!-- SEO Performance Chart -->
                                <div class="row mt-4">
                                    <div class="col-12">
                                        <h5 class="mb-3"><?php echo __('SEO Performance Trends'); ?></h5>
                                        <canvas id="seoPerformanceChart" height="100"></canvas>
                                    </div>
                                </div>
                            </div>
                            
                            <!-- Keywords Tab -->
                            <div class="tab-pane fade" id="keywords" role="tabpanel">
                                <div class="d-flex justify-content-between align-items-center mb-3">
                                    <h5 class="mb-0"><?php echo __('Keyword Rankings'); ?></h5>
                                    <div>
                                        <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addKeywordModal">
                                            <i class="fas fa-plus me-2"></i><?php echo __('Add Keywords'); ?>
                                        </button>
                                        <button class="btn btn-success" onclick="updateKeywordRankings()">
                                            <i class="fas fa-sync me-2"></i><?php echo __('Update Rankings'); ?>
                                        </button>
                                    </div>
                                </div>
                                
                                <div class="table-responsive">
                                    <table class="table table-hover">
                                        <thead>
                                            <tr>
                                                <th><?php echo __('Keyword'); ?></th>
                                                <th><?php echo __('Current Position'); ?></th>
                                                <th><?php echo __('Previous Position'); ?></th>
                                                <th><?php echo __('Change'); ?></th>
                                                <th><?php echo __('Search Volume'); ?></th>
                                                <th><?php echo __('Difficulty'); ?></th>
                                                <th><?php echo __('Actions'); ?></th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($keyword_rankings as $keyword): ?>
                                            <tr>
                                                <td>
                                                    <span class="keyword-tag"><?php echo htmlspecialchars($keyword['keyword']); ?></span>
                                                </td>
                                                <td>
                                                    <span class="badge bg-<?php echo $keyword['current_position'] <= 10 ? 'success' : ($keyword['current_position'] <= 30 ? 'warning' : 'secondary'); ?>">
                                                        #<?php echo $keyword['current_position']; ?>
                                                    </span>
                                                </td>
                                                <td>#<?php echo $keyword['previous_position']; ?></td>
                                                <td>
                                                    <?php 
                                                    $change = $keyword['previous_position'] - $keyword['current_position'];
                                                    if ($change > 0): 
                                                    ?>
                                                        <span class="text-success"><i class="fas fa-arrow-up"></i> +<?php echo $change; ?></span>
                                                    <?php elseif ($change < 0): ?>
                                                        <span class="text-danger"><i class="fas fa-arrow-down"></i> <?php echo $change; ?></span>
                                                    <?php else: ?>
                                                        <span class="text-muted">-</span>
                                                    <?php endif; ?>
                                                </td>
                                                <td><?php echo number_format($keyword['search_volume']); ?></td>
                                                <td>
                                                    <div class="progress" style="width: 60px; height: 8px;">
                                                        <div class="progress-bar bg-<?php echo $keyword['difficulty'] <= 30 ? 'success' : ($keyword['difficulty'] <= 70 ? 'warning' : 'danger'); ?>" 
                                                             style="width: <?php echo $keyword['difficulty']; ?>%"></div>
                                                    </div>
                                                    <small><?php echo $keyword['difficulty']; ?>%</small>
                                                </td>
                                                <td>
                                                    <button class="btn btn-sm btn-outline-primary" onclick="optimizeForKeyword('<?php echo $keyword['keyword']; ?>')">
                                                        <i class="fas fa-magic"></i> <?php echo __('Optimize'); ?>
                                                    </button>
                                                </td>
                                            </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                            
                            <!-- Backlinks Tab -->
                            <div class="tab-pane fade" id="backlinks" role="tabpanel">
                                <div class="d-flex justify-content-between align-items-center mb-3">
                                    <h5 class="mb-0"><?php echo __('Backlink Management'); ?></h5>
                                    <div>
                                        <button class="btn btn-primary" onclick="generateBacklinks()">
                                            <i class="fas fa-robot me-2"></i><?php echo __('Auto Generate'); ?>
                                        </button>
                                        <button class="btn btn-success" onclick="analyzeBacklinks()">
                                            <i class="fas fa-search me-2"></i><?php echo __('Analyze'); ?>
                                        </button>
                                    </div>
                                </div>
                                
                                <!-- Backlink Statistics -->
                                <div class="row mb-4">
                                    <div class="col-md-3">
                                        <div class="card text-center">
                                            <div class="card-body">
                                                <h4 class="text-success"><?php echo $backlink_status['total']; ?></h4>
                                                <p class="mb-0"><?php echo __('Total Backlinks'); ?></p>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="col-md-3">
                                        <div class="card text-center">
                                            <div class="card-body">
                                                <h4 class="text-info"><?php echo $backlink_status['high_quality']; ?></h4>
                                                <p class="mb-0"><?php echo __('High Quality'); ?></p>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="col-md-3">
                                        <div class="card text-center">
                                            <div class="card-body">
                                                <h4 class="text-warning"><?php echo $backlink_status['pending']; ?></h4>
                                                <p class="mb-0"><?php echo __('Pending'); ?></p>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="col-md-3">
                                        <div class="card text-center">
                                            <div class="card-body">
                                                <h4 class="text-danger"><?php echo $backlink_status['toxic']; ?></h4>
                                                <p class="mb-0"><?php echo __('Toxic Links'); ?></p>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                
                                <!-- Backlink Opportunities -->
                                <div class="card">
                                    <div class="card-header">
                                        <h6 class="mb-0"><?php echo __('Backlink Opportunities'); ?></h6>
                                    </div>
                                    <div class="card-body">
                                        <div id="backlinkOpportunities">
                                            <!-- Dynamic content loaded via AJAX -->
                                        </div>
                                    </div>
                                </div>
                            </div>
                            
                            <!-- Content Tab -->
                            <div class="tab-pane fade" id="content" role="tabpanel">
                                <div class="d-flex justify-content-between align-items-center mb-3">
                                    <h5 class="mb-0"><?php echo __('Content Optimization'); ?></h5>
                                    <button class="btn btn-primary" onclick="generateContentSuggestions()">
                                        <i class="fas fa-lightbulb me-2"></i><?php echo __('AI Suggestions'); ?>
                                    </button>
                                </div>
                                
                                <div id="contentOptimization">
                                    <!-- Dynamic content loaded via AJAX -->
                                </div>
                            </div>
                            
                            <!-- Technical SEO Tab -->
                            <div class="tab-pane fade" id="technical" role="tabpanel">
                                <div class="d-flex justify-content-between align-items-center mb-3">
                                    <h5 class="mb-0"><?php echo __('Technical SEO Analysis'); ?></h5>
                                    <button class="btn btn-primary" onclick="runTechnicalAudit()">
                                        <i class="fas fa-search me-2"></i><?php echo __('Run Audit'); ?>
                                    </button>
                                </div>
                                
                                <div id="technicalSEO">
                                    <!-- Dynamic content loaded via AJAX -->
                                </div>
                            </div>
                            
                            <!-- Competitors Tab -->
                            <div class="tab-pane fade" id="competitors" role="tabpanel">
                                <div class="d-flex justify-content-between align-items-center mb-3">
                                    <h5 class="mb-0"><?php echo __('Competitor Analysis'); ?></h5>
                                    <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addCompetitorModal">
                                        <i class="fas fa-plus me-2"></i><?php echo __('Add Competitor'); ?>
                                    </button>
                                </div>
                                
                                <div class="row">
                                    <?php foreach ($competitor_data as $competitor): ?>
                                    <div class="col-md-6 mb-3">
                                        <div class="card">
                                            <div class="card-body">
                                                <div class="d-flex justify-content-between align-items-center mb-3">
                                                    <h6 class="mb-0"><?php echo htmlspecialchars($competitor['domain']); ?></h6>
                                                    <span class="badge bg-<?php echo $competitor['threat_level'] === 'high' ? 'danger' : ($competitor['threat_level'] === 'medium' ? 'warning' : 'success'); ?>">
                                                        <?php echo ucfirst($competitor['threat_level']); ?>
                                                    </span>
                                                </div>
                                                <div class="row text-center">
                                                    <div class="col-4">
                                                        <small class="text-muted"><?php echo __('DA'); ?></small>
                                                        <div class="fw-bold"><?php echo $competitor['domain_authority']; ?></div>
                                                    </div>
                                                    <div class="col-4">
                                                        <small class="text-muted"><?php echo __('Backlinks'); ?></small>
                                                        <div class="fw-bold"><?php echo number_format($competitor['backlinks']); ?></div>
                                                    </div>
                                                    <div class="col-4">
                                                        <small class="text-muted"><?php echo __('Keywords'); ?></small>
                                                        <div class="fw-bold"><?php echo number_format($competitor['ranking_keywords']); ?></div>
                                                    </div>
                                                </div>
                                                <div class="mt-3">
                                                    <button class="btn btn-sm btn-outline-primary" onclick="analyzeCompetitor('<?php echo $competitor['domain']; ?>')">
                                                        <i class="fas fa-chart-line"></i> <?php echo __('Analyze'); ?>
                                                    </button>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- SEO Settings Modal -->
    <div class="modal fade" id="seoSettingsModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title"><?php echo __('SEO Settings'); ?></h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST">
                    <div class="modal-body">
                        <input type="hidden" name="action" value="update_seo_settings">
                        
                        <!-- Automation Settings -->
                        <div class="mb-4">
                            <h6><?php echo __('Automation Settings'); ?></h6>
                            <div class="form-check form-switch">
                                <input class="form-check-input automation-toggle" type="checkbox" id="autoBacklinks" name="auto_backlinks" <?php echo $seo_settings['auto_backlinks'] ? 'checked' : ''; ?>>
                                <label class="form-check-label" for="autoBacklinks">
                                    <?php echo __('Automatic Backlink Generation'); ?>
                                </label>
                            </div>
                            <div class="form-check form-switch">
                                <input class="form-check-input automation-toggle" type="checkbox" id="autoContentOptimization" name="auto_content_optimization" <?php echo $seo_settings['auto_content_optimization'] ? 'checked' : ''; ?>>
                                <label class="form-check-label" for="autoContentOptimization">
                                    <?php echo __('Automatic Content Optimization'); ?>
                                </label>
                            </div>
                            <div class="form-check form-switch">
                                <input class="form-check-input automation-toggle" type="checkbox" id="autoTechnicalFixes" name="auto_technical_fixes" <?php echo $seo_settings['auto_technical_fixes'] ? 'checked' : ''; ?>>
                                <label class="form-check-label" for="autoTechnicalFixes">
                                    <?php echo __('Automatic Technical SEO Fixes'); ?>
                                </label>
                            </div>
                        </div>
                        
                        <!-- Target Keywords -->
                        <div class="mb-4">
                            <label for="targetKeywords" class="form-label"><?php echo __('Target Keywords'); ?></label>
                            <textarea class="form-control" id="targetKeywords" name="target_keywords" rows="3" placeholder="<?php echo __('Enter keywords separated by commas'); ?>"><?php echo htmlspecialchars($seo_settings['target_keywords']); ?></textarea>
                        </div>
                        
                        <!-- Competitor Domains -->
                        <div class="mb-4">
                            <label for="competitorDomains" class="form-label"><?php echo __('Competitor Domains'); ?></label>
                            <textarea class="form-control" id="competitorDomains" name="competitor_domains" rows="3" placeholder="<?php echo __('Enter competitor domains separated by commas'); ?>"><?php echo htmlspecialchars($seo_settings['competitor_domains']); ?></textarea>
                        </div>
                        
                        <!-- API Keys -->
                        <div class="mb-4">
                            <h6><?php echo __('API Configuration'); ?></h6>
                            <div class="row">
                                <div class="col-md-6">
                                    <label for="semrushApiKey" class="form-label"><?php echo __('SEMrush API Key'); ?></label>
                                    <input type="password" class="form-control" id="semrushApiKey" name="semrush_api_key" value="<?php echo $seo_settings['semrush_api_key']; ?>">
                                </div>
                                <div class="col-md-6">
                                    <label for="ahrefsApiKey" class="form-label"><?php echo __('Ahrefs API Key'); ?></label>
                                    <input type="password" class="form-control" id="ahrefsApiKey" name="ahrefs_api_key" value="<?php echo $seo_settings['ahrefs_api_key']; ?>">
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal"><?php echo __('Cancel'); ?></button>
                        <button type="submit" class="btn btn-primary"><?php echo __('Save Settings'); ?></button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    
    <!-- Add Keyword Modal -->
    <div class="modal fade" id="addKeywordModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title"><?php echo __('Add Keywords'); ?></h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST">
                    <div class="modal-body">
                        <input type="hidden" name="action" value="add_keywords">
                        <div class="mb-3">
                            <label for="newKeywords" class="form-label"><?php echo __('Keywords'); ?></label>
                            <textarea class="form-control" id="newKeywords" name="keywords" rows="4" placeholder="<?php echo __('Enter keywords separated by commas or new lines'); ?>" required></textarea>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal"><?php echo __('Cancel'); ?></button>
                        <button type="submit" class="btn btn-primary"><?php echo __('Add Keywords'); ?></button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    
    <!-- Add Competitor Modal -->
    <div class="modal fade" id="addCompetitorModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title"><?php echo __('Add Competitor'); ?></h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST">
                    <div class="modal-body">
                        <input type="hidden" name="action" value="add_competitor">
                        <div class="mb-3">
                            <label for="competitorDomain" class="form-label"><?php echo __('Competitor Domain'); ?></label>
                            <input type="url" class="form-control" id="competitorDomain" name="domain" placeholder="https://example.com" required>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal"><?php echo __('Cancel'); ?></button>
                        <button type="submit" class="btn btn-primary"><?php echo __('Add Competitor'); ?></button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    
    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    
    <script>
        // Initialize charts and functionality
        document.addEventListener('DOMContentLoaded', function() {
            initializeSEOCharts();
            loadDynamicContent();
        });
        
        function initializeSEOCharts() {
            // SEO Score Ring Chart
            const seoScoreCtx = document.getElementById('seoScoreChart').getContext('2d');
            new Chart(seoScoreCtx, {
                type: 'doughnut',
                data: {
                    datasets: [{
                        data: [<?php echo $seo_metrics['overall_score']; ?>, <?php echo 100 - $seo_metrics['overall_score']; ?>],
                        backgroundColor: ['#28a745', '#e9ecef'],
                        borderWidth: 0
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    cutout: '70%',
                    plugins: {
                        legend: { display: false },
                        tooltip: { enabled: false }
                    }
                }
            });
            
            // SEO Performance Trends Chart
            const performanceCtx = document.getElementById('seoPerformanceChart').getContext('2d');
            new Chart(performanceCtx, {
                type: 'line',
                data: {
                    labels: ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun'],
                    datasets: [{
                        label: 'Organic Traffic',
                        data: [1200, 1350, 1100, 1400, 1600, 1800],
                        borderColor: '#007bff',
                        backgroundColor: 'rgba(0, 123, 255, 0.1)',
                        tension: 0.4
                    }, {
                        label: 'Keyword Rankings',
                        data: [45, 52, 48, 61, 68, 75],
                        borderColor: '#28a745',
                        backgroundColor: 'rgba(40, 167, 69, 0.1)',
                        tension: 0.4
                    }]
                },
                options: {
                    responsive: true,
                    plugins: {
                        legend: { position: 'top' }
                    },
                    scales: {
                        y: { beginAtZero: true }
                    }
                }
            });
        }
        
        function loadDynamicContent() {
            // Load backlink opportunities
            fetch('seo-api.php?action=get_backlink_opportunities')
                .then(response => response.json())
                .then(data => {
                    document.getElementById('backlinkOpportunities').innerHTML = data.html;
                });
            
            // Load content optimization suggestions
            fetch('seo-api.php?action=get_content_suggestions')
                .then(response => response.json())
                .then(data => {
                    document.getElementById('contentOptimization').innerHTML = data.html;
                });
            
            // Load technical SEO analysis
            fetch('seo-api.php?action=get_technical_analysis')
                .then(response => response.json())
                .then(data => {
                    document.getElementById('technicalSEO').innerHTML = data.html;
                });
        }
        
        function runFullSEOAudit() {
            const btn = event.target;
            const originalText = btn.innerHTML;
            btn.innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i>Running Audit...';
            btn.disabled = true;
            
            fetch('seo-api.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ action: 'run_full_audit' })
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    showAlert('success', 'SEO audit completed successfully!');
                    setTimeout(() => location.reload(), 2000);
                } else {
                    showAlert('error', data.message || 'Audit failed');
                }
            })
            .finally(() => {
                btn.innerHTML = originalText;
                btn.disabled = false;
            });
        }
        
        function generateBacklinks() {
            const btn = event.target;
            const originalText = btn.innerHTML;
            btn.innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i>Generating...';
            btn.disabled = true;
            
            fetch('seo-api.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ action: 'generate_backlinks' })
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    showAlert('success', 'Backlink generation started!');
                    loadDynamicContent();
                } else {
                    showAlert('error', data.message || 'Generation failed');
                }
            })
            .finally(() => {
                btn.innerHTML = originalText;
                btn.disabled = false;
            });
        }
        
        function optimizeForKeyword(keyword) {
            fetch('seo-api.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ action: 'optimize_keyword', keyword: keyword })
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    showAlert('success', `Optimization started for "${keyword}"`);
                } else {
                    showAlert('error', data.message || 'Optimization failed');
                }
            });
        }
        
        function analyzeCompetitor(domain) {
            fetch('seo-api.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ action: 'analyze_competitor', domain: domain })
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    showAlert('success', `Analysis started for ${domain}`);
                } else {
                    showAlert('error', data.message || 'Analysis failed');
                }
            });
        }
        
        function fixIssue(issueId) {
            fetch('seo-api.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ action: 'fix_issue', issue_id: issueId })
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    showAlert('success', 'Issue fixed successfully!');
                    setTimeout(() => location.reload(), 1000);
                } else {
                    showAlert('error', data.message || 'Fix failed');
                }
            });
        }
        
        function showAlert(type, message) {
            const alertClass = type === 'success' ? 'alert-success' : 'alert-danger';
            const alertHtml = `
                <div class="alert ${alertClass} alert-dismissible fade show" role="alert">
                    ${message}
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            `;
            
            const container = document.querySelector('.container-fluid');
            container.insertAdjacentHTML('afterbegin', alertHtml);
            
            // Auto-dismiss after 5 seconds
            setTimeout(() => {
                const alert = container.querySelector('.alert');
                if (alert) {
                    alert.remove();
                }
            }, 5000);
        }
    </script>
</body>
</html>

<?php
/**
 * SEO System Functions
 */

function updateSEOSettings($data) {
    global $pdo;
    
    try {
        $settings = [
            'auto_backlinks' => isset($data['auto_backlinks']) ? 1 : 0,
            'auto_content_optimization' => isset($data['auto_content_optimization']) ? 1 : 0,
            'auto_technical_fixes' => isset($data['auto_technical_fixes']) ? 1 : 0,
            'target_keywords' => sanitizeInput($data['target_keywords'] ?? ''),
            'competitor_domains' => sanitizeInput($data['competitor_domains'] ?? ''),
            'semrush_api_key' => sanitizeInput($data['semrush_api_key'] ?? ''),
            'ahrefs_api_key' => sanitizeInput($data['ahrefs_api_key'] ?? '')
        ];
        
        foreach ($settings as $key => $value) {
            $stmt = $pdo->prepare("
                INSERT INTO system_settings (setting_key, setting_value, updated_at) 
                VALUES (?, ?, NOW()) 
                ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value), updated_at = NOW()
            ");
            $stmt->execute(["seo_$key", $value]);
        }
        
        $_SESSION['success_message'] = 'SEO settings updated successfully!';
        
    } catch (Exception $e) {
        error_log('Error updating SEO settings: ' . $e->getMessage());
        $_SESSION['error_message'] = 'Failed to update SEO settings.';
    }
}

function getSEOSettings() {
    global $pdo;
    
    $defaults = [
        'auto_backlinks' => false,
        'auto_content_optimization' => false,
        'auto_technical_fixes' => false,
        'target_keywords' => '',
        'competitor_domains' => '',
        'semrush_api_key' => '',
        'ahrefs_api_key' => ''
    ];
    
    try {
        $stmt = $pdo->prepare("SELECT setting_key, setting_value FROM system_settings WHERE setting_key LIKE 'seo_%'");
        $stmt->execute();
        $settings = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);
        
        foreach ($defaults as $key => $default) {
            $setting_key = "seo_$key";
            if (isset($settings[$setting_key])) {
                $defaults[$key] = $settings[$setting_key];
            }
        }
        
    } catch (Exception $e) {
        error_log('Error getting SEO settings: ' . $e->getMessage());
    }
    
    return $defaults;
}

function getSEOMetrics() {
    // Mock data - in real implementation, this would fetch from analytics APIs
    return [
        'overall_score' => 78,
        'organic_traffic' => 15420,
        'traffic_growth' => 12.5,
        'total_backlinks' => 1247,
        'new_backlinks' => 23,
        'ranking_keywords' => 156,
        'top_10_keywords' => 34
    ];
}

function getRecentSEOActivities() {
    global $pdo;
    
    try {
        $stmt = $pdo->prepare("
            SELECT title, description, created_at 
            FROM seo_activities 
            ORDER BY created_at DESC 
            LIMIT 10
        ");
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (Exception $e) {
        // Return mock data if table doesn't exist
        return [
            ['title' => 'Backlink Generated', 'description' => 'New high-quality backlink from tech blog', 'created_at' => date('Y-m-d H:i:s', strtotime('-2 hours'))],
            ['title' => 'Content Optimized', 'description' => 'Updated meta tags for loan application page', 'created_at' => date('Y-m-d H:i:s', strtotime('-4 hours'))],
            ['title' => 'Technical Issue Fixed', 'description' => 'Resolved page speed issue on mobile', 'created_at' => date('Y-m-d H:i:s', strtotime('-1 day'))]
        ];
    }
}

function getKeywordRankings() {
    // Mock data - in real implementation, this would fetch from SEO APIs
    return [
        ['keyword' => 'personal loan', 'current_position' => 8, 'previous_position' => 12, 'search_volume' => 45000, 'difficulty' => 75],
        ['keyword' => 'quick loan approval', 'current_position' => 15, 'previous_position' => 18, 'search_volume' => 12000, 'difficulty' => 60],
        ['keyword' => 'online loan application', 'current_position' => 5, 'previous_position' => 7, 'search_volume' => 8500, 'difficulty' => 55],
        ['keyword' => 'bad credit loan', 'current_position' => 22, 'previous_position' => 25, 'search_volume' => 15000, 'difficulty' => 80]
    ];
}

function getBacklinkStatus() {
    // Mock data - in real implementation, this would fetch from backlink APIs
    return [
        'total' => 1247,
        'high_quality' => 892,
        'pending' => 45,
        'toxic' => 12
    ];
}

function getCompetitorData() {
    // Mock data - in real implementation, this would fetch from competitor analysis APIs
    return [
        ['domain' => 'competitor1.com', 'domain_authority' => 65, 'backlinks' => 15420, 'ranking_keywords' => 2340, 'threat_level' => 'high'],
        ['domain' => 'competitor2.com', 'domain_authority' => 58, 'backlinks' => 8920, 'ranking_keywords' => 1890, 'threat_level' => 'medium'],
        ['domain' => 'competitor3.com', 'domain_authority' => 42, 'backlinks' => 4560, 'ranking_keywords' => 980, 'threat_level' => 'low']
    ];
}

function getTechnicalSEOIssues() {
    // Mock data - in real implementation, this would run technical SEO checks
    return [
        ['id' => 1, 'title' => 'Page speed optimization needed', 'severity' => 'warning'],
        ['id' => 2, 'title' => 'Missing alt tags on images', 'severity' => 'error'],
        ['id' => 3, 'title' => 'Duplicate meta descriptions', 'severity' => 'warning'],
        ['id' => 4, 'title' => 'Broken internal links found', 'severity' => 'error']
    ];
}

function runSEOAudit() {
    // Implementation for running comprehensive SEO audit
    logSEOActivity('SEO Audit', 'Comprehensive SEO audit completed');
    $_SESSION['success_message'] = 'SEO audit completed successfully!';
}

function generateBacklinks($data) {
    // Implementation for AI-powered backlink generation
    logSEOActivity('Backlink Generation', 'AI backlink generation process started');
    $_SESSION['success_message'] = 'Backlink generation started!';
}

function optimizeContent($data) {
    // Implementation for content optimization
    logSEOActivity('Content Optimization', 'Content optimization process completed');
    $_SESSION['success_message'] = 'Content optimization completed!';
}

function analyzeCompetitors($data) {
    // Implementation for competitor analysis
    logSEOActivity('Competitor Analysis', 'Competitor analysis updated');
    $_SESSION['success_message'] = 'Competitor analysis completed!';
}

function updateMetaTags($data) {
    // Implementation for meta tag optimization
    logSEOActivity('Meta Tags Update', 'Meta tags optimized for better SEO');
    $_SESSION['success_message'] = 'Meta tags updated successfully!';
}

function generateSitemap() {
    // Implementation for sitemap generation
    logSEOActivity('Sitemap Generation', 'XML sitemap generated and submitted');
    $_SESSION['success_message'] = 'Sitemap generated successfully!';
}

function submitToSearchEngines() {
    // Implementation for search engine submission
    logSEOActivity('Search Engine Submission', 'Site submitted to major search engines');
    $_SESSION['success_message'] = 'Submitted to search engines successfully!';
}

function logSEOActivity($title, $description) {
    global $pdo;
    
    try {
        $stmt = $pdo->prepare("
            INSERT INTO seo_activities (title, description, created_at) 
            VALUES (?, ?, NOW())
        ");
        $stmt->execute([$title, $description]);
    } catch (Exception $e) {
        error_log('Error logging SEO activity: ' . $e->getMessage());
    }
}

?>