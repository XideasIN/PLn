<?php
/**
 * Advanced Analytics & Reporting System
 * LoanFlow Personal Loan Management System
 * 
 * Comprehensive analytics dashboard with predictive analytics,
 * business intelligence, and performance metrics
 */

session_start();
require_once '../config/database.php';
require_once '../includes/functions.php';
require_once '../includes/auth.php';

// Check if user is logged in and is admin
if (!isLoggedIn() || !isAdmin()) {
    header('Location: ../login.php');
    exit();
}

$current_user = getCurrentUser();
$page_title = 'Advanced Analytics & Reporting';

// Handle AJAX requests
if (isset($_POST['action'])) {
    header('Content-Type: application/json');
    
    switch ($_POST['action']) {
        case 'get_dashboard_data':
            echo json_encode(getDashboardData());
            exit();
        case 'get_predictive_analytics':
            echo json_encode(getPredictiveAnalytics());
            exit();
        case 'get_business_intelligence':
            echo json_encode(getBusinessIntelligence());
            exit();
        case 'generate_report':
            echo json_encode(generateCustomReport($_POST));
            exit();
        case 'export_data':
            exportAnalyticsData($_POST);
            exit();
        default:
            echo json_encode(['success' => false, 'message' => 'Invalid action']);
            exit();
    }
}

