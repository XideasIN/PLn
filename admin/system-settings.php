<?php
/**
 * System Settings Management
 * LoanFlow Personal Loan Management System
 */

require_once '../config/database.php';
require_once '../includes/functions.php';
require_once '../includes/auth.php';
require_once '../includes/language.php';
require_once '../includes/captcha.php';
require_once '../includes/backup_manager.php';

// Require admin access
requireRole('admin');

// Initialize language
LanguageManager::init();

$current_user = getCurrentUser();

$error = '';
$success = '';

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCSRFToken($_POST['csrf_token'] ?? '')) {
        $error = __('invalid_csrf_token');
    } else {
        $action = $_POST['action'] ?? '';
        
        switch ($action) {
            case 'update_general':
                $general_settings = [
                    'site_name' => sanitizeInput($_POST['site_name'] ?? ''),
                    'site_email' => sanitizeInput($_POST['site_email'] ?? ''),
                    'admin_email' => sanitizeInput($_POST['admin_email'] ?? ''),
                    'timezone' => sanitizeInput($_POST['timezone'] ?? ''),
                    'date_format' => sanitizeInput($_POST['date_format'] ?? ''),
                    'currency' => sanitizeInput($_POST['currency'] ?? ''),
                    'maintenance_mode' => isset($_POST['maintenance_mode']) ? '1' : '0'
                ];
                
                if (updateGeneralSettings($general_settings)) {
                    $success = __('general_settings_updated');
                    logAudit('general_settings_updated', 'system_settings', null, $current_user['id'], $general_settings);
                } else {
                    $error = __('general_settings_update_failed');
                }
                break;
                
            case 'update_email':
                $email_settings = [
                    'smtp_host' => sanitizeInput($_POST['smtp_host'] ?? ''),
                    'smtp_port' => sanitizeInput($_POST['smtp_port'] ?? ''),
                    'smtp_username' => sanitizeInput($_POST['smtp_username'] ?? ''),
                    'smtp_password' => sanitizeInput($_POST['smtp_password'] ?? ''),
                    'smtp_encryption' => sanitizeInput($_POST['smtp_encryption'] ?? ''),
                    'mail_from_name' => sanitizeInput($_POST['mail_from_name'] ?? ''),
                    'mail_from_address' => sanitizeInput($_POST['mail_from_address'] ?? '')
                ];
                
                if (updateEmailSettings($email_settings)) {
                    $success = __('email_settings_updated');
                    logAudit('email_settings_updated', 'system_settings', null, $current_user['id'], array_diff_key($email_settings, ['smtp_password' => '']));
                } else {
                    $error = __('email_settings_update_failed');
                }
                break;
                
            case 'test_email':
                $test_email = sanitizeInput($_POST['test_email'] ?? '');
                if (empty($test_email)) {
                    $error = __('test_email_required');
                } else {
                    $test_result = sendTestEmail($test_email);
                    if ($test_result) {
                        $success = __('test_email_sent');
                    } else {
                        $error = __('test_email_failed');
                    }
                }
                break;
                
            case 'update_security':
                $security_settings = [
                    'max_login_attempts' => intval($_POST['max_login_attempts'] ?? 5),
                    'lockout_duration' => intval($_POST['lockout_duration'] ?? 30),
                    'session_timeout' => intval($_POST['session_timeout'] ?? 30),
                    'password_min_length' => intval($_POST['password_min_length'] ?? 8),
                    'require_2fa' => isset($_POST['require_2fa']) ? '1' : '0',
                    'ip_whitelist' => sanitizeInput($_POST['ip_whitelist'] ?? ''),
                    'enable_audit_log' => isset($_POST['enable_audit_log']) ? '1' : '0'
                ];
                
                if (updateSecuritySettings($security_settings)) {
                    $success = __('security_settings_updated');
                    logAudit('security_settings_updated', 'system_settings', null, $current_user['id'], $security_settings);
                } else {
                    $error = __('security_settings_update_failed');
                }
                break;
                
            case 'update_payment':
                $payment_settings = [
                    'paypal_enabled' => isset($_POST['paypal_enabled']) ? '1' : '0',
                    'paypal_client_id' => sanitizeInput($_POST['paypal_client_id'] ?? ''),
                    'paypal_client_secret' => sanitizeInput($_POST['paypal_client_secret'] ?? ''),
                    'paypal_sandbox' => isset($_POST['paypal_sandbox']) ? '1' : '0',
                    'stripe_enabled' => isset($_POST['stripe_enabled']) ? '1' : '0',
                    'stripe_publishable_key' => sanitizeInput($_POST['stripe_publishable_key'] ?? ''),
                    'stripe_secret_key' => sanitizeInput($_POST['stripe_secret_key'] ?? ''),
                    'stripe_webhook_secret' => sanitizeInput($_POST['stripe_webhook_secret'] ?? '')
                ];
                
                if (updatePaymentSettings($payment_settings)) {
                    $success = __('payment_settings_updated');
                    logAudit('payment_settings_updated', 'system_settings', null, $current_user['id'], array_diff_key($payment_settings, ['paypal_client_secret' => '', 'stripe_secret_key' => '', 'stripe_webhook_secret' => '']));
                } else {
                    $error = __('payment_settings_update_failed');
                }
                break;
                
            case 'update_captcha':
                $captcha_settings = [
                    'captcha_enabled' => isset($_POST['captcha_enabled']) ? '1' : '0',
                    'captcha_provider' => sanitizeInput($_POST['captcha_provider'] ?? 'custom'),
                    'recaptcha_site_key' => sanitizeInput($_POST['recaptcha_site_key'] ?? ''),
                    'recaptcha_secret_key' => sanitizeInput($_POST['recaptcha_secret_key'] ?? ''),
                    'hcaptcha_site_key' => sanitizeInput($_POST['hcaptcha_site_key'] ?? ''),
                    'hcaptcha_secret_key' => sanitizeInput($_POST['hcaptcha_secret_key'] ?? ''),
                    'captcha_protected_forms' => sanitizeInput($_POST['captcha_protected_forms'] ?? '')
                ];
                
                if (updateCaptchaSettings($captcha_settings)) {
                    $success = __('captcha_settings_updated');
                    logAudit('captcha_settings_updated', 'system_settings', null, $current_user['id'], array_diff_key($captcha_settings, ['recaptcha_secret_key' => '', 'hcaptcha_secret_key' => '']));
                } else {
                    $error = __('captcha_settings_update_failed');
                }
                break;
                
            case 'create_complete_backup':
                $backup_result = BackupManager::createCompleteBackup(true);
                if ($backup_result['success']) {
                    $success = __('complete_backup_created') . ': ' . $backup_result['backup_info']['name'];
                    logAudit('complete_backup_created', 'system', null, $current_user['id'], ['backup_name' => $backup_result['backup_info']['name']]);
                } else {
                    $error = $backup_result['error'];
                }
                break;
                
            case 'update_backup_settings':
                $backup_settings = [
                    'max_retention' => intval($_POST['backup_max_retention'] ?? 4),
                    'email_notifications' => isset($_POST['backup_email_notifications']) ? '1' : '0',
                    'weekly_schedule' => isset($_POST['backup_weekly_schedule']) ? '1' : '0',
                    'schedule_day' => sanitizeInput($_POST['backup_schedule_day'] ?? 'sunday'),
                    'schedule_time' => sanitizeInput($_POST['backup_schedule_time'] ?? '02:00')
                ];
                
                if (BackupManager::updateBackupSettings($backup_settings)) {
                    $success = __('backup_settings_updated');
                    logAudit('backup_settings_updated', 'system_settings', null, $current_user['id'], $backup_settings);
                } else {
                    $error = __('backup_settings_update_failed');
                }
                break;
                
            case 'delete_backup':
                $backup_name = sanitizeInput($_POST['backup_name'] ?? '');
                if (!empty($backup_name)) {
                    $backup_path = dirname(__DIR__) . '/backups/' . $backup_name;
                    
                    if (file_exists($backup_path)) {
                        if (is_dir($backup_path)) {
                            if (removeDirectory($backup_path)) {
                                $success = __('backup_deleted_successfully');
                                logAudit('backup_deleted', 'system', null, $current_user['id'], ['backup_name' => $backup_name]);
                            } else {
                                $error = __('backup_delete_failed');
                            }
                        } else {
                            if (unlink($backup_path)) {
                                $success = __('backup_deleted_successfully');
                                logAudit('backup_deleted', 'system', null, $current_user['id'], ['backup_name' => $backup_name]);
                            } else {
                                $error = __('backup_delete_failed');
                            }
                        }
                    } else {
                        $error = __('backup_not_found');
                    }
                } else {
                    $error = __('invalid_backup_name');
                }
                break;
                
            case 'update_database':
                $database_settings = [
                    'db_host' => sanitizeInput($_POST['db_host'] ?? 'localhost'),
                    'db_name' => sanitizeInput($_POST['db_name'] ?? 'loanflow'),
                    'db_user' => sanitizeInput($_POST['db_user'] ?? 'loanflow_user'),
                    'db_password' => sanitizeInput($_POST['db_password'] ?? ''),
                    'db_charset' => sanitizeInput($_POST['db_charset'] ?? 'utf8mb4'),
                    'db_port' => intval($_POST['db_port'] ?? 3306),
                    'db_socket' => sanitizeInput($_POST['db_socket'] ?? ''),
                    'db_ssl_key' => sanitizeInput($_POST['db_ssl_key'] ?? ''),
                    'db_ssl_cert' => sanitizeInput($_POST['db_ssl_cert'] ?? ''),
                    'db_ssl_ca' => sanitizeInput($_POST['db_ssl_ca'] ?? ''),
                    'db_sql_mode' => sanitizeInput($_POST['db_sql_mode'] ?? 'STRICT_TRANS_TABLES,NO_ZERO_DATE,NO_ZERO_IN_DATE,ERROR_FOR_DIVISION_BY_ZERO'),
                    'db_timezone' => sanitizeInput($_POST['db_timezone'] ?? '+00:00'),
                    'db_init_command' => sanitizeInput($_POST['db_init_command'] ?? 'SET NAMES utf8mb4')
                ];
                
                if (updateDatabaseSettings($database_settings)) {
                    $success = __('database_settings_updated');
                    logAudit('database_settings_updated', 'system_settings', null, $current_user['id'], array_diff_key($database_settings, ['db_password' => '']));
                } else {
                    $error = __('database_settings_update_failed');
                }
                break;
                
            case 'test_database':
                $test_settings = [
                    'host' => sanitizeInput($_POST['db_host'] ?? 'localhost'),
                    'name' => sanitizeInput($_POST['db_name'] ?? 'loanflow'),
                    'user' => sanitizeInput($_POST['db_user'] ?? 'loanflow_user'),
                    'password' => sanitizeInput($_POST['db_password'] ?? ''),
                    'charset' => sanitizeInput($_POST['db_charset'] ?? 'utf8mb4'),
                    'port' => intval($_POST['db_port'] ?? 3306)
                ];
                
                $test_result = testDatabaseConnectionWithSettings($test_settings);
                if ($test_result['success']) {
                    $success = __('database_connection_successful') . ': ' . $test_result['message'];
                } else {
                    $error = __('database_connection_failed') . ': ' . $test_result['error'];
                }
                break;
                
            case 'clear_cache':
                if (clearSystemCache()) {
                    $success = __('cache_cleared');
                    logAudit('cache_cleared', 'system', null, $current_user['id']);
                } else {
                    $error = __('cache_clear_failed');
                }
                break;
        }
    }
}

