<?php
/**
 * Pre-Approval Automation Management Interface
 * LoanFlow Personal Loan Management System
 * Admin interface for managing automated pre-approval workflows
 */

require_once '../includes/config.php';
require_once '../includes/functions.php';
require_once '../includes/pre_approval_automation.php';

// Check admin authentication
if (!isLoggedIn() || !hasPermission('admin')) {
    header('Location: ../login.php');
    exit;
}

$db = getDB();
$message = '';
$error = '';

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        switch ($_POST['action']) {
            case 'update_settings':
                try {
                    $settings = [
                        'pre_approval_automation_enabled' => isset($_POST['automation_enabled']) ? '1' : '0',
                        'auto_pre_approve_enabled' => isset($_POST['auto_pre_approve']) ? '1' : '0',
                        'pre_approval_delay_hours' => (int)$_POST['pre_approval_delay'],
                        'document_review_timeout_hours' => (int)$_POST['document_timeout'],
                        'agreement_timeout_hours' => (int)$_POST['agreement_timeout'],
                        'funding_timeout_hours' => (int)$_POST['funding_timeout']
                    ];
                    
                    foreach ($settings as $key => $value) {
                        updateSystemSetting($key, $value);
                    }
                    
                    $message = 'Automation settings updated successfully!';
                } catch (Exception $e) {
                    $error = 'Error updating settings: ' . $e->getMessage();
                }
                break;
                
            case 'run_automation':
                try {
                    $results = PreApprovalAutomation::processPreApprovalWorkflows();
                    if (isset($results['error'])) {
                        $error = 'Automation error: ' . $results['error'];
                    } else {
                        $message = sprintf(
                            'Automation completed: %d pre-approved, %d document reminders, %d agreement reminders, %d funding processed, %d expired',
                            $results['pre_approved'],
                            $results['document_reminders'],
                            $results['agreement_reminders'],
                            $results['funding_processed'],
                            $results['expired_applications']
                        );
                    }
                } catch (Exception $e) {
                    $error = 'Error running automation: ' . $e->getMessage();
                }
                break;
        }
    }
}

// Get current settings
$settings = [
    'automation_enabled' => getSystemSetting('pre_approval_automation_enabled', '1') === '1',
    'auto_pre_approve' => getSystemSetting('auto_pre_approve_enabled', '1') === '1',
    'pre_approval_delay' => (int)getSystemSetting('pre_approval_delay_hours', '2'),
    'document_timeout' => (int)getSystemSetting('document_review_timeout_hours', '48'),
    'agreement_timeout' => (int)getSystemSetting('agreement_timeout_hours', '72'),
    'funding_timeout' => (int)getSystemSetting('funding_timeout_hours', '168')
];

// Get automation statistics
$stats = PreApprovalAutomation::getAutomationStats();

// Get recent automation activity
$recent_activity = $db->query("
    SELECT 
        la.reference_number,
        CONCAT(u.first_name, ' ', u.last_name) as client_name,
        la.application_status,
        la.pre_approved_at,
        la.loan_amount,
        la.pre_approval_rate
    FROM loan_applications la
    JOIN users u ON la.user_id = u.id
    WHERE la.pre_approved_at IS NOT NULL
    AND la.pre_approved_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)
    ORDER BY la.pre_approved_at DESC
    LIMIT 20
")->fetchAll();

