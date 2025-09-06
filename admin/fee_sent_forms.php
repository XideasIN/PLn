<?php
/**
 * Fee Sent Forms Management
 * LoanFlow Personal Loan Management System
 * Admin interface for managing country-specific fee payment submissions
 */

require_once '../includes/config.php';
require_once '../includes/auth.php';
require_once '../includes/functions.php';

// Check if user is admin
if (!isAdmin()) {
    header('Location: ../login.php');
    exit();
}

// Handle form actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        switch ($_POST['action']) {
            case 'update_status':
                $form_id = (int)$_POST['form_id'];
                $status = $_POST['status'];
                $admin_notes = $_POST['admin_notes'] ?? '';
                
                $stmt = $pdo->prepare("
                    UPDATE fee_sent_forms 
                    SET status = ?, admin_notes = ?, reviewed_by = ?, reviewed_at = NOW() 
                    WHERE id = ?
                ");
                $stmt->execute([$status, $admin_notes, $_SESSION['user_id'], $form_id]);
                
                $_SESSION['success'] = 'Fee form status updated successfully.';
                break;
                
            case 'bulk_update':
                if (isset($_POST['selected_forms']) && is_array($_POST['selected_forms'])) {
                    $bulk_status = $_POST['bulk_status'];
                    $bulk_notes = $_POST['bulk_notes'] ?? '';
                    
                    $placeholders = str_repeat('?,', count($_POST['selected_forms']) - 1) . '?';
                    $stmt = $pdo->prepare("
                        UPDATE fee_sent_forms 
                        SET status = ?, admin_notes = ?, reviewed_by = ?, reviewed_at = NOW() 
                        WHERE id IN ($placeholders)
                    ");
                    
                    $params = array_merge([$bulk_status, $bulk_notes, $_SESSION['user_id']], $_POST['selected_forms']);
                    $stmt->execute($params);
                    
                    $_SESSION['success'] = count($_POST['selected_forms']) . ' fee forms updated successfully.';
                }
                break;
        }
        
        header('Location: fee_sent_forms.php');
        exit();
    }
}

// Get filter parameters
$status_filter = $_GET['status'] ?? '';
$country_filter = $_GET['country'] ?? '';
$payment_method_filter = $_GET['payment_method'] ?? '';
$date_from = $_GET['date_from'] ?? '';
$date_to = $_GET['date_to'] ?? '';
$search = $_GET['search'] ?? '';
$page = max(1, (int)($_GET['page'] ?? 1));
$per_page = 25;
$offset = ($page - 1) * $per_page;

// Build query with filters
$where_conditions = [];
$params = [];

if ($status_filter) {
    $where_conditions[] = "fsf.status = ?";
    $params[] = $status_filter;
}

if ($country_filter) {
    $where_conditions[] = "fsf.country = ?";
    $params[] = $country_filter;
}

if ($payment_method_filter) {
    $where_conditions[] = "fsf.payment_method = ?";
    $params[] = $payment_method_filter;
}

if ($date_from) {
    $where_conditions[] = "fsf.date_sent >= ?";
    $params[] = $date_from;
}

if ($date_to) {
    $where_conditions[] = "fsf.date_sent <= ?";
    $params[] = $date_to;
}

if ($search) {
    $where_conditions[] = "(u.first_name LIKE ? OR u.last_name LIKE ? OR u.email LIKE ? OR fsf.transaction_reference LIKE ?)";
    $search_param = "%$search%";
    $params = array_merge($params, [$search_param, $search_param, $search_param, $search_param]);
}

$where_clause = $where_conditions ? 'WHERE ' . implode(' AND ', $where_conditions) : '';

