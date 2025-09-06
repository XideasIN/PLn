<?php
/**
 * Enhanced User Management System
 * LoanFlow Personal Loan Management System
 */

require_once '../config/database.php';
require_once '../includes/functions.php';
require_once '../includes/auth.php';
require_once '../includes/language.php';

// Require admin access
requireRole('admin');

// Initialize language
LanguageManager::init();

$current_user = getCurrentUser();

$error = '';
$success = '';

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCSRFToken($_POST['csrf_token'] ?? '')) {
        $error = __('invalid_csrf_token');
    } else {
        $action = $_POST['action'] ?? '';
        
        switch ($action) {
            case 'create_user':
                $result = createNewUser($_POST);
                if ($result['success']) {
                    $success = __('user_created_successfully');
                    logAudit('user_created', 'users', $result['user_id'], $current_user['id'], $_POST);
                } else {
                    $error = $result['error'];
                }
                break;
                
            case 'update_user':
                $user_id = intval($_POST['user_id'] ?? 0);
                $result = updateUser($user_id, $_POST);
                if ($result['success']) {
                    $success = __('user_updated_successfully');
                    logAudit('user_updated', 'users', $user_id, $current_user['id'], $_POST);
                } else {
                    $error = $result['error'];
                }
                break;
                
            case 'delete_user':
                $user_id = intval($_POST['user_id'] ?? 0);
                if ($user_id > 0 && $user_id !== $current_user['id']) {
                    $result = deleteUser($user_id);
                    if ($result) {
                        $success = __('user_deleted_successfully');
                        logAudit('user_deleted', 'users', $user_id, $current_user['id']);
                    } else {
                        $error = __('user_delete_failed');
                    }
                } else {
                    $error = __('cannot_delete_own_account');
                }
                break;
                
            case 'reset_password':
                $user_id = intval($_POST['user_id'] ?? 0);
                $result = resetUserPassword($user_id);
                if ($result['success']) {
                    $success = __('password_reset_successfully');
                    logAudit('password_reset', 'users', $user_id, $current_user['id']);
                } else {
                    $error = $result['error'];
                }
                break;
                
            case 'unlock_account':
                $user_id = intval($_POST['user_id'] ?? 0);
                $result = unlockUserAccount($user_id);
                if ($result) {
                    $success = __('account_unlocked_successfully');
                    logAudit('account_unlocked', 'users', $user_id, $current_user['id']);
                } else {
                    $error = __('account_unlock_failed');
                }
                break;
                
            case 'bulk_action':
                $user_ids = $_POST['user_ids'] ?? [];
                $bulk_action = $_POST['bulk_action_type'] ?? '';
                $result = performBulkUserAction($user_ids, $bulk_action, $current_user['id']);
                if ($result['success']) {
                    $success = $result['message'];
                    logAudit('bulk_user_action', 'users', null, $current_user['id'], [
                        'action' => $bulk_action,
                        'user_ids' => $user_ids,
                        'count' => count($user_ids)
                    ]);
                } else {
                    $error = $result['error'];
                }
                break;
        }
    }
}

// Get users with pagination
$page = intval($_GET['page'] ?? 1);
$limit = 20;
$offset = ($page - 1) * $limit;
$search = sanitizeInput($_GET['search'] ?? '');
$role_filter = sanitizeInput($_GET['role'] ?? '');
$status_filter = sanitizeInput($_GET['status'] ?? '');

$users_data = getUsersWithFilters($search, $role_filter, $status_filter, $limit, $offset);
$users = $users_data['users'];
$total_users = $users_data['total'];
$total_pages = ceil($total_users / $limit);

// Get user statistics
$user_stats = getUserStatistics();

