<?php
/**
 * Call Client List and CALL BOX System
 * LoanFlow Personal Loan Management System
 * 
 * Features:
 * - New Application Calls (highest priority)
 * - Pre-Approval Callbacks
 * - General Client Callbacks
 * - Call attempt tracking
 * - Callback scheduling
 * - Agent assignment
 * - Call notes and memos
 */

require_once '../config/database.php';
require_once '../includes/functions.php';
require_once '../includes/auth.php';

// Require admin access
requireRole('admin');

$current_user = getCurrentUser();
$db = getDB();

// Handle AJAX requests
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    header('Content-Type: application/json');
    
    try {
        switch ($_POST['action']) {
            case 'add_call_note':
                $user_id = (int)$_POST['user_id'];
                $note = trim($_POST['note']);
                $callback_date = !empty($_POST['callback_date']) ? $_POST['callback_date'] : null;
                $remove_from_list = isset($_POST['remove_from_list']);
                
                if (empty($note)) {
                    throw new Exception('Note is required');
                }
                
                // Add memo to user profile
                $stmt = $db->prepare("
                    INSERT INTO memos (user_id, memo_text, created_by, created_at) 
                    VALUES (?, ?, ?, NOW())
                ");
                $stmt->execute([$user_id, $note, $current_user['id']]);
                
                // Update call list entry
                if ($remove_from_list) {
                    $stmt = $db->prepare("
                        UPDATE call_lists 
                        SET status = 'completed', notes = ?, updated_at = NOW() 
                        WHERE user_id = ? AND status = 'pending'
                    ");
                    $stmt->execute([$note, $user_id]);
                } else {
                    $stmt = $db->prepare("
                        UPDATE call_lists 
                        SET status = 'contacted', notes = ?, callback_date = ?, 
                            call_attempts = call_attempts + 1, updated_at = NOW() 
                        WHERE user_id = ? AND status = 'pending'
                    ");
                    $stmt->execute([$note, $callback_date, $user_id]);
                }
                
                echo json_encode(['success' => true, 'message' => 'Call note added successfully']);
                exit;
                
            case 'schedule_callback':
                $user_id = (int)$_POST['user_id'];
                $callback_date = $_POST['callback_date'];
                $priority = $_POST['priority'] ?? 'normal';
                
                $stmt = $db->prepare("
                    UPDATE call_lists 
                    SET callback_date = ?, priority = ?, status = 'pending', updated_at = NOW() 
                    WHERE user_id = ? AND status IN ('pending', 'contacted')
                ");
                $stmt->execute([$callback_date, $priority, $user_id]);
                
                echo json_encode(['success' => true, 'message' => 'Callback scheduled successfully']);
                exit;
                
            case 'remove_from_list':
                $user_id = (int)$_POST['user_id'];
                $reason = $_POST['reason'] ?? 'Manual removal';
                
                $stmt = $db->prepare("
                    UPDATE call_lists 
                    SET status = 'removed', notes = ?, updated_at = NOW() 
                    WHERE user_id = ? AND status IN ('pending', 'contacted')
                ");
                $stmt->execute([$reason, $user_id]);
                
                echo json_encode(['success' => true, 'message' => 'Client removed from call list']);
                exit;
                
            case 'assign_agent':
                $user_id = (int)$_POST['user_id'];
                $agent_id = (int)$_POST['agent_id'];
                
                $stmt = $db->prepare("
                    UPDATE call_lists 
                    SET assigned_to = ?, updated_at = NOW() 
                    WHERE user_id = ? AND status IN ('pending', 'contacted')
                ");
                $stmt->execute([$agent_id, $user_id]);
                
                echo json_encode(['success' => true, 'message' => 'Agent assigned successfully']);
                exit;
        }
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        exit;
    }
}

// Get filter parameters
$filter_type = $_GET['type'] ?? 'all';
$filter_priority = $_GET['priority'] ?? 'all';
$filter_agent = $_GET['agent'] ?? 'all';
$search = $_GET['search'] ?? '';

// Build query conditions
$conditions = ["cl.status IN ('pending', 'contacted')"];
$params = [];

if ($filter_type !== 'all') {
    $conditions[] = "cl.list_type = ?";
    $params[] = $filter_type;
}

if ($filter_priority !== 'all') {
    $conditions[] = "cl.priority = ?";
    $params[] = $filter_priority;
}

if ($filter_agent !== 'all') {
    $conditions[] = "cl.assigned_to = ?";
    $params[] = $filter_agent;
}

if (!empty($search)) {
    $conditions[] = "(u.first_name LIKE ? OR u.last_name LIKE ? OR u.reference_number LIKE ? OR u.email LIKE ?)";
    $search_param = "%$search%";
    $params = array_merge($params, [$search_param, $search_param, $search_param, $search_param]);
}

$where_clause = implode(' AND ', $conditions);

// Get call list with user details
$stmt = $db->prepare("
    SELECT cl.*, u.first_name, u.last_name, u.reference_number, u.email, u.phone,
           la.loan_amount, la.application_status, la.created_at as app_created,
           agent.first_name as agent_first_name, agent.last_name as agent_last_name,
           CASE 
               WHEN cl.list_type = 'new_application' THEN 'New Application'
               WHEN cl.list_type = 'pre_approval' THEN 'Pre-Approval'
               WHEN cl.list_type = 'general' THEN 'General Follow-up'
               WHEN cl.list_type = 'paid_client' THEN 'Paid Client'
           END as type_display,
           CASE 
               WHEN cl.callback_date IS NOT NULL AND cl.callback_date > NOW() THEN 'Scheduled'
               WHEN cl.callback_date IS NOT NULL AND cl.callback_date <= NOW() THEN 'Due'
               ELSE 'Immediate'
           END as callback_status
    FROM call_lists cl
    JOIN users u ON cl.user_id = u.id
    LEFT JOIN loan_applications la ON u.id = la.user_id
    LEFT JOIN users agent ON cl.assigned_to = agent.id
    WHERE $where_clause
    ORDER BY 
        CASE cl.list_type 
            WHEN 'new_application' THEN 1
            WHEN 'pre_approval' THEN 2
            WHEN 'general' THEN 3
            WHEN 'paid_client' THEN 4
        END,
        CASE cl.priority 
            WHEN 'urgent' THEN 1
            WHEN 'high' THEN 2
            WHEN 'normal' THEN 3
            WHEN 'low' THEN 4
        END,
        cl.created_at ASC
");
$stmt->execute($params);
$call_list = $stmt->fetchAll();

// Get agents for assignment dropdown
$stmt = $db->query("
    SELECT id, first_name, last_name 
    FROM users 
    WHERE role IN ('admin', 'agent') 
    ORDER BY first_name, last_name
");
$agents = $stmt->fetchAll();

// Get statistics
$stats = [
    'total' => 0,
    'new_applications' => 0,
    'pre_approval' => 0,
    'general' => 0,
    'urgent' => 0,
    'overdue' => 0
];

foreach ($call_list as $call) {
    $stats['total']++;
    $stats[$call['list_type']]++;
    if ($call['priority'] === 'urgent') $stats['urgent']++;
    if ($call['callback_status'] === 'Due') $stats['overdue']++;
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Call Client List - LoanFlow Admin</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="../assets/css/style.css" rel="stylesheet">
    <link href="../assets/css/admin.css" rel="stylesheet">
    <style>
        .call-box {
            position: fixed;
            top: 20px;
            right: 20px;
            z-index: 1050;
            cursor: pointer;
            transition: all 0.3s ease;
        }
        .call-box:hover {
            transform: scale(1.1);
        }
        .priority-urgent { border-left: 4px solid #dc3545; }
        .priority-high { border-left: 4px solid #fd7e14; }
        .priority-normal { border-left: 4px solid #0d6efd; }
        .priority-low { border-left: 4px solid #6c757d; }
        .callback-due { background-color: #fff3cd; }
        .callback-scheduled { background-color: #d1ecf1; }
        .type-new-application { background-color: #f8d7da; }
        .type-pre-approval { background-color: #fff3cd; }
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
                        <a class="nav-link" href="index.php">
                            <i class="fas fa-home me-1"></i>Dashboard
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="applications.php">
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
                        <a class="nav-link active" href="call-list.php">
                            <i class="fas fa-phone me-1"></i>Call List
                            <span class="badge bg-info ms-1"><?= $stats['total'] ?></span>
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
                            <li><a class="dropdown-item" href="system-settings.php">
                                <i class="fas fa-sliders-h me-2"></i>System Settings
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

    <!-- CALL BOX Widget -->
    <div class="call-box" onclick="scrollToCallList()" title="Pending Calls">
        <div class="btn btn-danger btn-lg rounded-circle position-relative">
            <i class="fas fa-phone"></i>
            <?php if ($stats['total'] > 0): ?>
                <span class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-warning text-dark">
                    <?= $stats['total'] ?>
                </span>
            <?php endif; ?>
        </div>
    </div>

    <div class="container-fluid mt-4">
        <!-- Page Header -->
        <div class="row mb-4">
            <div class="col-12">
                <div class="d-flex justify-content-between align-items-center">
                    <h1 class="h3 mb-0">
                        <i class="fas fa-phone me-2"></i>Call Client List
                        <span class="badge bg-primary ms-2"><?= $stats['total'] ?> Total</span>
                    </h1>
                    <div class="btn-group">
                        <button type="button" class="btn btn-outline-primary" onclick="refreshCallList()">
                            <i class="fas fa-sync-alt me-1"></i>Refresh
                        </button>
                        <button type="button" class="btn btn-outline-success" data-bs-toggle="modal" data-bs-target="#bulkActionsModal">
                            <i class="fas fa-tasks me-1"></i>Bulk Actions
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <!-- Statistics Cards -->
        <div class="row mb-4">
            <div class="col-md-2">
                <div class="card text-center border-danger">
                    <div class="card-body">
                        <h5 class="card-title text-danger"><?= $stats['new_applications'] ?></h5>
                        <p class="card-text small">New Applications</p>
                    </div>
                </div>
            </div>
            <div class="col-md-2">
                <div class="card text-center border-warning">
                    <div class="card-body">
                        <h5 class="card-title text-warning"><?= $stats['pre_approval'] ?></h5>
                        <p class="card-text small">Pre-Approval</p>
                    </div>
                </div>
            </div>
            <div class="col-md-2">
                <div class="card text-center border-info">
                    <div class="card-body">
                        <h5 class="card-title text-info"><?= $stats['general'] ?></h5>
                        <p class="card-text small">General</p>
                    </div>
                </div>
            </div>
            <div class="col-md-2">
                <div class="card text-center border-dark">
                    <div class="card-body">
                        <h5 class="card-title text-dark"><?= $stats['urgent'] ?></h5>
                        <p class="card-text small">Urgent</p>
                    </div>
                </div>
            </div>
            <div class="col-md-2">
                <div class="card text-center border-danger">
                    <div class="card-body">
                        <h5 class="card-title text-danger"><?= $stats['overdue'] ?></h5>
                        <p class="card-text small">Overdue</p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Filters -->
        <div class="card mb-4">
            <div class="card-body">
                <form method="GET" class="row g-3">
                    <div class="col-md-2">
                        <label class="form-label">Type</label>
                        <select name="type" class="form-select">
                            <option value="all" <?= $filter_type === 'all' ? 'selected' : '' ?>>All Types</option>
                            <option value="new_application" <?= $filter_type === 'new_application' ? 'selected' : '' ?>>New Applications</option>
                            <option value="pre_approval" <?= $filter_type === 'pre_approval' ? 'selected' : '' ?>>Pre-Approval</option>
                            <option value="general" <?= $filter_type === 'general' ? 'selected' : '' ?>>General</option>
                            <option value="paid_client" <?= $filter_type === 'paid_client' ? 'selected' : '' ?>>Paid Clients</option>
                        </select>
                    </div>
                    <div class="col-md-2">
                        <label class="form-label">Priority</label>
                        <select name="priority" class="form-select">
                            <option value="all" <?= $filter_priority === 'all' ? 'selected' : '' ?>>All Priorities</option>
                            <option value="urgent" <?= $filter_priority === 'urgent' ? 'selected' : '' ?>>Urgent</option>
                            <option value="high" <?= $filter_priority === 'high' ? 'selected' : '' ?>>High</option>
                            <option value="normal" <?= $filter_priority === 'normal' ? 'selected' : '' ?>>Normal</option>
                            <option value="low" <?= $filter_priority === 'low' ? 'selected' : '' ?>>Low</option>
                        </select>
                    </div>
                    <div class="col-md-2">
                        <label class="form-label">Agent</label>
                        <select name="agent" class="form-select">
                            <option value="all" <?= $filter_agent === 'all' ? 'selected' : '' ?>>All Agents</option>
                            <option value="0" <?= $filter_agent === '0' ? 'selected' : '' ?>>Unassigned</option>
                            <?php foreach ($agents as $agent): ?>
                                <option value="<?= $agent['id'] ?>" <?= $filter_agent == $agent['id'] ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($agent['first_name'] . ' ' . $agent['last_name']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Search</label>
                        <input type="text" name="search" class="form-control" placeholder="Name, Reference #, Email..." value="<?= htmlspecialchars($search) ?>">
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

        <!-- Call List Table -->
        <div class="card" id="callListSection">
            <div class="card-header">
                <h5 class="mb-0">
                    <i class="fas fa-list me-2"></i>Call List
                    <span class="badge bg-secondary ms-2"><?= count($call_list) ?> clients</span>
                </h5>
            </div>
            <div class="card-body p-0">
                <?php if (empty($call_list)): ?>
                    <div class="text-center py-5">
                        <i class="fas fa-phone-slash fa-3x text-muted mb-3"></i>
                        <h5 class="text-muted">No clients in call list</h5>
                        <p class="text-muted">All clients have been contacted or removed from the list.</p>
                    </div>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-hover mb-0">
                            <thead class="table-dark">
                                <tr>
                                    <th><input type="checkbox" id="selectAll"></th>
                                    <th>Client</th>
                                    <th>Type</th>
                                    <th>Priority</th>
                                    <th>Attempts</th>
                                    <th>Callback</th>
                                    <th>Agent</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($call_list as $call): ?>
                                    <tr class="<?= 'priority-' . $call['priority'] ?> <?= 'callback-' . strtolower($call['callback_status']) ?> <?= 'type-' . str_replace('_', '-', $call['list_type']) ?>">
                                        <td>
                                            <input type="checkbox" class="client-checkbox" value="<?= $call['user_id'] ?>">
                                        </td>
                                        <td>
                                            <div class="d-flex align-items-center">
                                                <div class="me-3">
                                                    <div class="avatar bg-primary text-white rounded-circle d-flex align-items-center justify-content-center" style="width: 40px; height: 40px;">
                                                        <?= strtoupper(substr($call['first_name'], 0, 1) . substr($call['last_name'], 0, 1)) ?>
                                                    </div>
                                                </div>
                                                <div>
                                                    <h6 class="mb-0"><?= htmlspecialchars($call['first_name'] . ' ' . $call['last_name']) ?></h6>
                                                    <small class="text-muted">
                                                        Ref: <?= htmlspecialchars($call['reference_number']) ?><br>
                                                        <?= htmlspecialchars($call['email']) ?><br>
                                                        <?= htmlspecialchars($call['phone'] ?? 'No phone') ?>
                                                    </small>
                                                </div>
                                            </div>
                                        </td>
                                        <td>
                                            <span class="badge bg-<?= $call['list_type'] === 'new_application' ? 'danger' : ($call['list_type'] === 'pre_approval' ? 'warning' : 'info') ?>">
                                                <?= $call['type_display'] ?>
                                            </span>
                                            <?php if ($call['loan_amount']): ?>
                                                <br><small class="text-muted">$<?= number_format($call['loan_amount']) ?></small>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <span class="badge bg-<?= $call['priority'] === 'urgent' ? 'danger' : ($call['priority'] === 'high' ? 'warning' : ($call['priority'] === 'normal' ? 'primary' : 'secondary')) ?>">
                                                <?= ucfirst($call['priority']) ?>
                                            </span>
                                        </td>
                                        <td>
                                            <span class="badge bg-<?= $call['call_attempts'] >= $call['max_attempts'] ? 'danger' : 'secondary' ?>">
                                                <?= $call['call_attempts'] ?>/<?= $call['max_attempts'] ?>
                                            </span>
                                        </td>
                                        <td>
                                            <?php if ($call['callback_date']): ?>
                                                <small class="<?= $call['callback_status'] === 'Due' ? 'text-danger fw-bold' : 'text-info' ?>">
                                                    <?= date('M j, Y g:i A', strtotime($call['callback_date'])) ?>
                                                    <br><span class="badge bg-<?= $call['callback_status'] === 'Due' ? 'danger' : 'info' ?>"><?= $call['callback_status'] ?></span>
                                                </small>
                                            <?php else: ?>
                                                <span class="badge bg-warning">Immediate</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <?php if ($call['agent_first_name']): ?>
                                                <small><?= htmlspecialchars($call['agent_first_name'] . ' ' . $call['agent_last_name']) ?></small>
                                            <?php else: ?>
                                                <span class="text-muted">Unassigned</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <div class="btn-group btn-group-sm">
                                                <button type="button" class="btn btn-success" onclick="addCallNote(<?= $call['user_id'] ?>)" title="Add Call Note">
                                                    <i class="fas fa-phone"></i>
                                                </button>
                                                <button type="button" class="btn btn-info" onclick="scheduleCallback(<?= $call['user_id'] ?>)" title="Schedule Callback">
                                                    <i class="fas fa-calendar"></i>
                                                </button>
                                                <button type="button" class="btn btn-warning" onclick="assignAgent(<?= $call['user_id'] ?>)" title="Assign Agent">
                                                    <i class="fas fa-user"></i>
                                                </button>
                                                <button type="button" class="btn btn-danger" onclick="removeFromList(<?= $call['user_id'] ?>)" title="Remove from List">
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
        </div>
    </div>

    <!-- Add Call Note Modal -->
    <div class="modal fade" id="callNoteModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Add Call Note</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <form id="callNoteForm">
                        <input type="hidden" id="callNoteUserId">
                        <div class="mb-3">
                            <label class="form-label">Call Notes *</label>
                            <textarea class="form-control" id="callNote" rows="4" placeholder="Enter call notes, client response, next steps..."></textarea>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Callback Date (Optional)</label>
                            <input type="datetime-local" class="form-control" id="callbackDate">
                        </div>
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" id="removeFromList">
                            <label class="form-check-label" for="removeFromList">
                                Remove client from call list (call completed)
                            </label>
                        </div>
                    </form>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="button" class="btn btn-success" onclick="submitCallNote()">Save Call Note</button>
                </div>
            </div>
        </div>
    </div>

    <!-- Schedule Callback Modal -->
    <div class="modal fade" id="callbackModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Schedule Callback</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <form id="callbackForm">
                        <input type="hidden" id="callbackUserId">
                        <div class="mb-3">
                            <label class="form-label">Callback Date & Time *</label>
                            <input type="datetime-local" class="form-control" id="scheduleCallbackDate" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Priority</label>
                            <select class="form-select" id="callbackPriority">
                                <option value="normal">Normal</option>
                                <option value="high">High</option>
                                <option value="urgent">Urgent</option>
                                <option value="low">Low</option>
                            </select>
                        </div>
                    </form>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="button" class="btn btn-info" onclick="submitCallback()">Schedule Callback</button>
                </div>
            </div>
        </div>
    </div>

    <!-- Assign Agent Modal -->
    <div class="modal fade" id="assignAgentModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Assign Agent</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <form id="assignAgentForm">
                        <input type="hidden" id="assignUserId">
                        <div class="mb-3">
                            <label class="form-label">Select Agent *</label>
                            <select class="form-select" id="agentSelect" required>
                                <option value="">Choose an agent...</option>
                                <?php foreach ($agents as $agent): ?>
                                    <option value="<?= $agent['id'] ?>">
                                        <?= htmlspecialchars($agent['first_name'] . ' ' . $agent['last_name']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </form>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="button" class="btn btn-warning" onclick="submitAgentAssignment()">Assign Agent</button>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Scroll to call list when CALL BOX is clicked
        function scrollToCallList() {
            document.getElementById('callListSection').scrollIntoView({ behavior: 'smooth' });
        }

        // Refresh call list
        function refreshCallList() {
            location.reload();
        }

        // Select all checkboxes
        document.getElementById('selectAll').addEventListener('change', function() {
            const checkboxes = document.querySelectorAll('.client-checkbox');
            checkboxes.forEach(cb => cb.checked = this.checked);
        });

        // Add call note
        function addCallNote(userId) {
            document.getElementById('callNoteUserId').value = userId;
            document.getElementById('callNote').value = '';
            document.getElementById('callbackDate').value = '';
            document.getElementById('removeFromList').checked = false;
            new bootstrap.Modal(document.getElementById('callNoteModal')).show();
        }

        function submitCallNote() {
            const userId = document.getElementById('callNoteUserId').value;
            const note = document.getElementById('callNote').value.trim();
            const callbackDate = document.getElementById('callbackDate').value;
            const removeFromList = document.getElementById('removeFromList').checked;

            if (!note) {
                alert('Please enter call notes');
                return;
            }

            const formData = new FormData();
            formData.append('action', 'add_call_note');
            formData.append('user_id', userId);
            formData.append('note', note);
            formData.append('callback_date', callbackDate);
            if (removeFromList) formData.append('remove_from_list', '1');

            fetch('call-list.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    bootstrap.Modal.getInstance(document.getElementById('callNoteModal')).hide();
                    location.reload();
                } else {
                    alert('Error: ' + data.message);
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('An error occurred while saving the call note');
            });
        }

        // Schedule callback
        function scheduleCallback(userId) {
            document.getElementById('callbackUserId').value = userId;
            document.getElementById('scheduleCallbackDate').value = '';
            document.getElementById('callbackPriority').value = 'normal';
            new bootstrap.Modal(document.getElementById('callbackModal')).show();
        }

        function submitCallback() {
            const userId = document.getElementById('callbackUserId').value;
            const callbackDate = document.getElementById('scheduleCallbackDate').value;
            const priority = document.getElementById('callbackPriority').value;

            if (!callbackDate) {
                alert('Please select a callback date and time');
                return;
            }

            const formData = new FormData();
            formData.append('action', 'schedule_callback');
            formData.append('user_id', userId);
            formData.append('callback_date', callbackDate);
            formData.append('priority', priority);

            fetch('call-list.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    bootstrap.Modal.getInstance(document.getElementById('callbackModal')).hide();
                    location.reload();
                } else {
                    alert('Error: ' + data.message);
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('An error occurred while scheduling the callback');
            });
        }

        // Assign agent
        function assignAgent(userId) {
            document.getElementById('assignUserId').value = userId;
            document.getElementById('agentSelect').value = '';
            new bootstrap.Modal(document.getElementById('assignAgentModal')).show();
        }

        function submitAgentAssignment() {
            const userId = document.getElementById('assignUserId').value;
            const agentId = document.getElementById('agentSelect').value;

            if (!agentId) {
                alert('Please select an agent');
                return;
            }

            const formData = new FormData();
            formData.append('action', 'assign_agent');
            formData.append('user_id', userId);
            formData.append('agent_id', agentId);

            fetch('call-list.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    bootstrap.Modal.getInstance(document.getElementById('assignAgentModal')).hide();
                    location.reload();
                } else {
                    alert('Error: ' + data.message);
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('An error occurred while assigning the agent');
            });
        }

        // Remove from list
        function removeFromList(userId) {
            if (!confirm('Are you sure you want to remove this client from the call list?')) {
                return;
            }

            const reason = prompt('Reason for removal (optional):') || 'Manual removal';

            const formData = new FormData();
            formData.append('action', 'remove_from_list');
            formData.append('user_id', userId);
            formData.append('reason', reason);

            fetch('call-list.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    location.reload();
                } else {
                    alert('Error: ' + data.message);
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('An error occurred while removing the client');
            });
        }
    </script>
</body>
</html>