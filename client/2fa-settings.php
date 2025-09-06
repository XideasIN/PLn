<?php
/**
 * Two-Factor Authentication Settings
 * LoanFlow Personal Loan Management System
 */

require_once '../includes/functions.php';
require_once '../includes/2fa.php';
require_once '../includes/language.php';

// Require client login
requireLogin();

// Initialize language
LanguageManager::init();

$current_user = getCurrentUser();
$is_2fa_enabled = TwoFactorAuth::isEnabled($current_user['id']);

$error = '';
$success = '';
$setup_data = null;
$backup_codes = [];

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCSRFToken($_POST['csrf_token'] ?? '')) {
        $error = __('invalid_csrf_token');
    } else {
        $action = $_POST['action'] ?? '';
        
        switch ($action) {
            case 'setup':
                $setup_data = TwoFactorAuth::setupUser($current_user['id']);
                if ($setup_data) {
                    $success = __('2fa_setup_ready');
                } else {
                    $error = __('2fa_setup_failed');
                }
                break;
                
            case 'enable':
                $code = sanitizeInput($_POST['verification_code'] ?? '');
                if (empty($code)) {
                    $error = __('2fa_code_required');
                } else {
                    $backup_codes = TwoFactorAuth::enableUser($current_user['id'], $code);
                    if ($backup_codes) {
                        $is_2fa_enabled = true;
                        $success = __('2fa_enabled_success');
                    } else {
                        $error = __('2fa_invalid_code');
                    }
                }
                break;
                
            case 'disable':
                $code = sanitizeInput($_POST['verification_code'] ?? '');
                if (empty($code)) {
                    $error = __('2fa_code_required');
                } else {
                    if (TwoFactorAuth::disableUser($current_user['id'], $code)) {
                        $is_2fa_enabled = false;
                        $success = __('2fa_disabled_success');
                    } else {
                        $error = __('2fa_invalid_code');
                    }
                }
                break;
                
            case 'regenerate_codes':
                $backup_codes = TwoFactorAuth::regenerateBackupCodes($current_user['id']);
                if ($backup_codes) {
                    $success = __('2fa_backup_codes_regenerated');
                } else {
                    $error = __('2fa_backup_codes_failed');
                }
                break;
        }
    }
}

// Get current backup codes if 2FA is enabled
if ($is_2fa_enabled && empty($backup_codes)) {
    $backup_codes = TwoFactorAuth::getBackupCodes($current_user['id']);
}

?>
<!DOCTYPE html>
<html lang="<?= LanguageManager::getCurrentLanguage() ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= __('2fa_settings') ?> - LoanFlow</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="../assets/css/style.css" rel="stylesheet">
    <link href="../assets/css/client.css" rel="stylesheet">