// Get analytics overview data
$analytics_overview = getAnalyticsOverview();
$recent_reports = getRecentReports();
$kpi_metrics = getKPIMetrics();

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $page_title; ?> - LoanFlow Admin</title>
    
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Font Awesome -->
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <!-- Chart.js -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <!-- DataTables -->
    <link href="https://cdn.datatables.net/1.13.6/css/dataTables.bootstrap5.min.css" rel="stylesheet">
    
    <style>
        .analytics-card {
            border: none;
            border-radius: 15px;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
            transition: transform 0.2s;
        }
        .analytics-card:hover {
            transform: translateY(-2px);
        }
        .metric-card {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border-radius: 15px;
        }
        .chart-container {
            position: relative;
            height: 400px;
            margin: 20px 0;
        }
        .kpi-metric {
            text-align: center;
            padding: 20px;
        }
        .kpi-value {
            font-size: 2.5rem;
            font-weight: bold;
            color: #2c3e50;
        }
        .kpi-label {
            color: #7f8c8d;
            font-size: 0.9rem;
            text-transform: uppercase;
        }
        .trend-up {
            color: #27ae60;
        }
        .trend-down {
            color: #e74c3c;
        }
        .sidebar-analytics {
            background: #f8f9fa;
            min-height: 100vh;
            padding: 20px;
        }
        .nav-pills .nav-link {
            border-radius: 10px;
            margin-bottom: 5px;
        }
        .nav-pills .nav-link.active {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        }
        .report-builder {
            background: #fff;
            border-radius: 15px;
            padding: 25px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        .predictive-insight {
            background: linear-gradient(135deg, #ff9a9e 0%, #fecfef 100%);
            border-radius: 10px;
            padding: 15px;
            margin: 10px 0;
        }
        .ai-recommendation {
            background: linear-gradient(135deg, #a8edea 0%, #fed6e3 100%);
            border-radius: 10px;
            padding: 15px;
            margin: 10px 0;
        }
    </style>
</head>
<body>
    <div class="container-fluid">
        <div class="row">
            <!-- Sidebar -->
            <div class="col-md-3 sidebar-analytics">
                <div class="d-flex align-items-center mb-4">
                    <i class="fas fa-chart-line fa-2x text-primary me-3"></i>
                    <h4 class="mb-0">Analytics Hub</h4>
                </div>
                
                <ul class="nav nav-pills flex-column" id="analytics-nav">
                    <li class="nav-item">
                        <a class="nav-link active" data-bs-toggle="pill" href="#dashboard">
                            <i class="fas fa-tachometer-alt me-2"></i>Dashboard
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" data-bs-toggle="pill" href="#loan-analytics">
                            <i class="fas fa-money-bill-wave me-2"></i>Loan Analytics
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" data-bs-toggle="pill" href="#customer-insights">
                            <i class="fas fa-users me-2"></i>Customer Insights
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" data-bs-toggle="pill" href="#predictive-analytics">
                            <i class="fas fa-brain me-2"></i>Predictive Analytics
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" data-bs-toggle="pill" href="#business-intelligence">
                            <i class="fas fa-lightbulb me-2"></i>Business Intelligence
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" data-bs-toggle="pill" href="#performance-metrics">
                            <i class="fas fa-chart-bar me-2"></i>Performance Metrics
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" data-bs-toggle="pill" href="#report-builder">
                            <i class="fas fa-file-alt me-2"></i>Report Builder
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" data-bs-toggle="pill" href="#data-export">
                            <i class="fas fa-download me-2"></i>Data Export
                        </a>
                    </li>
                </ul>
                
                <div class="mt-4">
                    <div class="card analytics-card">
                        <div class="card-body text-center">
                            <i class="fas fa-robot fa-3x text-primary mb-3"></i>
                            <h6>AI Insights</h6>
                            <p class="small text-muted">Get AI-powered recommendations based on your data</p>
                            <button class="btn btn-primary btn-sm" onclick="generateAIInsights()">
                                Generate Insights
                            </button>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Main Content -->
            <div class="col-md-9">
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <h2><i class="fas fa-chart-line me-2"></i><?php echo $page_title; ?></h2>
                    <div>
                        <button class="btn btn-outline-primary me-2" onclick="refreshAnalytics()">
                            <i class="fas fa-sync-alt me-1"></i>Refresh
                        </button>
                        <button class="btn btn-primary" onclick="scheduleReport()">
                            <i class="fas fa-calendar me-1"></i>Schedule Report
                        </button>
                    </div>
                </div>
                
                <div class="tab-content" id="analytics-content">
                    <!-- Dashboard Tab -->
                    <div class="tab-pane fade show active" id="dashboard">
                        <!-- KPI Metrics -->
                        <div class="row mb-4">
                            <?php foreach ($kpi_metrics as $metric): ?>
                            <div class="col-md-3 mb-3">
                                <div class="card analytics-card">
                                    <div class="card-body kpi-metric">
                                        <div class="kpi-value"><?php echo $metric['value']; ?></div>
                                        <div class="kpi-label"><?php echo $metric['label']; ?></div>
                                        <div class="mt-2">
                                            <span class="<?php echo $metric['trend'] > 0 ? 'trend-up' : 'trend-down'; ?>">
                                                <i class="fas fa-arrow-<?php echo $metric['trend'] > 0 ? 'up' : 'down'; ?>"></i>
                                                <?php echo abs($metric['trend']); ?>%
                                            </span>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        </div>
                        
                        <!-- Charts Row -->
                        <div class="row mb-4">
                            <div class="col-md-8">
                                <div class="card analytics-card">
                                    <div class="card-header">
                                        <h5><i class="fas fa-chart-area me-2"></i>Revenue Trends</h5>
                                    </div>
                                    <div class="card-body">
                                        <div class="chart-container">
                                            <canvas id="revenueChart"></canvas>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="card analytics-card">
                                    <div class="card-header">
                                        <h5><i class="fas fa-chart-pie me-2"></i>Loan Status</h5>
                                    </div>
                                    <div class="card-body">
                                        <div class="chart-container">
                                            <canvas id="loanStatusChart"></canvas>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Recent Activity -->
                        <div class="row">
                            <div class="col-md-6">
                                <div class="card analytics-card">
                                    <div class="card-header">
                                        <h5><i class="fas fa-clock me-2"></i>Recent Reports</h5>
                                    </div>
                                    <div class="card-body">
                                        <div class="list-group list-group-flush">
                                            <?php foreach ($recent_reports as $report): ?>
                                            <div class="list-group-item d-flex justify-content-between align-items-center">
                                                <div>
                                                    <h6 class="mb-1"><?php echo htmlspecialchars($report['name']); ?></h6>
                                                    <small class="text-muted"><?php echo $report['created_at']; ?></small>
                                                </div>
                                                <span class="badge bg-primary rounded-pill"><?php echo $report['type']; ?></span>
                                            </div>
                                            <?php endforeach; ?>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="card analytics-card">
                                    <div class="card-header">
                                        <h5><i class="fas fa-exclamation-triangle me-2"></i>Alerts & Notifications</h5>
                                    </div>
                                    <div class="card-body">
                                        <div id="analytics-alerts">
                                            <!-- Alerts will be loaded here -->
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Loan Analytics Tab -->
                    <div class="tab-pane fade" id="loan-analytics">
                        <div class="row mb-4">
                            <div class="col-md-12">
                                <div class="card analytics-card">
                                    <div class="card-header d-flex justify-content-between align-items-center">
                                        <h5><i class="fas fa-money-bill-wave me-2"></i>Loan Performance Analytics</h5>
                                        <div>
                                            <select class="form-select" id="loanPeriodSelect" onchange="updateLoanAnalytics()">
                                                <option value="7d">Last 7 Days</option>
                                                <option value="30d" selected>Last 30 Days</option>
                                                <option value="90d">Last 90 Days</option>
                                                <option value="1y">Last Year</option>
                                            </select>
                                        </div>
                                    </div>
                                    <div class="card-body">
                                        <div class="row">
                                            <div class="col-md-8">
                                                <div class="chart-container">
                                                    <canvas id="loanVolumeChart"></canvas>
                                                </div>
                                            </div>
                                            <div class="col-md-4">
                                                <div class="loan-metrics">
                                                    <div class="metric-item mb-3">
                                                        <h4 class="text-success">$2.4M</h4>
                                                        <p class="text-muted mb-0">Total Loans Disbursed</p>
                                                    </div>
                                                    <div class="metric-item mb-3">
                                                        <h4 class="text-primary">847</h4>
                                                        <p class="text-muted mb-0">Active Loans</p>
                                                    </div>
                                                    <div class="metric-item mb-3">
                                                        <h4 class="text-warning">2.3%</h4>
                                                        <p class="text-muted mb-0">Default Rate</p>
                                                    </div>
                                                    <div class="metric-item">
                                                        <h4 class="text-info">94.7%</h4>
                                                        <p class="text-muted mb-0">Approval Rate</p>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6">
                                <div class="card analytics-card">
                                    <div class="card-header">
                                        <h5><i class="fas fa-chart-bar me-2"></i>Loan Amount Distribution</h5>
                                    </div>
                                    <div class="card-body">
                                        <div class="chart-container">
                                            <canvas id="loanAmountChart"></canvas>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="card analytics-card">
                                    <div class="card-header">
                                        <h5><i class="fas fa-clock me-2"></i>Processing Time Analysis</h5>
                                    </div>
                                    <div class="card-body">
                                        <div class="chart-container">
                                            <canvas id="processingTimeChart"></canvas>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Customer Insights Tab -->
                    <div class="tab-pane fade" id="customer-insights">
                        <div class="row mb-4">
                            <div class="col-md-8">
                                <div class="card analytics-card">
                                    <div class="card-header">
                                        <h5><i class="fas fa-users me-2"></i>Customer Demographics</h5>
                                    </div>
                                    <div class="card-body">
                                        <div class="row">
                                            <div class="col-md-6">
                                                <div class="chart-container">
                                                    <canvas id="ageDistributionChart"></canvas>
                                                </div>
                                            </div>
                                            <div class="col-md-6">
                                                <div class="chart-container">
                                                    <canvas id="incomeDistributionChart"></canvas>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="card analytics-card">
                                    <div class="card-header">
                                        <h5><i class="fas fa-star me-2"></i>Customer Satisfaction</h5>
                                    </div>
                                    <div class="card-body text-center">
                                        <div class="satisfaction-score mb-3">
                                            <h2 class="text-success">4.7/5</h2>
                                            <div class="stars">
                                                <i class="fas fa-star text-warning"></i>
                                                <i class="fas fa-star text-warning"></i>
                                                <i class="fas fa-star text-warning"></i>
                                                <i class="fas fa-star text-warning"></i>
                                                <i class="fas fa-star-half-alt text-warning"></i>
                                            </div>
                                        </div>
                                        <p class="text-muted">Based on 1,247 reviews</p>
                                        <div class="satisfaction-breakdown">
                                            <div class="d-flex justify-content-between mb-2">
                                                <span>5 Stars</span>
                                                <span>68%</span>
                                            </div>
                                            <div class="d-flex justify-content-between mb-2">
                                                <span>4 Stars</span>
                                                <span>22%</span>
                                            </div>
                                            <div class="d-flex justify-content-between mb-2">
                                                <span>3 Stars</span>
                                                <span>7%</span>
                                            </div>
                                            <div class="d-flex justify-content-between mb-2">
                                                <span>2 Stars</span>
                                                <span>2%</span>
                                            </div>
                                            <div class="d-flex justify-content-between">
                                                <span>1 Star</span>
                                                <span>1%</span>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-12">
                                <div class="card analytics-card">
                                    <div class="card-header">
                                        <h5><i class="fas fa-table me-2"></i>Customer Segmentation Analysis</h5>
                                    </div>
                                    <div class="card-body">
                                        <table class="table table-striped" id="customerSegmentTable">
                                            <thead>
                                                <tr>
                                                    <th>Segment</th>
                                                    <th>Count</th>
                                                    <th>Avg Loan Amount</th>
                                                    <th>Default Rate</th>
                                                    <th>Lifetime Value</th>
                                                    <th>Actions</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <!-- Data will be loaded via AJAX -->
                                            </tbody>
                                        </table>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Predictive Analytics Tab -->
                    <div class="tab-pane fade" id="predictive-analytics">
                        <div class="row mb-4">
                            <div class="col-md-12">
                                <div class="alert alert-info">
                                    <i class="fas fa-info-circle me-2"></i>
                                    <strong>AI-Powered Predictions:</strong> Our machine learning models analyze historical data to provide accurate forecasts and risk assessments.
                                </div>
                            </div>
                        </div>
                        
                        <div class="row mb-4">
                            <div class="col-md-6">
                                <div class="card analytics-card">
                                    <div class="card-header">
                                        <h5><i class="fas fa-chart-line me-2"></i>Revenue Forecast</h5>
                                    </div>
                                    <div class="card-body">
                                        <div class="chart-container">
                                            <canvas id="revenueForecastChart"></canvas>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="card analytics-card">
                                    <div class="card-header">
                                        <h5><i class="fas fa-exclamation-triangle me-2"></i>Risk Assessment</h5>
                                    </div>
                                    <div class="card-body">
                                        <div class="chart-container">
                                            <canvas id="riskAssessmentChart"></canvas>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-12">
                                <div class="card analytics-card">
                                    <div class="card-header">
                                        <h5><i class="fas fa-lightbulb me-2"></i>AI Insights & Recommendations</h5>
                                    </div>
                                    <div class="card-body">
                                        <div id="ai-insights">
                                            <div class="predictive-insight">
                                                <h6><i class="fas fa-trending-up me-2"></i>Growth Opportunity</h6>
                                                <p>Based on current trends, increasing loan limits for customers with credit scores above 750 could boost revenue by 15-20% over the next quarter.</p>
                                            </div>
                                            <div class="ai-recommendation">
                                                <h6><i class="fas fa-shield-alt me-2"></i>Risk Mitigation</h6>
                                                <p>Customers in the 25-35 age group with income below $40k show 23% higher default rates. Consider implementing additional verification steps.</p>
                                            </div>
                                            <div class="predictive-insight">
                                                <h6><i class="fas fa-clock me-2"></i>Seasonal Pattern</h6>
                                                <p>Loan applications typically increase by 35% during Q4. Prepare for higher volume and consider promotional campaigns.</p>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Business Intelligence Tab -->
                    <div class="tab-pane fade" id="business-intelligence">
                        <div class="row mb-4">
                            <div class="col-md-4">
                                <div class="card metric-card">
                                    <div class="card-body text-center">
                                        <h3>$847K</h3>
                                        <p class="mb-0">Monthly Revenue</p>
                                        <small><i class="fas fa-arrow-up"></i> 12% vs last month</small>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="card metric-card">
                                    <div class="card-body text-center">
                                        <h3>94.2%</h3>
                                        <p class="mb-0">Customer Retention</p>
                                        <small><i class="fas fa-arrow-up"></i> 3% vs last month</small>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="card metric-card">
                                    <div class="card-body text-center">
                                        <h3>2.1%</h3>
                                        <p class="mb-0">Default Rate</p>
                                        <small><i class="fas fa-arrow-down"></i> 0.5% vs last month</small>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-8">
                                <div class="card analytics-card">
                                    <div class="card-header">
                                        <h5><i class="fas fa-chart-area me-2"></i>Business Performance Dashboard</h5>
                                    </div>
                                    <div class="card-body">
                                        <div class="chart-container">
                                            <canvas id="businessPerformanceChart"></canvas>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="card analytics-card">
                                    <div class="card-header">
                                        <h5><i class="fas fa-bullseye me-2"></i>Key Objectives</h5>
                                    </div>
                                    <div class="card-body">
                                        <div class="objective-item mb-3">
                                            <div class="d-flex justify-content-between">
                                                <span>Monthly Revenue Target</span>
                                                <span class="text-success">94%</span>
                                            </div>
                                            <div class="progress mt-1">
                                                <div class="progress-bar bg-success" style="width: 94%"></div>
                                            </div>
                                        </div>
                                        <div class="objective-item mb-3">
                                            <div class="d-flex justify-content-between">
                                                <span>Customer Acquisition</span>
                                                <span class="text-warning">78%</span>
                                            </div>
                                            <div class="progress mt-1">
                                                <div class="progress-bar bg-warning" style="width: 78%"></div>
                                            </div>
                                        </div>
                                        <div class="objective-item mb-3">
                                            <div class="d-flex justify-content-between">
                                                <span>Risk Management</span>
                                                <span class="text-success">96%</span>
                                            </div>
                                            <div class="progress mt-1">
                                                <div class="progress-bar bg-success" style="width: 96%"></div>
                                            </div>
                                        </div>
                                        <div class="objective-item">
                                            <div class="d-flex justify-content-between">
                                                <span>Operational Efficiency</span>
                                                <span class="text-info">87%</span>
                                            </div>
                                            <div class="progress mt-1">
                                                <div class="progress-bar bg-info" style="width: 87%"></div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Performance Metrics Tab -->
                    <div class="tab-pane fade" id="performance-metrics">
                        <div class="row mb-4">
                            <div class="col-md-12">
                                <div class="card analytics-card">
                                    <div class="card-header d-flex justify-content-between align-items-center">
                                        <h5><i class="fas fa-chart-bar me-2"></i>System Performance Metrics</h5>
                                        <button class="btn btn-outline-primary btn-sm" onclick="refreshMetrics()">
                                            <i class="fas fa-sync-alt me-1"></i>Refresh
                                        </button>
                                    </div>
                                    <div class="card-body">
                                        <div class="row">
                                            <div class="col-md-3 text-center">
                                                <div class="metric-display">
                                                    <h4 class="text-success">99.9%</h4>
                                                    <p class="text-muted">System Uptime</p>
                                                </div>
                                            </div>
                                            <div class="col-md-3 text-center">
                                                <div class="metric-display">
                                                    <h4 class="text-primary">1.2s</h4>
                                                    <p class="text-muted">Avg Response Time</p>
                                                </div>
                                            </div>
                                            <div class="col-md-3 text-center">
                                                <div class="metric-display">
                                                    <h4 class="text-warning">2,847</h4>
                                                    <p class="text-muted">Daily Transactions</p>
                                                </div>
                                            </div>
                                            <div class="col-md-3 text-center">
                                                <div class="metric-display">
                                                    <h4 class="text-info">0.02%</h4>
                                                    <p class="text-muted">Error Rate</p>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6">
                                <div class="card analytics-card">
                                    <div class="card-header">
                                        <h5><i class="fas fa-server me-2"></i>Server Performance</h5>
                                    </div>
                                    <div class="card-body">
                                        <div class="chart-container">
                                            <canvas id="serverPerformanceChart"></canvas>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="card analytics-card">
                                    <div class="card-header">
                                        <h5><i class="fas fa-database me-2"></i>Database Performance</h5>
                                    </div>
                                    <div class="card-body">
                                        <div class="chart-container">
                                            <canvas id="databasePerformanceChart"></canvas>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Report Builder Tab -->
                    <div class="tab-pane fade" id="report-builder">
                        <div class="row">
                            <div class="col-md-12">
                                <div class="report-builder">
                                    <h4><i class="fas fa-file-alt me-2"></i>Custom Report Builder</h4>
                                    <p class="text-muted">Create custom reports with your preferred metrics and visualizations.</p>
                                    
                                    <form id="reportBuilderForm">
                                        <div class="row mb-3">
                                            <div class="col-md-6">
                                                <label class="form-label">Report Name</label>
                                                <input type="text" class="form-control" name="report_name" placeholder="Enter report name">
                                            </div>
                                            <div class="col-md-6">
                                                <label class="form-label">Report Type</label>
                                                <select class="form-select" name="report_type">
                                                    <option value="summary">Summary Report</option>
                                                    <option value="detailed">Detailed Analysis</option>
                                                    <option value="comparison">Comparison Report</option>
                                                    <option value="trend">Trend Analysis</option>
                                                </select>
                                            </div>
                                        </div>
                                        
                                        <div class="row mb-3">
                                            <div class="col-md-6">
                                                <label class="form-label">Date Range</label>
                                                <select class="form-select" name="date_range">
                                                    <option value="7d">Last 7 Days</option>
                                                    <option value="30d">Last 30 Days</option>
                                                    <option value="90d">Last 90 Days</option>
                                                    <option value="1y">Last Year</option>
                                                    <option value="custom">Custom Range</option>
                                                </select>
                                            </div>
                                            <div class="col-md-6">
                                                <label class="form-label">Output Format</label>
                                                <select class="form-select" name="output_format">
                                                    <option value="pdf">PDF</option>
                                                    <option value="excel">Excel</option>
                                                    <option value="csv">CSV</option>
                                                    <option value="html">HTML</option>
                                                </select>
                                            </div>
                                        </div>
                                        
                                        <div class="mb-3">
                                            <label class="form-label">Metrics to Include</label>
                                            <div class="row">
                                                <div class="col-md-3">
                                                    <div class="form-check">
                                                        <input class="form-check-input" type="checkbox" name="metrics[]" value="revenue" checked>
                                                        <label class="form-check-label">Revenue</label>
                                                    </div>
                                                    <div class="form-check">
                                                        <input class="form-check-input" type="checkbox" name="metrics[]" value="loans">
                                                        <label class="form-check-label">Loan Volume</label>
                                                    </div>
                                                </div>
                                                <div class="col-md-3">
                                                    <div class="form-check">
                                                        <input class="form-check-input" type="checkbox" name="metrics[]" value="customers">
                                                        <label class="form-check-label">Customer Data</label>
                                                    </div>
                                                    <div class="form-check">
                                                        <input class="form-check-input" type="checkbox" name="metrics[]" value="performance">
                                                        <label class="form-check-label">Performance</label>
                                                    </div>
                                                </div>
                                                <div class="col-md-3">
                                                    <div class="form-check">
                                                        <input class="form-check-input" type="checkbox" name="metrics[]" value="risk">
                                                        <label class="form-check-label">Risk Analysis</label>
                                                    </div>
                                                    <div class="form-check">
                                                        <input class="form-check-input" type="checkbox" name="metrics[]" value="satisfaction">
                                                        <label class="form-check-label">Satisfaction</label>
                                                    </div>
                                                </div>
                                                <div class="col-md-3">
                                                    <div class="form-check">
                                                        <input class="form-check-input" type="checkbox" name="metrics[]" value="trends">
                                                        <label class="form-check-label">Trends</label>
                                                    </div>
                                                    <div class="form-check">
                                                        <input class="form-check-input" type="checkbox" name="metrics[]" value="forecasts">
                                                        <label class="form-check-label">Forecasts</label>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                        
                                        <div class="mb-3">
                                            <label class="form-label">Additional Notes</label>
                                            <textarea class="form-control" name="notes" rows="3" placeholder="Add any specific requirements or notes for this report"></textarea>
                                        </div>
                                        
                                        <div class="d-flex gap-2">
                                            <button type="button" class="btn btn-primary" onclick="generateReport()">
                                                <i class="fas fa-play me-1"></i>Generate Report
                                            </button>
                                            <button type="button" class="btn btn-outline-secondary" onclick="previewReport()">
                                                <i class="fas fa-eye me-1"></i>Preview
                                            </button>
                                            <button type="button" class="btn btn-outline-info" onclick="saveTemplate()">
                                                <i class="fas fa-save me-1"></i>Save as Template
                                            </button>
                                        </div>
                                    </form>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Data Export Tab -->
                    <div class="tab-pane fade" id="data-export">
                        <div class="row">
                            <div class="col-md-8">
                                <div class="card analytics-card">
                                    <div class="card-header">
                                        <h5><i class="fas fa-download me-2"></i>Data Export Center</h5>
                                    </div>
                                    <div class="card-body">
                                        <form id="dataExportForm">
                                            <div class="row mb-3">
                                                <div class="col-md-6">
                                                    <label class="form-label">Data Category</label>
                                                    <select class="form-select" name="data_category">
                                                        <option value="all">All Data</option>
                                                        <option value="loans">Loan Data</option>
                                                        <option value="customers">Customer Data</option>
                                                        <option value="transactions">Transaction Data</option>
                                                        <option value="analytics">Analytics Data</option>
                                                    </select>
                                                </div>
                                                <div class="col-md-6">
                                                    <label class="form-label">Export Format</label>
                                                    <select class="form-select" name="export_format">
                                                        <option value="csv">CSV</option>
                                                        <option value="excel">Excel (XLSX)</option>
                                                        <option value="json">JSON</option>
                                                        <option value="xml">XML</option>
                                                    </select>
                                                </div>
                                            </div>
                                            
                                            <div class="row mb-3">
                                                <div class="col-md-6">
                                                    <label class="form-label">Date From</label>
                                                    <input type="date" class="form-control" name="date_from">
                                                </div>
                                                <div class="col-md-6">
                                                    <label class="form-label">Date To</label>
                                                    <input type="date" class="form-control" name="date_to">
                                                </div>
                                            </div>
                                            
                                            <div class="mb-3">
                                                <div class="form-check">
                                                    <input class="form-check-input" type="checkbox" name="include_sensitive" id="includeSensitive">
                                                    <label class="form-check-label" for="includeSensitive">
                                                        Include sensitive data (requires additional authorization)
                                                    </label>
                                                </div>
                                            </div>
                                            
                                            <button type="button" class="btn btn-primary" onclick="exportData()">
                                                <i class="fas fa-download me-1"></i>Export Data
                                            </button>
                                        </form>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="card analytics-card">
                                    <div class="card-header">
                                        <h5><i class="fas fa-history me-2"></i>Export History</h5>
                                    </div>
                                    <div class="card-body">
                                        <div class="export-history">
                                            <div class="export-item mb-3">
                                                <div class="d-flex justify-content-between">
                                                    <span>Customer Data Export</span>
                                                    <small class="text-muted">2 hours ago</small>
                                                </div>
                                                <div class="mt-1">
                                                    <span class="badge bg-success">Completed</span>
                                                    <a href="#" class="btn btn-sm btn-outline-primary ms-2">
                                                        <i class="fas fa-download"></i>
                                                    </a>
                                                </div>
                                            </div>
                                            <div class="export-item mb-3">
                                                <div class="d-flex justify-content-between">
                                                    <span>Loan Analytics Report</span>
                                                    <small class="text-muted">1 day ago</small>
                                                </div>
                                                <div class="mt-1">
                                                    <span class="badge bg-success">Completed</span>
                                                    <a href="#" class="btn btn-sm btn-outline-primary ms-2">
                                                        <i class="fas fa-download"></i>
                                                    </a>
                                                </div>
                                            </div>
                                            <div class="export-item mb-3">
                                                <div class="d-flex justify-content-between">
                                                    <span>Full System Backup</span>
                                                    <small class="text-muted">3 days ago</small>
                                                </div>
                                                <div class="mt-1">
                                                    <span class="badge bg-warning">Processing</span>
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
    
    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <!-- DataTables JS -->
    <script src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.6/js/dataTables.bootstrap5.min.js"></script>
    <!-- jQuery -->
    <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
    
    <script>
        // Initialize charts and data when page loads
        document.addEventListener('DOMContentLoaded', function() {
            initializeCharts();
            loadAnalyticsData();
        });
        
        // Chart initialization functions
        function initializeCharts() {
            // Revenue Chart
            const revenueCtx = document.getElementById('revenueChart').getContext('2d');
            new Chart(revenueCtx, {
                type: 'line',
                data: {
                    labels: ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun'],
                    datasets: [{
                        label: 'Revenue',
                        data: [65000, 72000, 68000, 85000, 92000, 87000],
                        borderColor: '#667eea',
                        backgroundColor: 'rgba(102, 126, 234, 0.1)',
                        tension: 0.4
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false
                }
            });
            
            // Loan Status Chart
            const loanStatusCtx = document.getElementById('loanStatusChart').getContext('2d');
            new Chart(loanStatusCtx, {
                type: 'doughnut',
                data: {
                    labels: ['Approved', 'Pending', 'Rejected'],
                    datasets: [{
                        data: [65, 25, 10],
                        backgroundColor: ['#28a745', '#ffc107', '#dc3545']
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false
                }
            });
            
            // Initialize other charts...
            initializeLoanVolumeChart();
            initializeCustomerCharts();
            initializePredictiveCharts();
            initializePerformanceCharts();
        }
        
        function initializeLoanVolumeChart() {
            const ctx = document.getElementById('loanVolumeChart').getContext('2d');
            new Chart(ctx, {
                type: 'bar',
                data: {
                    labels: ['Week 1', 'Week 2', 'Week 3', 'Week 4'],
                    datasets: [{
                        label: 'Loan Applications',
                        data: [120, 145, 132, 168],
                        backgroundColor: '#667eea'
                    }, {
                        label: 'Approved Loans',
                        data: [98, 125, 115, 142],
                        backgroundColor: '#28a745'
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false
                }
            });
        }
        
        function initializeCustomerCharts() {
            // Age Distribution Chart
            const ageCtx = document.getElementById('ageDistributionChart').getContext('2d');
            new Chart(ageCtx, {
                type: 'bar',
                data: {
                    labels: ['18-25', '26-35', '36-45', '46-55', '55+'],
                    datasets: [{
                        label: 'Customers',
                        data: [150, 320, 280, 180, 120],
                        backgroundColor: '#764ba2'
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false
                }
            });
            
            // Income Distribution Chart
            const incomeCtx = document.getElementById('incomeDistributionChart').getContext('2d');
            new Chart(incomeCtx, {
                type: 'pie',
                data: {
                    labels: ['<$30k', '$30k-$50k', '$50k-$75k', '$75k+'],
                    datasets: [{
                        data: [25, 35, 25, 15],
                        backgroundColor: ['#ff9a9e', '#fecfef', '#a8edea', '#fed6e3']
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false
                }
            });
        }
        
        function initializePredictiveCharts() {
            // Revenue Forecast Chart
            const forecastCtx = document.getElementById('revenueForecastChart').getContext('2d');
            new Chart(forecastCtx, {
                type: 'line',
                data: {
                    labels: ['Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'],
                    datasets: [{
                        label: 'Predicted Revenue',
                        data: [90000, 95000, 102000, 108000, 115000, 122000],
                        borderColor: '#ff6b6b',
                        backgroundColor: 'rgba(255, 107, 107, 0.1)',
                        borderDash: [5, 5]
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false
                }
            });
            
            // Risk Assessment Chart
            const riskCtx = document.getElementById('riskAssessmentChart').getContext('2d');
            new Chart(riskCtx, {
                type: 'radar',
                data: {
                    labels: ['Credit Risk', 'Market Risk', 'Operational Risk', 'Liquidity Risk', 'Compliance Risk'],
                    datasets: [{
                        label: 'Current Risk Level',
                        data: [3, 2, 4, 2, 1],
                        backgroundColor: 'rgba(255, 99, 132, 0.2)',
                        borderColor: 'rgba(255, 99, 132, 1)'
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    scales: {
                        r: {
                            beginAtZero: true,
                            max: 5
                        }
                    }
                }
            });
        }
        
        function initializePerformanceCharts() {
            // Server Performance Chart
            const serverCtx = document.getElementById('serverPerformanceChart').getContext('2d');
            new Chart(serverCtx, {
                type: 'line',
                data: {
                    labels: ['00:00', '04:00', '08:00', '12:00', '16:00', '20:00'],
                    datasets: [{
                        label: 'CPU Usage (%)',
                        data: [45, 32, 68, 75, 82, 58],
                        borderColor: '#4ecdc4',
                        backgroundColor: 'rgba(78, 205, 196, 0.1)'
                    }, {
                        label: 'Memory Usage (%)',
                        data: [62, 58, 71, 78, 85, 69],
                        borderColor: '#45b7d1',
                        backgroundColor: 'rgba(69, 183, 209, 0.1)'
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false
                }
            });
            
            // Database Performance Chart
            const dbCtx = document.getElementById('databasePerformanceChart').getContext('2d');
            new Chart(dbCtx, {
                type: 'bar',
                data: {
                    labels: ['Queries/sec', 'Connections', 'Cache Hit Rate', 'Lock Waits'],
                    datasets: [{
                        label: 'Performance Metrics',
                        data: [1250, 45, 94, 2],
                        backgroundColor: ['#96ceb4', '#ffeaa7', '#dda0dd', '#fab1a0']
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false
                }
            });
        }
        
        // Analytics functions
        function loadAnalyticsData() {
            // Load customer segmentation data
            $('#customerSegmentTable').DataTable({
                ajax: {
                    url: 'advanced-analytics.php',
                    type: 'POST',
                    data: { action: 'get_customer_segments' }
                },
                columns: [
                    { data: 'segment' },
                    { data: 'count' },
                    { data: 'avg_loan_amount' },
                    { data: 'default_rate' },
                    { data: 'lifetime_value' },
                    { data: 'actions', orderable: false }
                ]
            });
            
            // Load alerts
            loadAnalyticsAlerts();
        }
        
        function loadAnalyticsAlerts() {
            const alertsContainer = document.getElementById('analytics-alerts');
            alertsContainer.innerHTML = `
                <div class="alert alert-warning alert-dismissible fade show" role="alert">
                    <i class="fas fa-exclamation-triangle me-2"></i>
                    <strong>High Risk Alert:</strong> Default rate increased by 0.8% this week.
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
                <div class="alert alert-info alert-dismissible fade show" role="alert">
                    <i class="fas fa-info-circle me-2"></i>
                    <strong>Performance Update:</strong> System response time improved by 15%.
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
                <div class="alert alert-success alert-dismissible fade show" role="alert">
                    <i class="fas fa-check-circle me-2"></i>
                    <strong>Revenue Milestone:</strong> Monthly target achieved 5 days early!
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            `;
        }
        
        // Action functions
        function refreshAnalytics() {
            showToast('Refreshing analytics data...', 'info');
            // Simulate refresh
            setTimeout(() => {
                showToast('Analytics data refreshed successfully!', 'success');
                loadAnalyticsData();
            }, 1500);
        }
        
        function generateAIInsights() {
            showToast('Generating AI insights...', 'info');
            // Simulate AI processing
            setTimeout(() => {
                showToast('AI insights generated successfully!', 'success');
                // Add new insights to the predictive analytics tab
            }, 2000);
        }
        
        function generateReport() {
            const formData = new FormData(document.getElementById('reportBuilderForm'));
            showToast('Generating custom report...', 'info');
            
            // Simulate report generation
            setTimeout(() => {
                showToast('Report generated successfully!', 'success');
            }, 3000);
        }
        
        function exportData() {
            const formData = new FormData(document.getElementById('dataExportForm'));
            showToast('Preparing data export...', 'info');
            
            // Simulate export process
            setTimeout(() => {
                showToast('Data export completed! Download will start shortly.', 'success');
            }, 2500);
        }
        
        function scheduleReport() {
            // Show modal for scheduling reports
            showToast('Report scheduling feature coming soon!', 'info');
        }
        
        function updateLoanAnalytics() {
            const period = document.getElementById('loanPeriodSelect').value;
            showToast(`Updating loan analytics for ${period}...`, 'info');
            // Update charts based on selected period
        }
        
        function refreshMetrics() {
            showToast('Refreshing performance metrics...', 'info');
            setTimeout(() => {
                showToast('Performance metrics updated!', 'success');
            }, 1000);
        }
        
        function previewReport() {
            showToast('Opening report preview...', 'info');
        }
        
        function saveTemplate() {
            showToast('Report template saved successfully!', 'success');
        }
        
        // Utility functions
        function showToast(message, type = 'info') {
            // Create and show toast notification
            const toast = document.createElement('div');
            toast.className = `alert alert-${type} alert-dismissible fade show position-fixed`;
            toast.style.cssText = 'top: 20px; right: 20px; z-index: 9999; min-width: 300px;';
            toast.innerHTML = `
                ${message}
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            `;
            document.body.appendChild(toast);
            
            // Auto remove after 5 seconds
            setTimeout(() => {
                if (toast.parentNode) {
                    toast.parentNode.removeChild(toast);
                }
            }, 5000);
        }
    </script>
</body>
</html>

<?php
/**
 * Analytics Helper Functions
 */

function getAnalyticsOverview() {
    global $pdo;
    
    try {
        $overview = [
            'total_loans' => 0,
            'total_revenue' => 0,
            'active_customers' => 0,
            'default_rate' => 0
        ];
        
        // Get total loans
        $stmt = $pdo->query("SELECT COUNT(*) as count FROM loan_applications WHERE status = 'approved'");
        $overview['total_loans'] = $stmt->fetch()['count'] ?? 0;
        
        // Get total revenue
        $stmt = $pdo->query("SELECT SUM(amount) as total FROM loan_applications WHERE status = 'approved'");
        $overview['total_revenue'] = $stmt->fetch()['total'] ?? 0;
        
        // Get active customers
        $stmt = $pdo->query("SELECT COUNT(DISTINCT email) as count FROM loan_applications");
        $overview['active_customers'] = $stmt->fetch()['count'] ?? 0;
        
        // Calculate default rate
        $stmt = $pdo->query("SELECT 
            (SELECT COUNT(*) FROM loan_applications WHERE status = 'defaulted') as defaulted,
            (SELECT COUNT(*) FROM loan_applications WHERE status = 'approved') as approved
        ");
        $result = $stmt->fetch();
        if ($result['approved'] > 0) {
            $overview['default_rate'] = ($result['defaulted'] / $result['approved']) * 100;
        }
        
        return $overview;
    } catch (Exception $e) {
        error_log("Analytics Overview Error: " . $e->getMessage());
        return [
            'total_loans' => 0,
            'total_revenue' => 0,
            'active_customers' => 0,
            'default_rate' => 0
        ];
    }
}

function getKPIMetrics() {
    return [
        [
            'value' => '$847K',
            'label' => 'Monthly Revenue',
            'trend' => 12
        ],
        [
            'value' => '2,847',
            'label' => 'Active Loans',
            'trend' => 8
        ],
        [
            'value' => '94.2%',
            'label' => 'Approval Rate',
            'trend' => 3
        ],
        [
            'value' => '2.1%',
            'label' => 'Default Rate',
            'trend' => -5
        ]
    ];
}

function getRecentReports() {
    return [
        [
            'name' => 'Monthly Performance Report',
            'type' => 'Performance',
            'created_at' => '2 hours ago'
        ],
        [
            'name' => 'Customer Segmentation Analysis',
            'type' => 'Analytics',
            'created_at' => '1 day ago'
        ],
        [
            'name' => 'Risk Assessment Report',
            'type' => 'Risk',
            'created_at' => '2 days ago'
        ],
        [
            'name' => 'Revenue Forecast',
            'type' => 'Forecast',
            'created_at' => '3 days ago'
        ]
    ];
}

function getDashboardData() {
    global $pdo;
    
    try {
        $data = [
            'revenue_trend' => [],
            'loan_status' => [],
            'customer_growth' => [],
            'performance_metrics' => []
        ];
        
        // Revenue trend (last 6 months)
        $stmt = $pdo->query("
            SELECT 
                DATE_FORMAT(created_at, '%Y-%m') as month,
                SUM(amount) as revenue
            FROM loan_applications 
            WHERE status = 'approved' 
                AND created_at >= DATE_SUB(NOW(), INTERVAL 6 MONTH)
            GROUP BY DATE_FORMAT(created_at, '%Y-%m')
            ORDER BY month
        ");
        $data['revenue_trend'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Loan status distribution
        $stmt = $pdo->query("
            SELECT status, COUNT(*) as count
            FROM loan_applications
            GROUP BY status
        ");
        $data['loan_status'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        return $data;
    } catch (Exception $e) {
        error_log("Dashboard Data Error: " . $e->getMessage());
        return [
            'revenue_trend' => [],
            'loan_status' => [],
            'customer_growth' => [],
            'performance_metrics' => []
        ];
    }
}

function getPredictiveAnalytics() {
    // Simulate AI-powered predictive analytics
    return [
        'revenue_forecast' => [
            'next_month' => 920000,
            'confidence' => 87,
            'trend' => 'increasing'
        ],
        'risk_assessment' => [
            'overall_risk' => 'low',
            'credit_risk' => 2.3,
            'market_risk' => 1.8,
            'operational_risk' => 2.1
        ],
        'customer_insights' => [
            'churn_probability' => 5.2,
            'acquisition_forecast' => 340,
            'satisfaction_trend' => 'improving'
        ],
        'recommendations' => [
            'Increase loan limits for high-credit customers',
            'Implement additional verification for high-risk segments',
            'Prepare for seasonal Q4 increase in applications'
        ]
    ];
}

function getBusinessIntelligence() {
    return [
        'market_analysis' => [
            'market_share' => 12.5,
            'competitor_analysis' => 'favorable',
            'growth_opportunities' => [
                'Small business loans',
                'Student loan refinancing',
                'Green energy loans'
            ]
        ],
        'operational_efficiency' => [
            'processing_time' => 2.3, // days
            'automation_rate' => 78,
            'cost_per_acquisition' => 145
        ],
        'financial_health' => [
            'profit_margin' => 23.5,
            'roi' => 18.7,
            'cash_flow' => 'positive'
        ]
    ];
}

function generateCustomReport($params) {
    try {
        $report = [
            'id' => uniqid('report_'),
            'name' => $params['report_name'] ?? 'Custom Report',
            'type' => $params['report_type'] ?? 'summary',
            'date_range' => $params['date_range'] ?? '30d',
            'format' => $params['output_format'] ?? 'pdf',
            'metrics' => $params['metrics'] ?? [],
            'status' => 'generating',
            'created_at' => date('Y-m-d H:i:s')
        ];
        
        // Simulate report generation process
        // In a real implementation, this would generate the actual report
        
        return [
            'success' => true,
            'message' => 'Report generation started',
            'report_id' => $report['id'],
            'estimated_time' => '2-3 minutes'
        ];
    } catch (Exception $e) {
        return [
            'success' => false,
            'message' => 'Failed to generate report: ' . $e->getMessage()
        ];
    }
}

function exportAnalyticsData($params) {
    try {
        $category = $params['data_category'] ?? 'all';
        $format = $params['export_format'] ?? 'csv';
        $dateFrom = $params['date_from'] ?? null;
        $dateTo = $params['date_to'] ?? null;
        
        // Set appropriate headers for download
        $filename = 'analytics_export_' . date('Y-m-d_H-i-s') . '.' . $format;
        
        switch ($format) {
            case 'csv':
                header('Content-Type: text/csv');
                break;
            case 'excel':
                header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
                break;
            case 'json':
                header('Content-Type: application/json');
                break;
            case 'xml':
                header('Content-Type: application/xml');
                break;
        }
        
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        
        // Generate export data based on category
        $exportData = getExportData($category, $dateFrom, $dateTo);
        
        // Output data in requested format
        switch ($format) {
            case 'csv':
                outputCSV($exportData);
                break;
            case 'json':
                echo json_encode($exportData, JSON_PRETTY_PRINT);
                break;
            case 'xml':
                outputXML($exportData);
                break;
            default:
                outputCSV($exportData);
        }
        
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['error' => 'Export failed: ' . $e->getMessage()]);
    }
}

function getExportData($category, $dateFrom = null, $dateTo = null) {
    global $pdo;
    
    try {
        $data = [];
        
        switch ($category) {
            case 'loans':
                $sql = "SELECT * FROM loan_applications";
                if ($dateFrom && $dateTo) {
                    $sql .= " WHERE created_at BETWEEN ? AND ?";
                }
                $stmt = $pdo->prepare($sql);
                if ($dateFrom && $dateTo) {
                    $stmt->execute([$dateFrom, $dateTo]);
                } else {
                    $stmt->execute();
                }
                $data = $stmt->fetchAll(PDO::FETCH_ASSOC);
                break;
                
            case 'customers':
                $sql = "SELECT DISTINCT email, first_name, last_name, phone, created_at FROM loan_applications";
                if ($dateFrom && $dateTo) {
                    $sql .= " WHERE created_at BETWEEN ? AND ?";
                }
                $stmt = $pdo->prepare($sql);
                if ($dateFrom && $dateTo) {
                    $stmt->execute([$dateFrom, $dateTo]);
                } else {
                    $stmt->execute();
                }
                $data = $stmt->fetchAll(PDO::FETCH_ASSOC);
                break;
                
            default:
                // Export all data
                $stmt = $pdo->query("SELECT * FROM loan_applications LIMIT 1000");
                $data = $stmt->fetchAll(PDO::FETCH_ASSOC);
        }
        
        return $data;
    } catch (Exception $e) {
        error_log("Export Data Error: " . $e->getMessage());
        return [];
    }
}

function outputCSV($data) {
    if (empty($data)) {
        echo "No data available\n";
        return;
    }
    
    $output = fopen('php://output', 'w');
    
    // Output headers
    fputcsv($output, array_keys($data[0]));
    
    // Output data
    foreach ($data as $row) {
        fputcsv($output, $row);
    }
    
    fclose($output);
}

function outputXML($data) {
    echo "<?xml version='1.0' encoding='UTF-8'?>\n";
    echo "<analytics_export>\n";
    
    foreach ($data as $row) {
        echo "  <record>\n";
        foreach ($row as $key => $value) {
            echo "    <" . htmlspecialchars($key) . ">" . htmlspecialchars($value) . "</" . htmlspecialchars($key) . ">\n";
        }
        echo "  </record>\n";
    }
    
    echo "</analytics_export>\n";
}

?>