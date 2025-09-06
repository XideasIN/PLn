<?php
/**
 * System Monitoring Dashboard
 * LoanFlow Personal Loan Management System
 */

require_once '../config/database.php';
require_once '../includes/functions.php';
require_once '../includes/auth.php';
require_once '../includes/language.php';
require_once '../includes/error_monitoring.php';

// Require admin access
requireRole('admin');

// Initialize language
LanguageManager::init();

$current_user = getCurrentUser();

$error = '';
$success = '';

// Handle actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCSRFToken($_POST['csrf_token'] ?? '')) {
        $error = __('invalid_csrf_token');
    } else {
        $action = $_POST['action'] ?? '';
        
        switch ($action) {
            case 'run_system_tests':
                try {
                    $test_results = ErrorMonitoringManager::runSystemTests();
                    $_SESSION['test_results'] = $test_results;
                    $success = "System tests completed. {$test_results['passed']} passed, {$test_results['failed']} failed.";
                    logAudit('system_tests_run', 'system', null, $current_user['id'], $test_results);
                } catch (Exception $e) {
                    $error = "System tests failed: " . $e->getMessage();
                }
                break;
                
            case 'clear_error_logs':
                try {
                    $db = getDB();
                    $stmt = $db->prepare("DELETE FROM error_logs WHERE created_at < DATE_SUB(NOW(), INTERVAL 1 DAY)");
                    $stmt->execute();
                    $cleared = $stmt->rowCount();
                    $success = "Cleared {$cleared} old error log entries.";
                    logAudit('error_logs_cleared', 'system', null, $current_user['id'], ['cleared' => $cleared]);
                } catch (Exception $e) {
                    $error = "Failed to clear error logs: " . $e->getMessage();
                }
                break;
                
            case 'clear_performance_logs':
                try {
                    $db = getDB();
                    $stmt = $db->prepare("DELETE FROM performance_logs WHERE created_at < DATE_SUB(NOW(), INTERVAL 7 DAY)");
                    $stmt->execute();
                    $cleared = $stmt->rowCount();
                    $success = "Cleared {$cleared} old performance log entries.";
                    logAudit('performance_logs_cleared', 'system', null, $current_user['id'], ['cleared' => $cleared]);
                } catch (Exception $e) {
                    $error = "Failed to clear performance logs: " . $e->getMessage();
                }
                break;
        }
    }
}

// Get monitoring data
$monitoring_report = ErrorMonitoringManager::generateReport(7);
$system_health = getSystemSetting('system_health', 'unknown');
$last_test = getSystemSetting('last_system_test', 'Never');
$last_cron = getSystemSetting('last_cron_run', 'Never');

