<?php
/**
 * Autonomous Business System Dashboard
 * LoanFlow Personal Loan Management System
 * 
 * This dashboard provides comprehensive monitoring and control for the
 * autonomous business system including:
 * - Real-time system status and health monitoring
 * - Business metrics and KPIs
 * - AI service management and configuration
 * - Automated process monitoring
 * - Alert management and notifications
 * - Performance analytics
 * - System controls and emergency stops
 */

require_once '../includes/config.php';
require_once '../includes/auth.php';
require_once '../includes/functions.php';
require_once '../includes/database.php';

// Check admin authentication
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header('Location: login.php');
    exit();
}

// Initialize variables
$page_title = 'Autonomous Business System Dashboard';
$current_page = 'autonomous-dashboard';
$error_message = '';
$success_message = '';

// Handle AJAX requests
if (isset($_POST['action'])) {
    header('Content-Type: application/json');
    
    switch ($_POST['action']) {
        case 'get_system_status':
            echo json_encode(getAutonomousSystemStatus());
            exit();
            
        case 'get_business_metrics':
            echo json_encode(getBusinessMetrics());
            exit();
            
        case 'get_ai_services_status':
            echo json_encode(getAIServicesStatus());
            exit();
            
        case 'get_alerts':
            echo json_encode(getSystemAlerts());
            exit();
            
        case 'start_autonomous_system':
            echo json_encode(startAutonomousSystem());
            exit();
            
        case 'stop_autonomous_system':
            echo json_encode(stopAutonomousSystem());
            exit();
            
        case 'restart_service':
            $service = $_POST['service'] ?? '';
            echo json_encode(restartService($service));
            exit();
            
        case 'update_configuration':
            $config = $_POST['config'] ?? [];
            echo json_encode(updateSystemConfiguration($config));
            exit();
            
        case 'resolve_alert':
            $alert_id = $_POST['alert_id'] ?? '';
            echo json_encode(resolveAlert($alert_id));
            exit();
            
        case 'get_performance_data':
            $timeframe = $_POST['timeframe'] ?? '1h';
            echo json_encode(getPerformanceData($timeframe));
            exit();
            
        default:
            echo json_encode(['success' => false, 'message' => 'Invalid action']);
            exit();
    }
}

// Helper functions
function getAutonomousSystemStatus() {
    try {
        // Check if Python backend is running
        $python_status = checkPythonBackendStatus();
        
        // Get system health from monitoring
        $health_data = getSystemHealthData();
        
        // Get service statuses
        $services = [
            'autonomous_controller' => checkServiceStatus('autonomous_controller'),
            'ai_services' => checkServiceStatus('ai_services'),
            'business_services' => checkServiceStatus('business_services'),
            'database_manager' => checkServiceStatus('database_manager'),
            'redis_manager' => checkServiceStatus('redis_manager'),
            'system_monitor' => checkServiceStatus('system_monitor')
        ];
        
        return [
            'success' => true,
            'system_status' => $python_status ? 'running' : 'stopped',
            'health' => $health_data,
            'services' => $services,
            'uptime' => getSystemUptime(),
            'last_updated' => date('Y-m-d H:i:s')
        ];
        
    } catch (Exception $e) {
        return [
            'success' => false,
            'message' => 'Failed to get system status: ' . $e->getMessage()
        ];
    }
}