</head>
<body>
    <!-- Client Header -->
    <nav class="navbar navbar-expand-lg navbar-dark bg-primary">
        <div class="container">
            <a class="navbar-brand" href="dashboard.php">
                <i class="fas fa-coins me-2"></i>LoanFlow Client
            </a>
            <div class="navbar-nav ms-auto">
                <?= LanguageManager::getLanguageSelector() ?>
                <a class="nav-link" href="dashboard.php">
                    <i class="fas fa-arrow-left me-1"></i><?= __('back_to_dashboard') ?>
                </a>
            </div>
        </div>
    </nav>

    <div class="container py-4">
        <!-- Flash Messages -->
        <?php if ($error): ?>
            <div class="alert alert-danger alert-dismissible fade show">
                <?= htmlspecialchars($error) ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>
        
        <?php if ($success): ?>
            <div class="alert alert-success alert-dismissible fade show">
                <?= htmlspecialchars($success) ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <div class="row justify-content-center">
            <div class="col-lg-8">
                <div class="card">
                    <div class="card-header">
                        <h4 class="mb-0">
                            <i class="fas fa-shield-alt me-2"></i><?= __('2fa_settings') ?>
                        </h4>
                    </div>
                    <div class="card-body">
                        
                        <?php if (!$is_2fa_enabled): ?>
                            <!-- Setup 2FA -->
                            <?php if (!$setup_data): ?>
                                <div class="text-center mb-4">
                                    <i class="fas fa-mobile-alt fa-3x text-muted mb-3"></i>
                                    <h5><?= __('2fa_not_enabled') ?></h5>
                                    <p class="text-muted"><?= __('help_2fa') ?></p>
                                    
                                    <form method="POST" class="d-inline">
                                        <input type="hidden" name="csrf_token" value="<?= generateCSRFToken() ?>">
                                        <input type="hidden" name="action" value="setup">
                                        <button type="submit" class="btn btn-primary btn-lg">
                                            <i class="fas fa-shield-alt me-2"></i><?= __('2fa_setup') ?>
                                        </button>
                                    </form>
                                </div>
                            <?php else: ?>
                                <!-- QR Code Setup -->
                                <div class="row">
                                    <div class="col-md-6">
                                        <h5><?= __('2fa_scan_qr') ?></h5>
                                        <div class="text-center mb-3">
                                            <img src="<?= htmlspecialchars($setup_data['qr_code_url']) ?>" 
                                                 alt="QR Code" class="img-fluid border rounded">
                                        </div>
                                        <div class="alert alert-info">
                                            <small>
                                                <strong><?= __('manual_entry') ?>:</strong><br>
                                                <code><?= htmlspecialchars($setup_data['secret']) ?></code>
                                            </small>
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <h5><?= __('2fa_verify_setup') ?></h5>
                                        <form method="POST">
                                            <input type="hidden" name="csrf_token" value="<?= generateCSRFToken() ?>">
                                            <input type="hidden" name="action" value="enable">
                                            
                                            <div class="mb-3">
                                                <label for="verification_code" class="form-label">
                                                    <?= __('2fa_code') ?>
                                                </label>
                                                <input type="text" class="form-control form-control-lg text-center" 
                                                       id="verification_code" name="verification_code" 
                                                       placeholder="000000" maxlength="6" required
                                                       pattern="[0-9]{6}" autocomplete="off">
                                                <div class="form-text">
                                                    <?= __('enter_6_digit_code') ?>
                                                </div>
                                            </div>
                                            
                                            <button type="submit" class="btn btn-success btn-lg w-100">
                                                <i class="fas fa-check me-2"></i><?= __('2fa_enable') ?>
                                            </button>
                                        </form>
                                    </div>
                                </div>
                            <?php endif; ?>
                            
                        <?php else: ?>
                            <!-- 2FA Enabled -->
                            <div class="alert alert-success">
                                <i class="fas fa-check-circle me-2"></i>
                                <?= __('2fa_enabled_status') ?>
                            </div>
                            
                            <!-- Backup Codes -->
                            <?php if (!empty($backup_codes)): ?>
                                <div class="card mb-4">
                                    <div class="card-header">
                                        <h5 class="mb-0">
                                            <i class="fas fa-key me-2"></i><?= __('2fa_backup_codes') ?>
                                        </h5>
                                    </div>
                                    <div class="card-body">
                                        <div class="alert alert-warning">
                                            <i class="fas fa-exclamation-triangle me-2"></i>
                                            <?= __('backup_codes_warning') ?>
                                        </div>
                                        
                                        <div class="row">
                                            <?php foreach ($backup_codes as $code): ?>
                                                <div class="col-md-6 mb-2">
                                                    <code class="d-block p-2 bg-light rounded text-center">
                                                        <?= htmlspecialchars($code) ?>
                                                    </code>
                                                </div>
                                            <?php endforeach; ?>
                                        </div>
                                        
                                        <div class="mt-3">
                                            <button type="button" class="btn btn-outline-primary" onclick="printBackupCodes()">
                                                <i class="fas fa-print me-2"></i><?= __('print_codes') ?>
                                            </button>
                                            <button type="button" class="btn btn-outline-secondary" onclick="downloadBackupCodes()">
                                                <i class="fas fa-download me-2"></i><?= __('download_codes') ?>
                                            </button>
                                        </div>
                                    </div>
                                </div>
                            <?php endif; ?>
                            
                            <!-- Management Options -->
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <div class="card h-100">
                                        <div class="card-body text-center">
                                            <i class="fas fa-sync-alt fa-2x text-primary mb-3"></i>
                                            <h6><?= __('regenerate_backup_codes') ?></h6>
                                            <p class="text-muted small"><?= __('regenerate_backup_codes_help') ?></p>
                                            <form method="POST" class="d-inline">
                                                <input type="hidden" name="csrf_token" value="<?= generateCSRFToken() ?>">
                                                <input type="hidden" name="action" value="regenerate_codes">
                                                <button type="submit" class="btn btn-outline-primary">
                                                    <?= __('regenerate') ?>
                                                </button>
                                            </form>
                                        </div>
                                    </div>
                                </div>
                                
                                <div class="col-md-6 mb-3">
                                    <div class="card h-100">
                                        <div class="card-body text-center">
                                            <i class="fas fa-shield-slash fa-2x text-danger mb-3"></i>
                                            <h6><?= __('2fa_disable') ?></h6>
                                            <p class="text-muted small"><?= __('disable_2fa_help') ?></p>
                                            <button type="button" class="btn btn-outline-danger" data-bs-toggle="modal" data-bs-target="#disable2FAModal">
                                                <?= __('2fa_disable') ?>
                                            </button>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        <?php endif; ?>
                        
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Disable 2FA Modal -->
    <div class="modal fade" id="disable2FAModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">
                        <i class="fas fa-shield-slash me-2"></i><?= __('2fa_disable') ?>
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST">
                    <div class="modal-body">
                        <input type="hidden" name="csrf_token" value="<?= generateCSRFToken() ?>">
                        <input type="hidden" name="action" value="disable">
                        
                        <div class="alert alert-warning">
                            <i class="fas fa-exclamation-triangle me-2"></i>
                            <?= __('disable_2fa_warning') ?>
                        </div>
                        
                        <div class="mb-3">
                            <label for="disable_verification_code" class="form-label">
                                <?= __('2fa_code') ?>
                            </label>
                            <input type="text" class="form-control text-center" 
                                   id="disable_verification_code" name="verification_code" 
                                   placeholder="000000" maxlength="6" required
                                   pattern="[0-9]{6}" autocomplete="off">
                            <div class="form-text">
                                <?= __('enter_code_to_disable') ?>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                            <?= __('cancel') ?>
                        </button>
                        <button type="submit" class="btn btn-danger">
                            <i class="fas fa-shield-slash me-2"></i><?= __('2fa_disable') ?>
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function printBackupCodes() {
            const codes = <?= json_encode($backup_codes) ?>;
            const printWindow = window.open('', '_blank');
            const html = `
                <html>
                <head>
                    <title>2FA Backup Codes - LoanFlow</title>
                    <style>
                        body { font-family: Arial, sans-serif; margin: 20px; }
                        .header { text-align: center; margin-bottom: 30px; }
                        .codes { display: grid; grid-template-columns: 1fr 1fr; gap: 10px; }
                        .code { padding: 10px; background: #f8f9fa; border: 1px solid #dee2e6; text-align: center; font-family: monospace; font-size: 14px; }
                        .warning { background: #fff3cd; border: 1px solid #ffeaa7; padding: 15px; margin-top: 20px; }
                    </style>
                </head>
                <body>
                    <div class="header">
                        <h2>LoanFlow - 2FA Backup Codes</h2>
                        <p>Account: <?= htmlspecialchars($current_user['email']) ?></p>
                        <p>Generated: <?= date('Y-m-d H:i:s') ?></p>
                    </div>
                    <div class="codes">
                        ${codes.map(code => `<div class="code">${code}</div>`).join('')}
                    </div>
                    <div class="warning">
                        <strong>Important:</strong> Store these codes in a secure location. Each code can only be used once.
                    </div>
                </body>
                </html>
            `;
            printWindow.document.write(html);
            printWindow.document.close();
            printWindow.print();
        }
        
        function downloadBackupCodes() {
            const codes = <?= json_encode($backup_codes) ?>;
            const content = `LoanFlow - 2FA Backup Codes
Account: <?= htmlspecialchars($current_user['email']) ?>
Generated: <?= date('Y-m-d H:i:s') ?>

Backup Codes:
${codes.join('\n')}

Important: Store these codes in a secure location. Each code can only be used once.`;
            
            const blob = new Blob([content], { type: 'text/plain' });
            const url = window.URL.createObjectURL(blob);
            const a = document.createElement('a');
            a.href = url;
            a.download = 'loanflow-2fa-backup-codes.txt';
            a.click();
            window.URL.revokeObjectURL(url);
        }
        
        // Language change function
        function changeLanguage(lang) {
            const form = document.createElement('form');
            form.method = 'POST';
            form.action = '../change-language.php';
            
            const langInput = document.createElement('input');
            langInput.type = 'hidden';
            langInput.name = 'language';
            langInput.value = lang;
            
            const redirectInput = document.createElement('input');
            redirectInput.type = 'hidden';
            redirectInput.name = 'redirect';
            redirectInput.value = window.location.href;
            
            form.appendChild(langInput);
            form.appendChild(redirectInput);
            document.body.appendChild(form);
            form.submit();
        }
    </script>
</body>
</html>
