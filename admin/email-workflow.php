<?php
/**
 * Email Workflow Management Interface
 * Admin interface for managing automated email workflows and campaigns
 */

require_once '../includes/config.php';
require_once '../includes/auth.php';
require_once '../includes/email_automation.php';

// Check authentication and admin privileges
session_start();
if (!isLoggedIn() || !isAdmin()) {
    header('Location: ../login.php');
    exit();
}

$emailManager = new EmailAutomationManager();
$templateManager = new EmailTemplateManager();

// Handle form submissions
$message = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $action = $_POST['action'] ?? '';
        
        switch ($action) {
            case 'save_template':
                $templateData = [
                    'id' => $_POST['template_id'] ?? null,
                    'name' => $_POST['template_name'],
                    'subject' => $_POST['subject_template'],
                    'body' => $_POST['body_template'],
                    'trigger_type' => $_POST['trigger_type'],
                    'delay_minutes' => (int)$_POST['delay_minutes'],
                    'priority' => (int)$_POST['priority'],
                    'is_active' => isset($_POST['is_active']),
                    'from_email' => $_POST['from_email'],
                    'from_name' => $_POST['from_name']
                ];
                
                if ($templateManager->saveTemplate($templateData)) {
                    $message = 'Email template saved successfully!';
                } else {
                    $error = 'Failed to save email template.';
                }
                break;
                
            case 'delete_template':
                $templateId = $_POST['template_id'];
                if ($templateManager->deleteTemplate($templateId)) {
                    $message = 'Email template deleted successfully!';
                } else {
                    $error = 'Failed to delete email template.';
                }
                break;
                
            case 'create_campaign':
                $campaignData = [
                    'name' => $_POST['campaign_name'],
                    'subject' => $_POST['campaign_subject'],
                    'body' => $_POST['campaign_body'],
                    'target_criteria' => $_POST['target_criteria'],
                    'scheduled_at' => $_POST['scheduled_at'],
                    'from_email' => $_POST['from_email'],
                    'from_name' => $_POST['from_name']
                ];
                
                $campaignId = $emailManager->createBulkCampaign($campaignData);
                if ($campaignId) {
                    $message = 'Email campaign created successfully!';
                } else {
                    $error = 'Failed to create email campaign.';
                }
                break;
        }
    } catch (Exception $e) {
        $error = 'Error: ' . $e->getMessage();
    }
}

// Get templates and analytics
$templates = $templateManager->getTemplates();
$analytics = $emailManager->getEmailAnalytics();
$campaignStats = $emailManager->getCampaignStatistics();
$systemSettings = $emailManager->getSystemSettings();

?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Email Workflow Management - Admin</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="../assets/css/admin.css" rel="stylesheet">
    <style>
        .workflow-container {
            max-width: 1400px;
            margin: 0 auto;
            padding: 20px;
        }
        
        .workflow-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 30px;
            border-radius: 12px;
            margin-bottom: 30px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.1);
        }
        
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }
        
        .stat-card {
            background: white;
            padding: 25px;
            border-radius: 12px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.08);
            text-align: center;
            border-left: 4px solid #007bff;
        }
        
        .stat-card.sent {
            border-left-color: #28a745;
        }
        
        .stat-card.pending {
            border-left-color: #ffc107;
        }
        
        .stat-card.failed {
            border-left-color: #dc3545;
        }
        
        .stat-number {
            font-size: 2.5rem;
            font-weight: 700;
            color: #2c3e50;
            margin-bottom: 10px;
        }
        
        .template-card {
            background: white;
            border-radius: 12px;
            padding: 25px;
            margin-bottom: 20px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.08);
            border: 1px solid #e9ecef;
        }
        
        .template-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 15px;
        }
        
        .template-title {
            font-size: 1.3rem;
            font-weight: 600;
            color: #333;
            margin: 0;
        }
        
        .template-status {
            display: flex;
            gap: 10px;
        }
        
        .status-badge {
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 0.8rem;
            font-weight: 500;
        }
        
        .status-badge.active {
            background: #d4edda;
            color: #155724;
        }
        
        .status-badge.inactive {
            background: #f8d7da;
            color: #721c24;
        }
        
        .priority-badge {
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 0.8rem;
            font-weight: 500;
            background: #fff3cd;
            color: #856404;
        }
        
        .template-meta {
            color: #666;
            font-size: 0.9rem;
            margin-bottom: 15px;
        }
        
        .template-actions {
            display: flex;
            gap: 10px;
        }
        
        .editor-container {
            border: 1px solid #dee2e6;
            border-radius: 8px;
            overflow: hidden;
        }
        
        .editor-toolbar {
            background: #f8f9fa;
            padding: 10px 15px;
            border-bottom: 1px solid #dee2e6;
            display: flex;
            gap: 10px;
        }
        
        .variable-tags {
            display: flex;
            flex-wrap: wrap;
            gap: 8px;
            margin-top: 15px;
        }
        
        .variable-tag {
            background: #e3f2fd;
            color: #1976d2;
            padding: 4px 8px;
            border-radius: 4px;
            font-size: 0.8rem;
            cursor: pointer;
            transition: all 0.2s ease;
        }
        
        .variable-tag:hover {
            background: #bbdefb;
        }
    </style>
