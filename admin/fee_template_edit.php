<?php
/**
 * Fee Form Template Edit AJAX Endpoint
 * LoanFlow Personal Loan Management System
 * Provides edit form content for fee form templates
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

// Available countries and payment methods
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
?>

<input type="hidden" name="action" value="update_template">
<input type="hidden" name="template_id" value="<?= $template['id'] ?>">

<div class="row mb-3">
    <div class="col-md-6">
        <label class="form-label">Country</label>
        <input type="text" class="form-control" 
               value="<?= htmlspecialchars($countries[$template['country']] ?? $template['country']) ?> (<?= $template['country'] ?>)" 
               readonly>
        <div class="form-text">Country cannot be changed after creation</div>
    </div>
    <div class="col-md-6">
        <label class="form-label">Payment Method</label>
        <input type="text" class="form-control" 
               value="<?= htmlspecialchars($payment_methods[$template['payment_method']] ?? $template['payment_method']) ?>" 
               readonly>
        <div class="form-text">Payment method cannot be changed after creation</div>
    </div>
</div>

<div class="mb-3">
    <label class="form-label">Template Name <span class="text-danger">*</span></label>
    <input type="text" name="template_name" class="form-control" required 
           value="<?= htmlspecialchars($template['template_name']) ?>">
</div>

<div class="mb-3">
    <label class="form-label">Instructions <span class="text-danger">*</span></label>
    <textarea name="instructions" class="form-control" rows="4" required><?= htmlspecialchars($template['instructions']) ?></textarea>
    <div class="form-text">These instructions will be displayed to clients when they access the fee form.</div>
</div>

<div class="mb-3">
    <label class="form-label">Email Template</label>
    <textarea name="email_template" class="form-control" rows="6"><?= htmlspecialchars($template['email_template']) ?></textarea>
    <div class="form-text">
        Available placeholders: {user_name}, {application_id}, {amount}, {bank_name}, {account_number}, {routing_number}, {wallet_address}, {network}
    </div>
</div>

<div class="mb-3">
    <label class="form-label">Required Fields</label>
    <div class="row">
        <div class="col-md-4">
            <div class="form-check">
                <input class="form-check-input" type="checkbox" name="required_amount_sent" id="edit_required_amount_sent" 
                       <?= ($required_fields['amount_sent'] ?? false) ? 'checked' : '' ?>>
                <label class="form-check-label" for="edit_required_amount_sent">
                    Amount Sent
                </label>
            </div>
        </div>
        <div class="col-md-4">
            <div class="form-check">
                <input class="form-check-input" type="checkbox" name="required_date_sent" id="edit_required_date_sent" 
                       <?= ($required_fields['date_sent'] ?? false) ? 'checked' : '' ?>>
                <label class="form-check-label" for="edit_required_date_sent">
                    Date Sent
                </label>
            </div>
        </div>
        <div class="col-md-4">
            <div class="form-check">
                <input class="form-check-input" type="checkbox" name="required_transaction_reference" id="edit_required_transaction_reference" 
                       <?= ($required_fields['transaction_reference'] ?? false) ? 'checked' : '' ?>>
                <label class="form-check-label" for="edit_required_transaction_reference">
                    Transaction Reference
                </label>
            </div>
        </div>
    </div>
</div>

<div class="mb-3">
    <div class="form-check">
        <input class="form-check-input" type="checkbox" name="is_active" id="edit_is_active" 
               <?= $template['is_active'] ? 'checked' : '' ?>>
        <label class="form-check-label" for="edit_is_active">
            Template is active
        </label>
        <div class="form-text">Inactive templates will not be available for new fee forms</div>
    </div>
</div>

<div class="row">
    <div class="col-md-6">
        <div class="card bg-light">
            <div class="card-body">
                <h6 class="card-title">Template Statistics</h6>
                <?php
                // Get usage statistics
                $stats_stmt = $pdo->prepare("
                    SELECT 
                        COUNT(*) as total_forms,
                        COUNT(CASE WHEN status = 'pending' THEN 1 END) as pending_forms,
                        COUNT(CASE WHEN status = 'approved' THEN 1 END) as approved_forms,
                        COUNT(CASE WHEN status = 'rejected' THEN 1 END) as rejected_forms
                    FROM fee_sent_forms 
                    WHERE country = ? AND payment_method = ?
                ");
                $stats_stmt->execute([$template['country'], $template['payment_method']]);
                $stats = $stats_stmt->fetch();
                ?>
                <div class="row text-center">
                    <div class="col-6">
                        <div class="h5 mb-0"><?= number_format($stats['total_forms']) ?></div>
                        <small class="text-muted">Total Forms</small>
                    </div>
                    <div class="col-6">
                        <div class="h5 mb-0"><?= number_format($stats['pending_forms']) ?></div>
                        <small class="text-muted">Pending</small>
                    </div>
                </div>
                <div class="row text-center mt-2">
                    <div class="col-6">
                        <div class="h5 mb-0 text-success"><?= number_format($stats['approved_forms']) ?></div>
                        <small class="text-muted">Approved</small>
                    </div>
                    <div class="col-6">
                        <div class="h5 mb-0 text-danger"><?= number_format($stats['rejected_forms']) ?></div>
                        <small class="text-muted">Rejected</small>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-md-6">
        <div class="card bg-light">
            <div class="card-body">
                <h6 class="card-title">Template Info</h6>
                <div class="small">
                    <div class="mb-1">
                        <strong>Created:</strong> <?= date('M j, Y g:i A', strtotime($template['created_at'])) ?>
                    </div>
                    <?php if ($template['updated_at']): ?>
                        <div class="mb-1">
                            <strong>Last Updated:</strong> <?= date('M j, Y g:i A', strtotime($template['updated_at'])) ?>
                        </div>
                    <?php endif; ?>
                    <div class="mb-1">
                        <strong>Status:</strong> 
                        <span class="badge <?= $template['is_active'] ? 'bg-success' : 'bg-secondary' ?>">
                            <?= $template['is_active'] ? 'Active' : 'Inactive' ?>
                        </span>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php if ($stats['total_forms'] > 0): ?>
    <div class="alert alert-info mt-3">
        <i class="fas fa-info-circle"></i>
        <strong>Note:</strong> This template is currently being used by <?= number_format($stats['total_forms']) ?> fee form(s). 
        Changes will affect future form submissions but not existing ones.
    </div>
<?php endif; ?>