?>
<!DOCTYPE html>
<html lang="<?= LanguageManager::getCurrentLanguage() ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= __('user_management') ?> - LoanFlow Admin</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="https://cdn.datatables.net/1.11.5/css/dataTables.bootstrap5.min.css" rel="stylesheet">
    <link href="../assets/css/style.css" rel="stylesheet">
    <link href="../assets/css/admin.css" rel="stylesheet">
    
    <style>
        .user-avatar {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            background: linear-gradient(45deg, #007bff, #0056b3);
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-weight: bold;
        }
        .status-badge {
            font-size: 0.75rem;
            padding: 0.25rem 0.5rem;
        }
        .user-card {
            transition: transform 0.2s, box-shadow 0.2s;
        }
        .user-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 15px rgba(0,0,0,0.1);
        }
        .security-indicator {
            width: 12px;
            height: 12px;
            border-radius: 50%;
            display: inline-block;
            margin-right: 5px;
        }
        .security-high { background-color: #28a745; }
        .security-medium { background-color: #ffc107; }
        .security-low { background-color: #dc3545; }
        .bulk-actions {
            background: #f8f9fa;
            border-radius: 8px;
            padding: 15px;
            margin-bottom: 20px;
            display: none;
        }
        .password-strength {
            height: 4px;
            border-radius: 2px;
            margin-top: 5px;
        }
        .strength-weak { background-color: #dc3545; }
        .strength-medium { background-color: #ffc107; }
        .strength-strong { background-color: #28a745; }
    </style>
</head>
<body>
    <?php include '../includes/admin_header.php'; ?>
    
    <div class="container-fluid">
        <div class="row">
            <?php include '../includes/admin_sidebar.php'; ?>
            
            <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4">
                <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                    <h1 class="h2">
                        <i class="fas fa-users me-2 text-primary"></i><?= __('user_management') ?>
                    </h1>
                    <div class="btn-toolbar mb-2 mb-md-0">
                        <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#createUserModal">
                            <i class="fas fa-plus me-2"></i><?= __('create_user') ?>
                        </button>
                        <button type="button" class="btn btn-outline-secondary ms-2" onclick="exportUsers()">
                            <i class="fas fa-download me-2"></i><?= __('export_users') ?>
                        </button>
                    </div>
                </div>

                <?php if ($error): ?>
                    <div class="alert alert-danger alert-dismissible fade show" role="alert">
                        <i class="fas fa-exclamation-triangle me-2"></i><?= htmlspecialchars($error) ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>

                <?php if ($success): ?>
                    <div class="alert alert-success alert-dismissible fade show" role="alert">
                        <i class="fas fa-check-circle me-2"></i><?= htmlspecialchars($success) ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>

                <!-- User Statistics -->
                <div class="row mb-4">
                    <div class="col-md-3">
                        <div class="card bg-primary text-white">
                            <div class="card-body">
                                <div class="d-flex justify-content-between">
                                    <div>
                                        <h4 class="mb-0"><?= number_format($user_stats['total_users']) ?></h4>
                                        <p class="mb-0"><?= __('total_users') ?></p>
                                    </div>
                                    <div class="align-self-center">
                                        <i class="fas fa-users fa-2x"></i>
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
                                        <h4 class="mb-0"><?= number_format($user_stats['active_users']) ?></h4>
                                        <p class="mb-0"><?= __('active_users') ?></p>
                                    </div>
                                    <div class="align-self-center">
                                        <i class="fas fa-user-check fa-2x"></i>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card bg-warning text-white">
                            <div class="card-body">
                                <div class="d-flex justify-content-between">
                                    <div>
                                        <h4 class="mb-0"><?= number_format($user_stats['locked_users']) ?></h4>
                                        <p class="mb-0"><?= __('locked_users') ?></p>
                                    </div>
                                    <div class="align-self-center">
                                        <i class="fas fa-user-lock fa-2x"></i>
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
                                        <h4 class="mb-0"><?= number_format($user_stats['new_today']) ?></h4>
                                        <p class="mb-0"><?= __('new_today') ?></p>
                                    </div>
                                    <div class="align-self-center">
                                        <i class="fas fa-user-plus fa-2x"></i>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Filters and Search -->
                <div class="card mb-4">
                    <div class="card-body">
                        <form method="GET" class="row g-3">
                            <div class="col-md-4">
                                <label for="search" class="form-label"><?= __('search_users') ?></label>
                                <input type="text" class="form-control" id="search" name="search" 
                                       value="<?= htmlspecialchars($search) ?>" 
                                       placeholder="<?= __('search_by_name_email') ?>">
                            </div>
                            <div class="col-md-3">
                                <label for="role" class="form-label"><?= __('role') ?></label>
                                <select class="form-select" id="role" name="role">
                                    <option value=""><?= __('all_roles') ?></option>
                                    <option value="admin" <?= $role_filter === 'admin' ? 'selected' : '' ?>><?= __('admin') ?></option>
                                    <option value="user" <?= $role_filter === 'user' ? 'selected' : '' ?>><?= __('user') ?></option>
                                    <option value="client" <?= $role_filter === 'client' ? 'selected' : '' ?>><?= __('client') ?></option>
                                </select>
                            </div>
                            <div class="col-md-3">
                                <label for="status" class="form-label"><?= __('status') ?></label>
                                <select class="form-select" id="status" name="status">
                                    <option value=""><?= __('all_statuses') ?></option>
                                    <option value="active" <?= $status_filter === 'active' ? 'selected' : '' ?>><?= __('active') ?></option>
                                    <option value="inactive" <?= $status_filter === 'inactive' ? 'selected' : '' ?>><?= __('inactive') ?></option>
                                    <option value="locked" <?= $status_filter === 'locked' ? 'selected' : '' ?>><?= __('locked') ?></option>
                                </select>
                            </div>
                            <div class="col-md-2">
                                <label class="form-label">&nbsp;</label>
                                <div class="d-grid">
                                    <button type="submit" class="btn btn-primary">
                                        <i class="fas fa-search me-2"></i><?= __('filter') ?>
                                    </button>
                                </div>
                            </div>
                        </form>
                    </div>
                </div>

                <!-- Bulk Actions -->
                <div class="bulk-actions" id="bulkActions">
                    <form method="POST" id="bulkActionForm">
                        <input type="hidden" name="csrf_token" value="<?= generateCSRFToken() ?>">
                        <input type="hidden" name="action" value="bulk_action">
                        <div class="row g-3 align-items-end">
                            <div class="col-md-4">
                                <label class="form-label"><?= __('selected_users') ?>: <span id="selectedCount">0</span></label>
                                <select class="form-select" name="bulk_action_type" required>
                                    <option value=""><?= __('select_action') ?></option>
                                    <option value="activate"><?= __('activate_users') ?></option>
                                    <option value="deactivate"><?= __('deactivate_users') ?></option>
                                    <option value="unlock"><?= __('unlock_users') ?></option>
                                    <option value="reset_password"><?= __('reset_passwords') ?></option>
                                    <option value="delete"><?= __('delete_users') ?></option>
                                </select>
                            </div>
                            <div class="col-md-4">
                                <button type="submit" class="btn btn-warning me-2">
                                    <i class="fas fa-bolt me-2"></i><?= __('execute_action') ?>
                                </button>
                                <button type="button" class="btn btn-secondary" onclick="clearSelection()">
                                    <i class="fas fa-times me-2"></i><?= __('clear_selection') ?>
                                </button>
                            </div>
                        </div>
                    </form>
                </div>

                <!-- Users Table -->
                <div class="card">
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-hover" id="usersTable">
                                <thead>
                                    <tr>
                                        <th>
                                            <input type="checkbox" id="selectAll" onchange="toggleSelectAll()">
                                        </th>
                                        <th><?= __('user') ?></th>
                                        <th><?= __('role') ?></th>
                                        <th><?= __('status') ?></th>
                                        <th><?= __('security') ?></th>
                                        <th><?= __('last_login') ?></th>
                                        <th><?= __('created') ?></th>
                                        <th><?= __('actions') ?></th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($users as $user): ?>
                                        <tr>
                                            <td>
                                                <input type="checkbox" class="user-checkbox" 
                                                       value="<?= $user['id'] ?>" 
                                                       onchange="updateBulkActions()">
                                            </td>
                                            <td>
                                                <div class="d-flex align-items-center">
                                                    <div class="user-avatar me-3">
                                                        <?= strtoupper(substr($user['first_name'] ?? $user['email'], 0, 1)) ?>
                                                    </div>
                                                    <div>
                                                        <div class="fw-bold"><?= htmlspecialchars($user['first_name'] . ' ' . $user['last_name']) ?></div>
                                                        <div class="text-muted small"><?= htmlspecialchars($user['email']) ?></div>
                                                    </div>
                                                </div>
                                            </td>
                                            <td>
                                                <span class="badge bg-<?= getRoleBadgeColor($user['role']) ?>">
                                                    <?= htmlspecialchars(ucfirst($user['role'])) ?>
                                                </span>
                                            </td>
                                            <td>
                                                <span class="badge status-badge bg-<?= getStatusBadgeColor($user['status']) ?>">
                                                    <?= htmlspecialchars(ucfirst($user['status'])) ?>
                                                </span>
                                            </td>
                                            <td>
                                                <span class="security-indicator security-<?= getUserSecurityLevel($user) ?>"></span>
                                                <?= getUserSecurityText($user) ?>
                                            </td>
                                            <td>
                                                <?php if ($user['last_login']): ?>
                                                    <span title="<?= date('Y-m-d H:i:s', strtotime($user['last_login'])) ?>">
                                                        <?= timeAgo($user['last_login']) ?>
                                                    </span>
                                                <?php else: ?>
                                                    <span class="text-muted"><?= __('never') ?></span>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <span title="<?= date('Y-m-d H:i:s', strtotime($user['created_at'])) ?>">
                                                    <?= date('M j, Y', strtotime($user['created_at'])) ?>
                                                </span>
                                            </td>
                                            <td>
                                                <div class="btn-group" role="group">
                                                    <button type="button" class="btn btn-sm btn-outline-primary" 
                                                            onclick="editUser(<?= $user['id'] ?>)" 
                                                            title="<?= __('edit_user') ?>">
                                                        <i class="fas fa-edit"></i>
                                                    </button>
                                                    <button type="button" class="btn btn-sm btn-outline-warning" 
                                                            onclick="resetPassword(<?= $user['id'] ?>)" 
                                                            title="<?= __('reset_password') ?>">
                                                        <i class="fas fa-key"></i>
                                                    </button>
                                                    <?php if ($user['status'] === 'locked'): ?>
                                                        <button type="button" class="btn btn-sm btn-outline-success" 
                                                                onclick="unlockAccount(<?= $user['id'] ?>)" 
                                                                title="<?= __('unlock_account') ?>">
                                                            <i class="fas fa-unlock"></i>
                                                        </button>
                                                    <?php endif; ?>
                                                    <?php if ($user['id'] !== $current_user['id']): ?>
                                                        <button type="button" class="btn btn-sm btn-outline-danger" 
                                                                onclick="deleteUser(<?= $user['id'] ?>)" 
                                                                title="<?= __('delete_user') ?>">
                                                            <i class="fas fa-trash"></i>
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
                            <nav aria-label="User pagination">
                                <ul class="pagination justify-content-center">
                                    <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                                        <li class="page-item <?= $i === $page ? 'active' : '' ?>">
                                            <a class="page-link" href="?page=<?= $i ?>&search=<?= urlencode($search) ?>&role=<?= urlencode($role_filter) ?>&status=<?= urlencode($status_filter) ?>">
                                                <?= $i ?>
                                            </a>
                                        </li>
                                    <?php endfor; ?>
                                </ul>
                            </nav>
                        <?php endif; ?>
                    </div>
                </div>
            </main>
        </div>
    </div>

    <!-- Create User Modal -->
    <div class="modal fade" id="createUserModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">
                        <i class="fas fa-user-plus me-2"></i><?= __('create_new_user') ?>
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST" id="createUserForm">
                    <input type="hidden" name="csrf_token" value="<?= generateCSRFToken() ?>">
                    <input type="hidden" name="action" value="create_user">
                    <div class="modal-body">
                        <div class="row g-3">
                            <div class="col-md-6">
                                <label for="first_name" class="form-label"><?= __('first_name') ?> *</label>
                                <input type="text" class="form-control" id="first_name" name="first_name" required>
                            </div>
                            <div class="col-md-6">
                                <label for="last_name" class="form-label"><?= __('last_name') ?> *</label>
                                <input type="text" class="form-control" id="last_name" name="last_name" required>
                            </div>
                            <div class="col-md-6">
                                <label for="email" class="form-label"><?= __('email') ?> *</label>
                                <input type="email" class="form-control" id="email" name="email" required>
                            </div>
                            <div class="col-md-6">
                                <label for="phone" class="form-label"><?= __('phone') ?></label>
                                <input type="tel" class="form-control" id="phone" name="phone">
                            </div>
                            <div class="col-md-6">
                                <label for="role" class="form-label"><?= __('role') ?> *</label>
                                <select class="form-select" id="role" name="role" required>
                                    <option value="user"><?= __('user') ?></option>
                                    <option value="client"><?= __('client') ?></option>
                                    <option value="admin"><?= __('admin') ?></option>
                                </select>
                            </div>
                            <div class="col-md-6">
                                <label for="status" class="form-label"><?= __('status') ?> *</label>
                                <select class="form-select" id="status" name="status" required>
                                    <option value="active"><?= __('active') ?></option>
                                    <option value="inactive"><?= __('inactive') ?></option>
                                </select>
                            </div>
                            <div class="col-12">
                                <label for="password" class="form-label"><?= __('password') ?> *</label>
                                <div class="input-group">
                                    <input type="password" class="form-control" id="password" name="password" 
                                           required minlength="8" onkeyup="checkPasswordStrength(this.value)">
                                    <button class="btn btn-outline-secondary" type="button" onclick="togglePassword('password')">
                                        <i class="fas fa-eye"></i>
                                    </button>
                                </div>
                                <div class="password-strength" id="passwordStrength"></div>
                                <div class="form-text"><?= __('password_requirements') ?></div>
                            </div>
                            <div class="col-12">
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" id="send_welcome_email" name="send_welcome_email" checked>
                                    <label class="form-check-label" for="send_welcome_email">
                                        <?= __('send_welcome_email') ?>
                                    </label>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                            <?= __('cancel') ?>
                        </button>
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-save me-2"></i><?= __('create_user') ?>
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Edit User Modal -->
    <div class="modal fade" id="editUserModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">
                        <i class="fas fa-user-edit me-2"></i><?= __('edit_user') ?>
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST" id="editUserForm">
                    <input type="hidden" name="csrf_token" value="<?= generateCSRFToken() ?>">
                    <input type="hidden" name="action" value="update_user">
                    <input type="hidden" name="user_id" id="edit_user_id">
                    <div class="modal-body" id="editUserContent">
                        <!-- Content loaded via AJAX -->
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                            <?= __('cancel') ?>
                        </button>
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-save me-2"></i><?= __('update_user') ?>
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <?php include '../includes/admin_footer.php'; ?>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.datatables.net/1.11.5/js/jquery.dataTables.min.js"></script>
    <script src="https://cdn.datatables.net/1.11.5/js/dataTables.bootstrap5.min.js"></script>
    
    <script>
        // Initialize DataTable
        $(document).ready(function() {
            $('#usersTable').DataTable({
                "pageLength": 20,
                "order": [[ 6, "desc" ]],
                "columnDefs": [
                    { "orderable": false, "targets": [0, 7] }
                ]
            });
        });

        // Bulk actions functionality
        function toggleSelectAll() {
            const selectAll = document.getElementById('selectAll');
            const checkboxes = document.querySelectorAll('.user-checkbox');
            
            checkboxes.forEach(checkbox => {
                checkbox.checked = selectAll.checked;
            });
            
            updateBulkActions();
        }

        function updateBulkActions() {
            const checkboxes = document.querySelectorAll('.user-checkbox:checked');
            const bulkActions = document.getElementById('bulkActions');
            const selectedCount = document.getElementById('selectedCount');
            
            selectedCount.textContent = checkboxes.length;
            
            if (checkboxes.length > 0) {
                bulkActions.style.display = 'block';
                
                // Update hidden input with selected IDs
                const userIds = Array.from(checkboxes).map(cb => cb.value);
                let hiddenInput = document.querySelector('input[name="user_ids[]"]');
                if (!hiddenInput) {
                    hiddenInput = document.createElement('input');
                    hiddenInput.type = 'hidden';
                    hiddenInput.name = 'user_ids[]';
                    document.getElementById('bulkActionForm').appendChild(hiddenInput);
                }
                hiddenInput.value = userIds.join(',');
            } else {
                bulkActions.style.display = 'none';
            }
        }

        function clearSelection() {
            document.getElementById('selectAll').checked = false;
            document.querySelectorAll('.user-checkbox').forEach(cb => cb.checked = false);
            updateBulkActions();
        }

        // User management functions
        function editUser(userId) {
            fetch(`../api/user-management.php?action=get_user&id=${userId}`)
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        document.getElementById('edit_user_id').value = userId;
                        document.getElementById('editUserContent').innerHTML = data.html;
                        new bootstrap.Modal(document.getElementById('editUserModal')).show();
                    } else {
                        alert(data.error || 'Failed to load user data');
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    alert('Failed to load user data');
                });
        }

        function resetPassword(userId) {
            if (confirm('<?= __('confirm_reset_password') ?>')) {
                const form = document.createElement('form');
                form.method = 'POST';
                form.innerHTML = `
                    <input type="hidden" name="csrf_token" value="<?= generateCSRFToken() ?>">
                    <input type="hidden" name="action" value="reset_password">
                    <input type="hidden" name="user_id" value="${userId}">
                `;
                document.body.appendChild(form);
                form.submit();
            }
        }

        function unlockAccount(userId) {
            if (confirm('<?= __('confirm_unlock_account') ?>')) {
                const form = document.createElement('form');
                form.method = 'POST';
                form.innerHTML = `
                    <input type="hidden" name="csrf_token" value="<?= generateCSRFToken() ?>">
                    <input type="hidden" name="action" value="unlock_account">
                    <input type="hidden" name="user_id" value="${userId}">
                `;
                document.body.appendChild(form);
                form.submit();
            }
        }

        function deleteUser(userId) {
            if (confirm('<?= __('confirm_delete_user') ?>')) {
                const form = document.createElement('form');
                form.method = 'POST';
                form.innerHTML = `
                    <input type="hidden" name="csrf_token" value="<?= generateCSRFToken() ?>">
                    <input type="hidden" name="action" value="delete_user">
                    <input type="hidden" name="user_id" value="${userId}">
                `;
                document.body.appendChild(form);
                form.submit();
            }
        }

        function exportUsers() {
            window.open('../api/user-management.php?action=export_users', '_blank');
        }

        // Password strength checker
        function checkPasswordStrength(password) {
            const strengthBar = document.getElementById('passwordStrength');
            let strength = 0;
            
            if (password.length >= 8) strength++;
            if (password.match(/[a-z]/)) strength++;
            if (password.match(/[A-Z]/)) strength++;
            if (password.match(/[0-9]/)) strength++;
            if (password.match(/[^a-zA-Z0-9]/)) strength++;
            
            strengthBar.className = 'password-strength ';
            if (strength < 3) {
                strengthBar.classList.add('strength-weak');
            } else if (strength < 5) {
                strengthBar.classList.add('strength-medium');
            } else {
                strengthBar.classList.add('strength-strong');
            }
        }

        function togglePassword(fieldId) {
            const field = document.getElementById(fieldId);
            const icon = field.nextElementSibling.querySelector('i');
            
            if (field.type === 'password') {
                field.type = 'text';
                icon.classList.remove('fa-eye');
                icon.classList.add('fa-eye-slash');
            } else {
                field.type = 'password';
                icon.classList.remove('fa-eye-slash');
                icon.classList.add('fa-eye');
            }
        }

        // Form validation
        document.getElementById('bulkActionForm').addEventListener('submit', function(e) {
            const selectedUsers = document.querySelectorAll('.user-checkbox:checked');
            if (selectedUsers.length === 0) {
                e.preventDefault();
                alert('<?= __('please_select_users') ?>');
                return false;
            }
            
            const action = this.querySelector('select[name="bulk_action_type"]').value;
            if (!action) {
                e.preventDefault();
                alert('<?= __('please_select_action') ?>');
                return false;
            }
            
            if (action === 'delete') {
                if (!confirm('<?= __('confirm_bulk_delete') ?>')) {
                    e.preventDefault();
                    return false;
                }
            }
        });
    </script>
</body>
</html>

<?php
// Helper functions for user management

function createNewUser($data) {
    global $pdo;
    
    try {
        // Validate input
        $first_name = sanitizeInput($data['first_name'] ?? '');
        $last_name = sanitizeInput($data['last_name'] ?? '');
        $email = sanitizeInput($data['email'] ?? '');
        $phone = sanitizeInput($data['phone'] ?? '');
        $role = sanitizeInput($data['role'] ?? 'user');
        $status = sanitizeInput($data['status'] ?? 'active');
        $password = $data['password'] ?? '';
        
        if (empty($first_name) || empty($last_name) || empty($email) || empty($password)) {
            return ['success' => false, 'error' => __('required_fields_missing')];
        }
        
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return ['success' => false, 'error' => __('invalid_email_format')];
        }
        
        // Check if email already exists
        $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ?");
        $stmt->execute([$email]);
        if ($stmt->fetch()) {
            return ['success' => false, 'error' => __('email_already_exists')];
        }
        
        // Validate password strength
        if (strlen($password) < 8) {
            return ['success' => false, 'error' => __('password_too_short')];
        }
        
        // Hash password
        $password_hash = password_hash($password, PASSWORD_DEFAULT);
        
        // Insert user
        $stmt = $pdo->prepare("
            INSERT INTO users (first_name, last_name, email, phone, role, status, password_hash, created_at) 
            VALUES (?, ?, ?, ?, ?, ?, ?, NOW())
        ");
        
        $stmt->execute([$first_name, $last_name, $email, $phone, $role, $status, $password_hash]);
        $user_id = $pdo->lastInsertId();
        
        // Send welcome email if requested
        if (!empty($data['send_welcome_email'])) {
            sendWelcomeEmail($email, $first_name, $password);
        }
        
        return ['success' => true, 'user_id' => $user_id];
        
    } catch (Exception $e) {
        error_log('Error creating user: ' . $e->getMessage());
        return ['success' => false, 'error' => __('user_creation_failed')];
    }
}

function updateUser($user_id, $data) {
    global $pdo;
    
    try {
        // Validate input
        $first_name = sanitizeInput($data['first_name'] ?? '');
        $last_name = sanitizeInput($data['last_name'] ?? '');
        $email = sanitizeInput($data['email'] ?? '');
        $phone = sanitizeInput($data['phone'] ?? '');
        $role = sanitizeInput($data['role'] ?? 'user');
        $status = sanitizeInput($data['status'] ?? 'active');
        
        if (empty($first_name) || empty($last_name) || empty($email)) {
            return ['success' => false, 'error' => __('required_fields_missing')];
        }
        
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return ['success' => false, 'error' => __('invalid_email_format')];
        }
        
        // Check if email already exists for another user
        $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ? AND id != ?");
        $stmt->execute([$email, $user_id]);
        if ($stmt->fetch()) {
            return ['success' => false, 'error' => __('email_already_exists')];
        }
        
        // Update user
        $stmt = $pdo->prepare("
            UPDATE users 
            SET first_name = ?, last_name = ?, email = ?, phone = ?, role = ?, status = ?, updated_at = NOW() 
            WHERE id = ?
        ");
        
        $stmt->execute([$first_name, $last_name, $email, $phone, $role, $status, $user_id]);
        
        return ['success' => true];
        
    } catch (Exception $e) {
        error_log('Error updating user: ' . $e->getMessage());
        return ['success' => false, 'error' => __('user_update_failed')];
    }
}

function deleteUser($user_id) {
    global $pdo;
    
    try {
        // Soft delete - update status instead of actual deletion
        $stmt = $pdo->prepare("UPDATE users SET status = 'deleted', updated_at = NOW() WHERE id = ?");
        return $stmt->execute([$user_id]);
        
    } catch (Exception $e) {
        error_log('Error deleting user: ' . $e->getMessage());
        return false;
    }
}

function resetUserPassword($user_id) {
    global $pdo;
    
    try {
        // Generate new temporary password
        $temp_password = generateRandomPassword();
        $password_hash = password_hash($temp_password, PASSWORD_DEFAULT);
        
        // Update password and set force change flag
        $stmt = $pdo->prepare("
            UPDATE users 
            SET password_hash = ?, password_reset_required = 1, updated_at = NOW() 
            WHERE id = ?
        ");
        
        if ($stmt->execute([$password_hash, $user_id])) {
            // Get user email
            $stmt = $pdo->prepare("SELECT email, first_name FROM users WHERE id = ?");
            $stmt->execute([$user_id]);
            $user = $stmt->fetch();
            
            if ($user) {
                // Send password reset email
                sendPasswordResetEmail($user['email'], $user['first_name'], $temp_password);
                return ['success' => true];
            }
        }
        
        return ['success' => false, 'error' => __('password_reset_failed')];
        
    } catch (Exception $e) {
        error_log('Error resetting password: ' . $e->getMessage());
        return ['success' => false, 'error' => __('password_reset_failed')];
    }
}

function unlockUserAccount($user_id) {
    global $pdo;
    
    try {
        $stmt = $pdo->prepare("
            UPDATE users 
            SET status = 'active', failed_login_attempts = 0, locked_until = NULL, updated_at = NOW() 
            WHERE id = ?
        ");
        
        return $stmt->execute([$user_id]);
        
    } catch (Exception $e) {
        error_log('Error unlocking account: ' . $e->getMessage());
        return false;
    }
}

function performBulkUserAction($user_ids, $action, $admin_id) {
    global $pdo;
    
    try {
        if (empty($user_ids) || empty($action)) {
            return ['success' => false, 'error' => __('invalid_parameters')];
        }
        
        // Convert comma-separated string to array if needed
        if (is_string($user_ids)) {
            $user_ids = explode(',', $user_ids);
        }
        
        $user_ids = array_map('intval', $user_ids);
        $placeholders = str_repeat('?,', count($user_ids) - 1) . '?';
        
        $affected_rows = 0;
        
        switch ($action) {
            case 'activate':
                $stmt = $pdo->prepare("UPDATE users SET status = 'active', updated_at = NOW() WHERE id IN ($placeholders)");
                $stmt->execute($user_ids);
                $affected_rows = $stmt->rowCount();
                break;
                
            case 'deactivate':
                $stmt = $pdo->prepare("UPDATE users SET status = 'inactive', updated_at = NOW() WHERE id IN ($placeholders)");
                $stmt->execute($user_ids);
                $affected_rows = $stmt->rowCount();
                break;
                
            case 'unlock':
                $stmt = $pdo->prepare("
                    UPDATE users 
                    SET status = 'active', failed_login_attempts = 0, locked_until = NULL, updated_at = NOW() 
                    WHERE id IN ($placeholders)
                ");
                $stmt->execute($user_ids);
                $affected_rows = $stmt->rowCount();
                break;
                
            case 'reset_password':
                foreach ($user_ids as $user_id) {
                    $result = resetUserPassword($user_id);
                    if ($result['success']) {
                        $affected_rows++;
                    }
                }
                break;
                
            case 'delete':
                // Prevent deletion of admin performing the action
                $user_ids = array_filter($user_ids, function($id) use ($admin_id) {
                    return $id != $admin_id;
                });
                
                if (!empty($user_ids)) {
                    $placeholders = str_repeat('?,', count($user_ids) - 1) . '?';
                    $stmt = $pdo->prepare("UPDATE users SET status = 'deleted', updated_at = NOW() WHERE id IN ($placeholders)");
                    $stmt->execute($user_ids);
                    $affected_rows = $stmt->rowCount();
                }
                break;
                
            default:
                return ['success' => false, 'error' => __('invalid_action')];
        }
        
        return [
            'success' => true, 
            'message' => sprintf(__('bulk_action_completed'), $affected_rows, __("action_$action"))
        ];
        
    } catch (Exception $e) {
        error_log('Error performing bulk action: ' . $e->getMessage());
        return ['success' => false, 'error' => __('bulk_action_failed')];
    }
}

function getUsersWithFilters($search, $role_filter, $status_filter, $limit, $offset) {
    global $pdo;
    
    try {
        $where_conditions = ["status != 'deleted'"];
        $params = [];
        
        if (!empty($search)) {
            $where_conditions[] = "(first_name LIKE ? OR last_name LIKE ? OR email LIKE ?)";
            $search_param = "%$search%";
            $params[] = $search_param;
            $params[] = $search_param;
            $params[] = $search_param;
        }
        
        if (!empty($role_filter)) {
            $where_conditions[] = "role = ?";
            $params[] = $role_filter;
        }
        
        if (!empty($status_filter)) {
            $where_conditions[] = "status = ?";
            $params[] = $status_filter;
        }
        
        $where_clause = implode(' AND ', $where_conditions);
        
        // Get total count
        $count_sql = "SELECT COUNT(*) FROM users WHERE $where_clause";
        $stmt = $pdo->prepare($count_sql);
        $stmt->execute($params);
        $total = $stmt->fetchColumn();
        
        // Get users
        $sql = "
            SELECT id, first_name, last_name, email, phone, role, status, 
                   last_login, failed_login_attempts, locked_until, created_at
            FROM users 
            WHERE $where_clause 
            ORDER BY created_at DESC 
            LIMIT ? OFFSET ?
        ";
        
        $params[] = $limit;
        $params[] = $offset;
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $users = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        return ['users' => $users, 'total' => $total];
        
    } catch (Exception $e) {
        error_log('Error getting users: ' . $e->getMessage());
        return ['users' => [], 'total' => 0];
    }
}

function getUserStatistics() {
    global $pdo;
    
    try {
        $stats = [];
        
        // Total users
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM users WHERE status != 'deleted'");
        $stmt->execute();
        $stats['total_users'] = $stmt->fetchColumn();
        
        // Active users
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM users WHERE status = 'active'");
        $stmt->execute();
        $stats['active_users'] = $stmt->fetchColumn();
        
        // Locked users
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM users WHERE status = 'locked'");
        $stmt->execute();
        $stats['locked_users'] = $stmt->fetchColumn();
        
        // New users today
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM users WHERE DATE(created_at) = CURDATE() AND status != 'deleted'");
        $stmt->execute();
        $stats['new_today'] = $stmt->fetchColumn();
        
        return $stats;
        
    } catch (Exception $e) {
        error_log('Error getting user statistics: ' . $e->getMessage());
        return [
            'total_users' => 0,
            'active_users' => 0,
            'locked_users' => 0,
            'new_today' => 0
        ];
    }
}

// Helper functions for UI
function getRoleBadgeColor($role) {
    switch ($role) {
        case 'admin': return 'danger';
        case 'client': return 'primary';
        case 'user': return 'secondary';
        default: return 'light';
    }
}

function getStatusBadgeColor($status) {
    switch ($status) {
        case 'active': return 'success';
        case 'inactive': return 'warning';
        case 'locked': return 'danger';
        default: return 'secondary';
    }
}

function getUserSecurityLevel($user) {
    $score = 0;
    
    // Check for recent login
    if ($user['last_login'] && strtotime($user['last_login']) > strtotime('-30 days')) {
        $score++;
    }
    
    // Check failed login attempts
    if ($user['failed_login_attempts'] < 3) {
        $score++;
    }
    
    // Check if account is not locked
    if ($user['status'] === 'active') {
        $score++;
    }
    
    if ($score >= 3) return 'high';
    if ($score >= 2) return 'medium';
    return 'low';
}

function getUserSecurityText($user) {
    $level = getUserSecurityLevel($user);
    switch ($level) {
        case 'high': return __('high_security');
        case 'medium': return __('medium_security');
        case 'low': return __('low_security');
        default: return __('unknown');
    }
}

function generateRandomPassword($length = 12) {
    $chars = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789!@#$%^&*';
    return substr(str_shuffle($chars), 0, $length);
}

function sendWelcomeEmail($email, $name, $password) {
    // Implementation for sending welcome email
    // This would integrate with your existing email system
}

function sendPasswordResetEmail($email, $name, $temp_password) {
    // Implementation for sending password reset email
    // This would integrate with your existing email system
}
?>