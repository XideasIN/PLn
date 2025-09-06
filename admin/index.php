<?php
/**
 * Admin Dashboard
 * LoanFlow Personal Loan Management System
 */

require_once '../config/database.php';
require_once '../includes/functions.php';
require_once '../includes/auth.php';

// Require admin access
requireRole('admin');

$current_user = getCurrentUser();

// Get dashboard statistics
try {
    $db = getDB();
    
    // Total applications
    $stmt = $db->query("SELECT COUNT(*) as total FROM loan_applications");
    $total_applications = $stmt->fetch()['total'];
    
    // Applications by status
    $stmt = $db->query("
        SELECT application_status, COUNT(*) as count 
        FROM loan_applications 
        GROUP BY application_status
    ");
    $applications_by_status = $stmt->fetchAll();
    
    // Recent applications (last 7 days)
    $stmt = $db->query("
        SELECT COUNT(*) as count 
        FROM loan_applications 
        WHERE created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)
    ");
    $recent_applications = $stmt->fetch()['count'];
    
    // Pending documents
    $stmt = $db->query("
        SELECT COUNT(*) as count 
        FROM documents 
        WHERE upload_status = 'uploaded'
    ");
    $pending_documents = $stmt->fetch()['count'];
    
    // Call list count
    $stmt = $db->query("
        SELECT COUNT(*) as count 
        FROM call_lists 
        WHERE status = 'pending'
    ");
    $call_list_count = $stmt->fetch()['count'];
    
    // Email queue count
    $stmt = $db->query("
        SELECT COUNT(*) as count 
        FROM email_queue 
        WHERE status = 'pending'
    ");
    $email_queue_count = $stmt->fetch()['count'];
    
    // Revenue this month
    $stmt = $db->query("
        SELECT SUM(amount) as total 
        FROM payments 
        WHERE payment_status = 'completed' 
        AND MONTH(created_at) = MONTH(NOW()) 
        AND YEAR(created_at) = YEAR(NOW())
    ");
    $monthly_revenue = $stmt->fetch()['total'] ?? 0;
    
    // Recent applications for table
    $stmt = $db->query("
        SELECT la.*, u.first_name, u.last_name, u.reference_number, u.email
        FROM loan_applications la
        JOIN users u ON la.user_id = u.id
        ORDER BY la.created_at DESC
        LIMIT 10
    ");
    $recent_apps = $stmt->fetchAll();
    
} catch (Exception $e) {
    error_log("Dashboard stats failed: " . $e->getMessage());
    $total_applications = $recent_applications = $pending_documents = 0;
    $call_list_count = $email_queue_count = $monthly_revenue = 0;
    $applications_by_status = $recent_apps = [];
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard - LoanFlow</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="../assets/css/style.css" rel="stylesheet">
    <link href="../assets/css/admin.css" rel="stylesheet">
    <style>
        .card-hover {
            transition: transform 0.2s ease-in-out, box-shadow 0.2s ease-in-out;
        }
        .card-hover:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 8px rgba(0,0,0,0.15) !important;
        }
        a.text-decoration-none:hover .card-hover {
            color: inherit;
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
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav me-auto">
                    <li class="nav-item">
                        <a class="nav-link active" href="index.php">
                            <i class="fas fa-home me-1"></i>Dashboard
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="applications.php">
                            <i class="fas fa-file-alt me-1"></i>Applications
                            <?php if ($recent_applications > 0): ?>
                                <span class="badge bg-danger ms-1"><?= $recent_applications ?></span>
                            <?php endif; ?>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="users.php">
                            <i class="fas fa-users me-1"></i>Users
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="documents.php">
                            <i class="fas fa-folder me-1"></i>Documents
                            <?php if ($pending_documents > 0): ?>
                                <span class="badge bg-warning ms-1"><?= $pending_documents ?></span>
                            <?php endif; ?>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="call-list.php">
                            <i class="fas fa-phone me-1"></i>Call List
                            <?php if ($call_list_count > 0): ?>
                                <span class="badge bg-info ms-1"><?= $call_list_count ?></span>
                            <?php endif; ?>
                        </a>
                    </li>
                    <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle" href="#" role="button" data-bs-toggle="dropdown">
                            <i class="fas fa-cog me-1"></i>Settings
                        </a>
                        <ul class="dropdown-menu">
                            <li><a class="dropdown-item" href="email-templates.php">
                                <i class="fas fa-envelope me-2"></i>Email Templates
                            </a></li>
                            <li><a class="dropdown-item" href="payment-schemes.php">
                                <i class="fas fa-credit-card me-2"></i>Payment Schemes
                            </a></li>
                            <li><a class="dropdown-item" href="payment-methods.php">
                                <i class="fas fa-cogs me-2"></i>Payment Methods
                            </a></li>
                            <li><a class="dropdown-item" href="system-settings.php">
                                <i class="fas fa-sliders-h me-2"></i>System Settings
                            </a></li>
                            <li><a class="dropdown-item" href="template-manager.php">
                                <i class="fas fa-palette me-2"></i>Template Management
                            </a></li>
                            <li><hr class="dropdown-divider"></li>
                            <li><a class="dropdown-item" href="holidays.php">
                                <i class="fas fa-calendar me-2"></i>Holiday Management
                            </a></li>
                        </ul>
                    </li>
                </ul>
                <ul class="navbar-nav">
                    <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle" href="#" role="button" data-bs-toggle="dropdown">
                            <i class="fas fa-user me-1"></i><?= htmlspecialchars($current_user['first_name']) ?>
                        </a>
                        <ul class="dropdown-menu dropdown-menu-end">
                            <li><a class="dropdown-item" href="profile.php">
                                <i class="fas fa-user me-2"></i>Profile
                            </a></li>
                            <li><a class="dropdown-item" href="audit-logs.php">
                                <i class="fas fa-history me-2"></i>Audit Logs
                            </a></li>
                            <li><hr class="dropdown-divider"></li>
                            <li><a class="dropdown-item" href="../client/">
                                <i class="fas fa-external-link-alt me-2"></i>View Site
                            </a></li>
                            <li><a class="dropdown-item" href="logout.php">
                                <i class="fas fa-sign-out-alt me-2"></i>Logout
                            </a></li>
                        </ul>
                    </li>
                </ul>
            </div>
        </div>
    </nav>

    <div class="container-fluid py-4">
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

        <!-- Dashboard Statistics -->
        <div class="row mb-4">
            <div class="col-xl-3 col-md-6 mb-4">
                <div class="card border-left-primary shadow h-100 py-2">
                    <div class="card-body">
                        <div class="row no-gutters align-items-center">
                            <div class="col mr-2">
                                <div class="text-xs font-weight-bold text-primary text-uppercase mb-1">
                                    Total Applications
                                </div>
                                <div class="h5 mb-0 font-weight-bold text-gray-800"><?= number_format($total_applications) ?></div>
                            </div>
                            <div class="col-auto">
                                <i class="fas fa-file-alt fa-2x text-gray-300"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-xl-3 col-md-6 mb-4">
                <div class="card border-left-success shadow h-100 py-2">
                    <div class="card-body">
                        <div class="row no-gutters align-items-center">
                            <div class="col mr-2">
                                <div class="text-xs font-weight-bold text-success text-uppercase mb-1">
                                    Monthly Revenue
                                </div>
                                <div class="h5 mb-0 font-weight-bold text-gray-800">$<?= number_format($monthly_revenue, 2) ?></div>
                            </div>
                            <div class="col-auto">
                                <i class="fas fa-dollar-sign fa-2x text-gray-300"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-xl-3 col-md-6 mb-4">
                <a href="document_dashboard.php" class="text-decoration-none">
                    <div class="card border-left-info shadow h-100 py-2 card-hover">
                        <div class="card-body">
                            <div class="row no-gutters align-items-center">
                                <div class="col mr-2">
                                    <div class="text-xs font-weight-bold text-info text-uppercase mb-1">
                                        Pending Documents
                                    </div>
                                    <div class="h5 mb-0 font-weight-bold text-gray-800"><?= $pending_documents ?></div>
                                </div>
                                <div class="col-auto">
                                    <i class="fas fa-folder fa-2x text-gray-300"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                </a>
            </div>

            <div class="col-xl-3 col-md-6 mb-4">
                <div class="card border-left-warning shadow h-100 py-2">
                    <div class="card-body">
                        <div class="row no-gutters align-items-center">
                            <div class="col mr-2">
                                <div class="text-xs font-weight-bold text-warning text-uppercase mb-1">
                                    Call List
                                </div>
                                <div class="h5 mb-0 font-weight-bold text-gray-800"><?= $call_list_count ?></div>
                            </div>
                            <div class="col-auto">
                                <i class="fas fa-phone fa-2x text-gray-300"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="row">
            <!-- Applications by Status Chart -->
            <div class="col-xl-4 col-lg-5">
                <div class="card shadow mb-4">
                    <div class="card-header py-3 d-flex flex-row align-items-center justify-content-between">
                        <h6 class="m-0 font-weight-bold text-primary">Applications by Status</h6>
                    </div>
                    <div class="card-body">
                        <div class="chart-pie pt-4 pb-2">
                            <canvas id="statusChart"></canvas>
                        </div>
                        <div class="mt-4 text-center small">
                            <?php foreach ($applications_by_status as $status): ?>
                                <span class="mr-2">
                                    <i class="fas fa-circle text-primary"></i> <?= ucfirst(str_replace('_', ' ', $status['application_status'])) ?>: <?= $status['count'] ?>
                                </span>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Recent Applications -->
            <div class="col-xl-8 col-lg-7">
                <div class="card shadow mb-4">
                    <div class="card-header py-3 d-flex flex-row align-items-center justify-content-between">
                        <h6 class="m-0 font-weight-bold text-primary">Recent Applications</h6>
                        <a href="applications.php" class="btn btn-primary btn-sm">
                            <i class="fas fa-eye me-1"></i>View All
                        </a>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-bordered" width="100%" cellspacing="0">
                                <thead>
                                    <tr>
                                        <th>Reference</th>
                                        <th>Name</th>
                                        <th>Email</th>
                                        <th>Amount</th>
                                        <th>Status</th>
                                        <th>Date</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($recent_apps as $app): ?>
                                        <tr>
                                            <td><strong><?= htmlspecialchars($app['reference_number']) ?></strong></td>
                                            <td><?= htmlspecialchars($app['first_name'] . ' ' . $app['last_name']) ?></td>
                                            <td><?= htmlspecialchars($app['email']) ?></td>
                                            <td>$<?= number_format($app['loan_amount'], 2) ?></td>
                                            <td>
                                                <span class="status-badge status-<?= $app['application_status'] ?>">
                                                    <?= ucfirst(str_replace('_', ' ', $app['application_status'])) ?>
                                                </span>
                                            </td>
                                            <td><?= formatDate($app['created_at']) ?></td>
                                            <td>
                                                <a href="view-application.php?id=<?= $app['id'] ?>" class="btn btn-info btn-sm">
                                                    <i class="fas fa-eye"></i>
                                                </a>
                                                <a href="edit-application.php?id=<?= $app['id'] ?>" class="btn btn-primary btn-sm">
                                                    <i class="fas fa-edit"></i>
                                                </a>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Quick Actions -->
        <div class="row">
            <div class="col-12">
                <div class="card shadow mb-4">
                    <div class="card-header py-3">
                        <h6 class="m-0 font-weight-bold text-primary">Quick Actions</h6>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-3 mb-3">
                                <a href="applications.php?status=pending" class="btn btn-outline-primary btn-lg w-100">
                                    <i class="fas fa-clock fa-2x mb-2 d-block"></i>
                                    Review Pending Applications
                                </a>
                            </div>
                            <div class="col-md-3 mb-3">
                                <a href="documents.php?status=uploaded" class="btn btn-outline-warning btn-lg w-100">
                                    <i class="fas fa-folder-open fa-2x mb-2 d-block"></i>
                                    Review Documents
                                </a>
                            </div>
                            <div class="col-md-3 mb-3">
                                <a href="call-list.php" class="btn btn-outline-info btn-lg w-100">
                                    <i class="fas fa-phone fa-2x mb-2 d-block"></i>
                                    Call List
                                </a>
                            </div>
                            <div class="col-md-3 mb-3">
                                <a href="email-queue.php" class="btn btn-outline-success btn-lg w-100">
                                    <i class="fas fa-envelope fa-2x mb-2 d-block"></i>
                                    Email Queue
                                    <?php if ($email_queue_count > 0): ?>
                                        <span class="badge bg-danger ms-1"><?= $email_queue_count ?></span>
                                    <?php endif; ?>
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- System Status -->
        <div class="row">
            <div class="col-md-6">
                <div class="card shadow mb-4">
                    <div class="card-header py-3">
                        <h6 class="m-0 font-weight-bold text-primary">System Status</h6>
                    </div>
                    <div class="card-body">
                        <div class="d-flex align-items-center mb-3">
                            <div class="flex-shrink-0">
                                <i class="fas fa-database fa-2x text-success"></i>
                            </div>
                            <div class="flex-grow-1 ms-3">
                                <div class="fw-bold">Database</div>
                                <div class="text-muted">Connected and operational</div>
                            </div>
                            <div class="flex-shrink-0">
                                <span class="badge bg-success">Online</span>
                            </div>
                        </div>
                        <div class="d-flex align-items-center mb-3">
                            <div class="flex-shrink-0">
                                <i class="fas fa-envelope fa-2x text-<?= $email_queue_count > 0 ? 'warning' : 'success' ?>"></i>
                            </div>
                            <div class="flex-grow-1 ms-3">
                                <div class="fw-bold">Email Queue</div>
                                <div class="text-muted"><?= $email_queue_count ?> emails pending</div>
                            </div>
                            <div class="flex-shrink-0">
                                <span class="badge bg-<?= $email_queue_count > 0 ? 'warning' : 'success' ?>">
                                    <?= $email_queue_count > 0 ? 'Processing' : 'Clear' ?>
                                </span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-md-6">
                <div class="card shadow mb-4">
                    <div class="card-header py-3">
                        <h6 class="m-0 font-weight-bold text-primary">Recent Activity</h6>
                    </div>
                    <div class="card-body">
                        <div class="timeline">
                            <div class="timeline-item">
                                <div class="timeline-marker bg-primary"></div>
                                <div class="timeline-content">
                                    <small class="text-muted"><?= date('g:i A') ?></small>
                                    <div>Dashboard accessed by <?= htmlspecialchars($current_user['first_name']) ?></div>
                                </div>
                            </div>
                            <?php if ($recent_applications > 0): ?>
                            <div class="timeline-item">
                                <div class="timeline-marker bg-success"></div>
                                <div class="timeline-content">
                                    <small class="text-muted">Today</small>
                                    <div><?= $recent_applications ?> new applications received</div>
                                </div>
                            </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script>
        // Status Chart
        const statusData = <?= json_encode($applications_by_status) ?>;
        const statusLabels = statusData.map(item => item.application_status.replace('_', ' ').toUpperCase());
        const statusCounts = statusData.map(item => item.count);
        const statusColors = ['#007bff', '#28a745', '#ffc107', '#dc3545', '#6c757d', '#17a2b8'];

        const statusChart = new Chart(document.getElementById('statusChart'), {
            type: 'doughnut',
            data: {
                labels: statusLabels,
                datasets: [{
                    data: statusCounts,
                    backgroundColor: statusColors,
                    hoverBackgroundColor: statusColors.map(color => color + '80'),
                    borderWidth: 2
                }]
            },
            options: {
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        display: false
                    }
                }
            }
        });
    </script>

    <?php 
    // Include CALL BOX Widget
    require_once '../includes/call_box_widget.php'; 
    ?>
</body>
</html>
