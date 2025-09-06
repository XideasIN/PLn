<?php
/**
 * Fee Form Details AJAX Endpoint
 * LoanFlow Personal Loan Management System
 * Returns detailed information about a specific fee sent form
 */

require_once '../includes/config.php';
require_once '../includes/auth.php';
require_once '../includes/functions.php';

// Check if user is admin
if (!isAdmin()) {
    http_response_code(403);
    exit('Access denied');
}

// Get form ID
$form_id = (int)($_GET['id'] ?? 0);

if (!$form_id) {
    http_response_code(400);
    exit('Invalid form ID');
}

// Get fee form details with related information
$stmt = $pdo->prepare("
    SELECT 
        fsf.*,
        u.first_name, u.last_name, u.email, u.phone, u.address, u.city, u.state, u.postal_code,
        la.loan_amount, la.loan_purpose, la.status as application_status, la.created_at as application_date,
        la.employment_status, la.monthly_income, la.credit_score,
        reviewer.first_name as reviewer_first_name, reviewer.last_name as reviewer_last_name,
        reviewer.email as reviewer_email
    FROM fee_sent_forms fsf
    JOIN users u ON fsf.user_id = u.id
    JOIN loan_applications la ON fsf.application_id = la.id
    LEFT JOIN users reviewer ON fsf.reviewed_by = reviewer.id
    WHERE fsf.id = ?
");
$stmt->execute([$form_id]);
$form = $stmt->fetch();

if (!$form) {
    http_response_code(404);
    exit('Fee form not found');
}

// Get form template information
$template_stmt = $pdo->prepare("
    SELECT template_name, instructions, required_fields 
    FROM fee_form_templates 
    WHERE country = ? AND payment_method = ? AND is_active = 1
");
$template_stmt->execute([$form['country'], $form['payment_method']]);
$template = $template_stmt->fetch();

// Get notification history
$notifications_stmt = $pdo->prepare("
    SELECT notification_type, subject, sent_at, status, error_message
    FROM fee_form_notifications 
    WHERE fee_form_id = ? 
    ORDER BY created_at DESC
");
$notifications_stmt->execute([$form_id]);
$notifications = $notifications_stmt->fetchAll();

// Get payment method display name
$payment_methods = [
    'wire_transfer' => 'Wire Transfer',
    'crypto' => 'Cryptocurrency',
    'e_transfer' => 'e-Transfer',
    'credit_card' => 'Credit Card'
];

$payment_method_name = $payment_methods[$form['payment_method']] ?? ucfirst($form['payment_method']);

// Get status badge class
$status_classes = [
    'pending' => 'bg-warning',
    'confirmed' => 'bg-success',
    'rejected' => 'bg-danger',
    'under_review' => 'bg-info'
];
$status_class = $status_classes[$form['status']] ?? 'bg-secondary';

// Get country name
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
$country_name = $countries[$form['country']] ?? $form['country'];
?>

<div class="row">
    <!-- Client Information -->
    <div class="col-md-6">
        <div class="card mb-3">
            <div class="card-header">
                <h6 class="mb-0"><i class="fas fa-user"></i> Client Information</h6>
            </div>
            <div class="card-body">
                <table class="table table-sm table-borderless">
                    <tr>
                        <td class="text-muted" width="40%">Name:</td>
                        <td><strong><?= htmlspecialchars($form['first_name'] . ' ' . $form['last_name']) ?></strong></td>
                    </tr>
                    <tr>
                        <td class="text-muted">Email:</td>
                        <td>
                            <a href="mailto:<?= htmlspecialchars($form['email']) ?>"><?= htmlspecialchars($form['email']) ?></a>
                        </td>
                    </tr>
                    <tr>
                        <td class="text-muted">Phone:</td>
                        <td>
                            <?php if ($form['phone']): ?>
                                <a href="tel:<?= htmlspecialchars($form['phone']) ?>"><?= htmlspecialchars($form['phone']) ?></a>
                            <?php else: ?>
                                <span class="text-muted">Not provided</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <tr>
                        <td class="text-muted">Country:</td>
                        <td>
                            <span class="badge bg-secondary"><?= htmlspecialchars($form['country']) ?></span>
                            <?= htmlspecialchars($country_name) ?>
                        </td>
                    </tr>
                    <?php if ($form['address']): ?>
                    <tr>
                        <td class="text-muted">Address:</td>
                        <td>
                            <?= htmlspecialchars($form['address']) ?><br>
                            <?= htmlspecialchars($form['city'] . ', ' . $form['state'] . ' ' . $form['postal_code']) ?>
                        </td>
                    </tr>
                    <?php endif; ?>
                </table>
            </div>
        </div>
    </div>
    
    <!-- Application Information -->
    <div class="col-md-6">
        <div class="card mb-3">
            <div class="card-header">
                <h6 class="mb-0"><i class="fas fa-file-alt"></i> Application Information</h6>
            </div>
            <div class="card-body">
                <table class="table table-sm table-borderless">
                    <tr>
                        <td class="text-muted" width="40%">Application ID:</td>
                        <td><strong>#<?= $form['application_id'] ?></strong></td>
                    </tr>
                    <tr>
                        <td class="text-muted">Loan Amount:</td>
                        <td><strong>$<?= number_format($form['loan_amount'], 2) ?></strong></td>
                    </tr>
                    <tr>
                        <td class="text-muted">Purpose:</td>
                        <td><?= htmlspecialchars($form['loan_purpose']) ?></td>
                    </tr>
                    <tr>
                        <td class="text-muted">Application Status:</td>
                        <td><span class="badge bg-info"><?= ucfirst($form['application_status']) ?></span></td>
                    </tr>
                    <tr>
                        <td class="text-muted">Application Date:</td>
                        <td><?= date('M j, Y g:i A', strtotime($form['application_date'])) ?></td>
                    </tr>
                    <?php if ($form['monthly_income']): ?>
                    <tr>
                        <td class="text-muted">Monthly Income:</td>
                        <td>$<?= number_format($form['monthly_income'], 2) ?></td>
                    </tr>
                    <?php endif; ?>
                    <?php if ($form['credit_score']): ?>
                    <tr>
                        <td class="text-muted">Credit Score:</td>
                        <td><?= $form['credit_score'] ?></td>
                    </tr>
                    <?php endif; ?>
                </table>
            </div>
        </div>
    </div>
</div>

<!-- Fee Payment Details -->
<div class="card mb-3">
    <div class="card-header">
        <h6 class="mb-0"><i class="fas fa-credit-card"></i> Fee Payment Details</h6>
    </div>
    <div class="card-body">
        <div class="row">
            <div class="col-md-6">
                <table class="table table-sm table-borderless">
                    <tr>
                        <td class="text-muted" width="40%">Payment Method:</td>
                        <td><strong><?= htmlspecialchars($payment_method_name) ?></strong></td>
                    </tr>
                    <tr>
                        <td class="text-muted">Amount Sent:</td>
                        <td><strong class="text-success">$<?= number_format($form['amount_sent'], 2) ?></strong></td>
                    </tr>
                    <tr>
                        <td class="text-muted">Date Sent:</td>
                        <td><?= date('M j, Y', strtotime($form['date_sent'])) ?></td>
                    </tr>
                    <tr>
                        <td class="text-muted">Form Submitted:</td>
                        <td><?= date('M j, Y g:i A', strtotime($form['created_at'])) ?></td>
                    </tr>
                    <tr>
                        <td class="text-muted">Current Status:</td>
                        <td><span class="badge <?= $status_class ?>"><?= ucfirst(str_replace('_', ' ', $form['status'])) ?></span></td>
                    </tr>
                </table>
            </div>
            <div class="col-md-6">
                <?php if ($form['transaction_reference']): ?>
                <div class="mb-3">
                    <label class="form-label text-muted">Transaction Reference:</label>
                    <div class="p-2 bg-light rounded">
                        <code><?= htmlspecialchars($form['transaction_reference']) ?></code>
                        <button class="btn btn-sm btn-outline-secondary ms-2" onclick="copyToClipboard('<?= htmlspecialchars($form['transaction_reference']) ?>')">
                            <i class="fas fa-copy"></i>
                        </button>
                    </div>
                </div>
                <?php endif; ?>
                
                <?php if ($form['receipt_filename']): ?>
                <div class="mb-3">
                    <label class="form-label text-muted">Receipt/Proof:</label>
                    <div>
                        <a href="../uploads/receipts/<?= htmlspecialchars($form['receipt_filename']) ?>" target="_blank" class="btn btn-outline-primary btn-sm">
                            <i class="fas fa-file-alt"></i> View Receipt
                        </a>
                        <a href="../uploads/receipts/<?= htmlspecialchars($form['receipt_filename']) ?>" download class="btn btn-outline-secondary btn-sm">
                            <i class="fas fa-download"></i> Download
                        </a>
                    </div>
                </div>
                <?php endif; ?>
            </div>
        </div>
        
        <?php if ($form['additional_notes']): ?>
        <div class="mt-3">
            <label class="form-label text-muted">Client Notes:</label>
            <div class="p-3 bg-light rounded">
                <?= nl2br(htmlspecialchars($form['additional_notes'])) ?>
            </div>
        </div>
        <?php endif; ?>
    </div>
</div>

<!-- Review Information -->
<?php if ($form['reviewed_by'] || $form['admin_notes']): ?>
<div class="card mb-3">
    <div class="card-header">
        <h6 class="mb-0"><i class="fas fa-user-check"></i> Review Information</h6>
    </div>
    <div class="card-body">
        <?php if ($form['reviewed_by']): ?>
        <div class="row mb-3">
            <div class="col-md-6">
                <label class="form-label text-muted">Reviewed By:</label>
                <div><?= htmlspecialchars($form['reviewer_first_name'] . ' ' . $form['reviewer_last_name']) ?></div>
                <small class="text-muted"><?= htmlspecialchars($form['reviewer_email']) ?></small>
            </div>
            <div class="col-md-6">
                <label class="form-label text-muted">Review Date:</label>
                <div><?= date('M j, Y g:i A', strtotime($form['reviewed_at'])) ?></div>
            </div>
        </div>
        <?php endif; ?>
        
        <?php if ($form['admin_notes']): ?>
        <div>
            <label class="form-label text-muted">Admin Notes:</label>
            <div class="p-3 bg-light rounded">
                <?= nl2br(htmlspecialchars($form['admin_notes'])) ?>
            </div>
        </div>
        <?php endif; ?>
    </div>
</div>
<?php endif; ?>

<!-- Template Information -->
<?php if ($template): ?>
<div class="card mb-3">
    <div class="card-header">
        <h6 class="mb-0"><i class="fas fa-file-code"></i> Template Information</h6>
    </div>
    <div class="card-body">
        <div class="row">
            <div class="col-md-6">
                <label class="form-label text-muted">Template Name:</label>
                <div><?= htmlspecialchars($template['template_name']) ?></div>
            </div>
            <div class="col-md-6">
                <label class="form-label text-muted">Required Fields:</label>
                <div>
                    <?php 
                    $required_fields = json_decode($template['required_fields'], true);
                    if ($required_fields) {
                        foreach ($required_fields as $field => $required) {
                            if ($required) {
                                echo '<span class="badge bg-primary me-1">' . ucfirst(str_replace('_', ' ', $field)) . '</span>';
                            }
                        }
                    }
                    ?>
                </div>
            </div>
        </div>
        
        <?php if ($template['instructions']): ?>
        <div class="mt-3">
            <label class="form-label text-muted">Template Instructions:</label>
            <div class="p-3 bg-light rounded small">
                <?= nl2br(htmlspecialchars($template['instructions'])) ?>
            </div>
        </div>
        <?php endif; ?>
    </div>
</div>
<?php endif; ?>

<!-- Notification History -->
<?php if (!empty($notifications)): ?>
<div class="card">
    <div class="card-header">
        <h6 class="mb-0"><i class="fas fa-bell"></i> Notification History</h6>
    </div>
    <div class="card-body">
        <div class="table-responsive">
            <table class="table table-sm">
                <thead>
                    <tr>
                        <th>Type</th>
                        <th>Subject</th>
                        <th>Sent At</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($notifications as $notification): ?>
                    <tr>
                        <td>
                            <span class="badge bg-secondary"><?= ucfirst($notification['notification_type']) ?></span>
                        </td>
                        <td><?= htmlspecialchars($notification['subject']) ?></td>
                        <td>
                            <?php if ($notification['sent_at']): ?>
                                <?= date('M j, Y g:i A', strtotime($notification['sent_at'])) ?>
                            <?php else: ?>
                                <span class="text-muted">Not sent</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php
                            $notification_status_classes = [
                                'sent' => 'bg-success',
                                'pending' => 'bg-warning',
                                'failed' => 'bg-danger'
                            ];
                            $notification_status_class = $notification_status_classes[$notification['status']] ?? 'bg-secondary';
                            ?>
                            <span class="badge <?= $notification_status_class ?>"><?= ucfirst($notification['status']) ?></span>
                            <?php if ($notification['error_message']): ?>
                                <i class="fas fa-exclamation-triangle text-warning ms-1" title="<?= htmlspecialchars($notification['error_message']) ?>"></i>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
<?php endif; ?>

<script>
function copyToClipboard(text) {
    navigator.clipboard.writeText(text).then(function() {
        // Show success feedback
        const button = event.target.closest('button');
        const originalIcon = button.innerHTML;
        button.innerHTML = '<i class="fas fa-check"></i>';
        button.classList.add('btn-success');
        button.classList.remove('btn-outline-secondary');
        
        setTimeout(() => {
            button.innerHTML = originalIcon;
            button.classList.remove('btn-success');
            button.classList.add('btn-outline-secondary');
        }, 2000);
    }).catch(function(err) {
        console.error('Could not copy text: ', err);
        alert('Failed to copy to clipboard');
    });
}
</script>