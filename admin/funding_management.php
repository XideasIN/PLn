<?php
/**
 * Funding Management - Admin Interface
 * LoanFlow Personal Loan Management System
 */

require_once '../config/database.php';
require_once '../includes/functions.php';
require_once '../includes/auth.php';

// Require admin login
requireAdminLogin();

$current_admin = getCurrentUser();

// Handle funding actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $application_id = $_POST['application_id'] ?? 0;
    
    try {
        $db = getDB();
        
        switch ($action) {
            case 'initiate_funding':
                $stmt = $db->prepare("
                    UPDATE loan_applications 
                    SET application_status = 'funding', 
                        funding_initiated_at = NOW(),
                        funding_initiated_by = ?
                    WHERE id = ? AND application_status = 'approved'
                ");
                $stmt->execute([$current_admin['id'], $application_id]);
                
                // Log the action
                logAdminAction($current_admin['id'], 'funding_initiated', "Funding initiated for application ID: $application_id");
                
                // Send notification to client
                $client_stmt = $db->prepare("SELECT user_id FROM loan_applications WHERE id = ?");
                $client_stmt->execute([$application_id]);
                $client = $client_stmt->fetch();
                
                if ($client) {
                    sendNotificationEmail($client['user_id'], 'funding_initiated', [
                        'application_id' => $application_id,
                        'initiated_by' => $current_admin['first_name'] . ' ' . $current_admin['last_name']
                    ]);
                }
                
                setFlashMessage('success', 'Funding process initiated successfully.');
                break;
                
            case 'complete_funding':
                $funding_amount = $_POST['funding_amount'] ?? 0;
                $funding_reference = $_POST['funding_reference'] ?? '';
                $funding_notes = $_POST['funding_notes'] ?? '';
                
                $stmt = $db->prepare("
                    UPDATE loan_applications 
                    SET application_status = 'funded', 
                        funding_date = NOW(),
                        funding_amount = ?,
                        funding_reference = ?,
                        funding_notes = ?,
                        funded_by = ?
                    WHERE id = ? AND application_status = 'funding'
                ");
                $stmt->execute([$funding_amount, $funding_reference, $funding_notes, $current_admin['id'], $application_id]);
                
                // Log the action
                logAdminAction($current_admin['id'], 'funding_completed', "Funding completed for application ID: $application_id, Amount: $funding_amount");
                
                // Send notification to client
                $client_stmt = $db->prepare("SELECT user_id FROM loan_applications WHERE id = ?");
                $client_stmt->execute([$application_id]);
                $client = $client_stmt->fetch();
                
                if ($client) {
                    sendNotificationEmail($client['user_id'], 'funding_completed', [
                        'application_id' => $application_id,
                        'funding_amount' => $funding_amount,
                        'funding_reference' => $funding_reference
                    ]);
                }
                
                setFlashMessage('success', 'Funding completed successfully.');
                break;
                
            case 'cancel_funding':
                $cancel_reason = $_POST['cancel_reason'] ?? '';
                
                $stmt = $db->prepare("
                    UPDATE loan_applications 
                    SET application_status = 'approved', 
                        funding_initiated_at = NULL,
                        funding_cancel_reason = ?,
                        funding_cancelled_by = ?,
                        funding_cancelled_at = NOW()
                    WHERE id = ? AND application_status = 'funding'
                ");
                $stmt->execute([$cancel_reason, $current_admin['id'], $application_id]);
                
                // Log the action
                logAdminAction($current_admin['id'], 'funding_cancelled', "Funding cancelled for application ID: $application_id, Reason: $cancel_reason");
                
                setFlashMessage('warning', 'Funding process cancelled.');
                break;
        }
        
    } catch (Exception $e) {
        error_log("Funding action failed: " . $e->getMessage());
        setFlashMessage('error', 'Action failed: ' . $e->getMessage());
    }
    
    header('Location: funding_management.php');
    exit;
}

// Get filter parameters
$status_filter = $_GET['status'] ?? 'all';
$search = $_GET['search'] ?? '';
$page = max(1, intval($_GET['page'] ?? 1));
$per_page = 20;
$offset = ($page - 1) * $per_page;

// Build query conditions
$where_conditions = [];
$params = [];

if ($status_filter !== 'all') {
    $where_conditions[] = "la.application_status = ?";
    $params[] = $status_filter;
}

if ($search) {
    $where_conditions[] = "(u.first_name LIKE ? OR u.last_name LIKE ? OR u.email LIKE ? OR u.reference_number LIKE ?)";
    $search_param = "%$search%";
    $params = array_merge($params, [$search_param, $search_param, $search_param, $search_param]);
}

$where_clause = !empty($where_conditions) ? 'WHERE ' . implode(' AND ', $where_conditions) : '';

try {
    $db = getDB();
    
    // Get funding applications
    $stmt = $db->prepare("
        SELECT la.*, u.first_name, u.last_name, u.email, u.reference_number, u.country,
               bd.bank_name, bd.account_holder_name, bd.account_number,
               admin_initiated.first_name as initiated_by_name,
               admin_funded.first_name as funded_by_name
        FROM loan_applications la
        JOIN users u ON la.user_id = u.id
        LEFT JOIN bank_details bd ON la.user_id = bd.user_id AND bd.verified = 1
        LEFT JOIN users admin_initiated ON la.funding_initiated_by = admin_initiated.id
        LEFT JOIN users admin_funded ON la.funded_by = admin_funded.id
        $where_clause
        AND la.application_status IN ('approved', 'funding', 'funded')
        ORDER BY 
            CASE la.application_status 
                WHEN 'funding' THEN 1 
                WHEN 'approved' THEN 2 
                WHEN 'funded' THEN 3 
            END,
            la.funding_initiated_at DESC,
            la.approval_date DESC
        LIMIT $per_page OFFSET $offset
    ");
    $stmt->execute($params);
    $applications = $stmt->fetchAll();
    
    // Get total count for pagination
    $count_stmt = $db->prepare("
        SELECT COUNT(*) 
        FROM loan_applications la
        JOIN users u ON la.user_id = u.id
        $where_clause
        AND la.application_status IN ('approved', 'funding', 'funded')
    ");
    $count_stmt->execute($params);
    $total_applications = $count_stmt->fetchColumn();
    $total_pages = ceil($total_applications / $per_page);
    
    // Get funding statistics
    $stats_stmt = $db->query("
        SELECT 
            COUNT(CASE WHEN application_status = 'approved' THEN 1 END) as approved_count,
            COUNT(CASE WHEN application_status = 'funding' THEN 1 END) as funding_count,
            COUNT(CASE WHEN application_status = 'funded' THEN 1 END) as funded_count,
            SUM(CASE WHEN application_status = 'funded' THEN loan_amount ELSE 0 END) as total_funded_amount,
            AVG(CASE WHEN application_status = 'funded' AND funding_date IS NOT NULL AND approval_date IS NOT NULL 
                     THEN TIMESTAMPDIFF(HOUR, approval_date, funding_date) END) as avg_funding_time_hours
        FROM loan_applications
        WHERE application_status IN ('approved', 'funding', 'funded')
    ");
    $stats = $stats_stmt->fetch();
    
} catch (Exception $e) {
    error_log("Funding management data fetch failed: " . $e->getMessage());
    $applications = [];
    $total_applications = 0;
    $total_pages = 0;
    $stats = [
        'approved_count' => 0,
        'funding_count' => 0,
        'funded_count' => 0,
        'total_funded_amount' => 0,
        'avg_funding_time_hours' => 0
    ];
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Funding Management - Admin Panel</title>
    <link rel="stylesheet" href="../FrontEnd_Template/css/bootstrap.min.css">
    <link rel="stylesheet" href="../FrontEnd_Template/css/style.css">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="../assets/css/admin.css" rel="stylesheet">
    <style>
        .stats-card {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border-radius: 15px;
            padding: 20px;
            margin-bottom: 20px;
        }
        .status-approved {
            background: #fff3cd;
            color: #856404;
            padding: 4px 12px;
            border-radius: 15px;
            font-size: 0.8rem;
            font-weight: 600;
        }
        .status-funding {
            background: #d1ecf1;
            color: #0c5460;
            padding: 4px 12px;
            border-radius: 15px;
            font-size: 0.8rem;
            font-weight: 600;
        }
        .status-funded {
            background: #d4edda;
            color: #155724;
            padding: 4px 12px;
            border-radius: 15px;
            font-size: 0.8rem;
            font-weight: 600;
        }
        .funding-actions {
            display: flex;
            gap: 5px;
            flex-wrap: wrap;
        }
        .funding-actions .btn {
            font-size: 0.8rem;
            padding: 4px 8px;
        }
        .table-responsive {
            border-radius: 10px;
            overflow: hidden;
            box-shadow: 0 0 20px rgba(0,0,0,0.1);
        }
        .modal-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
        }
        .bank-info {
            font-size: 0.9rem;
            color: #6c757d;
        }
    </style>
</head>
<body>
    <div class="admin-wrapper">
        <!-- Admin Sidebar -->
        <?php include 'includes/sidebar.php'; ?>
        
        <!-- Main Content -->
        <div class="main-content">
            <!-- Admin Header -->
            <?php include 'includes/header.php'; ?>
            
            <div class="container-fluid p-4">
                <!-- Page Header -->
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <div>
                        <h2 class="mb-0">
                            <i class="fas fa-money-check-alt me-2"></i>
                            Funding Management
                        </h2>
                        <p class="text-muted mb-0">Manage loan funding processes and disbursements</p>
                    </div>
                    <div>
                        <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#bulkFundingModal">
                            <i class="fas fa-layer-group me-2"></i>Bulk Actions
                        </button>
                    </div>
                </div>
                
                <!-- Flash Messages -->
                <?php 
                $flash = getFlashMessage();
                if ($flash): 
                ?>
                    <div class="alert alert-<?= $flash['type'] ?> alert-dismissible fade show">
                        <?= htmlspecialchars($flash['message']) ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>
                
                <!-- Statistics Cards -->
                <div class="row mb-4">
                    <div class="col-md-3">
                        <div class="stats-card text-center">
                            <div class="h3 mb-1"><?= number_format($stats['approved_count']) ?></div>
                            <div>Ready for Funding</div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="stats-card text-center">
                            <div class="h3 mb-1"><?= number_format($stats['funding_count']) ?></div>
                            <div>In Progress</div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="stats-card text-center">
                            <div class="h3 mb-1"><?= number_format($stats['funded_count']) ?></div>
                            <div>Completed</div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="stats-card text-center">
                            <div class="h3 mb-1">$<?= number_format($stats['total_funded_amount']) ?></div>
                            <div>Total Funded</div>
                        </div>
                    </div>
                </div>
                
                <!-- Filters -->
                <div class="card mb-4">
                    <div class="card-body">
                        <form method="GET" class="row g-3">
                            <div class="col-md-3">
                                <label class="form-label">Status Filter</label>
                                <select name="status" class="form-select">
                                    <option value="all" <?= $status_filter === 'all' ? 'selected' : '' ?>>All Statuses</option>
                                    <option value="approved" <?= $status_filter === 'approved' ? 'selected' : '' ?>>Ready for Funding</option>
                                    <option value="funding" <?= $status_filter === 'funding' ? 'selected' : '' ?>>Funding in Progress</option>
                                    <option value="funded" <?= $status_filter === 'funded' ? 'selected' : '' ?>>Funded</option>
                                </select>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Search</label>
                                <input type="text" name="search" class="form-control" 
                                       placeholder="Search by name, email, or reference number" 
                                       value="<?= htmlspecialchars($search) ?>">
                            </div>
                            <div class="col-md-3">
                                <label class="form-label">&nbsp;</label>
                                <div class="d-grid">
                                    <button type="submit" class="btn btn-primary">
                                        <i class="fas fa-search me-1"></i>Search
                                    </button>
                                </div>
                            </div>
                        </form>
                    </div>
                </div>
                
                <!-- Applications Table -->
                <div class="card">
                    <div class="card-header">
                        <h5 class="mb-0">
                            <i class="fas fa-list me-2"></i>
                            Funding Applications (<?= number_format($total_applications) ?> total)
                        </h5>
                    </div>
                    <div class="card-body p-0">
                        <?php if (!empty($applications)): ?>
                            <div class="table-responsive">
                                <table class="table table-hover mb-0">
                                    <thead class="table-light">
                                        <tr>
                                            <th>Client</th>
                                            <th>Loan Details</th>
                                            <th>Bank Details</th>
                                            <th>Status</th>
                                            <th>Timeline</th>
                                            <th>Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($applications as $app): ?>
                                            <tr>
                                                <td>
                                                    <div>
                                                        <strong><?= htmlspecialchars($app['first_name'] . ' ' . $app['last_name']) ?></strong>
                                                        <br>
                                                        <small class="text-muted"><?= htmlspecialchars($app['email']) ?></small>
                                                        <br>
                                                        <small class="text-muted">Ref: <?= htmlspecialchars($app['reference_number']) ?></small>
                                                    </div>
                                                </td>
                                                <td>
                                                    <div>
                                                        <strong>$<?= number_format($app['loan_amount']) ?></strong>
                                                        <br>
                                                        <small class="text-muted"><?= htmlspecialchars($app['loan_type']) ?></small>
                                                        <br>
                                                        <small class="text-muted"><?= $app['interest_rate'] ?>% • <?= $app['loan_term'] ?> months</small>
                                                    </div>
                                                </td>
                                                <td>
                                                    <?php if ($app['bank_name']): ?>
                                                        <div class="bank-info">
                                                            <strong><?= htmlspecialchars($app['bank_name']) ?></strong>
                                                            <br>
                                                            <?= htmlspecialchars($app['account_holder_name']) ?>
                                                            <br>
                                                            ****<?= substr($app['account_number'], -4) ?>
                                                        </div>
                                                    <?php else: ?>
                                                        <span class="text-warning">
                                                            <i class="fas fa-exclamation-triangle"></i> No bank details
                                                        </span>
                                                    <?php endif; ?>
                                                </td>
                                                <td>
                                                    <span class="status-<?= $app['application_status'] ?>">
                                                        <?= ucfirst($app['application_status']) ?>
                                                    </span>
                                                    <?php if ($app['application_status'] === 'funding'): ?>
                                                        <br>
                                                        <small class="text-muted">
                                                            Started: <?= formatDateTime($app['funding_initiated_at']) ?>
                                                        </small>
                                                    <?php endif; ?>
                                                </td>
                                                <td>
                                                    <small>
                                                        <?php if ($app['approval_date']): ?>
                                                            <div class="text-success">
                                                                <i class="fas fa-check"></i> Approved: <?= formatDate($app['approval_date']) ?>
                                                            </div>
                                                        <?php endif; ?>
                                                        <?php if ($app['funding_initiated_at']): ?>
                                                            <div class="text-info">
                                                                <i class="fas fa-play"></i> Started: <?= formatDate($app['funding_initiated_at']) ?>
                                                            </div>
                                                        <?php endif; ?>
                                                        <?php if ($app['funding_date']): ?>
                                                            <div class="text-success">
                                                                <i class="fas fa-check-circle"></i> Funded: <?= formatDate($app['funding_date']) ?>
                                                            </div>
                                                        <?php endif; ?>
                                                    </small>
                                                </td>
                                                <td>
                                                    <div class="funding-actions">
                                                        <?php if ($app['application_status'] === 'approved'): ?>
                                                            <button class="btn btn-success btn-sm" 
                                                                    onclick="initiateFunding(<?= $app['id'] ?>)">
                                                                <i class="fas fa-play"></i> Start
                                                            </button>
                                                        <?php elseif ($app['application_status'] === 'funding'): ?>
                                                            <button class="btn btn-primary btn-sm" 
                                                                    onclick="completeFunding(<?= $app['id'] ?>)">
                                                                <i class="fas fa-check"></i> Complete
                                                            </button>
                                                            <button class="btn btn-warning btn-sm" 
                                                                    onclick="cancelFunding(<?= $app['id'] ?>)">
                                                                <i class="fas fa-times"></i> Cancel
                                                            </button>
                                                        <?php else: ?>
                                                            <button class="btn btn-info btn-sm" 
                                                                    onclick="viewFundingDetails(<?= $app['id'] ?>)">
                                                                <i class="fas fa-eye"></i> View
                                                            </button>
                                                        <?php endif; ?>
                                                        
                                                        <a href="applications.php?id=<?= $app['id'] ?>" 
                                                           class="btn btn-outline-secondary btn-sm">
                                                            <i class="fas fa-folder-open"></i>
                                                        </a>
                                                    </div>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                            
                            <!-- Pagination -->
                            <?php if ($total_pages > 1): ?>
                                <div class="card-footer">
                                    <nav>
                                        <ul class="pagination justify-content-center mb-0">
                                            <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                                                <li class="page-item <?= $i === $page ? 'active' : '' ?>">
                                                    <a class="page-link" href="?page=<?= $i ?>&status=<?= urlencode($status_filter) ?>&search=<?= urlencode($search) ?>">
                                                        <?= $i ?>
                                                    </a>
                                                </li>
                                            <?php endfor; ?>
                                        </ul>
                                    </nav>
                                </div>
                            <?php endif; ?>
                        <?php else: ?>
                            <div class="text-center py-5">
                                <i class="fas fa-inbox fa-3x text-muted mb-3"></i>
                                <h5 class="text-muted">No Applications Found</h5>
                                <p class="text-muted">No applications match your current filters.</p>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Funding Action Modals -->
    <div class="modal fade" id="initiateFundingModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">
                        <i class="fas fa-play me-2"></i>Initiate Funding
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST">
                    <div class="modal-body">
                        <input type="hidden" name="action" value="initiate_funding">
                        <input type="hidden" name="application_id" id="initiate_app_id">
                        
                        <div class="alert alert-info">
                            <i class="fas fa-info-circle me-2"></i>
                            This will start the funding process for the selected application.
                        </div>
                        
                        <p>Are you sure you want to initiate funding for this application?</p>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-success">
                            <i class="fas fa-play me-1"></i>Start Funding
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    
    <div class="modal fade" id="completeFundingModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">
                        <i class="fas fa-check me-2"></i>Complete Funding
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST">
                    <div class="modal-body">
                        <input type="hidden" name="action" value="complete_funding">
                        <input type="hidden" name="application_id" id="complete_app_id">
                        
                        <div class="mb-3">
                            <label class="form-label">Funding Amount *</label>
                            <input type="number" name="funding_amount" class="form-control" 
                                   step="0.01" min="0" required>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Funding Reference *</label>
                            <input type="text" name="funding_reference" class="form-control" 
                                   placeholder="Transaction reference or ID" required>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Notes</label>
                            <textarea name="funding_notes" class="form-control" rows="3" 
                                      placeholder="Additional notes about the funding"></textarea>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-check me-1"></i>Complete Funding
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    
    <div class="modal fade" id="cancelFundingModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">
                        <i class="fas fa-times me-2"></i>Cancel Funding
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST">
                    <div class="modal-body">
                        <input type="hidden" name="action" value="cancel_funding">
                        <input type="hidden" name="application_id" id="cancel_app_id">
                        
                        <div class="alert alert-warning">
                            <i class="fas fa-exclamation-triangle me-2"></i>
                            This will cancel the funding process and return the application to approved status.
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Cancellation Reason *</label>
                            <textarea name="cancel_reason" class="form-control" rows="3" 
                                      placeholder="Explain why the funding is being cancelled" required></textarea>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                        <button type="submit" class="btn btn-warning">
                            <i class="fas fa-times me-1"></i>Cancel Funding
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    
    <!-- Scripts -->
    <script src="../FrontEnd_Template/js/bootstrap.bundle.min.js"></script>
    <script>
        function initiateFunding(appId) {
            document.getElementById('initiate_app_id').value = appId;
            new bootstrap.Modal(document.getElementById('initiateFundingModal')).show();
        }
        
        function completeFunding(appId) {
            document.getElementById('complete_app_id').value = appId;
            new bootstrap.Modal(document.getElementById('completeFundingModal')).show();
        }
        
        function cancelFunding(appId) {
            document.getElementById('cancel_app_id').value = appId;
            new bootstrap.Modal(document.getElementById('cancelFundingModal')).show();
        }
        
        function viewFundingDetails(appId) {
            window.open('applications.php?id=' + appId, '_blank');
        }
        
        // Auto-refresh every 30 seconds for funding in progress
        setInterval(function() {
            if (document.querySelector('.status-funding')) {
                location.reload();
            }
        }, 30000);
    </script>
</body>
</html>