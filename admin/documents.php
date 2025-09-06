<?php
/**
 * Document Review System - Admin Panel
 * LoanFlow Personal Loan Management System
 */

require_once '../includes/functions.php';
require_once '../includes/admin_functions.php';
require_once '../includes/document_email_templates.php';

// Require admin login
requireAdminLogin();

$current_admin = getCurrentUser();

// Handle document approval/rejection
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCSRFToken($_POST['csrf_token'] ?? '')) {
        $error = "Security token mismatch. Please try again.";
    } else {
        $action = $_POST['action'] ?? '';
        $document_id = (int)($_POST['document_id'] ?? 0);
        $notes = trim($_POST['notes'] ?? '');
        
        if ($action && $document_id) {
            try {
                $db = getDB();
                
                // Get document details
                $stmt = $db->prepare("
                    SELECT d.*, u.first_name, u.last_name, u.email, u.reference_number,
                           la.id as application_id, la.application_status
                    FROM documents d
                    JOIN users u ON d.user_id = u.id
                    LEFT JOIN loan_applications la ON d.application_id = la.id
                    WHERE d.id = ?
                ");
                $stmt->execute([$document_id]);
                $document = $stmt->fetch();
                
                if (!$document) {
                    $error = "Document not found.";
                } else {
                    if ($action === 'approve') {
                        // Approve document
                        $stmt = $db->prepare("
                            UPDATE documents 
                            SET upload_status = 'verified', 
                                verification_notes = ?, 
                                verified_by = ?, 
                                verified_at = NOW()
                            WHERE id = ?
                        ");
                        $stmt->execute([$notes, $current_admin['id'], $document_id]);
                        
                        // Add memo
                        addMemo($document['user_id'], "Document approved: " . ucfirst(str_replace('_', ' ', $document['document_type'])), 'document_approved', false, $current_admin['id']);
                        
                        // Check if all required documents are approved
                        $stmt = $db->prepare("
                            SELECT COUNT(*) as approved_count
                            FROM documents 
                            WHERE user_id = ? AND upload_status = 'verified'
                            AND document_type IN ('photo_id', 'proof_income', 'proof_address')
                        ");
                        $stmt->execute([$document['user_id']]);
                        $approved_count = $stmt->fetch()['approved_count'];
                        
                        if ($approved_count >= 3) {
                            // All required documents approved, advance application
                            if ($document['application_id']) {
                                $stmt = $db->prepare("UPDATE loan_applications SET application_status = 'approved', current_step = 4 WHERE id = ?");
        $stmt->execute([$application_id]);
        
        // Send payment instruction email when reaching step 4
        require_once '../includes/email.php';
        $application = getApplicationById($application_id);
        if ($application) {
            sendPaymentInstructionEmail($application['user_id'], [
                'loan_amount' => $application['loan_amount'],
                'reference_number' => $application['reference_number']
            ]);
        }
        
        $stmt = $db->prepare("SELECT 1 FROM dual WHERE 1=0"); // Dummy statement to maintain flow
                                $stmt->execute([$document['application_id']]);
                                
                                addMemo($document['user_id'], "All documents approved - Application advanced to approval stage", 'status_change', false, $current_admin['id']);
                            }
                            
                            // Send approval email for all documents
                            autoSendDocumentApprovalEmail($document['user_id']);
                            addMemo($document['user_id'], "Document approval notification sent to client", 'system', false, $current_admin['id']);
                        }
                        
                        $success = "Document approved successfully.";
                        
                    } elseif ($action === 'reject') {
                        // Reject document
                        $stmt = $db->prepare("
                            UPDATE documents 
                            SET upload_status = 'rejected', 
                                verification_notes = ?, 
                                verified_by = ?, 
                                verified_at = NOW()
                            WHERE id = ?
                        ");
                        $stmt->execute([$notes, $current_admin['id'], $document_id]);
                        
                        // Add memo
                        addMemo($document['user_id'], "Document rejected: " . ucfirst(str_replace('_', ' ', $document['document_type'])) . " - " . $notes, 'document_rejected', false, $current_admin['id']);
                        
                        // Send rejection email with custom notes
                         autoSendDocumentRejectionEmail($document['user_id'], $notes);
                         addMemo($document['user_id'], "Document rejection notification sent to client", 'system', false, $current_admin['id']);
                         
                         $success = "Document rejected. Client will be notified.";
                     }
                    
                    // Log audit
                    logAudit('document_' . $action, 'documents', $document_id, $current_admin['id'], [
                        'document_type' => $document['document_type'],
                        'user_id' => $document['user_id'],
                        'notes' => $notes
                    ]);
                }
            } catch (Exception $e) {
                error_log("Document review failed: " . $e->getMessage());
                $error = "Failed to process document. Please try again.";
            }
        }
    }
}

// Get filter parameters
$status_filter = $_GET['status'] ?? 'pending';
$type_filter = $_GET['type'] ?? '';
$search = trim($_GET['search'] ?? '');
$page = max(1, (int)($_GET['page'] ?? 1));
$per_page = 20;
$offset = ($page - 1) * $per_page;

// Build query
try {
    $db = getDB();
    
    $where_conditions = [];
    $params = [];
    
    // Status filter
    if ($status_filter && $status_filter !== 'all') {
        if ($status_filter === 'pending') {
            $where_conditions[] = "d.upload_status IN ('uploaded', 'pending')";
        } else {
            $where_conditions[] = "d.upload_status = ?";
            $params[] = $status_filter;
        }
    }
    
    // Type filter
    if ($type_filter) {
        $where_conditions[] = "d.document_type = ?";
        $params[] = $type_filter;
    }
    
    // Search filter
    if ($search) {
        $where_conditions[] = "(u.first_name LIKE ? OR u.last_name LIKE ? OR u.email LIKE ? OR u.reference_number LIKE ?)";
        $search_param = '%' . $search . '%';
        $params = array_merge($params, [$search_param, $search_param, $search_param, $search_param]);
    }
    
    $where_clause = !empty($where_conditions) ? 'WHERE ' . implode(' AND ', $where_conditions) : '';
    
    // Get documents
    $stmt = $db->prepare("
        SELECT d.*, u.first_name, u.last_name, u.email, u.reference_number,
               la.loan_amount, la.application_status,
               admin.first_name as verified_by_name
        FROM documents d
        JOIN users u ON d.user_id = u.id
        LEFT JOIN loan_applications la ON d.application_id = la.id
        LEFT JOIN users admin ON d.verified_by = admin.id
        $where_clause
        ORDER BY 
            CASE d.upload_status 
                WHEN 'uploaded' THEN 1 
                WHEN 'pending' THEN 2 
                WHEN 'verified' THEN 3 
                WHEN 'rejected' THEN 4 
            END,
            d.created_at DESC
        LIMIT $per_page OFFSET $offset
    ");
    $stmt->execute($params);
    $documents = $stmt->fetchAll();
    
    // Get total count
    $stmt = $db->prepare("
        SELECT COUNT(*) as total
        FROM documents d
        JOIN users u ON d.user_id = u.id
        LEFT JOIN loan_applications la ON d.application_id = la.id
        $where_clause
    ");
    $stmt->execute($params);
    $total_documents = $stmt->fetch()['total'];
    
    // Get status counts
    $stmt = $db->query("
        SELECT 
            upload_status,
            COUNT(*) as count
        FROM documents d
        JOIN users u ON d.user_id = u.id
        GROUP BY upload_status
    ");
    $status_counts = [];
    while ($row = $stmt->fetch()) {
        $status_counts[$row['upload_status']] = $row['count'];
    }
    
    // Calculate pending count (uploaded + pending)
    $status_counts['pending_total'] = ($status_counts['uploaded'] ?? 0) + ($status_counts['pending'] ?? 0);
    
} catch (Exception $e) {
    error_log("Get documents failed: " . $e->getMessage());
    $documents = [];
    $total_documents = 0;
    $status_counts = [];
}

$total_pages = ceil($total_documents / $per_page);

include '../includes/admin_header.php';
?>

<div class="container-fluid">
    <!-- Flash Messages -->
    <?php if (isset($success)): ?>
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            <i class="fas fa-check-circle me-2"></i><?= htmlspecialchars($success) ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <?php if (isset($error)): ?>
        <div class="alert alert-danger alert-dismissible fade show" role="alert">
            <i class="fas fa-exclamation-triangle me-2"></i><?= htmlspecialchars($error) ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <!-- Page Header -->
    <div class="row mb-4">
        <div class="col-12">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <h2><i class="fas fa-folder-open me-2"></i>Document Review Center</h2>
                    <p class="text-muted">Review and approve client-submitted documents</p>
                </div>
                <div class="text-end">
                    <div class="badge bg-warning fs-6">
                        <?= $status_counts['pending_total'] ?? 0 ?> Pending Review
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Status Cards -->
    <div class="row mb-4">
        <div class="col-md-3">
            <div class="card bg-warning text-white">
                <div class="card-body">
                    <div class="d-flex justify-content-between">
                        <div>
                            <h4><?= $status_counts['pending_total'] ?? 0 ?></h4>
                            <p class="mb-0">Pending Review</p>
                        </div>
                        <div class="align-self-center">
                            <i class="fas fa-clock fa-2x"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card bg-success text-white">
                <div class="card-body">
                    <div class="d-flex justify-content-between">
                        <div>
                            <h4><?= $status_counts['verified'] ?? 0 ?></h4>
                            <p class="mb-0">Approved</p>
                        </div>
                        <div class="align-self-center">
                            <i class="fas fa-check-circle fa-2x"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card bg-danger text-white">
                <div class="card-body">
                    <div class="d-flex justify-content-between">
                        <div>
                            <h4><?= $status_counts['rejected'] ?? 0 ?></h4>
                            <p class="mb-0">Rejected</p>
                        </div>
                        <div class="align-self-center">
                            <i class="fas fa-times-circle fa-2x"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card bg-info text-white">
                <div class="card-body">
                    <div class="d-flex justify-content-between">
                        <div>
                            <h4><?= array_sum($status_counts) ?></h4>
                            <p class="mb-0">Total Documents</p>
                        </div>
                        <div class="align-self-center">
                            <i class="fas fa-file-alt fa-2x"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Filters -->
    <div class="card mb-4">
        <div class="card-body">
            <form method="GET" class="row g-3">
                <div class="col-md-3">
                    <label class="form-label">Status</label>
                    <select name="status" class="form-select">
                        <option value="pending" <?= $status_filter === 'pending' ? 'selected' : '' ?>>Pending Review</option>
                        <option value="all" <?= $status_filter === 'all' ? 'selected' : '' ?>>All Status</option>
                        <option value="verified" <?= $status_filter === 'verified' ? 'selected' : '' ?>>Approved</option>
                        <option value="rejected" <?= $status_filter === 'rejected' ? 'selected' : '' ?>>Rejected</option>
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label">Document Type</label>
                    <select name="type" class="form-select">
                        <option value="">All Types</option>
                        <option value="photo_id" <?= $type_filter === 'photo_id' ? 'selected' : '' ?>>Photo ID</option>
                        <option value="proof_income" <?= $type_filter === 'proof_income' ? 'selected' : '' ?>>Proof of Income</option>
                        <option value="proof_address" <?= $type_filter === 'proof_address' ? 'selected' : '' ?>>Proof of Address</option>
                        <option value="bank_statement" <?= $type_filter === 'bank_statement' ? 'selected' : '' ?>>Bank Statement</option>
                        <option value="other" <?= $type_filter === 'other' ? 'selected' : '' ?>>Other</option>
                    </select>
                </div>
                <div class="col-md-4">
                    <label class="form-label">Search Client</label>
                    <input type="text" name="search" class="form-control" 
                           placeholder="Name, email, or reference number..." 
                           value="<?= htmlspecialchars($search) ?>">
                </div>
                <div class="col-md-2">
                    <label class="form-label">&nbsp;</label>
                    <div class="d-grid">
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-search me-1"></i>Filter
                        </button>
                    </div>
                </div>
            </form>
        </div>
    </div>

    <!-- Documents Table -->
    <div class="card">
        <div class="card-header">
            <h5 class="mb-0">Document Review Queue</h5>
        </div>
        <div class="card-body">
            <?php if (empty($documents)): ?>
                <div class="text-center py-4">
                    <i class="fas fa-folder-open fa-3x text-muted mb-3"></i>
                    <h5 class="text-muted">No documents found</h5>
                    <p class="text-muted">No documents match your current filters.</p>
                </div>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-hover">
                        <thead>
                            <tr>
                                <th>Client</th>
                                <th>Document Type</th>
                                <th>File Info</th>
                                <th>Status</th>
                                <th>Uploaded</th>
                                <th>Verified By</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($documents as $doc): ?>
                                <tr>
                                    <td>
                                        <div>
                                            <strong><?= htmlspecialchars($doc['first_name'] . ' ' . $doc['last_name']) ?></strong>
                                            <br>
                                            <small class="text-muted"><?= htmlspecialchars($doc['email']) ?></small>
                                            <br>
                                            <span class="badge bg-secondary"><?= htmlspecialchars($doc['reference_number']) ?></span>
                                        </div>
                                    </td>
                                    <td>
                                        <span class="badge bg-info">
                                            <?= ucfirst(str_replace('_', ' ', $doc['document_type'])) ?>
                                        </span>
                                    </td>
                                    <td>
                                        <div>
                                            <strong><?= htmlspecialchars($doc['original_filename']) ?></strong>
                                            <br>
                                            <small class="text-muted">
                                                <?= formatFileSize($doc['file_size']) ?> • 
                                                <?= htmlspecialchars($doc['mime_type']) ?>
                                            </small>
                                        </div>
                                    </td>
                                    <td>
                                        <?php
                                        $status_class = [
                                            'uploaded' => 'warning',
                                            'pending' => 'warning', 
                                            'verified' => 'success',
                                            'rejected' => 'danger'
                                        ][$doc['upload_status']] ?? 'secondary';
                                        
                                        $status_text = [
                                            'uploaded' => 'Pending Review',
                                            'pending' => 'Pending Review',
                                            'verified' => 'Approved',
                                            'rejected' => 'Rejected'
                                        ][$doc['upload_status']] ?? ucfirst($doc['upload_status']);
                                        ?>
                                        <span class="badge bg-<?= $status_class ?>">
                                            <?= $status_text ?>
                                        </span>
                                        <?php if ($doc['verification_notes']): ?>
                                            <br>
                                            <small class="text-muted" title="<?= htmlspecialchars($doc['verification_notes']) ?>">
                                                <i class="fas fa-sticky-note"></i> Notes
                                            </small>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <small><?= date('M j, Y g:i A', strtotime($doc['created_at'])) ?></small>
                                    </td>
                                    <td>
                                        <?php if ($doc['verified_by_name']): ?>
                                            <small><?= htmlspecialchars($doc['verified_by_name']) ?></small>
                                            <br>
                                            <small class="text-muted"><?= date('M j, Y', strtotime($doc['verified_at'])) ?></small>
                                        <?php else: ?>
                                            <small class="text-muted">Not reviewed</small>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <div class="btn-group" role="group">
                                            <button type="button" class="btn btn-sm btn-outline-primary" 
                                                    onclick="viewDocument(<?= $doc['id'] ?>)" 
                                                    title="View Document">
                                                <i class="fas fa-eye"></i>
                                            </button>
                                            <?php if (in_array($doc['upload_status'], ['uploaded', 'pending'])): ?>
                                                <button type="button" class="btn btn-sm btn-success" 
                                                        onclick="reviewDocument(<?= $doc['id'] ?>, 'approve', '<?= htmlspecialchars($doc['first_name'] . ' ' . $doc['last_name']) ?>', '<?= ucfirst(str_replace('_', ' ', $doc['document_type'])) ?>')" 
                                                        title="Approve Document">
                                                    <i class="fas fa-check"></i>
                                                </button>
                                                <button type="button" class="btn btn-sm btn-danger" 
                                                        onclick="reviewDocument(<?= $doc['id'] ?>, 'reject', '<?= htmlspecialchars($doc['first_name'] . ' ' . $doc['last_name']) ?>', '<?= ucfirst(str_replace('_', ' ', $doc['document_type'])) ?>')" 
                                                        title="Reject Document">
                                                    <i class="fas fa-times"></i>
                                                </button>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>

                <!-- Pagination -->
                <?php if ($total_pages > 1): ?>
                    <nav class="mt-4">
                        <ul class="pagination justify-content-center">
                            <?php if ($page > 1): ?>
                                <li class="page-item">
                                    <a class="page-link" href="?<?= http_build_query(array_merge($_GET, ['page' => $page - 1])) ?>">
                                        <i class="fas fa-chevron-left"></i>
                                    </a>
                                </li>
                            <?php endif; ?>
                            
                            <?php for ($i = max(1, $page - 2); $i <= min($total_pages, $page + 2); $i++): ?>
                                <li class="page-item <?= $i === $page ? 'active' : '' ?>">
                                    <a class="page-link" href="?<?= http_build_query(array_merge($_GET, ['page' => $i])) ?>">
                                        <?= $i ?>
                                    </a>
                                </li>
                            <?php endfor; ?>
                            
                            <?php if ($page < $total_pages): ?>
                                <li class="page-item">
                                    <a class="page-link" href="?<?= http_build_query(array_merge($_GET, ['page' => $page + 1])) ?>">
                                        <i class="fas fa-chevron-right"></i>
                                    </a>
                                </li>
                            <?php endif; ?>
                        </ul>
                    </nav>
                <?php endif; ?>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- Document Review Modal -->
<div class="modal fade" id="reviewModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST">
                <input type="hidden" name="csrf_token" value="<?= generateCSRFToken() ?>">
                <input type="hidden" name="document_id" id="reviewDocumentId">
                <input type="hidden" name="action" id="reviewAction">
                
                <div class="modal-header">
                    <h5 class="modal-title" id="reviewModalTitle">Review Document</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <strong>Client:</strong> <span id="reviewClientName"></span><br>
                        <strong>Document:</strong> <span id="reviewDocumentType"></span>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Notes</label>
                        <textarea name="notes" class="form-control" rows="3" 
                                  placeholder="Add notes about your decision..."></textarea>
                        <div class="form-text">These notes will be visible to the client and other admins.</div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn" id="reviewSubmitBtn">Confirm</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Document View Modal -->
<div class="modal fade" id="viewModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">View Document</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div id="documentViewer" class="text-center">
                    <div class="spinner-border" role="status">
                        <span class="visually-hidden">Loading...</span>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
function reviewDocument(documentId, action, clientName, documentType) {
    document.getElementById('reviewDocumentId').value = documentId;
    document.getElementById('reviewAction').value = action;
    document.getElementById('reviewClientName').textContent = clientName;
    document.getElementById('reviewDocumentType').textContent = documentType;
    
    const modal = document.getElementById('reviewModal');
    const title = document.getElementById('reviewModalTitle');
    const submitBtn = document.getElementById('reviewSubmitBtn');
    
    if (action === 'approve') {
        title.textContent = 'Approve Document';
        submitBtn.textContent = 'Approve Document';
        submitBtn.className = 'btn btn-success';
    } else {
        title.textContent = 'Reject Document';
        submitBtn.textContent = 'Reject Document';
        submitBtn.className = 'btn btn-danger';
    }
    
    new bootstrap.Modal(modal).show();
}

function viewDocument(documentId) {
    const modal = new bootstrap.Modal(document.getElementById('viewModal'));
    const viewer = document.getElementById('documentViewer');
    
    // Show loading
    viewer.innerHTML = '<div class="spinner-border" role="status"><span class="visually-hidden">Loading...</span></div>';
    
    modal.show();
    
    // Load document (you can implement this based on your file serving system)
    fetch(`view_document.php?id=${documentId}`)
        .then(response => {
            if (response.ok) {
                return response.text();
            }
            throw new Error('Failed to load document');
        })
        .then(html => {
            viewer.innerHTML = html;
        })
        .catch(error => {
            viewer.innerHTML = '<div class="alert alert-danger">Failed to load document: ' + error.message + '</div>';
        });
}

// Auto-refresh pending count every 30 seconds
setInterval(() => {
    if (window.location.search.includes('status=pending') || !window.location.search.includes('status=')) {
        fetch(window.location.href)
            .then(response => response.text())
            .then(html => {
                const parser = new DOMParser();
                const doc = parser.parseFromString(html, 'text/html');
                const newCount = doc.querySelector('.badge.bg-warning.fs-6')?.textContent;
                if (newCount) {
                    document.querySelector('.badge.bg-warning.fs-6').textContent = newCount;
                }
            })
            .catch(error => console.log('Auto-refresh failed:', error));
    }
}, 30000);
</script>

<?php include '../includes/admin_footer.php'; ?>