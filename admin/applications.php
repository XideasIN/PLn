<?php
/**
 * Admin Applications Management
 * LoanFlow Personal Loan Management System
 */

require_once '../config/database.php';
require_once '../includes/functions.php';
require_once '../includes/auth.php';
require_once '../includes/memo_color_coding.php';

// Require admin access
requireRole('admin');

$current_user = getCurrentUser();

// Handle status updates
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if (!verifyCSRFToken($_POST['csrf_token'] ?? '')) {
        $error = "Security token mismatch. Please try again.";
    } else {
        $action = $_POST['action'];
        $application_id = (int)($_POST['application_id'] ?? 0);
        
        if ($action === 'update_status' && $application_id) {
            $new_status = $_POST['status'] ?? '';
            $notes = $_POST['notes'] ?? '';
            
            if (updateApplicationStatus($application_id, $new_status, $notes)) {
                // Log the action
                logAudit('application_status_updated', 'loan_applications', $application_id, null, [
                    'new_status' => $new_status,
                    'notes' => $notes
                ]);
                
                redirectWithMessage('applications.php', 'Application status updated successfully!', 'success');
            } else {
                $error = "Failed to update application status.";
            }
        }
    }
}

// Get filter parameters
$status_filter = $_GET['status'] ?? '';
$search = $_GET['search'] ?? '';
$page = max(1, (int)($_GET['page'] ?? 1));
$per_page = 20;
$offset = ($page - 1) * $per_page;

// Build query
$where_conditions = [];
$params = [];

if ($status_filter) {
    $where_conditions[] = "la.application_status = ?";
    $params[] = $status_filter;
}

if ($search) {
    $where_conditions[] = "(u.first_name LIKE ? OR u.last_name LIKE ? OR u.email LIKE ? OR u.reference_number LIKE ?)";
    $search_term = "%$search%";
    $params = array_merge($params, [$search_term, $search_term, $search_term, $search_term]);
}

$where_clause = !empty($where_conditions) ? 'WHERE ' . implode(' AND ', $where_conditions) : '';

try {
    $db = getDB();
    
    // Get total count
    $count_query = "
        SELECT COUNT(*) as total 
        FROM loan_applications la
        JOIN users u ON la.user_id = u.id
        $where_clause
    ";
    $stmt = $db->prepare($count_query);
    $stmt->execute($params);
    $total_applications = $stmt->fetch()['total'];
    
    // Get applications
    $query = "
        SELECT la.*, u.first_name, u.last_name, u.email, u.reference_number, u.phone, u.country,
               COUNT(d.id) as document_count,
               COUNT(ds.id) as signature_count,
               COUNT(p.id) as payment_count
        FROM loan_applications la
        JOIN users u ON la.user_id = u.id
        LEFT JOIN documents d ON la.user_id = d.user_id
        LEFT JOIN digital_signatures ds ON la.user_id = ds.user_id
        LEFT JOIN payments p ON la.user_id = p.user_id
        $where_clause
        GROUP BY la.id
        ORDER BY la.created_at DESC
        LIMIT $per_page OFFSET $offset
    ";
    
    $stmt = $db->prepare($query);
    $stmt->execute($params);
    $applications = $stmt->fetchAll();
    
    // Get memo statuses for all users in bulk
    $userIds = array_column($applications, 'user_id');
    $memoStatuses = [];
    if (!empty($userIds)) {
        $memoStatuses = getMemoStatusForUsers($userIds, $db);
    }
    
    // Get status counts for filters
    $status_query = "
        SELECT application_status, COUNT(*) as count 
        FROM loan_applications 
        GROUP BY application_status
    ";
    $stmt = $db->query($status_query);
    $status_counts = [];
    while ($row = $stmt->fetch()) {
        $status_counts[$row['application_status']] = $row['count'];
    }
    
} catch (Exception $e) {
    error_log("Applications fetch failed: " . $e->getMessage());
    $applications = [];
    $total_applications = 0;
    $status_counts = [];
}

