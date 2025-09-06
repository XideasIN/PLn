<?php
/**
 * Email Management Admin Interface
 * LoanFlow Personal Loan Management System
 */

require_once '../includes/config.php';
require_once '../includes/auth.php';
require_once '../includes/functions.php';

// Check authentication and admin role
if (!isLoggedIn() || !isAdmin()) {
    header('Location: ../login.php');
    exit();
}

$pageTitle = 'Email Management';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $pageTitle; ?> - LoanFlow Admin</title>
    <link href="../assets/css/bootstrap.min.css" rel="stylesheet">
    <link href="../assets/css/admin.css" rel="stylesheet">
    <link href="../assets/css/email-admin.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/summernote@0.8.20/dist/summernote-bs5.min.css" rel="stylesheet">
</head>
<body>
    <?php include '../includes/admin-header.php'; ?>
    
    <div class="admin-container">
        <?php include '../includes/admin-sidebar.php'; ?>
        
        <main class="admin-main">
            <div class="email-management">
                <div class="email-header">
                    <h1><i class="fas fa-envelope"></i> Email Management</h1>
                    <div class="header-actions">
                        <button class="btn btn-primary" onclick="emailManager.showTemplateModal()">
                            <i class="fas fa-plus"></i> New Template
                        </button>
                        <button class="btn btn-success" onclick="emailManager.showBulkEmailModal()">
                            <i class="fas fa-paper-plane"></i> Send Bulk Email
                        </button>
                        <button class="btn btn-info" onclick="emailManager.processQueue()">
                            <i class="fas fa-cogs"></i> Process Queue
                        </button>
                    </div>
                </div>

                <!-- Email System Status -->
                <div class="system-status">
                    <div class="status-card">
                        <div class="status-indicator" id="emailSystemStatus">
                            <i class="fas fa-circle"></i>
                            <span>Checking...</span>
                        </div>
                        <button class="btn btn-sm btn-outline-primary" onclick="emailManager.testEmailSettings()">
                            <i class="fas fa-vial"></i> Test Email
                        </button>
                    </div>
                </div>

                <!-- Statistics Cards -->
                <div class="stats-grid">
                    <div class="stat-card">
                        <div class="stat-icon">
                            <i class="fas fa-paper-plane"></i>
                        </div>
                        <div class="stat-content">
                            <h3 id="totalSentEmails">-</h3>
                            <p>Emails Sent (30 days)</p>
                        </div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-icon">
                            <i class="fas fa-clock"></i>
                        </div>
                        <div class="stat-content">
                            <h3 id="pendingEmails">-</h3>
                            <p>Pending in Queue</p>
                        </div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-icon">
                            <i class="fas fa-file-alt"></i>
                        </div>
                        <div class="stat-content">
                            <h3 id="activeTemplates">-</h3>
                            <p>Active Templates</p>
                        </div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-icon">
                            <i class="fas fa-percentage"></i>
                        </div>
                        <div class="stat-content">
                            <h3 id="successRate">-</h3>
                            <p>Success Rate</p>
                        </div>
                    </div>
                </div>

                <!-- Main Content Tabs -->
                <div class="email-tabs">
                    <nav class="nav nav-tabs" id="emailTabs">
                        <button class="nav-link active" data-tab="templates">
                            <i class="fas fa-file-alt"></i> Templates
                        </button>
                        <button class="nav-link" data-tab="queue">
                            <i class="fas fa-list"></i> Email Queue
                        </button>
                        <button class="nav-link" data-tab="campaigns">
                            <i class="fas fa-bullhorn"></i> Campaigns
                        </button>
                        <button class="nav-link" data-tab="analytics">
                            <i class="fas fa-chart-bar"></i> Analytics
                        </button>
                        <button class="nav-link" data-tab="logs">
                            <i class="fas fa-history"></i> Delivery Logs
                        </button>
                    </nav>

                    <!-- Templates Tab -->
                    <div class="tab-content active" id="templatesTab">
                        <div class="templates-header">
                            <h3>Email Templates</h3>
                            <div class="template-filters">
                                <select class="form-select" id="templateTypeFilter">
                                    <option value="all">All Types</option>
                                    <option value="step_before">Before Step</option>
                                    <option value="step_after">After Step</option>
                                    <option value="time_based">Time Based</option>
                                    <option value="manual">Manual</option>
                                    <option value="bulk">Bulk Email</option>
                                </select>
                            </div>
                        </div>
                        
                        <div class="templates-grid" id="templatesGrid">
                            <!-- Templates will be loaded here -->
                        </div>
                    </div>

                    <!-- Email Queue Tab -->
                    <div class="tab-content" id="queueTab">
                        <div class="queue-header">
                            <h3>Email Queue</h3>
                            <div class="queue-controls">
                                <select class="form-select" id="queueStatusFilter">
                                    <option value="all">All Status</option>
                                    <option value="pending">Pending</option>
                                    <option value="processing">Processing</option>
                                    <option value="sent">Sent</option>
                                    <option value="failed">Failed</option>
                                    <option value="cancelled">Cancelled</option>
                                </select>
                                <button class="btn btn-primary" onclick="emailManager.refreshQueue()">
                                    <i class="fas fa-sync"></i> Refresh
                                </button>
                            </div>
                        </div>
                        
                        <div class="queue-table-container">
                            <table class="table table-striped" id="queueTable">
                                <thead>
                                    <tr>
                                        <th>ID</th>
                                        <th>Recipient</th>
                                        <th>Subject</th>
                                        <th>Template</th>
                                        <th>Status</th>
                                        <th>Scheduled</th>
                                        <th>Attempts</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody id="queueTableBody">
                                    <!-- Queue items will be loaded here -->
                                </tbody>
                            </table>
                        </div>
                        
                        <div class="queue-pagination">
                            <nav id="queuePagination">
                                <!-- Pagination will be loaded here -->
                            </nav>
                        </div>
                    </div>

                    <!-- Campaigns Tab -->
                    <div class="tab-content" id="campaignsTab">
                        <div class="campaigns-header">
                            <h3>Bulk Email Campaigns</h3>
                        </div>
                        
                        <div class="campaigns-list" id="campaignsList">
                            <!-- Campaigns will be loaded here -->
                        </div>
                    </div>

                    <!-- Analytics Tab -->
                    <div class="tab-content" id="analyticsTab">
                        <div class="analytics-header">
                            <h3>Email Analytics</h3>
                        </div>
                        
                        <div class="analytics-grid">
                            <div class="chart-container">
                                <h4>Daily Email Volume</h4>
                                <canvas id="dailyVolumeChart"></canvas>
                            </div>
                            
                            <div class="chart-container">
                                <h4>Email Status Distribution</h4>
                                <canvas id="statusChart"></canvas>
                            </div>
                            
                            <div class="chart-container full-width">
                                <h4>Template Performance</h4>
                                <canvas id="templatePerformanceChart"></canvas>
                            </div>
                        </div>
                    </div>

                    <!-- Delivery Logs Tab -->
                    <div class="tab-content" id="logsTab">
                        <div class="logs-header">
                            <h3>Delivery Logs</h3>
                            <div class="log-filters">
                                <select class="form-select" id="logStatusFilter">
                                    <option value="all">All Status</option>
                                    <option value="sent">Sent</option>
                                    <option value="failed">Failed</option>
                                </select>
                                <select class="form-select" id="logTemplateFilter">
                                    <option value="0">All Templates</option>
                                    <!-- Template options will be loaded -->
                                </select>
                            </div>
                        </div>
                        
                        <div class="logs-table-container">
                            <table class="table table-striped" id="logsTable">
                                <thead>
                                    <tr>
                                        <th>ID</th>
                                        <th>Recipient</th>
                                        <th>Subject</th>
                                        <th>Template</th>
                                        <th>Status</th>
                                        <th>Sent At</th>
                                        <th>Attempts</th>
                                        <th>Error</th>
                                    </tr>
                                </thead>
                                <tbody id="logsTableBody">
                                    <!-- Log entries will be loaded here -->
                                </tbody>
                            </table>
                        </div>
                        
                        <div class="logs-pagination">
                            <nav id="logsPagination">
                                <!-- Pagination will be loaded here -->
                            </nav>
                        </div>
                    </div>
                </div>
            </div>
        </main>
    </div>

    <!-- Template Modal -->
    <div class="modal fade" id="templateModal" tabindex="-1">
        <div class="modal-dialog modal-xl">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Email Template</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <form id="templateForm">
                        <input type="hidden" id="templateId" name="template_id">
                        
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="templateName" class="form-label">Template Name</label>
                                    <input type="text" class="form-control" id="templateName" name="name" required>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="triggerType" class="form-label">Trigger Type</label>
                                    <select class="form-select" id="triggerType" name="trigger_type" required>
                                        <option value="step_before">Before Step</option>
                                        <option value="step_after">After Step</option>
                                        <option value="time_based">Time Based</option>
                                        <option value="manual">Manual</option>
                                        <option value="bulk">Bulk Email</option>
                                    </select>
                                </div>
                            </div>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-8">
                                <div class="mb-3">
                                    <label for="triggerCondition" class="form-label">Trigger Condition</label>
                                    <input type="text" class="form-control" id="triggerCondition" name="trigger_condition" 
                                           placeholder="e.g., step_4, application_approved">
                                    <div class="form-text">Specify when this template should be triggered</div>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="mb-3">
                                    <label for="delayHours" class="form-label">Delay (Hours)</label>
                                    <input type="number" class="form-control" id="delayHours" name="delay_hours" min="0" value="0">
                                    <div class="form-text">Delay before sending (0 = immediate)</div>
                                </div>
                            </div>
                        </div>
                        
                        <div class="mb-3">
                            <label for="templateSubject" class="form-label">Subject</label>
                            <input type="text" class="form-control" id="templateSubject" name="subject" required>
                        </div>
                        
                        <div class="mb-3">
                            <label for="templateBody" class="form-label">Email Body</label>
                            <textarea class="form-control" id="templateBody" name="body_template" rows="10" required></textarea>
                        </div>
                        
                        <div class="mb-3">
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" id="isActive" name="is_active" checked>
                                <label class="form-check-label" for="isActive">
                                    Active Template
                                </label>
                            </div>
                        </div>
                        
                        <div class="template-variables">
                            <h6>Available Variables:</h6>
                            <div class="variables-grid" id="variablesGrid">
                                <!-- Variables will be loaded here -->
                            </div>
                        </div>
                    </form>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="button" class="btn btn-primary" onclick="emailManager.saveTemplate()">Save Template</button>
                </div>
            </div>
        </div>
    </div>

    <!-- Bulk Email Modal -->
    <div class="modal fade" id="bulkEmailModal" tabindex="-1">
        <div class="modal-dialog modal-xl">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Send Bulk Email</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <form id="bulkEmailForm">
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="bulkTemplate" class="form-label">Use Template (Optional)</label>
                                    <select class="form-select" id="bulkTemplate" name="template_id">
                                        <option value="0">Custom Email</option>
                                        <!-- Templates will be loaded -->
                                    </select>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="recipientFilter" class="form-label">Recipients</label>
                                    <select class="form-select" id="recipientFilter" name="recipient_filter">
                                        <option value="all">All Users</option>
                                        <option value="active_applications">Active Applications</option>
                                        <option value="pending_step_1">Pending Step 1</option>
                                        <option value="pending_step_2">Pending Step 2</option>
                                        <option value="pending_step_3">Pending Step 3</option>
                                        <option value="pending_step_4">Pending Step 4</option>
                                        <option value="approved">Approved Applications</option>
                                        <option value="custom">Custom Selection</option>
                                    </select>
                                </div>
                            </div>
                        </div>
                        
                        <div class="mb-3">
                            <label for="bulkSubject" class="form-label">Subject</label>
                            <input type="text" class="form-control" id="bulkSubject" name="subject" required>
                        </div>
                        
                        <div class="mb-3">
                            <label for="bulkBody" class="form-label">Email Body</label>
                            <textarea class="form-control" id="bulkBody" name="body" rows="10" required></textarea>
                        </div>
                        
                        <div class="recipient-preview" id="recipientPreview">
                            <h6>Recipients Preview:</h6>
                            <div class="recipient-count">Loading...</div>
                            <div class="recipient-list"></div>
                        </div>
                        
                        <div class="mb-3">
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" id="sendImmediately" name="send_immediately">
                                <label class="form-check-label" for="sendImmediately">
                                    Send Immediately (otherwise queue for processing)
                                </label>
                            </div>
                        </div>
                    </form>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="button" class="btn btn-primary" onclick="emailManager.sendBulkEmail()">Send Email</button>
                </div>
            </div>
        </div>
    </div>

    <!-- Email Preview Modal -->
    <div class="modal fade" id="emailPreviewModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Email Preview</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="email-preview">
                        <div class="email-header-preview">
                            <strong>Subject:</strong> <span id="previewSubject"></span>
                        </div>
                        <div class="email-body-preview" id="previewBody">
                            <!-- Email body preview -->
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                </div>
            </div>
        </div>
    </div>

    <!-- Alert Container -->
    <div id="alertContainer" class="alert-container"></div>

    <!-- Scripts -->
    <script src="../assets/js/jquery.min.js"></script>
    <script src="../assets/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/summernote@0.8.20/dist/summernote-bs5.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script src="../assets/js/email-admin.js"></script>
    
    <script>
        // Initialize email management system
        $(document).ready(function() {
            emailManager.init();
        });
    </script>
</body>
</html>