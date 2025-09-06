<?php
/**
 * Funding Management - Client Area
 * LoanFlow Personal Loan Management System
 */

require_once '../config/database.php';
require_once '../includes/functions.php';
require_once '../includes/auth.php';

// Require client login
requireLogin();

$current_user = getCurrentUser();
$application = getApplicationByUserId($current_user['id']);

// Get funding details
try {
    $db = getDB();
    
    // Get funding information
    $stmt = $db->prepare("
        SELECT la.*, 
               bd.bank_name, bd.account_holder_name, bd.account_number, bd.account_type,
               p.amount as fee_amount, p.payment_status as fee_status, p.payment_date as fee_paid_date
        FROM loan_applications la
        LEFT JOIN bank_details bd ON la.user_id = bd.user_id AND bd.verified = 1
        LEFT JOIN payments p ON la.id = p.application_id AND p.payment_status = 'completed'
        WHERE la.user_id = ?
        ORDER BY bd.created_at DESC, p.created_at DESC
        LIMIT 1
    ");
    $stmt->execute([$current_user['id']]);
    $funding_info = $stmt->fetch();
    
    // Get funding timeline/history
    $stmt = $db->prepare("
        SELECT 'application' as event_type, 'Application Submitted' as event_title, 
               created_at as event_date, 'Application created and submitted' as event_description
        FROM loan_applications WHERE user_id = ?
        UNION ALL
        SELECT 'approval' as event_type, 'Application Approved' as event_title,
               approval_date as event_date, 'Loan application approved for funding' as event_description
        FROM loan_applications WHERE user_id = ? AND approval_date IS NOT NULL
        UNION ALL
        SELECT 'funding_initiated' as event_type, 'Funding Initiated' as event_title,
               funding_initiated_at as event_date, 'Funding process started' as event_description
        FROM loan_applications WHERE user_id = ? AND funding_initiated_at IS NOT NULL
        UNION ALL
        SELECT 'funding_completed' as event_type, 'Funds Disbursed' as event_title,
               funding_date as event_date, 'Funds successfully transferred to your account' as event_description
        FROM loan_applications WHERE user_id = ? AND funding_date IS NOT NULL
        ORDER BY event_date DESC
    ");
    $stmt->execute([$current_user['id'], $current_user['id'], $current_user['id'], $current_user['id']]);
    $funding_timeline = $stmt->fetchAll();
    
    // Get funding documents
    $stmt = $db->prepare("
        SELECT * FROM documents 
        WHERE user_id = ? AND document_type IN ('bank_statement', 'other')
        AND (original_filename LIKE '%funding%' OR original_filename LIKE '%disbursement%' OR verification_notes LIKE '%funding%')
        ORDER BY created_at DESC
    ");
    $stmt->execute([$current_user['id']]);
    $funding_documents = $stmt->fetchAll();
    
    // Get funding notifications/messages
    $stmt = $db->prepare("
        SELECT cm.*, u.first_name, u.last_name 
        FROM client_messages cm
        JOIN users u ON cm.sender_id = u.id
        WHERE cm.recipient_id = ? 
        AND (cm.subject LIKE '%funding%' OR cm.subject LIKE '%disbursement%' OR cm.message LIKE '%funding%')
        ORDER BY cm.created_at DESC
        LIMIT 10
    ");
    $stmt->execute([$current_user['id']]);
    $funding_messages = $stmt->fetchAll();
    
} catch (Exception $e) {
    error_log("Funding data fetch failed: " . $e->getMessage());
    $funding_info = null;
    $funding_timeline = [];
    $funding_documents = [];
    $funding_messages = [];
}

// Calculate funding status and progress
$funding_status = 'not_eligible';
$funding_progress = 0;
$estimated_funding_date = null;

if ($application) {
    switch ($application['application_status']) {
        case 'approved':
            $funding_status = 'approved_pending_funding';
            $funding_progress = 25;
            $estimated_funding_date = date('Y-m-d', strtotime('+2 business days'));
            break;
        case 'funding':
            $funding_status = 'funding_in_progress';
            $funding_progress = 75;
            $estimated_funding_date = date('Y-m-d', strtotime('+1 business day'));
            break;
        case 'funded':
            $funding_status = 'funded';
            $funding_progress = 100;
            break;
        default:
            $funding_status = 'not_eligible';
            $funding_progress = 0;
    }
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Funding Management - QuickFunds</title>
    <link rel="stylesheet" href="../FrontEnd_Template/css/bootstrap.min.css">
    <link rel="stylesheet" href="../FrontEnd_Template/css/style.css">
    <link rel="stylesheet" href="../FrontEnd_Template/animation/aos.css">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="../assets/css/client.css" rel="stylesheet">
    <style>
        .funding-status-card {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border-radius: 15px;
            padding: 30px;
            margin-bottom: 30px;
        }
        .funding-progress {
            background: rgba(255,255,255,0.2);
            border-radius: 10px;
            padding: 20px;
            margin-top: 20px;
        }
        .timeline-item {
            border-left: 3px solid #e9ecef;
            padding-left: 20px;
            margin-bottom: 20px;
            position: relative;
        }
        .timeline-item::before {
            content: '';
            position: absolute;
            left: -8px;
            top: 0;
            width: 13px;
            height: 13px;
            border-radius: 50%;
            background: #6c757d;
        }
        .timeline-item.completed::before {
            background: #28a745;
        }
        .timeline-item.active::before {
            background: #007bff;
            animation: pulse 2s infinite;
        }
        @keyframes pulse {
            0% { box-shadow: 0 0 0 0 rgba(0, 123, 255, 0.7); }
            70% { box-shadow: 0 0 0 10px rgba(0, 123, 255, 0); }
            100% { box-shadow: 0 0 0 0 rgba(0, 123, 255, 0); }
        }
        .funding-amount {
            font-size: 2.5rem;
            font-weight: bold;
            text-shadow: 2px 2px 4px rgba(0,0,0,0.3);
        }
        .status-badge {
            padding: 8px 16px;
            border-radius: 20px;
            font-weight: 600;
            text-transform: uppercase;
            font-size: 0.8rem;
        }
        .status-approved_pending_funding {
            background: #fff3cd;
            color: #856404;
        }
        .status-funding_in_progress {
            background: #d1ecf1;
            color: #0c5460;
        }
        .status-funded {
            background: #d4edda;
            color: #155724;
        }
        .status-not_eligible {
            background: #f8d7da;
            color: #721c24;
        }
        .bank-details-card {
            background: #f8f9fa;
            border: 1px solid #dee2e6;
            border-radius: 10px;
            padding: 20px;
        }
        .document-item {
            background: white;
            border: 1px solid #dee2e6;
            border-radius: 8px;
            padding: 15px;
            margin-bottom: 10px;
            transition: all 0.3s ease;
        }
        .document-item:hover {
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            transform: translateY(-2px);
        }
    </style>
</head>
<body>
  <div class="main-wrapper">
    <!-- Client Header -->
    <nav class="navbar navbar-expand-lg fixed-top">
        <div class="container">
            <a class="navbar-brand" href="dashboard.php">
                <img src="../FrontEnd_Template/images/logo.png" alt="QuickFunds" class="logo">
            </a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav me-auto">
                    <li class="nav-item">
                        <a class="nav-link" href="dashboard.php">
                            <i class="fas fa-tachometer-alt me-1"></i>Dashboard
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="documents.php">
                            <i class="fas fa-folder me-1"></i>Documents
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="agreements.php">
                            <i class="fas fa-file-signature me-1"></i>Agreements
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="banking.php">
                            <i class="fas fa-university me-1"></i>Banking
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="payments.php">
                            <i class="fas fa-credit-card me-1"></i>Payments
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link active" href="funding.php">
                            <i class="fas fa-money-check-alt me-1"></i>Funding
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="messages.php">
                            <i class="fas fa-envelope me-1"></i>Messages
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
                            <li><a class="dropdown-item" href="calculator.php">
                                <i class="fas fa-calculator me-2"></i>Loan Calculator
                            </a></li>
                            <li><hr class="dropdown-divider"></li>
                            <li><a class="dropdown-item" href="../logout.php">
                                <i class="fas fa-sign-out-alt me-2"></i>Logout
                            </a></li>
                        </ul>
                    </li>
                </ul>
            </div>
        </div>
    </nav>

    <div class="container-fluid" style="padding-top: 100px;">
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

        <!-- Funding Status Card -->
        <div class="row mb-4">
            <div class="col-12">
                <div class="funding-status-card">
                    <div class="row align-items-center">
                        <div class="col-md-8">
                            <h2 class="mb-3">
                                <i class="fas fa-money-check-alt me-3"></i>
                                Funding Management
                            </h2>
                            <?php if ($application): ?>
                                <div class="funding-amount mb-2">
                                    <?= formatCurrency($application['loan_amount'], $current_user['country']) ?>
                                </div>
                                <p class="mb-2">
                                    <i class="fas fa-info-circle me-2"></i>
                                    Reference: <strong><?= htmlspecialchars($current_user['reference_number']) ?></strong>
                                </p>
                                <div class="d-flex align-items-center">
                                    <span class="status-badge status-<?= $funding_status ?>">
                                        <?= ucfirst(str_replace('_', ' ', $funding_status)) ?>
                                    </span>
                                    <?php if ($estimated_funding_date && $funding_status !== 'funded'): ?>
                                        <span class="ms-3">
                                            <i class="fas fa-calendar me-1"></i>
                                            Est. Funding: <?= date('M j, Y', strtotime($estimated_funding_date)) ?>
                                        </span>
                                    <?php endif; ?>
                                </div>
                            <?php else: ?>
                                <p class="mb-0">No active loan application found.</p>
                            <?php endif; ?>
                        </div>
                        <div class="col-md-4">
                            <div class="funding-progress">
                                <div class="text-center mb-3">
                                    <div class="h3 mb-0"><?= $funding_progress ?>%</div>
                                    <small>Funding Progress</small>
                                </div>
                                <div class="progress" style="height: 8px;">
                                    <div class="progress-bar bg-light" role="progressbar" 
                                         style="width: <?= $funding_progress ?>%" 
                                         aria-valuenow="<?= $funding_progress ?>" 
                                         aria-valuemin="0" aria-valuemax="100">
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="row">
            <!-- Funding Timeline -->
            <div class="col-lg-8 mb-4">
                <div class="about-box">
                    <div class="mb-4">
                        <h4 class="service-title">
                            <i class="fas fa-timeline me-2"></i>Funding Timeline
                        </h4>
                    </div>
                    
                    <?php if (!empty($funding_timeline)): ?>
                        <div class="timeline">
                            <?php foreach ($funding_timeline as $event): ?>
                                <div class="timeline-item <?= $event['event_date'] ? 'completed' : '' ?>">
                                    <div class="d-flex justify-content-between align-items-start">
                                        <div>
                                            <h6 class="mb-1"><?= htmlspecialchars($event['event_title']) ?></h6>
                                            <p class="text-muted mb-1"><?= htmlspecialchars($event['event_description']) ?></p>
                                            <?php if ($event['event_date']): ?>
                                                <small class="text-success">
                                                    <i class="fas fa-check me-1"></i>
                                                    <?= formatDateTime($event['event_date']) ?>
                                                </small>
                                            <?php endif; ?>
                                        </div>
                                        <div class="text-end">
                                            <?php if ($event['event_date']): ?>
                                                <i class="fas fa-check-circle text-success"></i>
                                            <?php else: ?>
                                                <i class="fas fa-clock text-muted"></i>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php else: ?>
                        <div class="text-center py-4">
                            <i class="fas fa-info-circle fa-3x text-muted mb-3"></i>
                            <h5 class="text-muted">No Funding Activity Yet</h5>
                            <p class="text-muted">Your funding timeline will appear here once your application is approved.</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Bank Details & Quick Info -->
            <div class="col-lg-4 mb-4">
                <!-- Bank Details -->
                <div class="about-box mb-4">
                    <div class="mb-3">
                        <h5 class="service-title">
                            <i class="fas fa-university me-2"></i>Funding Destination
                        </h5>
                    </div>
                    
                    <?php if ($funding_info && $funding_info['bank_name']): ?>
                        <div class="bank-details-card">
                            <div class="mb-2">
                                <strong><?= htmlspecialchars($funding_info['bank_name']) ?></strong>
                            </div>
                            <div class="mb-2">
                                <small class="text-muted">Account Holder:</small><br>
                                <?= htmlspecialchars($funding_info['account_holder_name']) ?>
                            </div>
                            <div class="mb-2">
                                <small class="text-muted">Account Number:</small><br>
                                ****<?= substr($funding_info['account_number'], -4) ?>
                            </div>
                            <div>
                                <small class="text-muted">Account Type:</small><br>
                                <?= ucfirst($funding_info['account_type']) ?>
                            </div>
                        </div>
                    <?php else: ?>
                        <div class="text-center py-3">
                            <i class="fas fa-exclamation-triangle text-warning mb-2"></i>
                            <p class="text-muted mb-2">No bank details on file</p>
                            <a href="banking.php" class="btn btn-primary btn-sm">
                                <i class="fas fa-plus me-1"></i>Add Bank Details
                            </a>
                        </div>
                    <?php endif; ?>
                </div>

                <!-- Quick Actions -->
                <div class="about-box">
                    <div class="mb-3">
                        <h5 class="service-title">
                            <i class="fas fa-bolt me-2"></i>Quick Actions
                        </h5>
                    </div>
                    
                    <div class="d-grid gap-2">
                        <?php if ($funding_status === 'not_eligible'): ?>
                            <a href="dashboard.php" class="btn btn-primary">
                                <i class="fas fa-arrow-left me-2"></i>Complete Application
                            </a>
                        <?php elseif ($funding_status === 'approved_pending_funding'): ?>
                            <button class="btn btn-info" disabled>
                                <i class="fas fa-clock me-2"></i>Awaiting Funding
                            </button>
                        <?php elseif ($funding_status === 'funding_in_progress'): ?>
                            <button class="btn btn-warning" disabled>
                                <i class="fas fa-spinner fa-spin me-2"></i>Processing...
                            </button>
                        <?php else: ?>
                            <a href="messages.php" class="btn btn-success">
                                <i class="fas fa-envelope me-2"></i>Contact Support
                            </a>
                        <?php endif; ?>
                        
                        <a href="banking.php" class="btn btn-outline-primary">
                            <i class="fas fa-university me-2"></i>Update Bank Details
                        </a>
                        
                        <a href="documents.php" class="btn btn-outline-secondary">
                            <i class="fas fa-folder me-2"></i>View Documents
                        </a>
                    </div>
                </div>
            </div>
        </div>

        <!-- Funding Documents & Messages -->
        <div class="row">
            <!-- Funding Documents -->
            <div class="col-md-6 mb-4">
                <div class="about-box">
                    <div class="mb-3">
                        <h5 class="service-title">
                            <i class="fas fa-file-alt me-2"></i>Funding Documents
                        </h5>
                    </div>
                    
                    <?php if (!empty($funding_documents)): ?>
                        <?php foreach ($funding_documents as $doc): ?>
                            <div class="document-item">
                                <div class="d-flex justify-content-between align-items-center">
                                    <div>
                                        <h6 class="mb-1">
                                            <i class="fas fa-file me-2"></i>
                                            <?= htmlspecialchars($doc['original_filename']) ?>
                                        </h6>
                                        <small class="text-muted">
                                            <?= formatDateTime($doc['created_at']) ?>
                                        </small>
                                    </div>
                                    <span class="status-badge status-<?= $doc['upload_status'] ?>">
                                        <?= ucfirst($doc['upload_status']) ?>
                                    </span>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <div class="text-center py-4">
                            <i class="fas fa-folder-open fa-2x text-muted mb-3"></i>
                            <p class="text-muted">No funding-related documents yet.</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Funding Messages -->
            <div class="col-md-6 mb-4">
                <div class="about-box">
                    <div class="mb-3">
                        <h5 class="service-title">
                            <i class="fas fa-comments me-2"></i>Funding Updates
                        </h5>
                    </div>
                    
                    <?php if (!empty($funding_messages)): ?>
                        <?php foreach ($funding_messages as $message): ?>
                            <div class="document-item">
                                <div class="mb-2">
                                    <h6 class="mb-1"><?= htmlspecialchars($message['subject']) ?></h6>
                                    <small class="text-muted">
                                        From: <?= htmlspecialchars($message['first_name'] . ' ' . $message['last_name']) ?> • 
                                        <?= formatDateTime($message['created_at']) ?>
                                    </small>
                                </div>
                                <p class="mb-0 text-muted">
                                    <?= htmlspecialchars(substr($message['message'], 0, 100)) ?>
                                    <?= strlen($message['message']) > 100 ? '...' : '' ?>
                                </p>
                            </div>
                        <?php endforeach; ?>
                        
                        <div class="text-center mt-3">
                            <a href="messages.php" class="btn btn-outline-primary btn-sm">
                                <i class="fas fa-envelope me-1"></i>View All Messages
                            </a>
                        </div>
                    <?php else: ?>
                        <div class="text-center py-4">
                            <i class="fas fa-inbox fa-2x text-muted mb-3"></i>
                            <p class="text-muted">No funding updates yet.</p>
                            <a href="messages.php" class="btn btn-outline-primary btn-sm">
                                <i class="fas fa-plus me-1"></i>Send Message
                            </a>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
  </div>

  <!-- Scripts -->
  <script src="../FrontEnd_Template/js/bootstrap.bundle.min.js"></script>
  <script src="../FrontEnd_Template/animation/aos.js"></script>
  <script>
    // Initialize AOS
    AOS.init();
    
    // Auto-refresh funding status every 30 seconds if funding is in progress
    <?php if ($funding_status === 'funding_in_progress'): ?>
    setInterval(function() {
        location.reload();
    }, 30000);
    <?php endif; ?>
  </script>
</body>
</html>