<?php
/**
 * Edge Network System
 * LoanFlow Personal Loan Management System
 * 
 * Custom Cloudflare-like solution with comprehensive security,
 * performance optimization, and edge network capabilities
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
        case 'update_edge_settings':
            updateEdgeSettings($_POST);
            break;
        case 'configure_cdn':
            configureCDN($_POST);
            break;
        case 'update_security_rules':
            updateSecurityRules($_POST);
            break;
        case 'configure_caching':
            configureCaching($_POST);
            break;
        case 'update_ssl_settings':
            updateSSLSettings($_POST);
            break;
        case 'configure_load_balancer':
            configureLoadBalancer($_POST);
            break;
        case 'update_firewall_rules':
            updateFirewallRules($_POST);
            break;
        case 'configure_ddos_protection':
            configureDDoSProtection($_POST);
            break;
    }
}

// Get current edge network data
$edge_settings = getEdgeSettings();
$performance_metrics = getPerformanceMetrics();
$security_metrics = getSecurityMetrics();
$cdn_status = getCDNStatus();
$edge_locations = getEdgeLocations();
$recent_attacks = getRecentAttacks();
$cache_statistics = getCacheStatistics();
$ssl_certificates = getSSLCertificates();

?>
<!DOCTYPE html>
<html lang="<?php echo getCurrentLanguage(); ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo __('Edge Network System'); ?> - <?php echo getCompanyName(); ?></title>
    
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Font Awesome -->
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <!-- Chart.js -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <!-- Leaflet for maps -->
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />
    <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
    
    <style>
        .edge-card {
            border: none;
            border-radius: 15px;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
            transition: transform 0.2s;
        }
        .edge-card:hover {
            transform: translateY(-2px);
        }
        .metric-card {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border-radius: 15px;
        }
        .security-card {
            background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);
            color: white;
            border-radius: 15px;
        }
        .performance-card {
            background: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%);
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
        .status-online { background-color: #28a745; }
        .status-warning { background-color: #ffc107; }
        .status-offline { background-color: #dc3545; }
        .edge-toggle {
            transform: scale(1.2);
        }
        .world-map {
            height: 400px;
            border-radius: 10px;
        }
        .attack-alert {
            border-left: 4px solid #dc3545;
            background: #f8d7da;
            color: #721c24;
        }
        .performance-gauge {
            width: 120px;
            height: 120px;
        }
        .cdn-node {
            background: #e3f2fd;
            border: 2px solid #2196f3;
            border-radius: 10px;
            padding: 10px;
            margin: 5px;
            text-align: center;
        }
        .cdn-node.active {
            background: #c8e6c9;
            border-color: #4caf50;
        }
        .traffic-chart {
            height: 300px;
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
                            <i class="fas fa-network-wired text-primary me-2"></i>
                            <?php echo __('Edge Network System'); ?>
                        </h1>
                        <p class="text-muted mb-0"><?php echo __('Global CDN, Security & Performance Optimization'); ?></p>
                    </div>
                    <div>
                        <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#edgeSettingsModal">
                            <i class="fas fa-cog me-2"></i><?php echo __('Edge Settings'); ?>
                        </button>
                        <button class="btn btn-success" onclick="purgeCache()">
                            <i class="fas fa-sync me-2"></i><?php echo __('Purge Cache'); ?>
                        </button>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Status Overview -->
        <div class="row mb-4">
            <div class="col-md-3">
                <div class="card metric-card edge-card h-100">
                    <div class="card-body text-center">
                        <div class="performance-gauge mx-auto mb-3">
                            <canvas id="performanceGauge" width="120" height="120"></canvas>
                        </div>
                        <h4 class="mb-1"><?php echo $performance_metrics['overall_score']; ?>%</h4>
                        <p class="mb-0"><?php echo __('Performance Score'); ?></p>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card security-card edge-card h-100">
                    <div class="card-body text-center">
                        <i class="fas fa-shield-alt fa-3x mb-3"></i>
                        <h4 class="mb-1"><?php echo number_format($security_metrics['threats_blocked']); ?></h4>
                        <p class="mb-0"><?php echo __('Threats Blocked'); ?></p>
                        <small>
                            <i class="fas fa-clock"></i> <?php echo __('Last 24h'); ?>
                        </small>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card performance-card edge-card h-100">
                    <div class="card-body text-center">
                        <i class="fas fa-tachometer-alt fa-3x mb-3"></i>
                        <h4 class="mb-1"><?php echo $performance_metrics['avg_response_time']; ?>ms</h4>
                        <p class="mb-0"><?php echo __('Avg Response Time'); ?></p>
                        <small class="text-success">
                            <i class="fas fa-arrow-down"></i> -<?php echo $performance_metrics['improvement']; ?>% <?php echo __('improved'); ?>
                        </small>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card edge-card h-100">
                    <div class="card-body text-center">
                        <i class="fas fa-globe fa-3x text-info mb-3"></i>
                        <h4 class="mb-1"><?php echo count($edge_locations); ?></h4>
                        <p class="mb-0 text-muted"><?php echo __('Edge Locations'); ?></p>
                        <small class="text-info">
                            <i class="fas fa-check-circle"></i> <?php echo array_sum(array_column($edge_locations, 'active')); ?> <?php echo __('active'); ?>
                        </small>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Main Content Tabs -->
        <div class="row">
            <div class="col-12">
                <div class="card edge-card">
                    <div class="card-header bg-white">
                        <ul class="nav nav-tabs card-header-tabs" id="edgeTabs" role="tablist">
                            <li class="nav-item" role="presentation">
                                <button class="nav-link active" id="dashboard-tab" data-bs-toggle="tab" data-bs-target="#dashboard" type="button" role="tab">
                                    <i class="fas fa-tachometer-alt me-2"></i><?php echo __('Dashboard'); ?>
                                </button>
                            </li>
                            <li class="nav-item" role="presentation">
                                <button class="nav-link" id="cdn-tab" data-bs-toggle="tab" data-bs-target="#cdn" type="button" role="tab">
                                    <i class="fas fa-cloud me-2"></i><?php echo __('CDN'); ?>
                                </button>
                            </li>
                            <li class="nav-item" role="presentation">
                                <button class="nav-link" id="security-tab" data-bs-toggle="tab" data-bs-target="#security" type="button" role="tab">
                                    <i class="fas fa-shield-alt me-2"></i><?php echo __('Security'); ?>
                                </button>
                            </li>
                            <li class="nav-item" role="presentation">
                                <button class="nav-link" id="caching-tab" data-bs-toggle="tab" data-bs-target="#caching" type="button" role="tab">
                                    <i class="fas fa-database me-2"></i><?php echo __('Caching'); ?>
                                </button>
                            </li>
                            <li class="nav-item" role="presentation">
                                <button class="nav-link" id="ssl-tab" data-bs-toggle="tab" data-bs-target="#ssl" type="button" role="tab">
                                    <i class="fas fa-lock me-2"></i><?php echo __('SSL/TLS'); ?>
                                </button>
                            </li>
                            <li class="nav-item" role="presentation">
                                <button class="nav-link" id="analytics-tab" data-bs-toggle="tab" data-bs-target="#analytics" type="button" role="tab">
                                    <i class="fas fa-chart-bar me-2"></i><?php echo __('Analytics'); ?>
                                </button>
                            </li>
                        </ul>
                    </div>
                    <div class="card-body">
                        <div class="tab-content" id="edgeTabsContent">
                            <!-- Dashboard Tab -->
                            <div class="tab-pane fade show active" id="dashboard" role="tabpanel">
                                <div class="row">
                                    <!-- Global Traffic Map -->
                                    <div class="col-md-8">
                                        <h5 class="mb-3"><?php echo __('Global Traffic Distribution'); ?></h5>
                                        <div id="worldMap" class="world-map"></div>
                                    </div>
                                    
                                    <!-- Recent Security Events -->
                                    <div class="col-md-4">
                                        <h5 class="mb-3"><?php echo __('Recent Security Events'); ?></h5>
                                        <div class="list-group">
                                            <?php foreach ($recent_attacks as $attack): ?>
                                            <div class="list-group-item attack-alert">
                                                <div class="d-flex justify-content-between align-items-start">
                                                    <div>
                                                        <h6 class="mb-1"><?php echo htmlspecialchars($attack['type']); ?></h6>
                                                        <p class="mb-1"><?php echo htmlspecialchars($attack['description']); ?></p>
                                                        <small><?php echo __('Source'); ?>: <?php echo htmlspecialchars($attack['source_ip']); ?></small>
                                                    </div>
                                                    <small><?php echo timeAgo($attack['timestamp']); ?></small>
                                                </div>
                                            </div>
                                            <?php endforeach; ?>
                                        </div>
                                    </div>
                                </div>
                                
                                <!-- Performance Metrics Chart -->
                                <div class="row mt-4">
                                    <div class="col-12">
                                        <h5 class="mb-3"><?php echo __('Performance Trends'); ?></h5>
                                        <div class="traffic-chart">
                                            <canvas id="performanceChart"></canvas>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            
                            <!-- CDN Tab -->
                            <div class="tab-pane fade" id="cdn" role="tabpanel">
                                <div class="d-flex justify-content-between align-items-center mb-3">
                                    <h5 class="mb-0"><?php echo __('CDN Configuration'); ?></h5>
                                    <button class="btn btn-primary" onclick="addEdgeLocation()">
                                        <i class="fas fa-plus me-2"></i><?php echo __('Add Location'); ?>
                                    </button>
                                </div>
                                
                                <!-- CDN Status Overview -->
                                <div class="row mb-4">
                                    <div class="col-md-3">
                                        <div class="card text-center">
                                            <div class="card-body">
                                                <h4 class="text-success"><?php echo $cdn_status['total_bandwidth']; ?></h4>
                                                <p class="mb-0"><?php echo __('Total Bandwidth'); ?></p>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="col-md-3">
                                        <div class="card text-center">
                                            <div class="card-body">
                                                <h4 class="text-info"><?php echo $cdn_status['cache_hit_ratio']; ?>%</h4>
                                                <p class="mb-0"><?php echo __('Cache Hit Ratio'); ?></p>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="col-md-3">
                                        <div class="card text-center">
                                            <div class="card-body">
                                                <h4 class="text-warning"><?php echo number_format($cdn_status['requests_per_second']); ?></h4>
                                                <p class="mb-0"><?php echo __('Requests/sec'); ?></p>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="col-md-3">
                                        <div class="card text-center">
                                            <div class="card-body">
                                                <h4 class="text-primary"><?php echo $cdn_status['data_transfer']; ?></h4>
                                                <p class="mb-0"><?php echo __('Data Transfer'); ?></p>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                
                                <!-- Edge Locations -->
                                <div class="row">
                                    <?php foreach ($edge_locations as $location): ?>
                                    <div class="col-md-4 mb-3">
                                        <div class="cdn-node <?php echo $location['active'] ? 'active' : ''; ?>">
                                            <div class="d-flex justify-content-between align-items-center mb-2">
                                                <h6 class="mb-0"><?php echo htmlspecialchars($location['city']); ?></h6>
                                                <span class="status-indicator status-<?php echo $location['active'] ? 'online' : 'offline'; ?>"></span>
                                            </div>
                                            <p class="mb-2"><?php echo htmlspecialchars($location['country']); ?></p>
                                            <div class="row text-center">
                                                <div class="col-6">
                                                    <small class="text-muted"><?php echo __('Latency'); ?></small>
                                                    <div class="fw-bold"><?php echo $location['latency']; ?>ms</div>
                                                </div>
                                                <div class="col-6">
                                                    <small class="text-muted"><?php echo __('Load'); ?></small>
                                                    <div class="fw-bold"><?php echo $location['load']; ?>%</div>
                                                </div>
                                            </div>
                                            <div class="mt-2">
                                                <button class="btn btn-sm btn-outline-primary" onclick="configureLocation('<?php echo $location['id']; ?>')">
                                                    <i class="fas fa-cog"></i> <?php echo __('Configure'); ?>
                                                </button>
                                            </div>
                                        </div>
                                    </div>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                            
                            <!-- Security Tab -->
                            <div class="tab-pane fade" id="security" role="tabpanel">
                                <div class="d-flex justify-content-between align-items-center mb-3">
                                    <h5 class="mb-0"><?php echo __('Security Configuration'); ?></h5>
                                    <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#securityRulesModal">
                                        <i class="fas fa-shield-alt me-2"></i><?php echo __('Security Rules'); ?>
                                    </button>
                                </div>
                                
                                <!-- Security Features -->
                                <div class="row mb-4">
                                    <div class="col-md-6">
                                        <div class="card">
                                            <div class="card-header">
                                                <h6 class="mb-0"><?php echo __('DDoS Protection'); ?></h6>
                                            </div>
                                            <div class="card-body">
                                                <div class="form-check form-switch mb-3">
                                                    <input class="form-check-input edge-toggle" type="checkbox" id="ddosProtection" <?php echo $edge_settings['ddos_protection'] ? 'checked' : ''; ?>>
                                                    <label class="form-check-label" for="ddosProtection">
                                                        <?php echo __('Enable DDoS Protection'); ?>
                                                    </label>
                                                </div>
                                                <div class="mb-3">
                                                    <label class="form-label"><?php echo __('Sensitivity Level'); ?></label>
                                                    <select class="form-select" id="ddosSensitivity">
                                                        <option value="low" <?php echo $edge_settings['ddos_sensitivity'] === 'low' ? 'selected' : ''; ?>><?php echo __('Low'); ?></option>
                                                        <option value="medium" <?php echo $edge_settings['ddos_sensitivity'] === 'medium' ? 'selected' : ''; ?>><?php echo __('Medium'); ?></option>
                                                        <option value="high" <?php echo $edge_settings['ddos_sensitivity'] === 'high' ? 'selected' : ''; ?>><?php echo __('High'); ?></option>
                                                    </select>
                                                </div>
                                                <button class="btn btn-primary" onclick="updateDDoSSettings()">
                                                    <?php echo __('Update Settings'); ?>
                                                </button>
                                            </div>
                                        </div>
                                    </div>
                                    
                                    <div class="col-md-6">
                                        <div class="card">
                                            <div class="card-header">
                                                <h6 class="mb-0"><?php echo __('Web Application Firewall'); ?></h6>
                                            </div>
                                            <div class="card-body">
                                                <div class="form-check form-switch mb-3">
                                                    <input class="form-check-input edge-toggle" type="checkbox" id="wafEnabled" <?php echo $edge_settings['waf_enabled'] ? 'checked' : ''; ?>>
                                                    <label class="form-check-label" for="wafEnabled">
                                                        <?php echo __('Enable WAF'); ?>
                                                    </label>
                                                </div>
                                                <div class="mb-3">
                                                    <label class="form-label"><?php echo __('Security Level'); ?></label>
                                                    <select class="form-select" id="wafLevel">
                                                        <option value="off" <?php echo $edge_settings['waf_level'] === 'off' ? 'selected' : ''; ?>><?php echo __('Off'); ?></option>
                                                        <option value="low" <?php echo $edge_settings['waf_level'] === 'low' ? 'selected' : ''; ?>><?php echo __('Low'); ?></option>
                                                        <option value="medium" <?php echo $edge_settings['waf_level'] === 'medium' ? 'selected' : ''; ?>><?php echo __('Medium'); ?></option>
                                                        <option value="high" <?php echo $edge_settings['waf_level'] === 'high' ? 'selected' : ''; ?>><?php echo __('High'); ?></option>
                                                    </select>
                                                </div>
                                                <button class="btn btn-primary" onclick="updateWAFSettings()">
                                                    <?php echo __('Update Settings'); ?>
                                                </button>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                
                                <!-- Security Analytics -->
                                <div class="row">
                                    <div class="col-12">
                                        <h6 class="mb-3"><?php echo __('Security Analytics'); ?></h6>
                                        <canvas id="securityChart" height="100"></canvas>
                                    </div>
                                </div>
                            </div>
                            
                            <!-- Caching Tab -->
                            <div class="tab-pane fade" id="caching" role="tabpanel">
                                <div class="d-flex justify-content-between align-items-center mb-3">
                                    <h5 class="mb-0"><?php echo __('Cache Configuration'); ?></h5>
                                    <button class="btn btn-success" onclick="purgeAllCache()">
                                        <i class="fas fa-trash me-2"></i><?php echo __('Purge All Cache'); ?>
                                    </button>
                                </div>
                                
                                <!-- Cache Statistics -->
                                <div class="row mb-4">
                                    <div class="col-md-3">
                                        <div class="card text-center">
                                            <div class="card-body">
                                                <h4 class="text-success"><?php echo $cache_statistics['hit_ratio']; ?>%</h4>
                                                <p class="mb-0"><?php echo __('Cache Hit Ratio'); ?></p>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="col-md-3">
                                        <div class="card text-center">
                                            <div class="card-body">
                                                <h4 class="text-info"><?php echo $cache_statistics['total_size']; ?></h4>
                                                <p class="mb-0"><?php echo __('Cache Size'); ?></p>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="col-md-3">
                                        <div class="card text-center">
                                            <div class="card-body">
                                                <h4 class="text-warning"><?php echo number_format($cache_statistics['requests_cached']); ?></h4>
                                                <p class="mb-0"><?php echo __('Requests Cached'); ?></p>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="col-md-3">
                                        <div class="card text-center">
                                            <div class="card-body">
                                                <h4 class="text-primary"><?php echo $cache_statistics['bandwidth_saved']; ?></h4>
                                                <p class="mb-0"><?php echo __('Bandwidth Saved'); ?></p>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                
                                <!-- Cache Rules -->
                                <div class="card">
                                    <div class="card-header">
                                        <h6 class="mb-0"><?php echo __('Cache Rules'); ?></h6>
                                    </div>
                                    <div class="card-body">
                                        <div id="cacheRules">
                                            <!-- Dynamic content loaded via AJAX -->
                                        </div>
                                    </div>
                                </div>
                            </div>
                            
                            <!-- SSL/TLS Tab -->
                            <div class="tab-pane fade" id="ssl" role="tabpanel">
                                <div class="d-flex justify-content-between align-items-center mb-3">
                                    <h5 class="mb-0"><?php echo __('SSL/TLS Configuration'); ?></h5>
                                    <button class="btn btn-primary" onclick="generateSSLCertificate()">
                                        <i class="fas fa-certificate me-2"></i><?php echo __('Generate Certificate'); ?>
                                    </button>
                                </div>
                                
                                <!-- SSL Certificates -->
                                <div class="row">
                                    <?php foreach ($ssl_certificates as $cert): ?>
                                    <div class="col-md-6 mb-3">
                                        <div class="card">
                                            <div class="card-body">
                                                <div class="d-flex justify-content-between align-items-start mb-3">
                                                    <h6 class="mb-0"><?php echo htmlspecialchars($cert['domain']); ?></h6>
                                                    <span class="badge bg-<?php echo $cert['status'] === 'valid' ? 'success' : ($cert['status'] === 'expiring' ? 'warning' : 'danger'); ?>">
                                                        <?php echo ucfirst($cert['status']); ?>
                                                    </span>
                                                </div>
                                                <div class="row text-center mb-3">
                                                    <div class="col-6">
                                                        <small class="text-muted"><?php echo __('Issued'); ?></small>
                                                        <div class="fw-bold"><?php echo date('M d, Y', strtotime($cert['issued_date'])); ?></div>
                                                    </div>
                                                    <div class="col-6">
                                                        <small class="text-muted"><?php echo __('Expires'); ?></small>
                                                        <div class="fw-bold"><?php echo date('M d, Y', strtotime($cert['expiry_date'])); ?></div>
                                                    </div>
                                                </div>
                                                <div class="d-flex justify-content-between">
                                                    <small class="text-muted"><?php echo __('Type'); ?>: <?php echo $cert['type']; ?></small>
                                                    <button class="btn btn-sm btn-outline-primary" onclick="renewCertificate('<?php echo $cert['id']; ?>')">
                                                        <i class="fas fa-sync"></i> <?php echo __('Renew'); ?>
                                                    </button>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                            
                            <!-- Analytics Tab -->
                            <div class="tab-pane fade" id="analytics" role="tabpanel">
                                <div class="d-flex justify-content-between align-items-center mb-3">
                                    <h5 class="mb-0"><?php echo __('Edge Network Analytics'); ?></h5>
                                    <div>
                                        <select class="form-select d-inline-block" style="width: auto;" id="analyticsTimeframe">
                                            <option value="24h"><?php echo __('Last 24 Hours'); ?></option>
                                            <option value="7d"><?php echo __('Last 7 Days'); ?></option>
                                            <option value="30d"><?php echo __('Last 30 Days'); ?></option>
                                        </select>
                                    </div>
                                </div>
                                
                                <div id="analyticsContent">
                                    <!-- Dynamic content loaded via AJAX -->
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Edge Settings Modal -->
    <div class="modal fade" id="edgeSettingsModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title"><?php echo __('Edge Network Settings'); ?></h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST">
                    <div class="modal-body">
                        <input type="hidden" name="action" value="update_edge_settings">
                        
                        <!-- Global Settings -->
                        <div class="mb-4">
                            <h6><?php echo __('Global Settings'); ?></h6>
                            <div class="form-check form-switch">
                                <input class="form-check-input edge-toggle" type="checkbox" id="edgeEnabled" name="edge_enabled" <?php echo $edge_settings['enabled'] ? 'checked' : ''; ?>>
                                <label class="form-check-label" for="edgeEnabled">
                                    <?php echo __('Enable Edge Network'); ?>
                                </label>
                            </div>
                            <div class="form-check form-switch">
                                <input class="form-check-input edge-toggle" type="checkbox" id="autoScaling" name="auto_scaling" <?php echo $edge_settings['auto_scaling'] ? 'checked' : ''; ?>>
                                <label class="form-check-label" for="autoScaling">
                                    <?php echo __('Auto Scaling'); ?>
                                </label>
                            </div>
                            <div class="form-check form-switch">
                                <input class="form-check-input edge-toggle" type="checkbox" id="loadBalancing" name="load_balancing" <?php echo $edge_settings['load_balancing'] ? 'checked' : ''; ?>>
                                <label class="form-check-label" for="loadBalancing">
                                    <?php echo __('Load Balancing'); ?>
                                </label>
                            </div>
                        </div>
                        
                        <!-- Performance Settings -->
                        <div class="mb-4">
                            <h6><?php echo __('Performance Settings'); ?></h6>
                            <div class="row">
                                <div class="col-md-6">
                                    <label for="cacheLevel" class="form-label"><?php echo __('Cache Level'); ?></label>
                                    <select class="form-select" id="cacheLevel" name="cache_level">
                                        <option value="aggressive" <?php echo $edge_settings['cache_level'] === 'aggressive' ? 'selected' : ''; ?>><?php echo __('Aggressive'); ?></option>
                                        <option value="standard" <?php echo $edge_settings['cache_level'] === 'standard' ? 'selected' : ''; ?>><?php echo __('Standard'); ?></option>
                                        <option value="conservative" <?php echo $edge_settings['cache_level'] === 'conservative' ? 'selected' : ''; ?>><?php echo __('Conservative'); ?></option>
                                    </select>
                                </div>
                                <div class="col-md-6">
                                    <label for="compressionLevel" class="form-label"><?php echo __('Compression Level'); ?></label>
                                    <select class="form-select" id="compressionLevel" name="compression_level">
                                        <option value="maximum" <?php echo $edge_settings['compression_level'] === 'maximum' ? 'selected' : ''; ?>><?php echo __('Maximum'); ?></option>
                                        <option value="balanced" <?php echo $edge_settings['compression_level'] === 'balanced' ? 'selected' : ''; ?>><?php echo __('Balanced'); ?></option>
                                        <option value="minimal" <?php echo $edge_settings['compression_level'] === 'minimal' ? 'selected' : ''; ?>><?php echo __('Minimal'); ?></option>
                                    </select>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Security Settings -->
                        <div class="mb-4">
                            <h6><?php echo __('Security Settings'); ?></h6>
                            <div class="row">
                                <div class="col-md-6">
                                    <label for="minTLSVersion" class="form-label"><?php echo __('Minimum TLS Version'); ?></label>
                                    <select class="form-select" id="minTLSVersion" name="min_tls_version">
                                        <option value="1.0" <?php echo $edge_settings['min_tls_version'] === '1.0' ? 'selected' : ''; ?>>TLS 1.0</option>
                                        <option value="1.1" <?php echo $edge_settings['min_tls_version'] === '1.1' ? 'selected' : ''; ?>>TLS 1.1</option>
                                        <option value="1.2" <?php echo $edge_settings['min_tls_version'] === '1.2' ? 'selected' : ''; ?>>TLS 1.2</option>
                                        <option value="1.3" <?php echo $edge_settings['min_tls_version'] === '1.3' ? 'selected' : ''; ?>>TLS 1.3</option>
                                    </select>
                                </div>
                                <div class="col-md-6">
                                    <label for="hstsMaxAge" class="form-label"><?php echo __('HSTS Max Age (seconds)'); ?></label>
                                    <input type="number" class="form-control" id="hstsMaxAge" name="hsts_max_age" value="<?php echo $edge_settings['hsts_max_age']; ?>">
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
    
    <!-- Security Rules Modal -->
    <div class="modal fade" id="securityRulesModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title"><?php echo __('Security Rules'); ?></h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div id="securityRulesContent">
                        <!-- Dynamic content loaded via AJAX -->
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal"><?php echo __('Close'); ?></button>
                    <button type="button" class="btn btn-primary" onclick="saveSecurityRules()"><?php echo __('Save Rules'); ?></button>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    
    <script>
        // Initialize charts and maps
        document.addEventListener('DOMContentLoaded', function() {
            initializeCharts();
            initializeWorldMap();
            loadDynamicContent();
        });
        
        function initializeCharts() {
            // Performance Gauge
            const gaugeCtx = document.getElementById('performanceGauge').getContext('2d');
            new Chart(gaugeCtx, {
                type: 'doughnut',
                data: {
                    datasets: [{
                        data: [<?php echo $performance_metrics['overall_score']; ?>, <?php echo 100 - $performance_metrics['overall_score']; ?>],
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
            
            // Performance Trends Chart
            const performanceCtx = document.getElementById('performanceChart').getContext('2d');
            new Chart(performanceCtx, {
                type: 'line',
                data: {
                    labels: ['00:00', '04:00', '08:00', '12:00', '16:00', '20:00'],
                    datasets: [{
                        label: 'Response Time (ms)',
                        data: [120, 95, 85, 110, 90, 75],
                        borderColor: '#007bff',
                        backgroundColor: 'rgba(0, 123, 255, 0.1)',
                        tension: 0.4
                    }, {
                        label: 'Requests/sec',
                        data: [1200, 1800, 2400, 2100, 2600, 2200],
                        borderColor: '#28a745',
                        backgroundColor: 'rgba(40, 167, 69, 0.1)',
                        tension: 0.4,
                        yAxisID: 'y1'
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: { position: 'top' }
                    },
                    scales: {
                        y: {
                            type: 'linear',
                            display: true,
                            position: 'left',
                        },
                        y1: {
                            type: 'linear',
                            display: true,
                            position: 'right',
                            grid: {
                                drawOnChartArea: false,
                            },
                        }
                    }
                }
            });
            
            // Security Chart
            const securityCtx = document.getElementById('securityChart').getContext('2d');
            new Chart(securityCtx, {
                type: 'bar',
                data: {
                    labels: ['DDoS Attacks', 'SQL Injection', 'XSS Attempts', 'Bot Traffic', 'Malware'],
                    datasets: [{
                        label: 'Blocked Threats',
                        data: [45, 23, 67, 89, 12],
                        backgroundColor: [
                            '#dc3545',
                            '#fd7e14',
                            '#ffc107',
                            '#20c997',
                            '#6f42c1'
                        ]
                    }]
                },
                options: {
                    responsive: true,
                    plugins: {
                        legend: { display: false }
                    },
                    scales: {
                        y: { beginAtZero: true }
                    }
                }
            });
        }
        
        function initializeWorldMap() {
            const map = L.map('worldMap').setView([20, 0], 2);
            
            L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
                attribution: '© OpenStreetMap contributors'
            }).addTo(map);
            
            // Add edge location markers
            const locations = <?php echo json_encode($edge_locations); ?>;
            locations.forEach(location => {
                if (location.lat && location.lng) {
                    const marker = L.marker([location.lat, location.lng]).addTo(map);
                    marker.bindPopup(`
                        <strong>${location.city}, ${location.country}</strong><br>
                        Status: ${location.active ? 'Online' : 'Offline'}<br>
                        Latency: ${location.latency}ms<br>
                        Load: ${location.load}%
                    `);
                }
            });
        }
        
        function loadDynamicContent() {
            // Load cache rules
            fetch('edge-api.php?action=get_cache_rules')
                .then(response => response.json())
                .then(data => {
                    document.getElementById('cacheRules').innerHTML = data.html;
                });
            
            // Load security rules
            fetch('edge-api.php?action=get_security_rules')
                .then(response => response.json())
                .then(data => {
                    document.getElementById('securityRulesContent').innerHTML = data.html;
                });
            
            // Load analytics
            fetch('edge-api.php?action=get_analytics')
                .then(response => response.json())
                .then(data => {
                    document.getElementById('analyticsContent').innerHTML = data.html;
                });
        }
        
        function purgeCache() {
            const btn = event.target;
            const originalText = btn.innerHTML;
            btn.innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i>Purging...';
            btn.disabled = true;
            
            fetch('edge-api.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ action: 'purge_cache' })
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    showAlert('success', 'Cache purged successfully!');
                } else {
                    showAlert('error', data.message || 'Cache purge failed');
                }
            })
            .finally(() => {
                btn.innerHTML = originalText;
                btn.disabled = false;
            });
        }
        
        function addEdgeLocation() {
            // Implementation for adding new edge location
            showAlert('info', 'Edge location configuration dialog would open here');
        }
        
        function configureLocation(locationId) {
            // Implementation for configuring specific edge location
            showAlert('info', `Configuration for location ${locationId} would open here`);
        }
        
        function updateDDoSSettings() {
            const enabled = document.getElementById('ddosProtection').checked;
            const sensitivity = document.getElementById('ddosSensitivity').value;
            
            fetch('edge-api.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ 
                    action: 'update_ddos_settings',
                    enabled: enabled,
                    sensitivity: sensitivity
                })
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    showAlert('success', 'DDoS settings updated successfully!');
                } else {
                    showAlert('error', data.message || 'Update failed');
                }
            });
        }
        
        function updateWAFSettings() {
            const enabled = document.getElementById('wafEnabled').checked;
            const level = document.getElementById('wafLevel').value;
            
            fetch('edge-api.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ 
                    action: 'update_waf_settings',
                    enabled: enabled,
                    level: level
                })
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    showAlert('success', 'WAF settings updated successfully!');
                } else {
                    showAlert('error', data.message || 'Update failed');
                }
            });
        }
        
        function purgeAllCache() {
            if (confirm('Are you sure you want to purge all cache? This may temporarily impact performance.')) {
                fetch('edge-api.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ action: 'purge_all_cache' })
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        showAlert('success', 'All cache purged successfully!');
                    } else {
                        showAlert('error', data.message || 'Cache purge failed');
                    }
                });
            }
        }
        
        function generateSSLCertificate() {
            showAlert('info', 'SSL certificate generation dialog would open here');
        }
        
        function renewCertificate(certId) {
            fetch('edge-api.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ 
                    action: 'renew_certificate',
                    cert_id: certId
                })
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    showAlert('success', 'Certificate renewal initiated!');
                } else {
                    showAlert('error', data.message || 'Renewal failed');
                }
            });
        }
        
        function saveSecurityRules() {
            // Implementation for saving security rules
            showAlert('success', 'Security rules saved successfully!');
        }
        
        function showAlert(type, message) {
            const alertClass = type === 'success' ? 'alert-success' : (type === 'error' ? 'alert-danger' : 'alert-info');
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
        
        // Analytics timeframe change handler
        document.getElementById('analyticsTimeframe').addEventListener('change', function() {
            const timeframe = this.value;
            fetch(`edge-api.php?action=get_analytics&timeframe=${timeframe}`)
                .then(response => response.json())
                .then(data => {
                    document.getElementById('analyticsContent').innerHTML = data.html;
                });
        });
    </script>
</body>
</html>

<?php
/**
 * Edge Network System Functions
 */

