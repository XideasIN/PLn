<?php
/**
 * Client Agreements - Digital Signature System
 * LoanFlow Personal Loan Management System
 */

require_once '../config/database.php';
require_once '../includes/functions.php';
require_once '../includes/auth.php';

// Require client login
requireLogin();

$current_user = getCurrentUser();
$application = getApplicationByUserId($current_user['id']);

// Handle signature submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['sign_agreement'])) {
    try {
        $db = getDB();
        
        $agreement_type = $_POST['agreement_type'];
        $signature_data = $_POST['signature_data'];
        $ip_address = $_SERVER['REMOTE_ADDR'];
        
        // Insert digital signature
        $stmt = $db->prepare("
            INSERT INTO digital_signatures (user_id, application_id, agreement_type, signature_data, ip_address, signed_at)
            VALUES (?, ?, ?, ?, ?, NOW())
        ");
        $stmt->execute([
            $current_user['id'],
            $application['id'] ?? null,
            $agreement_type,
            $signature_data,
            $ip_address
        ]);
        
        // Update application step if needed
        if ($application && $application['current_step'] < 3) {
            $stmt = $db->prepare("UPDATE loan_applications SET current_step = 3 WHERE id = ?");
            $stmt->execute([$application['id']]);
        }
        
        setFlashMessage('Agreement signed successfully!', 'success');
        header('Location: agreements.php');
        exit;
        
    } catch (Exception $e) {
        error_log("Agreement signing failed: " . $e->getMessage());
        setFlashMessage('Failed to sign agreement. Please try again.', 'error');
    }
}

// Get existing signatures
try {
    $db = getDB();
    $stmt = $db->prepare("SELECT * FROM digital_signatures WHERE user_id = ? ORDER BY signed_at DESC");
    $stmt->execute([$current_user['id']]);
    $signatures = $stmt->fetchAll();
} catch (Exception $e) {
    error_log("Signatures fetch failed: " . $e->getMessage());
    $signatures = [];
}