// Get applications in workflow
$workflow_apps = $db->query("
    SELECT 
        la.reference_number,
        CONCAT(u.first_name, ' ', u.last_name) as client_name,
        la.application_status,
        la.current_step,
        la.created_at,
        la.updated_at,
        la.loan_amount,
        TIMESTAMPDIFF(HOUR, la.updated_at, NOW()) as hours_since_update
    FROM loan_applications la
    JOIN users u ON la.user_id = u.id
    WHERE la.application_status IN ('pre_approved', 'document_review', 'approved', 'funding')
    ORDER BY la.updated_at ASC
    LIMIT 50
")->fetchAll();

include '../includes/header.php';
?>

<div class="container-fluid">
    <div class="row">
        <div class="col-md-12">
            <div class="card">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h4 class="mb-0">
                        <i class="fas fa-robot"></i> Pre-Approval Automation
                    </h4>
                    <div>
                        <form method="POST" class="d-inline">
                            <input type="hidden" name="action" value="run_automation">
                            <button type="submit" class="btn btn-primary btn-sm">
                                <i class="fas fa-play"></i> Run Now
                            </button>
                        </form>
                    </div>
                </div>
                <div class="card-body">
                    <?php if ($message): ?>
                        <div class="alert alert-success alert-dismissible fade show">
                            <?php echo htmlspecialchars($message); ?>
                            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                        </div>
                    <?php endif; ?>
                    
                    <?php if ($error): ?>
                        <div class="alert alert-danger alert-dismissible fade show">
                            <?php echo htmlspecialchars($error); ?>
                            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                        </div>
                    <?php endif; ?>
                    
                    <!-- Statistics Cards -->
                    <div class="row mb-4">
                        <div class="col-md-3">
                            <div class="card bg-primary text-white">
                                <div class="card-body">
                                    <div class="d-flex justify-content-between">
                                        <div>
                                            <h6 class="card-title">Today's Pre-Approvals</h6>
                                            <h3 class="mb-0"><?php echo $stats['pre_approvals_today']; ?></h3>
                                        </div>
                                        <div class="align-self-center">
                                            <i class="fas fa-check-circle fa-2x"></i>
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
                                            <h6 class="card-title">This Month</h6>
                                            <h3 class="mb-0"><?php echo $stats['pre_approvals_this_month']; ?></h3>
                                        </div>
                                        <div class="align-self-center">
                                            <i class="fas fa-calendar-check fa-2x"></i>
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
                                            <h6 class="card-title">Avg Pre-Approval Time</h6>
                                            <h3 class="mb-0"><?php echo $stats['avg_pre_approval_hours']; ?>h</h3>
                                        </div>
                                        <div class="align-self-center">
                                            <i class="fas fa-clock fa-2x"></i>
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
                                            <h6 class="card-title">In Workflow</h6>
                                            <h3 class="mb-0"><?php echo array_sum($stats['workflow_counts'] ?? []); ?></h3>
                                        </div>
                                        <div class="align-self-center">
                                            <i class="fas fa-cogs fa-2x"></i>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Tabs -->
                    <ul class="nav nav-tabs" id="automationTabs" role="tablist">
                        <li class="nav-item" role="presentation">
                            <button class="nav-link active" id="settings-tab" data-bs-toggle="tab" data-bs-target="#settings" type="button" role="tab">
                                <i class="fas fa-cog"></i> Settings
                            </button>
                        </li>
                        <li class="nav-item" role="presentation">
                            <button class="nav-link" id="workflow-tab" data-bs-toggle="tab" data-bs-target="#workflow" type="button" role="tab">
                                <i class="fas fa-stream"></i> Active Workflows
                            </button>
                        </li>
                        <li class="nav-item" role="presentation">
                            <button class="nav-link" id="activity-tab" data-bs-toggle="tab" data-bs-target="#activity" type="button" role="tab">
                                <i class="fas fa-history"></i> Recent Activity
                            </button>
                        </li>
                    </ul>
                    
                    <div class="tab-content" id="automationTabsContent">
                        <!-- Settings Tab -->
                        <div class="tab-pane fade show active" id="settings" role="tabpanel">
                            <div class="mt-3">
                                <form method="POST">
                                    <input type="hidden" name="action" value="update_settings">
                                    
                                    <div class="row">
                                        <div class="col-md-6">
                                            <div class="card">
                                                <div class="card-header">
                                                    <h6 class="mb-0">General Settings</h6>
                                                </div>
                                                <div class="card-body">
                                                    <div class="form-check mb-3">
                                                        <input class="form-check-input" type="checkbox" name="automation_enabled" id="automation_enabled" <?php echo $settings['automation_enabled'] ? 'checked' : ''; ?>>
                                                        <label class="form-check-label" for="automation_enabled">
                                                            Enable Pre-Approval Automation
                                                        </label>
                                                    </div>
                                                    
                                                    <div class="form-check mb-3">
                                                        <input class="form-check-input" type="checkbox" name="auto_pre_approve" id="auto_pre_approve" <?php echo $settings['auto_pre_approve'] ? 'checked' : ''; ?>>
                                                        <label class="form-check-label" for="auto_pre_approve">
                                                            Enable Automatic Pre-Approvals
                                                        </label>
                                                    </div>
                                                    
                                                    <div class="mb-3">
                                                        <label for="pre_approval_delay" class="form-label">Pre-Approval Delay (hours)</label>
                                                        <input type="number" class="form-control" name="pre_approval_delay" id="pre_approval_delay" value="<?php echo $settings['pre_approval_delay']; ?>" min="1" max="72">
                                                        <div class="form-text">Minimum time before auto pre-approval</div>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                        
                                        <div class="col-md-6">
                                            <div class="card">
                                                <div class="card-header">
                                                    <h6 class="mb-0">Timeout Settings</h6>
                                                </div>
                                                <div class="card-body">
                                                    <div class="mb-3">
                                                        <label for="document_timeout" class="form-label">Document Upload Timeout (hours)</label>
                                                        <input type="number" class="form-control" name="document_timeout" id="document_timeout" value="<?php echo $settings['document_timeout']; ?>" min="24" max="168">
                                                        <div class="form-text">Time limit for document submission</div>
                                                    </div>
                                                    
                                                    <div class="mb-3">
                                                        <label for="agreement_timeout" class="form-label">Agreement Signing Timeout (hours)</label>
                                                        <input type="number" class="form-control" name="agreement_timeout" id="agreement_timeout" value="<?php echo $settings['agreement_timeout']; ?>" min="24" max="168">
                                                        <div class="form-text">Time limit for agreement signing</div>
                                                    </div>
                                                    
                                                    <div class="mb-3">
                                                        <label for="funding_timeout" class="form-label">Funding Processing Timeout (hours)</label>
                                                        <input type="number" class="form-control" name="funding_timeout" id="funding_timeout" value="<?php echo $settings['funding_timeout']; ?>" min="24" max="336">
                                                        <div class="form-text">Time limit for funding completion</div>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                    
                                    <div class="mt-3">
                                        <button type="submit" class="btn btn-primary">
                                            <i class="fas fa-save"></i> Save Settings
                                        </button>
                                    </div>
                                </form>
                            </div>
                        </div>
                        
                        <!-- Active Workflows Tab -->
                        <div class="tab-pane fade" id="workflow" role="tabpanel">
                            <div class="mt-3">
                                <div class="table-responsive">
                                    <table class="table table-striped">
                                        <thead>
                                            <tr>
                                                <th>Reference</th>
                                                <th>Client</th>
                                                <th>Status</th>
                                                <th>Step</th>
                                                <th>Amount</th>
                                                <th>Last Update</th>
                                                <th>Hours Waiting</th>
                                                <th>Actions</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($workflow_apps as $app): ?>
                                                <tr>
                                                    <td><?php echo htmlspecialchars($app['reference_number']); ?></td>
                                                    <td><?php echo htmlspecialchars($app['client_name']); ?></td>
                                                    <td>
                                                        <span class="badge bg-<?php 
                                                            echo match($app['application_status']) {
                                                                'pre_approved' => 'info',
                                                                'document_review' => 'warning',
                                                                'approved' => 'success',
                                                                'funding' => 'primary',
                                                                default => 'secondary'
                                                            };
                                                        ?>">
                                                            <?php echo ucwords(str_replace('_', ' ', $app['application_status'])); ?>
                                                        </span>
                                                    </td>
                                                    <td><?php echo $app['current_step']; ?>/5</td>
                                                    <td>$<?php echo number_format($app['loan_amount'], 2); ?></td>
                                                    <td><?php echo date('M j, Y H:i', strtotime($app['updated_at'])); ?></td>
                                                    <td>
                                                        <span class="<?php echo $app['hours_since_update'] > 48 ? 'text-danger' : ($app['hours_since_update'] > 24 ? 'text-warning' : 'text-success'); ?>">
                                                            <?php echo $app['hours_since_update']; ?>h
                                                        </span>
                                                    </td>
                                                    <td>
                                                        <a href="loan-details.php?id=<?php echo $app['reference_number']; ?>" class="btn btn-sm btn-outline-primary">
                                                            <i class="fas fa-eye"></i>
                                                        </a>
                                                    </td>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Recent Activity Tab -->
                        <div class="tab-pane fade" id="activity" role="tabpanel">
                            <div class="mt-3">
                                <div class="table-responsive">
                                    <table class="table table-striped">
                                        <thead>
                                            <tr>
                                                <th>Reference</th>
                                                <th>Client</th>
                                                <th>Status</th>
                                                <th>Amount</th>
                                                <th>Rate</th>
                                                <th>Pre-Approved</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($recent_activity as $app): ?>
                                                <tr>
                                                    <td><?php echo htmlspecialchars($app['reference_number']); ?></td>
                                                    <td><?php echo htmlspecialchars($app['client_name']); ?></td>
                                                    <td>
                                                        <span class="badge bg-<?php 
                                                            echo match($app['application_status']) {
                                                                'pre_approved' => 'info',
                                                                'document_review' => 'warning',
                                                                'approved' => 'success',
                                                                'funding' => 'primary',
                                                                'funded' => 'success',
                                                                default => 'secondary'
                                                            };
                                                        ?>">
                                                            <?php echo ucwords(str_replace('_', ' ', $app['application_status'])); ?>
                                                        </span>
                                                    </td>
                                                    <td>$<?php echo number_format($app['loan_amount'], 2); ?></td>
                                                    <td><?php echo $app['pre_approval_rate'] ? $app['pre_approval_rate'] . '%' : '-'; ?></td>
                                                    <td><?php echo date('M j, Y H:i', strtotime($app['pre_approved_at'])); ?></td>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<style>
.card {
    box-shadow: 0 0.125rem 0.25rem rgba(0, 0, 0, 0.075);
    border: 1px solid rgba(0, 0, 0, 0.125);
}

.nav-tabs .nav-link {
    border: 1px solid transparent;
    border-top-left-radius: 0.375rem;
    border-top-right-radius: 0.375rem;
}

.nav-tabs .nav-link.active {
    color: #495057;
    background-color: #fff;
    border-color: #dee2e6 #dee2e6 #fff;
}

.table th {
    border-top: none;
    font-weight: 600;
    color: #495057;
}

.badge {
    font-size: 0.75em;
}

.form-check-input:checked {
    background-color: #0d6efd;
    border-color: #0d6efd;
}

.btn-outline-primary:hover {
    color: #fff;
    background-color: #0d6efd;
    border-color: #0d6efd;
}
</style>

<?php include '../includes/footer.php'; ?>