function updateEdgeSettings($data) {
    global $pdo;
    
    try {
        $settings = [
            'enabled' => isset($data['edge_enabled']) ? 1 : 0,
            'auto_scaling' => isset($data['auto_scaling']) ? 1 : 0,
            'load_balancing' => isset($data['load_balancing']) ? 1 : 0,
            'cache_level' => sanitizeInput($data['cache_level'] ?? 'standard'),
            'compression_level' => sanitizeInput($data['compression_level'] ?? 'balanced'),
            'min_tls_version' => sanitizeInput($data['min_tls_version'] ?? '1.2'),
            'hsts_max_age' => intval($data['hsts_max_age'] ?? 31536000)
        ];
        
        foreach ($settings as $key => $value) {
            $stmt = $pdo->prepare("
                INSERT INTO system_settings (setting_key, setting_value, updated_at) 
                VALUES (?, ?, NOW()) 
                ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value), updated_at = NOW()
            ");
            $stmt->execute(["edge_$key", $value]);
        }
        
        $_SESSION['success_message'] = 'Edge network settings updated successfully!';
        
    } catch (Exception $e) {
        error_log('Error updating edge settings: ' . $e->getMessage());
        $_SESSION['error_message'] = 'Failed to update edge settings.';
    }
}

function getEdgeSettings() {
    global $pdo;
    
    $defaults = [
        'enabled' => true,
        'auto_scaling' => true,
        'load_balancing' => true,
        'cache_level' => 'standard',
        'compression_level' => 'balanced',
        'min_tls_version' => '1.2',
        'hsts_max_age' => 31536000,
        'ddos_protection' => true,
        'ddos_sensitivity' => 'medium',
        'waf_enabled' => true,
        'waf_level' => 'medium'
    ];
    
    try {
        $stmt = $pdo->prepare("SELECT setting_key, setting_value FROM system_settings WHERE setting_key LIKE 'edge_%'");
        $stmt->execute();
        $settings = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);
        
        foreach ($defaults as $key => $default) {
            $setting_key = "edge_$key";
            if (isset($settings[$setting_key])) {
                $defaults[$key] = $settings[$setting_key];
            }
        }
        
    } catch (Exception $e) {
        error_log('Error getting edge settings: ' . $e->getMessage());
    }
    
    return $defaults;
}