?>
<!DOCTYPE html>
<html lang="<?= LanguageManager::getCurrentLanguage() ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= __('system_monitoring') ?> - LoanFlow Admin</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="../assets/css/style.css" rel="stylesheet">
    <link href="../assets/css/admin.css" rel="stylesheet">
    <style>
        .health-indicator {
            width: 20px;
            height: 20px;
            border-radius: 50%;
            display: inline-block;
            margin-right: 8px;
        }
        .health-healthy { background-color: #28a745; }
        .health-warning { background-color: #ffc107; }
        .health-critical { background-color: #dc3545; }
        .health-unknown { background-color: #6c757d; }
        
        .metric-card {
            transition: transform 0.2s;
        }
        .metric-card:hover {
            transform: translateY(-2px);
        }
        
        .log-entry {
            font-family: 'Courier New', monospace;
            font-size: 0.85em;
            background: #f8f9fa;
            border-left: 3px solid #007bff;
            padding: 8px 12px;
            margin: 5px 0;
        }
        
        .error-entry { border-left-color: #dc3545; }
        .warning-entry { border-left-color: #ffc107; }
        .info-entry { border-left-color: #17a2b8; }
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

        <!-- System Health Overview -->
        <div class="row mb-4">
            <div class="col-12">
                <div class="card">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <h4 class="mb-0">
                            <i class="fas fa-heartbeat me-2"></i><?= __('system_health_overview') ?>
                        </h4>
                        <div class="btn-group">
                            <form method="POST" class="d-inline">
                                <input type="hidden" name="csrf_token" value="<?= generateCSRFToken() ?>">
                                <input type="hidden" name="action" value="run_system_tests">
                                <button type="submit" class="btn btn-primary">
                                    <i class="fas fa-play me-1"></i><?= __('run_tests') ?>
                                </button>
                            </form>
                        </div>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-lg-3 col-md-6 mb-3">
                                <div class="metric-card card bg-light h-100">
                                    <div class="card-body text-center">
                                        <div class="mb-2">
                                            <span class="health-indicator health-<?= $system_health ?>"></span>
                                            <span class="text-uppercase fw-bold"><?= ucfirst($system_health) ?></span>
                                        </div>
                                        <h6><?= __('system_status') ?></h6>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="col-lg-3 col-md-6 mb-3">
                                <div class="metric-card card bg-light h-100">
                                    <div class="card-body text-center">
                                        <div class="mb-2">
                                            <i class="fas fa-clock fa-2x text-info"></i>
                                        </div>
                                        <h6><?= __('last_system_test') ?></h6>
                                        <small class="text-muted"><?= $last_test ?></small>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="col-lg-3 col-md-6 mb-3">
                                <div class="metric-card card bg-light h-100">
                                    <div class="card-body text-center">
                                        <div class="mb-2">
                                            <i class="fas fa-cogs fa-2x text-success"></i>
                                        </div>
                                        <h6><?= __('last_cron_run') ?></h6>
                                        <small class="text-muted"><?= $last_cron ?></small>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="col-lg-3 col-md-6 mb-3">
                                <div class="metric-card card bg-light h-100">
                                    <div class="card-body text-center">
                                        <div class="mb-2">
                                            <i class="fas fa-server fa-2x text-primary"></i>
                                        </div>
                                        <h6><?= __('server_uptime') ?></h6>
                                        <small class="text-muted"><?= round(sys_getloadavg()[0], 2) ?> load avg</small>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Navigation Tabs -->
        <ul class="nav nav-tabs" id="monitoringTabs" role="tablist">
            <li class="nav-item" role="presentation">
                <button class="nav-link active" id="overview-tab" data-bs-toggle="tab" data-bs-target="#overview" type="button" role="tab">
                    <i class="fas fa-chart-line me-2"></i><?= __('overview') ?>
                </button>
            </li>
            <li class="nav-item" role="presentation">
                <button class="nav-link" id="errors-tab" data-bs-toggle="tab" data-bs-target="#errors" type="button" role="tab">
                    <i class="fas fa-exclamation-triangle me-2"></i><?= __('error_logs') ?>
                </button>
            </li>
            <li class="nav-item" role="presentation">
                <button class="nav-link" id="performance-tab" data-bs-toggle="tab" data-bs-target="#performance" type="button" role="tab">
                    <i class="fas fa-tachometer-alt me-2"></i><?= __('performance') ?>
                </button>
            </li>
            <li class="nav-item" role="presentation">
                <button class="nav-link" id="tests-tab" data-bs-toggle="tab" data-bs-target="#tests" type="button" role="tab">
                    <i class="fas fa-vial me-2"></i><?= __('system_tests') ?>
                </button>
            </li>
        </ul>

        <!-- Tab Content -->
        <div class="tab-content" id="monitoringTabsContent">
            
            <!-- Overview Tab -->
            <div class="tab-pane fade show active" id="overview" role="tabpanel">
                <div class="card">
                    <div class="card-body">
                        <?php if ($monitoring_report): ?>
                            <div class="row">
                                <!-- Error Statistics -->
                                <div class="col-lg-6 mb-4">
                                    <h5><?= __('error_statistics') ?> (<?= __('last_7_days') ?>)</h5>
                                    
                                    <?php if (!empty($monitoring_report['error_statistics'])): ?>
                                        <div class="table-responsive">
                                            <table class="table table-sm">
                                                <thead>
                                                    <tr>
                                                        <th><?= __('error_type') ?></th>
                                                        <th><?= __('severity') ?></th>
                                                        <th><?= __('count') ?></th>
                                                        <th><?= __('files') ?></th>
                                                    </tr>
                                                </thead>
                                                <tbody>
                                                    <?php foreach ($monitoring_report['error_statistics'] as $stat): ?>
                                                        <tr>
                                                            <td><?= htmlspecialchars($stat['error_type']) ?></td>
                                                            <td>
                                                                <span class="badge <?= $stat['severity'] === 'Fatal Error' ? 'bg-danger' : ($stat['severity'] === 'Warning' ? 'bg-warning' : 'bg-info') ?>">
                                                                    <?= htmlspecialchars($stat['severity']) ?>
                                                                </span>
                                                            </td>
                                                            <td><?= $stat['count'] ?></td>
                                                            <td><?= $stat['unique_files'] ?></td>
                                                        </tr>
                                                    <?php endforeach; ?>
                                                </tbody>
                                            </table>
                                        </div>
                                    <?php else: ?>
                                        <div class="alert alert-success">
                                            <i class="fas fa-check-circle me-2"></i><?= __('no_errors_recorded') ?>
                                        </div>
                                    <?php endif; ?>
                                </div>
                                
                                <!-- Performance Statistics -->
                                <div class="col-lg-6 mb-4">
                                    <h5><?= __('performance_statistics') ?> (<?= __('last_7_days') ?>)</h5>
                                    
                                    <?php if ($monitoring_report['performance_statistics']): ?>
                                        <div class="row">
                                            <div class="col-sm-6 mb-3">
                                                <div class="card bg-light">
                                                    <div class="card-body text-center">
                                                        <h4 class="text-primary"><?= round($monitoring_report['performance_statistics']['avg_execution_time'], 2) ?>ms</h4>
                                                        <small><?= __('avg_response_time') ?></small>
                                                    </div>
                                                </div>
                                            </div>
                                            
                                            <div class="col-sm-6 mb-3">
                                                <div class="card bg-light">
                                                    <div class="card-body text-center">
                                                        <h4 class="text-warning"><?= round($monitoring_report['performance_statistics']['max_execution_time'], 2) ?>ms</h4>
                                                        <small><?= __('max_response_time') ?></small>
                                                    </div>
                                                </div>
                                            </div>
                                            
                                            <div class="col-sm-6 mb-3">
                                                <div class="card bg-light">
                                                    <div class="card-body text-center">
                                                        <h4 class="text-info"><?= round($monitoring_report['performance_statistics']['avg_memory_usage'] / 1024 / 1024, 2) ?>MB</h4>
                                                        <small><?= __('avg_memory_usage') ?></small>
                                                    </div>
                                                </div>
                                            </div>
                                            
                                            <div class="col-sm-6 mb-3">
                                                <div class="card bg-light">
                                                    <div class="card-body text-center">
                                                        <h4 class="text-danger"><?= $monitoring_report['performance_statistics']['slow_requests'] ?></h4>
                                                        <small><?= __('slow_requests') ?> (>1s)</small>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    <?php else: ?>
                                        <div class="alert alert-info">
                                            <i class="fas fa-info-circle me-2"></i><?= __('no_performance_data') ?>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        <?php else: ?>
                            <div class="alert alert-warning">
                                <i class="fas fa-exclamation-triangle me-2"></i><?= __('monitoring_data_unavailable') ?>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            
            <!-- Error Logs Tab -->
            <div class="tab-pane fade" id="errors" role="tabpanel">
                <div class="card">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <h5 class="mb-0"><?= __('recent_error_logs') ?></h5>
                        <form method="POST" class="d-inline">
                            <input type="hidden" name="csrf_token" value="<?= generateCSRFToken() ?>">
                            <input type="hidden" name="action" value="clear_error_logs">
                            <button type="submit" class="btn btn-outline-danger btn-sm" onclick="return confirm('<?= __('confirm_clear_error_logs') ?>')">
                                <i class="fas fa-trash me-1"></i><?= __('clear_old_logs') ?>
                            </button>
                        </form>
                    </div>
                    <div class="card-body">
                        <?php
                        try {
                            $db = getDB();
                            $stmt = $db->prepare("
                                SELECT * FROM error_logs 
                                ORDER BY created_at DESC 
                                LIMIT 50
                            ");
                            $stmt->execute();
                            $error_logs = $stmt->fetchAll(PDO::FETCH_ASSOC);
                            
                            if (!empty($error_logs)):
                        ?>
                            <div class="table-responsive">
                                <table class="table table-sm">
                                    <thead>
                                        <tr>
                                            <th><?= __('timestamp') ?></th>
                                            <th><?= __('type') ?></th>
                                            <th><?= __('severity') ?></th>
                                            <th><?= __('message') ?></th>
                                            <th><?= __('file') ?></th>
                                            <th><?= __('line') ?></th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($error_logs as $log): ?>
                                            <tr>
                                                <td class="text-nowrap"><?= date('M j H:i', strtotime($log['created_at'])) ?></td>
                                                <td><?= htmlspecialchars($log['error_type']) ?></td>
                                                <td>
                                                    <span class="badge <?= $log['severity'] === 'Fatal Error' ? 'bg-danger' : ($log['severity'] === 'Warning' ? 'bg-warning' : 'bg-info') ?>">
                                                        <?= htmlspecialchars($log['severity']) ?>
                                                    </span>
                                                </td>
                                                <td class="text-truncate" style="max-width: 300px;" title="<?= htmlspecialchars($log['message']) ?>">
                                                    <?= htmlspecialchars(substr($log['message'], 0, 100)) ?><?= strlen($log['message']) > 100 ? '...' : '' ?>
                                                </td>
                                                <td class="text-truncate" style="max-width: 200px;" title="<?= htmlspecialchars($log['file']) ?>">
                                                    <?= htmlspecialchars(basename($log['file'])) ?>
                                                </td>
                                                <td><?= $log['line'] ?></td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php else: ?>
                            <div class="alert alert-success">
                                <i class="fas fa-check-circle me-2"></i><?= __('no_error_logs_found') ?>
                            </div>
                        <?php endif; ?>
                        <?php
                        } catch (Exception $e) {
                            echo '<div class="alert alert-danger">Error loading logs: ' . htmlspecialchars($e->getMessage()) . '</div>';
                        }
                        ?>
                    </div>
                </div>
            </div>
            
            <!-- Performance Tab -->
            <div class="tab-pane fade" id="performance" role="tabpanel">
                <div class="card">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <h5 class="mb-0"><?= __('performance_logs') ?></h5>
                        <form method="POST" class="d-inline">
                            <input type="hidden" name="csrf_token" value="<?= generateCSRFToken() ?>">
                            <input type="hidden" name="action" value="clear_performance_logs">
                            <button type="submit" class="btn btn-outline-warning btn-sm" onclick="return confirm('<?= __('confirm_clear_performance_logs') ?>')">
                                <i class="fas fa-trash me-1"></i><?= __('clear_old_logs') ?>
                            </button>
                        </form>
                    </div>
                    <div class="card-body">
                        <?php
                        try {
                            $db = getDB();
                            $stmt = $db->prepare("
                                SELECT * FROM performance_logs 
                                WHERE execution_time > 500 
                                ORDER BY execution_time DESC 
                                LIMIT 50
                            ");
                            $stmt->execute();
                            $perf_logs = $stmt->fetchAll(PDO::FETCH_ASSOC);
                            
                            if (!empty($perf_logs)):
                        ?>
                            <div class="table-responsive">
                                <table class="table table-sm">
                                    <thead>
                                        <tr>
                                            <th><?= __('timestamp') ?></th>
                                            <th><?= __('url') ?></th>
                                            <th><?= __('method') ?></th>
                                            <th><?= __('execution_time') ?></th>
                                            <th><?= __('memory_usage') ?></th>
                                            <th><?= __('peak_memory') ?></th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($perf_logs as $log): ?>
                                            <tr>
                                                <td class="text-nowrap"><?= date('M j H:i', strtotime($log['created_at'])) ?></td>
                                                <td class="text-truncate" style="max-width: 300px;" title="<?= htmlspecialchars($log['url']) ?>">
                                                    <?= htmlspecialchars($log['url']) ?>
                                                </td>
                                                <td>
                                                    <span class="badge bg-secondary"><?= htmlspecialchars($log['method']) ?></span>
                                                </td>
                                                <td>
                                                    <span class="badge <?= $log['execution_time'] > 2000 ? 'bg-danger' : ($log['execution_time'] > 1000 ? 'bg-warning' : 'bg-success') ?>">
                                                        <?= number_format($log['execution_time'], 2) ?>ms
                                                    </span>
                                                </td>
                                                <td><?= round($log['memory_usage'] / 1024 / 1024, 2) ?>MB</td>
                                                <td><?= round($log['peak_memory'] / 1024 / 1024, 2) ?>MB</td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php else: ?>
                            <div class="alert alert-success">
                                <i class="fas fa-check-circle me-2"></i><?= __('no_slow_requests_found') ?>
                            </div>
                        <?php endif; ?>
                        <?php
                        } catch (Exception $e) {
                            echo '<div class="alert alert-danger">Error loading performance logs: ' . htmlspecialchars($e->getMessage()) . '</div>';
                        }
                        ?>
                    </div>
                </div>
            </div>
            
            <!-- System Tests Tab -->
            <div class="tab-pane fade" id="tests" role="tabpanel">
                <div class="card">
                    <div class="card-header">
                        <h5 class="mb-0"><?= __('system_test_results') ?></h5>
                    </div>
                    <div class="card-body">
                        <?php if (isset($_SESSION['test_results'])): ?>
                            <?php $results = $_SESSION['test_results']; ?>
                            <div class="row mb-4">
                                <div class="col-md-4">
                                    <div class="card bg-light">
                                        <div class="card-body text-center">
                                            <h3 class="text-primary"><?= $results['total'] ?></h3>
                                            <small><?= __('total_tests') ?></small>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <div class="card bg-light">
                                        <div class="card-body text-center">
                                            <h3 class="text-success"><?= $results['passed'] ?></h3>
                                            <small><?= __('passed_tests') ?></small>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <div class="card bg-light">
                                        <div class="card-body text-center">
                                            <h3 class="text-danger"><?= $results['failed'] ?></h3>
                                            <small><?= __('failed_tests') ?></small>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            
                            <h6><?= __('test_details') ?>:</h6>
                            <div class="table-responsive">
                                <table class="table">
                                    <thead>
                                        <tr>
                                            <th><?= __('test_name') ?></th>
                                            <th><?= __('status') ?></th>
                                            <th><?= __('message') ?></th>
                                            <th><?= __('execution_time') ?></th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($results['tests'] as $test): ?>
                                            <tr>
                                                <td><?= htmlspecialchars($test['name']) ?></td>
                                                <td>
                                                    <?php if ($test['passed']): ?>
                                                        <span class="badge bg-success">
                                                            <i class="fas fa-check me-1"></i><?= __('passed') ?>
                                                        </span>
                                                    <?php else: ?>
                                                        <span class="badge bg-danger">
                                                            <i class="fas fa-times me-1"></i><?= __('failed') ?>
                                                        </span>
                                                    <?php endif; ?>
                                                </td>
                                                <td><?= htmlspecialchars($test['message']) ?></td>
                                                <td><?= $test['execution_time'] ?>ms</td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                            
                            <?php unset($_SESSION['test_results']); ?>
                        <?php else: ?>
                            <div class="alert alert-info">
                                <i class="fas fa-info-circle me-2"></i><?= __('no_recent_test_results') ?>
                                <br><small><?= __('run_system_tests_to_see_results') ?></small>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Auto-refresh page every 5 minutes
        setTimeout(function() {
            location.reload();
        }, 300000);
        
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
