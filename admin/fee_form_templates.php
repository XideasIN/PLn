<?php
/**
 * Fee Form Templates Management
 * LoanFlow Personal Loan Management System
 * Admin interface for managing country-specific fee payment form templates
 */

require_once '../includes/config.php';
require_once '../includes/auth.php';
require_once '../includes/functions.php';

// Check if user is admin
if (!isAdmin()) {
    header('Location: ../login.php');
    exit();
}

// Handle form actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        switch ($_POST['action']) {
            case 'create_template':
                $country = $_POST['country'];
                $payment_method = $_POST['payment_method'];
                $template_name = $_POST['template_name'];
                $instructions = $_POST['instructions'];
                $email_template = $_POST['email_template'];
                $required_fields = json_encode([
                    'amount_sent' => isset($_POST['required_amount_sent']),
                    'date_sent' => isset($_POST['required_date_sent']),
                    'transaction_reference' => isset($_POST['required_transaction_reference'])
                ]);
                
                try {
                    $stmt = $pdo->prepare("
                        INSERT INTO fee_form_templates 
                        (country, payment_method, template_name, instructions, email_template, required_fields, created_by) 
                        VALUES (?, ?, ?, ?, ?, ?, ?)
                    ");
                    $stmt->execute([
                        $country, $payment_method, $template_name, $instructions, 
                        $email_template, $required_fields, $_SESSION['user_id']
                    ]);
                    $_SESSION['success'] = 'Template created successfully.';
                } catch (PDOException $e) {
                    if ($e->getCode() == 23000) {
                        $_SESSION['error'] = 'A template for this country and payment method already exists.';
                    } else {
                        $_SESSION['error'] = 'Error creating template: ' . $e->getMessage();
                    }
                }
                break;
                
            case 'update_template':
                $template_id = (int)$_POST['template_id'];
                $template_name = $_POST['template_name'];
                $instructions = $_POST['instructions'];
                $email_template = $_POST['email_template'];
                $required_fields = json_encode([
                    'amount_sent' => isset($_POST['required_amount_sent']),
                    'date_sent' => isset($_POST['required_date_sent']),
                    'transaction_reference' => isset($_POST['required_transaction_reference'])
                ]);
                $is_active = isset($_POST['is_active']);
                
                $stmt = $pdo->prepare("
                    UPDATE fee_form_templates 
                    SET template_name = ?, instructions = ?, email_template = ?, 
                        required_fields = ?, is_active = ?, updated_at = NOW() 
                    WHERE id = ?
                ");
                $stmt->execute([
                    $template_name, $instructions, $email_template, 
                    $required_fields, $is_active, $template_id
                ]);
                $_SESSION['success'] = 'Template updated successfully.';
                break;
                
            case 'delete_template':
                $template_id = (int)$_POST['template_id'];
                
                // Check if template is being used
                $usage_stmt = $pdo->prepare("
                    SELECT COUNT(*) FROM fee_sent_forms fsf
                    JOIN fee_form_templates fft ON fsf.country = fft.country AND fsf.payment_method = fft.payment_method
                    WHERE fft.id = ?
                ");
                $usage_stmt->execute([$template_id]);
                $usage_count = $usage_stmt->fetchColumn();
                
                if ($usage_count > 0) {
                    $_SESSION['error'] = 'Cannot delete template: it is being used by ' . $usage_count . ' fee forms.';
                } else {
                    $stmt = $pdo->prepare("DELETE FROM fee_form_templates WHERE id = ?");
                    $stmt->execute([$template_id]);
                    $_SESSION['success'] = 'Template deleted successfully.';
                }
                break;
        }
        
        header('Location: fee_form_templates.php');
        exit();
    }
}