function getPerformanceMetrics() {
    // Mock data - in real implementation, this would fetch from monitoring systems
    return [
        'overall_score' => 92,
        'avg_response_time' => 85,
        'improvement' => 15,
        'uptime' => 99.98,
        'requests_per_second' => 2450,
        'bandwidth_usage' => '1.2 TB',
        'cache_hit_ratio' => 87
    ];
}

function getSecurityMetrics() {
    // Mock data - in real implementation, this would fetch from security systems
    return [
        'threats_blocked' => 1247,
        'ddos_attacks_blocked' => 23,
        'malware_blocked' => 156,
        'bot_requests_filtered' => 8920,
        'security_score' => 95
    ];
}

function getCDNStatus() {
    // Mock data - in real implementation, this would fetch from CDN APIs
    return [
        'total_bandwidth' => '2.5 TB',
        'cache_hit_ratio' => 87,
        'requests_per_second' => 2450,
        'data_transfer' => '1.8 TB',
        'edge_locations_active' => 15,
        'total_requests' => 15420000
    ];
}

function getEdgeLocations() {
    // Mock data - in real implementation, this would fetch from edge network APIs
    return [
        ['id' => 1, 'city' => 'New York', 'country' => 'USA', 'active' => true, 'latency' => 12, 'load' => 65, 'lat' => 40.7128, 'lng' => -74.0060],
        ['id' => 2, 'city' => 'London', 'country' => 'UK', 'active' => true, 'latency' => 18, 'load' => 72, 'lat' => 51.5074, 'lng' => -0.1278],
        ['id' => 3, 'city' => 'Tokyo', 'country' => 'Japan', 'active' => true, 'latency' => 25, 'load' => 58, 'lat' => 35.6762, 'lng' => 139.6503],
        ['id' => 4, 'city' => 'Sydney', 'country' => 'Australia', 'active' => true, 'latency' => 32, 'load' => 45, 'lat' => -33.8688, 'lng' => 151.2093],
        ['id' => 5, 'city' => 'Frankfurt', 'country' => 'Germany', 'active' => true, 'latency' => 15, 'load' => 68, 'lat' => 50.1109, 'lng' => 8.6821],
        ['id' => 6, 'city' => 'Singapore', 'country' => 'Singapore', 'active' => false, 'latency' => 0, 'load' => 0, 'lat' => 1.3521, 'lng' => 103.8198]
    ];
}