$total_pages = ceil($total_applications / $per_page);

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Applications Management - LoanFlow Admin</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="../assets/css/style.css" rel="stylesheet">
    <link href="../assets/css/admin.css" rel="stylesheet">
    <?php 
    // Include memo color coding CSS
    $memoColorCoding = getMemoColorCoding($db);
    if ($memoColorCoding) {
        echo $memoColorCoding->getCSS();
    }
    ?>
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
                        <a class="nav-link" href="index.php">
                            <i class="fas fa-home me-1"></i>Dashboard
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link active" href="applications.php">
                            <i class="fas fa-file-alt me-1"></i>Applications
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
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="call-list.php">
                            <i class="fas fa-phone me-1"></i>Call List
                        </a>
                    </li>
                    <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle" href="#" role="button" data-bs-toggle="dropdown">
                            <i class="fas fa-cog me-1"></i>Settings
                        </a>
                        <ul class="dropdown-menu">
                            <li><a class="dropdown-item" href="email-templates.php">Email Templates</a></li>
                            <li><a class="dropdown-item" href="payment-schemes.php">Payment Schemes</a></li>
                            <li><a class="dropdown-item" href="system-settings.php">System Settings</a></li>
                        </ul>
                    </li>
                </ul>
                <ul class="navbar-nav">
                    <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle" href="#" role="button" data-bs-toggle="dropdown">
                            <i class="fas fa-user me-1"></i><?= htmlspecialchars($current_user['first_name']) ?>
                        </a>
                        <ul class="dropdown-menu dropdown-menu-end">
                            <li><a class="dropdown-item" href="profile.php">Profile</a></li>
                            <li><a class="dropdown-item" href="logout.php">Logout</a></li>
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

        <?php if (isset($error)): ?>
            <div class="alert alert-danger">
                <i class="fas fa-exclamation-triangle me-2"></i><?= htmlspecialchars($error) ?>
            </div>
        <?php endif; ?>

        <!-- Page Header -->
        <div class="row mb-4">
            <div class="col-12">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <h2><i class="fas fa-file-alt me-2"></i>Applications Management</h2>
                        <p class="text-muted">Manage and review loan applications</p>
                    </div>
                    <div>
                        <span class="badge bg-primary fs-6"><?= number_format($total_applications) ?> Total Applications</span>
                    </div>
                </div>
            </div>
        </div>

        <!-- Filters and Search -->
        <div class="row mb-4">
            <div class="col-12">
                <div class="card">
                    <div class="card-body">
                        <form method="GET" class="row g-3">
                            <div class="col-md-3">
                                <label class="form-label">Status Filter</label>
                                <select name="status" class="form-select">
                                    <option value="">All Statuses</option>
                                    <option value="pending" <?= $status_filter === 'pending' ? 'selected' : '' ?>>
                                        Pending (<?= $status_counts['pending'] ?? 0 ?>)
                                    </option>
                                    <option value="pre_approved" <?= $status_filter === 'pre_approved' ? 'selected' : '' ?>>
                                        Pre-approved (<?= $status_counts['pre_approved'] ?? 0 ?>)
                                    </option>
                                    <option value="document_review" <?= $status_filter === 'document_review' ? 'selected' : '' ?>>
                                        Document Review (<?= $status_counts['document_review'] ?? 0 ?>)
                                    </option>
                                    <option value="approved" <?= $status_filter === 'approved' ? 'selected' : '' ?>>
                                        Approved (<?= $status_counts['approved'] ?? 0 ?>)
                                    </option>
                                    <option value="funded" <?= $status_filter === 'funded' ? 'selected' : '' ?>>
                                        Funded (<?= $status_counts['funded'] ?? 0 ?>)
                                    </option>
                                    <option value="cancelled" <?= $status_filter === 'cancelled' ? 'selected' : '' ?>>
                                        Cancelled (<?= $status_counts['cancelled'] ?? 0 ?>)
                                    </option>
                                    <option value="rejected" <?= $status_filter === 'rejected' ? 'selected' : '' ?>>
                                        Rejected (<?= $status_counts['rejected'] ?? 0 ?>)
                                    </option>
                                </select>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Search</label>
                                <input type="text" name="search" class="form-control" 
                                       placeholder="Search by name, email, or reference number..." 
                                       value="<?= htmlspecialchars($search) ?>">
                            </div>
                            <div class="col-md-3 d-flex align-items-end">
                                <button type="submit" class="btn btn-primary me-2">
                                    <i class="fas fa-search me-1"></i>Search
                                </button>
                                <a href="applications.php" class="btn btn-outline-secondary">
                                    <i class="fas fa-times me-1"></i>Clear
                                </a>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>

        <!-- Applications Table -->
        <div class="row">
            <div class="col-12">
                <div class="card">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <h5 class="mb-0">Applications</h5>
                        <div>
                            <button class="btn btn-success btn-sm me-2" onclick="exportApplications()">
                                <i class="fas fa-download me-1"></i>Export
                            </button>
                            <button class="btn btn-primary btn-sm" onclick="bulkActions()">
                                <i class="fas fa-tasks me-1"></i>Bulk Actions
                            </button>
                        </div>
                    </div>
                    <div class="card-body p-0">
                        <?php if (!empty($applications)): ?>
                            <div class="table-responsive">
                                <table class="table table-hover mb-0">
                                    <thead class="table-dark">
                                        <tr>
                                            <th>
                                                <input type="checkbox" id="selectAll" class="form-check-input">
                                            </th>
                                            <th>Reference</th>
                                            <th>Client</th>
                                            <th>Amount</th>
                                            <th>Type</th>
                                            <th>Status</th>
                                            <th>Progress</th>
                                            <th>Date</th>
                                            <th>Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($applications as $app): 
                                            $memoStatus = $memoStatuses[$app['user_id']] ?? ['css_class' => '', 'indicator' => '', 'tooltip' => 'No memo data'];
                                        ?>
                                            <tr class="<?= $memoStatus['css_class'] ?>">
                                                <td>
                                                    <input type="checkbox" class="form-check-input app-checkbox" 
                                                           value="<?= $app['id'] ?>">
                                                </td>
                                                <td>
                                                    <strong><?= htmlspecialchars($app['reference_number']) ?></strong>
                                                </td>
                                                <td>
                                                    <div class="d-flex align-items-center">
                                                        <div class="flex-grow-1">
                                                            <strong><?= htmlspecialchars($app['first_name'] . ' ' . $app['last_name']) ?></strong>
                                                            <br>
                                                            <small class="text-muted"><?= htmlspecialchars($app['email']) ?></small>
                                                            <br>
                                                            <small class="text-muted">
                                                                <i class="fas fa-phone me-1"></i><?= htmlspecialchars($app['phone']) ?>
                                                            </small>
                                                        </div>
                                                        <?php if (!empty($memoStatus['indicator'])): ?>
                                                            <div class="memo-indicator ms-2" 
                                                                 data-bs-toggle="tooltip" 
                                                                 data-bs-placement="top" 
                                                                 title="<?= htmlspecialchars($memoStatus['tooltip']) ?>">
                                                                <?= $memoStatus['indicator'] ?>
                                                            </div>
                                                        <?php endif; ?>
                                                    </div>
                                                </td>
                                                <td>
                                                    <strong><?= formatCurrency($app['loan_amount'], $app['country']) ?></strong>
                                                    <br>
                                                    <small class="text-muted"><?= $app['loan_term_months'] ?> months</small>
                                                </td>
                                                <td><?= ucfirst(str_replace('_', ' ', $app['loan_type'])) ?></td>
                                                <td>
                                                    <span class="status-badge status-<?= $app['application_status'] ?>">
                                                        <?= ucfirst(str_replace('_', ' ', $app['application_status'])) ?>
                                                    </span>
                                                </td>
                                                <td>
                                                    <div class="d-flex align-items-center">
                                                        <small class="me-2">
                                                            <i class="fas fa-file me-1"></i><?= $app['document_count'] ?>
                                                            <i class="fas fa-signature ms-2 me-1"></i><?= $app['signature_count'] ?>
                                                            <i class="fas fa-credit-card ms-2 me-1"></i><?= $app['payment_count'] ?>
                                                        </small>
                                                    </div>
                                                </td>
                                                <td><?= formatDate($app['created_at']) ?></td>
                                                <td>
                                                    <div class="btn-group" role="group">
                                                        <a href="view-application.php?id=<?= $app['id'] ?>" 
                                                           class="btn btn-info btn-sm" title="View">
                                                            <i class="fas fa-eye"></i>
                                                        </a>
                                                        <button type="button" class="btn btn-primary btn-sm" 
                                                                onclick="showStatusModal(<?= $app['id'] ?>, '<?= $app['application_status'] ?>')"
                                                                title="Update Status">
                                                            <i class="fas fa-edit"></i>
                                                        </button>
                                                        <a href="client-profile.php?id=<?= $app['user_id'] ?>" 
                                                           class="btn btn-secondary btn-sm" title="Client Profile">
                                                            <i class="fas fa-user"></i>
                                                        </a>
                                                    </div>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php else: ?>
                            <div class="text-center py-5">
                                <i class="fas fa-file-alt fa-3x text-muted mb-3"></i>
                                <h5>No applications found</h5>
                                <p class="text-muted">No applications match your current filters.</p>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>

        <!-- Pagination -->
        <?php if ($total_pages > 1): ?>
        <div class="row mt-4">
            <div class="col-12">
                <nav>
                    <ul class="pagination justify-content-center">
                        <li class="page-item <?= $page <= 1 ? 'disabled' : '' ?>">
                            <a class="page-link" href="?page=<?= $page - 1 ?>&status=<?= urlencode($status_filter) ?>&search=<?= urlencode($search) ?>">
                                <i class="fas fa-chevron-left"></i>
                            </a>
                        </li>
                        
                        <?php for ($i = max(1, $page - 2); $i <= min($total_pages, $page + 2); $i++): ?>
                            <li class="page-item <?= $i == $page ? 'active' : '' ?>">
                                <a class="page-link" href="?page=<?= $i ?>&status=<?= urlencode($status_filter) ?>&search=<?= urlencode($search) ?>">
                                    <?= $i ?>
                                </a>
                            </li>
                        <?php endfor; ?>
                        
                        <li class="page-item <?= $page >= $total_pages ? 'disabled' : '' ?>">
                            <a class="page-link" href="?page=<?= $page + 1 ?>&status=<?= urlencode($status_filter) ?>&search=<?= urlencode($search) ?>">
                                <i class="fas fa-chevron-right"></i>
                            </a>
                        </li>
                    </ul>
                </nav>
                <div class="text-center text-muted">
                    Showing <?= ($offset + 1) ?> to <?= min($offset + $per_page, $total_applications) ?> of <?= number_format($total_applications) ?> applications
                </div>
            </div>
        </div>
        <?php endif; ?>
    </div>

    <!-- Status Update Modal -->
    <div class="modal fade" id="statusModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <form method="POST" id="statusForm">
                    <input type="hidden" name="csrf_token" value="<?= generateCSRFToken() ?>">
                    <input type="hidden" name="action" value="update_status">
                    <input type="hidden" name="application_id" id="statusApplicationId">
                    
                    <div class="modal-header">
                        <h5 class="modal-title">Update Application Status</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <div class="mb-3">
                            <label class="form-label">New Status</label>
                            <select name="status" id="statusSelect" class="form-select" required>
                                <option value="">Select Status...</option>
                                <option value="pending">Pending</option>
                                <option value="pre_approved">Pre-approved</option>
                                <option value="document_review">Document Review</option>
                                <option value="approved">Approved</option>
                                <option value="funded">Funded</option>
                                <option value="cancelled">Cancelled</option>
                                <option value="rejected">Rejected</option>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Notes</label>
                            <textarea name="notes" class="form-control" rows="3" 
                                      placeholder="Add notes about this status change..."></textarea>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary">Update Status</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function showStatusModal(applicationId, currentStatus) {
            document.getElementById('statusApplicationId').value = applicationId;
            document.getElementById('statusSelect').value = currentStatus;
            new bootstrap.Modal(document.getElementById('statusModal')).show();
        }

        // Select all functionality
        document.getElementById('selectAll').addEventListener('change', function() {
            const checkboxes = document.querySelectorAll('.app-checkbox');
            checkboxes.forEach(cb => cb.checked = this.checked);
        });

        function exportApplications() {
            // Implementation for export functionality
            alert('Export functionality would be implemented here');
        }

        function bulkActions() {
            const selected = document.querySelectorAll('.app-checkbox:checked');
            if (selected.length === 0) {
                alert('Please select at least one application');
                return;
            }
            alert('Bulk actions functionality would be implemented here');
        }

        // Initialize memo tooltips
        var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
        var tooltipList = tooltipTriggerList.map(function (tooltipTriggerEl) {
            return new bootstrap.Tooltip(tooltipTriggerEl);
        });
        
        // Auto-refresh every 2 minutes
        setInterval(function() {
            location.reload();
        }, 120000);
    </script>
</body>
</html>
