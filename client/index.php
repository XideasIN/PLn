<?php
/**
 * Client Dashboard
 * LoanFlow Personal Loan Management System
 */

require_once '../config/database.php';
require_once '../includes/functions.php';
require_once '../includes/auth.php';

// Require client login
requireLogin();

$current_user = getCurrentUser();

// Get client's loan application
try {
    $db = getDB();
    
    // Get active loan application
    $stmt = $db->prepare("
        SELECT * FROM loan_applications 
        WHERE user_id = ? 
        ORDER BY created_at DESC 
        LIMIT 1
    ");
    $stmt->execute([$current_user['id']]);
    $application = $stmt->fetch();
    
    // Get uploaded documents
    $stmt = $db->prepare("
        SELECT * FROM documents 
        WHERE user_id = ? 
        ORDER BY created_at DESC
    ");
    $stmt->execute([$current_user['id']]);
    $documents = $stmt->fetchAll();
    
    // Get recent messages
    $stmt = $db->prepare("
        SELECT cm.*, u.first_name, u.last_name 
        FROM client_messages cm
        LEFT JOIN users u ON cm.sender_id = u.id
        WHERE cm.user_id = ? OR cm.recipient_id = ?
        ORDER BY cm.created_at DESC 
        LIMIT 5
    ");
    $stmt->execute([$current_user['id'], $current_user['id']]);
    $messages = $stmt->fetchAll();
    
    // Get payment information
    $stmt = $db->prepare("
        SELECT * FROM payments 
        WHERE user_id = ? 
        ORDER BY created_at DESC
    ");
    $stmt->execute([$current_user['id']]);
    $payments = $stmt->fetchAll();
    
    // Get digital signatures
    $stmt = $db->prepare("
        SELECT * FROM digital_signatures 
        WHERE user_id = ? 
        ORDER BY signed_at DESC
    ");
    $stmt->execute([$current_user['id']]);
    $signatures = $stmt->fetchAll();
    
    // Get recent memos (non-internal)
    $stmt = $db->prepare("
        SELECT cm.*, u.first_name, u.last_name 
        FROM client_memos cm
        LEFT JOIN users u ON cm.created_by = u.id
        WHERE cm.user_id = ? AND cm.is_internal = 0
        ORDER BY cm.created_at DESC 
        LIMIT 5
    ");
    $stmt->execute([$current_user['id']]);
    $memos = $stmt->fetchAll();
    
} catch (Exception $e) {
    error_log("Client dashboard error: " . $e->getMessage());
    $application = null;
    $documents = $messages = $payments = $signatures = $memos = [];
}

// Calculate application progress
$progress_steps = [
    1 => 'Application Submitted',
    2 => 'Documents Required',
    3 => 'Document Review',
    4 => 'Agreement Signing',
    5 => 'Bank Details',
    6 => 'Fee Payment',
    7 => 'Final Processing',
    8 => 'Approved/Funded'
];

$current_step = $application ? $application['current_step'] : 1;
$application_status = $application ? $application['application_status'] : 'pending';

// Determine next action needed
$next_action = getNextAction($application, $documents, $signatures, $payments);

function getNextAction($application, $documents, $signatures, $payments) {
    if (!$application) return 'Submit Application';
    
    switch ($application['application_status']) {
        case 'pending':
            return 'Waiting for Pre-Approval';
        case 'pre_approved':
            // Check if documents are uploaded
            $required_docs = ['photo_id', 'proof_income', 'proof_address'];
            $uploaded_docs = array_column($documents, 'document_type');
            $missing_docs = array_diff($required_docs, $uploaded_docs);
            
            if (!empty($missing_docs)) {
                return 'Upload Required Documents';
            }
            return 'Waiting for Document Review';
        case 'document_review':
            return 'Documents Under Review';
        case 'approved':
            // Check if agreements are signed
            $required_signatures = ['loan_agreement'];
            $signed_docs = array_column($signatures, 'document_type');
            $missing_signatures = array_diff($required_signatures, $signed_docs);
            
            if (!empty($missing_signatures)) {
                return 'Sign Loan Agreement';
            }
            
            // Check if bank details are provided
            // This would require another query, simplified for now
            return 'Provide Bank Details';
        case 'funded':
            return 'Application Complete';
        case 'cancelled':
            return 'Application Cancelled';
        case 'rejected':
            return 'Application Rejected';
        default:
            return 'Contact Support';
    }
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Client Dashboard - LoanFlow</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="../assets/css/style.css" rel="stylesheet">
    <link href="../assets/css/client.css" rel="stylesheet">
</head>
<body>
    <!-- Client Header -->
    <nav class="navbar navbar-expand-lg navbar-dark bg-primary">
        <div class="container-fluid">
            <a class="navbar-brand" href="index.php">
                <i class="fas fa-user-circle me-2"></i>LoanFlow Client Portal
            </a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav me-auto">
                    <li class="nav-item">
                        <a class="nav-link active" href="index.php">
                            <i class="fas fa-tachometer-alt me-1"></i>Dashboard
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="documents.php">
                            <i class="fas fa-folder me-1"></i>Documents
                            <?php if (count(array_filter($documents, fn($d) => $d['upload_status'] === 'uploaded')) > 0): ?>
                                <span class="badge bg-warning ms-1">!</span>
                            <?php endif; ?>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="agreements.php">
                            <i class="fas fa-file-contract me-1"></i>Agreements
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="payments.php">
                            <i class="fas fa-credit-card me-1"></i>Payments
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="messages.php">
                            <i class="fas fa-envelope me-1"></i>Messages
                            <?php if (count(array_filter($messages, fn($m) => !$m['is_read'])) > 0): ?>
                                <span class="badge bg-danger ms-1"><?= count(array_filter($messages, fn($m) => !$m['is_read'])) ?></span>
                            <?php endif; ?>
                        </a>
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
                            <li><a class="dropdown-item" href="settings.php">
                                <i class="fas fa-cog me-2"></i>Settings
                            </a></li>
                            <li><hr class="dropdown-divider"></li>
                            <li><a class="dropdown-item" href="support.php">
                                <i class="fas fa-question-circle me-2"></i>Support
                            </a></li>
                            <li><a class="dropdown-item" href="../admin/logout.php">
                                <i class="fas fa-sign-out-alt me-2"></i>Logout
                            </a></li>
                        </ul>
                    </li>
                </ul>
            </div>
        </div>
    </nav>

    <div class="container-fluid py-4">
        <!-- Welcome Section -->
        <div class="row mb-4">
            <div class="col-12">
                <div class="welcome-banner">
                    <div class="row align-items-center">
                        <div class="col-md-8">
                            <h2 class="welcome-title">
                                Welcome back, <?= htmlspecialchars($current_user['first_name']) ?>!
                            </h2>
                            <p class="welcome-subtitle mb-0">
                                Reference Number: <strong><?= htmlspecialchars($current_user['reference_number']) ?></strong>
                            </p>
                        </div>
                        <div class="col-md-4 text-md-end">
                            <div class="quick-stats">
                                <?php if ($application): ?>
                                    <div class="stat-item">
                                        <div class="stat-label">Loan Amount</div>
                                        <div class="stat-value">$<?= number_format($application['loan_amount'], 2) ?></div>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

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

        <!-- Application Status -->
        <?php if ($application): ?>
        <div class="row mb-4">
            <div class="col-12">
                <div class="card status-card">
                    <div class="card-header">
                        <h5 class="mb-0">
                            <i class="fas fa-chart-line me-2"></i>Application Status
                        </h5>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-8">
                                <div class="status-display">
                                    <div class="status-badge status-<?= $application_status ?> mb-3">
                                        <?= ucfirst(str_replace('_', ' ', $application_status)) ?>
                                    </div>
                                    <h6 class="next-action-title">Next Action Required:</h6>
                                    <p class="next-action-text"><?= htmlspecialchars($next_action) ?></p>
                                </div>
                                
                                <!-- Progress Steps -->
                                <div class="progress-steps mt-4">
                                    <?php foreach ($progress_steps as $step => $title): ?>
                                        <div class="step-item <?= $step <= $current_step ? 'completed' : '' ?> <?= $step === $current_step ? 'current' : '' ?>">
                                            <div class="step-number"><?= $step ?></div>
                                            <div class="step-title"><?= $title ?></div>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="application-details">
                                    <h6>Application Details</h6>
                                    <div class="detail-item">
                                        <span class="label">Application Date:</span>
                                        <span class="value"><?= formatDate($application['created_at']) ?></span>
                                    </div>
                                    <div class="detail-item">
                                        <span class="label">Loan Type:</span>
                                        <span class="value"><?= ucfirst(str_replace('_', ' ', $application['loan_type'])) ?></span>
                                    </div>
                                    <div class="detail-item">
                                        <span class="label">Loan Term:</span>
                                        <span class="value"><?= $application['loan_term_months'] ?> months</span>
                                    </div>
                                    <div class="detail-item">
                                        <span class="label">Interest Rate:</span>
                                        <span class="value"><?= $application['interest_rate'] ?>%</span>
                                    </div>
                                    <div class="detail-item">
                                        <span class="label">Monthly Payment:</span>
                                        <span class="value">$<?= number_format($application['monthly_payment'], 2) ?></span>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <?php endif; ?>

        <!-- Quick Actions -->
        <div class="row mb-4">
            <div class="col-12">
                <div class="card">
                    <div class="card-header">
                        <h5 class="mb-0">
                            <i class="fas fa-bolt me-2"></i>Quick Actions
                        </h5>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <?php if (!$application): ?>
                                <div class="col-md-4 mb-3">
                                    <a href="../index.php" class="btn btn-primary btn-lg w-100 action-btn">
                                        <i class="fas fa-plus fa-2x mb-2 d-block"></i>
                                        Submit Application
                                    </a>
                                </div>
                            <?php else: ?>
                                <?php if ($application_status === 'pre_approved'): ?>
                                    <div class="col-md-4 mb-3">
                                        <a href="documents.php" class="btn btn-warning btn-lg w-100 action-btn">
                                            <i class="fas fa-upload fa-2x mb-2 d-block"></i>
                                            Upload Documents
                                        </a>
                                    </div>
                                <?php endif; ?>
                                
                                <?php if ($application_status === 'approved'): ?>
                                    <div class="col-md-4 mb-3">
                                        <a href="agreements.php" class="btn btn-success btn-lg w-100 action-btn">
                                            <i class="fas fa-signature fa-2x mb-2 d-block"></i>
                                            Sign Agreements
                                        </a>
                                    </div>
                                <?php endif; ?>
                                
                                <div class="col-md-4 mb-3">
                                    <a href="payments.php" class="btn btn-info btn-lg w-100 action-btn">
                                        <i class="fas fa-credit-card fa-2x mb-2 d-block"></i>
                                        Make Payment
                                    </a>
                                </div>
                            <?php endif; ?>
                            
                            <div class="col-md-4 mb-3">
                                <a href="messages.php" class="btn btn-outline-primary btn-lg w-100 action-btn">
                                    <i class="fas fa-envelope fa-2x mb-2 d-block"></i>
                                    Send Message
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Dashboard Content -->
        <div class="row">
            <!-- Documents Status -->
            <div class="col-lg-6 mb-4">
                <div class="card h-100">
                    <div class="card-header">
                        <h6 class="mb-0">
                            <i class="fas fa-folder me-2"></i>Document Status
                        </h6>
                    </div>
                    <div class="card-body">
                        <?php if (empty($documents)): ?>
                            <div class="text-center py-4">
                                <i class="fas fa-folder-open fa-3x text-muted mb-3"></i>
                                <p class="text-muted">No documents uploaded yet</p>
                                <a href="documents.php" class="btn btn-primary">Upload Documents</a>
                            </div>
                        <?php else: ?>
                            <div class="document-list">
                                <?php foreach (array_slice($documents, 0, 5) as $doc): ?>
                                    <div class="document-item">
                                        <div class="document-info">
                                            <i class="fas fa-file-alt me-2"></i>
                                            <span><?= ucfirst(str_replace('_', ' ', $doc['document_type'])) ?></span>
                                        </div>
                                        <span class="status-badge status-<?= $doc['upload_status'] ?>">
                                            <?= ucfirst($doc['upload_status']) ?>
                                        </span>
                                    </div>
                                <?php endforeach; ?>
                                
                                <?php if (count($documents) > 5): ?>
                                    <div class="text-center mt-3">
                                        <a href="documents.php" class="btn btn-sm btn-outline-primary">
                                            View All Documents (<?= count($documents) ?>)
                                        </a>
                                    </div>
                                <?php endif; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <!-- Recent Messages -->
            <div class="col-lg-6 mb-4">
                <div class="card h-100">
                    <div class="card-header">
                        <h6 class="mb-0">
                            <i class="fas fa-envelope me-2"></i>Recent Messages
                        </h6>
                    </div>
                    <div class="card-body">
                        <?php if (empty($messages)): ?>
                            <div class="text-center py-4">
                                <i class="fas fa-envelope-open fa-3x text-muted mb-3"></i>
                                <p class="text-muted">No messages yet</p>
                                <a href="messages.php" class="btn btn-primary">Send Message</a>
                            </div>
                        <?php else: ?>
                            <div class="message-list">
                                <?php foreach (array_slice($messages, 0, 3) as $msg): ?>
                                    <div class="message-item <?= !$msg['is_read'] ? 'unread' : '' ?>">
                                        <div class="message-header">
                                            <span class="sender">
                                                <?= $msg['sender_id'] === $current_user['id'] ? 'You' : htmlspecialchars($msg['first_name'] . ' ' . $msg['last_name']) ?>
                                            </span>
                                            <span class="date"><?= formatDate($msg['created_at'], 'M j') ?></span>
                                        </div>
                                        <div class="message-subject">
                                            <?= htmlspecialchars($msg['subject']) ?>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                                
                                <div class="text-center mt-3">
                                    <a href="messages.php" class="btn btn-sm btn-outline-primary">
                                        View All Messages
                                    </a>
                                </div>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>

        <!-- Payment Information -->
        <?php if (!empty($payments)): ?>
        <div class="row">
            <div class="col-12">
                <div class="card">
                    <div class="card-header">
                        <h6 class="mb-0">
                            <i class="fas fa-credit-card me-2"></i>Payment History
                        </h6>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-hover">
                                <thead>
                                    <tr>
                                        <th>Date</th>
                                        <th>Type</th>
                                        <th>Amount</th>
                                        <th>Method</th>
                                        <th>Status</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach (array_slice($payments, 0, 5) as $payment): ?>
                                        <tr>
                                            <td><?= formatDate($payment['created_at']) ?></td>
                                            <td><?= ucfirst($payment['payment_type']) ?></td>
                                            <td>$<?= number_format($payment['amount'], 2) ?></td>
                                            <td><?= ucfirst(str_replace('_', ' ', $payment['payment_method'] ?? 'N/A')) ?></td>
                                            <td>
                                                <span class="status-badge status-<?= $payment['payment_status'] ?>">
                                                    <?= ucfirst($payment['payment_status']) ?>
                                                </span>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                        
                        <?php if (count($payments) > 5): ?>
                            <div class="text-center">
                                <a href="payments.php" class="btn btn-outline-primary">
                                    View All Payments
                                </a>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
        <?php endif; ?>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Auto-refresh dashboard every 5 minutes
        setTimeout(function() {
            location.reload();
        }, 300000);
        
        // Mark messages as read when viewed
        document.querySelectorAll('.message-item.unread').forEach(function(item) {
            item.addEventListener('click', function() {
                this.classList.remove('unread');
                // In production, send AJAX request to mark as read
            });
        });
    </script>
</body>
</html>