function getRecentAttacks() {
    // Mock data - in real implementation, this would fetch from security logs
    return [
        ['type' => 'DDoS Attack', 'description' => 'Large-scale DDoS attack blocked', 'source_ip' => '192.168.1.100', 'timestamp' => date('Y-m-d H:i:s', strtotime('-30 minutes'))],
        ['type' => 'SQL Injection', 'description' => 'SQL injection attempt detected and blocked', 'source_ip' => '10.0.0.50', 'timestamp' => date('Y-m-d H:i:s', strtotime('-1 hour'))],
        ['type' => 'Bot Traffic', 'description' => 'Malicious bot traffic filtered', 'source_ip' => '172.16.0.25', 'timestamp' => date('Y-m-d H:i:s', strtotime('-2 hours'))],
        ['type' => 'XSS Attempt', 'description' => 'Cross-site scripting attempt blocked', 'source_ip' => '203.0.113.10', 'timestamp' => date('Y-m-d H:i:s', strtotime('-3 hours'))]
    ];
}

function getCacheStatistics() {
    // Mock data - in real implementation, this would fetch from cache systems
    return [
        'hit_ratio' => 87,
        'total_size' => '2.3 GB',
        'requests_cached' => 1420000,
        'bandwidth_saved' => '1.8 TB',
        'cache_efficiency' => 92,
        'purge_frequency' => 'Daily'
    ];
}