// Get current settings
$general_settings = getGeneralSettings();
$email_settings = getEmailSettings();
$security_settings = getSecuritySettings();
$payment_settings = getPaymentSettings();
$captcha_settings = getCaptchaSettings();
$backup_statistics = BackupManager::getBackupStatistics();
$current_backups = BackupManager::getCurrentBackups();
$system_info = getSystemInfo();

?>
<!DOCTYPE html>
<html lang="<?= LanguageManager::getCurrentLanguage() ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= __('system_settings') ?> - LoanFlow Admin</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="../assets/css/style.css" rel="stylesheet">
    <link href="../assets/css/admin.css" rel="stylesheet">
</head>
<body>
    <!-- Admin Header -->
    <nav class="navbar navbar-expand-lg navbar-dark bg-dark">
        <div class="container-fluid">
            <a class="navbar-brand" href="index.php">
                <i class="fas fa-tachometer-alt me-2"></i>LoanFlow Admin
            </a>
            <div class="navbar-nav ms-auto">
                <?= LanguageManager::getLanguageSelector() ?>
                <a class="nav-link" href="index.php">
                    <i class="fas fa-arrow-left me-1"></i><?= __('back_to_dashboard') ?>
                </a>
            </div>
        </div>
    </nav>

    <div class="container-fluid py-4">
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

        <div class="row">
            <div class="col-12">
                <div class="card">
                    <div class="card-header">
                        <h4 class="mb-0">
                            <i class="fas fa-cogs me-2"></i><?= __('system_settings') ?>
                        </h4>
                    </div>
                    <div class="card-body">
                        
                        <!-- Navigation Tabs -->
                        <ul class="nav nav-tabs" id="settingsTabs" role="tablist">
                            <li class="nav-item" role="presentation">
                                <button class="nav-link active" id="general-tab" data-bs-toggle="tab" data-bs-target="#general" type="button" role="tab">
                                    <i class="fas fa-cog me-2"></i><?= __('general') ?>
                                </button>
                            </li>
                            <li class="nav-item" role="presentation">
                                <button class="nav-link" id="email-tab" data-bs-toggle="tab" data-bs-target="#email" type="button" role="tab">
                                    <i class="fas fa-envelope me-2"></i><?= __('email_smtp') ?>
                                </button>
                            </li>
                            <li class="nav-item" role="presentation">
                                <button class="nav-link" id="security-tab" data-bs-toggle="tab" data-bs-target="#security" type="button" role="tab">
                                    <i class="fas fa-shield-alt me-2"></i><?= __('security') ?>
                                </button>
                            </li>
                            <li class="nav-item" role="presentation">
                                <button class="nav-link" id="payment-tab" data-bs-toggle="tab" data-bs-target="#payment" type="button" role="tab">
                                    <i class="fas fa-credit-card me-2"></i><?= __('payments') ?>
                                </button>
                            </li>
                            <li class="nav-item" role="presentation">
                                <button class="nav-link" id="captcha-tab" data-bs-toggle="tab" data-bs-target="#captcha" type="button" role="tab">
                                    <i class="fas fa-robot me-2"></i><?= __('captcha') ?>
                                </button>
                            </li>
                            <li class="nav-item" role="presentation">
                                <button class="nav-link" id="backup-tab" data-bs-toggle="tab" data-bs-target="#backup" type="button" role="tab">
                                    <i class="fas fa-database me-2"></i><?= __('backup_system') ?>
                                </button>
                            </li>
                            <li class="nav-item" role="presentation">
                                <button class="nav-link" id="system-tab" data-bs-toggle="tab" data-bs-target="#system" type="button" role="tab">
                                    <i class="fas fa-server me-2"></i><?= __('system_info') ?>
                                </button>
                            </li>
                        </ul>
                        
                        <!-- Tab Content -->
                        <div class="tab-content" id="settingsTabsContent">
                            
                            <!-- General Settings Tab -->
                            <div class="tab-pane fade show active" id="general" role="tabpanel">
                                <form method="POST" class="mt-4">
                                    <input type="hidden" name="csrf_token" value="<?= generateCSRFToken() ?>">
                                    <input type="hidden" name="action" value="update_general">
                                    
                                    <div class="row">
                                        <div class="col-lg-6">
                                            <div class="mb-3">
                                                <label for="site_name" class="form-label">
                                                    <?= __('site_name') ?> <span class="text-danger">*</span>
                                                    <i class="fas fa-question-circle ms-1" data-bs-toggle="tooltip" title="<?= __('help_site_name') ?>"></i>
                                                </label>
                                                <input type="text" class="form-control" id="site_name" name="site_name" 
                                                       value="<?= htmlspecialchars($general_settings['site_name'] ?? 'LoanFlow') ?>" required>
                                            </div>
                                            
                                            <div class="mb-3">
                                                <label for="site_email" class="form-label">
                                                    <?= __('site_email') ?> <span class="text-danger">*</span>
                                                    <i class="fas fa-question-circle ms-1" data-bs-toggle="tooltip" title="<?= __('help_site_email') ?>"></i>
                                                </label>
                                                <input type="email" class="form-control" id="site_email" name="site_email" 
                                                       value="<?= htmlspecialchars($general_settings['site_email'] ?? '') ?>" required>
                                            </div>
                                            
                                            <div class="mb-3">
                                                <label for="admin_email" class="form-label">
                                                    <?= __('admin_email') ?> <span class="text-danger">*</span>
                                                    <i class="fas fa-question-circle ms-1" data-bs-toggle="tooltip" title="<?= __('help_admin_email') ?>"></i>
                                                </label>
                                                <input type="email" class="form-control" id="admin_email" name="admin_email" 
                                                       value="<?= htmlspecialchars($general_settings['admin_email'] ?? '') ?>" required>
                                            </div>
                                        </div>
                                        
                                        <div class="col-lg-6">
                                            <div class="mb-3">
                                                <label for="timezone" class="form-label">
                                                    <?= __('timezone') ?>
                                                    <i class="fas fa-question-circle ms-1" data-bs-toggle="tooltip" title="<?= __('help_timezone') ?>"></i>
                                                </label>
                                                <select class="form-select" id="timezone" name="timezone">
                                                    <?php foreach (getTimezones() as $tz): ?>
                                                        <option value="<?= htmlspecialchars($tz) ?>" <?= ($general_settings['timezone'] ?? '') === $tz ? 'selected' : '' ?>>
                                                            <?= htmlspecialchars($tz) ?>
                                                        </option>
                                                    <?php endforeach; ?>
                                                </select>
                                            </div>
                                            
                                            <div class="mb-3">
                                                <label for="date_format" class="form-label">
                                                    <?= __('date_format') ?>
                                                    <i class="fas fa-question-circle ms-1" data-bs-toggle="tooltip" title="<?= __('help_date_format') ?>"></i>
                                                </label>
                                                <select class="form-select" id="date_format" name="date_format">
                                                    <option value="Y-m-d" <?= ($general_settings['date_format'] ?? 'Y-m-d') === 'Y-m-d' ? 'selected' : '' ?>>2025-01-15</option>
                                                    <option value="m/d/Y" <?= ($general_settings['date_format'] ?? '') === 'm/d/Y' ? 'selected' : '' ?>>01/15/2025</option>
                                                    <option value="d/m/Y" <?= ($general_settings['date_format'] ?? '') === 'd/m/Y' ? 'selected' : '' ?>>15/01/2025</option>
                                                    <option value="F j, Y" <?= ($general_settings['date_format'] ?? '') === 'F j, Y' ? 'selected' : '' ?>>January 15, 2025</option>
                                                </select>
                                            </div>
                                            
                                            <div class="mb-3">
                                                <label for="currency" class="form-label">
                                                    <?= __('default_currency') ?>
                                                    <i class="fas fa-question-circle ms-1" data-bs-toggle="tooltip" title="<?= __('help_currency') ?>"></i>
                                                </label>
                                                <select class="form-select" id="currency" name="currency">
                                                    <option value="USD" <?= ($general_settings['currency'] ?? 'USD') === 'USD' ? 'selected' : '' ?>>USD ($)</option>
                                                    <option value="EUR" <?= ($general_settings['currency'] ?? '') === 'EUR' ? 'selected' : '' ?>>EUR (€)</option>
                                                    <option value="GBP" <?= ($general_settings['currency'] ?? '') === 'GBP' ? 'selected' : '' ?>>GBP (£)</option>
                                                    <option value="CAD" <?= ($general_settings['currency'] ?? '') === 'CAD' ? 'selected' : '' ?>>CAD (C$)</option>
                                                </select>
                                            </div>
                                        </div>
                                    </div>
                                    
                                    <div class="row">
                                        <div class="col-12">
                                            <div class="form-check mb-3">
                                                <input class="form-check-input" type="checkbox" id="maintenance_mode" name="maintenance_mode" 
                                                       <?= ($general_settings['maintenance_mode'] ?? '0') === '1' ? 'checked' : '' ?>>
                                                <label class="form-check-label" for="maintenance_mode">
                                                    <?= __('maintenance_mode') ?>
                                                    <i class="fas fa-question-circle ms-1" data-bs-toggle="tooltip" title="<?= __('help_maintenance_mode') ?>"></i>
                                                </label>
                                            </div>
                                        </div>
                                    </div>
                                    
                                    <button type="submit" class="btn btn-primary">
                                        <i class="fas fa-save me-2"></i><?= __('save_changes') ?>
                                    </button>
                                </form>
                            </div>
                            
                            <!-- Email/SMTP Settings Tab -->
                            <div class="tab-pane fade" id="email" role="tabpanel">
                                <div class="mt-4">
                                    <form method="POST">
                                        <input type="hidden" name="csrf_token" value="<?= generateCSRFToken() ?>">
                                        <input type="hidden" name="action" value="update_email">
                                        
                                        <div class="row">
                                            <div class="col-lg-6">
                                                <h5><?= __('smtp_configuration') ?></h5>
                                                
                                                <div class="mb-3">
                                                    <label for="smtp_host" class="form-label">
                                                        <?= __('smtp_host') ?> <span class="text-danger">*</span>
                                                        <i class="fas fa-question-circle ms-1" data-bs-toggle="tooltip" title="<?= __('help_smtp_host') ?>"></i>
                                                    </label>
                                                    <input type="text" class="form-control" id="smtp_host" name="smtp_host" 
                                                           value="<?= htmlspecialchars($email_settings['smtp_host'] ?? '') ?>" required>
                                                </div>
                                                
                                                <div class="row">
                                                    <div class="col-md-6 mb-3">
                                                        <label for="smtp_port" class="form-label">
                                                            <?= __('smtp_port') ?> <span class="text-danger">*</span>
                                                        </label>
                                                        <input type="number" class="form-control" id="smtp_port" name="smtp_port" 
                                                               value="<?= htmlspecialchars($email_settings['smtp_port'] ?? '587') ?>" required>
                                                    </div>
                                                    
                                                    <div class="col-md-6 mb-3">
                                                        <label for="smtp_encryption" class="form-label">
                                                            <?= __('smtp_encryption') ?>
                                                        </label>
                                                        <select class="form-select" id="smtp_encryption" name="smtp_encryption">
                                                            <option value="" <?= empty($email_settings['smtp_encryption']) ? 'selected' : '' ?>><?= __('none') ?></option>
                                                            <option value="tls" <?= ($email_settings['smtp_encryption'] ?? '') === 'tls' ? 'selected' : '' ?>>TLS</option>
                                                            <option value="ssl" <?= ($email_settings['smtp_encryption'] ?? '') === 'ssl' ? 'selected' : '' ?>>SSL</option>
                                                        </select>
                                                    </div>
                                                </div>
                                                
                                                <div class="mb-3">
                                                    <label for="smtp_username" class="form-label">
                                                        <?= __('smtp_username') ?> <span class="text-danger">*</span>
                                                    </label>
                                                    <input type="text" class="form-control" id="smtp_username" name="smtp_username" 
                                                           value="<?= htmlspecialchars($email_settings['smtp_username'] ?? '') ?>" required>
                                                </div>
                                                
                                                <div class="mb-3">
                                                    <label for="smtp_password" class="form-label">
                                                        <?= __('smtp_password') ?> <span class="text-danger">*</span>
                                                    </label>
                                                    <input type="password" class="form-control" id="smtp_password" name="smtp_password" 
                                                           value="<?= htmlspecialchars($email_settings['smtp_password'] ?? '') ?>" required>
                                                </div>
                                            </div>
                                            
                                            <div class="col-lg-6">
                                                <h5><?= __('email_settings') ?></h5>
                                                
                                                <div class="mb-3">
                                                    <label for="mail_from_name" class="form-label">
                                                        <?= __('from_name') ?> <span class="text-danger">*</span>
                                                    </label>
                                                    <input type="text" class="form-control" id="mail_from_name" name="mail_from_name" 
                                                           value="<?= htmlspecialchars($email_settings['mail_from_name'] ?? 'LoanFlow') ?>" required>
                                                </div>
                                                
                                                <div class="mb-3">
                                                    <label for="mail_from_address" class="form-label">
                                                        <?= __('from_address') ?> <span class="text-danger">*</span>
                                                    </label>
                                                    <input type="email" class="form-control" id="mail_from_address" name="mail_from_address" 
                                                           value="<?= htmlspecialchars($email_settings['mail_from_address'] ?? '') ?>" required>
                                                </div>
                                                
                                                <!-- Test Email Section -->
                                                <div class="card bg-light">
                                                    <div class="card-body">
                                                        <h6><?= __('test_email_configuration') ?></h6>
                                                        <div class="input-group mb-3">
                                                            <input type="email" class="form-control" id="test_email" name="test_email" 
                                                                   placeholder="<?= __('enter_test_email') ?>" value="<?= htmlspecialchars($current_user['email']) ?>">
                                                            <button type="button" class="btn btn-outline-secondary" onclick="sendTestEmail()">
                                                                <i class="fas fa-paper-plane me-2"></i><?= __('send_test') ?>
                                                            </button>
                                                        </div>
                                                        <small class="text-muted"><?= __('test_email_help') ?></small>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                        
                                        <button type="submit" class="btn btn-primary">
                                            <i class="fas fa-save me-2"></i><?= __('save_email_settings') ?>
                                        </button>
                                    </form>
                                </div>
                            </div>
                            
                            <!-- Security Settings Tab -->
                            <div class="tab-pane fade" id="security" role="tabpanel">
                                <form method="POST" class="mt-4">
                                    <input type="hidden" name="csrf_token" value="<?= generateCSRFToken() ?>">
                                    <input type="hidden" name="action" value="update_security">
                                    
                                    <div class="row">
                                        <div class="col-lg-6">
                                            <h5><?= __('login_security') ?></h5>
                                            
                                            <div class="mb-3">
                                                <label for="max_login_attempts" class="form-label">
                                                    <?= __('max_login_attempts') ?>
                                                    <i class="fas fa-question-circle ms-1" data-bs-toggle="tooltip" title="<?= __('help_max_login_attempts') ?>"></i>
                                                </label>
                                                <input type="number" class="form-control" id="max_login_attempts" name="max_login_attempts" 
                                                       value="<?= htmlspecialchars($security_settings['max_login_attempts'] ?? '5') ?>" min="1" max="20">
                                            </div>
                                            
                                            <div class="mb-3">
                                                <label for="lockout_duration" class="form-label">
                                                    <?= __('lockout_duration_minutes') ?>
                                                    <i class="fas fa-question-circle ms-1" data-bs-toggle="tooltip" title="<?= __('help_lockout_duration') ?>"></i>
                                                </label>
                                                <input type="number" class="form-control" id="lockout_duration" name="lockout_duration" 
                                                       value="<?= htmlspecialchars($security_settings['lockout_duration'] ?? '30') ?>" min="5" max="1440">
                                            </div>
                                            
                                            <div class="mb-3">
                                                <label for="session_timeout" class="form-label">
                                                    <?= __('session_timeout_minutes') ?>
                                                    <i class="fas fa-question-circle ms-1" data-bs-toggle="tooltip" title="<?= __('help_session_timeout') ?>"></i>
                                                </label>
                                                <input type="number" class="form-control" id="session_timeout" name="session_timeout" 
                                                       value="<?= htmlspecialchars($security_settings['session_timeout'] ?? '30') ?>" min="5" max="480">
                                            </div>
                                        </div>
                                        
                                        <div class="col-lg-6">
                                            <h5><?= __('password_security') ?></h5>
                                            
                                            <div class="mb-3">
                                                <label for="password_min_length" class="form-label">
                                                    <?= __('password_min_length') ?>
                                                    <i class="fas fa-question-circle ms-1" data-bs-toggle="tooltip" title="<?= __('help_password_min_length') ?>"></i>
                                                </label>
                                                <input type="number" class="form-control" id="password_min_length" name="password_min_length" 
                                                       value="<?= htmlspecialchars($security_settings['password_min_length'] ?? '8') ?>" min="6" max="50">
                                            </div>
                                            
                                            <div class="form-check mb-3">
                                                <input class="form-check-input" type="checkbox" id="require_2fa" name="require_2fa" 
                                                       <?= ($security_settings['require_2fa'] ?? '0') === '1' ? 'checked' : '' ?>>
                                                <label class="form-check-label" for="require_2fa">
                                                    <?= __('require_2fa_all_users') ?>
                                                    <i class="fas fa-question-circle ms-1" data-bs-toggle="tooltip" title="<?= __('help_require_2fa') ?>"></i>
                                                </label>
                                            </div>
                                            
                                            <div class="form-check mb-3">
                                                <input class="form-check-input" type="checkbox" id="enable_audit_log" name="enable_audit_log" 
                                                       <?= ($security_settings['enable_audit_log'] ?? '1') === '1' ? 'checked' : '' ?>>
                                                <label class="form-check-label" for="enable_audit_log">
                                                    <?= __('enable_audit_logging') ?>
                                                    <i class="fas fa-question-circle ms-1" data-bs-toggle="tooltip" title="<?= __('help_audit_logging') ?>"></i>
                                                </label>
                                            </div>
                                            
                                            <div class="mb-3">
                                                <label for="ip_whitelist" class="form-label">
                                                    <?= __('admin_ip_whitelist') ?>
                                                    <i class="fas fa-question-circle ms-1" data-bs-toggle="tooltip" title="<?= __('help_ip_whitelist') ?>"></i>
                                                </label>
                                                <textarea class="form-control" id="ip_whitelist" name="ip_whitelist" rows="3" 
                                                          placeholder="192.168.1.1&#10;10.0.0.0/8"><?= htmlspecialchars($security_settings['ip_whitelist'] ?? '') ?></textarea>
                                                <div class="form-text"><?= __('ip_whitelist_help') ?></div>
                                            </div>
                                        </div>
                                    </div>
                                    
                                    <button type="submit" class="btn btn-primary">
                                        <i class="fas fa-save me-2"></i><?= __('save_security_settings') ?>
                                    </button>
                                </form>
                            </div>
                            
                            <!-- Payment Settings Tab -->
                            <div class="tab-pane fade" id="payment" role="tabpanel">
                                <form method="POST" class="mt-4">
                                    <input type="hidden" name="csrf_token" value="<?= generateCSRFToken() ?>">
                                    <input type="hidden" name="action" value="update_payment">
                                    
                                    <div class="row">
                                        <div class="col-lg-6">
                                            <h5><?= __('paypal_settings') ?></h5>
                                            
                                            <div class="form-check mb-3">
                                                <input class="form-check-input" type="checkbox" id="paypal_enabled" name="paypal_enabled" 
                                                       <?= ($payment_settings['paypal_enabled'] ?? '0') === '1' ? 'checked' : '' ?>>
                                                <label class="form-check-label" for="paypal_enabled">
                                                    <?= __('enable_paypal') ?>
                                                </label>
                                            </div>
                                            
                                            <div class="mb-3">
                                                <label for="paypal_client_id" class="form-label">
                                                    <?= __('paypal_client_id') ?>
                                                </label>
                                                <input type="text" class="form-control" id="paypal_client_id" name="paypal_client_id" 
                                                       value="<?= htmlspecialchars($payment_settings['paypal_client_id'] ?? '') ?>">
                                            </div>
                                            
                                            <div class="mb-3">
                                                <label for="paypal_client_secret" class="form-label">
                                                    <?= __('paypal_client_secret') ?>
                                                </label>
                                                <input type="password" class="form-control" id="paypal_client_secret" name="paypal_client_secret" 
                                                       value="<?= htmlspecialchars($payment_settings['paypal_client_secret'] ?? '') ?>">
                                            </div>
                                            
                                            <div class="form-check mb-3">
                                                <input class="form-check-input" type="checkbox" id="paypal_sandbox" name="paypal_sandbox" 
                                                       <?= ($payment_settings['paypal_sandbox'] ?? '1') === '1' ? 'checked' : '' ?>>
                                                <label class="form-check-label" for="paypal_sandbox">
                                                    <?= __('paypal_sandbox_mode') ?>
                                                </label>
                                            </div>
                                        </div>
                                        
                                        <div class="col-lg-6">
                                            <h5><?= __('stripe_settings') ?></h5>
                                            
                                            <div class="form-check mb-3">
                                                <input class="form-check-input" type="checkbox" id="stripe_enabled" name="stripe_enabled" 
                                                       <?= ($payment_settings['stripe_enabled'] ?? '0') === '1' ? 'checked' : '' ?>>
                                                <label class="form-check-label" for="stripe_enabled">
                                                    <?= __('enable_stripe') ?>
                                                </label>
                                            </div>
                                            
                                            <div class="mb-3">
                                                <label for="stripe_publishable_key" class="form-label">
                                                    <?= __('stripe_publishable_key') ?>
                                                </label>
                                                <input type="text" class="form-control" id="stripe_publishable_key" name="stripe_publishable_key" 
                                                       value="<?= htmlspecialchars($payment_settings['stripe_publishable_key'] ?? '') ?>">
                                            </div>
                                            
                                            <div class="mb-3">
                                                <label for="stripe_secret_key" class="form-label">
                                                    <?= __('stripe_secret_key') ?>
                                                </label>
                                                <input type="password" class="form-control" id="stripe_secret_key" name="stripe_secret_key" 
                                                       value="<?= htmlspecialchars($payment_settings['stripe_secret_key'] ?? '') ?>">
                                            </div>
                                            
                                            <div class="mb-3">
                                                <label for="stripe_webhook_secret" class="form-label">
                                                    <?= __('stripe_webhook_secret') ?>
                                                </label>
                                                <input type="password" class="form-control" id="stripe_webhook_secret" name="stripe_webhook_secret" 
                                                       value="<?= htmlspecialchars($payment_settings['stripe_webhook_secret'] ?? '') ?>">
                                            </div>
                                        </div>
                                    </div>
                                    
                                    <button type="submit" class="btn btn-primary">
                                        <i class="fas fa-save me-2"></i><?= __('save_payment_settings') ?>
                                    </button>
                                </form>
                            </div>
                            
                            <!-- CAPTCHA Settings Tab -->
                            <div class="tab-pane fade" id="captcha" role="tabpanel">
                                <form method="POST" class="mt-4">
                                    <input type="hidden" name="csrf_token" value="<?= generateCSRFToken() ?>">
                                    <input type="hidden" name="action" value="update_captcha">
                                    
                                    <div class="row">
                                        <div class="col-lg-8">
                                            <div class="form-check mb-3">
                                                <input class="form-check-input" type="checkbox" id="captcha_enabled" name="captcha_enabled" 
                                                       <?= ($captcha_settings['captcha_enabled'] ?? '1') === '1' ? 'checked' : '' ?>>
                                                <label class="form-check-label" for="captcha_enabled">
                                                    <?= __('enable_captcha') ?>
                                                </label>
                                            </div>
                                            
                                            <div class="mb-3">
                                                <label for="captcha_provider" class="form-label">
                                                    <?= __('captcha_provider') ?>
                                                </label>
                                                <select class="form-select" id="captcha_provider" name="captcha_provider">
                                                    <option value="custom" <?= ($captcha_settings['captcha_provider'] ?? 'custom') === 'custom' ? 'selected' : '' ?>><?= __('custom_math_captcha') ?></option>
                                                    <option value="recaptcha" <?= ($captcha_settings['captcha_provider'] ?? '') === 'recaptcha' ? 'selected' : '' ?>>Google reCAPTCHA v3</option>
                                                    <option value="hcaptcha" <?= ($captcha_settings['captcha_provider'] ?? '') === 'hcaptcha' ? 'selected' : '' ?>>hCaptcha</option>
                                                </select>
                                            </div>
                                            
                                            <!-- reCAPTCHA Settings -->
                                            <div class="captcha-settings" id="recaptcha-settings" style="display: none;">
                                                <h6><?= __('recaptcha_settings') ?></h6>
                                                <div class="mb-3">
                                                    <label for="recaptcha_site_key" class="form-label"><?= __('site_key') ?></label>
                                                    <input type="text" class="form-control" id="recaptcha_site_key" name="recaptcha_site_key" 
                                                           value="<?= htmlspecialchars($captcha_settings['recaptcha_site_key'] ?? '') ?>">
                                                </div>
                                                <div class="mb-3">
                                                    <label for="recaptcha_secret_key" class="form-label"><?= __('secret_key') ?></label>
                                                    <input type="password" class="form-control" id="recaptcha_secret_key" name="recaptcha_secret_key" 
                                                           value="<?= htmlspecialchars($captcha_settings['recaptcha_secret_key'] ?? '') ?>">
                                                </div>
                                            </div>
                                            
                                            <!-- hCaptcha Settings -->
                                            <div class="captcha-settings" id="hcaptcha-settings" style="display: none;">
                                                <h6><?= __('hcaptcha_settings') ?></h6>
                                                <div class="mb-3">
                                                    <label for="hcaptcha_site_key" class="form-label"><?= __('site_key') ?></label>
                                                    <input type="text" class="form-control" id="hcaptcha_site_key" name="hcaptcha_site_key" 
                                                           value="<?= htmlspecialchars($captcha_settings['hcaptcha_site_key'] ?? '') ?>">
                                                </div>
                                                <div class="mb-3">
                                                    <label for="hcaptcha_secret_key" class="form-label"><?= __('secret_key') ?></label>
                                                    <input type="password" class="form-control" id="hcaptcha_secret_key" name="hcaptcha_secret_key" 
                                                           value="<?= htmlspecialchars($captcha_settings['hcaptcha_secret_key'] ?? '') ?>">
                                                </div>
                                            </div>
                                            
                                            <div class="mb-3">
                                                <label for="captcha_protected_forms" class="form-label">
                                                    <?= __('protected_forms') ?>
                                                    <i class="fas fa-question-circle ms-1" data-bs-toggle="tooltip" title="<?= __('help_protected_forms') ?>"></i>
                                                </label>
                                                <input type="text" class="form-control" id="captcha_protected_forms" name="captcha_protected_forms" 
                                                       value="<?= htmlspecialchars($captcha_settings['captcha_protected_forms'] ?? 'login,register,contact,loan_application') ?>">
                                                <div class="form-text"><?= __('protected_forms_help') ?></div>
                                            </div>
                                        </div>
                                        
                                        <div class="col-lg-4">
                                            <div class="card bg-light">
                                                <div class="card-body">
                                                    <h6><?= __('captcha_test') ?></h6>
                                                    <div id="captcha-test-area">
                                                        <?= CaptchaManager::generateHTML('test') ?>
                                                    </div>
                                                    <button type="button" class="btn btn-sm btn-outline-primary mt-2" onclick="testCaptcha()">
                                                        <?= __('test_captcha') ?>
                                                    </button>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                    
                                    <button type="submit" class="btn btn-primary">
                                        <i class="fas fa-save me-2"></i><?= __('save_captcha_settings') ?>
                                    </button>
                                </form>
                            </div>
                            
                            <!-- Backup System Tab -->
                            <div class="tab-pane fade" id="backup" role="tabpanel">
                                <div class="mt-4">
                                    <!-- Backup Statistics -->
                                    <div class="row mb-4">
                                        <div class="col-lg-3 col-md-6 mb-3">
                                            <div class="card bg-primary text-white">
                                                <div class="card-body">
                                                    <div class="d-flex justify-content-between">
                                                        <div>
                                                            <h4 class="mb-0"><?= $backup_statistics['total_backups'] ?? 0 ?></h4>
                                                            <p class="mb-0">Total Backups</p>
                                                        </div>
                                                        <div class="align-self-center">
                                                            <i class="fas fa-archive fa-2x"></i>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                        <div class="col-lg-3 col-md-6 mb-3">
                                            <div class="card bg-success text-white">
                                                <div class="card-body">
                                                    <div class="d-flex justify-content-between">
                                                        <div>
                                                            <h4 class="mb-0"><?= $backup_statistics['current_backups'] ?? 0 ?></h4>
                                                            <p class="mb-0">Current Backups</p>
                                                        </div>
                                                        <div class="align-self-center">
                                                            <i class="fas fa-database fa-2x"></i>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                        <div class="col-lg-3 col-md-6 mb-3">
                                            <div class="card bg-info text-white">
                                                <div class="card-body">
                                                    <div class="d-flex justify-content-between">
                                                        <div>
                                                            <h4 class="mb-0"><?= $backup_statistics['max_retention'] === 0 ? '24h' : $backup_statistics['max_retention'] ?></h4>
                                                            <p class="mb-0">Max Retention</p>
                                                        </div>
                                                        <div class="align-self-center">
                                                            <i class="fas fa-clock fa-2x"></i>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                        <div class="col-lg-3 col-md-6 mb-3">
                                            <div class="card bg-warning text-white">
                                                <div class="card-body">
                                                    <div class="d-flex justify-content-between">
                                                        <div>
                                                            <h4 class="mb-0"><?= isset($backup_statistics['latest_backup']) ? date('M j', strtotime($backup_statistics['latest_backup']['created_at'])) : 'Never' ?></h4>
                                                            <p class="mb-0">Last Backup</p>
                                                        </div>
                                                        <div class="align-self-center">
                                                            <i class="fas fa-calendar fa-2x"></i>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                    
                                    <div class="row">
                                        <!-- Backup Controls -->
                                        <div class="col-lg-6 mb-4">
                                            <div class="card">
                                                <div class="card-header">
                                                    <h5 class="mb-0">
                                                        <i class="fas fa-play-circle me-2"></i><?= __('backup_controls') ?>
                                                    </h5>
                                                </div>
                                                <div class="card-body">
                                                    <div class="mb-3">
                                                        <h6><?= __('create_manual_backup') ?></h6>
                                                        <p class="text-muted"><?= __('create_complete_backup_help') ?></p>
                                                        <form method="POST" class="d-inline" id="backupForm">
                                                            <input type="hidden" name="csrf_token" value="<?= generateCSRFToken() ?>">
                                                            <input type="hidden" name="action" value="create_complete_backup">
                                                            <button type="submit" class="btn btn-primary" id="createBackupBtn">
                                                                <i class="fas fa-download me-2"></i><?= __('create_complete_backup') ?>
                                                            </button>
                                                        </form>
                                                    </div>
                                                    
                                                    <hr>
                                                    
                                                    <div class="mb-3">
                                                        <h6><?= __('backup_includes') ?></h6>
                                                        <ul class="list-unstyled">
                                                            <li><i class="fas fa-check text-success me-2"></i>Complete database dump</li>
                                                            <li><i class="fas fa-check text-success me-2"></i>All project files</li>
                                                            <li><i class="fas fa-check text-success me-2"></i>Configuration files</li>
                                                            <li><i class="fas fa-check text-success me-2"></i>User uploads & documents</li>
                                                            <li><i class="fas fa-check text-success me-2"></i>System logs</li>
                                                            <li><i class="fas fa-check text-success me-2"></i>Automatic compression</li>
                                                        </ul>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                        
                                        <!-- Backup Settings -->
                                        <div class="col-lg-6 mb-4">
                                            <div class="card">
                                                <div class="card-header">
                                                    <h5 class="mb-0">
                                                        <i class="fas fa-cog me-2"></i><?= __('backup_settings') ?>
                                                    </h5>
                                                </div>
                                                <div class="card-body">
                                                    <form method="POST">
                                                        <input type="hidden" name="csrf_token" value="<?= generateCSRFToken() ?>">
                                                        <input type="hidden" name="action" value="update_backup_settings">
                                                        
                                                        <div class="mb-3">
                                                            <label for="backup_max_retention" class="form-label">
                                                                <?= __('max_backups_to_keep') ?>
                                                                <i class="fas fa-question-circle ms-1" data-bs-toggle="tooltip" title="<?= __('help_backup_retention') ?>"></i>
                                                            </label>
                                                            <input type="number" class="form-control" id="backup_max_retention" name="backup_max_retention" 
                                                                   value="<?= htmlspecialchars($backup_statistics['max_retention'] ?? 4) ?>" min="0" max="50">
                                                            <div class="form-text">
                                                                <?= __('backup_retention_help') ?>
                                                            </div>
                                                        </div>
                                                        
                                                        <div class="form-check mb-3">
                                                            <input class="form-check-input" type="checkbox" id="backup_email_notifications" name="backup_email_notifications" 
                                                                   <?= ($backup_statistics['email_notifications'] ?? false) ? 'checked' : '' ?>>
                                                            <label class="form-check-label" for="backup_email_notifications">
                                                                <?= __('send_email_notifications') ?>
                                                                <i class="fas fa-question-circle ms-1" data-bs-toggle="tooltip" title="<?= __('help_backup_emails') ?>"></i>
                                                            </label>
                                                        </div>
                                                        
                                                        <div class="form-check mb-3">
                                                            <input class="form-check-input" type="checkbox" id="backup_weekly_schedule" name="backup_weekly_schedule" 
                                                                   <?= ($backup_statistics['weekly_schedule'] ?? false) ? 'checked' : '' ?>>
                                                            <label class="form-check-label" for="backup_weekly_schedule">
                                                                <?= __('enable_weekly_backups') ?>
                                                                <i class="fas fa-question-circle ms-1" data-bs-toggle="tooltip" title="<?= __('help_weekly_backups') ?>"></i>
                                                            </label>
                                                        </div>
                                                        
                                                        <div class="row" id="scheduleSettings" style="display: <?= ($backup_statistics['weekly_schedule'] ?? false) ? 'block' : 'none' ?>">
                                                            <div class="col-md-6 mb-3">
                                                                <label for="backup_schedule_day" class="form-label"><?= __('backup_day') ?></label>
                                                                <select class="form-select" id="backup_schedule_day" name="backup_schedule_day">
                                                                    <?php 
                                                                    $days = ['sunday', 'monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday'];
                                                                    $current_day = getSystemSetting('backup_schedule_day', 'sunday');
                                                                    foreach ($days as $day): 
                                                                    ?>
                                                                        <option value="<?= $day ?>" <?= $current_day === $day ? 'selected' : '' ?>>
                                                                            <?= __(ucfirst($day)) ?>
                                                                        </option>
                                                                    <?php endforeach; ?>
                                                                </select>
                                                            </div>
                                                            <div class="col-md-6 mb-3">
                                                                <label for="backup_schedule_time" class="form-label"><?= __('backup_time') ?></label>
                                                                <input type="time" class="form-control" id="backup_schedule_time" name="backup_schedule_time" 
                                                                       value="<?= htmlspecialchars(getSystemSetting('backup_schedule_time', '02:00')) ?>">
                                                            </div>
                                                        </div>
                                                        
                                                        <button type="submit" class="btn btn-success">
                                                            <i class="fas fa-save me-2"></i><?= __('save_backup_settings') ?>
                                                        </button>
                                                    </form>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                    
                                    <!-- Current Backups -->
                                    <div class="row">
                                        <div class="col-12">
                                            <div class="card">
                                                <div class="card-header">
                                                    <h5 class="mb-0">
                                                        <i class="fas fa-list me-2"></i><?= __('current_backups') ?>
                                                    </h5>
                                                </div>
                                                <div class="card-body">
                                                    <?php if (!empty($current_backups)): ?>
                                                        <div class="table-responsive">
                                                            <table class="table table-hover">
                                                                <thead>
                                                                    <tr>
                                                                        <th><?= __('backup_name') ?></th>
                                                                        <th><?= __('type') ?></th>
                                                                        <th><?= __('size') ?></th>
                                                                        <th><?= __('created') ?></th>
                                                                        <th><?= __('actions') ?></th>
                                                                    </tr>
                                                                </thead>
                                                                <tbody>
                                                                    <?php foreach ($current_backups as $backup): ?>
                                                                        <tr>
                                                                            <td>
                                                                                <i class="fas fa-<?= $backup['type'] === 'zip' ? 'file-archive' : 'folder' ?> me-2"></i>
                                                                                <?= htmlspecialchars($backup['name']) ?>
                                                                            </td>
                                                                            <td>
                                                                                <span class="badge bg-<?= $backup['type'] === 'zip' ? 'primary' : 'secondary' ?>">
                                                                                    <?= ucfirst($backup['type']) ?>
                                                                                </span>
                                                                            </td>
                                                                            <td><?= formatBytes($backup['size']) ?></td>
                                                                            <td><?= date('Y-m-d H:i:s', $backup['created']) ?></td>
                                                                            <td>
                                                                                <button class="btn btn-sm btn-outline-danger" onclick="confirmDeleteBackup('<?= htmlspecialchars($backup['name']) ?>')">
                                                                                    <i class="fas fa-trash"></i>
                                                                                </button>
                                                                            </td>
                                                                        </tr>
                                                                    <?php endforeach; ?>
                                                                </tbody>
                                                            </table>
                                                        </div>
                                                    <?php else: ?>
                                                        <div class="text-center py-4">
                                                            <i class="fas fa-database fa-3x text-muted mb-3"></i>
                                                            <p class="text-muted"><?= __('no_backups_found') ?></p>
                                                            <p class="text-muted"><?= __('create_first_backup_help') ?></p>
                                                        </div>
                                                    <?php endif; ?>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            
                            <!-- System Info Tab -->
                            <div class="tab-pane fade" id="system" role="tabpanel">
                                <div class="mt-4">
                                    <div class="row">
                                        <div class="col-lg-6">
                                            <h5><?= __('system_information') ?></h5>
                                            <table class="table table-striped">
                                                <tr>
                                                    <td><?= __('php_version') ?>:</td>
                                                    <td><?= htmlspecialchars($system_info['php_version']) ?></td>
                                                </tr>
                                                <tr>
                                                    <td><?= __('server_software') ?>:</td>
                                                    <td><?= htmlspecialchars($system_info['server_software']) ?></td>
                                                </tr>
                                                <tr>
                                                    <td><?= __('mysql_version') ?>:</td>
                                                    <td><?= htmlspecialchars($system_info['mysql_version']) ?></td>
                                                </tr>
                                                <tr>
                                                    <td><?= __('max_upload_size') ?>:</td>
                                                    <td><?= htmlspecialchars($system_info['max_upload_size']) ?></td>
                                                </tr>
                                                <tr>
                                                    <td><?= __('memory_limit') ?>:</td>
                                                    <td><?= htmlspecialchars($system_info['memory_limit']) ?></td>
                                                </tr>
                                                <tr>
                                                    <td><?= __('disk_space') ?>:</td>
                                                    <td><?= htmlspecialchars($system_info['disk_space']) ?></td>
                                                </tr>
                                            </table>
                                        </div>
                                        
                                        <div class="col-lg-6">
                                            <h5><?= __('system_maintenance') ?></h5>
                                            
                                            <div class="card mb-3">
                                                <div class="card-body">
                                                    <h6><?= __('database_backup') ?></h6>
                                                    <p class="text-muted"><?= __('backup_database_help') ?></p>
                                                    <form method="POST" class="d-inline">
                                                        <input type="hidden" name="csrf_token" value="<?= generateCSRFToken() ?>">
                                                        <input type="hidden" name="action" value="backup_database">
                                                        <button type="submit" class="btn btn-outline-primary">
                                                            <i class="fas fa-download me-2"></i><?= __('create_backup') ?>
                                                        </button>
                                                    </form>
                                                </div>
                                            </div>
                                            
                                            <div class="card mb-3">
                                                <div class="card-body">
                                                    <h6><?= __('clear_cache') ?></h6>
                                                    <p class="text-muted"><?= __('clear_cache_help') ?></p>
                                                    <form method="POST" class="d-inline">
                                                        <input type="hidden" name="csrf_token" value="<?= generateCSRFToken() ?>">
                                                        <input type="hidden" name="action" value="clear_cache">
                                                        <button type="submit" class="btn btn-outline-warning">
                                                            <i class="fas fa-broom me-2"></i><?= __('clear_cache') ?>
                                                        </button>
                                                    </form>
                                                </div>
                                            </div>
                                            
                                            <div class="card">
                                                <div class="card-body">
                                                    <h6><?= __('system_logs') ?></h6>
                                                    <p class="text-muted"><?= __('system_logs_help') ?></p>
                                                    <a href="logs.php" class="btn btn-outline-info">
                                                        <i class="fas fa-file-alt me-2"></i><?= __('view_logs') ?>
                                                    </a>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Initialize tooltips
        var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
        var tooltipList = tooltipTriggerList.map(function (tooltipTriggerEl) {
            return new bootstrap.Tooltip(tooltipTriggerEl);
        });
        
        // CAPTCHA provider switching
        document.getElementById('captcha_provider').addEventListener('change', function() {
            document.querySelectorAll('.captcha-settings').forEach(el => el.style.display = 'none');
            const selected = this.value;
            if (selected !== 'custom') {
                const settingsDiv = document.getElementById(selected + '-settings');
                if (settingsDiv) {
                    settingsDiv.style.display = 'block';
                }
            }
        });
        
        // Initialize CAPTCHA settings visibility
        document.getElementById('captcha_provider').dispatchEvent(new Event('change'));
        
        // Test email function
        function sendTestEmail() {
            const email = document.getElementById('test_email').value;
            if (!email) {
                alert('<?= __('please_enter_test_email') ?>');
                return;
            }
            
            const form = document.createElement('form');
            form.method = 'POST';
            form.style.display = 'none';
            
            const csrfToken = document.createElement('input');
            csrfToken.type = 'hidden';
            csrfToken.name = 'csrf_token';
            csrfToken.value = '<?= generateCSRFToken() ?>';
            
            const action = document.createElement('input');
            action.type = 'hidden';
            action.name = 'action';
            action.value = 'test_email';
            
            const emailInput = document.createElement('input');
            emailInput.type = 'hidden';
            emailInput.name = 'test_email';
            emailInput.value = email;
            
            form.appendChild(csrfToken);
            form.appendChild(action);
            form.appendChild(emailInput);
            
            document.body.appendChild(form);
            form.submit();
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
        
        // Backup system functionality
        document.addEventListener('DOMContentLoaded', function() {
            // Weekly backup schedule toggle
            const weeklyScheduleCheckbox = document.getElementById('backup_weekly_schedule');
            const scheduleSettings = document.getElementById('scheduleSettings');
            
            if (weeklyScheduleCheckbox && scheduleSettings) {
                weeklyScheduleCheckbox.addEventListener('change', function() {
                    scheduleSettings.style.display = this.checked ? 'block' : 'none';
                });
            }
            
            // Backup form submission
            const backupForm = document.getElementById('backupForm');
            const createBackupBtn = document.getElementById('createBackupBtn');
            
            if (backupForm && createBackupBtn) {
                backupForm.addEventListener('submit', function(e) {
                    createBackupBtn.disabled = true;
                    createBackupBtn.innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i>Creating Backup...';
                    
                    // Show progress notification
                    showNotification('info', 'Backup process started. This may take several minutes...');
                });
            }
            
            // Real-time backup retention help
            const retentionInput = document.getElementById('backup_max_retention');
            if (retentionInput) {
                retentionInput.addEventListener('input', function() {
                    const value = parseInt(this.value);
                    const helpText = this.parentNode.querySelector('.form-text');
                    
                    if (value === 0) {
                        helpText.innerHTML = '<strong>Special Mode:</strong> Backups will be deleted after 24 hours (minimum retention time).';
                        helpText.className = 'form-text text-warning';
                    } else if (value === 1) {
                        helpText.innerHTML = 'Only the most recent backup will be kept. Older backups will be automatically removed.';
                        helpText.className = 'form-text text-info';
                    } else {
                        helpText.innerHTML = `The system will keep the ${value} most recent backups. Older backups will be automatically removed.`;
                        helpText.className = 'form-text text-muted';
                    }
                });
            }
        });
        
        // Confirm backup deletion
        function confirmDeleteBackup(backupName) {
            if (confirm('Are you sure you want to delete the backup "' + backupName + '"? This action cannot be undone.')) {
                // Create form to delete backup
                const form = document.createElement('form');
                form.method = 'POST';
                form.innerHTML = `
                    <input type="hidden" name="csrf_token" value="<?= generateCSRFToken() ?>">
                    <input type="hidden" name="action" value="delete_backup">
                    <input type="hidden" name="backup_name" value="${backupName}">
                `;
                document.body.appendChild(form);
                form.submit();
            }
        }
        
        // Show notification helper
        function showNotification(type, message) {
            const alertClass = type === 'success' ? 'alert-success' : 
                             type === 'error' ? 'alert-danger' : 
                             type === 'warning' ? 'alert-warning' : 'alert-info';
            
            const notification = document.createElement('div');
            notification.className = `alert ${alertClass} alert-dismissible fade show position-fixed`;
            notification.style.cssText = 'top: 20px; right: 20px; z-index: 9999; min-width: 300px;';
            notification.innerHTML = `
                ${message}
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            `;
            
            document.body.appendChild(notification);
            
            // Auto-remove after 5 seconds
            setTimeout(() => {
                if (notification.parentNode) {
                    notification.remove();
                }
            }, 5000);
        }
    </script>
</body>
</html>