</head>
<body>
    <div class="workflow-container">
        <!-- Header -->
        <div class="workflow-header">
            <h1><i class="fas fa-envelope-open-text me-3"></i>Email Workflow Management</h1>
            <p>Manage automated email templates, campaigns, and workflow triggers</p>
        </div>
        
        <!-- Alerts -->
        <?php if ($message): ?>
            <div class="alert alert-success alert-dismissible fade show" role="alert">
                <?php echo htmlspecialchars($message); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>
        
        <?php if ($error): ?>
            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                <?php echo htmlspecialchars($error); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>
        
        <!-- Statistics -->
        <div class="stats-grid">
            <div class="stat-card sent">
                <div class="stat-number"><?php echo number_format($analytics['emails_sent'] ?? 0); ?></div>
                <div class="stat-label">Emails Sent</div>
            </div>
            <div class="stat-card pending">
                <div class="stat-number"><?php echo number_format($analytics['emails_pending'] ?? 0); ?></div>
                <div class="stat-label">Pending</div>
            </div>
            <div class="stat-card failed">
                <div class="stat-number"><?php echo number_format($analytics['emails_failed'] ?? 0); ?></div>
                <div class="stat-label">Failed</div>
            </div>
            <div class="stat-card">
                <div class="stat-number"><?php echo count($templates); ?></div>
                <div class="stat-label">Active Templates</div>
            </div>
        </div>
        
        <!-- Navigation Tabs -->
        <ul class="nav nav-tabs mb-4" id="workflowTabs" role="tablist">
            <li class="nav-item" role="presentation">
                <button class="nav-link active" id="templates-tab" data-bs-toggle="tab" data-bs-target="#templates" type="button" role="tab">
                    <i class="fas fa-file-alt me-2"></i>Email Templates
                </button>
            </li>
            <li class="nav-item" role="presentation">
                <button class="nav-link" id="campaigns-tab" data-bs-toggle="tab" data-bs-target="#campaigns" type="button" role="tab">
                    <i class="fas fa-bullhorn me-2"></i>Bulk Campaigns
                </button>
            </li>
            <li class="nav-item" role="presentation">
                <button class="nav-link" id="analytics-tab" data-bs-toggle="tab" data-bs-target="#analytics" type="button" role="tab">
                    <i class="fas fa-chart-bar me-2"></i>Analytics
                </button>
            </li>
            <li class="nav-item" role="presentation">
                <button class="nav-link" id="settings-tab" data-bs-toggle="tab" data-bs-target="#settings" type="button" role="tab">
                    <i class="fas fa-cog me-2"></i>Settings
                </button>
            </li>
        </ul>
        
        <!-- Tab Content -->
        <div class="tab-content" id="workflowTabContent">
            <!-- Email Templates Tab -->
            <div class="tab-pane fade show active" id="templates" role="tabpanel">
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <h3>Email Templates</h3>
                    <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#templateModal">
                        <i class="fas fa-plus me-2"></i>Create Template
                    </button>
                </div>
                
                <div class="row">
                    <?php foreach ($templates as $template): ?>
                        <div class="col-lg-6 col-xl-4">
                            <div class="template-card">
                                <div class="template-header">
                                    <h4 class="template-title"><?php echo htmlspecialchars($template['template_name']); ?></h4>
                                    <div class="template-status">
                                        <span class="status-badge <?php echo $template['is_active'] ? 'active' : 'inactive'; ?>">
                                            <?php echo $template['is_active'] ? 'Active' : 'Inactive'; ?>
                                        </span>
                                        <span class="priority-badge">Priority: <?php echo $template['priority']; ?></span>
                                    </div>
                                </div>
                                
                                <div class="template-meta">
                                    <p><strong>Trigger:</strong> <?php echo ucfirst(str_replace('_', ' ', $template['trigger_type'])); ?></p>
                                    <p><strong>Delay:</strong> <?php echo $template['delay_minutes']; ?> minutes</p>
                                    <p><strong>Subject:</strong> <?php echo htmlspecialchars(substr($template['subject_template'], 0, 50)) . '...'; ?></p>
                                </div>
                                
                                <div class="template-actions">
                                    <button class="btn btn-sm btn-outline-primary" onclick="editTemplate(<?php echo $template['id']; ?>)">
                                        <i class="fas fa-edit"></i> Edit
                                    </button>
                                    <button class="btn btn-sm btn-outline-info" onclick="previewTemplate(<?php echo $template['id']; ?>)">
                                        <i class="fas fa-eye"></i> Preview
                                    </button>
                                    <button class="btn btn-sm btn-outline-danger" onclick="deleteTemplate(<?php echo $template['id']; ?>)">
                                        <i class="fas fa-trash"></i> Delete
                                    </button>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
            
            <!-- Bulk Campaigns Tab -->
            <div class="tab-pane fade" id="campaigns" role="tabpanel">
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <h3>Bulk Email Campaigns</h3>
                    <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#campaignModal">
                        <i class="fas fa-plus me-2"></i>Create Campaign
                    </button>
                </div>
                
                <div class="card">
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-hover">
                                <thead>
                                    <tr>
                                        <th>Campaign Name</th>
                                        <th>Subject</th>
                                        <th>Recipients</th>
                                        <th>Scheduled</th>
                                        <th>Status</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($campaignStats as $campaign): ?>
                                        <tr>
                                            <td><?php echo htmlspecialchars($campaign['campaign_name']); ?></td>
                                            <td><?php echo htmlspecialchars(substr($campaign['subject'], 0, 40)) . '...'; ?></td>
                                            <td><?php echo number_format($campaign['recipient_count']); ?></td>
                                            <td><?php echo date('M j, Y g:i A', strtotime($campaign['scheduled_at'])); ?></td>
                                            <td>
                                                <span class="badge bg-<?php echo $campaign['status'] === 'completed' ? 'success' : ($campaign['status'] === 'pending' ? 'warning' : 'info'); ?>">
                                                    <?php echo ucfirst($campaign['status']); ?>
                                                </span>
                                            </td>
                                            <td>
                                                <button class="btn btn-sm btn-outline-info" onclick="viewCampaignDetails(<?php echo $campaign['id']; ?>)">
                                                    <i class="fas fa-eye"></i> View
                                                </button>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Analytics Tab -->
            <div class="tab-pane fade" id="analytics" role="tabpanel">
                <h3 class="mb-4">Email Analytics</h3>
                
                <div class="row">
                    <div class="col-lg-8">
                        <div class="card">
                            <div class="card-header">
                                <h5>Email Delivery Trends</h5>
                            </div>
                            <div class="card-body">
                                <canvas id="emailChart" height="100"></canvas>
                            </div>
                        </div>
                    </div>
                    <div class="col-lg-4">
                        <div class="card">
                            <div class="card-header">
                                <h5>Template Performance</h5>
                            </div>
                            <div class="card-body">
                                <div id="templatePerformance">
                                    <!-- Template performance data will be loaded here -->
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Settings Tab -->
            <div class="tab-pane fade" id="settings" role="tabpanel">
                <h3 class="mb-4">Email System Settings</h3>
                
                <div class="card">
                    <div class="card-body">
                        <form method="POST">
                            <input type="hidden" name="action" value="save_settings">
                            
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label class="form-label">Default From Email</label>
                                        <input type="email" class="form-control" name="default_from_email" 
                                               value="<?php echo htmlspecialchars($systemSettings['default_from_email'] ?? ''); ?>">
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label class="form-label">Default From Name</label>
                                        <input type="text" class="form-control" name="default_from_name" 
                                               value="<?php echo htmlspecialchars($systemSettings['default_from_name'] ?? ''); ?>">
                                    </div>
                                </div>
                            </div>
                            
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label class="form-label">Queue Processing Interval (minutes)</label>
                                        <input type="number" class="form-control" name="queue_interval" 
                                               value="<?php echo $systemSettings['queue_processing_interval'] ?? 5; ?>">
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label class="form-label">Max Emails Per Batch</label>
                                        <input type="number" class="form-control" name="max_batch_size" 
                                               value="<?php echo $systemSettings['max_emails_per_batch'] ?? 50; ?>">
                                    </div>
                                </div>
                            </div>
                            
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-save me-2"></i>Save Settings
                            </button>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Template Modal -->
    <div class="modal fade" id="templateModal" tabindex="-1">
        <div class="modal-dialog modal-xl">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Email Template</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST" id="templateForm">
                    <div class="modal-body">
                        <input type="hidden" name="action" value="save_template">
                        <input type="hidden" name="template_id" id="templateId">
                        
                        <div class="row">
                            <div class="col-md-8">
                                <div class="mb-3">
                                    <label class="form-label">Template Name</label>
                                    <input type="text" class="form-control" name="template_name" id="templateName" required>
                                </div>
                                
                                <div class="mb-3">
                                    <label class="form-label">Subject Template</label>
                                    <input type="text" class="form-control" name="subject_template" id="subjectTemplate" required>
                                </div>
                                
                                <div class="mb-3">
                                    <label class="form-label">Email Body</label>
                                    <div class="editor-container">
                                        <div class="editor-toolbar">
                                            <button type="button" class="btn btn-sm btn-outline-secondary" onclick="insertVariable('{{client_name}}')">
                                                Client Name
                                            </button>
                                            <button type="button" class="btn btn-sm btn-outline-secondary" onclick="insertVariable('{{loan_amount}}')">
                                                Loan Amount
                                            </button>
                                            <button type="button" class="btn btn-sm btn-outline-secondary" onclick="insertVariable('{{reference_number}}')">
                                                Reference #
                                            </button>
                                            <button type="button" class="btn btn-sm btn-outline-secondary" onclick="insertVariable('{{company_name}}')">
                                                Company Name
                                            </button>
                                        </div>
                                        <textarea class="form-control" name="body_template" id="bodyTemplate" rows="10" required></textarea>
                                    </div>
                                    
                                    <div class="variable-tags">
                                        <span class="variable-tag" onclick="insertVariable('{{client_name}}')">{{client_name}}</span>
                                        <span class="variable-tag" onclick="insertVariable('{{first_name}}')">{{first_name}}</span>
                                        <span class="variable-tag" onclick="insertVariable('{{last_name}}')">{{last_name}}</span>
                                        <span class="variable-tag" onclick="insertVariable('{{email}}')">{{email}}</span>
                                        <span class="variable-tag" onclick="insertVariable('{{loan_amount}}')">{{loan_amount}}</span>
                                        <span class="variable-tag" onclick="insertVariable('{{reference_number}}')">{{reference_number}}</span>
                                        <span class="variable-tag" onclick="insertVariable('{{company_name}}')">{{company_name}}</span>
                                        <span class="variable-tag" onclick="insertVariable('{{login_url}}')">{{login_url}}</span>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="col-md-4">
                                <div class="mb-3">
                                    <label class="form-label">Trigger Type</label>
                                    <select class="form-select" name="trigger_type" id="triggerType" required>
                                        <option value="application_submitted">Application Submitted</option>
                                        <option value="application_approved">Application Approved</option>
                                        <option value="application_rejected">Application Rejected</option>
                                        <option value="documents_requested">Documents Requested</option>
                                        <option value="payment_due">Payment Due</option>
                                        <option value="payment_overdue">Payment Overdue</option>
                                        <option value="loan_completed">Loan Completed</option>
                                    </select>
                                </div>
                                
                                <div class="mb-3">
                                    <label class="form-label">Delay (minutes)</label>
                                    <input type="number" class="form-control" name="delay_minutes" id="delayMinutes" value="0" min="0">
                                </div>
                                
                                <div class="mb-3">
                                    <label class="form-label">Priority</label>
                                    <select class="form-select" name="priority" id="priority">
                                        <option value="1">Low</option>
                                        <option value="2" selected>Normal</option>
                                        <option value="3">High</option>
                                    </select>
                                </div>
                                
                                <div class="mb-3">
                                    <label class="form-label">From Email</label>
                                    <input type="email" class="form-control" name="from_email" id="fromEmail">
                                </div>
                                
                                <div class="mb-3">
                                    <label class="form-label">From Name</label>
                                    <input type="text" class="form-control" name="from_name" id="fromName">
                                </div>
                                
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" name="is_active" id="isActive" checked>
                                    <label class="form-check-label" for="isActive">
                                        Active Template
                                    </label>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-save me-2"></i>Save Template
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    
    <!-- Campaign Modal -->
    <div class="modal fade" id="campaignModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Create Bulk Email Campaign</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST">
                    <div class="modal-body">
                        <input type="hidden" name="action" value="create_campaign">
                        
                        <div class="mb-3">
                            <label class="form-label">Campaign Name</label>
                            <input type="text" class="form-control" name="campaign_name" required>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Subject</label>
                            <input type="text" class="form-control" name="campaign_subject" required>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Email Body</label>
                            <textarea class="form-control" name="campaign_body" rows="8" required></textarea>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">Target Criteria</label>
                                    <select class="form-select" name="target_criteria" required>
                                        <option value="all_clients">All Clients</option>
                                        <option value="active_loans">Clients with Active Loans</option>
                                        <option value="pending_applications">Pending Applications</option>
                                        <option value="overdue_payments">Overdue Payments</option>
                                    </select>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">Schedule Date/Time</label>
                                    <input type="datetime-local" class="form-control" name="scheduled_at" required>
                                </div>
                            </div>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">From Email</label>
                                    <input type="email" class="form-control" name="from_email" required>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">From Name</label>
                                    <input type="text" class="form-control" name="from_name" required>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-paper-plane me-2"></i>Create Campaign
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script src="../assets/js/email-workflow.js"></script>
</body>
</html>