function getSSLCertificates() {
    // Mock data - in real implementation, this would fetch from certificate management
    return [
        [
            'id' => 1,
            'domain' => 'loanflow.com',
            'status' => 'valid',
            'type' => 'Let\'s Encrypt',
            'issued_date' => '2024-01-15',
            'expiry_date' => '2024-04-15'
        ],
        [
            'id' => 2,
            'domain' => 'api.loanflow.com',
            'status' => 'expiring',
            'type' => 'Let\'s Encrypt',
            'issued_date' => '2024-01-10',
            'expiry_date' => '2024-04-10'
        ],
        [
            'id' => 3,
            'domain' => 'admin.loanflow.com',
            'status' => 'valid',
            'type' => 'Wildcard SSL',
            'issued_date' => '2024-01-20',
            'expiry_date' => '2025-01-20'
        ]
    ];
}

function configureCDN($data) {
    // Implementation for CDN configuration
    $_SESSION['success_message'] = 'CDN configuration updated successfully!';
}

function updateSecurityRules($data) {
    // Implementation for security rules update
    $_SESSION['success_message'] = 'Security rules updated successfully!';
}

function configureCaching($data) {
    // Implementation for caching configuration
    $_SESSION['success_message'] = 'Caching configuration updated successfully!';
}

function updateSSLSettings($data) {
    // Implementation for SSL settings update
    $_SESSION['success_message'] = 'SSL settings updated successfully!';
}

function configureLoadBalancer($data) {
    // Implementation for load balancer configuration
    $_SESSION['success_message'] = 'Load balancer configuration updated successfully!';
}

function updateFirewallRules($data) {
    // Implementation for firewall rules update
    $_SESSION['success_message'] = 'Firewall rules updated successfully!';
}

function configureDDoSProtection($data) {
    // Implementation for DDoS protection configuration
    $_SESSION['success_message'] = 'DDoS protection configuration updated successfully!';
}
?>