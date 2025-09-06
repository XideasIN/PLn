<?php
/**
 * Test Memo Color Coding System
 * This script adds sample memos to demonstrate the color coding functionality
 */

require_once '../config/database.php';
require_once '../includes/functions.php';
require_once '../includes/auth.php';
require_once '../includes/memo_color_coding.php';

// Require admin access
requireRole('admin');

$current_user = getCurrentUser();

// Handle memo creation
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if (!verifyCSRFToken($_POST['csrf_token'] ?? '')) {
        $error = "Security token mismatch. Please try again.";
    } else {
        $action = $_POST['action'];
        
        if ($action === 'create_test_memos') {
            try {
                $db = getDB();
                
                // Get some users to add memos to
                $stmt = $db->query("SELECT id, first_name, last_name FROM users WHERE role = 'client' LIMIT 10");
                $users = $stmt->fetchAll();
                
                if (empty($users)) {
                    $error = "No client users found to add memos to.";
                } else {
                    $success_count = 0;
                    
                    foreach ($users as $index => $user) {
                        $userId = $user['id'];
                        $userName = $user['first_name'] . ' ' . $user['last_name'];
                        
                        // Create different types of memos for demonstration
                        switch ($index % 5) {
                            case 0:
                                // Today's manual memo
                                $memo_text = "Manual follow-up call completed with {$userName}. Client requested additional documentation.";
                                $memo_type = 'manual';
                                $created_at = date('Y-m-d H:i:s');
                                break;
                                
                            case 1:
                                // Today's system memo
                                $memo_text = "System: Application status updated to pre-approved for {$userName}.";
                                $memo_type = 'system';
                                $created_at = date('Y-m-d H:i:s');
                                break;
                                
                            case 2:
                                // Recent memo (3 days ago)
                                $memo_text = "Client {$userName} submitted additional documents as requested.";
                                $memo_type = 'manual';
                                $created_at = date('Y-m-d H:i:s', strtotime('-3 days'));
                                break;
                                
                            case 3:
                                // Old memo (15 days ago)
                                $memo_text = "Initial application review completed for {$userName}. Pending document verification.";
                                $memo_type = 'manual';
                                $created_at = date('Y-m-d H:i:s', strtotime('-15 days'));
                                break;
                                
                            case 4:
                                // Very old memo (45 days ago)
                                $memo_text = "First contact made with {$userName}. Application submitted successfully.";
                                $memo_type = 'manual';
                                $created_at = date('Y-m-d H:i:s', strtotime('-45 days'));
                                break;
                        }
                        
                        // Insert memo
                        $stmt = $db->prepare("
                            INSERT INTO client_memos (user_id, memo_text, memo_type, is_internal, created_by, created_at) 
                            VALUES (?, ?, ?, 1, ?, ?)
                        ");
                        
                        if ($stmt->execute([$userId, $memo_text, $memo_type, $current_user['id'], $created_at])) {
                            $success_count++;
                        }
                    }
                    
                    $success = "Successfully created {$success_count} test memos to demonstrate color coding.";
                }
                
            } catch (Exception $e) {
                error_log("Test memo creation failed: " . $e->getMessage());
                $error = "Failed to create test memos: " . $e->getMessage();
            }
        }
        
        if ($action === 'clear_test_memos') {
            try {
                $db = getDB();
                
                // Clear all memos (be careful with this in production!)
                $stmt = $db->prepare("DELETE FROM client_memos WHERE memo_text LIKE '%Manual follow-up call completed%' OR memo_text LIKE '%System: Application status updated%' OR memo_text LIKE '%submitted additional documents%' OR memo_text LIKE '%Initial application review%' OR memo_text LIKE '%First contact made%'");
                $deleted = $stmt->execute();
                
                if ($deleted) {
                    $success = "Test memos cleared successfully.";
                } else {
                    $error = "Failed to clear test memos.";
                }
                
            } catch (Exception $e) {
                error_log("Test memo clearing failed: " . $e->getMessage());
                $error = "Failed to clear test memos: " . $e->getMessage();
            }
        }
    }
}

