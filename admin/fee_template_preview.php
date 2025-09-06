<?php
/**
 * Fee Form Template Preview AJAX Endpoint
 * LoanFlow Personal Loan Management System
 * Provides preview of how templates will appear to clients
 */

require_once '../includes/config.php';
require_once '../includes/auth.php';

// Check if user is admin
if (!isAdmin()) {
    http_response_code(403);
    exit('Access denied');
}

$template_id = (int)($_GET['id'] ?? 0);

if (!$template_id) {
    http_response_code(400);
    exit('Invalid template ID');
}

// Get template details
$stmt = $pdo->prepare("
    SELECT * FROM fee_form_templates 
    WHERE id = ?
");
$stmt->execute([$template_id]);
$template = $stmt->fetch();

if (!$template) {
    http_response_code(404);
    exit('Template not found');
}

// Available countries and payment methods for display
$countries = [
    'US' => 'United States',
    'CA' => 'Canada',
    'AU' => 'Australia',
    'GB' => 'United Kingdom',
    'DE' => 'Germany',
    'FR' => 'France',
    'IT' => 'Italy',
    'ES' => 'Spain',
    'NL' => 'Netherlands',
    'BE' => 'Belgium',
    'CH' => 'Switzerland',
    'AT' => 'Austria',
    'SE' => 'Sweden',
    'NO' => 'Norway',
    'DK' => 'Denmark',
    'FI' => 'Finland'
];

$payment_methods = [
    'wire_transfer' => 'Wire Transfer',
    'crypto' => 'Cryptocurrency',
    'e_transfer' => 'e-Transfer',
    'credit_card' => 'Credit Card'
];

$required_fields = json_decode($template['required_fields'], true) ?? [];

// Sample data for preview
$sample_data = [
    'user_name' => 'John Doe',
    'application_id' => 'LA-2024-001',
    'amount' => '$2,500.00',
    'bank_name' => 'Sample Bank',
    'account_number' => '1234567890',
    'routing_number' => '021000021',
    'wallet_address' => '1A1zP1eP5QGefi2DMPTfTL5SLmv7DivfNa',
    'network' => 'Bitcoin'
];

// Process email template with sample data
$email_preview = $template['email_template'];
foreach ($sample_data as $key => $value) {
    $email_preview = str_replace('{' . $key . '}', $value, $email_preview);
}
?>

<div class="row">
    <div class="col-md-8">
        <!-- Client Form Preview -->
        <div class="card">
            <div class="card-header bg-primary text-white">
                <h5 class="mb-0">
                    <i class="fas fa-file-invoice-dollar"></i>
                    Fee Payment Form - <?= htmlspecialchars($countries[$template['country']] ?? $template['country']) ?>
                </h5>
                <small>Payment Method: <?= htmlspecialchars($payment_methods[$template['payment_method']] ?? $template['payment_method']) ?></small>
            </div>
            <div class="card-body">
                <!-- Instructions Section -->
                <div class="alert alert-info">
                    <h6><i class="fas fa-info-circle"></i> Payment Instructions</h6>
                    <div class="instructions-content">
                        <?= nl2br(htmlspecialchars($template['instructions'])) ?>
                    </div>
                </div>

                <!-- Sample Form Fields -->
                <form class="preview-form">
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label class="form-label">Application ID</label>
                            <input type="text" class="form-control" value="LA-2024-001" readonly>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Client Name</label>
                            <input type="text" class="form-control" value="John Doe" readonly>
                        </div>
                    </div>

                    <?php if ($required_fields['amount_sent'] ?? false): ?>
                    <div class="mb-3">
                        <label class="form-label">Amount Sent <span class="text-danger">*</span></label>
                        <div class="input-group">
                            <span class="input-group-text">$</span>
                            <input type="number" class="form-control" placeholder="0.00" step="0.01" required>
                        </div>
                    </div>
                    <?php endif; ?>

                    <?php if ($required_fields['date_sent'] ?? false): ?>
                    <div class="mb-3">
                        <label class="form-label">Date Sent <span class="text-danger">*</span></label>
                        <input type="date" class="form-control" required>
                    </div>
                    <?php endif; ?>

                    <?php if ($required_fields['transaction_reference'] ?? false): ?>
                    <div class="mb-3">
                        <label class="form-label">Transaction Reference <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" placeholder="Enter transaction ID or reference number" required>
                    </div>
                    <?php endif; ?>

                    <div class="mb-3">
                        <label class="form-label">Payment Receipt/Proof</label>
                        <input type="file" class="form-control" accept=".pdf,.jpg,.jpeg,.png">
                        <div class="form-text">Upload a screenshot or receipt of your payment (PDF, JPG, PNG)</div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Additional Notes</label>
                        <textarea class="form-control" rows="3" placeholder="Any additional information about your payment..."></textarea>
                    </div>

                    <div class="d-grid">
                        <button type="button" class="btn btn-primary btn-lg" disabled>
                            <i class="fas fa-paper-plane"></i> Submit Fee Payment Form
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    
    <div class="col-md-4">
        <!-- Template Details -->
        <div class="card mb-3">
            <div class="card-header">
                <h6 class="mb-0">Template Details</h6>
            </div>
            <div class="card-body">
                <div class="mb-2">
                    <strong>Name:</strong><br>
                    <span class="text-muted"><?= htmlspecialchars($template['template_name']) ?></span>
                </div>
                <div class="mb-2">
                    <strong>Country:</strong><br>
                    <span class="text-muted"><?= htmlspecialchars($countries[$template['country']] ?? $template['country']) ?> (<?= $template['country'] ?>)</span>
                </div>
                <div class="mb-2">
                    <strong>Payment Method:</strong><br>
                    <span class="text-muted"><?= htmlspecialchars($payment_methods[$template['payment_method']] ?? $template['payment_method']) ?></span>
                </div>
                <div class="mb-2">
                    <strong>Status:</strong><br>
                    <span class="badge <?= $template['is_active'] ? 'bg-success' : 'bg-secondary' ?>">
                        <?= $template['is_active'] ? 'Active' : 'Inactive' ?>
                    </span>
                </div>
                <div class="mb-2">
                    <strong>Required Fields:</strong><br>
                    <?php foreach ($required_fields as $field => $required): ?>
                        <?php if ($required): ?>
                            <span class="badge bg-primary me-1 mb-1"><?= ucfirst(str_replace('_', ' ', $field)) ?></span>
                        <?php endif; ?>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>

        <!-- Email Template Preview -->
        <?php if (!empty($template['email_template'])): ?>
        <div class="card">
            <div class="card-header">
                <h6 class="mb-0">Email Template Preview</h6>
            </div>
            <div class="card-body">
                <div class="email-preview p-3 border rounded bg-light">
                    <div class="mb-2">
                        <strong>Subject:</strong> Fee Payment Instructions - Application <?= $sample_data['application_id'] ?>
                    </div>
                    <hr>
                    <div class="email-content" style="white-space: pre-line; font-family: Arial, sans-serif; line-height: 1.5;"><?= htmlspecialchars($email_preview) ?></div>
                </div>
                <div class="mt-2">
                    <small class="text-muted">
                        <i class="fas fa-info-circle"></i>
                        This preview uses sample data. Actual emails will contain real client information.
                    </small>
                </div>
            </div>
        </div>
        <?php endif; ?>

        <!-- Usage Statistics -->
        <div class="card mt-3">
            <div class="card-header">
                <h6 class="mb-0">Usage Statistics</h6>
            </div>
            <div class="card-body">
                <?php
                // Get usage statistics
                $stats_stmt = $pdo->prepare("
                    SELECT 
                        COUNT(*) as total_forms,
                        COUNT(CASE WHEN status = 'pending' THEN 1 END) as pending_forms,
                        COUNT(CASE WHEN status = 'approved' THEN 1 END) as approved_forms,
                        COUNT(CASE WHEN status = 'rejected' THEN 1 END) as rejected_forms,
                        AVG(CASE WHEN status = 'approved' THEN TIMESTAMPDIFF(HOUR, created_at, updated_at) END) as avg_approval_hours
                    FROM fee_sent_forms 
                    WHERE country = ? AND payment_method = ?
                ");
                $stats_stmt->execute([$template['country'], $template['payment_method']]);
                $stats = $stats_stmt->fetch();
                ?>
                
                <div class="row text-center mb-3">
                    <div class="col-6">
                        <div class="h4 mb-0"><?= number_format($stats['total_forms']) ?></div>
                        <small class="text-muted">Total Submissions</small>
                    </div>
                    <div class="col-6">
                        <div class="h4 mb-0"><?= number_format($stats['pending_forms']) ?></div>
                        <small class="text-muted">Pending Review</small>
                    </div>
                </div>
                
                <div class="row text-center mb-3">
                    <div class="col-6">
                        <div class="h4 mb-0 text-success"><?= number_format($stats['approved_forms']) ?></div>
                        <small class="text-muted">Approved</small>
                    </div>
                    <div class="col-6">
                        <div class="h4 mb-0 text-danger"><?= number_format($stats['rejected_forms']) ?></div>
                        <small class="text-muted">Rejected</small>
                    </div>
                </div>
                
                <?php if ($stats['avg_approval_hours']): ?>
                <div class="text-center">
                    <div class="h5 mb-0"><?= number_format($stats['avg_approval_hours'], 1) ?>h</div>
                    <small class="text-muted">Avg. Approval Time</small>
                </div>
                <?php endif; ?>
                
                <?php if ($stats['total_forms'] > 0): ?>
                <div class="mt-3">
                    <div class="progress" style="height: 8px;">
                        <?php 
                        $approved_pct = ($stats['approved_forms'] / $stats['total_forms']) * 100;
                        $rejected_pct = ($stats['rejected_forms'] / $stats['total_forms']) * 100;
                        $pending_pct = ($stats['pending_forms'] / $stats['total_forms']) * 100;
                        ?>
                        <div class="progress-bar bg-success" style="width: <?= $approved_pct ?>%" title="<?= number_format($approved_pct, 1) ?>% Approved"></div>
                        <div class="progress-bar bg-danger" style="width: <?= $rejected_pct ?>%" title="<?= number_format($rejected_pct, 1) ?>% Rejected"></div>
                        <div class="progress-bar bg-warning" style="width: <?= $pending_pct ?>%" title="<?= number_format($pending_pct, 1) ?>% Pending"></div>
                    </div>
                    <div class="d-flex justify-content-between mt-1">
                        <small class="text-success"><?= number_format($approved_pct, 1) ?>% Approved</small>
                        <small class="text-danger"><?= number_format($rejected_pct, 1) ?>% Rejected</small>
                    </div>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<style>
.preview-form {
    background: #f8f9fa;
    padding: 20px;
    border-radius: 8px;
    border: 2px dashed #dee2e6;
}

.preview-form .form-control:disabled,
.preview-form .form-control[readonly] {
    background-color: #e9ecef;
    opacity: 1;
}

.instructions-content {
    background: white;
    padding: 15px;
    border-radius: 5px;
    border-left: 4px solid #0d6efd;
}

.email-preview {
    max-height: 300px;
    overflow-y: auto;
    font-size: 14px;
}

.progress {
    border-radius: 10px;
    overflow: hidden;
}

.card-header h6 {
    color: #495057;
    font-weight: 600;
}
</style>