// Get fee sent forms with user and application details
$stmt = $pdo->prepare("
    SELECT 
        fsf.*,
        u.first_name, u.last_name, u.email, u.phone,
        la.loan_amount, la.loan_purpose, la.status as application_status,
        reviewer.first_name as reviewer_first_name, reviewer.last_name as reviewer_last_name
    FROM fee_sent_forms fsf
    JOIN users u ON fsf.user_id = u.id
    JOIN loan_applications la ON fsf.application_id = la.id
    LEFT JOIN users reviewer ON fsf.reviewed_by = reviewer.id
    $where_clause
    ORDER BY fsf.created_at DESC
    LIMIT $per_page OFFSET $offset
");
$stmt->execute($params);
$fee_forms = $stmt->fetchAll();

// Get total count for pagination
$count_stmt = $pdo->prepare("
    SELECT COUNT(*) 
    FROM fee_sent_forms fsf
    JOIN users u ON fsf.user_id = u.id
    JOIN loan_applications la ON fsf.application_id = la.id
    $where_clause
");
$count_stmt->execute($params);
$total_forms = $count_stmt->fetchColumn();
$total_pages = ceil($total_forms / $per_page);

// Get statistics
$stats_stmt = $pdo->query("
    SELECT 
        COUNT(*) as total_forms,
        COUNT(CASE WHEN status = 'pending' THEN 1 END) as pending_forms,
        COUNT(CASE WHEN status = 'confirmed' THEN 1 END) as confirmed_forms,
        COUNT(CASE WHEN status = 'rejected' THEN 1 END) as rejected_forms,
        COUNT(CASE WHEN status = 'under_review' THEN 1 END) as under_review_forms,
        SUM(CASE WHEN status = 'confirmed' THEN amount_sent ELSE 0 END) as total_confirmed_amount,
        COUNT(CASE WHEN DATE(created_at) = CURDATE() THEN 1 END) as today_submissions
    FROM fee_sent_forms
");
$stats = $stats_stmt->fetch();

// Get countries and payment methods for filters
$countries_stmt = $pdo->query("SELECT DISTINCT country FROM fee_sent_forms ORDER BY country");
$countries = $countries_stmt->fetchAll(PDO::FETCH_COLUMN);

$payment_methods = ['wire_transfer', 'crypto', 'e_transfer', 'credit_card'];

include '../includes/header.php';
?>

<div class="container-fluid">
    <!-- Page Header -->
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h1 class="h3 mb-0">Fee Sent Forms Management</h1>
            <p class="text-muted">Review and manage client fee payment submissions</p>
        </div>
        <div>
            <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#bulkActionModal">
                <i class="fas fa-tasks"></i> Bulk Actions
            </button>
            <a href="fee_form_templates.php" class="btn btn-outline-primary">
                <i class="fas fa-cog"></i> Manage Templates
            </a>
        </div>
    </div>

    <!-- Statistics Cards -->
    <div class="row mb-4">
        <div class="col-md-2">
            <div class="card bg-primary text-white">
                <div class="card-body">
                    <div class="d-flex justify-content-between">
                        <div>
                            <h6 class="card-title">Total Forms</h6>
                            <h3 class="mb-0"><?= number_format($stats['total_forms']) ?></h3>
                        </div>
                        <i class="fas fa-file-alt fa-2x opacity-75"></i>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-2">
            <div class="card bg-warning text-white">
                <div class="card-body">
                    <div class="d-flex justify-content-between">
                        <div>
                            <h6 class="card-title">Pending</h6>
                            <h3 class="mb-0"><?= number_format($stats['pending_forms']) ?></h3>
                        </div>
                        <i class="fas fa-clock fa-2x opacity-75"></i>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-2">
            <div class="card bg-success text-white">
                <div class="card-body">
                    <div class="d-flex justify-content-between">
                        <div>
                            <h6 class="card-title">Confirmed</h6>
                            <h3 class="mb-0"><?= number_format($stats['confirmed_forms']) ?></h3>
                        </div>
                        <i class="fas fa-check-circle fa-2x opacity-75"></i>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-2">
            <div class="card bg-info text-white">
                <div class="card-body">
                    <div class="d-flex justify-content-between">
                        <div>
                            <h6 class="card-title">Under Review</h6>
                            <h3 class="mb-0"><?= number_format($stats['under_review_forms']) ?></h3>
                        </div>
                        <i class="fas fa-search fa-2x opacity-75"></i>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-2">
            <div class="card bg-danger text-white">
                <div class="card-body">
                    <div class="d-flex justify-content-between">
                        <div>
                            <h6 class="card-title">Rejected</h6>
                            <h3 class="mb-0"><?= number_format($stats['rejected_forms']) ?></h3>
                        </div>
                        <i class="fas fa-times-circle fa-2x opacity-75"></i>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-2">
            <div class="card bg-dark text-white">
                <div class="card-body">
                    <div class="d-flex justify-content-between">
                        <div>
                            <h6 class="card-title">Today's Forms</h6>
                            <h3 class="mb-0"><?= number_format($stats['today_submissions']) ?></h3>
                        </div>
                        <i class="fas fa-calendar-day fa-2x opacity-75"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Filters -->
    <div class="card mb-4">
        <div class="card-body">
            <form method="GET" class="row g-3">
                <div class="col-md-2">
                    <label class="form-label">Status</label>
                    <select name="status" class="form-select">
                        <option value="">All Statuses</option>
                        <option value="pending" <?= $status_filter === 'pending' ? 'selected' : '' ?>>Pending</option>
                        <option value="confirmed" <?= $status_filter === 'confirmed' ? 'selected' : '' ?>>Confirmed</option>
                        <option value="rejected" <?= $status_filter === 'rejected' ? 'selected' : '' ?>>Rejected</option>
                        <option value="under_review" <?= $status_filter === 'under_review' ? 'selected' : '' ?>>Under Review</option>
                    </select>
                </div>
                <div class="col-md-2">
                    <label class="form-label">Country</label>
                    <select name="country" class="form-select">
                        <option value="">All Countries</option>
                        <?php foreach ($countries as $country): ?>
                            <option value="<?= htmlspecialchars($country) ?>" <?= $country_filter === $country ? 'selected' : '' ?>>
                                <?= htmlspecialchars($country) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-2">
                    <label class="form-label">Payment Method</label>
                    <select name="payment_method" class="form-select">
                        <option value="">All Methods</option>
                        <?php foreach ($payment_methods as $method): ?>
                            <option value="<?= $method ?>" <?= $payment_method_filter === $method ? 'selected' : '' ?>>
                                <?= ucfirst(str_replace('_', ' ', $method)) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-2">
                    <label class="form-label">Date From</label>
                    <input type="date" name="date_from" class="form-control" value="<?= htmlspecialchars($date_from) ?>">
                </div>
                <div class="col-md-2">
                    <label class="form-label">Date To</label>
                    <input type="date" name="date_to" class="form-control" value="<?= htmlspecialchars($date_to) ?>">
                </div>
                <div class="col-md-2">
                    <label class="form-label">Search</label>
                    <input type="text" name="search" class="form-control" placeholder="Name, email, reference..." value="<?= htmlspecialchars($search) ?>">
                </div>
                <div class="col-12">
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-search"></i> Filter
                    </button>
                    <a href="fee_sent_forms.php" class="btn btn-outline-secondary">
                        <i class="fas fa-times"></i> Clear
                    </a>
                </div>
            </form>
        </div>
    </div>

    <!-- Fee Forms Table -->
    <div class="card">
        <div class="card-header d-flex justify-content-between align-items-center">
            <h5 class="mb-0">Fee Sent Forms (<?= number_format($total_forms) ?> total)</h5>
            <div>
                <button class="btn btn-sm btn-outline-primary" onclick="selectAll()">
                    <i class="fas fa-check-square"></i> Select All
                </button>
                <button class="btn btn-sm btn-outline-secondary" onclick="clearSelection()">
                    <i class="fas fa-square"></i> Clear
                </button>
            </div>
        </div>
        <div class="card-body p-0">
            <?php if (empty($fee_forms)): ?>
                <div class="text-center py-5">
                    <i class="fas fa-file-alt fa-3x text-muted mb-3"></i>
                    <h5 class="text-muted">No fee forms found</h5>
                    <p class="text-muted">No fee forms match your current filters.</p>
                </div>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-hover mb-0">
                        <thead class="table-light">
                            <tr>
                                <th width="40">
                                    <input type="checkbox" id="selectAllCheckbox" onchange="toggleAll(this)">
                                </th>
                                <th>Client</th>
                                <th>Application</th>
                                <th>Payment Details</th>
                                <th>Amount</th>
                                <th>Date Sent</th>
                                <th>Status</th>
                                <th>Reviewed By</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($fee_forms as $form): ?>
                                <tr>
                                    <td>
                                        <input type="checkbox" name="selected_forms[]" value="<?= $form['id'] ?>" class="form-check">
                                    </td>
                                    <td>
                                        <div>
                                            <strong><?= htmlspecialchars($form['first_name'] . ' ' . $form['last_name']) ?></strong>
                                            <br>
                                            <small class="text-muted"><?= htmlspecialchars($form['email']) ?></small>
                                            <br>
                                            <span class="badge bg-secondary"><?= htmlspecialchars($form['country']) ?></span>
                                        </div>
                                    </td>
                                    <td>
                                        <div>
                                            <strong>App #<?= $form['application_id'] ?></strong>
                                            <br>
                                            <small class="text-muted">$<?= number_format($form['loan_amount'], 2) ?></small>
                                            <br>
                                            <span class="badge bg-info"><?= ucfirst($form['application_status']) ?></span>
                                        </div>
                                    </td>
                                    <td>
                                        <div>
                                            <strong><?= ucfirst(str_replace('_', ' ', $form['payment_method'])) ?></strong>
                                            <?php if ($form['transaction_reference']): ?>
                                                <br>
                                                <small class="text-muted">Ref: <?= htmlspecialchars($form['transaction_reference']) ?></small>
                                            <?php endif; ?>
                                            <?php if ($form['receipt_filename']): ?>
                                                <br>
                                                <a href="../uploads/receipts/<?= htmlspecialchars($form['receipt_filename']) ?>" target="_blank" class="text-primary">
                                                    <i class="fas fa-file-alt"></i> Receipt
                                                </a>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                    <td>
                                        <strong>$<?= number_format($form['amount_sent'], 2) ?></strong>
                                    </td>
                                    <td>
                                        <?= date('M j, Y', strtotime($form['date_sent'])) ?>
                                        <br>
                                        <small class="text-muted"><?= date('g:i A', strtotime($form['created_at'])) ?></small>
                                    </td>
                                    <td>
                                        <?php
                                        $status_classes = [
                                            'pending' => 'bg-warning',
                                            'confirmed' => 'bg-success',
                                            'rejected' => 'bg-danger',
                                            'under_review' => 'bg-info'
                                        ];
                                        $status_class = $status_classes[$form['status']] ?? 'bg-secondary';
                                        ?>
                                        <span class="badge <?= $status_class ?>"><?= ucfirst(str_replace('_', ' ', $form['status'])) ?></span>
                                    </td>
                                    <td>
                                        <?php if ($form['reviewed_by']): ?>
                                            <div>
                                                <small><?= htmlspecialchars($form['reviewer_first_name'] . ' ' . $form['reviewer_last_name']) ?></small>
                                                <br>
                                                <small class="text-muted"><?= date('M j, g:i A', strtotime($form['reviewed_at'])) ?></small>
                                            </div>
                                        <?php else: ?>
                                            <span class="text-muted">Not reviewed</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <div class="btn-group" role="group">
                                            <button class="btn btn-sm btn-outline-primary" onclick="viewForm(<?= $form['id'] ?>)" title="View Details">
                                                <i class="fas fa-eye"></i>
                                            </button>
                                            <button class="btn btn-sm btn-outline-success" onclick="updateStatus(<?= $form['id'] ?>, 'confirmed')" title="Confirm">
                                                <i class="fas fa-check"></i>
                                            </button>
                                            <button class="btn btn-sm btn-outline-danger" onclick="updateStatus(<?= $form['id'] ?>, 'rejected')" title="Reject">
                                                <i class="fas fa-times"></i>
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
        
        <!-- Pagination -->
        <?php if ($total_pages > 1): ?>
            <div class="card-footer">
                <nav aria-label="Fee forms pagination">
                    <ul class="pagination justify-content-center mb-0">
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
            </div>
        <?php endif; ?>
    </div>
</div>

<!-- View Form Modal -->
<div class="modal fade" id="viewFormModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Fee Form Details</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body" id="formDetails">
                <!-- Form details will be loaded here -->
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>

<!-- Update Status Modal -->
<div class="modal fade" id="updateStatusModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST">
                <div class="modal-header">
                    <h5 class="modal-title">Update Fee Form Status</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <input type="hidden" name="action" value="update_status">
                    <input type="hidden" name="form_id" id="updateFormId">
                    
                    <div class="mb-3">
                        <label class="form-label">Status</label>
                        <select name="status" id="updateStatus" class="form-select" required>
                            <option value="pending">Pending</option>
                            <option value="confirmed">Confirmed</option>
                            <option value="rejected">Rejected</option>
                            <option value="under_review">Under Review</option>
                        </select>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Admin Notes</label>
                        <textarea name="admin_notes" class="form-control" rows="3" placeholder="Add notes about this status change..."></textarea>
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

<!-- Bulk Action Modal -->
<div class="modal fade" id="bulkActionModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST" onsubmit="return validateBulkAction()">
                <div class="modal-header">
                    <h5 class="modal-title">Bulk Actions</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <input type="hidden" name="action" value="bulk_update">
                    
                    <div class="alert alert-info">
                        <i class="fas fa-info-circle"></i>
                        Select fee forms from the table below, then choose an action to apply to all selected forms.
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Action</label>
                        <select name="bulk_status" class="form-select" required>
                            <option value="">Choose action...</option>
                            <option value="confirmed">Mark as Confirmed</option>
                            <option value="rejected">Mark as Rejected</option>
                            <option value="under_review">Mark as Under Review</option>
                            <option value="pending">Mark as Pending</option>
                        </select>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Notes (optional)</label>
                        <textarea name="bulk_notes" class="form-control" rows="3" placeholder="Add notes for all selected forms..."></textarea>
                    </div>
                    
                    <div id="selectedFormsCount" class="text-muted">
                        No forms selected
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Apply to Selected</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
// Form selection functions
function toggleAll(checkbox) {
    const checkboxes = document.querySelectorAll('input[name="selected_forms[]"]');
    checkboxes.forEach(cb => cb.checked = checkbox.checked);
    updateSelectedCount();
}

function selectAll() {
    const checkboxes = document.querySelectorAll('input[name="selected_forms[]"]');
    checkboxes.forEach(cb => cb.checked = true);
    document.getElementById('selectAllCheckbox').checked = true;
    updateSelectedCount();
}

function clearSelection() {
    const checkboxes = document.querySelectorAll('input[name="selected_forms[]"]');
    checkboxes.forEach(cb => cb.checked = false);
    document.getElementById('selectAllCheckbox').checked = false;
    updateSelectedCount();
}

function updateSelectedCount() {
    const selected = document.querySelectorAll('input[name="selected_forms[]"]:checked');
    const countElement = document.getElementById('selectedFormsCount');
    if (countElement) {
        countElement.textContent = selected.length + ' forms selected';
    }
}

// View form details
function viewForm(formId) {
    fetch(`fee_form_details.php?id=${formId}`)
        .then(response => response.text())
        .then(html => {
            document.getElementById('formDetails').innerHTML = html;
            new bootstrap.Modal(document.getElementById('viewFormModal')).show();
        })
        .catch(error => {
            console.error('Error loading form details:', error);
            alert('Error loading form details');
        });
}

// Update status
function updateStatus(formId, status) {
    document.getElementById('updateFormId').value = formId;
    document.getElementById('updateStatus').value = status;
    new bootstrap.Modal(document.getElementById('updateStatusModal')).show();
}

// Validate bulk action
function validateBulkAction() {
    const selected = document.querySelectorAll('input[name="selected_forms[]"]:checked');
    if (selected.length === 0) {
        alert('Please select at least one fee form.');
        return false;
    }
    
    // Add selected form IDs to the form
    const form = document.querySelector('#bulkActionModal form');
    selected.forEach(checkbox => {
        const input = document.createElement('input');
        input.type = 'hidden';
        input.name = 'selected_forms[]';
        input.value = checkbox.value;
        form.appendChild(input);
    });
    
    return confirm(`Are you sure you want to apply this action to ${selected.length} fee forms?`);
}

// Update selected count when checkboxes change
document.addEventListener('change', function(e) {
    if (e.target.name === 'selected_forms[]') {
        updateSelectedCount();
    }
});

// Initialize selected count
document.addEventListener('DOMContentLoaded', function() {
    updateSelectedCount();
});
</script>

<?php include '../includes/footer.php'; ?>