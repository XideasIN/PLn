<?php
require_once '../includes/config.php';
require_once '../includes/functions.php';
require_once '../includes/auth.php';
require_once '../includes/admin_functions.php';

// Check if user is logged in and is admin
if (!isLoggedIn() || !isAdmin()) {
    header('Location: ../login.php');
    exit();
}

$current_admin = getCurrentUser();

try {
    $db = getDB();
    
    // Get document statistics
    $stats = [];
    
    // Total documents
    $stmt = $db->query("SELECT COUNT(*) FROM documents");
    $stats['total_documents'] = $stmt->fetchColumn();
    
    // Pending documents
    $stmt = $db->query("SELECT COUNT(*) FROM documents WHERE upload_status = 'pending'");
    $stats['pending_documents'] = $stmt->fetchColumn();
    
    // Approved documents
    $stmt = $db->query("SELECT COUNT(*) FROM documents WHERE upload_status = 'approved'");
    $stats['approved_documents'] = $stmt->fetchColumn();
    
    // Rejected documents
    $stmt = $db->query("SELECT COUNT(*) FROM documents WHERE upload_status = 'rejected'");
    $stats['rejected_documents'] = $stmt->fetchColumn();
    
    // Documents by type
    $stmt = $db->query("
        SELECT document_type, COUNT(*) as count
        FROM documents 
        GROUP BY document_type
        ORDER BY count DESC
    ");
    $stats['by_type'] = $stmt->fetchAll();
    
    // Recent activity (last 7 days)
    $stmt = $db->query("
        SELECT DATE(created_at) as date, COUNT(*) as count
        FROM documents 
        WHERE created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)
        GROUP BY DATE(created_at)
        ORDER BY date DESC
    ");
    $stats['recent_activity'] = $stmt->fetchAll();
    
    // Clients with incomplete documents
    $stmt = $db->query("
        SELECT u.id, u.first_name, u.last_name, u.reference_number,
               COUNT(d.id) as uploaded_docs,
               GROUP_CONCAT(DISTINCT d.document_type) as uploaded_types
        FROM users u
        LEFT JOIN documents d ON u.id = d.user_id AND d.upload_status != 'rejected'
        WHERE u.status IN ('pending', 'document_review')
        GROUP BY u.id
        HAVING uploaded_docs < 3
        ORDER BY u.created_at DESC
        LIMIT 10
    ");
    $stats['incomplete_clients'] = $stmt->fetchAll();
    
    // Top reviewers (admins who have reviewed most documents)
    $stmt = $db->query("
        SELECT u.first_name, u.last_name, COUNT(d.id) as reviewed_count
        FROM documents d
        JOIN users u ON d.verified_by = u.id
        WHERE d.verified_at IS NOT NULL
        GROUP BY d.verified_by
        ORDER BY reviewed_count DESC
        LIMIT 5
    ");
    $stats['top_reviewers'] = $stmt->fetchAll();
    
} catch (Exception $e) {
    $error = "Error loading dashboard: " . $e->getMessage();
}

$page_title = "Document Review Dashboard";
include '../includes/admin_header.php';
?>

<div class="container-fluid">
    <div class="row">
        <div class="col-12">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <h1 class="h3 mb-0">Document Review Dashboard</h1>
                <div>
                    <a href="documents.php" class="btn btn-primary">
                        <i class="fas fa-folder-open"></i> Review Documents
                    </a>
                    <a href="applications.php" class="btn btn-outline-secondary">
                        <i class="fas fa-list"></i> Applications
                    </a>
                </div>
            </div>
        </div>
    </div>
    
    <?php if (isset($error)): ?>
        <div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div>
    <?php endif; ?>
    
    <!-- Statistics Cards -->
    <div class="row mb-4">
        <div class="col-xl-3 col-md-6 mb-4">
            <div class="card border-left-primary shadow h-100 py-2">
                <div class="card-body">
                    <div class="row no-gutters align-items-center">
                        <div class="col mr-2">
                            <div class="text-xs font-weight-bold text-primary text-uppercase mb-1">
                                Total Documents
                            </div>
                            <div class="h5 mb-0 font-weight-bold text-gray-800">
                                <?php echo number_format($stats['total_documents']); ?>
                            </div>
                        </div>
                        <div class="col-auto">
                            <i class="fas fa-file-alt fa-2x text-gray-300"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="col-xl-3 col-md-6 mb-4">
            <div class="card border-left-warning shadow h-100 py-2">
                <div class="card-body">
                    <div class="row no-gutters align-items-center">
                        <div class="col mr-2">
                            <div class="text-xs font-weight-bold text-warning text-uppercase mb-1">
                                Pending Review
                            </div>
                            <div class="h5 mb-0 font-weight-bold text-gray-800">
                                <?php echo number_format($stats['pending_documents']); ?>
                            </div>
                        </div>
                        <div class="col-auto">
                            <i class="fas fa-clock fa-2x text-gray-300"></i>
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
                                Approved
                            </div>
                            <div class="h5 mb-0 font-weight-bold text-gray-800">
                                <?php echo number_format($stats['approved_documents']); ?>
                            </div>
                        </div>
                        <div class="col-auto">
                            <i class="fas fa-check-circle fa-2x text-gray-300"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="col-xl-3 col-md-6 mb-4">
            <div class="card border-left-danger shadow h-100 py-2">
                <div class="card-body">
                    <div class="row no-gutters align-items-center">
                        <div class="col mr-2">
                            <div class="text-xs font-weight-bold text-danger text-uppercase mb-1">
                                Rejected
                            </div>
                            <div class="h5 mb-0 font-weight-bold text-gray-800">
                                <?php echo number_format($stats['rejected_documents']); ?>
                            </div>
                        </div>
                        <div class="col-auto">
                            <i class="fas fa-times-circle fa-2x text-gray-300"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <div class="row">
        <!-- Document Types Chart -->
        <div class="col-xl-4 col-lg-5">
            <div class="card shadow mb-4">
                <div class="card-header py-3">
                    <h6 class="m-0 font-weight-bold text-primary">Documents by Type</h6>
                </div>
                <div class="card-body">
                    <?php if (!empty($stats['by_type'])): ?>
                        <?php foreach ($stats['by_type'] as $type): ?>
                            <div class="mb-3">
                                <div class="d-flex justify-content-between">
                                    <span class="text-sm font-weight-bold">
                                        <?php echo ucfirst(str_replace('_', ' ', $type['document_type'])); ?>
                                    </span>
                                    <span class="text-sm"><?php echo $type['count']; ?></span>
                                </div>
                                <div class="progress" style="height: 8px;">
                                    <div class="progress-bar bg-primary" role="progressbar" 
                                         style="width: <?php echo ($type['count'] / $stats['total_documents']) * 100; ?>%"></div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <p class="text-muted">No documents uploaded yet.</p>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        
        <!-- Recent Activity -->
        <div class="col-xl-8 col-lg-7">
            <div class="card shadow mb-4">
                <div class="card-header py-3">
                    <h6 class="m-0 font-weight-bold text-primary">Recent Upload Activity (Last 7 Days)</h6>
                </div>
                <div class="card-body">
                    <?php if (!empty($stats['recent_activity'])): ?>
                        <div class="table-responsive">
                            <table class="table table-sm">
                                <thead>
                                    <tr>
                                        <th>Date</th>
                                        <th>Documents Uploaded</th>
                                        <th>Activity</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($stats['recent_activity'] as $activity): ?>
                                        <tr>
                                            <td><?php echo date('M j, Y', strtotime($activity['date'])); ?></td>
                                            <td><?php echo $activity['count']; ?></td>
                                            <td>
                                                <div class="progress" style="height: 10px; width: 100px;">
                                                    <div class="progress-bar bg-info" role="progressbar" 
                                                         style="width: <?php echo min(100, ($activity['count'] / 10) * 100); ?>%"></div>
                                                </div>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php else: ?>
                        <p class="text-muted">No recent activity.</p>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
    
    <div class="row">
        <!-- Clients with Incomplete Documents -->
        <div class="col-xl-8">
            <div class="card shadow mb-4">
                <div class="card-header py-3">
                    <h6 class="m-0 font-weight-bold text-primary">Clients with Incomplete Documents</h6>
                </div>
                <div class="card-body">
                    <?php if (!empty($stats['incomplete_clients'])): ?>
                        <div class="table-responsive">
                            <table class="table table-sm">
                                <thead>
                                    <tr>
                                        <th>Client</th>
                                        <th>Reference</th>
                                        <th>Uploaded</th>
                                        <th>Missing</th>
                                        <th>Action</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($stats['incomplete_clients'] as $client): ?>
                                        <?php
                                        $uploaded_types = $client['uploaded_types'] ? explode(',', $client['uploaded_types']) : [];
                                        $required_types = ['photo_id', 'proof_income', 'proof_address'];
                                        $missing_types = array_diff($required_types, $uploaded_types);
                                        ?>
                                        <tr>
                                            <td>
                                                <strong><?php echo htmlspecialchars($client['first_name'] . ' ' . $client['last_name']); ?></strong>
                                            </td>
                                            <td>
                                                <span class="badge badge-secondary"><?php echo htmlspecialchars($client['reference_number']); ?></span>
                                            </td>
                                            <td>
                                                <span class="badge badge-success"><?php echo $client['uploaded_docs']; ?>/3</span>
                                            </td>
                                            <td>
                                                <?php foreach ($missing_types as $missing): ?>
                                                    <span class="badge badge-warning badge-sm">
                                                        <?php echo ucfirst(str_replace('_', ' ', $missing)); ?>
                                                    </span>
                                                <?php endforeach; ?>
                                            </td>
                                            <td>
                                                <a href="applications.php?search=<?php echo urlencode($client['reference_number']); ?>" 
                                                   class="btn btn-sm btn-outline-primary">
                                                    <i class="fas fa-eye"></i> View
                                                </a>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php else: ?>
                        <p class="text-muted">All active clients have complete documents.</p>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        
        <!-- Top Reviewers -->
        <div class="col-xl-4">
            <div class="card shadow mb-4">
                <div class="card-header py-3">
                    <h6 class="m-0 font-weight-bold text-primary">Top Document Reviewers</h6>
                </div>
                <div class="card-body">
                    <?php if (!empty($stats['top_reviewers'])): ?>
                        <?php foreach ($stats['top_reviewers'] as $index => $reviewer): ?>
                            <div class="d-flex align-items-center mb-3">
                                <div class="mr-3">
                                    <div class="icon-circle bg-primary">
                                        <i class="fas fa-user text-white"></i>
                                    </div>
                                </div>
                                <div class="flex-grow-1">
                                    <div class="small font-weight-bold">
                                        <?php echo htmlspecialchars($reviewer['first_name'] . ' ' . $reviewer['last_name']); ?>
                                    </div>
                                    <div class="small text-muted">
                                        <?php echo $reviewer['reviewed_count']; ?> documents reviewed
                                    </div>
                                </div>
                                <div class="text-right">
                                    <span class="badge badge-primary">#<?php echo $index + 1; ?></span>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <p class="text-muted">No review activity yet.</p>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<style>
.border-left-primary {
    border-left: 0.25rem solid #4e73df !important;
}
.border-left-success {
    border-left: 0.25rem solid #1cc88a !important;
}
.border-left-warning {
    border-left: 0.25rem solid #f6c23e !important;
}
.border-left-danger {
    border-left: 0.25rem solid #e74a3b !important;
}
.icon-circle {
    height: 2.5rem;
    width: 2.5rem;
    border-radius: 100%;
    display: flex;
    align-items: center;
    justify-content: center;
}
.progress {
    background-color: #eaecf4;
}
.badge-sm {
    font-size: 0.7em;
}
</style>

<script>
// Auto-refresh pending count every 30 seconds
setInterval(function() {
    fetch('ajax/get_pending_documents_count.php')
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                // Update pending count if it exists
                const pendingElement = document.querySelector('.border-left-warning .h5');
                if (pendingElement && data.count !== undefined) {
                    pendingElement.textContent = new Intl.NumberFormat().format(data.count);
                }
            }
        })
        .catch(error => console.log('Auto-refresh error:', error));
}, 30000);
</script>

<?php include '../includes/admin_footer.php'; ?>