// Agreement templates
$agreements = [
    'loan_agreement' => [
        'title' => 'Personal Loan Agreement',
        'required' => true,
        'content' => 'This Personal Loan Agreement ("Agreement") is entered into between QuickFunds ("Lender") and the borrower ("Borrower"). By signing this agreement, the Borrower agrees to the terms and conditions outlined herein...'
    ],
    'privacy_policy' => [
        'title' => 'Privacy Policy Acknowledgment',
        'required' => true,
        'content' => 'I acknowledge that I have read and understand the Privacy Policy of QuickFunds and consent to the collection, use, and disclosure of my personal information as described therein...'
    ],
    'terms_conditions' => [
        'title' => 'Terms and Conditions',
        'required' => true,
        'content' => 'These Terms and Conditions govern the use of QuickFunds services. By using our services, you agree to be bound by these terms...'
    ]
];

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Agreements - QuickFunds</title>
    <link rel="stylesheet" href="../FrontEnd_Template/css/bootstrap.min.css">
    <link rel="stylesheet" href="../FrontEnd_Template/css/style.css">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="../assets/css/client.css" rel="stylesheet">
    <style>
        .signature-pad {
            border: 2px dashed #ddd;
            border-radius: 8px;
            cursor: crosshair;
            background: #fafafa;
        }
        .agreement-content {
            max-height: 300px;
            overflow-y: auto;
            border: 1px solid #ddd;
            padding: 15px;
            background: #f9f9f9;
            border-radius: 5px;
        }
        .signed-badge {
            background: #28a745;
            color: white;
            padding: 5px 10px;
            border-radius: 15px;
            font-size: 0.8rem;
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
                            <a class="nav-link active" href="agreements.php">
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

            <!-- Page Header -->
            <div class="row mb-4">
                <div class="col-12">
                    <div class="about-box">
                        <h3 class="service-title"><i class="fas fa-file-signature me-2"></i>Digital Agreements</h3>
                        <p class="works-subtext">Review and sign the required agreements to proceed with your loan application.</p>
                    </div>
                </div>
            </div>

            <!-- Agreements List -->
            <div class="row">
                <?php foreach ($agreements as $agreement_key => $agreement): ?>
                    <?php 
                    $is_signed = false;
                    foreach ($signatures as $signature) {
                        if ($signature['agreement_type'] === $agreement_key) {
                            $is_signed = true;
                            break;
                        }
                    }
                    ?>
                    <div class="col-lg-6 mb-4">
                        <div class="about-box">
                            <div class="d-flex justify-content-between align-items-center mb-3">
                                <h4 class="service-title mb-0"><?= $agreement['title'] ?></h4>
                                <?php if ($is_signed): ?>
                                    <span class="signed-badge">
                                        <i class="fas fa-check me-1"></i>Signed
                                    </span>
                                <?php endif; ?>
                            </div>
                            
                            <div class="agreement-content mb-3">
                                <?= nl2br(htmlspecialchars($agreement['content'])) ?>
                            </div>
                            
                            <?php if (!$is_signed): ?>
                                <form method="POST" class="signature-form">
                                    <input type="hidden" name="agreement_type" value="<?= $agreement_key ?>">
                                    <input type="hidden" name="signature_data" class="signature-data">
                                    
                                    <div class="mb-3">
                                        <label class="form-label">Digital Signature:</label>
                                        <canvas class="signature-pad form-control" width="400" height="150"></canvas>
                                        <small class="text-muted">Draw your signature above</small>
                                    </div>
                                    
                                    <div class="d-flex gap-2">
                                        <button type="button" class="btn btn-secondary clear-signature">Clear</button>
                                        <button type="submit" name="sign_agreement" class="btn btn-primary">
                                            <i class="fas fa-signature me-1"></i>Sign Agreement
                                        </button>
                                    </div>
                                </form>
                            <?php else: ?>
                                <div class="text-success">
                                    <i class="fas fa-check-circle me-1"></i>
                                    Signed on <?= date('M j, Y g:i A', strtotime($signature['signed_at'])) ?>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>

            <!-- Signed Agreements History -->
            <?php if (!empty($signatures)): ?>
            <div class="row mt-4">
                <div class="col-12">
                    <div class="about-box">
                        <h4 class="service-title"><i class="fas fa-history me-2"></i>Signature History</h4>
                        <div class="table-responsive">
                            <table class="table table-striped">
                                <thead>
                                    <tr>
                                        <th>Agreement</th>
                                        <th>Signed Date</th>
                                        <th>IP Address</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($signatures as $signature): ?>
                                    <tr>
                                        <td><?= ucfirst(str_replace('_', ' ', $signature['agreement_type'])) ?></td>
                                        <td><?= date('M j, Y g:i A', strtotime($signature['signed_at'])) ?></td>
                                        <td><?= htmlspecialchars($signature['ip_address']) ?></td>
                                        <td>
                                            <button class="btn btn-sm btn-outline-primary view-signature" 
                                                    data-signature="<?= htmlspecialchars($signature['signature_data']) ?>">
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
            <?php endif; ?>
        </div>
    </div>

    <!-- Signature View Modal -->
    <div class="modal fade" id="signatureModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Digital Signature</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body text-center">
                    <canvas id="signatureDisplay" width="400" height="150" style="border: 1px solid #ddd;"></canvas>
                </div>
            </div>
        </div>
    </div>

    <script src="../FrontEnd_Template/js/bootstrap.bundle.min.js"></script>
    <script>
        // Signature pad functionality
        document.addEventListener('DOMContentLoaded', function() {
            const signaturePads = document.querySelectorAll('.signature-pad');
            
            signaturePads.forEach(function(canvas) {
                const ctx = canvas.getContext('2d');
                let isDrawing = false;
                let lastX = 0;
                let lastY = 0;
                
                function startDrawing(e) {
                    isDrawing = true;
                    const rect = canvas.getBoundingClientRect();
                    lastX = e.clientX - rect.left;
                    lastY = e.clientY - rect.top;
                }
                
                function draw(e) {
                    if (!isDrawing) return;
                    
                    const rect = canvas.getBoundingClientRect();
                    const currentX = e.clientX - rect.left;
                    const currentY = e.clientY - rect.top;
                    
                    ctx.beginPath();
                    ctx.moveTo(lastX, lastY);
                    ctx.lineTo(currentX, currentY);
                    ctx.strokeStyle = '#000';
                    ctx.lineWidth = 2;
                    ctx.lineCap = 'round';
                    ctx.stroke();
                    
                    lastX = currentX;
                    lastY = currentY;
                    
                    // Update hidden input
                    const form = canvas.closest('form');
                    const signatureData = form.querySelector('.signature-data');
                    signatureData.value = canvas.toDataURL();
                }
                
                function stopDrawing() {
                    isDrawing = false;
                }
                
                canvas.addEventListener('mousedown', startDrawing);
                canvas.addEventListener('mousemove', draw);
                canvas.addEventListener('mouseup', stopDrawing);
                canvas.addEventListener('mouseout', stopDrawing);
                
                // Touch events for mobile
                canvas.addEventListener('touchstart', function(e) {
                    e.preventDefault();
                    const touch = e.touches[0];
                    const mouseEvent = new MouseEvent('mousedown', {
                        clientX: touch.clientX,
                        clientY: touch.clientY
                    });
                    canvas.dispatchEvent(mouseEvent);
                });
                
                canvas.addEventListener('touchmove', function(e) {
                    e.preventDefault();
                    const touch = e.touches[0];
                    const mouseEvent = new MouseEvent('mousemove', {
                        clientX: touch.clientX,
                        clientY: touch.clientY
                    });
                    canvas.dispatchEvent(mouseEvent);
                });
                
                canvas.addEventListener('touchend', function(e) {
                    e.preventDefault();
                    const mouseEvent = new MouseEvent('mouseup', {});
                    canvas.dispatchEvent(mouseEvent);
                });
            });
            
            // Clear signature functionality
            document.querySelectorAll('.clear-signature').forEach(function(button) {
                button.addEventListener('click', function() {
                    const form = button.closest('form');
                    const canvas = form.querySelector('.signature-pad');
                    const ctx = canvas.getContext('2d');
                    const signatureData = form.querySelector('.signature-data');
                    
                    ctx.clearRect(0, 0, canvas.width, canvas.height);
                    signatureData.value = '';
                });
            });
            
            // View signature functionality
            document.querySelectorAll('.view-signature').forEach(function(button) {
                button.addEventListener('click', function() {
                    const signatureData = button.getAttribute('data-signature');
                    const modal = new bootstrap.Modal(document.getElementById('signatureModal'));
                    const canvas = document.getElementById('signatureDisplay');
                    const ctx = canvas.getContext('2d');
                    
                    const img = new Image();
                    img.onload = function() {
                        ctx.clearRect(0, 0, canvas.width, canvas.height);
                        ctx.drawImage(img, 0, 0);
                    };
                    img.src = signatureData;
                    
                    modal.show();
                });
            });
            
            // Form validation
            document.querySelectorAll('.signature-form').forEach(function(form) {
                form.addEventListener('submit', function(e) {
                    const signatureData = form.querySelector('.signature-data').value;
                    if (!signatureData) {
                        e.preventDefault();
                        alert('Please provide your signature before submitting.');
                    }
                });
            });
        });
    </script>
</body>
</html>