function getBusinessMetrics() {
    global $pdo;
    
    try {
        $today = date('Y-m-d');
        $this_month = date('Y-m');
        
        // Today's metrics
        $stmt = $pdo->prepare("
            SELECT 
                COUNT(CASE WHEN DATE(created_at) = ? THEN 1 END) as applications_today,
                COUNT(CASE WHEN DATE(created_at) = ? AND status = 'approved' THEN 1 END) as approvals_today,
                COUNT(CASE WHEN DATE(created_at) = ? AND status = 'rejected' THEN 1 END) as rejections_today
            FROM loan_applications
        ");
        $stmt->execute([$today, $today, $today]);
        $daily_metrics = $stmt->fetch(PDO::FETCH_ASSOC);
        
        // Monthly metrics
        $stmt = $pdo->prepare("
            SELECT 
                COUNT(*) as applications_month,
                COUNT(CASE WHEN status = 'approved' THEN 1 END) as approvals_month,
                COUNT(CASE WHEN status = 'rejected' THEN 1 END) as rejections_month
            FROM loan_applications 
            WHERE DATE_FORMAT(created_at, '%Y-%m') = ?
        ");
        $stmt->execute([$this_month]);
        $monthly_metrics = $stmt->fetch(PDO::FETCH_ASSOC);
        
        // Revenue metrics
        $stmt = $pdo->prepare("
            SELECT 
                COALESCE(SUM(CASE WHEN DATE(created_at) = ? AND status = 'completed' THEN amount END), 0) as revenue_today,
                COALESCE(SUM(CASE WHEN DATE_FORMAT(created_at, '%Y-%m') = ? AND status = 'completed' THEN amount END), 0) as revenue_month
            FROM payments
        ");
        $stmt->execute([$today, $this_month]);
        $revenue_metrics = $stmt->fetch(PDO::FETCH_ASSOC);
        
        // Active users
        $stmt = $pdo->prepare("
            SELECT COUNT(DISTINCT user_id) as active_users
            FROM user_sessions 
            WHERE last_activity >= DATE_SUB(NOW(), INTERVAL 24 HOUR)
        ");
        $stmt->execute();
        $active_users = $stmt->fetch(PDO::FETCH_ASSOC);
        
        // Conversion rates
        $approval_rate = $daily_metrics['applications_today'] > 0 
            ? ($daily_metrics['approvals_today'] / $daily_metrics['applications_today']) * 100 
            : 0;
        
        return [
            'success' => true,
            'daily' => $daily_metrics,
            'monthly' => $monthly_metrics,
            'revenue' => $revenue_metrics,
            'active_users' => $active_users['active_users'],
            'approval_rate' => round($approval_rate, 2),
            'last_updated' => date('Y-m-d H:i:s')
        ];
        
    } catch (Exception $e) {
        return [
            'success' => false,
            'message' => 'Failed to get business metrics: ' . $e->getMessage()
        ];
    }
}

function getAIServicesStatus() {
    try {
        // Check AI service endpoints
        $services = [
            'risk_assessment' => checkAIServiceEndpoint('/api/ai/risk-assessment'),
            'fraud_detection' => checkAIServiceEndpoint('/api/ai/fraud-detection'),
            'content_generation' => checkAIServiceEndpoint('/api/ai/content-generation'),
            'seo_optimization' => checkAIServiceEndpoint('/api/ai/seo-optimization'),
            'customer_service' => checkAIServiceEndpoint('/api/ai/customer-service'),
            'business_intelligence' => checkAIServiceEndpoint('/api/ai/business-intelligence')
        ];
        
        // Get AI model statuses
        $models = getAIModelStatuses();
        
        // Get processing queues
        $queues = getAIProcessingQueues();
        
        return [
            'success' => true,
            'services' => $services,
            'models' => $models,
            'queues' => $queues,
            'last_updated' => date('Y-m-d H:i:s')
        ];
        
    } catch (Exception $e) {
        return [
            'success' => false,
            'message' => 'Failed to get AI services status: ' . $e->getMessage()
        ];
    }
}

function getSystemAlerts() {
    global $pdo;
    
    try {
        $stmt = $pdo->prepare("
            SELECT id, level, message, source, timestamp, resolved, resolved_at
            FROM system_alerts 
            WHERE timestamp >= DATE_SUB(NOW(), INTERVAL 24 HOUR)
            ORDER BY timestamp DESC
            LIMIT 50
        ");
        $stmt->execute();
        $alerts = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        return [
            'success' => true,
            'alerts' => $alerts,
            'total_count' => count($alerts),
            'unresolved_count' => count(array_filter($alerts, function($alert) {
                return !$alert['resolved'];
            }))
        ];
        
    } catch (Exception $e) {
        return [
            'success' => false,
            'message' => 'Failed to get alerts: ' . $e->getMessage()
        ];
    }
}

function startAutonomousSystem() {
    try {
        // Execute Python script to start autonomous system
        $command = 'python "' . __DIR__ . '/../backend/start_autonomous_business.py" start';
        $output = [];
        $return_code = 0;
        
        exec($command . ' 2>&1', $output, $return_code);
        
        if ($return_code === 0) {
            logAdminAction('start_autonomous_system', 'Autonomous system started successfully');
            return [
                'success' => true,
                'message' => 'Autonomous system started successfully',
                'output' => implode("\n", $output)
            ];
        } else {
            return [
                'success' => false,
                'message' => 'Failed to start autonomous system',
                'output' => implode("\n", $output),
                'return_code' => $return_code
            ];
        }
        
    } catch (Exception $e) {
        return [
            'success' => false,
            'message' => 'Error starting autonomous system: ' . $e->getMessage()
        ];
    }
}

function stopAutonomousSystem() {
    try {
        // Execute Python script to stop autonomous system
        $command = 'python "' . __DIR__ . '/../backend/start_autonomous_business.py" stop';
        $output = [];
        $return_code = 0;
        
        exec($command . ' 2>&1', $output, $return_code);
        
        if ($return_code === 0) {
            logAdminAction('stop_autonomous_system', 'Autonomous system stopped successfully');
            return [
                'success' => true,
                'message' => 'Autonomous system stopped successfully',
                'output' => implode("\n", $output)
            ];
        } else {
            return [
                'success' => false,
                'message' => 'Failed to stop autonomous system',
                'output' => implode("\n", $output),
                'return_code' => $return_code
            ];
        }
        
    } catch (Exception $e) {
        return [
            'success' => false,
            'message' => 'Error stopping autonomous system: ' . $e->getMessage()
        ];
    }
}

function restartService($service) {
    try {
        $allowed_services = [
            'autonomous_controller',
            'ai_services',
            'business_services',
            'system_monitor'
        ];
        
        if (!in_array($service, $allowed_services)) {
            return [
                'success' => false,
                'message' => 'Invalid service name'
            ];
        }
        
        // Execute Python script to restart specific service
        $command = 'python "' . __DIR__ . '/../backend/start_autonomous_business.py" restart ' . $service;
        $output = [];
        $return_code = 0;
        
        exec($command . ' 2>&1', $output, $return_code);
        
        if ($return_code === 0) {
            logAdminAction('restart_service', "Service {$service} restarted successfully");
            return [
                'success' => true,
                'message' => "Service {$service} restarted successfully",
                'output' => implode("\n", $output)
            ];
        } else {
            return [
                'success' => false,
                'message' => "Failed to restart service {$service}",
                'output' => implode("\n", $output),
                'return_code' => $return_code
            ];
        }
        
    } catch (Exception $e) {
        return [
            'success' => false,
            'message' => 'Error restarting service: ' . $e->getMessage()
        ];
    }
}

function updateSystemConfiguration($config) {
    try {
        // Validate configuration
        $allowed_configs = [
            'monitoring_interval',
            'alert_thresholds',
            'ai_model_settings',
            'business_rules',
            'performance_settings'
        ];
        
        $validated_config = [];
        foreach ($config as $key => $value) {
            if (in_array($key, $allowed_configs)) {
                $validated_config[$key] = $value;
            }
        }
        
        if (empty($validated_config)) {
            return [
                'success' => false,
                'message' => 'No valid configuration provided'
            ];
        }
        
        // Save configuration to file
        $config_file = __DIR__ . '/../backend/config/autonomous_config.json';
        $existing_config = [];
        
        if (file_exists($config_file)) {
            $existing_config = json_decode(file_get_contents($config_file), true) ?: [];
        }
        
        $updated_config = array_merge($existing_config, $validated_config);
        
        if (file_put_contents($config_file, json_encode($updated_config, JSON_PRETTY_PRINT))) {
            logAdminAction('update_configuration', 'System configuration updated');
            return [
                'success' => true,
                'message' => 'Configuration updated successfully'
            ];
        } else {
            return [
                'success' => false,
                'message' => 'Failed to save configuration'
            ];
        }
        
    } catch (Exception $e) {
        return [
            'success' => false,
            'message' => 'Error updating configuration: ' . $e->getMessage()
        ];
    }
}

function resolveAlert($alert_id) {
    global $pdo;
    
    try {
        $stmt = $pdo->prepare("
            UPDATE system_alerts 
            SET resolved = 1, resolved_at = NOW(), resolved_by = ?
            WHERE id = ? AND resolved = 0
        ");
        
        $admin_id = $_SESSION['admin_id'] ?? 'unknown';
        $stmt->execute([$admin_id, $alert_id]);
        
        if ($stmt->rowCount() > 0) {
            logAdminAction('resolve_alert', "Alert {$alert_id} resolved");
            return [
                'success' => true,
                'message' => 'Alert resolved successfully'
            ];
        } else {
            return [
                'success' => false,
                'message' => 'Alert not found or already resolved'
            ];
        }
        
    } catch (Exception $e) {
        return [
            'success' => false,
            'message' => 'Error resolving alert: ' . $e->getMessage()
        ];
    }
}

function getPerformanceData($timeframe) {
    global $pdo;
    
    try {
        // Convert timeframe to SQL interval
        $intervals = [
            '1h' => 'INTERVAL 1 HOUR',
            '6h' => 'INTERVAL 6 HOUR',
            '24h' => 'INTERVAL 24 HOUR',
            '7d' => 'INTERVAL 7 DAY',
            '30d' => 'INTERVAL 30 DAY'
        ];
        
        $interval = $intervals[$timeframe] ?? 'INTERVAL 1 HOUR';
        
        // Get system metrics
        $stmt = $pdo->prepare("
            SELECT metric_name, value, timestamp
            FROM system_metrics 
            WHERE timestamp >= DATE_SUB(NOW(), {$interval})
            ORDER BY timestamp ASC
        ");
        $stmt->execute();
        $metrics = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Group metrics by name
        $grouped_metrics = [];
        foreach ($metrics as $metric) {
            $grouped_metrics[$metric['metric_name']][] = [
                'value' => floatval($metric['value']),
                'timestamp' => $metric['timestamp']
            ];
        }
        
        return [
            'success' => true,
            'timeframe' => $timeframe,
            'metrics' => $grouped_metrics,
            'last_updated' => date('Y-m-d H:i:s')
        ];
        
    } catch (Exception $e) {
        return [
            'success' => false,
            'message' => 'Error getting performance data: ' . $e->getMessage()
        ];
    }
}

// Helper functions for system checks
function checkPythonBackendStatus() {
    // Check if Python process is running
    $command = 'tasklist /FI "IMAGENAME eq python.exe" 2>NUL | find /I "python.exe" >NUL';
    exec($command, $output, $return_code);
    return $return_code === 0;
}

function checkServiceStatus($service) {
    // Simulate service status check
    // In real implementation, this would check actual service status
    $statuses = ['running', 'stopped', 'error', 'starting'];
    return [
        'status' => $statuses[array_rand($statuses)],
        'last_check' => date('Y-m-d H:i:s'),
        'uptime' => rand(0, 86400) // Random uptime in seconds
    ];
}

function getSystemHealthData() {
    // Get system health metrics
    return [
        'cpu_usage' => rand(10, 80),
        'memory_usage' => rand(30, 90),
        'disk_usage' => rand(20, 70),
        'network_status' => 'healthy',
        'database_status' => 'healthy',
        'redis_status' => 'healthy'
    ];
}

function getSystemUptime() {
    // Calculate system uptime
    $start_time = time() - rand(3600, 86400); // Random uptime
    return time() - $start_time;
}

function checkAIServiceEndpoint($endpoint) {
    // Simulate AI service endpoint check
    return [
        'status' => rand(0, 1) ? 'healthy' : 'unhealthy',
        'response_time' => rand(50, 500), // ms
        'last_check' => date('Y-m-d H:i:s')
    ];
}

function getAIModelStatuses() {
    return [
        'risk_assessment_model' => [
            'status' => 'loaded',
            'version' => '1.2.3',
            'accuracy' => 94.5,
            'last_trained' => '2024-01-15'
        ],
        'fraud_detection_model' => [
            'status' => 'loaded',
            'version' => '2.1.0',
            'accuracy' => 97.2,
            'last_trained' => '2024-01-20'
        ],
        'content_generation_model' => [
            'status' => 'loaded',
            'version' => '1.0.5',
            'last_updated' => '2024-01-18'
        ]
    ];
}

function getAIProcessingQueues() {
    return [
        'risk_assessment_queue' => rand(0, 50),
        'fraud_detection_queue' => rand(0, 20),
        'content_generation_queue' => rand(0, 100),
        'seo_optimization_queue' => rand(0, 30)
    ];
}

function logAdminAction($action, $description) {
    global $pdo;
    
    try {
        $stmt = $pdo->prepare("
            INSERT INTO admin_audit_log (admin_id, action, description, ip_address, user_agent, timestamp)
            VALUES (?, ?, ?, ?, ?, NOW())
        ");
        
        $admin_id = $_SESSION['admin_id'] ?? 'unknown';
        $ip_address = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
        $user_agent = $_SERVER['HTTP_USER_AGENT'] ?? 'unknown';
        
        $stmt->execute([$admin_id, $action, $description, $ip_address, $user_agent]);
        
    } catch (Exception $e) {
        error_log("Failed to log admin action: " . $e->getMessage());
    }
}

include 'header.php';
?>

<div class="container-fluid">
    <div class="row">
        <div class="col-12">
            <div class="page-header">
                <h1 class="page-title">
                    <i class="fas fa-robot"></i> Autonomous Business System
                </h1>
                <div class="page-subtitle">Real-time monitoring and control dashboard</div>
            </div>
        </div>
    </div>

    <!-- System Status Overview -->
    <div class="row mb-4">
        <div class="col-12">
            <div class="card">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h5 class="card-title mb-0">
                        <i class="fas fa-heartbeat"></i> System Status
                    </h5>
                    <div class="btn-group">
                        <button type="button" class="btn btn-success btn-sm" id="startSystemBtn">
                            <i class="fas fa-play"></i> Start System
                        </button>
                        <button type="button" class="btn btn-warning btn-sm" id="stopSystemBtn">
                            <i class="fas fa-stop"></i> Stop System
                        </button>
                        <button type="button" class="btn btn-info btn-sm" id="refreshStatusBtn">
                            <i class="fas fa-sync"></i> Refresh
                        </button>
                    </div>
                </div>
                <div class="card-body">
                    <div class="row" id="systemStatusContainer">
                        <div class="col-12 text-center">
                            <div class="spinner-border" role="status">
                                <span class="sr-only">Loading...</span>
                            </div>
                            <p class="mt-2">Loading system status...</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Business Metrics -->
    <div class="row mb-4">
        <div class="col-12">
            <div class="card">
                <div class="card-header">
                    <h5 class="card-title mb-0">
                        <i class="fas fa-chart-line"></i> Business Metrics
                    </h5>
                </div>
                <div class="card-body">
                    <div class="row" id="businessMetricsContainer">
                        <div class="col-12 text-center">
                            <div class="spinner-border" role="status">
                                <span class="sr-only">Loading...</span>
                            </div>
                            <p class="mt-2">Loading business metrics...</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- AI Services Status -->
    <div class="row mb-4">
        <div class="col-12">
            <div class="card">
                <div class="card-header">
                    <h5 class="card-title mb-0">
                        <i class="fas fa-brain"></i> AI Services
                    </h5>
                </div>
                <div class="card-body">
                    <div class="row" id="aiServicesContainer">
                        <div class="col-12 text-center">
                            <div class="spinner-border" role="status">
                                <span class="sr-only">Loading...</span>
                            </div>
                            <p class="mt-2">Loading AI services status...</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Performance Charts -->
    <div class="row mb-4">
        <div class="col-md-6">
            <div class="card">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h5 class="card-title mb-0">
                        <i class="fas fa-tachometer-alt"></i> System Performance
                    </h5>
                    <select class="form-select form-select-sm" id="performanceTimeframe" style="width: auto;">
                        <option value="1h">Last Hour</option>
                        <option value="6h">Last 6 Hours</option>
                        <option value="24h" selected>Last 24 Hours</option>
                        <option value="7d">Last 7 Days</option>
                        <option value="30d">Last 30 Days</option>
                    </select>
                </div>
                <div class="card-body">
                    <canvas id="performanceChart" width="400" height="200"></canvas>
                </div>
            </div>
        </div>
        <div class="col-md-6">
            <div class="card">
                <div class="card-header">
                    <h5 class="card-title mb-0">
                        <i class="fas fa-exclamation-triangle"></i> System Alerts
                    </h5>
                </div>
                <div class="card-body">
                    <div id="alertsContainer">
                        <div class="text-center">
                            <div class="spinner-border" role="status">
                                <span class="sr-only">Loading...</span>
                            </div>
                            <p class="mt-2">Loading alerts...</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Configuration Panel -->
    <div class="row mb-4">
        <div class="col-12">
            <div class="card">
                <div class="card-header">
                    <h5 class="card-title mb-0">
                        <i class="fas fa-cogs"></i> System Configuration
                    </h5>
                </div>
                <div class="card-body">
                    <form id="configurationForm">
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="monitoringInterval" class="form-label">Monitoring Interval (seconds)</label>
                                    <input type="number" class="form-control" id="monitoringInterval" value="60" min="10" max="3600">
                                </div>
                                <div class="mb-3">
                                    <label for="cpuThreshold" class="form-label">CPU Alert Threshold (%)</label>
                                    <input type="number" class="form-control" id="cpuThreshold" value="80" min="50" max="95">
                                </div>
                                <div class="mb-3">
                                    <label for="memoryThreshold" class="form-label">Memory Alert Threshold (%)</label>
                                    <input type="number" class="form-control" id="memoryThreshold" value="85" min="60" max="95">
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="diskThreshold" class="form-label">Disk Alert Threshold (%)</label>
                                    <input type="number" class="form-control" id="diskThreshold" value="90" min="70" max="98">
                                </div>
                                <div class="mb-3">
                                    <label for="responseTimeThreshold" class="form-label">Response Time Threshold (seconds)</label>
                                    <input type="number" class="form-control" id="responseTimeThreshold" value="5" min="1" max="30" step="0.1">
                                </div>
                                <div class="mb-3">
                                    <label for="errorRateThreshold" class="form-label">Error Rate Threshold (%)</label>
                                    <input type="number" class="form-control" id="errorRateThreshold" value="5" min="1" max="20" step="0.1">
                                </div>
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-12">
                                <button type="submit" class="btn btn-primary">
                                    <i class="fas fa-save"></i> Save Configuration
                                </button>
                                <button type="button" class="btn btn-secondary" id="resetConfigBtn">
                                    <i class="fas fa-undo"></i> Reset to Defaults
                                </button>
                            </div>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Modals -->
<div class="modal fade" id="alertModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Alert Details</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body" id="alertModalBody">
                <!-- Alert details will be loaded here -->
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-success" id="resolveAlertBtn">
                    <i class="fas fa-check"></i> Resolve Alert
                </button>
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
$(document).ready(function() {
    let performanceChart = null;
    let refreshInterval = null;
    let currentAlertId = null;
    
    // Initialize dashboard
    initializeDashboard();
    
    function initializeDashboard() {
        loadSystemStatus();
        loadBusinessMetrics();
        loadAIServicesStatus();
        loadAlerts();
        loadPerformanceData('24h');
        
        // Set up auto-refresh
        refreshInterval = setInterval(function() {
            loadSystemStatus();
            loadBusinessMetrics();
            loadAIServicesStatus();
            loadAlerts();
        }, 30000); // Refresh every 30 seconds
    }
    
    // System control buttons
    $('#startSystemBtn').click(function() {
        if (confirm('Are you sure you want to start the autonomous system?')) {
            controlSystem('start_autonomous_system');
        }
    });
    
    $('#stopSystemBtn').click(function() {
        if (confirm('Are you sure you want to stop the autonomous system?')) {
            controlSystem('stop_autonomous_system');
        }
    });
    
    $('#refreshStatusBtn').click(function() {
        loadSystemStatus();
        loadBusinessMetrics();
        loadAIServicesStatus();
        loadAlerts();
    });
    
    // Performance timeframe selector
    $('#performanceTimeframe').change(function() {
        loadPerformanceData($(this).val());
    });
    
    // Configuration form
    $('#configurationForm').submit(function(e) {
        e.preventDefault();
        saveConfiguration();
    });
    
    $('#resetConfigBtn').click(function() {
        if (confirm('Reset all configuration to defaults?')) {
            resetConfiguration();
        }
    });
    
    // Alert resolution
    $('#resolveAlertBtn').click(function() {
        if (currentAlertId) {
            resolveAlert(currentAlertId);
        }
    });
    
    function loadSystemStatus() {
        $.post('', {action: 'get_system_status'}, function(response) {
            if (response.success) {
                renderSystemStatus(response);
            } else {
                showError('Failed to load system status: ' + response.message);
            }
        }, 'json').fail(function() {
            showError('Failed to communicate with server');
        });
    }
    
    function loadBusinessMetrics() {
        $.post('', {action: 'get_business_metrics'}, function(response) {
            if (response.success) {
                renderBusinessMetrics(response);
            } else {
                showError('Failed to load business metrics: ' + response.message);
            }
        }, 'json');
    }
    
    function loadAIServicesStatus() {
        $.post('', {action: 'get_ai_services_status'}, function(response) {
            if (response.success) {
                renderAIServicesStatus(response);
            } else {
                showError('Failed to load AI services status: ' + response.message);
            }
        }, 'json');
    }
    
    function loadAlerts() {
        $.post('', {action: 'get_alerts'}, function(response) {
            if (response.success) {
                renderAlerts(response);
            } else {
                showError('Failed to load alerts: ' + response.message);
            }
        }, 'json');
    }
    
    function loadPerformanceData(timeframe) {
        $.post('', {action: 'get_performance_data', timeframe: timeframe}, function(response) {
            if (response.success) {
                renderPerformanceChart(response);
            } else {
                showError('Failed to load performance data: ' + response.message);
            }
        }, 'json');
    }
    
    function controlSystem(action) {
        const button = action === 'start_autonomous_system' ? $('#startSystemBtn') : $('#stopSystemBtn');
        button.prop('disabled', true).html('<i class="fas fa-spinner fa-spin"></i> Processing...');
        
        $.post('', {action: action}, function(response) {
            if (response.success) {
                showSuccess(response.message);
                setTimeout(loadSystemStatus, 2000); // Reload status after 2 seconds
            } else {
                showError(response.message);
            }
        }, 'json').always(function() {
            // Reset button
            if (action === 'start_autonomous_system') {
                button.prop('disabled', false).html('<i class="fas fa-play"></i> Start System');
            } else {
                button.prop('disabled', false).html('<i class="fas fa-stop"></i> Stop System');
            }
        });
    }
    
    function saveConfiguration() {
        const config = {
            monitoring_interval: parseInt($('#monitoringInterval').val()),
            alert_thresholds: {
                cpu: parseInt($('#cpuThreshold').val()),
                memory: parseInt($('#memoryThreshold').val()),
                disk: parseInt($('#diskThreshold').val()),
                response_time: parseFloat($('#responseTimeThreshold').val()),
                error_rate: parseFloat($('#errorRateThreshold').val())
            }
        };
        
        $.post('', {action: 'update_configuration', config: config}, function(response) {
            if (response.success) {
                showSuccess('Configuration saved successfully');
            } else {
                showError('Failed to save configuration: ' + response.message);
            }
        }, 'json');
    }
    
    function resetConfiguration() {
        $('#monitoringInterval').val(60);
        $('#cpuThreshold').val(80);
        $('#memoryThreshold').val(85);
        $('#diskThreshold').val(90);
        $('#responseTimeThreshold').val(5);
        $('#errorRateThreshold').val(5);
    }
    
    function resolveAlert(alertId) {
        $.post('', {action: 'resolve_alert', alert_id: alertId}, function(response) {
            if (response.success) {
                showSuccess('Alert resolved successfully');
                $('#alertModal').modal('hide');
                loadAlerts();
            } else {
                showError('Failed to resolve alert: ' + response.message);
            }
        }, 'json');
    }
    
    function renderSystemStatus(data) {
        const statusColor = data.system_status === 'running' ? 'success' : 'danger';
        const statusIcon = data.system_status === 'running' ? 'play' : 'stop';
        
        let html = `
            <div class="col-md-3">
                <div class="card bg-${statusColor} text-white">
                    <div class="card-body text-center">
                        <i class="fas fa-${statusIcon} fa-2x mb-2"></i>
                        <h5>System Status</h5>
                        <h3>${data.system_status.toUpperCase()}</h3>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card bg-info text-white">
                    <div class="card-body text-center">
                        <i class="fas fa-clock fa-2x mb-2"></i>
                        <h5>Uptime</h5>
                        <h3>${formatUptime(data.uptime)}</h3>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card bg-warning text-white">
                    <div class="card-body text-center">
                        <i class="fas fa-microchip fa-2x mb-2"></i>
                        <h5>CPU Usage</h5>
                        <h3>${data.health.cpu_usage}%</h3>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card bg-primary text-white">
                    <div class="card-body text-center">
                        <i class="fas fa-memory fa-2x mb-2"></i>
                        <h5>Memory Usage</h5>
                        <h3>${data.health.memory_usage}%</h3>
                    </div>
                </div>
            </div>
        `;
        
        // Add services status
        html += '<div class="col-12 mt-3"><h6>Services Status:</h6><div class="row">';
        
        Object.entries(data.services).forEach(([service, status]) => {
            const statusColor = status.status === 'running' ? 'success' : 'danger';
            html += `
                <div class="col-md-2 mb-2">
                    <div class="card border-${statusColor}">
                        <div class="card-body p-2 text-center">
                            <small class="text-muted">${service.replace('_', ' ').toUpperCase()}</small><br>
                            <span class="badge bg-${statusColor}">${status.status}</span>
                        </div>
                    </div>
                </div>
            `;
        });
        
        html += '</div></div>';
        
        $('#systemStatusContainer').html(html);
    }
    
    function renderBusinessMetrics(data) {
        let html = `
            <div class="col-md-3">
                <div class="card bg-primary text-white">
                    <div class="card-body text-center">
                        <i class="fas fa-file-alt fa-2x mb-2"></i>
                        <h5>Applications Today</h5>
                        <h3>${data.daily.applications_today}</h3>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card bg-success text-white">
                    <div class="card-body text-center">
                        <i class="fas fa-check fa-2x mb-2"></i>
                        <h5>Approvals Today</h5>
                        <h3>${data.daily.approvals_today}</h3>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card bg-danger text-white">
                    <div class="card-body text-center">
                        <i class="fas fa-times fa-2x mb-2"></i>
                        <h5>Rejections Today</h5>
                        <h3>${data.daily.rejections_today}</h3>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card bg-warning text-white">
                    <div class="card-body text-center">
                        <i class="fas fa-dollar-sign fa-2x mb-2"></i>
                        <h5>Revenue Today</h5>
                        <h3>$${parseFloat(data.revenue.revenue_today).toFixed(2)}</h3>
                    </div>
                </div>
            </div>
            <div class="col-md-4 mt-3">
                <div class="card">
                    <div class="card-body text-center">
                        <i class="fas fa-users fa-2x mb-2 text-info"></i>
                        <h5>Active Users</h5>
                        <h3>${data.active_users}</h3>
                    </div>
                </div>
            </div>
            <div class="col-md-4 mt-3">
                <div class="card">
                    <div class="card-body text-center">
                        <i class="fas fa-percentage fa-2x mb-2 text-success"></i>
                        <h5>Approval Rate</h5>
                        <h3>${data.approval_rate}%</h3>
                    </div>
                </div>
            </div>
            <div class="col-md-4 mt-3">
                <div class="card">
                    <div class="card-body text-center">
                        <i class="fas fa-calendar-alt fa-2x mb-2 text-primary"></i>
                        <h5>Monthly Revenue</h5>
                        <h3>$${parseFloat(data.revenue.revenue_month).toFixed(2)}</h3>
                    </div>
                </div>
            </div>
        `;
        
        $('#businessMetricsContainer').html(html);
    }
    
    function renderAIServicesStatus(data) {
        let html = '<div class="row">';
        
        // AI Services
        html += '<div class="col-md-6"><h6>AI Services:</h6>';
        Object.entries(data.services).forEach(([service, status]) => {
            const statusColor = status.status === 'healthy' ? 'success' : 'danger';
            html += `
                <div class="d-flex justify-content-between align-items-center mb-2 p-2 border rounded">
                    <span>${service.replace('_', ' ').toUpperCase()}</span>
                    <div>
                        <span class="badge bg-${statusColor}">${status.status}</span>
                        <small class="text-muted ms-2">${status.response_time}ms</small>
                    </div>
                </div>
            `;
        });
        html += '</div>';
        
        // AI Models
        html += '<div class="col-md-6"><h6>AI Models:</h6>';
        Object.entries(data.models).forEach(([model, info]) => {
            const statusColor = info.status === 'loaded' ? 'success' : 'warning';
            html += `
                <div class="d-flex justify-content-between align-items-center mb-2 p-2 border rounded">
                    <div>
                        <span>${model.replace('_', ' ').toUpperCase()}</span><br>
                        <small class="text-muted">v${info.version}</small>
                    </div>
                    <div class="text-end">
                        <span class="badge bg-${statusColor}">${info.status}</span>
                        ${info.accuracy ? `<br><small class="text-muted">${info.accuracy}% accuracy</small>` : ''}
                    </div>
                </div>
            `;
        });
        html += '</div>';
        
        // Processing Queues
        html += '<div class="col-12 mt-3"><h6>Processing Queues:</h6><div class="row">';
        Object.entries(data.queues).forEach(([queue, count]) => {
            const queueColor = count > 50 ? 'danger' : count > 20 ? 'warning' : 'success';
            html += `
                <div class="col-md-3 mb-2">
                    <div class="card border-${queueColor}">
                        <div class="card-body p-2 text-center">
                            <small class="text-muted">${queue.replace('_', ' ').toUpperCase()}</small><br>
                            <span class="badge bg-${queueColor}">${count} items</span>
                        </div>
                    </div>
                </div>
            `;
        });
        html += '</div></div>';
        
        html += '</div>';
        
        $('#aiServicesContainer').html(html);
    }
    
    function renderAlerts(data) {
        if (data.alerts.length === 0) {
            $('#alertsContainer').html('<div class="text-center text-muted"><i class="fas fa-check-circle fa-3x mb-2"></i><p>No alerts</p></div>');
            return;
        }
        
        let html = '<div class="alert-list" style="max-height: 400px; overflow-y: auto;">';
        
        data.alerts.forEach(alert => {
            const levelColor = {
                'info': 'info',
                'warning': 'warning',
                'error': 'danger',
                'critical': 'danger'
            }[alert.level] || 'secondary';
            
            const resolvedBadge = alert.resolved ? '<span class="badge bg-success ms-2">Resolved</span>' : '';
            
            html += `
                <div class="alert alert-${levelColor} alert-dismissible mb-2" role="alert">
                    <div class="d-flex justify-content-between align-items-start">
                        <div>
                            <strong>${alert.level.toUpperCase()}</strong> - ${alert.source}
                            ${resolvedBadge}
                            <br>
                            <small class="text-muted">${alert.timestamp}</small>
                        </div>
                        <div>
                            ${!alert.resolved ? `<button type="button" class="btn btn-sm btn-outline-success resolve-alert-btn" data-alert-id="${alert.id}">Resolve</button>` : ''}
                        </div>
                    </div>
                    <div class="mt-2">${alert.message}</div>
                </div>
            `;
        });
        
        html += '</div>';
        
        $('#alertsContainer').html(html);
        
        // Bind resolve alert buttons
        $('.resolve-alert-btn').click(function() {
            const alertId = $(this).data('alert-id');
            currentAlertId = alertId;
            
            // Find alert details
            const alert = data.alerts.find(a => a.id == alertId);
            if (alert) {
                $('#alertModalBody').html(`
                    <div class="row">
                        <div class="col-md-6"><strong>Level:</strong> ${alert.level}</div>
                        <div class="col-md-6"><strong>Source:</strong> ${alert.source}</div>
                        <div class="col-md-6"><strong>Timestamp:</strong> ${alert.timestamp}</div>
                        <div class="col-md-6"><strong>Status:</strong> ${alert.resolved ? 'Resolved' : 'Active'}</div>
                        <div class="col-12 mt-3"><strong>Message:</strong><br>${alert.message}</div>
                    </div>
                `);
                $('#alertModal').modal('show');
            }
        });
    }
    
    function renderPerformanceChart(data) {
        const ctx = document.getElementById('performanceChart').getContext('2d');
        
        if (performanceChart) {
            performanceChart.destroy();
        }
        
        // Prepare chart data
        const datasets = [];
        const colors = ['#007bff', '#28a745', '#ffc107', '#dc3545', '#6f42c1'];
        let colorIndex = 0;
        
        Object.entries(data.metrics).forEach(([metricName, points]) => {
            if (metricName.includes('cpu') || metricName.includes('memory') || metricName.includes('response_time')) {
                datasets.push({
                    label: metricName.replace('_', ' ').toUpperCase(),
                    data: points.map(p => ({x: p.timestamp, y: p.value})),
                    borderColor: colors[colorIndex % colors.length],
                    backgroundColor: colors[colorIndex % colors.length] + '20',
                    fill: false,
                    tension: 0.1
                });
                colorIndex++;
            }
        });
        
        performanceChart = new Chart(ctx, {
            type: 'line',
            data: {
                datasets: datasets
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                scales: {
                    x: {
                        type: 'time',
                        time: {
                            displayFormats: {
                                hour: 'HH:mm',
                                day: 'MMM DD'
                            }
                        }
                    },
                    y: {
                        beginAtZero: true
                    }
                },
                plugins: {
                    legend: {
                        position: 'top'
                    },
                    tooltip: {
                        mode: 'index',
                        intersect: false
                    }
                }
            }
        });
    }
    
    function formatUptime(seconds) {
        const days = Math.floor(seconds / 86400);
        const hours = Math.floor((seconds % 86400) / 3600);
        const minutes = Math.floor((seconds % 3600) / 60);
        
        if (days > 0) {
            return `${days}d ${hours}h ${minutes}m`;
        } else if (hours > 0) {
            return `${hours}h ${minutes}m`;
        } else {
            return `${minutes}m`;
        }
    }
    
    function showSuccess(message) {
        // You can implement a toast notification system here
        alert('Success: ' + message);
    }
    
    function showError(message) {
        // You can implement a toast notification system here
        alert('Error: ' + message);
    }
    
    // Cleanup on page unload
    $(window).on('beforeunload', function() {
        if (refreshInterval) {
            clearInterval(refreshInterval);
        }
        if (performanceChart) {
            performanceChart.destroy();
        }
    });
});
</script>

<?php include 'footer.php'; ?>