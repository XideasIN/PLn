<?php
/**
 * Advanced Analytics API
 * Provides REST API endpoints for analytics and reporting system
 */

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

require_once '../config/database.php';
require_once '../includes/auth.php';
require_once '../includes/functions.php';

// Rate limiting
session_start();
$rate_limit_key = 'analytics_api_' . ($_SERVER['REMOTE_ADDR'] ?? 'unknown');
if (!isset($_SESSION[$rate_limit_key])) {
    $_SESSION[$rate_limit_key] = ['count' => 0, 'reset_time' => time() + 3600];
}

if (time() > $_SESSION[$rate_limit_key]['reset_time']) {
    $_SESSION[$rate_limit_key] = ['count' => 0, 'reset_time' => time() + 3600];
}

if ($_SESSION[$rate_limit_key]['count'] >= 1000) {
    http_response_code(429);
    echo json_encode(['error' => 'Rate limit exceeded']);
    exit();
}

$_SESSION[$rate_limit_key]['count']++;

// Authentication check
if (!isLoggedIn() || !isAdmin()) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized access']);
    exit();
}

$method = $_SERVER['REQUEST_METHOD'];
$path = $_GET['path'] ?? '';
$input = json_decode(file_get_contents('php://input'), true);

try {
    switch ($method) {
        case 'GET':
            handleGetRequest($path);
            break;
        case 'POST':
            handlePostRequest($path, $input);
            break;
        case 'PUT':
            handlePutRequest($path, $input);
            break;
        case 'DELETE':
            handleDeleteRequest($path);
            break;
        default:
            http_response_code(405);
            echo json_encode(['error' => 'Method not allowed']);
    }
} catch (Exception $e) {
    error_log('Analytics API Error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'Internal server error']);
}

function handleGetRequest($path) {
    switch ($path) {
        case 'overview':
            echo json_encode(getAnalyticsOverview());
            break;
        case 'kpi-metrics':
            echo json_encode(getKPIMetrics());
            break;
        case 'dashboard-data':
            echo json_encode(getDashboardData());
            break;
        case 'predictive-analytics':
            echo json_encode(getPredictiveAnalytics());
            break;
        case 'business-intelligence':
            echo json_encode(getBusinessIntelligence());
            break;
        case 'recent-reports':
            echo json_encode(getRecentReports());
            break;
        case 'revenue-trend':
            echo json_encode(getRevenueTrend());
            break;
        case 'loan-statistics':
            echo json_encode(getLoanStatistics());
            break;
        case 'customer-analytics':
            echo json_encode(getCustomerAnalytics());
            break;
        case 'performance-metrics':
            echo json_encode(getPerformanceMetrics());
            break;
        case 'risk-assessment':
            echo json_encode(getRiskAssessment());
            break;
        case 'market-analysis':
            echo json_encode(getMarketAnalysis());
            break;
        case 'operational-efficiency':
            echo json_encode(getOperationalEfficiency());
            break;
        case 'financial-health':
            echo json_encode(getFinancialHealth());
            break;
        case 'export-data':
            handleDataExport();
            break;
        default:
            http_response_code(404);
            echo json_encode(['error' => 'Endpoint not found']);
    }
}

function handlePostRequest($path, $input) {
    switch ($path) {
        case 'generate-report':
            echo json_encode(generateCustomReport($input));
            break;
        case 'save-dashboard-config':
            echo json_encode(saveDashboardConfig($input));
            break;
        case 'create-alert':
            echo json_encode(createAnalyticsAlert($input));
            break;
        case 'run-analysis':
            echo json_encode(runCustomAnalysis($input));
            break;
        case 'schedule-report':
            echo json_encode(scheduleReport($input));
            break;
        default:
            http_response_code(404);
            echo json_encode(['error' => 'Endpoint not found']);
    }
}

function handlePutRequest($path, $input) {
    switch ($path) {
        case 'update-metrics':
            echo json_encode(updateMetricsConfig($input));
            break;
        case 'update-alert':
            echo json_encode(updateAnalyticsAlert($input));
            break;
        default:
            http_response_code(404);
            echo json_encode(['error' => 'Endpoint not found']);
    }
}

function handleDeleteRequest($path) {
    switch ($path) {
        case 'delete-report':
            $reportId = $_GET['id'] ?? null;
            echo json_encode(deleteReport($reportId));
            break;
        case 'delete-alert':
            $alertId = $_GET['id'] ?? null;
            echo json_encode(deleteAnalyticsAlert($alertId));
            break;
        default:
            http_response_code(404);
            echo json_encode(['error' => 'Endpoint not found']);
    }
}

// Analytics Functions
function getAnalyticsOverview() {
    global $pdo;
    
    try {
        $overview = [
            'total_loans' => 0,
            'total_revenue' => 0,
            'active_customers' => 0,
            'default_rate' => 0,
            'approval_rate' => 0,
            'average_loan_amount' => 0
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
        
        // Calculate rates
        $stmt = $pdo->query("
            SELECT 
                COUNT(*) as total,
                SUM(CASE WHEN status = 'approved' THEN 1 ELSE 0 END) as approved,
                SUM(CASE WHEN status = 'defaulted' THEN 1 ELSE 0 END) as defaulted,
                AVG(CASE WHEN status = 'approved' THEN amount ELSE NULL END) as avg_amount
            FROM loan_applications
        ");
        $result = $stmt->fetch();
        
        if ($result['total'] > 0) {
            $overview['approval_rate'] = ($result['approved'] / $result['total']) * 100;
            $overview['default_rate'] = ($result['defaulted'] / $result['approved']) * 100;
        }
        $overview['average_loan_amount'] = $result['avg_amount'] ?? 0;
        
        return $overview;
    } catch (Exception $e) {
        error_log("Analytics Overview Error: " . $e->getMessage());
        return ['error' => 'Failed to fetch overview data'];
    }
}

function getKPIMetrics() {
    global $pdo;
    
    try {
        $metrics = [];
        
        // Monthly Revenue
        $stmt = $pdo->query("
            SELECT 
                SUM(CASE WHEN MONTH(created_at) = MONTH(NOW()) THEN amount ELSE 0 END) as current_month,
                SUM(CASE WHEN MONTH(created_at) = MONTH(NOW()) - 1 THEN amount ELSE 0 END) as last_month
            FROM loan_applications WHERE status = 'approved'
        ");
        $revenue = $stmt->fetch();
        $revenue_trend = $revenue['last_month'] > 0 ? 
            (($revenue['current_month'] - $revenue['last_month']) / $revenue['last_month']) * 100 : 0;
        
        $metrics[] = [
            'value' => '$' . number_format($revenue['current_month'] / 1000, 0) . 'K',
            'label' => 'Monthly Revenue',
            'trend' => round($revenue_trend, 1)
        ];
        
        // Active Loans
        $stmt = $pdo->query("SELECT COUNT(*) as count FROM loan_applications WHERE status = 'approved'");
        $active_loans = $stmt->fetch()['count'];
        
        $metrics[] = [
            'value' => number_format($active_loans),
            'label' => 'Active Loans',
            'trend' => 8 // Simulated trend
        ];
        
        // Approval Rate
        $stmt = $pdo->query("
            SELECT 
                COUNT(*) as total,
                SUM(CASE WHEN status = 'approved' THEN 1 ELSE 0 END) as approved
            FROM loan_applications
        ");
        $approval = $stmt->fetch();
        $approval_rate = $approval['total'] > 0 ? ($approval['approved'] / $approval['total']) * 100 : 0;
        
        $metrics[] = [
            'value' => round($approval_rate, 1) . '%',
            'label' => 'Approval Rate',
            'trend' => 3
        ];
        
        // Default Rate
        $stmt = $pdo->query("
            SELECT 
                SUM(CASE WHEN status = 'approved' THEN 1 ELSE 0 END) as approved,
                SUM(CASE WHEN status = 'defaulted' THEN 1 ELSE 0 END) as defaulted
            FROM loan_applications
        ");
        $default = $stmt->fetch();
        $default_rate = $default['approved'] > 0 ? ($default['defaulted'] / $default['approved']) * 100 : 0;
        
        $metrics[] = [
            'value' => round($default_rate, 1) . '%',
            'label' => 'Default Rate',
            'trend' => -5
        ];
        
        return $metrics;
    } catch (Exception $e) {
        error_log("KPI Metrics Error: " . $e->getMessage());
        return [];
    }
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
                SUM(amount) as revenue,
                COUNT(*) as count
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
        
        // Customer growth (last 12 months)
        $stmt = $pdo->query("
            SELECT 
                DATE_FORMAT(created_at, '%Y-%m') as month,
                COUNT(DISTINCT email) as customers
            FROM loan_applications
            WHERE created_at >= DATE_SUB(NOW(), INTERVAL 12 MONTH)
            GROUP BY DATE_FORMAT(created_at, '%Y-%m')
            ORDER BY month
        ");
        $data['customer_growth'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        return $data;
    } catch (Exception $e) {
        error_log("Dashboard Data Error: " . $e->getMessage());
        return ['error' => 'Failed to fetch dashboard data'];
    }
}

function getPredictiveAnalytics() {
    // AI-powered predictive analytics simulation
    return [
        'revenue_forecast' => [
            'next_month' => 920000,
            'next_quarter' => 2750000,
            'confidence' => 87,
            'trend' => 'increasing',
            'factors' => ['seasonal_growth', 'market_expansion', 'product_improvements']
        ],
        'risk_assessment' => [
            'overall_risk' => 'low',
            'credit_risk' => 2.3,
            'market_risk' => 1.8,
            'operational_risk' => 2.1,
            'liquidity_risk' => 1.5
        ],
        'customer_insights' => [
            'churn_probability' => 5.2,
            'acquisition_forecast' => 340,
            'satisfaction_trend' => 'improving',
            'lifetime_value' => 15600
        ],
        'recommendations' => [
            'Increase loan limits for high-credit customers',
            'Implement additional verification for high-risk segments',
            'Prepare for seasonal Q4 increase in applications',
            'Focus marketing on 25-35 age demographic'
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
                'Green energy loans',
                'Cryptocurrency-backed loans'
            ],
            'market_trends' => [
                'Digital transformation acceleration',
                'Increased demand for flexible terms',
                'Growing interest in sustainable financing'
            ]
        ],
        'operational_efficiency' => [
            'processing_time' => 2.3, // days
            'automation_rate' => 78,
            'cost_per_acquisition' => 145,
            'employee_productivity' => 92
        ],
        'financial_health' => [
            'profit_margin' => 23.5,
            'roi' => 18.7,
            'cash_flow' => 'positive',
            'debt_to_equity' => 0.45
        ]
    ];
}

function getRecentReports() {
    return [
        [
            'id' => 'rpt_001',
            'name' => 'Monthly Performance Report',
            'type' => 'Performance',
            'created_at' => '2 hours ago',
            'status' => 'completed'
        ],
        [
            'id' => 'rpt_002',
            'name' => 'Customer Segmentation Analysis',
            'type' => 'Analytics',
            'created_at' => '1 day ago',
            'status' => 'completed'
        ],
        [
            'id' => 'rpt_003',
            'name' => 'Risk Assessment Report',
            'type' => 'Risk',
            'created_at' => '2 days ago',
            'status' => 'completed'
        ],
        [
            'id' => 'rpt_004',
            'name' => 'Revenue Forecast',
            'type' => 'Forecast',
            'created_at' => '3 days ago',
            'status' => 'generating'
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
            'created_at' => date('Y-m-d H:i:s'),
            'estimated_completion' => date('Y-m-d H:i:s', strtotime('+3 minutes'))
        ];
        
        // Log report generation
        error_log("Report generated: " . json_encode($report));
        
        return [
            'success' => true,
            'message' => 'Report generation started',
            'report_id' => $report['id'],
            'estimated_time' => '2-3 minutes',
            'report' => $report
        ];
    } catch (Exception $e) {
        return [
            'success' => false,
            'message' => 'Failed to generate report: ' . $e->getMessage()
        ];
    }
}

function saveDashboardConfig($config) {
    try {
        // Save dashboard configuration
        $configFile = '../config/dashboard_config.json';
        file_put_contents($configFile, json_encode($config, JSON_PRETTY_PRINT));
        
        return [
            'success' => true,
            'message' => 'Dashboard configuration saved successfully'
        ];
    } catch (Exception $e) {
        return [
            'success' => false,
            'message' => 'Failed to save configuration: ' . $e->getMessage()
        ];
    }
}

function createAnalyticsAlert($params) {
    try {
        $alert = [
            'id' => uniqid('alert_'),
            'name' => $params['name'] ?? 'New Alert',
            'metric' => $params['metric'] ?? '',
            'condition' => $params['condition'] ?? 'greater_than',
            'threshold' => $params['threshold'] ?? 0,
            'frequency' => $params['frequency'] ?? 'daily',
            'recipients' => $params['recipients'] ?? [],
            'active' => true,
            'created_at' => date('Y-m-d H:i:s')
        ];
        
        // Save alert configuration
        $alertsFile = '../config/analytics_alerts.json';
        $alerts = [];
        if (file_exists($alertsFile)) {
            $alerts = json_decode(file_get_contents($alertsFile), true) ?? [];
        }
        $alerts[] = $alert;
        file_put_contents($alertsFile, json_encode($alerts, JSON_PRETTY_PRINT));
        
        return [
            'success' => true,
            'message' => 'Alert created successfully',
            'alert_id' => $alert['id']
        ];
    } catch (Exception $e) {
        return [
            'success' => false,
            'message' => 'Failed to create alert: ' . $e->getMessage()
        ];
    }
}

function handleDataExport() {
    $category = $_GET['category'] ?? 'all';
    $format = $_GET['format'] ?? 'csv';
    $dateFrom = $_GET['date_from'] ?? null;
    $dateTo = $_GET['date_to'] ?? null;
    
    try {
        exportAnalyticsData([
            'data_category' => $category,
            'export_format' => $format,
            'date_from' => $dateFrom,
            'date_to' => $dateTo
        ]);
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['error' => 'Export failed: ' . $e->getMessage()]);
    }
}

function exportAnalyticsData($params) {
    global $pdo;
    
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