// Get all templates
$templates_stmt = $pdo->query("
    SELECT 
        fft.*,
        creator.first_name as creator_first_name, creator.last_name as creator_last_name,
        COUNT(fsf.id) as usage_count
    FROM fee_form_templates fft
    LEFT JOIN users creator ON fft.created_by = creator.id
    LEFT JOIN fee_sent_forms fsf ON fft.country = fsf.country AND fft.payment_method = fsf.payment_method
    GROUP BY fft.id
    ORDER BY fft.country, fft.payment_method
");
$templates = $templates_stmt->fetchAll();

// Get available countries and payment methods
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

include '../includes/header.php';
?>

<div class="container-fluid">
    <!-- Page Header -->
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h1 class="h3 mb-0">Fee Form Templates</h1>
            <p class="text-muted">Manage country-specific payment form templates and instructions</p>
        </div>
        <div>
            <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#createTemplateModal">
                <i class="fas fa-plus"></i> Create Template
            </button>
            <a href="fee_sent_forms.php" class="btn btn-outline-primary">
                <i class="fas fa-arrow-left"></i> Back to Forms
            </a>
        </div>
    </div>

    <!-- Templates Grid -->
    <div class="row">
        <?php if (empty($templates)): ?>
            <div class="col-12">
                <div class="card">
                    <div class="card-body text-center py-5">
                        <i class="fas fa-file-code fa-3x text-muted mb-3"></i>
                        <h5 class="text-muted">No Templates Found</h5>
                        <p class="text-muted">Create your first fee form template to get started.</p>
                        <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#createTemplateModal">
                            <i class="fas fa-plus"></i> Create Template
                        </button>
                    </div>
                </div>
            </div>
        <?php else: ?>
            <?php foreach ($templates as $template): ?>
                <div class="col-md-6 col-lg-4 mb-4">
                    <div class="card h-100 <?= $template['is_active'] ? '' : 'border-secondary' ?>">
                        <div class="card-header d-flex justify-content-between align-items-center">
                            <div>
                                <h6 class="mb-0"><?= htmlspecialchars($template['template_name']) ?></h6>
                                <small class="text-muted">
                                    <?= htmlspecialchars($countries[$template['country']] ?? $template['country']) ?> - 
                                    <?= htmlspecialchars($payment_methods[$template['payment_method']] ?? $template['payment_method']) ?>
                                </small>
                            </div>
                            <div>
                                <?php if ($template['is_active']): ?>
                                    <span class="badge bg-success">Active</span>
                                <?php else: ?>
                                    <span class="badge bg-secondary">Inactive</span>
                                <?php endif; ?>
                            </div>
                        </div>
                        <div class="card-body">
                            <div class="mb-3">
                                <small class="text-muted">Instructions Preview:</small>
                                <div class="small text-truncate" style="max-height: 60px; overflow: hidden;">
                                    <?= nl2br(htmlspecialchars(substr($template['instructions'], 0, 150))) ?>
                                    <?= strlen($template['instructions']) > 150 ? '...' : '' ?>
                                </div>
                            </div>
                            
                            <div class="mb-3">
                                <small class="text-muted">Required Fields:</small>
                                <div>
                                    <?php 
                                    $required_fields = json_decode($template['required_fields'], true) ?? [];
                                    foreach ($required_fields as $field => $required) {
                                        if ($required) {
                                            echo '<span class="badge bg-primary me-1 mb-1">' . ucfirst(str_replace('_', ' ', $field)) . '</span>';
                                        }
                                    }
                                    ?>
                                </div>
                            </div>
                            
                            <div class="row text-center">
                                <div class="col-6">
                                    <small class="text-muted">Usage Count</small>
                                    <div class="h5 mb-0"><?= number_format($template['usage_count']) ?></div>
                                </div>
                                <div class="col-6">
                                    <small class="text-muted">Created</small>
                                    <div class="small"><?= date('M j, Y', strtotime($template['created_at'])) ?></div>
                                </div>
                            </div>
                        </div>
                        <div class="card-footer">
                            <div class="btn-group w-100" role="group">
                                <button class="btn btn-outline-primary btn-sm" onclick="editTemplate(<?= $template['id'] ?>)" title="Edit">
                                    <i class="fas fa-edit"></i>
                                </button>
                                <button class="btn btn-outline-info btn-sm" onclick="previewTemplate(<?= $template['id'] ?>)" title="Preview">
                                    <i class="fas fa-eye"></i>
                                </button>
                                <button class="btn btn-outline-success btn-sm" onclick="testTemplate(<?= $template['id'] ?>)" title="Test Email">
                                    <i class="fas fa-envelope"></i>
                                </button>
                                <?php if ($template['usage_count'] == 0): ?>
                                    <button class="btn btn-outline-danger btn-sm" onclick="deleteTemplate(<?= $template['id'] ?>)" title="Delete">
                                        <i class="fas fa-trash"></i>
                                    </button>
                                <?php endif; ?>
                            </div>
                            
                            <?php if ($template['creator_first_name']): ?>
                                <div class="text-center mt-2">
                                    <small class="text-muted">
                                        Created by <?= htmlspecialchars($template['creator_first_name'] . ' ' . $template['creator_last_name']) ?>
                                    </small>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
</div>

<!-- Create Template Modal -->
<div class="modal fade" id="createTemplateModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <form method="POST">
                <div class="modal-header">
                    <h5 class="modal-title">Create Fee Form Template</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <input type="hidden" name="action" value="create_template">
                    
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label class="form-label">Country <span class="text-danger">*</span></label>
                            <select name="country" class="form-select" required>
                                <option value="">Select Country</option>
                                <?php foreach ($countries as $code => $name): ?>
                                    <option value="<?= $code ?>"><?= htmlspecialchars($name) ?> (<?= $code ?>)</option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Payment Method <span class="text-danger">*</span></label>
                            <select name="payment_method" class="form-select" required>
                                <option value="">Select Payment Method</option>
                                <?php foreach ($payment_methods as $method => $name): ?>
                                    <option value="<?= $method ?>"><?= htmlspecialchars($name) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Template Name <span class="text-danger">*</span></label>
                        <input type="text" name="template_name" class="form-control" required 
                               placeholder="e.g., USA Wire Transfer Form">
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Instructions <span class="text-danger">*</span></label>
                        <textarea name="instructions" class="form-control" rows="4" required
                                  placeholder="Enter instructions for clients on how to complete their payment..."></textarea>
                        <div class="form-text">These instructions will be displayed to clients when they access the fee form.</div>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Email Template</label>
                        <textarea name="email_template" class="form-control" rows="6"
                                  placeholder="Dear {user_name},\n\nPlease complete your payment using the following details:\n\n{payment_details}\n\nBest regards,\nLoanFlow Team"></textarea>
                        <div class="form-text">
                            Available placeholders: {user_name}, {application_id}, {amount}, {bank_name}, {account_number}, {routing_number}, {wallet_address}, {network}
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Required Fields</label>
                        <div class="row">
                            <div class="col-md-4">
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" name="required_amount_sent" id="required_amount_sent" checked>
                                    <label class="form-check-label" for="required_amount_sent">
                                        Amount Sent
                                    </label>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" name="required_date_sent" id="required_date_sent" checked>
                                    <label class="form-check-label" for="required_date_sent">
                                        Date Sent
                                    </label>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" name="required_transaction_reference" id="required_transaction_reference">
                                    <label class="form-check-label" for="required_transaction_reference">
                                        Transaction Reference
                                    </label>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Create Template</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Edit Template Modal -->
<div class="modal fade" id="editTemplateModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <form method="POST">
                <div class="modal-header">
                    <h5 class="modal-title">Edit Fee Form Template</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body" id="editTemplateContent">
                    <!-- Content will be loaded via AJAX -->
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Update Template</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Preview Template Modal -->
<div class="modal fade" id="previewTemplateModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Template Preview</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body" id="previewTemplateContent">
                <!-- Content will be loaded via AJAX -->
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>

<script>
// Edit template
function editTemplate(templateId) {
    fetch(`fee_template_edit.php?id=${templateId}`)
        .then(response => response.text())
        .then(html => {
            document.getElementById('editTemplateContent').innerHTML = html;
            new bootstrap.Modal(document.getElementById('editTemplateModal')).show();
        })
        .catch(error => {
            console.error('Error loading template:', error);
            alert('Error loading template for editing');
        });
}

// Preview template
function previewTemplate(templateId) {
    fetch(`fee_template_preview.php?id=${templateId}`)
        .then(response => response.text())
        .then(html => {
            document.getElementById('previewTemplateContent').innerHTML = html;
            new bootstrap.Modal(document.getElementById('previewTemplateModal')).show();
        })
        .catch(error => {
            console.error('Error loading preview:', error);
            alert('Error loading template preview');
        });
}

// Test template email
function testTemplate(templateId) {
    if (confirm('Send a test email using this template to your admin email?')) {
        fetch('fee_template_test.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
            },
            body: `template_id=${templateId}`
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                alert('Test email sent successfully!');
            } else {
                alert('Error sending test email: ' + data.message);
            }
        })
        .catch(error => {
            console.error('Error sending test email:', error);
            alert('Error sending test email');
        });
    }
}

// Delete template
function deleteTemplate(templateId) {
    if (confirm('Are you sure you want to delete this template? This action cannot be undone.')) {
        const form = document.createElement('form');
        form.method = 'POST';
        form.innerHTML = `
            <input type="hidden" name="action" value="delete_template">
            <input type="hidden" name="template_id" value="${templateId}">
        `;
        document.body.appendChild(form);
        form.submit();
    }
}

// Auto-generate template name based on country and payment method
document.addEventListener('DOMContentLoaded', function() {
    const countrySelect = document.querySelector('#createTemplateModal select[name="country"]');
    const methodSelect = document.querySelector('#createTemplateModal select[name="payment_method"]');
    const nameInput = document.querySelector('#createTemplateModal input[name="template_name"]');
    
    function updateTemplateName() {
        const country = countrySelect.options[countrySelect.selectedIndex]?.text?.split(' (')[0] || '';
        const method = methodSelect.options[methodSelect.selectedIndex]?.text || '';
        
        if (country && method) {
            nameInput.value = `${country} ${method} Form`;
        }
    }
    
    countrySelect.addEventListener('change', updateTemplateName);
    methodSelect.addEventListener('change', updateTemplateName);
});
</script>

<?php include '../includes/footer.php'; ?>