// Get memo statistics
try {
    $db = getDB();
    
    // Get memo counts by type and date
    $stmt = $db->query("
        SELECT 
            DATE(created_at) as memo_date,
            memo_type,
            COUNT(*) as count
        FROM client_memos 
        WHERE created_at >= DATE_SUB(NOW(), INTERVAL 7 DAYS)
        GROUP BY DATE(created_at), memo_type
        ORDER BY memo_date DESC, memo_type
    ");
    $memo_stats = $stmt->fetchAll();
    
    // Get users with memos for color coding preview
    $stmt = $db->query("
        SELECT DISTINCT u.id, u.first_name, u.last_name, u.email
        FROM users u
        JOIN client_memos cm ON u.id = cm.user_id
        WHERE u.role = 'client'
        LIMIT 20
    ");
    $users_with_memos = $stmt->fetchAll();
    
    // Get memo statuses for preview
    $userIds = array_column($users_with_memos, 'id');
    $memoStatuses = [];
    if (!empty($userIds)) {
        $memoStatuses = getMemoStatusForUsers($userIds, $db);
    }
    
} catch (Exception $e) {
    error_log("Memo stats fetch failed: " . $e->getMessage());
    $memo_stats = [];
    $users_with_memos = [];
    $memoStatuses = [];
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Test Memo Color Coding - LoanFlow Admin</title>
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
            <div class="navbar-nav ms-auto">
                <a class="nav-link" href="applications.php">
                    <i class="fas fa-arrow-left me-1"></i>Back to Applications
                </a>
            </div>
        </div>
    </nav>

    <div class="container-fluid mt-4">
        <!-- Flash Messages -->
        <?php if (isset($error)): ?>
            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                <i class="fas fa-exclamation-circle me-2"></i><?= htmlspecialchars($error) ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>
        
        <?php if (isset($success)): ?>
            <div class="alert alert-success alert-dismissible fade show" role="alert">
                <i class="fas fa-check-circle me-2"></i><?= htmlspecialchars($success) ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <!-- Page Header -->
        <div class="row mb-4">
            <div class="col-12">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <h2><i class="fas fa-palette me-2"></i>Test Memo Color Coding</h2>
                        <p class="text-muted mb-0">Create test memos to demonstrate the color coding functionality</p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Test Controls -->
        <div class="row mb-4">
            <div class="col-12">
                <div class="card">
                    <div class="card-header">
                        <h5 class="mb-0">Test Controls</h5>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-6">
                                <form method="POST">
                                    <input type="hidden" name="csrf_token" value="<?= generateCSRFToken() ?>">
                                    <input type="hidden" name="action" value="create_test_memos">
                                    <button type="submit" class="btn btn-primary">
                                        <i class="fas fa-plus me-1"></i>Create Test Memos
                                    </button>
                                </form>
                                <small class="text-muted">Creates sample memos with different dates to test color coding</small>
                            </div>
                            <div class="col-md-6">
                                <form method="POST">
                                    <input type="hidden" name="csrf_token" value="<?= generateCSRFToken() ?>">
                                    <input type="hidden" name="action" value="clear_test_memos">
                                    <button type="submit" class="btn btn-danger">
                                        <i class="fas fa-trash me-1"></i>Clear Test Memos
                                    </button>
                                </form>
                                <small class="text-muted">Removes test memos from the system</small>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Color Coding Legend -->
        <div class="row mb-4">
            <div class="col-12">
                <div class="card">
                    <div class="card-header">
                        <h5 class="mb-0">Color Coding Legend</h5>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-6">
                                <div class="memo-today p-3 mb-2 rounded">
                                    <i class="fas fa-sticky-note text-success me-2"></i>
                                    <strong>Manual Memo Today</strong> - Green background with note icon
                                </div>
                                <div class="system-memo-today p-3 mb-2 rounded">
                                    <i class="fas fa-robot text-info me-2"></i>
                                    <strong>System Memo Today</strong> - Blue background with robot icon
                                </div>
                                <div class="memo-recent p-3 mb-2 rounded">
                                    <span class="badge bg-warning text-dark me-2">3</span>
                                    <strong>Recent Memo (1-7 days)</strong> - Yellow background with day count
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="memo-old p-3 mb-2 rounded">
                                    <span class="badge bg-secondary me-2">15</span>
                                    <strong>Old Memo (8-30 days)</strong> - Gray background with day count
                                </div>
                                <div class="memo-very-old p-3 mb-2 rounded">
                                    <span class="badge bg-danger me-2">45+</span>
                                    <strong>Very Old Memo (30+ days)</strong> - Red background with day count
                                </div>
                                <div class="p-3 mb-2 rounded border">
                                    <span class="text-muted me-2">No indicator</span>
                                    <strong>No Memos</strong> - Default appearance
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Memo Statistics -->
        <?php if (!empty($memo_stats)): ?>
        <div class="row mb-4">
            <div class="col-12">
                <div class="card">
                    <div class="card-header">
                        <h5 class="mb-0">Recent Memo Activity (Last 7 Days)</h5>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-sm">
                                <thead>
                                    <tr>
                                        <th>Date</th>
                                        <th>Type</th>
                                        <th>Count</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($memo_stats as $stat): ?>
                                        <tr>
                                            <td><?= formatDate($stat['memo_date']) ?></td>
                                            <td>
                                                <span class="badge bg-<?= $stat['memo_type'] === 'manual' ? 'success' : 'info' ?>">
                                                    <?= ucfirst($stat['memo_type']) ?>
                                                </span>
                                            </td>
                                            <td><?= $stat['count'] ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <?php endif; ?>

        <!-- Preview of Users with Memo Color Coding -->
        <?php if (!empty($users_with_memos)): ?>
        <div class="row">
            <div class="col-12">
                <div class="card">
                    <div class="card-header">
                        <h5 class="mb-0">Preview: Users with Memo Color Coding</h5>
                    </div>
                    <div class="card-body p-0">
                        <div class="table-responsive">
                            <table class="table table-hover mb-0">
                                <thead class="table-dark">
                                    <tr>
                                        <th>Client</th>
                                        <th>Email</th>
                                        <th>Memo Status</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($users_with_memos as $user): 
                                        $memoStatus = $memoStatuses[$user['id']] ?? ['css_class' => '', 'indicator' => '', 'tooltip' => 'No memo data'];
                                    ?>
                                        <tr class="<?= $memoStatus['css_class'] ?>">
                                            <td>
                                                <div class="d-flex align-items-center">
                                                    <div class="flex-grow-1">
                                                        <strong><?= htmlspecialchars($user['first_name'] . ' ' . $user['last_name']) ?></strong>
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
                                            <td><?= htmlspecialchars($user['email']) ?></td>
                                            <td>
                                                <small class="text-muted"><?= htmlspecialchars($memoStatus['tooltip']) ?></small>
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
        <?php endif; ?>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Initialize tooltips
        var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
        var tooltipList = tooltipTriggerList.map(function (tooltipTriggerEl) {
            return new bootstrap.Tooltip(tooltipTriggerEl);
        });
    </script>
</body>
</html>